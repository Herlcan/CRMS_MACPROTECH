<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';
include '../../auth_check.php';

if($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {

    $item_id = (int) $_GET['id'];

    $delete_query = mysqli_prepare($conn,
        "DELETE FROM items WHERE id = ?"
    );

    mysqli_stmt_bind_param($delete_query, "i", $item_id);

    if (mysqli_stmt_execute($delete_query)) {
        mysqli_stmt_close($delete_query);
        header("Location: ../../items.php");
        exit();
    } else {
        echo 'Failed to delete item. Please try again.';
    }
} else {
    header("Location: ../../items.php");
    exit();
}
?>
