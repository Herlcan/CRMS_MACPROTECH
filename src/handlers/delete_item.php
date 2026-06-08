<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';
include '../../auth_check.php';
require_once __DIR__ . '/inventory_transaction_schema.php';
require_once __DIR__ . '/activity_log_helper.php';

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
    $item_label = "product item #{$item_id}";

    $item_query = mysqli_prepare($conn, "SELECT product_code, brand_name, model FROM items WHERE id = ? LIMIT 1");
    if ($item_query) {
        mysqli_stmt_bind_param($item_query, "i", $item_id);
        mysqli_stmt_execute($item_query);
        $item_result = mysqli_stmt_get_result($item_query);
        $item = mysqli_fetch_assoc($item_result);
        mysqli_stmt_close($item_query);

        if ($item) {
            $code = $item['product_code'] ?: ('#' . $item_id);
            $item_label = trim($code . ' ' . $item['brand_name'] . ' ' . $item['model']);
        }
    }

    try {
        delete_inventory_records_for_item($conn, $item_id);
    } catch (Exception $e) {
        redirectItemWithDialog('error', 'Product Item Not Deleted', $e->getMessage());
    }

    $delete_query = mysqli_prepare($conn,
        "DELETE FROM items WHERE id = ?"
    );

    mysqli_stmt_bind_param($delete_query, "i", $item_id);

    if (mysqli_stmt_execute($delete_query)) {
        if (mysqli_stmt_affected_rows($delete_query) > 0) {
            log_activity($conn, "Deleted product item {$item_label}");
        }
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
