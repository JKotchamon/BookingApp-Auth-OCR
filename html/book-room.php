<?php
require('includes/dbconnection.php');
session_start();
error_reporting(E_ALL);
if (!isset($_SESSION['hbmsuid'])) {
    header('location:logout.php');
    exit;
} else {
    // ==============================================================
    // KYC Verification Check for Booking
    // --------------------------------------------------------------
    // Ensure the user is KYC-verified and that their verification
    // status is current. Automatically update expired verifications.
    // ==============================================================
    $userId = (int)$_SESSION['hbmsuid'];
    $stmt = $dbh->prepare(
        'SELECT kyc_status, kyc_expiry_date FROM tbluser WHERE ID = :uid LIMIT 1'
    );
    $stmt->execute([':uid' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_OBJ);

    $verificationStatus = $user->kyc_status ?? 'unverified';
    $expiryDate = $user->kyc_expiry_date ?? null;

    // Check for expired KYC status and update if necessary
    if ($verificationStatus === 'verified' && $expiryDate && strtotime($expiryDate) <= time()) {
        $dbh->prepare("UPDATE tbluser SET kyc_status='expired' WHERE ID = :uid")
            ->execute([':uid' => $userId]);
        $verificationStatus = 'expired';
    }

    // Redirect based on KYC status
    if ($verificationStatus !== 'verified') {
        $roomId = intval($_GET['rmid']);
        if ($verificationStatus === 'pending' || $verificationStatus === 'rejected') {
            header("Location: kyc-status.php?reason=booking&rmid=$roomId");
        } else {
            header("Location: kyc-verify.php?reason=booking&rmid=$roomId");
        }
        exit;
    }
    // ==============================================================
    // End of KYC Verification Check
    // ==============================================================

 if(isset($_POST['submit']))
  {

$bookingNumber = mt_rand(100000000, 999999999);
 $roomId = intval($_GET['rmid']);
 $userId = $_SESSION['hbmsuid'];
     $idType = $_POST['idtype'];
    $gender = $_POST['gender'];
    $address = $_POST['address'];
    $checkInDate = $_POST['checkindate'];
    $checkOutDate = $_POST['checkoutdate'];
   
$currentDate = date('Y-m-d');
if($checkInDate <  $currentDate){
 echo '<script>alert("Check-in date must be after today.")</script>';
} else if($checkInDate > $checkOutDate)
{
echo '<script>alert("Check-out date must be on or after check-in date.")</script>'; 
} else {
$insertQuery = "INSERT INTO tblbooking(RoomId, BookingNumber, UserID, IDType, Gender, Address, CheckinDate, CheckoutDate) VALUES (:roomId, :bookingNumber, :userId, :idType, :gender, :address, :checkInDate, :checkOutDate)";
$insertStmt = $dbh->prepare($insertQuery);
$insertStmt->bindParam(':roomId', $roomId, PDO::PARAM_STR);
$insertStmt->bindParam(':bookingNumber', $bookingNumber, PDO::PARAM_STR);
$insertStmt->bindParam(':userId', $userId, PDO::PARAM_STR);
$insertStmt->bindParam(':idType', $idType, PDO::PARAM_STR);
$insertStmt->bindParam(':gender', $gender, PDO::PARAM_STR);
$insertStmt->bindParam(':address', $address, PDO::PARAM_STR);
$insertStmt->bindParam(':checkInDate', $checkInDate, PDO::PARAM_STR);
$insertStmt->bindParam(':checkOutDate', $checkOutDate, PDO::PARAM_STR);
$insertStmt->execute();

   $lastInsertId = $dbh->lastInsertId();
   if ($lastInsertId > 0) {
   echo '<script>alert("Your booking request has been submitted. Booking Number: " + '.$bookingNumber.')</script>';

echo "<script>window.location.href ='index.php'</script>";
  }
  else
    {
         echo '<script>alert("An error occurred. Please try again.")</script>';
    }

  }
}
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Hotel Booking Management System | Book Your Room</title>
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
					<h2>Book Your Room</h2>
					
				<div class="contact-grids">
					
						<div class="col-md-6 contact-right">
							<form method="post">
					
									</select>
									<?php
$userId = $_SESSION['hbmsuid'];
$fetchQuery = "SELECT * FROM tbluser WHERE ID = :uid";
$fetchStmt = $dbh->prepare($fetchQuery);
$fetchStmt->bindParam(':uid', $userId, PDO::PARAM_STR);
$fetchStmt->execute();
$userDetails = $fetchStmt->fetchAll(PDO::FETCH_OBJ);
$count = 1;
if($fetchStmt->rowCount() > 0)
{
foreach($userDetails as $user)
{               ?>
								<h5>Name</h5>
								<input type="text" value="<?php echo $user->FullName; ?>" name="name" class="form-control" required readonly>
								<h5>Mobile Number</h5>
								<input type="text" name="phone" class="form-control" required maxlength="10" pattern="[0-9]+" value="<?php echo $user->MobileNumber; ?>" readonly>
								<h5>Email Address</h5>
								<input type="email" value="<?php echo $user->Email; ?>" class="form-control" name="email" required readonly><?php $count++; }} ?>
								<h5>ID Type</h5>
								<select class="form-control" name="idtype" required>
									<option value="">Select ID Type</option>
									<option value="Voter Card">Voter Card</option>
									<option value="Aadhar Card">Aadhar Card</option>
									<option value="Driving License">Driving License</option>
									<option value="Passport">Passport</option>
								</select>
								<h5>Gender</h5>
								<p style="text-align: left;"><input type="radio" name="gender" value="Female" checked>Female</p>
								<p style="text-align: left;"><input type="radio" name="gender" value="Male">Male</p>
								<h5>Address</h5>
								<textarea rows="10" name="address" required></textarea>
								<h5>Check-in Date</h5>
								<input type="date" class="form-control" name="checkindate" required>
								<h5>Check-out Date</h5>
								<input type="date" class="form-control" name="checkoutdate" required>
								<input type="submit" value="Submit" name="submit">
							</form>
						</div>
						<div class="clearfix"></div>
					</div>
				</div>
			</div>
		<?php include_once('includes/getintouch.php');?>
			</div>
			<?php include_once('includes/footer.php');?>
</html><?php } ?>
<?php
include('includes/dbconnection.php');
session_start();
error_reporting(0);
if (empty($_SESSION['hbmsuid'])) {
    header('location:logout.php');
    exit;
} else {
    // =========================================================================
    // KYC BOOKING GATE
    // -------------------------------------------------------------------------
    // No booking can be confirmed unless the user is KYC-verified and the KYC
    // is not expired. Auto-flips 'verified -> expired' when past expiry so the
    // gate stays in sync with kyc-status.php's own expiry handling.
    // =========================================================================
    $gateUid = (int)$_SESSION['hbmsuid'];
    $gateQ = $dbh->prepare(
        'SELECT kyc_status, kyc_expiry_date FROM tbluser WHERE ID = :uid LIMIT 1'
    );
    $gateQ->execute([':uid' => $gateUid]);
    $gateUser = $gateQ->fetch(PDO::FETCH_OBJ);

    $gateStatus = $gateUser->kyc_status       ?? 'unverified';
    $gateExpiry = $gateUser->kyc_expiry_date ?? null;

    // Auto-expire: a KYC marked 'verified' but past its expiry date is treated
    // as expired and the DB is silently updated to match.
    if ($gateStatus === 'verified' && $gateExpiry && strtotime($gateExpiry) <= time()) {
        $dbh->prepare("UPDATE tbluser SET kyc_status='expired' WHERE ID = :uid")
            ->execute([':uid' => $gateUid]);
        $gateStatus = 'expired';
    }

    // If anything other than 'verified', block the booking flow and route the
    // user to the appropriate KYC page. Users with a submission already in flight
    // (pending / flagged) go to kyc-status.php to read their state; everyone else
    // goes straight to the upload form (per design Q1=A).
    if ($gateStatus !== 'verified') {
        $rmid = intval($_GET['rmid']);
        if ($gateStatus === 'pending' || $gateStatus === 'rejected') {
            header("Location: kyc-status.php?reason=booking&rmid=$rmid");
        } else {
            header("Location: kyc-verify.php?reason=booking&rmid=$rmid");
        }
        exit;
    }
    // =========================================================================
    // END KYC BOOKING GATE
    // =========================================================================

 if(isset($_POST['submit']))
  {

$booknum=mt_rand(100000000, 999999999);
 $rid=intval($_GET['rmid']);
 $uid=$_SESSION['hbmsuid'];
     $idtype=$_POST['idtype'];
    $gender=$_POST['gender'];
    $address=$_POST['address'];
    $checkindate=$_POST['checkindate'];
    $checkoutdate=$_POST['checkoutdate'];
   
 $cdate=date('Y-m-d');
if($checkindate <  $cdate){
 echo '<script>alert("Check in date must be greater than current date")</script>';
} else if($checkindate > $checkoutdate)
{
echo '<script>alert("Check out date must be equal to / greater than  check in date")</script>';	
} else {
$sql="insert into tblbooking(RoomId,BookingNumber,UserID,IDType,Gender,Address,CheckinDate,CheckoutDate)values(:rid,:booknum,:uid,:idtype,:gender,:address,:checkindate,:checkoutdate)";
$query=$dbh->prepare($sql);
$query->bindParam(':rid',$rid,PDO::PARAM_STR);
$query->bindParam(':booknum',$booknum,PDO::PARAM_STR);
$query->bindParam(':uid',$uid,PDO::PARAM_STR);
$query->bindParam(':idtype',$idtype,PDO::PARAM_STR);
$query->bindParam(':gender',$gender,PDO::PARAM_STR);
$query->bindParam(':address',$address,PDO::PARAM_STR);
$query->bindParam(':checkindate',$checkindate,PDO::PARAM_STR);
$query->bindParam(':checkoutdate',$checkoutdate,PDO::PARAM_STR);
 $query->execute();

   $LastInsertId=$dbh->lastInsertId();
   if ($LastInsertId>0) {
   echo '<script>alert("Your room booking request has been sent to us. This does not guarantee a booking. Booking Number is "+"'.$booknum.'")</script>';

echo "<script>window.location.href ='index.php'</script>";
  }
  else
    {
         echo '<script>alert("Something Went Wrong. Please try again")</script>';
    }

  }
}
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Hotel Booking Management System | Hotel :: Book Room</title>
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
					<h2>Book Your Room</h2>
					
				<div class="contact-grids">
					
						<div class="col-md-6 contact-right">
							<form method="post">
					
									
								</select>
								<?php
$uid=$_SESSION['hbmsuid'];
$sql="SELECT * from  tbluser where ID=:uid";
$query = $dbh -> prepare($sql);
$query->bindParam(':uid',$uid,PDO::PARAM_STR);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $row)
{               ?>
								<h5>Name</h5>
								<input type="text"  value="<?php  echo $row->FullName;?>" name="name" class="form-control" required="true" readonly="true">
								<h5>Mobile Number</h5>
								<input type="text" name="phone" class="form-control" required="true" maxlength="10" pattern="[0-9]+" value="<?php  echo $row->MobileNumber;?>" readonly="true">
								<h5>Email Address</h5>
								<input  type="email" value="<?php  echo $row->Email;?>" class="form-control" name="email" required="true" readonly="true"><?php $cnt=$cnt+1;}} ?>
								<h5>ID Type</h5>
								<select  type="text" value="" class="form-control" name="idtype" required="true" class="form-control">
									<option value="">Choose ID Type</option>
									<option value="Voter Card">Voter Card</option>
									<option value="Adhar Card">Adhar Card</option>
									<option value="Driving Licence Card">Driving Licence Card</option>
									<option value="Passport">Passport</option>
									</select>
									<h5>Gender</h5>
								 <p style="text-align: left;"> <input type="radio"  name="gender" id="gender" value="Female" checked="true">Female</p>
             
                                 <p style="text-align: left;"> <input type="radio" name="gender" id="gender" value="Male">Male</p>
                               
								<h5>Address</h5>
								 <textarea type="text" rows="10" name="address" required="true"></textarea>
								 <h5>Checkin Date</h5>
								<input  type="date" value="" class="form-control" name="checkindate" required="true">
								<h5>Checkout Date</h5>
								<input  type="date" value="" class="form-control" name="checkoutdate" required="true">
								
								 <input type="submit" value="send" name="submit">
						 	 </form>
						</div>
						<div class="clearfix"></div>
					</div>
				</div>
			</div>
		<?php include_once('includes/getintouch.php');?>
			</div>
			<?php include_once('includes/footer.php');?>
</html><?php }  ?>
