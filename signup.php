<?php
require_once 'config.inc.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTC-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Sign In</title>

    <script src="https://code.jquery.com/jquery-4.0.0.min.js" integrity="sha256-OaVG6prZf4v69dPg6PhVattBXkcOWQB62pdZ3ORyrao=" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/css/intlTelInput.css">
    <link rel="stylesheet" href="login.css">
    <link rel="icon" type="image/x-icon" href="Images/GlowUpLogoClearBackground.ico">
    <script src="signup.js"></script>

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
    <div class="main-container">
        <div class="left-panel">
            <div class="logo-header">
                <img src="Images/GlowUpLogoClear.png" class="brand-logo">
                <h1>Glow Up</h1>
            </div>

            <div class="hero-content slide-anim">
                <span class="eyebrow">TICKETING & SERVICE SYSTEM</span>
                <h2>Every issue tracked.<br>Every request resolved.</h2>
                <p>Glow up centralizes your support tickets, service requests, and team workflows — giving customers transparency and agents the clarity to act fast. From the first report to final resolution, every step is visible.</p>
            </div>

            <div class="timeline slide-anim">

                <div class="active step">
                    <div class="circle"></div>
                    <span>Open</span>
                </div>

                <div class="line"></div>

                <div class="active step">
                    <div class="circle"></div>
                    <span>In Progress</span>
                </div>

                <div class="line"></div>

                <div class="current step">
                    <div class="circle pulse-dot"></div>
                    <span>Resolved</span>
                </div>

                <div class="line"></div>

                <div class="step">
                    <div class="circle"></div>
                    <span>Closed</span>
                </div>

            </div>
        </div>

        <div class="right-panel">
            <div class="form-wrapper border border-secondary border-1 fade-anim">

                <form action="signup.inc.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                <div id="step-1" >
                    <h1>Sign up</h1>
                        <p>Create an account to get started.</p>
                    
                    <div class="form-group mb-4">
                        <label for="first-name">First Name</label>
                        <input type="text" id="first-name" name="first-name" placeholder="Your name" minlength="2" maxlength="100" required>
                        <small id="name-message"></small>
                    </div>
                    <div class="form-group mb-4">
                        <label for="last-name">Last Name</label>
                        <input type="text" id="last-name" name="last-name" placeholder="Your surname" minlength="2" maxlength="100" required>
                        <small id="surname-message"></small>
                    </div>
                    <div class="form-group mb-4">
                        <label for="phone-number">Phone Number</label>
                        <input type="tel" id="phone-number"  name="phone-number" minlength="10" maxlength="15" required>
                        <small id="number-message"></small>
                    </div>
                    
                    <button type="button" id="nextBtn" class="submit-btn">Continue</button>

                    <div class="have-account-text">
                        <small>Already have an account? <a href="signin.php">Sign in</a></small>
                    </div>
                    
                </div>

                <div id="step-2" style="display: none;">
                    
                    <h2>Almost there</h2>
                    <p>Create an account to get started.</p>
                        
                        <div class="form-group mb-4">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" placeholder="Your username" minlength="3" maxlength="50" required>
                            <small id="username-message"></small>
                        </div>

                        <div class="form-group mb-4">
                            <label for="email">Email Address</label> 
                            <input type="email" id="email" name="email" placeholder="your.email@example.com" required>
                            <small id="email-message"></small>
                        </div>

                        <div class="form-group mb-4">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="••••••••••" minlength="6" required>  
                            <small id="pwd-message"></small>                  
                        </div>

                        <div class="form-group mb-4">
                            <label for="confirm-password">Confirm Password</label>
                            <input type="password" id="confirm-password" name="confirm-password" placeholder="••••••••••" minlength="6" required>
                            <small id="pwdConfirm-message"></small>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" id="prevBtn" class="btn">Back</button>
                            <button type="submit" name="submit-btn" class="submit-btn">Create Account</button>
                        </div>

                    </div>

                </form>   

            </div>
        </div>
    </div>
</body>
</html>