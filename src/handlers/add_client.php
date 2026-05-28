<?php

include '../db/connection.php';
include '../../auth_check.php';
require_once __DIR__ . '/notification_helpers.php';

$add_client_message = '';
$add_client_error = '';

if (!function_exists('redirectClientWithDialog')) {
    function redirectClientWithDialog($type, $title, $message) {
        $_SESSION['dialog_flash'] = [
            'type' => $type,
            'title' => $title,
            'message' => $message
        ];
        header("Location: ../../clients.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_client'])) {

    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $contact    = trim($_POST['contact']);
    $address    = trim($_POST['address']);

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($contact)) {

        $add_client_error = 'First name, last name, email, and contact are required.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $add_client_error = 'Invalid email address.';

    } else {

        // MySQL friendly date format
        $date = date('Y-m-d');

        $add_query = mysqli_prepare($conn,
            "INSERT INTO client 
            (first_name, last_name, contact_num, email, address, date) 
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $add_query,
            "ssssss",
            $first_name,
            $last_name,
            $contact,
            $email,
            $address,
            $date
        );

        if (mysqli_stmt_execute($add_query)) {

            mysqli_stmt_close($add_query);
            $clientName = trim($first_name . ' ' . $last_name);
            notify_users_by_roles(
                $conn,
                ['Administrator', 'Cashier/Front Desk', 'Cashier/Front Desk Staff'],
                'New Customer',
                "{$clientName} registered as a new customer.",
                'info',
                'clients.php?search=' . urlencode($clientName)
            );
            redirectClientWithDialog('success', 'Customer Created', 'New customer created successfully.');

        } else {

            $add_client_error = 'Failed to add client. Please try again.';
        }
    }

    if (!empty($add_client_error)) {
        redirectClientWithDialog('error', 'Customer Not Created', $add_client_error);
    }
}
?>
