
<?php
	include 'header.php';
	include 'sidebar.php'; 
?>
	<!-- EDIT CATEGORY MODAL (Pure CSS) -->
	<input type="checkbox" id="editCategoryToggle" class="edit-client-toggle">
	<label for="editCategoryToggle" class="css-modal-overlay edit-client-overlay"></label>

	<div class="edit-client-modal-container">
		<div class="css-modal-content">
			<!-- Modal Header -->
			<div class="css-modal-header">
				<h5 class="css-modal-title">Edit Category</h5>
				<label for="editCategoryToggle" class="css-modal-close">&times;</label>
			</div>

			<!-- Modal Body -->
			<div class="css-modal-body">
				<!-- Form -->
				<form method="POST" action="src/handlers/edit_category.php">

					<input type="hidden" name="id" id="categoryIdField" value="">
					
					<div class="form-group">
						<label class="form-label">Category Name</label>
						<input type="text" class="form-control" id="editCategoryName" name="category" required>
					</div>

					<!-- Modal Footer -->
					<div class="css-modal-footer">
						<label for="editCategoryToggle" class="btn btn-secondary">Cancel</label>
						<button type="submit" name="edit_category" class="btn btn-primary">Save Changes</button>
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
						<div class="col-md-6 col-sm-12">
							<div class="title">
								<h4><i class="micon dw dw-file mtext"></i> Item Category</h4>
							</div>
						</div>
					</div>
				</div>
				<!-- Simple Datatable start -->
				<div class="card-box mb-30">
					<div class="pd-20">
						<h4 class="text-blue h4">Category List</h4>
					</div>
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
						<div class="col-sm-12 col-md-6">
							<div id="DataTables_Table_0_filter" class="dataTables_filter">
								<form method="GET">
										<input type="search" name="search" class="form-control form-control-sm" placeholder="Search Category..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" autocomplete="off">
									</form>
							</div>
						</div>
					</div>
					<div class="pb-20">
						<div class="row">
						<div class="col-md-4 col-sm-12">
							<div class="pd-20 ">
								<form method="POST" action="src/handlers/add_category.php">
									<div class="form-group">
										<label>Category Name</label>
										<input class="form-control" type="text" placeholder="input category name" name="category_name" autocomplete="off">
									</div>
									<div class="form-group">
										<button class="btn btn-primary" type="submit" name="add_category">Add Category</button>
									</div>
								</form>
							</div>
						</div>
						<div class="col-md-8 col-sm-12" style="width: 100%;">
							<table class="data-table table responsive">
								<thead>
									<tr>
										<th style="width: 80%;">Category Name</th>
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
								    $where .= " AND (LOWER(category_name) LIKE '%$s%')";
								}

								// Get total count for pagination info
								$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM item_category WHERE $where");
								$count_row = mysqli_fetch_assoc($count_result);
								$total_records = $count_row['total'];

								// Calculate offset
								$offset = ($current_page - 1) * $limit;
								$total_pages = ceil($total_records / $limit);
								$offset = min($offset, $total_records); // Prevent offset from exceeding total records

								// Correct table + column names with LIMIT and OFFSET
								$result = mysqli_query($conn, "SELECT * FROM item_category WHERE $where ORDER BY category_name ASC LIMIT $limit OFFSET $offset");
								$records_shown = mysqli_num_rows($result);
								$record_start = ($total_records > 0) ? $offset + 1 : 0;
								$record_end = min($offset + $records_shown, $total_records);
								?>
								<tbody>
									<?php while ($row = mysqli_fetch_assoc($result)) { 
										$category_name = $row['category_name'];?>
									<tr>
										<td><?= htmlspecialchars($category_name) ?></td>
										<td>
											<div class="dropdown">
												<a class="btn btn-link font-24 p-0 line-height-1 no-arrow dropdown-toggle" href="#" role="button" data-toggle="dropdown">
													<img src="src/images/menu-dots.png" width="25px" style="border: none">
												</a>
												<div class="dropdown-menu dropdown-menu-right dropdown-menu-icon-list">
													<a>
														<label class="dropdown-item" onclick="editCategory('<?= htmlspecialchars($row['category_name']) ?>','<?= $row['id'] ?>');">
															<i class="dw dw-edit2"></i> Edit
														</label>
													</a>
													<a class="dropdown-item" href="#" data-toggle="modal" data-target="#delete"><i class="dw dw-delete-3"></i> Delete</a>
												</div>
											</div>
										</td>
									</tr>
									<?php } ?>
								</tbody>
							</table>
						</div>
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
						<div class="col-sm-12 col-md-7">
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
											<p>Are you sure you want to delete this category?</p>
											<button type="button" class="btn btn-light" data-dismiss="modal">Yes</button>
											<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
										</div>
									</div>
								</div>
							</div>
					</div>

</html>

<script>
	// Function to populate edit modal and open it
	function editCategory(category_name, category_id) {
		document.getElementById('editCategoryName').value = category_name;
		document.getElementById('categoryIdField').value = category_id;
		
		// Trigger checkbox to open modal
		document.getElementById('editCategoryToggle').checked = true;
	}

	// Close edit modal when clicking outside
	document.getElementById('editCategoryToggle').addEventListener('change', function() {
		if (!this.checked) {
			// Clear form when modal closes
			document.getElementById('editCategoryName').value = '';
			document.getElementById('categoryIdFeild').value = '';
		}
	});
</script>
