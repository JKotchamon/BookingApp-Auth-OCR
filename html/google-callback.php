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
    // if we r in linking mode, remember it in the sesion
    if (isset($_GET['mode']) && $_GET['mode'] === 'link') {
        $_SESSION['oauth_action'] = 'link';
    } else {
        unset($_SESSION['oauth_action']);
    }

    $authUrl = $provider->getAuthorizationUrl([
        'scope' => ['openid', 'profile', 'email',
                    'https://www.googleapis.com/auth/user.birthday.read'],
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

// Fetch Date of Birth from Google People API
// (requires the user.birthday.read scope; may be null if not set in Google account)
$dob = null;
try {
    $ctx = stream_context_create(['http' => [
        'header'  => 'Authorization: Bearer ' . $token->getToken(),
        'timeout' => 5,
    ]]);
    $peopleRaw = @file_get_contents(
        'https://people.googleapis.com/v1/people/me?personFields=birthdays',
        false, $ctx
    );
    if ($peopleRaw !== false) {
        $peopleData = json_decode($peopleRaw, true);
        $bday = $peopleData['birthdays'][0]['date'] ?? null;
        if ($bday && isset($bday['year'], $bday['month'], $bday['day'])
                  && $bday['year'] > 1900) {
            $dob = sprintf('%04d-%02d-%02d', $bday['year'], $bday['month'], $bday['day']);
        }
    }
} catch (Exception $e) {
    $dob = null; // non-fatal — DoB is optional
}

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

// --- NEW LINKING LOGIC ---
// if we r just trying to link an account while logged in...
if (isset($_SESSION['oauth_action']) && $_SESSION['oauth_action'] === 'link') {
    $currentUserId = $_SESSION['hbmsuid'] ?? 0;
    unset($_SESSION['oauth_action']); // clear it so it doesn't loop

    if ($currentUserId <= 0) {
        exit('u gotta be logged in to link accounts buddy.');
    }

    // check if this google account is already taken by someone else
    $check = $dbh->prepare("SELECT ID FROM tbluser WHERE oauth_provider = 'google' AND oauth_id = :oid AND ID != :uid LIMIT 1");
    $check->execute([':oid' => $oauthId, ':uid' => $currentUserId]);
    if ($check->fetch()) {
        exit('this google account is already linked to another user. sry.');
    }

    // update the current user profile
    $upd = $dbh->prepare("UPDATE tbluser SET
        oauth_provider = 'google',
        oauth_id       = :oid,
        FullName       = COALESCE(NULLIF(:name, ''), FullName),
        DateOfBirth    = COALESCE(:dob, DateOfBirth),
        ProfilePhoto   = COALESCE(:photo, ProfilePhoto),
        auth_method    = IF(auth_method = 'local', 'both', auth_method)
        WHERE ID = :id");
    $upd->execute([
        ':oid'   => $oauthId,
        ':name'  => $fullName,
        ':dob'   => $dob,
        ':photo' => $photoPath,
        ':id'    => $currentUserId
    ]);

    // add to the link table too
    $linkUpsert = $dbh->prepare(
        "INSERT INTO tbl_oauth_links (UserID, Provider, ProviderUserID, ProviderEmail, EmailVerified)
         VALUES (:uid, 'google', :pid, :pemail, 1)
         ON DUPLICATE KEY UPDATE
            ProviderUserID = VALUES(ProviderUserID),
            ProviderEmail  = VALUES(ProviderEmail),
            EmailVerified  = 1"
    );
    $linkUpsert->execute([
        ':uid'    => $currentUserId,
        ':pid'    => $oauthId,
        ':pemail' => $email,
    ]);

    header('Location: profile.php?msg=linked');
    exit;
}
// --- END LINKING LOGIC ---

// Step 6: One email = one account. Look up by email (canonical identifier).
$stmt = $dbh->prepare("SELECT ID, FullName, Password, auth_method, oauth_provider, oauth_id
                       FROM tbluser WHERE Email = :email LIMIT 1");
$stmt->execute([':email' => $email]);
$existing = $stmt->fetch(PDO::FETCH_OBJ);

if ($existing) {
    $hasLocalPassword     = !empty($existing->Password);
    // Check if this Google ID is already linked to this user in tbl_oauth_links.
    $linkCheck = $dbh->prepare("SELECT 1 FROM tbl_oauth_links WHERE UserID = :uid AND Provider = 'google' AND ProviderUserID = :oid LIMIT 1");
    $linkCheck->execute([':uid' => $existing->ID, ':oid' => $oauthId]);
    $googleAlreadyLinked = (bool)$linkCheck->fetch();

    if ($googleAlreadyLinked) {
        // Already linked → refresh profile snapshot and sign in.
        $upd = $dbh->prepare("UPDATE tbluser SET
            FullName       = COALESCE(NULLIF(:name, ''), FullName),
            DateOfBirth    = COALESCE(:dob, DateOfBirth),
            ProfilePhoto   = COALESCE(:photo, ProfilePhoto),
            oauth_provider = 'google',
            oauth_id       = :oid
            WHERE ID = :id");
        $upd->execute([
            ':name'  => $fullName,
            ':dob'   => $dob,
            ':photo' => $photoPath,
            ':oid'   => $oauthId,
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
            'date_of_birth'    => $dob,
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
        DateOfBirth    = COALESCE(:dob, DateOfBirth),
        ProfilePhoto   = COALESCE(:photo, ProfilePhoto),
        oauth_id       = :oid,
        oauth_provider = 'google',
        auth_method    = 'oauth'
        WHERE ID = :id");
    $upd->execute([
        ':name'  => $fullName,
        ':dob'   => $dob,
        ':photo' => $photoPath,
        ':oid'   => $oauthId,
        ':id'    => $existing->ID,
    ]);

    $userId            = (int)$existing->ID;
    $promptSetPassword = true;
} else {
    // Brand new user → create with auth_method='oauth' (no password yet).
    $ins = $dbh->prepare(
        "INSERT INTO tbluser (FullName, Email, Password, auth_method, oauth_provider, oauth_id, DateOfBirth, ProfilePhoto)
         VALUES (:name, :email, '', 'oauth', 'google', :oid, :dob, :photo)"
    );
    $ins->execute([
        ':name'  => $fullName,
        ':email' => $email,
        ':oid'   => $oauthId,
        ':dob'   => $dob,
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
