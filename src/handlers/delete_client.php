<?php

    include '../db/connection.php';
    include '../../auth_check.php';

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
            header("Location: ../../clients.php");
            exit();

        } else {

            echo 'Failed to delete client. Please try again.';
        }
    } else {
        header("Location: ../../clients.php");
        exit();
    }
?>