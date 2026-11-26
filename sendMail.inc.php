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

    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        $email = $_POST['email'] ?? '';
        $request = $_POST['request_type'] ?? '';


        $userRecord = UidExists($conn, $email, $email);

        if ($userRecord['last_mail_sent'] !== NULL) {
            $last_mail_time = strtotime($userRecord['last_mail_sent']);
            
            $time_query = mysqli_query($conn, "SELECT NOW() as current_db_time");
            $time_row = mysqli_fetch_assoc($time_query);
            $current_time = strtotime($time_row['current_db_time']);
            
            $countdown = 60;
            $waiting_time = $current_time - $last_mail_time;
            
            if ($waiting_time >= 0 && $waiting_time < $countdown) {
                $time_left = $countdown - $waiting_time;
                $_SESSION['error'] = "Please wait for $time_left seconds before sending another request.";
                header("location: sendMail.php?mail=" . $request);
                exit();
            }
        }
 
        if ( empty($email) ) {
            $_SESSION['error'] = "Please fill all the inputs.";
            header("location: sendMail.php?mail=" . $request);
            exit();
        }
        if ( !filter_var($email, FILTER_VALIDATE_EMAIL) ) {
            $_SESSION['error'] = "Invalid email. Check if the email is correct.";
            header("location: sendMail.php?mail=" . $request);
            exit();
        }

        if ( UidExists($conn, $email, $email) === false ) {
            $_SESSION['error'] = "Incorrect email. Check if the email is correct.";
            header("location: sendMail.php?mail=" . $request);
            exit();
        }
        if ( isUserVerified($conn, $email) && $request === "verify" ) {
            $_SESSION['error'] = "Your email is already verified. You can sign in.";
            header("location: signin.php");
            exit();
        }

        if($request === "reset"){ 
            $verify_token = randToken($conn, $email);
            sendResetEmail($email, $verify_token);

            //Deneme için sonra sil.
            //$verify_link = "http://localhost/Biletleme-Sistemi/resetPassword.php?email=" . $email . "&resettoken=" . $verify_token;
            //$log_content = "Kime: $email \nOnay Linki: $verify_link \nTarih: " . date("Y-m-d H:i:s") . "\nFOR PASSWORD RESET\n-----------------------\n";
           //file_put_contents('deneme mailler/mailler.txt', $log_content, FILE_APPEND);

            $sql_update_time = "UPDATE users SET last_mail_sent = NOW() WHERE email = ?";
            $stmt_time = mysqli_stmt_init($conn);
            if (mysqli_stmt_prepare($stmt_time, $sql_update_time)) {
                mysqli_stmt_bind_param($stmt_time, "s", $email);
                mysqli_stmt_execute($stmt_time);
                mysqli_stmt_close($stmt_time);
            }

            $_SESSION['success'] = "Link sent successfully. Please check your email.";
            header("location: sendMail.php?mail=reset");
            exit();
        }
        elseif($request === "verify"){ 
            $verify_token = randToken($conn, $email);
            sendVerificationEmail($email, $verify_token);

            //DEneme için sonra sil.
            //$verify_link = "http://localhost/Biletleme-Sistemi/verify.php?email=" . $email . "&token=" . $verify_token;
            //$log_content = "Kime: $email \nOnay Linki: $verify_link \nTarih: " . date("Y-m-d H:i:s") . "\nFOR EMAIL VERIFICATION\n-----------------------\n";
            //file_put_contents('deneme mailler/mailler.txt', $log_content, FILE_APPEND);

            $sql_update_time = "UPDATE users SET last_mail_sent = NOW() WHERE email = ?";
            $stmt_time = mysqli_stmt_init($conn);
            if (mysqli_stmt_prepare($stmt_time, $sql_update_time)) {
                mysqli_stmt_bind_param($stmt_time, "s", $email);
                mysqli_stmt_execute($stmt_time);
                mysqli_stmt_close($stmt_time);
            }

            $_SESSION['success'] = "Link sent successfully. Please check your email.";
            header("location: sendMail.php?mail=verify");
            exit();
        }
        else{
            $_SESSION['error'] = "Invalid request.";
            header("location: sendMail.php?mail=" . $request);
            exit();
        }
        
        
    }
    else{
        $request = $_GET['mail'] ?? 'verify';
        header("location: sendMail.php?mail=" . $request);
        exit();
    }

}