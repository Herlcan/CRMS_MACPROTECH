<?php

function items_table_has_column($conn, $column) {
    $column = mysqli_real_escape_string($conn, $column);
    $check = mysqli_query($conn, "SHOW COLUMNS FROM items LIKE '$column'");

    if (!$check) {
        throw new Exception('Failed to inspect items table: ' . mysqli_error($conn));
    }

    return mysqli_num_rows($check) > 0;
}

function ensure_items_markup_percentage_column($conn) {
    if (items_table_has_column($conn, 'markup_percentage')) {
        return;
    }

    $sql = "ALTER TABLE items ADD COLUMN markup_percentage decimal(10,2) NOT NULL DEFAULT 0.00 AFTER quantity";

    if (!mysqli_query($conn, $sql)) {
        throw new Exception('Failed to prepare item markup percentage column: ' . mysqli_error($conn));
    }
}

function ensure_items_average_price_column($conn) {
    if (!items_table_has_column($conn, 'average_price') && items_table_has_column($conn, 'price')) {
        $sql = "ALTER TABLE items CHANGE price average_price decimal(10,2) NOT NULL DEFAULT 0.00";

        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Failed to prepare item average price column: ' . mysqli_error($conn));
        }

        return;
    }

    if (!items_table_has_column($conn, 'average_price')) {
        $sql = "ALTER TABLE items ADD COLUMN average_price decimal(10,2) NOT NULL DEFAULT 0.00 AFTER markup_percentage";

        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Failed to prepare item average price column: ' . mysqli_error($conn));
        }

        return;
    }

    $check = mysqli_query($conn, "SHOW COLUMNS FROM items LIKE 'average_price'");
    $column_info = mysqli_fetch_assoc($check);

    if ($column_info && stripos($column_info['Type'], 'decimal') === false) {
        $sql = "ALTER TABLE items MODIFY average_price decimal(10,2) NOT NULL DEFAULT 0.00";

        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Failed to prepare item average price column: ' . mysqli_error($conn));
        }
    }
}

function ensure_items_status_column($conn) {
    if (items_table_has_column($conn, 'status')) {
        if (!mysqli_query($conn, "ALTER TABLE items MODIFY status varchar(50) NOT NULL DEFAULT ''")) {
            throw new Exception('Failed to prepare item status column: ' . mysqli_error($conn));
        }
        return;
    }

    $sql = "ALTER TABLE items ADD COLUMN status varchar(50) NOT NULL DEFAULT '' AFTER average_price";

    if (!mysqli_query($conn, $sql)) {
        throw new Exception('Failed to prepare item status column: ' . mysqli_error($conn));
    }
}

function ensure_items_quantity_column($conn) {
    if (!items_table_has_column($conn, 'quantity')) {
        return;
    }

    if (!mysqli_query($conn, "ALTER TABLE items MODIFY quantity int(11) NOT NULL DEFAULT 0")) {
        throw new Exception('Failed to prepare item quantity column: ' . mysqli_error($conn));
    }
}

function ensure_items_inventory_columns($conn) {
    ensure_items_quantity_column($conn);
    ensure_items_markup_percentage_column($conn);
    ensure_items_average_price_column($conn);
    ensure_items_status_column($conn);
}

function drop_items_capital_column($conn) {
    if (!items_table_has_column($conn, 'capital')) {
        return;
    }

    if (!mysqli_query($conn, "ALTER TABLE items DROP COLUMN capital")) {
        throw new Exception('Failed to remove item capital column: ' . mysqli_error($conn));
    }
}

?>
