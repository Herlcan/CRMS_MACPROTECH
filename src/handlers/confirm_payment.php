<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: application/json');

include '../db/connection.php';
include 'payment_schema.php';
require_once __DIR__ . '/notification_helpers.php';

$response = ['success' => false, 'message' => 'Unknown error'];
$transactionStarted = false;

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception('Unauthorized');
    }

    ensure_payment_detail_columns($conn);
    ensure_notifications_table($conn);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $paymentId = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $referenceNumber = trim($_POST['reference_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $amountPaid = isset($_POST['amount_paid']) ? (float) $_POST['amount_paid'] : 0;
    $discountAmount = isset($_POST['discount_amount']) ? (float) $_POST['discount_amount'] : 0;
    $validMethods = ['Cash', 'GCash', 'Maya', 'Bank Transfer'];
    $digitalMethods = ['GCash', 'Maya', 'Bank Transfer'];

    if ($paymentId <= 0) {
        throw new Exception('Invalid payment ID');
    }

    if (!in_array($paymentMethod, $validMethods, true)) {
        throw new Exception('Invalid payment method');
    }

    if ($amountPaid < 0) {
        throw new Exception('Amount paid cannot be negative');
    }

    if ($discountAmount < 0) {
        throw new Exception('Discount cannot be negative');
    }

    if (in_array($paymentMethod, $digitalMethods, true) && $referenceNumber === '') {
        throw new Exception('Reference number is required for digital payments');
    }

    if (!in_array($paymentMethod, $digitalMethods, true)) {
        $referenceNumber = '';
    }

    $paymentQuery = mysqli_prepare($conn, "
        SELECT p.id, p.work_order_id, p.amount_paid, p.date, wo.code
        FROM payments p
        INNER JOIN work_order wo ON wo.id = p.work_order_id
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

    $costs = get_payment_costs($conn, (int) $payment['work_order_id']);
    $grossTotal = (float) $costs['gross_total'];
    $discountAmount = min($discountAmount, $grossTotal);
    $netTotal = max(0, $grossTotal - $discountAmount);
    $totalRefunded = get_total_refunded($conn, $paymentId);
    $computed = calculate_payment_status($netTotal, $amountPaid, $totalRefunded);
    $paymentStatus = $computed['payment_status'];
    $changeAmount = $computed['change_amount'];
    $remainingBalance = $computed['remaining_balance'];
    $currentAmountPaid = (float) ($payment['amount_paid'] ?? 0);
    $currentPaidDate = $payment['date'] ?? null;
    $paidDate = $currentPaidDate;

    if ($amountPaid <= 0) {
        $paidDate = null;
    } elseif (empty($currentPaidDate) || $currentPaidDate === '0000-00-00' || abs($currentAmountPaid - $amountPaid) > 0.009) {
        $paidDate = date('Y-m-d');
    }

    mysqli_begin_transaction($conn);
    $transactionStarted = true;

    $updateQuery = mysqli_prepare($conn, "
        UPDATE payments
        SET
            total_amount = ?,
            discount_amount = ?,
            payment_method = ?,
            reference_number = ?,
            amount_paid = ?,
            change_amount = ?,
            remaining_balance = ?,
            payment_status = ?,
            status = ?,
            notes = ?,
            date = ?
        WHERE id = ?
    ");

    if (!$updateQuery) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $updateQuery,
        "ddssdddssssi",
        $grossTotal,
        $discountAmount,
        $paymentMethod,
        $referenceNumber,
        $amountPaid,
        $changeAmount,
        $remainingBalance,
        $paymentStatus,
        $paymentStatus,
        $notes,
        $paidDate,
        $paymentId
    );

    if (!mysqli_stmt_execute($updateQuery)) {
        throw new Exception('Failed to confirm payment: ' . mysqli_stmt_error($updateQuery));
    }

    mysqli_stmt_close($updateQuery);

    mysqli_commit($conn);
    $transactionStarted = false;

    notify_users_by_roles(
        $conn,
        ['Administrator', 'Cashier/Front Desk', 'Cashier/Front Desk Staff'],
        'Payment Received',
        "{$payment['code']} payment status is {$paymentStatus}.",
        $paymentStatus === 'Paid' ? 'success' : 'info',
        'payment.php?search=' . urlencode((string) $payment['code'])
    );

    $response = [
        'success' => true,
        'message' => 'Payment saved successfully',
        'payment_status' => $paymentStatus,
        'total_amount' => $grossTotal,
        'discount_amount' => $discountAmount,
        'net_total' => $netTotal,
        'amount_paid' => $amountPaid,
        'actual_paid' => $computed['actual_paid'],
        'total_refunded' => $totalRefunded,
        'refundable_balance' => $computed['refundable_balance'],
        'change_amount' => $changeAmount,
        'remaining_balance' => $remainingBalance,
        'payment_method' => $paymentMethod,
        'reference_number' => $referenceNumber,
        'date' => $paidDate,
        'repair_status' => null
    ];
} catch (Exception $e) {
    if ($transactionStarted && isset($conn)) {
        mysqli_rollback($conn);
    }

    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
?>
