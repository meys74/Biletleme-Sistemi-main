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
        
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm-password'];
        $first_name = $_POST['first-name'];
        $last_name = $_POST['last-name'];
        $phone_number = $_POST['phone-number'];

        if ( empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name) || empty($phone_number) ) {
            $_SESSION['error'] = "Please fill all the inputs.";
            header("location: signup.php");
            exit();
        }
        if ( !preg_match( "/^[a-zA-Z0-9]*$/", $username) ) {
            $_SESSION['error'] = "Invalid username. Try something else.";
            header("location: signup.php");
            exit();
        }
        if ( !filter_var($email, FILTER_VALIDATE_EMAIL) ) {
            $_SESSION['error'] = "Invalid email. Check if the email is correct.";
            header("location: signup.php");
            exit();
        }
        if ( $password !== $confirm_password ) {
            $_SESSION['error'] = "Passwords don't match. Try again.";
            header("location: signup.php");
            exit();
        }
        if (strlen($password) < 6) {
            $_SESSION['error'] = "Password must be at least 6 characters long.";
            header("location: signup.php");
            exit();
        }

        if ( UidExists($conn, $username, $email) !== false ) {
            $_SESSION['error'] = "Username or email already exists.";
            header("location: signup.php");
            exit();
        }


        createUser($conn, $username, $email, $password, $first_name, $last_name, $phone_number);
    }
    else{
        header("location: signup.php");
    }

}