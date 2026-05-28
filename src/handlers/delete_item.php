<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';
include '../../auth_check.php';

if (!function_exists('redirectItemWithDialog')) {
    function redirectItemWithDialog($type, $title, $message) {
        $_SESSION['dialog_flash'] = [
            'type' => $type,
            'title' => $title,
            'message' => $message
        ];
        header("Location: ../../items.php");
        exit();
    }
}

if($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {

    $item_id = (int) $_GET['id'];

    $delete_query = mysqli_prepare($conn,
        "DELETE FROM items WHERE id = ?"
    );

    mysqli_stmt_bind_param($delete_query, "i", $item_id);

    if (mysqli_stmt_execute($delete_query)) {
        mysqli_stmt_close($delete_query);
        redirectItemWithDialog('success', 'Product Item Deleted', 'Product item deleted successfully.');
    } else {
        redirectItemWithDialog('error', 'Product Item Not Deleted', 'Failed to delete product item. Please try again.');
    }
} else {
    header("Location: ../../items.php");
    exit();
}
?>
