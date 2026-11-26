<?php

require_once 'config.inc.php';
require_once 'dbHandler.inc.php';
require_once 'functions.inc.php';


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
        
    if (isset($_POST["submit-btn"])) {
        
        $email = $_POST['email'];
        $resettoken = $_POST['token'];
        $hashed_submitted_token = hash('sha256', $resettoken);
        $new_password = $_POST['password'];
        $confirm_new_password = $_POST['confirm-password'];

        if ( empty($new_password) || empty($confirm_new_password) ) {
            $_SESSION['error'] = "Please fill all the inputs.";
            header("location: resetPassword.php?email=" . $email . "&resettoken=" . $resettoken);
            exit();
        }
        if ( $new_password !== $confirm_new_password ) {
            $_SESSION['error'] = "Passwords don't match. Try again.";
            header("location: resetPassword.php?email=" . $email . "&resettoken=" . $resettoken);
            exit();
        }
        if (strlen($new_password) < 6) {
            $_SESSION['error'] = "Password must be at least 6 characters long.";
            header("location: resetPassword.php?email=" . $email . "&resettoken=" . $resettoken);
            exit();
        }
        if ( UidExists($conn, $email, $email) === false ) {
            $_SESSION['error'] = "Incorrect email. Check if the email is correct.";
            header("location: sendMail.php?mail=reset");
            exit();
        }
        $sql = "SELECT * FROM users WHERE email = ? AND verify_token = ? AND token_expires_at > NOW();";
        $stmt = mysqli_stmt_init($conn);

        if (!mysqli_stmt_prepare($stmt, $sql)) {
            $_SESSION['error'] = "Connection failed.";
            header("location: signin.php");
            exit();
        }

        mysqli_stmt_bind_param($stmt, "ss", $email, $hashed_submitted_token);
        mysqli_stmt_execute($stmt);

        $resultData = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($resultData);

        mysqli_stmt_close($stmt);

        if ($row) {
            if( $row['verify_token'] == NULL ) {
                $_SESSION['error'] = "Your link has expired. Please request a new password reset link.";
                header("location: sendMail.php?mail=reset");
                exit();
            }

            if (!hash_equals($row['verify_token'], $hashed_submitted_token)) {
                $_SESSION['error'] = "Invalid token.";
                header("location: sendMail.php?mail=reset");
                exit();
            }

            resetPassword($conn, $email, $new_password);

            $sql2 = "UPDATE users SET verify_token = NULL, token_expires_at = NULL WHERE email = ?;";
            $stmt2 = mysqli_stmt_init($conn);

            if( !mysqli_stmt_prepare($stmt2, $sql2) ) {
                $_SESSION['error'] = "Connection failed.";
                header("location: signin.php");
                exit();
            }

            mysqli_stmt_bind_param($stmt2, "s", $email);
            mysqli_stmt_execute($stmt2);
        
            mysqli_stmt_close($stmt2);
            

            $_SESSION['success'] = "Password reset successfully. You can now login with your new password.";
            header("location: signin.php");
            exit();

        }
        else {
            
            $sql_clear = "UPDATE users SET verify_token = NULL, token_expires_at = NULL WHERE email = ?";
            $stmt_clear = mysqli_stmt_init($conn);

            if (!mysqli_stmt_prepare($stmt_clear, $sql_clear)) {
                $_SESSION['error'] = "Connection failed.";
                header("location: signin.php");
                exit();     
            }
            mysqli_stmt_bind_param($stmt_clear, "s", $email);
            mysqli_stmt_execute($stmt_clear);
            mysqli_stmt_close($stmt_clear);

            $_SESSION['error'] = "Your link has expired. Please request a new password reset link.";
            header("location: sendMail.php?mail=reset");
            exit();
        }
    }
}
else{
    header("location: resetPassword.php");
}

