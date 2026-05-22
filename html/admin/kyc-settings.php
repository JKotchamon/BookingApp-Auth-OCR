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
    .key-table th {
        background: #f1f3f5;
        font-weight: 600;
        color: #495057;
    }
    .key-table td {
        vertical-align: middle !important;
    }
    .fingerprint-text {
        font-family: 'Courier New', Courier, monospace;
        font-weight: bold;
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 4px;
        border: 1px solid #e9ecef;
        font-size: 13px;
        word-break: break-all;
    }
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 11px;
        display: inline-block;
    }
    .status-active {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .status-inactive {
        background: #e2e3e5;
        color: #383d41;
        border: 1px solid #d6d8db;
    }
    .copy-btn {
        background: none;
        border: none;
        color: #007bff;
        cursor: pointer;
        padding: 0 5px;
        transition: color 0.2s;
    }
    .copy-btn:hover {
        color: #0056b3;
    }
    .info-card {
        border-left: 5px solid #17a2b8;
        background: #f3fafd;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 25px;
    }
    .btn-action {
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 600;
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
                                            <p style="margin-top: 5px; font-size: 13.5px; color: #495057;">
                                                The admin can maintain multiple RSA public keys. 
                                                When a customer uploads their passport, the system encrypts the records using the <strong>Active Key</strong> and stores its unique SHA-256 fingerprint. 
                                                To decrypt the records client-side in the Review Queue, you must upload the matching RSA Private Key. 
                                                <em>Note: Private keys are never stored on the server for maximum security.</em>
                                            </p>
                                        </div>

                                        <!-- System Status Banner -->
                                        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; border: 1px solid #ddd; margin-bottom: 30px;">
                                            <h4 style="margin: 0; display: flex; align-items: center; justify-content: space-between;">
                                                <span>System Encryption Status: 
                                                    <?php if($isSystemActive) { ?>
                                                        <span style="color: green; font-weight: bold;"><i class="fa fa-check-circle"></i> Active & Secure</span>
                                                    <?php } else { ?>
                                                        <span style="color: red; font-weight: bold;"><i class="fa fa-warning"></i> Offline (No Active Key)</span>
                                                    <?php } ?>
                                                </span>
                                            </h4>
                                        </div>

                                        <!-- Upload Form -->
                                        <div class="row" style="margin-bottom: 30px;">
                                            <div class="col-md-6">
                                                <div class="panel panel-default" style="border: 1px solid #e3e6f0; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                                                    <div class="panel-heading" style="background-color: #f8f9fc; font-weight: bold; color: #4e73df;">
                                                        Upload New Public Key
                                                    </div>
                                                    <div class="panel-body">
                                                        <form method="post" enctype="multipart/form-data">
                                                            <div class="form-group">
                                                                <label for="public_key" style="font-weight: 500;">Select RSA Public Key (.pem)</label>
                                                                <input type="file" name="public_key" id="public_key" accept=".pem" required class="form-control-file">
                                                                <small class="form-text text-muted" style="display: block; margin-top: 5px;">Only valid RSA public keys in .pem format are accepted.</small>
                                                            </div>
                                                            <button type="submit" name="upload" class="btn btn-primary btn-sm"><i class="fa fa-upload"></i> Upload Public Key</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Registered Keys Table -->
                                        <div class="panel panel-default" style="border: 1px solid #e3e6f0; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                                            <div class="panel-heading" style="background-color: #f8f9fc; font-weight: bold; color: #4e73df; display: flex; justify-content: space-between; align-items: center;">
                                                <span>Registered Public Keys</span>
                                                <span class="badge" style="background: #4e73df;"><?php echo count($keys); ?> Keys Found</span>
                                            </div>
                                            <div class="panel-body" style="padding: 0;">
                                                <div class="table-responsive">
                                                    <table class="table table-hover key-table" style="margin-bottom: 0;">
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
                                                                            <div style="display: flex; gap: 5px; justify-content: center;">
                                                                                <?php if(!$keyData['is_active']) { ?>
                                                                                    <form method="post" style="display: inline-block;">
                                                                                        <input type="hidden" name="fingerprint" value="<?php echo $keyData['fingerprint']; ?>">
                                                                                        <button type="submit" name="activate" class="btn btn-success btn-xs btn-action"><i class="fa fa-check"></i> Activate</button>
                                                                                    </form>
                                                                                    <form method="post" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this key? Records encrypted with this key will become undecryptable unless you possess the corresponding private key.');">
                                                                                        <input type="hidden" name="fingerprint" value="<?php echo $keyData['fingerprint']; ?>">
                                                                                        <button type="submit" name="remove" class="btn btn-danger btn-xs btn-action"><i class="fa fa-trash"></i> Delete</button>
                                                                                    </form>
                                                                                <?php } else { ?>
                                                                                    <span style="font-size: 12px; color: #1cc88a; font-weight: bold;"><i class="fa fa-lock"></i> Locked (Currently Active)</span>
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
            alert('Fingerprint copied to clipboard!');
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
