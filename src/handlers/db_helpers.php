<?php

if (!function_exists('db_bind_params')) {
    function db_bind_params(mysqli_stmt $stmt, string $types, array &$params): bool {
        if ($types === '') {
            return true;
        }

        $references = [];
        foreach ($params as $key => &$value) {
            $references[$key] = &$value;
        }

        return mysqli_stmt_bind_param($stmt, $types, ...$references);
    }
}

if (!function_exists('db_prepared_result')) {
    function db_prepared_result(mysqli $conn, string $sql, string $types = '', array $params = []) {
        $statement = mysqli_prepare($conn, $sql);
        if (!$statement) {
            return false;
        }

        if ($types !== '' && !db_bind_params($statement, $types, $params)) {
            mysqli_stmt_close($statement);
            return false;
        }

        if (!mysqli_stmt_execute($statement)) {
            mysqli_stmt_close($statement);
            return false;
        }

        return mysqli_stmt_get_result($statement);
    }
}

if (!function_exists('db_execute_statement')) {
    function db_execute_statement(mysqli $conn, string $sql): bool {
        $statement = mysqli_prepare($conn, $sql);
        if (!$statement) {
            return false;
        }

        $success = mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        return $success;
    }
}

if (!function_exists('db_table_exists')) {
    function db_table_exists(mysqli $conn, string $table): bool {
        $statement = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = ?"
        );

        if (!$statement) {
            return false;
        }

        mysqli_stmt_bind_param($statement, "s", $table);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($statement);

        return (int) ($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('db_table_column_info')) {
    function db_table_column_info(mysqli $conn, string $table, string $column): ?array {
        $statement = mysqli_prepare(
            $conn,
            "SELECT
                COLUMN_TYPE AS Type,
                IS_NULLABLE AS `Null`
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = ?
             AND COLUMN_NAME = ?
             LIMIT 1"
        );

        if (!$statement) {
            return null;
        }

        mysqli_stmt_bind_param($statement, "ss", $table, $column);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($statement);

        return $row ?: null;
    }
}

if (!function_exists('db_table_column_exists')) {
    function db_table_column_exists(mysqli $conn, string $table, string $column): bool {
        return db_table_column_info($conn, $table, $column) !== null;
    }
}

if (!function_exists('db_table_index_exists')) {
    function db_table_index_exists(mysqli $conn, string $table, string $index): bool {
        $statement = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = ?
             AND INDEX_NAME = ?"
        );

        if (!$statement) {
            return false;
        }

        mysqli_stmt_bind_param($statement, "ss", $table, $index);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($statement);

        return (int) ($row['total'] ?? 0) > 0;
    }
}

?>
