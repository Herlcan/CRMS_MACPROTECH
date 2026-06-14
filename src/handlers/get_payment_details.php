<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

header('Content-Type: application/json');

include '../db/connection.php';
include 'payment_schema.php';
require_once __DIR__ . '/security_helpers.php';

$response = ['success' => false, 'message' => 'Unknown error'];

try {
    require_authenticated_json($conn);
    if (!user_has_role(['Administrator', 'Cashier/Front Desk', 'Cashier/Front Desk Staff'])) {
        audit_authorization_failure($conn, 'view payment details');
        json_response(['success' => false, 'message' => 'You are not allowed to view payment details.'], 403);
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
    log_activity($conn, 'Viewed payment details ' . ($payment['payment_code'] ?: ('#' . $paymentId)), $workOrderId);
    $purchasedParts = get_payment_purchased_parts($conn, $workOrderId);
    $orderedParts = get_payment_ordered_parts($conn, $workOrderId);

    $costs = get_payment_costs($conn, $workOrderId);
    $totals = get_payment_ledger_totals($conn, $paymentId);
    $totalPaid = (float) $totals['total_paid'];
    $totalRefunded = (float) $totals['total_refunded'];
    $grossTotal = (float) $costs['gross_total'];
    $discountAmount = min(max((float) ($payment['discount_amount'] ?? 0), 0), $grossTotal);
    $netTotal = max(0, $grossTotal - $discountAmount);
    $computed = calculate_payment_status($netTotal, $totalPaid, $totalRefunded);
    $computed['total_paid'] = $totalPaid;
    $computed['net_paid'] = $computed['actual_paid'];
    $computed['net_total'] = $netTotal;
    $transactions = get_payment_transactions($conn, $paymentId);

    $payment['total_amount'] = $grossTotal;
    $payment['discount_amount'] = $discountAmount;
    $payment['amount_paid'] = $totalPaid;
    $payment['change_amount'] = $computed['change_amount'];
    $payment['remaining_balance'] = $computed['remaining_balance'];
    $payment['payment_status'] = $computed['payment_status'];
    $payment['status'] = $computed['payment_status'];

    $response = [
        'success' => true,
        'payment' => $payment,
        'costs' => $costs,
        'purchasedParts' => $purchasedParts,
        'orderedParts' => $orderedParts,
        'transactions' => $transactions,
        'refunds' => [],
        'computed' => $computed
    ];
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
?>
