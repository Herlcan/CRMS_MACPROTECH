<?php
	include 'header.php';
	include 'sidebar.php'; 
?>

	<div class="mobile-menu-overlay"></div>

	<div class="main-container">
		<div class="pd-ltr-20 xs-pd-20-10">
			<div class="min-height-200px">
				<div class="page-header">
					<div class="row">
						<div class="col-md-6 col-sm-12">
							<div class="title">
								<h4><i class="micon dw dw-shopping-basket mtext"></i> Work Order List</h4>
							</div>
							
						</div>
					</div>
				</div>
				<!-- Simple Datatable start -->
				<div class="card-box mb-30">
					<!-- Table Controls -->
					<div class="row mb-20">
						<div class="col-sm-12 col-md-6">
							<div class="dataTables_length" id="DataTables_Table_0_length">
								<label>Show 
									<form method="GET" style="display: inline;">
										<input type="hidden" name="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
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
						<div class="col-sm-12 col-md-6" style="display: flex; align-items: center;">
							<form method="GET" class="form-inline">

								<!-- Preserve search + limit -->
								<input type="hidden" name="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
								<input type="hidden" name="limit" value="<?= isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10' ?>">

								<select name="filter" class="form-control form-control-sm" onchange="this.form.submit()" style=" max-height: 40px;">
									<option value="">All Status</option>
									<option value="Pending" <?= (isset($_GET['filter']) && $_GET['filter']=='Pending')?'selected':'' ?>>Pending</option>
									<option value="Diagnosing" <?= (isset($_GET['filter']) && $_GET['filter']=='Diagnosing')?'selected':'' ?>>Diagnosing</option>
									<option value="Waiting for Parts" <?= (isset($_GET['filter']) && $_GET['filter']=='Waiting for Parts')?'selected':'' ?>>Waiting for Parts</option>
									<option value="In Progress" <?= (isset($_GET['filter']) && $_GET['filter']=='In Progress')?'selected':'' ?>>In Progress</option>
									<option value="Repaired" <?= (isset($_GET['filter']) && $_GET['filter']=='Repaired')?'selected':'' ?>>Repaired</option>
									<option value="Released" <?= (isset($_GET['filter']) && $_GET['filter']=='Released')?'selected':'' ?>>Released</option>
									<option value="Cancelled" <?= (isset($_GET['filter']) && $_GET['filter']=='Cancelled')?'selected':'' ?>>Cancelled</option>
								</select>

							</form>
						</div>
						<div class="col-sm-12 col-md-6" style="margin-left: auto;">
							<div id="DataTables_Table_0_filter" class="dataTables_filter">
								<label>Search:
									<form method="GET">
										<input type="search" name="search" class="form-control form-control-sm" placeholder="Search clients..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" autocomplete="off">
									</form>
								</label>
							</div>
						</div>
					</div>
					<div class="pb-20">
						<table class="data-table table responsive">
							<thead>
								<tr>
									<th style="width: 11%; text-align: center;">Work Order Code</th>
									<th style="width: 10%; text-align: center;">Request Date</th>
									<th style="width: 10%; text-align: center;">Unit Type</th>
									<th style="width: 10%; text-align: center;">Brand & Model</th>
									<th style="width: 20%; text-align: center;">Diagnoses</th>
									<th style="width: 10%; text-align: center;">Amount</th>
									<th style="width: 11%; text-align: center;">Completion Date</th>
									<th style="width: 10%; text-align: center;">Status</th>
									<th class="datatable-nosort" style="width: 8%; text-align: center;">Action</th>
								</tr>
							</thead>
							<?php
								$where = "1";
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
									$s = mysqli_real_escape_string($conn, strtolower($_GET['search']));
									$where .= " AND (
										LOWER(code) LIKE '%$s%' OR
										LOWER(unit_type) LIKE '%$s%' OR
										LOWER(brand) LIKE '%$s%' OR
										LOWER(model) LIKE '%$s%'
									)";
								}

								// Secure filter
								if (!empty($_GET['filter'])) {
									$allowed_status = ['Pending','Diagnosing','Waiting for Parts','In Progress','Repaired','Released','Cancelled'];

									if (in_array($_GET['filter'], $allowed_status)) {
										$f = mysqli_real_escape_string($conn, $_GET['filter']);
										if ($f === 'Repaired') {
											$where .= " AND status IN ('Repaired', 'Ready for Release')";
										} else {
											$where .= " AND status='$f'";
										}
									}
								}

								// Technician restriction
								if ($_SESSION["role"] == "Technician") {

								    $technician_id = intval($_SESSION['user_id']);

								    // Add technician filter to WHERE
								    $where .= " AND technician_id = $technician_id";
								}

								// Get total count for pagination info
								$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM work_order WHERE $where");
								$count_row = mysqli_fetch_assoc($count_result);
								$total_records = $count_row['total'];

								// Calculate offset
								$offset = ($current_page - 1) * $limit;
								$total_pages = ceil($total_records / $limit);
								$offset = min($offset, $total_records); // Prevent offset from exceeding total records

								// Correct table + column names with LIMIT and OFFSET
								$result = mysqli_query($conn, "SELECT * FROM work_order WHERE $where ORDER BY code DESC LIMIT $limit OFFSET $offset");
								$records_shown = mysqli_num_rows($result);
								$record_start = ($total_records > 0) ? $offset + 1 : 0;
								$record_end = min($offset + $records_shown, $total_records);
								
								function canEditStatus($conn) {

									if (!isset($_SESSION['user_id'])) {
										return false;
									}

									$user_id = intval($_SESSION['user_id']);

									$query = "SELECT role FROM users WHERE id = $user_id LIMIT 1";
									$result = mysqli_query($conn, $query);

									if ($row = mysqli_fetch_assoc($result)) {
										return in_array($row['role'], ['Administrator', 'Technician']);
									}

									return false;
									}

								$canEdit = canEditStatus($conn);
							?>
							<tbody>
								<?php while ($wo = mysqli_fetch_assoc($result)) { ?>
								<?php include 'src/partials/workorder_row_template.php'?>
								<?php } ?>
								<?php if ($total_records == 0): ?>
								<tr>
									<td colspan="7" style="text-align: center;">No work orders found</td>
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
										<a href="?page=<?= max(1, $current_page - 1) ?>&limit=<?= isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10' ?>&search=<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" aria-controls="DataTables_Table_0" class="page-link" <?= ($current_page <= 1) ? 'style="pointer-events: none;"' : '' ?>>
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
											echo '<li class="paginate_button page-item"><a href="?page=1&limit=' . (isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10') . '&search=' . (isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '') . '" class="page-link">1</a></li>';
											if ($start_page > 2) {
												echo '<li class="paginate_button page-item disabled"><span class="page-link">...</span></li>';
											}
										}
										
										for ($i = $start_page; $i <= $end_page; $i++) {
											$active = ($i == $current_page) ? 'active' : '';
											echo '<li class="paginate_button page-item ' . $active . '"><a href="?page=' . $i . '&limit=' . (isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10') . '&search=' . (isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '') . '" class="page-link">' . $i . '</a></li>';
										}
										
										if ($end_page < $total_pages) {
											if ($end_page < $total_pages - 1) {
												echo '<li class="paginate_button page-item disabled"><span class="page-link">...</span></li>';
											}
											echo '<li class="paginate_button page-item"><a href="?page=' . $total_pages . '&limit=' . (isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10') . '&search=' . (isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '') . '" class="page-link">' . $total_pages . '</a></li>';
										}
									?>

									<!-- Next Button -->
									<li class="paginate_button page-item next <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
										<a href="?page=<?= min($total_pages, $current_page + 1) ?>&limit=<?= isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10' ?>&search=<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" aria-controls="DataTables_Table_0" class="page-link" <?= ($current_page >= $total_pages) ? 'style="pointer-events: none;"' : '' ?>>
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

	<!-- View Work Order Modal -->
	<div class="modal fade" id="viewWorkOrderDrawer" tabindex="-1" role="dialog" aria-labelledby="viewWorkOrderLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-centered" role="document">
			<div class="modal-content" style="border-radius: 8px; box-shadow: 0 5px 25px rgba(0,0,0,0.2);">
				<!-- Modal Header -->
				<div class="modal-header" style="background: #1e1e2d; color: white; border: none;">
					<h5 class="modal-title" id="viewWorkOrderLabel" style="font-weight: 600; color: #fff;">Work Order Details</h5>
					<button type="button" class="close" onclick="closeViewDrawer()" style="color: white; opacity: 0.9;" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>

				<!-- Modal Body -->
				<div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 30px;">
					<!-- Progress Stepper -->
				<div class="progress-stepper" id="workOrderStepper" style="margin-bottom: 40px;"></div>
					<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
						<div class="row">
							<div class="col-md-6">
								<div style="margin-bottom: 15px;">
									<small style="color: #6c757d; font-weight: 600; text-transform: uppercase;">Work Order Code</small>
									<p id="vw_code" style="color: #333; margin: 5px 0 0 0; font-size: 1.1rem; font-weight: 600;">—</p>
								</div>
								<div style="margin-bottom: 15px;">
									<small style="color: #6c757d; font-weight: 600; text-transform: uppercase;">Request Date</small>
									<p id="vw_request_date" style="color: #555; margin: 5px 0 0 0;">—</p>
								</div>
								<div>
									<small style="color: #6c757d; font-weight: 600; text-transform: uppercase;">Technician</small>
									<p id="vw_technician" style="color: #555; margin: 5px 0 0 0;">—</p>
								</div>
							</div>
							<div class="col-md-6" style="padding-left:20%;">
								<div style="margin-bottom: 15px;">
									<small style="color: #6c757d; font-weight: 600; text-transform: uppercase;">Status</small>
									<p id="vw_status" style="color: #555; margin: 5px 0 0 0;">
										<span class="badge badge-info" style="font-size: 0.9rem; padding: 4px 8px; background: #0dcaf0;">Pending</span>
									</p>
								</div>
								<div style="margin-bottom: 15px;">
									<small style="color: #6c757d; font-weight: 600; text-transform: uppercase;">Completion Date</small>
									<p id="vw_completion_date" style="color: #555; margin: 5px 0 0 0;">—</p>
								</div>
								<div>
									<small style="color: #6c757d; font-weight: 600; text-transform: uppercase;">Unit Type</small>
									<p id="vw_unit_type" style="color: #555; margin: 5px 0 0 0;">—</p>
								</div>
							</div>
						</div>
					</div>

					<!-- Device Details -->
					<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
						<h6 style="font-weight: 700; margin-top: 0; margin-bottom: 15px; color: #333;">Device Information</h6>
						<div class="row">
							<div class="col-md-6">
								<div style="margin-bottom: 12px;">
									<small style="color: #6c757d; font-weight: 600;">Brand & Model</small>
									<p id="vw_brand_model" style="color: #555; margin: 5px 0 0 0;">—</p>
								</div>
							</div>
							<div class="col-md-6" style="padding-left: 17%;">
								<div style="margin-bottom: 12px;">
									<small style="color: #6c757d; font-weight: 600;">Specs/Accessories</small>
									<p id="vw_specs" style="color: #555; margin: 5px 0 0 0;">—</p>
								</div>
							</div>
						</div>
					</div>

					<!-- Diagnoses Section -->
					<div id="vw_diagnoses" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px; margin-bottom: 25px;">
						<small style="color: #856404; font-weight: 600; text-transform: uppercase;">Diagnoses / Problem Found</small>
						<p id="vw_prob_find" style="color: #333; margin: 8px 0 0 0; line-height: 1.6;">—</p>
					</div>

					<!-- Notes Section -->
					<div id="vw_notes_div" style="background: #e7f3ff; border-left: 4px solid #0d6efd; padding: 15px; border-radius: 4px; margin-bottom: 25px; display: none;">
						<small style="color: #004085; font-weight: 600; text-transform: uppercase;">Notes</small>
						<p id="vw_notes_text" style="color: #333; margin: 8px 0 0 0; line-height: 1.6;">—</p>
					</div>

					<!-- Parts Section -->
					<div id="vw_parts"></div>

					<!-- Payment Section -->
					<div id="wo_payment_div" style="display: none;">
						<hr style="margin: 40px 0;">
						<small style="color: #6c757d; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 15px;">Cost Summary</small>
						<div id="wo_payment" style="margin-bottom: 40px;"></div>
					</div>
				</div>

				<!-- Modal Footer -->
				<div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 15px 30px;">
					<button type="button" class="btn btn-secondary" onclick="closeViewDrawer()">Close</button>
				</div>
			</div>
		</div>
	</div>
		

					<div class="col-md-4 col-sm-12 mb-30">
							<div class="modal fade" id="delete" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
								<div class="modal-dialog modal-sm modal-dialog-centered">
									<div class="modal-content bg-danger text-white">
										<div class="modal-body text-center">
											<h3 class="text-white mb-15"><i class="fa fa-exclamation-triangle"></i> Alert</h3>
											<p>Are you sure you want to delete this Work Order?</p>
											<button type="button" class="btn btn-light" data-dismiss="modal">Yes</button>
											<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
										</div>
									</div>
								</div>
							</div>
					</div>
					<script>
						// View drawer functions (top-level so onclick handlers can call them)

		function openViewDrawer() {
			const modal = document.getElementById('viewWorkOrderDrawer');
			modal.classList.add('show');
			modal.style.display = 'block';
			document.body.style.overflow = 'hidden';
			
			// Create backdrop
			let backdrop = document.querySelector('.modal-backdrop');
			if (!backdrop) {
				backdrop = document.createElement('div');
				backdrop.className = 'modal-backdrop fade show';
				document.body.appendChild(backdrop);
			}
		}

		function closeViewDrawer() {
			const modal = document.getElementById('viewWorkOrderDrawer');
			modal.classList.remove('show');
			modal.style.display = 'none';
			document.body.style.overflow = 'auto';
			
			const backdrop = document.querySelector('.modal-backdrop');
			if (backdrop) {
				backdrop.remove();
			}
		}

		function renderWorkOrderStepper(currentStatus, cancelledFromStatus) {
			const stepper = document.getElementById('workOrderStepper');
			if (!stepper) return;

			const lifecycle = [
				'Pending',
				'Diagnosing',
				'Waiting for Parts',
				'In Progress',
				'Repaired',
				'Released'
			];
			const normalizeStatus = value => {
				if (value === 'Completed') return 'Released';
				if (value === 'Ready for Release') return 'Repaired';
				return value || 'Pending';
			};
			const status = normalizeStatus(currentStatus);
			const isCancelled = status === 'Cancelled';
			const activeStatus = normalizeStatus(isCancelled ? (cancelledFromStatus || 'Pending') : status);
			const activeIndex = Math.max(0, lifecycle.indexOf(activeStatus));
			let steps = lifecycle;

			if (isCancelled) {
				steps = lifecycle.slice(0, activeIndex + 1).concat('Cancelled');
			}

			stepper.innerHTML = steps.map((step, index) => {
				let state = '';

				if (step === 'Cancelled') {
					state = ' active cancelled';
				} else if (isCancelled) {
					state = index <= activeIndex ? ' completed' : '';
				} else if (index < activeIndex) {
					state = ' completed';
				} else if (index === activeIndex) {
					state = ' active' + (step === 'Released' ? ' completed' : '');
				}

				return `
					<div class="stepper-item${state}">
						<div class="stepper-circle">${index + 1}</div>
						<div class="stepper-label">${escapeHtml(step)}</div>
					</div>
				`;
			}).join('');
		}

function viewWorkOrder(id) {
			// Open drawer first, then fetch and render into modal
			openViewDrawer();
			fetch('/MACPROTECH/src/handlers/get_work_order.php?id=' + encodeURIComponent(id))

			.then(response => response.json())
			.then(data => {
				if (!data.success) {
					MacproDialog.error({
						title: 'Work Order Not Loaded',
						message: data.message || 'Failed to load work order.'
					});
					return;
				}

				const wo = data.workOrder;
				const purchased = data.purchasedParts || [];
				const clientParts = data.clientParts || [];
				const payments = data.payments || [];

				// Populate Header Section
				document.getElementById('vw_code').textContent = wo.code || '—';
				document.getElementById('vw_request_date').textContent = wo.request_date || '—';
				document.getElementById('vw_technician').textContent = wo.technician_name || '—';
				document.getElementById('vw_completion_date').textContent = wo.completion_date || '—';
				document.getElementById('vw_unit_type').textContent = wo.unit_type || '—';

				// Update status badge
				const statusBadge = document.querySelector('#vw_status .badge');
				const displayStatus = wo.status === 'Ready for Release' ? 'Repaired' : (wo.status || 'Pending');
				statusBadge.textContent = displayStatus;
				statusBadge.className = 'badge';
				const status = displayStatus.toLowerCase();
				if (status === 'pending') statusBadge.style.background = '#ffc107';
				else if (status === 'diagnosing') statusBadge.style.background = '#7c3aed';
				else if (status === 'waiting for parts') statusBadge.style.background = '#f59e0b';
				else if (status === 'in progress') statusBadge.style.background = '#0d6efd';
				else if (status === 'repaired' || status === 'released') statusBadge.style.background = '#198754';
				else if (status === 'cancelled') statusBadge.style.background = '#dc3545';
				else statusBadge.style.background = '#0dcaf0';

				renderWorkOrderStepper(wo.status || 'Pending', data.cancelledFromStatus || null);

				// Device Details
				document.getElementById('vw_brand_model').textContent = (wo.brand || '') + ' ' + (wo.model || '') || '—';
				document.getElementById('vw_specs').textContent = wo.specs_acce || '—';

				// Diagnoses
				document.getElementById('vw_prob_find').textContent = wo.prob_find || '—';

				// Notes - show only if present
				const notesDiv = document.getElementById('vw_notes_div');
				if (wo.notes) {
					notesDiv.style.display = 'block';
					document.getElementById('vw_notes_text').textContent = wo.notes;
				} else {
					notesDiv.style.display = 'none';
				}

				// Parts
				const partsEl = document.getElementById('vw_parts');
				let partsHtml = '';
				if (purchased.length > 0 || clientParts.length > 0) {
					partsHtml = '<hr style="margin: 25px 0;"><h6 style="font-weight: 700; margin-bottom: 15px; color: #333;">Parts Used</h6>';
					
					if (purchased.length) {
						partsHtml += `<div style="margin-bottom: 15px;">
							<small style="color: #6c757d; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 10px;">Purchased Parts</small>
							<div style="background: #f8f9fa; padding: 12px; border-radius: 6px;">
								<ul style="margin: 0; padding-left: 20px;">` + purchased.map(p => {
									const qty = parseFloat(p.quantity) || 0;
									const price = parseFloat(p.product_price) || 0;
									const total = qty * price;
									return `<li style="margin-bottom: 8px; color: #555;">${p.product_name||'Item'} <strong>x${qty}</strong> @ Php ${price.toFixed(2)} = <span style="color: #28a745; font-weight: 700;">Php ${total.toFixed(2)}</span></li>`;
								}).join('') + `</ul>
							</div>
						</div>`;
					}
					if (clientParts.length) {
						partsHtml += `<div>
							<small style="color: #6c757d; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 10px;">Client Provided Parts</small>
							<div style="background: #f1f9f4; border-left: 3px solid #28a745; padding: 12px; border-radius: 6px;">
								<ul style="margin: 0; padding-left: 20px;">` + clientParts.map(p => `<li style="margin-bottom: 8px; color: #555;">${p.product_name||'Item'} ${p.description ? `(${p.description})` : ''} <strong>x${p.quantity||1}</strong></li>`).join('') + `</ul>
							</div>
						</div>`;
					}
				}
				partsEl.innerHTML = partsHtml;

				// Payment summary
				const diagnosticFee = parseFloat(wo.diagnostic_fee) || 0;
				const workOrderCost = parseFloat(wo.work_order_cost) || 0;
				const purchasedPartTotal = purchased.reduce((sum, part) => {
					const quantity = parseFloat(part.quantity) || 0;
					const price = parseFloat(part.product_price) || 0;
					return sum + (quantity * price);
				}, 0);

				const grandTotal = diagnosticFee + workOrderCost + purchasedPartTotal;

				let paymentHtml = `
					<div class="row">
						<div class="col-md-4" style="display: grid; gap: 12px; width: 100%;">
							<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 12px; border-left: 3px solid #667eea;">
								<small style="color: #667eea; font-weight: 600;">Diagnostic Fee</small>
								<p style="font-size: 1.25rem; font-weight: 700; color: #667eea; margin: 8px 0 0 0;">Php ${diagnosticFee.toFixed(2)}</p>
							</div>
							<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 12px; border-left: 3px solid #667eea;">
								<small style="color: #667eea; font-weight: 600;">Work Order Cost</small>
								<p style="font-size: 1.25rem; font-weight: 700; color: #667eea; margin: 8px 0 0 0;">Php ${workOrderCost.toFixed(2)}</p>
							</div>
							<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 3px solid #667eea;">
								<small style="color: #667eea; font-weight: 600;">Purchased Parts</small>
								<p style="font-size: 1.25rem; font-weight: 700; color: #667eea; margin: 8px 0 0 0;">Php ${purchasedPartTotal.toFixed(2)}</p>
							</div>
						</div>
						<div class="col-md-8" style="margin-left: auto;">
							<div style="background: #333341; color: white; padding: 25px; border-radius: 8px; display: grid; place-items: center; height: 95%;">
								<small style="opacity: 0.9; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 8px; font-size: 0.9rem;">Total Amount</small>
								<p style="font-size: 2.5rem; font-weight: 700; margin: 0;">Php ${grandTotal.toFixed(2)}</p>
								<small style="opacity: 0.85; display: block; margin-top: 8px; font-size: 0.85rem;">(Diagnostic + Work Order + Parts)</small>
							</div>
						</div>
					</div>
				`;

				if (payments.length > 0) {
					paymentHtml += `<hr style="margin: 15px 0;"><small style="color: #6c757d; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 10px;">Payment Records</small>`;
					payments.forEach(payment => {
						paymentHtml += `
							<div style="background: #f1f9f4; border-left: 4px solid #28a745; padding: 15px; border-radius: 6px; margin-bottom: 10px;">
								<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
									<small style="color: #6c757d; font-weight: 600;">Payment Code: ${escapeHtml(payment.payment_code)}</small>
									<span class="badge badge-success" style="background: #28a745; padding: 5px 10px; border-radius: 4px; color: white;">${escapeHtml(payment.status)}</span>
								</div>
								<p style="font-size: 1.2rem; font-weight: 700; color: #28a745; margin: 5px 0;">Php ${parseFloat(payment.total_amount).toFixed(2)}</p>
								<small style="color: #6c757d;">Paid on: ${escapeHtml(payment.date)}</small>
							</div>
						`;
					});
				}

				document.getElementById('wo_payment').innerHTML = paymentHtml;
				document.getElementById('wo_payment_div').style.display = 'block';
			})
			.catch(err => {
				console.error(err);
				MacproDialog.error({
					title: 'Work Order Not Loaded',
					message: 'Error loading work order.'
				});
			});

		}
						// Handle View button clicks
						document.addEventListener('click', function(e) {
							const btn = e.target.closest('.view-workorder-btn');
							if (!btn) return;
							viewWorkOrder(btn.dataset.id);
							e.preventDefault();
						});

						function showModal() { 

								// legacy (work-order now uses viewWorkOrderDrawer)
								return;

								const modal = document.getElementById('viewWorkOrderDrawer');


							modal.classList.add('show');
							modal.style.display = 'block';
							document.body.style.overflow = 'hidden';
							
							// Create backdrop
							let backdrop = document.querySelector('.modal-backdrop');
							if (!backdrop) {
								backdrop = document.createElement('div');
								backdrop.className = 'modal-backdrop fade show';
								document.body.appendChild(backdrop);
							}
						}

						function closeModal() {
							const modalEl = document.getElementById('viewWorkOrderDrawer');
							if (modalEl) {
								modalEl.classList.remove('show');
								modalEl.style.display = 'none';
							}
							closeViewDrawer();

							document.body.style.overflow = 'auto';

							const backdrop = document.querySelector('.modal-backdrop');
							if (backdrop) backdrop.remove();
						}


						// Close modal when X button is clicked
						document.addEventListener('click', function(e) {
							if (e.target.closest('[data-dismiss="modal"]')) {
								closeModal();
							}
						});

						// Close modal when backdrop is clicked
						document.addEventListener('click', function(e) {
							if (e.target.classList.contains('modal-backdrop')) {
								closeModal();
							}
						});

							function loadWorkOrderDetails__disabled(woId) {
							fetch('src/handlers/get_workorder_details.php?id=' + woId)
								.then(res => {
									console.log('Response status:', res.status);
									return res.text();
								})
								.then(text => {
									console.log('Response text:', text);
									try {
										const data = JSON.parse(text);
												handleWorkOrderData__disabled(data);
									} catch (e) {
										console.error('JSON parse error:', e);
										MacproDialog.error({
											title: 'Response Error',
											message: 'Error parsing response: ' + e.message
										});
									}
								})
								.catch(err => {
									console.error('Fetch error:', err);
									MacproDialog.error({
										title: 'Work Order Not Loaded',
										message: 'Failed to load work order details: ' + err.message
									});
								});
						}

							function handleWorkOrderData__disabled(data) {
							if (data.error) {
								MacproDialog.error({
									title: 'Work Order Not Loaded',
									message: data.error
								});
								return;
							}

							const wo = data.workOrder;
							const purchased = data.purchasedParts || [];
							const clientParts = data.clientParts || [];
							const payments = data.payments || [];

							if (!wo) {
								MacproDialog.error({
									title: 'Work Order Not Loaded',
									message: 'No work order data received.'
								});
								return;
							}

							renderWorkOrderStepper(wo.status || 'Pending', data.cancelledFromStatus || null);
										document.getElementById('vw_code').textContent = wo.code || '—';
								document.getElementById('vw_request_date').textContent = wo.request_date || '—';
										document.getElementById('vw_status').innerHTML = `<span class="badge badge-info" style="font-size: 0.9rem; padding: 4px 8px; background: #0dcaf0;">${escapeHtml(wo.status || '—')}</span>`;
								document.getElementById('vw_completion_date').textContent = wo.completion_date || '—';

								// Populate device info
								document.getElementById('vw_unit_type').textContent = wo.unit_type || '—';
								document.getElementById('vw_brand_model').textContent = (wo.brand || '—') + ' ' + (wo.model || '');
								document.getElementById('vw_specs').textContent = wo.specs || '—';

							// Populate problem found
								document.getElementById('vw_prob_find').textContent = wo.prob_find || '—';

								// Populate notes if exist
								if (wo.remarks) {
											document.getElementById('vw_notes_div').style.display = 'block';
											document.getElementById('vw_notes_text').textContent = wo.remarks;
							} else {
												document.getElementById('vw_notes_div').style.display = 'none';
							}

							// Calculate and populate payment section
							const diagnosticFee = parseFloat(wo.diagnostic_fee) || 0;
							const workOrderCost = parseFloat(wo.work_order_cost) || 0;
							const purchasedPartTotal = purchased.reduce((sum, part) => {
								const quantity = parseFloat(part.quantity) || 0;
								const price = parseFloat(part.product_price) || 0;
								return sum + (quantity * price);
							}, 0);
							const grandTotal = diagnosticFee + workOrderCost + purchasedPartTotal;

							let paymentHtml = `
								<div class="row">
									<div class="col-md-4">
										<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 12px; border-left: 3px solid #667eea;">
											<small style="color: #667eea; font-weight: 600;">Diagnostic Fee</small>
											<p style="font-size: 1.25rem; font-weight: 700; color: #667eea; margin: 8px 0 0 0;">Php ${diagnosticFee.toFixed(2)}</p>
										</div>
										<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 12px; border-left: 3px solid #667eea;">
											<small style="color: #667eea; font-weight: 600;">Work Order Cost</small>
											<p style="font-size: 1.25rem; font-weight: 700; color: #667eea; margin: 8px 0 0 0;">Php ${workOrderCost.toFixed(2)}</p>
										</div>
										<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 3px solid #667eea;">
											<small style="color: #667eea; font-weight: 600;">Purchased Parts</small>
											<p style="font-size: 1.25rem; font-weight: 700; color: #667eea; margin: 8px 0 0 0;">Php ${purchasedPartTotal.toFixed(2)}</p>
										</div>
									</div>
									<div class="col-md-8">
										<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 8px; text-align: center; height: 100%;">
											<small style="opacity: 0.9; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 8px; font-size: 0.9rem;">Total Amount Due</small>
											<p style="font-size: 2.5rem; font-weight: 700; margin: 0;">Php ${grandTotal.toFixed(2)}</p>
											<small style="opacity: 0.85; display: block; margin-top: 8px; font-size: 0.85rem;">(Diagnostic + Work Order + Parts)</small>
										</div>
									</div>
								</div>
							`;

							if (payments.length > 0) {
								paymentHtml += `<hr style="margin: 15px 0;"><small style="color: #6c757d; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 10px;">Payment Records</small>`;
								payments.forEach(payment => {
									paymentHtml += `
										<div style="background: #f1f9f4; border-left: 4px solid #28a745; padding: 15px; border-radius: 6px; margin-bottom: 10px;">
											<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
												<small style="color: #6c757d; font-weight: 600;">Payment Code: ${escapeHtml(payment.payment_code)}</small>
												<span class="badge badge-success" style="background: #28a745; padding: 5px 10px; border-radius: 4px; color: white;">${escapeHtml(payment.status)}</span>
											</div>
											<p style="font-size: 1.2rem; font-weight: 700; color: #28a745; margin: 5px 0;">Php ${parseFloat(payment.total_amount).toFixed(2)}</p>
											<small style="color: #6c757d;">Paid on: ${escapeHtml(payment.date)}</small>
										</div>
									`;
								});
							}

											const paymentEl = document.getElementById('vw_payment');
											const paymentDivEl = document.getElementById('wo_payment_div');

											if (!paymentEl || !paymentDivEl) {
												console.error('Missing payment DOM elements');
											} else {
												paymentEl.innerHTML = paymentHtml;
												paymentDivEl.style.display = 'block';
											}


							// Populate parts section
							if (purchased.length > 0 || clientParts.length > 0) {
								let partsHtml = '';

								const partsEl = document.getElementById('vw_parts');
								const partsSectionEl = document.getElementById('vw_parts_section');

								if (!partsEl || !partsSectionEl) {
									console.error('Missing parts DOM elements');
									return;
								}


								if (purchased.length > 0) {
									partsHtml += `<div style="margin-bottom: 15px;">
										<small style="color: #6c757d; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 10px;">Purchased Parts</small>
										<div style="background: #f8f9fa; padding: 12px; border-radius: 6px;">
											<ul style="margin: 0; padding-left: 20px;">`;
									purchased.forEach(part => {
										const qty = parseFloat(part.quantity) || 0;
										const price = parseFloat(part.product_price) || 0;
										const total = qty * price;
										partsHtml += `<li style="margin-bottom: 8px; color: #555;">${escapeHtml(part.product_name || 'Item')} <strong>x${qty}</strong> @ Php ${price.toFixed(2)} = <span style="color: #28a745; font-weight: 700;">Php ${total.toFixed(2)}</span></li>`;
									});
									partsHtml += `</ul></div></div>`;
								}

								if (clientParts.length > 0) {
									partsHtml += `<div>
										<small style="color: #6c757d; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 10px;">Client Provided Parts</small>
										<div style="background: #f1f9f4; border-left: 3px solid #28a745; padding: 12px; border-radius: 6px;">
											<ul style="margin: 0; padding-left: 20px;">`;
									clientParts.forEach(part => {
										const qty = parseFloat(part.quantity) || 0;
										partsHtml += `<li style="margin-bottom: 8px; color: #555;">${escapeHtml(part.product_name || 'Item')} ${part.description ? `(${escapeHtml(part.description)})` : ''} <strong>x${qty}</strong></li>`;
									});
									partsHtml += `</ul></div></div>`;
								}

									document.getElementById('vw_parts').innerHTML = partsHtml;
											document.getElementById('vw_parts_section').style.display = 'block';

														} else {
											document.getElementById('vw_parts_section').style.display = 'none';

							}
						}

						function escapeHtml(text) {
							if (!text) return '';
							const map = {
								'&': '&amp;',
								'<': '&lt;',
								'>': '&gt;',
								'"': '&quot;',
								"'": '&#039;'
							};
							return String(text).replace(/[&<>"']/g, m => map[m]);
						}
						document.addEventListener('change', function (e) {

							if (!e.target.classList.contains('status-select')) return;

							let element = e.target;
							let wrapper = element.closest('.status-wrapper');
							let loading = wrapper ? wrapper.querySelector('.status-loading') : null;

							let workOrderId = element.dataset.id;
							let oldStatus = element.dataset.old;
							let newStatus = element.value;

							if (newStatus === oldStatus) return;

							MacproDialog.confirm({
								type: 'warning',
								title: 'Update Status?',
								message: 'Change status to ' + newStatus + '?',
								confirmLabel: 'Update Status'
							}).then((confirmed) => {

								if (!confirmed) {
									element.value = oldStatus;
									return;
								}

								// 🔒 Disable select
								element.disabled = true;

								// 🔄 Show spinner if exists
								if (loading) loading.style.display = 'flex';

								fetch('src/handlers/update_status.php', {
									method: 'POST',
									headers: {
										'Content-Type': 'application/x-www-form-urlencoded'
									},
									body: `id=${workOrderId}&status=${encodeURIComponent(newStatus)}&update_status=1`
								})
								.then(res => res.json())
								.then(data => {

									// 🔓 Re-enable select
									element.disabled = false;
									if (loading) loading.style.display = 'none';

									if (data.success) {

										// Update dataset old value
										element.dataset.old = newStatus;

										// Refresh only this row
										refreshRow(workOrderId);

										MacproDialog.success({
											title: 'Status Updated',
											message: 'Work order status updated successfully.',
											autoClose: 1500
										});

									} else {
										element.value = oldStatus;

										MacproDialog.error({
											title: 'Status Not Updated',
											message: data.message || 'Something went wrong.'
										});
									}
								})
								.catch(() => {

									element.disabled = false;
									if (loading) loading.style.display = 'none';

									element.value = oldStatus;

									MacproDialog.error({
										title: 'Status Not Updated',
										message: 'Request failed.'
									});
								});

							});
						});


						function refreshRow(id) {
							fetch('src/handlers/get_workorder_row.php?id=' + id)
							.then(res => res.text())
							.then(html => {
								let row = document.querySelector('#row-' + id);
								if (row) {
									row.outerHTML = html;
								}
							});
						}
					</script>
</body>
</html>
