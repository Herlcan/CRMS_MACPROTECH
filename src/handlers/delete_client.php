<?php

    include '../db/connection.php';
    include '../../auth_check.php';

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

    if($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {

        $client_id = $_GET['id'];

        $delete_query = mysqli_prepare($conn,
            "DELETE FROM client WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $delete_query,
            "i",
            $client_id
        );

        if (mysqli_stmt_execute($delete_query)) {

            mysqli_stmt_close($delete_query);
            redirectClientWithDialog('success', 'Customer Deleted', 'Customer deleted successfully.');

        } else {

            redirectClientWithDialog('error', 'Customer Not Deleted', 'Failed to delete customer. Please try again.');
        }
    } else {
        header("Location: ../../clients.php");
        exit();
    }
?>
