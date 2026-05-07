<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['hbmsuid']==0)) {
  header('location:logout.php');
  } else{
    if(isset($_POST['submit']))
  {
    $uid=$_SESSION['hbmsuid'];
    $AName=$_POST['fname'];
  $mobno=$_POST['mobno'];
  $sql="update tbluser set FullName=:name,MobileNumber=:mobilenumber where ID=:uid";
     $query = $dbh->prepare($sql);
     $query->bindParam(':name',$AName,PDO::PARAM_STR);
     $query->bindParam(':mobilenumber',$mobno,PDO::PARAM_STR);
     $query->bindParam(':uid',$uid,PDO::PARAM_STR);
$query->execute();

        echo '<script>alert("Profile has been updated")</script>';
     

  }
  ?>
<!DOCTYPE HTML>
<html>
<head>
<title>Hotel Booking Management System | Hotel :: Profile</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link href="css/bootstrap.css" rel="stylesheet" type="text/css" media="all">
<link href="css/style.css" rel="stylesheet" type="text/css" media="all" />
<link href="css/auth.css" rel="stylesheet" type="text/css" media="all" />

<script type="application/x-javascript"> addEventListener("load", function() { setTimeout(hideURLbar, 0); }, false); function hideURLbar(){ window.scrollTo(0,1); } </script>
<script src="js/jquery-1.11.1.min.js"></script>
<script src="js/bootstrap.js"></script>
<script src="js/responsiveslides.min.js"></script>
 <script>
    $(function () {
      $("#slider").responsiveSlides({
      	auto: true,
      	nav: true,
      	speed: 500,
        namespace: "callbacks",
        pager: true,
      });
    });
  </script>

</head>
<body>
		<!--header-->
			<div class="header head-top">
				<div class="container">
			<?php include_once('includes/header.php');?>
		</div>
</div>
<!--header-->
		<!--about-->
		
			<div class="content">
				<div class="contact">
				<div class="container">
					
					<h2>View Your Profile !!!!!!</h2>
					
				<div class="contact-grids">
					
						<div class="col-md-6 contact-right">
							<form method="post">
								<?php
$uid = $_SESSION['hbmsuid'];
$sql = "SELECT * FROM tbluser WHERE ID = :uid";
$query = $dbh->prepare($sql);
$query->bindParam(':uid', $uid, PDO::PARAM_STR);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);

// fetch existing oauth links for this user
$linksSql = "SELECT Provider FROM tbl_oauth_links WHERE UserID = :uid";
$linksQuery = $dbh->prepare($linksSql);
$linksQuery->execute([':uid' => $uid]);
$linkedProviders = $linksQuery->fetchAll(PDO::FETCH_COLUMN);

$isGoogleLinked = in_array('google', $linkedProviders);
$isMsLinked     = in_array('microsoft', $linkedProviders);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $row) { $result = $row; ?>

                    <!-- Status Messages -->
                    <?php if ($_GET['msg'] === 'linked'): ?>
                        <div class="alert alert-success">Account successfully linked!</div>
                    <?php elseif ($_GET['msg'] === 'unlinked'): ?>
                        <div class="alert alert-warning">Account unlinked successfully.</div>
                    <?php endif; ?>

                    <!-- Connected Accounts Section -->
                    <div class="row" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 30px; margin-bottom: 30px;">
                        <div class="col-md-12">
                            <h3 style="margin-bottom: 15px; font-weight: 600; color: #333;">Connected Accounts</h3>
                            <p class="text-muted" style="margin-bottom: 25px;">Manage your social identities. Linking accounts allows you to sign in securely with one click.</p>
                            
                            <div class="row">
                                <!-- Google -->
                                <div class="col-md-6" style="margin-bottom: 20px;">
                                    <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                        <div style="display: flex; align-items: center;">
                                            <div style="width: 40px; height: 40px; background: #f8f9fa; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                                <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.08 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-3.59-13.46-8.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; font-size: 15px; color: #333;">Google</div>
                                                <div style="font-size: 13px; color: <?php echo $isGoogleLinked ? '#28a745' : '#777'; ?>;">
                                                    <?php echo $isGoogleLinked ? 'Connected' : 'Not Connected'; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <?php if ($isGoogleLinked): ?>
                                                <a href="unlink.php?provider=google" class="btn btn-sm btn-outline-danger" style="border-radius: 20px; padding: 5px 15px;" onclick="return confirm('r u sure u want to unlink google?');">Unlink</a>
                                            <?php else: ?>
                                                <a href="google-callback.php?mode=link" class="btn btn-sm btn-primary" style="border-radius: 20px; padding: 5px 15px;">Link</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Microsoft -->
                                <div class="col-md-6" style="margin-bottom: 20px;">
                                    <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                        <div style="display: flex; align-items: center;">
                                            <div style="width: 40px; height: 40px; background: #f8f9fa; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                                <svg width="20" height="20" viewBox="0 0 21 21"><rect x="1" y="1" width="9" height="9" fill="#F25022"/><rect x="11" y="1" width="9" height="9" fill="#7FBA00"/><rect x="1" y="11" width="9" height="9" fill="#00A4EF"/><rect x="11" y="11" width="9" height="9" fill="#FFB900"/></svg>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; font-size: 15px; color: #333;">Microsoft</div>
                                                <div style="font-size: 13px; color: <?php echo $isMsLinked ? '#00a4ef' : '#777'; ?>;">
                                                    <?php echo $isMsLinked ? 'Connected' : 'Not Connected'; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <?php if ($isMsLinked): ?>
                                                <a href="unlink.php?provider=microsoft" class="btn btn-sm btn-outline-danger" style="border-radius: 20px; padding: 5px 15px;" onclick="return confirm('unlink microsoft?');">Unlink</a>
                                            <?php else: ?>
                                                <a href="oauth-callback.php?mode=link" class="btn btn-sm btn-primary" style="border-radius: 20px; padding: 5px 15px; background-color: #00a4ef; border-color: #00a4ef;">Link</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <h4 style="margin-bottom: 20px; font-weight: 600;">Personal Information</h4>
                            <h5>Full Name</h5>
                            <input type="text" value="<?php echo $row->FullName;?>" name="fname" required="true" class="form-control">
                            <h5>Mobile Number</h5>
                            <input type="text" name="mobno" class="form-control" required="true" maxlength="10" pattern="[0-9]+" value="<?php echo $row->MobileNumber;?>">
                            <h5>Email Address</h5>
                            <input type="email" class="form-control" value="<?php echo $row->Email;?>" name="email" required="true" readonly='true'>
                            <h5>Registration Date</h5>
                            <input type="text" value="<?php echo $row->RegDate;?>" class="form-control" name="regdate" readonly="true">
                            <br />
                            <input type="submit" value="Update Profile" name="submit" class="btn-auth-primary btn-submit">
                        </div>
                        <div class="col-md-4 text-center">
                            <div style="margin-top: 50px;">
                                <img src="<?php echo !empty($result->ProfilePhoto) ? $result->ProfilePhoto : 'images/img.jpg'; ?>" alt="Profile Photo" style="width:150px; height:150px; border-radius:50%; object-fit:cover; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                <p class="text-muted" style="margin-top: 15px;">Profile Picture</p>
                                <p style="font-size: 12px; color: #999;">Automatically synced from your linked social account.</p>
                            </div>
                        </div>
                    </div>
                    <?php $cnt=$cnt+1;}} ?>
						<div class="clearfix"></div>
					</div>
						<div class="clearfix"></div>
					</div>
				</div>
			</div>
		<?php include_once('includes/getintouch.php');?>
			</div>
			<?php include_once('includes/footer.php');?>
</html><?php }  ?>
