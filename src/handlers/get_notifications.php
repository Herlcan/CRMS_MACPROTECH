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
$limit = isset($_GET['limit']) ? max(1, min(50, (int) $_GET['limit'])) : 5;
$includeArchived = isset($_GET['archived']) && $_GET['archived'] === '1';
$filter = $_GET['filter'] ?? 'active';

$where = "user_id = ?";
$types = "i";
$params = [$userId];

if ($includeArchived || $filter === 'archived') {
    $where .= " AND is_archived = 1";
} else {
    $where .= " AND is_archived = 0";
}

if ($filter === 'unread') {
    $where .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $where .= " AND is_read = 1";
}

$countQuery = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0 AND is_archived = 0");
mysqli_stmt_bind_param($countQuery, "i", $userId);
mysqli_stmt_execute($countQuery);
$countResult = mysqli_stmt_get_result($countQuery);
$unreadCount = (int) (mysqli_fetch_assoc($countResult)['total'] ?? 0);
mysqli_stmt_close($countQuery);

$sql = "
    SELECT id, title, message, type, link, is_read, is_archived, created_at
    FROM notifications
    WHERE {$where}
    ORDER BY created_at DESC, id DESC
    LIMIT ?
";
$types .= "i";
$params[] = $limit;

$query = mysqli_prepare($conn, $sql);
notification_bind_params($query, $types, $params);
mysqli_stmt_execute($query);
$result = mysqli_stmt_get_result($query);

$notifications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['id'] = (int) $row['id'];
    $row['is_read'] = (int) $row['is_read'];
    $row['is_archived'] = (int) $row['is_archived'];
    $row['time_ago'] = notification_time_ago((string) $row['created_at']);
    $notifications[] = $row;
}

mysqli_stmt_close($query);

echo json_encode([
    'success' => true,
    'unread_count' => $unreadCount,
    'notifications' => $notifications
]);
?>
