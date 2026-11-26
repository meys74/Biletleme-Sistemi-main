<?php
    require_once 'config.inc.php';
    header('Content-Type: application/json');
    require_once 'dbHandler.inc.php';

    if (isset($_POST['username_check'])) {
        $username = $_POST['username_check'];

        $sql = "SELECT username FROM users WHERE username = ?;";
        $stmt = mysqli_stmt_init($conn);
        
        if (mysqli_stmt_prepare($stmt, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            $resultCheck = mysqli_stmt_num_rows($stmt);
            
            if ($resultCheck > 0) {
                echo json_encode(['exists' => true]);
            } else {
                echo json_encode(['exists' => false]);
            }
        }
        mysqli_stmt_close($stmt);
        exit();
    }
    elseif (isset($_POST['email_check'])) {
        $email = $_POST['email_check'];

        $sql = "SELECT email FROM users WHERE email = ?;";
        $stmt = mysqli_stmt_init($conn);
        
        if (mysqli_stmt_prepare($stmt, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            $resultCheck = mysqli_stmt_num_rows($stmt);
            
            if ($resultCheck > 0) {
                echo json_encode(['exists' => true]);
            } else {
                echo json_encode(['exists' => false]);
            }
        }
        mysqli_stmt_close($stmt);
        exit();
    }
    else{
        $_SESSION['error'] = "Something went wrong.";
        header("location: signin.php");
        exit();
    }

