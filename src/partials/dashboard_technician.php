<?php
	$technician_id = (int) ($_SESSION['user_id'] ?? 0);
	$technician_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
	$technician_name = $technician_name !== '' ? $technician_name : ($_SESSION['username'] ?? 'Technician');

	$tech_active_repairs = (int) dashboard_scalar(
		$conn,
		"SELECT COUNT(*) AS total
		 FROM work_order
		 WHERE technician_id = $technician_id
		 AND status IN ('In Progress', 'Diagnosing')"
	);

	$tech_waiting_parts = (int) dashboard_scalar(
		$conn,
		"SELECT COUNT(*) AS total
		 FROM work_order
		 WHERE technician_id = $technician_id
		 AND status = 'Waiting for Parts'"
	);

	$tech_completed_today = (int) dashboard_scalar(
		$conn,
		"SELECT COUNT(*) AS total
		 FROM work_order
		 WHERE technician_id = $technician_id
		 AND status IN ('Repaired', 'Released')
		 AND completion_date = CURDATE()"
	);

	$tech_aged_handled = (int) dashboard_scalar(
		$conn,
		"SELECT COUNT(*) AS total
		 FROM work_order
		 WHERE technician_id = $technician_id
		 AND status NOT IN ('Repaired', 'Released', 'Cancelled')
		 AND request_date <= DATE_SUB(CURDATE(), INTERVAL 5 DAY)"
	);

	$tech_active_queue = mysqli_query(
		$conn,
		"SELECT id, code, request_date, unit_type, brand, model, prob_find, status, priority
		 FROM work_order
		 WHERE technician_id = $technician_id
		 AND status NOT IN ('Repaired', 'Released', 'Cancelled')
		 ORDER BY
			CASE WHEN priority = 'Rush' THEN 0 ELSE 1 END,
			request_date ASC,
			id ASC
		 LIMIT 8"
	);

	$tech_parts_tracker = mysqli_query(
		$conn,
		"SELECT
			op.id,
			op.part_name,
			op.brand,
			op.quantity,
			op.created_at,
			w.code,
			w.status,
			COALESCE(MAX(i.quantity), 0) AS available_quantity
		 FROM ordered_parts op
		 INNER JOIN work_order w ON w.id = op.work_order_id
		 LEFT JOIN items i ON (
			LOWER(i.product_code) = LOWER(op.part_name)
			OR LOWER(i.model) = LOWER(op.part_name)
			OR LOWER(CONCAT(i.brand_name, ' ', i.model)) = LOWER(TRIM(CONCAT(COALESCE(op.brand, ''), ' ', op.part_name)))
		 )
		 WHERE w.technician_id = $technician_id
		 AND w.status NOT IN ('Released', 'Cancelled')
		 GROUP BY op.id, op.part_name, op.brand, op.quantity, op.created_at, w.code, w.status
		 ORDER BY
			CASE
				WHEN COALESCE(MAX(i.quantity), 0) >= op.quantity THEN 0
				WHEN COALESCE(MAX(i.quantity), 0) > 0 THEN 1
				ELSE 2
			END,
			op.created_at DESC
		 LIMIT 8"
	);
?>

<div class="mobile-menu-overlay"></div>

<div class="main-container">
	<div class="xs-pd-20-10 pd-ltr-20">
		<div class="dashboard-header">
			<div>
				<h2 class="h3 mb-0">Technician Dashboard</h2>
				<p><?= htmlspecialchars($technician_name) ?>'s repair queue, parts readiness, and completion pace.</p>
			</div>
			<div class="dashboard-header-actions">
				<a href="work-order.php" class="btn btn-primary">Open Work Orders</a>
			</div>
		</div>

		<div class="dashboard-grid dashboard-stats-grid pb-10">
			<div class="stat-card role-stat-card">
				<div class="stat-top">
					<div>
						<div class="stat-number"><?= number_format($tech_active_repairs) ?></div>
						<div class="stat-label">My Active Repairs</div>
						<div class="stat-trend neutral">Diagnosing or in progress</div>
					</div>
					<div class="stat-icon green">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.2-3.2a6 6 0 01-7.9 7.9l-5.7 5.7a2.1 2.1 0 01-3-3l5.7-5.7a6 6 0 017.9-7.9l-3.2 3.2z"/></svg>
					</div>
				</div>
			</div>

			<div class="stat-card role-stat-card">
				<div class="stat-top">
					<div>
						<div class="stat-number"><?= number_format($tech_waiting_parts) ?></div>
						<div class="stat-label">Waiting for Parts</div>
						<div class="stat-trend <?= $tech_waiting_parts > 0 ? 'negative' : 'neutral' ?>">Assigned blockers</div>
					</div>
					<div class="stat-icon amber">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4a2 2 0 001-1.73z"/><path d="M3.3 7L12 12l8.7-5"/><path d="M12 22V12"/></svg>
					</div>
				</div>
			</div>

			<div class="stat-card role-stat-card">
				<div class="stat-top">
					<div>
						<div class="stat-number"><?= number_format($tech_completed_today) ?></div>
						<div class="stat-label">Completed Today</div>
						<div class="stat-trend positive">Moved to repaired</div>
					</div>
					<div class="stat-icon teal">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
					</div>
				</div>
			</div>

			<div class="stat-card role-stat-card">
				<div class="stat-top">
					<div>
						<div class="stat-number"><?= number_format($tech_aged_handled) ?></div>
						<div class="stat-label">Aged Handled (&gt;5 Days)</div>
						<div class="stat-trend <?= $tech_aged_handled > 0 ? 'negative' : 'neutral' ?>">Priority flag</div>
					</div>
					<div class="stat-icon red">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
					</div>
				</div>
			</div>
		</div>

		<div class="dashboard-grid dashboard-role-grid pb-10">
			<div class="card-box dashboard-table-card is-scrollable">
				<div class="dashboard-card-heading dashboard-table-heading">
					<div>
						<h5>My Active Work Queue</h5>
						<p>Assigned work orders ordered by rush priority and request date.</p>
					</div>
					<a href="work-order.php">View all</a>
				</div>
				<table class="table dashboard-table">
					<thead>
						<tr>
							<th>Work Order Code</th>
							<th>Device</th>
							<th>Issue</th>
							<th>Status</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						<?php if ($tech_active_queue && mysqli_num_rows($tech_active_queue) > 0): ?>
							<?php while ($row = mysqli_fetch_assoc($tech_active_queue)): ?>
								<?php
									$device = trim(($row['unit_type'] ?? '') . ' ' . ($row['brand'] ?? '') . ' ' . ($row['model'] ?? ''));
									$display_status = ($row['status'] === 'Ready for Release') ? 'Repaired' : $row['status'];
								?>
								<tr>
									<td>
										<strong><?= htmlspecialchars($row['code']) ?></strong>
										<?php if (($row['priority'] ?? '') === 'Rush'): ?>
											<span class="queue-priority">Rush</span>
										<?php endif; ?>
									</td>
									<td><?= htmlspecialchars($device ?: ($row['unit_type'] ?? 'Device')) ?></td>
									<td class="dashboard-issue-cell"><?= htmlspecialchars($row['prob_find'] ?? '') ?></td>
									<td><span class="status-pill <?= dashboard_status_class($display_status) ?>"><?= htmlspecialchars($display_status) ?></span></td>
									<td><a class="dashboard-action-link" href="work-order.php?search=<?= urlencode((string) $row['code']) ?>">Start/Resume</a></td>
								</tr>
							<?php endwhile; ?>
						<?php else: ?>
							<tr>
								<td colspan="5" class="dashboard-empty-table">No active assigned repairs right now.</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div class="card-box dashboard-table-card">
				<div class="dashboard-card-heading dashboard-table-heading">
					<div>
						<h5>Parts Status Tracker</h5>
						<p>Ordered parts for assigned work orders.</p>
					</div>
					<a href="items.php">Inventory</a>
				</div>
				<div class="parts-tracker-list">
					<?php if ($tech_parts_tracker && mysqli_num_rows($tech_parts_tracker) > 0): ?>
						<?php while ($part = mysqli_fetch_assoc($tech_parts_tracker)): ?>
							<?php
								$needed = max(1, (int) ($part['quantity'] ?? 1));
								$available = (int) ($part['available_quantity'] ?? 0);
								if ($available >= $needed) {
									$part_status = 'In Stock';
									$part_class = 'part-status-ready';
								} elseif ($available > 0) {
									$part_status = 'Low Stock';
									$part_class = 'part-status-low';
								} else {
									$part_status = 'On Order';
									$part_class = 'part-status-order';
								}
							?>
							<a class="parts-tracker-item" href="work-order.php?search=<?= urlencode((string) $part['code']) ?>">
								<span>
									<strong><?= htmlspecialchars($part['part_name']) ?></strong>
									<small><?= htmlspecialchars($part['code']) ?> &middot; Needed <?= number_format($needed) ?> &middot; Available <?= number_format($available) ?></small>
								</span>
								<span class="parts-status-badge <?= $part_class ?>"><?= htmlspecialchars($part_status) ?></span>
							</a>
						<?php endwhile; ?>
					<?php else: ?>
						<div class="dashboard-empty">No ordered parts tied to your active jobs.</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
