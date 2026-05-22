<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'includes/dbconnection.php';
require_once 'includes/kyc-handler.php';

$uid = $_SESSION['hbmsuid'];
$ocrData = null;
$error = null;
$blocked = false;
$isSystemActive = file_exists(__DIR__ . '/includes/kyc_public_key.pem');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['passport'])) {
    // 1. Consent check
    if (empty($_POST['consent'])) {
        $error = 'You must agree to the terms before uploading.';
    } else {
        $file = $_FILES['passport'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
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

                            $nameEnc   = encryptTextRSA(strtoupper(trim($ocrData['name'] ?? '')));
                            $dobEnc    = encryptTextRSA($ocrData['dob'] ?? '');
                            $numEnc    = encryptTextRSA($ocrData['passport_number']);
                            $blindHash = computeBlindIndex($ocrData['passport_number']);

                            $vq = $dbh->prepare('SELECT MAX(version) FROM tbl_kyc_records WHERE user_id=:uid');
                            $vq->execute([':uid' => $uid]);
                            $ver = (int)($vq->fetchColumn() ?? 0) + 1;

                            $dbh->prepare('UPDATE tbl_kyc_records SET is_current=0 WHERE user_id=:uid')
                                ->execute([':uid' => $uid]);

                            $logSql = "INSERT INTO tbl_kyc_records (user_id,version,is_current,verification_status,
                                       full_name_encrypted,date_of_birth_enc,document_number_enc,
                                       document_number_hash,nationality,expiry_date,rejection_reason,temp_image_path,symmetric_key_enc,iv)
                                       VALUES (:uid,:ver,1,:st,:pne,:dobe,:numenc,:hash,:nat,:exp,:md,:ipath,NULL,NULL)";
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
<style>
    /* Premium Loading Overlay */
    #loading-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        z-index: 9999;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    .spinner {
        width: 60px;
        height: 60px;
        border: 6px solid #f3f3f3;
        border-top: 6px solid #2ecc71;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 20px;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .loading-text { font-size: 1.2em; color: #2c3e50; font-weight: 500; font-family: 'Roboto', sans-serif; }

    /* Button and Form Style */
    .btn-submit {
        background: #2ecc71;
        color: white;
        border: none;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s;
    }
    .btn-submit:hover {
        background: #27ae60;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(46, 204, 113, 0.3);
    }
    .upload-zone {
        transition: all 0.3s;
        border: 2px dashed #ddd;
    }
    .upload-zone:hover {
        border-color: #2ecc71;
        background: #f9fffb !important;
    }
</style>
<script>
    $(document).ready(function() {
        $('form').on('submit', function() {
            // Only show loader on the upload form, not the confirm form
            if ($('#passport_file').length > 0 && $('#passport_file').val()) {
                $('#loading-overlay').css('display', 'flex');
            }
        });
    });
</script>
</head>
<body>
    <div id="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-text">Verifying Identity...</div>
        <p style="color: #7f8c8d; margin-top: 10px;">This may take up to 10 seconds.</p>
    </div>
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

                        <?php if (!$isSystemActive): ?>
                            <div class="alert alert-warning" style="text-align: center; padding: 40px; border-radius: 12px; margin-top: 20px;">
                                <h3 style="color: #e67e22; margin-bottom: 15px;">🚧 System Maintenance 🚧</h3>
                                <p style="font-size: 1.1em; color: #555;">The identity verification system is currently undergoing cryptographic maintenance to ensure your data remains completely secure. Please check back later.</p>
                            </div>
                        <?php elseif (!$ocrData && !$blocked): ?>
                            <p style="margin-bottom: 20px; font-size: 1.1em; color: #555;">
                                Secure Identity Anchoring: To keep our community safe, we use advanced OCR to verify your identity. 
                                It's a quick one-time process—just upload a clear photo of your <strong>Passport</strong>.
                            </p>

                            <form method="post" enctype="multipart/form-data" style="background: #fdfdfd; padding: 40px; border-radius: 12px; border: 1px solid #eee; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                                <div class="form-group">
                                    <label style="font-weight: 600; display: block; margin-bottom: 12px; color: #333;">Passport Main Page (with photo):</label>
                                    <div class="upload-zone" style="padding: 20px; text-align: center; border-radius: 8px; background: #fff; cursor: pointer;" onclick="$('#passport_file').click();">
                                        <i class="glyphicon glyphicon-camera" style="font-size: 32px; color: #999; margin-bottom: 10px;"></i>
                                        <p id="file-name" style="color: #666;">Click to select or drag and drop (JPG/PNG/WebP)</p>
                                    </div>
                                    <input type="file" id="passport_file" name="passport" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="if(this.files[0]) $('#file-name').text(this.files[0].name);">
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
                                    <h3 class="panel-title">Our AI extracted the following details. Please ensure they are 100% correct.</h3>
                                </div>
                                <div class="panel-body">
                                    <form method="POST" action="kyc-crosscheck.php">
                                        <input type="hidden" name="rmid" value="<?php echo (int)($_GET['rmid'] ?? $_POST['rmid'] ?? 0); ?>">
                                        <input type="hidden" name="raw_ocr_name" value="<?php echo htmlspecialchars($ocrData['name'] ?? ''); ?>">
                                        <input type="hidden" name="raw_ocr_number" value="<?php echo htmlspecialchars($ocrData['passport_number'] ?? ''); ?>">
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
