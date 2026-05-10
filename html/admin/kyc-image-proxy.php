<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// 1. Auth Check: Only logged in admins allowed
if (strlen($_SESSION['hbmsaid'] == 0)) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

// 2. Input Validation
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header('HTTP/1.0 400 Bad Request');
    exit;
}

$filename = basename($_GET['file']); // Security: prevent directory traversal
$filepath = '../uploads/kyc_temp/' . $filename;

// 3. Verify file exists and is in the correct directory
if (!file_exists($filepath) || strpos(realpath($filepath), realpath('../uploads/kyc_temp/')) !== 0) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

// 4. Stream the image securely
$mime = mime_content_type($filepath);
if (strpos($mime, 'image/') !== 0) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
?>
