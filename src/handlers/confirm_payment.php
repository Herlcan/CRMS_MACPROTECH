<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: application/json');

include '../db/connection.php';
include 'payment_schema.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/activity_log_helper.php';
require_once __DIR__ . '/settings_helpers.php';

$response = ['success' => false, 'message' => 'Unknown error'];
$transactionStarted = false;

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception('Unauthorized');
    }

    ensure_payment_detail_columns($conn);
    ensure_notifications_table($conn);
    $appSettings = get_app_settings($conn);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $paymentId = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $referenceNumber = trim($_POST['reference_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $paymentAmount = isset($_POST['amount_paid']) ? (float) $_POST['amount_paid'] : 0;
    $discountAmount = isset($_POST['discount_amount']) ? (float) $_POST['discount_amount'] : 0;
    $validMethods = ['Cash', 'GCash', 'Maya', 'Bank Transfer'];
    $digitalMethods = ['GCash', 'Maya', 'Bank Transfer'];

    if ($paymentId <= 0) {
        throw new Exception('Invalid payment ID');
    }

    if (!in_array($paymentMethod, $validMethods, true)) {
        throw new Exception('Invalid payment method');
    }

    if ($paymentAmount <= 0) {
        throw new Exception('Payment amount must be greater than zero');
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

    mysqli_begin_transaction($conn);
    $transactionStarted = true;

    $paymentQuery = mysqli_prepare($conn, "
        SELECT p.id, p.work_order_id, p.amount_paid, p.date, wo.code
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

    $transactionId = record_payment_transaction(
        $conn,
        $paymentId,
        (int) $payment['work_order_id'],
        'payment',
        $paymentAmount,
        $paymentMethod,
        $referenceNumber,
        $notes,
        null,
        (int) $_SESSION['user_id']
    );

    $summary = refresh_payment_summary($conn, $paymentId, $discountAmount, $paymentMethod, $referenceNumber, $notes);
    log_activity(
        $conn,
        'Recorded payment of Php ' . number_format($paymentAmount, 2) . " for {$payment['code']}",
        (int) $payment['work_order_id']
    );
    $repairStatus = null;

    if (
        app_setting_enabled($appSettings, 'auto_release_paid_work_orders')
        &&
        in_array($summary['payment_status'], ['Paid', 'Partially Refunded'], true)
        && (float) $summary['remaining_balance'] <= 0.009
    ) {
        $releaseStmt = mysqli_prepare($conn, "
            UPDATE work_order
            SET status = 'Released',
                completion_date = COALESCE(completion_date, CURDATE())
            WHERE id = ?
            AND status IN ('Repaired', 'Ready for Release')
        ");

        if (!$releaseStmt) {
            throw new Exception('Failed to prepare release update: ' . mysqli_error($conn));
        }

        $workOrderId = (int) $payment['work_order_id'];
        mysqli_stmt_bind_param($releaseStmt, "i", $workOrderId);

        if (!mysqli_stmt_execute($releaseStmt)) {
            $error = mysqli_stmt_error($releaseStmt);
            mysqli_stmt_close($releaseStmt);
            throw new Exception('Failed to release work order: ' . $error);
        }

        $releasedRows = mysqli_stmt_affected_rows($releaseStmt);
        mysqli_stmt_close($releaseStmt);

        if ($releasedRows > 0) {
            $repairStatus = 'Released';
            log_activity($conn, 'Released work order after full payment', $workOrderId);
        }
    }

    mysqli_commit($conn);
    $transactionStarted = false;

    notify_users_by_roles(
        $conn,
        ['Administrator', 'Cashier/Front Desk', 'Cashier/Front Desk Staff'],
        'Payment Received',
        "{$payment['code']} payment status is {$summary['payment_status']}.",
        $summary['payment_status'] === 'Paid' ? 'success' : 'info',
        'payment.php?search=' . urlencode((string) $payment['code'])
    );

    $statusSmsResult = [
        'attempted' => false,
        'success' => false,
        'message' => $repairStatus === 'Released'
            ? 'Released status SMS is skipped. Send the digital receipt email to notify the customer by SMS.'
            : 'Repair status did not change.'
    ];

    $response = [
        'success' => true,
        'message' => 'Payment saved successfully',
        'transaction_id' => $transactionId,
        'transaction_amount' => $paymentAmount,
        'payment_status' => $summary['payment_status'],
        'total_amount' => $summary['total_amount'],
        'discount_amount' => $summary['discount_amount'],
        'net_total' => $summary['net_total'],
        'amount_paid' => $summary['amount_paid'],
        'total_paid' => $summary['total_paid'],
        'actual_paid' => $summary['actual_paid'],
        'net_paid' => $summary['net_paid'],
        'total_refunded' => $summary['total_refunded'],
        'refundable_balance' => $summary['refundable_balance'],
        'change_amount' => $summary['change_amount'],
        'remaining_balance' => $summary['remaining_balance'],
        'payment_method' => $summary['payment_method'],
        'reference_number' => $summary['reference_number'],
        'date' => $summary['date'],
        'transaction_at' => date('Y-m-d H:i:s'),
        'recorded_by_name' => trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')),
        'repair_status' => $repairStatus,
        'status_sms_notification' => $statusSmsResult
    ];
} catch (Exception $e) {
    if ($transactionStarted && isset($conn)) {
        mysqli_rollback($conn);
    }

    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
?>
