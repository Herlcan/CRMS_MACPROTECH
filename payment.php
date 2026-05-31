
<?php 
	include 'header.php';
	include 'sidebar.php';
	include 'src/db/connection.php';
	require_once 'src/handlers/payment_schema.php';

	ensure_payment_detail_columns($conn);

	function payment_display_date($value, $format = 'M d, Y') {
		if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
			return '—';
		}

		try {
			return (new DateTime($value))->format($format);
		} catch (Exception $e) {
			return $value;
		}
	}
?>

	<style>
		.payment-modal {
			display: none;
			position: fixed;
			inset: 0;
			z-index: 2500;
		}

		.payment-modal.show {
			display: block;
		}

		.payment-modal-backdrop {
			position: absolute;
			inset: 0;
			background: rgba(15, 23, 42, 0.55);
		}

		.payment-modal-dialog {
			position: relative;
			width: min(1120px, calc(100vw - 32px));
			height: calc(100vh - 48px);
			max-height: calc(100vh - 48px);
			margin: 24px auto;
			background: #fff;
			border-radius: 8px;
			box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24);
			overflow: hidden;
			display: flex;
			flex-direction: column;
		}

		.payment-modal-dialog form {
			display: flex;
			flex: 1;
			flex-direction: column;
			min-height: 0;
		}

		.payment-modal-header,
		.payment-modal-footer {
			padding: 18px 24px;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
		}

		.payment-modal-header {
			background: #1f2937;
			color: #fff;
		}

		.payment-modal-header h5 {
			margin: 0;
			color: #fff;
			font-weight: 700;
		}

		.payment-modal-close {
			border: 0;
			background: transparent;
			color: #fff;
			font-size: 28px;
			line-height: 1;
			cursor: pointer;
		}

		.payment-modal-body {
			flex: 1;
			min-height: 0;
			padding: 24px;
			overflow-y: auto;
			-webkit-overflow-scrolling: touch;
		}

		.payment-modal-footer {
			flex-shrink: 0;
		}

		.payment-section {
			margin-bottom: 24px;
		}

		.payment-section-title {
			font-weight: 700;
			color: #111827;
			margin-bottom: 12px;
		}

		.payment-info-grid {
			display: grid;
			grid-template-columns: repeat(3, minmax(0, 1fr));
			gap: 12px;
		}

		.payment-info-item {
			background: #f8fafc;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			padding: 12px;
			min-width: 0;
		}

		.payment-info-item small {
			display: block;
			color: #6b7280;
			font-weight: 700;
			text-transform: uppercase;
			margin-bottom: 6px;
		}

		.payment-info-item span {
			color: #111827;
			font-weight: 600;
			overflow-wrap: anywhere;
		}

		.payment-table {
			width: 100%;
			border-collapse: collapse;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			overflow: hidden;
		}

		.payment-table th,
		.payment-table td {
			padding: 12px;
			border-bottom: 1px solid #e5e7eb;
			color: #111827;
		}

		.payment-table th {
			background: #f8fafc;
			font-weight: 700;
			text-align: left;
		}

		.payment-table tr:last-child td {
			border-bottom: 0;
		}

		.payment-table .text-right {
			text-align: right;
		}

		.payment-form-grid {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 16px;
		}

		.payment-computed-box {
			background: #f8fafc;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			padding: 14px;
			display: grid;
			gap: 10px;
		}

		.payment-computed-row {
			display: flex;
			justify-content: space-between;
			gap: 12px;
			color: #374151;
		}

		.payment-computed-row strong {
			color: #111827;
		}

		.payment-status-badge {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: 74px;
			padding: 6px 10px;
			border-radius: 999px;
			font-weight: 700;
			font-size: 12px;
		}

		.payment-status-paid {
			background: #dcfce7;
			color: #15803d;
		}

		.payment-status-partial {
			background: #dbeafe;
			color: #1d4ed8;
		}

		.payment-status-unpaid,
		.payment-status-pending {
			background: #fef3c7;
			color: #92400e;
		}

		.payment-status-partially-refunded {
			background: #ede9fe;
			color: #6d28d9;
		}

		.payment-status-refunded {
			background: #fee2e2;
			color: #991b1b;
		}

		.payment-history-list {
			display: grid;
			gap: 10px;
		}

		.payment-history-entry {
			display: flex;
			justify-content: space-between;
			gap: 14px;
			padding: 12px;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			background: #f8fafc;
		}

		.payment-history-entry strong {
			color: #111827;
		}

		.payment-history-entry small {
			color: #6b7280;
			display: block;
			margin-top: 4px;
		}

		.payment-history-amount.inflow {
			color: #15803d;
		}

		.payment-history-amount.outflow {
			color: #b91c1c;
		}

		.refund-modal {
			display: none;
			position: fixed;
			inset: 0;
			z-index: 2700;
		}

		.refund-modal.show {
			display: block;
		}

		.refund-modal-dialog {
			position: relative;
			width: min(520px, calc(100vw - 32px));
			margin: 70px auto;
			background: #fff;
			border-radius: 8px;
			box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
			overflow: hidden;
		}

		.payment-alert {
			display: none;
			margin-bottom: 16px;
			padding: 12px 14px;
			border-radius: 8px;
		}

		.payment-alert.show {
			display: block;
		}

		.payment-alert.error {
			background: #fee2e2;
			color: #991b1b;
		}

		.payment-alert.success {
			background: #dcfce7;
			color: #166534;
		}

		@media (max-width: 768px) {
			.payment-modal-dialog {
				width: calc(100vw - 20px);
				margin: 10px auto;
				height: calc(100vh - 20px);
				max-height: calc(100vh - 20px);
			}

			.payment-info-grid,
			.payment-form-grid {
				grid-template-columns: 1fr;
			}

			.payment-modal-header,
			.payment-modal-footer,
			.payment-modal-body {
				padding: 16px;
			}
		}

		@media print {
			body.payment-receipt-printing > *:not(#paymentReceiptPrintArea) {
				display: none !important;
			}
		}
	</style>

	<div class="mobile-menu-overlay"></div>

	<div class="main-container">
		<div class="pd-ltr-20 xs-pd-20-10">
			<div class="min-height-200px">
				<div class="page-header">
					<div class="row">
						<div class="col-md-6 col-sm-12" style="margin-top: auto; margin-bottom: auto;">
							<div class="title">
								<h4><i class="micon dw dw-user1 mtext"></i> Payments Lists</h4>
							</div>
						</div>
					</div>
				</div>
				
				<!-- Simple Datatable start -->
				<div class="card-box mb-30">
					<!-- Table Controls -->
					<div class="row mb-20">
						<div class="col-sm-12 col-md-3">
							<div class="dataTables_length" id="DataTables_Table_0_length">
								<label>Show 
									<form method="GET" style="display: inline;">
										<input type="hidden" name="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
										<input type="hidden" name="filter" value="<?= isset($_GET['filter']) ? htmlspecialchars($_GET['filter']) : '' ?>">
										<select name="limit" aria-controls="DataTables_Table_0" class="custom-select custom-select-sm form-control form-control-sm" onchange="this.form.submit();">
											<option value="10" <?= (isset($_GET['limit']) && $_GET['limit'] == '10') ? 'selected' : '' ?>>10</option>
											<option value="25" <?= (isset($_GET['limit']) && $_GET['limit'] == '25') ? 'selected' : '' ?>>25</option>
											<option value="50" <?= (isset($_GET['limit']) && $_GET['limit'] == '50') ? 'selected' : '' ?>>50</option>
											<option value="-1" <?= (isset($_GET['limit']) && $_GET['limit'] == '-1') ? 'selected' : '' ?>>All</option>
										</select>
									</form> entries
								</label>
							</div>
						</div>
						<div class="col-sm-12 col-md-3" style="display: flex; align-items: center;">
							<form method="GET" class="form-inline">
								<input type="hidden" name="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
								<input type="hidden" name="limit" value="<?= isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10' ?>">

								<select name="filter" class="form-control form-control-sm" onchange="this.form.submit()" style="max-height: 40px;">
									<option value="">All Payment Status</option>
									<option value="Paid" <?= (isset($_GET['filter']) && $_GET['filter'] == 'Paid') ? 'selected' : '' ?>>Paid</option>
									<option value="Partial" <?= (isset($_GET['filter']) && $_GET['filter'] == 'Partial') ? 'selected' : '' ?>>Partial</option>
									<option value="Unpaid" <?= (isset($_GET['filter']) && $_GET['filter'] == 'Unpaid') ? 'selected' : '' ?>>Unpaid</option>
								</select>
							</form>
						</div>
						<div class="col-sm-12 col-md-6" style="margin-left: auto;">
							<div id="DataTables_Table_0_filter" class="dataTables_filter">
								<label>Search: 
									<form method="GET" class="form-inline">
										<input type="hidden" name="limit" value="<?= isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10' ?>">
										<input type="hidden" name="filter" value="<?= isset($_GET['filter']) ? htmlspecialchars($_GET['filter']) : '' ?>">
										<input type="search" name="search" class="form-control form-control-sm" placeholder="Search payments..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" autocomplete="off">
									</form>
								</label>
							</div>
						</div>
					</div>
					<div class="pb-20">
						<table class="data-table table responsive">
							<thead>
								<tr>
									<th style="text-align: center;">Payment Code</th>
									<th style="text-align: center;">Work Order Code</th>
									<th style="text-align: center;">Created At</th>
									<th style="text-align: center;">Total Amount</th>
									<th style="text-align: center;">Status</th>
									<th style="text-align: center;">Date Paid</th>
									<th class="datatable-nosort" style="padding-left: 25px;">Action</th>
								</tr>
							</thead>
							<?php
								$where = "1";
								$from_clause = "payments p LEFT JOIN work_order wo ON p.work_order_id = wo.id";
								$payment_status_sql = "COALESCE(NULLIF(p.payment_status, ''), CASE WHEN p.status IS NULL OR p.status = 'Pending' OR p.status = '' THEN 'Unpaid' ELSE p.status END)";
								$limit = 10; // Default limit
								$current_page = 1; // Default page

								// Get limit from query string
								if (!empty($_GET['limit'])) {
									$limit_input = intval($_GET['limit']);
									$limit = ($limit_input == -1) ? 999999 : $limit_input; // -1 means show all
								}

								// Get current page from query string
								if (!empty($_GET['page'])) {
									$current_page = max(1, intval($_GET['page'])); // Ensure page is at least 1
								}

								// Secure search
								if (!empty($_GET['search'])) {
								    $s = strtolower(mysqli_real_escape_string($conn, trim($_GET['search'])));
								    $amount_search = preg_replace('/[^0-9.]/', '', $s);
								    $amount_condition = "";

								    if ($amount_search !== '' && $amount_search !== $s) {
										$amount_search = mysqli_real_escape_string($conn, $amount_search);
										$amount_condition = " OR CAST(p.total_amount AS CHAR) LIKE '%$amount_search%'";
								    }

								    $where .= " AND (
										LOWER(p.payment_code) LIKE '%$s%' OR
										LOWER(wo.code) LIKE '%$s%' OR
										CAST(p.total_amount AS CHAR) LIKE '%$s%' OR
										EXISTS (
											SELECT 1
											FROM purchased_item pi
											INNER JOIN items i ON pi.product_id = i.id
											WHERE pi.work_order_id = p.work_order_id
											AND LOWER(i.product_code) LIKE '%$s%'
										)
										$amount_condition
									)";
								}

								// Secure filter
								if (!empty($_GET['filter'])) {
									$allowed_payment_status = ['Paid', 'Partial', 'Unpaid'];

									if (in_array($_GET['filter'], $allowed_payment_status, true)) {
										$f = mysqli_real_escape_string($conn, $_GET['filter']);
										$where .= " AND $payment_status_sql='$f'";
									}
								}

								// Get total count for pagination info
								$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM $from_clause WHERE $where");
								$count_row = mysqli_fetch_assoc($count_result);
								$total_records = $count_row['total'];

								// Calculate offset
								$offset = ($current_page - 1) * $limit;
								$total_pages = ceil($total_records / $limit);
								$offset = min($offset, $total_records); // Prevent offset from exceeding total records

								// Correct table + column names with LIMIT and OFFSET
								$result = mysqli_query($conn, "SELECT p.*, wo.code AS work_order_code FROM $from_clause WHERE $where ORDER BY COALESCE(p.date, DATE(p.created_at)) DESC, p.created_at DESC, p.id DESC LIMIT $limit OFFSET $offset");
								$records_shown = mysqli_num_rows($result);
								$record_start = ($total_records > 0) ? $offset + 1 : 0;
								$record_end = min($offset + $records_shown, $total_records);
								$pagination_limit = isset($_GET['limit']) ? urlencode($_GET['limit']) : '10';
								$pagination_search = isset($_GET['search']) ? urlencode($_GET['search']) : '';
								$pagination_filter = isset($_GET['filter']) ? urlencode($_GET['filter']) : '';
							?>
							<tbody>
								<?php while ($row = mysqli_fetch_assoc($result)) { ?>

								<tr id="payment-row-<?= (int) $row['id'] ?>">
									<td style="text-align: center;"><?= htmlspecialchars($row['payment_code']) ?></td>
									<td style="text-align: center;"><?= htmlspecialchars($row['work_order_code'] ?? '—') ?></td>
									<td style="text-align: center;"><?= htmlspecialchars(payment_display_date($row['created_at'], 'M d, Y h:i A')) ?></td>
									<td style="text-align: center;" id="payment-total-<?= (int) $row['id'] ?>">Php <?= htmlspecialchars(number_format((float) $row['total_amount'], 2)) ?></td>
									<td>
										<?php
											$display_status = !empty($row['payment_status']) ? $row['payment_status'] : (($row['status'] ?? '') === 'Pending' ? 'Unpaid' : $row['status']);
											$status = strtolower($display_status);
											$status_class = '';

											if ($status == 'paid') {
												$status_class = 'bg-admin';
											} elseif ($status == 'partial') {
												$status_class = 'bg-info';
											} elseif ($status == 'pending' || $status == 'unpaid') {
												$status_class = 'bg-staff';
											}
										?>
										<span class="badge <?= $status_class ?>" id="payment-status-<?= (int) $row['id'] ?>" style="display: grid; align-items: center; justify-content: center;">
											<?= htmlspecialchars($display_status) ?>
										</span>
									</td>
									<td style="text-align: center;" id="payment-date-paid-<?= (int) $row['id'] ?>"><?= htmlspecialchars(payment_display_date($row['date'])) ?></td>
									
									<td>
										<button type="button" class="btn btn-sm btn-primary" style="margin-right: 5px;" onclick="openPaymentModal(<?= (int) $row['id'] ?>)">
											<i class="dw dw-eye"></i> View
										</button>
									</td>
								</tr>
								<?php } ?>
								<?php if ($total_records == 0): ?>
								<tr>
									<td colspan="7" style="text-align: center;">No payments found</td>
								</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
					<!-- Pagination -->
					<div class="row">
						<div class="col-sm-12 col-md-5">
							<div class="dataTables_info" id="DataTables_Table_0_info" role="status" aria-live="polite">
								<?php 
									echo ($total_records > 0) ? $record_start . "-" . $record_end . " of " . $total_records . " entries" : "No entries"; 
								?>
							</div>
						</div>
						<div class="col-sm-12 col-md-7" style="margin-left: auto;">
							<div class="dataTables_paginate paging_simple_numbers" id="DataTables_Table_0_paginate">
								<ul class="pagination justify-content-end">
									<!-- Previous Button -->
									<li class="paginate_button page-item previous <?= ($current_page <= 1) ? 'disabled' : '' ?>">
										<a href="?page=<?= max(1, $current_page - 1) ?>&limit=<?= $pagination_limit ?>&search=<?= $pagination_search ?>&filter=<?= $pagination_filter ?>" aria-controls="DataTables_Table_0" class="page-link" <?= ($current_page <= 1) ? 'style="pointer-events: none;"' : '' ?>>
											<i class="ion-chevron-left">
												<img src="src/images/angle-double-small-left.png" width="20px" style="border: none">
											</i> 
										</a>
									</li>

									<!-- Page Numbers -->
									<?php 
										$start_page = max(1, $current_page - 2);
										$end_page = min($total_pages, $current_page + 2);
										
										if ($start_page > 1) {
											echo '<li class="paginate_button page-item"><a href="?page=1&limit=' . $pagination_limit . '&search=' . $pagination_search . '&filter=' . $pagination_filter . '" class="page-link">1</a></li>';
											if ($start_page > 2) {
												echo '<li class="paginate_button page-item disabled"><span class="page-link">...</span></li>';
											}
										}
										
										for ($i = $start_page; $i <= $end_page; $i++) {
											$active = ($i == $current_page) ? 'active' : '';
											echo '<li class="paginate_button page-item ' . $active . '"><a href="?page=' . $i . '&limit=' . $pagination_limit . '&search=' . $pagination_search . '&filter=' . $pagination_filter . '" class="page-link">' . $i . '</a></li>';
										}
										
										if ($end_page < $total_pages) {
											if ($end_page < $total_pages - 1) {
												echo '<li class="paginate_button page-item disabled"><span class="page-link">...</span></li>';
											}
											echo '<li class="paginate_button page-item"><a href="?page=' . $total_pages . '&limit=' . $pagination_limit . '&search=' . $pagination_search . '&filter=' . $pagination_filter . '" class="page-link">' . $total_pages . '</a></li>';
										}
									?>

									<!-- Next Button -->
									<li class="paginate_button page-item next <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
										<a href="?page=<?= min($total_pages, $current_page + 1) ?>&limit=<?= $pagination_limit ?>&search=<?= $pagination_search ?>&filter=<?= $pagination_filter ?>" aria-controls="DataTables_Table_0" class="page-link" <?= ($current_page >= $total_pages) ? 'style="pointer-events: none;"' : '' ?>>
											<i class="ion-chevron-right">
												<img src="src/images/angle-double-small-right.png" width="20px" style="border: none">
											</i>
										</a>
									</li>
								</ul>
							</div>
						</div>
					</div>
				</div>
				<!-- Simple Datatable End -->
		</div>
	</div>
	<div class="payment-modal" id="paymentModal" aria-hidden="true">
		<div class="payment-modal-backdrop" onclick="closePaymentModal()"></div>
		<div class="payment-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="paymentModalTitle">
			<div class="payment-modal-header">
				<h5 id="paymentModalTitle">Payment Details</h5>
				<button type="button" class="payment-modal-close" onclick="closePaymentModal()" aria-label="Close">&times;</button>
			</div>
			<form id="paymentForm">
				<div class="payment-modal-body">
					<div id="paymentAlert" class="payment-alert"></div>
					<input type="hidden" name="payment_id" id="payment_id">

					<div class="payment-section">
						<div class="payment-section-title">Work Order Information</div>
						<div class="payment-info-grid">
							<div class="payment-info-item">
								<small>Work Order ID</small>
								<span id="pm_work_order_code">—</span>
							</div>
							<div class="payment-info-item">
								<small>Customer</small>
								<span id="pm_customer_name">—</span>
							</div>
							<div class="payment-info-item">
								<small>Device</small>
								<span id="pm_device">—</span>
							</div>
							<div class="payment-info-item">
								<small>Issue</small>
								<span id="pm_issue">—</span>
							</div>
							<div class="payment-info-item">
								<small>Technician</small>
								<span id="pm_technician">—</span>
							</div>
							<div class="payment-info-item">
								<small>Date Completed</small>
								<span id="pm_completion_date">—</span>
							</div>
							<div class="payment-info-item">
								<small>Repair Status</small>
								<span id="pm_repair_status">—</span>
							</div>
							<div class="payment-info-item">
								<small>Payment Status</small>
								<span id="pm_payment_status" class="payment-status-badge payment-status-pending">Pending</span>
							</div>
						</div>
					</div>

					<div class="payment-section">
						<div class="payment-section-title">Cost Breakdown</div>
						<table class="payment-table">
							<thead>
								<tr>
									<th>Description</th>
									<th class="text-right">Amount</th>
								</tr>
							</thead>
							<tbody id="pm_cost_breakdown">
								<tr>
									<td colspan="2">Loading...</td>
								</tr>
							</tbody>
						</table>
					</div>

					<div class="payment-section">
						<div class="payment-section-title">Purchased Items Breakdown</div>
						<table class="payment-table">
							<thead>
								<tr>
									<th>Item</th>
									<th class="text-right">Qty</th>
									<th class="text-right">Price</th>
									<th class="text-right">Subtotal</th>
								</tr>
							</thead>
							<tbody id="pm_purchased_items">
								<tr>
									<td colspan="4">Loading...</td>
								</tr>
							</tbody>
						</table>
					</div>

					<div class="payment-section">
						<div class="payment-section-title">Payment</div>
						<div class="payment-form-grid">
							<div>
								<div class="form-group">
									<label class="form-label">Payment Method</label>
									<select class="form-control" name="payment_method" id="payment_method" required>
										<option value="Cash">Cash</option>
										<option value="GCash">GCash</option>
										<option value="Maya">Maya</option>
										<option value="Bank Transfer">Bank Transfer</option>
									</select>
								</div>
								<div class="form-group" id="referenceNumberGroup" style="display: none;">
									<label class="form-label">Reference Number</label>
									<input type="text" class="form-control" name="reference_number" id="reference_number" autocomplete="off">
								</div>
								<div class="form-group">
									<label class="form-label">Amount Paid</label>
									<input type="number" class="form-control" name="amount_paid" id="amount_paid" min="0" step="0.01" autocomplete="off">
								</div>
								<div class="form-group">
									<label class="form-label">Discount</label>
									<input type="number" class="form-control" name="discount_amount" id="discount_amount" min="0" step="0.01" value="0" autocomplete="off">
								</div>
							</div>
							<div>
								<div class="payment-computed-box">
									<div class="payment-computed-row">
										<span>Total</span>
										<strong id="pm_total_amount">&#8369;0.00</strong>
									</div>
									<div class="payment-computed-row">
										<span>Discount</span>
										<strong id="pm_discount_amount">&#8369;0.00</strong>
									</div>
									<div class="payment-computed-row">
										<span>Amount Due</span>
										<strong id="pm_net_total">&#8369;0.00</strong>
									</div>
									<div class="payment-computed-row">
										<span>Paid</span>
										<strong id="pm_amount_paid">&#8369;0.00</strong>
									</div>
									<div class="payment-computed-row">
										<span>Refunded</span>
										<strong id="pm_refunded_amount">&#8369;0.00</strong>
									</div>
									<div class="payment-computed-row">
										<span>Actual Paid</span>
										<strong id="pm_actual_paid">&#8369;0.00</strong>
									</div>
									<div class="payment-computed-row">
										<span>Change</span>
										<strong id="pm_change_amount">&#8369;0.00</strong>
									</div>
									<div class="payment-computed-row">
										<span>Remaining</span>
										<strong id="pm_remaining_balance">&#8369;0.00</strong>
									</div>
								</div>
								<div class="form-group" style="margin-top: 16px;">
									<label class="form-label">Notes</label>
									<textarea class="form-control" name="notes" id="payment_notes" rows="5"></textarea>
								</div>
							</div>
						</div>
					</div>

					<div class="payment-section" id="paymentHistorySection">
						<div class="payment-section-title">Payment History</div>
						<div class="payment-history-list" id="payment_history">
							<div class="payment-history-entry">
								<div>Loading...</div>
							</div>
						</div>
					</div>
				</div>
				<div class="payment-modal-footer">
					<div style="display: flex; gap: 10px; flex-wrap: wrap;">
						<button type="button" class="btn btn-danger" id="refundPaymentBtn" onclick="openRefundModal()" disabled>Refund</button>
						<button type="button" class="btn btn-secondary" id="printReceiptBtn" onclick="printPaymentReceipt()" disabled>Print Receipt</button>
						<button type="button" class="btn btn-secondary" id="emailReceiptBtn" onclick="emailPaymentReceipt()" disabled>Email Receipt</button>
					</div>
					<div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end;">
						<button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
						<button type="submit" class="btn btn-primary" id="confirmPaymentBtn">Confirm Payment</button>
					</div>
				</div>
			</form>
		</div>
	</div>

	<div class="refund-modal" id="refundModal" aria-hidden="true">
		<div class="payment-modal-backdrop" onclick="closeRefundModal()"></div>
		<div class="refund-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="refundModalTitle">
			<div class="payment-modal-header">
				<h5 id="refundModalTitle">Refund Payment</h5>
				<button type="button" class="payment-modal-close" onclick="closeRefundModal()" aria-label="Close">&times;</button>
			</div>
			<form id="refundForm">
				<div class="payment-modal-body" style="max-height: calc(100vh - 220px);">
					<div id="refundAlert" class="payment-alert"></div>
					<input type="hidden" name="payment_id" id="refund_payment_id">
					<div class="payment-computed-box" style="margin-bottom: 16px;">
						<div class="payment-computed-row">
							<span>Total Paid</span>
							<strong id="refund_total_paid">&#8369;0.00</strong>
						</div>
						<div class="payment-computed-row">
							<span>Already Refunded</span>
							<strong id="refund_total_refunded">&#8369;0.00</strong>
						</div>
						<div class="payment-computed-row">
							<span>Refundable Balance</span>
							<strong id="refund_refundable_balance">&#8369;0.00</strong>
						</div>
					</div>
					<div class="form-group">
						<label class="form-label">Refund Amount</label>
						<input type="number" class="form-control" name="refund_amount" id="refund_amount" min="0.01" step="0.01" required autocomplete="off">
					</div>
					<div class="form-group">
						<label class="form-label">Refund Method</label>
						<select class="form-control" name="refund_method" id="refund_method" required>
							<option value="Cash">Cash</option>
							<option value="GCash">GCash</option>
							<option value="Maya">Maya</option>
							<option value="Bank Transfer">Bank Transfer</option>
						</select>
					</div>
					<div class="form-group">
						<label class="form-label">Reason</label>
						<textarea class="form-control" name="reason" id="refund_reason" rows="4" required placeholder="Example: Overpayment, wrong charge, customer cancellation"></textarea>
					</div>
				</div>
				<div class="payment-modal-footer">
					<button type="button" class="btn btn-secondary" onclick="closeRefundModal()">Cancel</button>
					<button type="submit" class="btn btn-danger" id="confirmRefundBtn">Confirm Refund</button>
				</div>
			</form>
		</div>
	</div>

	<script>
		let currentPaymentDetails = null;
		const digitalPaymentMethods = ['GCash', 'Maya', 'Bank Transfer'];

		function formatMoney(value) {
			const amount = Number(value) || 0;
			return '\u20b1' + amount.toLocaleString('en-PH', {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2
			});
		}

		function formatDateForDisplay(value) {
			if (!value || value === '0000-00-00' || value === '0000-00-00 00:00:00') return '—';

			const normalized = String(value).trim();
			const [datePart] = normalized.split(' ');
			const parts = datePart.split('-').map(Number);

			if (parts.length === 3 && parts.every(Boolean)) {
				return new Intl.DateTimeFormat('en-PH', {
					month: 'short',
					day: '2-digit',
					year: 'numeric'
				}).format(new Date(parts[0], parts[1] - 1, parts[2]));
			}

			const parsed = new Date(normalized);
			return Number.isNaN(parsed.getTime()) ? normalized : new Intl.DateTimeFormat('en-PH', {
				month: 'short',
				day: '2-digit',
				year: 'numeric'
			}).format(parsed);
		}

		function escapeHtml(value) {
			if (value === null || value === undefined || value === '') return '—';
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String(value).replace(/[&<>"']/g, char => map[char]);
		}

		function getStatusClass(status) {
			const normalized = String(status || 'Unpaid').toLowerCase();
			if (normalized === 'paid') return 'payment-status-badge payment-status-paid';
			if (normalized === 'partial') return 'payment-status-badge payment-status-partial';
			if (normalized === 'unpaid') return 'payment-status-badge payment-status-unpaid';
			if (normalized === 'partially refunded') return 'payment-status-badge payment-status-partially-refunded';
			if (normalized === 'refunded') return 'payment-status-badge payment-status-refunded';
			return 'payment-status-badge payment-status-pending';
		}

		function showPaymentAlert(message, type) {
			const alert = document.getElementById('paymentAlert');
			alert.textContent = message;
			alert.className = 'payment-alert show ' + type;
		}

		function clearPaymentAlert() {
			const alert = document.getElementById('paymentAlert');
			alert.textContent = '';
			alert.className = 'payment-alert';
		}

		function showRefundAlert(message, type) {
			const alert = document.getElementById('refundAlert');
			alert.textContent = message;
			alert.className = 'payment-alert show ' + type;
		}

		function clearRefundAlert() {
			const alert = document.getElementById('refundAlert');
			alert.textContent = '';
			alert.className = 'payment-alert';
		}

		function openPaymentModal(paymentId) {
			const modal = document.getElementById('paymentModal');
			modal.classList.add('show');
			modal.setAttribute('aria-hidden', 'false');
			document.body.style.overflow = 'hidden';
			clearPaymentAlert();
			document.getElementById('paymentForm').reset();
			document.getElementById('payment_id').value = paymentId;
			document.getElementById('pm_cost_breakdown').innerHTML = '<tr><td colspan="2">Loading...</td></tr>';
			document.getElementById('pm_purchased_items').innerHTML = '<tr><td colspan="4">Loading...</td></tr>';
			document.getElementById('payment_history').innerHTML = '<div class="payment-history-entry"><div>Loading...</div></div>';
			document.getElementById('refundPaymentBtn').disabled = true;
			document.getElementById('printReceiptBtn').disabled = true;
			document.getElementById('emailReceiptBtn').disabled = true;

			fetch('src/handlers/get_payment_details.php?id=' + encodeURIComponent(paymentId))
				.then(response => response.json())
				.then(data => {
					if (!data.success) {
						showPaymentAlert(data.message || 'Failed to load payment details', 'error');
						return;
					}

					currentPaymentDetails = data;
					populatePaymentModal(data);
				})
				.catch(error => {
					console.error(error);
					showPaymentAlert('Error loading payment details', 'error');
				});
		}

		function closePaymentModal() {
			const modal = document.getElementById('paymentModal');
			modal.classList.remove('show');
			modal.setAttribute('aria-hidden', 'true');
			document.body.style.overflow = 'auto';
		}

		function populatePaymentModal(data) {
			const payment = data.payment || {};
			const costs = data.costs || {};
			const parts = data.purchasedParts || [];
			const device = [payment.brand, payment.model].filter(Boolean).join(' ');
			const computed = data.computed || {};
			const paymentStatus = computed.payment_status || payment.payment_status || payment.status || 'Unpaid';

			document.getElementById('pm_work_order_code').textContent = payment.work_order_code || '—';
			document.getElementById('pm_customer_name').textContent = payment.customer_name || '—';
			document.getElementById('pm_device').textContent = device || payment.unit_type || '—';
			document.getElementById('pm_issue').textContent = payment.prob_find || '—';
			document.getElementById('pm_technician').textContent = payment.technician_name || '—';
			document.getElementById('pm_completion_date').textContent = payment.completion_date || '—';
			document.getElementById('pm_repair_status').textContent = payment.work_order_status || '—';
			document.getElementById('pm_payment_status').textContent = paymentStatus;
			document.getElementById('pm_payment_status').className = getStatusClass(paymentStatus);

			const diagnosticFee = Number(costs.diagnostic_fee) || 0;
			const workOrderCost = Number(costs.work_order_cost) || 0;
			const purchasedPartsTotal = Number(costs.purchased_parts_total) || 0;
			const grossTotal = Number(costs.gross_total) || 0;

			document.getElementById('pm_cost_breakdown').innerHTML = `
				<tr>
					<td>Diagnostic Fee</td>
					<td class="text-right">${formatMoney(diagnosticFee)}</td>
				</tr>
				<tr>
					<td>Work Order Cost</td>
					<td class="text-right">${formatMoney(workOrderCost)}</td>
				</tr>
				<tr>
					<td>Purchased Parts</td>
					<td class="text-right">${formatMoney(purchasedPartsTotal)}</td>
				</tr>
				<tr>
					<td><strong>TOTAL</strong></td>
					<td class="text-right"><strong>${formatMoney(grossTotal)}</strong></td>
				</tr>
			`;

			if (parts.length) {
				document.getElementById('pm_purchased_items').innerHTML = parts.map(part => {
					const qty = Number(part.quantity) || 0;
					const price = Number(part.product_price) || 0;
					const itemName = [part.product_name, part.product_model].filter(Boolean).join(' ');
					return `
						<tr>
							<td>${escapeHtml(itemName || part.product_code || 'Item')}</td>
							<td class="text-right">${qty}</td>
							<td class="text-right">${formatMoney(price)}</td>
							<td class="text-right">${formatMoney(qty * price)}</td>
						</tr>
					`;
				}).join('');
			} else {
				document.getElementById('pm_purchased_items').innerHTML = '<tr><td colspan="4" style="text-align:center;">No purchased items</td></tr>';
			}

			document.getElementById('payment_method').value = payment.payment_method || 'Cash';
			document.getElementById('reference_number').value = payment.reference_number || '';
			document.getElementById('amount_paid').value = Number(payment.amount_paid) > 0 ? Number(payment.amount_paid).toFixed(2) : '';
			document.getElementById('discount_amount').value = Number(payment.discount_amount) > 0 ? Number(payment.discount_amount).toFixed(2) : '0';
			document.getElementById('payment_notes').value = payment.notes || '';

			toggleReferenceNumber();
			updatePaymentComputation();
			renderPaymentHistory();
			document.getElementById('refundPaymentBtn').disabled = !(getComputedPaymentValues().refundable > 0);
			document.getElementById('printReceiptBtn').disabled = false;
			document.getElementById('emailReceiptBtn').disabled = !payment.customer_email;
		}

		function toggleReferenceNumber() {
			const method = document.getElementById('payment_method').value;
			const group = document.getElementById('referenceNumberGroup');
			const input = document.getElementById('reference_number');
			const isDigital = digitalPaymentMethods.includes(method);
			group.style.display = isDigital ? 'block' : 'none';
			input.required = isDigital;
			if (!isDigital) {
				input.value = '';
			}
		}

		function getComputedPaymentValues() {
			const costs = currentPaymentDetails ? (currentPaymentDetails.costs || {}) : {};
			const computed = currentPaymentDetails ? (currentPaymentDetails.computed || {}) : {};
			const total = Number(costs.gross_total) || 0;
			const discountInput = Number(document.getElementById('discount_amount').value) || 0;
			const discount = Math.min(Math.max(discountInput, 0), total);
			const paid = Math.max(Number(document.getElementById('amount_paid').value) || 0, 0);
			const refunded = Math.min(Math.max(Number(computed.total_refunded) || 0, 0), paid);
			const netTotal = Math.max(total - discount, 0);
			const actualPaid = Math.max(paid - refunded, 0);
			const change = Math.max(actualPaid - netTotal, 0);
			const remaining = Math.max(netTotal - actualPaid, 0);
			const refundable = Math.max(paid - refunded, 0);
			let status = 'Unpaid';

			if (paid > 0 && refunded >= paid) {
				status = 'Refunded';
			} else if (refunded > 0) {
				status = 'Partially Refunded';
			} else if (netTotal <= 0 || paid >= netTotal) {
				status = 'Paid';
			} else if (paid > 0) {
				status = 'Partial';
			}

			return { total, discount, netTotal, paid, refunded, actualPaid, change, remaining, refundable, status };
		}

		function updatePaymentComputation() {
			const values = getComputedPaymentValues();
			document.getElementById('pm_total_amount').textContent = formatMoney(values.total);
			document.getElementById('pm_discount_amount').textContent = formatMoney(values.discount);
			document.getElementById('pm_net_total').textContent = formatMoney(values.netTotal);
			document.getElementById('pm_amount_paid').textContent = formatMoney(values.paid);
			document.getElementById('pm_refunded_amount').textContent = formatMoney(values.refunded);
			document.getElementById('pm_actual_paid').textContent = formatMoney(values.actualPaid);
			document.getElementById('pm_change_amount').textContent = formatMoney(values.change);
			document.getElementById('pm_remaining_balance').textContent = formatMoney(values.remaining);
			document.getElementById('pm_payment_status').textContent = values.status;
			document.getElementById('pm_payment_status').className = getStatusClass(values.status);
			document.getElementById('refundPaymentBtn').disabled = !(values.refundable > 0);
		}

		function updateListRow(paymentId, status, total, paidDate) {
			const statusEl = document.getElementById('payment-status-' + paymentId);
			const totalEl = document.getElementById('payment-total-' + paymentId);
			const paidDateEl = document.getElementById('payment-date-paid-' + paymentId);

			if (statusEl) {
				statusEl.textContent = status;
				statusEl.className = 'badge ' + (status === 'Paid' ? 'bg-admin' : (status === 'Partial' ? 'bg-info' : (status.includes('Refunded') ? 'bg-danger' : 'bg-staff')));
				statusEl.style.display = 'grid';
				statusEl.style.alignItems = 'center';
				statusEl.style.justifyContent = 'center';
			}

			if (totalEl) {
				totalEl.textContent = 'Php ' + Number(total || 0).toLocaleString('en-PH', {
					minimumFractionDigits: 2,
					maximumFractionDigits: 2
				});
			}

			if (paidDateEl) {
				paidDateEl.textContent = formatDateForDisplay(paidDate);
			}
		}

		document.getElementById('payment_method').addEventListener('change', function () {
			toggleReferenceNumber();
			updatePaymentComputation();
		});

		document.getElementById('amount_paid').addEventListener('input', updatePaymentComputation);
		document.getElementById('discount_amount').addEventListener('input', updatePaymentComputation);

		function renderPaymentHistory() {
			if (!currentPaymentDetails) return;

			const payment = currentPaymentDetails.payment || {};
			const refunds = currentPaymentDetails.refunds || [];
			const entries = [];
			const amountPaid = Number(payment.amount_paid) || 0;

			if (amountPaid > 0) {
				entries.push({
					type: 'payment',
					date: payment.date || payment.created_at || '',
					title: '+ ' + formatMoney(amountPaid) + ' ' + (payment.payment_method || 'Payment'),
					detail: payment.reference_number ? 'Ref #: ' + payment.reference_number : 'Payment received',
					amountClass: 'inflow'
				});
			}

			refunds.forEach(refund => {
				entries.push({
					type: 'refund',
					date: refund.refunded_at || '',
					title: '- ' + formatMoney(refund.refund_amount) + ' Refund',
					detail: (refund.reason || 'Refund') + (refund.refund_method ? ' via ' + refund.refund_method : ''),
					amountClass: 'outflow'
				});
			});

			if (!entries.length) {
				document.getElementById('payment_history').innerHTML = `
					<div class="payment-history-entry">
						<div>No payment or refund history yet</div>
					</div>
				`;
				return;
			}

			document.getElementById('payment_history').innerHTML = entries.map(entry => `
				<div class="payment-history-entry">
					<div>
						<strong>${escapeHtml(entry.title)}</strong>
						<small>${escapeHtml(entry.detail)}</small>
					</div>
					<div class="payment-history-amount ${entry.amountClass}">
						${escapeHtml(entry.date || '—')}
					</div>
				</div>
			`).join('');
		}

		function openRefundModal() {
			if (!currentPaymentDetails) return;

			const values = getComputedPaymentValues();
			if (values.refundable <= 0) {
				showPaymentAlert('There is no refundable balance for this payment.', 'error');
				return;
			}

			clearRefundAlert();
			document.getElementById('refundForm').reset();
			document.getElementById('refund_payment_id').value = document.getElementById('payment_id').value;
			document.getElementById('refund_total_paid').textContent = formatMoney(values.paid);
			document.getElementById('refund_total_refunded').textContent = formatMoney(values.refunded);
			document.getElementById('refund_refundable_balance').textContent = formatMoney(values.refundable);
			document.getElementById('refund_amount').max = values.refundable.toFixed(2);
			document.getElementById('refundModal').classList.add('show');
			document.getElementById('refundModal').setAttribute('aria-hidden', 'false');
		}

		function closeRefundModal() {
			document.getElementById('refundModal').classList.remove('show');
			document.getElementById('refundModal').setAttribute('aria-hidden', 'true');
		}

		document.getElementById('paymentForm').addEventListener('submit', function (event) {
			event.preventDefault();
			clearPaymentAlert();

			const submitBtn = document.getElementById('confirmPaymentBtn');
			const formData = new FormData(this);
			submitBtn.disabled = true;
			submitBtn.textContent = 'Saving...';

			fetch('src/handlers/confirm_payment.php', {
				method: 'POST',
				body: formData
			})
				.then(response => response.json())
				.then(data => {
					if (!data.success) {
						showPaymentAlert(data.message || 'Failed to save payment', 'error');
						return;
					}

					const paymentId = document.getElementById('payment_id').value;
					currentPaymentDetails.payment = {
						...currentPaymentDetails.payment,
						payment_method: data.payment_method,
						reference_number: data.reference_number,
						discount_amount: data.discount_amount,
						amount_paid: data.amount_paid,
						change_amount: data.change_amount,
						remaining_balance: data.remaining_balance,
						payment_status: data.payment_status,
						status: data.payment_status,
						date: data.date,
						notes: document.getElementById('payment_notes').value
					};
					currentPaymentDetails.computed = {
						...(currentPaymentDetails.computed || {}),
						total_refunded: data.total_refunded,
						actual_paid: data.actual_paid,
						refundable_balance: data.refundable_balance
					};
					if (data.repair_status) {
						currentPaymentDetails.payment.work_order_status = data.repair_status;
						document.getElementById('pm_repair_status').textContent = data.repair_status;
					}

					updatePaymentComputation();
					renderPaymentHistory();
					updateListRow(paymentId, data.payment_status, data.total_amount, data.date);
					document.getElementById('printReceiptBtn').disabled = false;
					document.getElementById('emailReceiptBtn').disabled = !(currentPaymentDetails.payment && currentPaymentDetails.payment.customer_email);
					showPaymentAlert(data.message || 'Payment saved successfully', 'success');
				})
				.catch(error => {
					console.error(error);
					showPaymentAlert('Error saving payment', 'error');
				})
				.finally(() => {
					submitBtn.disabled = false;
					submitBtn.textContent = 'Confirm Payment';
				});
		});

		document.getElementById('refundForm').addEventListener('submit', function (event) {
			event.preventDefault();
			clearRefundAlert();

			const submitBtn = document.getElementById('confirmRefundBtn');
			const formData = new FormData(this);
			submitBtn.disabled = true;
			submitBtn.textContent = 'Saving...';

			fetch('src/handlers/create_refund.php', {
				method: 'POST',
				body: formData
			})
				.then(response => response.json())
				.then(data => {
					if (!data.success) {
						showRefundAlert(data.message || 'Failed to save refund', 'error');
						return;
					}

					const paymentId = document.getElementById('payment_id').value;
					const refundAmount = Number(data.refund_amount) || 0;
					const refundMethod = document.getElementById('refund_method').value;
					const reason = document.getElementById('refund_reason').value;

					currentPaymentDetails.refunds = currentPaymentDetails.refunds || [];
					currentPaymentDetails.refunds.unshift({
						refund_amount: refundAmount,
						refund_method: refundMethod,
						reason: reason,
						refunded_at: new Date().toLocaleString('en-PH')
					});
					currentPaymentDetails.computed = {
						...(currentPaymentDetails.computed || {}),
						total_refunded: data.total_refunded,
						actual_paid: data.actual_paid,
						refundable_balance: data.refundable_balance
					};
					currentPaymentDetails.payment = {
						...currentPaymentDetails.payment,
						payment_status: data.payment_status,
						status: data.payment_status,
						change_amount: data.change_amount,
						remaining_balance: data.remaining_balance
					};

					updatePaymentComputation();
					renderPaymentHistory();
					updateListRow(paymentId, data.payment_status, currentPaymentDetails.payment.total_amount);
					closeRefundModal();
					showPaymentAlert(data.message || 'Refund saved successfully', 'success');
				})
				.catch(error => {
					console.error(error);
					showRefundAlert('Error saving refund', 'error');
				})
				.finally(() => {
					submitBtn.disabled = false;
					submitBtn.textContent = 'Confirm Refund';
				});
		});

		function buildReceiptHtml() {
			if (!currentPaymentDetails) return '';

			const payment = currentPaymentDetails.payment || {};
			const parts = currentPaymentDetails.purchasedParts || [];
			const values = getComputedPaymentValues();
			const device = [payment.brand, payment.model].filter(Boolean).join(' ') || payment.unit_type || '—';
			const ref = document.getElementById('reference_number').value;
			const method = document.getElementById('payment_method').value;
			const notes = document.getElementById('payment_notes').value;

			const partsRows = parts.length ? parts.map(part => {
				const qty = Number(part.quantity) || 0;
				const price = Number(part.product_price) || 0;
				const itemName = [part.product_name, part.product_model].filter(Boolean).join(' ') || part.product_code || 'Item';
				return `<tr><td>${escapeHtml(itemName)}</td><td style="text-align:right;">${qty}</td><td style="text-align:right;">${formatMoney(price)}</td><td style="text-align:right;">${formatMoney(qty * price)}</td></tr>`;
			}).join('') : '<tr><td colspan="4" style="text-align:center;">No purchased items</td></tr>';

			return `
				<!doctype html>
				<html>
				<head>
					<meta charset="utf-8">
					<title>Payment Receipt</title>
					<style>
						body { font-family: Arial, sans-serif; color: #111827; margin: 32px; }
						h2, h3 { margin: 0 0 12px; }
						.receipt-header { border-bottom: 2px solid #111827; padding-bottom: 16px; margin-bottom: 20px; }
						.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; margin-bottom: 20px; }
						table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
						th, td { border-bottom: 1px solid #e5e7eb; padding: 9px; }
						th { text-align: left; background: #f8fafc; }
						.right { text-align: right; }
						.total { font-weight: 700; }
					</style>
				</head>
				<body>
					<div class="receipt-header">
						<h2>MACPROTECH Payment Receipt</h2>
						<div>Payment Code: ${escapeHtml(payment.payment_code)}</div>
						<div>Date: ${escapeHtml(new Date().toLocaleDateString('en-PH'))}</div>
					</div>
					<div class="grid">
						<div><strong>Work Order:</strong> ${escapeHtml(payment.work_order_code)}</div>
						<div><strong>Customer:</strong> ${escapeHtml(payment.customer_name)}</div>
						<div><strong>Device:</strong> ${escapeHtml(device)}</div>
						<div><strong>Technician:</strong> ${escapeHtml(payment.technician_name)}</div>
						<div><strong>Status:</strong> ${escapeHtml(values.status)}</div>
						<div><strong>Method:</strong> ${escapeHtml(method)}${ref ? ' / Ref #: ' + escapeHtml(ref) : ''}</div>
					</div>
					<h3>Cost Breakdown</h3>
					<table>
						<tr><td>Diagnostic Fee</td><td class="right">${formatMoney(currentPaymentDetails.costs.diagnostic_fee)}</td></tr>
						<tr><td>Work Order Cost</td><td class="right">${formatMoney(currentPaymentDetails.costs.work_order_cost)}</td></tr>
						<tr><td>Purchased Parts</td><td class="right">${formatMoney(currentPaymentDetails.costs.purchased_parts_total)}</td></tr>
						<tr><td class="total">Total</td><td class="right total">${formatMoney(values.total)}</td></tr>
					</table>
					<h3>Purchased Items</h3>
					<table>
						<tr><th>Item</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Subtotal</th></tr>
						${partsRows}
					</table>
					<h3>Payment</h3>
					<table>
						<tr><td>Total</td><td class="right">${formatMoney(values.total)}</td></tr>
						<tr><td>Discount</td><td class="right">${formatMoney(values.discount)}</td></tr>
						<tr><td>Amount Due</td><td class="right">${formatMoney(values.netTotal)}</td></tr>
						<tr><td>Paid</td><td class="right">${formatMoney(values.paid)}</td></tr>
						<tr><td>Refunded</td><td class="right">${formatMoney(values.refunded)}</td></tr>
						<tr><td>Actual Paid</td><td class="right">${formatMoney(values.actualPaid)}</td></tr>
						<tr><td>Change</td><td class="right">${formatMoney(values.change)}</td></tr>
						<tr><td>Remaining</td><td class="right">${formatMoney(values.remaining)}</td></tr>
					</table>
					${notes ? '<p><strong>Notes:</strong> ' + escapeHtml(notes) + '</p>' : ''}
				</body>
				</html>
			`;
		}

		function printPaymentReceipt() {
			const receiptHtml = buildReceiptHtml();
			if (!receiptHtml) return;

			const receiptWindow = window.open('', '_blank', 'width=820,height=900');
			if (!receiptWindow) {
				showPaymentAlert('Please allow pop-ups to print the receipt', 'error');
				return;
			}

			receiptWindow.document.open();
			receiptWindow.document.write(receiptHtml);
			receiptWindow.document.close();
			receiptWindow.focus();
			receiptWindow.print();
		}

		function emailPaymentReceipt() {
			if (!currentPaymentDetails || !currentPaymentDetails.payment || !currentPaymentDetails.payment.customer_email) return;

			clearPaymentAlert();

			const button = document.getElementById('emailReceiptBtn');
			const formData = new FormData();
			formData.append('payment_id', document.getElementById('payment_id').value);
			button.disabled = true;
			button.textContent = 'Sending...';

			fetch('src/handlers/send_payment_receipt.php', {
				method: 'POST',
				body: formData
			})
				.then(response => response.json())
				.then(data => {
					if (!data.success) {
						showPaymentAlert(data.message || 'Failed to send receipt email', 'error');
						return;
					}

					showPaymentAlert(data.message || 'Receipt emailed successfully', 'success');
				})
				.catch(error => {
					console.error(error);
					showPaymentAlert('Error sending receipt email', 'error');
				})
				.finally(() => {
					button.disabled = false;
					button.textContent = 'Email Receipt';
				});
		}
	</script>
</html>
