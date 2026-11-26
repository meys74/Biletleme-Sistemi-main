<?php
require_once 'config.inc.php';

require_once 'dbHandler.inc.php';

if(!isset($_SESSION['userid'])) {
    header("Location: signin.php");
    exit();
}

$user_ID = $_SESSION['userid'];

$sql = "SELECT * FROM users WHERE user_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_ID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user= mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);
if(!$user){
        session_destroy();
        header("Location: signin.php");
        exit();
    }
$profileImage= $user['profile_image'];

require_once 'notification.inc.php';
$successMessage = "";



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token kontrolü
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        die("Hata: CSRF Token bulunamadı. İstek reddedildi.");
    }

    // hash_equals() fonksiyonu Timing Attack (Zamanlama Saldırısı) adı verilen ileri seviye
    // bir saldırıyı engeller. '==' veya '===' yerine KESİNLİKLE bu kullanılmalıdır.
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Hata: Geçersiz CSRF Token. Güvenlik ihlali şüphesi.");
    }
    
    
  

    $ticket_ID = "";
    $ticketTitle = "";
    $ticketDescription = "";
    $ticketStatus =  "open";
    $ticketPriority = "low";
    $customer_ID = $_SESSION['userid'];
    $employee_ID = "";
    $department_ID = "";
    $createdAt;
    $ticket_attachment_file ="";

    $successMessage = $_SESSION['successMessage'] ?? "";
    $errorMessage = "";
    $ticketTitleError = "";
    $ticketDescriptionError = "";
    $attachmentsError="";

    unset($_SESSION['successMessage']);

    if(isset($_POST['submitTicket'])){

        $ticketTitle=$_POST['ticket-title'];
        $ticketDescription=$_POST['ticket-description'];

        if(empty($ticketTitle)){
            $ticketTitleError = "Ticket title is required";
        }elseif(strlen($ticketTitle) < 10 || strlen($ticketTitle) > 50){
            $ticketTitleError = "Ticket title must be between 10 and 50 characters";
        }

        if(empty($ticketDescription)){
            $ticketDescriptionError = "Ticket description is required";
        }elseif(strlen($ticketDescription) < 20 || strlen($ticketDescription) > 200){
            $ticketDescriptionError = "Ticket description must be between 20 and 200 characters";
        }

        //attachments upload
        if(!empty($_FILES['attachments']['name'])) {

            $fileName = $_FILES['attachments']['name'];
            $tmpName =$_FILES['attachments']['tmp_name'];
            $fileSize = $_FILES['attachments']['size'];

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];


            $extension =  strtolower(
                pathinfo($fileName, PATHINFO_EXTENSION)
            );

            if($fileSize > 5 * 1024 * 1024) {
                $attachmentsError ="File is too large.";

            }elseif(!in_array($extension, $allowedExtensions)){
                $attachmentsError = "Invalid file type.";
            }else{

                $newFileName = uniqid() . "." . $extension;

                $ticket_attachment_file = "uploads/" . $newFileName;

                if(move_uploaded_file($tmpName, $ticket_attachment_file)){
                    $successMessage = "file upload successfully";
                }else{
                    $attachmentsError = "Failed to upload file.";
                }

            }
        }

        if(empty($ticketTitleError) && empty($ticketDescriptionError) && empty($attachmentsError)){
            $sql = "INSERT INTO tickets (
                ticket_title, 
                ticket_description, 
                ticket_status, 
                ticket_priority, 
                customer_ID,  
                ticket_created_at, 
                ticket_attachment_file)
            VALUES (?, ?, ?, ?, ?, NOW(),?)";

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt,
                "ssssis", 
                $ticketTitle, 
                $ticketDescription, 
                $ticketStatus,
                $ticketPriority,
                $customer_ID,
                $ticket_attachment_file
                );
            if(mysqli_stmt_execute($stmt)){

                $notificationMessage ="Ticket Created successfully.";
                $notificationType = "ticket_create";
                $sql = "INSERT INTO notifications 
                    (user_ID,notification_message,notification_type)
                    VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param(
                    $stmt, 
                    "iss",
                    $user['user_ID'],
                    $notificationMessage,
                    $notificationType
                );
                mysqli_stmt_execute($stmt);
                $setNotification = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);


                header("Location: customerCreateTicket.php?updated=1");
                exit();
            } else {

                header("Location: customerCreateTicket.php?updated=0");
                exit();
            }

            mysqli_stmt_close($stmt);

        } else {
            $errorMessage = "Please fix the errors in the form.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="admin_sidebar.css">
    <link rel="stylesheet" href="customerCreateTicket.css">
    
    <script src="https://kit.fontawesome.com/70fc0809a2.js" crossorigin="anonymous"></script>
    
    <title>New Ticket Page</title>
</head>
<body class="dashboard-body">
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
                    <a href="customerTicket.php" class="menu-link"><i class="bi bi-grid-fill"></i> Dashboard</a>
                    <a href="customerCreateTicket.php" class="menu-link"><i class="bi bi-ticket-perforated"></i> Tickets</a>
                    
                    <span class="menu-title mt-4">ACCOUNT</span>
                    <a href="updateCustomerProfile.php" class="menu-link active"> <i class="bi bi-person fs-5"></i> Profile</a>
                    <a href="logout.inc.php" class="menu-link"><i class="bi bi-box-arrow-left"></i> Sign Out</a>
                </nav>
            </div>
        </aside>


        <div class="ticket-main">
            <div class="ticket-topbar">  
                <p>Create Ticket</p>
                <span class="profile-area">
                    <span class="notification">
                        <i id="notification-icon" class="fa-regular fa-bell"></i>
                        <?php if(mysqli_num_rows($notification) > 0): ?>
                            <span class="notification-dot"></span>
                        <?php endif; ?>
                    </span>

                    <img 
                        class="topbar-image" 
                        id="customerTopImg" 
                        src="<?= !empty($user['profile_image'])
                            ? htmlspecialchars($user['profile_image'])
                            : 'Images/maleUser.png'; ?>"
                        alt="profile Image">
                </span>
            </div>
            <div class="ticket-content">

                <div class="ticket-form-card">  
                    <div class="ticket-card-header">
                        <p class="ticket-card-title">Create a New Ticket</p>
                        <p >Describe your issue and create a support ticket.</p>
                    </div>
                    
                    <script>
                        const updateStatus = <?= isset($_GET['updated']) ? (int)$_GET['updated'] : -1 ?>;
                    </script>

                    <form 
                        action="" 
                        method="post" 
                        enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                        <div class="ticket-form-fields">
                            <label for="ticket-title">Ticket Title</label>
                            <input  
                                class="ticket-input" 
                                type="text" 
                                id="ticket-title" 
                                name="ticket-title" 
                                placeholder="e.g. Billing amount discrepancy"
                                value="<?=isset($_POST['ticket-title']) ? htmlspecialchars($ticketTitle) : '' ?>">
                            <div class="input-error"><?= htmlspecialchars($ticketTitleError) ?></div><br>

                            <label for="ticket-description">Ticket Description</label>
                            <textarea 
                                class="ticket-input" 
                                id="ticket-description"
                                name="ticket-description" 
                                placeholder="Describe your ticket in as much detail as possible..."
                                ><?=isset($_POST['ticket-description']) ? htmlspecialchars($ticketDescription) : '' ?></textarea>
                            <div class="input-error"><?= htmlspecialchars($ticketDescriptionError) ?></div><br>
                            
                            <label for="attachments">Attachments (optional)</label>
                            <input name="attachments" 
                                class="ticket-input"   
                                type="file" 
                                id="attachments">
                            <div class="attachments-error"><?= htmlspecialchars($attachmentsError) ?></div><br>
                        </div>
                        <div class="ticket-form-actions">
                            <button type="button" id="cancel" class="button btn-cancel">Cancel</button>
                            <button type="submit" id="submit" name="submitTicket" class="button btn-submit">Submit Ticket</button>
                        </div>
                    </form>
                </div>

                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div 
                        id="successToast"
                        class="toast text-bg-success"
                        role="alert">

                        <div class="toast-body">
                            Ticket created successfully.
                        </div>
                    </div>

                    <div 
                        id="errorToast"
                        class="toast text-bg-warning"
                        role="alert">

                        <div class="toast-body">
                            There is something wrong!
                        </div>
                    </div>
                </div> 
                <!--notification sidebar scroollablelist-->
                <div class="notification-container" >
                    <header class="notification-head">
                        <h2 class="notification-title">Notifications</h2>
                        <button class="close-notification-sidebar" type="button"><i class="fa-solid fa-xmark"></i></button>
                    </header><hr>
                    <div class="scrollable-container">
                        <ul class="scrollableList-notification">
                            <?php if(mysqli_num_rows($notification)> 0): ?>
                                <?php while($row =mysqli_fetch_assoc($notification)): ?>
                                    <li class="notification-inputs" data-id="<?= $row['notification_ID'] ?>">
                                        <div class="notification-icon">
                                            <?php if ($row['notification_type'] == 'profile_update'): ?>
                                                <i class="fa-solid fa-user-pen"></i>
                                            
                                            <?php elseif ($row['notification_type'] == 'message'): ?>
                                                <i class="fa-solid fa-message"></i>
                                            
                                            <?php elseif ($row['notification_type'] == 'ticket_create'): ?>
                                                <i class="fa-solid fa-ticket"></i>
                                            <?php else: ?>
                                                <i class="fa-solid fa-bell"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notification-content">
                                            <span class="notification-type">
                                                <?= htmlspecialchars($row['notification_type']) ?>
                                            </span>
                                            <span class="notification-message">
                                                <?= htmlspecialchars($row['notification_message']) ?>
                                            </span>
                                            <span class="notification-date">
                                                <?= htmlspecialchars($row['created_at']) ?>
                                            </span>
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
            </div>
        </div>
        
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
    </script>
    <script src="profileImage.js"></script>
    
    <script src="common.js"></script>
    <script src="customerCreateTicket.js"></script>
</body>
</html>