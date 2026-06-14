<?php
	include 'src/db/connection.php';
	include 'auth_check.php';

	if (($_SESSION['role'] ?? '') !== 'Administrator') {
		audit_authorization_failure($conn, 'reports page view');
		$_SESSION['dialog_flash'] = [
			'type' => 'error',
			'title' => 'Reports Restricted',
			'message' => 'Only administrators can access system reports.'
		];
		header('Location: index.php');
		exit();
	}

	require_once __DIR__ . '/src/handlers/payment_schema.php';
	require_once __DIR__ . '/src/handlers/work_order_schema.php';
	require_once __DIR__ . '/src/handlers/inventory_transaction_schema.php';
	require_once __DIR__ . '/src/handlers/ordered_part_schema.php';
	require_once __DIR__ . '/src/handlers/db_helpers.php';

	$report_warnings = [];

	function reports_table_exists(mysqli $conn, string $table): bool {
		$statement = mysqli_prepare(
			$conn,
			"SELECT COUNT(*) AS total
			 FROM INFORMATION_SCHEMA.TABLES
			 WHERE TABLE_SCHEMA = DATABASE()
			 AND TABLE_NAME = ?"
		);

		if (!$statement) {
			return false;
		}

		mysqli_stmt_bind_param($statement, "s", $table);
		mysqli_stmt_execute($statement);
		$result = mysqli_stmt_get_result($statement);
		$row = $result ? mysqli_fetch_assoc($result) : null;
		mysqli_stmt_close($statement);

		return (int) ($row['total'] ?? 0) > 0;
	}

	function reports_safe_date($date): string {
		$date = trim((string) $date);
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
	}

	function reports_allowed_date_column(string $column): string {
		$allowed_columns = [
			'request_date',
			'w.request_date',
			'completion_date',
			'w.completion_date',
			'date',
			'c.date',
			'COALESCE(p.date, DATE(p.created_at))',
			'DATE(pt.transaction_at)',
			'sit.stock_in_date',
			'sot.stock_out_date',
			'pi.date',
			'DATE(op.created_at)',
			'DATE(al.created_at)',
			'DATE(r.refunded_at)'
		];

		if (!in_array($column, $allowed_columns, true)) {
			throw new InvalidArgumentException('Unsupported report date filter column.');
		}

		return $column;
	}

	function reports_date_condition(string $column, string $date_from, string $date_to): array {
		$column = reports_allowed_date_column($column);
		$conditions = [];
		$types = '';
		$params = [];

		if ($date_from !== '') {
			$conditions[] = "$column >= ?";
			$types .= 's';
			$params[] = $date_from;
		}

		if ($date_to !== '') {
			$conditions[] = "$column <= ?";
			$types .= 's';
			$params[] = $date_to;
		}

		return [
			'sql' => $conditions ? implode(' AND ', $conditions) : '1',
			'types' => $types,
			'params' => $params
		];
	}

	function reports_params(array ...$filters): array {
		$types = '';
		$params = [];

		foreach ($filters as $filter) {
			$types .= $filter['types'] ?? '';
			foreach (($filter['params'] ?? []) as $param) {
				$params[] = $param;
			}
		}

		return [$types, $params];
	}

	function reports_scalar(mysqli $conn, string $sql, string $field = 'total', $fallback = 0, string $types = '', array $params = []) {
		global $report_warnings;
		$statement = mysqli_prepare($conn, $sql);

		if (!$statement) {
			$report_warnings[] = mysqli_error($conn);
			return $fallback;
		}

		db_bind_params($statement, $types, $params);

		if (!mysqli_stmt_execute($statement)) {
			$report_warnings[] = mysqli_stmt_error($statement);
			mysqli_stmt_close($statement);
			return $fallback;
		}

		$result = mysqli_stmt_get_result($statement);
		if (!$result) {
			$report_warnings[] = mysqli_stmt_error($statement);
			mysqli_stmt_close($statement);
			return $fallback;
		}

		$row = mysqli_fetch_assoc($result);
		mysqli_stmt_close($statement);

		return $row[$field] ?? $fallback;
	}

	function reports_row(mysqli $conn, string $sql, array $fallback = [], string $types = '', array $params = []): array {
		global $report_warnings;
		$statement = mysqli_prepare($conn, $sql);

		if (!$statement) {
			$report_warnings[] = mysqli_error($conn);
			return $fallback;
		}

		db_bind_params($statement, $types, $params);

		if (!mysqli_stmt_execute($statement)) {
			$report_warnings[] = mysqli_stmt_error($statement);
			mysqli_stmt_close($statement);
			return $fallback;
		}

		$result = mysqli_stmt_get_result($statement);
		if (!$result) {
			$report_warnings[] = mysqli_stmt_error($statement);
			mysqli_stmt_close($statement);
			return $fallback;
		}

		$row = mysqli_fetch_assoc($result) ?: $fallback;
		mysqli_stmt_close($statement);

		return $row;
	}

	function reports_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array {
		global $report_warnings;
		$rows = [];
		$statement = mysqli_prepare($conn, $sql);

		if (!$statement) {
			$report_warnings[] = mysqli_error($conn);
			return $rows;
		}

		db_bind_params($statement, $types, $params);

		if (!mysqli_stmt_execute($statement)) {
			$report_warnings[] = mysqli_stmt_error($statement);
			mysqli_stmt_close($statement);
			return $rows;
		}

		$result = mysqli_stmt_get_result($statement);
		if (!$result) {
			$report_warnings[] = mysqli_stmt_error($statement);
			mysqli_stmt_close($statement);
			return $rows;
		}

		while ($row = mysqli_fetch_assoc($result)) {
			$rows[] = $row;
		}
		mysqli_stmt_close($statement);

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

	function reports_activity_summary(?string $work_order_code, string $action): string {
		$action = trim(preg_replace('/\s+/', ' ', $action));
		$work_order_code = trim((string) $work_order_code);

		if (preg_match('/^Changed status from .+ to (.+)$/', $action, $matches)) {
			$summary = 'marked as ' . trim($matches[1]);
			return $work_order_code !== '' ? $work_order_code . ' ' . $summary : ucfirst($summary);
		}

		if (preg_match('/^Reassigned technician from .+ to ([^.]+)(?:\. Reason:.*)?$/', $action, $matches)) {
			$summary = 'reassigned to ' . trim($matches[1]);
			return $work_order_code !== '' ? $work_order_code . ' ' . $summary : ucfirst($summary);
		}

		if (preg_match('/^Updated .+ status to (.+)$/', $action, $matches)) {
			$summary = 'payment marked as ' . trim($matches[1]);
			return $work_order_code !== '' ? $work_order_code . ' ' . $summary : ucfirst($summary);
		}

		if ($action === 'Released work order after full payment') {
			$summary = 'released after full payment';
			return $work_order_code !== '' ? $work_order_code . ' ' . $summary : ucfirst($summary);
		}

		return $work_order_code !== '' ? $work_order_code . ': ' . $action : $action;
	}

	$date_from = reports_safe_date($_GET['date_from'] ?? '');
	$date_to = reports_safe_date($_GET['date_to'] ?? '');

	if ($date_from !== '' && $date_to !== '' && $date_from > $date_to) {
		[$date_from, $date_to] = [$date_to, $date_from];
	}

	log_activity($conn, 'Viewed reports page (' . reports_range_label($date_from, $date_to) . ')');

	try {
		ensure_work_order_priority_column($conn);
		ensure_payment_detail_columns($conn);
		refresh_payment_summaries($conn);
		ensure_inventory_transaction_table($conn);
		backfill_inventory_transactions_from_items($conn);
		sync_all_items_from_inventory_transactions($conn);
		ensure_ordered_parts_table($conn);
	} catch (Exception $e) {
		$report_warnings[] = $e->getMessage();
	}

	$work_order_date_filter = reports_date_condition('request_date', $date_from, $date_to);
	$work_order_alias_date_filter = reports_date_condition('w.request_date', $date_from, $date_to);
	$completion_date_filter = reports_date_condition('completion_date', $date_from, $date_to);
	$completion_alias_date_filter = reports_date_condition('w.completion_date', $date_from, $date_to);
	$client_date_filter = reports_date_condition('date', $date_from, $date_to);
	$payment_date_filter = reports_date_condition('COALESCE(p.date, DATE(p.created_at))', $date_from, $date_to);
	$payment_transaction_date_filter = reports_date_condition('DATE(pt.transaction_at)', $date_from, $date_to);
	$stock_in_date_filter = reports_date_condition('sit.stock_in_date', $date_from, $date_to);
	$stock_out_date_filter = reports_date_condition('sot.stock_out_date', $date_from, $date_to);
	$purchased_part_date_filter = reports_date_condition('pi.date', $date_from, $date_to);
	$ordered_part_date_filter = reports_date_condition('DATE(op.created_at)', $date_from, $date_to);
	$activity_date_filter = reports_date_condition('DATE(al.created_at)', $date_from, $date_to);
	$refund_date_filter = reports_date_condition('DATE(r.refunded_at)', $date_from, $date_to);
	$client_alias_date_filter = reports_date_condition('c.date', $date_from, $date_to);

	$work_order_date_where = $work_order_date_filter['sql'];
	$work_order_alias_date_where = $work_order_alias_date_filter['sql'];
	$completion_date_where = $completion_date_filter['sql'];
	$completion_alias_date_where = $completion_alias_date_filter['sql'];
	$client_date_where = $client_date_filter['sql'];
	$payment_date_where = $payment_date_filter['sql'];
	$payment_transaction_date_where = $payment_transaction_date_filter['sql'];
	$stock_in_date_where = $stock_in_date_filter['sql'];
	$stock_out_date_where = $stock_out_date_filter['sql'];
	$purchased_part_date_where = $purchased_part_date_filter['sql'];
	$ordered_part_date_where = $ordered_part_date_filter['sql'];
	$activity_date_where = $activity_date_filter['sql'];
	$refund_date_where = $refund_date_filter['sql'];
	$client_alias_date_where = $client_alias_date_filter['sql'];

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
	$period_workorders = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM work_order WHERE $work_order_date_where", 'total', 0, $work_order_date_filter['types'], $work_order_date_filter['params']);
	$open_workorders = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM work_order WHERE status NOT IN ('Released', 'Cancelled')");
	$unassigned_open = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM work_order WHERE technician_id IS NULL AND status NOT IN ('Released', 'Cancelled')");
	$aged_open = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM work_order WHERE status NOT IN ('Released', 'Cancelled') AND request_date <= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
	$completed_period = (int) reports_scalar(
		$conn,
		"SELECT COUNT(*) AS total
		 FROM work_order
		 WHERE completion_date IS NOT NULL
		 AND status IN ('Repaired', 'Ready for Release', 'Released')
		 AND $completion_date_where",
		'total',
		0,
		$completion_date_filter['types'],
		$completion_date_filter['params']
	);
	$cancelled_period = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM work_order WHERE status = 'Cancelled' AND $work_order_date_where", 'total', 0, $work_order_date_filter['types'], $work_order_date_filter['params']);
	$avg_cycle_days = (float) reports_scalar(
		$conn,
		"SELECT COALESCE(AVG(DATEDIFF(completion_date, request_date)), 0) AS total
		 FROM work_order
		 WHERE completion_date IS NOT NULL
		 AND completion_date >= request_date
		 AND status IN ('Repaired', 'Ready for Release', 'Released')
		 AND $completion_date_where",
		'total',
		0,
		$completion_date_filter['types'],
		$completion_date_filter['params']
	);

	$total_customers = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM client");
	$new_customers = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM client WHERE $client_date_where", 'total', 0, $client_date_filter['types'], $client_date_filter['params']);
	$repeat_customers = (int) reports_scalar(
		$conn,
		"SELECT COUNT(*) AS total
		 FROM (
			SELECT c.id
			FROM client c
			INNER JOIN work_order w ON w.client_id = c.id AND $work_order_alias_date_where
			GROUP BY c.id
			HAVING COUNT(w.id) > 1
		 ) repeat_clients",
		'total',
		0,
		$work_order_alias_date_filter['types'],
		$work_order_alias_date_filter['params']
	);

	$payment_summary = reports_row(
		$conn,
		"SELECT
			COALESCE(SUM(CASE WHEN pt.transaction_type = 'payment' THEN pt.amount ELSE 0 END), 0) AS collected,
			COALESCE(SUM(CASE WHEN pt.transaction_type = 'refund' THEN pt.amount ELSE 0 END), 0) AS refunded
		 FROM payment_transaction pt
		 WHERE $payment_transaction_date_where",
		['collected' => 0, 'refunded' => 0],
		$payment_transaction_date_filter['types'],
		$payment_transaction_date_filter['params']
	);
	$collected_total = (float) $payment_summary['collected'];
	$refunded_total = (float) $payment_summary['refunded'];
	$net_revenue = max(0, $collected_total - $refunded_total);
	$gross_billed = (float) reports_scalar($conn, "SELECT COALESCE(SUM(p.total_amount), 0) AS total FROM payments p WHERE $payment_date_where", 'total', 0, $payment_date_filter['types'], $payment_date_filter['params']);
	$discount_total = (float) reports_scalar($conn, "SELECT COALESCE(SUM(p.discount_amount), 0) AS total FROM payments p WHERE $payment_date_where", 'total', 0, $payment_date_filter['types'], $payment_date_filter['params']);
	$outstanding_balance = (float) reports_scalar($conn, "SELECT COALESCE(SUM($payment_balance_sql), 0) AS total FROM payments p WHERE $payment_date_where", 'total', 0, $payment_date_filter['types'], $payment_date_filter['params']);
	$pending_payments = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM payments p WHERE $payment_date_where AND $payment_balance_sql > 0", 'total', 0, $payment_date_filter['types'], $payment_date_filter['params']);

	$total_items = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM items");
	$low_stock_items = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM items WHERE quantity > 0 AND quantity < 10");
	$out_of_stock_items = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM items WHERE quantity <= 0");
	$inventory_value = (float) reports_scalar($conn, "SELECT COALESCE(SUM(quantity * average_price), 0) AS total FROM items");
	$stock_in_units = (int) reports_scalar($conn, "SELECT COALESCE(SUM(sit.stock_in), 0) AS total FROM stock_in_transaction sit WHERE $stock_in_date_where", 'total', 0, $stock_in_date_filter['types'], $stock_in_date_filter['params']);
	$stock_in_cost = (float) reports_scalar($conn, "SELECT COALESCE(SUM(sit.capital * sit.stock_in), 0) AS total FROM stock_in_transaction sit WHERE $stock_in_date_where", 'total', 0, $stock_in_date_filter['types'], $stock_in_date_filter['params']);
	$stock_out_units = (int) reports_scalar($conn, "SELECT COALESCE(SUM(sot.quantity), 0) AS total FROM stock_out_transaction sot WHERE $stock_out_date_where", 'total', 0, $stock_out_date_filter['types'], $stock_out_date_filter['params']);
	$purchased_part_units = (int) reports_scalar($conn, "SELECT COALESCE(SUM(pi.quantity), 0) AS total FROM purchased_item pi WHERE $purchased_part_date_where", 'total', 0, $purchased_part_date_filter['types'], $purchased_part_date_filter['params']);
	$ordered_part_spend = (float) reports_scalar($conn, "SELECT COALESCE(SUM(op.quantity * op.price), 0) AS total FROM ordered_parts op WHERE $ordered_part_date_where", 'total', 0, $ordered_part_date_filter['types'], $ordered_part_date_filter['params']);
	$ordered_part_units = (int) reports_scalar($conn, "SELECT COALESCE(SUM(op.quantity), 0) AS total FROM ordered_parts op WHERE $ordered_part_date_where", 'total', 0, $ordered_part_date_filter['types'], $ordered_part_date_filter['params']);

	$total_users = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM users");
	$total_technicians = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'Technician'");
	$total_categories = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM item_category");
	$total_unit_types = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM unit_type");
	$activity_count = (int) reports_scalar($conn, "SELECT COUNT(*) AS total FROM activity_logs al WHERE $activity_date_where", 'total', 0, $activity_date_filter['types'], $activity_date_filter['params']);
	$customer_parts_count = (int) reports_scalar($conn, "SELECT COALESCE(SUM(quantity), 0) AS total FROM customer_provided_component");

	$work_order_status_rows = reports_rows(
		$conn,
		"SELECT CASE WHEN status = 'Ready for Release' THEN 'Repaired' ELSE status END AS status, COUNT(*) AS total
		 FROM work_order
		 WHERE $work_order_date_where
		 GROUP BY CASE WHEN status = 'Ready for Release' THEN 'Repaired' ELSE status END
		 ORDER BY total DESC, status ASC",
		$work_order_date_filter['types'],
		$work_order_date_filter['params']
	);
	$status_total = array_sum(array_map(fn($row) => (int) $row['total'], $work_order_status_rows));

	$work_order_priority_rows = reports_rows(
		$conn,
		"SELECT COALESCE(NULLIF(priority, ''), 'Unspecified') AS priority, COUNT(*) AS total
		 FROM work_order
		 WHERE $work_order_date_where
		 GROUP BY COALESCE(NULLIF(priority, ''), 'Unspecified')
		 ORDER BY total DESC, priority ASC",
		$work_order_date_filter['types'],
		$work_order_date_filter['params']
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
		 LIMIT 8",
		$work_order_alias_date_filter['types'],
		$work_order_alias_date_filter['params']
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
		 ORDER BY total DESC, status ASC",
		$payment_date_filter['types'],
		$payment_date_filter['params']
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
		 ORDER BY total DESC, transaction_count DESC",
		$payment_transaction_date_filter['types'],
		$payment_transaction_date_filter['params']
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
		 LIMIT 8",
		$payment_date_filter['types'],
		$payment_date_filter['params']
	);

	$refund_rows = reports_rows(
		$conn,
		"SELECT p.payment_code, wo.code AS work_order_code, r.refund_amount, r.refund_method, r.reason, r.refunded_at
		 FROM refunds r
		 INNER JOIN payments p ON p.id = r.payment_id
		 LEFT JOIN work_order wo ON wo.id = p.work_order_id
		 WHERE $refund_date_where
		 ORDER BY r.refunded_at DESC, r.id DESC
		 LIMIT 8",
		$refund_date_filter['types'],
		$refund_date_filter['params']
	);

	$top_customers = reports_rows(
		$conn,
		"SELECT c.id,
				TRIM(CONCAT(c.first_name, ' ', c.last_name)) AS customer_name,
				COUNT(w.id) AS work_order_count,
				MAX(w.request_date) AS latest_order,
				COALESCE(SUM(ps.net_paid), 0) AS net_paid
		 FROM client c
		 INNER JOIN work_order w ON w.client_id = c.id AND $work_order_alias_date_where
		 LEFT JOIN ($payment_net_by_work_order_sql) ps ON ps.work_order_id = w.id
		 GROUP BY c.id, c.first_name, c.last_name
		 ORDER BY net_paid DESC, work_order_count DESC, latest_order DESC
		 LIMIT 8",
		$work_order_alias_date_filter['types'],
		$work_order_alias_date_filter['params']
	);

	$customer_intake_rows = reports_rows(
		$conn,
		"SELECT DATE_FORMAT(c.date, '%Y-%m') AS month_key, COUNT(*) AS total
		 FROM client c
		 WHERE $client_alias_date_where
		 GROUP BY DATE_FORMAT(c.date, '%Y-%m')
		 ORDER BY month_key DESC
		 LIMIT 6",
		$client_alias_date_filter['types'],
		$client_alias_date_filter['params']
	);

	[$technician_date_types, $technician_date_params] = reports_params(
		$work_order_alias_date_filter,
		$completion_alias_date_filter,
		$completion_alias_date_filter
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
		 ORDER BY current_open DESC, completed_period DESC, technician_name ASC",
		$technician_date_types,
		$technician_date_params
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
		 LIMIT 8",
		$stock_out_date_filter['types'],
		$stock_out_date_filter['params']
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
		 LIMIT 8",
		$ordered_part_date_filter['types'],
		$ordered_part_date_filter['params']
	);

	$activity_rows = reports_rows(
		$conn,
		"SELECT al.action,
				al.created_at,
				wo.code AS work_order_code,
				COALESCE(
					NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''),
					NULLIF(u.username, ''),
					CONCAT('Deleted user #', al.user_id)
				) AS user_name
		 FROM activity_logs al
		 LEFT JOIN users u ON u.id = al.user_id
		 LEFT JOIN work_order wo ON wo.id = al.work_order_id
		 WHERE $activity_date_where
		 ORDER BY al.created_at DESC, al.id DESC
		 LIMIT 12",
		$activity_date_filter['types'],
		$activity_date_filter['params']
	);

	$customer_intake_chart_rows = array_reverse($customer_intake_rows);
	$report_chart_data = [
		'workStatus' => [
			'labels' => array_map(fn($row) => (string) ($row['status'] ?: 'Unspecified'), $work_order_status_rows),
			'data' => array_map(fn($row) => (int) $row['total'], $work_order_status_rows),
		],
		'paymentMethods' => [
			'labels' => array_map(fn($row) => (string) ($row['method'] ?: 'Unspecified'), $payment_method_rows),
			'data' => array_map(fn($row) => round((float) $row['total'], 2), $payment_method_rows),
		],
		'customerIntake' => [
			'labels' => array_map(fn($row) => (string) $row['month_key'], $customer_intake_chart_rows),
			'data' => array_map(fn($row) => (int) $row['total'], $customer_intake_chart_rows),
		],
		'inventoryUsage' => [
			'labels' => array_map(fn($row) => trim(($row['brand_name'] ?? '') . ' ' . ($row['model'] ?? '')) ?: (string) $row['product_code'], $most_used_parts),
			'data' => array_map(fn($row) => (int) $row['used_quantity'], $most_used_parts),
		],
	];

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

	.reports-export-form {
		display: grid;
		grid-template-columns: repeat(4, minmax(170px, 1fr));
		gap: 12px;
		align-items: end;
	}

	.reports-export-form .form-group {
		margin-bottom: 0;
	}

	.reports-export-form label {
		font-size: 12px;
		font-weight: 700;
		color: #4b5563;
		margin-bottom: 4px;
	}

	.reports-export-form .btn {
		min-height: 38px;
	}

	.reports-export-wide {
		grid-column: span 2;
	}

	.report-export-status {
		position: fixed;
		inset: 0;
		z-index: 3200;
		display: none;
		align-items: center;
		justify-content: center;
		padding: 20px;
		background: rgba(15, 23, 42, 0.52);
	}

	.report-export-status.show {
		display: flex;
	}

	.report-export-dialog {
		width: min(420px, 100%);
		background: #fff;
		border-radius: 8px;
		box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
		padding: 24px;
		text-align: center;
	}

	.report-export-icon {
		width: 54px;
		height: 54px;
		margin: 0 auto 16px;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		background: #eef2ff;
		color: #3730a3;
		font-weight: 800;
	}

	.report-export-status.is-loading .report-export-icon {
		border: 4px solid #dbeafe;
		border-top-color: #2563eb;
		background: transparent;
		color: transparent;
		animation: reportExportSpin 0.8s linear infinite;
	}

	.report-export-status.is-success .report-export-icon {
		background: #dcfce7;
		color: #166534;
	}

	.report-export-status.is-error .report-export-icon {
		background: #fee2e2;
		color: #991b1b;
	}

	.report-export-dialog h5 {
		margin: 0 0 8px;
		font-weight: 700;
		color: #111827;
	}

	.report-export-dialog p {
		margin: 0;
		color: #6b7280;
	}

	.report-export-actions {
		margin-top: 18px;
		display: none;
	}

	.report-export-status.is-success .report-export-actions,
	.report-export-status.is-error .report-export-actions {
		display: block;
	}

	@keyframes reportExportSpin {
		to {
			transform: rotate(360deg);
		}
	}

	.reports-grid {
		display: grid;
		grid-template-columns: repeat(4, minmax(0, 1fr));
		gap: 14px;
		margin-bottom: 18px;
	}

	.reports-tabs {
		margin-top: 18px;
	}

	.reports-tablist {
		display: flex;
		gap: 8px;
		overflow-x: auto;
		padding: 4px 2px 12px;
		margin-bottom: 6px;
		scrollbar-width: thin;
	}

	.reports-tab {
		border: 1px solid #dbe3ef;
		background: #fff;
		color: #4b5563;
		border-radius: 8px;
		padding: 9px 13px;
		font-weight: 700;
		white-space: nowrap;
		cursor: pointer;
		transition: color .18s ease, background-color .18s ease, border-color .18s ease, box-shadow .18s ease;
	}

	.reports-tab:hover,
	.reports-tab:focus {
		color: #111827;
		border-color: #93c5fd;
		outline: none;
		box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
	}

	.reports-tab.is-active {
		background: #2563eb;
		border-color: #2563eb;
		color: #fff;
		box-shadow: 0 8px 18px rgba(37, 99, 235, 0.2);
	}

	.reports-tab-panel {
		display: none;
	}

	.reports-tab-panel.is-active {
		display: block;
		animation: reportsTabEnter .18s ease both;
	}

	#report-panel-overview.is-active {
		display: flex;
		flex-direction: column;
	}

	#report-panel-overview > .reports-grid {
		order: 1;
	}

	#report-panel-overview > .reports-section {
		order: 2;
	}

	#report-panel-overview > .report-export-status {
		order: 3;
	}

	@keyframes reportsTabEnter {
		from {
			opacity: 0;
			transform: translateY(6px);
		}

		to {
			opacity: 1;
			transform: translateY(0);
		}
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

	.reports-chart-grid {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 18px;
		margin-bottom: 22px;
	}

	.reports-mini-grid {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 14px;
		margin-bottom: 0;
	}

	.report-chart-card {
		border: 1px solid #e5e7eb;
		border-radius: 8px;
		padding: 16px;
		background: #fff;
		min-width: 0;
	}

	.report-chart-head {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 12px;
		margin-bottom: 12px;
	}

	.report-chart-head h6 {
		margin: 0 0 3px;
		font-weight: 700;
		color: #111827;
	}

	.report-chart-head p {
		margin: 0;
		color: #6b7280;
		font-size: 12px;
	}

	.report-chart-canvas {
		position: relative;
		height: 270px;
	}

	.report-chart-canvas.is-short {
		height: 230px;
	}

	.report-chart-canvas canvas {
		width: 100% !important;
		height: 100% !important;
	}

	.report-chart-empty {
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 180px;
		color: #6b7280;
		background: #f8fafc;
		border-radius: 8px;
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

	.report-table-toolbar {
		display: flex;
		justify-content: flex-end;
		margin-bottom: 10px;
	}

	.report-table-search {
		width: min(260px, 100%);
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

	.report-insight strong {
		display: block;
		font-size: 15px;
		color: #111827;
		margin-bottom: 6px;
	}

	@media (max-width: 1200px) {
		.reports-grid,
		.reports-chart-grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}

		.reports-three-col {
			grid-template-columns: 1fr;
		}

		.reports-export-form {
			grid-template-columns: repeat(2, minmax(0, 1fr));
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
		.reports-chart-grid,
		.reports-mini-grid,
		.reports-export-form {
			grid-template-columns: 1fr;
		}

		.report-table {
			min-width: 560px;
		}

		.report-table-search {
			width: 100%;
		}

		.reports-export-wide {
			grid-column: span 1;
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
		.reports-tablist,
		.btn {
			display: none !important;
		}

		.reports-tab-panel {
			display: block !important;
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

			<div class="reports-tabs" data-report-tabs>
				<div class="reports-tablist" role="tablist" aria-label="Report sections">
					<button type="button" class="reports-tab is-active" id="report-tab-overview" role="tab" aria-selected="true" aria-controls="report-panel-overview" data-report-tab="overview">Overview</button>
					<button type="button" class="reports-tab" id="report-tab-work-orders" role="tab" aria-selected="false" aria-controls="report-panel-work-orders" data-report-tab="work-orders">Work Orders</button>
					<button type="button" class="reports-tab" id="report-tab-payments" role="tab" aria-selected="false" aria-controls="report-panel-payments" data-report-tab="payments">Payments</button>
					<button type="button" class="reports-tab" id="report-tab-customers" role="tab" aria-selected="false" aria-controls="report-panel-customers" data-report-tab="customers">Customers</button>
					<button type="button" class="reports-tab" id="report-tab-inventory" role="tab" aria-selected="false" aria-controls="report-panel-inventory" data-report-tab="inventory">Inventory</button>
					<button type="button" class="reports-tab" id="report-tab-activity" role="tab" aria-selected="false" aria-controls="report-panel-activity" data-report-tab="activity">Activity</button>
				</div>

				<section class="reports-tab-panel is-active" id="report-panel-overview" role="tabpanel" aria-labelledby="report-tab-overview" data-report-panel="overview">
			<div class="reports-section">
				<div class="reports-section-title">
					<div>
						<h5>CSV Export</h5>
						<p>Work orders, payments, customers, revenue, and inventory.</p>
					</div>
					<span class="report-pill">CSV</span>
				</div>
				<div class="reports-section-body">
					<form method="GET" action="export-reports.php" class="reports-export-form" id="reportsExportForm" data-no-page-skeleton>
						<div class="form-group">
							<label for="export_type">Data Set</label>
							<select class="form-control form-control-sm" id="export_type" name="type">
								<option value="work_orders">Work Orders</option>
								<option value="payments">Payments</option>
								<option value="customers">Customers</option>
								<option value="financial">Financial Report</option>
								<option value="inventory">Inventory</option>
							</select>
						</div>
						<div class="form-group">
							<label for="export_scope">Records</label>
							<select class="form-control form-control-sm" id="export_scope" name="scope">
								<option value="filtered">Filtered Records</option>
								<option value="all">All Records</option>
							</select>
						</div>
						<div class="form-group">
							<label for="export_date_from">From</label>
							<input type="date" class="form-control form-control-sm" id="export_date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
						</div>
						<div class="form-group">
							<label for="export_date_to">To</label>
							<input type="date" class="form-control form-control-sm" id="export_date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
						</div>
						<div class="form-group">
							<label for="export_work_order_status">Work Order Status</label>
							<select class="form-control form-control-sm" id="export_work_order_status" name="work_order_status">
								<option value="">All Statuses</option>
								<option value="Pending">Pending</option>
								<option value="Diagnosing">Diagnosing</option>
								<option value="Waiting for Parts">Waiting for Parts</option>
								<option value="In Progress">In Progress</option>
								<option value="Repaired">Repaired</option>
								<option value="Released">Released</option>
								<option value="Cancelled">Cancelled</option>
							</select>
						</div>
						<div class="form-group">
							<label for="export_technician_id">Technician</label>
							<select class="form-control form-control-sm" id="export_technician_id" name="technician_id">
								<option value="">All Technicians</option>
								<?php foreach ($technician_rows as $technician): ?>
									<option value="<?= (int) $technician['id'] ?>"><?= htmlspecialchars($technician['technician_name'] ?: 'Technician') ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="form-group">
							<label for="export_payment_status">Payment Status</label>
							<select class="form-control form-control-sm" id="export_payment_status" name="payment_status">
								<option value="">All Payment Statuses</option>
								<option value="Paid">Paid</option>
								<option value="Partial">Partial</option>
								<option value="Unpaid">Unpaid</option>
								<option value="Partially Refunded">Partially Refunded</option>
								<option value="Refunded">Refunded</option>
							</select>
						</div>
						<div class="form-group">
							<label for="export_payment_method">Payment Method</label>
							<select class="form-control form-control-sm" id="export_payment_method" name="payment_method">
								<option value="">All Methods</option>
								<option value="Cash">Cash</option>
								<option value="GCash">GCash</option>
								<option value="Maya">Maya</option>
								<option value="Bank Transfer">Bank Transfer</option>
							</select>
						</div>
						<div class="form-group">
							<label for="export_customer_filter">Customers</label>
							<select class="form-control form-control-sm" id="export_customer_filter" name="customer_filter">
								<option value="">All Customers</option>
								<option value="unpaid_balance">With Unpaid Balance</option>
							</select>
						</div>
						<div class="form-group">
							<label for="export_inventory_filter">Inventory Stock</label>
							<select class="form-control form-control-sm" id="export_inventory_filter" name="inventory_filter">
								<option value="">All Stock</option>
								<option value="in_stock">In Stock</option>
								<option value="low_stock">Low Stock</option>
								<option value="out_of_stock">Out of Stock</option>
							</select>
						</div>
						<div class="form-group reports-export-wide">
							<button type="submit" class="btn btn-primary btn-sm" id="reportsExportButton">
								<i class="fa fa-download"></i> Export CSV
							</button>
						</div>
					</form>
				</div>
			</div>

			<div class="report-export-status" id="reportExportStatus" aria-hidden="true">
				<div class="report-export-dialog" role="dialog" aria-modal="true" aria-labelledby="reportExportTitle">
					<div class="report-export-icon" id="reportExportIcon" aria-hidden="true">OK</div>
					<h5 id="reportExportTitle">Exporting CSV</h5>
					<p id="reportExportMessage">Preparing your file...</p>
					<div class="report-export-actions">
						<button type="button" class="btn btn-primary btn-sm" id="reportExportClose">OK</button>
					</div>
				</div>
			</div>

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
				<div class="report-metric <?= $aged_open > 0 ? 'is-danger' : '' ?>">
					<small>Delayed Repairs</small>
					<strong><?= reports_number($aged_open) ?></strong>
					<span>Open 7+ days, <?= reports_number($unassigned_open) ?> unassigned open</span>
				</div>
				<div class="report-metric <?= $low_stock_items > 0 ? 'is-warning' : 'is-success' ?>">
					<small>Low Stock Items</small>
					<strong><?= reports_number($low_stock_items) ?></strong>
					<span>Items below reorder level</span>
				</div>
				<div class="report-metric <?= $out_of_stock_items > 0 ? 'is-danger' : 'is-success' ?>">
					<small>Out of Stock Items</small>
					<strong><?= reports_number($out_of_stock_items) ?></strong>
					<span>Items with zero available quantity</span>
				</div>
				<div class="report-metric">
					<small>Inventory Value</small>
					<strong><?= reports_money($inventory_value) ?></strong>
					<span><?= reports_number($total_items) ?> inventory items</span>
				</div>
			</div>
				</section>

				<section class="reports-tab-panel" id="report-panel-work-orders" role="tabpanel" aria-labelledby="report-tab-work-orders" data-report-panel="work-orders" hidden>
			<div class="reports-section">
				<div class="reports-section-title">
					<div>
						<h5>Work Order Reports</h5>
						<p>Repair volume, status mix, unit demand, priority, and technician workload.</p>
					</div>
					<span class="report-pill"><?= reports_number($period_workorders) ?> in range</span>
				</div>
				<div class="reports-section-body">
					<div class="reports-chart-grid">
						<div class="report-chart-card">
							<div class="report-chart-head">
								<div>
									<h6>Status Distribution</h6>
									<p>Share of repair statuses in the selected range.</p>
								</div>
								<span class="report-pill">Pie</span>
							</div>
							<?php if (!empty($work_order_status_rows)): ?>
								<div class="report-chart-canvas is-short">
									<canvas id="workOrderStatusChart"></canvas>
								</div>
							<?php else: ?>
								<div class="report-chart-empty">No status data found.</div>
							<?php endif; ?>
						</div>

						<div class="report-chart-card">
							<div class="report-chart-head">
								<div>
									<h6>Technician Productivity</h6>
									<p>Completed repairs remain ranked in the table below.</p>
								</div>
								<span class="report-pill"><?= reports_number($completed_period) ?> done</span>
							</div>
							<table class="report-table">
								<thead>
									<tr>
										<th>Technician</th>
										<th class="text-right">Completed</th>
										<th class="text-right">Open</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach (array_slice($technician_rows, 0, 5) as $row): ?>
										<tr>
											<td><?= htmlspecialchars($row['technician_name'] ?: 'Technician') ?></td>
											<td class="text-right"><?= reports_number($row['completed_period']) ?></td>
											<td class="text-right"><?= reports_number($row['current_open']) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($technician_rows)): ?>
										<tr><td colspan="3" class="report-empty">No technicians found.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>

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

				</section>

				<section class="reports-tab-panel" id="report-panel-payments" role="tabpanel" aria-labelledby="report-tab-payments" data-report-panel="payments" hidden>
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

					<div class="reports-chart-grid">
						<div class="report-chart-card">
							<div class="report-chart-head">
								<div>
									<h6>Collections by Method</h6>
									<p>Payment channels ranked by collected amount.</p>
								</div>
								<span class="report-pill">Bar</span>
							</div>
							<?php if (!empty($payment_method_rows)): ?>
								<div class="report-chart-canvas is-short">
									<canvas id="paymentMethodChart"></canvas>
								</div>
							<?php else: ?>
								<div class="report-chart-empty">No payment method data found.</div>
							<?php endif; ?>
						</div>

						<div class="report-chart-card">
							<div class="report-chart-head">
								<div>
									<h6>Aging Receivables</h6>
									<p>Unpaid balance watch for collection follow-up.</p>
								</div>
								<span class="report-pill <?= $outstanding_balance > 0 ? 'is-warning' : 'is-success' ?>"><?= reports_money($outstanding_balance) ?></span>
							</div>
							<div class="reports-mini-grid">
								<div class="report-metric <?= $pending_payments > 0 ? 'is-warning' : 'is-success' ?>">
									<small>Open Balances</small>
									<strong><?= reports_number($pending_payments) ?></strong>
									<span>Payment records</span>
								</div>
								<div class="report-metric">
									<small>Discounts</small>
									<strong><?= reports_money($discount_total) ?></strong>
									<span>Applied in range</span>
								</div>
							</div>
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

				</section>

				<section class="reports-tab-panel" id="report-panel-customers" role="tabpanel" aria-labelledby="report-tab-customers" data-report-panel="customers" hidden>
			<div class="reports-section">
				<div class="reports-section-title">
					<div>
						<h5>Customer Reports</h5>
						<p>New customers, repeat customers, top customers, and intake trend.</p>
					</div>
					<span class="report-pill"><?= reports_number($total_customers) ?> customers</span>
				</div>
				<div class="reports-section-body">
					<div class="reports-chart-grid">
						<div class="report-chart-card">
							<div class="report-chart-head">
								<div>
									<h6>Customer Intake Trend</h6>
									<p>New customers by month in the selected range.</p>
								</div>
								<span class="report-pill">Line</span>
							</div>
							<?php if (!empty($customer_intake_rows)): ?>
								<div class="report-chart-canvas is-short">
									<canvas id="customerIntakeChart"></canvas>
								</div>
							<?php else: ?>
								<div class="report-chart-empty">No customer intake data found.</div>
							<?php endif; ?>
						</div>

						<div class="report-chart-card">
							<div class="report-chart-head">
								<div>
									<h6>Customer Mix</h6>
									<p>New and repeat activity during the selected range.</p>
								</div>
							</div>
							<div class="reports-mini-grid">
								<div class="report-metric">
									<small>New Customers</small>
									<strong><?= reports_number($new_customers) ?></strong>
									<span>Added in range</span>
								</div>
								<div class="report-metric">
									<small>Repeat Customers</small>
									<strong><?= reports_number($repeat_customers) ?></strong>
									<span>More than one order</span>
								</div>
							</div>
						</div>
					</div>

					<div class="reports-two-col">
						<div>
							<h6 class="report-subtable-title">Top Customers</h6>
							<div class="report-table-toolbar">
								<input type="search" class="form-control form-control-sm report-table-search" placeholder="Search customers..." data-report-table-search="#topCustomersTable" autocomplete="off">
							</div>
							<table class="report-table" id="topCustomersTable">
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

				</section>

				<section class="reports-tab-panel" id="report-panel-inventory" role="tabpanel" aria-labelledby="report-tab-inventory" data-report-panel="inventory" hidden>
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

					<div class="reports-chart-grid">
						<div class="report-chart-card">
							<div class="report-chart-head">
								<div>
									<h6>Most Used Parts</h6>
									<p>Stock-out usage by part in the selected range.</p>
								</div>
								<span class="report-pill">Bars</span>
							</div>
							<?php if (!empty($most_used_parts)): ?>
								<div class="report-chart-canvas">
									<canvas id="inventoryUsageChart"></canvas>
								</div>
							<?php else: ?>
								<div class="report-chart-empty">No stock-out usage found.</div>
							<?php endif; ?>
						</div>

						<div class="report-chart-card">
							<div class="report-chart-head">
								<div>
									<h6>Stock Health</h6>
									<p>Separate counts for low and unavailable items.</p>
								</div>
							</div>
							<div class="reports-mini-grid">
								<div class="report-metric <?= $low_stock_items > 0 ? 'is-warning' : 'is-success' ?>">
									<small>Low Stock</small>
									<strong><?= reports_number($low_stock_items) ?></strong>
									<span>Needs watch</span>
								</div>
								<div class="report-metric <?= $out_of_stock_items > 0 ? 'is-danger' : 'is-success' ?>">
									<small>Out of Stock</small>
									<strong><?= reports_number($out_of_stock_items) ?></strong>
									<span>Needs reorder</span>
								</div>
							</div>
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
										<?php
											$stock_quantity = (int) $row['quantity'];
											$stock_status = $stock_quantity <= 0 ? 'Out of Stock' : ($stock_quantity < 10 ? 'Low Stock' : 'In Stock');
											$stock_status_class = $stock_quantity <= 0 ? 'is-danger' : ($stock_quantity < 10 ? 'is-warning' : 'is-success');
										?>
										<tr>
											<td>
												<?= htmlspecialchars($row['product_code']) ?>
												<div class="report-muted"><?= htmlspecialchars(trim(($row['brand_name'] ?? '') . ' ' . ($row['model'] ?? ''))) ?></div>
											</td>
											<td><span class="report-pill <?= $stock_status_class ?>"><?= htmlspecialchars($stock_status) ?></span></td>
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
							<div class="report-table-toolbar">
								<input type="search" class="form-control form-control-sm report-table-search" placeholder="Search parts..." data-report-table-search="#mostUsedPartsTable" autocomplete="off">
							</div>
							<table class="report-table" id="mostUsedPartsTable">
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

				</section>

				<section class="reports-tab-panel" id="report-panel-activity" role="tabpanel" aria-labelledby="report-tab-activity" data-report-panel="activity" hidden>
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

					<h6 class="report-subtable-title">Recent Activity</h6>
					<div class="report-table-toolbar">
						<input type="search" class="form-control form-control-sm report-table-search" placeholder="Search activity..." data-report-table-search="#activityLogsTable" autocomplete="off">
					</div>
					<table class="report-table" id="activityLogsTable">
						<thead>
							<tr>
								<th>Event</th>
								<th>User</th>
								<th>When</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($activity_rows as $row): ?>
								<tr>
									<td><?= htmlspecialchars(reports_activity_summary($row['work_order_code'] ?? '', $row['action'])) ?></td>
									<td><?= htmlspecialchars($row['user_name']) ?></td>
									<td><?= htmlspecialchars(reports_display_date($row['created_at'], 'M d, Y h:i A')) ?></td>
								</tr>
							<?php endforeach; ?>
							<?php if (empty($activity_rows)): ?>
								<tr><td colspan="3" class="report-empty">No activity logs found.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

				</section>
			</div>
		</div>
	</div>
</div>

<script>
(function () {
	const chartData = <?= json_encode($report_chart_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
	const chartBundle = <?= json_encode(asset_url('src/scripts/chart.js'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
	const renderedCharts = {};
	const palette = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#0891b2', '#7c3aed', '#475569', '#db2777'];
	const tabsRoot = document.querySelector('[data-report-tabs]');
	const chartPanels = ['work-orders', 'payments', 'customers', 'inventory'];

	function valuesHaveData(values) {
		return Array.isArray(values) && values.some(function (value) {
			return Number(value) > 0;
		});
	}

	function formatNumber(value) {
		return Number(value || 0).toLocaleString();
	}

	function formatMoney(value) {
		return 'Php ' + Number(value || 0).toLocaleString(undefined, {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		});
	}

	function chartCanvas(id) {
		const canvas = document.getElementById(id);
		return canvas ? canvas.getContext('2d') : null;
	}

	function renderPanelCharts(panelName) {
		if (!window.Chart || renderedCharts[panelName]) {
			return;
		}

		Chart.defaults.color = '#4b5563';
		Chart.defaults.font.family = 'Arial, sans-serif';

		if (panelName === 'work-orders' && valuesHaveData(chartData.workStatus.data)) {
			const ctx = chartCanvas('workOrderStatusChart');
			if (ctx) {
				renderedCharts[panelName] = new Chart(ctx, {
					type: 'pie',
					data: {
						labels: chartData.workStatus.labels,
						datasets: [{
							data: chartData.workStatus.data,
							backgroundColor: palette,
							borderColor: '#ffffff',
							borderWidth: 2
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: {
								position: 'bottom',
								labels: { usePointStyle: true, boxWidth: 8 }
							},
							tooltip: {
								callbacks: {
									label: function (context) {
										const total = context.dataset.data.reduce(function (sum, value) {
											return sum + Number(value || 0);
										}, 0);
										const value = Number(context.raw || 0);
										const share = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
										return context.label + ': ' + formatNumber(value) + ' (' + share + '%)';
									}
								}
							}
						}
					}
				});
			}
		}

		if (panelName === 'payments' && valuesHaveData(chartData.paymentMethods.data)) {
			const ctx = chartCanvas('paymentMethodChart');
			if (ctx) {
				renderedCharts[panelName] = new Chart(ctx, {
					type: 'bar',
					data: {
						labels: chartData.paymentMethods.labels,
						datasets: [{
							label: 'Collected',
							data: chartData.paymentMethods.data,
							backgroundColor: '#2563eb',
							borderRadius: 6
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							y: {
								beginAtZero: true,
								ticks: { callback: formatMoney },
								grid: { color: '#eef2f7' }
							},
							x: { grid: { display: false } }
						},
						plugins: {
							legend: { display: false },
							tooltip: {
								callbacks: {
									label: function (context) {
										return formatMoney(context.raw);
									}
								}
							}
						}
					}
				});
			}
		}

		if (panelName === 'customers' && valuesHaveData(chartData.customerIntake.data)) {
			const ctx = chartCanvas('customerIntakeChart');
			if (ctx) {
				renderedCharts[panelName] = new Chart(ctx, {
					type: 'line',
					data: {
						labels: chartData.customerIntake.labels,
						datasets: [{
							label: 'New Customers',
							data: chartData.customerIntake.data,
							borderColor: '#16a34a',
							backgroundColor: 'rgba(22, 163, 74, 0.12)',
							pointBackgroundColor: '#16a34a',
							pointRadius: 4,
							tension: 0.35,
							fill: true
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							y: {
								beginAtZero: true,
								ticks: { precision: 0 },
								grid: { color: '#eef2f7' }
							},
							x: { grid: { display: false } }
						},
						plugins: { legend: { display: false } }
					}
				});
			}
		}

		if (panelName === 'inventory' && valuesHaveData(chartData.inventoryUsage.data)) {
			const ctx = chartCanvas('inventoryUsageChart');
			if (ctx) {
				renderedCharts[panelName] = new Chart(ctx, {
					type: 'bar',
					data: {
						labels: chartData.inventoryUsage.labels,
						datasets: [{
							label: 'Used',
							data: chartData.inventoryUsage.data,
							backgroundColor: '#f59e0b',
							borderRadius: 6
						}]
					},
					options: {
						indexAxis: 'y',
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							x: {
								beginAtZero: true,
								ticks: { precision: 0 },
								grid: { color: '#eef2f7' }
							},
							y: { grid: { display: false } }
						},
						plugins: { legend: { display: false } }
					}
				});
			}
		}
	}

	function ensurePanelCharts(panelName) {
		if (chartPanels.indexOf(panelName) === -1 || renderedCharts[panelName]) {
			return;
		}

		if (window.Chart) {
			renderPanelCharts(panelName);
			return;
		}

		if (!window.MacproScriptLoader) {
			window.setTimeout(function () {
				ensurePanelCharts(panelName);
			}, 50);
			return;
		}

		window.MacproScriptLoader.load(chartBundle)
			.then(function () {
				renderPanelCharts(panelName);
			})
			.catch(function () {});
	}

	function scheduleActivePanelCharts() {
		if (!tabsRoot) {
			return;
		}

		const activeTab = tabsRoot.querySelector('[data-report-tab].is-active');
		if (activeTab) {
			ensurePanelCharts(activeTab.dataset.reportTab);
		}
	}

	function activateReportTab(name) {
		if (!tabsRoot) {
			return;
		}

		tabsRoot.querySelectorAll('[data-report-tab]').forEach(function (button) {
			const isActive = button.dataset.reportTab === name;
			button.classList.toggle('is-active', isActive);
			button.setAttribute('aria-selected', isActive ? 'true' : 'false');
			button.tabIndex = isActive ? 0 : -1;
		});

		tabsRoot.querySelectorAll('[data-report-panel]').forEach(function (panel) {
			const isActive = panel.dataset.reportPanel === name;
			panel.classList.toggle('is-active', isActive);
			panel.hidden = !isActive;
		});

		ensurePanelCharts(name);
	}

	if (tabsRoot) {
		const tabButtons = Array.from(tabsRoot.querySelectorAll('[data-report-tab]'));

		tabButtons.forEach(function (button, index) {
			button.addEventListener('click', function () {
				activateReportTab(button.dataset.reportTab);
			});

			button.addEventListener('keydown', function (event) {
				if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
					return;
				}

				event.preventDefault();
				const offset = event.key === 'ArrowRight' ? 1 : -1;
				const nextIndex = (index + offset + tabButtons.length) % tabButtons.length;
				tabButtons[nextIndex].focus();
				activateReportTab(tabButtons[nextIndex].dataset.reportTab);
			});
		});
	}

	document.querySelectorAll('[data-report-table-search]').forEach(function (input) {
		const table = document.querySelector(input.dataset.reportTableSearch);
		if (!table) {
			return;
		}

		input.addEventListener('input', function () {
			const query = input.value.trim().toLowerCase();

			table.querySelectorAll('tbody tr').forEach(function (row) {
				if (row.querySelector('.report-empty')) {
					return;
				}

				row.hidden = query !== '' && row.textContent.toLowerCase().indexOf(query) === -1;
			});
		});
	});

	if (window.MacproScriptLoader) {
		window.MacproScriptLoader.runAfterLoad(scheduleActivePanelCharts, 1500);
	} else {
		window.setTimeout(scheduleActivePanelCharts, 400);
	}
})();

(function () {
	const form = document.getElementById('reportsExportForm');
	const exportButton = document.getElementById('reportsExportButton');
	const statusModal = document.getElementById('reportExportStatus');
	const statusIcon = document.getElementById('reportExportIcon');
	const statusTitle = document.getElementById('reportExportTitle');
	const statusMessage = document.getElementById('reportExportMessage');
	const closeButton = document.getElementById('reportExportClose');
	let autoFocusTimer = null;

	if (!form || !statusModal || !statusTitle || !statusMessage || !closeButton) {
		return;
	}

	function clearPageSkeletonState() {
		try {
			sessionStorage.removeItem('macproPageNavigating');
			sessionStorage.removeItem('macproPageSkeletonLayout');
			sessionStorage.removeItem('macproPageNavigationStartedAt');
		} catch (error) {}

		document.documentElement.classList.remove('macpro-page-boot-loading');
		document.body.classList.remove('macpro-page-is-loading');

		const mainContainer = document.querySelector('.main-container');
		if (mainContainer) {
			mainContainer.removeAttribute('aria-busy');
		}

		const skeleton = document.getElementById('macproPageSkeleton');
		if (skeleton) {
			skeleton.setAttribute('aria-hidden', 'true');
		}
	}

	function setExportStatus(state, title, message, icon) {
		window.clearTimeout(autoFocusTimer);
		statusModal.classList.remove('is-loading', 'is-success', 'is-error');
		statusModal.classList.add('show', 'is-' + state);
		statusModal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('macpro-dialog-open');
		statusTitle.textContent = title;
		statusMessage.textContent = message;
		statusIcon.textContent = icon;

		if (state !== 'loading') {
			autoFocusTimer = window.setTimeout(function () {
				closeButton.focus();
			}, 80);
		}
	}

	function closeExportStatus() {
		window.clearTimeout(autoFocusTimer);
		statusModal.classList.remove('show', 'is-loading', 'is-success', 'is-error');
		statusModal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('macpro-dialog-open');
	}

	function exportUrlFromForm() {
		const url = new URL(form.getAttribute('action') || 'export-reports.php', window.location.href);
		const formData = new FormData(form);

		url.search = '';
		formData.forEach(function (value, key) {
			if (value !== null && String(value) !== '') {
				url.searchParams.append(key, value);
			}
		});

		return url;
	}

	function filenameFromResponse(response) {
		const disposition = response.headers.get('Content-Disposition') || '';
		let match = disposition.match(/filename\*=UTF-8''([^;]+)/i);

		if (match && match[1]) {
			return decodeURIComponent(match[1].replace(/["']/g, ''));
		}

		match = disposition.match(/filename="?([^"]+)"?/i);
		return match && match[1] ? match[1] : 'macprotech-report.csv';
	}

	function downloadBlob(blob, filename) {
		const objectUrl = URL.createObjectURL(blob);
		const link = document.createElement('a');

		link.href = objectUrl;
		link.download = filename;
		link.style.display = 'none';
		document.body.appendChild(link);
		link.click();

		window.setTimeout(function () {
			URL.revokeObjectURL(objectUrl);
			link.remove();
		}, 1000);
	}

	async function exportCsv() {
		const response = await fetch(exportUrlFromForm().toString(), {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		});

		const contentType = response.headers.get('Content-Type') || '';
		if (!response.ok || contentType.indexOf('text/csv') === -1) {
			const errorText = (await response.text()).trim();
			const looksLikeHtml = contentType.indexOf('text/html') !== -1 || errorText.charAt(0) === '<';
			let message = looksLikeHtml ? 'The export could not be completed. Please refresh the page and try again.' : errorText;

			if (message.length > 180) {
				message = message.slice(0, 177) + '...';
			}

			throw new Error(message || 'The CSV export could not be completed.');
		}

		const blob = await response.blob();
		downloadBlob(blob, filenameFromResponse(response));
	}

	form.addEventListener('submit', function (event) {
		event.preventDefault();
		clearPageSkeletonState();
		setExportStatus('loading', 'Exporting CSV', 'Preparing your file...', '');

		if (exportButton) {
			exportButton.disabled = true;
		}

		exportCsv()
			.then(function () {
				setExportStatus('success', 'Export Complete', 'Your CSV download has started.', 'OK');
			})
			.catch(function (error) {
				setExportStatus('error', 'Export Failed', error.message || 'The CSV export could not be completed.', '!');
			})
			.finally(function () {
				if (exportButton) {
					exportButton.disabled = false;
				}
			});
	});

	closeButton.addEventListener('click', closeExportStatus);
})();
</script>

<?php include 'footer.php'; ?>
