<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['hbmsaid'] == 0)) {
    header('location:logout.php');
} else {
    $msg = '';
    $keyPath = __DIR__ . '/../includes/kyc_public_key.pem';

    // Handle File Upload
    if (isset($_POST['upload'])) {
        if (isset($_FILES['public_key']) && $_FILES['public_key']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['public_key']['tmp_name'];
            $fileExtension = strtolower(pathinfo($_FILES['public_key']['name'], PATHINFO_EXTENSION));

            if ($fileExtension === 'pem') {
                // Read the file contents and validate if it's a valid public key
                $keyContent = file_get_contents($fileTmpPath);
                $pubKey = openssl_pkey_get_public($keyContent);
                
                if ($pubKey !== false) {
                    if (move_uploaded_file($fileTmpPath, $keyPath)) {
                        $msg = "Success: RSA Public Key uploaded and system is now active.";
                    } else {
                        $msg = "Error: Could not save the file to the server.";
                    }
                } else {
                    $msg = "Error: The uploaded file is not a valid RSA Public Key.";
                }
            } else {
                $msg = "Error: Invalid file format. Only .pem files are allowed.";
            }
        } else {
            $msg = "Error: Please select a file to upload.";
        }
    }

    // Handle Key Removal
    if (isset($_POST['remove'])) {
        if (file_exists($keyPath)) {
            unlink($keyPath);
            $msg = "Success: Public Key removed. KYC system is now in maintenance mode.";
        }
    }

    $isKeyActive = file_exists($keyPath);
?>
<!DOCTYPE HTML>
<html>
<head>
<title>HBMS | KYC Settings</title>
<script type="application/x-javascript"> addEventListener("load", function() { setTimeout(hideURLbar, 0); }, false); function hideURLbar(){ window.scrollTo(0,1); } </script>
<link href="css/bootstrap.min.css" rel='stylesheet' type='text/css' />
<link href="css/style.css" rel='stylesheet' type='text/css' />
<link href="css/font-awesome.css" rel="stylesheet"> 
<link href='//fonts.googleapis.com/css?family=Roboto:700,500,300,100italic,100,400' rel='stylesheet' type='text/css'/>
<link href='//fonts.googleapis.com/css?family=Montserrat:400,700' rel='stylesheet' type='text/css'>
<link rel="stylesheet" href="css/icon-font.min.css" type='text/css' />
<script src="js/jquery-1.10.2.min.js"></script>
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
                                        <h4>KYC Cryptography Settings</h4>
                                    </div>
                                    <div class="form-body">
                                        <?php if($msg) { ?>
                                            <div class="alert alert-info"><?php echo htmlentities($msg); ?></div>
                                        <?php } ?>

                                        <p style="margin-bottom: 20px;">
                                            To enable the Customer KYC Verification system, you must provide an RSA Public Key (`.pem`).
                                            This key will be used by the server to safely encrypt all incoming KYC documents.
                                        </p>

                                        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; border: 1px solid #ddd;">
                                            <h4>System Status: 
                                                <?php if($isKeyActive) { ?>
                                                    <span style="color: green; font-weight: bold;"><i class="fa fa-check-circle"></i> Active (Key Configured)</span>
                                                <?php } else { ?>
                                                    <span style="color: red; font-weight: bold;"><i class="fa fa-warning"></i> Offline (Missing Key)</span>
                                                <?php } ?>
                                            </h4>
                                            
                                            <hr>

                                            <?php if(!$isKeyActive) { ?>
                                            <form method="post" enctype="multipart/form-data">
                                                <div class="form-group">
                                                    <label for="public_key">Upload `public_key.pem`</label>
                                                    <input type="file" name="public_key" id="public_key" accept=".pem" required>
                                                </div>
                                                <button type="submit" name="upload" class="btn btn-primary">Upload & Enable KYC</button>
                                            </form>
                                            <?php } else { ?>
                                            <form method="post">
                                                <p>The system is currently protecting customer data using the uploaded Public Key.</p>
                                                <button type="submit" name="remove" class="btn btn-danger" onclick="return confirm('Are you sure? Removing the key will disable the KYC system for customers.');">Remove Key (Maintenance Mode)</button>
                                            </form>
                                            <?php } ?>
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
