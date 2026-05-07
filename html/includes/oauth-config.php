<?php
// Try to load .env file from a few places
$possibleEnvPaths = [
    __DIR__ . '/.env',
    dirname(__DIR__) . '/.env',
    dirname(dirname(__DIR__)) . '/.env'
];

foreach ($possibleEnvPaths as $envFile) {
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                putenv("$key=$val");
                $_ENV[$key] = $val;
            }
        }
        break; // stop at the first one we find
    }
}

// helper to get env with fallback so we dont get those annoying warnings
function get_env_custom($key, $default = '') {
    $val = getenv($key);
    return ($val !== false) ? $val : ($_ENV[$key] ?? $default);
}

define('MICROSOFT_CLIENT_ID',     get_env_custom('MICROSOFT_CLIENT_ID'));
define('MICROSOFT_CLIENT_SECRET', get_env_custom('MICROSOFT_CLIENT_SECRET'));
define('MICROSOFT_REDIRECT_URI',  get_env_custom('MICROSOFT_REDIRECT_URI'));
define('MICROSOFT_TENANT',        get_env_custom('MICROSOFT_TENANT'));

define('GOOGLE_CLIENT_ID',        get_env_custom('GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET',    get_env_custom('GOOGLE_CLIENT_SECRET'));
define('GOOGLE_REDIRECT_URI',     get_env_custom('GOOGLE_REDIRECT_URI'));

// check if we are still using placeholders. if so, stop and tell the user to fix it!
if (str_contains(GOOGLE_CLIENT_ID, 'your-google-client-id') || 
    str_contains(MICROSOFT_CLIENT_ID, 'your-microsoft-client-id') || 
    empty(GOOGLE_CLIENT_ID)) {
    
    // Only stop if we are actually in a callback file or something that needs OAuth
    $currentFile = basename($_SERVER['PHP_SELF']);
    if ($currentFile == 'google-callback.php' || $currentFile == 'oauth-callback.php') {
        echo "<html><body style='font-family: sans-serif; padding: 20px;'>";
        echo "<h2 style='color: #d32f2f;'>Hold up! OAuth isn't configured yet. 🛑</h2>";
        echo "<p>You're seeing this because your <code>html/includes/.env</code> file still has placeholder values like <i>'your-google-client-id-here'</i>.</p>";
        echo "<p>To fix this, you need to:</p>";
        echo "<ol>";
        echo "<li>Go to the <a href='https://console.cloud.google.com/apis/credentials' target='_blank'>Google Cloud Console</a>.</li>";
        echo "<li>Create an OAuth 2.0 Client ID.</li>";
        echo "<li>Copy the real Client ID and Secret into your <code>.env</code> file.</li>";
        echo "</ol>";
        echo "<p>Once you put the real keys in there, this page will actually redirect you to Google/Microsoft. Good luck!</p>";
        echo "</body></html>";
        exit;
    }
}
