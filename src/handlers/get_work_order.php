<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: application/json');

include '../db/connection.php';
include '../../auth_check.php';

$response = ['success' => false, 'message' => 'Unknown error', 'workOrder' => null, 'purchasedParts' => [], 'clientParts' => []];

try {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Work order ID is required');
    }

    $work_order_id = intval($_GET['id']);

    if ($work_order_id <= 0) {
        throw new Exception('Invalid work order ID');
    }

    // Fetch work order with technician name
    $query = mysqli_prepare($conn, "
        SELECT wo.*, CONCAT(u.first_name, ' ', u.last_name) AS technician_name
        FROM work_order wo
        LEFT JOIN users u ON wo.technician_id = u.id
        WHERE wo.id = ?
    ");
    if (!$query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($query, "i", $work_order_id);
    if (!mysqli_stmt_execute($query)) {
        throw new Exception('Failed to fetch work order: ' . mysqli_stmt_error($query));
    }

    $result = mysqli_stmt_get_result($query);
    $work_order = mysqli_fetch_assoc($result);
    mysqli_stmt_close($query);

    if (!$work_order) {
        throw new Exception('Work order not found');
    }

    $cancelled_from_status = null;
    if (($work_order['status'] ?? '') === 'Cancelled') {
        $cancelLogQuery = mysqli_prepare($conn, "
            SELECT action
            FROM activity_logs
            WHERE work_order_id = ?
            AND action LIKE '% to Cancelled'
            ORDER BY id DESC
            LIMIT 1
        ");

        if ($cancelLogQuery) {
            mysqli_stmt_bind_param($cancelLogQuery, "i", $work_order_id);
            if (mysqli_stmt_execute($cancelLogQuery)) {
                $cancelLogResult = mysqli_stmt_get_result($cancelLogQuery);
                $cancelLog = mysqli_fetch_assoc($cancelLogResult);

                if ($cancelLog && preg_match('/Changed status from (.+) to Cancelled$/', $cancelLog['action'], $matches)) {
                    $cancelled_from_status = $matches[1];
                }
            }
            mysqli_stmt_close($cancelLogQuery);
        }
    }

    $payments = [];
    // Fetch payments if table exists
    $checkPayments = mysqli_query($conn, "SHOW TABLES LIKE 'payments'");
    if ($checkPayments && mysqli_num_rows($checkPayments) > 0) {
        $paymentsQuery = mysqli_prepare($conn, "
            SELECT * FROM payments
            WHERE work_order_id = ?
            ORDER BY date ASC
        ");
        if ($paymentsQuery) {
            mysqli_stmt_bind_param($paymentsQuery, "i", $work_order_id);
            if (mysqli_stmt_execute($paymentsQuery)) {
                $paymentsResult = mysqli_stmt_get_result($paymentsQuery);
                $payments = mysqli_fetch_all($paymentsResult, MYSQLI_ASSOC);
            }
            mysqli_stmt_close($paymentsQuery);
        }
    }

    // Fetch purchased parts (returns empty array if no parts exist)
    $purchased_parts = [];
    $purchased_query = mysqli_prepare($conn, "
        SELECT pi.*, COALESCE(i.brand_name, 'Unknown Item') as product_name, COALESCE(i.price, 0) as product_price
        FROM purchased_item pi
        LEFT JOIN items i ON pi.product_id = i.id
        WHERE pi.work_order_id = ?
        ORDER BY pi.id ASC
    ");
    
    if ($purchased_query) {
        mysqli_stmt_bind_param($purchased_query, "i", $work_order_id);
        if (mysqli_stmt_execute($purchased_query)) {
            $purchased_result = mysqli_stmt_get_result($purchased_query);
            $purchased_parts = mysqli_fetch_all($purchased_result, MYSQLI_ASSOC);
        }
        mysqli_stmt_close($purchased_query);
    }

    // Fetch client provided parts (returns empty array if no parts exist)
    $client_parts = [];
    $client_query = mysqli_prepare($conn, "
        SELECT * FROM customer_provided_component WHERE work_order_id = ?
        ORDER BY id ASC
    ");
    
    if ($client_query) {
        mysqli_stmt_bind_param($client_query, "i", $work_order_id);
        if (mysqli_stmt_execute($client_query)) {
            $client_result = mysqli_stmt_get_result($client_query);
            $client_parts = mysqli_fetch_all($client_result, MYSQLI_ASSOC);
        }
        mysqli_stmt_close($client_query);
    }

    $response = [
        'success' => true,
        'workOrder' => $work_order,
        'purchasedParts' => $purchased_parts,
        'clientParts' => $client_parts,
        'payments' => $payments,
        'cancelledFromStatus' => $cancelled_from_status
    ];

} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
?>
