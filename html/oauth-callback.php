<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/dbconnection.php';
require_once 'includes/oauth-config.php';
require_once 'vendor/autoload.php';

$provider = new TheNetworg\OAuth2\Client\Provider\Azure([
    'clientId'               => MICROSOFT_CLIENT_ID,
    'clientSecret'           => MICROSOFT_CLIENT_SECRET,
    'redirectUri'            => MICROSOFT_REDIRECT_URI,
    'tenant'                 => MICROSOFT_TENANT,
    'scopes'                 => ['openid', 'profile', 'email', 'User.Read'],
    'defaultEndPointVersion' => '2.0',
]);

// Step 1: No code yet — redirect user to Microsoft login
if (!isset($_GET['code'])) {
    $authUrl = $provider->getAuthorizationUrl();
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

// Step 4: Get user profile from Microsoft Graph
try {
    $me = $provider->get('https://graph.microsoft.com/v1.0/me', $token);
} catch (Exception $e) {
    exit('Failed to get user profile: ' . $e->getMessage());
}

$oauthId  = $me['id']          ?? '';
$email    = $me['mail']        ?? $me['userPrincipalName'] ?? '';
$fullName = $me['displayName'] ?? '';
$dob      = (isset($me['birthday']) && $me['birthday'] !== '') ? $me['birthday'] : null;

if (empty($email)) {
    exit('Could not retrieve email from Microsoft account.');
}

// Step 5: Fetch profile photo from Microsoft Graph
$photoPath = null;
try {
    $photoDir = __DIR__ . '/images/oauth/';
    if (!is_dir($photoDir)) {
        mkdir($photoDir, 0755, true);
    }

    $photoResponse = $provider->getHttpClient()->request('GET',
        'https://graph.microsoft.com/v1.0/me/photo/$value',
        ['headers' => ['Authorization' => 'Bearer ' . $token->getToken()]]
    );

    if ($photoResponse->getStatusCode() === 200) {
        $photoFilename = 'ms_' . $oauthId . '.jpg';
        file_put_contents($photoDir . $photoFilename, $photoResponse->getBody()->getContents());
        $photoPath = 'images/oauth/' . $photoFilename;
    }
} catch (Exception $e) {
    $photoPath = null;
}

// Step 6: One email = one account. Look up by email (canonical identifier).
$stmt = $dbh->prepare("SELECT ID, Password, auth_method, oauth_provider, oauth_id
                       FROM tbluser WHERE Email = :email LIMIT 1");
$stmt->execute([':email' => $email]);
$existing = $stmt->fetch(PDO::FETCH_OBJ);

$promptSetPassword = false;

if ($existing) {
    $hasLocalPassword = !empty($existing->Password);
    $newAuthMethod    = $hasLocalPassword ? 'both' : 'oauth';

    $upd = $dbh->prepare("UPDATE tbluser SET
        FullName       = COALESCE(NULLIF(:name, ''),  FullName),
        DateOfBirth    = COALESCE(:dob,               DateOfBirth),
        ProfilePhoto   = COALESCE(:photo,             ProfilePhoto),
        oauth_id       = :oid,
        oauth_provider = 'microsoft',
        auth_method    = :method
        WHERE ID = :id");
    $upd->execute([
        ':name'   => $fullName,
        ':dob'    => $dob,
        ':photo'  => $photoPath,
        ':oid'    => $oauthId,
        ':method' => $newAuthMethod,
        ':id'     => $existing->ID,
    ]);

    $userId            = (int)$existing->ID;
    $promptSetPassword = !$hasLocalPassword;
} else {
    $ins = $dbh->prepare(
        "INSERT INTO tbluser (FullName, Email, Password, auth_method, oauth_provider, oauth_id, DateOfBirth, ProfilePhoto)
         VALUES (:name, :email, '', 'oauth', 'microsoft', :oid, :dob, :photo)"
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

$linkUpsert = $dbh->prepare(
    "INSERT INTO tbl_oauth_links (UserID, Provider, ProviderUserID, ProviderEmail, EmailVerified)
     VALUES (:uid, 'microsoft', :pid, :pemail, 1)
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
    // Non-fatal.
}

$_SESSION['hbmsuid'] = $userId;
$_SESSION['login']   = $email;

if ($promptSetPassword) {
    header('Location: set-password-prompt.php');
    exit;
}

header('Location: index.php');
exit;
