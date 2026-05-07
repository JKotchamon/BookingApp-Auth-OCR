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
<link href="css/bootstrap.css" rel="stylesheet" type="text/css" media="all">
<link href="css/style.css" rel="stylesheet" type="text/css" media="all" />

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

                    <!-- Connected Accounts Section -->
                    <div class="row" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                        <div class="col-md-12">
                            <h4>Connected Accounts</h4>
                            <p class="text-muted">Manage your social logins here. linking accounts makes it easier to sign in next time!</p>
                            
                            <div class="list-group">
                                <!-- Google -->
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fa fa-google" style="color: #db4437; margin-right: 10px;"></i>
                                        <strong>Google Account</strong>
                                        <?php if ($isGoogleLinked): ?>
                                            <span class="badge badge-success" style="background-color: #28a745; margin-left: 10px;">Connected</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary" style="margin-left: 10px;">Not Linked</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if ($isGoogleLinked): ?>
                                            <a href="unlink.php?provider=google" class="btn btn-sm btn-outline-danger" onclick="return confirm('r u sure u want to unlink google?');">Unlink</a>
                                        <?php else: ?>
                                            <a href="google-callback.php?mode=link" class="btn btn-sm btn-primary">Link Google</a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Microsoft -->
                                <div class="list-group-item d-flex justify-content-between align-items-center" style="margin-top: 10px;">
                                    <div>
                                        <i class="fa fa-windows" style="color: #00a4ef; margin-right: 10px;"></i>
                                        <strong>Microsoft Account</strong>
                                        <?php if ($isMsLinked): ?>
                                            <span class="badge badge-success" style="background-color: #28a745; margin-left: 10px;">Connected</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary" style="margin-left: 10px;">Not Linked</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if ($isMsLinked): ?>
                                            <a href="unlink.php?provider=microsoft" class="btn btn-sm btn-outline-danger" onclick="return confirm('unlink microsoft? u might loose easy access.');">Unlink</a>
                                        <?php else: ?>
                                            <a href="oauth-callback.php?mode=link" class="btn btn-sm btn-primary" style="background-color: #00a4ef; border-color: #00a4ef;">Link Microsoft</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
								<h5>Full Name</h5>
								<input type="text" value="<?php  echo $row->FullName;?>" name="fname" required="true" class="form-control">
								<h5>Mobile Number</h5>
								<input type="text" name="mobno" class="form-control" required="true" maxlength="10" pattern="[0-9]+" value="<?php  echo $row->MobileNumber;?>">
								<h5>Email Address</h5>
								<input type="email" class="form-control" value="<?php  echo $row->Email;?>" name="email" required="true" readonly='true'>
								<h5>Registration Date</h5>
								<input type="text" value="<?php  echo $row->RegDate;?>" class="form-control" name="password" readonly="true">
								<br /><?php $cnt=$cnt+1;}} ?>
								
								<br/>
								 <input type="submit" value="Update" name="submit">
						 	 </form>

						</div>
						<div class="col-md-6 contact-right">
							
						 	 <img src="<?php echo !empty($result->ProfilePhoto) ? $result->ProfilePhoto : 'images/img.jpg'; ?>" alt="Profile Photo" style="width:100px; height:100px; border-radius:50%; object-fit:cover;">

						</div>
						<div class="clearfix"></div>
					</div>
				</div>
			</div>
		<?php include_once('includes/getintouch.php');?>
			</div>
			<?php include_once('includes/footer.php');?>
</html><?php }  ?>
