<?php

function work_order_column_exists(mysqli $conn, string $column): bool
{
    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM work_order LIKE '$column'");

    return $result && mysqli_num_rows($result) > 0;
}

function ensure_work_order_priority_column(mysqli $conn): void
{
    if (!work_order_column_exists($conn, 'priority')) {
        if (!mysqli_query($conn, "ALTER TABLE work_order ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'In Que' AFTER work_order_cost")) {
            throw new Exception('Failed to prepare work order priority: ' . mysqli_error($conn));
        }
    }

    mysqli_query($conn, "
        UPDATE work_order
        SET priority = 'In Que'
        WHERE priority IS NULL
        OR priority = ''
        OR priority NOT IN ('In Que', 'Rush')
    ");
}

function normalize_work_order_priority($priority): string
{
    $priority = is_scalar($priority) ? trim((string) $priority) : '';

    return in_array($priority, ['In Que', 'Rush'], true) ? $priority : 'In Que';
}

?>
