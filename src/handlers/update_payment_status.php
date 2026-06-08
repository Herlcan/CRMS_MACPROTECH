<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';
include '../../auth_check.php';
require_once __DIR__ . '/activity_log_helper.php';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_status'])) {

    $payment_id = (int) $_POST['payment_id'];
    $new_status = trim($_POST['status']);
    $payment_date = $_POST['date'] ?? null;

    // Validate status
    $valid_statuses = ['Unpaid', 'Partial', 'Paid', 'Partially Refunded', 'Refunded'];
    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['payment_error'] = 'Invalid payment status';
        header("Location: ../../payment.php");
        exit();
    }

    $payment = null;
    $payment_query = mysqli_prepare($conn, "
        SELECT p.work_order_id, p.payment_code, wo.code AS work_order_code
        FROM payments p
        LEFT JOIN work_order wo ON wo.id = p.work_order_id
        WHERE p.id = ?
        LIMIT 1
    ");

    if ($payment_query) {
        mysqli_stmt_bind_param($payment_query, "i", $payment_id);
        mysqli_stmt_execute($payment_query);
        $payment_result = mysqli_stmt_get_result($payment_query);
        $payment = mysqli_fetch_assoc($payment_result);
        mysqli_stmt_close($payment_query);
    }

    $update_query = mysqli_prepare($conn,
        "UPDATE payments SET status = ?, payment_status = ?, date = ? WHERE id = ?"
    );

    if (!$update_query) {
        $_SESSION['payment_error'] = 'Database error: ' . mysqli_error($conn);
        header("Location: ../../payment.php");
        exit();
    }

    mysqli_stmt_bind_param($update_query, "sssi", $new_status, $new_status, $payment_date, $payment_id);

    if (mysqli_stmt_execute($update_query)) {
        mysqli_stmt_close($update_query);
        $payment_label = $payment && !empty($payment['payment_code']) ? $payment['payment_code'] : ('payment #' . $payment_id);
        $work_order_label = $payment && !empty($payment['work_order_code']) ? " for {$payment['work_order_code']}" : '';
        $work_order_id = $payment && !empty($payment['work_order_id']) ? (int) $payment['work_order_id'] : null;
        log_activity($conn, "Updated {$payment_label}{$work_order_label} status to {$new_status}", $work_order_id);
        $_SESSION['payment_success'] = 'Payment status updated successfully';
        header("Location: ../../payment.php");
        exit();
    } else {
        $_SESSION['payment_error'] = 'Failed to update payment status';
        header("Location: ../../payment.php");
        exit();
    }
} else {
    header("Location: ../../payment.php");
    exit();
}
?>
