<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

require_once '../db/connection.php';
require_once 'notification_helpers.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

ensure_notifications_table($conn);

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? 'read';
$notificationId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($action === 'read_all') {
    $query = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_archived = 0");
    mysqli_stmt_bind_param($query, "i", $userId);
} elseif ($action === 'archive' && $notificationId > 0) {
    $query = mysqli_prepare($conn, "UPDATE notifications SET is_archived = 1, is_read = 1 WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($query, "ii", $notificationId, $userId);
} elseif ($action === 'restore' && $notificationId > 0) {
    $query = mysqli_prepare($conn, "UPDATE notifications SET is_archived = 0 WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($query, "ii", $notificationId, $userId);
} elseif ($notificationId > 0) {
    $query = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($query, "ii", $notificationId, $userId);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid notification request']);
    exit;
}

$success = mysqli_stmt_execute($query);
mysqli_stmt_close($query);

echo json_encode([
    'success' => $success,
    'message' => $success ? 'Notification updated' : 'Failed to update notification'
]);
?>
