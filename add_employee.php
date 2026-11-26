<?php
ob_start();

require_once 'db.php';
require_once 'config.inc.php';

// 1. OTURUM VE YETKİ KONTROLÜ (Sayfa açılır açılmaz çalışmalı)
if (!isset($_SESSION['userid']) || !isset($_SESSION['roleid']) || $_SESSION['roleid'] != 1) {
    header("Location: signin.php");
    exit();
}

// 2. HATALARI VE ESKİ GİRDİLERİ TEMİZLE/AL
$errors = $_SESSION['errors'] ?? [];
$old = $_SESSION['old_input'] ?? [];

unset($_SESSION['errors']);
unset($_SESSION['old_input']);

// 3. DEPARTMANLARI ÇEK (Sayfa ilk açıldığında da çalışmalı ki dropdown dolsun!)
try {
    $dept_stmt = $db->query("SELECT department_ID, department_name FROM departments ORDER BY department_name ASC");
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}

// 4. SADECE FORM GÖNDERİLDİĞİNDE ÇALIŞACAK KISIM (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF Token kontrolü
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        die("Hata: CSRF Token bulunamadı. İstek reddedildi.");
    }

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Hata: Geçersiz CSRF Token. Güvenlik ihlali şüphesi.");
    }

    $first_name    = trim($_POST['first_name'] ?? '');
    $last_name     = trim($_POST['last_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $start_date    = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');

    $post_errors = [];

    if (empty($first_name)) {
        $post_errors['first_name'] = "First Name is required.";
    }
    if (empty($last_name)) {
        $post_errors['last_name'] = "Last Name is required.";
    }
    if (empty($email)) {
        $post_errors['email'] = "Email Address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $post_errors['email'] = "Please enter a valid email address format (e.g. alex.smith@gmail.com).";
    }

    if (!empty($phone)) {
        $phone_digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone_digits) < 7 || strlen($phone_digits) > 15) {
            $post_errors['phone'] = "Please enter a valid phone number (7 to 15 digits).";
        }
    }

    if (empty($post_errors['email'])) {
        $check_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $check_stmt->execute([':email' => $email]);
        if ($check_stmt->fetchColumn() > 0) {
            $post_errors['email'] = "This email address is already in use!";
        }
    }
    if (empty($_POST['department_id'])) {
        $post_errors['department_id'] = "Please select a department.";
    }

    if (!empty($post_errors)) {
        $_SESSION['errors'] = $post_errors;
        $_SESSION['old_input'] = $_POST;
        header("Location: add_employee.php");
        exit();
    }

    try {
        $temp_password = bin2hex(random_bytes(4));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (first_name, last_name, email, password, phone_number, role_ID, department_ID, join_date, emp_status) 
                VALUES (:first_name, :last_name, :email, :password, :phone, 2, :department_id, :join_date, 'Active')";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':first_name'    => $first_name,
            ':last_name'     => $last_name,
            ':email'         => $email,
            ':password'      => $hashed_password,
            ':phone'         => $phone,
            ':department_id' => $department_id,
            ':join_date'     => $start_date
        ]);

        $current_time = date('d M Y H:i');

        echo "<script>
        let notifications = JSON.parse(localStorage.getItem('adminNotifications')) || [];
        notifications.unshift({
            title: 'Employee Added',
            text: '" . addslashes($first_name . " " . $last_name) . "',
            icon: 'bi-person-plus',
            time: '" . $current_time . "'
        });
        localStorage.setItem('adminNotifications', JSON.stringify(notifications));
        window.location.href = 'employees.php?msg=added';
        </script>";
        exit();
    } catch (PDOException $e) {
        $_SESSION['errors']['general'] = "Database Error: " . $e->getMessage();
        $_SESSION['old_input'] = $_POST;
        header("Location: add_employee.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="Images/GlowUpLogo_Ellipse_Background.png">
    <title>Add New Employee - Glow Up</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css">
    <link rel="stylesheet" href="admin.css">
</head>

<body class="dashboard-body">
    <div class="dashboard-container">
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
                    <a href="worker_panel.php" class="menu-link"><i class="bi bi-ticket-perforated"></i> Tickets</a>
                    <a href="employees.php" class="menu-link active"><i class="bi bi-people-fill"></i> Employees</a>
                    <span class="menu-title mt-4">ACCOUNT</span>
                    <a href="updateAdminProfile.php" class="menu-link"> <i class="bi bi-person fs-5"></i> Profile</a>
                    <a href="signin.php" class="menu-link"><i class="bi bi-box-arrow-left"></i> Sign Out</a>
                </nav>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <div class="header-title">
                    <h2>Add Employee</h2>
                    <p>Create a new employee profile for your team.</p>
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
            </header>

            <section class="tickets-table-card">
                <div class="table-header border-bottom pb-3 mb-4">
                    <h3><i class="bi bi-person-plus-fill me-2"></i> Employee Details</h3>
                </div>

                <form action="add_employee.php" method="POST" class="row g-3" novalidate>
                    <!-- FIRST NAME -->
                    <div class="col-md-6">
                        <label for="first_name" class="form-label fw-semibold text-secondary small">FIRST NAME <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control custom-input <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>" id="first_name" placeholder="e.g. Jane" value="<?php echo htmlspecialchars($old['first_name'] ?? ''); ?>">
                        <div class="invalid-feedback" id="firstNameError">
                            <?php echo $errors['first_name'] ?? ''; ?>
                        </div>
                    </div>

                    <!-- LAST NAME -->
                    <div class="col-md-6">
                        <label for="last_name" class="form-label fw-semibold text-secondary small">LAST NAME <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="form-control custom-input <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>" id="last_name" placeholder="e.g. Doe" value="<?php echo htmlspecialchars($old['last_name'] ?? ''); ?>">
                        <div class="invalid-feedback" id="lastNameError">
                            <?php echo $errors['last_name'] ?? ''; ?>
                        </div>
                    </div>

                    <!-- EMAIL ADDRESS -->
                    <div class="col-md-6">
                        <label for="email" class="form-label fw-semibold text-secondary small">EMAIL ADDRESS <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control custom-input <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" placeholder="e.g. jane.doe@gmail.com" value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>">
                        <div class="invalid-feedback" id="emailError">
                            <?php echo $errors['email'] ?? ''; ?>
                        </div>
                    </div>

                    <!-- PHONE NUMBER -->
                    <div class="col-md-6">
                        <label for="phone" class="form-label fw-semibold text-secondary small">PHONE NUMBER</label>
                        <input type="tel" name="phone" class="form-control custom-input <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" id="phone" value="<?php echo htmlspecialchars($old['phone'] ?? ''); ?>">
                        <div class="invalid-feedback" id="phoneError">
                            <?php echo $errors['phone'] ?? ''; ?>
                        </div>
                    </div>

                    <!-- DEPARTMENT -->
                    <div class="col-md-6">
                        <label for="department" class="form-label fw-semibold text-secondary small">DEPARTMENT <span class="text-danger">*</span></label>
                        <select name="department_id" class="form-select filter-select w-100 <?php echo isset($errors['department_id']) ? 'is-invalid' : ''; ?>" id="department">
                            <option selected value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_ID']; ?>" <?php echo (isset($old['department_id']) && $old['department_id'] == $dept['department_ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback" id="deptError">
                            <?php echo $errors['department_id'] ?? ''; ?>
                        </div>
                    </div>

                    <!-- START DATE -->
                    <div class="col-md-6">
                        <label for="start_date" class="form-label fw-semibold text-secondary small">START DATE</label>
                        <input type="date" name="start_date" class="form-control custom-input" id="start_date" value="<?php echo htmlspecialchars($old['start_date'] ?? date('Y-m-d')); ?>">
                    </div>

                    <!-- BUTTONS -->
                    <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="employees.php" class="btn btn-outline-secondary px-4 me-2">Cancel</a>
                        <button type="submit" class="add-employee-btn border-0 px-4 py-2">
                            <i class="bi bi-check-lg me-1"></i> Save Employee
                        </button>
                    </div>
                </form>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
    <script src="notifications.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const phoneInput = document.querySelector("#phone");
            const emailInput = document.querySelector("#email");
            const firstNameInput = document.querySelector("#first_name");
            const lastNameInput = document.querySelector("#last_name");
            const form = document.querySelector("form");
            const deptInput = document.querySelector("#department");
            const startDateInput = document.querySelector("#start_date");

            // OPEN THE CALENDAR
            if (startDateInput) {
                startDateInput.addEventListener("click", function() {
                    if (typeof this.showPicker === "function") {
                        this.showPicker();
                    }
                });
            }

            // Function to write the error message below the relevant box
            function setFieldError(inputEl, errorElId, message) {
                if (inputEl) inputEl.classList.add("is-invalid");
                const errorEl = document.getElementById(errorElId);
                if (errorEl) errorEl.textContent = message;
            }

            // Clear the red frame and error message
            document.querySelectorAll(".form-control, .form-select").forEach(function(inputElement) {
                const clearError = function() {
                    this.classList.remove("is-invalid");
                    const container = this.closest("div");
                    if (container) {
                        const errorEl = container.querySelector(".invalid-feedback");
                        if (errorEl) errorEl.textContent = "";
                    }
                };
                inputElement.addEventListener("input", clearError);
                inputElement.addEventListener("change", clearError);
            });

            // 1. The Phone Library
            let iti = null;
            if (phoneInput) {
                iti = window.intlTelInput(phoneInput, {
                    initialCountry: "tr",
                    preferredCountries: ["tr", "uz", "de", "gb", "us"],
                    separateDialCode: true,
                    autoPlaceholder: "off",
                    utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
                });

                phoneInput.setAttribute("placeholder", "Mobile number");

                phoneInput.addEventListener("input", function() {
                    this.value = this.value.replace(/[^\d\s]/g, '');
                    this.classList.remove("is-invalid");
                    const phoneError = document.getElementById("phoneError");
                    if (phoneError) phoneError.textContent = "";
                });

                phoneInput.addEventListener("countrychange", function() {
                    phoneInput.value = "";
                    phoneInput.setAttribute("placeholder", "Mobile number");
                });
            }

            // 2. Frontend Validation Before Form Submission
            if (form) {
                form.addEventListener("submit", function(e) {
                    let hasError = false;


                    document.querySelectorAll(".form-control, .form-select").forEach(el => el.classList.remove("is-invalid"));
                    document.querySelectorAll(".invalid-feedback").forEach(el => el.textContent = "");

                    // First Name
                    if (firstNameInput && firstNameInput.value.trim() === "") {
                        setFieldError(firstNameInput, "firstNameError", "First Name is required.");
                        hasError = true;
                    }

                    // Last Name
                    if (lastNameInput && lastNameInput.value.trim() === "") {
                        setFieldError(lastNameInput, "lastNameError", "Last Name is required.");
                        hasError = true;
                    }

                    if (deptInput && deptInput.value === "") {
                        setFieldError(deptInput, "deptError", "Please select a department.");
                        hasError = true;
                    }

                    // Email
                    const emailVal = emailInput ? emailInput.value.trim() : "";
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                    if (emailVal === "") {
                        setFieldError(emailInput, "emailError", "Email Address is required.");
                        hasError = true;
                    } else if (!emailRegex.test(emailVal)) {
                        setFieldError(emailInput, "emailError", "Please enter a valid email address.");
                        hasError = true;
                    }

                    // Phone
                    if (phoneInput) {
                        const rawValue = phoneInput.value.trim();
                        if (rawValue !== "" && iti && !iti.isValidNumber()) {
                            setFieldError(phoneInput, "phoneError", "Please enter a valid phone number.");
                            hasError = true;
                        }
                    }

                    // If there are any errors, do not submit the form.
                    if (hasError) {
                        e.preventDefault();
                        return false;
                    }

                    // Convert phone number to international format if there are no errors
                    if (phoneInput && iti && phoneInput.value.trim() !== "") {
                        phoneInput.value = iti.getNumber();
                    }
                });
            }
        });
    </script>
</body>

</html>