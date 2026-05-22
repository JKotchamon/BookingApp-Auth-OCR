<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$token = is_string($token) ? trim($token) : '';

$error  = '';
$done   = false;
$user   = null;

function fetch_valid_token(PDO $dbh, string $token)
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return null;
    }
    $stmt = $dbh->prepare(
        "SELECT t.ID AS TokenID, t.UserID, t.ExpiresAt, t.UsedAt,
                u.Email, u.FullName, u.Password, u.auth_method
         FROM tbl_password_set_tokens t
         JOIN tbluser u ON u.ID = t.UserID
         WHERE t.Token = :tok
         LIMIT 1"
    );
    $stmt->execute([':tok' => $token]);
    return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
}

$tokenRow = fetch_valid_token($dbh, $token);

if (!$tokenRow) {
    $error = 'This link is invalid. Please request a new Set Password email.';
} elseif ($tokenRow->UsedAt !== null) {
    $error = 'This link has already been used. Please request a new Set Password email.';
} elseif (strtotime($tokenRow->ExpiresAt) < time()) {
    $error = 'This link has expired. Please request a new Set Password email.';
}

// Handle form submission
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_password'])) {
    $newPassword     = (string)($_POST['newpassword']     ?? '');
    $confirmPassword = (string)($_POST['confirmpassword'] ?? '');

    if (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);

        $dbh->beginTransaction();
        try {
            $upd = $dbh->prepare(
                "UPDATE tbluser
                 SET Password = :pwd, auth_method = 'both'
                 WHERE ID = :id"
            );
            $upd->execute([':pwd' => $hash, ':id' => $tokenRow->UserID]);

            $mark = $dbh->prepare(
                "UPDATE tbl_password_set_tokens SET UsedAt = NOW() WHERE ID = :tid"
            );
            $mark->execute([':tid' => $tokenRow->TokenID]);

            $dbh->commit();
            $done = true;
        } catch (Throwable $e) {
            $dbh->rollBack();
            $error = 'Could not save your password. Please try again.';
        }
    }
}
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Hotel Booking Management System | Set Your Password</title>
<link href="css/bootstrap.css" rel="stylesheet" type="text/css" media="all">
<link href="css/style.css" rel="stylesheet" type="text/css" media="all" />
<script src="js/jquery-1.11.1.min.js"></script>
<script src="js/bootstrap.js"></script>
<script type="text/javascript">
function checkpass() {
    var a = document.setpwd.newpassword.value;
    var b = document.setpwd.confirmpassword.value;
    if (a.length < 8) {
        alert('Password must be at least 8 characters long.');
        document.setpwd.newpassword.focus();
        return false;
    }
    if (a !== b) {
        alert('New Password and Confirm Password do not match.');
        document.setpwd.confirmpassword.focus();
        return false;
    }
    return true;
}
</script>
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
                <h2>Set your password</h2>

                <?php if ($done): ?>
                    <div class="col-md-6 contact-right">
                        <div style="background:#e7f7ec; border:1px solid #b7e0c2; color:#205c32;
                                    padding:14px 18px; border-radius:6px;">
                            <strong>All set!</strong> Your password has been saved.
                            You can now log in with either Google or your email + password.
                        </div>
                        <p style="margin-top:18px;">
                            <a href="signin.php" style="color:#2563eb;">Go to login &rarr;</a>
                            &nbsp;·&nbsp;
                            <a href="index.php">Back to home</a>
                        </p>
                    </div>
                <?php elseif ($error): ?>
                    <div class="col-md-6 contact-right">
                        <div style="background:#fdecea; border:1px solid #f5b5b0; color:#7a1f17;
                                    padding:14px 18px; border-radius:6px;">
                            <?php echo htmlentities($error); ?>
                        </div>
                        <p style="margin-top:18px;">
                            <a href="signin.php" style="color:#2563eb;">Back to sign in</a>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="contact-grids">
                        <div class="col-md-6 contact-right">
                            <p style="color:#444;">
                                Setting password for
                                <strong><?php echo htmlentities($tokenRow->Email); ?></strong>.
                            </p>
                            <form method="post" name="setpwd" onsubmit="return checkpass();">
                                <input type="hidden" name="token"
                                       value="<?php echo htmlentities($token); ?>">

                                <h5>New Password</h5>
                                <input type="password" name="newpassword"
                                       class="form-control" required minlength="8"
                                       placeholder="At least 8 characters">

                                <h5>Confirm Password</h5>
                                <input type="password" name="confirmpassword"
                                       class="form-control" required minlength="8">

                                <br>
                                <input type="submit" name="set_password" value="Save Password" 
                                       style="background:#2563eb; color:#fff; border:0; padding:12px 22px; border-radius:6px; font-weight:600; cursor:pointer;">
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include_once('includes/getintouch.php'); ?>
    <?php include_once('includes/footer.php'); ?>
</body>
</html>
