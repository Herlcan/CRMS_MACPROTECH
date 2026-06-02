<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');

	include 'header.php';
	include 'sidebar.php';
	include 'src/db/connection.php';

	function dashboard_scalar(mysqli $conn, string $sql, string $field = 'total', $fallback = 0)
	{
		$result = mysqli_query($conn, $sql);
		if (!$result) {
			return $fallback;
		}

		$row = mysqli_fetch_assoc($result);
		return $row[$field] ?? $fallback;
	}

	function dashboard_row(mysqli $conn, string $sql, array $fallback = []): array
	{
		$result = mysqli_query($conn, $sql);
		if (!$result) {
			return $fallback;
		}

		return mysqli_fetch_assoc($result) ?: $fallback;
	}

	function dashboard_monthly_series(mysqli $conn, string $sql): array
	{
		$labels = [];
		$keys = [];
		$values = [];

		for ($i = 5; $i >= 0; $i--) {
			$time = strtotime("-$i months");
			$key = date('Y-m', $time);
			$keys[] = $key;
			$labels[] = date('M', $time);
			$values[$key] = 0;
		}

		$result = mysqli_query($conn, $sql);
		if ($result) {
			while ($row = mysqli_fetch_assoc($result)) {
				$key = $row['month_key'] ?? '';
				if (array_key_exists($key, $values)) {
					$values[$key] = (float) $row['total'];
				}
			}
		}

		return [
			'labels' => $labels,
			'data' => array_map(fn($key) => $values[$key], $keys)
		];
	}

	function dashboard_previous_period_count(mysqli $conn, string $table, string $date_column, string $where = '1'): array
	{
		$current = (int) dashboard_scalar(
			$conn,
			"SELECT COUNT(*) AS total FROM $table
			 WHERE $where
			 AND $date_column >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
			 AND $date_column < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)"
		);

		$previous = (int) dashboard_scalar(
			$conn,
			"SELECT COUNT(*) AS total FROM $table
			 WHERE $where
			 AND $date_column >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
			 AND $date_column < DATE_FORMAT(CURDATE(), '%Y-%m-01')"
		);

		return ['current' => $current, 'previous' => $previous];
	}

	function dashboard_trend_markup(float $current, float $previous, string $suffix = 'from last month'): string
	{
		if ($previous <= 0 && $current <= 0) {
			return '<div class="stat-trend neutral">No activity <span class="trend-sub">' . htmlspecialchars($suffix) . '</span></div>';
		}

		if ($previous <= 0) {
			return '<div class="stat-trend positive">New <span class="trend-sub">' . htmlspecialchars($suffix) . '</span></div>';
		}

		$change = (($current - $previous) / $previous) * 100;
		$class = $change >= 0 ? 'positive' : 'negative';
		$prefix = $change >= 0 ? '+' : '';

		return '<div class="stat-trend ' . $class . '">' . $prefix . number_format($change, 1) . '% <span class="trend-sub">' . htmlspecialchars($suffix) . '</span></div>';
	}

	function dashboard_status_class(string $status): string
	{
		return match ($status) {
			'Pending' => 'bg-pending',
			'Diagnosing' => 'bg-diagnosing',
			'Waiting for Parts' => 'bg-waiting',
			'In Progress' => 'bg-inprogress',
			'Repaired', 'Ready for Release' => 'bg-repaired',
			'Released' => 'bg-released',
			'Cancelled' => 'bg-cancelled',
			default => 'bg-pending'
		};
	}

	$current_role = $_SESSION['role'] ?? '';
	$can_manage_clients = in_array($current_role, ['Administrator', 'Cashier/Front Desk'], true);
	$can_manage_payments = in_array($current_role, ['Administrator', 'Cashier/Front Desk'], true);
	$can_view_reports = $current_role === 'Administrator';
	$total_workorders = (int) dashboard_scalar($conn, "SELECT COUNT(*) AS total FROM work_order");
	$total_technicians = (int) dashboard_scalar($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'Technician'");
	$open_workorders = (int) dashboard_scalar($conn, "SELECT COUNT(*) AS total FROM work_order WHERE status NOT IN ('Released', 'Cancelled')");
	$pending_workorders = (int) dashboard_scalar($conn, "SELECT COUNT(*) AS total FROM work_order WHERE status = 'Pending'");
	$waiting_parts_count = (int) dashboard_scalar($conn, "SELECT COUNT(*) AS total FROM work_order WHERE status = 'Waiting for Parts'");
	$aged_pending_count = (int) dashboard_scalar($conn, "SELECT COUNT(*) AS total FROM work_order WHERE status = 'Pending' AND request_date <= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
	$low_stock_count = (int) dashboard_scalar($conn, "SELECT COUNT(*) AS total FROM items WHERE quantity < 10");
	$out_of_stock_count = (int) dashboard_scalar($conn, "SELECT COUNT(*) AS total FROM items WHERE quantity <= 0");
	$unpaid_payment_count = (int) dashboard_scalar($conn, "SELECT COUNT(*) AS total FROM payments WHERE COALESCE(NULLIF(payment_status, ''), status, 'Unpaid') IN ('Unpaid', 'Partial', 'Pending')");
	$outstanding_balance = (float) dashboard_scalar($conn, "
		SELECT COALESCE(SUM(
			CASE
				WHEN COALESCE(remaining_balance, 0) > 0 THEN remaining_balance
				WHEN COALESCE(NULLIF(payment_status, ''), status, 'Unpaid') IN ('Unpaid', 'Partial', 'Pending')
					THEN GREATEST(COALESCE(total_amount, 0) - COALESCE(discount_amount, 0) - COALESCE(amount_paid, 0), 0)
				ELSE 0
			END
		), 0) AS total
		FROM payments
	");

	$refund_subquery = "(SELECT payment_id, SUM(refund_amount) AS total_refunded FROM refunds GROUP BY payment_id)";
	$total_revenue = (float) dashboard_scalar(
		$conn,
		"SELECT COALESCE(SUM(GREATEST(COALESCE(p.amount_paid, 0) - COALESCE(r.total_refunded, 0), 0)), 0) AS total
		 FROM payments p
		 LEFT JOIN $refund_subquery r ON r.payment_id = p.id"
	);

	$revenue_period = dashboard_row($conn, "
		SELECT
			COALESCE(SUM(CASE
				WHEN COALESCE(p.date, DATE(p.created_at)) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
				AND COALESCE(p.date, DATE(p.created_at)) < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
				THEN GREATEST(COALESCE(p.amount_paid, 0) - COALESCE(r.total_refunded, 0), 0)
				ELSE 0
			END), 0) AS current,
			COALESCE(SUM(CASE
				WHEN COALESCE(p.date, DATE(p.created_at)) >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
				AND COALESCE(p.date, DATE(p.created_at)) < DATE_FORMAT(CURDATE(), '%Y-%m-01')
				THEN GREATEST(COALESCE(p.amount_paid, 0) - COALESCE(r.total_refunded, 0), 0)
				ELSE 0
			END), 0) AS previous
		FROM payments p
		LEFT JOIN $refund_subquery r ON r.payment_id = p.id
	", ['current' => 0, 'previous' => 0]);

	$workorder_period = dashboard_previous_period_count($conn, 'work_order', 'request_date');

	$status_query = mysqli_query(
		$conn,
		"SELECT CASE WHEN status = 'Ready for Release' THEN 'Repaired' ELSE status END AS status, COUNT(*) AS total
		 FROM work_order
		 GROUP BY CASE WHEN status = 'Ready for Release' THEN 'Repaired' ELSE status END
		 ORDER BY total DESC"
	);

	$status_labels = [];
	$status_data = [];
	$total_chart = 0;

	if ($status_query) {
		while ($row = mysqli_fetch_assoc($status_query)) {
			$status_labels[] = $row['status'];
			$status_data[] = (int) $row['total'];
			$total_chart += (int) $row['total'];
		}
	}

	$workorder_series = dashboard_monthly_series(
		$conn,
		"SELECT DATE_FORMAT(request_date, '%Y-%m') AS month_key, COUNT(*) AS total
		 FROM work_order
		 WHERE request_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
		 GROUP BY DATE_FORMAT(request_date, '%Y-%m')"
	);

	$revenue_series = dashboard_monthly_series(
		$conn,
		"SELECT DATE_FORMAT(COALESCE(p.date, DATE(p.created_at)), '%Y-%m') AS month_key,
				COALESCE(SUM(GREATEST(COALESCE(p.amount_paid, 0) - COALESCE(r.total_refunded, 0), 0)), 0) AS total
		 FROM payments p
		 LEFT JOIN $refund_subquery r ON r.payment_id = p.id
		 WHERE COALESCE(p.date, DATE(p.created_at)) >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
		 GROUP BY DATE_FORMAT(COALESCE(p.date, DATE(p.created_at)), '%Y-%m')"
	);

	$recent_orders = mysqli_query(
		$conn,
		"SELECT w.code, w.status, w.request_date, w.unit_type, c.first_name, c.last_name,
				CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS technician_name
		 FROM work_order w
		 JOIN client c ON c.id = w.client_id
		 LEFT JOIN users u ON u.id = w.technician_id
		 ORDER BY w.id DESC
		 LIMIT 6"
	);

	$low_stock_items = mysqli_query(
		$conn,
		"SELECT product_code, brand_name, model, quantity
		 FROM items
		 WHERE quantity < 10
		 ORDER BY quantity ASC, id DESC
		 LIMIT 5"
	);

	$technician_workload = mysqli_query(
		$conn,
		"SELECT u.first_name, u.last_name,
				COUNT(w.id) AS open_total,
				SUM(CASE WHEN w.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_total,
				SUM(CASE WHEN w.status = 'Waiting for Parts' THEN 1 ELSE 0 END) AS waiting_total
		 FROM users u
		 LEFT JOIN work_order w ON w.technician_id = u.id AND w.status NOT IN ('Released', 'Cancelled')
		 WHERE u.role = 'Technician'
		 GROUP BY u.id, u.first_name, u.last_name
		 ORDER BY open_total DESC, u.first_name ASC
		 LIMIT 5"
	);
?>

<div class="mobile-menu-overlay"></div>

<div class="main-container">
	<div class="xs-pd-20-10 pd-ltr-20">

		<div class="dashboard-header">
			<div>
				<h2 class="h3 mb-0">Dashboard</h2>
				<p>Live overview of repairs, payments, inventory, and technician workload.</p>
			</div>
			<div class="dashboard-header-actions">
				<a href="work-order.php" class="btn btn-primary">New Work Order</a>
				<?php if ($can_view_reports): ?>
					<a href="reports.php" class="btn btn-secondary">Reports</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="row pb-10">
			<div class="col-xl-3 col-lg-3 col-md-6 mb-20">
				<div class="stat-card">
					<div class="stat-top">
						<div>
							<div class="stat-number"><?= number_format($total_workorders) ?></div>
							<div class="stat-label">Total Work Orders</div>
							<?= dashboard_trend_markup((float) $workorder_period['current'], (float) $workorder_period['previous']) ?>
						</div>
						<div class="stat-icon green">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
						</div>
					</div>
					<canvas class="stat-spark" id="spark-wo" height="50"></canvas>
				</div>
			</div>

			<div class="col-xl-3 col-lg-3 col-md-6 mb-20">
				<div class="stat-card">
					<div class="stat-top">
						<div>
							<div class="stat-number"><?= number_format($open_workorders) ?></div>
							<div class="stat-label">Open Repairs</div>
							<div class="stat-trend <?= $aged_pending_count > 0 ? 'negative' : 'neutral' ?>">
								<?= number_format($aged_pending_count) ?> aged pending <span class="trend-sub">over 7 days</span>
							</div>
						</div>
						<div class="stat-icon amber">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
						</div>
					</div>
					<canvas class="stat-spark stat-spark-amber" id="spark-open" height="50"></canvas>
				</div>
			</div>

			<div class="col-xl-3 col-lg-3 col-md-6 mb-20">
				<div class="stat-card">
					<div class="stat-top">
						<div>
							<div class="stat-number">Php <?= number_format($total_revenue, 2) ?></div>
							<div class="stat-label">Collected Revenue</div>
							<?= dashboard_trend_markup((float) $revenue_period['current'], (float) $revenue_period['previous']) ?>
						</div>
						<div class="stat-icon teal">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
						</div>
					</div>
					<canvas class="stat-spark stat-spark-teal" id="spark-rev" height="50"></canvas>
				</div>
			</div>

			<div class="col-xl-3 col-lg-3 col-md-6 mb-20">
				<div class="stat-card">
					<div class="stat-top">
						<div>
							<div class="stat-number"><?= number_format($low_stock_count) ?></div>
							<div class="stat-label">Low Stock Items</div>
							<div class="stat-trend <?= $out_of_stock_count > 0 ? 'negative' : 'neutral' ?>">
								<?= number_format($out_of_stock_count) ?> out of stock <span class="trend-sub">needs reorder</span>
							</div>
						</div>
						<div class="stat-icon red">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05"/><path d="M12 22.08V12"/></svg>
						</div>
					</div>
					<canvas class="stat-spark stat-spark-red" id="spark-cl" height="50"></canvas>
				</div>
			</div>
		</div>

		<div class="dashboard-grid dashboard-grid-main pb-10">
			<div class="card-box dashboard-chart-card">
				<div class="dashboard-card-heading">
					<div>
						<h5>Work Order Status</h5>
						<p><?= number_format($open_workorders) ?> active repairs out of <?= number_format($total_workorders) ?> total.</p>
					</div>
				</div>
				<div class="dashboard-donut-wrap">
					<canvas
						id="statusChart"
						height="300"
						data-labels='<?= htmlspecialchars(json_encode($status_labels), ENT_QUOTES, 'UTF-8') ?>'
						data-data='<?= htmlspecialchars(json_encode($status_data), ENT_QUOTES, 'UTF-8') ?>'
						data-wo-trend='<?= htmlspecialchars(json_encode($workorder_series['data']), ENT_QUOTES, 'UTF-8') ?>'
						data-low-stock-trend='<?= htmlspecialchars(json_encode(array_fill(0, 5, 0) + [5 => $low_stock_count]), ENT_QUOTES, 'UTF-8') ?>'
						data-open-trend='<?= htmlspecialchars(json_encode(array_fill(0, 5, 0) + [5 => $open_workorders]), ENT_QUOTES, 'UTF-8') ?>'
						data-rev-total='<?= htmlspecialchars((string) $total_revenue, ENT_QUOTES, 'UTF-8') ?>'
					></canvas>
					<div class="donut-center">
						<div class="donut-total"><?= number_format($total_chart) ?></div>
						<div class="donut-sub">Total</div>
					</div>
				</div>
			</div>

			<div class="card-box dashboard-chart-card">
				<div class="dashboard-card-heading">
					<div>
						<h5>6-Month Trend</h5>
						<p>Repair volume compared with collected revenue.</p>
					</div>
				</div>
				<canvas
					id="monthlyChart"
					height="300"
					data-labels='<?= htmlspecialchars(json_encode($workorder_series['labels']), ENT_QUOTES, 'UTF-8') ?>'
					data-workorders='<?= htmlspecialchars(json_encode($workorder_series['data']), ENT_QUOTES, 'UTF-8') ?>'
					data-revenue='<?= htmlspecialchars(json_encode($revenue_series['data']), ENT_QUOTES, 'UTF-8') ?>'
				></canvas>
			</div>
		</div>

		<div class="dashboard-grid dashboard-grid-side pb-10">
			<div class="card-box">
				<div class="dashboard-card-heading">
					<div>
						<h5>Needs Attention</h5>
						<p>Fast checks for the front desk and admin.</p>
					</div>
				</div>
				<div class="attention-list">
					<a href="work-order.php?filter=Pending" class="attention-item">
						<span class="attention-count"><?= number_format($pending_workorders) ?></span>
						<span>
							<strong>Pending repairs</strong>
							<small><?= number_format($aged_pending_count) ?> older than 7 days</small>
						</span>
					</a>
					<a href="work-order.php?filter=Waiting%20for%20Parts" class="attention-item">
						<span class="attention-count"><?= number_format($waiting_parts_count) ?></span>
						<span>
							<strong>Waiting for parts</strong>
							<small>Check ordered or available inventory</small>
						</span>
					</a>
					<?php if ($can_manage_payments): ?>
						<a href="payment.php?filter=Unpaid" class="attention-item">
							<span class="attention-count"><?= number_format($unpaid_payment_count) ?></span>
							<span>
								<strong>Unpaid or partial payments</strong>
								<small>Php <?= number_format($outstanding_balance, 2) ?> outstanding</small>
							</span>
						</a>
					<?php endif; ?>
					<a href="items.php?tab=stock_records" class="attention-item">
						<span class="attention-count"><?= number_format($low_stock_count) ?></span>
						<span>
							<strong>Low stock items</strong>
							<small><?= number_format($out_of_stock_count) ?> already out of stock</small>
						</span>
					</a>
				</div>
			</div>

			<div class="card-box">
				<div class="dashboard-card-heading">
					<div>
						<h5>Quick Actions</h5>
						<p>Jump into common daily tasks.</p>
					</div>
				</div>
				<a href="work-order.php" class="quick-action-btn">
					<span class="qa-icon">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
					</span>
					Create Work Order
				</a>
				<?php if ($can_manage_clients): ?>
					<a href="clients.php" class="quick-action-btn">
						<span class="qa-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
						</span>
						Add Customer
					</a>
				<?php endif; ?>
				<a href="items.php" class="quick-action-btn">
					<span class="qa-icon">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
					</span>
					Manage Inventory
				</a>
				<?php if ($can_manage_payments): ?>
					<a href="payment.php" class="quick-action-btn">
						<span class="qa-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
						</span>
						Review Payments
					</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="dashboard-grid dashboard-grid-side pb-10">
			<div class="card-box">
				<div class="dashboard-card-heading">
					<div>
						<h5>Technician Workload</h5>
						<p><?= number_format($total_technicians) ?> technician<?= $total_technicians === 1 ? '' : 's' ?> in the system.</p>
					</div>
				</div>
				<div class="workload-list">
					<?php if ($technician_workload && mysqli_num_rows($technician_workload) > 0): ?>
						<?php while ($tech = mysqli_fetch_assoc($technician_workload)): ?>
							<?php
								$tech_name = trim(($tech['first_name'] ?? '') . ' ' . ($tech['last_name'] ?? ''));
								$open_total = (int) ($tech['open_total'] ?? 0);
								$load_width = min(100, $open_total * 20);
							?>
							<div class="workload-item">
								<div class="workload-top">
									<strong><?= htmlspecialchars($tech_name ?: 'Technician') ?></strong>
									<span><?= number_format($open_total) ?> open</span>
								</div>
								<div class="workload-bar"><span style="width: <?= $load_width ?>%;"></span></div>
								<small><?= number_format((int) $tech['in_progress_total']) ?> in progress, <?= number_format((int) $tech['waiting_total']) ?> waiting parts</small>
							</div>
						<?php endwhile; ?>
					<?php else: ?>
						<div class="dashboard-empty">No technicians found yet.</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="card-box">
				<div class="dashboard-card-heading">
					<div>
						<h5>Low Stock Watchlist</h5>
						<p>Items below 10 available units.</p>
					</div>
				</div>
				<div class="stock-watch-list">
					<?php if ($low_stock_items && mysqli_num_rows($low_stock_items) > 0): ?>
						<?php while ($item = mysqli_fetch_assoc($low_stock_items)): ?>
							<a href="items.php?search=<?= urlencode($item['product_code']) ?>" class="stock-watch-item">
								<span>
									<strong><?= htmlspecialchars($item['product_code']) ?></strong>
									<small><?= htmlspecialchars(trim($item['brand_name'] . ' ' . $item['model'])) ?></small>
								</span>
								<span class="stock-count <?= ((int) $item['quantity'] <= 0) ? 'danger' : '' ?>"><?= number_format((int) $item['quantity']) ?></span>
							</a>
						<?php endwhile; ?>
					<?php else: ?>
						<div class="dashboard-empty">Inventory is healthy right now.</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="card-box dashboard-table-card pb-20 mb-30">
			<div class="dashboard-card-heading dashboard-table-heading">
				<div>
					<h5>Recent Work Orders</h5>
					<p>Latest repair requests and current assignment.</p>
				</div>
				<a href="work-order.php">View all</a>
			</div>
			<table class="table dashboard-table">
				<thead>
					<tr>
						<th>Code</th>
						<th>Customer</th>
						<th>Unit</th>
						<th>Technician</th>
						<th>Status</th>
						<th>Date</th>
					</tr>
				</thead>
				<tbody>
					<?php if ($recent_orders && mysqli_num_rows($recent_orders) > 0): ?>
						<?php while ($row = mysqli_fetch_assoc($recent_orders)): ?>
							<?php
								$status = $row['status'] === 'Ready for Release' ? 'Repaired' : $row['status'];
								$technician_name = trim($row['technician_name'] ?? '');
							?>
							<tr>
								<td><?= htmlspecialchars($row['code']) ?></td>
								<td><?= htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])) ?></td>
								<td><?= htmlspecialchars($row['unit_type']) ?></td>
								<td><?= htmlspecialchars($technician_name ?: 'Unassigned') ?></td>
								<td><span class="status-pill <?= dashboard_status_class($status) ?>" style="color: #fff"><?= htmlspecialchars($status) ?></span></td>
								<td><?= htmlspecialchars($row['request_date']) ?></td>
							</tr>
						<?php endwhile; ?>
					<?php else: ?>
						<tr>
							<td colspan="6" class="dashboard-empty-table">No work orders yet</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<script src="src/scripts/chart.js"></script>
<script src="src/scripts/dashboard-charts.js"></script>

<?php include 'footer.php'; ?>
