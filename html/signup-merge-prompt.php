<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

$email = $_SESSION['merge_email'] ?? '';
if (empty($email)) {
    header('Location: signup.php');
    exit;
}

$flash = $_GET['msg'] ?? '';
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Hotel Booking Management System | Account Merge</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link href="css/bootstrap.css" rel="stylesheet" type="text/css" media="all">
<link href="css/style.css" rel="stylesheet" type="text/css" media="all" />
<link href="css/auth.css" rel="stylesheet" type="text/css" media="all" />
<script src="js/jquery-1.11.1.min.js"></script>
<script src="js/bootstrap.js"></script>
</head>
<body>
    <div class="header head-top">
        <div class="container">
            <?php include_once('includes/header.php'); ?>
        </div>
    </div>

    <div class="content">
        <div class="contact">
            <div class="container">
                <div class="contact-grids">
                    <div class="col-md-8 contact-right">
                        <div class="auth-form-wrapper" style="max-width: 700px;">
                            <h2>Account Already Exists via Social Login</h2>
                            <br>
                            <p style="font-size:16px; color:#444; line-height: 1.6;">
                                An account with the email <strong><?php echo htmlentities($email); ?></strong> is already registered using Google or Microsoft login.
                            </p>
                            <p style="font-size:16px; color:#444; line-height: 1.6; margin-top: 10px;">
                                Would you like to add a local password to this account? This will allow you to sign in using either your social account or your email and password.
                            </p>

                            <?php if ($flash === 'sent'): ?>
                                <div style="background:#e7f7ec; border:1px solid #b7e0c2; color:#205c32;
                                            padding:12px 16px; border-radius:6px; margin:20px 0;">
                                    <strong>Success!</strong> We've sent a confirmation link to <strong><?php echo htmlentities($email); ?></strong>.
                                    Please check your email to set up your local password.
                                </div>
                            <?php elseif ($flash === 'error'): ?>
                                <div style="background:#fdecea; border:1px solid #f5b5b0; color:#7a1f17;
                                            padding:12px 16px; border-radius:6px; margin:20px 0;">
                                    We couldn't send the confirmation email. Please try again in a moment.
                                </div>
                            <?php endif; ?>

                            <div style="margin-top:30px; display: flex; gap: 15px;">
                                <form method="post" action="send-signup-merge.php">
                                    <button type="submit" class="btn-auth-primary btn-submit" style="padding: 12px 25px; width: auto !important;">
                                        Yes, send me a confirmation email
                                    </button>
                                </form>

                                <a href="signin.php" class="btn-auth-primary" style="background:#f3f4f6; color:#374151; border:1px solid #d1d5db; padding: 12px 25px; width: auto !important; text-decoration: none; text-align: center;">
                                    No, I'll use Social Login
                                </a>
                            </div>

                            <p style="margin-top:30px; color:#6b7280; font-size:14px;">
                                For security reasons, we need to verify your email address before adding a local password to your existing account.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once('includes/getintouch.php'); ?>
    <?php include_once('includes/footer.php'); ?>
</body>
</html>
