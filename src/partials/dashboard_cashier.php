<?php
	$payment_status_sql = "COALESCE(NULLIF(p.payment_status, ''), CASE WHEN p.status IS NULL OR p.status = 'Pending' OR p.status = '' THEN 'Unpaid' ELSE p.status END)";
	$work_order_fallback_total_sql = "
		COALESCE(w.diagnostic_fee, 0)
		+ COALESCE(w.work_order_cost, 0)
		+ COALESCE((
			SELECT SUM(pi.quantity * COALESCE(pi.unit_price, i.average_price, 0))
			FROM purchased_item pi
			LEFT JOIN items i ON (
				(pi.product_id REGEXP '^[0-9]+$' AND CAST(pi.product_id AS UNSIGNED) = i.id)
				OR pi.product_id = i.product_code
			)
			WHERE pi.work_order_id = w.id
		), 0)
		+ COALESCE((
			SELECT SUM(op.quantity * op.price)
			FROM ordered_parts op
			WHERE op.work_order_id = w.id
		), 0)
	";
	$balance_due_sql = "
		CASE
			WHEN p.id IS NULL THEN GREATEST($work_order_fallback_total_sql, 0)
			WHEN COALESCE(p.remaining_balance, 0) > 0 THEN p.remaining_balance
			WHEN $payment_status_sql IN ('Unpaid', 'Partial', 'Pending')
				THEN GREATEST(COALESCE(p.total_amount, 0) - COALESCE(p.discount_amount, 0) - COALESCE(p.amount_paid, 0), 0)
			ELSE 0
		END
	";

	$cashier_ready_release = (int) dashboard_scalar(
		$conn,
		"SELECT COUNT(*) AS total
		 FROM work_order
		 WHERE status IN ('Repaired', 'Ready for Release')"
	);

	$cashier_today_intakes = (int) dashboard_scalar(
		$conn,
		"SELECT COUNT(*) AS total
		 FROM work_order
		 WHERE request_date = CURDATE()"
	);

	$cashier_pending_payments = (int) dashboard_scalar(
		$conn,
		"SELECT COUNT(*) AS total
		 FROM payments p
		 WHERE $payment_status_sql IN ('Unpaid', 'Partial', 'Pending')
		 AND (
			COALESCE(p.remaining_balance, 0) > 0
			OR GREATEST(COALESCE(p.total_amount, 0) - COALESCE(p.discount_amount, 0) - COALESCE(p.amount_paid, 0), 0) > 0
		 )"
	);

	$cashier_outstanding_balance = (float) dashboard_scalar(
		$conn,
		"SELECT COALESCE(SUM(
			CASE
				WHEN COALESCE(p.remaining_balance, 0) > 0 THEN p.remaining_balance
				WHEN $payment_status_sql IN ('Unpaid', 'Partial', 'Pending')
					THEN GREATEST(COALESCE(p.total_amount, 0) - COALESCE(p.discount_amount, 0) - COALESCE(p.amount_paid, 0), 0)
				ELSE 0
			END
		 ), 0) AS total
		 FROM payments p"
	);

	$cashier_unassigned_orders = (int) dashboard_scalar(
		$conn,
		"SELECT COUNT(*) AS total
		 FROM work_order
		 WHERE technician_id IS NULL
		 AND status NOT IN ('Released', 'Cancelled')"
	);

	$cashier_pickup_queue = dashboard_result(
		$conn,
		"SELECT
			w.id,
			w.code,
			w.unit_type,
			w.brand,
			w.model,
			w.completion_date,
			c.first_name,
			c.last_name,
			p.id AS payment_id,
			p.payment_code,
			$balance_due_sql AS balance_due
		 FROM work_order w
		 INNER JOIN client c ON c.id = w.client_id
		 LEFT JOIN payments p ON p.id = (
			SELECT p2.id
			FROM payments p2
			WHERE p2.work_order_id = w.id
			ORDER BY p2.id DESC
			LIMIT 1
		 )
		 WHERE w.status IN ('Repaired', 'Ready for Release')
		 ORDER BY COALESCE(w.completion_date, w.request_date) ASC, w.id ASC
		 LIMIT 8"
	);
?>

<div class="mobile-menu-overlay"></div>

<div class="main-container">
	<div class="xs-pd-20-10 pd-ltr-20">
		<div class="dashboard-header">
			<div>
				<h2 class="h3 mb-0">Cashier / Front-Desk Dashboard</h2>
				<p>Customer intake, pickup, payments, and unassigned work orders for today.</p>
			</div>
		</div>

		<div class="dashboard-grid dashboard-stats-grid pb-10">
			<div class="stat-card role-stat-card">
				<div class="stat-top">
					<div>
						<div class="stat-number"><?= number_format($cashier_ready_release) ?></div>
						<div class="stat-label">Ready for Release</div>
						<div class="stat-trend <?= $cashier_ready_release > 0 ? 'positive' : 'neutral' ?>">Pickup queue</div>
					</div>
					<div class="stat-icon green">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
					</div>
				</div>
			</div>

			<div class="stat-card role-stat-card">
				<div class="stat-top">
					<div>
						<div class="stat-number"><?= number_format($cashier_today_intakes) ?></div>
						<div class="stat-label">Today's Intakes</div>
						<div class="stat-trend neutral">New work orders</div>
					</div>
					<div class="stat-icon teal">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
					</div>
				</div>
			</div>

			<div class="stat-card role-stat-card">
				<div class="stat-top">
					<div>
						<div class="stat-number"><?= number_format($cashier_pending_payments) ?></div>
						<div class="stat-label">Pending Payments</div>
						<div class="stat-trend <?= $cashier_pending_payments > 0 ? 'negative' : 'neutral' ?>">Php <?= number_format($cashier_outstanding_balance, 2) ?></div>
					</div>
					<div class="stat-icon amber">
						<span aria-hidden="true" style="font-size: 22px; font-weight: 800; line-height: 1;">&#8369;</span>
					</div>
				</div>
			</div>

			<div class="stat-card role-stat-card">
				<div class="stat-top">
					<div>
						<div class="stat-number"><?= number_format($cashier_unassigned_orders) ?></div>
						<div class="stat-label">Unassigned Orders</div>
						<div class="stat-trend <?= $cashier_unassigned_orders > 0 ? 'negative' : 'neutral' ?>">Needs technician</div>
					</div>
					<div class="stat-icon red">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6"/><path d="M23 11h-6"/></svg>
					</div>
				</div>
			</div>
		</div>

		<div class="dashboard-grid dashboard-role-grid pb-10">
			<div class="card-box dashboard-table-card is-scrollable">
				<div class="dashboard-card-heading dashboard-table-heading">
					<div>
						<h5>Customer Pickup &amp; Release Queue</h5>
						<p>Devices marked repaired and waiting at the counter.</p>
					</div>
					<a href="work-order.php?filter=Repaired">View all</a>
				</div>
				<table class="table dashboard-table">
					<thead>
						<tr>
							<th>Customer Name</th>
							<th>Device</th>
							<th>Total Balance Due</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						<?php if ($cashier_pickup_queue && mysqli_num_rows($cashier_pickup_queue) > 0): ?>
							<?php while ($pickup = mysqli_fetch_assoc($cashier_pickup_queue)): ?>
								<?php
									$customer_name = trim(($pickup['first_name'] ?? '') . ' ' . ($pickup['last_name'] ?? ''));
									$device = trim(($pickup['unit_type'] ?? '') . ' ' . ($pickup['brand'] ?? '') . ' ' . ($pickup['model'] ?? ''));
									$balance_due = max(0, (float) ($pickup['balance_due'] ?? 0));
									$payment_id = (int) ($pickup['payment_id'] ?? 0);
								?>
								<tr>
									<td>
										<strong><?= htmlspecialchars($customer_name ?: 'Customer') ?></strong>
										<small class="dashboard-table-note"><?= htmlspecialchars($pickup['code']) ?></small>
									</td>
									<td><?= htmlspecialchars($device ?: ($pickup['unit_type'] ?? 'Device')) ?></td>
									<td><span class="balance-due <?= $balance_due > 0 ? 'is-open' : '' ?>">Php <?= number_format($balance_due, 2) ?></span></td>
									<td>
										<?php if ($payment_id > 0): ?>
											<a class="dashboard-action-link is-primary" href="payment.php?open_payment=<?= urlencode((string) $payment_id) ?>">Process Release/Payment</a>
										<?php else: ?>
											<a class="dashboard-action-link" href="payment.php?search=<?= urlencode((string) $pickup['code']) ?>">Find Payment</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endwhile; ?>
						<?php else: ?>
							<tr>
								<td colspan="4" class="dashboard-empty-table">No repaired devices are waiting for pickup.</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div class="card-box dashboard-table-card">
				<div class="dashboard-card-heading dashboard-table-heading">
					<div>
						<h5>Quick Actions</h5>
						<p>Front-desk shortcuts for customer flow.</p>
					</div>
				</div>
				<div class="cashier-quick-actions">
					<a href="clients.php?open_add_client=1" class="quick-action-btn large">
						<span class="qa-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6"/><path d="M23 11h-6"/></svg>
						</span>
						Register New Customer
					</a>
					<a href="payment.php" class="quick-action-btn large">
						<span class="qa-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg>
						</span>
						Search Invoices / Payments
					</a>
				</div>
			</div>
		</div>
	</div>
</div>
