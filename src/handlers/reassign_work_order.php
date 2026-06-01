<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json');

require_once '../db/connection.php';
require_once '../../auth_check.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/work_order_assignment_schema.php';

function current_user_role(mysqli $conn): string
{
    if (empty($_SESSION['user_id'])) {
        return '';
    }

    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($role);
    $stmt->fetch();
    $stmt->close();

    return (string) $role;
}

function can_reassign_work_order(mysqli $conn): bool
{
    return in_array(current_user_role($conn), ['Administrator', 'Cashier/Front Desk', 'Cashier/Front Desk Staff'], true);
}

function user_full_name(?string $firstName, ?string $lastName): string
{
    $name = trim((string) $firstName . ' ' . (string) $lastName);
    return $name !== '' ? $name : 'Unassigned';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if (!can_reassign_work_order($conn)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not allowed to reassign work orders.']);
    exit;
}

$workOrderId = isset($_POST['work_order_id']) ? (int) $_POST['work_order_id'] : 0;
$newTechnicianId = isset($_POST['new_technician_id']) ? (int) $_POST['new_technician_id'] : 0;
$reasonCategory = isset($_POST['reason_category']) ? trim((string) $_POST['reason_category']) : '';
$reasonDetails = isset($_POST['reason_details']) ? trim((string) $_POST['reason_details']) : '';

if ($workOrderId <= 0 || $newTechnicianId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a work order and a new technician.']);
    exit;
}

$allowedReasons = [
    'Sick Leave',
    'Workload Balancing',
    'Expertise Required',
    'Scheduling Conflict',
    'Resigned',
    'Emergency Redistribution',
    'Other'
];

if (!in_array($reasonCategory, $allowedReasons, true)) {
    echo json_encode(['success' => false, 'message' => 'Please select a reassignment reason.']);
    exit;
}

if ($reasonCategory === 'Other' && $reasonDetails === '') {
    echo json_encode(['success' => false, 'message' => 'Please enter the custom reassignment reason.']);
    exit;
}

$reason = $reasonCategory;
if ($reasonDetails !== '') {
    $reason .= ': ' . $reasonDetails;
}

ensure_notifications_table($conn);
ensure_work_order_assignments_table($conn);

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        SELECT
            wo.id,
            wo.code,
            wo.status,
            wo.technician_id,
            wo.unit_type,
            wo.brand,
            wo.model,
            c.first_name AS client_first_name,
            c.last_name AS client_last_name,
            old_user.first_name AS old_first_name,
            old_user.last_name AS old_last_name
        FROM work_order wo
        INNER JOIN client c ON wo.client_id = c.id
        LEFT JOIN users old_user ON wo.technician_id = old_user.id
        WHERE wo.id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('Failed to load work order.');
    }

    $stmt->bind_param("i", $workOrderId);
    $stmt->execute();
    $workOrder = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$workOrder) {
        throw new Exception('Work order not found.');
    }

    $blockedStatuses = ['Completed', 'Repaired', 'Ready for Release', 'Released', 'Cancelled'];
    if (in_array((string) $workOrder['status'], $blockedStatuses, true)) {
        throw new Exception('This work order can no longer be reassigned.');
    }

    $techStmt = $conn->prepare("
        SELECT id, first_name, last_name
        FROM users
        WHERE id = ?
        AND role IN ('Technician', 'Administrator')
        LIMIT 1
    ");

    if (!$techStmt) {
        throw new Exception('Failed to validate technician.');
    }

    $techStmt->bind_param("i", $newTechnicianId);
    $techStmt->execute();
    $newTechnician = $techStmt->get_result()->fetch_assoc();
    $techStmt->close();

    if (!$newTechnician) {
        throw new Exception('Selected technician was not found.');
    }

    $oldTechnicianId = !empty($workOrder['technician_id']) ? (int) $workOrder['technician_id'] : null;
    if ($oldTechnicianId === $newTechnicianId) {
        throw new Exception('Please choose a different technician.');
    }

    $updateStmt = $conn->prepare("UPDATE work_order SET technician_id = ? WHERE id = ?");
    if (!$updateStmt) {
        throw new Exception('Failed to prepare reassignment.');
    }

    $updateStmt->bind_param("ii", $newTechnicianId, $workOrderId);
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update work order technician.');
    }
    $updateStmt->close();

    $changedBy = (int) $_SESSION['user_id'];
    record_work_order_assignment($conn, $workOrderId, $oldTechnicianId, $newTechnicianId, $changedBy, $reason);

    $actorStmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1");
    $actorStmt->bind_param("i", $changedBy);
    $actorStmt->execute();
    $actor = $actorStmt->get_result()->fetch_assoc();
    $actorStmt->close();

    $oldName = user_full_name($workOrder['old_first_name'] ?? null, $workOrder['old_last_name'] ?? null);
    $newName = user_full_name($newTechnician['first_name'] ?? null, $newTechnician['last_name'] ?? null);
    $actorName = user_full_name($actor['first_name'] ?? null, $actor['last_name'] ?? null);
    $workCode = (string) ($workOrder['code'] ?: ('WO-' . sprintf('%04d', $workOrderId)));
    $clientName = user_full_name($workOrder['client_first_name'] ?? null, $workOrder['client_last_name'] ?? null);
    $device = trim((string) $workOrder['unit_type'] . ' ' . (string) $workOrder['brand'] . ' ' . (string) $workOrder['model']);

    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, work_order_id, action)
        VALUES (?, ?, ?)
    ");

    if ($logStmt) {
        $action = "Reassigned technician from {$oldName} to {$newName}. Reason: {$reason}";
        $logStmt->bind_param("iis", $changedBy, $workOrderId, $action);
        $logStmt->execute();
        $logStmt->close();
    }

    create_notification(
        $conn,
        $newTechnicianId,
        'Work Order Assigned',
        "You have been assigned {$workCode}. Customer: {$clientName}. Device: {$device}.",
        'info',
        'work-order.php?search=' . urlencode($workCode)
    );

    if ($oldTechnicianId) {
        create_notification(
            $conn,
            $oldTechnicianId,
            'Work Order Reassigned',
            "{$workCode} has been reassigned to {$newName} by {$actorName}. Reason: {$reason}.",
            'warning',
            'work-order.php?search=' . urlencode($workCode)
        );
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "{$workCode} reassigned to {$newName}.",
        'newTechnicianName' => $newName
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Work order reassignment error: ' . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() ?: 'Reassignment failed.'
    ]);
}

exit;
?>
