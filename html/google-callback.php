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
$stmt = $dbh->prepare("SELECT ID, FullName, Password, auth_method, oauth_provider, oauth_id
                       FROM tbluser WHERE Email = :email LIMIT 1");
$stmt->execute([':email' => $email]);
$existing = $stmt->fetch(PDO::FETCH_OBJ);

if ($existing) {
    $hasLocalPassword     = !empty($existing->Password);
    $googleAlreadyLinked  = ($existing->oauth_provider === 'google'
                              && !empty($existing->oauth_id)
                              && (string)$existing->oauth_id === (string)$oauthId);

    if ($googleAlreadyLinked) {
        // Already linked → just refresh profile snapshot and sign in.
        $upd = $dbh->prepare("UPDATE tbluser SET
            FullName     = COALESCE(NULLIF(:name, ''), FullName),
            ProfilePhoto = COALESCE(:photo, ProfilePhoto)
            WHERE ID = :id");
        $upd->execute([
            ':name'  => $fullName,
            ':photo' => $photoPath,
            ':id'    => $existing->ID,
        ]);

        // Keep tbl_oauth_links row in sync.
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
                ':uid'    => (int)$existing->ID,
                ':pid'    => $oauthId,
                ':pemail' => $email,
            ]);
        } catch (PDOException $e) {
            // Non-fatal.
        }

        $_SESSION['hbmsuid'] = (int)$existing->ID;
        $_SESSION['login']   = $email;
        header('Location: index.php');
        exit;
    }

    if ($hasLocalPassword) {
        // Case 2 — local account exists with a password but Google is NOT yet
        // linked. We must obtain explicit consent via an email confirmation
        // before joining the two identities. DO NOT log the user in here.
        $_SESSION['pending_link'] = [
            'user_id'          => (int)$existing->ID,
            'email'            => $email,
            'full_name_local'  => (string)($existing->FullName ?? ''),
            'provider'         => 'google',
            'provider_user_id' => (string)$oauthId,
            'provider_email'   => $email,
            'full_name'        => (string)$fullName,
            'photo_path'       => $photoPath,
            'date_of_birth'    => null,
        ];
        unset($_SESSION['hbmsuid'], $_SESSION['login']);
        header('Location: link-account-prompt.php');
        exit;
    }

    // Existing OAuth-only account (no password) — safe to attach Google
    // transparently and sign in (Case 1 territory: we'll still offer
    // a set-password email so they get a usable local credential).
    $upd = $dbh->prepare("UPDATE tbluser SET
        FullName       = COALESCE(NULLIF(:name, ''), FullName),
        ProfilePhoto   = COALESCE(:photo, ProfilePhoto),
        oauth_id       = :oid,
        oauth_provider = 'google',
        auth_method    = 'oauth'
        WHERE ID = :id");
    $upd->execute([
        ':name'  => $fullName,
        ':photo' => $photoPath,
        ':oid'   => $oauthId,
        ':id'    => $existing->ID,
    ]);

    $userId            = (int)$existing->ID;
    $promptSetPassword = true;
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
