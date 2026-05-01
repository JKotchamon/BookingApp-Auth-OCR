<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// This page is reachable only after an OAuth callback detected an existing
// LOCAL account that needs explicit consent before being linked. The OAuth
// callback stashes a $_SESSION['pending_link'] payload for us to consume.
$pending = $_SESSION['pending_link'] ?? null;
if (!is_array($pending) || empty($pending['user_id']) || empty($pending['email'])) {
    header('Location: signin.php');
    exit;
}

$provider      = (string)($pending['provider'] ?? 'google');
$providerLabel = ucfirst($provider);
$email         = (string)$pending['email'];
$displayName   = $pending['full_name_local'] !== ''
    ? $pending['full_name_local']
    : ($pending['full_name'] ?? '');

$flash = $_GET['msg'] ?? '';
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Hotel Booking Management System | Link <?php echo htmlentities($providerLabel); ?> Account</title>
<link href="css/bootstrap.css" rel="stylesheet" type="text/css" media="all">
<link href="css/style.css" rel="stylesheet" type="text/css" media="all" />
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
                <h2>Link your <?php echo htmlentities($providerLabel); ?> account?</h2>

                <p style="font-size:16px; color:#444; max-width:640px;">
                    An account with the email
                    <strong><?php echo htmlentities($email); ?></strong>
                    already exists on HBMS Hotel Booking.
                    Would you like to link your <strong><?php echo htmlentities($providerLabel); ?></strong>
                    account to it so you can sign in either way?
                </p>

                <p style="font-size:14px; color:#666; max-width:640px;">
                    To protect your existing account, we'll email you a one-time confirmation
                    link before anything is changed.
                </p>

                <?php if ($flash === 'sent'): ?>
                    <div style="background:#e7f7ec; border:1px solid #b7e0c2; color:#205c32;
                                padding:12px 16px; border-radius:6px; max-width:640px; margin:14px 0;">
                        We just sent a confirmation link to
                        <strong><?php echo htmlentities($email); ?></strong>.
                        Open the email and click the button to finish linking your
                        <?php echo htmlentities($providerLabel); ?> account.
                        The link expires in 30 minutes.
                    </div>
                <?php elseif ($flash === 'error'): ?>
                    <div style="background:#fdecea; border:1px solid #f5b5b0; color:#7a1f17;
                                padding:12px 16px; border-radius:6px; max-width:640px; margin:14px 0;">
                        We couldn't send the confirmation email. Please try again in a moment.
                    </div>
                <?php endif; ?>

                <div style="margin-top:18px;">
                    <form method="post" action="send-link-account.php" style="display:inline-block; margin-right:10px;">
                        <button type="submit"
                                style="background:#2563eb; color:#fff; border:0; padding:12px 22px;
                                       border-radius:6px; font-weight:600; cursor:pointer;">
                            Yes, send me a confirmation email
                        </button>
                    </form>

                    <form method="post" action="link-account-cancel.php" style="display:inline-block;">
                        <button type="submit"
                                style="background:#f3f4f6; color:#374151; border:1px solid #d1d5db;
                                       padding:12px 22px; border-radius:6px; font-weight:600; cursor:pointer;">
                            No, cancel
                        </button>
                    </form>
                </div>

                <p style="margin-top:24px; color:#777; font-size:13px;">
                    If you cancel, your existing account will be left unchanged and you can
                    keep signing in with your password as usual.
                </p>
            </div>
        </div>
    </div>

    <?php include_once('includes/getintouch.php'); ?>
    <?php include_once('includes/footer.php'); ?>
</body>
</html>
