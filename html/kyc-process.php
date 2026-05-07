<?php
session_start();
include('includes/dbconnection.php');

/**
 * KYC Process Handler
 * This script handles the passport upload and "simulates" the OCR verification.
 * In a production setup, this would call the Python OCR microservice.
 */

// Safety check: must be logged in
if (empty($_SESSION['hbmsuid'])) {
    header('location:signin.php');
    exit;
}

if (!isset($_POST['submit'])) {
    header('location:kyc-verify.php');
    exit;
}

$uid = (int)$_SESSION['hbmsuid'];

// Handle the upload
if (isset($_FILES['passport_image']) && $_FILES['passport_image']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png'];
    $filename = $_FILES['passport_image']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        $_SESSION['kyc_msg'] = "Oops! Only JPG and PNG images are allowed for passports.";
        header('location:kyc-verify.php');
        exit;
    }

    // --- STEP 1: OCR Processing (Simulated for now) ---
    // Later we will replace this with a real cURL request to the OCR microservice container
    // $ocrData = callOcrMicroservice($_FILES['passport_image']['tmp_name']);
    
    $simulatedOcr = [
        'full_name'   => 'JOHN DOE',
        'dob'         => '1995-05-15',
        'doc_number'  => 'P12345678',
        'expiry'      => '2032-10-20',
        'nationality' => 'USA',
        'mrz_valid'   => 1
    ];

    // --- STEP 2: Database Storage ---
    // We encrypt sensitive data using a key from the environment (or a hardcoded fallback for students lol)
    $encryptionKey = getenv('KYC_ENCRYPTION_KEY') ?: 'HBMS_SUPER_SECRET_KEY_123';

    try {
        $dbh->beginTransaction();

        // 1. Reset current status for old records
        $reset = $dbh->prepare("UPDATE tbl_kyc_records SET is_current = 0 WHERE user_id = :uid");
        $reset->execute([':uid' => $uid]);

        // 2. Insert the new record with "encrypted" fields
        // Using AES_ENCRYPT directly in SQL is easy and efficient
        $sql = "INSERT INTO tbl_kyc_records 
                (user_id, is_current, document_type, full_name_encrypted, nationality, 
                 date_of_birth_enc, document_number_enc, document_number_hash, 
                 expiry_date, mrz_checksum_valid, verification_status, verification_method, verified_at)
                VALUES 
                (:uid, 1, 'passport', AES_ENCRYPT(:name, :key), :nat, 
                 AES_ENCRYPT(:dob, :key), AES_ENCRYPT(:doc, :key), SHA2(:doc, 256), 
                 :expiry, :mrz, 'verified', 'OCR_AI', NOW())";
        
        $stmt = $dbh->prepare($sql);
        $stmt->execute([
            ':uid'    => $uid,
            ':name'   => $simulatedOcr['full_name'],
            ':key'    => $encryptionKey,
            ':nat'    => $simulatedOcr['nationality'],
            ':dob'    => $simulatedOcr['dob'],
            ':doc'    => $simulatedOcr['doc_number'],
            ':expiry' => $simulatedOcr['expiry'],
            ':mrz'    => $simulatedOcr['mrz_valid']
        ]);

        // 3. Update user status to 'verified'
        $updateUser = $dbh->prepare("UPDATE tbluser SET kyc_status = 'verified', kyc_verified_at = NOW(), kyc_expiry_date = :expiry WHERE ID = :uid");
        $updateUser->execute([
            ':expiry' => $simulatedOcr['expiry'],
            ':uid'    => $uid
        ]);

        // 4. Log the action for audit purposes
        $log = $dbh->prepare("INSERT INTO tbl_kyc_audit_log (user_id, action, details, ip_address) VALUES (:uid, 'VERIFICATION_SUCCESS', 'Passport verified automatically', :ip)");
        $log->execute([
            ':uid' => $uid,
            ':ip'  => $_SERVER['REMOTE_ADDR']
        ]);

        $dbh->commit();

        $_SESSION['kyc_msg'] = "Awesome! Your identity has been verified. You're all set to book!";
        header('location:kyc-verify.php');

    } catch (Exception $e) {
        $dbh->rollBack();
        error_log("KYC Process Error: " . $e->getMessage());
        $_SESSION['kyc_msg'] = "Ugh, something went wrong on our end. Please try uploading again.";
        header('location:kyc-verify.php');
    }

} else {
    $_SESSION['kyc_msg'] = "Please pick a photo of your passport to upload.";
    header('location:kyc-verify.php');
}
