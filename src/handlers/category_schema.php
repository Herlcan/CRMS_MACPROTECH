<?php

require_once __DIR__ . '/db_helpers.php';

function ensure_item_category_name_column($conn) {
    $column_info = db_table_column_info($conn, 'item_category', 'category_name');

    if (!$column_info || stripos($column_info['Type'], 'varchar(50)') !== false) {
        return;
    }

    if (!db_execute_statement($conn, "ALTER TABLE item_category MODIFY category_name varchar(50) NOT NULL")) {
        throw new Exception('Failed to prepare item category name column: ' . mysqli_error($conn));
    }
}

?>
