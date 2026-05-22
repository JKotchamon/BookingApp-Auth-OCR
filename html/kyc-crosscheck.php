<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'includes/dbconnection.php';
require_once 'includes/encryption.php';
require_once 'includes/kyc-handler.php';

$uid = $_SESSION['hbmsuid'];

// 0. Security: Rate Limiting (Brute Force Protection)
if (!checkRateLimit($dbh, 'KYC_VERIFY_ATTEMPT', 5, 600)) {
    logKycAction($dbh, $uid, 'RATE_LIMIT_EXCEEDED', 'User exceeded verification attempt limit');
    echo "<script>alert('Too many verification attempts. Please wait 10 minutes before trying again.');</script>";
    echo "<script>window.location.href='kyc-status.php';</script>";
    exit;
}

// 1. Get Anchor Name (registration_name) from tbluser
// We match against the name at registration, not the current profile name, to prevent impersonation.
$q = $dbh->prepare('SELECT registration_name, FullName FROM tbluser WHERE ID=:uid');
$q->execute([':uid' => $uid]);
$row = $q->fetch(PDO::FETCH_ASSOC);
$anchorName = trim($row['registration_name'] ?? $row['FullName'] ?? '');

// 2. Get confirmed OCR data from kyc-verify.php form
$passportName   = strtoupper(trim($_POST['passport_name']   ?? ''));
$passportDOB    = trim($_POST['passport_dob']    ?? '');
$passportNumber = strtoupper(trim($_POST['passport_number'] ?? ''));
$nationality    = trim($_POST['nationality']    ?? '');
$expiryDate     = trim($_POST['expiry_date']    ?? '');

// Basic sanity
if ($passportName === '' || $passportNumber === '') {
    header('Location: kyc-verify.php');
    exit;
}

// 3. 3-Tier Fuzzy Name Match (Levenshtein distance)
$anchorUpper = strtoupper($anchorName);
$maxLen      = max(strlen($anchorUpper), strlen($passportName));
$distance    = levenshtein($anchorUpper, $passportName);
$matchScore  = ($maxLen > 0) ? (1 - $distance / $maxLen) * 100 : 0;

// 3.5 Duplicate Check (Primary Fraud Prevention)
$isDuplicate = checkBlindIndex($dbh, $passportNumber, $uid);

if ($isDuplicate) {
    // FORCE Admin Review for duplicates
    $newStatus = 'pending';
    $mismatch  = 'FLAGGED: Duplicate passport number detected. Requires manual investigation.';
    if ($matchScore < 40) {
        $mismatch = 'CRITICAL FLAG: Duplicate passport number AND Name Mismatch (' . round($matchScore, 1) . '% match). High probability of fraud.';
    }
} elseif ($matchScore >= 85) {
    // Tier 1: Auto-Approve
    $newStatus = 'verified';
    $mismatch  = null;
    // Lock the FullName to the passport name upon verification
    $dbh->prepare('UPDATE tbluser SET FullName=:n WHERE ID=:uid')
        ->execute([':n' => $passportName, ':uid' => $uid]);
} elseif ($matchScore >= 40) {
    // Tier 2: Admin Review
    $newStatus = 'pending';
    $mismatch  = 'Score:' . round($matchScore, 1) . '% | Anchor:' . $anchorName . ' | Passport:' . $passportName;
} else {
    // Tier 3: Hard Block (Reject)
    $newStatus = 'rejected';
    $mismatch  = 'HARD BLOCK Score:' . round($matchScore, 1) . '% | Anchor:' . $anchorName . ' | Passport:' . $passportName;
}

// 3.8 OCR Variance Check (Detection of manual tampering vs typos)
$rawOcrName   = strtoupper(trim($_POST['raw_ocr_name'] ?? ''));
$rawOcrNumber = strtoupper(trim($_POST['raw_ocr_number'] ?? ''));

$isHighVariance = false;
$varianceReason = '';

// If passport number was changed at all, it's a variance (could be typo fix or fraud)
if ($rawOcrNumber !== '' && $passportNumber !== $rawOcrNumber) {
    $isHighVariance = true;
    $varianceReason = 'Passport Number Edited (OCR: '.$rawOcrNumber.' | Submitted: '.$passportNumber.')';
}

// Check name variance (if user changed more than 20% of what OCR got)
if ($rawOcrName !== '') {
    $nameDist = levenshtein($rawOcrName, $passportName);
    $nameVar  = (max(strlen($rawOcrName), strlen($passportName)) > 0) ? ($nameDist / max(strlen($rawOcrName), strlen($passportName))) : 0;
    if ($nameVar > 0.2) { // More than 20% difference
        $isHighVariance = true;
        $varianceReason .= ($varianceReason ? ' | ' : '') . 'Significant Name Edit ('.round($nameVar*100).'% change)';
    }
}

if ($isHighVariance) {
    if ($newStatus === 'verified') {
        // Demote auto-verified to pending if they edited the data significantly
        $newStatus = 'pending';
        $mismatch = 'OCR VARIANCE: ' . $varianceReason;
    } elseif ($newStatus === 'pending') {
        // Append variance to existing pending reason
        $mismatch .= ' | OCR VARIANCE: ' . $varianceReason;
    }
}

// 3.1 Age Check (Must be 18+)
if ($newStatus !== 'rejected' && !empty($passportDOB)) {
    $dob = new DateTime($passportDOB);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
    if ($age < 18) {
        $newStatus = 'rejected';
        $mismatch = 'REJECTED: User is under 18 years old (Age: ' . $age . ')';
    }
}

// 4. Calculate KYC expiry
$twoYears  = date('Y-m-d', strtotime('+2 years'));
$kycExpiry = (!empty($expiryDate) && $expiryDate < $twoYears) ? $expiryDate : $twoYears;

// 5. Encrypt sensitive fields
$nameEnc   = encryptField($passportName);
$dobEnc    = encryptField($passportDOB);
$numEnc    = encryptField($passportNumber);
$blindHash = computeBlindIndex($passportNumber);

// 6. Handle Temporary Image Lifecycle
$sessionTempFile = 'uploads/kyc_temp/sess_' . session_id() . '.jpg';
$finalImagePath = null;

if ($newStatus === 'pending') {
    // Keep it for admin review
    $tempName = bin2hex(random_bytes(16)) . '.jpg';
    $finalPath = 'uploads/kyc_temp/' . $tempName;
    if (file_exists($sessionTempFile)) {
        rename($sessionTempFile, $finalPath);
        $finalImagePath = $tempName;
    }
} else {
    // Auto-verified or Hard Blocked -> Delete image immediately
    if (file_exists($sessionTempFile)) {
        unlink($sessionTempFile);
    }
}

// 7. Get version number
$vq = $dbh->prepare('SELECT MAX(version) FROM tbl_kyc_records WHERE user_id=:uid');
$vq->execute([':uid' => $uid]);
$ver = (int)($vq->fetchColumn() ?? 0) + 1;

// 8. Mark all previous records as not current
$dbh->prepare('UPDATE tbl_kyc_records SET is_current=0 WHERE user_id=:uid')
    ->execute([':uid' => $uid]);

// 9. Insert new versioned row
$log = $dbh->prepare(
    'INSERT INTO tbl_kyc_records (user_id,version,is_current,verification_status,
        full_name_encrypted,date_of_birth_enc,document_number_enc,
        document_number_hash,nationality,expiry_date,name_match_score,rejection_reason,temp_image_path)
     VALUES (:uid,:ver,1,:st,:pne,:dobe,:numenc,:hash,:nat,:exp,:score,:md,:ipath)'
);
$log->execute([
    ':uid'    => $uid,
    ':ver'    => $ver,
    ':st'     => $newStatus,
    ':pne'    => $nameEnc,
    ':dobe'   => $dobEnc,
    ':numenc' => $numEnc,
    ':hash'   => $blindHash,
    ':nat'    => $nationality,
    ':exp'    => !empty($expiryDate) ? $expiryDate : null,
    ':score'  => $matchScore,
    ':md'     => $mismatch,
    ':ipath'  => $finalImagePath,
]);

// 10. Audit Log
$logMsg = "Status: $newStatus | Score: " . round($matchScore, 1) . "%";
if ($mismatch) $logMsg .= " | Details: " . $mismatch;
logKycAction($dbh, $uid, 'KYC_CROSSCHECK_COMPLETED', $logMsg);

// 11. Update tbluser
$upd = $dbh->prepare(
    'UPDATE tbluser SET kyc_status=:s, kyc_verified_at=IF(:s="verified", NOW(), kyc_verified_at),
            kyc_expiry_date=:exp WHERE ID=:uid'
);
$upd->execute([':s' => $newStatus, ':exp' => $kycExpiry, ':uid' => $uid]);

if ($newStatus === 'pending') {
    if (isset($isHighVariance) && $isHighVariance) {
        $_SESSION['kyc_msg'] = 'Your manual corrections differ significantly from the automated detection. For your security, this submission has been queued for manual administrative review (1-2 business days).';
    } else {
        $_SESSION['kyc_msg'] = 'Your verification has been queued for manual administrative review. Please wait 1-2 business days.';
    }
    $_SESSION['kyc_msg_type'] = 'info';
}

// Contextual Redirect
$rmid = isset($_POST['rmid']) ? intval($_POST['rmid']) : null;

if ($newStatus === 'verified') {
    $redirect = $rmid ? "book-room.php?rmid=$rmid" : "kyc-status.php";
} else {
    $redirect = $rmid ? "kyc-status.php?rmid=$rmid" : "kyc-status.php";
}

header("Location: $redirect");
exit;
?>
