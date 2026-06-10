<?php

require_once __DIR__ . '/notification_schema.php';
require_once __DIR__ . '/work_order_schema.php';

function notification_valid_type(string $type): string
{
    $allowed = ['info', 'success', 'warning', 'critical'];
    return in_array($type, $allowed, true) ? $type : 'info';
}

function notification_bind_params(mysqli_stmt $stmt, string $types, array &$params): bool
{
    $references = [];
    foreach ($params as $key => &$value) {
        $references[$key] = &$value;
    }

    return mysqli_stmt_bind_param($stmt, $types, ...$references);
}

function create_notification(
    mysqli $conn,
    int $userId,
    string $title,
    string $message,
    string $type = 'info',
    string $link = ''
): bool {
    ensure_notifications_table($conn);

    if ($userId <= 0 || trim($title) === '') {
        return false;
    }

    $type = notification_valid_type($type);
    $query = mysqli_prepare($conn, "
        INSERT INTO notifications (user_id, title, message, type, link)
        VALUES (?, ?, ?, ?, ?)
    ");

    if (!$query) {
        error_log('Notification prepare failed: ' . mysqli_error($conn));
        return false;
    }

    mysqli_stmt_bind_param($query, "issss", $userId, $title, $message, $type, $link);
    $ok = mysqli_stmt_execute($query);

    if (!$ok) {
        error_log('Notification insert failed: ' . mysqli_stmt_error($query));
    }

    mysqli_stmt_close($query);
    return $ok;
}

function get_user_ids_by_roles(mysqli $conn, array $roles): array
{
    if (empty($roles)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $types = str_repeat('s', count($roles));
    $query = mysqli_prepare($conn, "SELECT id FROM users WHERE role IN ($placeholders)");

    if (!$query) {
        error_log('Notification role lookup failed: ' . mysqli_error($conn));
        return [];
    }

    notification_bind_params($query, $types, $roles);
    mysqli_stmt_execute($query);
    $result = mysqli_stmt_get_result($query);

    $ids = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $ids[] = (int) $row['id'];
    }

    mysqli_stmt_close($query);
    return array_values(array_unique($ids));
}

function notify_users_by_roles(
    mysqli $conn,
    array $roles,
    string $title,
    string $message,
    string $type = 'info',
    string $link = '',
    ?int $excludeUserId = null
): void {
    foreach (get_user_ids_by_roles($conn, $roles) as $userId) {
        if ($excludeUserId !== null && $userId === $excludeUserId) {
            continue;
        }

        create_notification($conn, $userId, $title, $message, $type, $link);
    }
}

function notify_work_order_assigned(mysqli $conn, ?int $technicianId, string $workCode, int $workOrderId): void
{
    if (!$technicianId) {
        return;
    }

    create_notification(
        $conn,
        $technicianId,
        'New Work Order Assigned',
        "You were assigned to {$workCode}.",
        'info',
        'work-order.php?search=' . urlencode($workCode)
    );
}

function notify_work_order_updated(mysqli $conn, ?int $technicianId, string $workCode, string $message): void
{
    if (!$technicianId) {
        return;
    }

    create_notification(
        $conn,
        $technicianId,
        'Work Order Updated',
        "{$workCode}: {$message}",
        'info',
        'work-order.php?search=' . urlencode($workCode)
    );
}

function notify_low_stock_for_item(mysqli $conn, int $itemId, int $threshold = 10): void
{
    $query = mysqli_prepare($conn, "
        SELECT product_code, brand_name, model, quantity
        FROM items
        WHERE id = ?
        LIMIT 1
    ");

    if (!$query) {
        return;
    }

    mysqli_stmt_bind_param($query, "i", $itemId);
    mysqli_stmt_execute($query);
    $result = mysqli_stmt_get_result($query);
    $item = mysqli_fetch_assoc($result);
    mysqli_stmt_close($query);

    if (!$item) {
        return;
    }

    $quantity = (int) $item['quantity'];
    if ($quantity >= $threshold) {
        return;
    }

    $name = trim(($item['brand_name'] ?? '') . ' ' . ($item['model'] ?? ''));
    $name = $name !== '' ? $name : ($item['product_code'] ?? 'Product item');
    $type = $quantity <= 0 ? 'critical' : 'warning';
    $title = $quantity <= 0 ? 'Out of Stock' : 'Inventory Low Stock';
    $message = $quantity <= 0
        ? "{$name} is out of stock."
        : "{$name} remaining stock: {$quantity}.";

    notify_users_by_roles(
        $conn,
        ['Administrator', 'Cashier/Front Desk', 'Cashier/Front Desk Staff'],
        $title,
        $message,
        $type,
        'items.php?search=' . urlencode((string) ($item['product_code'] ?? $name))
    );
}

function notify_expired_work_order_warranties(mysqli $conn, int $limit = 25): int
{
    ensure_notifications_table($conn);
    ensure_work_order_warranty_columns($conn);

    $limit = max(1, min(100, $limit));
    $query = mysqli_prepare($conn, "
        SELECT
            w.id,
            w.code,
            w.warranty_expiration_date,
            CONCAT(c.first_name, ' ', c.last_name) AS customer_name
        FROM work_order w
        LEFT JOIN client c ON c.id = w.client_id
        WHERE w.status = 'Released'
        AND w.warranty_days > 0
        AND w.warranty_expiration_date IS NOT NULL
        AND w.warranty_expiration_date < CURDATE()
        AND w.warranty_expiration_notified_at IS NULL
        ORDER BY w.warranty_expiration_date ASC, w.id ASC
        LIMIT ?
    ");

    if (!$query) {
        error_log('Expired warranty lookup failed: ' . mysqli_error($conn));
        return 0;
    }

    mysqli_stmt_bind_param($query, "i", $limit);
    mysqli_stmt_execute($query);
    $result = mysqli_stmt_get_result($query);
    $expired = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($query);

    $notified = 0;
    foreach ($expired as $workOrder) {
        $workOrderId = (int) ($workOrder['id'] ?? 0);
        if ($workOrderId <= 0) {
            continue;
        }

        $claimQuery = mysqli_prepare($conn, "
            UPDATE work_order
            SET warranty_expiration_notified_at = NOW()
            WHERE id = ?
            AND warranty_expiration_notified_at IS NULL
        ");

        if (!$claimQuery) {
            error_log('Expired warranty claim prepare failed: ' . mysqli_error($conn));
            continue;
        }

        mysqli_stmt_bind_param($claimQuery, "i", $workOrderId);
        mysqli_stmt_execute($claimQuery);
        $claimed = mysqli_stmt_affected_rows($claimQuery) > 0;
        mysqli_stmt_close($claimQuery);

        if (!$claimed) {
            continue;
        }

        $workCode = (string) ($workOrder['code'] ?? ('WO-' . sprintf('%04d', $workOrderId)));
        $customerName = trim((string) ($workOrder['customer_name'] ?? ''));
        $customerLabel = $customerName !== '' ? " for {$customerName}" : '';
        $expirationDate = (string) ($workOrder['warranty_expiration_date'] ?? '');

        notify_users_by_roles(
            $conn,
            ['Administrator', 'Cashier/Front Desk', 'Cashier/Front Desk Staff'],
            'Repair Warranty Expired',
            "{$workCode}{$customerLabel} warranty expired on {$expirationDate}.",
            'warning',
            'work-order.php?search=' . urlencode($workCode)
        );

        $notified++;
    }

    return $notified;
}

function notification_time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return '';
    }

    $seconds = max(1, time() - $timestamp);
    $units = [
        'year' => 31536000,
        'month' => 2592000,
        'week' => 604800,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60
    ];

    foreach ($units as $label => $length) {
        if ($seconds >= $length) {
            $value = (int) floor($seconds / $length);
            return $value . ' ' . $label . ($value > 1 ? 's' : '') . ' ago';
        }
    }

    return 'Just now';
}
?>
