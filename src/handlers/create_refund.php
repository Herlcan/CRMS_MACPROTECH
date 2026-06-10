<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: application/json');

include '../db/connection.php';
include 'payment_schema.php';
require_once __DIR__ . '/activity_log_helper.php';
require_once __DIR__ . '/security_helpers.php';

$response = ['success' => false, 'message' => 'Unknown error'];
$transactionStarted = false;

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception('Unauthorized');
    }

    ensure_payment_detail_columns($conn);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!verify_csrf_token()) {
        http_response_code(403);
        throw new Exception('Your form session expired. Please try again.');
    }

    if (!user_has_role(['Administrator', 'Cashier/Front Desk', 'Cashier/Front Desk Staff'])) {
        http_response_code(403);
        throw new Exception('You are not allowed to record refunds.');
    }

    $paymentId = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
    $refundAmount = isset($_POST['refund_amount']) ? (float) $_POST['refund_amount'] : 0;
    $refundMethod = trim($_POST['refund_method'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $validMethods = ['Cash', 'GCash', 'Maya', 'Bank Transfer'];

    if ($paymentId <= 0) {
        throw new Exception('Invalid payment ID');
    }

    if ($refundAmount <= 0) {
        throw new Exception('Refund amount must be greater than zero');
    }

    if (!in_array($refundMethod, $validMethods, true)) {
        throw new Exception('Invalid refund method');
    }

    if ($reason === '') {
        throw new Exception('Refund reason is required');
    }

    mysqli_begin_transaction($conn);
    $transactionStarted = true;

    $paymentQuery = mysqli_prepare($conn, "
        SELECT p.id, p.work_order_id, p.total_amount, p.discount_amount, p.amount_paid, wo.code
        FROM payments p
        INNER JOIN work_order wo ON wo.id = p.work_order_id
        WHERE p.id = ?
        LIMIT 1
        FOR UPDATE
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

    $totals = get_payment_ledger_totals($conn, $paymentId);
    $amountPaid = (float) $totals['total_paid'];
    $totalRefunded = (float) $totals['total_refunded'];
    $refundableBalance = max(0, $amountPaid - $totalRefunded);

    if ($refundAmount > $refundableBalance) {
        throw new Exception('Refund amount exceeds refundable balance');
    }

    $userId = (int) $_SESSION['user_id'];
    $transactionId = record_payment_transaction(
        $conn,
        $paymentId,
        (int) $payment['work_order_id'],
        'refund',
        $refundAmount,
        $refundMethod,
        null,
        null,
        $reason,
        $userId
    );

    $summary = refresh_payment_summary($conn, $paymentId);
    log_activity(
        $conn,
        'Recorded refund of Php ' . number_format($refundAmount, 2) . " for {$payment['code']}",
        (int) $payment['work_order_id'],
        $userId
    );
    mysqli_commit($conn);
    $transactionStarted = false;

    $response = [
        'success' => true,
        'message' => 'Refund saved successfully',
        'transaction_id' => $transactionId,
        'payment_status' => $summary['payment_status'],
        'refund_amount' => $refundAmount,
        'total_paid' => $summary['total_paid'],
        'amount_paid' => $summary['amount_paid'],
        'total_refunded' => $summary['total_refunded'],
        'actual_paid' => $summary['actual_paid'],
        'net_paid' => $summary['net_paid'],
        'change_amount' => $summary['change_amount'],
        'remaining_balance' => $summary['remaining_balance'],
        'refundable_balance' => $summary['refundable_balance'],
        'transaction_at' => date('Y-m-d H:i:s'),
        'recorded_by_name' => trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''))
    ];
} catch (Exception $e) {
    if ($transactionStarted && isset($conn)) {
        mysqli_rollback($conn);
    }

    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
?>
