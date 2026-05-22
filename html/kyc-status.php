<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'includes/dbconnection.php'; 

$uid = $_SESSION['hbmsuid'];
$q = $dbh->prepare('SELECT kyc_status, kyc_expiry_date FROM tbluser WHERE ID=:uid');
$q->execute([':uid' => $uid]);
$user = $q->fetch(PDO::FETCH_OBJ);

$status = $user->kyc_status ?? 'unverified';
$expiry = $user->kyc_expiry_date ?? null;

// Fetch the most recent record details for status message
$rq = $dbh->prepare('SELECT rejection_reason FROM tbl_kyc_records WHERE user_id=:uid AND is_current=1 ORDER BY id DESC LIMIT 1');
$rq->execute([':uid' => $uid]);
$record = $rq->fetch(PDO::FETCH_OBJ);
$reason = $record->rejection_reason ?? null;

// Check if verified KYC has expired
if ($status === 'verified' && $expiry && strtotime($expiry) <= time()) {
    // Auto-update to expired
    $dbh->prepare('UPDATE tbluser SET kyc_status="expired" WHERE ID=:uid')
        ->execute([':uid' => $uid]);
    $status = 'expired';
}

// If verified and not expired — allow them to see the verified status page instead of force redirecting

// 30-day expiry warning
$expiryWarning = false;
if ($status === 'verified' && $expiry) {
    $daysLeft = (strtotime($expiry) - time()) / 86400;
    if ($daysLeft <= 30) $expiryWarning = true;
}
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Hotel Booking Management System | KYC Status</title>
<link href="css/bootstrap.css" rel="stylesheet" type="text/css" media="all">
<link href="css/style.css" rel="stylesheet" type="text/css" media="all" />
<script src="js/jquery-1.11.1.min.js"></script>
<script src="js/bootstrap.js"></script>
<style>
    body {
        display: flex;
        min-height: 100vh;
        flex-direction: column;
    }
    .content {
        flex: 1;
    }
</style>
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
                <h2 class="tittle" style="margin-bottom: 40px;">Verification Status</h2>
                <div class="contact-grids">
                    <div class="col-md-8 col-md-offset-2 contact-grid">
                        
                        <?php if ($expiryWarning): ?>
                            <div class="alert alert-warning">
                                Your KYC expires on <?php echo htmlspecialchars($expiry); ?>. Re-verify soon.
                            </div>
                        <?php endif; ?>

                        <?php if ($status === 'pending'): ?>
                            <div class="alert alert-info">
                                <h4 style="margin-bottom: 10px;">Verification In Progress</h4>
                                <p>Our AI is currently analyzing your documents, followed by a final review by our administrative team.</p>
                                
                                <?php if (strpos($reason, 'Duplicate') !== false): ?>
                                    <div style="background: rgba(0,0,0,0.05); padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #31708f;">
                                        <strong>Security Flag:</strong> We detected that this document might be associated with another account. We need to manually verify this to ensure your account security.
                                    </div>
                                <?php elseif (strpos($reason, 'VARIANCE') !== false): ?>
                                    <div style="background: rgba(0,0,0,0.05); padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #31708f;">
                                        <strong>Manual Review Triggered:</strong> We noticed a slight mismatch between your input and the document scan. Our team is manually reviewing this to assist you.
                                    </div>
                                <?php elseif ($reason): ?>
                                    <p style="margin-top: 10px; font-style: italic; color: #555;">Note: <?php echo htmlspecialchars($reason); ?></p>
                                <?php endif; ?>
                                
                                <p style="margin-top: 15px; font-size: 0.9em;">This usually takes 1-2 business days. We will notify you once completed.</p>
                            </div>
                        <?php elseif ($status === 'rejected'): ?>
                            <div class="alert alert-danger">
                                <strong>Verification Failed</strong><br>
                                <?php 
                                if ($reason) {
                                    if (strpos($reason, 'HARD BLOCK') !== false) {
                                        echo "The name on the passport does not match the name registered on your account.";
                                    } elseif (strpos($reason, 'User is under 18') !== false) {
                                        echo "You must be 18 or older to verify your identity.";
                                    } else {
                                        echo htmlspecialchars($reason);
                                    }
                                } else {
                                    echo "We couldn't verify your identity. This might be because the uploaded document is unclear or doesn't match your account details.";
                                }
                                ?><br><br>
                                <a href="kyc-verify.php" class="btn btn-danger">Try again with a valid passport</a>
                            </div>
                        <?php elseif ($status === 'expired'): ?>
                            <div class="alert alert-warning">
                                <strong>Verification Expired</strong><br>
                                Your identity verification has expired. Please re-verify to continue booking rooms.<br><br>
                                <a href="kyc-verify.php" class="btn btn-warning">Re-verify Now</a>
                            </div>
                        <?php elseif ($status === 'unverified'): ?>
                            <div class="alert alert-warning">
                                <strong>Not Yet Submitted</strong><br>
                                You haven't verified your identity yet.<br><br>
                                <a href="kyc-verify.php" class="btn btn-warning">Upload your passport to continue</a>
                            </div>
                        <?php elseif ($status === 'verified'): ?>
                            <div class="alert alert-success">
                                <strong>Verification Successful</strong><br>
                                Your identity has been verified. You now have full access to all booking features.
                            </div>
                        <?php endif; ?>


                    </div>
                    <div class="clearfix"> </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once('includes/footer.php');?>
</body>
</html>
