
<?php 
	include 'header.php';
	include 'sidebar.php'; 
	require_once __DIR__ . '/src/handlers/item_schema.php';
	require_once __DIR__ . '/src/handlers/inventory_transaction_schema.php';
	require_once __DIR__ . '/src/handlers/category_schema.php';

	try {
		ensure_item_category_name_column($conn);
		ensure_items_inventory_columns($conn);
		ensure_inventory_transaction_table($conn);
		backfill_inventory_transactions_from_items($conn);
		sync_all_items_from_inventory_transactions($conn);
		drop_items_capital_column($conn);
	} catch (Exception $e) {
		$_SESSION['dialog_flash'] = [
			'type' => 'error',
			'title' => 'Inventory Transaction Error',
			'message' => $e->getMessage()
		];
	}

	function truncate_words($text, $limit = 10) {
		$words = explode(' ', $text);
		if (count($words) > $limit) {
			return implode(' ', array_slice($words, 0, $limit)) . '...';
		}
		return $text;
	}

	$allowed_inventory_tabs = ['product_inventory', 'stock_records'];
	$active_tab = $_GET['tab'] ?? 'product_inventory';
	if (!in_array($active_tab, $allowed_inventory_tabs, true)) {
		$active_tab = 'product_inventory';
	}

	function inventory_url(array $overrides = []) {
		$params = array_merge($_GET, $overrides);
		foreach ($params as $key => $value) {
			if ($value === '' || $value === null) {
				unset($params[$key]);
			}
		}
		return '?' . http_build_query($params);
	}

	function inventory_tab_url($tab) {
		return inventory_url(['tab' => $tab, 'page' => 1]);
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
								<input type="text" class="form-control" id="otherCategoryInput" name="other_category" placeholder="Enter category" maxlength="50" autocomplete="off" style="display: none; margin-top: 10px;" disabled>
							</div>
						</div>

						<div style="width: 55%; padding-left: 5%;">

							<div class="form-group">
								<label class="form-label">Date</label>
								<input type="date" class="form-control" name="date" required autocomplete="off">
							</div>

							<div class="form-group">
								<label class="form-label">Description</label>
								<textarea class="form-control" style="height: 260px;" placeholder="Input Description" name="description" required autocomplete="off"></textarea>
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
								<input class="form-control" type="text" placeholder="Input category name" name="category_name" maxlength="50" autocomplete="off" required>
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
						<input type="text" class="form-control" id="editCategoryName" name="category" maxlength="50" required autocomplete="off">
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
								<input type="text" class="form-control" id="editOtherCategoryInput" name="other_category" placeholder="Enter category" maxlength="50" autocomplete="off" style="display: none; margin-top: 10px;" disabled>
							</div>
						</div>

						<div style="width: 55%; padding-left: 5%;">

							<div class="form-group">
								<label class="form-label">Date</label>
								<input type="date" class="form-control" id="editItemDate" name="date" required autocomplete="off">
							</div>

							<div class="form-group">
								<label class="form-label">Description</label>
								<textarea class="form-control" style="height: 260px;" placeholder="Input Description" id="editItemDescription" name="description" required autocomplete="off"></textarea>
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
								<h4><i class="micon dw dw-table mtext"></i> Inventory</h4>
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
					<div class="tab-header" style="margin: 0; padding: 0 20px;">
						<a href="<?= htmlspecialchars(inventory_tab_url('product_inventory')) ?>" style="padding: 12px 18px; font-weight: 600; color: <?= $active_tab === 'product_inventory' ? '#0d6efd' : '#666' ?>; border-bottom: <?= $active_tab === 'product_inventory' ? '2px solid #0d6efd' : '2px solid transparent' ?>;">Product Inventory</a>
						<a href="<?= htmlspecialchars(inventory_tab_url('stock_records')) ?>" style="padding: 12px 18px; font-weight: 600; color: <?= $active_tab === 'stock_records' ? '#0d6efd' : '#666' ?>; border-bottom: <?= $active_tab === 'stock_records' ? '2px solid #0d6efd' : '2px solid transparent' ?>;">Stock Records</a>
					</div>
					<div class="row mb-20">
						<div class="col-sm-12 col-md-6">
							<div class="dataTables_length" id="DataTables_Table_0_length">
								<label>Show 
									<form method="GET" style="display: inline;">
										<input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
										<input type="hidden" name="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
										<input type="hidden" name="category" value="<?= isset($_GET['category']) ? htmlspecialchars($_GET['category']) : '' ?>">
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
						<div class="col-sm-12 col-md-6" style="display: flex; align-items: center;">
							<form method="GET" class="form-inline">

								<!-- Preserve search + limit -->
								<input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
								<input type="hidden" name="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
								<input type="hidden" name="limit" value="<?= isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10' ?>">
								<?php if ($active_tab === 'stock_records'): ?>
									<input type="hidden" name="filter" value="<?= isset($_GET['filter']) ? htmlspecialchars($_GET['filter']) : '' ?>">
								<?php endif; ?>

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
							<?php if ($active_tab === 'stock_records'): ?>
								<form method="GET" class="form-inline" style="margin-left: 10px;">
									<input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
									<input type="hidden" name="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
									<input type="hidden" name="limit" value="<?= isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10' ?>">
									<input type="hidden" name="category" value="<?= isset($_GET['category']) ? htmlspecialchars($_GET['category']) : '' ?>">
									<select name="filter" class="form-control form-control-sm" onchange="this.form.submit()" style="max-height: 40px;">
										<option value="">All Status</option>
										<option value="In Stock" <?= (isset($_GET['filter']) && $_GET['filter'] === 'In Stock') ? 'selected' : '' ?>>In Stock</option>
										<option value="Out of Stock" <?= (isset($_GET['filter']) && $_GET['filter'] === 'Out of Stock') ? 'selected' : '' ?>>Out of Stock</option>
									</select>
								</form>
							<?php endif; ?>
						</div>
						<div class="col-sm-12 col-md-6" style="margin-left: auto;">
							<div id="DataTables_Table_0_filter" class="dataTables_filter">
								<label>Search:
									<form method="GET">
										<input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
										<input type="hidden" name="limit" value="<?= isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10' ?>">
										<input type="hidden" name="category" value="<?= isset($_GET['category']) ? htmlspecialchars($_GET['category']) : '' ?>">
										<input type="hidden" name="filter" value="<?= isset($_GET['filter']) ? htmlspecialchars($_GET['filter']) : '' ?>">
										<input type="search" name="search" class="form-control form-control-sm" placeholder="<?= $active_tab === 'stock_records' ? 'Search stock records...' : 'Search items...' ?>" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" autocomplete="off">
									</form>
								</label>
							</div>
						</div>
					</div>
					<div class="pb-20">
						<table class="data-table table responsive">
							<thead>
								<tr>
										<?php if ($active_tab === 'stock_records'): ?>
											<th style="text-align: center;">Product Code</th>
											<th style="text-align: center;">Product Name</th>
											<th style="text-align: center;">Total Stock-In</th>
											<th style="text-align: center;">Total Stock-Out</th>
											<th style="text-align: center;">Status</th>
											<th class="datatable-nosort" style="text-align: center;">Action</th>
									<?php else: ?>
										<th style="width: 13%; text-align: center;">Product Code</th>
										<th style="width: 10%; text-align: center;">Image</th>
										<th style="text-align: center;">Product Name</th>
										<th style="width: 10%; text-align: center;">Quantity</th>
										<th style="width: 12%; text-align: center;">Average Price</th>
										<th style="width: 12%; text-align: center;">Status</th>
										<th class="datatable-nosort" style="width: 10%; text-align: center;">Action</th>
									<?php endif; ?>
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
									if ($active_tab === 'stock_records') {
										$where .= " AND (LOWER(i.brand_name) LIKE '%$s%' OR LOWER(i.model) LIKE '%$s%' OR LOWER(CONCAT(i.brand_name, ' ', i.model)) LIKE '%$s%' OR LOWER(i.product_code) LIKE '%$s%' OR LOWER(it.status) LIKE '%$s%')";
									} else {
										$where .= " AND (LOWER(brand_name) LIKE '%$s%' OR LOWER(model) LIKE '%$s%' OR LOWER(product_code) LIKE '%$s%' OR LOWER(status) LIKE '%$s%')";
									}
								}
								
								// Category filter
								if (!empty($_GET['category'])) {
									$cat = mysqli_real_escape_string($conn, $_GET['category']);
									$where .= ($active_tab === 'stock_records') ? " AND i.category_id='$cat'" : " AND category_id='$cat'";
								}

								if ($active_tab === 'stock_records' && !empty($_GET['filter'])) {
									$allowed_status = ['In Stock', 'Out of Stock'];
									if (in_array($_GET['filter'], $allowed_status, true)) {
										$f = mysqli_real_escape_string($conn, $_GET['filter']);
										$where .= " AND it.status='$f'";
									}
								}

									// Get total count for pagination info
									if ($active_tab === 'stock_records') {
										$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM items i LEFT JOIN inventory_transaction it ON it.item_id = i.id WHERE $where");
									} else {
									$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM items WHERE $where");
								}
								$count_row = mysqli_fetch_assoc($count_result);
								$total_records = $count_row['total'];

								// Calculate offset
								$offset = ($current_page - 1) * $limit;
								$total_pages = max(1, (int) ceil($total_records / $limit));
								if ($current_page > $total_pages) {
									$current_page = $total_pages;
									$offset = ($current_page - 1) * $limit;
								}
								$offset = min($offset, $total_records); // Prevent offset from exceeding total records

								// Correct table + column names with LIMIT and OFFSET
									if ($active_tab === 'stock_records') {
										$result = mysqli_query($conn, "
											SELECT
												i.id AS item_id,
												i.product_code,
												i.brand_name,
												i.model,
												i.description,
												COALESCE(it.total_stock_in, 0) AS total_stock_in,
												COALESCE(it.total_stock_out, 0) AS total_stock_out,
												COALESCE(it.status, '') AS status
											FROM items i
											LEFT JOIN inventory_transaction it ON it.item_id = i.id
											WHERE $where
											ORDER BY i.brand_name ASC, i.model ASC
											LIMIT $limit OFFSET $offset
										");
								} else {
									$result = mysqli_query($conn, "SELECT * FROM items WHERE $where ORDER BY brand_name ASC LIMIT $limit OFFSET $offset");
								}
								$records_shown = mysqli_num_rows($result);
								$record_start = ($total_records > 0) ? $offset + 1 : 0;
								$record_end = min($offset + $records_shown, $total_records);
								?>
							<tbody>
								<?php while ($row = mysqli_fetch_assoc($result)) { ?>
									<?php if ($active_tab === 'stock_records'): ?>
										<?php
											$product_name = trim(($row['brand_name'] ?? '') . ' ' . ($row['model'] ?? ''));
											$status_value = trim($row['status'] ?? '');
											$status_style = $status_value === '' ? 'background: #f3f4f6; color: #6b7280;' : (strtolower($status_value) === 'out of stock' ? 'background: #fee2e2; color: #dc2626;' : 'background: #dcfce7; color: #16a34a;');
										?>
										<tr>
											<td style="text-align: center;"><?= htmlspecialchars($row['product_code'] ?? '') ?></td>
											<td><?= htmlspecialchars($product_name) ?></td>
											<td style="text-align: center;"><?= htmlspecialchars((string) $row['total_stock_in']) ?></td>
											<td style="text-align: center;"><?= htmlspecialchars((string) $row['total_stock_out']) ?></td>
											<td style="text-align: center;"><span class="badge" style="<?= $status_style ?>"><?= htmlspecialchars($status_value) ?></span></td>
											<td style="text-align: center;">
												<a class="btn btn-sm btn-outline-primary" href="stock_transaction.php?item_id=<?= (int) $row['item_id'] ?>">View</a>
											</td>
										</tr>
									<?php else: ?>
										<?php $item_status_style = strtolower($row['status'] ?? '') === 'out of stock' ? 'background: #fee2e2; color: #dc2626;' : 'background: #dcfce7; color: #16a34a;'; ?>
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
											<td style="text-align: center;"><?= htmlspecialchars($row['quantity']) ?></td>
											<td style="text-align: center;"><span><?= "Php" . " " . number_format((float) $row['average_price'], 2) ?></span></td>
											<td style="text-align: center;"><span class="badge" style="<?= $item_status_style ?>"><?= htmlspecialchars($row['status'] ?? 'Out of Stock') ?></span></td>
											<td style="text-align: center;">
												<div class="dropdown">
													<a class="btn btn-link font-24 p-0 line-height-1 no-arrow dropdown-toggle" href="#" role="button" data-toggle="dropdown">
														<img src="src/images/menu-dots.png" width="25px" style="border: none">
													</a>
													<div class="dropdown-menu dropdown-menu-right dropdown-menu-icon-list">
														<a class="dropdown-item" href="#" onclick="editItem('<?= $row['id'] ?>', '<?= htmlspecialchars($row['brand_name']) ?>', '<?= htmlspecialchars($row['model']) ?>', '<?= htmlspecialchars($row['description']) ?>', '<?= $row['category_id'] ?>', '<?= $row['date'] ?>'); return false;"><i class="dw dw-edit2"></i> Edit</a>
														<a class="dropdown-item text-danger"
														   href="src/handlers/delete_item.php?id=<?= $row['id'] ?>"
														   data-macpro-confirm
														   data-macpro-confirm-title="Delete Product Item?"
														   data-macpro-confirm-message="This product item will be permanently deleted."
														   data-macpro-confirm-label="Delete Item"
														   data-macpro-confirm-variant="danger"><i class="dw dw-delete-3"></i> Delete</a>
													</div>
												</div>
											</td>
										</tr>
									<?php endif; ?>
								<?php } ?>
								<?php if ($total_records == 0): ?>
								<tr>
									<td colspan="<?= $active_tab === 'stock_records' ? '6' : '7' ?>" style="text-align: center;"><?= $active_tab === 'stock_records' ? 'No stock records found' : 'No product items found' ?></td>
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
										<a href="<?= htmlspecialchars(inventory_url(['page' => max(1, $current_page - 1)])) ?>" aria-controls="DataTables_Table_0" class="page-link" <?= ($current_page <= 1) ? 'style="pointer-events: none;"' : '' ?>>
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
											echo '<li class="paginate_button page-item"><a href="' . htmlspecialchars(inventory_url(['page' => 1])) . '" class="page-link">1</a></li>';
											if ($start_page > 2) {
												echo '<li class="paginate_button page-item disabled"><span class="page-link">...</span></li>';
											}
										}
										
										for ($i = $start_page; $i <= $end_page; $i++) {
											$active = ($i == $current_page) ? 'active' : '';
											echo '<li class="paginate_button page-item ' . $active . '"><a href="' . htmlspecialchars(inventory_url(['page' => $i])) . '" class="page-link">' . $i . '</a></li>';
										}
										
										if ($end_page < $total_pages) {
											if ($end_page < $total_pages - 1) {
												echo '<li class="paginate_button page-item disabled"><span class="page-link">...</span></li>';
											}
											echo '<li class="paginate_button page-item"><a href="' . htmlspecialchars(inventory_url(['page' => $total_pages])) . '" class="page-link">' . $total_pages . '</a></li>';
										}
									?>

									<!-- Next Button -->
									<li class="paginate_button page-item next <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
										<a href="<?= htmlspecialchars(inventory_url(['page' => min($total_pages, $current_page + 1)])) ?>" aria-controls="DataTables_Table_0" class="page-link" <?= ($current_page >= $total_pages) ? 'style="pointer-events: none;"' : '' ?>>
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

	function editItem(itemId, brand, model, description, categoryId, itemDate) {
		document.getElementById('editItemId').value = itemId;
		document.getElementById('editItemBrand').value = brand;
		document.getElementById('editItemModel').value = model;
		document.getElementById('editItemDescription').value = description;
		document.getElementById('editItemCategory').value = categoryId;
		toggleOtherCategory('editItemCategory', 'editOtherCategoryInput');
		document.getElementById('editItemDate').value = itemDate;
		document.getElementById('editItemToggle').checked = true;
	}
	</script>

<?php include 'footer.php'; ?>
</html>
