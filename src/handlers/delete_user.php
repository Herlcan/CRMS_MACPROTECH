<?php
session_start();
include '../db/connection.php';

function redirectUserWithDialog($type, $title, $message) {
    $_SESSION['dialog_flash'] = [
        'type' => $type,
        'title' => $title,
        'message' => $message
    ];
    header("Location: ../../user.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
        redirectUserWithDialog('error', 'Unauthorized', 'You are not allowed to delete users.');
    }

    $user_id = intval($_GET['id']);
    $logged_in_user_id = $_SESSION['user_id'];

    // Get role of user being deleted
    $role_query = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ?");
    mysqli_stmt_bind_param($role_query, "i", $user_id);
    mysqli_stmt_execute($role_query);
    $result = mysqli_stmt_get_result($role_query);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($role_query);

    if (!$user) {
        redirectUserWithDialog('error', 'User Not Found', 'The selected user could not be found.');
    }

    $is_current_user = ($logged_in_user_id == $user_id);

    /*
     * Prevent deleting the last administrator
     */
    if ($user['role'] === 'Administrator') {

        $count_query = mysqli_query(
            $conn,
            "SELECT COUNT(*) as total FROM users WHERE role='Administrator'"
        );

        $admin_count = mysqli_fetch_assoc($count_query)['total'];

        if ($admin_count <= 1) {
            redirectUserWithDialog('error', 'User Not Deleted', 'Cannot delete the last administrator.');
        }
    }

    /*
     * Delete user
     */
    $delete_query = mysqli_prepare($conn,
        "DELETE FROM users WHERE id = ? LIMIT 1"
    );

    mysqli_stmt_bind_param($delete_query, "i", $user_id);

    if (mysqli_stmt_execute($delete_query)) {

        mysqli_stmt_close($delete_query);

        /*
         * If deleting current user → logout
         */
        if ($is_current_user) {

            $_SESSION = [];

            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }

            session_destroy();

            header("Location: ../../login.php?message=Account deleted");
            exit();
        }

        redirectUserWithDialog('success', 'User Deleted', 'User deleted successfully.');

    } else {
        redirectUserWithDialog('error', 'User Not Deleted', 'Failed to delete user. Please try again.');
    }

} else {
    header("Location: ../../user.php");
    exit();
}
