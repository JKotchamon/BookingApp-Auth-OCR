<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/dbconnection.php';
require_once 'includes/oauth-config.php';
require_once 'vendor/autoload.php';

$provider = new League\OAuth2\Client\Provider\Google([
    'clientId'     => GOOGLE_CLIENT_ID,
    'clientSecret' => GOOGLE_CLIENT_SECRET,
    'redirectUri'  => GOOGLE_REDIRECT_URI,
]);

// Step 1: No code yet — redirect user to Google login
if (!isset($_GET['code'])) {
    $authUrl = $provider->getAuthorizationUrl([
        'scope' => ['openid', 'profile', 'email'],
    ]);
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: ' . $authUrl);
    exit;
}

// Step 2: Validate state to prevent CSRF
if (empty($_GET['state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
    unset($_SESSION['oauth2state']);
    exit('Invalid OAuth state. Please try again.');
}
unset($_SESSION['oauth2state']);

// Step 3: Exchange code for access token
try {
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code'],
    ]);
} catch (Exception $e) {
    exit('Failed to get access token: ' . $e->getMessage());
}

// Step 4: Get user profile from Google
try {
    $googleUser = $provider->getResourceOwner($token);
} catch (Exception $e) {
    exit('Failed to get user profile: ' . $e->getMessage());
}

$oauthId  = $googleUser->getId();
$email    = $googleUser->getEmail()    ?? '';
$fullName = $googleUser->getName()     ?? '';
$photoUrl = $googleUser->getAvatar()   ?? null;

if (empty($email)) {
    exit('Could not retrieve email from Google account.');
}

// Step 5: Save profile photo locally
$photoPath = null;
if ($photoUrl) {
    try {
        $photoDir = __DIR__ . '/images/oauth/';
        if (!is_dir($photoDir)) {
            mkdir($photoDir, 0755, true);
        }
        $photoFilename = 'google_' . $oauthId . '.jpg';
        $photoData = @file_get_contents($photoUrl);
        if ($photoData !== false) {
            file_put_contents($photoDir . $photoFilename, $photoData);
            $photoPath = 'images/oauth/' . $photoFilename;
        }
    } catch (Exception $e) {
        $photoPath = null;
    }
}

// Step 6: One email = one account. Look up by email (canonical identifier).
$stmt = $dbh->prepare("SELECT ID, Password, auth_method, oauth_provider, oauth_id
                       FROM tbluser WHERE Email = :email LIMIT 1");
$stmt->execute([':email' => $email]);
$existing = $stmt->fetch(PDO::FETCH_OBJ);

$isNewUser            = false;
$promptSetPassword    = false;

if ($existing) {
    // Existing account → link Google if not already linked, then sign in.
    $hasLocalPassword = !empty($existing->Password);
    $newAuthMethod    = $hasLocalPassword ? 'both' : 'oauth';

    $upd = $dbh->prepare("UPDATE tbluser SET
        FullName       = COALESCE(NULLIF(:name, ''), FullName),
        ProfilePhoto   = COALESCE(:photo, ProfilePhoto),
        oauth_id       = :oid,
        oauth_provider = 'google',
        auth_method    = :method
        WHERE ID = :id");
    $upd->execute([
        ':name'   => $fullName,
        ':photo'  => $photoPath,
        ':oid'    => $oauthId,
        ':method' => $newAuthMethod,
        ':id'     => $existing->ID,
    ]);

    $userId = (int)$existing->ID;

    // First time linking Google to a passwordless account → still offer set-password.
    $promptSetPassword = !$hasLocalPassword;
} else {
    // Brand new user → create with auth_method='oauth' (no password yet).
    $ins = $dbh->prepare(
        "INSERT INTO tbluser (FullName, Email, Password, auth_method, oauth_provider, oauth_id, ProfilePhoto)
         VALUES (:name, :email, '', 'oauth', 'google', :oid, :photo)"
    );
    $ins->execute([
        ':name'  => $fullName,
        ':email' => $email,
        ':oid'   => $oauthId,
        ':photo' => $photoPath,
    ]);

    $userId            = (int)$dbh->lastInsertId();
    $isNewUser         = true;
    $promptSetPassword = true;
}

// Maintain a row in tbl_oauth_links for analytics / multi-provider support.
$linkUpsert = $dbh->prepare(
    "INSERT INTO tbl_oauth_links (UserID, Provider, ProviderUserID, ProviderEmail, EmailVerified)
     VALUES (:uid, 'google', :pid, :pemail, 1)
     ON DUPLICATE KEY UPDATE
        ProviderUserID = VALUES(ProviderUserID),
        ProviderEmail  = VALUES(ProviderEmail),
        EmailVerified  = 1"
);
try {
    $linkUpsert->execute([
        ':uid'    => $userId,
        ':pid'    => $oauthId,
        ':pemail' => $email,
    ]);
} catch (PDOException $e) {
    // Non-fatal: the link table is auxiliary.
}

$_SESSION['hbmsuid'] = $userId;
$_SESSION['login']   = $email;

// Case 1, step 3: prompt to set password if user has none yet.
if ($promptSetPassword) {
    header('Location: set-password-prompt.php');
    exit;
}

header('Location: index.php');
exit;
