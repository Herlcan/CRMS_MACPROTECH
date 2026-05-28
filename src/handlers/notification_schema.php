<?php

function notification_column_exists(mysqli $conn, string $table, string $column): bool
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

function ensure_notifications_table(mysqli $conn): void
{
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            title VARCHAR(255),
            message TEXT,
            type ENUM('info', 'success', 'warning', 'critical') DEFAULT 'info',
            link VARCHAR(255),
            is_read TINYINT(1) DEFAULT 0,
            is_archived TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $columns = [
        'user_id' => "ALTER TABLE notifications ADD COLUMN user_id INT NOT NULL AFTER id",
        'title' => "ALTER TABLE notifications ADD COLUMN title VARCHAR(255) NULL AFTER user_id",
        'message' => "ALTER TABLE notifications ADD COLUMN message TEXT NULL AFTER title",
        'type' => "ALTER TABLE notifications ADD COLUMN type ENUM('info', 'success', 'warning', 'critical') DEFAULT 'info' AFTER message",
        'link' => "ALTER TABLE notifications ADD COLUMN link VARCHAR(255) NULL AFTER type",
        'is_read' => "ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER link",
        'is_archived' => "ALTER TABLE notifications ADD COLUMN is_archived TINYINT(1) DEFAULT 0 AFTER is_read",
        'created_at' => "ALTER TABLE notifications ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER is_archived"
    ];

    foreach ($columns as $column => $sql) {
        if (!notification_column_exists($conn, 'notifications', $column)) {
            mysqli_query($conn, $sql);
        }
    }
}
?>
