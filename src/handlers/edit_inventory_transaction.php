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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['edit_inventory_transaction'])) {
    header("Location: ../../items.php?tab=stock_records");
    exit();
}

$transaction_id = (int) ($_POST['id'] ?? 0);
$item_id = (int) ($_POST['item_id'] ?? 0);
$capital = (float) ($_POST['capital'] ?? 0);
$stock_in = (int) ($_POST['stock_in'] ?? 0);
$stock_in_date = $_POST['stock_in_date'] ?? date('Y-m-d');

if ($transaction_id <= 0 || $item_id <= 0) {
    redirectStockRecordsWithDialog($item_id, 'error', 'Transaction Not Updated', 'Invalid inventory transaction.');
}

if ($capital < 0 || $stock_in < 0) {
    redirectStockRecordsWithDialog($item_id, 'error', 'Transaction Not Updated', 'Capital and stock-in cannot be negative.');
}

if ($stock_in_date === '') {
    redirectStockRecordsWithDialog($item_id, 'error', 'Transaction Not Updated', 'Stock-in date is required.');
}

try {
    ensure_inventory_transaction_table($conn);

    $check_query = mysqli_prepare(
        $conn,
        "SELECT id FROM stock_in_transaction WHERE id = ? AND item_id = ? LIMIT 1"
    );

    if (!$check_query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($check_query, "ii", $transaction_id, $item_id);
    mysqli_stmt_execute($check_query);
    $check_result = mysqli_stmt_get_result($check_query);

    if (mysqli_num_rows($check_result) === 0) {
        mysqli_stmt_close($check_query);
        redirectStockRecordsWithDialog($item_id, 'error', 'Transaction Not Updated', 'Inventory transaction was not found.');
    }

    mysqli_stmt_close($check_query);

    $query = mysqli_prepare(
        $conn,
        "UPDATE stock_in_transaction
         SET capital = ?, stock_in = ?, stock_in_date = ?
         WHERE id = ? AND item_id = ?"
    );

    if (!$query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($query, "disii", $capital, $stock_in, $stock_in_date, $transaction_id, $item_id);

    if (!mysqli_stmt_execute($query)) {
        throw new Exception('Failed to update inventory transaction: ' . mysqli_stmt_error($query));
    }

    mysqli_stmt_close($query);
    sync_item_from_inventory_transactions($conn, $item_id);
    notify_low_stock_for_item($conn, $item_id);
    redirectStockRecordsWithDialog($item_id, 'success', 'Transaction Updated', 'Stock-in transaction updated successfully.');
} catch (Exception $e) {
    redirectStockRecordsWithDialog($item_id, 'error', 'Transaction Not Updated', $e->getMessage());
}

?>
