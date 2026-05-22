<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// 1. Auth Check: Only logged in admins allowed
if (empty($_SESSION['hbmsaid'])) {
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

// 3. HARDENING: Verify the file is actually registered in a pending KYC record
// This prevents unauthorized access to orphaned or old images.
$stmt = $dbh->prepare("SELECT COUNT(*) FROM tbl_kyc_records WHERE temp_image_path = :path AND verification_status = 'pending'");
$stmt->execute([':path' => $filename]);
if ($stmt->fetchColumn() == 0) {
    header('HTTP/1.0 403 Forbidden');
    exit("Access Denied: Record not found or not in pending status.");
}

// 4. Verify physical file exists and is in the correct directory
if (!file_exists($filepath) || strpos(realpath($filepath), realpath('../uploads/kyc_temp/')) !== 0) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

// 5. Stream the encrypted binary safely
$ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
if ($ext !== 'enc' && $ext !== 'jpg') {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
?>
