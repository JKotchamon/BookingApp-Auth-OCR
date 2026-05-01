<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/dbconnection.php';
require_once 'includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: set-password-prompt.php');
    exit;
}

// Two entry modes:
//   A) Logged-in OAuth user clicked "Yes, send me a Set Password email"
//   B) Anonymous user tried to log in with email but has no password
//      — submitted with form field `email` to resend the link.
$userId = null;
$user   = null;

if (!empty($_SESSION['hbmsuid'])) {
    $stmt = $dbh->prepare("SELECT ID, FullName, Email, Password FROM tbluser WHERE ID = :id");
    $stmt->execute([':id' => (int)$_SESSION['hbmsuid']]);
    $user = $stmt->fetch(PDO::FETCH_OBJ);
} elseif (!empty($_POST['email'])) {
    $email = trim($_POST['email']);
    $stmt  = $dbh->prepare("SELECT ID, FullName, Email, Password FROM tbluser WHERE Email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_OBJ);
}

if (!$user) {
    // Generic response — don't leak whether the email exists.
    header('Location: signin.php?msg=set_password_sent');
    exit;
}

// If the user already has a local password, do not flood them with set-password emails.
if (!empty($user->Password)) {
    if (!empty($_SESSION['hbmsuid'])) {
        header('Location: index.php');
    } else {
        header('Location: signin.php?msg=already_has_password');
    }
    exit;
}

try {
    $token  = hbms_create_set_password_token($dbh, (int)$user->ID, 30);
    $result = hbms_send_set_password_email($user->Email, (string)$user->FullName, $token);
} catch (Throwable $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
}

if (!empty($_SESSION['hbmsuid'])) {
    header('Location: set-password-prompt.php?msg=' . ($result['ok'] ? 'sent' : 'error'));
} else {
    header('Location: signin.php?msg=set_password_sent');
}
exit;
