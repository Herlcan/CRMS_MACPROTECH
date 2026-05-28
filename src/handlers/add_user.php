<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';
include '../../auth_check.php';

$add_user_message = '';
$add_user_error = '';

if (!function_exists('redirectUserWithDialog')) {
    function redirectUserWithDialog($type, $title, $message) {
        $_SESSION['dialog_flash'] = [
            'type' => $type,
            'title' => $title,
            'message' => $message
        ];
        header("Location: ../../user.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {

    $username   = trim($_POST['username']);
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $contact    = trim($_POST['contact_num']);
    $role       = trim($_POST['role']);
    $password   = $_POST['password'];

    // Validation
    if (empty($username) || empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($role) || empty($contact)) {

        $add_user_error = 'All fields are required.';

        $_SESSION['add_user_error'] = $add_user_error;
        redirectUserWithDialog('error', 'User Not Created', $add_user_error);

    } elseif (strlen($username) < 3) {

        $add_user_error = 'Username must be at least 3 characters long.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $add_user_error = 'Invalid email address.';

    } elseif (strlen($password) < 8) {

        $add_user_error = 'Password must be at least 8 characters long.';

    } else {

        // Check duplicate username or email
        $check_query = mysqli_prepare($conn,
            "SELECT id FROM users WHERE username = ? OR email = ?"
        );

        mysqli_stmt_bind_param($check_query, "ss", $username, $email);
        mysqli_stmt_execute($check_query);
        mysqli_stmt_store_result($check_query);
        
        if (mysqli_stmt_num_rows($check_query) > 0) {

            $add_user_error = 'Username or Email already exists.';

            mysqli_stmt_close($check_query);

        } else {

            mysqli_stmt_close($check_query);

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $add_query = mysqli_prepare($conn,
                "INSERT INTO users 
                (username, first_name, last_name, contact_num, email, password, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $add_query,
                "sssssss",
                $username,
                $first_name,
                $last_name,
                $contact,
                $email,
                $hashed_password,
                $role
            );

            if (mysqli_stmt_execute($add_query)) {

                mysqli_stmt_close($add_query);

                redirectUserWithDialog('success', 'New User Created', 'New user created successfully.');

            } else {

                mysqli_stmt_close($add_query);

                $add_user_error = 'Failed to add user. Please try again.';
            }
        }
    }

    if (!empty($add_user_error)) {
        $_SESSION['add_user_error'] = $add_user_error;
        redirectUserWithDialog('error', 'User Not Created', $add_user_error);
    }
}
?>
