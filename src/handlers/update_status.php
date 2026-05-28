<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json');

require_once '../db/connection.php';
require_once '../../auth_check.php';
require_once 'config.php';

require_once __DIR__ . '/../../vendor/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../../vendor/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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


/**
 * Send completion email
 */
function sendCompletionEmail(string $email, string $name, string $workCode): void
{
    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) SMTP_PORT;

        $mail->setFrom(SMTP_USER, 'Macprotech Computer Repair');
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = "Your Device is Ready for Pickup";

        $mail->Body = "
            <h2>Repair Completed</h2>
            <p>Dear <strong>{$name}</strong>,</p>
            <p>Your device with Work Order Code 
            <strong>{$workCode}</strong> has been successfully repaired 
            and is now ready for pickup.</p>
            <p>Please visit our shop during business hours.</p>
            <br>
            <p>Thank you for trusting Macprotech Computer Repair Services.</p>
        ";

        $mail->send();

    } catch (Exception $e) {
        error_log("Email send failed: " . $e->getMessage());
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

$allowedStatuses = [
    'Pending',
    'Diagnosing',
    'Waiting for Parts',
    'In Progress',
    'Repaired',
    'Ready for Release',
    'Released',
    'Cancelled'
];

if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit;
}

$conn->begin_transaction();

try {

    /**
     * Get previous status + client info
     */
    $stmt = $conn->prepare("
        SELECT w.status, w.code, c.first_name, c.email
        FROM work_order w
        INNER JOIN client c ON w.client_id = c.id
        WHERE w.id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($previousStatus, $workCode, $clientName, $clientEmail);

    if (!$stmt->fetch()) {
        throw new Exception("Work order not found.");
    }

    $stmt->close();

    if (in_array($status, ['Ready for Release', 'Released'], true)) {
        $paymentStmt = $conn->prepare("
            SELECT COALESCE(payment_status, status) AS payment_status
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
        $paymentStmt->bind_result($paymentStatus);
        $paymentStmt->fetch();
        $paymentStmt->close();

        if ($paymentStatus !== 'Paid') {
            throw new Exception("Work order can only be {$status} when payment status is Paid.");
        }
    }

    /**
     * Update status
     */
    if (in_array($status, ['Repaired', 'Ready for Release', 'Released'], true)) {
        $stmt = $conn->prepare("
            UPDATE work_order
            SET status = ?, completion_date = COALESCE(completion_date, CURDATE())
            WHERE id = ?
        ");
    } else {
        $stmt = $conn->prepare("
            UPDATE work_order
            SET status = ?, completion_date = NULL
            WHERE id = ?
        ");
    }

    $stmt->bind_param("si", $status, $id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to update work order.");
    }

    $stmt->close();


    /**
     * Activity Log (Audit Trail)
     */
    $userId = $_SESSION['user_id'];

    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, work_order_id, action)
        VALUES (?, ?, ?)
    ");

    $action = "Changed status from {$previousStatus} to {$status}";
    $logStmt->bind_param("iis", $userId, $id, $action);
    $logStmt->execute();
    $logStmt->close();


    /**
     * Send email if changed to Repaired
     */
    if ($status === 'Repaired' && $previousStatus !== 'Repaired') {
        sendCompletionEmail($clientEmail, $clientName, $workCode);
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'new_status' => $status
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
