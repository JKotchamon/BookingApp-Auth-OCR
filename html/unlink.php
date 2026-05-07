<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['hbmsuid'] == 0)) {
    header('location:logout.php');
    exit;
}

$uid      = $_SESSION['hbmsuid'];
$provider = $_GET['provider'] ?? '';

if (!in_array($provider, ['google', 'microsoft'])) {
    exit('invalid provider bro.');
}

// safety check: dont let them unlink if it's their only way in!
// we check if they have a password OR at least one OTHER link
$stmt = $dbh->prepare("SELECT Password, 
                              (SELECT COUNT(*) FROM tbl_oauth_links WHERE UserID = :uid AND Provider != :provider) as other_links
                       FROM tbluser WHERE ID = :uid");
$stmt->execute([':uid' => $uid, ':provider' => $provider]);
$user = $stmt->fetch(PDO::FETCH_OBJ);

$hasPassword   = !empty($user->Password);
$hasOtherLinks = ($user->other_links > 0);

if (!$hasPassword && !$hasOtherLinks) {
    echo "<script>alert('u cant unlink this! u dont have a password or any other social account linked. u would be locked out forever lol.');</script>";
    echo "<script>window.location.href='profile.php'</script>";
    exit;
}

// ok safe to delete
$del = $dbh->prepare("DELETE FROM tbl_oauth_links WHERE UserID = :uid AND Provider = :provider");
$del->execute([':uid' => $uid, ':provider' => $provider]);

// update the main user table to reflect the change
// if they still have links, auth_method stays 'both' (if it was both) or 'oauth'
// if they have NO links left, it must be 'local' (since they must have a password to get here)
if (!$hasOtherLinks) {
    $upd = $dbh->prepare("UPDATE tbluser SET 
        auth_method    = 'local',
        oauth_provider = NULL,
        oauth_id       = NULL
        WHERE ID = :uid");
    $upd->execute([':uid' => $uid]);
} else {
    // still have other links, just clear the primary oauth columns if they matched the one being deleted
    $upd = $dbh->prepare("UPDATE tbluser SET 
        oauth_provider = (SELECT Provider FROM tbl_oauth_links WHERE UserID = :uid LIMIT 1),
        oauth_id       = (SELECT ProviderUserID FROM tbl_oauth_links WHERE UserID = :uid LIMIT 1)
        WHERE ID = :uid AND oauth_provider = :provider");
    $upd->execute([':uid' => $uid, ':provider' => $provider]);
}

header('location:profile.php?msg=unlinked');
?>
