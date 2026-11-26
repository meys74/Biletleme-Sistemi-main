<?php
require_once 'config.inc.php';
require_once 'dbHandler.inc.php';
require_once 'ticket-chat-page-functions.inc.php';

if(!isset($_SESSION['userid'])) {
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
    header("Location: worker_panel.php");
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
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF Token. Request denied.']);
            exit();
        }
        die("Hata: Geçersiz CSRF Token. Güvenlik ihlali şüphesi.");
    }
}

// DEPARTMAN ATAMA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_department_btn'])) {
    $department_id = intval($_POST['department_id']);
    $assigned = assignTicketToDepartment($conn, $ticket_id, $department_id);
    
    if ($assigned) {
        $depQuery = mysqli_query($conn, "SELECT department_name FROM departments WHERE department_id = $department_id");
        $depData = mysqli_fetch_assoc($depQuery);
        $department_name = $depData['department_name'] ?? 'Unknown Department';

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'success',
                'message' => 'The ticket was successfully transferred to unit ' . $department_name . '.',
                'department_name' => $department_name
            ]);
            exit();
        }

        header("Location: ticket-chat-page-employee.php?id=" . $ticket_id);
        exit();
    }
}

// DURUM GÜNCELLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status_btn'])) {
    $new_status = trim($_POST['ticket_status'] ?? '');

    if (!empty($new_status)) {
        $updated = updateTicketStatus($conn, $ticket_id, $new_status);
        
        if ($updated) {
            // Müşteriye durum güncellendi bildirimi gönderiyoruz (Çalışanın kendi ziline yazılmaz)
            $custQuery = mysqli_query($conn, "SELECT customer_ID FROM tickets WHERE ticket_ID = $ticket_id");
            if ($custRow = mysqli_fetch_assoc($custQuery)) {
                $customer_id = $custRow['customer_ID'];
                $notifMsg = "Ticket #{$ticket_id} status updated to '{$new_status}'.";
                createNotification($conn, $customer_id, 'ticket', $notifMsg);
            }

            $statusLower = strtolower(trim($new_status));
            $step = 1;
            if ($statusLower === 'in progress') $step = 2;
            elseif ($statusLower === 'resolved') $step = 3;
            elseif ($statusLower === 'closed') $step = 4;

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'status' => 'success',
                    'new_status' => htmlspecialchars($new_status),
                    'current_step' => $step
                ]);
                exit();
            }

            header("Location: ticket-chat-page-employee.php?id=" . $ticket_id);
            exit();
        }
    }
}

// MESAJ GÖNDERME
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
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'message' => 'Invalid file format! Only JPG, PNG, PDF, DOCX, and ZIP allowed.']);
                exit();
            }
        } elseif ($fileSize > $maxFileSize) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
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
            // Müşteriye yeni mesaj bildirimi oluşturuyoruz
            $custQuery = mysqli_query($conn, "SELECT customer_ID FROM tickets WHERE ticket_ID = $ticket_id");
            if ($custRow = mysqli_fetch_assoc($custQuery)) {
                $customer_id = $custRow['customer_ID'];
                $notifMsg = "Support representative replied to Ticket #{$ticket_id}.";
                createNotification($conn, $customer_id, 'message', $notifMsg);
            }

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                $userQuery = mysqli_query($conn, "SELECT username, role_ID FROM users WHERE user_ID = $logged_in_user_id");
                $userData = mysqli_fetch_assoc($userQuery);

                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'status' => 'success',
                    'chat_id' => $inserted_id,
                    'sender' => htmlspecialchars($userData['username'] ?? 'Customer Support'),
                    'time' => date("d.m.Y - H:i"),
                    'message' => nl2br(htmlspecialchars($message)),
                    'file_url' => $filePath,
                    'message_class' => 'sent'
                ]);
                exit();
            }
            
            header("Location: ticket-chat-page-employee.php?id=" . $ticket_id);
            exit();
        }
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Please enter a message or attach a file!']);
            exit();
        }
    }
}

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

// Anlık Yeni Mesaj ve Bildirim Kontrolü (AJAX Polling)
if (isset($_GET['fetch_new_messages']) && $_GET['fetch_new_messages'] == 1) {
    $last_msg_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

    // 1. Yeni Mesajlar
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

    // 2. Çalışan İçin Bildirim Kartları
    $notifSql = "SELECT * FROM notifications WHERE user_ID = ? ORDER BY created_at DESC LIMIT 10";
    $notifStmt = mysqli_prepare($conn, $notifSql);
    mysqli_stmt_bind_param($notifStmt, "i", $logged_in_user_id);
    mysqli_stmt_execute($notifStmt);
    $notifResult = mysqli_stmt_get_result($notifStmt);
    $rawNotifications = mysqli_fetch_all($notifResult, MYSQLI_ASSOC);
    mysqli_stmt_close($notifStmt);

    $formattedNotifications = [];
    foreach ($rawNotifications as $n) {
        $formattedNotifications[] = [
            'id' => $n['notification_ID'],
            'type' => $n['notification_type'],
            'message' => htmlspecialchars($n['notification_message']),
            'date' => date("d.m.Y H:i", strtotime($n['created_at']))
        ];
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
            'message_class' => $isCustomer ? 'received' : 'sent'
        ];
    }


    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success', 
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
    
    <link rel ="stylesheet" href="admin_sidebar.css">
    <link rel="stylesheet" href="ticket-chat-page-style.css">
    <script src="https://kit.fontawesome.com/70fc0809a2.js" crossorigin="anonymous"></script>
    <title>Employee Ticket Page</title>
</head>

<body class="dashboard-body">
    <!-- Anlık Bildirim Balonu (Toast) -->
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
                    <a href="worker_panel.php" class="menu-link"><i class="bi bi-grid-fill"></i> Dashboard</a>
                    <a href="ticket-chat-page-employee.php" class="menu-link"><i class="bi bi-ticket-perforated"></i> Tickets</a>
                    <span class="menu-title mt-4">ACCOUNT</span>
                    <a href="updateEmployeeProfile.php" class="menu-link active"> <i class="bi bi-person fs-5"></i> Profile</a>
                    <a href="logout.inc.php" class="menu-link"><i class="bi bi-box-arrow-left"></i> Sign Out</a>
                </nav>
            </div>
        </aside>

        <div class="ticketTable">

            <div class="topDashboard">
                <div class="PageTitle">
                    <h1>Ticket Details</h1>
                    <small>Ticket History of #<?php echo htmlspecialchars($ticket['ticket_ID']); ?></small>
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

                <button class="backBtn all-card-btn" type="button" onclick="window.location.href='worker_panel.php'">
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
                                #<?php echo htmlspecialchars($ticket['ticket_ID']); ?> - <?php echo htmlspecialchars($ticket['department_name']); ?>
                            </small>
                            <h2><?php echo htmlspecialchars($ticket['ticket_title']); ?></h2>
                            <div class="ticketInfo">
                                <div class="ticketTags">
                                    <span class="priority all-card-btn">
                                        <?php echo htmlspecialchars($ticket['ticket_status']); ?>
                                    </span>
                                    <span class="department all-card-btn">
                                        <?php echo htmlspecialchars($ticket['ticket_priority']); ?> Priority
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

                        <div class="current-department-info" style="margin-bottom: 15px; padding: 10px; background-color: #f4f6f9; border-radius: 5px;">
                            <strong>Assigned unit:</strong> 
                            <span id="chat-assigned-unit-badge" class="badge" style="background-color: #007bff; color: #fff; padding: 5px 10px; border-radius: 4px;">
                                <?php echo !empty($ticket['department_name']) ? htmlspecialchars($ticket['department_name']) : 'Customer Support'; ?>
                            </span>
                        </div>

                        <div class="chatBody">
                            <?php if (!empty($messages)): ?>
                                <?php foreach ($messages as $msg): ?>
                                <?php 
                                    $isCustomer = ($msg['role_ID'] == 3);
                                    $messageClass = $isCustomer ? 'received' : 'sent';
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

                        <form method="POST" class="chatInput">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <textarea name="chat_message" placeholder="Type your message..." rows="2"></textarea> 
                            <input type="file" name="chat_file" id="chatFileInput" style="display: none;">

                            <div class="chatActions">
                                <button type="button" class="attachBtn" onclick="$('#chatFileInput').click();">
                                    <i class="fa-solid fa-paperclip"></i> Attach File
                                </button>
                                <span id="selectedFileName" style="font-size: 12px; color: #555; margin-left: 8px;"></span>
                                <button type="button" id="sendChatBtn" name="send_message_btn" class="sendBtn">
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
                                    <span class="infoValue">#<?php echo htmlspecialchars($ticket['ticket_ID']); ?></span>
                                </div>
                                <div class="infoRow">
                                    <span class="infoLabel">Priority</span>
                                    <span class="infoValue"><?php echo htmlspecialchars($ticket['ticket_priority']); ?></span>
                                </div>
                                <div class="infoRow">
                                    <span class="infoLabel">Department</span>
                                    <span class="infoValue"><?php echo htmlspecialchars($ticket['department_name']); ?></span>
                                </div>
                                <div class="infoRow">
                                    <span class="infoLabel">Assigned To</span>
                                    <span class="infoValue"><?php echo htmlspecialchars($ticket['employee_name'] ?? 'Unassigned'); ?></span>
                                </div>
                                <div class="infoRow">
                                    <span class="infoLabel">Created Date</span>
                                    <span class="infoValue"><?php echo date("d.m.Y", strtotime($ticket['ticket_created_at'])); ?></span>
                                </div>
                                <div class="infoRow">
                                    <span class="infoLabel">Created By</span>
                                    <span class="infoValue"><?php echo htmlspecialchars($ticket['customer_name']); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="infoCard all-card-btn" style="margin-top: 15px;">
                            <div class="cardHeader">
                                <h3>Ticket Assignment</h3>
                            </div>
                            <div class="infoBody" style="padding: 15px;">
                                <div style="margin-bottom: 12px; font-weight: 500; font-size: 14px;">
                                    Current Department: 
                                    <span id="current-department-badge" style="background-color: #007bff; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 12px;">
                                        <?php echo !empty($ticket['department_name']) ? htmlspecialchars($ticket['department_name']) : 'Customer Support'; ?>
                                    </span>
                                </div>

                                <form method="POST" class="assign-department-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <label for="department_id">Assign The Ticket To:</label>
                                    <select name="department_id" id="department_id" required>
                                        <option value="1" <?php echo (isset($ticket['department_id']) && $ticket['department_id'] == 1) ? 'selected' : ''; ?>>Technical Support</option>
                                        <option value="2" <?php echo (isset($ticket['department_id']) && $ticket['department_id'] == 2) ? 'selected' : ''; ?>>Billing & Finance</option>
                                        <option value="4" <?php echo (isset($ticket['department_id']) && $ticket['department_id'] == 4) ? 'selected' : ''; ?>>Customer Support</option>
                                    </select>
                                    <button type="submit" name="assign_department_btn" class="updateBtn" style="width: 100%; margin-top: 10px; padding: 10px; font-size: 16px;">
                                        Transfer To The Department
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="updateCard all-card-btn">
                            <div class="cardHeader">
                                <h3>Update Status</h3>
                            </div>
                            <form method="POST" class="updateBody">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <select name="ticket_status" id="status">
                                    <option value="Open" <?php echo (strtolower(trim($ticket['ticket_status'])) === 'open') ? 'selected' : ''; ?>>Open</option>
                                    <option value="In Progress" <?php echo (strtolower(trim($ticket['ticket_status'])) === 'in progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Resolved" <?php echo (strtolower(trim($ticket['ticket_status'])) === 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="Closed" <?php echo (strtolower(trim($ticket['ticket_status'])) === 'closed') ? 'selected' : ''; ?>>Closed</option>
                                </select>
                                <button type="submit" name="update_status_btn" class="updateBtn">
                                    Save
                                </button>
                            </form>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
    console.log(csrfToken);
</script>
<script src="profileImage.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

    // 1. Durum Güncelleme (AJAX)
    $(document).off('submit', '.updateBody').on('submit', '.updateBody', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var newStatus = $('#status').val();
        var csrfToken = $form.find('input[name="csrf_token"]').val();

        if ($submitBtn.prop('disabled')) return false;
        $submitBtn.prop('disabled', true);

        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                update_status_btn: true,
                ticket_status: newStatus,
                csrf_token: csrfToken
            },
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                $submitBtn.prop('disabled', false);

                if (response.status === 'success') {
                    $('.ticketTags .priority').text(response.new_status);

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

                    showNotification("Status updated to " + response.new_status + ".", "success");
                }
            },
            error: function() {
                $submitBtn.prop('disabled', false);
                showNotification("Failed to update status!", "error");
            }
        });
    });

    // 2. Departman Değiştirme (AJAX)
    $(document).off('submit', '.assign-department-form').on('submit', '.assign-department-form', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var selectedDeptId = $form.find('select[name="department_id"]').val();
        var csrfToken = $form.find('input[name="csrf_token"]').val();

        if ($submitBtn.prop('disabled')) return false;
        $submitBtn.prop('disabled', true);

        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                assign_department_btn: true,
                department_id: selectedDeptId,
                csrf_token: csrfToken
            },
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                $submitBtn.prop('disabled', false);

                if (response.status === 'success') {
                    $('#chat-assigned-unit-badge').text(response.department_name);
                    $('#current-department-badge').text(response.department_name);
                    showNotification(response.message, "success");
                }
            },
            error: function() {
                $submitBtn.prop('disabled', false);
                showNotification("Failed to transfer department!", "error");
            }
        });
    });

    // 3. Mesaj Gönderme Fonksiyonu (YENİ VE KORUMALI YAPI)
    function submitChatMessage() {
        var $textarea = $('textarea[name="chat_message"]');
        var messageText = $.trim($textarea.val());
        var fileInput = $('#chatFileInput')[0].files[0];
        var csrfToken = $('input[name="csrf_token"]').val();
        var $sendBtn = $('.sendBtn');

        if ($sendBtn.prop('disabled')) return;

        if (messageText === "" && !fileInput) {
            showNotification("Please enter a message or attach a file!", "error");
            $textarea.focus();
            return;
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

                    // Alanları Temizle
                    $textarea.val('');
                    $('#chatFileInput').val('');
                    $('#selectedFileName').text('');

                    showNotification("Message sent.", "success");
                }
            },
            error: function(xhr) {
                $sendBtn.prop('disabled', false);
                console.error("AJAX Error:", xhr.responseText);
                showNotification("An error occurred while sending the message!", "error");
            }
        });
    }

    // Butona basıldığında gönder
    $(document).off('click', '.sendBtn').on('click', '.sendBtn', function(e) {
        e.preventDefault();
        submitChatMessage();
    });

    // Form submit olursa (Enter vb.) yine bu fonksiyonu çalıştır
    $(document).off('submit', '.chatInput').on('submit', '.chatInput', function(e) {
        e.preventDefault();
        submitChatMessage();
    });

});

    // Her 3 Saniyede Bir Yeni Mesaj ve Bildirim Kontrolü (Polling)
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

                // 1. Kalıcı Bildirim Kartları ve Zil Kırmızı Noktası
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

                // 2. Yeni Mesajları Canlı Basma & Gelen Mesaj Toast Bildirimi
                if (response.status === 'success' && response.messages && response.messages.length > 0) {
                    $('.chatBody p').remove();
                    var hasIncomingCustomerMessage = false;

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
                                hasIncomingCustomerMessage = true;
                            }
                        }
                    });

                    $('.chatBody').scrollTop($('.chatBody')[0].scrollHeight);

                    // Müşteriden Yeni Mesaj Geldiğinde 3 Sn Toast Bildirimi Göster
                    if (hasIncomingCustomerMessage) {
                        var bgColor = '#28a745';
                        $('#chat-notification')
                            .css('background-color', bgColor)
                            .text("New message received from Customer!")
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