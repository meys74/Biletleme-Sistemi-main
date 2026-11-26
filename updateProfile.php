<?php 

     require_once 'config.inc.php';
    require_once 'dbHandler.inc.php';

    if(!isset($_SESSION['userid'])){
        header("Location: signin.php");
        exit();
    }
    
    $user_ID = $_SESSION['userid'];

    $sql = "SELECT * FROM users WHERE user_ID=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_ID);
    mysqli_stmt_execute($stmt);
    
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt); 

    if (!$user) {
        session_destroy();
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
    $usernameError = "";
    $emailError = "";
    $phoneError = "";    
    $firstNameError = "";
    $lastNameError = "";
    $currentPasswordError= "";
    $newPasswordError= "";
    $userError = "";

    if(isset($_POST["saveChanges"])){
        $firstName = $_POST["firstName"];
        $lastName = $_POST["lastName"];
        $username =$_POST["username"];
        $email = $_POST["email"];
        $phone = $_POST["phone"];

        $currentPassword =$_POST["currentPassword"];
        $newPassword =$_POST["newPassword"];

        if(!empty($_FILES["profileImage"]["name"])) {
            $fileName = $_FILES["profileImage"]['name'];
            $tmpName = $_FILES['profileImage']['tmp_name'];
            move_uploaded_file($tmpName, "uploads/".$fileName);
            $profileImage = "uploads/" . $fileName;
        }else{
            $profileImage= $user["profile_image"];
        }
        

        if(empty($username) ){
            $usernameError = "username is required";
        }
        if(empty($email)){
            $emailError = "email is required";
        }elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $emailError = "Invalid email format";
        }
        if(empty($phone)){
            $phoneError = "phone is required";
        }elseif (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
            $phoneError = "Please enter a valid phone number.";
        }
        if(empty($firstName)){
            $firstNameError = "Firs Name is required";
        }
        if(empty($lastName)){
            $lastNameError = "Last Name is required";
        }
         
        $passwordToUpdate = $user["password"];

        $isChangingPassword =
            !empty($currentPassword) ||
            !empty($newPassword);

        if ($isChangingPassword) {

        /* Current password */

        if (empty($currentPassword)) {

            $currentPasswordError =
                "Current Password is required";
        }


        /* New password */

        if (empty($newPassword)) {

            $newPasswordError =
                "New Password is required";

        } elseif (strlen($newPassword) < 6) {

            $newPasswordError =
                "Password must be at least 6 characters long";
        }


        /* Check current password */

        if (
            empty($currentPasswordError) &&
            empty($newPasswordError)
        ) {

            if (
                !password_verify(
                    $currentPassword,
                    $user["password"]
                )
            ) {

                $currentPasswordError =
                    "Current password is incorrect";

            } else {

                /* Create new password hash */

                $passwordToUpdate =
                    password_hash(
                        $newPassword,
                        PASSWORD_BCRYPT
                    );
            }
        }
    }



        // Check if itmes are correct and update the database
    $hasChanges =
        $username != $user['username'] ||
        $email != $user['email'] ||
        $phone != $user['phone_number'] ||
        $firstName != $user['first_name'] ||
        $lastName != $user['last_name'] ||
        !empty($_FILES["profileImage"]["name"]) ;

        $hasPasswordChanges =
        $isChangingPassword;


        $hasAnyChanges =
            $hasChanges ||
            $hasPasswordChanges;
            
    // Check if itmes are correct and update the database    
        if (
            empty($usernameError) &&
            empty($emailError) &&
            empty($phoneError) &&
            empty($firstNameError) &&
            empty($lastNameError) &&
            empty($currentPasswordError) &&
            empty($newPasswordError)
        ) {
        
            if ($isChangingPassword) {

                if (
                    !password_verify(
                        $currentPassword,
                        $user['password']
                    )
                ) {
                    $currentPasswordError = "Current password is incorrect";
                }
            }

            if (!empty($currentPasswordError)) {

                $_SESSION["currentPasswordError"] = $currentPasswordError;

                switch ($_SESSION["roleid"]) {

                    case 1:
                        header("Location: updateAdminProfile.php");
                        break;
                    
                    case 2:
                        header("Location: updateEmployeeProfile.php");
                        break;
                    
                    case 3:
                        header("Location: updateCustomerProfile.php");
                        break;
                }
                    
                exit();
            }
            
            $hasChanges =
                $username != $user['username'] ||
                $email != $user['email'] ||
                $phone != $user['phone_number'] ||
                $firstName != $user['first_name'] ||
                $lastName != $user['last_name'] ||
                !empty($_FILES["profileImage"]["name"]);
                    
                    
            $hasPasswordChanges = $isChangingPassword;
                    
                    
            $hasAnyChanges =
                $hasChanges ||
                $hasPasswordChanges;
                    

            if (!$hasAnyChanges) {
                    
                switch ($_SESSION["roleid"]) {
                    
                    case 1:
                        header("Location: updateAdminProfile.php?updated=0");
                        break;
                    
                    case 2:
                        header("Location: updateEmployeeProfile.php?updated=0");
                        break;
                    
                    case 3:
                        header("Location: updateCustomerProfile.php?updated=0");
                        break;
                }
                    
                exit();
            }
                    
            $passwordToUpdate = $user['password'];
                    
            if ($isChangingPassword) {
                    
                $passwordToUpdate = password_hash(
                    $newPassword,
                    PASSWORD_BCRYPT
                );
            }
            
            $sql = "UPDATE users
                    SET username = ?,
                        email = ?,
                        phone_number = ?,
                        first_name = ?,
                        last_name = ?,
                        profile_image = ?,
                        password = ?
                    WHERE user_ID = ?";

                    
            $stmt = mysqli_prepare($conn, $sql);
                    
            mysqli_stmt_bind_param(
                $stmt,
                "sssssssi",
                $username,
                $email,
                $phone,
                $firstName,
                $lastName,
                $profileImage,
                $passwordToUpdate,
                $user_ID
            );
                    
                    
            mysqli_stmt_execute($stmt);
                    
            $affected = mysqli_stmt_affected_rows($stmt);
                    
            mysqli_stmt_close($stmt);
                    
            if ($affected > 0) {
                    
                $notificationMessage =
                    "Profile updated successfully.";
                    
                $notificationType =
                    "profile_update";
                    
                    
                $sql = "INSERT INTO notifications
                        (
                            user_ID,
                            notification_message,
                            notification_type
                        )
                        VALUES (?, ?, ?)";

                    
                $stmt = mysqli_prepare($conn, $sql);
                    
                mysqli_stmt_bind_param(
                    $stmt,
                    "iss",
                    $user_ID,
                    $notificationMessage,
                    $notificationType
                );
                    
                mysqli_stmt_execute($stmt);
                    
                mysqli_stmt_close($stmt);
                    
                    
                switch ($_SESSION["roleid"]) {
                    
                    case 1:
                        header("Location: updateAdminProfile.php?updated=1");
                        break;
                    
                    case 2:
                        header("Location: updateEmployeeProfile.php?updated=1");
                        break;
                    
                    case 3:
                        header("Location: updateCustomerProfile.php?updated=1");
                        break;
                }
                    
            } else {
                    
                switch ($_SESSION["roleid"]) {
                    
                    case 1:
                        header("Location: updateAdminProfile.php?updated=0");
                        break;
                    
                    case 2:
                        header("Location: updateEmployeeProfile.php?updated=0");
                        break;
                    
                    case 3:
                        header("Location: updateCustomerProfile.php?updated=0");
                        break;
                }
            }
                    
            exit();
        }                       
        $_SESSION["usernameError"] = $usernameError;
        $_SESSION["emailError"] = $emailError;
        $_SESSION["phoneError"] = $phoneError;
        $_SESSION["firstNameError"] = $firstNameError;
        $_SESSION["lastNameError"] = $lastNameError;
        $_SESSION["currentPasswordError"] = $currentPasswordError;
        $_SESSION["newPasswordError"] = $newPasswordError;
        $_SESSION["userError"] = $userError; 
        switch ($_SESSION["roleid"]) {
                        
            case 1:
                header("Location: updateAdminProfile.php");
                break;
            
            case 2:
                header("Location: updateEmployeeProfile.php");
                break;
            
            case 3:
                header("Location: updateCustomerProfile.php");
                break;
        }
            
        exit();
    
    }
}
?>