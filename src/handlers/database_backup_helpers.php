<?php

if (!function_exists('database_backup_identifier')) {
    function database_backup_identifier(string $identifier): string {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('Invalid database identifier.');
        }

        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}

if (!function_exists('database_backup_tables')) {
    function database_backup_tables(mysqli $conn): array {
        $tables = [];
        $result = mysqli_query($conn, "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        if (!$result) {
            throw new Exception('Unable to list database tables: ' . mysqli_error($conn));
        }

        while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
            $tables[] = (string) $row[0];
        }

        mysqli_free_result($result);
        sort($tables);

        return $tables;
    }
}

if (!function_exists('database_backup_sql_value')) {
    function database_backup_sql_value(mysqli $conn, $value): string {
        if ($value === null) {
            return 'NULL';
        }

        return "'" . mysqli_real_escape_string($conn, (string) $value) . "'";
    }
}

if (!function_exists('database_backup_send')) {
    function database_backup_send(mysqli $conn): void {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $databaseResult = mysqli_query($conn, 'SELECT DATABASE() AS db_name');
        $databaseRow = $databaseResult ? mysqli_fetch_assoc($databaseResult) : ['db_name' => 'database'];
        $databaseName = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($databaseRow['db_name'] ?? 'database'));
        $filename = 'backup_' . date('Y-m-d_His') . '.sql';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $tables = database_backup_tables($conn);

        echo "-- MACPROTECH database backup\n";
        echo "-- Database: {$databaseName}\n";
        echo "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        echo "SET FOREIGN_KEY_CHECKS=0;\n";
        echo "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
        echo "START TRANSACTION;\n\n";

        foreach (array_reverse($tables) as $table) {
            echo "DROP TABLE IF EXISTS " . database_backup_identifier($table) . ";\n";
        }

        echo "\n";

        foreach ($tables as $table) {
            $tableSql = database_backup_identifier($table);
            $createResult = mysqli_query($conn, "SHOW CREATE TABLE {$tableSql}");

            if (!$createResult) {
                throw new Exception('Unable to read table schema for ' . $table . ': ' . mysqli_error($conn));
            }

            $createRow = mysqli_fetch_assoc($createResult);
            mysqli_free_result($createResult);

            echo "--\n-- Table structure for {$table}\n--\n\n";
            echo ($createRow['Create Table'] ?? '') . ";\n\n";

            $rowsResult = mysqli_query($conn, "SELECT * FROM {$tableSql}");
            if (!$rowsResult) {
                throw new Exception('Unable to read table data for ' . $table . ': ' . mysqli_error($conn));
            }

            $fields = mysqli_fetch_fields($rowsResult);
            $columns = array_map(fn($field) => $field->name, $fields);
            $columnSql = implode(', ', array_map('database_backup_identifier', $columns));
            $rowCount = 0;

            while ($row = mysqli_fetch_assoc($rowsResult)) {
                $values = [];
                foreach ($columns as $column) {
                    $values[] = database_backup_sql_value($conn, $row[$column] ?? null);
                }

                echo "INSERT INTO {$tableSql} ({$columnSql}) VALUES (" . implode(', ', $values) . ");\n";
                $rowCount++;
            }

            mysqli_free_result($rowsResult);

            if ($rowCount > 0) {
                echo "\n";
            }
        }

        echo "COMMIT;\n";
        echo "SET FOREIGN_KEY_CHECKS=1;\n";
        exit();
    }
}

if (!function_exists('database_backup_split_sql')) {
    function database_backup_split_sql(string $sql): array {
        $statements = [];
        $statement = '';
        $quote = null;
        $escape = false;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                $statement .= $char;
                if ($char === "\n") {
                    $lineComment = false;
                }
                continue;
            }

            if ($blockComment) {
                $statement .= $char;
                if ($char === '*' && $next === '/') {
                    $statement .= $next;
                    $i++;
                    $blockComment = false;
                }
                continue;
            }

            if ($quote !== null) {
                $statement .= $char;

                if ($escape) {
                    $escape = false;
                    continue;
                }

                if ($char === '\\') {
                    $escape = true;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '-' && $next === '-') {
                $statement .= $char . $next;
                $i++;
                $lineComment = true;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $statement .= $char . $next;
                $i++;
                $blockComment = true;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $statement .= $char;
                continue;
            }

            if ($char === ';') {
                $trimmed = trim($statement);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $statement = '';
                continue;
            }

            $statement .= $char;
        }

        $trimmed = trim($statement);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}

if (!function_exists('database_restore_normalized_statement')) {
    function database_restore_normalized_statement(string $statement): string {
        $statement = trim($statement);

        while ($statement !== '') {
            $statement = ltrim($statement);

            if (substr($statement, 0, 2) === '--') {
                $lineEnd = strpos($statement, "\n");
                if ($lineEnd === false) {
                    return '';
                }
                $statement = substr($statement, $lineEnd + 1);
                continue;
            }

            if (substr($statement, 0, 1) === '#') {
                $lineEnd = strpos($statement, "\n");
                if ($lineEnd === false) {
                    return '';
                }
                $statement = substr($statement, $lineEnd + 1);
                continue;
            }

            if (substr($statement, 0, 2) === '/*') {
                $commentEnd = strpos($statement, '*/');
                if ($commentEnd === false) {
                    return '';
                }
                $statement = substr($statement, $commentEnd + 2);
                continue;
            }

            break;
        }

        return strtoupper(preg_replace('/\s+/', ' ', trim($statement)));
    }
}

if (!function_exists('database_restore_statement_allowed')) {
    function database_restore_statement_allowed(string $statement): bool {
        $allowedPrefixes = [
            'SET FOREIGN_KEY_CHECKS',
            'SET SQL_MODE',
            'SET NAMES',
            'SET CHARACTER_SET_CLIENT',
            'SET CHARACTER_SET_RESULTS',
            'SET COLLATION_CONNECTION',
            'START TRANSACTION',
            'BEGIN',
            'COMMIT',
            'ROLLBACK',
            'DROP TABLE IF EXISTS',
            'CREATE TABLE',
            'ALTER TABLE',
            'INSERT INTO',
            'LOCK TABLES',
            'UNLOCK TABLES'
        ];

        foreach ($allowedPrefixes as $prefix) {
            if (strncmp($statement, $prefix, strlen($prefix)) === 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('database_restore_from_uploaded_file')) {
    function database_restore_from_uploaded_file(mysqli $conn, array $upload): int {
        $maxBytes = 50 * 1024 * 1024;

        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception('Please upload a valid SQL backup file.');
        }

        if ((int) ($upload['size'] ?? 0) <= 0 || (int) ($upload['size'] ?? 0) > $maxBytes) {
            throw new Exception('Backup file must be between 1 byte and 50MB.');
        }

        $name = (string) ($upload['name'] ?? '');
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'sql') {
            throw new Exception('Only .sql backup files can be restored.');
        }

        $path = (string) ($upload['tmp_name'] ?? '');
        if ($path === '' || !is_uploaded_file($path)) {
            throw new Exception('Uploaded backup file could not be read.');
        }

        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            throw new Exception('Uploaded backup file is empty.');
        }

        $statements = database_backup_split_sql($sql);
        if (empty($statements)) {
            throw new Exception('Uploaded backup file does not contain SQL statements.');
        }

        if (!mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0')) {
            throw new Exception('Unable to disable foreign key checks: ' . mysqli_error($conn));
        }

        $executed = 0;

        try {
            foreach ($statements as $statement) {
                $normalizedStatement = database_restore_normalized_statement($statement);
                if ($normalizedStatement === '') {
                    continue;
                }

                if (!database_restore_statement_allowed($normalizedStatement)) {
                    throw new Exception('Restore contains a disallowed SQL statement near statement ' . ($executed + 1) . '.');
                }

                if (!mysqli_query($conn, $statement)) {
                    throw new Exception('Restore failed near statement ' . ($executed + 1) . ': ' . mysqli_error($conn));
                }
                $executed++;
            }
        } finally {
            mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
        }

        return $executed;
    }
}

?>
