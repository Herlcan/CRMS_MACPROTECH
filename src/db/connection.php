<?php
require_once __DIR__ . '/../handlers/security_headers.php';

macprotech_send_security_headers();

// Secure session configuration and start only if none exists
if (session_status() === PHP_SESSION_NONE) {
    // Determine if connection is secure
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || $forwardedProto === 'https';

    // Set strict and cookie-only mode
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);

    // Configure cookie params (PHP 7.3+ supports array parameter)
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        // Fallback for older PHP: omit samesite
        session_set_cookie_params(0, '/', '', $secure, true);
    }

    session_start();
}

if (!function_exists('macprotech_database_env')) {
    function macprotech_database_env(string $name, string $default = ''): string
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            return $default;
        }

        return (string) $value;
    }
}

$dbHost = macprotech_database_env('DB_HOST', 'localhost');
$dbUser = macprotech_database_env('DB_USER', 'root');
$dbPassword = macprotech_database_env('DB_PASSWORD', '');
$dbName = macprotech_database_env('DB_NAME', 'crms_macprotech');
$dbPortValue = macprotech_database_env('DB_PORT', '3306');
$dbPort = ctype_digit($dbPortValue) ? (int) $dbPortValue : 3306;

$conn = mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName, $dbPort);

if (!$conn) {
    die("Database connection failed");
}

mysqli_set_charset($conn, 'utf8mb4');
?>
