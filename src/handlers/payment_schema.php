<?php

function payment_column_exists(mysqli $conn, string $column): bool
{
    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM payments LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

function ensure_payment_detail_columns(mysqli $conn): void
{
    $columns = [
        'payment_method' => "ALTER TABLE payments ADD COLUMN payment_method VARCHAR(50) NULL AFTER status",
        'reference_number' => "ALTER TABLE payments ADD COLUMN reference_number VARCHAR(100) NULL AFTER payment_method",
        'discount_amount' => "ALTER TABLE payments ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total_amount",
        'amount_paid' => "ALTER TABLE payments ADD COLUMN amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER reference_number",
        'change_amount' => "ALTER TABLE payments ADD COLUMN change_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER amount_paid",
        'remaining_balance' => "ALTER TABLE payments ADD COLUMN remaining_balance DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER change_amount",
        'payment_status' => "ALTER TABLE payments ADD COLUMN payment_status VARCHAR(20) NULL AFTER remaining_balance",
        'notes' => "ALTER TABLE payments ADD COLUMN notes TEXT NULL AFTER payment_status",
        'created_at' => "ALTER TABLE payments ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER date"
    ];

    foreach ($columns as $column => $alterSql) {
        if (!payment_column_exists($conn, $column) && !mysqli_query($conn, $alterSql)) {
            throw new Exception('Failed to prepare payment details: ' . mysqli_error($conn));
        }
    }

    if (!mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS refunds (
            id INT PRIMARY KEY AUTO_INCREMENT,
            payment_id INT NOT NULL,
            refund_amount DECIMAL(10,2) NOT NULL,
            refund_method VARCHAR(50),
            reason TEXT,
            refunded_by INT,
            refunded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (payment_id),
            INDEX (refunded_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ")) {
        throw new Exception('Failed to prepare refund records: ' . mysqli_error($conn));
    }

    mysqli_query($conn, "
        UPDATE payments
        SET status = 'Unpaid', payment_status = 'Unpaid'
        WHERE (status = 'Pending' OR status = '')
        AND (payment_status IS NULL OR payment_status = '')
    ");
}

function get_payment_costs(mysqli $conn, int $workOrderId): array
{
    $costs = [
        'diagnostic_fee' => 0,
        'work_order_cost' => 0,
        'purchased_parts_total' => 0,
        'gross_total' => 0
    ];

    $workOrderQuery = mysqli_prepare($conn, "
        SELECT diagnostic_fee, work_order_cost
        FROM work_order
        WHERE id = ?
        LIMIT 1
    ");

    if ($workOrderQuery) {
        mysqli_stmt_bind_param($workOrderQuery, "i", $workOrderId);
        mysqli_stmt_execute($workOrderQuery);
        $workOrderResult = mysqli_stmt_get_result($workOrderQuery);
        $workOrder = mysqli_fetch_assoc($workOrderResult);
        mysqli_stmt_close($workOrderQuery);

        if ($workOrder) {
            $costs['diagnostic_fee'] = (float) $workOrder['diagnostic_fee'];
            $costs['work_order_cost'] = (float) $workOrder['work_order_cost'];
        }
    }

    $partsQuery = mysqli_prepare($conn, "
        SELECT pi.quantity, COALESCE(i.price, 0) AS product_price
        FROM purchased_item pi
        LEFT JOIN items i ON pi.product_id = i.id
        WHERE pi.work_order_id = ?
    ");

    if ($partsQuery) {
        mysqli_stmt_bind_param($partsQuery, "i", $workOrderId);
        mysqli_stmt_execute($partsQuery);
        $partsResult = mysqli_stmt_get_result($partsQuery);

        while ($part = mysqli_fetch_assoc($partsResult)) {
            $costs['purchased_parts_total'] += ((float) $part['quantity']) * ((float) $part['product_price']);
        }

        mysqli_stmt_close($partsQuery);
    }

    $costs['gross_total'] = $costs['diagnostic_fee'] + $costs['work_order_cost'] + $costs['purchased_parts_total'];

    return $costs;
}

function get_total_refunded(mysqli $conn, int $paymentId): float
{
    $stmt = mysqli_prepare($conn, "
        SELECT COALESCE(SUM(refund_amount), 0) AS total_refunded
        FROM refunds
        WHERE payment_id = ?
    ");

    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, "i", $paymentId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row ? (float) $row['total_refunded'] : 0;
}

function calculate_payment_status(float $netTotal, float $amountPaid, float $totalRefunded): array
{
    $actualPaid = max(0, $amountPaid - $totalRefunded);
    $remainingBalance = max(0, $netTotal - $actualPaid);
    $changeAmount = max(0, $actualPaid - $netTotal);

    if ($amountPaid > 0 && $totalRefunded >= $amountPaid) {
        $paymentStatus = 'Refunded';
    } elseif ($totalRefunded > 0) {
        $paymentStatus = 'Partially Refunded';
    } elseif ($netTotal <= 0 || $amountPaid >= $netTotal) {
        $paymentStatus = 'Paid';
    } elseif ($amountPaid > 0) {
        $paymentStatus = 'Partial';
    } else {
        $paymentStatus = 'Unpaid';
    }

    return [
        'payment_status' => $paymentStatus,
        'actual_paid' => $actualPaid,
        'remaining_balance' => $remainingBalance,
        'change_amount' => $changeAmount,
        'total_refunded' => $totalRefunded,
        'refundable_balance' => max(0, $amountPaid - $totalRefunded)
    ];
}

?>
