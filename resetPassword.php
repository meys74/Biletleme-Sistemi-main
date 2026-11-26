<?php
require_once 'config.inc.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTC-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>

    <script src="https://code.jquery.com/jquery-4.0.0.min.js" integrity="sha256-OaVG6prZf4v69dPg6PhVattBXkcOWQB62pdZ3ORyrao=" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="login.css">
    <link rel="icon" type="image/x-icon" href="Images/GlowUpLogoClearBackground.ico">

    <script src="signup.js"></script>
    <script>
        $(document).ready(function() {
            $("#tempAlert").delay(3000).fadeOut(500, function() {
                $(this).remove();
            });

            $('#email').on('blur', function(){
                let emailValue = $(this).val();

                if (emailValue.length > 4) {
                    $.ajax({
                        url: 'ajax_check.inc.php',
                        type: 'POST',
                        data: { email_check: emailValue },
                        dataType: 'json',
                        success: function(result) {
                            if (result.exists) {
                                $('#email-message').css('color', 'green').text('');
                                $('#email').css('border-color', 'green');
                            } else {
                                $('#email-message').css('color', 'red').text('Invalid email.');
                                $('#email').css('border-color', 'red');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("AJAX error:", error);
                        }
                    });
                }
                else{
                    $('#email-message').css('color', 'red').text('Empty input');
                    $('#email').css('border-color', 'red');
                }
            });

        });
    </script>
</head>
<body>
    <?php
        if ( isset($_SESSION["error"]) ){
            echo "<div id='tempAlert' class='alert alert-danger shadow d-flex align-items-center' style='position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999;'>
            <div>
                <p style='color: red; margin: 0; font-weight: bold;'>" . $_SESSION['error'] . "</p>
            </div>
            </div>";

            unset($_SESSION['error']);
        }
        if ( isset($_SESSION["success"]) ){
            echo "<div id='tempAlert' class='alert alert-success shadow d-flex align-items-center' style='position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999;'>
            <div>
                <p style='color: green; margin: 0; font-weight: bold;'>" . $_SESSION['success'] . "</p>
            </div>
            </div>";

            unset($_SESSION['success']);
        }
    ?>
    <div class="reset-password-container">
        <div class="reset-password-form fade-anim">
            
                <form action="resetPassword.inc.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['resettoken'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                    <h1>Reset Password</h1>
                    <p>Enter your new password.</p>
                
                    <div class="form-group mb-4">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" placeholder="••••••••••" minlength="6" required>
                        <small id="pwd-message"></small>
                    </div>
                    <div class="form-group mb-4">
                        <label for="confirm-password">Confirm Password</label>
                        <input type="password" id="confirm-password" name="confirm-password" placeholder="••••••••••" minlength="6" required>
                        <small id="pwdConfirm-message"></small>
                    </div>

                    <button type="submit" name="submit-btn" class="submit-btn">Reset Password</button>
                    
                </form>
        </div>
    </div>
</body>
</html>