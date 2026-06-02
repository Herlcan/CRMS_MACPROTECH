<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: application/json');

include '../db/connection.php';
include 'payment_schema.php';

$response = ['success' => false, 'message' => 'Unknown error'];

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception('Unauthorized');
    }

    ensure_payment_detail_columns($conn);
    ensure_items_inventory_columns($conn);

    if (empty($_GET['id'])) {
        throw new Exception('Payment ID is required');
    }

    $paymentId = intval($_GET['id']);
    if ($paymentId <= 0) {
        throw new Exception('Invalid payment ID');
    }

    $paymentQuery = mysqli_prepare($conn, "
        SELECT
            p.*,
            wo.code AS work_order_code,
            wo.request_date,
            wo.unit_type,
            wo.brand,
            wo.model,
            wo.specs_acce,
            wo.prob_find,
            wo.completion_date,
            wo.status AS work_order_status,
            CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
            c.email AS customer_email,
            CONCAT(u.first_name, ' ', u.last_name) AS technician_name
        FROM payments p
        LEFT JOIN work_order wo ON p.work_order_id = wo.id
        LEFT JOIN client c ON wo.client_id = c.id
        LEFT JOIN users u ON wo.technician_id = u.id
        WHERE p.id = ?
        LIMIT 1
    ");

    if (!$paymentQuery) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($paymentQuery, "i", $paymentId);
    mysqli_stmt_execute($paymentQuery);
    $paymentResult = mysqli_stmt_get_result($paymentQuery);
    $payment = mysqli_fetch_assoc($paymentResult);
    mysqli_stmt_close($paymentQuery);

    if (!$payment) {
        throw new Exception('Payment record not found');
    }

    $workOrderId = (int) $payment['work_order_id'];
    $purchasedParts = [];
    $partsQuery = mysqli_prepare($conn, "
        SELECT
            pi.*,
            COALESCE(i.product_code, '') AS product_code,
            COALESCE(i.brand_name, 'Unknown Item') AS product_name,
            COALESCE(i.model, '') AS product_model,
            COALESCE(i.average_price, 0) AS product_price
        FROM purchased_item pi
        LEFT JOIN items i ON pi.product_id = i.id
        WHERE pi.work_order_id = ?
        ORDER BY pi.id ASC
    ");

    if ($partsQuery) {
        mysqli_stmt_bind_param($partsQuery, "i", $workOrderId);
        mysqli_stmt_execute($partsQuery);
        $partsResult = mysqli_stmt_get_result($partsQuery);
        $purchasedParts = mysqli_fetch_all($partsResult, MYSQLI_ASSOC);
        mysqli_stmt_close($partsQuery);
    }

    $costs = get_payment_costs($conn, $workOrderId);
    $totalRefunded = get_total_refunded($conn, $paymentId);
    $netTotal = max(0, (float) $payment['total_amount'] - (float) ($payment['discount_amount'] ?? 0));
    $computed = calculate_payment_status($netTotal, (float) ($payment['amount_paid'] ?? 0), $totalRefunded);

    $refunds = [];
    $refundsQuery = mysqli_prepare($conn, "
        SELECT
            r.*,
            CONCAT(u.first_name, ' ', u.last_name) AS refunded_by_name
        FROM refunds r
        LEFT JOIN users u ON r.refunded_by = u.id
        WHERE r.payment_id = ?
        ORDER BY r.refunded_at DESC, r.id DESC
    ");

    if ($refundsQuery) {
        mysqli_stmt_bind_param($refundsQuery, "i", $paymentId);
        mysqli_stmt_execute($refundsQuery);
        $refundsResult = mysqli_stmt_get_result($refundsQuery);
        $refunds = mysqli_fetch_all($refundsResult, MYSQLI_ASSOC);
        mysqli_stmt_close($refundsQuery);
    }

    $response = [
        'success' => true,
        'payment' => $payment,
        'costs' => $costs,
        'purchasedParts' => $purchasedParts,
        'refunds' => $refunds,
        'computed' => $computed
    ];
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
?>
