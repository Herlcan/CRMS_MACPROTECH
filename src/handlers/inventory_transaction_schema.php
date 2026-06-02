<?php

require_once __DIR__ . '/item_schema.php';

function inventory_table_has_column($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $check = mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE '$column'");

    if (!$check) {
        throw new Exception("Failed to inspect $table table: " . mysqli_error($conn));
    }

    return mysqli_num_rows($check) > 0;
}

function ensure_stock_in_transaction_table($conn) {
    $sql = "
        CREATE TABLE IF NOT EXISTS stock_in_transaction (
            id int(11) NOT NULL AUTO_INCREMENT,
            item_id int(11) NOT NULL,
            capital decimal(10,2) NOT NULL DEFAULT 0.00,
            stock_in int(11) NOT NULL DEFAULT 0,
            stock_in_date date NOT NULL,
            PRIMARY KEY (id),
            KEY idx_stock_in_transaction_item (item_id),
            KEY idx_stock_in_transaction_date (stock_in_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!mysqli_query($conn, $sql)) {
        throw new Exception('Failed to prepare stock-in transaction table: ' . mysqli_error($conn));
    }
}

function ensure_stock_out_transaction_table($conn) {
    $sql = "
        CREATE TABLE IF NOT EXISTS stock_out_transaction (
            id int(11) NOT NULL AUTO_INCREMENT,
            item_id int(11) NOT NULL,
            work_order_id int(11) NOT NULL DEFAULT 0,
            quantity int(11) NOT NULL DEFAULT 0,
            stock_out_date date NOT NULL,
            PRIMARY KEY (id),
            KEY idx_stock_out_transaction_item (item_id),
            KEY idx_stock_out_transaction_work_order (work_order_id),
            KEY idx_stock_out_transaction_date (stock_out_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!mysqli_query($conn, $sql)) {
        throw new Exception('Failed to prepare stock-out transaction table: ' . mysqli_error($conn));
    }
}

function ensure_inventory_transaction_table($conn) {
    ensure_stock_in_transaction_table($conn);
    ensure_stock_out_transaction_table($conn);

    $sql = "
        CREATE TABLE IF NOT EXISTS inventory_transaction (
            id int(11) NOT NULL AUTO_INCREMENT,
            item_id int(11) NOT NULL,
            total_stock_in int(11) NOT NULL DEFAULT 0,
            total_stock_out int(11) NOT NULL DEFAULT 0,
            status varchar(50) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY uq_inventory_transaction_item (item_id),
            KEY idx_inventory_transaction_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!mysqli_query($conn, $sql)) {
        throw new Exception('Failed to prepare inventory transaction table: ' . mysqli_error($conn));
    }

    migrate_legacy_inventory_transactions($conn);
    ensure_inventory_aggregate_columns($conn);
}

function ensure_inventory_aggregate_columns($conn) {
    if (!inventory_table_has_column($conn, 'inventory_transaction', 'total_stock_in')) {
        if (inventory_table_has_column($conn, 'inventory_transaction', 'stock_in')) {
            mysqli_query($conn, "ALTER TABLE inventory_transaction CHANGE stock_in total_stock_in int(11) NOT NULL DEFAULT 0");
        } else {
            mysqli_query($conn, "ALTER TABLE inventory_transaction ADD COLUMN total_stock_in int(11) NOT NULL DEFAULT 0 AFTER item_id");
        }
    }

    if (!inventory_table_has_column($conn, 'inventory_transaction', 'total_stock_out')) {
        if (inventory_table_has_column($conn, 'inventory_transaction', 'stock_out')) {
            mysqli_query($conn, "ALTER TABLE inventory_transaction CHANGE stock_out total_stock_out int(11) NOT NULL DEFAULT 0");
        } else {
            mysqli_query($conn, "ALTER TABLE inventory_transaction ADD COLUMN total_stock_out int(11) NOT NULL DEFAULT 0 AFTER total_stock_in");
        }
    }

    if (!inventory_table_has_column($conn, 'inventory_transaction', 'status')) {
        mysqli_query($conn, "ALTER TABLE inventory_transaction ADD COLUMN status varchar(50) NOT NULL DEFAULT '' AFTER total_stock_out");
    } else if (!mysqli_query($conn, "ALTER TABLE inventory_transaction MODIFY status varchar(50) NOT NULL DEFAULT ''")) {
        throw new Exception('Failed to prepare inventory transaction status column: ' . mysqli_error($conn));
    }

    foreach (['capital', 'stock_in_date'] as $legacy_column) {
        if (inventory_table_has_column($conn, 'inventory_transaction', $legacy_column)) {
            if (!mysqli_query($conn, "ALTER TABLE inventory_transaction DROP COLUMN $legacy_column")) {
                throw new Exception("Failed to remove inventory transaction $legacy_column column: " . mysqli_error($conn));
            }
        }
    }

    $index_check = mysqli_query($conn, "SHOW INDEX FROM inventory_transaction WHERE Key_name = 'uq_inventory_transaction_item'");
    if ($index_check && mysqli_num_rows($index_check) === 0) {
        if (!mysqli_query($conn, "DELETE FROM inventory_transaction")) {
            throw new Exception('Failed to reset inventory aggregate rows: ' . mysqli_error($conn));
        }

        if (!mysqli_query($conn, "ALTER TABLE inventory_transaction ADD UNIQUE KEY uq_inventory_transaction_item (item_id)")) {
            throw new Exception('Failed to prepare inventory item aggregate index: ' . mysqli_error($conn));
        }
    }
}

function migrate_legacy_inventory_transactions($conn) {
    if (
        inventory_table_has_column($conn, 'inventory_transaction', 'capital')
        && inventory_table_has_column($conn, 'inventory_transaction', 'stock_in')
        && inventory_table_has_column($conn, 'inventory_transaction', 'stock_in_date')
    ) {
        $sql = "
            INSERT INTO stock_in_transaction (item_id, capital, stock_in, stock_in_date)
            SELECT it.item_id, it.capital, it.stock_in, it.stock_in_date
            FROM inventory_transaction it
            WHERE it.stock_in > 0
            AND NOT EXISTS (
                SELECT 1
                FROM stock_in_transaction sit
                WHERE sit.item_id = it.item_id
                AND sit.capital = it.capital
                AND sit.stock_in = it.stock_in
                AND sit.stock_in_date = it.stock_in_date
            )
        ";

        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Failed to migrate stock-in transactions: ' . mysqli_error($conn));
        }
    }

    backfill_stock_out_transactions_from_purchased_items($conn);
}

function inventory_transaction_status($total_stock_in, $total_stock_out = 0) {
    $total_stock_in = (int) $total_stock_in;
    $total_stock_out = (int) $total_stock_out;

    if ($total_stock_in <= 0 && $total_stock_out <= 0) {
        return '';
    }

    return ($total_stock_in - $total_stock_out) <= 0 ? 'Out of Stock' : 'In Stock';
}

function calculate_inventory_average_price($average_cost, $markup_percentage) {
    return round((float) $average_cost * (1 + ((float) $markup_percentage / 100)), 2);
}

function create_inventory_transaction_for_item($conn, $item_id, $capital, $stock_in, $stock_in_date) {
    return create_stock_in_transaction($conn, $item_id, $capital, $stock_in, $stock_in_date);
}

function create_stock_in_transaction($conn, $item_id, $capital, $stock_in, $stock_in_date) {
    ensure_inventory_transaction_table($conn);

    $query = mysqli_prepare(
        $conn,
        "INSERT INTO stock_in_transaction (item_id, capital, stock_in, stock_in_date)
         VALUES (?, ?, ?, ?)"
    );

    if (!$query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($query, "idis", $item_id, $capital, $stock_in, $stock_in_date);

    if (!mysqli_stmt_execute($query)) {
        $error = mysqli_stmt_error($query);
        mysqli_stmt_close($query);
        throw new Exception('Failed to add stock-in transaction: ' . $error);
    }

    mysqli_stmt_close($query);
}

function record_stock_out_transaction($conn, $item_id, $work_order_id, $quantity, $stock_out_date) {
    ensure_inventory_transaction_table($conn);

    $query = mysqli_prepare(
        $conn,
        "INSERT INTO stock_out_transaction (item_id, work_order_id, quantity, stock_out_date)
         VALUES (?, ?, ?, ?)"
    );

    if (!$query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($query, "iiis", $item_id, $work_order_id, $quantity, $stock_out_date);

    if (!mysqli_stmt_execute($query)) {
        $error = mysqli_stmt_error($query);
        mysqli_stmt_close($query);
        throw new Exception('Failed to add stock-out transaction: ' . $error);
    }

    mysqli_stmt_close($query);
}

function calculate_inventory_weighted_average($conn, $item_id) {
    ensure_inventory_transaction_table($conn);

    $item_id = (int) $item_id;

    if ($item_id <= 0) {
        return ['quantity' => 0, 'average_cost' => 0.0];
    }

    $query = mysqli_prepare(
        $conn,
        "SELECT movement_type, movement_date, movement_order, id, capital, quantity
         FROM (
            SELECT 'in' AS movement_type, stock_in_date AS movement_date, 1 AS movement_order, id, capital, stock_in AS quantity
            FROM stock_in_transaction
            WHERE item_id = ?
            UNION ALL
            SELECT 'out' AS movement_type, stock_out_date AS movement_date, 2 AS movement_order, id, 0 AS capital, quantity
            FROM stock_out_transaction
            WHERE item_id = ?
         ) movements
         ORDER BY movement_date ASC, movement_order ASC, id ASC"
    );

    if (!$query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($query, "ii", $item_id, $item_id);
    mysqli_stmt_execute($query);
    $result = mysqli_stmt_get_result($query);

    $quantity = 0;
    $inventory_value = 0.0;
    $average_cost = 0.0;

    while ($row = mysqli_fetch_assoc($result)) {
        $movement_quantity = max(0, (int) $row['quantity']);

        if ($row['movement_type'] === 'in') {
            $quantity += $movement_quantity;
            $inventory_value += max(0, (float) $row['capital']);
            $average_cost = $quantity > 0 ? $inventory_value / $quantity : 0.0;
            continue;
        }

        $deducted_quantity = min($movement_quantity, $quantity);
        $inventory_value -= $deducted_quantity * $average_cost;
        $quantity -= $deducted_quantity;

        if ($quantity <= 0) {
            $quantity = 0;
            $inventory_value = 0.0;
            $average_cost = 0.0;
        }
    }

    mysqli_stmt_close($query);

    return [
        'quantity' => $quantity,
        'average_cost' => $quantity > 0 ? round($inventory_value / $quantity, 2) : 0.0
    ];
}

function backfill_inventory_transactions_from_items($conn) {
    ensure_inventory_transaction_table($conn);

    return sync_all_items_from_inventory_transactions($conn);
}

function backfill_stock_out_transactions_from_purchased_items($conn) {
    ensure_stock_out_transaction_table($conn);

    $sql = "
        INSERT INTO stock_out_transaction (item_id, work_order_id, quantity, stock_out_date)
        SELECT pi.product_id, pi.work_order_id, pi.quantity,
            CASE
                WHEN pi.date IS NULL OR pi.date = '0000-00-00' THEN CURDATE()
                ELSE pi.date
            END
        FROM purchased_item pi
        WHERE pi.product_id IS NOT NULL
        AND pi.product_id > 0
        AND NOT EXISTS (
            SELECT 1
            FROM stock_out_transaction sot
            WHERE sot.item_id = pi.product_id
            AND sot.work_order_id = pi.work_order_id
            AND sot.quantity = pi.quantity
            AND sot.stock_out_date = pi.date
        )
    ";

    if (!mysqli_query($conn, $sql)) {
        throw new Exception('Failed to backfill stock-out transactions: ' . mysqli_error($conn));
    }
}

function reduce_stock_out_transaction($conn, $item_id, $work_order_id, $quantity) {
    $remaining = (int) $quantity;

    if ($remaining <= 0) {
        return;
    }

    $query = mysqli_prepare(
        $conn,
        "SELECT id, quantity
         FROM stock_out_transaction
         WHERE item_id = ? AND work_order_id = ?
         ORDER BY id DESC"
    );

    if (!$query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($query, "ii", $item_id, $work_order_id);
    mysqli_stmt_execute($query);
    $result = mysqli_stmt_get_result($query);

    while ($row = mysqli_fetch_assoc($result)) {
        if ($remaining <= 0) {
            break;
        }

        $transaction_id = (int) $row['id'];
        $transaction_quantity = (int) $row['quantity'];

        if ($transaction_quantity <= $remaining) {
            mysqli_query($conn, "DELETE FROM stock_out_transaction WHERE id = $transaction_id");
            $remaining -= $transaction_quantity;
        } else {
            $new_quantity = $transaction_quantity - $remaining;
            mysqli_query($conn, "UPDATE stock_out_transaction SET quantity = $new_quantity WHERE id = $transaction_id");
            $remaining = 0;
        }
    }

    mysqli_stmt_close($query);
}

function adjust_inventory_stock_out($conn, $item_id, $quantity_delta, $work_order_id = 0, $stock_out_date = null) {
    ensure_inventory_transaction_table($conn);

    $item_id = (int) $item_id;
    $quantity_delta = (int) $quantity_delta;
    $work_order_id = (int) $work_order_id;
    $stock_out_date = $stock_out_date ?: date('Y-m-d');

    if ($item_id <= 0 || $quantity_delta === 0) {
        return;
    }

    if ($quantity_delta > 0) {
        record_stock_out_transaction($conn, $item_id, $work_order_id, $quantity_delta, $stock_out_date);
    } else {
        reduce_stock_out_transaction($conn, $item_id, $work_order_id, abs($quantity_delta));
    }

    sync_item_from_inventory_transactions($conn, $item_id);
}

function sync_stock_out_transactions_for_work_order($conn, $work_order_id) {
    ensure_inventory_transaction_table($conn);

    $work_order_id = (int) $work_order_id;

    if ($work_order_id <= 0) {
        return;
    }

    $affected_items = [];

    $existing_query = mysqli_prepare($conn, "SELECT DISTINCT item_id FROM stock_out_transaction WHERE work_order_id = ?");
    if (!$existing_query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($existing_query, "i", $work_order_id);
    mysqli_stmt_execute($existing_query);
    $existing_result = mysqli_stmt_get_result($existing_query);

    while ($row = mysqli_fetch_assoc($existing_result)) {
        $affected_items[(int) $row['item_id']] = true;
    }

    mysqli_stmt_close($existing_query);

    $delete_query = mysqli_prepare($conn, "DELETE FROM stock_out_transaction WHERE work_order_id = ?");
    if (!$delete_query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($delete_query, "i", $work_order_id);
    if (!mysqli_stmt_execute($delete_query)) {
        $error = mysqli_stmt_error($delete_query);
        mysqli_stmt_close($delete_query);
        throw new Exception('Failed to reset work order stock-out records: ' . $error);
    }
    mysqli_stmt_close($delete_query);

    $insert_query = mysqli_prepare(
        $conn,
        "INSERT INTO stock_out_transaction (item_id, work_order_id, quantity, stock_out_date)
         SELECT product_id, work_order_id, SUM(quantity),
            CASE
                WHEN MIN(date) IS NULL OR MIN(date) = '0000-00-00' THEN CURDATE()
                ELSE MIN(date)
            END
         FROM purchased_item
         WHERE work_order_id = ?
         AND product_id IS NOT NULL
         AND product_id > 0
         GROUP BY product_id, work_order_id"
    );

    if (!$insert_query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($insert_query, "i", $work_order_id);
    if (!mysqli_stmt_execute($insert_query)) {
        $error = mysqli_stmt_error($insert_query);
        mysqli_stmt_close($insert_query);
        throw new Exception('Failed to rebuild work order stock-out records: ' . $error);
    }
    mysqli_stmt_close($insert_query);

    $new_query = mysqli_prepare($conn, "SELECT DISTINCT product_id FROM purchased_item WHERE work_order_id = ? AND product_id IS NOT NULL AND product_id > 0");
    if (!$new_query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($new_query, "i", $work_order_id);
    mysqli_stmt_execute($new_query);
    $new_result = mysqli_stmt_get_result($new_query);

    while ($row = mysqli_fetch_assoc($new_result)) {
        $affected_items[(int) $row['product_id']] = true;
    }

    mysqli_stmt_close($new_query);

    foreach (array_keys($affected_items) as $item_id) {
        if ($item_id > 0) {
            sync_item_from_inventory_transactions($conn, $item_id);
        }
    }
}

function sync_item_from_inventory_transactions($conn, $item_id, $markup_percentage = null, $average_price_override = null) {
    ensure_inventory_transaction_table($conn);
    ensure_items_inventory_columns($conn);

    $item_id = (int) $item_id;

    if ($item_id <= 0) {
        return;
    }

    $totals_query = mysqli_prepare(
        $conn,
        "SELECT
            COALESCE((SELECT SUM(stock_in) FROM stock_in_transaction WHERE item_id = ?), 0) AS total_stock_in,
            COALESCE((SELECT SUM(quantity) FROM stock_out_transaction WHERE item_id = ?), 0) AS total_stock_out"
    );

    if (!$totals_query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($totals_query, "ii", $item_id, $item_id);
    mysqli_stmt_execute($totals_query);
    $totals = mysqli_fetch_assoc(mysqli_stmt_get_result($totals_query));
    mysqli_stmt_close($totals_query);

    $total_stock_in = (int) ($totals['total_stock_in'] ?? 0);
    $total_stock_out = (int) ($totals['total_stock_out'] ?? 0);
    $status = inventory_transaction_status($total_stock_in, $total_stock_out);

    $upsert = mysqli_prepare(
        $conn,
        "INSERT INTO inventory_transaction (item_id, total_stock_in, total_stock_out, status)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            total_stock_in = VALUES(total_stock_in),
            total_stock_out = VALUES(total_stock_out),
            status = VALUES(status)"
    );

    if (!$upsert) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($upsert, "iiis", $item_id, $total_stock_in, $total_stock_out, $status);

    if (!mysqli_stmt_execute($upsert)) {
        $error = mysqli_stmt_error($upsert);
        mysqli_stmt_close($upsert);
        throw new Exception('Failed to sync inventory totals: ' . $error);
    }

    mysqli_stmt_close($upsert);

    $inventory = calculate_inventory_weighted_average($conn, $item_id);
    $current_quantity = max(0, (int) $inventory['quantity']);
    $average_cost = (float) $inventory['average_cost'];

    if ($markup_percentage === null) {
        $markup_query = mysqli_prepare($conn, "SELECT markup_percentage FROM items WHERE id = ? LIMIT 1");

        if (!$markup_query) {
            throw new Exception('Database error: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($markup_query, "i", $item_id);
        mysqli_stmt_execute($markup_query);
        $markup_row = mysqli_fetch_assoc(mysqli_stmt_get_result($markup_query));
        mysqli_stmt_close($markup_query);

        $markup_percentage = (float) ($markup_row['markup_percentage'] ?? 0);
    }

    $markup_percentage = (float) $markup_percentage;
    $average_price = $average_price_override !== null
        ? (float) $average_price_override
        : calculate_inventory_average_price($average_cost, $markup_percentage);

    $item_query = mysqli_prepare(
        $conn,
        "UPDATE items
         SET quantity = ?,
             markup_percentage = ?,
             average_price = ?,
             status = ?
         WHERE id = ?"
    );

    if (!$item_query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($item_query, "iddsi", $current_quantity, $markup_percentage, $average_price, $status, $item_id);

    if (!mysqli_stmt_execute($item_query)) {
        $error = mysqli_stmt_error($item_query);
        mysqli_stmt_close($item_query);
        throw new Exception('Failed to sync item inventory: ' . $error);
    }

    mysqli_stmt_close($item_query);
}

function sync_all_items_from_inventory_transactions($conn) {
    ensure_inventory_transaction_table($conn);
    ensure_items_inventory_columns($conn);

    $result = mysqli_query($conn, "SELECT id FROM items");

    if (!$result) {
        throw new Exception('Failed to fetch items: ' . mysqli_error($conn));
    }

    $count = 0;

    while ($row = mysqli_fetch_assoc($result)) {
        sync_item_from_inventory_transactions($conn, (int) $row['id']);
        $count++;
    }

    return $count;
}

function delete_inventory_records_for_item($conn, $item_id) {
    ensure_inventory_transaction_table($conn);
    $item_id = (int) $item_id;

    if ($item_id <= 0) {
        return;
    }

    mysqli_query($conn, "DELETE FROM stock_in_transaction WHERE item_id = $item_id");
    mysqli_query($conn, "DELETE FROM stock_out_transaction WHERE item_id = $item_id");
    mysqli_query($conn, "DELETE FROM inventory_transaction WHERE item_id = $item_id");
}

?>
