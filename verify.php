<?php
    require_once 'config.inc.php';
    require_once 'dbHandler.inc.php';
    require_once 'functions.inc.php';

    if (isset($_GET['token']) && isset($_GET['email'])) {
        $url_token = $_GET['token'];
        $email = $_GET['email'];
        $hashed_submitted_token = hash('sha256', $url_token);

        $sql = "SELECT * FROM users WHERE email = ? AND verify_token = ? AND token_expires_at > NOW();";
        $stmt = mysqli_stmt_init($conn);

        if ( !mysqli_stmt_prepare($stmt, $sql) ) {
            $_SESSION['error'] = "Connection failed.";
            header("location: signin.php");
            exit();
        }

        mysqli_stmt_bind_param($stmt, "ss", $email, $hashed_submitted_token);
        mysqli_stmt_execute($stmt);

        $resultData = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($resultData);

        mysqli_stmt_close($stmt);

        if($row){
            if( $row['verify_token'] == NULL ) {
                $_SESSION['error'] = "Your link has expired. Please request a new verify link.";
                header("location: sendMail.php?mail=verify");
                exit();
            }

            if (!hash_equals($row['verify_token'], $hashed_submitted_token)) {
                $_SESSION['error'] = "Invalid token.";
                header("location: sendMail.php?mail=verify");
                exit();
            }

            $sql2 = "UPDATE users SET is_verified = 1, verify_token = NULL, token_expires_at = NULL WHERE verify_token = ?;";
            $stmt2 = mysqli_stmt_init($conn);

            if( !mysqli_stmt_prepare($stmt2, $sql2) ) {
                $_SESSION['error'] = "Connection failed.";
                header("location: signin.php");
                exit();
            }

            mysqli_stmt_bind_param($stmt2, "s", $hashed_submitted_token);
            mysqli_stmt_execute($stmt2);
            
            mysqli_stmt_close($stmt2);


            $_SESSION['success'] = "Account verified successfully. Now you can sign in.";
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

            $_SESSION['error'] = "Your link has expired. Please request a new verify link.";
            header("location: sendMail.php?mail=verify");
            exit();
        }

    }
    else{
        header("location: signin.php");
    }


?>