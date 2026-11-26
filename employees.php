<?php
require_once 'db.php';
require_once 'config.inc.php';

// TODO: Remove comment lines below when authentication is integrated

if (!isset($_SESSION['userid']) || !isset($_SESSION['roleid']) || $_SESSION['roleid'] != 1) {
    header("Location: signin.php");
    exit();
}


// --- PAGINATION LINK HELPER FUNCTION ---
function getPageUrl($pageNumber)
{
    $params = $_GET;
    $params['page'] = $pageNumber;
    return '?' . http_build_query($params);
}

// 2. FETCH DEPARTMENTS FOR SELECT DROPDOWN
try {
    $deptQuery = "SELECT department_ID, department_name FROM departments ORDER BY department_ID ASC";
    $deptStmt = $db->query($deptQuery);
    $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Department Fetch Error: " . $e->getMessage());
}

// 3. FETCH EMPLOYEES WITH FILTERS & PAGINATION (role_id = 2)
try {
    // Capturing URL parameters from JavaScript
    $statusFilter = $_GET['status'] ?? 'all';
    $deptFilter   = $_GET['dept'] ?? $_GET['department'] ?? 'all'; // Hem JS hem eski URL uyumu
    $searchQuery  = trim($_GET['search'] ?? '');
    $dateFilter    = $_GET['date'] ?? '';

    // Pagination Calculations
    $limit = 10;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // SQL Query
    $query = "SELECT u.user_ID, u.first_name, u.last_name, u.email, u.emp_status, u.join_date, u.department_ID, d.department_name 
              FROM users u 
              LEFT JOIN departments d ON u.department_ID = d.department_ID
              WHERE u.role_ID = 2";

    $conditions = [];
    $params = [];

    // A. EMP_STATUS FILTER
    if (!empty($statusFilter) && strpos(strtolower($statusFilter), 'all') === false) {
        $conditions[] = "u.emp_status = :status";
        $params[':status'] = $statusFilter;
    }

    // B. DEPARTMENT FILTER
    if ($deptFilter !== 'all' && $deptFilter !== 'all departments' && !empty($deptFilter)) {
        if (is_numeric($deptFilter)) {

            $conditions[] = "u.department_ID = :dept";
        } else {

            $conditions[] = "d.department_name = :dept";
        }
        $params[':dept'] = $deptFilter;
    }

    // C. SEARCH BOX
    if (!empty($searchQuery)) {
        $conditions[] = "(u.user_ID LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search)";
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
                    $conditions[] = "u.join_date BETWEEN :startDate AND :endDate";
                    $params[':startDate'] = date('Y-m-d 00:00:00', $startTimestamp);
                    $params[':endDate']   = date('Y-m-d 23:59:59', $endTimestamp);
                }
            }
        }
    }

    // Combining extra SQL terms
    if (count($conditions) > 0) {
        $query .= " AND " . implode(' AND ', $conditions);
    }

    // --- CALCULATING TOTAL NUMBER OF RECORDS (According to Filters) ---
    $countQuery = "SELECT COUNT(*) FROM users u LEFT JOIN departments d ON u.department_ID = d.department_ID WHERE u.role_ID = 2";
    if (count($conditions) > 0) {
        $countQuery .= " AND " . implode(' AND ', $conditions);
    }
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $total_records = (int)$countStmt->fetchColumn();

    // Total pages and display ranges
    $total_pages = ceil($total_records / $limit);
    $start = $total_records > 0 ? $offset + 1 : 0;
    $end = min($offset + $limit, $total_records);

    // --- ADDING PAGNITUDE LIMIT TO THE QUERY ---
    $query .= " ORDER BY u.user_ID DESC LIMIT $limit OFFSET $offset";

    // Running the Query
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Fetch Error: " . $e->getMessage());
}

// Pulling Unique Status Options
$status_stmt = $db->query("SELECT DISTINCT emp_status FROM users WHERE emp_status IS NOT NULL AND emp_status != ''");
$statuses = $status_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="Images/GlowUpLogo_Ellipse_Background.png">
    <title>Employees - Glow Up Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="admin.css?v=1.2">
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
                    <a href="admin.php" class="menu-link"><i class="bi bi-grid-fill"></i> Dashboard</a>
                    <a href="admin_tickets.php" class="menu-link"><i class="bi bi-ticket-perforated"></i> Tickets</a>
                    <a href="employees.php" class="menu-link active"><i class="bi bi-people-fill"></i> Employees</a>
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
                    <h2>Employees</h2>
                    <p>Manage your team members and their details.</p>
                </div>
                <div class="header-actions">
                    <div class="search-box position-relative d-flex align-items-center">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" name="search" form="filterForm" id="employeeSearchInput" class="form-control"
                            placeholder="Search employee..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <i class="bi bi-x-lg clear-search-btn" id="clearSearchBtn"
                            style="<?= empty($_GET['search']) ? 'display: none;' : '' ?>"></i>
                    </div>

                    <!-- TOP RIGHT NOTIFICATION (Bell) Icon and Dropdown Menu -->
                    <div class="dropdown">
                        <button class="btn btn-link text-dark position-relative p-0 border-0 bg-transparent" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell fs-5"></i>
                            <!-- Notification dot -->
                            <span id="notificationBadge" class="position-absolute top-0 start-100 translate-middle p-1 bg-primary border border-light rounded-circle" style="display: none;">
                                <span class="visually-hidden">New alerts</span>
                            </span>
                        </button>

                        <!-- DROPDOWN MENU -->
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

            <!-- EMPLOYEES TABLE CARD -->
            <section class="tickets-table-card">
                <!-- PAGE TOP SUCCESS NOTIFICATION -->
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
                    <div class="alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3 shadow z-3 pe-5 rounded-3 page-top-alert"
                        role="alert" style="z-index: 1055;">
                        <i class="bi bi-check-circle-fill me-2"></i> Employee added successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="table-header">
                    <h3>All Employees</h3>
                    <?php $is_empty = empty($employees); ?>

                    <!-- FORM  -->
                    <form method="GET" action="" id="filterForm" class="table-filters">

                        <!-- DEPARTMENT FILTER -->
                        <select name="dept" class="filter-select" id="deptFilterSelect"
                            onchange="document.getElementById('filterForm').submit();"
                            <?= $is_empty ? 'disabled' : '' ?>>
                            <option value="" <?= empty($_GET['dept']) ? 'selected' : '' ?>>All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['department_name']); ?>"
                                    <?= (($_GET['dept'] ?? '') === $dept['department_name']) ? 'selected' : '' ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- STATUS FILTER -->
                        <select name="status" class="filter-select" id="statusFilterSelect"
                            onchange="document.getElementById('filterForm').submit();"
                            <?= $is_empty ? 'disabled' : '' ?>>
                            <option value="" <?= empty($_GET['status']) ? 'selected' : '' ?>>All EMP Statuses</option>
                            <option value="Active" <?= (($_GET['status'] ?? '') === 'Active') ? 'selected' : '' ?>>
                                Active</option>
                            <option value="On Leave" <?= (($_GET['status'] ?? '') === 'On Leave') ? 'selected' : '' ?>>
                                On Leave</option>
                            <option value="Inactive" <?= (($_GET['status'] ?? '') === 'Inactive') ? 'selected' : '' ?>>
                                Inactive</option>
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

                        <!-- ADD EMPLOYEE BUTTON -->
                        <a href="add_employee.php" class="add-employee-btn">
                            <i class="bi bi-person-plus-fill"></i> Add Employee
                        </a>
                    </form>
                </div>

                <table class="custom-table" id="employeesTable">
                    <thead>
                        <tr>
                            <th>EMP ID</th>
                            <th>EMPLOYEE</th>
                            <th>DEPARTMENT</th>
                            <th>EMP STATUS</th>
                            <th>JOINED DATE</th>
                            <th class="text-end" style="width: 100px;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($employees)):
                            foreach ($employees as $employee):
                                $status = trim($employee['emp_status'] ?? 'Active');
                                $statusClass = strtolower(str_replace(' ', '-', $status)); ?>
                                <tr>
                                    <td>
                                        <span class="ticket-id-tag">
                                            EMP-<?php echo htmlspecialchars($employee['user_ID']); ?>
                                        </span>
                                    </td>
                                    <td class="ticket-title-cell">
                                        <div>
                                            <div class="fw-semibold">
                                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                            </div>
                                            <small class="text-muted" style="font-size: 0.78rem;">
                                                <?php echo htmlspecialchars($employee['email']); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($employee['department_name'] ?? 'Unassigned'); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($employee['join_date'])) {
                                            echo date('d M Y', strtotime($employee['join_date']));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-end" style="width: 100px;">
                                        <!-- EDIT BUTTON TRIGGERING POP-UP MODAL -->
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                            data-bs-target="#editEmployeeModal" data-id="<?php echo $employee['user_ID']; ?>"
                                            data-name="<?php echo htmlspecialchars($employee['first_name']); ?>"
                                            data-lastname="<?php echo htmlspecialchars($employee['last_name']); ?>"
                                            data-email="<?php echo htmlspecialchars($employee['email']); ?>"
                                            data-dept="<?php echo $employee['department_ID']; ?>"
                                            data-status="<?php echo htmlspecialchars($status); ?>" title="Edit Employee">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="6" class="text-center p-4 text-muted">
                                    <i class="bi bi-search me-2"></i>
                                    <?php
                                    $isFiltered = (!empty($_GET['search']) || (!empty($_GET['dept']) && $_GET['dept'] !== 'all') || (!empty($_GET['status']) && $_GET['status'] !== 'all') || !empty($_GET['date']));

                                    if ($isFiltered) {
                                        echo "No matching employees found.";
                                    } else {
                                        echo "No employee records found in the database.";
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- PAGINATION -->
                <div class="d-flex justify-content-between align-items-center mt-3 px-2">
                    <!-- Left Side: Showing Text (Simplified to Employees format) -->
                    <div class="text-muted small fw-semibold">
                        Showing <?= $start ?>-<?= $end ?> of <?= $total_records ?> tickets
                    </div>

                    <!-- Right Side: Page Navigation Buttons -->
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

    <!-- EDIT EMPLOYEE POP-UP MODAL -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="editEmployeeForm" action="update_employee.php" method="POST" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEmployeeModalLabel">
                            <i class="bi bi-person-gear me-2 text-primary"></i>Edit Employee Details
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Hidden input to pass employee ID -->
                        <input type="hidden" name="user_ID" id="modal_user_id">
                        <div class="row g-3">
                            <div class="col-md-6 mb-3">
                                <label for="modal_first_name" class="form-label">First Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="modal_first_name" name="first_name">
                                <div class="invalid-feedback" id="firstNameError">First name is required.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="modal_last_name" class="form-label">Last Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="modal_last_name" name="last_name">
                                <div class="invalid-feedback" id="lastNameError">Last name is required.</div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="modal_email" class="form-label">Email Address <span
                                        class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="modal_email" name="email">
                                <div class="invalid-feedback" id="emailError">Please enter a valid email address.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="modal_department" class="form-label">Department <span
                                        class="text-danger">*</span></label>
                                <select class="form-select" name="department_ID" id="modal_department" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['department_ID']; ?>">
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback" id="deptError">Please select a department.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="modal_status" class="form-label">Employee Status <span
                                        class="text-danger">*</span></label>
                                <select class="form-select" name="emp_status" id="modal_status" required>
                                    <option value="Active">Active</option>
                                    <option value="On Leave">On Leave</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save
                            Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle & jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Jguery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Flatpickr -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="notifications.js"></script>
    <script>
        // ==========================================
        // PAGE OPERATIONS
        // ==========================================
        $(document).ready(function() {
            const searchInput = $('#employeeSearchInput');
            const clearBtn = $('#clearSearchBtn');
            const filterForm = $('#filterForm');
            const urlParams = new URLSearchParams(window.location.search);

            // ==========================================
            // 1. Notification URL Cleaner
            // ==========================================
            if (urlParams.has('msg')) {
                const cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('msg');
                window.history.replaceState({}, document.title, cleanUrl.pathname + (cleanUrl.searchParams.toString() ? '?' + cleanUrl.searchParams.toString() : ''));
            }
            // ==========================================
            // 2. SEARCH AND CROSS BUTTON OPERATIONS (CONTINUOUS FOCUS)
            // ==========================================
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

            // WHEN THE CROSS BUTTON IS PRESSED
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
            // ==========================================
            // 3. FLATPICKR CALENDAR AND ICON MANAGEMENT
            // ==========================================
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

                // Reset when the cross button is clicked
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

        // ==========================================
        // 4. MODAL AND EDITING PROCESSES
        // ==========================================
        document.addEventListener('DOMContentLoaded', function() {
            const editModal = document.getElementById('editEmployeeModal');
            const editForm = document.getElementById('editEmployeeForm');
            let activeRow = null;

            // Automatic Alert Deactivation
            function initAlertAutoClose() {
                const successAlerts = document.querySelectorAll('.page-top-alert');
                successAlerts.forEach(function(alert) {
                    setTimeout(function() {
                        const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                        if (bsAlert) bsAlert.close();
                    }, 3500);
                });
            }
            initAlertAutoClose();

            if (editForm) {
                editForm.querySelectorAll('.form-control, .form-select').forEach(input => {
                    const clearError = function() {
                        this.classList.remove('is-invalid');
                        const container = this.closest('div');
                        if (container) {
                            const errorEl = container.querySelector('.invalid-feedback, div[id$="Error"]');
                            if (errorEl) errorEl.textContent = '';
                        }
                    };
                    input.addEventListener('input', clearError);
                    input.addEventListener('change', clearError);
                });
            }

            // Populating Data When Modal Opens
            if (editModal) {
                editModal.addEventListener('show.bs.modal', function(event) {
                    if (editForm) {
                        editForm.querySelectorAll('.form-control, .form-select').forEach(el => el.classList.remove('is-invalid'));
                        editForm.querySelectorAll('.invalid-feedback, div[id$="Error"]').forEach(el => el.textContent = '');
                    }

                    const button = event.relatedTarget;
                    if (!button) return;

                    activeRow = $(button).closest('tr');

                    document.getElementById('modal_user_id').value = button.getAttribute('data-id') || '';
                    document.getElementById('modal_first_name').value = button.getAttribute('data-name') || '';
                    document.getElementById('modal_last_name').value = button.getAttribute('data-lastname') || '';
                    document.getElementById('modal_email').value = button.getAttribute('data-email') || '';
                    document.getElementById('modal_department').value = button.getAttribute('data-dept') || '';
                    document.getElementById('modal_status').value = button.getAttribute('data-status') || '';
                });
            }

            // Saving Modal Form
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const submitBtn = editForm.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;

                    editForm.querySelectorAll('.form-control, .form-select').forEach(el => el.classList.remove('is-invalid'));
                    editForm.querySelectorAll('.invalid-feedback, div[id$="Error"]').forEach(el => el.textContent = '');

                    const fieldMap = {
                        'first_name': {
                            inputId: 'modal_first_name',
                            errorId: 'firstNameError'
                        },
                        'last_name': {
                            inputId: 'modal_last_name',
                            errorId: 'lastNameError'
                        },
                        'email': {
                            inputId: 'modal_email',
                            errorId: 'emailError'
                        },
                        'department_ID': {
                            inputId: 'modal_department',
                            errorId: 'deptError'
                        }
                    };

                    const formData = new FormData(editForm);

                    fetch('update_employee.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                const modalInstance = bootstrap.Modal.getInstance(editModal);
                                if (modalInstance) modalInstance.hide();

                                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                                document.body.classList.remove('modal-open');
                                document.body.style.overflow = '';
                                document.body.style.paddingRight = '';

                                const alertHtml = `
                        <div class="alert alert-success alert-dismissible fade show page-top-alert position-fixed top-0 end-0 m-3 shadow z-3 pe-5 rounded-3" role="alert" style="z-index: 1055;">
                            <i class="bi bi-check-circle-fill me-2"></i> Employee updated successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>`;
                                document.body.insertAdjacentHTML('beforeend', alertHtml);
                                initAlertAutoClose();
                                // Code for notifications
                                const updatedName = document.getElementById('modal_first_name').value;
                                const updatedLastName = document.getElementById('modal_last_name').value;


                                addNotification('Employee Updated', `${updatedName} ${updatedLastName}`, 'bi-person-gear');

                                if (activeRow && activeRow.length) {
                                    const newName = document.getElementById('modal_first_name').value;
                                    const newLastName = document.getElementById('modal_last_name').value;
                                    const newEmail = document.getElementById('modal_email').value;
                                    const newStatus = document.getElementById('modal_status').value;
                                    const deptSelect = document.getElementById('modal_department');
                                    const newDeptName = (deptSelect && deptSelect.selectedIndex !== -1) ? deptSelect.options[deptSelect.selectedIndex].text : '';
                                    const statusClass = newStatus.toLowerCase().replace(/\s+/g, '-');

                                    activeRow.find('td:eq(1)').html(`
                            <div>
                                <div class="fw-semibold">${newName} ${newLastName}</div>
                                <small class="text-muted" style="font-size: 0.78rem;">${newEmail}</small>
                            </div>`);

                                    activeRow.find('td:eq(2)').text(newDeptName);
                                    activeRow.find('td:eq(3)').html(`<span class="status-badge ${statusClass}">${newStatus}</span>`);

                                    const editBtn = activeRow.find('button[data-bs-target="#editEmployeeModal"]');
                                    if (editBtn.length) {
                                        editBtn.attr('data-name', newName);
                                        editBtn.attr('data-lastname', newLastName);
                                        editBtn.attr('data-email', newEmail);
                                        editBtn.attr('data-dept', deptSelect.value);
                                        editBtn.attr('data-status', newStatus);
                                    }
                                }

                            } else if (data.status === 'error' && data.errors) {
                                Object.keys(data.errors).forEach(field => {
                                    if (fieldMap[field]) {
                                        const inputEl = document.getElementById(fieldMap[field].inputId);
                                        const errorEl = document.getElementById(fieldMap[field].errorId);
                                        if (inputEl) inputEl.classList.add('is-invalid');
                                        if (errorEl) errorEl.textContent = data.errors[field];
                                    }
                                });
                            }
                        })
                        .catch(error => console.error('Fetch Error:', error))
                        .finally(() => {
                            submitBtn.disabled = false;
                        });
                });
            }
        });
    </script>
</body>

</html>