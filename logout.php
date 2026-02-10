<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Clear session data
$_SESSION = [];

// If session uses cookies, remove the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;

?>