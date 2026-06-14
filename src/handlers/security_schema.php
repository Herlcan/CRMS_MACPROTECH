<?php

require_once __DIR__ . '/db_helpers.php';

if (!function_exists('security_unique_index_exists')) {
    function security_unique_index_exists(mysqli $conn, string $table, array $columns): bool {
        $expectedColumns = implode(',', $columns);
        $statement = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total
             FROM (
                SELECT INDEX_NAME,
                       MAX(NON_UNIQUE) AS non_unique,
                       GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS columns_csv
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                GROUP BY INDEX_NAME
             ) indexed_columns
             WHERE non_unique = 0
             AND columns_csv = ?"
        );

        if (!$statement) {
            return false;
        }

        mysqli_stmt_bind_param($statement, "ss", $table, $expectedColumns);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($statement);

        return (int) ($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('security_schema_identifier')) {
    function security_schema_identifier(string $identifier): string {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException('Invalid schema identifier.');
        }

        return '`' . $identifier . '`';
    }
}

if (!function_exists('security_add_unique_constraint')) {
    function security_add_unique_constraint(mysqli $conn, string $table, string $index, array $columns): bool {
        if (security_unique_index_exists($conn, $table, $columns)) {
            return true;
        }

        foreach ($columns as $column) {
            if (!db_table_column_exists($conn, $table, $column)) {
                error_log("Unique constraint skipped: {$table}.{$column} does not exist.");
                return false;
            }
        }

        $tableSql = security_schema_identifier($table);
        $indexSql = security_schema_identifier($index);
        $columnSql = implode(', ', array_map('security_schema_identifier', $columns));

        if (!db_execute_statement($conn, "ALTER TABLE {$tableSql} ADD UNIQUE KEY {$indexSql} ({$columnSql})")) {
            error_log("Unique constraint {$index} could not be added: " . mysqli_error($conn));
            return false;
        }

        return true;
    }
}

if (!function_exists('ensure_security_unique_constraints')) {
    function ensure_security_unique_constraints(mysqli $conn): void {
        $constraints = [
            ['users', 'uq_users_username', ['username']],
            ['users', 'uq_users_email', ['email']],
            ['work_order', 'uq_work_order_code', ['code']],
            ['payments', 'uq_payments_payment_code', ['payment_code']],
            ['items', 'uq_items_product_code', ['product_code']]
        ];

        foreach ($constraints as [$table, $index, $columns]) {
            security_add_unique_constraint($conn, $table, $index, $columns);
        }
    }
}

?>
