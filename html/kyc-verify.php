<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'includes/dbconnection.php';
require_once 'includes/kyc-handler.php';

$uid = $_SESSION['hbmsuid'];
$ocrData = null;
$error = null;
$blocked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['passport'])) {
    // 1. Consent check
    if (empty($_POST['consent'])) {
        $error = 'You must agree to the terms before uploading.';
    } else {
        $file = $_FILES['passport'];
        $allowed = ['image/jpeg', 'image/png'];
        $maxSize = 10 * 1024 * 1024; // 10MB raw max

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'File upload failed. Error code: ' . $file['error'];
        } elseif (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $error = 'No file was uploaded or the upload is invalid.';
        } else {
            // 2. MIME type validation
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            if (!in_array($mimeType, $allowed)) {
                $error = 'Only JPG or PNG files accepted.';
            } elseif ($file['size'] > $maxSize) {
                $error = 'File must be under 10MB.';
            } else {
                // 3. Read file into memory
                $imageData = file_get_contents($file['tmp_name']);

                // 4. Compress image with GD
                $src = imagecreatefromstring($imageData);
                if (!$src) {
                    $error = 'Invalid image file. Could not process.';
                } else {
                    ob_start();
                    imagejpeg($src, null, 85);
                    $compressed = ob_get_clean();
                    imagedestroy($src);

                    // Save a session-based temp file so crosscheck.php can use it
                    $sessionTempFile = 'uploads/kyc_temp/sess_' . session_id() . '.jpg';
                    file_put_contents($sessionTempFile, $compressed);

                    // 5. Send to OCR microservice
                    $ocrData = sendToOcr($compressed, 'image/jpeg');

                    if (!$ocrData['success']) {
                        $error = $ocrData['error'] ?? 'Failed to process passport image. Please try again.';
                        $ocrData = null;
                    }

                    // 6. Blind index duplicate check
                    if ($ocrData && isset($ocrData['passport_number'])) {
                        $isDuplicate = checkBlindIndex($dbh, $ocrData['passport_number'], $uid);
                        if ($isDuplicate) {
                            $blocked = true;
                            // Change from hard reject to manual review for duplicates
                            $error = 'Your verification has been flagged for manual administrative review. Please wait 1-2 business days.';

                            // 1. Move session temp to unique permanent name for Admin Review
                            $tempName = bin2hex(random_bytes(16)) . '.jpg';
                            $finalPath = 'uploads/kyc_temp/' . $tempName;
                            if (file_exists($sessionTempFile)) {
                                rename($sessionTempFile, $finalPath);
                            }

                            // Flag the attempt in DB
                            require_once 'includes/encryption.php';

                            $nameEnc   = encryptField(strtoupper(trim($ocrData['name'] ?? '')));
                            $dobEnc    = encryptField($ocrData['dob'] ?? '');
                            $numEnc    = encryptField($ocrData['passport_number']);
                            $blindHash = computeBlindIndex($ocrData['passport_number']);

                            $vq = $dbh->prepare('SELECT MAX(version) FROM tbl_kyc_records WHERE user_id=:uid');
                            $vq->execute([':uid' => $uid]);
                            $ver = (int)($vq->fetchColumn() ?? 0) + 1;

                            $dbh->prepare('UPDATE tbl_kyc_records SET is_current=0 WHERE user_id=:uid')
                                ->execute([':uid' => $uid]);

                            $logSql = "INSERT INTO tbl_kyc_records (user_id,version,is_current,verification_status,
                                       full_name_encrypted,date_of_birth_enc,document_number_enc,
                                       document_number_hash,nationality,expiry_date,rejection_reason,temp_image_path)
                                       VALUES (:uid,:ver,1,:st,:pne,:dobe,:numenc,:hash,:nat,:exp,:md,:ipath)";
                            $dbh->prepare($logSql)->execute([
                                ':uid'    => $uid,
                                ':ver'    => $ver,
                                ':st'     => 'pending',
                                ':pne'    => $nameEnc,
                                ':dobe'   => $dobEnc,
                                ':numenc' => $numEnc,
                                ':hash'   => $blindHash,
                                ':nat'    => trim($ocrData['nationality'] ?? ''),
                                ':exp'    => !empty($ocrData['expiry_date']) ? $ocrData['expiry_date'] : null,
                                ':md'     => 'FLAGGED: Duplicate passport number detected. Requires manual investigation.',
                                ':ipath'  => $tempName,
                            ]);

                            $dbh->prepare("UPDATE tbluser SET kyc_status='pending' WHERE ID=:uid")
                                ->execute([':uid' => $uid]);
                        }
                    }

                    // 7. Check passport expiry
                    if (!$blocked && $ocrData && isset($ocrData['expiry_date'])) {
                        if (strtotime($ocrData['expiry_date']) < time()) {
                            $error = 'This passport has expired. Please use a valid passport.';
                            $ocrData = null;
                        }
                    }
                }
            }
        }
    }
}

// Redirect if already verified
$stmt = $dbh->prepare("SELECT kyc_status FROM tbluser WHERE ID = :uid");
$stmt->execute([':uid' => $uid]);
$status = $stmt->fetchColumn();
if ($status === 'verified') {
    header('location: kyc-status.php');
    exit;
}

$msg = $_SESSION['kyc_msg'] ?? '';
unset($_SESSION['kyc_msg']);
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Hotel Booking Management System | KYC Verification</title>
<link href="css/bootstrap.css" rel="stylesheet" type="text/css" media="all">
<link href="css/style.css" rel="stylesheet" type="text/css" media="all" />
<script src="js/jquery-1.11.1.min.js"></script>
<script src="js/bootstrap.js"></script>
</head>
<body>
    <div class="header head-top">
        <div class="container">
            <?php include_once('includes/header.php');?>
        </div>
    </div>

    <div class="content">
        <div class="contact">
            <div class="container">
                <h2 class="tittle" style="margin-bottom: 40px;">Identity Verification</h2>
                <div class="contact-grids">
                    <div class="col-md-8 col-md-offset-2 contact-grid">
                        
                        <?php if($msg): ?>
                            <div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <?php if (!$ocrData && !$blocked): ?>
                            <p style="margin-bottom: 20px; font-size: 1.1em; color: #555;">
                                To keep everything secure, we need to verify your identity before you can book. 
                                Just upload a clear photo of your <strong>Passport</strong>.
                            </p>

                            <form method="post" enctype="multipart/form-data" style="background: #fdfdfd; padding: 40px; border-radius: 12px; border: 1px solid #eee; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                                <div class="form-group">
                                    <label style="font-weight: 600; display: block; margin-bottom: 12px; color: #333;">Passport Main Page (with photo):</label>
                                    <div style="border: 2px dashed #ccc; padding: 20px; text-align: center; border-radius: 8px; background: #fff; cursor: pointer;" onclick="$('#passport_file').click();">
                                        <i class="glyphicon glyphicon-camera" style="font-size: 32px; color: #999; margin-bottom: 10px;"></i>
                                        <p id="file-name" style="color: #666;">Click to select or drag and drop (JPG/PNG)</p>
                                    </div>
                                    <input type="file" id="passport_file" name="passport" accept="image/jpeg,image/png" style="display: none;" onchange="if(this.files[0]) $('#file-name').text(this.files[0].name);">
                                    <small class="text-muted" style="display:block; margin-top:10px;">Make sure the text at the bottom is clear and readable.</small>
                                </div>

                                <div class="checkbox" style="margin: 25px 0;">
                                    <label style="color: #666;">
                                        <input type="checkbox" name="consent" required> 
                                        I consent to the processing of my identity document for booking verification.
                                    </label>
                                </div>

                                <button type="submit" name="submit" class="btn-submit" style="width: 100%; padding: 15px; border-radius: 8px;">
                                    Upload & Verify
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($ocrData && isset($ocrData['success']) && $ocrData['success']): ?>
                            <div class="panel panel-success" style="margin-top: 20px;">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Please confirm your legal details extracted from your passport</h3>
                                </div>
                                <div class="panel-body">
                                    <form method="POST" action="kyc-crosscheck.php">
                                        <div class="form-group">
                                            <label>Full Name (as on passport)</label>
                                            <input type="text" name="passport_name" class="form-control" value="<?php echo htmlspecialchars($ocrData['name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Date of Birth</label>
                                            <input type="date" name="passport_dob" class="form-control" value="<?php echo htmlspecialchars($ocrData['dob'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Passport Number</label>
                                            <input type="text" name="passport_number" class="form-control" value="<?php echo htmlspecialchars($ocrData['passport_number'] ?? ''); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Nationality</label>
                                            <input type="text" name="nationality" class="form-control" value="<?php echo htmlspecialchars($ocrData['nationality'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Passport Expiry Date</label>
                                            <input type="date" name="expiry_date" class="form-control" value="<?php echo htmlspecialchars($ocrData['expiry_date'] ?? ''); ?>">
                                        </div>
                                        <p class="text-muted small">Correct any wrong fields before confirming.</p>
                                        <button type="submit" class="btn btn-success" style="width: 100%; padding: 12px; font-size: 16px;">Confirm & Submit</button>
                                    </form>
                                </div>
                            </div>
                        <?php elseif (isset($ocrData['success']) && !$ocrData['success']): ?>
                            <div class="alert alert-warning mt-3">
                                Could not read your passport. Please retake the photo showing the MRZ strip clearly.
                                <br><br>
                                <a href="kyc-verify.php" class="btn btn-warning">Try Again</a>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 30px; text-align: center;">
                            <a href="profile.php" style="color: #999; text-decoration: none;">&larr; Back to My Account</a>
                        </div>
                    </div>
                    <div class="clearfix"> </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once('includes/footer.php');?>
</body>
</html>
