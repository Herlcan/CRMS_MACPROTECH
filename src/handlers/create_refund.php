<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: application/json');

include '../db/connection.php';
include 'payment_schema.php';

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

    $paymentQuery = mysqli_prepare($conn, "
        SELECT id, work_order_id, total_amount, discount_amount, amount_paid
        FROM payments
        WHERE id = ?
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

    $amountPaid = (float) $payment['amount_paid'];
    $totalRefunded = get_total_refunded($conn, $paymentId);
    $refundableBalance = max(0, $amountPaid - $totalRefunded);

    if ($refundAmount > $refundableBalance) {
        throw new Exception('Refund amount exceeds refundable balance');
    }

    mysqli_begin_transaction($conn);
    $transactionStarted = true;

    $insertRefund = mysqli_prepare($conn, "
        INSERT INTO refunds (payment_id, refund_amount, refund_method, reason, refunded_by)
        VALUES (?, ?, ?, ?, ?)
    ");

    if (!$insertRefund) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    $userId = (int) $_SESSION['user_id'];
    mysqli_stmt_bind_param($insertRefund, "idssi", $paymentId, $refundAmount, $refundMethod, $reason, $userId);

    if (!mysqli_stmt_execute($insertRefund)) {
        throw new Exception('Failed to save refund: ' . mysqli_stmt_error($insertRefund));
    }

    mysqli_stmt_close($insertRefund);

    $totalRefunded += $refundAmount;
    $netTotal = max(0, (float) $payment['total_amount'] - (float) $payment['discount_amount']);
    $computed = calculate_payment_status($netTotal, $amountPaid, $totalRefunded);
    $paymentStatus = $computed['payment_status'];
    $changeAmount = $computed['change_amount'];
    $remainingBalance = $computed['remaining_balance'];

    $updatePayment = mysqli_prepare($conn, "
        UPDATE payments
        SET payment_status = ?, status = ?, change_amount = ?, remaining_balance = ?
        WHERE id = ?
    ");

    if (!$updatePayment) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($updatePayment, "ssddi", $paymentStatus, $paymentStatus, $changeAmount, $remainingBalance, $paymentId);

    if (!mysqli_stmt_execute($updatePayment)) {
        throw new Exception('Failed to update payment after refund: ' . mysqli_stmt_error($updatePayment));
    }

    mysqli_stmt_close($updatePayment);
    mysqli_commit($conn);
    $transactionStarted = false;

    $response = [
        'success' => true,
        'message' => 'Refund saved successfully',
        'payment_status' => $paymentStatus,
        'refund_amount' => $refundAmount,
        'total_refunded' => $totalRefunded,
        'actual_paid' => $computed['actual_paid'],
        'change_amount' => $changeAmount,
        'remaining_balance' => $remainingBalance,
        'refundable_balance' => $computed['refundable_balance']
    ];
} catch (Exception $e) {
    if ($transactionStarted && isset($conn)) {
        mysqli_rollback($conn);
    }

    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
?>
