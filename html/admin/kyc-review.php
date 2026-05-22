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
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <th>User (OAuth)</th>
                                                    <th>Passport Details</th>
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
                                                        ?>
                                                <tr class="kyc-row" 
                                                    data-enc-name="<?php echo htmlentities($row->full_name_encrypted); ?>" 
                                                    data-enc-num="<?php echo htmlentities($row->document_number_enc); ?>"
                                                    data-enc-sym="<?php echo htmlentities($row->symmetric_key_enc); ?>"
                                                    data-iv="<?php echo htmlentities($row->iv); ?>"
                                                    data-img-path="<?php echo urlencode($row->temp_image_path); ?>">
                                                    <td>
                                                        <strong><?php echo htmlentities($row->OAuthName); ?></strong><br>
                                                        <small><?php echo htmlentities($row->Email); ?></small>
                                                    </td>
                                                    <td>
                                                        Name: <code class="dec-name" style="color:#666; background:#eee; padding:2px 5px;">[Encrypted Data]</code><br>
                                                        Doc#: <code class="dec-num" style="color:#666; background:#eee; padding:2px 5px;">[Encrypted Data]</code>
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
                                                    <td colspan="6" class="text-center">No pending verifications found.</td>
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
document.getElementById('privateKeyFile').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async function(e) {
        const privateKeyPem = e.target.result;
        try {
            // Parse the private key
            const privateKey = forge.pki.privateKeyFromPem(privateKeyPem);
            
            const rows = document.querySelectorAll('.kyc-row');
            for (let row of rows) {
                const encName = row.getAttribute('data-enc-name');
                const encNum = row.getAttribute('data-enc-num');
                const encSym = row.getAttribute('data-enc-sym');
                const ivB64 = row.getAttribute('data-iv');
                const imgPath = row.getAttribute('data-img-path');

                // 1. Decrypt Text (Name)
                if (encName) {
                    try {
                        const decodedName = forge.util.decode64(encName);
                        // PHP default openssl_public_encrypt uses RSAES-PKCS1-v1_5
                        row.querySelector('.dec-name').innerText = privateKey.decrypt(decodedName);
                        row.querySelector('.dec-name').style.background = 'transparent';
                        row.querySelector('.dec-name').style.color = '#d9534f';
                    } catch(err) { console.error("Name decryption error", err); }
                }
                
                // 2. Decrypt Text (Number)
                if (encNum) {
                    try {
                        const decodedNum = forge.util.decode64(encNum);
                        row.querySelector('.dec-num').innerText = privateKey.decrypt(decodedNum);
                        row.querySelector('.dec-num').style.background = 'transparent';
                        row.querySelector('.dec-num').style.color = '#333';
                    } catch(err) { console.error("Number decryption error", err); }
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
                        // In PHP, tag is appended to the end (last 16 bytes)
                        const ciphertextLen = encryptedBytes.length() - 16;
                        const ciphertext = forge.util.createBuffer(encryptedBytes.getBytes(ciphertextLen));
                        const tag = forge.util.createBuffer(encryptedBytes.getBytes(16));

                        const decipher = forge.cipher.createDecipher('AES-GCM', aesKey);
                        decipher.start({ iv: iv, tag: tag });
                        decipher.update(ciphertext);
                        const pass = decipher.finish();

                        if (pass) {
                            // Convert forge buffer to Blob
                            const rawOutput = decipher.output.getBytes();
                            const uint8 = new Uint8Array(rawOutput.length);
                            for (let i = 0; i < rawOutput.length; i++) {
                                uint8[i] = rawOutput.charCodeAt(i);
                            }
                            
                            const blob = new Blob([uint8], { type: 'image/jpeg' });
                            const url = URL.createObjectURL(blob);
                            
                            // Render image safely without base64 bloat
                            const container = row.querySelector('.img-container');
                            container.innerHTML = `<a href="${url}" target="_blank"><img src="${url}" class="passport-thumb"></a>`;
                            container.style.background = 'transparent';
                            container.style.border = 'none';
                        }
                    } catch(err) {
                        console.error('Image decryption failed', err);
                    }
                }
            }

            // Wipe private key from file input (security)
            document.getElementById('privateKeyFile').value = '';

        } catch(err) {
            console.error(err);
            alert("Invalid Private Key file! Could not parse.");
        }
    };
    reader.readAsText(file);
});
</script>
</body>
</html>
