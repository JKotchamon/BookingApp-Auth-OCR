<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
require_once(__DIR__ . '/../includes/encryption.php');

if (strlen($_SESSION['hbmsaid'] == 0)) {
    header('location:logout.php');
} else {
    $msg = '';
    $msgType = 'info'; // info, success, danger

    // Handle File Upload
    if (isset($_POST['upload'])) {
        if (isset($_FILES['public_key']) && $_FILES['public_key']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['public_key']['tmp_name'];
            $fileExtension = strtolower(pathinfo($_FILES['public_key']['name'], PATHINFO_EXTENSION));

            if ($fileExtension === 'pem') {
                $keyContent = file_get_contents($fileTmpPath);
                $pubKey = openssl_pkey_get_public($keyContent);
                
                if ($pubKey !== false) {
                    $fingerprint = getPublicKeyFingerprint($keyContent);
                    if (!empty($fingerprint)) {
                        $keysDir = __DIR__ . '/../keys';
                        if (!file_exists($keysDir)) {
                            mkdir($keysDir, 0755, true);
                        }
                        
                        $destPath = $keysDir . '/pubkey_' . $fingerprint . '.pem';
                        if (file_put_contents($destPath, $keyContent) !== false) {
                            // If no active fingerprint is set, set this one
                            $activeFile = $keysDir . '/active_fingerprint.txt';
                            if (!file_exists($activeFile) || filesize($activeFile) == 0) {
                                file_put_contents($activeFile, $fingerprint);
                            }
                            $msg = "Success: Public Key uploaded successfully. Fingerprint: " . substr($fingerprint, 0, 16) . "...";
                            $msgType = "success";
                        } else {
                            $msg = "Error: Could not save the file to the server.";
                            $msgType = "danger";
                        }
                    } else {
                        $msg = "Error: Failed to compute key fingerprint.";
                        $msgType = "danger";
                    }
                } else {
                    $msg = "Error: The uploaded file is not a valid RSA Public Key.";
                    $msgType = "danger";
                }
            } else {
                $msg = "Error: Invalid file format. Only .pem files are allowed.";
                $msgType = "danger";
            }
        } else {
            $msg = "Error: Please select a file to upload.";
            $msgType = "danger";
        }
    }

    // Handle Key Activation
    if (isset($_POST['activate'])) {
        $fp = $_POST['fingerprint'];
        if (!empty($fp)) {
            $keysDir = __DIR__ . '/../keys';
            $activeFile = $keysDir . '/active_fingerprint.txt';
            if (file_put_contents($activeFile, $fp) !== false) {
                $msg = "Success: Selected key (" . substr($fp, 0, 12) . "...) is now active for encryption.";
                $msgType = "success";
            } else {
                $msg = "Error: Failed to update active key.";
                $msgType = "danger";
            }
        }
    }

    // Handle Key Removal
    if (isset($_POST['remove'])) {
        $fp = $_POST['fingerprint'];
        $activeFP = getActivePublicKeyFingerprint();
        
        if ($fp === $activeFP) {
            $msg = "Error: Cannot remove the currently active key. Please activate another key first.";
            $msgType = "danger";
        } else {
            $legacyPath = __DIR__ . '/../keys/kyc_public_key.pem';
            $isLegacy = false;
            if (file_exists($legacyPath)) {
                $legacyFP = getPublicKeyFingerprint(file_get_contents($legacyPath));
                if ($fp === $legacyFP) {
                    $isLegacy = true;
                }
            }
            
            $pathToRemove = $isLegacy ? $legacyPath : (__DIR__ . '/../keys/pubkey_' . $fp . '.pem');
            if (file_exists($pathToRemove)) {
                if (unlink($pathToRemove)) {
                    $msg = "Success: Public key deleted successfully.";
                    $msgType = "success";
                } else {
                    $msg = "Error: Failed to delete key file.";
                    $msgType = "danger";
                }
            } else {
                $msg = "Error: Key file not found on disk.";
                $msgType = "danger";
            }
        }
    }

    // Gather all public keys
    $keys = [];
    $activeFingerprint = getActivePublicKeyFingerprint();
    
    // Check legacy key
    $legacyPath = __DIR__ . '/../keys/kyc_public_key.pem';
    if (file_exists($legacyPath)) {
        $content = file_get_contents($legacyPath);
        $fp = getPublicKeyFingerprint($content);
        $keys[$fp] = [
            'name' => 'Legacy Key (kyc_public_key.pem)',
            'fingerprint' => $fp,
            'mtime' => filemtime($legacyPath),
            'is_active' => (!empty($activeFingerprint) && $activeFingerprint === $fp)
        ];
    }
    
    // Check other keys
    $otherKeys = glob(__DIR__ . '/../keys/pubkey_*.pem');
    foreach ($otherKeys as $path) {
        $content = file_get_contents($path);
        $fp = getPublicKeyFingerprint($content);
        if (isset($keys[$fp])) {
            continue;
        }
        $keys[$fp] = [
            'name' => 'Public Key (' . substr($fp, 0, 8) . ')',
            'fingerprint' => $fp,
            'mtime' => filemtime($path),
            'is_active' => ($activeFingerprint === $fp)
        ];
    }
    
    $isSystemActive = !empty($activeFingerprint);
?>
<!DOCTYPE HTML>
<html>
<head>
<title>HBMS | KYC Cryptography Settings</title>
<script type="application/x-javascript"> addEventListener("load", function() { setTimeout(hideURLbar, 0); }, false); function hideURLbar(){ window.scrollTo(0,1); } </script>
<link href="css/bootstrap.min.css" rel='stylesheet' type='text/css' />
<link href="css/style.css" rel='stylesheet' type='text/css' />
<link href="css/font-awesome.css" rel="stylesheet"> 
<link href='//fonts.googleapis.com/css?family=Roboto:700,500,300,100italic,100,400' rel='stylesheet' type='text/css'/>
<link href='//fonts.googleapis.com/css?family=Montserrat:400,700' rel='stylesheet' type='text/css'>
<link rel="stylesheet" href="css/icon-font.min.css" type='text/css' />
<script src="js/jquery-1.10.2.min.js"></script>
<style>
    .premium-panel {
        background: #ffffff;
        border: 1px solid rgba(0, 0, 0, 0.06);
        border-radius: 14px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.02);
        margin-bottom: 30px;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    .premium-panel:hover {
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.04);
    }
    .premium-panel-heading {
        background: #f8fafc;
        padding: 18px 24px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        font-weight: 700;
        color: #1e293b;
        font-size: 15px;
        letter-spacing: 0.3px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .premium-panel-body {
        padding: 24px;
    }
    .info-card {
        border-left: 5px solid #475569;
        background: #f8fafc;
        border-radius: 8px;
        padding: 18px 22px;
        margin-bottom: 25px;
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.02);
    }
    .info-card h5 {
        color: #1e293b;
        font-weight: 700;
        margin-top: 0;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 15px;
    }
    .status-banner {
        border-radius: 10px;
        padding: 18px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 25px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.01);
    }
    .status-banner.active {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }
    .status-banner.inactive {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }
    .status-banner-title {
        font-size: 15px;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        color: inherit !important;
    }
    .status-banner-title span,
    .status-banner-title i {
        color: inherit !important;
    }
    .status-banner-badge {
        font-size: 14px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: inherit !important;
    }
    .status-banner-badge span,
    .status-banner-badge i {
        color: inherit !important;
    }
    .custom-upload-zone {
        border: 2px dashed #475569;
        background: #f8fafc;
        border-radius: 12px;
        padding: 26px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
    }
    .custom-upload-zone:hover {
        border-color: #0f172a;
        background: #f1f5f9;
        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.08);
    }
    .custom-upload-zone.dragover {
        border-color: #1cc88a;
        background: #f0fdf4;
        box-shadow: 0 4px 15px rgba(28, 200, 138, 0.08);
    }
    .upload-zone-icon {
        font-size: 38px;
        color: #1e293b;
        margin-bottom: 10px;
        transition: transform 0.3s ease;
    }
    .custom-upload-zone:hover .upload-zone-icon {
        transform: translateY(-4px);
    }
    .upload-zone-text {
        font-size: 14.5px;
        font-weight: 500;
        color: #334155;
        display: block;
        margin-bottom: 4px;
    }
    .browse-btn-text {
        color: #0f172a;
        font-weight: 700;
        text-decoration: underline;
    }
    .upload-zone-sub {
        font-size: 12px;
        color: #64748b;
        display: block;
    }
    .file-name-container {
        margin-top: 14px;
        background: #ffffff;
        padding: 6px 14px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .btn-premium-primary {
        background: linear-gradient(135deg, #334155 0%, #0f172a 100%);
        color: white !important;
        border: none;
        border-radius: 8px;
        padding: 10px 22px;
        font-weight: 600;
        font-size: 13.5px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.2);
    }
    .btn-premium-primary:hover {
        background: linear-gradient(135deg, #1e293b 0%, #020617 100%);
        box-shadow: 0 6px 15px rgba(15, 23, 42, 0.3);
        transform: translateY(-1.5px);
        text-decoration: none;
    }
    .btn-premium-primary:active {
        transform: translateY(0);
    }
    .key-table th {
        background: #f8fafc;
        font-weight: 700;
        color: #475569;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        padding: 14px 18px !important;
        border-bottom: 2px solid #e2e8f0 !important;
    }
    .key-table td {
        vertical-align: middle !important;
        padding: 14px 18px !important;
        font-size: 13.5px;
        color: #334155;
        border-bottom: 1px solid #e2e8f0 !important;
    }
    .key-table tr {
        transition: background-color 0.15s ease;
    }
    .key-table tr:hover {
        background-color: #f8fafc;
    }
    .fingerprint-text {
        font-family: 'Courier New', Courier, monospace;
        font-weight: 700;
        background: #f8fafc;
        padding: 5px 9px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        font-size: 12.5px;
        word-break: break-all;
        color: #334155;
        display: inline-block;
    }
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 11px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .status-active {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .status-inactive {
        background: #f1f3f5;
        color: #495057;
        border: 1px solid #e9ecef;
    }
    .copy-btn {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #475569;
        cursor: pointer;
        padding: 5px 9px;
        border-radius: 6px;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-left: 6px;
    }
    .copy-btn:hover {
        background: #1e293b;
        color: white;
        border-color: #1e293b;
        transform: scale(1.05);
    }
    .btn-action-activate {
        background: #1cc88a;
        color: white !important;
        border: none;
        border-radius: 6px;
        padding: 6px 14px;
        font-weight: 600;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 5px rgba(28, 200, 138, 0.15);
    }
    .btn-action-activate:hover {
        background: #17a673;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(28, 200, 138, 0.25);
    }
    .btn-action-delete {
        background: #e74a3b;
        color: white !important;
        border: none;
        border-radius: 6px;
        padding: 6px 14px;
        font-weight: 600;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 5px rgba(231, 74, 59, 0.15);
    }
    .btn-action-delete:hover {
        background: #be2617;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(231, 74, 59, 0.25);
    }
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
                        <div class="panel panel-widget forms-panel">
                            <div class="forms">
                                <div class="form-grids widget-shadow" data-example-id="basic-forms"> 
                                    <div class="form-title">
                                        <h4>KYC Key Management & Fingerprinting</h4>
                                    </div>
                                    <div class="form-body">
                                        <?php if($msg) { ?>
                                            <div class="alert alert-<?php echo $msgType; ?> alert-dismissible" role="alert">
                                                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                                <?php echo htmlentities($msg); ?>
                                            </div>
                                        <?php } ?>

                                        <div class="info-card">
                                            <h5><i class="fa fa-info-circle"></i> Multiple Asymmetric Keys Support</h5>
                                            <p style="margin-top: 5px; font-size: 13.5px; color: #495057; line-height: 1.6;">
                                                The admin can maintain multiple RSA public keys. 
                                                When a customer uploads their passport, the system encrypts the records using the <strong>Active Key</strong> and stores its unique SHA-256 fingerprint. 
                                                To decrypt the records client-side in the Review Queue, you must upload the matching RSA Private Key. 
                                                <em>Note: Private keys are never stored on the server for maximum security.</em>
                                            </p>
                                        </div>

                                        <!-- System Status Banner -->
                                        <div class="status-banner <?php echo $isSystemActive ? 'active' : 'inactive'; ?>">
                                            <h4 class="status-banner-title">
                                                <i class="fa <?php echo $isSystemActive ? 'fa-shield' : 'fa-warning'; ?>" style="font-size: 20px;"></i>
                                                <span>System Encryption Status:</span>
                                            </h4>
                                            <span class="status-banner-badge">
                                                <?php if($isSystemActive) { ?>
                                                    <span style="font-weight: 700;"><i class="fa fa-check-circle"></i> Active & Secure</span>
                                                <?php } else { ?>
                                                    <span style="font-weight: 700;"><i class="fa fa-times-circle"></i> Offline (No Active Key)</span>
                                                <?php } ?>
                                            </span>
                                        </div>

                                        <!-- Upload Form -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="premium-panel">
                                                    <div class="premium-panel-heading">
                                                        <span><i class="fa fa-key"></i> Upload New Public Key</span>
                                                    </div>
                                                    <div class="premium-panel-body">
                                                        <form method="post" enctype="multipart/form-data" id="key-upload-form">
                                                            <div class="form-group">
                                                                <label style="font-weight: 600; color: #495057; font-size: 14px; margin-bottom: 10px; display: block;">Select RSA Public Key (.pem)</label>
                                                                
                                                                <div class="custom-upload-zone" id="upload-zone">
                                                                    <input type="file" name="public_key" id="public_key" accept=".pem" required style="display: none;">
                                                                    <div class="upload-zone-content">
                                                                        <i class="fa fa-cloud-upload upload-zone-icon"></i>
                                                                        <span class="upload-zone-text">Drag & drop your public key here, or <span class="browse-btn-text">browse</span></span>
                                                                        <span class="upload-zone-sub">Only valid RSA public keys in .pem format are accepted.</span>
                                                                        <div id="file-name-container" class="file-name-container" style="display: none;">
                                                                            <i class="fa fa-file-code-o"></i> <span id="selected-file-name" class="selected-file-name">filename.pem</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <button type="submit" name="upload" class="btn-premium-primary"><i class="fa fa-upload"></i> Upload Public Key</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Registered Keys Table -->
                                        <div class="premium-panel">
                                            <div class="premium-panel-heading">
                                                <span><i class="fa fa-list-alt"></i> Registered Public Keys</span>
                                                <span class="badge" style="background: #1e293b; font-weight: 700; padding: 6px 12px; font-size: 11px; border-radius: 12px;"><?php echo count($keys); ?> Keys Found</span>
                                            </div>
                                            <div class="premium-panel-body" style="padding: 0;">
                                                <div class="table-responsive">
                                                    <table class="table key-table" style="margin-bottom: 0;">
                                                        <thead>
                                                            <tr>
                                                                <th style="width: 120px; text-align: center;">Status</th>
                                                                <th>Key Identifier</th>
                                                                <th>SHA-256 Public Key Fingerprint</th>
                                                                <th style="width: 180px;">Last Modified</th>
                                                                <th style="width: 200px; text-align: center;">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if(empty($keys)) { ?>
                                                                <tr>
                                                                    <td colspan="5" style="text-align: center; padding: 25px; color: #858796;">
                                                                        <i class="fa fa-key" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                                                                        No cryptographic public keys found on the server. Upload a key to begin.
                                                                    </td>
                                                                </tr>
                                                            <?php } else { ?>
                                                                <?php foreach($keys as $fingerprint => $keyData) { ?>
                                                                    <tr>
                                                                        <td style="text-align: center;">
                                                                            <?php if($keyData['is_active']) { ?>
                                                                                <span class="status-badge status-active"><i class="fa fa-circle"></i> Active</span>
                                                                            <?php } else { ?>
                                                                                <span class="status-badge status-inactive">Inactive</span>
                                                                            <?php } ?>
                                                                        </td>
                                                                        <td>
                                                                            <strong><?php echo htmlentities($keyData['name']); ?></strong>
                                                                        </td>
                                                                        <td>
                                                                            <span class="fingerprint-text"><?php echo $keyData['fingerprint']; ?></span>
                                                                            <button type="button" class="copy-btn" onclick="copyFingerprint('<?php echo $keyData['fingerprint']; ?>')" title="Copy Fingerprint">
                                                                                <i class="fa fa-copy"></i>
                                                                            </button>
                                                                        </td>
                                                                        <td>
                                                                            <?php echo date('Y-m-d H:i:s', $keyData['mtime']); ?>
                                                                        </td>
                                                                        <td style="text-align: center;">
                                                                            <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                                                                <?php if(!$keyData['is_active']) { ?>
                                                                                    <form method="post" style="display: inline-block; margin: 0;">
                                                                                        <input type="hidden" name="fingerprint" value="<?php echo $keyData['fingerprint']; ?>">
                                                                                        <button type="submit" name="activate" class="btn-action-activate"><i class="fa fa-check"></i> Activate</button>
                                                                                    </form>
                                                                                    <form method="post" style="display: inline-block; margin: 0;" onsubmit="return confirm('Are you sure you want to delete this key? Records encrypted with this key will become undecryptable unless you possess the corresponding private key.');">
                                                                                        <input type="hidden" name="fingerprint" value="<?php echo $keyData['fingerprint']; ?>">
                                                                                        <button type="submit" name="remove" class="btn-action-delete"><i class="fa fa-trash"></i> Delete</button>
                                                                                    </form>
                                                                                <?php } else { ?>
                                                                                    <span style="font-size: 13px; color: #1cc88a; font-weight: bold; display: inline-flex; align-items: center; gap: 4px;"><i class="fa fa-lock"></i> Locked (Active)</span>
                                                                                <?php } ?>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php } ?>
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
                    </div>
                </div>
                <?php include_once('includes/footer.php');?>
			</div>
		</div>
	</div>
    <?php include_once('includes/sidebar.php');?>
    <div class="clearfix"></div>		
</div>
<script src="js/jquery.nicescroll.js"></script>
<script src="js/scripts.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
    function copyFingerprint(fp) {
        navigator.clipboard.writeText(fp).then(function() {
            // Premium brief notification instead of alert
            const notification = document.createElement('div');
            notification.style.position = 'fixed';
            notification.style.bottom = '20px';
            notification.style.right = '20px';
            notification.style.background = '#1e293b';
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
            // Fallback
            var tempInput = document.createElement("input");
            tempInput.value = fp;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand("copy");
            document.body.removeChild(tempInput);
            alert('Fingerprint copied to clipboard!');
        });
    }

    // Drag and drop and styled file input integration
    $(document).ready(function() {
        const uploadZone = document.getElementById('upload-zone');
        const fileInput = document.getElementById('public_key');
        const selectedFileName = document.getElementById('selected-file-name');
        const fileNameContainer = document.getElementById('file-name-container');

        if (uploadZone && fileInput) {
            // Handle propagation to prevent recursion
            fileInput.addEventListener('click', (e) => {
                e.stopPropagation();
            });

            // Trigger click on file input when clicking the zone
            uploadZone.addEventListener('click', () => {
                fileInput.click();
            });

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    selectedFileName.textContent = fileInput.files[0].name;
                    fileNameContainer.style.display = 'inline-flex';
                    uploadZone.style.borderColor = '#1cc88a';
                    uploadZone.style.background = '#f0fdf4';
                } else {
                    fileNameContainer.style.display = 'none';
                    uploadZone.style.borderColor = '#475569';
                    uploadZone.style.background = '#f8fafc';
                }
            });

            // Drag over
            uploadZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadZone.classList.add('dragover');
            });

            // Drag leave
            uploadZone.addEventListener('dragleave', () => {
                uploadZone.classList.remove('dragover');
            });

            // Drop file
            uploadZone.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    // Trigger change manually
                    const event = new Event('change');
                    fileInput.dispatchEvent(event);
                }
            });
        }
    });

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
</body>
</html>
<?php } ?>
