<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'includes/dbconnection.php';
require_once 'includes/encryption.php';

$uid = $_SESSION['hbmsuid'];

// 1. Get anchor (registration) name from tbluser
$q = $dbh->prepare('SELECT FullName FROM tbluser WHERE ID=:uid');
$q->execute([':uid' => $uid]);
$anchorName = trim($q->fetchColumn() ?? '');

// 2. Get OCR data and user-edited data from POST
$ocrName        = strtoupper(trim($_POST['ocr_name'] ?? ''));
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

// 4. Variance Check: If user edits OCR data > 20%, force pending
$varianceScore = null;
if ($ocrName !== '' && $passportName !== '') {
    $varLen = max(strlen($ocrName), strlen($passportName));
    $varDist = levenshtein($ocrName, $passportName);
    $varianceScore = ($varLen > 0) ? (1 - $varDist / $varLen) * 100 : 0;
}

$forcePending = ($varianceScore !== null && $varianceScore < 80);

if ($forcePending) {
    $newStatus = 'pending';
    $mismatch  = 'VARIANCE: User-edited name differs >20% from OCR. Manual review required.';
} elseif ($matchScore >= 85) {
    // Tier 1: Auto-Approve
    $newStatus = 'verified';
    $mismatch  = null;
    $dbh->prepare('UPDATE tbluser SET FullName=:n WHERE ID=:uid')
        ->execute([':n' => $passportName, ':uid' => $uid]);
} elseif ($matchScore >= 40) {
    // Tier 2: Admin Review
    $newStatus = 'pending';
    $mismatch  = 'Score:' . round($matchScore, 1) . '% | Anchor:' . $anchorName . ' | Passport:' . $passportName;
} else {
    // Tier 3: Hard Block
    $newStatus = 'rejected';
    $mismatch  = 'HARD BLOCK Score:' . round($matchScore, 1) . '% | Anchor:' . $anchorName . ' | Passport:' . $passportName;
}

// 4.1 Age Check (Must be 18+)
if ($newStatus !== 'rejected' && !empty($passportDOB)) {
    $dob = new DateTime($passportDOB);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
    if ($age < 18) {
        $newStatus = 'rejected';
        $mismatch = 'REJECTED: User is under 18 years old (Age: ' . $age . ')';
    }
}

// 5. Calculate KYC expiry
$twoYears  = date('Y-m-d', strtotime('+2 years'));
$kycExpiry = (!empty($expiryDate) && $expiryDate < $twoYears) ? $expiryDate : $twoYears;

// 6. Encrypt sensitive fields
$nameEnc   = encryptField($passportName);
$dobEnc    = encryptField($passportDOB);
$numEnc    = encryptField($passportNumber);
$blindHash = computeBlindIndex($passportNumber);

// 7. Handle Temporary Image Lifecycle
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

// 8. Get version number
$vq = $dbh->prepare('SELECT MAX(version) FROM tbl_kyc_records WHERE user_id=:uid');
$vq->execute([':uid' => $uid]);
$ver = (int)($vq->fetchColumn() ?? 0) + 1;

// 9. Mark all previous records as not current
$dbh->prepare('UPDATE tbl_kyc_records SET is_current=0 WHERE user_id=:uid')
    ->execute([':uid' => $uid]);

// 10. Insert new versioned row
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

// 11. Update tbluser
$upd = $dbh->prepare(
    'UPDATE tbluser SET kyc_status=:s, kyc_verified_at=IF(:s="verified", NOW(), kyc_verified_at),
            kyc_expiry_date=:exp WHERE ID=:uid'
);
$upd->execute([':s' => $newStatus, ':exp' => $kycExpiry, ':uid' => $uid]);

header('Location: kyc-status.php');
exit;
?>
