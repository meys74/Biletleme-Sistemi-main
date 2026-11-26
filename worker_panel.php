<?php 
require_once 'config.inc.php';
require_once 'dbHandler.inc.php'; // Veritabanı bağlantısı önce yüklenmeli

if (!isset($_SESSION['userid'])) {
    header("Location: signin.php");
    exit();
}

require_once 'get_tickets.php';

function timeAgo($datetime) {
  if (empty($datetime)) return 'N/A';
    $time = strtotime($datetime);
   if (!$time) return htmlspecialchars($datetime);
    
    $diff = time() - $time;
   if ($diff < 60) return 'Just now';
    
    $intervals = [
       31536000 => 'year',
       2592000  => 'month',
       604800   => 'week',
       86400    => 'day',
       3600     => 'hour',
       60       => 'minute'
    ];
    
    foreach ($intervals as $secs => $str) {
      $d = $diff / $secs;
      if ($d >= 1) {
       $r = round($d);
       return $r . ' ' . $str . ($r > 1 ? 's' : '') . ' ago';
      }
    }
    return 'Just now';
}
?>


<!DOCTYPE html>
<html lang="en">
 <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Worker Panel</title>
   
    <link rel ="stylesheet" href="admin_sidebar.css">
   <style>
      body{
        margin: 0;
        font-family: Arial, sans-serif;
        background-color: #F3F6FA;
        height: 100vh;
        overflow: hidden; 
      }

      /* Sayfayı ikiye bölme */
      .container{
       display: flex;
      height: 100vh;
      }

      /* SOL MENÜ */
      .sidebar{
       width: 220px;
       height: 100vh;
       background-color: #203046;
       color: white;
       padding: 20px;
       box-sizing: border-box;
      }

      .sidebar h2{
       font-size: 18px;
       margin-bottom: 30px;
      }

      .sidebar a {
        display: flex;          
        align-items: center;   
        gap: 10px;              
        color: #C7D0E0;
        text-decoration: none;
        padding: 10px 12px;
        margin-bottom: 5px;
        border-radius: 6px;
      }  

      .sidebar a.active{
        background-color: #3B6EC9;
        color: white;
      }

      .brand {
       margin-bottom: 30px;
      }

      .brand-header {
       display: flex;
       align-items: center;
        gap: 12px;
      }

    /* Logo */
    .brand-logo {
     width: 36px;
     height: 36px;
     background-color: white;
     border-radius: 50%;
     padding: 4px; 
     object-fit: contain;
     box-sizing: border-box;
    }

    /* "Glow Up" Yazısı */
    .brand-title {
      font-size: 24px;
      font-weight: 500;
      color: #7BB4E3; 
      font-family: Georgia, serif; 
    }

    /* TICKETING & SERVICE SYSTEM */
    .brand-subtitle {
      display: block;
      font-size: 8.5px;
      font-weight: bold;
      color: #6C8CAE;
      letter-spacing: 1.2px;
      margin-top: 6px;
    }

    .menu-label {
      font-size: 11px;
      font-weight: bold;
      color: #7A889B;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-top: 20px;
      margin-bottom: 8px;
      padding-left: 10px;
    }

    /* SAĞ ALAN */
    .content {
      flex: 1;
      display: flex;
      flex-direction: column;
      background-color: #949ca5; 
      height: 100vh;
      overflow: hidden;
    }

    /* üst kısım  */
    .topbar {
      background-color: #d3d9db;
      padding: 15px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #DCE2E8;
    }

    .topbar h1 {
      margin: 0;
      font-size: 24px;
      font-family: Georgia, serif; 
      color: #000000;
    }

    /* arama ve bildirim alanı */
    .topbar-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .search-box {
      display: flex;
      align-items: center;
      background-color: #F3F6FA;
      border: 1px solid #E1E8ED;
      border-radius: 6px;
      padding: 6px 12px;
      gap: 6px;
    }

    .search-box input {
      border: none;
      background: transparent;
      outline: none;
      font-size: 13px;
      color: #333;
    }

    .notification-btn {
      background-color: #F3F6FA;
      border: 1px solid #E1E8ED;
      border-radius: 6px;
      padding: 6px 10px;
      cursor: pointer;
    }    

    /* ALT İÇERİK */
    .main-content {
      padding: 30px;
      flex: 1;
      overflow: hidden;
    }

    /* tablu kutusu */
    .panel{
      background-color: #daddde;
      border:1px solid #ddd;
      border-radius: 8px;
      display: flex;
      flex-direction: column;
      max-height: calc(100vh - 120px); 
      overflow: hidden;
    }

    /* başlık ve filtreler */
    .panel-head {
      display: flex;
      justify-content: space-between; 
      align-items: center;
      padding: 15px 20px;
      background-color: #daddde;
    }

    /* filtre kapsayıcısı */
    .filter-group {
      display: flex;
      gap: 10px; 
    }

    /* açılır menü*/
    .filter-select {
      background-color: #FFFFFF;
      color: #495057;
      border: 1px solid #CED4DA;
      border-radius: 8px; 
      padding: 6px 14px;
      font-size: 13px;
      outline: none;
      cursor: pointer;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      transition: border-color 0.2s;
    }

    .filter-select:hover {
      border-color: #ADB5BD;
    }

    .filter-select:focus {
      border-color: #3B6EC9;
    }

    /* Scrollbar */

    .table-responsive {
      max-height: 420px; 
      overflow-y: auto;
    }

    .table-responsive::-webkit-scrollbar {
      width: 6px;
    }
    .table-responsive::-webkit-scrollbar-track {
      background: #daddde;
    }
    .table-responsive::-webkit-scrollbar-thumb {
      background: #a0aab5;
      border-radius: 4px;
    }
    .table-responsive::-webkit-scrollbar-thumb:hover {
      background: #7a889b;
    }

      table{
        width: 100%;
        border-collapse: collapse;
      }


      th {
        position: sticky;
        top: 0;
        z-index: 2;
        background-color: #F3F6FA;
        font-size: 13px;
        color: gray;
      }

      th, td{
        text-align: left;
        padding: 12px 20px;
      }

      td{
        border-top: 1px solid #eee;
      }

      /* tıklanabilir satır */
      .clickable-row:hover {
        background-color: #f0f4f8;
        transition: background-color 0.2s ease;
      }

      /* DURUM ETİKETLERİ VE RENKLERİ */
      .badge {
       padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
        display: inline-block;
      }

      /* Status Renkleri */
      .status-open { background-color: #E8EFFB; color: #3B6EC9; }
      .status-pending { background-color: #FBF1E1; color: #B8862E; }
      .status-in-progress, .status-in-proccess { background-color: #FBF1E1; color: #B8862E; }
      .status-resolved { background-color: #E4F3EC; color: #2F7D5A; }
      .status-closed { background-color: #EAEEF3; color: #5B6B79; }

      /* Priority Renkleri */
      .priority-low { background-color: #EAEEF3; color: #5B6B79; }
      .priority-mid, .priority-medium { background-color: #E8EFFB; color: #3B6EC9; }
      .priority-important, .priority-high { background-color: #FBEAEA; color: #B84C4C; }
    </style>
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
                    <a href="worker_panel.php" class="menu-link"><i class="bi bi-grid-fill"></i> Dashboard</a>
                    <a href="ticket-chat-page-employee.php" class="menu-link"><i class="bi bi-ticket-perforated"></i> Tickets</a>
                    <span class="menu-title mt-4">ACCOUNT</span>
                    <a href="updateEmployeeProfile.php" class="menu-link active"> <i class="bi bi-person fs-5"></i> Profile</a>
                    <a href="logout.inc.php" class="menu-link"><i class="bi bi-box-arrow-left"></i> Sign Out</a>
                </nav>
            </div>
        </aside>

  


        <!-- SAĞ ALAN -->
        <div class="content">
        <!-- üst kısım  -->
        <div class="topbar">
        <h1>Panel</h1>

        <div class="topbar-right">
          <div class="search-box">
            <form method="GET" action="worker_panel.php" style="display: flex; align-items: center; width: 100%; margin: 0; padding: 0;">
              <input type="hidden" name="status" value="<?php echo htmlspecialchars($_GET['status'] ?? ''); ?>">
              <input type="hidden" name="department" value="<?php echo htmlspecialchars($_GET['department'] ?? ''); ?>">

              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>

              <input 
                type="text" 
                name="search" 
                placeholder="Search Ticket..." 
                value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
              />
            </form>
          </div>
    
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bell-icon lucide-bell"><path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/></svg>
          </div>
          </div>
  

          <!-- alt bölüm -->
          <div class="main-content">
          <div class="panel">
          <div class="panel-head">
           <h3>All Tickets</h3>
            <div class="filter-group">

          <form method="GET" action="worker_panel.php">
          <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">

          <!-- Durum Filtresi -->
          <select name="status" class="filter-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="open" <?php echo (isset($_GET['status']) && $_GET['status'] == 'open') ? 'selected' : ''; ?>>Open</option>
            <option value="in process" <?php echo (isset($_GET['status']) && ($_GET['status'] == 'in process' || $_GET['status'] == 'in proccess')) ? 'selected' : ''; ?>>In Process</option>
            <option value="resolved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'resolved') ? 'selected' : ''; ?>>Resolved</option>
            <option value="closed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'closed') ? 'selected' : ''; ?>>Closed</option>
            </select>

          <!-- Departman Filtresi -->
          <select name="department" class="filter-select" onchange="this.form.submit()">
            <option value="">All Departments</option>
            <option value="1" <?php echo (isset($_GET['department']) && $_GET['department'] == '1') ? 'selected' : ''; ?>>Finance</option>
            <option value="2" <?php echo (isset($_GET['department']) && $_GET['department'] == '2') ? 'selected' : ''; ?>>HR</option>
            <option value="3" <?php echo (isset($_GET['department']) && $_GET['department'] == '3') ? 'selected' : ''; ?>>IT</option>
            <option value="4" <?php echo (isset($_GET['department']) && $_GET['department'] == '4') ? 'selected' : ''; ?>>Technical Support</option>
            <option value="5" <?php echo (isset($_GET['department']) && $_GET['department'] == '5') ? 'selected' : ''; ?>>Sales</option>
          </select>
          </form>
          </div>
          </div>


         <div class="table-responsive">
            <table>
              <thead>
                <tr>
                  <th>Customer</th>
                  <th>Ticket</th>
                  <th>Department</th>
                  <th>Priority</th>
                  <th>Status</th>
                  <th>Updated</th>
                </tr>
              </thead>


              <tbody>
                <?php if (!empty($tickets)): ?>
                  <?php foreach ($tickets as $ticket): ?>

                    <?php 
                      $tId = $ticket['ticket_ID'] ?? $ticket['ticket_id'] ?? 0;?>
                      <tr onclick="window.location.href='ticket-chat-page-employee.php?id=<?php echo $tId; ?>';" style="cursor: pointer;" class="clickable-row">

                      <td><?php echo htmlspecialchars($ticket['customer_name'] ?? 'Unknown'); ?></td>
                      <td><?php echo htmlspecialchars($ticket['ticket_title']); ?></td>
                      <td><?php echo htmlspecialchars($ticket['department_name'] ?? 'N/A'); ?></td>

                      <td>
                        <span class="badge priority-<?php echo strtolower($ticket['ticket_priority']); ?>">
                         <?php echo htmlspecialchars($ticket['ticket_priority']); ?>
                        </span>
                      </td>

                      <td>
                        <span class="badge status-<?php echo strtolower(str_replace(' ', '-', $ticket['ticket_status'])); ?>">
                          <?php echo htmlspecialchars($ticket['ticket_status']); ?>
                        </span>
                      </td>

                      <td><?php echo timeAgo($ticket['ticket_created_at'] ?? $ticket['updated_at'] ?? ''); ?></td>

                    </tr>
                 <?php endforeach; ?>
                 <?php else: ?>

                  <tr>
                  <td colspan="6">No tickets found.</td>
                  </tr>

                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </body>
</html>