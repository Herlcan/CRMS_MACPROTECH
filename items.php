
<?php 
	include 'header.php';
	include 'sidebar.php'; 
?>

	<!-- Hidden checkbox for add item modal toggle (reuse add-client modal CSS) -->
	<input type="checkbox" id="addItemToggle" class="add-client-toggle">

	<!-- Add Item Modal Overlay (reuse add-client overlay) -->
	<label for="addItemToggle" class="css-modal-overlay add-client-overlay"></label>

	<!-- ADD ITEM MODAL -->
	<div class="add-client-modal-container">
		<div class="css-modal-content" style="max-width: 800px;">
			<!-- Modal Header -->
			<div class="css-modal-header">
				<h5 class="css-modal-title">Add New Product Item</h5>
				<label for="addItemToggle" class="css-modal-close">&times;</label>
			</div>

			<!-- Modal Body -->
			<div class="css-modal-body">
				<!-- Form -->
				<form method="POST" enctype="multipart/form-data" action="src/handlers/add_item.php">
					<div class="row">
						<div style="width: 45%;">
							<input type="hidden" name="client_id" value="<?=htmlspecialchars($client_id)?>">
							<input type="hidden" name="status" value="Pending">
							<div class="form-group">
								<label class="form-label">Image</label>
								<input type="file" class="form-control" placeholder="Uplaod Image" name="image" autocomplete="off">
							</div>

							<div class="form-group">
								<label class="form-label">Product Name</label>
								<input type="text" class="form-control" placeholder="Input Product Name" name="product_name" required autocomplete="off">
							</div>
							
							<div class="form-group">
								<label class="form-label">Description</label>
								<textarea class="form-control" style="height: 130px;" placeholder="Input Description" name="description" required autocomplete="off"></textarea>
							</div>
							
							<?php $result = mysqli_query($conn, "SELECT * FROM item_category ORDER BY category_name ASC"); ?>
							
							<div class="form-group">
							<label class="form-label">Category</label>
							<select class="form-control" name="category" required autocomplete="off">
								<option value="">Select Category</option>
								<?php while ($row = mysqli_fetch_assoc($result)) { ?>
									<option value="<?= htmlspecialchars($row['id']) ?>">
										<?= htmlspecialchars($row['category_name']) ?>
									</option>
								<?php } ?>
							</select>
					</div>
						</div>
						
						<div style="width: 55%; padding-left: 5%;">
							<div class="form-group">
								<label class="form-label">Date</label>
								<input type="date" class="form-control" name="date" required autocomplete="off">
							</div>

							<div class="form-group">
								<label class="form-label">Capital</label>
								<input type="number" class="form-control" placeholder="Input Capital" name="capital" id="capital" step="0.01" required autocomplete="off">
							</div>
							
							<div class="form-group">
								<label class="form-label">Quantity</label>
								<input type="number" class="form-control" placeholder="Input Quantity" name="quantity" id="quantity" required autocomplete="off">
							</div>
							
							<div class="form-group">
								<label class="form-label">Markup Percentage (%)</label>
								<input type="number" class="form-control" placeholder="Input Markup Percentage" name="markup_percentage" id="markup_percentage" step="0.01" required autocomplete="off">
							</div>
							
							<div class="form-group">
								<label class="form-label">Price</label>
								<input type="number" class="form-control" placeholder="Input Price" name="price" id="price" step="0.01" required autocomplete="off">
							</div>
						</div>
					</div>
					
					<!-- Modal Footer -->
					<div class="css-modal-footer">
						<label for="addItemToggle" class="btn btn-secondary">Cancel</label>
						<button type="submit" name="add_item" class="btn btn-primary">Add Product Item</button>
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
								<h4><i class="micon dw dw-table mtext"></i> Product Items</h4>
							</div>
						</div>
						<div class="col-md-6 col-sm-12 text-right" style="margin-left: auto;">
							<div class="dropdown">
								<label for="addItemToggle" class="btn btn-primary">Add New</label>
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

								<select name="category" class="form-control form-control-sm" onchange="this.form.submit()" style=" max-height: 40px;">
									<option value="">All Categories</option>

									<?php
									// Load categories dynamically (BEST PRACTICE ⭐)
									$cat_result = mysqli_query($conn, "SELECT * FROM item_category ORDER BY category_name ASC");

									while($cat = mysqli_fetch_assoc($cat_result)){
										$selected = (isset($_GET['category']) && $_GET['category']==$cat['id']) ? 'selected' : '';
										echo "<option value='".htmlspecialchars($cat['id'])."' $selected>
												".htmlspecialchars($cat['category_name'])."
											  </option>";
									}
									?>

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
									<th style="width: 10%; text-align: center;">Product Code</th>
									<th style="width: 10%; text-align: center;">Image</th>
									<th style="text-align: center;">Product Name</th>
									<th style="width: 10%; text-align: center;">Capital</th>
									<th style="width: 10%; text-align: center;">Quantity</th>
									<th style="width: 10%; text-align: center;">Price</th>
									<th class="datatable-nosort" style="width: 10%; text-align: center;">Action</th>
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
								    $where .= " AND (LOWER(product_name) LIKE '%$s%' OR LOWER(product_code) LIKE '%$s%')";
								}

								// Secure filter
								if (!empty($_GET['filter'])) {
								    $f = mysqli_real_escape_string($conn, $_GET['filter']);
								    $where .= " AND status='$f'";
								}
								
								// Category filter
								if (!empty($_GET['category'])) {
									$cat = mysqli_real_escape_string($conn, $_GET['category']);
									$where .= " AND category_id='$cat'";
								}

								// Get total count for pagination info
								$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM items WHERE $where");
								$count_row = mysqli_fetch_assoc($count_result);
								$total_records = $count_row['total'];

								// Calculate offset
								$offset = ($current_page - 1) * $limit;
								$total_pages = ceil($total_records / $limit);
								$offset = min($offset, $total_records); // Prevent offset from exceeding total records

								// Correct table + column names with LIMIT and OFFSET
								$result = mysqli_query($conn, "SELECT * FROM items WHERE $where ORDER BY product_code ASC LIMIT $limit OFFSET $offset");
								$records_shown = mysqli_num_rows($result);
								$record_start = ($total_records > 0) ? $offset + 1 : 0;
								$record_end = min($offset + $records_shown, $total_records);
								?>
							<tbody>
								<?php while ($row = mysqli_fetch_assoc($result)) { ?>
								<tr>
									<td style="text-align: center;"><?= htmlspecialchars($row['product_code']) ?></td>
									<td style="text-align: center;"><img src="./src/uploads/<?= htmlspecialchars($row['image']) ?>" style="border-radius: 10%; width: 50px; height: 50px;"></td>
									<td>
										<div style="display: flex; flex-direction: column;">
											<span style="font-weight: 600; font-size: 14px;">
												<?= htmlspecialchars($row['product_name']) ?>
											</span>
											<small style="color: #6c757d; font-size: 12px;">
												<?= htmlspecialchars($row['description']) ?>
											</small>
										</div>
									</td>
									<td style="text-align: center;"><?= "Php" . " " . number_format($row['capital'], 2) ?></td>
									<td style="text-align: center;"><?= htmlspecialchars($row['quantity']) ?></td>
									<td style="text-align: center;"><span><?= "Php" . " " . number_format($row['price'], 2) ?></span></td>
									<td style="text-align: center;">
										<div class="dropdown">
											<a class="btn btn-link font-24 p-0 line-height-1 no-arrow dropdown-toggle" href="#" role="button" data-toggle="dropdown">
												<img src="src/images/menu-dots.png" width="25px" style="border: none">
											</a>
											<div class="dropdown-menu dropdown-menu-right dropdown-menu-icon-list">
												<a class="dropdown-item" href="#"><i class="dw dw-edit2"></i> Edit</a>
												<a class="dropdown-item" href="#"><i class="dw dw-delete-3"></i> Delete</a>
											</div>
										</div>
									</td>
								</tr>
								<?php } ?>
								<?php if ($total_records == 0): ?>
								<tr>
									<td colspan="7" style="text-align: center;">No product items found</td>
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
	<script>
	document.addEventListener("DOMContentLoaded", function () {

		const capitalInput = document.getElementById("capital");
		const quantityInput = document.getElementById("quantity");
		const markupInput = document.getElementById("markup_percentage");
		const priceInput = document.getElementById("price");

		function calculatePrice() {

			const capital = parseFloat(capitalInput.value);
			const quantity = parseFloat(quantityInput.value);
			const markup = parseFloat(markupInput.value);

			if (!isNaN(capital) && !isNaN(quantity) && quantity > 0 && !isNaN(markup)) {

				const capitalPerUnit = capital / quantity;
				const finalPrice = capitalPerUnit * (1 + markup / 100);

				priceInput.value = finalPrice.toFixed(2);
			} else {
				priceInput.value = "";
			}
		}

		capitalInput.addEventListener("input", calculatePrice);
		quantityInput.addEventListener("input", calculatePrice);
		markupInput.addEventListener("input", calculatePrice);

	});
	</script>

<?php include 'footer.php'; ?>
</html>
