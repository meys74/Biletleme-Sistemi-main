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
        $password = $_POST['password'];

        if ( empty($email) || empty($password) ) {
            $_SESSION['error'] = "Please fill all the inputs.";
            header("location: signin.php");
            exit();
        }
        if (strlen($password) < 6) {
            $_SESSION['error'] = "Password must be at least 6 characters long.";
            header("location: signin.php");
            exit();
        }

        loginUser($conn, $email, $password);

    }
    else{
        header("location: signin.php");
        exit();
    }

}