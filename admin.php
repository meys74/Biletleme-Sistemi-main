<?php
require_once 'db.php';
require_once 'config.inc.php';


// TODO: Giriş sistemi bağlandığında aşağıdaki yorum satırlarını kaldır

if (!isset($_SESSION['userid']) || !isset($_SESSION['roleid']) || $_SESSION['roleid'] != 1) {
    header("Location: signin.php");
    exit();
}

/** @var PDO $db */

// --- PAGINATION HELPER FUNCTION ---
function getPageUrl($pageNumber)
{
    $params = $_GET;
    $params['page'] = $pageNumber;
    return '?' . http_build_query($params);
}

// --- WEEKLY TREND CALCULATION FUNCTION ---
function getWeeklyTrend($this_week_count, $last_week_count)
{
    if ($last_week_count > 0) {
        $pct = round((($this_week_count - $last_week_count) / $last_week_count) * 100);
    } else {
        $pct = $this_week_count > 0 ? 100 : 0;
    }

    $badge_class = $pct >= 0 ? 'positive' : 'negative';
    $icon_class  = $pct >= 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short';
    $sign        = $pct >= 0 ? '+' : '';

    return [
        'text'        => "{$sign}{$pct}% this week",
        'badge_class' => $badge_class,
        'icon_class'  => $icon_class
    ];
}

// --- Elapsed Time Calculation Function ---
function timeAgo($datetime)
{
    if (!$datetime) return 'N/A';
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    $mins = round($diff / 60);
    if ($mins < 60) return $mins . ' mins ago';
    $hours = round($diff / 3600);
    if ($hours < 24) return $hours . ' hours ago';
    $days = round($diff / 86400);
    return $days . ' days ago';
}

// --- 1. STATS GRID: TOTAL NUMBERS ---
$open_count = $db->query("SELECT COUNT(*) FROM tickets WHERE LOWER(ticket_status) = 'open'")->fetchColumn() ?: 0;
$in_progress_count = $db->query("SELECT COUNT(*) FROM tickets WHERE LOWER(ticket_status) = 'in progress'")->fetchColumn() ?: 0;
$resolved_today_count = $db->query("SELECT COUNT(*) FROM tickets WHERE LOWER(ticket_status) = 'resolved' AND DATE(ticket_created_at) = CURDATE()")->fetchColumn() ?: 0;
$overdue_count = $db->query("SELECT COUNT(*) FROM tickets WHERE LOWER(ticket_status) NOT IN ('resolved', 'closed') AND ticket_created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)")->fetchColumn() ?: 0;

// --- 2. STATS GRID: WEEKLY COMPARISONS ---
$open_this_week = $db->query("SELECT COUNT(*) FROM tickets WHERE LOWER(ticket_status) = 'open' AND YEARWEEK(ticket_created_at, 1) = YEARWEEK(NOW(), 1)")->fetchColumn() ?: 0;
$open_last_week = $db->query("SELECT COUNT(*) FROM tickets WHERE LOWER(ticket_status) = 'open' AND YEARWEEK(ticket_created_at, 1) = YEARWEEK(NOW() - INTERVAL 1 WEEK, 1)")->fetchColumn() ?: 0;
$open_trend = getWeeklyTrend($open_this_week, $open_last_week);

$progress_this_week = $db->query("SELECT COUNT(*) FROM tickets WHERE LOWER(ticket_status) = 'in progress' AND YEARWEEK(ticket_created_at, 1) = YEARWEEK(NOW(), 1)")->fetchColumn() ?: 0;
$progress_last_week = $db->query("SELECT COUNT(*) FROM tickets WHERE LOWER(ticket_status) = 'in progress' AND YEARWEEK(ticket_created_at, 1) = YEARWEEK(NOW() - INTERVAL 1 WEEK, 1)")->fetchColumn() ?: 0;
$progress_trend = getWeeklyTrend($progress_this_week, $progress_last_week);

$resolved_this_week = $db->query("SELECT COUNT(*) FROM tickets WHERE LOWER(ticket_status) = 'resolved' AND YEARWEEK(ticket_created_at, 1) = YEARWEEK(NOW(), 1)")->fetchColumn() ?: 0;
$resolved_last_week = $db->query("SELECT COUNT(*) FROM tickets WHERE LOWER(ticket_status) = 'resolved' AND YEARWEEK(ticket_created_at, 1) = YEARWEEK(NOW() - INTERVAL 1 WEEK, 1)")->fetchColumn() ?: 0;
$resolved_trend = getWeeklyTrend($resolved_this_week, $resolved_last_week);

$overdue_this_week = $db->query("SELECT COUNT(*) FROM tickets WHERE LOWER(ticket_status) NOT IN ('resolved', 'closed') AND ticket_created_at < DATE_SUB(NOW(), INTERVAL 3 DAY) AND YEARWEEK(ticket_created_at, 1) = YEARWEEK(NOW(), 1)")->fetchColumn() ?: 0;
$overdue_last_week = $db->query("SELECT COUNT(*) FROM tickets WHERE LOWER(ticket_status) NOT IN ('resolved', 'closed') AND ticket_created_at < DATE_SUB(NOW(), INTERVAL 3 DAY) AND YEARWEEK(ticket_created_at, 1) = YEARWEEK(NOW() - INTERVAL 1 WEEK, 1)")->fetchColumn() ?: 0;
$overdue_trend = getWeeklyTrend($overdue_this_week, $overdue_last_week);

// --- 3. LIFECYCLE (PROGRESS BAR) CALCULATIONS ---
$resolved_total = $db->query("SELECT COUNT(*) FROM tickets WHERE LOWER(ticket_status) = 'resolved'")->fetchColumn() ?: 0;
$closed_count   = $db->query("SELECT COUNT(*) FROM tickets WHERE LOWER(ticket_status) = 'closed'")->fetchColumn() ?: 0;
$total_tickets  = $open_count + $in_progress_count + $resolved_total + $closed_count;

$open_pct        = $total_tickets > 0 ? round(($open_count / $total_tickets) * 100, 1) : 0;
$in_progress_pct = $total_tickets > 0 ? round(($in_progress_count / $total_tickets) * 100, 1) : 0;
$resolved_pct    = $total_tickets > 0 ? round(($resolved_total / $total_tickets) * 100, 1) : 0;
$closed_pct      = $total_tickets > 0 ? round(($closed_count / $total_tickets) * 100, 1) : 0;

// --- 4. PULLING DEPARTMENTS ---
try {
    $deptStmt = $db->query("SELECT department_ID, department_name FROM departments ORDER BY department_ID ASC");
    $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Department Fetch Error: " . $e->getMessage());
}

// --- 5. RETRIEVING TICKETS BY FILTERING, SEARCHING, SORTING, AND PAGRANCY ---
try {
    $statusFilter = $_GET['status'] ?? 'all';
    $deptFilter   = $_GET['dept'] ?? 'all';
    $searchQuery  = trim($_GET['search'] ?? '');
    $dateFilter   = $_GET['date'] ?? '';

    // Sorting (Newest / Oldest)
    $sort_order = (isset($_GET['sort']) && strtolower($_GET['sort']) === 'asc') ? 'ASC' : 'DESC';
    $next_sort  = ($sort_order === 'DESC') ? 'asc' : 'desc';
    $sort_icon  = ($sort_order === 'DESC') ? 'bi-sort-down' : 'bi-sort-up';
    $sort_label = ($sort_order === 'DESC') ? 'Newest' : 'Oldest';

    // Pagination
    $limit = 10;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // SQL Query
    $query = "SELECT tickets.*, departments.department_name 
              FROM tickets 
              LEFT JOIN departments ON tickets.department_ID = departments.department_ID";

    $conditions = [];
    $params = [];

    // A. TICKET STATUS FILTER
    if (!empty($statusFilter) && strpos(strtolower($statusFilter), 'all') === false) {
        $conditions[] = "LOWER(tickets.ticket_status) = LOWER(:status)";
        $params[':status'] = $statusFilter;
    }

    // B. DEPARTMENT FILTER
    if ($deptFilter !== 'all' && $deptFilter !== 'all departments' && !empty($deptFilter)) {
        if (is_numeric($deptFilter)) {
            $conditions[] = "tickets.department_ID = :dept";
        } else {
            $conditions[] = "departments.department_name = :dept";
        }
        $params[':dept'] = $deptFilter;
    }

    // C. SEARCH BOX (Ticket ID, Title, Description)
    if (!empty($searchQuery)) {
        $conditions[] = "(tickets.ticket_ID LIKE :search OR tickets.ticket_title LIKE :search OR tickets.ticket_description LIKE :search)";
        $params[':search'] = '%' . $searchQuery . '%';
    }

    // D. FLATPICKR DATE RANGE FILTER
    if (!empty($dateFilter)) {
        $separator = (strpos($dateFilter, ' to ') !== false) ? ' to ' : ((strpos($dateFilter, ' - ') !== false) ? ' - ' : null);
        if ($separator) {
            $dates = explode($separator, $dateFilter);
            if (count($dates) === 2) {
                $startTimestamp = strtotime(trim($dates[0]));
                $endTimestamp   = strtotime(trim($dates[1]));

                if ($startTimestamp !== false && $endTimestamp !== false) {
                    $conditions[] = "tickets.ticket_created_at BETWEEN :startDate AND :endDate";
                    $params[':startDate'] = date('Y-m-d 00:00:00', $startTimestamp);
                    $params[':endDate']   = date('Y-m-d 23:59:59', $endTimestamp);
                }
            }
        }
    }

    // Adding conditions
    if (count($conditions) > 0) {
        $query .= " WHERE " . implode(' AND ', $conditions);
    }

    // CALCULATING THE TOTAL NUMBER OF RECORDS (For Pagination)
    $countQuery = "SELECT COUNT(*) FROM tickets LEFT JOIN departments ON tickets.department_ID = departments.department_ID";
    if (count($conditions) > 0) {
        $countQuery .= " WHERE " . implode(' AND ', $conditions);
    }
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $total_records = (int)$countStmt->fetchColumn();

    $total_pages = ceil($total_records / $limit);
    if ($total_pages < 1) $total_pages = 1;
    $start = $total_records > 0 ? $offset + 1 : 0;
    $end = min($offset + $limit, $total_records);

    // Adding Sorting and Limits
    $query .= " ORDER BY tickets.ticket_ID $sort_order LIMIT $limit OFFSET $offset";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Fetch Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="Images/GlowUpLogo_Ellipse_Background.png">
    <title>Admin Dashboard - Glow Up</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="admin.css">
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
                    <a href="admin.php" class="menu-link active"><i class="bi bi-grid-fill"></i> Dashboard</a>
                    <a href="admin_tickets.php"  class="menu-link"><i class="bi bi-ticket-perforated"></i> Tickets</a>
                    <a href="employees.php" class="menu-link"><i class="bi bi-people-fill"></i> Employees</a>
                    <span class="menu-title mt-4">ACCOUNT</span>
                    <a href="updateAdminProfile.php" class="menu-link"> <i class="bi bi-person fs-5"></i> Profile</a>
                    <a href="logout.inc.php" class="menu-link"><i class="bi bi-box-arrow-left"></i> Sign Out</a>
                </nav>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="main-content">
            <!-- TOP HEADER -->
            <header class="top-header">
                <div class="header-title">
                    <h2>Dashboard</h2>
                    <p>Let's see what's happening today.</p>
                </div>
                <div class="header-actions">
                    <!-- SEARCH BOX -->
                    <div class="search-box position-relative d-flex align-items-center">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" name="search" form="filterForm" id="ticketSearchInput" class="form-control"
                            placeholder="Search ticket..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <!-- Cross button -->
                        <i class="bi bi-x-lg clear-search-btn" id="clearSearchBtn"
                            style="<?= empty($_GET['search']) ? 'display: none;' : '' ?>"></i>
                    </div>

                    <!-- NOTIFICATION (BELL) ICON AND DROPDOWN -->
                    <div class="dropdown">
                        <button class="btn btn-link text-dark position-relative p-0 border-0 bg-transparent" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell fs-5"></i>
                            <!-- Notification dot -->
                            <span id="notificationBadge" class="position-absolute top-0 start-100 translate-middle p-1 bg-primary border border-light rounded-circle" style="display: none;">
                                <span class="visually-hidden">New alerts</span>
                            </span>
                        </button>

                        <!-- Pop-up Notification Menu -->
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-2" aria-labelledby="notificationDropdown" style="width: 300px; font-size: 13px;" id="notificationList">
                            <li class="px-3 py-2 border-bottom mb-1 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold text-dark" style="font-size: 13px;">System Notifications</span>
                                    <span id="notificationCountBadge" class="badge bg-primary rounded-pill" style="display: none;">0 New</span>
                                </div>
                                <button type="button" id="clearAllBtn" class="btn btn-link p-0 border-0 clear-all-btn" style="display: none;">
                                    Clear All
                                </button>
                            </li>

                            <li id="emptyNotification" class="px-3 py-2 text-center text-muted" style="font-size: 12px;">
                                No new notifications
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- 1. STATS GRID -->
            <section class="stats-grid">
                <!-- OPEN TICKETS CARD -->
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">OPEN TICKETS</span>
                        <span class="stat-icon open"><i class="bi bi-envelope-open-fill"></i></span>
                    </div>
                    <h2 class="stat-value"><?= $open_count ?></h2>
                    <div class="stat-badge <?= $open_trend['badge_class'] ?>">
                        <i class="bi <?= $open_trend['icon_class'] ?>"></i> <?= $open_trend['text'] ?>
                    </div>
                </div>

                <!-- IN PROGRESS CARD -->
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">IN PROGRESS</span>
                        <span class="stat-icon progress-icon"><i class="bi bi-clock-history"></i></span>
                    </div>
                    <h2 class="stat-value"><?= $in_progress_count ?></h2>
                    <div class="stat-badge <?= $progress_trend['badge_class'] ?>">
                        <i class="bi <?= $progress_trend['icon_class'] ?>"></i> <?= $progress_trend['text'] ?>
                    </div>
                </div>

                <!-- RESOLVED TODAY CARD -->
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">RESOLVED TODAY</span>
                        <span class="stat-icon resolved"><i class="bi bi-check-circle-fill"></i></span>
                    </div>
                    <h2 class="stat-value"><?= $resolved_today_count ?></h2>
                    <div class="stat-badge <?= $resolved_trend['badge_class'] ?>">
                        <i class="bi <?= $resolved_trend['icon_class'] ?>"></i> <?= $resolved_trend['text'] ?>
                    </div>
                </div>

                <!-- OVERDUE CARD -->
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">OVERDUE</span>
                        <span class="stat-icon overdue"><i class="bi bi-exclamation-triangle-fill"></i></span>
                    </div>
                    <h2 class="stat-value"><?= $overdue_count ?></h2>
                    <div class="stat-badge <?= $overdue_trend['badge_class'] ?>">
                        <i class="bi <?= $overdue_trend['icon_class'] ?>"></i> <?= $overdue_trend['text'] ?>
                    </div>
                </div>
            </section>

            <!-- 2. TICKETS LIFECYCLE DISTRIBUTION -->
            <section class="lifecycle-card">
                <div class="lifecycle-header">
                    <h3>Lifecycle distribution of all tickets</h3>
                    <span class="total-count">Total <?= $total_tickets ?> tickets</span>
                </div>
                <div class="progress custom-progress">
                    <div class="progress-bar bar-open" role="progressbar" style="width: <?= $open_pct ?>%;"></div>
                    <div class="progress-bar bar-progress" role="progressbar" style="width: <?= $in_progress_pct ?>%;"></div>
                    <div class="progress-bar bar-resolved" role="progressbar" style="width: <?= $resolved_pct ?>%;"></div>
                    <div class="progress-bar bar-closed" role="progressbar" style="width: <?= $closed_pct ?>%;"></div>
                </div>

                <div class="lifecycle-legend">
                    <span class="legend-item"><span class="dot open"></span> Open — <?= $open_count ?></span>
                    <span class="legend-item"><span class="dot progress"></span> In Progress — <?= $in_progress_count ?></span>
                    <span class="legend-item"><span class="dot resolved"></span> Resolved — <?= $resolved_total ?></span>
                    <span class="legend-item"><span class="dot closed"></span> Closed — <?= $closed_count ?></span>
                </div>
            </section>

            <!-- 3. ALL TICKETS TABLE -->
            <section class="tickets-table-card" id="ticketsTableCard">
                <div class="table-header">
                    <h3>All Tickets</h3>
                    <?php $is_empty = empty($tickets); ?>
                    <form method="GET" action="" id="filterForm" class="table-filters">
                        <!-- STATUS FILTER -->
                        <select name="status" class="filter-select" id="statusFilterSelect"
                            onchange="document.getElementById('filterForm').submit();">
                            <option value="" <?= empty($_GET['status']) ? 'selected' : '' ?>>All Statuses</option>
                            <option value="Open" <?= (($_GET['status'] ?? '') === 'Open') ? 'selected' : '' ?>>Open</option>
                            <option value="In Progress" <?= (($_GET['status'] ?? '') === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                            <option value="Pending" <?= (($_GET['status'] ?? '') === 'Pending') ? 'selected' : '' ?>>Pending</option>
                            <option value="Resolved" <?= (($_GET['status'] ?? '') === 'Resolved') ? 'selected' : '' ?>>Resolved</option>
                            <option value="Closed" <?= (($_GET['status'] ?? '') === 'Closed') ? 'selected' : '' ?>>Closed</option>
                        </select>

                        <!-- DEPARTMENT FILTER -->
                        <select name="dept" class="filter-select" id="deptFilterSelect"
                            onchange="document.getElementById('filterForm').submit();">
                            <option value="" <?= empty($_GET['dept']) ? 'selected' : '' ?>>All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['department_name']); ?>"
                                    <?= (($_GET['dept'] ?? '') === $dept['department_name']) ? 'selected' : '' ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- FLATPICKR DATE FILTER -->
                        <div class="date-filter-box position-relative d-flex align-items-center">
                            <i class="bi bi-calendar-event date-icon"></i>
                            <input type="text" name="date" id="dateFilterInput" class="filter-select form-control"
                                placeholder="Select Date Range" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>"
                                style="padding-right: 35px;"
                                <?= $is_empty ? 'disabled' : '' ?>>

                            <!-- Down arrow icon -->
                            <i class="bi bi-chevron-down date-right-icon" id="dateDownIcon"></i>

                            <!-- Cross button -->
                            <button type="button" id="dateClearBtn" class="btn btn-sm position-absolute top-50 translate-middle-y border-0 bg-transparent text-muted" style="right: 12px; display: none; z-index: 10; padding: 0;">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <!-- SORTING DROPDOWN -->
                        <button type="button"
                            id="sort-toggle-btn"
                            class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 text-decoration-none px-3 py-1 me-2 <?= $is_empty ? 'disabled' : '' ?>"
                            style="border-radius: 8px; font-weight: 500;"
                            <?= $is_empty ? 'disabled' : '' ?>>
                            <i class="bi <?= $sort_icon ?> fs-6" id="sort-icon"></i>
                            <span id="sort-label"><?= $sort_label ?></span>
                        </button>
                    </form>
                </div>

                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>TICKET ID</th>
                            <th>TICKET TITLE</th>
                            <th>CREATED TIME</th>
                            <th>DEPARTMENT</th>
                            <th>PRIORITY</th>
                            <th>STATUS</th>
                            <th class="pe-3" style="width: 110px;">PROGRESS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                            <tr>
                                <td colspan="7" class="text-center p-4 text-muted">
                                    <i class="bi bi-search me-2"></i>
                                    <?php
                                    $isFiltered = (!empty($_GET['search']) || (!empty($_GET['dept']) && $_GET['dept'] !== 'all') || (!empty($_GET['status']) && $_GET['status'] !== 'all') || !empty($_GET['date']));

                                    if ($isFiltered) {
                                        echo "No matching tickets found.";
                                    } else {
                                        echo "No ticket records found in the database.";
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket):
                                $status_clean = strtolower(trim($ticket['ticket_status'] ?? ''));
                                $status_class = match ($status_clean) {
                                    'open' => 'open',
                                    'in progress' => 'in-progress',
                                    'resolved' => 'resolved',
                                    'closed' => 'closed',
                                    default => ''
                                };

                                $priority_clean = strtolower(trim($ticket['ticket_priority'] ?? ''));
                                $priority_class = match ($priority_clean) {
                                    'high' => 'high',
                                    'medium' => 'medium',
                                    'low' => 'low',
                                    default => ''
                                };
                            ?>
                                <tr class="clickable-row" onclick="window.location.href='ticket-chat-page-admin.php?id=<?= $ticket['ticket_ID'] ?>'">
                                    <td><span class="ticket-id-tag"><?= htmlspecialchars('TCK-' . $ticket['ticket_ID']) ?></span></td>
                                    <td class="ticket-title-cell"><?= htmlspecialchars($ticket['ticket_title'] ?? '') ?></td>
                                    <td><?= !empty($ticket['ticket_created_at']) ? date('d M Y', strtotime($ticket['ticket_created_at'])) : '-' ?></td>
                                    <td><?= htmlspecialchars($ticket['department_name'] ?? (!empty($ticket['department_ID']) ? 'Department #' . $ticket['department_ID'] : 'Unassigned')) ?></td>
                                    <td><span class="priority-badge <?= $priority_class ?>"><?= ucfirst(htmlspecialchars($ticket['ticket_priority'] ?? '')) ?></span></td>
                                    <td><span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($ticket['ticket_status'] ?? '') ?></span></td>
                                    <td class="pe-3" style="width: 110px;">
                                        <?php if ($status_clean === 'resolved' || $status_clean === 'closed'): ?>
                                            <div class="dots-progress d-flex justify-content-end align-items-center <?= $status_clean === 'closed' ? 'closed' : '' ?>">
                                                <span class="step-dot active"></span>
                                                <span class="step-line active"></span>
                                                <span class="step-dot active"></span>
                                                <span class="step-line active"></span>
                                                <span class="step-dot active"></span>
                                            </div>
                                        <?php elseif ($status_clean === 'in progress'): ?>
                                            <div class="dots-progress d-flex justify-content-end align-items-center">
                                                <span class="step-dot active"></span>
                                                <span class="step-line active"></span>
                                                <span class="step-dot active"></span>
                                                <span class="step-line"></span>
                                                <span class="step-dot"></span>
                                            </div>
                                        <?php else: ?>
                                            <div class="dots-progress d-flex justify-content-end align-items-center">
                                                <span class="step-dot active"></span>
                                                <span class="step-line"></span>
                                                <span class="step-dot"></span>
                                                <span class="step-line"></span>
                                                <span class="step-dot"></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- PAGINATION -->
                <div class="d-flex justify-content-between align-items-center mt-3 px-2">
                    <!-- Left Side -->
                    <div class="text-muted small fw-semibold">
                        Showing <?= $start ?>-<?= $end ?> of <?= $total_records ?> tickets
                    </div>

                    <!-- Right Side -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm mb-0">
                                <!-- Previous Button -->
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= getPageUrl($page - 1) ?>"><i class="bi bi-chevron-left"></i></a>
                                </li>

                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= getPageUrl($i) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Next Button -->
                                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= getPageUrl($page + 1) ?>"><i class="bi bi-chevron-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
    <!-- Bootstrap JS Bundle & jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Jguery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Flatpickr -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="notifications.js"></script>
    <script>
        document.getElementById('sort-toggle-btn').addEventListener('click', function() {
            const tbody = document.querySelector('.custom-table tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const icon = document.getElementById('sort-icon');
            const label = document.getElementById('sort-label');

            // Skip execution if "No tickets found" message is displayed
            if (rows.length === 1 && rows[0].querySelector('td[colspan]')) return;

            // Instantly reverse the order of table rows
            rows.reverse();
            rows.forEach(row => tbody.appendChild(row));

            // Dynamically update button label and icon state
            if (label.textContent.trim() === 'Newest') {
                label.textContent = 'Oldest';
                icon.className = 'bi bi-sort-up fs-6';
            } else {
                label.textContent = 'Newest';
                icon.className = 'bi bi-sort-down fs-6';
            }
        });



        // PAGE OPERATIONS
        document.addEventListener('DOMContentLoaded', function() {
            renderNotifications();
        });

        // ==========================================
        // 1. Notification URL Cleaner
        // ==========================================
        function addNotification(title, text, iconClass) {
            let notifications = JSON.parse(localStorage.getItem('adminNotifications')) || [];

            const now = new Date();
            const formattedDate = now.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
            const formattedTime = now.toLocaleTimeString('en-GB', {
                hour: '2-digit',
                minute: '2-digit'
            });
            const timeStamp = `${formattedDate} ${formattedTime}`;

            notifications.unshift({
                title: title,
                text: text || '',
                icon: iconClass || 'bi-ticket-detailed',
                time: timeStamp
            });

            localStorage.setItem('adminNotifications', JSON.stringify(notifications));
            renderNotifications();
        }

        function addNotification(text, iconClass) {
            let notifications = JSON.parse(localStorage.getItem('adminNotifications')) || [];
            notifications.unshift({
                text: text,
                icon: iconClass || 'bi-ticket-detailed',
                time: 'Just now'
            });
            localStorage.setItem('adminNotifications', JSON.stringify(notifications));

            renderNotifications();
        }

        // ==========================================
        // 2. SEARCH AND CROSS BUTTON OPERATIONS (CONTINUOUS FOCUS)
        // ==========================================
        $(document).ready(function() {

            const searchInput = $('#ticketSearchInput');
            const clearBtn = $('#clearSearchBtn');
            const filterForm = $('#filterForm');
            const urlParams = new URLSearchParams(window.location.search);

            // 1. URL Correction
            if (urlParams.has('msg')) {
                const cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('msg');
                window.history.replaceState({}, document.title, cleanUrl.pathname + (cleanUrl.searchParams.toString() ? '?' + cleanUrl.searchParams.toString() : ''));
            }

            // 2. SEARCH AND CROSS BUTTON OPERATIONS
            let searchTimer;

            if (searchInput.length && searchInput.val().length > 0) {
                setTimeout(function() {
                    searchInput.focus();
                    const val = searchInput.val();
                    searchInput.val('').val(val);
                }, 50);
            }

            function toggleClearBtn() {
                if (searchInput.val() && searchInput.val().trim().length > 0) {
                    clearBtn.show();
                } else {
                    clearBtn.hide();
                }
            }
            toggleClearBtn();

            searchInput.on('input keyup', function(e) {
                if ([9, 37, 38, 39, 40].includes(e.which)) return;

                toggleClearBtn();

                clearTimeout(searchTimer);
                searchTimer = setTimeout(function() {
                    if (filterForm.length) {
                        filterForm.submit();
                    }
                }, 1000);
            });

            searchInput.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    clearTimeout(searchTimer);
                    if (filterForm.length) {
                        filterForm.submit();
                    }
                }
            });

            clearBtn.on('click', function(e) {
                e.preventDefault();
                searchInput.val('');
                clearBtn.hide();

                if (filterForm.length) {
                    filterForm.submit();
                } else {
                    window.location.href = window.location.pathname;
                }
            });

            // 3. FLATPICKR CALENDAR AND ICON MANAGEMENT
            if (typeof flatpickr !== 'undefined') {
                const dateInput = document.getElementById('dateFilterInput');
                const clearDateBtn = $('#dateClearBtn');
                const downIcon = $('#dateDownIcon');

                function toggleDateIcons(val) {
                    if (val && val.trim().length > 0) {
                        downIcon.hide();
                        clearDateBtn.show();
                    } else {
                        downIcon.show();
                        clearDateBtn.hide();
                    }
                }

                const fpInstance = flatpickr(dateInput, {
                    mode: "range",
                    dateFormat: "Y-m-d",
                    altInput: true,
                    altFormat: "d m Y",
                    locale: {
                        rangeSeparator: " - "
                    },
                    onChange: function(selectedDates, dateStr, instance) {
                        toggleDateIcons(dateStr);
                        if (selectedDates.length === 2 || selectedDates.length === 0) {
                            if (filterForm.length) {
                                filterForm.submit();
                            }
                        }
                    },
                    onReady: function(selectedDates, dateStr, instance) {
                        toggleDateIcons(dateInput.value);
                    }
                });

                clearDateBtn.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    fpInstance.clear();
                    dateInput.value = '';
                    toggleDateIcons('');

                    const url = new URL(window.location.href);
                    url.searchParams.delete('date');
                    window.location.href = url.toString();
                });
            }
        });
    </script>
</body>

</html>