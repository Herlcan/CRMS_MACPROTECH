<?php
session_start();
require_once '../db/connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'success' => false]);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid work order ID', 'success' => false]);
    exit;
}

// Fetch work order
$stmt = $conn->prepare("SELECT * FROM work_order WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $conn->error, 'success' => false]);
    exit;
}

$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Query execution error: ' . $stmt->error, 'success' => false]);
    exit;
}

$result = $stmt->get_result();
$wo = $result->fetch_assoc();
$stmt->close();

if (!$wo) {
    http_response_code(404);
    echo json_encode(['error' => 'Work order not found', 'success' => false]);
    exit;
}

// Initialize arrays for optional data
$purchased = [];
$clientParts = [];
$payments = [];

// Try to fetch purchased parts if table exists
$check = $conn->query("SHOW TABLES LIKE 'purchased_parts'");
if ($check && $check->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT p.*, i.product_name, i.selling_price as product_price
        FROM purchased_parts p
        LEFT JOIN items i ON p.item_id = i.id
        WHERE p.work_order_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $purchased = $result->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();
    }
}

// Try to fetch client provided parts if table exists
$check = $conn->query("SHOW TABLES LIKE 'client_provided_parts'");
if ($check && $check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT * FROM client_provided_parts WHERE work_order_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $clientParts = $result->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();
    }
}

// Try to fetch payments if table exists
$check = $conn->query("SHOW TABLES LIKE 'payments'");
if ($check && $check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT * FROM payments WHERE work_order_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payments = $result->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();
    }
}

echo json_encode([
    'workOrder' => $wo,
    'purchasedParts' => $purchased,
    'clientParts' => $clientParts,
    'payments' => $payments,
    'success' => true
]);
