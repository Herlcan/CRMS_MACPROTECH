<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';
include '../../auth_check.php';
require_once __DIR__ . '/inventory_transaction_schema.php';
require_once __DIR__ . '/notification_helpers.php';

if (!function_exists('redirectStockRecordsWithDialog')) {
    function redirectStockRecordsWithDialog($item_id, $type, $title, $message) {
        $_SESSION['dialog_flash'] = [
            'type' => $type,
            'title' => $title,
            'message' => $message
        ];
        header("Location: ../../stock_transaction.php?item_id=" . urlencode((string) $item_id) . "&tab=inventory_transaction");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['add_inventory_transaction'])) {
    header("Location: ../../items.php?tab=stock_records");
    exit();
}

$item_id = (int) ($_POST['item_id'] ?? 0);
$capital = (float) ($_POST['capital'] ?? 0);
$stock_in = (int) ($_POST['stock_in'] ?? 0);
$markup_percentage = (float) ($_POST['markup_percentage'] ?? 0);
$average_price = isset($_POST['average_price']) && $_POST['average_price'] !== ''
    ? (float) $_POST['average_price']
    : null;
$stock_in_date = date('Y-m-d');

if ($item_id <= 0) {
    redirectStockRecordsWithDialog($item_id, 'error', 'Stock Not Added', 'Invalid product item.');
}

if ($capital < 0 || $stock_in <= 0 || $markup_percentage < 0 || ($average_price !== null && $average_price < 0)) {
    redirectStockRecordsWithDialog($item_id, 'error', 'Stock Not Added', 'Capital, stock-in, markup, and average price must be valid non-negative values.');
}

try {
    ensure_inventory_transaction_table($conn);
    ensure_items_inventory_columns($conn);
    $transaction_started = false;

    $item_query = mysqli_prepare($conn, "SELECT id FROM items WHERE id = ? LIMIT 1");
    if (!$item_query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($item_query, "i", $item_id);
    mysqli_stmt_execute($item_query);
    $item_result = mysqli_stmt_get_result($item_query);

    if (mysqli_num_rows($item_result) === 0) {
        mysqli_stmt_close($item_query);
        redirectStockRecordsWithDialog($item_id, 'error', 'Stock Not Added', 'Product item was not found.');
    }

    mysqli_stmt_close($item_query);

    mysqli_begin_transaction($conn);
    $transaction_started = true;

    create_inventory_transaction_for_item($conn, $item_id, $capital, $stock_in, $stock_in_date);

    if ($average_price === null) {
        $inventory = calculate_inventory_weighted_average($conn, $item_id);
        $average_price = calculate_inventory_average_price($inventory['average_cost'], $markup_percentage);
    }

    sync_item_from_inventory_transactions($conn, $item_id, $markup_percentage, $average_price);

    mysqli_commit($conn);

    notify_low_stock_for_item($conn, $item_id);
    redirectStockRecordsWithDialog($item_id, 'success', 'Stock Added', 'New stock was added successfully.');
} catch (Exception $e) {
    if (!empty($transaction_started)) {
        mysqli_rollback($conn);
    }
    redirectStockRecordsWithDialog($item_id, 'error', 'Stock Not Added', $e->getMessage());
}

?>
