<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: application/json');

include '../db/connection.php';
include '../../auth_check.php';
require_once __DIR__ . '/work_order_assignment_schema.php';

$response = [
    'success' => false,
    'message' => 'Unknown error',
    'workOrder' => null,
    'purchasedParts' => [],
    'clientParts' => [],
    'payments' => [],
    'technicians' => [],
    'assignmentHistory' => [],
    'activityTimeline' => [],
    'canReassign' => false
];

function work_order_user_role(mysqli $conn): string
{
    if (empty($_SESSION['user_id'])) {
        return '';
    }

    $query = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ? LIMIT 1");
    if (!$query) {
        return '';
    }

    mysqli_stmt_bind_param($query, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($query);
    mysqli_stmt_bind_result($query, $role);
    mysqli_stmt_fetch($query);
    mysqli_stmt_close($query);

    return (string) $role;
}

function work_order_can_reassign(mysqli $conn): bool
{
    return in_array(work_order_user_role($conn), ['Administrator', 'Cashier/Front Desk', 'Cashier/Front Desk Staff'], true);
}

try {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Work order ID is required');
    }

    $work_order_id = intval($_GET['id']);

    if ($work_order_id <= 0) {
        throw new Exception('Invalid work order ID');
    }

    ensure_work_order_assignments_table($conn);

    // Fetch work order with technician and customer names.
    $query = mysqli_prepare($conn, "
        SELECT
            wo.*,
            CONCAT(u.first_name, ' ', u.last_name) AS technician_name,
            CONCAT(c.first_name, ' ', c.last_name) AS customer_name
        FROM work_order wo
        LEFT JOIN users u ON wo.technician_id = u.id
        LEFT JOIN client c ON wo.client_id = c.id
        WHERE wo.id = ?
    ");
    if (!$query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($query, "i", $work_order_id);
    if (!mysqli_stmt_execute($query)) {
        throw new Exception('Failed to fetch work order: ' . mysqli_stmt_error($query));
    }

    $result = mysqli_stmt_get_result($query);
    $work_order = mysqli_fetch_assoc($result);
    mysqli_stmt_close($query);

    if (!$work_order) {
        throw new Exception('Work order not found');
    }

    $cancelled_from_status = null;
    if (($work_order['status'] ?? '') === 'Cancelled') {
        $cancelLogQuery = mysqli_prepare($conn, "
            SELECT action
            FROM activity_logs
            WHERE work_order_id = ?
            AND action LIKE '% to Cancelled'
            ORDER BY id DESC
            LIMIT 1
        ");

        if ($cancelLogQuery) {
            mysqli_stmt_bind_param($cancelLogQuery, "i", $work_order_id);
            if (mysqli_stmt_execute($cancelLogQuery)) {
                $cancelLogResult = mysqli_stmt_get_result($cancelLogQuery);
                $cancelLog = mysqli_fetch_assoc($cancelLogResult);

                if ($cancelLog && preg_match('/Changed status from (.+) to Cancelled$/', $cancelLog['action'], $matches)) {
                    $cancelled_from_status = $matches[1];
                }
            }
            mysqli_stmt_close($cancelLogQuery);
        }
    }

    $payments = [];
    // Fetch payments if table exists
    $checkPayments = mysqli_query($conn, "SHOW TABLES LIKE 'payments'");
    if ($checkPayments && mysqli_num_rows($checkPayments) > 0) {
        $paymentsQuery = mysqli_prepare($conn, "
            SELECT * FROM payments
            WHERE work_order_id = ?
            ORDER BY date ASC
        ");
        if ($paymentsQuery) {
            mysqli_stmt_bind_param($paymentsQuery, "i", $work_order_id);
            if (mysqli_stmt_execute($paymentsQuery)) {
                $paymentsResult = mysqli_stmt_get_result($paymentsQuery);
                $payments = mysqli_fetch_all($paymentsResult, MYSQLI_ASSOC);
            }
            mysqli_stmt_close($paymentsQuery);
        }
    }

    $technicians = [];
    $techniciansQuery = mysqli_query($conn, "
        SELECT id, first_name, last_name, role
        FROM users
        WHERE role IN ('Technician', 'Administrator')
        ORDER BY last_name, first_name
    ");

    if ($techniciansQuery) {
        while ($technician = mysqli_fetch_assoc($techniciansQuery)) {
            $technicians[] = $technician;
        }
    }

    $assignment_history = [];
    $assignmentQuery = mysqli_prepare($conn, "
        SELECT
            woa.*,
            CONCAT(tech.first_name, ' ', tech.last_name) AS technician_name,
            CONCAT(assigner.first_name, ' ', assigner.last_name) AS assigned_by_name
        FROM work_order_assignments woa
        LEFT JOIN users tech ON woa.technician_id = tech.id
        LEFT JOIN users assigner ON woa.assigned_by = assigner.id
        WHERE woa.work_order_id = ?
        ORDER BY woa.assigned_at ASC, woa.id ASC
    ");

    if ($assignmentQuery) {
        mysqli_stmt_bind_param($assignmentQuery, "i", $work_order_id);
        if (mysqli_stmt_execute($assignmentQuery)) {
            $assignmentResult = mysqli_stmt_get_result($assignmentQuery);
            $assignment_history = mysqli_fetch_all($assignmentResult, MYSQLI_ASSOC);
        }
        mysqli_stmt_close($assignmentQuery);
    }

    $activity_timeline = [];
    if (empty($assignment_history) && !empty($work_order['technician_id'])) {
        $activity_timeline[] = [
            'date' => $work_order['request_date'] ?? '',
            'title' => 'Assigned to ' . ($work_order['technician_name'] ?: 'Unassigned'),
            'details' => '',
            'type' => 'assignment'
        ];
    }

    foreach ($assignment_history as $index => $assignment) {
        $date = $assignment['assigned_at'] ?? null;
        if (!$date) {
            continue;
        }

        $activity_timeline[] = [
            'date' => $date,
            'title' => ($index === 0 ? 'Assigned to ' : 'Reassigned to ') . ($assignment['technician_name'] ?: 'Unassigned'),
            'details' => $assignment['reason'] ?: '',
            'type' => $index === 0 ? 'assignment' : 'reassignment'
        ];
    }

    $activityQuery = mysqli_prepare($conn, "
        SELECT action, created_at
        FROM activity_logs
        WHERE work_order_id = ?
        AND action NOT LIKE 'Reassigned technician%'
        ORDER BY created_at ASC, id ASC
    ");

    if ($activityQuery) {
        mysqli_stmt_bind_param($activityQuery, "i", $work_order_id);
        if (mysqli_stmt_execute($activityQuery)) {
            $activityResult = mysqli_stmt_get_result($activityQuery);
            while ($activity = mysqli_fetch_assoc($activityResult)) {
                $activity_timeline[] = [
                    'date' => $activity['created_at'],
                    'title' => $activity['action'],
                    'details' => '',
                    'type' => 'activity'
                ];
            }
        }
        mysqli_stmt_close($activityQuery);
    }

    usort($activity_timeline, function ($a, $b) {
        return strcmp((string) $a['date'], (string) $b['date']);
    });

    // Fetch purchased parts (returns empty array if no parts exist)
    $purchased_parts = [];
    $purchased_query = mysqli_prepare($conn, "
        SELECT pi.*, COALESCE(i.brand_name, 'Unknown Item') as product_name, COALESCE(i.price, 0) as product_price
        FROM purchased_item pi
        LEFT JOIN items i ON pi.product_id = i.id
        WHERE pi.work_order_id = ?
        ORDER BY pi.id ASC
    ");
    
    if ($purchased_query) {
        mysqli_stmt_bind_param($purchased_query, "i", $work_order_id);
        if (mysqli_stmt_execute($purchased_query)) {
            $purchased_result = mysqli_stmt_get_result($purchased_query);
            $purchased_parts = mysqli_fetch_all($purchased_result, MYSQLI_ASSOC);
        }
        mysqli_stmt_close($purchased_query);
    }

    // Fetch client provided parts (returns empty array if no parts exist)
    $client_parts = [];
    $client_query = mysqli_prepare($conn, "
        SELECT * FROM customer_provided_component WHERE work_order_id = ?
        ORDER BY id ASC
    ");
    
    if ($client_query) {
        mysqli_stmt_bind_param($client_query, "i", $work_order_id);
        if (mysqli_stmt_execute($client_query)) {
            $client_result = mysqli_stmt_get_result($client_query);
            $client_parts = mysqli_fetch_all($client_result, MYSQLI_ASSOC);
        }
        mysqli_stmt_close($client_query);
    }

    $response = [
        'success' => true,
        'workOrder' => $work_order,
        'purchasedParts' => $purchased_parts,
        'clientParts' => $client_parts,
        'payments' => $payments,
        'technicians' => $technicians,
        'assignmentHistory' => $assignment_history,
        'activityTimeline' => $activity_timeline,
        'canReassign' => work_order_can_reassign($conn),
        'cancelledFromStatus' => $cancelled_from_status
    ];

} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
?>
