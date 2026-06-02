<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: application/json');

include '../db/connection.php';
include '../../auth_check.php';
require_once __DIR__ . '/ordered_part_schema.php';
require_once __DIR__ . '/inventory_transaction_schema.php';

$response = ['success' => false, 'message' => 'Unknown error'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_POST['id']) || empty($_POST['id'])) {
        throw new Exception('Work order ID is required');
    }

    $work_order_id = intval($_POST['id']);

    if ($work_order_id <= 0) {
        throw new Exception('Invalid work order ID');
    }

    // Verify work order exists
    $verify_query = mysqli_prepare($conn, "SELECT id FROM work_order WHERE id = ?");
    if (!$verify_query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($verify_query, "i", $work_order_id);
    mysqli_stmt_execute($verify_query);
    $result = mysqli_stmt_get_result($verify_query);

    if (mysqli_num_rows($result) === 0) {
        mysqli_stmt_close($verify_query);
        throw new Exception('Work order not found');
    }
    mysqli_stmt_close($verify_query);

    ensure_items_inventory_columns($conn);
    ensure_inventory_transaction_table($conn);
    backfill_inventory_transactions_from_items($conn);

    // Start transaction
    if (!mysqli_begin_transaction($conn)) {
        throw new Exception('Failed to start transaction');
    }

    ensure_ordered_parts_table($conn);

    // Revert stock for purchased items before deletion
    $revert_stock = mysqli_prepare($conn, "
        SELECT p.product_id, p.quantity
        FROM purchased_item p
        WHERE p.work_order_id = ?
    ");
    if (!$revert_stock) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($revert_stock, "i", $work_order_id);
    if (!mysqli_stmt_execute($revert_stock)) {
        throw new Exception('Failed to fetch purchased items: ' . mysqli_stmt_error($revert_stock));
    }
    $stock_result = mysqli_stmt_get_result($revert_stock);

    while ($row = mysqli_fetch_assoc($stock_result)) {
        $product_id = (int) $row['product_id'];
        $quantity = (int) $row['quantity'];

        if ($product_id <= 0 || $quantity <= 0) {
            continue;
        }

        $revert_stock_query = mysqli_prepare(
            $conn,
            "UPDATE items SET quantity = quantity + ? WHERE id = ?"
        );
        if (!$revert_stock_query) {
            throw new Exception('Database error: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($revert_stock_query, "ii", $quantity, $product_id);

        if (!mysqli_stmt_execute($revert_stock_query)) {
            throw new Exception('Failed to revert stock for purchased items: ' . mysqli_stmt_error($revert_stock_query));
        }
        mysqli_stmt_close($revert_stock_query);

    }
    mysqli_stmt_close($revert_stock);

    // Delete purchased items (may have 0 rows if no items exist)
    $delete_purchased = mysqli_prepare($conn, "DELETE FROM purchased_item WHERE work_order_id = ?");
    if (!$delete_purchased) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($delete_purchased, "i", $work_order_id);
    if (!mysqli_stmt_execute($delete_purchased)) {
        throw new Exception('Failed to delete purchased items: ' . mysqli_stmt_error($delete_purchased));
    }
    mysqli_stmt_close($delete_purchased);
    sync_stock_out_transactions_for_work_order($conn, $work_order_id);

    // Delete customer provided components (may have 0 rows if no items exist)
    $delete_client_parts = mysqli_prepare($conn, "DELETE FROM customer_provided_component WHERE work_order_id = ?");
    if (!$delete_client_parts) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($delete_client_parts, "i", $work_order_id);
    if (!mysqli_stmt_execute($delete_client_parts)) {
        throw new Exception('Failed to delete client provided parts: ' . mysqli_stmt_error($delete_client_parts));
    }
    mysqli_stmt_close($delete_client_parts);

    // Delete ordered parts (may have 0 rows if no items exist)
    $delete_ordered_parts = mysqli_prepare($conn, "DELETE FROM ordered_parts WHERE work_order_id = ?");
    if (!$delete_ordered_parts) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($delete_ordered_parts, "i", $work_order_id);
    if (!mysqli_stmt_execute($delete_ordered_parts)) {
        throw new Exception('Failed to delete ordered parts: ' . mysqli_stmt_error($delete_ordered_parts));
    }
    mysqli_stmt_close($delete_ordered_parts);

    // Delete work order
    $delete_work_order = mysqli_prepare($conn, "DELETE FROM work_order WHERE id = ?");
    if (!$delete_work_order) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($delete_work_order, "i", $work_order_id);
    if (!mysqli_stmt_execute($delete_work_order)) {
        throw new Exception('Failed to delete work order: ' . mysqli_stmt_error($delete_work_order));
    }

    if (mysqli_stmt_affected_rows($delete_work_order) === 0) {
        throw new Exception('Work order could not be deleted');
    }
    mysqli_stmt_close($delete_work_order);

    // Commit transaction
    if (!mysqli_commit($conn)) {
        throw new Exception('Failed to commit transaction');
    }

    $response = ['success' => true, 'message' => 'Work order deleted successfully'];

} catch (Exception $e) {
    // Rollback on any error
    if ($conn) {
        mysqli_rollback($conn);
    }
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
?>
