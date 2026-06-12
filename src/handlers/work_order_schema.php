<?php

require_once __DIR__ . '/db_helpers.php';

function work_order_column_exists(mysqli $conn, string $column): bool
{
    return db_table_column_exists($conn, 'work_order', $column);
}

function ensure_work_order_priority_column(mysqli $conn): void
{
    if (!work_order_column_exists($conn, 'priority')) {
        if (!db_execute_statement($conn, "ALTER TABLE work_order ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'In Que' AFTER work_order_cost")) {
            throw new Exception('Failed to prepare work order priority: ' . mysqli_error($conn));
        }
    }

    db_execute_statement($conn, "
        UPDATE work_order
        SET priority = 'In Que'
        WHERE priority IS NULL
        OR priority = ''
        OR priority NOT IN ('In Que', 'Rush')
    ");
}

function ensure_work_order_warranty_columns(mysqli $conn): void
{
    if (!work_order_column_exists($conn, 'warranty_days')) {
        if (!db_execute_statement($conn, "ALTER TABLE work_order ADD COLUMN warranty_days SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER completion_date")) {
            throw new Exception('Failed to prepare work order warranty days: ' . mysqli_error($conn));
        }
    }

    if (!work_order_column_exists($conn, 'warranty_start_date')) {
        if (!db_execute_statement($conn, "ALTER TABLE work_order ADD COLUMN warranty_start_date DATE DEFAULT NULL AFTER warranty_days")) {
            throw new Exception('Failed to prepare work order warranty start date: ' . mysqli_error($conn));
        }
    }

    if (!work_order_column_exists($conn, 'warranty_expiration_date')) {
        if (!db_execute_statement($conn, "ALTER TABLE work_order ADD COLUMN warranty_expiration_date DATE DEFAULT NULL AFTER warranty_start_date")) {
            throw new Exception('Failed to prepare work order warranty expiration date: ' . mysqli_error($conn));
        }
    }

    if (!work_order_column_exists($conn, 'warranty_expiration_notified_at')) {
        if (!db_execute_statement($conn, "ALTER TABLE work_order ADD COLUMN warranty_expiration_notified_at DATETIME DEFAULT NULL AFTER warranty_expiration_date")) {
            throw new Exception('Failed to prepare work order warranty notification date: ' . mysqli_error($conn));
        }
    }

    db_execute_statement($conn, "
        UPDATE work_order
        SET warranty_days = 0
        WHERE warranty_days IS NULL
    ");
}

function normalize_work_order_priority($priority): string
{
    $priority = is_scalar($priority) ? trim((string) $priority) : '';

    return in_array($priority, ['In Que', 'Rush'], true) ? $priority : 'In Que';
}

function normalize_work_order_warranty_days($warranty_days): int
{
    if ($warranty_days === null || $warranty_days === '') {
        return 0;
    }

    if (!is_scalar($warranty_days)) {
        return 0;
    }

    $warranty_days = trim((string) $warranty_days);

    if ($warranty_days === '') {
        return 0;
    }

    if (!preg_match('/^\d+$/', $warranty_days)) {
        return 0;
    }

    $days = (int) $warranty_days;

    return min($days, 3650);
}

function activate_work_order_warranty(mysqli $conn, int $work_order_id): void
{
    ensure_work_order_warranty_columns($conn);

    $query = mysqli_prepare($conn, "
        UPDATE work_order
        SET
            warranty_expiration_notified_at = CASE
                WHEN warranty_days > 0
                    AND (
                        warranty_start_date IS NULL
                        OR warranty_expiration_date IS NULL
                        OR warranty_expiration_date <> DATE_ADD(COALESCE(warranty_start_date, CURDATE()), INTERVAL warranty_days DAY)
                    )
                    THEN NULL
                WHEN warranty_days <= 0 THEN NULL
                ELSE warranty_expiration_notified_at
            END,
            warranty_start_date = CASE
                WHEN warranty_days > 0 THEN COALESCE(warranty_start_date, CURDATE())
                ELSE NULL
            END,
            warranty_expiration_date = CASE
                WHEN warranty_days > 0 THEN DATE_ADD(COALESCE(warranty_start_date, CURDATE()), INTERVAL warranty_days DAY)
                ELSE NULL
            END
        WHERE id = ?
    ");

    if (!$query) {
        throw new Exception('Failed to prepare warranty activation: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($query, "i", $work_order_id);

    if (!mysqli_stmt_execute($query)) {
        $error = mysqli_stmt_error($query);
        mysqli_stmt_close($query);
        throw new Exception('Failed to activate work order warranty: ' . $error);
    }

    mysqli_stmt_close($query);
}

function clear_work_order_warranty_dates(mysqli $conn, int $work_order_id): void
{
    ensure_work_order_warranty_columns($conn);

    $query = mysqli_prepare($conn, "
        UPDATE work_order
        SET warranty_start_date = NULL,
            warranty_expiration_date = NULL,
            warranty_expiration_notified_at = NULL
        WHERE id = ?
    ");

    if (!$query) {
        throw new Exception('Failed to prepare warranty reset: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($query, "i", $work_order_id);

    if (!mysqli_stmt_execute($query)) {
        $error = mysqli_stmt_error($query);
        mysqli_stmt_close($query);
        throw new Exception('Failed to reset work order warranty dates: ' . $error);
    }

    mysqli_stmt_close($query);
}

?>
