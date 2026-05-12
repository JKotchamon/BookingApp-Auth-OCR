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
