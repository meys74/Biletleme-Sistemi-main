<?php
// Canlı ortam (Production) Güvenlik Ayarları

// 1. Hataların kullanıcıya (tarayıcıya) gösterilmesini tamamen kapat
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// 2. Tüm hataları raporla (fakat ekrana basmayacak, sadece loglayacak)
error_reporting(E_ALL);

// 3. Hataları log dosyasına yazılmasını aktif et
ini_set('log_errors', '1');

// 4. Log dosyasının yolunu belirtin (Mutlaka public_html/www klasörünün DIŞINDA bir yer olmalı)
ini_set('error_log', __DIR__ . '/secure_directory/php_errors.log');

$is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

// Session'ı başlatmadan ÖNCE yapılandırma ayarlarını veriyoruz
$session_options = [
    'use_strict_mode' => true,      
    'cookie_httponly' => true,      
    'cookie_secure'   => $is_secure,      
    //'cookie_samesite' => 'Strict',
    'cookie_samesite' => 'Lax',//e-postalardan gelen dış linkler için buna gectik
    // DİKKAT: 24 saatlik oturum istiyorsak, sunucunun çerezi erken silmemesi için bu süreyi 86400 (24 saat) yapıyoruz.
    'gc_maxlifetime'  => 86400       
];

// Oturumu güvenli ayarlarla başlat
session_start($session_options);

// --- 1. CSRF TOKEN OLUŞTURMA ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- 2. 24 SAAT (86400 Saniye) MUTLAK ZAMAN AŞIMI (ABSOLUTE TIMEOUT) ---
// Kullanıcı giriş yapmışsa ve oturum süresi 24 saati geçmişse otomatik çıkış yaptırılır.
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $max_session_time = 60 * 60 * 24; // 24 Saat (Saniye cinsinden)
    
    if (!isset($_SESSION['session_created_at'])) {
        $_SESSION['session_created_at'] = time();
    } elseif (time() - $_SESSION['session_created_at'] >= $max_session_time) {
        // 24 saat dolmuş. Oturumu temizle ve yok et.
        session_unset();
        session_destroy();
        
        // Kullanıcıyı tekrar giriş sayfasına yönlendir.
        header("Location: signin.php?timeout=1");
        exit();
    }
}

// --- 3. PERİYODİK SESSION REGENERATE (Videodaki Mantık) ---
// Her 30 dakikada bir oturum kimliğini (ID) yenileyerek çalınma riskini minimize ediyoruz.
$regeneration_interval = 60 * 30; // 30 Dakika

if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} else {
    if (time() - $_SESSION['last_regeneration'] >= $regeneration_interval) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// KULLANICI GİRİŞ YAPTIĞINDA (Login aşaması)
// Not: Bu fonksiyonu giriş doğrulama kodunun (loginUser) içinde, şifre doğruysa çağırmalısın.
function userLoggedIn($kullanici_id) {
    // ÇOK KRİTİK: Kullanıcı yetki aldığında eski oturum ID'sini silip yepyeni bir ID üretiriz.
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $kullanici_id;
    $_SESSION['logged_in'] = true;
    
    // Zamanlayıcıları sıfırlıyoruz ki süreler giriş yaptığı andan itibaren başlasın
    $_SESSION['session_created_at'] = time();
    $_SESSION['last_regeneration'] = time();
    
    // Tarayıcı parmak izi
    //$_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
}

// HER SAYFA YÜKLENDİĞİNDE (Oturum kontrolü aşaması)
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Oturumu çalan kişi Tarayıcı değiştirmiş mi kontrolü
    if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        
        // Şüpheli durum: Oturumu yok et ve çıkış yaptır
        session_unset();
        session_destroy();
        die("Güvenlik ihlali algılandı. Lütfen yeniden giriş yapın.");
    }
}
?>