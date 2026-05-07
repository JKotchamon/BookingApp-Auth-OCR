<?php 
// DB credentials.
// Load env if not already loaded (useful for scripts that don't include oauth-config)
if (!getenv('DB_HOST')) {
    $possibleEnvPaths = [__DIR__ . '/.env', dirname(__DIR__) . '/.env'];
    foreach ($possibleEnvPaths as $envFile) {
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) putenv(trim($parts[0]) . "=" . trim($parts[1]));
            }
            break;
        }
    }
}

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'db');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: 'StrongDBPassword@');
    define('DB_NAME', getenv('DB_NAME') ?: 'hbmsdb');
}
// Establish database connection.
try
{
$dbh = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME,DB_USER, DB_PASS,array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
}
catch (PDOException $e)
{
exit("Error: " . $e->getMessage());
}