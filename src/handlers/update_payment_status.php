<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';
include '../../auth_check.php';

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
