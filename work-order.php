
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
									<option value="In Progress" <?= (isset($_GET['filter']) && $_GET['filter']=='In Progress')?'selected':'' ?>>In Progress</option>
									<option value="Repaired" <?= (isset($_GET['filter']) && $_GET['filter']=='Repaired')?'selected':'' ?>>Repaired</option>
									<option value="Completed" <?= (isset($_GET['filter']) && $_GET['filter']=='Completed')?'selected':'' ?>>Completed</option>
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
									$allowed_status = ['Pending','In Progress','Repaired','Completed','Cancelled'];

									if (in_array($_GET['filter'], $allowed_status)) {
										$f = mysqli_real_escape_string($conn, $_GET['filter']);
										$where .= " AND status='$f'";
									}
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
						<!-- Delete modal -->
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
					<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
					<script>
						document.addEventListener('change', function (e) {

							if (!e.target.classList.contains('status-select')) return;

							let element = e.target;
							let wrapper = element.closest('.status-wrapper');
							let loading = wrapper ? wrapper.querySelector('.status-loading') : null;

							let workOrderId = element.dataset.id;
							let oldStatus = element.dataset.old;
							let newStatus = element.value;

							if (newStatus === oldStatus) return;

							Swal.fire({
								title: 'Update Status?',
								text: "Change status to " + newStatus + "?",
								icon: 'question',
								showCancelButton: true,
								confirmButtonColor: '#28a745',
								cancelButtonColor: '#d33',
								confirmButtonText: 'Yes, update it!'
							}).then((result) => {

								if (!result.isConfirmed) {
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

										Swal.fire({
											icon: 'success',
											title: 'Updated!',
											text: 'Status updated successfully.',
											timer: 1500,
											showConfirmButton: false
										});

									} else {
										element.value = oldStatus;

										Swal.fire({
											icon: 'error',
											title: 'Error!',
											text: data.message || 'Something went wrong.'
										});
									}
								})
								.catch(() => {

									element.disabled = false;
									if (loading) loading.style.display = 'none';

									element.value = oldStatus;

									Swal.fire({
										icon: 'error',
										title: 'Error!',
										text: 'Request failed.'
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
