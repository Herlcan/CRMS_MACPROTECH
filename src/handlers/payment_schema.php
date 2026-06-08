<?php

require_once __DIR__ . '/item_schema.php';
require_once __DIR__ . '/ordered_part_schema.php';

function payment_column_exists(mysqli $conn, string $column): bool
{
    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM payments LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

function payment_table_exists(mysqli $conn, string $table): bool
{
    $table = mysqli_real_escape_string($conn, $table);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return $result && mysqli_num_rows($result) > 0;
}

function payment_table_column_exists(mysqli $conn, string $table, string $column): bool
{
    $table = str_replace('`', '', $table);
    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

function payment_table_index_exists(mysqli $conn, string $table, string $index): bool
{
    $table = str_replace('`', '', $table);
    $index = mysqli_real_escape_string($conn, $index);
    $result = mysqli_query($conn, "SHOW INDEX FROM `$table` WHERE Key_name = '$index'");
    return $result && mysqli_num_rows($result) > 0;
}

function payment_column_allows_null(mysqli $conn, string $column): bool
{
    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM payments LIKE '$column'");
    $row = $result ? mysqli_fetch_assoc($result) : null;

    return $row && strtoupper((string) $row['Null']) === 'YES';
}

function payment_column_type(mysqli $conn, string $column): string
{
    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM payments LIKE '$column'");
    $row = $result ? mysqli_fetch_assoc($result) : null;

    return $row ? strtolower((string) $row['Type']) : '';
}

function ensure_payment_detail_columns(mysqli $conn): void
{
    ensure_purchased_item_unit_price_column($conn);

    $columns = [
        'payment_method' => "ALTER TABLE payments ADD COLUMN payment_method VARCHAR(50) NULL AFTER status",
        'reference_number' => "ALTER TABLE payments ADD COLUMN reference_number VARCHAR(100) NULL AFTER payment_method",
        'discount_amount' => "ALTER TABLE payments ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total_amount",
        'amount_paid' => "ALTER TABLE payments ADD COLUMN amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER reference_number",
        'change_amount' => "ALTER TABLE payments ADD COLUMN change_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER amount_paid",
        'remaining_balance' => "ALTER TABLE payments ADD COLUMN remaining_balance DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER change_amount",
        'payment_status' => "ALTER TABLE payments ADD COLUMN payment_status VARCHAR(50) NULL AFTER remaining_balance",
        'notes' => "ALTER TABLE payments ADD COLUMN notes TEXT NULL AFTER payment_status",
        'created_at' => "ALTER TABLE payments ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER date"
    ];

    foreach ($columns as $column => $alterSql) {
        if (!payment_column_exists($conn, $column) && !mysqli_query($conn, $alterSql)) {
            throw new Exception('Failed to prepare payment details: ' . mysqli_error($conn));
        }
    }

    if (payment_column_exists($conn, 'total_amount') && !str_contains(payment_column_type($conn, 'total_amount'), 'decimal')) {
        if (!mysqli_query($conn, "ALTER TABLE payments MODIFY COLUMN total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00")) {
            throw new Exception('Failed to prepare payment total amount precision: ' . mysqli_error($conn));
        }
    }

    if (!payment_column_allows_null($conn, 'date') && !mysqli_query($conn, "ALTER TABLE payments MODIFY COLUMN date DATE NULL DEFAULT NULL")) {
        throw new Exception('Failed to prepare paid date column: ' . mysqli_error($conn));
    }

    if (payment_column_exists($conn, 'payment_status')) {
        mysqli_query($conn, "ALTER TABLE payments MODIFY COLUMN payment_status VARCHAR(50) NULL");
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

    ensure_payment_transaction_table($conn);
    migrate_legacy_payment_transactions($conn);

    mysqli_query($conn, "
        UPDATE payments
        SET status = 'Unpaid', payment_status = 'Unpaid'
        WHERE (status = 'Pending' OR status = '')
        AND (payment_status IS NULL OR payment_status = '')
    ");

    mysqli_query($conn, "
        UPDATE payments
        SET date = NULL
        WHERE COALESCE(amount_paid, 0) = 0
        AND (
            payment_status IS NULL
            OR payment_status = ''
            OR payment_status = 'Unpaid'
            OR status IS NULL
            OR status = ''
            OR status = 'Pending'
            OR status = 'Unpaid'
        )
    ");
}

function ensure_purchased_item_unit_price_column(mysqli $conn): void
{
    if (!payment_table_exists($conn, 'purchased_item')) {
        return;
    }

    ensure_items_inventory_columns($conn);

    if (!payment_table_column_exists($conn, 'purchased_item', 'unit_price')) {
        if (!mysqli_query($conn, "ALTER TABLE purchased_item ADD COLUMN unit_price DECIMAL(10,2) NULL AFTER quantity")) {
            throw new Exception('Failed to prepare purchased item unit price: ' . mysqli_error($conn));
        }
    }

    mysqli_query($conn, "
        UPDATE purchased_item pi
        LEFT JOIN items i ON (
            (pi.product_id REGEXP '^[0-9]+$' AND CAST(pi.product_id AS UNSIGNED) = i.id)
            OR pi.product_id = i.product_code
        )
        SET pi.unit_price = COALESCE(i.average_price, 0)
        WHERE pi.unit_price IS NULL
    ");
}

function ensure_payment_transaction_table(mysqli $conn): void
{
    if (!mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS payment_transaction (
            id INT PRIMARY KEY AUTO_INCREMENT,
            payment_id INT NOT NULL,
            work_order_id INT NOT NULL,
            transaction_type VARCHAR(20) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            method VARCHAR(50) DEFAULT NULL,
            reference_number VARCHAR(100) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            reason TEXT DEFAULT NULL,
            recorded_by INT DEFAULT NULL,
            transaction_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            source_table VARCHAR(50) DEFAULT NULL,
            source_id INT DEFAULT NULL,
            INDEX idx_payment_transaction_payment (payment_id),
            INDEX idx_payment_transaction_work_order (work_order_id),
            INDEX idx_payment_transaction_type (transaction_type),
            INDEX idx_payment_transaction_recorded_by (recorded_by),
            INDEX idx_payment_transaction_at (transaction_at),
            UNIQUE KEY uq_payment_transaction_source (source_table, source_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ")) {
        throw new Exception('Failed to prepare payment transaction records: ' . mysqli_error($conn));
    }

    $columns = [
        'payment_id' => "ALTER TABLE payment_transaction ADD COLUMN payment_id INT NOT NULL AFTER id",
        'work_order_id' => "ALTER TABLE payment_transaction ADD COLUMN work_order_id INT NOT NULL AFTER payment_id",
        'transaction_type' => "ALTER TABLE payment_transaction ADD COLUMN transaction_type VARCHAR(20) NOT NULL AFTER work_order_id",
        'amount' => "ALTER TABLE payment_transaction ADD COLUMN amount DECIMAL(10,2) NOT NULL AFTER transaction_type",
        'method' => "ALTER TABLE payment_transaction ADD COLUMN method VARCHAR(50) DEFAULT NULL AFTER amount",
        'reference_number' => "ALTER TABLE payment_transaction ADD COLUMN reference_number VARCHAR(100) DEFAULT NULL AFTER method",
        'notes' => "ALTER TABLE payment_transaction ADD COLUMN notes TEXT DEFAULT NULL AFTER reference_number",
        'reason' => "ALTER TABLE payment_transaction ADD COLUMN reason TEXT DEFAULT NULL AFTER notes",
        'recorded_by' => "ALTER TABLE payment_transaction ADD COLUMN recorded_by INT DEFAULT NULL AFTER reason",
        'transaction_at' => "ALTER TABLE payment_transaction ADD COLUMN transaction_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER recorded_by",
        'created_at' => "ALTER TABLE payment_transaction ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER transaction_at",
        'source_table' => "ALTER TABLE payment_transaction ADD COLUMN source_table VARCHAR(50) DEFAULT NULL AFTER created_at",
        'source_id' => "ALTER TABLE payment_transaction ADD COLUMN source_id INT DEFAULT NULL AFTER source_table"
    ];

    foreach ($columns as $column => $alterSql) {
        if (!payment_table_column_exists($conn, 'payment_transaction', $column) && !mysqli_query($conn, $alterSql)) {
            throw new Exception('Failed to prepare payment transaction column: ' . mysqli_error($conn));
        }
    }

    $indexes = [
        'idx_payment_transaction_payment' => "ALTER TABLE payment_transaction ADD INDEX idx_payment_transaction_payment (payment_id)",
        'idx_payment_transaction_work_order' => "ALTER TABLE payment_transaction ADD INDEX idx_payment_transaction_work_order (work_order_id)",
        'idx_payment_transaction_type' => "ALTER TABLE payment_transaction ADD INDEX idx_payment_transaction_type (transaction_type)",
        'idx_payment_transaction_recorded_by' => "ALTER TABLE payment_transaction ADD INDEX idx_payment_transaction_recorded_by (recorded_by)",
        'idx_payment_transaction_at' => "ALTER TABLE payment_transaction ADD INDEX idx_payment_transaction_at (transaction_at)",
        'uq_payment_transaction_source' => "ALTER TABLE payment_transaction ADD UNIQUE KEY uq_payment_transaction_source (source_table, source_id)"
    ];

    foreach ($indexes as $index => $alterSql) {
        if (!payment_table_index_exists($conn, 'payment_transaction', $index) && !mysqli_query($conn, $alterSql)) {
            throw new Exception('Failed to prepare payment transaction index: ' . mysqli_error($conn));
        }
    }
}

function migrate_legacy_payment_transactions(mysqli $conn): void
{
    mysqli_query($conn, "
        INSERT INTO payment_transaction
            (payment_id, work_order_id, transaction_type, amount, method, reference_number, notes, recorded_by, transaction_at, source_table, source_id)
        SELECT
            p.id,
            p.work_order_id,
            'payment',
            p.amount_paid,
            p.payment_method,
            p.reference_number,
            p.notes,
            NULL,
            CASE
                WHEN p.date IS NOT NULL AND p.date <> '0000-00-00' THEN CAST(CONCAT(p.date, ' 00:00:00') AS DATETIME)
                WHEN p.created_at IS NOT NULL THEN p.created_at
                ELSE NOW()
            END,
            'payments',
            p.id
        FROM payments p
        WHERE COALESCE(p.amount_paid, 0) > 0
        AND NOT EXISTS (
            SELECT 1
            FROM payment_transaction pt
            WHERE pt.payment_id = p.id
            AND pt.transaction_type = 'payment'
        )
    ");

    if (payment_table_exists($conn, 'refunds')) {
        mysqli_query($conn, "
            INSERT INTO payment_transaction
                (payment_id, work_order_id, transaction_type, amount, method, reference_number, notes, reason, recorded_by, transaction_at, source_table, source_id)
            SELECT
                r.payment_id,
                p.work_order_id,
                'refund',
                r.refund_amount,
                r.refund_method,
                NULL,
                NULL,
                r.reason,
                r.refunded_by,
                COALESCE(r.refunded_at, NOW()),
                'refunds',
                r.id
            FROM refunds r
            INNER JOIN payments p ON p.id = r.payment_id
            WHERE COALESCE(r.refund_amount, 0) > 0
            AND NOT EXISTS (
                SELECT 1
                FROM payment_transaction pt
                WHERE pt.source_table = 'refunds'
                AND pt.source_id = r.id
            )
        ");
    }
}

function get_payment_costs(mysqli $conn, int $workOrderId): array
{
    ensure_items_inventory_columns($conn);

    $costs = [
        'diagnostic_fee' => 0,
        'work_order_cost' => 0,
        'purchased_parts_total' => 0,
        'ordered_parts_total' => 0,
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

    foreach (get_payment_purchased_parts($conn, $workOrderId) as $part) {
        $costs['purchased_parts_total'] += ((float) $part['quantity']) * ((float) $part['product_price']);
    }

    foreach (get_payment_ordered_parts($conn, $workOrderId) as $part) {
        $costs['ordered_parts_total'] += ((float) $part['quantity']) * ((float) $part['price']);
    }

    $costs['gross_total'] = $costs['diagnostic_fee']
        + $costs['work_order_cost']
        + $costs['purchased_parts_total']
        + $costs['ordered_parts_total'];

    return $costs;
}

function get_payment_ledger_totals(mysqli $conn, int $paymentId): array
{
    ensure_payment_transaction_table($conn);

    $totals = [
        'total_paid' => 0,
        'total_refunded' => 0
    ];

    $stmt = mysqli_prepare($conn, "
        SELECT
            COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END), 0) AS total_paid,
            COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0) AS total_refunded
        FROM payment_transaction
        WHERE payment_id = ?
    ");

    if (!$stmt) {
        return $totals;
    }

    mysqli_stmt_bind_param($stmt, "i", $paymentId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row) {
        $totals['total_paid'] = (float) $row['total_paid'];
        $totals['total_refunded'] = (float) $row['total_refunded'];
    }

    return $totals;
}

function get_total_paid(mysqli $conn, int $paymentId): float
{
    $totals = get_payment_ledger_totals($conn, $paymentId);
    return (float) $totals['total_paid'];
}

function get_total_refunded(mysqli $conn, int $paymentId): float
{
    $totals = get_payment_ledger_totals($conn, $paymentId);
    return (float) $totals['total_refunded'];
}

function get_latest_payment_transaction_date(mysqli $conn, int $paymentId): ?string
{
    $stmt = mysqli_prepare($conn, "
        SELECT DATE(transaction_at) AS paid_date
        FROM payment_transaction
        WHERE payment_id = ?
        AND transaction_type = 'payment'
        ORDER BY transaction_at DESC, id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "i", $paymentId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row && !empty($row['paid_date']) ? $row['paid_date'] : null;
}

function get_payment_purchased_parts(mysqli $conn, int $workOrderId): array
{
    ensure_purchased_item_unit_price_column($conn);

    $parts = [];
    $partsQuery = mysqli_prepare($conn, "
        SELECT
            pi.*,
            COALESCE(i.product_code, pi.product_id, '') AS product_code,
            COALESCE(i.brand_name, 'Unknown Item') AS product_name,
            COALESCE(i.model, '') AS product_model,
            COALESCE(pi.unit_price, i.average_price, 0) AS product_price
        FROM purchased_item pi
        LEFT JOIN items i ON (
            (pi.product_id REGEXP '^[0-9]+$' AND CAST(pi.product_id AS UNSIGNED) = i.id)
            OR pi.product_id = i.product_code
        )
        WHERE pi.work_order_id = ?
        ORDER BY pi.id ASC
    ");

    if (!$partsQuery) {
        return $parts;
    }

    mysqli_stmt_bind_param($partsQuery, "i", $workOrderId);
    mysqli_stmt_execute($partsQuery);
    $partsResult = mysqli_stmt_get_result($partsQuery);
    $parts = mysqli_fetch_all($partsResult, MYSQLI_ASSOC);
    mysqli_stmt_close($partsQuery);

    return $parts;
}

function get_payment_ordered_parts(mysqli $conn, int $workOrderId): array
{
    ensure_ordered_parts_table($conn);

    $orderedParts = [];
    $orderedQuery = mysqli_prepare($conn, "
        SELECT id, work_order_id, part_name, brand, category, description, quantity, price, created_at
        FROM ordered_parts
        WHERE work_order_id = ?
        ORDER BY id ASC
    ");

    if (!$orderedQuery) {
        return $orderedParts;
    }

    mysqli_stmt_bind_param($orderedQuery, "i", $workOrderId);
    mysqli_stmt_execute($orderedQuery);
    $orderedResult = mysqli_stmt_get_result($orderedQuery);
    $orderedParts = mysqli_fetch_all($orderedResult, MYSQLI_ASSOC);
    mysqli_stmt_close($orderedQuery);

    return $orderedParts;
}

function record_payment_transaction(
    mysqli $conn,
    int $paymentId,
    int $workOrderId,
    string $transactionType,
    float $amount,
    ?string $method,
    ?string $referenceNumber,
    ?string $notes,
    ?string $reason,
    ?int $recordedBy
): int {
    ensure_payment_transaction_table($conn);

    if (!in_array($transactionType, ['payment', 'refund'], true)) {
        throw new Exception('Invalid payment transaction type.');
    }

    if ($paymentId <= 0 || $workOrderId <= 0 || $amount <= 0) {
        throw new Exception('Invalid payment transaction details.');
    }

    $method = $method !== null && trim($method) !== '' ? trim($method) : null;
    $referenceNumber = $referenceNumber !== null && trim($referenceNumber) !== '' ? trim($referenceNumber) : null;
    $notes = $notes !== null && trim($notes) !== '' ? trim($notes) : null;
    $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

    $stmt = mysqli_prepare($conn, "
        INSERT INTO payment_transaction
            (payment_id, work_order_id, transaction_type, amount, method, reference_number, notes, reason, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $stmt,
        "iisdssssi",
        $paymentId,
        $workOrderId,
        $transactionType,
        $amount,
        $method,
        $referenceNumber,
        $notes,
        $reason,
        $recordedBy
    );

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('Failed to record payment transaction: ' . $error);
    }

    $transactionId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return (int) $transactionId;
}

function refresh_payment_summary(
    mysqli $conn,
    int $paymentId,
    ?float $discountAmount = null,
    ?string $paymentMethod = null,
    ?string $referenceNumber = null,
    ?string $notes = null
): array {
    $paymentQuery = mysqli_prepare($conn, "
        SELECT id, work_order_id, total_amount, discount_amount, payment_method, reference_number, notes
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

    $costs = get_payment_costs($conn, (int) $payment['work_order_id']);
    $grossTotal = (float) $costs['gross_total'];
    $discount = $discountAmount !== null ? $discountAmount : (float) ($payment['discount_amount'] ?? 0);
    $discount = min(max($discount, 0), $grossTotal);
    $totals = get_payment_ledger_totals($conn, $paymentId);
    $totalPaid = (float) $totals['total_paid'];
    $totalRefunded = (float) $totals['total_refunded'];
    $netTotal = max(0, $grossTotal - $discount);
    $computed = calculate_payment_status($netTotal, $totalPaid, $totalRefunded);
    $paidDate = $totalPaid > 0 ? get_latest_payment_transaction_date($conn, $paymentId) : null;
    $paymentMethod = $paymentMethod !== null ? $paymentMethod : ($payment['payment_method'] ?? null);
    $referenceNumber = $referenceNumber !== null ? $referenceNumber : ($payment['reference_number'] ?? null);
    $notes = $notes !== null ? $notes : ($payment['notes'] ?? null);

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
        $discount,
        $paymentMethod,
        $referenceNumber,
        $totalPaid,
        $computed['change_amount'],
        $computed['remaining_balance'],
        $computed['payment_status'],
        $computed['payment_status'],
        $notes,
        $paidDate,
        $paymentId
    );

    if (!mysqli_stmt_execute($updateQuery)) {
        $error = mysqli_stmt_error($updateQuery);
        mysqli_stmt_close($updateQuery);
        throw new Exception('Failed to update payment summary: ' . $error);
    }

    mysqli_stmt_close($updateQuery);

    return [
        'payment_id' => $paymentId,
        'work_order_id' => (int) $payment['work_order_id'],
        'costs' => $costs,
        'total_amount' => $grossTotal,
        'discount_amount' => $discount,
        'net_total' => $netTotal,
        'total_paid' => $totalPaid,
        'amount_paid' => $totalPaid,
        'total_refunded' => $totalRefunded,
        'actual_paid' => $computed['actual_paid'],
        'net_paid' => $computed['actual_paid'],
        'change_amount' => $computed['change_amount'],
        'remaining_balance' => $computed['remaining_balance'],
        'refundable_balance' => $computed['refundable_balance'],
        'payment_status' => $computed['payment_status'],
        'payment_method' => $paymentMethod,
        'reference_number' => $referenceNumber,
        'date' => $paidDate
    ];
}

function refresh_payment_summaries(mysqli $conn, ?int $workOrderId = null): void
{
    ensure_payment_detail_columns($conn);

    if ($workOrderId !== null) {
        $query = mysqli_prepare($conn, "SELECT id FROM payments WHERE work_order_id = ? ORDER BY id ASC");
        if (!$query) {
            throw new Exception('Failed to load payment summaries: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($query, "i", $workOrderId);
        mysqli_stmt_execute($query);
        $result = mysqli_stmt_get_result($query);
    } else {
        $query = mysqli_prepare($conn, "SELECT id FROM payments ORDER BY id ASC");
        if (!$query) {
            throw new Exception('Failed to load payment summaries: ' . mysqli_error($conn));
        }

        mysqli_stmt_execute($query);
        $result = mysqli_stmt_get_result($query);
    }

    while ($row = mysqli_fetch_assoc($result)) {
        refresh_payment_summary($conn, (int) $row['id']);
    }

    mysqli_stmt_close($query);
}

function get_payment_transactions(mysqli $conn, int $paymentId): array
{
    ensure_payment_transaction_table($conn);

    $transactions = [];
    $stmt = mysqli_prepare($conn, "
        SELECT
            pt.*,
            CONCAT(u.first_name, ' ', u.last_name) AS recorded_by_name
        FROM payment_transaction pt
        LEFT JOIN users u ON pt.recorded_by = u.id
        WHERE pt.payment_id = ?
        ORDER BY pt.transaction_at ASC, pt.id ASC
    ");

    if (!$stmt) {
        return $transactions;
    }

    mysqli_stmt_bind_param($stmt, "i", $paymentId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $transactions = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $transactions;
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
