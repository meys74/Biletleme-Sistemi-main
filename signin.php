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
    <link rel="stylesheet" href="login.css">
    <link rel="icon" type="image/x-icon" href="Images/GlowUpLogoClearBackground.ico">

    <script>
        $(document).ready(function() {
            $("#tempAlert").delay(3000).fadeOut(500, function() {
                $(this).remove();
            });
            $("#NotTempAlert").on('click', function() {
                $(this).delay(200).fadeOut(500, function() {
                    $(this).remove();
                });
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
        if( isset($_SESSION["dismissable_error"]) ){
            echo "<div id='NotTempAlert' class='alert alert-danger alert-dismissible fade show shadow d-flex align-items-center' role='alert' style='position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999;'>
            <div>
                <p style='color: red; margin: 0; font-weight: bold;'>" . $_SESSION['dismissable_error'] . "</p>
            </div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>";

        unset($_SESSION['dismissable_error']);
        }
    ?>
    <div class="main-container">
        <div class="left-panel" >
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
                    <div class="circle  pulse-dot"></div>
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
        
                <form action="signin.inc.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                <h1>Welcome back</h1>
                <p>Enter your credentials to continue.</p>
                <div class="form-group mb-4">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="your.email@example.com" required>
                </div>
                <div class="form-group mb-4">
                    <div class="label-row">
                        <label for="password">Password</label>
                        <div class="signup-and-password-text">
                            <small><a class="icon-link icon-link-hover" style="--bs-icon-link-transform: translate3d(0, -.125rem, 0);" href="sendMail.php?mail=reset">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-unlock2-fill" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M8 0c1.07 0 2.041.42 2.759 1.104l.14.14.062.08a.5.5 0 0 1-.71.675l-.076-.066-.216-.205A3 3 0 0 0 5 4v2h6.5A2.5 2.5 0 0 1 14 8.5v5a2.5 2.5 0 0 1-2.5 2.5h-7A2.5 2.5 0 0 1 2 13.5v-5a2.5 2.5 0 0 1 2-2.45V4a4 4 0 0 1 4-4"/>
                        </svg>
                            Forgot Password</a></small>
                        </div>
                    </div>
                        
                    <input type="password" id="password" name="password" placeholder="••••••••••" required>                   
                </div>
                
                
                <button type="submit" name="submit-btn" class="submit-btn">Sign in</button>
                
                <div class="have-account-text">
                    <small>Don't have an account? <a href="signup.php">Create one</a></small>
                </div>
                
                </form>

            </div>
        </div>
        
    </div>
</body>
</html>