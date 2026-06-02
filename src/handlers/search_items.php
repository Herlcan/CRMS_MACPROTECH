<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';
require_once __DIR__ . '/item_schema.php';

header('Content-Type: application/json');

try {
    ensure_items_inventory_columns($conn);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Check if search term is provided
if (!isset($_GET['q'])) {
    echo json_encode(['error' => 'No search term provided']);
    exit;
}

$search_term = trim($_GET['q']);

// If search term is empty, return empty array
if (empty($search_term)) {
    echo json_encode([]);
    exit;
}

// Sanitize search term
$search_term = mysqli_real_escape_string($conn, $search_term);

// Query to search items by code, product name (brand_name, model), description, or category
$query = "SELECT i.id, i.brand_name, i.model, i.description, i.average_price AS price, ic.category_name as category_name
          FROM items i
          LEFT JOIN item_category ic ON i.category_id = ic.id
          WHERE i.id LIKE '%$search_term%'
          OR i.brand_name LIKE '%$search_term%' 
          OR i.model LIKE '%$search_term%' 
          OR i.description LIKE '%$search_term%'
          OR i.product_code LIKE '%$search_term%'
          OR ic.category_name LIKE '%$search_term%'
          LIMIT 10";

$result = mysqli_query($conn, $query);

$items = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $product_name = $row['brand_name'] . ' ' . $row['model'];
        $items[] = [
            'id' => $row['id'],
            'brand_name' => $row['brand_name'],
            'model' => $row['model'],
            'description' => $row['description'],
            'price' => $row['price'],
            'category_name' => $row['category_name'],
            'product_name' => $product_name
        ];
    }
}

echo json_encode($items);
?>
