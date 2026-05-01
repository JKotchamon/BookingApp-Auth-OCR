<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$token = is_string($token) ? trim($token) : '';

$error    = '';
$done     = false;
$tokenRow = null;

function fetch_link_token(PDO $dbh, string $token)
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return null;
    }
    $stmt = $dbh->prepare(
        "SELECT v.ID AS TokenID, v.UserID, v.Provider, v.ProviderUserID,
                v.ProviderEmail, v.FullName AS SnapName, v.PhotoPath AS SnapPhoto,
                v.DateOfBirth AS SnapDob, v.ExpiresAt, v.UsedAt,
                u.Email AS UserEmail, u.FullName AS UserName, u.Password,
                u.oauth_provider, u.oauth_id
         FROM tbl_email_verifications v
         JOIN tbluser u ON u.ID = v.UserID
         WHERE v.Token = :tok
         LIMIT 1"
    );
    $stmt->execute([':tok' => $token]);
    return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
}

$tokenRow = fetch_link_token($dbh, $token);

if (!$tokenRow) {
    $error = 'This confirmation link is invalid. Please start the linking process again from the sign-in page.';
} elseif ($tokenRow->UsedAt !== null) {
    $error = 'This confirmation link has already been used. Your account is already linked, so just sign in normally.';
} elseif (strtotime($tokenRow->ExpiresAt) < time()) {
    $error = 'This confirmation link has expired. Please try linking again from the sign-in page.';
} elseif (strcasecmp((string)$tokenRow->ProviderEmail, (string)$tokenRow->UserEmail) !== 0) {
    // Sanity check: the email on the OAuth side must still match the local
    // account's email — otherwise the canonical "one email = one account"
    // invariant has been violated since the email was issued.
    $error = 'The email addresses no longer match. Please try again.';
}

if (!$error) {
    $dbh->beginTransaction();
    try {
        // Apply the link to tbluser. Local password stays intact, so
        // auth_method becomes 'both'. Refresh cached profile snapshot from
        // the OAuth provider too.
        $upd = $dbh->prepare(
            "UPDATE tbluser SET
                FullName       = COALESCE(NULLIF(:name, ''), FullName),
                ProfilePhoto   = COALESCE(:photo, ProfilePhoto),
                DateOfBirth    = COALESCE(:dob, DateOfBirth),
                oauth_provider = :prov,
                oauth_id       = :oid,
                auth_method    = 'both'
             WHERE ID = :uid"
        );
        $upd->execute([
            ':name'  => (string)$tokenRow->SnapName,
            ':photo' => $tokenRow->SnapPhoto,
            ':dob'   => $tokenRow->SnapDob,
            ':prov'  => (string)$tokenRow->Provider,
            ':oid'   => (string)$tokenRow->ProviderUserID,
            ':uid'   => (int)$tokenRow->UserID,
        ]);

        // Mark the verification token as used so it can't be replayed.
        $mark = $dbh->prepare(
            "UPDATE tbl_email_verifications
             SET UsedAt = NOW(), EmailVerified = 1
             WHERE ID = :tid"
        );
        $mark->execute([':tid' => (int)$tokenRow->TokenID]);

        // Keep the multi-provider link table in sync.
        $linkUpsert = $dbh->prepare(
            "INSERT INTO tbl_oauth_links
                (UserID, Provider, ProviderUserID, ProviderEmail, EmailVerified)
             VALUES (:uid, :prov, :pid, :pemail, 1)
             ON DUPLICATE KEY UPDATE
                ProviderUserID = VALUES(ProviderUserID),
                ProviderEmail  = VALUES(ProviderEmail),
                EmailVerified  = 1"
        );
        try {
            $linkUpsert->execute([
                ':uid'    => (int)$tokenRow->UserID,
                ':prov'   => (string)$tokenRow->Provider,
                ':pid'    => (string)$tokenRow->ProviderUserID,
                ':pemail' => (string)$tokenRow->ProviderEmail,
            ]);
        } catch (PDOException $e) {
            // Non-fatal — the auxiliary link table failing should not abort the link.
        }

        $dbh->commit();
        $done = true;

        // Drop any leftover pending state from the OAuth callback.
        unset($_SESSION['pending_link']);

        // Sign the user in: ownership of the inbox (and their existing
        // local password identity) has now been positively confirmed.
        $_SESSION['hbmsuid'] = (int)$tokenRow->UserID;
        $_SESSION['login']   = (string)$tokenRow->UserEmail;
    } catch (Throwable $e) {
        $dbh->rollBack();
        $error = 'Could not finish linking your account. Please try again.';
    }
}

$providerLabel = $tokenRow ? ucfirst((string)$tokenRow->Provider) : 'OAuth';
?>
<!DOCTYPE HTML>
<html>
<head>
<title>Hotel Booking Management System | Confirm Account Link</title>
<link href="css/bootstrap.css" rel="stylesheet" type="text/css" media="all">
<link href="css/style.css" rel="stylesheet" type="text/css" media="all" />
<script src="js/jquery-1.11.1.min.js"></script>
<script src="js/bootstrap.js"></script>
</head>
<body>
    <div class="header head-top">
        <div class="container">
            <?php include_once('includes/header.php'); ?>
        </div>
    </div>

    <div class="content">
        <div class="contact">
            <div class="container">
                <h2>Account linking</h2>

                <?php if ($done): ?>
                    <div class="col-md-6 contact-right">
                        <div style="background:#e7f7ec; border:1px solid #b7e0c2; color:#205c32;
                                    padding:14px 18px; border-radius:6px;">
                            <strong>All set!</strong> Your <?php echo htmlentities($providerLabel); ?>
                            account is now linked to your HBMS Hotel Booking account.
                            From now on you can sign in with either your password or
                            <?php echo htmlentities($providerLabel); ?>.
                        </div>
                        <p style="margin-top:18px;">
                            <a href="index.php" style="color:#2563eb;">Continue to home &rarr;</a>
                            &nbsp;·&nbsp;
                            <a href="profile.php">View my profile</a>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="col-md-6 contact-right">
                        <div style="background:#fdecea; border:1px solid #f5b5b0; color:#7a1f17;
                                    padding:14px 18px; border-radius:6px;">
                            <?php echo htmlentities($error); ?>
                        </div>
                        <p style="margin-top:18px;">
                            <a href="signin.php" style="color:#2563eb;">Back to sign in</a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include_once('includes/getintouch.php'); ?>
    <?php include_once('includes/footer.php'); ?>
</body>
</html>
