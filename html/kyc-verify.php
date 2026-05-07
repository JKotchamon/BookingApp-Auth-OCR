<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// User must be logged in to verify
if (empty($_SESSION['hbmsuid'])) {
    header('location:signin.php');
    exit;
}

$uid = (int)$_SESSION['hbmsuid'];
$msg = $_SESSION['kyc_msg'] ?? '';
unset($_SESSION['kyc_msg']);

// Let's see if they already have a pending or verified record
$stmt = $dbh->prepare("SELECT status FROM tbl_kyc_records WHERE user_id = :uid ORDER BY created_at DESC LIMIT 1");
$stmt->execute([':uid' => $uid]);
$existing = $stmt->fetch(PDO::FETCH_OBJ);

$status = $existing ? $existing->status : 'none';

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
                <h2 class="tittle">Identity Verification</h2>
                <div class="contact-grids">
                    <div class="col-md-8 col-md-offset-2 contact-grid">
                        
                        <?php if($msg): ?>
                            <div class="alert alert-info"><?php echo $msg; ?></div>
                        <?php endif; ?>

                        <?php if ($status == 'verified'): ?>
                            <div class="alert alert-success">
                                <h4>You are already verified!</h4>
                                <p>Your identity has been confirmed. You can now book any room you like.</p>
                                <a href="index.php" class="btn btn-success" style="margin-top:15px;">Go to Home</a>
                            </div>
                        <?php elseif ($status == 'pending'): ?>
                            <div class="alert alert-warning">
                                <h4>Verification in Progress</h4>
                                <p>We've received your passport. Our team (and the AI) are looking at it right now. Please check back later!</p>
                                <a href="profile.php" class="btn btn-warning" style="margin-top:15px;">Back to Profile</a>
                            </div>
                        <?php else: ?>
                            <p style="margin-bottom: 20px; font-size: 1.1em; color: #555;">
                                Hey there! To keep everything secure, we need to verify your identity before you can book. 
                                It's a quick one-time process. Just upload a clear photo of your <strong>Passport</strong>.
                            </p>

                            <form action="kyc-process.php" method="post" enctype="multipart/form-data" style="background: #fdfdfd; padding: 40px; border-radius: 12px; border: 1px solid #eee; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                                <div class="form-group">
                                    <label style="font-weight: 600; display: block; margin-bottom: 12px; color: #333;">Passport Main Page (with photo):</label>
                                    <div style="border: 2px dashed #ccc; padding: 20px; text-align: center; border-radius: 8px; background: #fff; cursor: pointer;" onclick="$('#passport_file').click();">
                                        <i class="glyphicon glyphicon-camera" style="font-size: 32px; color: #999; margin-bottom: 10px;"></i>
                                        <p id="file-name" style="color: #666;">Click to select or drag and drop</p>
                                        <input type="file" id="passport_file" name="passport_image" accept="image/jpeg,image/png" style="display: none;" onchange="$('#file-name').text(this.files[0].name);">
                                    </div>
                                    <small class="text-muted" style="display:block; margin-top:10px;">Make sure the text at the bottom is clear and readable.</small>
                                </div>

                                <div class="checkbox" style="margin: 25px 0;">
                                    <label style="color: #666;">
                                        <input type="checkbox" name="consent" required> 
                                        I consent to the processing of my identity document for booking verification.
                                    </label>
                                </div>

                                <button type="submit" name="submit" class="btn btn-primary btn-lg" style="width: 100%; background: #ff4c4c; border: none; padding: 15px; font-weight: bold; border-radius: 8px;">
                                    Upload & Verify
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <div style="margin-top: 30px; text-align: center;">
                            <a href="profile.php" style="color: #999; text-decoration: none;">← Back to My Account</a>
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
