<?php
include 'src/db/connection.php';
include 'auth_check.php';

if (($_SESSION['role'] ?? '') !== 'Administrator') {
    audit_authorization_failure($conn, 'report export');
    $_SESSION['dialog_flash'] = [
        'type' => 'error',
        'title' => 'Export Restricted',
        'message' => 'Only administrators can export reports.'
    ];
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/src/handlers/work_order_schema.php';
require_once __DIR__ . '/src/handlers/payment_schema.php';
require_once __DIR__ . '/src/handlers/inventory_transaction_schema.php';
require_once __DIR__ . '/src/handlers/ordered_part_schema.php';
require_once __DIR__ . '/src/handlers/activity_log_helper.php';
require_once __DIR__ . '/src/handlers/db_helpers.php';
require_once __DIR__ . '/src/handlers/sql_filter_helpers.php';

function export_report_safe_date($date): string
{
    $date = trim((string) $date);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
}

function export_report_allowed_date_column(string $column): string
{
    $allowedColumns = [
        'w.request_date',
        'COALESCE(p.date, DATE(p.created_at))',
        'DATE(pt.transaction_at)',
        'c.date',
        'i.date'
    ];

    if (!in_array($column, $allowedColumns, true)) {
        throw new InvalidArgumentException('Unsupported export date filter column.');
    }

    return $column;
}

function export_report_date_condition(string $column, string $dateFrom, string $dateTo, string &$types, array &$params): string
{
    $column = export_report_allowed_date_column($column);
    $conditions = [];

    if ($dateFrom !== '') {
        $conditions[] = "$column >= ?";
        $types .= "s";
        $params[] = $dateFrom;
    }

    if ($dateTo !== '') {
        $conditions[] = "$column <= ?";
        $types .= "s";
        $params[] = $dateTo;
    }

    return $conditions ? implode(' AND ', $conditions) : '1';
}

function export_report_money($value): string
{
    return number_format((float) $value, 2, '.', '');
}

function export_report_text($value): string
{
    return trim((string) ($value ?? ''));
}

function export_report_filename(string $type, string $scope, string $dateFrom, string $dateTo): string
{
    $type = preg_replace('/[^a-z0-9-]+/', '-', strtolower(str_replace('_', '-', $type)));
    $scope = $scope === 'all' ? 'all' : 'filtered';
    $range = 'all-time';

    if ($scope !== 'all' && ($dateFrom !== '' || $dateTo !== '')) {
        $range = ($dateFrom !== '' ? $dateFrom : 'start') . '-to-' . ($dateTo !== '' ? $dateTo : 'today');
    }

    return "macprotech-$type-$scope-$range.csv";
}

function export_report_send_csv(string $filename, array $headers, mysqli_result $result, callable $mapRow): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);

    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $mapRow($row));
    }

    fclose($output);
    exit();
}

function export_report_query(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_result
{
    $statement = mysqli_prepare($conn, $sql);

    if (!$statement) {
        http_response_code(500);
        exit('Unable to export report: ' . mysqli_error($conn));
    }

    db_bind_params($statement, $types, $params);

    if (!mysqli_stmt_execute($statement)) {
        http_response_code(500);
        exit('Unable to export report: ' . mysqli_stmt_error($statement));
    }

    $result = mysqli_stmt_get_result($statement);

    if (!$result) {
        http_response_code(500);
        exit('Unable to export report: ' . mysqli_stmt_error($statement));
    }

    return $result;
}

function export_report_payment_status_sql(): string
{
    return "COALESCE(NULLIF(p.payment_status, ''), CASE WHEN p.status IS NULL OR p.status = 'Pending' OR p.status = '' THEN 'Unpaid' ELSE p.status END)";
}

function export_report_payment_balance_sql(string $paymentStatusSql): string
{
    return "
        CASE
            WHEN COALESCE(p.remaining_balance, 0) > 0 THEN p.remaining_balance
            WHEN $paymentStatusSql IN ('Unpaid', 'Partial', 'Pending')
                THEN GREATEST(COALESCE(p.total_amount, 0) - COALESCE(p.discount_amount, 0) - COALESCE(p.amount_paid, 0), 0)
            ELSE 0
        END
    ";
}

function export_report_apply_work_order_filters(array &$where, string &$types, array &$params, string $scope, string $dateFrom, string $dateTo): void
{
    if ($scope === 'all') {
        return;
    }

    $dateCondition = export_report_date_condition('w.request_date', $dateFrom, $dateTo, $types, $params);
    if ($dateCondition !== '1') {
        $where[] = $dateCondition;
    }

    $allowedStatuses = ['Pending', 'Diagnosing', 'Waiting for Parts', 'In Progress', 'Repaired', 'Released', 'Cancelled'];
    $status = $_GET['work_order_status'] ?? '';
    if (in_array($status, $allowedStatuses, true)) {
        if ($status === 'Repaired') {
            $where[] = "w.status IN (?, ?)";
            $types .= "ss";
            $params[] = 'Repaired';
            $params[] = 'Ready for Release';
        } else {
            $where[] = "w.status = ?";
            $types .= "s";
            $params[] = $status;
        }
    }

    $technicianId = isset($_GET['technician_id']) ? (int) $_GET['technician_id'] : 0;
    if ($technicianId > 0) {
        $where[] = "w.technician_id = ?";
        $types .= "i";
        $params[] = $technicianId;
    }
}

function export_report_apply_payment_filters(array &$where, string &$types, array &$params, string $scope, string $dateFrom, string $dateTo, string $paymentStatusSql, bool $useTransactionDate = false): void
{
    if ($scope === 'all') {
        return;
    }

    $dateColumn = $useTransactionDate ? 'DATE(pt.transaction_at)' : 'COALESCE(p.date, DATE(p.created_at))';
    $dateCondition = export_report_date_condition($dateColumn, $dateFrom, $dateTo, $types, $params);
    if ($dateCondition !== '1') {
        $where[] = $dateCondition;
    }

    $allowedPaymentStatuses = ['Paid', 'Partial', 'Unpaid', 'Partially Refunded', 'Refunded'];
    $paymentStatus = $_GET['payment_status'] ?? '';
    if (!$useTransactionDate && in_array($paymentStatus, $allowedPaymentStatuses, true)) {
        $where[] = "$paymentStatusSql = ?";
        $types .= "s";
        $params[] = $paymentStatus;
    }

    $allowedMethods = ['Cash', 'GCash', 'Maya', 'Bank Transfer'];
    $paymentMethod = $_GET['payment_method'] ?? '';
    if (in_array($paymentMethod, $allowedMethods, true)) {
        $methodColumn = sql_allowed_fragment(
            $useTransactionDate ? 'transaction' : 'payment',
            ['transaction' => 'pt.method', 'payment' => 'p.payment_method'],
            'payment'
        );
        $where[] = "$methodColumn = ?";
        $types .= "s";
        $params[] = $paymentMethod;
    }
}

function export_report_apply_customer_filters(array &$where, string &$types, array &$params, string $scope, string $dateFrom, string $dateTo): void
{
    if ($scope === 'all') {
        return;
    }

    $dateCondition = export_report_date_condition('c.date', $dateFrom, $dateTo, $types, $params);
    if ($dateCondition !== '1') {
        $where[] = $dateCondition;
    }

    if (($_GET['customer_filter'] ?? '') === 'unpaid_balance') {
        $where[] = 'COALESCE(cs.outstanding_balance, 0) > 0';
    }
}

function export_report_apply_inventory_filters(array &$where, string &$types, array &$params, string $scope, string $dateFrom, string $dateTo): void
{
    if ($scope === 'all') {
        return;
    }

    $dateCondition = export_report_date_condition('i.date', $dateFrom, $dateTo, $types, $params);
    if ($dateCondition !== '1') {
        $where[] = $dateCondition;
    }

    $stockFilter = $_GET['inventory_filter'] ?? '';
    if ($stockFilter === 'low_stock') {
        $where[] = 'i.quantity > 0 AND i.quantity < 10';
    } elseif ($stockFilter === 'out_of_stock') {
        $where[] = 'i.quantity <= 0';
    } elseif ($stockFilter === 'in_stock') {
        $where[] = 'i.quantity >= 10';
    }
}

try {
    ensure_work_order_priority_column($conn);
    ensure_payment_detail_columns($conn);
    refresh_payment_summaries($conn);
    ensure_inventory_transaction_table($conn);
    backfill_inventory_transactions_from_items($conn);
    sync_all_items_from_inventory_transactions($conn);
    ensure_ordered_parts_table($conn);
} catch (Exception $e) {
    http_response_code(500);
    exit('Unable to prepare export data: ' . $e->getMessage());
}

$allowedTypes = ['work_orders', 'payments', 'customers', 'financial', 'inventory'];
$type = $_GET['type'] ?? 'work_orders';
$type = in_array($type, $allowedTypes, true) ? $type : 'work_orders';
$scope = ($_GET['scope'] ?? 'filtered') === 'all' ? 'all' : 'filtered';
$dateFrom = export_report_safe_date($_GET['date_from'] ?? '');
$dateTo = export_report_safe_date($_GET['date_to'] ?? '');

if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$filename = export_report_filename($type, $scope, $dateFrom, $dateTo);
$paymentStatusSql = export_report_payment_status_sql();
$paymentBalanceSql = export_report_payment_balance_sql($paymentStatusSql);
$rangeLabel = ($dateFrom !== '' || $dateTo !== '') ? (($dateFrom !== '' ? $dateFrom : 'start') . ' to ' . ($dateTo !== '' ? $dateTo : 'today')) : 'all time';
log_activity($conn, "Exported {$type} {$scope} report ({$rangeLabel})");

if ($type === 'work_orders') {
    $where = ['1'];
    $where_types = "";
    $where_params = [];
    export_report_apply_work_order_filters($where, $where_types, $where_params, $scope, $dateFrom, $dateTo);

    $sql = "
        SELECT
            w.code,
            w.request_date,
            TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))) AS customer_name,
            w.unit_type,
            w.brand,
            w.model,
            w.prob_find,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), 'Unassigned') AS technician_name,
            CASE WHEN w.status = 'Ready for Release' THEN 'Repaired' ELSE w.status END AS status,
            w.diagnostic_fee,
            w.work_order_cost,
            COALESCE(pp.purchased_parts_total, 0) + COALESCE(op.ordered_parts_total, 0) AS parts_cost,
            COALESCE(p.total_amount, COALESCE(w.diagnostic_fee, 0) + COALESCE(w.work_order_cost, 0) + COALESCE(pp.purchased_parts_total, 0) + COALESCE(op.ordered_parts_total, 0)) AS total_amount,
            w.completion_date,
            COALESCE(NULLIF(w.priority, ''), 'In Que') AS priority
        FROM work_order w
        LEFT JOIN client c ON c.id = w.client_id
        LEFT JOIN users u ON u.id = w.technician_id
        LEFT JOIN (
            SELECT
                pi.work_order_id,
                SUM(pi.quantity * COALESCE(pi.unit_price, i.average_price, 0)) AS purchased_parts_total
            FROM purchased_item pi
            LEFT JOIN items i ON (
                (pi.product_id REGEXP '^[0-9]+$' AND CAST(pi.product_id AS UNSIGNED) = i.id)
                OR pi.product_id = i.product_code
            )
            GROUP BY pi.work_order_id
        ) pp ON pp.work_order_id = w.id
        LEFT JOIN (
            SELECT work_order_id, SUM(quantity * price) AS ordered_parts_total
            FROM ordered_parts
            GROUP BY work_order_id
        ) op ON op.work_order_id = w.id
        LEFT JOIN (
            SELECT p1.*
            FROM payments p1
            INNER JOIN (
                SELECT work_order_id, MAX(id) AS latest_payment_id
                FROM payments
                GROUP BY work_order_id
            ) latest_payment ON latest_payment.latest_payment_id = p1.id
        ) p ON p.work_order_id = w.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY w.request_date DESC, w.code DESC
    ";

    $result = export_report_query($conn, $sql, $where_types, $where_params);
    export_report_send_csv(
        $filename,
        ['Work Order Number', 'Date Created', 'Customer Name', 'Device/Item', 'Brand', 'Model', 'Problem Description', 'Technician Assigned', 'Status', 'Diagnostic Fee', 'Labor Fee', 'Parts Cost', 'Total Amount', 'Date Completed', 'Priority'],
        $result,
        fn($row) => [
            export_report_text($row['code']),
            export_report_text($row['request_date']),
            export_report_text($row['customer_name']),
            export_report_text($row['unit_type']),
            export_report_text($row['brand']),
            export_report_text($row['model']),
            export_report_text($row['prob_find']),
            export_report_text($row['technician_name']),
            export_report_text($row['status']),
            export_report_money($row['diagnostic_fee']),
            export_report_money($row['work_order_cost']),
            export_report_money($row['parts_cost']),
            export_report_money($row['total_amount']),
            export_report_text($row['completion_date']),
            export_report_text($row['priority'])
        ]
    );
}

if ($type === 'payments') {
    $where = ['1'];
    $where_types = "";
    $where_params = [];
    export_report_apply_payment_filters($where, $where_types, $where_params, $scope, $dateFrom, $dateTo, $paymentStatusSql);

    $paymentTypeSql = "
        CASE
            WHEN COALESCE(p.amount_paid, 0) <= 0 THEN 'Unpaid'
            WHEN $paymentBalanceSql > 0 THEN 'Partial'
            ELSE 'Full'
        END
    ";

    $sql = "
        SELECT
            p.payment_code,
            wo.code AS work_order_code,
            TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))) AS customer_name,
            p.total_amount,
            p.discount_amount,
            p.amount_paid,
            $paymentTypeSql AS payment_type,
            COALESCE(NULLIF(p.payment_method, ''), 'Unspecified') AS payment_method,
            COALESCE(p.date, DATE(p.created_at)) AS payment_date,
            $paymentBalanceSql AS remaining_balance,
            $paymentStatusSql AS payment_status
        FROM payments p
        LEFT JOIN work_order wo ON wo.id = p.work_order_id
        LEFT JOIN client c ON c.id = wo.client_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY COALESCE(p.date, DATE(p.created_at)) DESC, p.id DESC
    ";

    $result = export_report_query($conn, $sql, $where_types, $where_params);
    export_report_send_csv(
        $filename,
        ['Payment ID', 'Work Order Number', 'Customer Name', 'Total Bill', 'Discount', 'Amount Paid', 'Payment Type', 'Payment Method', 'Payment Date', 'Remaining Balance', 'Payment Status'],
        $result,
        fn($row) => [
            export_report_text($row['payment_code']),
            export_report_text($row['work_order_code']),
            export_report_text($row['customer_name']),
            export_report_money($row['total_amount']),
            export_report_money($row['discount_amount']),
            export_report_money($row['amount_paid']),
            export_report_text($row['payment_type']),
            export_report_text($row['payment_method']),
            export_report_text($row['payment_date']),
            export_report_money($row['remaining_balance']),
            export_report_text($row['payment_status'])
        ]
    );
}

if ($type === 'customers') {
    $where = ['1'];
    $where_types = "";
    $where_params = [];
    export_report_apply_customer_filters($where, $where_types, $where_params, $scope, $dateFrom, $dateTo);

    $sql = "
        SELECT
            c.id,
            TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))) AS customer_name,
            c.contact_num,
            c.email,
            c.address,
            c.date,
            COALESCE(cs.work_order_count, 0) AS work_order_count,
            COALESCE(cs.outstanding_balance, 0) AS outstanding_balance
        FROM client c
        LEFT JOIN (
            SELECT
                w.client_id,
                COUNT(DISTINCT w.id) AS work_order_count,
                COALESCE(SUM($paymentBalanceSql), 0) AS outstanding_balance
            FROM work_order w
            LEFT JOIN payments p ON p.work_order_id = w.id
            GROUP BY w.client_id
        ) cs ON cs.client_id = c.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.last_name ASC, c.first_name ASC, c.id ASC
    ";

    $result = export_report_query($conn, $sql, $where_types, $where_params);
    export_report_send_csv(
        $filename,
        ['Customer ID', 'Full Name', 'Contact Number', 'Email', 'Address', 'Date Registered', 'Total Work Orders', 'Outstanding Balance'],
        $result,
        fn($row) => [
            export_report_text($row['id']),
            export_report_text($row['customer_name']),
            export_report_text($row['contact_num']),
            export_report_text($row['email']),
            export_report_text($row['address']),
            export_report_text($row['date']),
            (string) (int) $row['work_order_count'],
            export_report_money($row['outstanding_balance'])
        ]
    );
}

if ($type === 'financial') {
    $where = ["pt.transaction_type IN ('payment', 'refund')"];
    $where_types = "";
    $where_params = [];
    export_report_apply_payment_filters($where, $where_types, $where_params, $scope, $dateFrom, $dateTo, $paymentStatusSql, true);

    $sql = "
        SELECT
            DATE(pt.transaction_at) AS report_date,
            wo.code AS work_order_code,
            TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))) AS customer_name,
            COALESCE(SUM(CASE WHEN pt.transaction_type = 'payment' THEN pt.amount ELSE 0 END), 0) AS revenue,
            COALESCE(SUM(CASE WHEN pt.transaction_type = 'refund' THEN pt.amount ELSE 0 END), 0) AS refunds,
            COALESCE(SUM(CASE WHEN pt.transaction_type = 'payment' THEN pt.amount ELSE -pt.amount END), 0) AS net_revenue
        FROM payment_transaction pt
        LEFT JOIN payments p ON p.id = pt.payment_id
        LEFT JOIN work_order wo ON wo.id = pt.work_order_id
        LEFT JOIN client c ON c.id = wo.client_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY DATE(pt.transaction_at), wo.id, wo.code, c.first_name, c.last_name
        ORDER BY report_date DESC, wo.code ASC
    ";

    $result = export_report_query($conn, $sql, $where_types, $where_params);
    export_report_send_csv(
        $filename,
        ['Date', 'Work Order Number', 'Customer', 'Revenue', 'Refunds', 'Net Revenue'],
        $result,
        fn($row) => [
            export_report_text($row['report_date']),
            export_report_text($row['work_order_code']),
            export_report_text($row['customer_name']),
            export_report_money($row['revenue']),
            export_report_money($row['refunds']),
            export_report_money($row['net_revenue'])
        ]
    );
}

if ($type === 'inventory') {
    $where = ['1'];
    $where_types = "";
    $where_params = [];
    export_report_apply_inventory_filters($where, $where_types, $where_params, $scope, $dateFrom, $dateTo);

    $stockStatusSql = "
        CASE
            WHEN i.quantity <= 0 THEN 'Out of Stock'
            WHEN i.quantity < 10 THEN 'Low Stock'
            ELSE COALESCE(NULLIF(i.status, ''), 'In Stock')
        END
    ";

    $sql = "
        SELECT
            i.product_code,
            TRIM(CONCAT(COALESCE(i.brand_name, ''), ' ', COALESCE(i.model, ''))) AS item_name,
            COALESCE(ic.category_name, 'Uncategorized') AS category_name,
            i.quantity,
            i.average_price,
            COALESCE(i.markup_percentage, 0) AS markup_percentage,
            (i.average_price + (i.average_price * COALESCE(i.markup_percentage, 0) / 100)) AS selling_price,
            $stockStatusSql AS stock_status,
            i.date
        FROM items i
        LEFT JOIN item_category ic ON ic.id = i.category_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY i.brand_name ASC, i.model ASC, i.product_code ASC
    ";

    $result = export_report_query($conn, $sql, $where_types, $where_params);
    export_report_send_csv(
        $filename,
        ['Item Code', 'Item Name', 'Category', 'Quantity', 'Capital Cost', 'Markup Percentage', 'Selling Price', 'Stock Status', 'Date Added'],
        $result,
        fn($row) => [
            export_report_text($row['product_code']),
            export_report_text($row['item_name']),
            export_report_text($row['category_name']),
            (string) (int) $row['quantity'],
            export_report_money($row['average_price']),
            export_report_money($row['markup_percentage']),
            export_report_money($row['selling_price']),
            export_report_text($row['stock_status']),
            export_report_text($row['date'])
        ]
    );
}

http_response_code(400);
exit('Invalid export type.');
