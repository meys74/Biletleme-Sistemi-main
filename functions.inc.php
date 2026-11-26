<?php

require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

function UidExists( $conn, $username, $email ){
    $sql = "SELECT * FROM users WHERE username = ? OR email = ?;";
    $stmt = mysqli_stmt_init($conn);
    if ( !mysqli_stmt_prepare($stmt, $sql) ) {
        $_SESSION['error'] = "Connection failed.";
        header("location: signup.php");
        exit();
    }
    mysqli_stmt_bind_param($stmt, "ss", $username, $email);
    mysqli_stmt_execute($stmt);

    $resultData = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($resultData)) {
        return $row;
    }
    else{
        $result = false;
        return $result;
    }

    mysqli_stmt_close($stmt);
}

function createUserID($conn){
    $isUnique = false;
    $user_id = 0;

    while(!$isUnique){
        $user_id = random_int(100000, 999999);
        
        $sql = "SELECT user_ID FROM users WHERE user_ID = ?;";
        $stmt = mysqli_stmt_init($conn);

        if ( !mysqli_stmt_prepare($stmt, $sql) ) {
            $_SESSION['error'] = "Connection failed.";
            header("location: signup.php");
            exit();
        }

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $resultData = mysqli_stmt_get_result($stmt);

        if (!mysqli_fetch_assoc($resultData)) {
            $isUnique = true;
        }
        mysqli_stmt_close($stmt);
    }

    return $user_id;
}

function createUser( $conn, $username, $email, $password, $first_name, $last_name, $phone_number){
    $user_id = createUserID($conn);
    $options = [
        'cost' => 12
    ];
    $hashed_password = password_hash($password, PASSWORD_BCRYPT,$options);

    $sql = "INSERT INTO users (user_ID, username, first_name, last_name, email, password, phone_number, join_date) VALUES (?,?,?,?,?,?,?,NOW());";
    $stmt = mysqli_stmt_init($conn);

    if ( !mysqli_stmt_prepare($stmt, $sql) ) {
        $_SESSION['error'] = "Connection failed.";
        header("location: signup.php");
        exit();
    }

    mysqli_stmt_bind_param($stmt, "issssss", $user_id, $username, $first_name, $last_name, $email, $hashed_password, $phone_number);
    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);

    $_SESSION['success'] = "Account created successfully. Please re-enter your email to get sent verification mail.";
    header("location: sendMail.php?mail=verify");
    exit();
    
}

function loginUser($conn, $email, $password){
    $uid_exists = UidExists( $conn, $email, $email );

    if($uid_exists === false){
        $_SESSION['error'] = "Password or email is incorrect. Please try again.";
        header("location: signin.php");
        exit();
    }

    $password_hashed = $uid_exists["password"];
    $check_password = password_verify($password, $password_hashed);

    if($check_password === false){
        $_SESSION['error'] = "Password or email is incorrect. Please try again.";
        header("location: signin.php");
        exit();
    }
    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long.";
        header("location: signin.php");
        exit();
    }
    elseif ($check_password === true) {
        if(isUserVerified($conn, $email) == 0){
            $_SESSION['dismissable_error'] = "You have not verified your email yet. " 
            . "<a class='icon-link icon-link-hover verify-link' style='--bs-link-hover-color-rgb: 196, 55, 55;' href='sendMail.php?mail=verify'>
            Click here to send mail again
            </a>";
            header("location: signin.php");
            exit();
        }

        require_once 'config.inc.php';
        userLoggedIn($uid_exists["user_ID"]);
        $_SESSION["userid"] = $uid_exists["user_ID"];
        $_SESSION["usrnme"] = $uid_exists["username"];
        $_SESSION["roleid"] = $uid_exists["role_ID"];
        $_SESSION["email"]  = $uid_exists["email"];

        session_write_close();

        if(isset($_SESSION['roleid'])){
            switch($_SESSION["roleid"]){
                case 1:
                    header("location: admin.php");
                    break;
                case 2:
                    header("location: worker_panel.php");
                    break;
                case 3:
                    header("location: customerTicket.php");
                    break;
                default:
                    session_start();
                    $_SESSION['error'] = "Unauthorized login.";
                    header("location: signin.php");
                        break;
            }
        } else {
            session_start();
            $_SESSION['error'] = "Role ID not set. Please contact support.";
            header("location: signin.php");
        }

        exit();
    }
}

function isUserVerified($conn, $email){
    $sql = "SELECT is_verified FROM users WHERE email = ?;";
    $stmt = mysqli_stmt_init($conn);

    if ( !mysqli_stmt_prepare($stmt, $sql) ) {
        $_SESSION['error'] = "Connection failed.";
        header("location: signin.php");
        exit();
    }

    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);

    $resultData = mysqli_stmt_get_result($stmt);
    $result = mysqli_fetch_assoc($resultData);

    mysqli_stmt_close($stmt);

    if ($result) {
        return $result['is_verified']; 
    } else {
        return 0;
    }
}

function sendVerificationEmail($email, $verify_token){ 
    $mail = new PHPMailer(true);

    try {
        // SMTP Sunucu Ayarları
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Örnek olarak Gmail kullanıyoruz
        $mail->SMTPAuth   = true;
        $mail->Username   = 'service.glowupcorp@gmail.com'; // Kendi gönderici mailiniz
        // DİKKAT: Buraya normal Gmail şifrenizi değil, Google hesabınızdan alacağınız "Uygulama Şifresi"ni (App Password) girmelisiniz.
        $mail->Password   = 'utct nxal zmbr ldtu'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Gönderici ve Alıcı
        $mail->setFrom('service.glowupcorp@gmail.com', 'Glow Up');
        $mail->addAddress($email);

        // İçerik Ayarları
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Verify Your Email Address for Glow Up';

        // URL'yi oluşturuyoruz (Kendi klasör yolunuza göre localhost adresini ayarlayın)
        // urlencode() fonksiyonu e-posta adresindeki @ gibi işaretlerin linki bozmamasını sağlar
        $verify_link = "http://localhost/Biletleme-Sistemi/verify.php?email=" . $email . "&token=" . $verify_token;

        // Mailin Gövdesi (HTML formatında)
        $mail->Body = "
            <h2>Welcome to Glow Up!</h2>
            <p>To activate your account, please click the following link:</p>
            <br>
            <a href='{$verify_link}' style='padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Hesabımı Doğrula</a>
            <p>Expires in 1 hour.</p>
            <br><br>
            <p>If you can't click the button, you can copy paste this link to your browser:</p>
            <p>{$verify_link}</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Hata durumunda loglama yapılabilir: $mail->ErrorInfo;
        return false;
    }
}
function sendResetEmail($email, $verify_token){ 
    $mail = new PHPMailer(true);

    try {
        // SMTP Sunucu Ayarları
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Örnek olarak Gmail kullanıyoruz
        $mail->SMTPAuth   = true;
        $mail->Username   = 'service.glowupcorp@gmail.com'; // Kendi gönderici mailiniz
        // DİKKAT: Buraya normal Gmail şifrenizi değil, Google hesabınızdan alacağınız "Uygulama Şifresi"ni (App Password) girmelisiniz.
        $mail->Password   = 'utct nxal zmbr ldtu'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Gönderici ve Alıcı
        $mail->setFrom('service.glowupcorp@gmail.com', 'Glow Up');
        $mail->addAddress($email);

        // İçerik Ayarları
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Account Password Reset for Glow Up';

        // URL'yi oluşturuyoruz (Kendi klasör yolunuza göre localhost adresini ayarlayın)
        // urlencode() fonksiyonu e-posta adresindeki @ gibi işaretlerin linki bozmamasını sağlar
        $verify_link = "http://localhost/Biletleme-Sistemi/resetPassword.php?email=" . $email . "&resettoken=" . $verify_token;

        // Mailin Gövdesi (HTML formatında)
        $mail->Body = "
            <h2>Reset Your Password</h2>
            <p>To reset your password, please click the following link:</p>
            <br>
            <a href='{$verify_link}' style='padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a>
            <p>Expires in 1 hour.</p>
            <br><br>
            <p>If you can't click the button, you can copy paste this link to your browser:</p>
            <p>{$verify_link}</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Hata durumunda loglama yapılabilir: $mail->ErrorInfo;
        return false;
    }
}


function resetPassword($conn, $email, $new_password){
    $options = [
        'cost' => 12
    ];
    $hashed_new_password = password_hash($new_password, PASSWORD_BCRYPT,$options);

    $sql = "UPDATE users SET password = ? WHERE email = ?;";
    $stmt = mysqli_stmt_init($conn);

    if ( !mysqli_stmt_prepare($stmt, $sql) ) {
        $_SESSION['error'] = "Connection failed.";
        header("location: resetPassword.php");
        exit();
    }

    mysqli_stmt_bind_param($stmt, "ss", $hashed_new_password, $email);
    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);
}

function randToken($conn, $email){
     $token = bin2hex(random_bytes(32));
     $hashed_token = hash('sha256', $token);
     //$expires_at = date('Y-m-d H:i:s', time() + 3600);
     
     $sql = "UPDATE users SET verify_token = ?, token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?;";
     $stmt = mysqli_stmt_init($conn);

     if ( !mysqli_stmt_prepare($stmt, $sql) ) {
         $_SESSION['error'] = "Connection failed.";
         header("location: signin.php");
         exit();
     }

     mysqli_stmt_bind_param($stmt, "ss", $hashed_token, $email);
     mysqli_stmt_execute($stmt);

     mysqli_stmt_close($stmt);
     return $token;
 }