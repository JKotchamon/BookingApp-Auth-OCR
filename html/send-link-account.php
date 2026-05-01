<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/dbconnection.php';
require_once 'includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: link-account-prompt.php');
    exit;
}

$pending = $_SESSION['pending_link'] ?? null;
if (!is_array($pending) || empty($pending['user_id']) || empty($pending['email'])) {
    header('Location: signin.php');
    exit;
}

$userId   = (int)$pending['user_id'];
$email    = (string)$pending['email'];
$provider = (string)($pending['provider'] ?? 'google');

// Re-confirm the user still exists and is the same person (defence in depth).
$stmt = $dbh->prepare("SELECT ID, FullName, Email FROM tbluser WHERE ID = :id LIMIT 1");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_OBJ);

if (!$user || strcasecmp((string)$user->Email, $email) !== 0) {
    unset($_SESSION['pending_link']);
    header('Location: signin.php');
    exit;
}

try {
    $token = hbms_create_account_link_token($dbh, $userId, [
        'provider'         => $provider,
        'provider_user_id' => (string)$pending['provider_user_id'],
        'provider_email'   => (string)$pending['provider_email'],
        'full_name'        => (string)($pending['full_name']     ?? ''),
        'photo_path'       =>           $pending['photo_path']    ?? null,
        'date_of_birth'    =>           $pending['date_of_birth'] ?? null,
    ]);

    $result = hbms_send_account_link_email(
        (string)$user->Email,
        (string)$user->FullName,
        $token,
        $provider
    );
} catch (Throwable $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
}

header('Location: link-account-prompt.php?msg=' . ($result['ok'] ? 'sent' : 'error'));
exit;
