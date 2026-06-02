<?php

function ensure_item_category_name_column($conn) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM item_category LIKE 'category_name'");

    if (!$check) {
        throw new Exception('Failed to inspect item category table: ' . mysqli_error($conn));
    }

    $column_info = mysqli_fetch_assoc($check);

    if (!$column_info || stripos($column_info['Type'], 'varchar(50)') !== false) {
        return;
    }

    if (!mysqli_query($conn, "ALTER TABLE item_category MODIFY category_name varchar(50) NOT NULL")) {
        throw new Exception('Failed to prepare item category name column: ' . mysqli_error($conn));
    }
}

?>
