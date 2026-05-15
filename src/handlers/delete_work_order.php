<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: application/json');

include '../db/connection.php';
include '../../auth_check.php';

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

    // Start transaction
    if (!mysqli_begin_transaction($conn)) {
        throw new Exception('Failed to start transaction');
    }

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
