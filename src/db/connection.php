<?php
// Secure session configuration and start only if none exists
if (session_status() === PHP_SESSION_NONE) {
    // Determine if connection is secure
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

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

$conn = mysqli_connect("localhost", "root", "", "crms_macprotech");

if (!$conn) {
    die("Database connection failed");
}
?>
