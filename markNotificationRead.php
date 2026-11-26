<?php 
 require_once 'config.inc.php';
require_once 'dbHandler.inc.php';

if(!isset($_SESSION['userid'])) {
    header("Location: signin.php");
    exit();
}

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
    $notification_ID = $_POST['notification_ID'];

    $sql = "UPDATE notifications 
        SET is_read =1
        WHERE notification_ID = ?
        AND user_ID = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt, 
        "ii",
        $notification_ID,
        $_SESSION['userid']
    );
    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);
}

