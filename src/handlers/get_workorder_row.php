<?php
session_start();
require_once '../db/connection.php';
require_once __DIR__ . '/security_helpers.php';

require_authenticated_fragment($conn);

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

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $conn->prepare("
    SELECT *
    FROM work_order
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$wo = $result->fetch_assoc()) {
    exit;
}

$canEdit = canEditStatus($conn);
if (
    !$canEdit
    || (
        user_has_role('Technician')
        && (int) ($wo['technician_id'] ?? 0) !== (int) ($_SESSION['user_id'] ?? 0)
    )
) {
    http_response_code(403);
    exit;
}

include '../partials/workorder_row_template.php';
