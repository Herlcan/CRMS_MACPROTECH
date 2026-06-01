<?php

function work_order_assignment_column_exists(mysqli $conn, string $table, string $column): bool
{
    $query = mysqli_prepare($conn, "
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");

    if (!$query) {
        return false;
    }

    mysqli_stmt_bind_param($query, "ss", $table, $column);
    mysqli_stmt_execute($query);
    $result = mysqli_stmt_get_result($query);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($query);

    return (int) ($row['total'] ?? 0) > 0;
}

function ensure_work_order_assignments_table(mysqli $conn): void
{
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS work_order_assignments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            work_order_id INT NOT NULL,
            technician_id INT NOT NULL,
            assigned_by INT NULL,
            assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            unassigned_at DATETIME NULL,
            reason TEXT NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 1,
            INDEX idx_work_order_assignments_work_order (work_order_id),
            INDEX idx_work_order_assignments_technician (technician_id),
            INDEX idx_work_order_assignments_current (work_order_id, is_current)
        )
    ");

    $columns = [
        'work_order_id' => "ALTER TABLE work_order_assignments ADD COLUMN work_order_id INT NOT NULL AFTER id",
        'technician_id' => "ALTER TABLE work_order_assignments ADD COLUMN technician_id INT NOT NULL AFTER work_order_id",
        'assigned_by' => "ALTER TABLE work_order_assignments ADD COLUMN assigned_by INT NULL AFTER technician_id",
        'assigned_at' => "ALTER TABLE work_order_assignments ADD COLUMN assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER assigned_by",
        'unassigned_at' => "ALTER TABLE work_order_assignments ADD COLUMN unassigned_at DATETIME NULL AFTER assigned_at",
        'reason' => "ALTER TABLE work_order_assignments ADD COLUMN reason TEXT NULL AFTER unassigned_at",
        'is_current' => "ALTER TABLE work_order_assignments ADD COLUMN is_current TINYINT(1) NOT NULL DEFAULT 1 AFTER reason"
    ];

    foreach ($columns as $column => $sql) {
        if (!work_order_assignment_column_exists($conn, 'work_order_assignments', $column)) {
            mysqli_query($conn, $sql);
        }
    }
}

function record_work_order_assignment(
    mysqli $conn,
    int $workOrderId,
    ?int $oldTechnicianId,
    ?int $newTechnicianId,
    int $changedBy,
    string $reason
): void {
    ensure_work_order_assignments_table($conn);

    if ($workOrderId <= 0 || $oldTechnicianId === $newTechnicianId) {
        return;
    }

    $closeStmt = mysqli_prepare($conn, "
        UPDATE work_order_assignments
        SET is_current = 0, unassigned_at = COALESCE(unassigned_at, NOW())
        WHERE work_order_id = ?
        AND is_current = 1
    ");

    if (!$closeStmt) {
        throw new Exception('Failed to prepare assignment history update.');
    }

    mysqli_stmt_bind_param($closeStmt, "i", $workOrderId);
    if (!mysqli_stmt_execute($closeStmt)) {
        throw new Exception('Failed to update assignment history.');
    }

    $closedRows = mysqli_stmt_affected_rows($closeStmt);
    mysqli_stmt_close($closeStmt);

    if ($closedRows === 0 && $oldTechnicianId) {
        $legacyReason = $newTechnicianId ? 'Initial assignment before reassignment tracking' : $reason;
        $legacyStmt = mysqli_prepare($conn, "
            INSERT INTO work_order_assignments
                (work_order_id, technician_id, assigned_by, assigned_at, unassigned_at, reason, is_current)
            VALUES (?, ?, ?, NOW(), NOW(), ?, 0)
        ");

        if (!$legacyStmt) {
            throw new Exception('Failed to prepare previous assignment history.');
        }

        mysqli_stmt_bind_param($legacyStmt, "iiis", $workOrderId, $oldTechnicianId, $changedBy, $legacyReason);
        if (!mysqli_stmt_execute($legacyStmt)) {
            throw new Exception('Failed to save previous assignment history.');
        }
        mysqli_stmt_close($legacyStmt);
    }

    if (!$newTechnicianId) {
        return;
    }

    $insertStmt = mysqli_prepare($conn, "
        INSERT INTO work_order_assignments
            (work_order_id, technician_id, assigned_by, assigned_at, unassigned_at, reason, is_current)
        VALUES (?, ?, ?, NOW(), NULL, ?, 1)
    ");

    if (!$insertStmt) {
        throw new Exception('Failed to prepare assignment history.');
    }

    mysqli_stmt_bind_param($insertStmt, "iiis", $workOrderId, $newTechnicianId, $changedBy, $reason);
    if (!mysqli_stmt_execute($insertStmt)) {
        throw new Exception('Failed to save assignment history.');
    }
    mysqli_stmt_close($insertStmt);
}
?>
