<?php

    include '../db/connection.php';
    include '../../auth_check.php';

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

        $client_id  = $_POST['id'];
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
