<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['hbmsaid']==0)) {
  header('location:logout.php');
  } else{
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Hotel Booking Management System | KYC Audit Log</title>
<script type="application/x-javascript"> addEventListener("load", function() { setTimeout(hideURLbar, 0); }, false); function hideURLbar(){ window.scrollTo(0,1); } </script>
<link href="css/bootstrap.min.css" rel='stylesheet' type='text/css' />
<link href="css/style.css" rel='stylesheet' type='text/css' />
<link href="css/font-awesome.css" rel="stylesheet"> 
<link href='//fonts.googleapis.com/css?family=Roboto:700,500,300,100italic,100,400' rel='stylesheet' type='text/css'/>
<link href='//fonts.googleapis.com/css?family=Montserrat:400,700' rel='stylesheet' type='text/css'>
<link rel="stylesheet" href="css/icon-font.min.css" type='text/css' />
<script src="js/jquery-1.10.2.min.js"></script>
<style>
    .badge-info { background-color: #31708f; }
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
						<h2>KYC Audit Log</h2>
					</div>
					<div class="panel panel-widget forms-panel">
						<div class="forms">
							<div class="form-grids widget-shadow" data-example-id="basic-forms"> 
								<div class="form-title">
									<h4>System Audit Logs </h4>
								</div>
								<div class="form-body">
									     <table class="table table-bordered table-striped table-vcenter js-dataTable-full-pagination">
                                <thead>
                                    <tr>
                                        <th class="text-center">S.No</th>
                                        <th>Date & Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>IP Address</th>
                                       </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (isset($_GET['pageno'])) {
            $pageno = $_GET['pageno'];
        } else {
            $pageno = 1;
        }
        $no_of_records_per_page = 20;
        $offset = ($pageno-1) * $no_of_records_per_page;
       $ret = "SELECT ID FROM tbl_kyc_audit_log";
$query1 = $dbh -> prepare($ret);
$query1->execute();
$results1=$query1->fetchAll(PDO::FETCH_OBJ);
$total_rows=$query1->rowCount();
$total_pages = ceil($total_rows / $no_of_records_per_page);

$sql="SELECT a.*, u.FullName, u.Email FROM tbl_kyc_audit_log a LEFT JOIN tbluser u ON a.user_id = u.ID ORDER BY a.created_at DESC LIMIT $offset, $no_of_records_per_page";
$query = $dbh -> prepare($sql);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);

$cnt=1 + $offset;
if($query->rowCount() > 0)
{
foreach($results as $row)
{               ?>
                                    <tr>
                                        <td class="text-center"><?php echo htmlentities($cnt);?></td>
                                        <td><span class="text-muted"><?php  echo htmlentities($row->created_at);?></span></td>
                                        <td>
                                            <strong><?php echo htmlentities($row->FullName ?? 'Unknown User');?></strong><br>
                                            <small><?php echo htmlentities($row->Email ?? '');?></small>
                                        </td>
                                        <td><span class="badge badge-info"><?php echo htmlentities($row->action);?></span></td>
                                        <td style="max-width: 300px; word-wrap: break-word;"><?php echo htmlentities($row->details);?></td>
                                        <td><small><code><?php echo htmlentities($row->ip_address);?></code></small></td>
                                    </tr>
                                    <?php $cnt=$cnt+1;}} else { ?>
                                    <tr><td colspan="6" class="text-center">No logs found.</td></tr>
                                    <?php } ?> 
                                </tbody>
                            </table>
<div align="left">
    <ul class="pagination" >
        <li><a href="?pageno=1"><strong>First</strong></a></li>
        <li class="<?php if($pageno <= 1){ echo 'disabled'; } ?>">
            <a href="<?php if($pageno <= 1){ echo '#'; } else { echo "?pageno=".($pageno - 1); } ?>"><strong style="padding-left: 10px">Prev</strong></a>
        </li>
        <li class="<?php if($pageno >= $total_pages){ echo 'disabled'; } ?>">
            <a href="<?php if($pageno >= $total_pages){ echo '#'; } else { echo "?pageno=".($pageno + 1); } ?>"><strong style="padding-left: 10px">Next</strong></a>
        </li>
        <li><a href="?pageno=<?php echo $total_pages; ?>"><strong style="padding-left: 10px">Last</strong></a></li>
    </ul>
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
</div>
			<?php include_once('includes/sidebar.php');?>
							  <div class="clearfix"></div>		
							</div>
<script src="js/jquery.nicescroll.js"></script>
<script src="js/scripts.js"></script>
   <script src="js/bootstrap.min.js"></script>
		   <script src="js/menu_jquery.js"></script>
</body>
</html><?php }  ?>
