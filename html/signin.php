<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

$flash = $_GET['msg'] ?? '';
$inlineError = '';

if (isset($_POST['login'])) {
    $email    = trim((string)($_POST['email']    ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $stmt = $dbh->prepare(
        "SELECT ID, Email, Password, FullName, auth_method
         FROM tbluser WHERE Email = :email LIMIT 1"
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$user) {
        $inlineError = 'Invalid email or password.';
    } elseif (empty($user->Password)) {
        // OAuth-only account: no password set yet → resend Set Password email.
        require_once 'includes/mailer.php';
        $token = hbms_create_set_password_token($dbh, (int)$user->ID, 30);
        hbms_send_set_password_email($user->Email, (string)$user->FullName, $token);
        header('Location: signin.php?msg=set_password_sent');
        exit;
    } else {
        $stored = (string)$user->Password;
        $ok     = false;

        // Bcrypt (preferred) — hashes start with $2y$, $2a$, etc.
        if (password_verify($password, $stored)) {
            $ok = true;
        } elseif (preg_match('/^[a-f0-9]{32}$/i', $stored) && md5($password) === $stored) {
            // Legacy md5 — accept once, then upgrade to bcrypt.
            $ok = true;
            $upgrade = $dbh->prepare("UPDATE tbluser SET Password = :pwd WHERE ID = :id");
            $upgrade->execute([
                ':pwd' => password_hash($password, PASSWORD_BCRYPT),
                ':id'  => $user->ID,
            ]);
        }

        if ($ok) {
            $_SESSION['hbmsuid'] = $user->ID;
            $_SESSION['login']   = $user->Email;
            echo "<script type='text/javascript'> document.location ='index.php'; </script>";
            exit;
        }

        $inlineError = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Hotel Booking Management System | Hotel :: Login Page</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link href="css/bootstrap.css" rel="stylesheet" type="text/css" media="all">
<link href="css/style.css" rel="stylesheet" type="text/css" media="all" />
<link href="css/auth.css" rel="stylesheet" type="text/css" media="all" />

<script type="application/x-javascript"> addEventListener("load", function() { setTimeout(hideURLbar, 0); }, false); function hideURLbar(){ window.scrollTo(0,1); } </script>
<script src="js/jquery-1.11.1.min.js"></script>
<script src="js/bootstrap.js"></script>
<script src="js/responsiveslides.min.js"></script>
 <script>
    $(function () {
      $("#slider").responsiveSlides({
      	auto: true,
      	nav: true,
      	speed: 500,
        namespace: "callbacks",
        pager: true,
      });
    });
  </script>

</head>
<body>
		<!--header-->
			<div class="header head-top">
				<div class="container">
			<?php include_once('includes/header.php');?>
		</div>
</div>
<!--header-->

			<div class="content">
				<div class="contact">
				<div class="container">

					<h2>If you have an account with us, please log in.</h2>

					<?php if ($flash === 'set_password_sent'): ?>
						<div style="background:#e7f7ec; border:1px solid #b7e0c2; color:#205c32;
						            padding:12px 16px; border-radius:6px; max-width:640px; margin:14px 0;">
							If an account exists for that email, we just sent a Set Password link.
							Please check your inbox (and spam folder).
						</div>
					<?php elseif ($flash === 'already_has_password'): ?>
						<div style="background:#fffbe6; border:1px solid #f5e3a3; color:#705c10;
						            padding:12px 16px; border-radius:6px; max-width:640px; margin:14px 0;">
							This account already has a password. Use it to sign in, or use
							<a href="forgot-password.php">forgot password</a>.
						</div>
					<?php elseif ($flash === 'link_cancelled'): ?>
						<div style="background:#fffbe6; border:1px solid #f5e3a3; color:#705c10;
						            padding:12px 16px; border-radius:6px; max-width:640px; margin:14px 0;">
							Account linking cancelled. Your existing account is unchanged —
							sign in with your email and password as usual.
						</div>
					<?php endif; ?>

					<?php if ($inlineError): ?>
						<div style="background:#fdecea; border:1px solid #f5b5b0; color:#7a1f17;
						            padding:12px 16px; border-radius:6px; max-width:640px; margin:14px 0;">
							<?php echo htmlentities($inlineError); ?>
						</div>
					<?php endif; ?>

				<div class="contact-grids">

						<div class="col-md-6 contact-right">
							<div class="auth-form-wrapper">
								<form method="post">
									<h5>Email Address</h5>
									<input type="email" class="form-control" value="" name="email" required="true">
									<h5>Password</h5>
									<input type="password" value="" class="form-control" name="password" required="true">
									
									<div class="auth-link-wrapper">
										<a href="forgot-password.php">Forgot your password?</a>
									</div>

									<div class="auth-button-group">
										<input type="submit" value="LOGIN" name="login" class="btn-auth-primary btn-submit">
									</div>
								</form>

								<div class="or-divider">or log in with</div>

								<div class="auth-button-group">
									<a href="google-callback.php" class="btn-auth-primary btn-google">
										<span class="google-icon-wrapper">
											<svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.08 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-3.59-13.46-8.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
										</span>
										<span>Sign in with Google</span>
									</a>

									<!-- Microsoft Button -->
									<a href="oauth-callback.php" class="btn-auth-primary btn-microsoft">
										<span class="oauth-icon">
											<svg width="18" height="18" viewBox="0 0 21 21"><rect x="1" y="1" width="9" height="9" fill="#F25022"/><rect x="11" y="1" width="9" height="9" fill="#7FBA00"/><rect x="1" y="11" width="9" height="9" fill="#00A4EF"/><rect x="11" y="11" width="9" height="9" fill="#FFB900"/></svg>
										</span>
										<span>Sign in with Microsoft</span>
									</a>
								</div>
							</div>
						</div>
						<div class="clearfix"></div>
					</div>
				</div>
			</div>
		<?php include_once('includes/getintouch.php');?>
			</div>
			<?php include_once('includes/footer.php');?>
</html>
