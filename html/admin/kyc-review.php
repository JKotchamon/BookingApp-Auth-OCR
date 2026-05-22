<?php
ob_start();
session_start();
error_reporting(0);
include('includes/dbconnection.php');
require_once('../includes/encryption.php');
require_once('../includes/kyc-handler.php');

if (empty($_SESSION['hbmsaid'])) {
    header('location:logout.php');
    exit();
}

// Handle Approve/Reject Actions
if (isset($_POST['kyc_action']) && isset($_POST['record_id'])) {
    $action = $_POST['kyc_action'];
    $recordId = intval($_POST['record_id']);
    
    // 1. Fetch record
    $sq = $dbh->prepare("SELECT user_id, temp_image_path FROM tbl_kyc_records WHERE ID = :rid");
    $sq->execute([':rid' => $recordId]);
    $record = $sq->fetch(PDO::FETCH_OBJ);
    
    if ($record) {
        $uid = $record->user_id;
        // 2. Determine new status
        $newStatus = 'rejected';
        if ($action === 'approve') $newStatus = 'verified';
        if ($action === 'block')   $newStatus = 'blocked';
        
        // 3. Update Record
        $adminReason = trim($_POST['admin_reason'] ?? '');
        if (empty($adminReason)) {
            if ($newStatus === 'verified') $adminReason = 'Approved';
            elseif ($newStatus === 'blocked') $adminReason = 'Account restricted due to high fraud risk.';
            else $adminReason = 'Our team has reviewed the documents flagged by our AI and unfortunately could not verify your identity. Please ensure your photo is clear and matches your account details.';
        }

        $dbh->prepare("UPDATE tbl_kyc_records SET verification_status = :st, rejection_reason = :re, verified_at = NOW(), verified_by = :admin WHERE ID = :rid")
            ->execute([':st' => $newStatus, ':re' => $adminReason, ':admin' => $_SESSION['hbmsaid'], ':rid' => $recordId]);
            
        // 4. Update User
        $expiryDate = date('Y-m-d', strtotime('+2 years'));
        $userUpd = "UPDATE tbluser SET kyc_status = :st, 
                    kyc_verified_at = IF(:st='verified', NOW(), kyc_verified_at),
                    kyc_expiry_date = IF(:st='verified', :exp, kyc_expiry_date) 
                    WHERE ID = :uid";
        $dbh->prepare($userUpd)->execute([':st' => $newStatus, ':exp' => $expiryDate, ':uid' => $uid]);
        
        // 5. Audit log
        $logAction = 'ADMIN_KYC_REJECTED';
        if ($action === 'approve') $logAction = 'ADMIN_KYC_APPROVED';
        if ($action === 'block')   $logAction = 'ADMIN_USER_BLOCKED';
        
        logKycAction($dbh, $uid, $logAction, "Record ID: $recordId | Admin: " . $_SESSION['hbmsaid']);

        // 6. Cleanup temp image
        if (!empty($record->temp_image_path)) {
            $filePath = '../uploads/kyc_temp/' . $record->temp_image_path;
            if (file_exists($filePath)) { @unlink($filePath); }
            $dbh->prepare("UPDATE tbl_kyc_records SET temp_image_path = NULL WHERE ID = :rid")->execute([':rid' => $recordId]);
        }
        
        $_SESSION['kyc_msg'] = "User KYC status successfully updated to " . ucfirst($newStatus) . ".";
        $_SESSION['kyc_msg_type'] = ($newStatus === 'verified') ? 'success' : 'warning';
        if ($newStatus === 'blocked') $_SESSION['kyc_msg_type'] = 'danger';
    } else {
        $_SESSION['kyc_msg'] = "Error: Record not found.";
        $_SESSION['kyc_msg_type'] = 'danger';
    }
    
    ob_end_clean();
    header("Location: kyc-review.php");
    exit();
}

$displayMsg = "";
$msgType = "success";
if (isset($_SESSION['kyc_msg'])) {
    $displayMsg = $_SESSION['kyc_msg'];
    $msgType = $_SESSION['kyc_msg_type'] ?? 'success';
    unset($_SESSION['kyc_msg'], $_SESSION['kyc_msg_type']);
}
ob_end_flush();
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Hotel Booking Management System | KYC Review</title>
<link href="css/bootstrap.min.css" rel='stylesheet' type='text/css' />
<link href="css/style.css" rel='stylesheet' type='text/css' />
<link href="css/font-awesome.css" rel="stylesheet"> 
<link rel="stylesheet" href="css/icon-font.min.css" type='text/css' />
<link href='//fonts.googleapis.com/css?family=Roboto:700,500,300,100italic,100,400' rel='stylesheet' type='text/css'/>
<script src="js/jquery-1.10.2.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/forge/1.3.1/forge.min.js"></script>
<style>
    .passport-thumb { max-width: 150px; border: 1px solid #ddd; padding: 2px; }
    .alert-top { margin: 15px 0; }
</style>
</head> 
<body>
   <div class="page-container">
   <div class="left-content">
	   <div class="inner-content">
			<?php include_once('includes/header.php');?>
			<div class="content">
                <div class="women_main">
                    <div class="grids">
                        <div class="progressbar-heading grids-heading">
                            <h2>KYC Verification Review</h2>
                        </div>
                        
                        <?php if($displayMsg): ?>
                        <div class="alert alert-<?php echo $msgType; ?> alert-dismissible alert-top" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <strong><?php echo ($msgType === 'success') ? 'Success!' : 'Notice:'; ?></strong> <?php echo htmlentities($displayMsg); ?>
                        </div>
                        <?php endif; ?>

                        <div id="decryptionStatusContainer"></div>

                        <div class="panel panel-widget forms-panel">
                            <div class="forms">
                                <div class="form-grids widget-shadow"> 
                                    <div class="form-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                                        <h4>Pending Verifications Queue</h4>
                                        <div style="background: #f9f9f9; padding: 10px 15px; border-radius: 6px; border: 1px solid #ddd; display: flex; align-items: center; gap: 10px;">
                                            <i class="fa fa-key" style="color: #f39c12; font-size: 20px;"></i>
                                            <label style="margin: 0; font-weight: normal;">Unlock Data (Private Key):</label>
                                            <input type="file" id="privateKeyFile" accept=".pem" style="max-width: 220px; font-size: 12px;">
                                        </div>
                                    </div>
                                    <div class="form-body">
                                        <?php
                                        // Gather all public keys on the server
                                        $activeFingerprint = getActivePublicKeyFingerprint();
                                        $serverKeys = [];

                                        // Check legacy key
                                        $legacyPath = __DIR__ . '/../keys/kyc_public_key.pem';
                                        $legacyFP = '';
                                        if (file_exists($legacyPath)) {
                                            $content = file_get_contents($legacyPath);
                                            $legacyFP = getPublicKeyFingerprint($content);
                                            $serverKeys[$legacyFP] = [
                                                'name' => 'Legacy Key',
                                                'fingerprint' => $legacyFP,
                                                'is_active' => (!empty($activeFingerprint) && $activeFingerprint === $legacyFP)
                                            ];
                                        }

                                        // Check other keys
                                        $otherKeys = glob(__DIR__ . '/../keys/pubkey_*.pem');
                                        foreach ($otherKeys as $path) {
                                            $content = file_get_contents($path);
                                            $fp = getPublicKeyFingerprint($content);
                                            if (isset($serverKeys[$fp])) {
                                                continue;
                                            }
                                            $serverKeys[$fp] = [
                                                'name' => 'Public Key (' . substr($fp, 0, 8) . ')',
                                                'fingerprint' => $fp,
                                                'is_active' => ($activeFingerprint === $fp)
                                            ];
                                        }
                                        ?>
                                        <?php if (!empty($serverKeys)): ?>
                                        <!-- Server Registered Keys Reference Widget -->
                                        <div style="margin-bottom: 20px; background: #f8f9fc; border: 1px solid #eaecf4; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                            <div id="toggleKeysHeader" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; padding: 12px 18px; user-select: none;" onclick="toggleKeysReference()">
                                                <span style="font-weight: bold; color: #4e73df; font-size: 14px;">
                                                    <i class="fa fa-info-circle" style="color: #4e73df; margin-right: 5px;"></i> 
                                                    Registered Server Keys Reference (Active Fingerprints)
                                                </span>
                                                <span id="keysToggleIcon" style="color: #4e73df; font-size: 12px; font-weight: bold;">
                                                    <i class="fa fa-chevron-down"></i> Show Registered Keys
                                                </span>
                                            </div>
                                            <div id="keysReferenceBody" style="display: none; border-top: 1px dashed #eaecf4; padding: 15px 18px; background: #ffffff; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
                                                <p style="font-size: 12px; color: #858796; margin-bottom: 12px; line-height: 1.5;">
                                                    Below are the public keys currently uploaded to the server. Match the required fingerprint shown in the table to one of the keys below to know which private key file you need to upload for decryption.
                                                </p>
                                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 12px;">
                                                    <?php foreach ($serverKeys as $fp => $kData): ?>
                                                        <div style="background: #fdfdfd; border: 1px solid #e3e6f0; border-radius: 6px; padding: 10px 14px; position: relative; box-shadow: 0 1px 3px rgba(0,0,0,0.01); display: flex; flex-direction: column; justify-content: space-between; transition: all 0.2s ease-in-out;">
                                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                                                <strong style="font-size: 12px; color: #2e59d9;">
                                                                    <i class="fa fa-key" style="color: #f39c12; margin-right: 4px;"></i>
                                                                    <?php echo htmlentities($kData['name']); ?>
                                                                </strong>
                                                                <?php if ($kData['is_active']): ?>
                                                                    <span style="background: #e5fbe5; border: 1px solid #c3f2c3; color: #155724; font-size: 9px; font-weight: bold; padding: 2px 6px; border-radius: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
                                                                        Active
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div style="display: flex; align-items: center; gap: 6px; width: 100%;">
                                                                <code style="font-size: 11px; font-family: 'Courier New', Courier, monospace; background: #f8f9fc; border: 1px solid #eaecf4; color: #4e73df; padding: 3px 8px; border-radius: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex-grow: 1;" title="<?php echo $fp; ?>"><?php echo $fp; ?></code>
                                                                <button class="btn btn-default btn-xs" style="padding: 3px 6px; font-size: 10px; border-color: #ddd;" onclick="copyFingerprint('<?php echo $fp; ?>')" title="Copy SHA-256 fingerprint">
                                                                    <i class="fa fa-copy"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <th>User (OAuth)</th>
                                                    <th>Passport Details</th>
                                                    <th style="width: 320px; text-align: center;">Required Key Fingerprint</th>
                                                    <th>Match Score</th>
                                                    <th>Flag Reason</th>
                                                    <th>Document</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $sql = "SELECT k.*, u.FullName as OAuthName, u.Email 
                                                        FROM tbl_kyc_records k 
                                                        JOIN tbluser u ON k.user_id = u.ID 
                                                        WHERE k.verification_status = 'pending' AND k.is_current = 1";
                                                $query = $dbh->prepare($sql);
                                                $query->execute();
                                                $results = $query->fetchAll(PDO::FETCH_OBJ);

                                                if($query->rowCount() > 0) {
                                                    foreach($results as $row) {
                                                        $rowFingerprint = $row->key_fingerprint ?: $legacyFP;
                                                        if (empty($rowFingerprint) && !empty($serverKeys)) {
                                                            $rowFingerprint = array_key_first($serverKeys);
                                                        }
                                                        ?>
                                                <tr class="kyc-row" 
                                                    data-enc-name="<?php echo htmlentities($row->full_name_encrypted); ?>" 
                                                    data-enc-num="<?php echo htmlentities($row->document_number_enc); ?>"
                                                    data-enc-sym="<?php echo htmlentities($row->symmetric_key_enc); ?>"
                                                    data-iv="<?php echo htmlentities($row->iv); ?>"
                                                    data-img-path="<?php echo urlencode($row->temp_image_path); ?>"
                                                    data-key-fingerprint="<?php echo htmlentities($rowFingerprint); ?>">
                                                    <td>
                                                        <strong><?php echo htmlentities($row->OAuthName); ?></strong><br>
                                                        <small><?php echo htmlentities($row->Email); ?></small>
                                                    </td>
                                                    <td>
                                                        Name: <code class="dec-name" style="color:#666; background:#eee; padding:2px 5px;">[Encrypted Data]</code><br>
                                                        Doc#: <code class="dec-num" style="color:#666; background:#eee; padding:2px 5px;">[Encrypted Data]</code>
                                                    </td>
                                                     <td style="text-align: center; vertical-align: middle; min-width: 320px;">
                                                         <?php if ($rowFingerprint): 
                                                             $keyName = 'Unknown Key';
                                                             if (isset($serverKeys[$rowFingerprint])) {
                                                                 $keyName = $serverKeys[$rowFingerprint]['name'];
                                                             } else {
                                                                 $keyName = 'Public Key (' . substr($rowFingerprint, 0, 8) . ')';
                                                             }
                                                         ?>
                                                             <div style="display: inline-flex; align-items: center; gap: 8px; justify-content: center; background: #f8f9fc; border: 1px solid #eaecf4; padding: 6px 12px; border-radius: 6px;">
                                                                 <span class="fingerprint-label" title="<?php echo htmlentities($rowFingerprint); ?>" style="font-family: 'Courier New', Courier, monospace; font-weight: bold; color: #4e73df; font-size: 11px; display: inline-block; text-align: left; word-break: break-all;">
                                                                     <i class="fa fa-key" style="margin-right: 4px; color: #f39c12;"></i>
                                                                     <strong><?php echo htmlentities($keyName); ?></strong> - 
                                                                     <span style="color: #666; font-weight: normal;"><?php echo htmlentities($rowFingerprint); ?></span>
                                                                     <?php if (empty($row->key_fingerprint) && !empty($legacyFP)): ?>
                                                                         <br><span style="font-size: 9px; color: #858796; font-weight: normal; text-transform: uppercase; display: block; margin-top: 2px;">Legacy Key</span>
                                                                     <?php endif; ?>
                                                                 </span>
                                                                 <button class="btn btn-default btn-xs" style="padding: 3px 6px; font-size: 10px; border-color: #ddd; background: #fff; flex-shrink: 0;" onclick="copyFingerprint('<?php echo $rowFingerprint; ?>')" title="Copy required key fingerprint">
                                                                     <i class="fa fa-copy" style="color: #4e73df;"></i>
                                                                 </button>
                                                             </div>
                                                         <?php else: ?>
                                                             <span class="text-muted" style="font-size: 12px; font-style: italic;">No Key</span>
                                                         <?php endif; ?>
                                                     </td>
                                                    <td>
                                                        <span class="badge badge-warning"><?php echo $row->name_match_score; ?>%</span>
                                                    </td>
                                                    <td>
                                                        <small class="text-danger"><?php echo htmlentities($row->rejection_reason); ?></small>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if($row->temp_image_path): ?>
                                                            <div class="img-container" style="width: 120px; height: 80px; background: #f5f5f5; border: 1px dashed #ccc; border-radius: 4px; display: flex; align-items: center; justify-content: center; margin: 0 auto; flex-direction: column;">
                                                                <i class="fa fa-lock" style="font-size: 20px; color: #aaa;"></i>
                                                                <small style="color: #aaa; margin-top: 4px; font-size: 10px;">Provide Key</small>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">No Image</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="width: 250px;">
                                                        <form method="POST" action="kyc-review.php">
                                                            <input type="hidden" name="record_id" value="<?php echo $row->ID; ?>">
                                                            <div style="margin-bottom: 8px;">
                                                                <input type="text" name="admin_reason" class="form-control input-sm" placeholder="Reason (for Rejection/Block)...">
                                                            </div>
                                                            <div style="display: flex; gap: 5px;">
                                                                <button type="submit" name="kyc_action" value="approve" class="btn btn-success btn-xs" onclick="return confirm('Approve this KYC?')">Approve</button>
                                                                <button type="submit" name="kyc_action" value="reject" class="btn btn-warning btn-xs" onclick="return confirm('Reject this KYC?')">Reject</button>
                                                                <button type="submit" name="kyc_action" value="block" class="btn btn-danger btn-xs" onclick="return confirm('BLOCK this user?')">Block</button>
                                                            </div>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php } } else { ?>
                                                <tr>
                                                    <td colspan="7" class="text-center">No pending verifications found.</td>
                                                </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
			</div>
			<?php include_once('includes/footer.php');?>
		</div>
</div>
<?php include_once('includes/sidebar.php');?>
<div class="clearfix"></div>		
</div>
<script>
var toggle = true;
$(".sidebar-icon").click(function() {                
  if (toggle) {
    $(".page-container").addClass("sidebar-collapsed").removeClass("sidebar-collapsed-back");
    $("#menu span").css({"position":"absolute"});
  } else {
    $(".page-container").removeClass("sidebar-collapsed").addClass("sidebar-collapsed-back");
    setTimeout(function() {
      $("#menu span").css({"position":"relative"});
    }, 400);
  }
  toggle = !toggle;
});
</script>
<script src="js/jquery.nicescroll.js"></script>
<script src="js/scripts.js"></script>
<script>
function toggleKeysReference() {
    const body = document.getElementById('keysReferenceBody');
    const icon = document.getElementById('keysToggleIcon');
    if (body.style.display === 'none') {
        body.style.display = 'block';
        icon.innerHTML = '<i class="fa fa-chevron-up"></i> Hide Registered Keys';
    } else {
        body.style.display = 'none';
        icon.innerHTML = '<i class="fa fa-chevron-down"></i> Show Registered Keys';
    }
}

function copyFingerprint(fp) {
    navigator.clipboard.writeText(fp).then(function() {
        const notification = document.createElement('div');
        notification.style.position = 'fixed';
        notification.style.bottom = '20px';
        notification.style.right = '20px';
        notification.style.background = '#4e73df';
        notification.style.color = '#fff';
        notification.style.padding = '12px 24px';
        notification.style.borderRadius = '8px';
        notification.style.boxShadow = '0 4px 15px rgba(0,0,0,0.15)';
        notification.style.fontWeight = 'bold';
        notification.style.fontSize = '14px';
        notification.style.zIndex = '9999';
        notification.innerHTML = '<i class="fa fa-check-circle"></i> Fingerprint copied to clipboard!';
        document.body.appendChild(notification);
        setTimeout(() => {
            notification.style.transition = 'opacity 0.5s ease';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 500);
        }, 2500);
    }, function() {
        var tempInput = document.createElement("input");
        tempInput.value = fp;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand("copy");
        document.body.removeChild(tempInput);
        alert('Fingerprint copied to clipboard!');
    });
}

document.getElementById('privateKeyFile').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async function(e) {
        const privateKeyPem = e.target.result;
        try {
            // Parse the private key
            const privateKey = forge.pki.privateKeyFromPem(privateKeyPem);
            
            // Derive public key parameters and compute fingerprint
            const publicKey = forge.pki.setRsaPublicKey(privateKey.n, privateKey.e);
            const publicKeyPem = forge.pki.publicKeyToPem(publicKey);
            const base64 = publicKeyPem.replace(/-----(BEGIN|END) (RSA )?PUBLIC KEY-----/g, '')
                                        .replace(/\s+/g, '');
            const md = forge.md.sha256.create();
            md.update(base64);
            const computedFingerprint = md.digest().toHex();
            
            console.log("Uploaded Private Key Public Fingerprint:", computedFingerprint);

            let decryptedCount = 0;
            let skippedCount = 0;
            let failedCount = 0;
            const uniqueSkippedFingerprints = new Set();

            const rows = document.querySelectorAll('.kyc-row');
            for (let row of rows) {
                const rowFingerprint = row.getAttribute('data-key-fingerprint');
                const encName = row.getAttribute('data-enc-name');
                const encNum = row.getAttribute('data-enc-num');
                const encSym = row.getAttribute('data-enc-sym');
                const ivB64 = row.getAttribute('data-iv');
                const imgPath = row.getAttribute('data-img-path');

                // Skip if fingerprint exists and doesn't match
                if (rowFingerprint && rowFingerprint !== computedFingerprint) {
                    skippedCount++;
                    uniqueSkippedFingerprints.add(rowFingerprint ? rowFingerprint.substring(0, 12) + '...' : 'Legacy');
                    continue;
                }

                let rowDecrypted = false;

                // 1. Decrypt Text (Name)
                if (encName) {
                    try {
                        const decodedName = forge.util.decode64(encName);
                        row.querySelector('.dec-name').innerText = privateKey.decrypt(decodedName);
                        row.querySelector('.dec-name').style.background = 'transparent';
                        row.querySelector('.dec-name').style.color = '#28a745';
                        row.querySelector('.dec-name').style.fontWeight = 'bold';
                        rowDecrypted = true;
                    } catch(err) { 
                        console.error("Name decryption error", err); 
                        failedCount++;
                        continue;
                    }
                }
                
                // 2. Decrypt Text (Number)
                if (encNum) {
                    try {
                        const decodedNum = forge.util.decode64(encNum);
                        row.querySelector('.dec-num').innerText = privateKey.decrypt(decodedNum);
                        row.querySelector('.dec-num').style.background = 'transparent';
                        row.querySelector('.dec-num').style.color = '#333';
                        row.querySelector('.dec-num').style.fontWeight = 'bold';
                        rowDecrypted = true;
                    } catch(err) { 
                        console.error("Number decryption error", err); 
                        failedCount++;
                        continue;
                    }
                }

                // 3. Decrypt Image (Hybrid: RSA -> AES-GCM)
                if (encSym && ivB64 && imgPath) {
                    try {
                        // Decrypt the AES key using RSA
                        const decodedSym = forge.util.decode64(encSym);
                        const aesKey = privateKey.decrypt(decodedSym);
                        const iv = forge.util.decode64(ivB64);

                        // Fetch the encrypted image blob from the server
                        const response = await fetch('kyc-image-proxy.php?file=' + imgPath);
                        if (!response.ok) throw new Error('Proxy returned ' + response.status);
                        const arrayBuffer = await response.arrayBuffer();
                        const encryptedBytes = forge.util.createBuffer(new Uint8Array(arrayBuffer));

                        // AES-GCM Decryption
                        const ciphertextLen = encryptedBytes.length() - 16;
                        const ciphertext = forge.util.createBuffer(encryptedBytes.getBytes(ciphertextLen));
                        const tag = forge.util.createBuffer(encryptedBytes.getBytes(16));

                        const decipher = forge.cipher.createDecipher('AES-GCM', aesKey);
                        decipher.start({ iv: iv, tag: tag });
                        decipher.update(ciphertext);
                        const pass = decipher.finish();

                        if (pass) {
                            const rawOutput = decipher.output.getBytes();
                            const uint8 = new Uint8Array(rawOutput.length);
                            for (let i = 0; i < rawOutput.length; i++) {
                                uint8[i] = rawOutput.charCodeAt(i);
                            }
                            
                            const blob = new Blob([uint8], { type: 'image/jpeg' });
                            const url = URL.createObjectURL(blob);
                            
                            const container = row.querySelector('.img-container');
                            container.innerHTML = `<a href="${url}" target="_blank"><img src="${url}" class="passport-thumb"></a>`;
                            container.style.background = 'transparent';
                            container.style.border = 'none';
                        }
                    } catch(err) {
                        console.error('Image decryption failed', err);
                    }
                }

                if (rowDecrypted) {
                    decryptedCount++;
                }
            }

            // Render status notification
            let statusHtml = `
                <div class="alert alert-success alert-dismissible" role="alert" style="margin-top: 15px;">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <strong><i class="fa fa-unlock"></i> Key Applied:</strong> Successfully decrypted <strong>\${decryptedCount}</strong> record(s) matching public key fingerprint <code style="font-size: 11px;">\${computedFingerprint.substring(0, 16)}...</code>
                </div>
            `;

            if (skippedCount > 0) {
                const skippedFpList = Array.from(uniqueSkippedFingerprints).join(', ');
                statusHtml += `
                    <div class="alert alert-warning alert-dismissible" role="alert" style="margin-top: 10px;">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <strong><i class="fa fa-exclamation-triangle"></i> Locked Records:</strong> <strong>\${skippedCount}</strong> record(s) were skipped because they require a different private key (Fingerprints: <code>\${skippedFpList}</code>).
                    </div>
                `;
            }

            if (failedCount > 0) {
                statusHtml += `
                    <div class="alert alert-danger alert-dismissible" role="alert" style="margin-top: 10px;">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <strong><i class="fa fa-times-circle"></i> Decryption Failed:</strong> Failed to decrypt <strong>\${failedCount}</strong> legacy/corrupted record(s) with this key.
                    </div>
                `;
            }

            // Inject the status container
            let statusContainer = document.getElementById('decryptionStatusContainer');
            if (!statusContainer) {
                statusContainer = document.createElement('div');
                statusContainer.id = 'decryptionStatusContainer';
                const parent = document.querySelector('.progressbar-heading');
                parent.after(statusContainer);
            }
            statusContainer.innerHTML = statusHtml;

            // Wipe private key from file input (security)
            document.getElementById('privateKeyFile').value = '';

        } catch(err) {
            console.error(err);
            alert("Invalid Private Key file! Could not parse. Please ensure it's a valid RSA Private Key in PEM format.");
        }
    };
    reader.readAsText(file);
});
</script>
</body>
</html>
