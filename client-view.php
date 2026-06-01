<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1'); 


	include 'header.php';
	include 'sidebar.php'; 
	include 'src/db/connection.php';

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
	
	$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
	$row = null;

	if ($client_id > 0) {
		$query = "SELECT * FROM client WHERE id = $client_id";
		$result = mysqli_query($conn, $query);
		$row = mysqli_fetch_assoc($result);
	}

	if (!$row) {
		echo '<div class="main-container"><div class="pd-ltr-20 xs-pd-20-10"><div class="min-height-200px">';
		echo '<h4>Client not found.</h4>';
		echo '<p>Please go back and select a client before searching work orders.</p>';
		echo '</div></div></div>';
		exit;
	}

	$unitTypes = [];
	$unitTypeQuery = "SELECT id, unit_type FROM unit_type ORDER BY unit_type";
	$unitTypeResult = mysqli_query($conn, $unitTypeQuery);
	if ($unitTypeResult) {
		while ($unitTypeRow = mysqli_fetch_assoc($unitTypeResult)) {
			$unitTypes[] = $unitTypeRow;
		}
	}

	$itemCategories = [];
	$itemCategoryQuery = "SELECT id, category_name FROM item_category ORDER BY category_name ASC";
	$itemCategoryResult = mysqli_query($conn, $itemCategoryQuery);
	if ($itemCategoryResult) {
		while ($itemCategoryRow = mysqli_fetch_assoc($itemCategoryResult)) {
			$itemCategories[] = $itemCategoryRow;
		}
	}

?>
	<!-- Hidden checkbox for add transaction modal toggle -->
	<input type="checkbox" id="addWorkOrderToggle" class="add-client-toggle" onchange="if (!this.checked) resetFormToAdd();">

	<!-- Add transaction Modal Overlay -->
	<label for="addWorkOrderToggle" class="css-modal-overlay add-client-overlay"></label>

	<!-- ADD WORK ORDER MODAL -->
	<div class="add-client-modal-container">
		<div class="css-modal-content" style="max-width: 800px;">
			<!-- Modal Header -->
			<div class="css-modal-header">
				<h5 class="css-modal-title" id="modalTitle">Add New Work Order</h5>
				<label for="addWorkOrderToggle" class="css-modal-close">&times;</label>
			</div>

			<!-- Modal Body -->
			<div class="css-modal-body">
				<!-- Form -->
				<form method="POST" action="src/handlers/add_work_order.php" id="workOrderForm">
					<!-- STEP 1: WORK ORDER DETAILS -->
					<div id="step1Form" class="form-step" style="display: block;">
						<div class="row">
							<div style="width: 45%;">
								<input type="hidden" name="client_id" value="<?=htmlspecialchars($client_id)?>">
								<input type="hidden" name="status" value="Pending">
								<div class="form-group">
									<label class="form-label">Unit Type</label>
									<select class="form-control" name="unit_type" id="unitTypeSelect" required autocomplete="off" onchange="toggleOtherUnitType()">
										<option value="">-- Select Unit Type --</option>
										<?php foreach ($unitTypes as $type): ?>
											<option value="<?= htmlspecialchars($type['unit_type']) ?>"><?= htmlspecialchars($type['unit_type']) ?></option>
										<?php endforeach; ?>
										<option value="__other__">Other</option>
									</select>
									<input type="text" class="form-control" id="otherUnitTypeInput" name="other_unit_type" placeholder="Enter unit type" autocomplete="off" style="display: none; margin-top: 10px;" disabled>
								</div>
								<div class="form-group">
									<label class="form-label">Brand</label>
									<input type="text" class="form-control" placeholder="Input Brand Name" name="brand" required autocomplete="off">
								</div>

								<div class="form-group">
									<label class="form-label">Model</label>
									<input type="text" class="form-control" placeholder="Input Model" name="model" required autocomplete="off">
								</div>
								
								<div class="form-group">
									<label class="form-label">Specifications/Accessories</label>
									<textarea class="form-control" style="height: 151px;" placeholder="Input Specifications/Accessories" name="specs_acce" required autocomplete="off"></textarea>
								</div>
							</div>
							
							<div style="width: 55%; padding-left: 5%;">
								<div class="form-group">
									<label class="form-label">Date</label>
									<input type="date" class="form-control" name="request_date" required autocomplete="off">
								</div>
								
								<div class="form-group">
									<label class="form-label">Problems/Findings</label>
									<textarea class="form-control" style="height: 150px;" placeholder="Input Problems/Findings" name="prob_find" required autocomplete="off"></textarea>
								</div>

								<div class="form-group">
									<label class="form-label">Diagnostic Fee</label>
									<input type="number" class="form-control" placeholder="Diagnostic Fee" name="diagnostic_fee" required autocomplete="off">
								</div>

								<div class="form-group">
									<label class="form-label">Work Order Cost</label>
									<input type="number" class="form-control" placeholder="Work Order Cost" name="work_order_cost" required autocomplete="off">
								</div>
							</div>
						</div>
					</div>

<!-- STEP 2: PARTS -->
				<div id="step2Form" class="form-step" style="display: none;">
					<div class="row">
						<div style="width: 65%; max-height: 55vh; overflow-y: auto; padding-right: 10px;">
							<div style="margin-bottom: 30px;">
								<h6 style="margin-bottom: 15px; font-weight: 600; border-bottom: 2px solid #007bff; padding-bottom: 10px;">Purchase Parts</h6>
								<div id="purchasedPartsContainer">
									<!-- First purchase part entry (default) -->
									<div class="purchased-part-entry" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background-color: #f9f9f9;">
										<div class="row">
											<div style="width: 70%;">
												<div class="form-group">
													<label class="form-label">Part Name</label>
													<div style="position: relative;">
														<input type="text" class="form-control part-search" placeholder="Search for a part..." autocomplete="off" data-item-id="">
														<input type="hidden" class="part-item-id" name="purchased_part_item_id[]" value="">
														<div class="part-dropdown" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; max-height: 200px; overflow-y: auto; z-index: 100; display: none;">
														</div>
													</div>
												</div>
											</div>
											<div style="width: 25%;">
												<div class="form-group">
													<label class="form-label">Quantity</label>
													<input type="number" class="form-control" placeholder="Qty" name="purchased_part_quantity[]" min="1" value="1" autocomplete="off">
												</div>
											</div>
											<div style="width: 5%; align-self: flex-end; margin-bottom: 12px;">
												<button type="button" class="btn btn-sm btn-danger" onclick="removePurchasedPartEntry(this)">×</button>
											</div>
										</div>
									</div>
								</div>
								<button type="button" class="btn btn-secondary" onclick="addPurchasedPartEntry()" style="margin-top: 10px;">+ Add Purchased Part</button>
							</div>

							<!-- ORDERED PARTS SECTION -->
							<div style="margin-bottom: 30px;">
								<h6 style="margin-bottom: 15px; font-weight: 600; border-bottom: 2px solid #fd7e14; padding-bottom: 10px;">Ordered Parts</h6>
								<div id="orderedPartsContainer">
									<div class="ordered-part-entry" style="margin-bottom: 20px; padding: 15px; border: 1px solid #fd7e14; border-radius: 4px; background-color: #fff7ed;">
										<div class="row">
											<div style="width: 47%;">
												<div class="form-group">
													<label class="form-label">Part Name</label>
													<input type="text" class="form-control" placeholder="Enter part name" name="ordered_part_name[]" autocomplete="off">
												</div>
											</div>
											<div style="width: 48%; padding-left: 3%;">
												<div class="form-group">
													<label class="form-label">Part Category</label>
													<select class="form-control" name="ordered_part_category[]" autocomplete="off">
														<option value="">-- Select Category --</option>
														<?php foreach ($itemCategories as $category): ?>
															<option value="<?= htmlspecialchars($category['category_name']) ?>"><?= htmlspecialchars($category['category_name']) ?></option>
														<?php endforeach; ?>
													</select>
												</div>
											</div>
											<div style="width: 5%; align-self: flex-end; margin-bottom: 12px;">
												<button type="button" class="btn btn-sm btn-danger" onclick="removeOrderedPartEntry(this)">×</button>
											</div>
										</div>
										<div class="row">
											<div style="width: 47%;">
												<div class="form-group">
													<label class="form-label">Brand</label>
													<input type="text" class="form-control" placeholder="Enter brand" name="ordered_part_brand[]" autocomplete="off">
												</div>
											</div>
											<div style="width: 23%; padding-left: 3%;">
												<div class="form-group">
													<label class="form-label">Quantity</label>
													<input type="number" class="form-control" placeholder="Qty" name="ordered_part_quantity[]" min="1" value="1" autocomplete="off">
												</div>
											</div>
											<div style="width: 25%; padding-left: 3%;">
												<div class="form-group">
													<label class="form-label">Price</label>
													<input type="number" class="form-control" placeholder="Price" name="ordered_part_price[]" min="0" step="0.01" autocomplete="off">
												</div>
											</div>
										</div>
										<div class="form-group" style="margin-bottom: 0;">
											<label class="form-label">Description</label>
											<textarea class="form-control" placeholder="Enter description" name="ordered_part_description[]" style="height: 50px;" autocomplete="off"></textarea>
										</div>
									</div>
								</div>
								<button type="button" class="btn btn-secondary" onclick="addOrderedPartEntry()" style="margin-top: 10px;">+ Add Ordered Part</button>
							</div>

							<!-- CLIENT PROVIDED PARTS SECTION -->
							<div>
								<h6 style="margin-bottom: 15px; font-weight: 600; border-bottom: 2px solid #28a745; padding-bottom: 10px;">Client Provided Parts</h6>
								<div id="clientProvidedPartsContainer">
									<!-- First client provided part entry (default) -->
									<div class="client-part-entry" style="margin-bottom: 20px; padding: 15px; border: 1px solid #28a745; border-radius: 4px; background-color: #f1f9f4;">
										<div class="row">
											<div style="width: 45%;">
												<div class="form-group">
													<label class="form-label">Product Name</label>
													<input type="text" class="form-control" placeholder="Enter product name" name="client_part_product_name[]" autocomplete="off">
												</div>
											</div>
											<div style="width: 50%; padding-left: 3%;">
												<div class="form-group">
													<label class="form-label">Description</label>
													<textarea class="form-control" placeholder="Enter description" name="client_part_description[]" style="height: 40px;" autocomplete="off"></textarea>
												</div>
											</div>
											<div style="width: 5%; align-self: flex-end; margin-bottom: 12px;">
												<button type="button" class="btn btn-sm btn-danger" onclick="removeClientPartEntry(this)">×</button>
											</div>
										</div>
										<div class="row">
											<div style="width: 45%;">
												<div class="form-group">
													<label class="form-label">Quantity</label>
													<input type="number" class="form-control" placeholder="Qty" name="client_part_quantity[]" min="1" value="1" autocomplete="off">
												</div>
											</div>
										</div>
									</div>
								</div>
								<button type="button" class="btn btn-secondary" onclick="addClientProvidedPartEntry()" style="margin-top: 10px;">+ Add Client Provided Part</button>
							</div>
						</div>
						<div style="width: 35%; padding-left: 5%;">
							<h5 style="margin-bottom: 20px;">Additional Information</h5>
							
							<div class="form-group">
								<label class="form-label">Assign Technician</label>
								<select class="form-control" name="technician_id" autocomplete="off">
									<option value="">-- Select Technician --</option>
									<?php
										$tech_query = "SELECT id, first_name, last_name FROM users WHERE role IN ('Technician','Administrator') ORDER BY last_name, first_name";
										$tech_result = mysqli_query($conn, $tech_query);
										while ($tech = mysqli_fetch_assoc($tech_result)) {
											echo '<option value="' . htmlspecialchars($tech['id']) . '">' . htmlspecialchars($tech['last_name'] . ', ' . $tech['first_name']) . '</option>';
										}
									?>
								</select>
							</div>

							<div class="form-group">
								<label class="form-label">Notes</label>
								<textarea class="form-control" placeholder="Add any additional notes..." name="notes" autocomplete="off" style="height: 150px;"></textarea>
							</div>
						</div>
					</div>
				</div>
					
					<!-- Modal Footer -->
					<div class="css-modal-footer">
						<label for="addWorkOrderToggle" class="btn btn-secondary" onclick="resetFormToAdd()">Cancel</label>
						<button type="button" id="backBtn" class="btn btn-secondary" onclick="goToStep(1)" style="display: none;">Back</button>
						<button type="button" id="nextBtn" class="btn btn-primary" onclick="goToStep(2)">Next</button>
						<button type="submit" id="submitBtn" name="add_work_order" class="btn btn-primary" style="display: none;">Add Work Order</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- JavaScript for multi-step form -->
	<script>
		console.log('Modal script loaded');
		const orderedPartCategories = <?= json_encode(array_column($itemCategories, 'category_name'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

		function renderOrderedPartCategoryOptions(selectedCategory = '') {
			return '<option value="">-- Select Category --</option>' + orderedPartCategories.map(category => {
				const selected = String(category) === String(selectedCategory || '') ? ' selected' : '';
				return `<option value="${escapeHtml(category)}"${selected}>${escapeHtml(category)}</option>`;
			}).join('');
		}

		function orderedPartEntryTemplate(part = {}) {
			const partName = part.part_name || '';
			const brand = part.brand || '';
			const category = part.category || '';
			const description = part.description || '';
			const quantity = part.quantity || 1;
			const price = part.price || '';

			return `
				<div class="row">
					<div style="width: 47%;">
						<div class="form-group">
							<label class="form-label">Part Name</label>
							<input type="text" class="form-control" placeholder="Enter part name" name="ordered_part_name[]" value="${escapeHtml(partName)}" autocomplete="off">
						</div>
					</div>
					<div style="width: 48%; padding-left: 3%;">
						<div class="form-group">
							<label class="form-label">Part Category</label>
							<select class="form-control" name="ordered_part_category[]" autocomplete="off">
								${renderOrderedPartCategoryOptions(category)}
							</select>
						</div>
					</div>
					<div style="width: 5%; align-self: flex-end; margin-bottom: 12px;">
						<button type="button" class="btn btn-sm btn-danger" onclick="removeOrderedPartEntry(this)">×</button>
					</div>
				</div>
				<div class="row">
					<div style="width: 47%;">
						<div class="form-group">
							<label class="form-label">Brand</label>
							<input type="text" class="form-control" placeholder="Enter brand" name="ordered_part_brand[]" value="${escapeHtml(brand)}" autocomplete="off">
						</div>
					</div>
					<div style="width: 23%; padding-left: 3%;">
						<div class="form-group">
							<label class="form-label">Quantity</label>
							<input type="number" class="form-control" placeholder="Qty" name="ordered_part_quantity[]" min="1" value="${escapeHtml(quantity)}" autocomplete="off">
						</div>
					</div>
					<div style="width: 25%; padding-left: 3%;">
						<div class="form-group">
							<label class="form-label">Price</label>
							<input type="number" class="form-control" placeholder="Price" name="ordered_part_price[]" min="0" step="0.01" value="${escapeHtml(price)}" autocomplete="off">
						</div>
					</div>
				</div>
				<div class="form-group" style="margin-bottom: 0;">
					<label class="form-label">Description</label>
					<textarea class="form-control" placeholder="Enter description" name="ordered_part_description[]" style="height: 50px;" autocomplete="off">${escapeHtml(description)}</textarea>
				</div>
			`;
		}
		
		document.addEventListener('DOMContentLoaded', function() {
			// Handle part search autocomplete for purchased parts
			document.addEventListener('input', function(e) {
				if (e.target.classList.contains('part-search')) {
					const searchInput = e.target;
					const searchTerm = searchInput.value.trim();
					const dropdown = searchInput.closest('div').querySelector('.part-dropdown');

					if (searchTerm.length === 0) {
						dropdown.style.display = 'none';
						return;
					}

					// Search for items
					fetch(`src/handlers/search_items.php?q=${encodeURIComponent(searchTerm)}`)
						.then(response => {
							if (!response.ok) {
								throw new Error(`HTTP error! status: ${response.status}`);
							}
							return response.json();
						})
						.then(data => {
							dropdown.innerHTML = '';
							
							if (data.length === 0) {
								dropdown.innerHTML = '<div style="padding: 10px; color: #999;">No items found</div>';
								dropdown.style.display = 'block';
								return;
							}

							data.forEach(item => {
								const option = document.createElement('div');
								option.style.cssText = 'padding: 10px; cursor: pointer; border-bottom: 1px solid #eee;';
								option.innerHTML = `${item.product_name}`;
								
								option.addEventListener('mouseenter', function() {
									this.style.backgroundColor = '#f5f5f5';
								});
								
								option.addEventListener('mouseleave', function() {
									this.style.backgroundColor = 'white';
								});

								option.addEventListener('click', function() {
									selectItem(searchInput, item);
								});

								dropdown.appendChild(option);
							});

							dropdown.style.display = 'block';
						})
						.catch(error => {
							console.error('Error searching items:', error);
							dropdown.innerHTML = '<div style="padding: 10px; color: #f00;">Error loading items</div>';
							dropdown.style.display = 'block';
						});
				}
			});
		});
		
		function toggleOtherUnitType() {
			const unitTypeSelect = document.getElementById('unitTypeSelect');
			const otherUnitTypeInput = document.getElementById('otherUnitTypeInput');
			const isOther = unitTypeSelect.value === '__other__';

			otherUnitTypeInput.style.display = isOther ? 'block' : 'none';
			otherUnitTypeInput.disabled = !isOther;
			otherUnitTypeInput.required = isOther;

			if (!isOther) {
				otherUnitTypeInput.value = '';
				otherUnitTypeInput.style.borderColor = '';
			}
		}

		function setUnitTypeValue(unitType) {
			const unitTypeSelect = document.getElementById('unitTypeSelect');
			const otherUnitTypeInput = document.getElementById('otherUnitTypeInput');
			const hasOption = Array.from(unitTypeSelect.options).some(option => option.value === unitType);

			if (unitType && !hasOption) {
				unitTypeSelect.value = '__other__';
				toggleOtherUnitType();
				otherUnitTypeInput.value = unitType;
				return;
			}

			unitTypeSelect.value = unitType || '';
			toggleOtherUnitType();
		}

		function goToStep(step) {
			console.log('goToStep called with step:', step);
			const step1 = document.getElementById('step1Form');
			const step2 = document.getElementById('step2Form');
			const nextBtn = document.getElementById('nextBtn');
			const backBtn = document.getElementById('backBtn');
			const submitBtn = document.getElementById('submitBtn');
			const modalTitle = document.getElementById('modalTitle');

			console.log('Elements found:', { step1, step2, nextBtn, backBtn, submitBtn, modalTitle });

			if (step === 1) {
				// Show step 1
				step1.style.display = 'block';
				step2.style.display = 'none';
				nextBtn.style.display = 'inline-block';
				backBtn.style.display = 'none';
				submitBtn.style.display = 'none';
				modalTitle.textContent = 'Add New Work Order';
			} else if (step === 2) {
				// Validate step 1 before moving to step 2
				const form = document.getElementById('workOrderForm');
				const inputs = step1.querySelectorAll('input[required]:not(:disabled), select[required]:not(:disabled), textarea[required]:not(:disabled)');
				let isValid = true;
				let emptyFields = [];

				inputs.forEach(input => {
					const value = input.value.trim();
					console.log('Checking field:', input.name, 'value:', value, 'type:', input.type);
					if (!value) {
						isValid = false;
						emptyFields.push(input.name);
						input.style.borderColor = 'red';
					} else {
						input.style.borderColor = '';
					}
				});

				console.log('Validation result:', isValid, 'empty fields:', emptyFields);

				if (!isValid) {
					MacproDialog.error({
						title: 'Missing Required Fields',
						message: 'Please fill in all required fields before proceeding. Empty fields: ' + emptyFields.join(', ')
					});
					return;
				}

				// Show step 2
				step1.style.display = 'none';
				step2.style.display = 'block';
				nextBtn.style.display = 'none';
				backBtn.style.display = 'inline-block';
				submitBtn.style.display = 'inline-block';
				modalTitle.textContent = 'Add Work Order - Parts';
			}
		}

		function selectItem(inputElement, item) {
			const displayText = item.product_name;
			inputElement.value = displayText;
			inputElement.dataset.itemId = item.id;
			inputElement.closest('div').querySelector('.part-item-id').value = item.id;
			
			inputElement.closest('div').querySelector('.part-dropdown').style.display = 'none';
		}

		// Close dropdowns when clicking outside
		document.addEventListener('click', function(e) {
			if (!e.target.classList.contains('part-search')) {
				document.querySelectorAll('.part-dropdown').forEach(dropdown => {
					dropdown.style.display = 'none';
				});
			}
		});

		// PURCHASED PARTS FUNCTIONS
		function addPurchasedPartEntry() {
			const purchasedPartsContainer = document.getElementById('purchasedPartsContainer');
			const partEntry = document.createElement('div');
			partEntry.className = 'purchased-part-entry';
			partEntry.style.marginBottom = '20px';
			partEntry.style.padding = '15px';
			partEntry.style.border = '1px solid #ddd';
			partEntry.style.borderRadius = '4px';
			partEntry.style.backgroundColor = '#f9f9f9';

			partEntry.innerHTML = `
				<div class="row">
					<div style="width: 70%;">
						<div class="form-group">
							<label class="form-label">Part Name</label>
							<div style="position: relative;">
								<input type="text" class="form-control part-search" placeholder="Search for a part..." autocomplete="off" data-item-id="">
								<input type="hidden" class="part-item-id" name="purchased_part_item_id[]" value="">
								<div class="part-dropdown" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; max-height: 200px; overflow-y: auto; z-index: 100; display: none;">
								</div>
							</div>
						</div>
					</div>
					<div style="width: 25%;">
						<div class="form-group">
							<label class="form-label">Quantity</label>
							<input type="number" class="form-control" placeholder="Qty" name="purchased_part_quantity[]" min="1" value="1" autocomplete="off">
						</div>
					</div>
					<div style="width: 5%; align-self: flex-end; margin-bottom: 12px;">
						<button type="button" class="btn btn-sm btn-danger" onclick="removePurchasedPartEntry(this)">×</button>
					</div>
				</div>
			`;

			purchasedPartsContainer.appendChild(partEntry);
		}

		function removePurchasedPartEntry(button) {
			const purchasedPartsContainer = document.getElementById('purchasedPartsContainer');
			if (purchasedPartsContainer.children.length > 1) {
				button.closest('.purchased-part-entry').remove();
			} else {
				MacproDialog.info({
					title: 'Purchased Part Required',
					message: 'You must have at least one purchased part entry.'
				});
			}
		}

		// ORDERED PARTS FUNCTIONS
		function addOrderedPartEntry(part = {}) {
			const orderedPartsContainer = document.getElementById('orderedPartsContainer');
			const partEntry = document.createElement('div');
			partEntry.className = 'ordered-part-entry';
			partEntry.style.marginBottom = '20px';
			partEntry.style.padding = '15px';
			partEntry.style.border = '1px solid #fd7e14';
			partEntry.style.borderRadius = '4px';
			partEntry.style.backgroundColor = '#fff7ed';
			partEntry.innerHTML = orderedPartEntryTemplate(part);

			orderedPartsContainer.appendChild(partEntry);
		}

		function removeOrderedPartEntry(button) {
			const orderedPartsContainer = document.getElementById('orderedPartsContainer');
			if (orderedPartsContainer.children.length > 1) {
				button.closest('.ordered-part-entry').remove();
			} else {
				MacproDialog.info({
					title: 'Ordered Part Entry',
					message: 'Leave this entry blank if no ordered part is needed.'
				});
			}
		}

		// CLIENT PROVIDED PARTS FUNCTIONS
		function addClientProvidedPartEntry() {
			const clientProvidedPartsContainer = document.getElementById('clientProvidedPartsContainer');
			const partEntry = document.createElement('div');
			partEntry.className = 'client-part-entry';
			partEntry.style.marginBottom = '20px';
			partEntry.style.padding = '15px';
			partEntry.style.border = '1px solid #28a745';
			partEntry.style.borderRadius = '4px';
			partEntry.style.backgroundColor = '#f1f9f4';

			partEntry.innerHTML = `
				<div class="row">
					<div style="width: 45%;">
						<div class="form-group">
							<label class="form-label">Product Name</label>
							<input type="text" class="form-control" placeholder="Enter product name" name="client_part_product_name[]" autocomplete="off">
						</div>
					</div>
					<div style="width: 50%; padding-left: 3%;">
						<div class="form-group">
							<label class="form-label">Description</label>
							<textarea class="form-control" placeholder="Enter description" name="client_part_description[]" style="height: 40px;" autocomplete="off"></textarea>
						</div>
					</div>
					<div style="width: 5%; align-self: flex-end; margin-bottom: 12px;">
						<button type="button" class="btn btn-sm btn-danger" onclick="removeClientPartEntry(this)">×</button>
					</div>
				</div>
				<div class="row">
					<div style="width: 45%;">
						<div class="form-group">
							<label class="form-label">Quantity</label>
							<input type="number" class="form-control" placeholder="Qty" name="client_part_quantity[]" min="1" value="1" autocomplete="off">
						</div>
					</div>
				</div>
			`;

			clientProvidedPartsContainer.appendChild(partEntry);
		}

		function removeClientPartEntry(button) {
			const clientProvidedPartsContainer = document.getElementById('clientProvidedPartsContainer');
			if (clientProvidedPartsContainer.children.length > 1) {
				button.closest('.client-part-entry').remove();
			} else {
				MacproDialog.info({
					title: 'Client Part Required',
					message: 'You must have at least one client provided part entry.'
				});
			}
		}

		// DELETE WORK ORDER
		let workOrderIdToDelete = null;

		function openDeleteModal() {
			document.getElementById('deleteWorkOrderModal').style.display = 'block';
			document.body.classList.add('modal-open');
		}

		function closeDeleteModal() {
			document.getElementById('deleteWorkOrderModal').style.display = 'none';
			document.body.classList.remove('modal-open');
		}

		function deleteWorkOrder(event, id, code) {
			if (event && typeof event.preventDefault === 'function') {
				event.preventDefault();
			}
			workOrderIdToDelete = id;
			MacproDialog.confirm({
				type: 'danger',
				title: 'Delete Work Order?',
				message: 'Delete ' + code + '? Stock from purchased items will be restored.',
				confirmLabel: 'Delete Work Order'
			}).then(function (confirmed) {
				if (confirmed) {
					confirmDeleteWorkOrder();
				}
			});
		}

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
			fetch('src/handlers/get_work_order.php?id=' + encodeURIComponent(id))

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
				const orderedParts = data.orderedParts || [];
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
				if (purchased.length > 0 || orderedParts.length > 0 || clientParts.length > 0) {
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
					if (orderedParts.length) {
						partsHtml += `<div style="margin-bottom: 15px;">
							<small style="color: #6c757d; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 10px;">Ordered Parts</small>
							<div style="background: #fff7ed; border-left: 3px solid #fd7e14; padding: 12px; border-radius: 6px;">
								<ul style="margin: 0; padding-left: 20px;">` + orderedParts.map(p => {
									const qty = parseFloat(p.quantity) || 0;
									const price = parseFloat(p.price) || 0;
									const total = qty * price;
									const name = `${p.brand ? escapeHtml(p.brand) + ' ' : ''}${escapeHtml(p.part_name || 'Item')}`;
									const category = p.category ? ` <small style="color: #6c757d;">(${escapeHtml(p.category)})</small>` : '';
									return `<li style="margin-bottom: 8px; color: #555;">${name}${category} <strong>x${qty}</strong> @ Php ${price.toFixed(2)} = <span style="color: #fd7e14; font-weight: 700;">Php ${total.toFixed(2)}</span>${p.description ? `<br><small>${escapeHtml(p.description)}</small>` : ''}</li>`;
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
				const orderedPartTotal = orderedParts.reduce((sum, part) => {
					const quantity = parseFloat(part.quantity) || 0;
					const price = parseFloat(part.price) || 0;
					return sum + (quantity * price);
				}, 0);

				const grandTotal = diagnosticFee + workOrderCost + purchasedPartTotal + orderedPartTotal;

				let paymentHtml = `
					<div style="display: grid; gap: 14px;">
						<div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px;">
							<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 3px solid #667eea; min-width: 0;">
								<small style="color: #667eea; font-weight: 600;">Diagnostic Fee</small>
								<p style="font-size: 1.2rem; font-weight: 700; color: #667eea; margin: 8px 0 0 0; overflow-wrap: anywhere;">Php ${diagnosticFee.toFixed(2)}</p>
							</div>
							<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 3px solid #667eea; min-width: 0;">
								<small style="color: #667eea; font-weight: 600;">Work Order Cost</small>
								<p style="font-size: 1.2rem; font-weight: 700; color: #667eea; margin: 8px 0 0 0; overflow-wrap: anywhere;">Php ${workOrderCost.toFixed(2)}</p>
							</div>
							<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 3px solid #667eea; min-width: 0;">
								<small style="color: #667eea; font-weight: 600;">Purchased Parts</small>
								<p style="font-size: 1.2rem; font-weight: 700; color: #667eea; margin: 8px 0 0 0; overflow-wrap: anywhere;">Php ${purchasedPartTotal.toFixed(2)}</p>
							</div>
							<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 3px solid #fd7e14; min-width: 0;">
								<small style="color: #fd7e14; font-weight: 600;">Ordered Parts</small>
								<p style="font-size: 1.2rem; font-weight: 700; color: #fd7e14; margin: 8px 0 0 0; overflow-wrap: anywhere;">Php ${orderedPartTotal.toFixed(2)}</p>
							</div>
						</div>
						<div style="background: #333341; color: white; padding: 24px; border-radius: 8px; text-align: center;">
							<small style="opacity: 0.9; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 8px; font-size: 0.9rem;">Total Amount</small>
							<p style="font-size: 2.5rem; font-weight: 700; margin: 0; overflow-wrap: anywhere;">Php ${grandTotal.toFixed(2)}</p>
							<small style="opacity: 0.85; display: block; margin-top: 8px; font-size: 0.85rem;">(Diagnostic + Work Order + Purchased/Ordered Parts)</small>
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

		function confirmDeleteWorkOrder() {
			if (!workOrderIdToDelete) {
				MacproDialog.error({
					title: 'No Work Order Selected',
					message: 'No work order selected for deletion.'
				});
				return;
			}

			const yesBtn = document.getElementById('confirmDeleteBtn');
			if (yesBtn) {
				yesBtn.disabled = true;
				yesBtn.textContent = 'Deleting...';
			}

			fetch('src/handlers/delete_work_order.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: 'id=' + encodeURIComponent(workOrderIdToDelete)
			})
			.then(response => {
				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}
				return response.json();
			})
			.then(data => {
				console.log('Delete response:', data);
				if (data.success) {
					MacproDialog.success({
						title: 'Work Order Deleted',
						message: 'Work order deleted successfully.'
					}).then(function () {
						location.reload();
					});
				} else {
					MacproDialog.error({
						title: 'Work Order Not Deleted',
						message: data.message || 'Unknown error'
					});
					if (yesBtn) {
						yesBtn.disabled = false;
						yesBtn.textContent = 'Yes';
					}
				}
			})
			.catch(error => {
				console.error('Delete error:', error);
				MacproDialog.error({
					title: 'Work Order Not Deleted',
					message: 'Failed to delete work order: ' + error.message
				});
				if (yesBtn) {
					yesBtn.disabled = false;
					yesBtn.textContent = 'Yes';
				}
			});

			closeDeleteModal();
		}

		// EDIT WORK ORDER
		function editWorkOrder(id) {
			// Fetch the work order data
			fetch('src/handlers/get_work_order.php?id=' + encodeURIComponent(id))
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					populateEditForm(data.workOrder, data.purchasedParts, data.clientParts, data.orderedParts);
					// Switch to step 1 and open modal
					goToStep(1);
					document.getElementById('addWorkOrderToggle').checked = true;
				} else {
					MacproDialog.error({
						title: 'Work Order Not Loaded',
						message: data.message || 'Failed to load work order details.'
					});
				}
			})
			.catch(error => {
				console.error('Error:', error);
				MacproDialog.error({
					title: 'Work Order Not Loaded',
					message: 'Failed to load work order.'
				});
			});
		}

		function populateEditForm(workOrder, purchasedParts, clientParts, orderedParts) {
			// Store the work order ID for update
			let workOrderIdInput = document.querySelector('input[name="work_order_id"]');
			if (!workOrderIdInput) {
				workOrderIdInput = document.createElement('input');
				workOrderIdInput.type = 'hidden';
				workOrderIdInput.name = 'work_order_id';
				document.getElementById('workOrderForm').appendChild(workOrderIdInput);
			}
			workOrderIdInput.value = workOrder.id;

			// Change form action to update handler
			const form = document.getElementById('workOrderForm');
			form.action = 'src/handlers/update_work_order.php';

			// Change submit button text and input name
			const submitBtn = document.getElementById('submitBtn');
			submitBtn.textContent = 'Update Work Order';
			submitBtn.name = 'update_work_order';

			// Change modal title
			document.getElementById('modalTitle').textContent = 'Edit Work Order';

			// Populate Step 1 fields
			setUnitTypeValue(workOrder.unit_type || '');
			document.querySelector('input[name="brand"]').value = workOrder.brand || '';
			document.querySelector('input[name="model"]').value = workOrder.model || '';
			document.querySelector('textarea[name="specs_acce"]').value = workOrder.specs_acce || '';
			document.querySelector('input[name="request_date"]').value = workOrder.request_date || '';
			document.querySelector('textarea[name="prob_find"]').value = workOrder.prob_find || '';
			document.querySelector('input[name="diagnostic_fee"]').value = workOrder.diagnostic_fee || '';
			document.querySelector('input[name="work_order_cost"]').value = workOrder.work_order_cost || '';
			document.querySelector('input[name="status"]').value = workOrder.status || 'Pending';
			document.querySelector('select[name="technician_id"]').value = workOrder.technician_id || '';
			document.querySelector('textarea[name="notes"]').value = workOrder.notes || '';

			// Clear and populate purchased parts
			const purchasedContainer = document.getElementById('purchasedPartsContainer');
			purchasedContainer.innerHTML = '';

			if (purchasedParts && purchasedParts.length > 0) {
				purchasedParts.forEach(part => {
					const partEntry = document.createElement('div');
					partEntry.className = 'purchased-part-entry';
					partEntry.style.marginBottom = '20px';
					partEntry.style.padding = '15px';
					partEntry.style.border = '1px solid #ddd';
					partEntry.style.borderRadius = '4px';
					partEntry.style.backgroundColor = '#f9f9f9';

					partEntry.innerHTML = `
						<div class="row">
							<div style="width: 70%;">
								<div class="form-group">
									<label class="form-label">Part Name</label>
									<div style="position: relative;">
										<input type="text" class="form-control part-search" placeholder="Search for a part..." autocomplete="off" value="${part.product_name || ''}">
										<input type="hidden" class="part-item-id" name="purchased_part_item_id[]" value="${part.product_id}">
										<div class="part-dropdown" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; max-height: 200px; overflow-y: auto; z-index: 100; display: none;">
										</div>
									</div>
								</div>
							</div>
							<div style="width: 25%;">
								<div class="form-group">
									<label class="form-label">Quantity</label>
									<input type="number" class="form-control" placeholder="Qty" name="purchased_part_quantity[]" min="1" value="${part.quantity}" autocomplete="off">
								</div>
							</div>
							<div style="width: 5%; align-self: flex-end; margin-bottom: 12px;">
								<button type="button" class="btn btn-sm btn-danger" onclick="removePurchasedPartEntry(this)">×</button>
							</div>
						</div>
					`;
					purchasedContainer.appendChild(partEntry);
				});
			} else {
				// Add empty entry
				const partEntry = document.createElement('div');
				partEntry.className = 'purchased-part-entry';
				partEntry.style.marginBottom = '20px';
				partEntry.style.padding = '15px';
				partEntry.style.border = '1px solid #ddd';
				partEntry.style.borderRadius = '4px';
				partEntry.style.backgroundColor = '#f9f9f9';

				partEntry.innerHTML = `
					<div class="row">
						<div style="width: 70%;">
							<div class="form-group">
								<label class="form-label">Part Name</label>
								<div style="position: relative;">
									<input type="text" class="form-control part-search" placeholder="Search for a part..." autocomplete="off" data-item-id="">
									<input type="hidden" class="part-item-id" name="purchased_part_item_id[]" value="">
									<div class="part-dropdown" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; max-height: 200px; overflow-y: auto; z-index: 100; display: none;">
									</div>
								</div>
							</div>
						</div>
						<div style="width: 25%;">
							<div class="form-group">
								<label class="form-label">Quantity</label>
								<input type="number" class="form-control" placeholder="Qty" name="purchased_part_quantity[]" min="1" value="1" autocomplete="off">
							</div>
						</div>
						<div style="width: 5%; align-self: flex-end; margin-bottom: 12px;">
							<button type="button" class="btn btn-sm btn-danger" onclick="removePurchasedPartEntry(this)">×</button>
						</div>
					</div>
				`;
				purchasedContainer.appendChild(partEntry);
			}

			// Clear and populate ordered parts
			const orderedContainer = document.getElementById('orderedPartsContainer');
			orderedContainer.innerHTML = '';

			if (orderedParts && orderedParts.length > 0) {
				orderedParts.forEach(part => addOrderedPartEntry(part));
			} else {
				addOrderedPartEntry();
			}

			// Clear and populate client provided parts
			const clientContainer = document.getElementById('clientProvidedPartsContainer');
			clientContainer.innerHTML = '';

			if (clientParts && clientParts.length > 0) {
				clientParts.forEach(part => {
					const partEntry = document.createElement('div');
					partEntry.className = 'client-part-entry';
					partEntry.style.marginBottom = '20px';
					partEntry.style.padding = '15px';
					partEntry.style.border = '1px solid #28a745';
					partEntry.style.borderRadius = '4px';
					partEntry.style.backgroundColor = '#f1f9f4';

					partEntry.innerHTML = `
						<div class="row">
							<div style="width: 45%;">
								<div class="form-group">
									<label class="form-label">Product Name</label>
									<input type="text" class="form-control" placeholder="Enter product name" name="client_part_product_name[]" value="${part.product_name || ''}" autocomplete="off">
								</div>
							</div>
							<div style="width: 50%; padding-left: 3%;">
								<div class="form-group">
									<label class="form-label">Description</label>
									<textarea class="form-control" placeholder="Enter description" name="client_part_description[]" style="height: 40px;" autocomplete="off">${part.description || ''}</textarea>
								</div>
							</div>
							<div style="width: 5%; align-self: flex-end; margin-bottom: 12px;">
								<button type="button" class="btn btn-sm btn-danger" onclick="removeClientPartEntry(this)">×</button>
							</div>
						</div>
						<div class="row">
							<div style="width: 45%;">
								<div class="form-group">
									<label class="form-label">Quantity</label>
									<input type="number" class="form-control" placeholder="Qty" name="client_part_quantity[]" min="1" value="${part.quantity}" autocomplete="off">
								</div>
							</div>
						</div>
					`;
					clientContainer.appendChild(partEntry);
				});
			} else {
				// Add empty entry
				const partEntry = document.createElement('div');
				partEntry.className = 'client-part-entry';
				partEntry.style.marginBottom = '20px';
				partEntry.style.padding = '15px';
				partEntry.style.border = '1px solid #28a745';
				partEntry.style.borderRadius = '4px';
				partEntry.style.backgroundColor = '#f1f9f4';

				partEntry.innerHTML = `
					<div class="row">
						<div style="width: 45%;">
							<div class="form-group">
								<label class="form-label">Product Name</label>
								<input type="text" class="form-control" placeholder="Enter product name" name="client_part_product_name[]" autocomplete="off">
							</div>
						</div>
						<div style="width: 50%; padding-left: 3%;">
							<div class="form-group">
								<label class="form-label">Description</label>
								<textarea class="form-control" placeholder="Enter description" name="client_part_description[]" style="height: 40px;" autocomplete="off"></textarea>
							</div>
						</div>
						<div style="width: 5%; align-self: flex-end; margin-bottom: 12px;">
							<button type="button" class="btn btn-sm btn-danger" onclick="removeClientPartEntry(this)">×</button>
						</div>
					</div>
					<div class="row">
						<div style="width: 45%;">
							<div class="form-group">
								<label class="form-label">Quantity</label>
								<input type="number" class="form-control" placeholder="Qty" name="client_part_quantity[]" min="1" value="1" autocomplete="off">
							</div>
						</div>
					</div>
				`;
				clientContainer.appendChild(partEntry);
			}
		}

		function resetFormToAdd() {
			// Reset form for new addition
			const form = document.getElementById('workOrderForm');
			form.action = 'src/handlers/add_work_order.php';
			form.reset();
			toggleOtherUnitType();

			// Remove work order ID input
			let workOrderIdInput = document.querySelector('input[name="work_order_id"]');
			if (workOrderIdInput) {
				workOrderIdInput.remove();
			}

			// Reset submit button
			const submitBtn = document.getElementById('submitBtn');
			submitBtn.textContent = 'Add Work Order';
			submitBtn.name = 'add_work_order';

			// Reset modal title
			document.getElementById('modalTitle').textContent = 'Add New Work Order';

			// Reset parts to single empty entries
			const purchasedContainer = document.getElementById('purchasedPartsContainer');
			purchasedContainer.innerHTML = `
				<div class="purchased-part-entry" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background-color: #f9f9f9;">
					<div class="row">
						<div style="width: 70%;">
							<div class="form-group">
								<label class="form-label">Part Name</label>
								<div style="position: relative;">
									<input type="text" class="form-control part-search" placeholder="Search for a part..." autocomplete="off" data-item-id="">
									<input type="hidden" class="part-item-id" name="purchased_part_item_id[]" value="">
									<div class="part-dropdown" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; max-height: 200px; overflow-y: auto; z-index: 100; display: none;">
									</div>
								</div>
							</div>
						</div>
						<div style="width: 25%;">
							<div class="form-group">
								<label class="form-label">Quantity</label>
								<input type="number" class="form-control" placeholder="Qty" name="purchased_part_quantity[]" min="1" value="1" autocomplete="off">
							</div>
						</div>
						<div style="width: 5%; align-self: flex-end; margin-bottom: 12px;">
							<button type="button" class="btn btn-sm btn-danger" onclick="removePurchasedPartEntry(this)">×</button>
						</div>
					</div>
				</div>
			`;

			const orderedContainer = document.getElementById('orderedPartsContainer');
			orderedContainer.innerHTML = '';
			addOrderedPartEntry();

			const clientContainer = document.getElementById('clientProvidedPartsContainer');
			clientContainer.innerHTML = `
				<div class="client-part-entry" style="margin-bottom: 20px; padding: 15px; border: 1px solid #28a745; border-radius: 4px; background-color: #f1f9f4;">
					<div class="row">
						<div style="width: 45%;">
							<div class="form-group">
								<label class="form-label">Product Name</label>
								<input type="text" class="form-control" placeholder="Enter product name" name="client_part_product_name[]" autocomplete="off">
							</div>
						</div>
						<div style="width: 50%; padding-left: 3%;">
							<div class="form-group">
								<label class="form-label">Description</label>
								<textarea class="form-control" placeholder="Enter description" name="client_part_description[]" style="height: 40px;" autocomplete="off"></textarea>
							</div>
						</div>
						<div style="width: 5%; align-self: flex-end; margin-bottom: 12px;">
							<button type="button" class="btn btn-sm btn-danger" onclick="removeClientPartEntry(this)">×</button>
						</div>
					</div>
					<div class="row">
						<div style="width: 45%;">
							<div class="form-group">
								<label class="form-label">Quantity</label>
								<input type="number" class="form-control" placeholder="Qty" name="client_part_quantity[]" min="1" value="1" autocomplete="off">
							</div>
						</div>
					</div>
				</div>
			`;
		}
	</script>
	<div class="main-container">
			<div class="min-height-200px">
				<div class="page-header">
					<div class="row">
						<div class="col-md-6 col-sm-12">
							<span class="user-icon">
								<div class="icon-letter-container" style="width: 100px; height: 100px;">
									<label class="icon-letter" style="font-size:40px;"><?= " ".  htmlspecialchars($row['first_name'][0]) . htmlspecialchars($row['last_name'][0]) ." "; ?></label>
								</div>
							</span>
						</div>
						<div class="col-md-6 col-sm-12">
							<div class="title">
								<h4 style="font-size: 50px;"><?= htmlspecialchars($row['last_name'].', '.$row['first_name']) ?></h4>
							</div>
							<div>
								<p><?= htmlspecialchars($row['address'])?></p>
								<p><?= htmlspecialchars($row['email'])?></p>
								<p><?= htmlspecialchars($row['contact_num'])?></p>
							</div>
						</div>
						<div class="col-md-6 col-sm-12"></div>	
					</div>
				</div>
				<!-- Simple Datatable start -->
				<div class="card-box mb-30">
					<div class="pd-20">
						<div class="row">
							<div class="col-md-6 col-sm-12" style="margin-top: auto; margin-bottom: auto;">
								<div class="">
									<h4><i></i> Transaction</h4>
								</div>
							</div>
							<div class="col-sm-12 col-md-6">
								<div class="dataTables_length" id="DataTables_Table_0_length">
									<label>Show 
										<form method="GET" style="display: inline;">
											<input type="hidden" name="client_id" value="<?= htmlspecialchars($client_id) ?>">
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
									<label>Search:
										<form method="GET">
											<input type="hidden" name="client_id" value="<?= htmlspecialchars($client_id) ?>">
						<input type="search" name="search" class="form-control form-control-sm" placeholder="Search work order..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" autocomplete="off">
										</form>
									</label>
								</div>
							</div>
							<div class="col-md-6 col-sm-12 text-right" style="margin-left: auto;">
								<div class="dropdown">
									<label for="addWorkOrderToggle" class="btn btn-primary">
										Add New
									</label>
								</div>
							</div>
						</div>
					</div>

					<!-- Tabs -->
					<div class="tabs">
						<input type="radio" id="tab-workorder" name="transaction-tab" checked>
						<input type="radio" id="tab-purchase" name="transaction-tab">

						<div class="tab-header">
							<label for="tab-workorder">Work Order List</label>
							<label for="tab-purchase">Purchased List</label>
						</div>

						<div class="tab-body">
							<!-- WORK ORDER TAB -->
							<div class="tab-panel workorder-panel">
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
											$s = mysqli_real_escape_string($conn, $_GET['search']);
											$where .= " AND (LOWER(code) LIKE '%$s%')";
										}

										// Secure filter
										if (!empty($_GET['filter'])) {
											$f = mysqli_real_escape_string($conn, $_GET['filter']);
											$where .= " AND status='$f'";
										}

										// Get total count for pagination info
										$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM work_order WHERE client_id = $client_id AND $where");
										$count_row = mysqli_fetch_assoc($count_result);
										$total_records = $count_row['total'];

										// Calculate offset
										$offset = ($current_page - 1) * $limit;
										$total_pages = ceil($total_records / $limit);
										$offset = min($offset, $total_records); // Prevent offset from exceeding total records

										// Correct table + column names with LIMIT and OFFSET
										$result = mysqli_query($conn, "SELECT * FROM work_order WHERE client_id=$client_id AND $where ORDER BY code DESC LIMIT $limit OFFSET $offset");
										$records_shown = mysqli_num_rows($result);
										$record_start = ($total_records > 0) ? $offset + 1 : 0;
										$record_end = min($offset + $records_shown, $total_records);
									?>
									<tbody>
										<?php while ($wo = mysqli_fetch_assoc($result)) { ?>
										<tr>
											<td style="text-align: center;"><?= htmlspecialchars($wo['code']) ?></td>

											<td style="text-align: center;"><?= htmlspecialchars(payment_display_date($wo['request_date'])) ?></td>

											<td style="text-align: center;"><?= htmlspecialchars($wo['unit_type']) ?></td>

											<td style="text-align: center;"><?= htmlspecialchars($wo['brand']) . ' ' . $wo['model'] ?></td>
											
											<td style="text-align: center;"><?= htmlspecialchars($wo['prob_find']) ?></td>
											
											<td style="text-align: center;"><?= 'Php'.' '.htmlspecialchars($wo['work_order_cost'] + $wo['diagnostic_fee']) ?></td>
											
											<td style="text-align: center;"><?= htmlspecialchars(payment_display_date($wo['completion_date'], 'M d, Y') ?? '—')?></td>
											
											<td style="text-align: center;">
												<?php
													$status = strtolower($wo['status']);
													$status_class = '';

													if ($status == 'pending') {
														$status_class = 'bg-warning';
													} elseif ($status == 'diagnosing') {
														$status_class = 'bg-info';
													} elseif ($status == 'waiting for parts') {
														$status_class = 'bg-warning';
													} elseif ($status == 'in progress') {
														$status_class = 'bg-info';
													} elseif ($status == 'repaired') {
														$status_class = 'bg-success';
													} elseif ($status == 'released') {
														$status_class = 'bg-success';
													} elseif ($status == 'cancelled') {
														$status_class = 'bg-danger';
													}
												?>
												<span class="badge <?= $status_class ?>" style="width: 100%; ">
													<?= htmlspecialchars($wo['status']) ?>
												</span>
											</td>
											
											<td style="text-align: center;">
												<div class="dropdown">
													<a class="btn btn-link font-24 p-0 line-height-1 no-arrow dropdown-toggle" href="#" role="button" data-toggle="dropdown">
														<img src="src/images/menu-dots.png" width="25px" style="border: none">
													</a>
													<div class="dropdown-menu dropdown-menu-right dropdown-menu-icon-list">
														<a class="dropdown-item" href="#" onclick="viewWorkOrder(<?= $wo['id'] ?>); return false;"><i class="dw dw-eye"></i> View</a>
														<a class="dropdown-item" href="#" onclick="editWorkOrder(<?= $wo['id'] ?>); return false;"><i class="dw dw-edit"></i> Edit</a>
														<a class="dropdown-item" href="javascript:void(0)" onclick="deleteWorkOrder(event, <?= $wo['id'] ?>, '<?= htmlspecialchars($wo['code']) ?>'); return false;"><i class="dw dw-delete-3"></i> Delete</a>
													</div>
												</div>
											</td>
										</tr>
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
													<a href="?page=<?= max(1, $current_page - 1) ?>&limit=<?= isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10' ?>&search=<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>&client_id=<?= htmlspecialchars($client_id) ?>" aria-controls="DataTables_Table_0" class="page-link" <?= ($current_page <= 1) ? 'style="pointer-events: none;"' : '' ?>>
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
														echo '<li class="paginate_button page-item"><a href="?page=1&limit=' . (isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10') . '&search=' . (isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '') . '&client_id=' . htmlspecialchars($client_id) . '" class="page-link">1</a></li>';
														if ($start_page > 2) {
															echo '<li class="paginate_button page-item disabled"><span class="page-link">...</span></li>';
														}
													}
													
													for ($i = $start_page; $i <= $end_page; $i++) {
														$active = ($i == $current_page) ? 'active' : '';
														echo '<li class="paginate_button page-item ' . $active . '"><a href="?page=' . $i . '&limit=' . (isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10') . '&search=' . (isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '') . '&client_id=' . htmlspecialchars($client_id) . '" class="page-link">' . $i . '</a></li>';
													}
													if ($end_page < $total_pages) {
														if ($end_page < $total_pages - 1) {
															echo '<li class="paginate_button page-item disabled"><span class="page-link">...</span></li>';
														}
														echo '<li class="paginate_button page-item"><a href="?page=' . $total_pages . '&limit=' . (isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10') . '&search=' . (isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '') . '&client_id=' . htmlspecialchars($client_id) . '" class="page-link">' . $total_pages . '</a></li>';
													}
												?>

												<!-- Next Button -->
												<li class="paginate_button page-item next <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
													<a href="?page=<?= min($total_pages, $current_page + 1) ?>&limit=<?= isset($_GET['limit']) ? htmlspecialchars($_GET['limit']) : '10' ?>&search=<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>&client_id=<?= htmlspecialchars($client_id) ?>" aria-controls="DataTables_Table_0" class="page-link" <?= ($current_page >= $total_pages) ? 'style="pointer-events: none;"' : '' ?>>
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

							<!-- PURCHASE TAB -->
							<div class="tab-panel purchase-panel">
								<p class="pd-20">Purchased items will appear here.</p>
							</div>
						</div>
					</div>
				</div>

				<!-- Simple Datatable End -->
		</div>
	</div>
					
					
	<!-- Delete Confirmation Modal -->
	<div id="deleteWorkOrderModal" style="display:none; position:fixed; inset:0; z-index:2000;">
		<div style="position:absolute; inset:0; background:rgba(0,0,0,0.5);" onclick="closeDeleteModal()"></div>
		<div style="position:relative; width:100%; max-width:420px; margin:80px auto; background:#fff; border-radius:8px; box-shadow:0 20px 50px rgba(0,0,0,0.2); overflow:hidden; z-index:2001;">
			<div style="padding:20px; border-bottom:1px solid #eee;">
				<h4 style="margin:0;">Confirm Delete</h4>
			</div>
			<div style="padding:20px;">
				<p style="margin:0 0 16px;">Are you sure you want to delete work order <strong id="workOrderCodeDelete"></strong>?</p>
				<div style="display:flex; gap:10px; justify-content:flex-end;">
					<button type="button" class="btn btn-secondary" onclick="closeDeleteModal()" style="padding:10px 16px;">Cancel</button>
					<button type="button" id="confirmDeleteBtn" class="btn btn-danger" onclick="confirmDeleteWorkOrder()" style="padding:10px 16px;">Yes, Delete</button>
				</div>
			</div>
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
							<div class="col-md-6" style="margin-left:20%;">
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
</html>
