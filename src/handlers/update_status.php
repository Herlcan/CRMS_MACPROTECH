<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json');

require_once '../db/connection.php';
require_once '../../auth_check.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/activity_log_helper.php';
require_once __DIR__ . '/settings_helpers.php';
require_once __DIR__ . '/communication_helpers.php';
require_once __DIR__ . '/work_order_schema.php';

/**
 * Check if logged-in user can edit work order status
 */
function canEditStatus(mysqli $conn): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }

    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($role);
    $stmt->fetch();
    $stmt->close();

    return in_array($role, ['Administrator', 'Technician'], true);
}


function sendStatusUpdateSms(string $phone, string $name, string $workCode, string $status): array
{
    global $conn;

    try {
        return send_work_order_status_sms($conn, $phone, $name, $workCode, $status);
    } catch (Throwable $e) {
        error_log("Status SMS failed: " . $e->getMessage());
        return [
            'attempted' => false,
            'success' => false,
            'message' => 'SMS notification failed.'
        ];
    }
}


/**
 * MAIN REQUEST HANDLER (AJAX)
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update_status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if (!canEditStatus($conn)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if (!isset($_POST['id'], $_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
    exit;
}

$id = (int) $_POST['id'];
$status = trim($_POST['status']);

if ($status === 'Ready for Release') {
    $status = 'Repaired';
}

$allowedStatuses = [
    'Pending',
    'Diagnosing',
    'Waiting for Parts',
    'In Progress',
    'Repaired',
    'Released',
    'Cancelled'
];

if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit;
}

ensure_notifications_table($conn);
ensure_work_order_warranty_columns($conn);

$conn->begin_transaction();

try {

    /**
     * Get previous status + client info
     */
    $stmt = $conn->prepare("
        SELECT
            w.status,
            w.code,
            w.technician_id,
            CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
            c.email,
            c.contact_num
        FROM work_order w
        INNER JOIN client c ON w.client_id = c.id
        WHERE w.id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($previousStatus, $workCode, $technicianId, $clientName, $clientEmail, $clientContact);

    if (!$stmt->fetch()) {
        throw new Exception("Work order not found.");
    }

    $stmt->close();

    if ($status === 'Released') {
        $paymentStmt = $conn->prepare("
            SELECT COALESCE(payment_status, status) AS payment_status, COALESCE(remaining_balance, 0) AS remaining_balance
            FROM payments
            WHERE work_order_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        if (!$paymentStmt) {
            throw new Exception("Failed to validate payment status.");
        }

        $paymentStmt->bind_param("i", $id);
        $paymentStmt->execute();
        $paymentStmt->bind_result($paymentStatus, $remainingBalance);
        $paymentStmt->fetch();
        $paymentStmt->close();

        if (!in_array($paymentStatus, ['Paid', 'Partially Refunded'], true) || (float) $remainingBalance > 0.009) {
            throw new Exception("Work order can only be {$status} when the payment balance is fully settled.");
        }
    }

    /**
     * Update status
     */
    if (in_array($status, ['Repaired', 'Released'], true)) {
        $stmt = $conn->prepare("
            UPDATE work_order
            SET status = ?, completion_date = COALESCE(completion_date, CURDATE())
            WHERE id = ?
            AND COALESCE(CASE WHEN status = 'Ready for Release' THEN 'Repaired' ELSE status END, '') <> ?
        ");
    } else {
        $stmt = $conn->prepare("
            UPDATE work_order
            SET status = ?, completion_date = NULL
            WHERE id = ?
            AND COALESCE(CASE WHEN status = 'Ready for Release' THEN 'Repaired' ELSE status END, '') <> ?
        ");
    }

    if (!$stmt) {
        throw new Exception("Failed to prepare work order update.");
    }

    $stmt->bind_param("sis", $status, $id, $status);

    if (!$stmt->execute()) {
        throw new Exception("Failed to update work order.");
    }

    $affectedRows = $stmt->affected_rows;
    $stmt->close();


    /**
     * Activity Log (Audit Trail)
     */
    $userId = $_SESSION['user_id'];
    $previousDisplayStatus = communication_display_status((string) $previousStatus);
    $statusChanged = $affectedRows > 0;

    if ($statusChanged) {
        if ($status === 'Released') {
            activate_work_order_warranty($conn, $id);
        } else {
            clear_work_order_warranty_dates($conn, $id);
        }
    }

    if ($statusChanged) {
        $action = "Changed status from {$previousDisplayStatus} to {$status}";
        log_activity($conn, $action, $id, (int) $userId);
    }

    $statusSmsAllowed = should_send_work_order_status_sms($status);
    $smsResult = [
        'attempted' => false,
        'success' => false,
        'message' => $statusChanged && !$statusSmsAllowed
            ? 'Released status SMS is skipped. Send the digital receipt email to notify the customer by SMS.'
            : 'Status did not change.'
    ];
    $shouldSendSms = $statusChanged && $statusSmsAllowed;

    if ($statusChanged && $technicianId) {
        notify_work_order_updated($conn, (int) $technicianId, $workCode, "Status changed to {$status}.");
    }

    $conn->commit();

    if ($shouldSendSms) {
        $smsResult = sendStatusUpdateSms((string) $clientContact, (string) $clientName, (string) $workCode, $status);
    }

    echo json_encode([
        'success' => true,
        'new_status' => $status,
        'status_changed' => $statusChanged,
        'sms_notification' => $smsResult
    ]);

} catch (Throwable $e) {

    $conn->rollback();

    error_log("Status update error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() ?: 'Something went wrong. Please try again.'
    ]);
}

exit;
