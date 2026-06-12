<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';
require_once __DIR__ . '/item_schema.php';
require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/security_helpers.php';

header('Content-Type: application/json');

try {
    require_authenticated_json($conn);
    require_json_role(
        ['Administrator', 'Cashier/Front Desk', 'Cashier/Front Desk Staff'],
        'You are not allowed to search inventory items.'
    );
    ensure_items_inventory_columns($conn);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

// Check if search term is provided
if (!isset($_GET['q'])) {
    echo json_encode(['success' => false, 'error' => 'No search term provided']);
    exit;
}

$search_term = trim($_GET['q']);

// If search term is empty, return empty array
if (empty($search_term)) {
    echo json_encode([]);
    exit;
}

$like_search_term = '%' . $search_term . '%';

// Query to search items by code, product name (brand_name, model), description, or category
$query = mysqli_prepare($conn, "SELECT i.id, i.brand_name, i.model, i.description, i.average_price AS price, ic.category_name as category_name
          FROM items i
          LEFT JOIN item_category ic ON i.category_id = ic.id
          WHERE CAST(i.id AS CHAR) LIKE ?
          OR i.brand_name LIKE ? 
          OR i.model LIKE ? 
          OR i.description LIKE ?
          OR i.product_code LIKE ?
          OR ic.category_name LIKE ?
          LIMIT 10");

$query_params = [
    $like_search_term,
    $like_search_term,
    $like_search_term,
    $like_search_term,
    $like_search_term,
    $like_search_term
];
db_bind_params($query, "ssssss", $query_params);
mysqli_stmt_execute($query);
$result = mysqli_stmt_get_result($query);

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

mysqli_stmt_close($query);
echo json_encode($items);
?>
