<?php
ob_start();
require_once 'config.inc.php';
require_once 'dbHandler.inc.php';
require_once 'ticket-chat-page-functions.inc.php';

if (!isset($_SESSION['userid'])) {
    header("Location: signin.php");
    exit();
}

$logged_in_user_id = $_SESSION['userid'];

$userQuery = "SELECT profile_image FROM users WHERE user_ID = ?";
$stmt = mysqli_prepare($conn, $userQuery);
mysqli_stmt_bind_param($stmt, "i", $logged_in_user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$profileImage = $user['profile_image'] ?? null;


require_once 'notification.inc.php';

// URL'den ticket_id yakalama 
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $ticket_id = intval($_GET['id']);
} else {
    header("Location: customerTicket.php");
    exit();
}

// Session'da CSRF Token yoksa güvenli bir şekilde oluştur
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CSRF Token kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            ob_end_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token! Request denied.']);
            exit();
        }
        die("Hata: Geçersiz CSRF Token. Güvenlik ihlali şüphesi.");
    }
}

// MESAJ GÖNDERME (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message_btn'])) {
    $message = trim($_POST['chat_message'] ?? '');
    $filePath = null;

    if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['chat_file']['tmp_name'];
        $fileName = $_FILES['chat_file']['name'];
        $fileSize = $_FILES['chat_file']['size'];
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'zip'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $maxFileSize = 5 * 1024 * 1024; 

        if (!in_array($fileExtension, $allowedExtensions)) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                ob_end_clean();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'message' => 'Invalid file format! Only JPG, PNG, PDF, DOCX, and ZIP allowed.']);
                exit();
            }
        } elseif ($fileSize > $maxFileSize) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                ob_end_clean();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'message' => 'File size exceeds the limit (Max: 5MB)!']);
                exit();
            }
        } else {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $newFileName = time() . '_' . md5(uniqid()) . '.' . $fileExtension;
            $targetFilePath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $targetFilePath)) {
                $filePath = 'uploads/' . $newFileName;
            }
        }
    }

    if (!empty($message) || $filePath !== null) {
        $inserted_id = sendMessage($conn, $ticket_id, $logged_in_user_id, $message, $filePath);
        
        if ($inserted_id) {
            $notifMsg = "Customer sent a message on Ticket #{$ticket_id}.";

            // 1. Bilete atanmış personele bildirim at (Kolon adı kontrolüyle)
            $tQuery = mysqli_query($conn, "SELECT * FROM tickets WHERE ticket_ID = $ticket_id");
            if ($tRow = mysqli_fetch_assoc($tQuery)) {
                $assigned_emp_id = $tRow['assigned_employee_ID'] ?? $tRow['employee_ID'] ?? $tRow['employee_id'] ?? null;
                if (!empty($assigned_emp_id)) {
                    createNotification($conn, $assigned_emp_id, 'message', $notifMsg);
                }
            }

            // 2. Sistemdeki Admin kullanıcılara bildirim at (role_ID = 1)
            $adminQuery = mysqli_query($conn, "SELECT user_ID FROM users WHERE role_ID = 1");
            if ($adminQuery) {
                while ($adminRow = mysqli_fetch_assoc($adminQuery)) {
                    if (empty($assigned_emp_id) || $adminRow['user_ID'] != $assigned_emp_id) {
                        createNotification($conn, $adminRow['user_ID'], 'message', $notifMsg);
                    }
                }
            }

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                $userQuery = mysqli_query($conn, "SELECT username, role_ID FROM users WHERE user_ID = $logged_in_user_id");
                $userData = mysqli_fetch_assoc($userQuery);
                
                ob_end_clean();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'status' => 'success',
                    'chat_id' => $inserted_id,
                    'sender' => htmlspecialchars($userData['username'] ?? 'Customer'),
                    'time' => date("d.m.Y - H:i"),
                    'message' => nl2br(htmlspecialchars($message)),
                    'file_url' => $filePath, 
                    'message_class' => 'sent'
                ]);
                exit();
            }

            header("Location: ticket-chat-page-customer.php?id=" . $ticket_id);
            exit();
        }
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            ob_end_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Please enter a message or attach a file!']);
            exit();
        }
    }
}

// Bilet Detayları
$ticket = getTicketDetails($conn, $ticket_id);
$messages = getTicketMessages($conn, $ticket_id);

if (!$ticket) {
    die("Ticket not found!");
}

$status = strtolower(trim($ticket['ticket_status']));
$currentStep = 1;
if ($status === 'in progress') $currentStep = 2;
elseif ($status === 'resolved') $currentStep = 3;
elseif ($status === 'closed') $currentStep = 4;

// Anlık Yeni Mesaj, Durum ve Bildirim Kontrolü (AJAX Polling)
if (isset($_GET['fetch_new_messages']) && $_GET['fetch_new_messages'] == 1) {
    $last_msg_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

    $sql = "SELECT c.*, u.username, u.role_ID 
            FROM chat c 
            INNER JOIN users u ON c.user_ID = u.user_ID 
            WHERE c.ticket_ID = ? AND c.chat_ID > ? 
            ORDER BY c.chat_created_at ASC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $ticket_id, $last_msg_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $newMessages = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $notifSql = "SELECT * FROM notifications WHERE user_ID = " . intval($logged_in_user_id) . " ORDER BY created_at DESC LIMIT 10";
    $notifResult = mysqli_query($conn, $notifSql);
    $formattedNotifications = [];
    if ($notifResult) {
        while ($n = mysqli_fetch_assoc($notifResult)) {
            $formattedNotifications[] = [
                'id' => $n['notification_ID'],
                'type' => $n['notification_type'],
                'message' => htmlspecialchars($n['notification_message']),
                'date' => date("d.m.Y H:i", strtotime($n['created_at']))
            ];
        }
    }

    $formattedMessages = [];
    foreach ($newMessages as $msg) {
        $isCustomer = ($msg['role_ID'] == 3);
        $senderName = !empty($msg['username']) ? $msg['username'] : ($isCustomer ? 'Customer' : 'Customer Support');
        
        $formattedMessages[] = [
            'chat_id' => $msg['chat_ID'],
            'sender' => htmlspecialchars($senderName),
            'time' => date("d.m.Y - H:i", strtotime($msg['chat_created_at'])),
            'message' => nl2br(htmlspecialchars($msg['chat_message'] ?? '')),
            'file_url' => $msg['chat_attachment_file'],
            'message_class' => $isCustomer ? 'sent' : 'received'
        ];
    }

    $ticketCheck = mysqli_query($conn, "SELECT ticket_status FROM tickets WHERE ticket_ID = $ticket_id");
    $ticketCheckRow = mysqli_fetch_assoc($ticketCheck);
    $freshStatus = $ticketCheckRow['ticket_status'] ?? 'Open';

    $statusLower = strtolower(trim($freshStatus));
    $step = 1;
    if ($statusLower === 'in progress') $step = 2;
    elseif ($statusLower === 'resolved') $step = 3;
    elseif ($statusLower === 'closed') $step = 4;

    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success', 
        'ticket_status' => htmlspecialchars($freshStatus),
        'current_step' => $step,
        'messages' => $formattedMessages,
        'notifications' => $formattedNotifications
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="ticket-chat-page-style.css">
    <link rel="stylesheet" href="admin_sidebar.css">
    <script src="https://kit.fontawesome.com/70fc0809a2.js" crossorigin="anonymous"></script>
    <title>Customer Ticket Page</title>
</head>

<body class="dashboard-body">
    <!-- 3 Saniyelik Anlık Toast Bildirim Balonu -->
    <div id="chat-notification" style="display: none; position: fixed; top: 20px; right: 20px; padding: 12px 20px; border-radius: 6px; color: #fff; z-index: 9999; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.15);"></div>

    <div class="dashboard-container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-top">
                <div class="logo-header">
                    <img src="Images/GlowUpLogo_Ellipse_Background.png" class="brand-logo" alt="Glow Up Logo">
                    <h1>Glow Up</h1>
                </div>
                <span class="eyebrow">TICKETING & SERVICE SYSTEM</span>

                <nav class="nav-menu">
                    <span class="menu-title">GENERAL</span>
                    <a href="customerTicket.php" class="menu-link active"><i class="bi bi-grid-fill"></i> Dashboard</a>
                    <a href="customerCreateTicket.php" class="menu-link"><i class="bi bi-ticket-perforated"></i> Tickets</a>
                    <span class="menu-title mt-4">ACCOUNT</span>
                    <a href="updateCustomerProfile.php" class="menu-link"> <i class="bi bi-person fs-5"></i> Profile</a>
                    <a href="logout.inc.php" class="menu-link"><i class="bi bi-box-arrow-left"></i> Sign Out</a>
                </nav>
            </div>
        </aside>

       

        <div class="ticketTable">

            <div class="topDashboard">
                <div class="PageTitle">
                    <h1>Ticket Details</h1>
                    <small>Ticket History of #<?php echo htmlspecialchars($ticket['ticket_ID'] ?? $ticket['ticket_id']); ?></small>
                </div>
                <span class="topbar-profile">
                    <span class="notification">
                        <i id="notification-icon" class="fa-regular fa-bell"></i>
                        <?php if(mysqli_num_rows($notification) > 0): ?>
                            <span class="notification-dot"></span>
                        <?php endif; ?>
                    </span>

                    <img 
                        id="topImg"
                        class="topbar-image" 
                        src="<?= !empty($profileImage)
                            ? htmlspecialchars($profileImage)
                            : 'Images/maleUser.png'; ?>"
                        alt="profile Image">
                </span>
            </div>

            <div class="content">

                <button class="backBtn all-card-btn" type="button" onclick="window.location.href='customerTicket.php'">
                    <i class="fa-solid fa-chevron-left"></i> Back to Ticket List
                </button>

                <!-- Notification Drawer -->
                <div class="notification-container">
                    <header class="notification-head">
                        <h2 class="notification-title">Notifications</h2>
                        <button class="close-notification-sidebar" type="button"><i class="fa-solid fa-xmark"></i></button>
                    </header><hr>
                    <div class="scrollable-container">
                        <ul class="scrollableList-notification">
                            <?php if(mysqli_num_rows($notification)> 0): ?>
                                <?php while($row = mysqli_fetch_assoc($notification)): ?>
                                    <li class="notification-inputs" data-id="<?= $row['notification_ID'] ?>">
                                        <div class="notification-icon">
                                            <?php if ($row['notification_type'] == 'profile_update'): ?>
                                                <i class="fa-solid fa-user-pen"></i>
                                            <?php elseif ($row['notification_type'] == 'message'): ?>
                                                <i class="fa-solid fa-message"></i>
                                            <?php elseif ($row['notification_type'] == 'ticket'): ?>
                                                <i class="fa-solid fa-ticket"></i>
                                            <?php else: ?>
                                                <i class="fa-solid fa-bell"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notification-content">
                                            <span class="notification-type"><?= htmlspecialchars($row['notification_type']) ?></span>
                                            <span class="notification-message"><?= htmlspecialchars($row['notification_message']) ?></span>
                                            <span class="notification-date"><?= htmlspecialchars($row['created_at']) ?></span>
                                        </div>
                                    </li>
                                <?php endwhile; ?> 
                            <?php else:?>
                                <li id="notFound">
                                    <i class="fa-regular fa-bell-slash"></i>
                                    <span>No notifications</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div> 

                <div class="ticketCard">

                    <div class="ticketHeader">
                        <div class="ticketTitle">
                            <small class="ticketCode">
                                #<?php echo htmlspecialchars($ticket['ticket_ID'] ?? $ticket['ticket_id']); ?> - <?php echo htmlspecialchars($ticket['department_name'] ?? ''); ?>
                            </small>
                            <h2><?php echo htmlspecialchars($ticket['ticket_title'] ?? ''); ?></h2>
                            <div class="ticketInfo">
                                <div class="ticketTags">
                                    <span class="priority all-card-btn">
                                        <?php echo htmlspecialchars($ticket['ticket_status'] ?? ''); ?>
                                    </span>
                                    <span class="department all-card-btn">
                                        <?php echo htmlspecialchars($ticket['ticket_priority'] ?? ''); ?> Priority
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                            
                    <div class="ticketTimeline">
                        <div class="step <?php if ($currentStep == 1) echo 'current';elseif ($currentStep > 1) echo 'active';?>">
                            <div class="circle"></div>
                            <p>Open</p>
                        </div>
                        <div class="line <?php echo ($currentStep > 1) ? 'active' : ''; ?>"></div>
                        <div class="step <?php if ($currentStep == 2) echo 'current';elseif ($currentStep > 2) echo 'active';?>">
                            <div class="circle"></div>
                            <p>In Progress</p>
                        </div>
                        <div class="line <?php echo ($currentStep > 2) ? 'active' : ''; ?>"></div>
                        <div class="step <?php if ($currentStep == 3) echo 'current';elseif ($currentStep > 3) echo 'active';?>">
                            <div class="circle"></div>
                            <p>Resolved</p>
                        </div>
                        <div class="line <?php echo ($currentStep > 3) ? 'active' : ''; ?>"></div>
                        <div class="step <?php if ($currentStep == 4) echo 'current';elseif ($currentStep > 4) echo 'active';?>">
                            <div class="circle"></div>
                            <p>Closed</p>
                        </div>
                    </div>

                </div>

                <div class="ticketContent">

                    <div class="chatSection all-card-btn">
                        <div class="cardHeader">
                            <h3>Chat History</h3>
                        </div>

                        <div class="chatBody">
                            <?php if (!empty($messages)): ?>
                                <?php foreach ($messages as $msg): ?>
                                <?php 
                                    $isCustomer = ($msg['role_ID'] == 3);
                                    $messageClass = $isCustomer ? 'sent' : 'received';
                                    $senderName = !empty($msg['username']) ? $msg['username'] : ($isCustomer ? 'Customer' : 'Customer Support');
                                ?>
                                <div class="message <?php echo $messageClass; ?>" data-id="<?php echo $msg['chat_ID']; ?>">
                                    <div class="messageHeader">
                                        <strong><?php echo htmlspecialchars($senderName); ?></strong>
                                        <small><?php echo date("d.m.Y - H:i", strtotime($msg['chat_created_at'])); ?></small>
                                    </div>
                                    <div class="messageContent">
                                        <?php echo nl2br(htmlspecialchars($msg['chat_message'])); ?>
                                        <?php if (!empty($msg['chat_attachment_file'])): ?>
                                            <br>
                                            <a href="<?php echo htmlspecialchars($msg['chat_attachment_file']); ?>" target="_blank" class="chat-attachment" style="display: inline-block; margin-top: 5px; color: #007bff; text-decoration: underline;">
                                                <i class="fa-solid fa-paperclip"></i> View Attachment
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="padding: 15px; color: #666;">There are no messages yet.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Form elementini tamamen kontrollü div/form yapısına aldık -->
                        <form class="chatInput" id="customerChatForm" onsubmit="event.preventDefault(); return false;">
                            <input type="hidden" name="csrf_token" id="customerCsrfToken" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <textarea name="chat_message" id="customerChatMessage" placeholder="Type your message..." rows="2"></textarea> 
                            <input type="file" name="chat_file" id="chatFileInput" style="display: none;">

                            <div class="chatActions">
                                <button type="button" class="attachBtn" onclick="$('#chatFileInput').click();">
                                    <i class="fa-solid fa-paperclip"></i> Attach File
                                </button>
                                <span id="selectedFileName" style="font-size: 12px; color: #555; margin-left: 8px;"></span>
                                <button type="button" id="customerSendBtn" class="sendBtn">
                                    <i class="fa-solid fa-paper-plane"></i> Send
                                </button>
                            </div>
                        </form>

                    </div>

                    <div class="sideSection">
                        <div class="infoCard all-card-btn">
                            <div class="cardHeader">
                                <h3>Ticket Information</h3>
                            </div>
                            <div class="infoBody">
                                <div class="infoRow">
                                    <span class="infoLabel">Ticket ID</span>
                                    <span class="infoValue">#<?php echo htmlspecialchars($ticket['ticket_ID'] ?? $ticket['ticket_id']); ?></span>
                                </div>
                                <div class="infoRow">
                                    <span class="infoLabel">Priority</span>
                                    <span class="infoValue"><?php echo htmlspecialchars($ticket['ticket_priority'] ?? ''); ?></span>
                                </div>
                                <div class="infoRow">
                                    <span class="infoLabel">Department</span>
                                    <span class="infoValue"><?php echo htmlspecialchars($ticket['department_name'] ?? ''); ?></span>
                                </div>
                                <div class="infoRow">
                                    <span class="infoLabel">Assigned To</span>
                                    <span class="infoValue"><?php echo htmlspecialchars($ticket['employee_name'] ?? 'Unassigned'); ?></span>
                                </div>
                                <div class="infoRow">
                                    <span class="infoLabel">Created Date</span>
                                    <span class="infoValue"><?php echo date("d.m.Y", strtotime($ticket['ticket_created_at'] ?? 'now')); ?></span>
                                </div>
                                <div class="infoRow">
                                    <span class="infoLabel">Created By</span>
                                    <span class="infoValue"><?php echo htmlspecialchars($ticket['customer_name'] ?? ''); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
</script>
    <script src="common.js"></script>  
    <script src="updateProfile.js"></script>

    <script>
    $(document).ready(function() {

        if ($('.chatBody').length > 0) {
            $('.chatBody').scrollTop($('.chatBody')[0].scrollHeight);
        }

        // 3 Saniyelik Toast Bildirim Fonksiyonu
        function showNotification(text, type) {
            var bgColor = (type === 'success') ? '#28a745' : '#dc3545';
            $('#chat-notification')
                .css('background-color', bgColor)
                .text(text)
                .stop(true, true)
                .fadeIn();

            setTimeout(function() {
                $('#chat-notification').fadeOut();
            }, 3000);
        }

        $('#chatFileInput').on('change', function() {
            var fileName = this.files[0] ? this.files[0].name : '';
            $('#selectedFileName').text(fileName);
        });

        // Güvenli Mesaj Gönderme Fonksiyonu (Customer)
        function submitCustomerMessage() {
            var $textarea = $('#customerChatMessage');
            var messageText = $.trim($textarea.val());
            var fileInput = $('#chatFileInput')[0].files[0];
            var csrfToken = $('#customerCsrfToken').val();
            var $sendBtn = $('#customerSendBtn');

            if ($sendBtn.prop('disabled')) return false;

            if (messageText === "" && !fileInput) {
                showNotification("Please enter a message or attach a file!", "error");
                $textarea.focus();
                return false;
            }

            $sendBtn.prop('disabled', true);

            var formData = new FormData();
            formData.append('send_message_btn', '1');
            formData.append('chat_message', messageText);
            formData.append('csrf_token', csrfToken);

            if (fileInput) {
                formData.append('chat_file', fileInput);
            }

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    $sendBtn.prop('disabled', false);

                    if (response.status === 'error') {
                        showNotification(response.message, "error");
                        return;
                    }

                    if (response.status === 'success') {
                        $('.chatBody p').remove();

                        var fileHtml = response.file_url 
                            ? `<br><a href="${response.file_url}" target="_blank" class="chat-attachment" style="display:inline-block; margin-top:5px; color:#007bff;"><i class="fa-solid fa-paperclip"></i> View Attachment</a>` 
                            : '';

                        if ($('.message[data-id="' + response.chat_id + '"]').length === 0) {
                            var newMessageHtml = `
                                <div class="message ${response.message_class}" data-id="${response.chat_id}">
                                    <div class="messageHeader">
                                        <strong>${response.sender}</strong>
                                        <small>${response.time}</small>
                                    </div>
                                    <div class="messageContent">
                                        ${response.message}
                                        ${fileHtml}
                                    </div>
                                </div>
                            `;
                            $('.chatBody').append(newMessageHtml);
                        }

                        $('.chatBody').scrollTop($('.chatBody')[0].scrollHeight);

                        // --- ALANLARI TEMİZLE ---
                        $textarea.val('');
                        document.getElementById('chatFileInput').value = '';
                        $('#selectedFileName').text('');

                        showNotification("Message sent.", "success");
                    }
                },
                error: function(xhr) {
                    $sendBtn.prop('disabled', false);
                    console.error("AJAX Hatası:", xhr.status, xhr.statusText);
                    showNotification("An error occurred while sending the message!", "error");
                }
            });
            return false;
        }

        // Tıklama Olayı (Event propagation durduruldu)
        $(document).off('click', '#customerSendBtn').on('click', '#customerSendBtn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            submitCustomerMessage();
        });

        // Form Submit'i Tamamen Engelleyin
        $('#customerChatForm').on('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            submitCustomerMessage();
            return false;
        });

    });

    // Her 3 Saniyede Bir Yeni Mesaj, Durum ve Bildirim Kontrolü (Polling)
    setInterval(function() {
        var lastMsgId = 0;
        var $lastMsg = $('.chatBody .message').last();
        if ($lastMsg.length > 0 && $lastMsg.attr('data-id')) {
            lastMsgId = parseInt($lastMsg.attr('data-id'), 10);
        }

        $.ajax({
            url: window.location.href,
            type: 'GET',
            data: { 
                fetch_new_messages: 1,
                last_id: lastMsgId
            },
            dataType: 'json',
            success: function(response) {

                // 1. Durum Değişikliği ve Timeline Güncellemesi (3 sn Toast)
                if (response.ticket_status) {
                    var currentStatusOnPage = $('.ticketTags .priority').text().trim();
                    if (currentStatusOnPage !== "" && currentStatusOnPage !== response.ticket_status) {
                        
                        $('.ticketTags .priority').text(response.ticket_status);

                        $('.ticketTimeline .step').removeClass('current active');
                        $('.ticketTimeline .line').removeClass('active');

                        var step = response.current_step;
                        $('.ticketTimeline .step').each(function(index) {
                            var stepNum = index + 1;
                            if (stepNum === step) $(this).addClass('current');
                            else if (stepNum < step) $(this).addClass('active');
                        });

                        $('.ticketTimeline .line').each(function(index) {
                            if ((index + 1) < step) $(this).addClass('active');
                        });

                        var bgColor = '#28a745';
                        $('#chat-notification')
                            .css('background-color', bgColor)
                            .text("Ticket status updated to '" + response.ticket_status + "'.")
                            .stop(true, true)
                            .fadeIn();
                        setTimeout(function() { $('#chat-notification').fadeOut(); }, 3000);
                    }
                }

                // 2. Kalıcı Bildirim Kartları ve Zil Kırmızı Noktası
                if (response.notifications && response.notifications.length > 0) {
                    if ($('.notification-dot').length === 0) {
                        $('.notification').append('<span class="notification-dot"></span>');
                    }

                    $('#notFound').remove();

                    $.each(response.notifications, function(index, notif) {
                        if ($('.notification-inputs[data-id="' + notif.id + '"]').length === 0) {
                            var iconHtml = '<i class="fa-solid fa-bell"></i>';
                            if (notif.type === 'message') iconHtml = '<i class="fa-solid fa-message"></i>';
                            else if (notif.type === 'ticket') iconHtml = '<i class="fa-solid fa-ticket"></i>';
                            else if (notif.type === 'profile_update') iconHtml = '<i class="fa-solid fa-user-pen"></i>';

                            var notifCardHtml = `
                                <li class="notification-inputs" data-id="${notif.id}">
                                    <div class="notification-icon">${iconHtml}</div>
                                    <div class="notification-content">
                                        <span class="notification-type">${notif.type}</span>
                                        <span class="notification-message">${notif.message}</span>
                                        <span class="notification-date">${notif.date}</span>
                                    </div>
                                </li>
                            `;
                            $('.scrollableList-notification').prepend(notifCardHtml);
                        }
                    });
                }

                // 3. Yeni Mesajları Canlı Basma & Gelen Mesaj Toast Bildirimi
                if (response.status === 'success' && response.messages && response.messages.length > 0) {
                    $('.chatBody p').remove();
                    var hasIncomingMessage = false;

                    $.each(response.messages, function(index, msg) {
                        if ($('.message[data-id="' + msg.chat_id + '"]').length === 0) {
                            var fileHtml = msg.file_url 
                                ? `<br><a href="${msg.file_url}" target="_blank" class="chat-attachment" style="display:inline-block; margin-top:5px; color:#007bff;"><i class="fa-solid fa-paperclip"></i> View Attachment</a>` 
                                : '';

                            var newMessageHtml = `
                                <div class="message ${msg.message_class}" data-id="${msg.chat_id}">
                                    <div class="messageHeader">
                                        <strong>${msg.sender}</strong>
                                        <small>${msg.time}</small>
                                    </div>
                                    <div class="messageContent">
                                        ${msg.message}
                                        ${fileHtml}
                                    </div>
                                </div>
                            `;

                            $('.chatBody').append(newMessageHtml);

                            if (msg.message_class === 'received') {
                                hasIncomingMessage = true;
                            }
                        }
                    });

                    $('.chatBody').scrollTop($('.chatBody')[0].scrollHeight);

                    if (hasIncomingMessage) {
                        var bgColor = '#28a745';
                        $('#chat-notification')
                            .css('background-color', bgColor)
                            .text("New message received from Customer Support!")
                            .stop(true, true)
                            .fadeIn();
                        setTimeout(function() { $('#chat-notification').fadeOut(); }, 3000);
                    }
                }
            }
        });
    }, 3000);
    </script>
</body>
</html>