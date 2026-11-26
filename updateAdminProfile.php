<?php
require_once 'config.inc.php';
require_once 'dbHandler.inc.php';

// Admin veritabaninda bağlamak için
require_once 'db.php';

$user_ID = $_SESSION['userid'];

require_once 'notification.inc.php';

if (!isset($_SESSION['userid'])) {
    header("Location: signin.php");
    exit();
}


$sql = "SELECT * FROM users WHERE user_ID = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_ID);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$firstName = $user['first_name'];
$lastName = $user['last_name'];
$username = $user['username'];
$email = $user['email'];
$phone = $user['phone_number'];
$profileImage = $user['profile_image'];

$usernameError = $_SESSION["usernameError"] ?? "";
$emailError = $_SESSION["emailError"] ?? "";
$phoneError = $_SESSION["phoneError"] ?? "";
$firstNameError = $_SESSION["firstNameError"] ?? "";
$lastNameError = $_SESSION["lastNameError"] ?? "";
$currentPasswordError = $_SESSION["currentPasswordError"] ?? "";
$newPasswordError = $_SESSION["newPasswordError"] ?? "";
$userError = $_SESSION["userError"] ?? "";

unset($_SESSION["usernameError"]);
unset($_SESSION["emailError"]);
unset($_SESSION["phoneError"]);
unset($_SESSION["firstNameError"]);
unset($_SESSION["lastNameError"]);
unset($_SESSION["currentPasswordError"]);
unset($_SESSION["newPasswordError"]);
unset($_SESSION["userError"]);


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="admin_sidebar.css">
    <link rel="stylesheet" href="updateUsersProfile.css">
    <script src="https://kit.fontawesome.com/70fc0809a2.js" crossorigin="anonymous"></script>
    <title>Update Admin Profile</title>
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
                    <a href="admin.php" class="menu-link"><i class="bi bi-grid-fill"></i> Dashboard</a>
                    <a href="admin_tickets.php" class="menu-link"><i class="bi bi-ticket-perforated"></i> Tickets</a>
                    <a href="employees.php" class="menu-link"><i class="bi bi-people-fill"></i> Employees</a>
                    <span class="menu-title mt-4">ACCOUNT</span>
                    <a href="updateAdminProfile.php" class="menu-link active"> <i class="bi bi-person fs-5"></i> Profile</a>
                    <a href="logout.inc.php" class="menu-link"><i class="bi bi-box-arrow-left"></i> Sign Out</a>
                </nav>
            </div>
        </aside>

        <form
            action="updateProfile.php"
            method="post"
            enctype="multipart/form-data"
            class="profile-form">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="profile-topbar">
                <p>Admin Profile</p>
                <span class="topbar-profile">
                    <span class="notification">
                        <i id="notification-icon" class="fa-regular fa-bell"></i>
                        <?php if (mysqli_num_rows($notification) > 0): ?>
                            <span class="notification-dot"></span>
                        <?php endif; ?>
                    </span>

                    <img
                        id="topImg"
                        class="topbar-image"
                        src="<?= !empty($user['profile_image'])
                                    ? htmlspecialchars($user['profile_image'])
                                    : 'Images/maleUser.png'; ?>"
                        alt="profile Image">
                </span>
            </div>
            <div class="profile-content">
                <div class="profile-card-left">
                    <input
                        type="file"
                        id="profileImage"
                        name="profileImage"
                        accept="image/*"
                        hidden>
                    <div class="profile-image-container">
                        <img
                            id="profilePhoto"
                            class="profile-avatar"
                            src="<?= !empty($user['profile_image'])
                                        ? htmlspecialchars($user['profile_image'])
                                        : 'Images/maleUser.png'; ?>"
                            alt="Profile Image">
                        <div class="profile-image-overlay">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                    </div>
                    <div class="profile-details">
                        <h3 class="profile-fullname"><?= htmlspecialchars($firstName) . " " . htmlspecialchars($lastName) ?>
                            <hr>
                        </h3>
                        <p class="profile-username"><i class="fa-solid fa-user"></i> <?= htmlspecialchars($username) ?></p>
                        <p class="profile-email"><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($email) ?></p>
                        <p class="profile-phone"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($phone) ?></p>
                    </div>
                </div>
                <div class="profile-settings">
                    <div class="settings-card">
                        <div class="settings-header">
                            <p class="settings-title">Profile Settings</p>
                            <p>Manage your account information and security setttings.</p>
                        </div>

                        <script>
                            const updateStatus = <?= isset($_GET['updated']) ? (int)$_GET['updated'] : -1 ?>;
                        </script>

                        <div class="settings-form">
                            <label for="username">Username</label>
                            <input
                                class="settings-input"
                                type="text"
                                id="username"
                                name="username"
                                value="<?= htmlspecialchars($username) ?>"
                                placeholder="enter your Username">
                            <div class="profile-error"><?= htmlspecialchars($usernameError) ?></div><br>
                            <label for="email">Email Address</label>
                            <input
                                class="settings-input"
                                type="email"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars($email) ?>"
                                placeholder="enter your Email">
                            <div class="profile-error"><?= htmlspecialchars($emailError) ?></div><br>
                            <label for="phone">Phone Number</label>
                            <input
                                class="settings-input"
                                type="tel"
                                id="phone"
                                name="phone"
                                value="<?= htmlspecialchars($phone) ?>"
                                placeholder="Enter your phone number"
                                inputmode="numeric"
                                maxlength="15">
                            <div class="profile-error"><?= htmlspecialchars($phoneError) ?></div><br>
                            <label for="firstName">First Name</label>
                            <input
                                class="settings-input"
                                type="text"
                                id="firstName"
                                name="firstName"
                                value="<?= htmlspecialchars($firstName) ?>"
                                placeholder="enter your First Name">
                            <div class="profile-error"><?= htmlspecialchars($firstNameError) ?></div><br>
                            <label for="lastName">Last Name</label>
                            <input
                                class="settings-input"
                                type="text"
                                id="lastName"
                                name="lastName"
                                value="<?= htmlspecialchars($lastName) ?>"
                                placeholder="enter your Last Name">
                            <div class="profile-error"><?= htmlspecialchars($lastNameError) ?></div><br>

                            <div class="password-section">
                                <hr>
                                <p class="password-title">Change Password</p>
                            </div>
                            <label for="currentPassword">Current Password</label>
                            <input
                                class="settings-input-password"
                                type="password"
                                id="currentPassword"
                                name="currentPassword"
                                placeholder="Enter your current password">
                            <div class="profile-error"><?= htmlspecialchars($currentPasswordError) ?></div><br>
                            <label>New Password</label>
                            <input
                                class="settings-input-password"
                                type="password"
                                id="newPassword"
                                name="newPassword"
                                placeholder="Enter your new password">
                            <div class="profile-error"><?= htmlspecialchars($newPasswordError) ?></div><br>
                        </div>
                        <div class="settings-buttons">
                            <button type="button" id="cancel" class="settings-btn btn-cancel">Cancel</button>
                            <button type="submit" id="save" name="saveChanges" class="settings-btn btn-save">Save Changes</button>
                        </div>

                        <div class="toast-container position-fixed top-0 end-0 p-3">

                            <div
                                id="successToast"
                                class="toast text-bg-success"
                                role="alert">

                                <div class="toast-body">
                                    Profile updated successfully.
                                </div>

                            </div>

                            <div
                                id="errorToast"
                                class="toast text-bg-warning"
                                role="alert">

                                <div class="toast-body">
                                    No changes were made.
                                </div>

                            </div>

                        </div>

                    </div>
                </div>
                <!--notification sidebar scroollablelist-->
                <div class="notification-container">

                    <header class="notification-head">
                        <h2 class="notification-title">Notifications</h2>
                        <button class="close-notification-sidebar" type="button"><i class="fa-solid fa-xmark"></i></button>
                    </header>
                    <hr>

                    <div class="scrollable-container">
                        <ul class="scrollableList-notification">
                            <?php if (mysqli_num_rows($notification) > 0): ?>

                                <?php while ($row = mysqli_fetch_assoc($notification)): ?>
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
                            <?php else: ?>
                                <li id="notFound">
                                    <i class="fa-regular fa-bell-slash"></i>
                                    <span>No notifications</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="profileImage.js"></script>
    <script>
        const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
    </script>
    <script src="common.js"></script>
    <script src="updateProfile.js"></script>
</body>

</html>