<?php
	include 'src/db/connection.php';
	include 'auth_check.php';

	if (($_SESSION['role'] ?? '') !== 'Administrator') {
		$_SESSION['dialog_flash'] = [
			'type' => 'error',
			'title' => 'Reports Restricted',
			'message' => 'Only administrators can access system reports.'
		];
		header('Location: index.php');
		exit();
	}

	require_once __DIR__ . '/src/handlers/payment_schema.php';
	require_once __DIR__ . '/src/handlers/inventory_transaction_schema.php';
	require_once __DIR__ . '/src/handlers/ordered_part_schema.php';

	$report_warnings = [];

	function reports_table_exists(mysqli $conn, string $table): bool {
		$table = mysqli_real_escape_string($conn, $table);
		$result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
		return $result && mysqli_num_rows($result) > 0;
	}

	function reports_safe_date($date): string {
		$date = trim((string) $date);
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
	}

	function reports_date_condition(mysqli $conn, string $column, string $date_from, string $date_to): string {
		$conditions = [];

		if ($date_from !== '') {
			$from = mysqli_real_escape_string($conn, $date_from);
			$conditions[] = "$column >= '$from'";
		}

		if ($date_to !== '') {
			$to = mysqli_real_escape_string($conn, $date_to);
			$conditions[] = "$column <= '$to'";
		}

		return $conditions ? implode(' AND ', $conditions) : '1';
	}

	function reports_scalar(mysqli $conn, string $sql, string $field = 'total', $fallback = 0) {
		global $report_warnings;
		$result = mysqli_query($conn, $sql);

		if (!$result) {
			$report_warnings[] = mysqli_error($conn);
			return $fallback;
		}

		$row = mysqli_fetch_assoc($result);
		return $row[$field] ?? $fallback;
	}

	function reports_row(mysqli $conn, string $sql, array $fallback = []): array {
		global $report_warnings;
		$result = mysqli_query($conn, $sql);

		if (!$result) {
			$report_warnings[] = mysqli_error($conn);
			return $fallback;
		}

		return mysqli_fetch_assoc($result) ?: $fallback;
	}

	function reports_rows(mysqli $conn, string $sql): array {
		global $report_warnings;
		$rows = [];
		$result = mysqli_query($conn, $sql);

		if (!$result) {
			$report_warnings[] = mysqli_error($conn);
			return $rows;
		}

		while ($row = mysqli_fetch_assoc($result)) {
			$rows[] = $row;
		}

		return $rows;
	}

	function reports_money($value): string {
		return 'Php ' . number_format((float) $value, 2);
	}

	function reports_number($value): string {
		return number_format((float) $value);
	}

	function reports_percent($part, $total): float {
		$total = (float) $total;
		return $total > 0 ? round(((float) $part / $total) * 100, 1) : 0.0;
	}

	function reports_display_date($date, string $format = 'M d, Y'): string {
		if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
			return '-';
		}

		try {
			return (new DateTime($date))->format($format);
		} catch (Exception $e) {
			return (string) $date;
		}
	}

	function reports_range_label(string $date_from, string $date_to): string {
		if ($date_from === '' && $date_to === '') {
			return 'All time';
		}

		if ($date_from !== '' && $date_to !== '') {
			return reports_display_date($date_from) . ' to ' . reports_display_date($date_to);
		}

		if ($date_from !== '') {
			return 'From ' . reports_display_date($date_from);
		}

		return 'Until ' . reports_display_date($date_to);
	}

	$date_from = reports_safe_date($_GET['date_from'] ?? '');
	$date_to = reports_safe_date($_GET['date_to'] ?? '');

	if ($date_from !== '' && $date_to !== '' && $date_from > $date_to) {
		[$date_from, $date_to] = [$date_to, $date_from];
	}

	try {
		ensure_payment_detail_columns($conn);
		refresh_payment_summaries($conn);
		ensure_inventory_transaction_table($conn);
		backfill_inventory_transactions_from_items($conn);
		sync_all_items_from_inventory_transactions($conn);
		ensure_ordered_parts_table($conn);
	} catch (Exception $e) {
		$report_warnings[] = $e->getMessage();
	}

	$work_order_date_where = reports_date_condition($conn, 'request_date', $date_from, $date_to);
	$work_order_alias_date_where = reports_date_condition($conn, 'w.request_date', $date_from, $date_to);
	$completion_date_where = reports_date_condition($conn, 'completion_date', $date_from, $date_to);
	$completion_alias_date_where = reports_date_condition($conn, 'w.completion_date', $date_from, $date_to);
	$client_date_where = reports_date_condition($conn, 'date', $date_from, $date_to);
	$payment_date_where = reports_date_condition($conn, 'COALESCE(p.date, DATE(p.created_at))', $date_from, $date_to);
	$payment_transaction_date_where = reports_date_condition($conn, 'DATE(pt.transaction_at)', $date_from, $date_to);
	$stock_in_date_where = reports_date_condition($conn, 'sit.stock_in_date', $date_from, $date_to);
	$stock_out_date_where = reports_date_condition($conn, 'sot.stock_out_date', $date_from, $date_to);
	$purchased_part_date_where = reports_date_condition($conn, 'pi.date', $date_from, $date_to);
	$ordered_part_date_where = reports_date_condition($conn, 'DATE(op.created_at)', $date_from, $date_to);
	$activity_date_where = reports_date_condition($conn, 'DATE(al.created_at)', $date_from, $date_to);

	$payment_status_sql = "COALESCE(NULLIF(p.payment_status, ''), CASE WHEN p.status IS NULL OR p.status = 'Pending' OR p.status = '' THEN 'Unpaid' ELSE p.status END)";
	$payment_balance_sql = "
		CASE
			WHEN COALESCE(p.remaining_balance, 0) > 0 THEN p.remaining_balance
			WHEN $payment_status_sql IN ('Unpaid', 'Partial', 'Pending')
				THEN GREATEST(COALESCE(p.total_amount, 0) - COALESCE(p.discount_amount, 0) - COALESCE(p.amount_paid, 0), 0)
			ELSE 0
		END
	";
	$refund_by_payment_sql = "SELECT payment_id, SUM(refund_amount) AS total_refunded FROM refunds GROUP BY payment_id";
	$payment_net_by_work_order_sql = "
		SELECT p.work_order_id, SUM(GREATEST(COALESCE(p.amount_paid, 0) - COALESCE(r.total_refunded, 0), 0)) AS net_paid
		FROM payments p
		LEFT JOIN ($refund_by_payment_sql) r ON r.payment_id = p.id
		GROUP BY p.work_order_id
	";

	$total_workorders = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM work_order");
	$period_workorders = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM work_order WHERE $work_order_date_where");
	$open_workorders = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM work_order WHERE status NOT IN ('Released', 'Cancelled')");
	$unassigned_open = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM work_order WHERE technician_id IS NULL AND status NOT IN ('Released', 'Cancelled')");
	$aged_open = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM work_order WHERE status NOT IN ('Released', 'Cancelled') AND request_date <= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
	$completed_period = (int) reports_scalar(
		$conn,
		"SELECT COUNT(*) AS total
		 FROM work_order
		 WHERE completion_date IS NOT NULL
		 AND status IN ('Repaired', 'Ready for Release', 'Released')
		 AND $completion_date_where"
	);
	$cancelled_period = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM work_order WHERE status = 'Cancelled' AND $work_order_date_where");
	$avg_cycle_days = (float) reports_scalar(
		$conn,
		"SELECT COALESCE(AVG(DATEDIFF(completion_date, request_date)), 0) AS total
		 FROM work_order
		 WHERE completion_date IS NOT NULL
		 AND completion_date >= request_date
		 AND status IN ('Repaired', 'Ready for Release', 'Released')
		 AND $completion_date_where"
	);

	$total_customers = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM client");
	$new_customers = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM client WHERE $client_date_where");
	$repeat_customers = (int) reports_scalar(
		$conn,
		"SELECT COUNT(*) AS total
		 FROM (
			SELECT c.id
			FROM client c
			INNER JOIN work_order w ON w.client_id = c.id AND $work_order_alias_date_where
			GROUP BY c.id
			HAVING COUNT(w.id) > 1
		 ) repeat_clients"
	);

	$payment_summary = reports_row(
		$conn,
		"SELECT
			COALESCE(SUM(CASE WHEN pt.transaction_type = 'payment' THEN pt.amount ELSE 0 END), 0) AS collected,
			COALESCE(SUM(CASE WHEN pt.transaction_type = 'refund' THEN pt.amount ELSE 0 END), 0) AS refunded
		 FROM payment_transaction pt
		 WHERE $payment_transaction_date_where",
		['collected' => 0, 'refunded' => 0]
	);
	$collected_total = (float) $payment_summary['collected'];
	$refunded_total = (float) $payment_summary['refunded'];
	$net_revenue = max(0, $collected_total - $refunded_total);
	$gross_billed = (float) reports_scalar($conn, "SELECT COALESCE(SUM(p.total_amount), 0) AS total FROM payments p WHERE $payment_date_where");
	$discount_total = (float) reports_scalar($conn, "SELECT COALESCE(SUM(p.discount_amount), 0) AS total FROM payments p WHERE $payment_date_where");
	$outstanding_balance = (float) reports_scalar($conn, "SELECT COALESCE(SUM($payment_balance_sql), 0) AS total FROM payments p WHERE $payment_date_where");
	$pending_payments = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM payments p WHERE $payment_date_where AND $payment_balance_sql > 0");

	$total_items = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM items");
	$low_stock_items = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM items WHERE quantity > 0 AND quantity < 10");
	$out_of_stock_items = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM items WHERE quantity <= 0");
	$inventory_value = (float) reports_scalar($conn, "SELECT COALESCE(SUM(quantity * average_price), 0) AS total FROM items");
	$stock_in_units = (int) reports_scalar($conn, "SELECT COALESCE(SUM(sit.stock_in), 0) AS total FROM stock_in_transaction sit WHERE $stock_in_date_where");
	$stock_in_cost = (float) reports_scalar($conn, "SELECT COALESCE(SUM(sit.capital * sit.stock_in), 0) AS total FROM stock_in_transaction sit WHERE $stock_in_date_where");
	$stock_out_units = (int) reports_scalar($conn, "SELECT COALESCE(SUM(sot.quantity), 0) AS total FROM stock_out_transaction sot WHERE $stock_out_date_where");
	$purchased_part_units = (int) reports_scalar($conn, "SELECT COALESCE(SUM(pi.quantity), 0) AS total FROM purchased_item pi WHERE $purchased_part_date_where");
	$ordered_part_spend = (float) reports_scalar($conn, "SELECT COALESCE(SUM(op.quantity * op.price), 0) AS total FROM ordered_parts op WHERE $ordered_part_date_where");
	$ordered_part_units = (int) reports_scalar($conn, "SELECT COALESCE(SUM(op.quantity), 0) AS total FROM ordered_parts op WHERE $ordered_part_date_where");

	$total_users = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM users");
	$total_technicians = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'Technician'");
	$total_categories = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM item_category");
	$total_unit_types = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM unit_type");
	$activity_count = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM activity_logs al WHERE $activity_date_where");
	$customer_parts_count = (int) reports_scalar($conn, "SELECT COALESCE(SUM(quantity), 0) AS total FROM customer_provided_component");

	$work_order_status_rows = reports_rows(
		$conn,
		"SELECT CASE WHEN status = 'Ready for Release' THEN 'Repaired' ELSE status END AS status, COUNT(*) AS total
		 FROM work_order
		 WHERE $work_order_date_where
		 GROUP BY CASE WHEN status = 'Ready for Release' THEN 'Repaired' ELSE status END
		 ORDER BY total DESC, status ASC"
	);
	$status_total = array_sum(array_map(fn($row) => (int) $row['total'], $work_order_status_rows));

	$work_order_priority_rows = reports_rows(
		$conn,
		"SELECT COALESCE(NULLIF(priority, ''), 'Unspecified') AS priority, COUNT(*) AS total
		 FROM work_order
		 WHERE $work_order_date_where
		 GROUP BY COALESCE(NULLIF(priority, ''), 'Unspecified')
		 ORDER BY total DESC, priority ASC"
	);

	$unit_type_rows = reports_rows(
		$conn,
		"SELECT COALESCE(NULLIF(w.unit_type, ''), 'Unspecified') AS unit_type,
				COUNT(w.id) AS total,
				SUM(CASE WHEN w.status NOT IN ('Released', 'Cancelled') THEN 1 ELSE 0 END) AS open_total,
				COALESCE(SUM(ps.net_paid), 0) AS net_paid
		 FROM work_order w
		 LEFT JOIN ($payment_net_by_work_order_sql) ps ON ps.work_order_id = w.id
		 WHERE $work_order_alias_date_where
		 GROUP BY COALESCE(NULLIF(w.unit_type, ''), 'Unspecified')
		 ORDER BY total DESC, net_paid DESC
		 LIMIT 8"
	);

	$payment_status_rows = reports_rows(
		$conn,
		"SELECT $payment_status_sql AS status,
				COUNT(*) AS total,
				COALESCE(SUM(p.total_amount), 0) AS gross_total,
				COALESCE(SUM(p.amount_paid), 0) AS paid_total,
				COALESCE(SUM($payment_balance_sql), 0) AS outstanding_total
		 FROM payments p
		 WHERE $payment_date_where
		 GROUP BY $payment_status_sql
		 ORDER BY total DESC, status ASC"
	);

	$payment_method_rows = reports_rows(
		$conn,
		"SELECT COALESCE(NULLIF(pt.method, ''), 'Unspecified') AS method,
				COUNT(*) AS transaction_count,
				COALESCE(SUM(pt.amount), 0) AS total
		 FROM payment_transaction pt
		 WHERE pt.transaction_type = 'payment'
		 AND $payment_transaction_date_where
		 GROUP BY COALESCE(NULLIF(pt.method, ''), 'Unspecified')
		 ORDER BY total DESC, transaction_count DESC"
	);

	$recent_payments = reports_rows(
		$conn,
		"SELECT p.payment_code, wo.code AS work_order_code, $payment_status_sql AS payment_status,
				p.total_amount, p.amount_paid, $payment_balance_sql AS balance_due,
				COALESCE(p.date, DATE(p.created_at)) AS payment_date
		 FROM payments p
		 LEFT JOIN work_order wo ON wo.id = p.work_order_id
		 WHERE $payment_date_where
		 ORDER BY COALESCE(p.date, DATE(p.created_at)) DESC, p.id DESC
		 LIMIT 8"
	);

	$refund_rows = reports_rows(
		$conn,
		"SELECT p.payment_code, wo.code AS work_order_code, r.refund_amount, r.refund_method, r.reason, r.refunded_at
		 FROM refunds r
		 INNER JOIN payments p ON p.id = r.payment_id
		 LEFT JOIN work_order wo ON wo.id = p.work_order_id
		 WHERE " . reports_date_condition($conn, 'DATE(r.refunded_at)', $date_from, $date_to) . "
		 ORDER BY r.refunded_at DESC, r.id DESC
		 LIMIT 8"
	);

	$top_customers = reports_rows(
		$conn,
		"SELECT c.id,
				TRIM(CONCAT(c.first_name, ' ', c.last_name)) AS customer_name,
				c.contact_num,
				COUNT(w.id) AS work_order_count,
				MAX(w.request_date) AS latest_order,
				COALESCE(SUM(ps.net_paid), 0) AS net_paid
		 FROM client c
		 INNER JOIN work_order w ON w.client_id = c.id AND $work_order_alias_date_where
		 LEFT JOIN ($payment_net_by_work_order_sql) ps ON ps.work_order_id = w.id
		 GROUP BY c.id, c.first_name, c.last_name, c.contact_num
		 ORDER BY net_paid DESC, work_order_count DESC, latest_order DESC
		 LIMIT 8"
	);

	$customer_intake_rows = reports_rows(
		$conn,
		"SELECT DATE_FORMAT(c.date, '%Y-%m') AS month_key, COUNT(*) AS total
		 FROM client c
		 WHERE " . reports_date_condition($conn, 'c.date', $date_from, $date_to) . "
		 GROUP BY DATE_FORMAT(c.date, '%Y-%m')
		 ORDER BY month_key DESC
		 LIMIT 6"
	);

	$technician_rows = reports_rows(
		$conn,
		"SELECT u.id,
				TRIM(CONCAT(u.first_name, ' ', u.last_name)) AS technician_name,
				COUNT(CASE WHEN w.status NOT IN ('Released', 'Cancelled') THEN 1 END) AS current_open,
				COUNT(CASE WHEN $work_order_alias_date_where THEN 1 END) AS assigned_period,
				COUNT(CASE WHEN w.completion_date IS NOT NULL AND w.status IN ('Repaired', 'Ready for Release', 'Released') AND $completion_alias_date_where THEN 1 END) AS completed_period,
				COALESCE(AVG(CASE WHEN w.completion_date IS NOT NULL AND w.completion_date >= w.request_date AND $completion_alias_date_where THEN DATEDIFF(w.completion_date, w.request_date) END), 0) AS avg_cycle_days
		 FROM users u
		 LEFT JOIN work_order w ON w.technician_id = u.id
		 WHERE u.role = 'Technician'
		 GROUP BY u.id, u.first_name, u.last_name
		 ORDER BY current_open DESC, completed_period DESC, technician_name ASC"
	);

	$inventory_category_rows = reports_rows(
		$conn,
		"SELECT COALESCE(c.category_name, 'Uncategorized') AS category_name,
				COUNT(i.id) AS item_count,
				COALESCE(SUM(i.quantity), 0) AS units_on_hand,
				COALESCE(SUM(i.quantity * i.average_price), 0) AS inventory_value
		 FROM items i
		 LEFT JOIN item_category c ON c.id = i.category_id
		 GROUP BY COALESCE(c.category_name, 'Uncategorized')
		 ORDER BY inventory_value DESC, item_count DESC"
	);

	$low_stock_rows = reports_rows(
		$conn,
		"SELECT id, product_code, brand_name, model, quantity, average_price, status
		 FROM items
		 WHERE quantity < 10
		 ORDER BY quantity ASC, brand_name ASC, model ASC
		 LIMIT 10"
	);

	$most_used_parts = reports_rows(
		$conn,
		"SELECT i.id, i.product_code, i.brand_name, i.model,
				COALESCE(SUM(sot.quantity), 0) AS used_quantity,
				COUNT(DISTINCT sot.work_order_id) AS work_order_count
		 FROM stock_out_transaction sot
		 INNER JOIN items i ON i.id = sot.item_id
		 WHERE $stock_out_date_where
		 GROUP BY i.id, i.product_code, i.brand_name, i.model
		 ORDER BY used_quantity DESC, work_order_count DESC
		 LIMIT 8"
	);

	$ordered_parts_rows = reports_rows(
		$conn,
		"SELECT COALESCE(NULLIF(op.part_name, ''), 'Unnamed Part') AS part_name,
				COALESCE(NULLIF(op.brand, ''), 'Unspecified') AS brand,
				COALESCE(NULLIF(op.category, ''), 'Unspecified') AS category,
				COALESCE(SUM(op.quantity), 0) AS quantity,
				COALESCE(SUM(op.quantity * op.price), 0) AS total_cost
		 FROM ordered_parts op
		 WHERE $ordered_part_date_where
		 GROUP BY COALESCE(NULLIF(op.part_name, ''), 'Unnamed Part'), COALESCE(NULLIF(op.brand, ''), 'Unspecified'), COALESCE(NULLIF(op.category, ''), 'Unspecified')
		 ORDER BY total_cost DESC, quantity DESC
		 LIMIT 8"
	);

	$activity_rows = reports_rows(
		$conn,
		"SELECT al.action,
				COALESCE(
					NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''),
					NULLIF(u.username, ''),
					CONCAT('Deleted user #', al.user_id)
				) AS user_name,
				COUNT(*) AS total,
				MAX(al.created_at) AS latest_activity
		 FROM activity_logs al
		 LEFT JOIN users u ON u.id = al.user_id
		 WHERE $activity_date_where
		 GROUP BY al.action, al.user_id, u.first_name, u.last_name, u.username
		 ORDER BY total DESC, latest_activity DESC
		 LIMIT 8"
	);

	include 'header.php';
	include 'sidebar.php';
?>

<style>
	.reports-page {
		color: #111827;
	}

	.reports-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 18px;
		margin-bottom: 18px;
	}

	.reports-header h4 {
		margin: 0 0 4px;
		font-weight: 700;
	}

	.reports-header p,
	.reports-section-title p {
		margin: 0;
		color: #6b7280;
	}

	.reports-filter {
		display: flex;
		align-items: flex-end;
		justify-content: flex-end;
		gap: 10px;
		flex-wrap: wrap;
	}

	.reports-filter .form-group {
		margin-bottom: 0;
	}

	.reports-filter label {
		font-size: 12px;
		font-weight: 700;
		color: #4b5563;
		margin-bottom: 4px;
	}

	.reports-grid {
		display: grid;
		grid-template-columns: repeat(4, minmax(0, 1fr));
		gap: 14px;
		margin-bottom: 18px;
	}

	.report-metric,
	.report-insight {
		background: #fff;
		border: 1px solid #e5e7eb;
		border-radius: 8px;
		padding: 16px;
		box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
		min-width: 0;
	}

	.report-metric small,
	.report-insight small {
		display: block;
		color: #6b7280;
		font-weight: 700;
		text-transform: uppercase;
		margin-bottom: 8px;
	}

	.report-metric strong {
		display: block;
		font-size: 24px;
		line-height: 1.2;
		color: #111827;
		overflow-wrap: anywhere;
	}

	.report-metric span,
	.report-insight span {
		display: block;
		color: #6b7280;
		margin-top: 8px;
	}

	.report-metric.is-warning strong {
		color: #b45309;
	}

	.report-metric.is-danger strong {
		color: #b91c1c;
	}

	.report-metric.is-success strong {
		color: #15803d;
	}

	.reports-section {
		background: #fff;
		border: 1px solid #e5e7eb;
		border-radius: 8px;
		margin-bottom: 18px;
		box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
	}

	.reports-section-title {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		padding: 18px 20px;
		border-bottom: 1px solid #e5e7eb;
	}

	.reports-section-title h5 {
		margin: 0 0 4px;
		font-weight: 700;
		color: #111827;
	}

	.reports-section-body {
		padding: 20px;
	}

	.reports-two-col {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 18px;
	}

	.reports-three-col {
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 18px;
	}

	.report-table {
		width: 100%;
		border-collapse: collapse;
	}

	.report-table th,
	.report-table td {
		border-bottom: 1px solid #eef2f7;
		padding: 11px 10px;
		vertical-align: middle;
	}

	.report-table th {
		color: #4b5563;
		font-size: 12px;
		text-transform: uppercase;
		background: #f8fafc;
	}

	.report-table td {
		color: #111827;
	}

	.report-table .text-right {
		text-align: right;
	}

	.report-subtable-title {
		font-weight: 700;
		margin: 0 0 10px;
		color: #111827;
	}

	.report-muted {
		color: #6b7280;
		font-size: 12px;
	}

	.report-empty {
		text-align: center;
		color: #6b7280;
		padding: 18px;
	}

	.report-bar {
		height: 7px;
		background: #e5e7eb;
		border-radius: 999px;
		overflow: hidden;
		margin-top: 6px;
	}

	.report-bar span {
		display: block;
		height: 100%;
		background: #2563eb;
	}

	.report-pill {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 5px 9px;
		border-radius: 999px;
		font-size: 12px;
		font-weight: 700;
		background: #eef2ff;
		color: #3730a3;
	}

	.report-pill.is-danger {
		background: #fee2e2;
		color: #991b1b;
	}

	.report-pill.is-warning {
		background: #fef3c7;
		color: #92400e;
	}

	.report-pill.is-success {
		background: #dcfce7;
		color: #166534;
	}

	.report-recommendations {
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 14px;
	}

	.report-insight strong {
		display: block;
		font-size: 15px;
		color: #111827;
		margin-bottom: 6px;
	}

	@media (max-width: 1200px) {
		.reports-grid,
		.report-recommendations {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}

		.reports-three-col {
			grid-template-columns: 1fr;
		}
	}

	@media (max-width: 768px) {
		.reports-header,
		.reports-section-title {
			display: block;
		}

		.reports-filter {
			justify-content: flex-start;
			margin-top: 12px;
		}

		.reports-grid,
		.reports-two-col,
		.report-recommendations {
			grid-template-columns: 1fr;
		}

		.reports-section-body {
			padding: 14px;
			overflow-x: auto;
		}
	}

	@media print {
		.header,
		.left-side-bar,
		.mobile-menu-overlay,
		.reports-filter,
		.btn {
			display: none !important;
		}

		.main-container {
			padding-left: 0 !important;
		}

		.reports-section,
		.report-metric,
		.report-insight {
			box-shadow: none;
			break-inside: avoid;
		}
	}
</style>

<div class="mobile-menu-overlay"></div>

<div class="main-container reports-page">
	<div class="pd-ltr-20 xs-pd-20-10">
		<div class="min-height-200px">
			<div class="reports-header">
				<div>
					<h4><i class="micon dw dw-bar-chart mtext"></i> Reports</h4>
					<p><?= htmlspecialchars(reports_range_label($date_from, $date_to)) ?> operational summary across repairs, payments, customers, inventory, parts, users, and activity.</p>
				</div>
				<form method="GET" class="reports-filter">
					<div class="form-group">
						<label for="date_from">From</label>
						<input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
					</div>
					<div class="form-group">
						<label for="date_to">To</label>
						<input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
					</div>
					<button type="submit" class="btn btn-primary btn-sm">Apply</button>
					<a href="reports.php" class="btn btn-secondary btn-sm">All Time</a>
					<button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">Print</button>
				</form>
			</div>

			<?php if (!empty($report_warnings)): ?>
				<div class="alert alert-warning">
					Some report sections could not load: <?= htmlspecialchars(implode(' ', array_slice(array_unique($report_warnings), 0, 2))) ?>
				</div>
			<?php endif; ?>

			<div class="reports-grid">
				<div class="report-metric">
					<small>Work Orders</small>
					<strong><?= reports_number($period_workorders) ?></strong>
					<span><?= reports_number($total_workorders) ?> all-time, <?= reports_number($open_workorders) ?> open now</span>
				</div>
				<div class="report-metric is-success">
					<small>Net Collected</small>
					<strong><?= reports_money($net_revenue) ?></strong>
					<span><?= reports_money($collected_total) ?> collected, <?= reports_money($refunded_total) ?> refunded</span>
				</div>
				<div class="report-metric <?= $outstanding_balance > 0 ? 'is-warning' : 'is-success' ?>">
					<small>Outstanding</small>
					<strong><?= reports_money($outstanding_balance) ?></strong>
					<span><?= reports_number($pending_payments) ?> payment<?= $pending_payments === 1 ? '' : 's' ?> with balance</span>
				</div>
				<div class="report-metric">
					<small>Customers</small>
					<strong><?= reports_number($new_customers) ?></strong>
					<span><?= reports_number($total_customers) ?> total, <?= reports_number($repeat_customers) ?> repeat in range</span>
				</div>
				<div class="report-metric">
					<small>Completed Repairs</small>
					<strong><?= reports_number($completed_period) ?></strong>
					<span><?= number_format($avg_cycle_days, 1) ?> avg cycle days</span>
				</div>
				<div class="report-metric <?= ($aged_open + $unassigned_open) > 0 ? 'is-danger' : '' ?>">
					<small>Repair Risk</small>
					<strong><?= reports_number($aged_open + $unassigned_open) ?></strong>
					<span><?= reports_number($aged_open) ?> aged, <?= reports_number($unassigned_open) ?> unassigned</span>
				</div>
				<div class="report-metric <?= $out_of_stock_items > 0 ? 'is-danger' : ($low_stock_items > 0 ? 'is-warning' : 'is-success') ?>">
					<small>Inventory Alerts</small>
					<strong><?= reports_number($low_stock_items + $out_of_stock_items) ?></strong>
					<span><?= reports_number($out_of_stock_items) ?> out, <?= reports_number($low_stock_items) ?> low</span>
				</div>
				<div class="report-metric">
					<small>Inventory Value</small>
					<strong><?= reports_money($inventory_value) ?></strong>
					<span><?= reports_number($total_items) ?> items, <?= reports_number($stock_out_units) ?> stock-out units</span>
				</div>
			</div>

			<div class="reports-section">
				<div class="reports-section-title">
					<div>
						<h5>Work Order Reports</h5>
						<p>Repair volume, status mix, unit demand, priority, and technician workload.</p>
					</div>
					<span class="report-pill"><?= reports_number($period_workorders) ?> in range</span>
				</div>
				<div class="reports-section-body">
					<div class="reports-three-col">
						<div>
							<h6 class="report-subtable-title">Status Breakdown</h6>
							<table class="report-table">
								<thead>
									<tr>
										<th>Status</th>
										<th class="text-right">Count</th>
										<th class="text-right">Share</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($work_order_status_rows as $row): ?>
										<?php $pct = reports_percent($row['total'], $status_total); ?>
										<tr>
											<td>
												<?= htmlspecialchars($row['status'] ?: 'Unspecified') ?>
												<div class="report-bar"><span style="width: <?= min(100, $pct) ?>%;"></span></div>
											</td>
											<td class="text-right"><?= reports_number($row['total']) ?></td>
											<td class="text-right"><?= number_format($pct, 1) ?>%</td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($work_order_status_rows)): ?>
										<tr><td colspan="3" class="report-empty">No work orders found.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>

						<div>
							<h6 class="report-subtable-title">Priority Breakdown</h6>
							<table class="report-table">
								<thead>
									<tr>
										<th>Priority</th>
										<th class="text-right">Count</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($work_order_priority_rows as $row): ?>
										<tr>
											<td><?= htmlspecialchars($row['priority']) ?></td>
											<td class="text-right"><?= reports_number($row['total']) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($work_order_priority_rows)): ?>
										<tr><td colspan="2" class="report-empty">No priority data found.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>

						<div>
							<h6 class="report-subtable-title">Top Unit Types</h6>
							<table class="report-table">
								<thead>
									<tr>
										<th>Unit Type</th>
										<th class="text-right">Orders</th>
										<th class="text-right">Net Paid</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($unit_type_rows as $row): ?>
										<tr>
											<td>
												<?= htmlspecialchars($row['unit_type']) ?>
												<div class="report-muted"><?= reports_number($row['open_total']) ?> open</div>
											</td>
											<td class="text-right"><?= reports_number($row['total']) ?></td>
											<td class="text-right"><?= reports_money($row['net_paid']) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($unit_type_rows)): ?>
										<tr><td colspan="3" class="report-empty">No unit type data found.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>

					<div style="margin-top: 22px;">
						<h6 class="report-subtable-title">Technician Performance</h6>
						<table class="report-table">
							<thead>
								<tr>
									<th>Technician</th>
									<th class="text-right">Open Now</th>
									<th class="text-right">Assigned</th>
									<th class="text-right">Completed</th>
									<th class="text-right">Avg Cycle</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($technician_rows as $row): ?>
									<tr>
										<td><?= htmlspecialchars($row['technician_name'] ?: 'Technician') ?></td>
										<td class="text-right"><?= reports_number($row['current_open']) ?></td>
										<td class="text-right"><?= reports_number($row['assigned_period']) ?></td>
										<td class="text-right"><?= reports_number($row['completed_period']) ?></td>
										<td class="text-right"><?= number_format((float) $row['avg_cycle_days'], 1) ?> days</td>
									</tr>
								<?php endforeach; ?>
								<?php if (empty($technician_rows)): ?>
									<tr><td colspan="5" class="report-empty">No technicians found.</td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="reports-section">
				<div class="reports-section-title">
					<div>
						<h5>Payments &amp; Revenue Reports</h5>
						<p>Billing, collections, refunds, balances, statuses, and payment methods.</p>
					</div>
					<span class="report-pill <?= $outstanding_balance > 0 ? 'is-warning' : 'is-success' ?>"><?= reports_money($outstanding_balance) ?> due</span>
				</div>
				<div class="reports-section-body">
					<div class="reports-grid">
						<div class="report-metric">
							<small>Gross Billed</small>
							<strong><?= reports_money($gross_billed) ?></strong>
							<span><?= reports_money($discount_total) ?> discounts</span>
						</div>
						<div class="report-metric is-success">
							<small>Collected</small>
							<strong><?= reports_money($collected_total) ?></strong>
							<span>Payment ledger total</span>
						</div>
						<div class="report-metric is-danger">
							<small>Refunded</small>
							<strong><?= reports_money($refunded_total) ?></strong>
							<span>Refund ledger total</span>
						</div>
						<div class="report-metric">
							<small>Net Revenue</small>
							<strong><?= reports_money($net_revenue) ?></strong>
							<span>Collected minus refunded</span>
						</div>
					</div>

					<div class="reports-two-col">
						<div>
							<h6 class="report-subtable-title">Payment Status</h6>
							<table class="report-table">
								<thead>
									<tr>
										<th>Status</th>
										<th class="text-right">Count</th>
										<th class="text-right">Paid</th>
										<th class="text-right">Due</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($payment_status_rows as $row): ?>
										<tr>
											<td><?= htmlspecialchars($row['status']) ?></td>
											<td class="text-right"><?= reports_number($row['total']) ?></td>
											<td class="text-right"><?= reports_money($row['paid_total']) ?></td>
											<td class="text-right"><?= reports_money($row['outstanding_total']) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($payment_status_rows)): ?>
										<tr><td colspan="4" class="report-empty">No payment records found.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>

						<div>
							<h6 class="report-subtable-title">Payment Methods</h6>
							<table class="report-table">
								<thead>
									<tr>
										<th>Method</th>
										<th class="text-right">Transactions</th>
										<th class="text-right">Collected</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($payment_method_rows as $row): ?>
										<tr>
											<td><?= htmlspecialchars($row['method']) ?></td>
											<td class="text-right"><?= reports_number($row['transaction_count']) ?></td>
											<td class="text-right"><?= reports_money($row['total']) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($payment_method_rows)): ?>
										<tr><td colspan="3" class="report-empty">No payment method data found.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>

					<div class="reports-two-col" style="margin-top: 22px;">
						<div>
							<h6 class="report-subtable-title">Recent Payments</h6>
							<table class="report-table">
								<thead>
									<tr>
										<th>Payment</th>
										<th>Status</th>
										<th class="text-right">Paid</th>
										<th class="text-right">Balance</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($recent_payments as $row): ?>
										<tr>
											<td>
												<?= htmlspecialchars($row['payment_code']) ?>
												<div class="report-muted"><?= htmlspecialchars($row['work_order_code'] ?? '-') ?> · <?= htmlspecialchars(reports_display_date($row['payment_date'])) ?></div>
											</td>
											<td><?= htmlspecialchars($row['payment_status']) ?></td>
											<td class="text-right"><?= reports_money($row['amount_paid']) ?></td>
											<td class="text-right"><?= reports_money($row['balance_due']) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($recent_payments)): ?>
										<tr><td colspan="4" class="report-empty">No recent payments found.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>

						<div>
							<h6 class="report-subtable-title">Recent Refunds</h6>
							<table class="report-table">
								<thead>
									<tr>
										<th>Payment</th>
										<th>Method</th>
										<th class="text-right">Amount</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($refund_rows as $row): ?>
										<tr>
											<td>
												<?= htmlspecialchars($row['payment_code']) ?>
												<div class="report-muted"><?= htmlspecialchars($row['work_order_code'] ?? '-') ?> · <?= htmlspecialchars(reports_display_date($row['refunded_at'], 'M d, Y h:i A')) ?></div>
											</td>
											<td><?= htmlspecialchars($row['refund_method'] ?: 'Unspecified') ?></td>
											<td class="text-right"><?= reports_money($row['refund_amount']) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($refund_rows)): ?>
										<tr><td colspan="3" class="report-empty">No refunds found.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="reports-section">
				<div class="reports-section-title">
					<div>
						<h5>Customer Reports</h5>
						<p>New customers, repeat customers, top customers, and intake trend.</p>
					</div>
					<span class="report-pill"><?= reports_number($total_customers) ?> customers</span>
				</div>
				<div class="reports-section-body">
					<div class="reports-two-col">
						<div>
							<h6 class="report-subtable-title">Top Customers</h6>
							<table class="report-table">
								<thead>
									<tr>
										<th>Customer</th>
										<th class="text-right">Orders</th>
										<th class="text-right">Net Paid</th>
										<th>Latest</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($top_customers as $row): ?>
										<tr>
											<td>
												<?= htmlspecialchars($row['customer_name'] ?: 'Customer') ?>
												<div class="report-muted"><?= htmlspecialchars($row['contact_num'] ?: '-') ?></div>
											</td>
											<td class="text-right"><?= reports_number($row['work_order_count']) ?></td>
											<td class="text-right"><?= reports_money($row['net_paid']) ?></td>
											<td><?= htmlspecialchars(reports_display_date($row['latest_order'])) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($top_customers)): ?>
										<tr><td colspan="4" class="report-empty">No customer activity found.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>

						<div>
							<h6 class="report-subtable-title">Customer Intake by Month</h6>
							<table class="report-table">
								<thead>
									<tr>
										<th>Month</th>
										<th class="text-right">New Customers</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($customer_intake_rows as $row): ?>
										<tr>
											<td><?= htmlspecialchars($row['month_key']) ?></td>
											<td class="text-right"><?= reports_number($row['total']) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($customer_intake_rows)): ?>
										<tr><td colspan="2" class="report-empty">No customer intake data found.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="reports-section">
				<div class="reports-section-title">
					<div>
						<h5>Inventory &amp; Parts Reports</h5>
						<p>Stock health, inventory value, stock movement, used parts, ordered parts, and category value.</p>
					</div>
					<span class="report-pill <?= $out_of_stock_items > 0 ? 'is-danger' : ($low_stock_items > 0 ? 'is-warning' : 'is-success') ?>"><?= reports_number($low_stock_items + $out_of_stock_items) ?> alerts</span>
				</div>
				<div class="reports-section-body">
					<div class="reports-grid">
						<div class="report-metric">
							<small>Stock In</small>
							<strong><?= reports_number($stock_in_units) ?></strong>
							<span><?= reports_money($stock_in_cost) ?> capital value</span>
						</div>
						<div class="report-metric">
							<small>Stock Out</small>
							<strong><?= reports_number($stock_out_units) ?></strong>
							<span><?= reports_number($purchased_part_units) ?> purchased part units</span>
						</div>
						<div class="report-metric">
							<small>Ordered Parts</small>
							<strong><?= reports_number($ordered_part_units) ?></strong>
							<span><?= reports_money($ordered_part_spend) ?> total ordered</span>
						</div>
						<div class="report-metric">
							<small>Customer Parts</small>
							<strong><?= reports_number($customer_parts_count) ?></strong>
							<span>Customer-provided component units</span>
						</div>
					</div>

					<div class="reports-two-col">
						<div>
							<h6 class="report-subtable-title">Low Stock Watchlist</h6>
							<table class="report-table">
								<thead>
									<tr>
										<th>Item</th>
										<th>Status</th>
										<th class="text-right">Qty</th>
										<th class="text-right">Value</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($low_stock_rows as $row): ?>
										<tr>
											<td>
												<?= htmlspecialchars($row['product_code']) ?>
												<div class="report-muted"><?= htmlspecialchars(trim(($row['brand_name'] ?? '') . ' ' . ($row['model'] ?? ''))) ?></div>
											</td>
											<td><span class="report-pill <?= ((int) $row['quantity'] <= 0) ? 'is-danger' : 'is-warning' ?>"><?= htmlspecialchars($row['status'] ?: (((int) $row['quantity'] <= 0) ? 'Out of Stock' : 'Low Stock')) ?></span></td>
											<td class="text-right"><?= reports_number($row['quantity']) ?></td>
											<td class="text-right"><?= reports_money(((float) $row['quantity']) * ((float) $row['average_price'])) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($low_stock_rows)): ?>
										<tr><td colspan="4" class="report-empty">No low stock items found.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>

						<div>
							<h6 class="report-subtable-title">Most Used Inventory Parts</h6>
							<table class="report-table">
								<thead>
									<tr>
										<th>Item</th>
										<th class="text-right">Used</th>
										<th class="text-right">Work Orders</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($most_used_parts as $row): ?>
										<tr>
											<td>
												<?= htmlspecialchars($row['product_code']) ?>
												<div class="report-muted"><?= htmlspecialchars(trim(($row['brand_name'] ?? '') . ' ' . ($row['model'] ?? ''))) ?></div>
											</td>
											<td class="text-right"><?= reports_number($row['used_quantity']) ?></td>
											<td class="text-right"><?= reports_number($row['work_order_count']) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($most_used_parts)): ?>
										<tr><td colspan="3" class="report-empty">No stock-out usage found.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>

					<div class="reports-two-col" style="margin-top: 22px;">
						<div>
							<h6 class="report-subtable-title">Inventory Value by Category</h6>
							<table class="report-table">
								<thead>
									<tr>
										<th>Category</th>
										<th class="text-right">Items</th>
										<th class="text-right">Units</th>
										<th class="text-right">Value</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($inventory_category_rows as $row): ?>
										<tr>
											<td><?= htmlspecialchars($row['category_name']) ?></td>
											<td class="text-right"><?= reports_number($row['item_count']) ?></td>
											<td class="text-right"><?= reports_number($row['units_on_hand']) ?></td>
											<td class="text-right"><?= reports_money($row['inventory_value']) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($inventory_category_rows)): ?>
										<tr><td colspan="4" class="report-empty">No category data found.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>

						<div>
							<h6 class="report-subtable-title">Ordered Parts Cost</h6>
							<table class="report-table">
								<thead>
									<tr>
										<th>Part</th>
										<th class="text-right">Qty</th>
										<th class="text-right">Cost</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($ordered_parts_rows as $row): ?>
										<tr>
											<td>
												<?= htmlspecialchars($row['part_name']) ?>
												<div class="report-muted"><?= htmlspecialchars($row['brand']) ?> · <?= htmlspecialchars($row['category']) ?></div>
											</td>
											<td class="text-right"><?= reports_number($row['quantity']) ?></td>
											<td class="text-right"><?= reports_money($row['total_cost']) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($ordered_parts_rows)): ?>
										<tr><td colspan="3" class="report-empty">No ordered parts found.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="reports-section">
				<div class="reports-section-title">
					<div>
						<h5>System &amp; Activity Reports</h5>
						<p>User, technician, category, unit type, and activity log coverage.</p>
					</div>
					<span class="report-pill"><?= reports_number($activity_count) ?> activity logs</span>
				</div>
				<div class="reports-section-body">
					<div class="reports-grid">
						<div class="report-metric">
							<small>Users</small>
							<strong><?= reports_number($total_users) ?></strong>
							<span><?= reports_number($total_technicians) ?> technicians</span>
						</div>
						<div class="report-metric">
							<small>Categories</small>
							<strong><?= reports_number($total_categories) ?></strong>
							<span>Inventory categories</span>
						</div>
						<div class="report-metric">
							<small>Unit Types</small>
							<strong><?= reports_number($total_unit_types) ?></strong>
							<span>Repair intake options</span>
						</div>
						<div class="report-metric">
							<small>Cancelled Orders</small>
							<strong><?= reports_number($cancelled_period) ?></strong>
							<span>Cancelled during range</span>
						</div>
					</div>

					<h6 class="report-subtable-title">Activity Log Summary</h6>
					<table class="report-table">
						<thead>
							<tr>
								<th>Action</th>
								<th>User</th>
								<th class="text-right">Count</th>
								<th>Latest Activity</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($activity_rows as $row): ?>
								<tr>
									<td><?= htmlspecialchars($row['action']) ?></td>
									<td><?= htmlspecialchars($row['user_name']) ?></td>
									<td class="text-right"><?= reports_number($row['total']) ?></td>
									<td><?= htmlspecialchars(reports_display_date($row['latest_activity'], 'M d, Y h:i A')) ?></td>
								</tr>
							<?php endforeach; ?>
							<?php if (empty($activity_rows)): ?>
								<tr><td colspan="4" class="report-empty">No activity logs found.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="reports-section">
				<div class="reports-section-title">
					<div>
						<h5>Recommended Next Reports</h5>
						<p>Higher-value reporting ideas that would make decision-making sharper.</p>
					</div>
				</div>
				<div class="reports-section-body">
					<div class="report-recommendations">
						<div class="report-insight">
							<small>Repair Operations</small>
							<strong>SLA and turnaround targets</strong>
							<span>Track average days from intake to diagnosis, diagnosis to repair, and repair to release per technician and unit type.</span>
						</div>
						<div class="report-insight">
							<small>Finance</small>
							<strong>Profit margin by work order</strong>
							<span>Compare labor, diagnostic fees, inventory cost, ordered part cost, discounts, refunds, and collected revenue.</span>
						</div>
						<div class="report-insight">
							<small>Inventory</small>
							<strong>Reorder forecast</strong>
							<span>Use stock-out velocity and lead time to recommend reorder points before fast-moving parts run out.</span>
						</div>
						<div class="report-insight">
							<small>Customers</small>
							<strong>Repeat customer and device history</strong>
							<span>Show repeat repairs by customer, device brand/model, issue type, and revenue contribution.</span>
						</div>
						<div class="report-insight">
							<small>Payments</small>
							<strong>Aging receivables</strong>
							<span>Group unpaid balances by 0-7, 8-14, 15-30, and 30+ days so collections can be prioritized.</span>
						</div>
						<div class="report-insight">
							<small>Quality</small>
							<strong>Return or rework tracking</strong>
							<span>Add a rework flag to work orders so the shop can measure repair quality and recurring issues.</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include 'footer.php'; ?>
