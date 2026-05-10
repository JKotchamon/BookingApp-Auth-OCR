<?php
if (isset($_SESSION['hbmsuid']) && !empty($_SESSION['hbmsuid'])) {
    $uid = $_SESSION['hbmsuid'];
    $sql = "SELECT kyc_status FROM tbluser WHERE ID = :uid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':uid', $uid, PDO::PARAM_INT);
    $query->execute();
    $userKyc = $query->fetch(PDO::FETCH_ASSOC);
    
    if ($userKyc && $userKyc['kyc_status'] !== 'verified') {
        $bannerMsg = "";
        $bannerClass = "kyc-banner-danger";
        
        switch($userKyc['kyc_status']) {
            case 'unverified':
                $bannerMsg = "Action Required: Your identity is not verified. You cannot book rooms until verification is complete.";
                break;
            case 'pending':
                $bannerMsg = "Status: Your identity verification is currently under review by our administration team.";
                $bannerClass = "kyc-banner-warning";
                break;
            case 'rejected':
                $bannerMsg = "Alert: Identity verification failed. Please review your documents and try again.";
                break;
            case 'expired':
                $bannerMsg = "Alert: Your identity verification has expired. Please re-verify to continue booking.";
                break;
        }

        if ($bannerMsg) {
            echo '<style>
                .kyc-global-banner { 
                    padding: 12px 0; 
                    text-align: center; 
                    font-weight: 600; 
                    font-size: 14px; 
                    position: relative; 
                    color: #fff; 
                    z-index: 1000; 
                    border-bottom: 2px solid rgba(0,0,0,0.1);
                    width: 100vw;
                    margin-left: calc(-50vw + 50%);
                    left: 0;
                }
                .kyc-banner-danger { background: linear-gradient(90deg, #d9534f 0%, #c9302c 100%); }
                .kyc-banner-warning { background: linear-gradient(90deg, #f0ad4e 0%, #ec971f 100%); }
                .btn-kyc-banner { background: rgba(255,255,255,0.15); border: 1px solid #fff; color: #fff; padding: 4px 15px; border-radius: 20px; margin-left: 15px; text-decoration: none; transition: all 0.3s ease; font-size: 12px; text-transform: uppercase; }
                .btn-kyc-banner:hover { background: #fff; color: #333; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
                @media (max-width: 768px) { .btn-kyc-banner { display: block; margin: 10px auto 0; width: fit-content; } }
            </style>';
            echo '<div class="kyc-global-banner ' . $bannerClass . '">
                    <div class="container">
                        <i class="glyphicon glyphicon-exclamation-sign" style="margin-right: 8px;"></i> ' . $bannerMsg . ' 
                        <a href="kyc-status.php" class="btn-kyc-banner">View Status</a>
                    </div>
                  </div>';
        }
    }
}
?>
    <div class="header-top">
                        <nav class="navbar navbar-default">
                            <div class="container-fluid">
    <!-- Brand and toggle get grouped for better mobile display -->
                                <div class="navbar-header">
                                      <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
                                            <span class="sr-only">Toggle navigation</span>
                                            <span class="icon-bar"></span>
                                            <span class="icon-bar"></span>
                                            <span class="icon-bar"></span>
                                      </button>
                                    <div class="navbar-brand">
                                        <h1><a href="index.php">HBMS</a></h1>
                                    </div>
                                </div>

    <!-- Collect the nav links, forms, and other content for toggling -->
                            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                              <ul class="nav navbar-nav">
                                    <li class="active"><a href="index.php">Home <span class="sr-only">(current)</span></a></li>
                                    <li><a href="about.php">About</a></li>
                                    <li><a href="services.php">Facilities</a></li>
                                    <li class="dropdown">
                                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Rooms <span class="caret"></span></a>
                                <ul class="dropdown-menu">
                                    <?php
$ret="SELECT * from tblcategory";
$query1 = $dbh -> prepare($ret);
$query1->execute();
$resultss=$query1->fetchAll(PDO::FETCH_OBJ);
foreach($resultss as $rows)
{               ?>
                                   <li><a href="category-details.php?catid=<?php echo htmlentities($rows->ID)?>"><?php echo htmlentities($rows->CategoryName)?></a></li>
                                    <?php } ?> 
                                </ul>
                                    </li>
                                    <li><a href="gallery.php">Gallery</a></li>
                                    <li><a href="contact.php">Contact</a></li>
                                     <?php if (strlen($_SESSION['hbmsuid']==0)) {?>
                                    <li><a href="admin/login.php">Admin</a></li>

                                    <li><a href="signup.php">Sign Up</a></li>
                                    <li><a href="signin.php">Login</a></li><?php } ?>
                                    <?php if (strlen($_SESSION['hbmsuid']!=0)) {?>
                                    <li class="dropdown">
                                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">My Account <span class="caret"></span></a>
                                <ul class="dropdown-menu">
                                    <li><a href="profile.php">Profile</a></li>
                                    <li><a href="my-booking.php">My Booking</a></li>
                                    <li><a href="change-password.php">Change Password</a></li>
                                    <li><a href="logout.php">Logout</a></li>
                                    
                                </ul>
                                    </li><?php } ?>
                                </ul>
                              
                            </div><!-- /.navbar-collapse -->
  </div><!-- /.container-fluid -->
                        </nav>

                    </div>