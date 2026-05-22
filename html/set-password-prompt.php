<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (empty($_SESSION['hbmsuid'])) {
    header('Location: signin.php');
    exit;
}

$uid = (int)$_SESSION['hbmsuid'];
$stmt = $dbh->prepare("SELECT FullName, Email, Password, auth_method FROM tbluser WHERE ID = :id");
$stmt->execute([':id' => $uid]);
$user = $stmt->fetch(PDO::FETCH_OBJ);

if (!$user) {
    header('Location: logout.php');
    exit;
}

// User already has a local password — nothing to set up, send them home.
if (!empty($user->Password)) {
    header('Location: index.php');
    exit;
}

$flash = $_GET['msg'] ?? '';
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Hotel Booking Management System | Set Password</title>
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
                <h2 style="margin-bottom: 20px;">Welcome, <?php echo htmlentities($user->FullName ?: 'there'); ?>!</h2>
                <p style="font-size:16px; color:#444; max-width:640px;">
                    You're currently signed in with <strong>Google</strong>.
                    Would you like to set a password so you can also log in with email + password?
                </p>

                <?php if ($flash === 'sent'): ?>
                    <div style="background:#e7f7ec; border:1px solid #b7e0c2; color:#205c32;
                                padding:12px 16px; border-radius:6px; max-width:640px; margin:14px 0;">
                        We've sent a Set Password link to
                        <strong><?php echo htmlentities($user->Email); ?></strong>.
                        Please check your inbox (and spam folder) — the link expires in 30 minutes.
                    </div>
                <?php elseif ($flash === 'error'): ?>
                    <div style="background:#fdecea; border:1px solid #f5b5b0; color:#7a1f17;
                                padding:12px 16px; border-radius:6px; max-width:640px; margin:14px 0;">
                        We couldn't send the email. Please try again in a moment.
                    </div>
                <?php endif; ?>

                <div style="margin-top:18px;">
                    <form method="post" action="send-set-password.php" style="display:inline-block; margin-right:10px;">
                        <button type="submit"
                                style="background:#111; color:#fff; border:0; padding:12px 22px;
                                       border-radius:6px; font-weight:600; cursor:pointer;">
                            Yes, send me a Set Password email
                        </button>
                    </form>

                    <a href="index.php"
                       style="display:inline-block; background:#f3f4f6; color:#374151; border:1px solid #d1d5db;
                              padding:12px 22px; border-radius:6px; font-weight:600; text-decoration:none;">
                        Skip for now
                    </a>
                </div>

                <p style="margin-top:24px; color:#777; font-size:13px;">
                    You can always set a password later from your profile.
                </p>
            </div>
        </div>
    </div>

    <?php include_once('includes/getintouch.php'); ?>
    <?php include_once('includes/footer.php'); ?>
</body>
</html>
