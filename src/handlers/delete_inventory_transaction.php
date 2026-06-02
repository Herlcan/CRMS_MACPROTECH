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

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['id'], $_GET['item_id'])) {
    header("Location: ../../items.php?tab=stock_records");
    exit();
}

$transaction_id = (int) $_GET['id'];
$item_id = (int) $_GET['item_id'];

if ($transaction_id <= 0 || $item_id <= 0) {
    redirectStockRecordsWithDialog($item_id, 'error', 'Transaction Not Deleted', 'Invalid inventory transaction.');
}

try {
    ensure_inventory_transaction_table($conn);

    $delete_query = mysqli_prepare($conn, "DELETE FROM stock_in_transaction WHERE id = ? AND item_id = ?");
    if (!$delete_query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($delete_query, "ii", $transaction_id, $item_id);

    if (!mysqli_stmt_execute($delete_query)) {
        throw new Exception('Failed to delete inventory transaction: ' . mysqli_stmt_error($delete_query));
    }

    if (mysqli_stmt_affected_rows($delete_query) === 0) {
        mysqli_stmt_close($delete_query);
        redirectStockRecordsWithDialog($item_id, 'error', 'Transaction Not Deleted', 'Inventory transaction was not found.');
    }

    mysqli_stmt_close($delete_query);
    sync_item_from_inventory_transactions($conn, $item_id);
    notify_low_stock_for_item($conn, $item_id);
    redirectStockRecordsWithDialog($item_id, 'success', 'Transaction Deleted', 'Stock-in transaction deleted successfully.');
} catch (Exception $e) {
    redirectStockRecordsWithDialog($item_id, 'error', 'Transaction Not Deleted', $e->getMessage());
}

?>
