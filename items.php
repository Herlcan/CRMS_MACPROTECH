
<?php 
	include 'header.php';
	include 'sidebar.php'; 

	function truncate_words($text, $limit = 10) {
		$words = explode(' ', $text);
		if (count($words) > $limit) {
			return implode(' ', array_slice($words, 0, $limit)) . '...';
		}
		return $text;
	}
?>

	<!-- Hidden checkbox for add item modal toggle (reuse add-client modal CSS) -->
<input type="checkbox" id="addItemToggle" class="add-item-toggle" />

	<!-- Add Item Modal Overlay (reuse add-client overlay) -->
	<label for="addItemToggle" class="css-modal-overlay add-client-overlay"></label>

	<!-- ADD ITEM MODAL -->
<div class="category-modal-container">
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
								<label class="form-label">Brand Name</label>
								<input type="text" class="form-control" placeholder="Input Brand Name" name="brand_name" required autocomplete="off">
							</div>

							<div class="form-group">
								<label class="form-label">Model</label>
								<input type="text" class="form-control" placeholder="Input Model" name="model" required autocomplete="off">
							</div>
							
							<div class="form-group">
								<label class="form-label">Description</label>
								<textarea class="form-control" style="height: 220px;" placeholder="Input Description" name="description" required autocomplete="off"></textarea>
							</div>				
						</div>
						
						<div style="width: 55%; padding-left: 5%;">

							<?php $result = mysqli_query($conn, "SELECT * FROM item_category ORDER BY category_name ASC"); ?>
							
							<div class="form-group">
							<label class="form-label">Category</label>
							<select class="form-control" id="itemCategorySelect" name="category" required autocomplete="off" onchange="toggleOtherCategory('itemCategorySelect', 'otherCategoryInput')">
								<option value="">Select Category</option>
								<?php while ($row = mysqli_fetch_assoc($result)) { ?>
									<option value="<?= htmlspecialchars($row['id']) ?>">
										<?= htmlspecialchars($row['category_name']) ?>
									</option>
								<?php } ?>
								<option value="__other__">Other</option>
							</select>
							<input type="text" class="form-control" id="otherCategoryInput" name="other_category" placeholder="Enter category" autocomplete="off" style="display: none; margin-top: 10px;" disabled>
							</div>

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

	<!-- CATEGORY MANAGEMENT MODAL -->
<input type="checkbox" id="categoryModalToggle" class="category-toggle" />
<label for="categoryModalToggle" class="css-modal-overlay category-overlay"></label>
	<div class="category-management-modal-container">
		<div class="css-modal-content" style="max-width: 900px;">
			<div class="css-modal-header">
				<h5 class="css-modal-title">Manage Categories</h5>
				<label for="categoryModalToggle" class="css-modal-close">&times;</label>
			</div>
			<div class="css-modal-body">
				<div class="row">
					<div class="col-md-4 col-sm-12">
						<form method="POST" action="src/handlers/add_category.php">
							<input type="hidden" name="redirect" value="items.php">
							<div class="form-group">
								<label class="form-label">New Category</label>
								<input class="form-control" type="text" placeholder="Input category name" name="category_name" autocomplete="off" required>
							</div>
							<div class="css-modal-footer" style="padding: 0;">
								<button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
								<label for="categoryModalToggle" class="btn btn-secondary" style="margin-left: 10px;">Close</label>
							</div>
						</form>
					</div>
					<div class="col-md-8 col-sm-12">
						<h6 class="mb-20">Category List</h6>
						<?php $category_result = mysqli_query($conn, "SELECT * FROM item_category ORDER BY category_name ASC"); ?>
						<table class="data-table table responsive">
							<thead>
								<tr>
									<th>Category Name</th>
									<th class="datatable-nosort" style="width: 120px; text-align: center;">Action</th>
								</tr>
							</thead>
							<tbody>
								<?php while ($category_row = mysqli_fetch_assoc($category_result)) : ?>
								<tr>
									<td><?= htmlspecialchars($category_row['category_name']) ?></td>
									<td style="text-align: center;">
										<button type="button" class="btn btn-sm btn-outline-primary" onclick="editCategory('<?= addslashes(htmlspecialchars($category_row['category_name'])) ?>', '<?= $category_row['id'] ?>')">Edit</button>
									</td>
								</tr>
								<?php endwhile; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- EDIT CATEGORY MODAL (Pure CSS) -->
	<input type="checkbox" id="editCategoryToggle" class="edit-category-toggle">
	<label for="editCategoryToggle" class="css-modal-overlay edit-category-overlay"></label>

	<div class="edit-category-modal-container">
		<div class="css-modal-content">
			<div class="css-modal-header">
				<h5 class="css-modal-title">Edit Category</h5>
				<label for="editCategoryToggle" class="css-modal-close">&times;</label>
			</div>
			<div class="css-modal-body">
				<form method="POST" action="src/handlers/edit_category.php">
					<input type="hidden" name="redirect" value="items.php">
					<input type="hidden" name="id" id="categoryIdField" value="">
					<div class="form-group">
						<label class="form-label">Category Name</label>
						<input type="text" class="form-control" id="editCategoryName" name="category" required autocomplete="off">
					</div>
					<div class="css-modal-footer">
						<label for="editCategoryToggle" class="btn btn-secondary">Cancel</label>
						<button type="submit" name="edit_category" class="btn btn-primary">Save Changes</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- EDIT ITEM MODAL (Pure CSS) -->
	<input type="checkbox" id="editItemToggle" class="edit-item-toggle">
	<label for="editItemToggle" class="css-modal-overlay edit-item-overlay"></label>

	<div class="edit-item-modal-container">
		<div class="css-modal-content" style="max-width: 800px;">
			<div class="css-modal-header">
				<h5 class="css-modal-title">Edit Product Item</h5>
				<label for="editItemToggle" class="css-modal-close">&times;</label>
			</div>
			<div class="css-modal-body">
				<form method="POST" enctype="multipart/form-data" action="src/handlers/edit_item.php">
					<input type="hidden" name="id" id="editItemId" value="">
					<div class="row">
						<div style="width: 45%;">
							<div class="form-group">
								<label class="form-label">Image</label>
								<input type="file" class="form-control" placeholder="Upload Image (Optional)" name="image" autocomplete="off">
							</div>

							<div class="form-group">
								<label class="form-label">Brand Name</label>
								<input type="text" class="form-control" placeholder="Input Brand Name" id="editItemBrand" name="brand_name" required autocomplete="off">
							</div>

							<div class="form-group">
								<label class="form-label">Model</label>
								<input type="text" class="form-control" placeholder="Input Model" id="editItemModel" name="model" required autocomplete="off">
							</div>
							
							<div class="form-group">
								<label class="form-label">Description</label>
								<textarea class="form-control" style="height: 220px;" placeholder="Input Description" id="editItemDescription" name="description" required autocomplete="off"></textarea>
							</div>				
						</div>
						
						<div style="width: 55%; padding-left: 5%;">

							<?php $result_edit = mysqli_query($conn, "SELECT * FROM item_category ORDER BY category_name ASC"); ?>
							
							<div class="form-group">
							<label class="form-label">Category</label>
							<select class="form-control" id="editItemCategory" name="category" required autocomplete="off" onchange="toggleOtherCategory('editItemCategory', 'editOtherCategoryInput')">
								<option value="">Select Category</option>
								<?php while ($row_cat = mysqli_fetch_assoc($result_edit)) { ?>
									<option value="<?= htmlspecialchars($row_cat['id']) ?>">
										<?= htmlspecialchars($row_cat['category_name']) ?>
									</option>
								<?php } ?>
								<option value="__other__">Other</option>
							</select>
							<input type="text" class="form-control" id="editOtherCategoryInput" name="other_category" placeholder="Enter category" autocomplete="off" style="display: none; margin-top: 10px;" disabled>
							</div>

							<div class="form-group">
								<label class="form-label">Date</label>
								<input type="date" class="form-control" id="editItemDate" name="date" required autocomplete="off">
							</div>

							<div class="form-group">
								<label class="form-label">Capital</label>
								<input type="number" class="form-control" placeholder="Input Capital" id="editItemCapital" name="capital" step="0.01" required autocomplete="off">
							</div>
							
							<div class="form-group">
								<label class="form-label">Quantity</label>
								<input type="number" class="form-control" placeholder="Input Quantity" id="editItemQuantity" name="quantity" required autocomplete="off">
							</div>
							
							<div class="form-group">
								<label class="form-label">Markup Percentage (%)</label>
								<input type="number" class="form-control" placeholder="Input Markup Percentage" id="editItemMarkup" name="markup_percentage" step="0.01" required autocomplete="off">
							</div>
							
							<div class="form-group">
								<label class="form-label">Price</label>
								<input type="number" class="form-control" placeholder="Input Price" id="editItemPrice" name="price" step="0.01" required autocomplete="off">
							</div>
						</div>
					</div>
					
					<div class="css-modal-footer">
						<label for="editItemToggle" class="btn btn-secondary">Cancel</label>
						<button type="submit" name="edit_item" class="btn btn-primary">Save Changes</button>
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
					<label for="categoryModalToggle" class="btn btn-secondary" style="margin-right: 10px;">Category</label>
					<div class="dropdown d-inline-block">
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
										<input type="search" name="search" class="form-control form-control-sm" placeholder="Search items..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" autocomplete="off">
									</form>
								</label>
							</div>
						</div>
					</div>
					<div class="pb-20">
						<table class="data-table table responsive">
							<thead>
								<tr>
									<th style="width: 13%; text-align: center;">Product Code</th>
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
								    $where .= " AND (LOWER(brand_name) LIKE '%$s%' OR LOWER(model) LIKE '%$s%' OR LOWER(product_code) LIKE '%$s%')";
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
								$result = mysqli_query($conn, "SELECT * FROM items WHERE $where ORDER BY brand_name ASC LIMIT $limit OFFSET $offset");
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
												<?= htmlspecialchars($row['brand_name'] . " " . $row['model']) ?>
											</span>
											<small style="color: #6c757d; font-size: 12px;">
												<?= htmlspecialchars(truncate_words($row['description'])) ?>
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
												<a class="dropdown-item" href="#" onclick="editItem('<?= $row['id'] ?>', '<?= htmlspecialchars($row['brand_name']) ?>', '<?= htmlspecialchars($row['model']) ?>', '<?= htmlspecialchars($row['description']) ?>', '<?= $row['category_id'] ?>', '<?= $row['date'] ?>', '<?= $row['capital'] ?>', '<?= $row['quantity'] ?>', '<?= $row['markup_percentage'] ?>', '<?= $row['price'] ?>'); return false;"><i class="dw dw-edit2"></i> Edit</a>
												<a class="dropdown-item" href="src/handlers/delete_item.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this item?');"><i class="dw dw-delete-3"></i> Delete</a>
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

		const editCapitalInput = document.getElementById("editItemCapital");
		const editQuantityInput = document.getElementById("editItemQuantity");
		const editMarkupInput = document.getElementById("editItemMarkup");
		const editPriceInput = document.getElementById("editItemPrice");

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

		function calculateEditPrice() {
			const capital = parseFloat(editCapitalInput.value);
			const quantity = parseFloat(editQuantityInput.value);
			const markup = parseFloat(editMarkupInput.value);

			if (!isNaN(capital) && !isNaN(quantity) && quantity > 0 && !isNaN(markup)) {
				const capitalPerUnit = capital / quantity;
				const finalPrice = capitalPerUnit * (1 + markup / 100);
				editPriceInput.value = finalPrice.toFixed(2);
			} else {
				editPriceInput.value = "";
			}
		}

		if (capitalInput) capitalInput.addEventListener("input", calculatePrice);
		if (quantityInput) quantityInput.addEventListener("input", calculatePrice);
		if (markupInput) markupInput.addEventListener("input", calculatePrice);

		if (editCapitalInput) editCapitalInput.addEventListener("input", calculateEditPrice);
		if (editQuantityInput) editQuantityInput.addEventListener("input", calculateEditPrice);
		if (editMarkupInput) editMarkupInput.addEventListener("input", calculateEditPrice);
	});

	function editCategory(category_name, category_id) {
		document.getElementById('editCategoryName').value = category_name;
		document.getElementById('categoryIdField').value = category_id;
		document.getElementById('editCategoryToggle').checked = true;
	}

	function toggleOtherCategory(selectId, inputId) {
		const categorySelect = document.getElementById(selectId);
		const otherCategoryInput = document.getElementById(inputId);
		const isOther = categorySelect.value === '__other__';

		otherCategoryInput.style.display = isOther ? 'block' : 'none';
		otherCategoryInput.disabled = !isOther;
		otherCategoryInput.required = isOther;

		if (!isOther) {
			otherCategoryInput.value = '';
			otherCategoryInput.style.borderColor = '';
		}
	}

	function editItem(itemId, brand, model, description, categoryId, itemDate, capital, quantity, markup, price) {
		document.getElementById('editItemId').value = itemId;
		document.getElementById('editItemBrand').value = brand;
		document.getElementById('editItemModel').value = model;
		document.getElementById('editItemDescription').value = description;
		document.getElementById('editItemCategory').value = categoryId;
		toggleOtherCategory('editItemCategory', 'editOtherCategoryInput');
		document.getElementById('editItemDate').value = itemDate;
		document.getElementById('editItemCapital').value = capital;
		document.getElementById('editItemQuantity').value = quantity;
		document.getElementById('editItemMarkup').value = markup;
		document.getElementById('editItemPrice').value = price;
		document.getElementById('editItemToggle').checked = true;
	}
	</script>

<?php include 'footer.php'; ?>
</html>
