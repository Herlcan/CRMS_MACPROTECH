
<?php 
	include 'header.php';
	include 'sidebar.php';
	include 'src/db/connection.php';
?>

	<!-- Hidden checkbox for edit payment modal toggle -->
	<input type="checkbox" id="editPaymentToggle" class="edit-payment-toggle">

	<!-- Edit Payment Modal Overlay -->
	<label for="editPaymentToggle" class="css-modal-overlay edit-payment-overlay"></label>

	<!-- EDIT PAYMENT MODAL (Pure CSS) -->
	<div class="edit-payment-modal-container">
		<div class="css-modal-content">
			<!-- Modal Header -->
			<div class="css-modal-header">
				<h5 class="css-modal-title">Update Payment Status</h5>
				<label for="editPaymentToggle" class="css-modal-close">&times;</label>
			</div>

			<!-- Modal Body -->
			<div class="css-modal-body">
				<!-- Form -->
				<form method="POST" action="src/handlers/update_payment_status.php">
					<input type="hidden" name="payment_id" id="paymentIdField" value="">
					
					<div class="form-group">
						<label class="form-label">Payment Code</label>
						<input type="text" class="form-control" id="paymentCodeField" readonly>
					</div>

					<div class="form-group">
						<label class="form-label">Status</label>
						<select class="form-control" id="paymentStatusField" name="status" required>
							<option value="">Select Status</option>
							<option value="Pending">Pending</option>
							<option value="Paid">Paid</option>
							<option value="Overdue">Overdue</option>
						</select>
					</div>

					<div class="form-group">
						<label class="form-label">Paid Date</label>
						<input type="date" class="form-control" id="paymentDateField" name="date">
					</div>

					<!-- Modal Footer -->
					<div class="css-modal-footer">
						<label for="editPaymentToggle" class="btn btn-secondary">Cancel</label>
						<button type="submit" name="update_payment_status" class="btn btn-primary">Save Changes</button>
					</div>
				</form>
			</div>
		</div>
	</div>

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
						<div class="col-sm-12 col-md-6" style="margin-left: auto;">
							<div id="DataTables_Table_0_filter" class="dataTables_filter">
								<label>Search: 
									<form method="GET">
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
									<th style="text-align: center;">Total Amount</th>
									<th style="text-align: center;">Status</th>
									<th style="text-align: center;">Date</th>
									<th class="datatable-nosort" style="padding-left: 25px;">Action</th>
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
								    $s = mysqli_real_escape_string($conn, $_GET['search']);
								    $where .= " AND (LOWER(payment_code) LIKE '%$s%' OR LOWER(work_order) LIKE '%$s%' OR LOWER(total_amount) LIKE '%$s%')";
								}

								// Secure filter
								if (!empty($_GET['filter'])) {
								    $f = mysqli_real_escape_string($conn, $_GET['filter']);
								    $where .= " AND status='$f'";
								}

								// Get total count for pagination info
								$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM payments WHERE $where");
								$count_row = mysqli_fetch_assoc($count_result);
								$total_records = $count_row['total'];

								// Calculate offset
								$offset = ($current_page - 1) * $limit;
								$total_pages = ceil($total_records / $limit);
								$offset = min($offset, $total_records); // Prevent offset from exceeding total records

								// Correct table + column names with LIMIT and OFFSET
								$result = mysqli_query($conn, "SELECT * FROM payments WHERE $where ORDER BY payment_code ASC LIMIT $limit OFFSET $offset");
								$records_shown = mysqli_num_rows($result);
								$record_start = ($total_records > 0) ? $offset + 1 : 0;
								$record_end = min($offset + $records_shown, $total_records);
							?>
							<tbody>
								<?php while ($row = mysqli_fetch_assoc($result)) { ?>

								<tr>
									<td style="text-align: center;"><?= htmlspecialchars($row['payment_code']) ?></td>
									<td style="text-align: center;"><?= htmlspecialchars($row['work_order']) ?></td>
									<td style="text-align: center;">Php <?= htmlspecialchars($row['total_amount']) ?></td>
									<td>
										<?php
											$status = strtolower($row['status']);
											$status_class = '';

											if ($status == 'paid') {
												$status_class = 'bg-admin';
											} elseif ($status == 'pending') {
												$status_class = 'bg-staff';
											}
										?>
										<span class="badge <?= $status_class ?>" style="display: grid; align-items: center; justify-content: center;">
											<?= htmlspecialchars($row['status']) ?>
										</span>
									</td>
									<td style="text-align: center;"><?= htmlspecialchars($row['date']) ?></td>
									
									<td>
										<<a>
											<label class="dropdown-item" onclick="editUser('<?= htmlspecialchars($row['username']) ?>', '<?= htmlspecialchars($row['first_name']) ?>', '<?= htmlspecialchars($row['last_name']) ?>', '<?= htmlspecialchars($row['contact_num']) ?>', '<?= htmlspecialchars($row['email']) ?>', '<?= htmlspecialchars($row['role']) ?>', '<?= $row['id'] ?>');">
												<i class="dw dw-edit2"></i> View
											</label>
										</a>
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
</html>