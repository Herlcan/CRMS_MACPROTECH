<?php

function ordered_part_column_exists(mysqli $conn, string $table, string $column): bool
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

function ensure_ordered_parts_table(mysqli $conn): void
{
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS ordered_parts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            work_order_id INT NOT NULL,
            part_name VARCHAR(255) NOT NULL,
            brand VARCHAR(100) NULL,
            category VARCHAR(100) NULL,
            description TEXT NULL,
            quantity INT NOT NULL DEFAULT 1,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ordered_parts_work_order (work_order_id)
        )
    ");

    $columns = [
        'work_order_id' => "ALTER TABLE ordered_parts ADD COLUMN work_order_id INT NOT NULL AFTER id",
        'part_name' => "ALTER TABLE ordered_parts ADD COLUMN part_name VARCHAR(255) NOT NULL AFTER work_order_id",
        'brand' => "ALTER TABLE ordered_parts ADD COLUMN brand VARCHAR(100) NULL AFTER part_name",
        'category' => "ALTER TABLE ordered_parts ADD COLUMN category VARCHAR(100) NULL AFTER brand",
        'description' => "ALTER TABLE ordered_parts ADD COLUMN description TEXT NULL AFTER category",
        'quantity' => "ALTER TABLE ordered_parts ADD COLUMN quantity INT NOT NULL DEFAULT 1 AFTER description",
        'price' => "ALTER TABLE ordered_parts ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER quantity",
        'created_at' => "ALTER TABLE ordered_parts ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER price"
    ];

    foreach ($columns as $column => $sql) {
        if (!ordered_part_column_exists($conn, 'ordered_parts', $column)) {
            mysqli_query($conn, $sql);
        }
    }
}

function save_ordered_parts_from_post(mysqli $conn, int $workOrderId): float
{
    ensure_ordered_parts_table($conn);

    if (!isset($_POST['ordered_part_name']) || !is_array($_POST['ordered_part_name'])) {
        return 0.0;
    }

    $names = $_POST['ordered_part_name'];
    $brands = isset($_POST['ordered_part_brand']) && is_array($_POST['ordered_part_brand']) ? $_POST['ordered_part_brand'] : [];
    $categories = isset($_POST['ordered_part_category']) && is_array($_POST['ordered_part_category']) ? $_POST['ordered_part_category'] : [];
    $descriptions = isset($_POST['ordered_part_description']) && is_array($_POST['ordered_part_description']) ? $_POST['ordered_part_description'] : [];
    $quantities = isset($_POST['ordered_part_quantity']) && is_array($_POST['ordered_part_quantity']) ? $_POST['ordered_part_quantity'] : [];
    $prices = isset($_POST['ordered_part_price']) && is_array($_POST['ordered_part_price']) ? $_POST['ordered_part_price'] : [];
    $orderedPartsTotal = 0.0;

    for ($i = 0; $i < count($names); $i++) {
        $partName = trim((string) $names[$i]);
        $brand = isset($brands[$i]) ? trim((string) $brands[$i]) : '';
        $category = isset($categories[$i]) ? trim((string) $categories[$i]) : '';
        $description = isset($descriptions[$i]) ? trim((string) $descriptions[$i]) : '';
        $quantity = isset($quantities[$i]) ? max(1, (int) $quantities[$i]) : 1;
        $price = isset($prices[$i]) ? max(0, (float) $prices[$i]) : 0.0;

        if ($partName === '') {
            continue;
        }

        $insert = mysqli_prepare($conn, "
            INSERT INTO ordered_parts
                (work_order_id, part_name, brand, category, description, quantity, price)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$insert) {
            throw new Exception('Database error: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($insert, "issssid", $workOrderId, $partName, $brand, $category, $description, $quantity, $price);
        if (!mysqli_stmt_execute($insert)) {
            throw new Exception('Failed to add ordered part.');
        }
        mysqli_stmt_close($insert);

        $orderedPartsTotal += $quantity * $price;
    }

    return $orderedPartsTotal;
}
?>
