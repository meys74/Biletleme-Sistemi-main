<?php
require_once 'config.inc.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTC-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Link</title>

    <script src="https://code.jquery.com/jquery-4.0.0.min.js" integrity="sha256-OaVG6prZf4v69dPg6PhVattBXkcOWQB62pdZ3ORyrao=" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="login.css">
    <link rel="icon" type="image/x-icon" href="Images/GlowUpLogoClearBackground.ico">
    
    <script src="sendMail.js"></script>
    
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
            
                <form action="sendMail.inc.php" method="POST" id="mailForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                    <h1>Enter your email</h1>
                    <p>Please enter your email address to receive a one-time link.</p>
                    <div class="form-group mb-4">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="your.email@example.com" required>
                    </div>
                    <button type="submit" name="submit-btn" class="submit-btn" id="sendBtn">Send Link</button>

                    <input type="hidden" name="request_type" value="<?php echo $_GET['mail'] ?? ''; ?>">

                </form>
                
        </div>
    </div>
</body>
</html>
