<?php

    include '../db/connection.php';
    include '../../auth_check.php';
    require_once __DIR__ . '/activity_log_helper.php';
    require_once __DIR__ . '/security_helpers.php';

    $edit_client_message = '';
    $edit_client_error = '';

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

    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_client'])) {
        if (!verify_csrf_token_or_audit($conn, 'update customer')) {
            redirectClientWithDialog('error', 'Security Check Failed', 'Your form session expired. Please try again.');
        }

        require_role(['Administrator', 'Cashier/Front Desk', 'Cashier/Front Desk Staff'], function () {
            redirectClientWithDialog('error', 'Permission Required', 'Only authorized staff can update customers.');
        }, $conn, 'update customer');

        $client_id  = (int) ($_POST['id'] ?? 0);
        $first_name = trim($_POST['first_name']);
        $last_name  = trim($_POST['last_name']);
        $email      = trim($_POST['email']);
        $contact    = trim($_POST['contact']);
        $address    = trim($_POST['address']);

        // Validation
        if (empty($first_name) || empty($last_name) || empty($email) || empty($contact)) {

            $edit_client_error = 'First name, last name, email, and contact are required.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $edit_client_error = 'Invalid email address.';

        } else {

            $update_query = mysqli_prepare($conn,
                "UPDATE client 
                SET first_name = ?, last_name = ?, contact_num = ?, email = ?, address = ? 
                WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $update_query,
                "sssssi",
                $first_name,
                $last_name,
                $contact,
                $email,
                $address,
                $client_id
            );

            if (mysqli_stmt_execute($update_query)) {

                mysqli_stmt_close($update_query);
                $clientName = trim($first_name . ' ' . $last_name);
                log_activity($conn, "Updated customer {$clientName} (#{$client_id})");
                redirectClientWithDialog('success', 'Customer Updated', 'Customer updated successfully.');

            } else {

                $edit_client_error = 'Failed to update client. Please try again.';
            }
        }

        if (!empty($edit_client_error)) {
            redirectClientWithDialog('error', 'Customer Not Updated', $edit_client_error);
        }
    }

?>
