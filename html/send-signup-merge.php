<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/dbconnection.php';
require_once 'includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: signup-merge-prompt.php');
    exit;
}

$email = $_SESSION['merge_email'] ?? '';
if (empty($email)) {
    header('Location: signup.php');
    exit;
}

// 1. Fetch user to get ID and ensure they are still OAuth-only (safety check)
$stmt = $dbh->prepare("SELECT ID, FullName, Password FROM tbluser WHERE Email = :email LIMIT 1");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_OBJ);

if (!$user || !empty($user->Password)) {
    // Either user doesn't exist or already has a password. No merge needed.
    unset($_SESSION['merge_email']);
    header('Location: signin.php');
    exit;
}

// 2. Generate token and send email
try {
    $token = hbms_create_set_password_token($dbh, (int)$user->ID);
    $result = hbms_send_set_password_email(
        (string)$email,
        (string)$user->FullName,
        $token
    );
} catch (Throwable $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
}

header('Location: signup-merge-prompt.php?msg=' . ($result['ok'] ? 'sent' : 'error'));
exit;
