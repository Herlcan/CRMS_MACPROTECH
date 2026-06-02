<?php
	include 'header.php';
	include 'sidebar.php';
	require_once __DIR__ . '/src/handlers/inventory_transaction_schema.php';

	$item_id = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;
	$item = null;
	$transactions = [];
	$stock_out_records = [];
	$page_error = '';
	$allowed_stock_record_tabs = ['inventory_transaction', 'stock_out_history'];
	$active_stock_record_tab = $_GET['tab'] ?? 'inventory_transaction';

	if (!in_array($active_stock_record_tab, $allowed_stock_record_tabs, true)) {
		$active_stock_record_tab = 'inventory_transaction';
	}

	function stock_records_tab_url($item_id, $tab) {
		return 'stock_transaction.php?item_id=' . urlencode((string) $item_id) . '&tab=' . urlencode($tab);
	}

	function stock_records_history_url($item_id, array $overrides = []) {
		$params = [
			'item_id' => $item_id,
			'tab' => 'inventory_transaction',
			'transaction_search' => $_GET['transaction_search'] ?? '',
			'transaction_limit' => $_GET['transaction_limit'] ?? '10',
			'transaction_page' => $_GET['transaction_page'] ?? '1'
		];

		$params = array_merge($params, $overrides);

		foreach ($params as $key => $value) {
			if ($value === '' || $value === null) {
				unset($params[$key]);
			}
		}

		return 'stock_transaction.php?' . http_build_query($params);
	}

	try {
		ensure_inventory_transaction_table($conn);
		backfill_inventory_transactions_from_items($conn);
		sync_all_items_from_inventory_transactions($conn);
		drop_items_capital_column($conn);
	} catch (Exception $e) {
		$page_error = $e->getMessage();
	}

	if ($item_id > 0 && $page_error === '') {
		$item_query = mysqli_prepare(
			$conn,
			"SELECT i.*, c.category_name
			 FROM items i
			 LEFT JOIN item_category c ON i.category_id = c.id
			 WHERE i.id = ?
			 LIMIT 1"
		);

		if ($item_query) {
			mysqli_stmt_bind_param($item_query, "i", $item_id);
			mysqli_stmt_execute($item_query);
			$item_result = mysqli_stmt_get_result($item_query);
			$item = mysqli_fetch_assoc($item_result);
			mysqli_stmt_close($item_query);
		}

		if (!$item) {
			$page_error = 'Product item not found.';
		}
	} elseif ($page_error === '') {
		$page_error = 'Invalid product item.';
	}

	if ($item && $page_error === '') {
		$stock_out_query = mysqli_prepare(
			$conn,
			"SELECT sot.quantity, sot.stock_out_date, wo.code AS work_order_code, wo.id AS work_order_id, c.first_name, c.last_name
			 FROM stock_out_transaction sot
			 LEFT JOIN work_order wo ON sot.work_order_id = wo.id
			 LEFT JOIN client c ON wo.client_id = c.id
			 WHERE sot.item_id = ?
			 ORDER BY sot.stock_out_date DESC, sot.id DESC"
		);

		if ($stock_out_query) {
			mysqli_stmt_bind_param($stock_out_query, "i", $item_id);
			mysqli_stmt_execute($stock_out_query);
			$stock_out_result = mysqli_stmt_get_result($stock_out_query);

			while ($row = mysqli_fetch_assoc($stock_out_result)) {
				$stock_out_records[] = $row;
			}

			mysqli_stmt_close($stock_out_query);
		}
	}

	$product_name = $item ? trim($item['brand_name'] . ' ' . $item['model']) : '';
	$total_stock_in = 0;
	$total_stock_out = 0;
	$transaction_where = "item_id = " . (int) $item_id;
	$transaction_search = trim($_GET['transaction_search'] ?? '');
	$transaction_limit = 10;
	$transaction_current_page = 1;
	$transaction_total_records = 0;
	$transaction_total_pages = 1;
	$transaction_record_start = 0;
	$transaction_record_end = 0;
	$current_average_cost = 0.0;

	if (!empty($_GET['transaction_limit'])) {
		$transaction_limit_input = (int) $_GET['transaction_limit'];
		$transaction_limit = ($transaction_limit_input === -1) ? 999999 : max(1, $transaction_limit_input);
	}

	if (!empty($_GET['transaction_page'])) {
		$transaction_current_page = max(1, (int) $_GET['transaction_page']);
	}

	if ($item && $page_error === '') {
		$inventory_summary = calculate_inventory_weighted_average($conn, $item_id);
		$current_average_cost = (float) $inventory_summary['average_cost'];

		$summary_query = mysqli_prepare(
			$conn,
			"SELECT COALESCE(total_stock_in, 0) AS total_stock_in, COALESCE(total_stock_out, 0) AS total_stock_out
			 FROM inventory_transaction
			 WHERE item_id = ?"
		);

		if ($summary_query) {
			mysqli_stmt_bind_param($summary_query, "i", $item_id);
			mysqli_stmt_execute($summary_query);
			$summary_result = mysqli_stmt_get_result($summary_query);
			$summary_row = mysqli_fetch_assoc($summary_result);
			$total_stock_in = (int) ($summary_row['total_stock_in'] ?? 0);
			$total_stock_out = (int) ($summary_row['total_stock_out'] ?? 0);
			mysqli_stmt_close($summary_query);
		}

		if ($transaction_search !== '') {
			$escaped_transaction_search = mysqli_real_escape_string($conn, strtolower($transaction_search));
			$transaction_where .= " AND (
				LOWER(CAST(capital AS CHAR)) LIKE '%$escaped_transaction_search%'
				OR LOWER(CAST(stock_in AS CHAR)) LIKE '%$escaped_transaction_search%'
				OR LOWER(stock_in_date) LIKE '%$escaped_transaction_search%'
			)";
		}

		$transaction_count_result = mysqli_query(
			$conn,
			"SELECT COUNT(*) AS total FROM stock_in_transaction WHERE $transaction_where"
		);
		$transaction_count_row = mysqli_fetch_assoc($transaction_count_result);
		$transaction_total_records = (int) ($transaction_count_row['total'] ?? 0);

		$transaction_offset = ($transaction_current_page - 1) * $transaction_limit;
		$transaction_total_pages = max(1, (int) ceil($transaction_total_records / $transaction_limit));

		if ($transaction_current_page > $transaction_total_pages) {
			$transaction_current_page = $transaction_total_pages;
			$transaction_offset = ($transaction_current_page - 1) * $transaction_limit;
		}

		$transaction_offset = min($transaction_offset, $transaction_total_records);

		$transaction_result = mysqli_query(
			$conn,
			"SELECT *
			 FROM stock_in_transaction
			 WHERE $transaction_where
			 ORDER BY stock_in_date DESC, id DESC
			 LIMIT $transaction_limit OFFSET $transaction_offset"
		);

		if ($transaction_result) {
			while ($row = mysqli_fetch_assoc($transaction_result)) {
				$transactions[] = $row;
			}
		}

		$transaction_records_shown = count($transactions);
		$transaction_record_start = ($transaction_total_records > 0) ? $transaction_offset + 1 : 0;
		$transaction_record_end = min($transaction_offset + $transaction_records_shown, $transaction_total_records);
	}

	$current_stock = $item ? (int) $item['quantity'] : 0;
?>

	<div id="addInventoryStockModal" style="display: none; position: fixed; inset: 0; z-index: 1052; align-items: center; justify-content: center; padding: 16px;">
		<div onclick="closeAddInventoryStock()" style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45);"></div>
		<div class="css-modal-content" style="max-width: 640px; position: relative; z-index: 1;">
			<div class="css-modal-header">
				<h5 class="css-modal-title">Add Stock</h5>
				<button type="button" class="css-modal-close" onclick="closeAddInventoryStock()" style="background: transparent; border: 0;">&times;</button>
			</div>
			<div class="css-modal-body">
				<form method="POST" action="src/handlers/add_inventory_transaction.php">
					<input type="hidden" name="item_id" value="<?= htmlspecialchars((string) $item_id) ?>">
					<input type="hidden" id="addStockCurrentQuantity" value="<?= htmlspecialchars((string) $current_stock) ?>">
					<input type="hidden" id="addStockCurrentAverageCost" value="<?= htmlspecialchars((string) $current_average_cost) ?>">
					<div class="row">
						<div class="col-md-6 col-sm-12">
							<div class="form-group">
								<label class="form-label">Capital</label>
								<input type="number" class="form-control" name="capital" id="addStockCapital" step="0.01" min="0" required autocomplete="off">
							</div>
							<div class="form-group">
								<label class="form-label">Stock-In</label>
								<input type="number" class="form-control" name="stock_in" id="addStockIn" min="1" required autocomplete="off">
							</div>
						</div>
						<div class="col-md-6 col-sm-12">
							<div class="form-group">
								<label class="form-label">Markup Percentage (%)</label>
								<input type="number" class="form-control" name="markup_percentage" id="addStockMarkup" step="0.01" min="0" value="<?= htmlspecialchars((string) ($item['markup_percentage'] ?? 0)) ?>" required autocomplete="off">
							</div>
							<div class="form-group">
								<label class="form-label">Average Price</label>
								<input type="number" class="form-control" name="average_price" id="addStockAveragePrice" step="0.01" min="0" required autocomplete="off">
							</div>
						</div>
					</div>
					<div class="css-modal-footer">
						<button type="button" class="btn btn-secondary" onclick="closeAddInventoryStock()">Cancel</button>
						<button type="submit" name="add_inventory_transaction" class="btn btn-primary">Add Stock</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div id="editInventoryTransactionModal" style="display: none; position: fixed; inset: 0; z-index: 1052; align-items: center; justify-content: center; padding: 16px;">
		<div onclick="closeInventoryTransactionEdit()" style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45);"></div>
		<div class="css-modal-content" style="max-width: 640px; position: relative; z-index: 1;">
			<div class="css-modal-header">
				<h5 class="css-modal-title">Edit Stock-In Transaction</h5>
				<button type="button" class="css-modal-close" onclick="closeInventoryTransactionEdit()" style="background: transparent; border: 0;">&times;</button>
			</div>
			<div class="css-modal-body">
				<form method="POST" action="src/handlers/edit_inventory_transaction.php">
					<input type="hidden" name="id" id="editTransactionId" value="">
					<input type="hidden" name="item_id" value="<?= htmlspecialchars((string) $item_id) ?>">
					<div class="row">
						<div class="col-md-6 col-sm-12">
							<div class="form-group">
								<label class="form-label">Capital</label>
								<input type="number" class="form-control" name="capital" id="editTransactionCapital" step="0.01" required autocomplete="off">
							</div>
							<div class="form-group">
								<label class="form-label">Stock-In</label>
								<input type="number" class="form-control" name="stock_in" id="editTransactionStockIn" min="0" required autocomplete="off">
							</div>
						</div>
						<div class="col-md-6 col-sm-12">
							<div class="form-group">
								<label class="form-label">Stock-In Date</label>
								<input type="date" class="form-control" name="stock_in_date" id="editTransactionStockInDate" required autocomplete="off">
							</div>
						</div>
					</div>
					<div class="css-modal-footer">
						<button type="button" class="btn btn-secondary" onclick="closeInventoryTransactionEdit()">Cancel</button>
						<button type="submit" name="edit_inventory_transaction" class="btn btn-primary">Save Changes</button>
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
								<h4><i class="micon dw dw-table mtext"></i> Stock Records</h4>
							</div>
						</div>
						<div class="col-md-6 col-sm-12 text-right" style="margin-left: auto;">
							<a href="items.php?tab=stock_records" class="btn btn-secondary">Back to Stock Records</a>
						</div>
					</div>
				</div>

				<?php if ($page_error !== ''): ?>
					<div class="card-box mb-30">
						<div class="pd-20">
							<p style="margin: 0; color: #dc2626;"><?= htmlspecialchars($page_error) ?></p>
						</div>
					</div>
				<?php else: ?>
					<div class="card-box mb-30">
						<div class="row" style="padding: 20px; align-items: stretch;">
							<div class="col-md-6 col-sm-12" style="display: flex; flex-direction: column; justify-content: center;">
								<h4 class="text-blue h4" style="margin-bottom: 8px;"><?= htmlspecialchars($product_name) ?></h4>
								<p style="margin-bottom: 8px; color: #6c757d;">
									<?= htmlspecialchars($item['product_code']) ?> · <?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?>
								</p>
								<p style="margin-bottom: 0; color: #6c757d; max-width: 620px;">
									<?= htmlspecialchars($item['description'] ?? '') ?>
								</p>
							</div>
							<div class="col-md-6 col-sm-12" style="display: flex; flex-direction: column;">
								<div style="display: grid; grid-template-columns: repeat(4, minmax(120px, 1fr)); gap: 14px; height: 100%;">
								<div style="border: 1px solid #eef2f7; border-radius: 8px; padding: 16px 14px; background: #f8fafc; min-height: 106px; display: flex; flex-direction: column; justify-content: center;">
									<div style="font-size: 12px; color: #6c757d; font-weight: 600; margin-bottom: 8px;">Current Stock</div>
									<div style="font-size: 26px; font-weight: 700; color: #111827;"><?= htmlspecialchars((string) $current_stock) ?></div>
								</div>
								<div style="border: 1px solid #eef2f7; border-radius: 8px; padding: 16px 14px; background: #f8fafc; min-height: 106px; display: flex; flex-direction: column; justify-content: center;">
									<div style="font-size: 12px; color: #6c757d; font-weight: 600; margin-bottom: 8px;">Total Stock-In</div>
									<div style="font-size: 26px; font-weight: 700; color: #111827;"><?= htmlspecialchars((string) $total_stock_in) ?></div>
								</div>
								<div style="border: 1px solid #eef2f7; border-radius: 8px; padding: 16px 14px; background: #f8fafc; min-height: 106px; display: flex; flex-direction: column; justify-content: center;">
									<div style="font-size: 12px; color: #6c757d; font-weight: 600; margin-bottom: 8px;">Total Stock-Out</div>
									<div style="font-size: 26px; font-weight: 700; color: #111827;"><?= htmlspecialchars((string) $total_stock_out) ?></div>
								</div>
								<div style="border: 1px solid #eef2f7; border-radius: 8px; padding: 16px 14px; background: #f8fafc; min-height: 106px; display: flex; flex-direction: column; justify-content: center;">
									<div style="font-size: 12px; color: #6c757d; font-weight: 600; margin-bottom: 8px;">Avg Cost</div>
										<div style="font-size: 18px; font-weight: 700; color: #111827;"><?= "Php " . number_format($current_average_cost, 2) ?></div>
									</div>
								</div>
							</div>
							<div class="col-md-6 col-sm-12 text-right" style="margin-left: auto; display: flex; justify-content: flex-end; align-items: center;">
								<div style="display: flex; justify-content: flex-end; margin-top: 14px;">
									<button type="button" class="btn btn-primary" onclick="openAddInventoryStock()">Add Stock</button>
								</div>
							</div>
						</div>
					</div>

					<div class="card-box mb-30">
						<div class="tab-header" style="margin: 0; padding: 0 20px;">
							<a href="<?= htmlspecialchars(stock_records_tab_url($item_id, 'inventory_transaction')) ?>" style="padding: 12px 18px; font-weight: 600; color: <?= $active_stock_record_tab === 'inventory_transaction' ? '#0d6efd' : '#666' ?>; border-bottom: <?= $active_stock_record_tab === 'inventory_transaction' ? '2px solid #0d6efd' : '2px solid transparent' ?>;">Stock-In Transaction History</a>
							<a href="<?= htmlspecialchars(stock_records_tab_url($item_id, 'stock_out_history')) ?>" style="padding: 12px 18px; font-weight: 600; color: <?= $active_stock_record_tab === 'stock_out_history' ? '#0d6efd' : '#666' ?>; border-bottom: <?= $active_stock_record_tab === 'stock_out_history' ? '2px solid #0d6efd' : '2px solid transparent' ?>;">Stock-Out Work Order History</a>
						</div>
						<div class="pb-20">
							<?php if ($active_stock_record_tab === 'inventory_transaction'): ?>
								<div class="row mb-20">
									<div class="col-sm-12 col-md-6">
										<div class="dataTables_length">
											<label>Show 
												<form method="GET" style="display: inline;">
													<input type="hidden" name="item_id" value="<?= htmlspecialchars((string) $item_id) ?>">
													<input type="hidden" name="tab" value="inventory_transaction">
													<input type="hidden" name="transaction_search" value="<?= htmlspecialchars($transaction_search) ?>">
													<select name="transaction_limit" class="custom-select custom-select-sm form-control form-control-sm" onchange="this.form.submit();">
														<option value="10" <?= (isset($_GET['transaction_limit']) && $_GET['transaction_limit'] == '10') ? 'selected' : '' ?>>10</option>
														<option value="25" <?= (isset($_GET['transaction_limit']) && $_GET['transaction_limit'] == '25') ? 'selected' : '' ?>>25</option>
														<option value="50" <?= (isset($_GET['transaction_limit']) && $_GET['transaction_limit'] == '50') ? 'selected' : '' ?>>50</option>
														<option value="-1" <?= (isset($_GET['transaction_limit']) && $_GET['transaction_limit'] == '-1') ? 'selected' : '' ?>>All</option>
													</select>
												</form> entries
											</label>
										</div>
									</div>
									<div class="col-sm-12 col-md-6" style="margin-left: auto;">
										<div class="dataTables_filter">
											<label>Search:
												<form method="GET">
													<input type="hidden" name="item_id" value="<?= htmlspecialchars((string) $item_id) ?>">
													<input type="hidden" name="tab" value="inventory_transaction">
													<input type="hidden" name="transaction_limit" value="<?= isset($_GET['transaction_limit']) ? htmlspecialchars($_GET['transaction_limit']) : '10' ?>">
													<input type="search" name="transaction_search" class="form-control form-control-sm" placeholder="Search transactions..." value="<?= htmlspecialchars($transaction_search) ?>" autocomplete="off">
												</form>
											</label>
										</div>
									</div>
								</div>
								<table class="data-table table responsive">
									<thead>
										<tr>
											<th style="text-align: center;">Capital</th>
											<th style="text-align: center;">Stock-In</th>
											<th style="text-align: center;">Stock-In Date</th>
											<th class="datatable-nosort" style="text-align: center;">Action</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($transactions as $transaction): ?>
											<?php
												$edit_payload = [
													'id' => (string) $transaction['id'],
													'capital' => (string) $transaction['capital'],
													'stock_in' => (string) $transaction['stock_in'],
													'stock_in_date' => $transaction['stock_in_date']
												];
											?>
											<tr>
												<td style="text-align: center;"><?= "Php " . number_format((float) $transaction['capital'], 2) ?></td>
												<td style="text-align: center;"><?= htmlspecialchars($transaction['stock_in']) ?></td>
												<td style="text-align: center;"><?= htmlspecialchars($transaction['stock_in_date']) ?></td>
												<td style="text-align: center;">
													<div class="dropdown">
														<a class="btn btn-link font-24 p-0 line-height-1 no-arrow dropdown-toggle" href="#" role="button" data-toggle="dropdown">
															<img src="src/images/menu-dots.png" width="25px" style="border: none">
														</a>
														<div class="dropdown-menu dropdown-menu-right dropdown-menu-icon-list">
															<a class="dropdown-item" href="#" onclick='editInventoryTransaction(<?= htmlspecialchars(json_encode($edit_payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES) ?>); return false;'><i class="dw dw-edit2"></i> Edit</a>
															<a class="dropdown-item text-danger"
															   href="src/handlers/delete_inventory_transaction.php?id=<?= (int) $transaction['id'] ?>&item_id=<?= (int) $item_id ?>"
															   data-macpro-confirm
															   data-macpro-confirm-title="Delete Inventory Transaction?"
															   data-macpro-confirm-message="This inventory transaction record will be permanently deleted."
															   data-macpro-confirm-label="Delete Transaction"
															   data-macpro-confirm-variant="danger"><i class="dw dw-delete-3"></i> Delete</a>
														</div>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
										<?php if (empty($transactions)): ?>
											<tr>
													<td colspan="4" style="text-align: center;">No stock-in transactions found</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
								<div class="row">
									<div class="col-sm-12 col-md-5">
										<div class="dataTables_info" role="status" aria-live="polite">
											<?php
												echo ($transaction_total_records > 0)
													? $transaction_record_start . "-" . $transaction_record_end . " of " . $transaction_total_records . " entries"
													: "No entries";
											?>
										</div>
									</div>
									<div class="col-sm-12 col-md-7" style="margin-left: auto;">
										<div class="dataTables_paginate paging_simple_numbers">
											<ul class="pagination justify-content-end">
												<li class="paginate_button page-item previous <?= ($transaction_current_page <= 1) ? 'disabled' : '' ?>">
													<a href="<?= htmlspecialchars(stock_records_history_url($item_id, ['transaction_page' => max(1, $transaction_current_page - 1)])) ?>" class="page-link" <?= ($transaction_current_page <= 1) ? 'style="pointer-events: none;"' : '' ?>>
														<i class="ion-chevron-left">
															<img src="src/images/angle-double-small-left.png" width="20px" style="border: none">
														</i>
													</a>
												</li>

												<?php
													$transaction_start_page = max(1, $transaction_current_page - 2);
													$transaction_end_page = min($transaction_total_pages, $transaction_current_page + 2);

													if ($transaction_start_page > 1) {
														echo '<li class="paginate_button page-item"><a href="' . htmlspecialchars(stock_records_history_url($item_id, ['transaction_page' => 1])) . '" class="page-link">1</a></li>';
														if ($transaction_start_page > 2) {
															echo '<li class="paginate_button page-item disabled"><span class="page-link">...</span></li>';
														}
													}

													for ($i = $transaction_start_page; $i <= $transaction_end_page; $i++) {
														$active = ($i === $transaction_current_page) ? 'active' : '';
														echo '<li class="paginate_button page-item ' . $active . '"><a href="' . htmlspecialchars(stock_records_history_url($item_id, ['transaction_page' => $i])) . '" class="page-link">' . $i . '</a></li>';
													}

													if ($transaction_end_page < $transaction_total_pages) {
														if ($transaction_end_page < $transaction_total_pages - 1) {
															echo '<li class="paginate_button page-item disabled"><span class="page-link">...</span></li>';
														}
														echo '<li class="paginate_button page-item"><a href="' . htmlspecialchars(stock_records_history_url($item_id, ['transaction_page' => $transaction_total_pages])) . '" class="page-link">' . $transaction_total_pages . '</a></li>';
													}
												?>

												<li class="paginate_button page-item next <?= ($transaction_current_page >= $transaction_total_pages) ? 'disabled' : '' ?>">
													<a href="<?= htmlspecialchars(stock_records_history_url($item_id, ['transaction_page' => min($transaction_total_pages, $transaction_current_page + 1)])) ?>" class="page-link" <?= ($transaction_current_page >= $transaction_total_pages) ? 'style="pointer-events: none;"' : '' ?>>
														<i class="ion-chevron-right">
															<img src="src/images/angle-double-small-right.png" width="20px" style="border: none">
														</i>
													</a>
												</li>
											</ul>
										</div>
									</div>
								</div>
							<?php else: ?>
								<table class="data-table table responsive">
									<thead>
										<tr>
											<th style="text-align: center;">Work Order</th>
											<th style="text-align: center;">Customer</th>
											<th style="text-align: center;">Quantity</th>
											<th style="text-align: center;">Date</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($stock_out_records as $record): ?>
											<?php $customer_name = trim(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? '')); ?>
											<tr>
												<td style="text-align: center;"><?= htmlspecialchars($record['work_order_code'] ?? 'N/A') ?></td>
												<td style="text-align: center;"><?= htmlspecialchars($customer_name !== '' ? $customer_name : 'N/A') ?></td>
												<td style="text-align: center;"><?= htmlspecialchars($record['quantity']) ?></td>
												<td style="text-align: center;"><?= htmlspecialchars($record['stock_out_date']) ?></td>
											</tr>
										<?php endforeach; ?>
										<?php if (empty($stock_out_records)): ?>
											<tr>
												<td colspan="4" style="text-align: center;">No stock-out work orders found</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<script>
	function calculateAddStockAveragePrice() {
		const currentQuantity = parseFloat(document.getElementById('addStockCurrentQuantity').value) || 0;
		const currentAverageCost = parseFloat(document.getElementById('addStockCurrentAverageCost').value) || 0;
		const capital = parseFloat(document.getElementById('addStockCapital').value);
		const stockIn = parseFloat(document.getElementById('addStockIn').value);
		const markup = parseFloat(document.getElementById('addStockMarkup').value);
		const averagePriceInput = document.getElementById('addStockAveragePrice');

		if (!isNaN(capital) && !isNaN(stockIn) && stockIn > 0 && !isNaN(markup)) {
			const currentValue = currentQuantity * currentAverageCost;
			const newQuantity = currentQuantity + stockIn;
			const newAverageCost = newQuantity > 0 ? (currentValue + capital) / newQuantity : 0;
			const averagePrice = newAverageCost * (1 + markup / 100);
			averagePriceInput.value = averagePrice.toFixed(2);
		}
	}

	function openAddInventoryStock() {
		document.getElementById('addInventoryStockModal').style.display = 'flex';
		calculateAddStockAveragePrice();
	}

	function closeAddInventoryStock() {
		document.getElementById('addInventoryStockModal').style.display = 'none';
	}

	function editInventoryTransaction(transaction) {
		document.getElementById('editTransactionId').value = transaction.id || '';
		document.getElementById('editTransactionCapital').value = transaction.capital || '0';
		document.getElementById('editTransactionStockIn').value = transaction.stock_in || '0';
		document.getElementById('editTransactionStockInDate').value = transaction.stock_in_date || '';
		document.getElementById('editInventoryTransactionModal').style.display = 'flex';
	}

	function closeInventoryTransactionEdit() {
		document.getElementById('editInventoryTransactionModal').style.display = 'none';
	}

	['addStockCapital', 'addStockIn', 'addStockMarkup'].forEach(function (fieldId) {
		const field = document.getElementById(fieldId);
		if (field) {
			field.addEventListener('input', calculateAddStockAveragePrice);
		}
	});
	</script>

<?php include 'footer.php'; ?>
</html>
