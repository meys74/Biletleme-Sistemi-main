<?php
ob_start();
require_once 'db.php';
require_once 'config.inc.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_ID = $_POST['user_ID'] ?? null;
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department_ID = $_POST['department_ID'] ?? null;
    $emp_status = $_POST['emp_status'] ?? 'Active';

    $errors = [];
    $hasEmpty = false;

    // 1. Empty Field Checks
    if (empty($first_name)) {
        $errors['first_name'] = 'First name is required.';
        $hasEmpty = true;
    }
    if (empty($last_name)) {
        $errors['last_name'] = 'Last name is required.';
        $hasEmpty = true;
    }
    if (empty($department_ID)) {
        $errors['department_ID'] = 'Please select a department.';
        $hasEmpty = true;
    }

    // 2. Email Validations
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
        $hasEmpty = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } else {
        // Check database for email conflict even if department is empty
        try {
            $checkQuery = "SELECT user_ID FROM users WHERE email = :email AND user_ID != :user_ID";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([':email' => $email, ':user_ID' => $user_ID]);

            if ($checkStmt->fetch()) {
                $errors['email'] = 'This email address is already in use.';
            }
        } catch (PDOException $e) {
            // DB Error
        }
    }

    // Return all errors at once if any exist
    if (!empty($errors)) {
        ob_end_clean();
        echo json_encode([
            'status' => 'error',
            'errors' => $errors,
            'message' => $hasEmpty ? 'Please fill in all required fields!' : 'Please correct the errors in the highlighted fields.'
        ]);
        exit();
    }

    // 3. Update Process (If No Errors)
    try {
        $updateQuery = "UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, department_ID = :department_ID, emp_status = :emp_status WHERE user_ID = :user_ID";
        $stmt = $db->prepare($updateQuery);
        $stmt->execute([
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':email' => $email,
            ':department_ID' => $department_ID,
            ':emp_status' => $emp_status,
            ':user_ID' => $user_ID
        ]);

        ob_end_clean();
        echo json_encode(['status' => 'success']);
        exit();
    } catch (PDOException $e) {
        ob_end_clean();
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        exit();
    }
}
