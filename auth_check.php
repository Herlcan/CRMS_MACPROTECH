<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// Verify that the logged-in user still exists in the database
// This prevents deleted users from continuing to use the system
include 'src/db/connection.php';
$user_id = $_SESSION['user_id'];
$verify_query = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ?");
mysqli_stmt_bind_param($verify_query, "i", $user_id);
mysqli_stmt_execute($verify_query);
$verify_result = mysqli_stmt_get_result($verify_query);

if (mysqli_num_rows($verify_result) === 0) {
    // User no longer exists in database - log them out
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    header("Location: login.php?message=Your account has been deleted by an administrator");
    exit();
}

mysqli_stmt_close($verify_query);
?>