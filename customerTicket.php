<?php
require_once 'config.inc.php';
if(!isset($_SESSION['userid'])) {
    header("Location: signin.php");
    exit();
}
require_once 'dbHandler.inc.php';

$user_ID = $_SESSION['userid'];

require_once 'notification.inc.php';



$sql = "SELECT tickets.*, departments.department_name
    FROM tickets
    LEFT JOIN departments
    ON tickets.department_ID = departments.department_ID
    WHERE tickets.customer_ID = ?";

$params =[$_SESSION['userid']];
$types ="i";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="admin_sidebar.css">
    <link rel="stylesheet" href="customerTicket.css">
    
    <script src="https://kit.fontawesome.com/70fc0809a2.js" crossorigin="anonymous"></script>
    
    <title>Customer Ticket Page</title>
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


        <div class="ticket-dashboard">
            <div class="dashboard-header">  
                <p class="dashboard-title">Panel</p>
                
                <span id="search" >
                    <span class="notification">
                        <i id="notification-icon" class="fa-regular fa-bell"></i>
                        <?php if(mysqli_num_rows($notification) > 0): ?>
                            <span class="notification-dot"></span>
                        <?php endif; ?>
                    </span>
                    <input 
                        id="search-ticket"
                        class="search-ticket" 
                        name="search" 
                        type="search" 
                        placeholder="Search Ticket..."
                        value= "<?= htmlspecialchars($_GET['search'] ?? '')?>">
                </span>
            </div> 
            <div class="tickets-card-content" >  
                <div class="tickets-card">
                    <div class="tickets-header">
                        <p class="tickets-title">My Tickets</p>
                        <span>
                            <select class="filter-tickets" id="searchStatus" name="status">
                                <option value="" <?= empty($_GET['status']) ? 'selected' : '' ?>>All Statuses</option>
                                <option value="open" <?= ($_GET['status'] ?? '' ) == 'open' ? 'selected' : '' ?>>Open</option>
                                <option value="pending" <?= ($_GET['status'] ?? '' ) == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="inProgress" <?= ($_GET['status'] ?? '' ) == 'inProgress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="closed" <?= ($_GET['status'] ?? '' ) == 'closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                            <select class="filter-tickets" id="searchDepartment" name="department" >
                                <option value="" <?= empty($_GET['department']) ? 'selected' : '' ?>>All Departments</option>
                                <option value="1" <?= ($_GET['department'] ?? '') == '1' ? 'selected' : '' ?>>HR</option>
                                <option value="2"<?= ($_GET['department'] ?? '') == '2' ? 'selected' : '' ?>>IT</option>
                                <option value="3" <?= ($_GET['department'] ?? '') == '3' ? 'selected' : '' ?>>Finance</option>
                                <option value="4" <?= ($_GET['department'] ?? '') == '4' ? 'selected' : '' ?>>Sales</option>
                                <option value="5" <?= ($_GET['department'] ?? '') == '5' ? 'selected' : '' ?>>Marketing</option>
                                <option value="6" <?= ($_GET['department'] ?? '') == '6' ? 'selected' : '' ?>>Customer Service</option>
                            </select>
                            <a  id="create-ticket-button" href="customerCreateTicket.php">
                                <i class="fa-solid fa-plus"></i> New Ticket
                            </a>
                        </span>
                    </div id="my-tickets-section">
                    <table class="tickets-table">
    <thead>
        <tr class="tickets-table-header">
            <th>TICKET</th>
            <th>DEPARTMENT</th>
            <th>CREATED TIME</th>
            <th>PRIORITY</th>
            <th>STATUS</th>
        </tr>
    </thead>
    <tbody id="tickets-body">
        <?php if(mysqli_num_rows($result) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
                <!-- Satıra tıklama verisi ve stili eklendi -->
                <tr class="ticket-row" data-id="<?= $row['ticket_ID'] ?>" style="cursor: pointer;">
                    <td class="ticket-title"><?= htmlspecialchars($row['ticket_title']) ?></td>
                    <td class="department-name"><?= htmlspecialchars($row['department_name']) ?></td>
                    <td class="ticket-created-at"><?= htmlspecialchars($row['ticket_created_at']) ?></td>
                    <td class="ticket-priority">
                        <span class="ticket-priority <?= strtolower(str_replace(' ','-',htmlspecialchars($row['ticket_priority']))) ?>">
                            <?= htmlspecialchars($row['ticket_priority']) ?>
                        </span>
                    </td>
                    <td class="ticket-status">
                        <span class="ticket-status <?= strtolower(htmlspecialchars($row['ticket_status'])) ?>">
                            <?= htmlspecialchars($row['ticket_status']) ?>
                        </span>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5">No tickets found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
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
    <script src="common.js"></script>
    <script src="customerTicket.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const ticketRows = document.querySelectorAll('.ticket-row');
    
    ticketRows.forEach(row => {
        row.addEventListener('click', function() {
            const ticketId = this.getAttribute('data-id');
            if(ticketId) {
                window.location.href = 'ticket-chat-page-customer.php?id=' + ticketId;
            }
        });
    });
});
</script>
</body>
</html>