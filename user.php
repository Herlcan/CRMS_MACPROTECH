
<?php
	include 'src/db/connection.php';
	include 'header.php';
	include 'sidebar.php'; 
?>

	<!-- EDIT USER MODAL (Pure CSS) -->
	<input type="checkbox" id="editUserToggle" class="edit-client-toggle">
	<label for="editUserToggle" class="css-modal-overlay edit-client-overlay"></label>

	<!-- Pure CSS Modal -->
	<div class="css-modal">
		<div class="css-modal-content">
			<!-- Modal Header -->
			<div class="css-modal-header">
				<h5 class="css-modal-title">Edit User</h5>
				<label for="editUserToggle" class="css-modal-close">&times;</label>
			</div>

			<!-- Modal Body -->
			<div class="css-modal-body">
				<!-- Status Messages -->
				<?php if ($update_message): ?>
					<div class="alert alert-success">
						<i class="dw dw-checked"></i> <?= htmlspecialchars($update_message) ?>
					</div>
				<?php endif; ?>
				<?php if ($update_error): ?>
					<div class="alert alert-danger">
						<i class="dw dw-close"></i> <?= htmlspecialchars($update_error) ?>
					</div>
				<?php endif; ?>

				<!-- Profile Picture Section -->
				<!--<div class="profile-pic-container icon-letter-container profile-pic" style="margin-left: auto; margin-right: auto; margin-bottom: 20px;">
					<label class="icon-letter text-center" style="font-size: 40px;" id="modalUserInitial"></label>
				</div>-->

				<!-- Profile Form -->
				<form method="POST" action="src/handlers/edit_user.php" class="profile-form">
					<input type="hidden" name="user_id" id="userIdField" value="">
					<div class="form-group">
						<label class="form-label">Username</label>
						<input type="text" class="form-control" name="username" id="editUsername" autocomplete="off">
						<small class="form-text text-muted">At least 3 characters, must be unique</small>
					</div>

					<div class="form-group">
						<label class="form-label">First Name</label>
						<input type="text" class="form-control" name="first_name" id="editFirstName" autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">Last Name</label>
						<input type="text" class="form-control" name="last_name" id="editLastName" autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">Contact Number</label>
						<input type="text" class="form-control" name="contact_num" id="editContactNum" autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">Email</label>
						<input type="email" class="form-control" name="email" id="editEmail" autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">New Password (optional)</label>
						<input type="password" class="form-control" name="new_password" placeholder="Enter new password" autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">Role</label>
						<select class="form-control" name="role" id="editRole" required autocomplete="off">
							<option value="">Select Role</option>
							<option value="Administrator">Administrator</option>
							<option value="Technician">Technician</option>
							<option value="Staff">Staff</option>
						</select>
					</div>

					<!-- Modal Footer -->
					<div class="css-modal-footer">
						<!-- Edit Mode: Show Save and Cancel -->
						<label for="editUserToggle" class="btn btn-secondary">Cancel</label>
						<button type="submit" name="edit_user" class="btn btn-primary">Save Changes</button>
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
								<h4><i class="micon dw dw-user1 mtext"></i> Users</h4>
							</div>
						</div>
						<div class="col-md-6 col-sm-12 text-right" style="margin-left: auto;">
							<div class="dropdown">
								<label for="addUserToggle" class="btn btn-primary">
									Add New
								</label>
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
									<th style="text-align: center;">Profile</th>
									<th style="text-align: center;">Username</th>
									<th style="text-align: center;">Full Name</th>
									<th style="text-align: center;">Contact</th>
									<th style="text-align: center;">Email</th>
									<th style="text-align: center;">Role</th>
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
								    $where .= " AND (LOWER(first_name) LIKE '%$s%' OR LOWER(last_name) LIKE '%$s%' OR LOWER(email) LIKE '%$s%')";
								}

								// Secure filter
								if (!empty($_GET['filter'])) {
								    $f = mysqli_real_escape_string($conn, $_GET['filter']);
								    $where .= " AND status='$f'";
								}

								// Get total count for pagination info
								$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE $where");
								$count_row = mysqli_fetch_assoc($count_result);
								$total_records = $count_row['total'];

								// Calculate offset
								$offset = ($current_page - 1) * $limit;
								$total_pages = ceil($total_records / $limit);
								$offset = min($offset, $total_records); // Prevent offset from exceeding total records

								// Correct table + column names with LIMIT and OFFSET
								$result = mysqli_query($conn, "SELECT * FROM users WHERE $where ORDER BY last_name ASC LIMIT $limit OFFSET $offset");
								$records_shown = mysqli_num_rows($result);
								$record_start = ($total_records > 0) ? $offset + 1 : 0;
								$record_end = min($offset + $records_shown, $total_records);
							?>
							<tbody>
								<?php while ($row = mysqli_fetch_assoc($result)) { 

								$first_name = $row['first_name'];?>

								<tr>
									<td>
								        <div class="icon-letter-container"  style="margin: auto; text-align: center;">
											<label class="icon-letter"><?= " ".  htmlspecialchars($first_name[0]) ." "; ?></label>
										</div>
								    </td>
									<td style="text-align: center;"><?= htmlspecialchars($row['username']) ?></td>
									<td style="text-align: center;"><?= htmlspecialchars($row['last_name'].', '.$row['first_name']) ?></td>
									<td style="text-align: center;"><?= htmlspecialchars($row['contact_num']) ?></td>
									<td style="text-align: center;"><?= htmlspecialchars($row['email']) ?></td>
									<td><span class="badge bg-success" style="display: grid; align-items: center; justify-content: center;"><?= htmlspecialchars($row['role']) ?></span></td>
									<td>
										<div class="dropdown">
											<a class="btn btn-link font-24 p-0 line-height-1 no-arrow dropdown-toggle" href="#" role="button" data-toggle="dropdown">
												<img src="src/images/menu-dots.png" width="25px" style="border: none margin: auto;">
											</a>
											<div class="dropdown-menu dropdown-menu-right dropdown-menu-icon-list">
												<a>
													<label class="dropdown-item" onclick="editUser('<?= htmlspecialchars($row['username']) ?>', '<?= htmlspecialchars($row['first_name']) ?>', '<?= htmlspecialchars($row['last_name']) ?>', '<?= htmlspecialchars($row['contact_num']) ?>', '<?= htmlspecialchars($row['email']) ?>', '<?= htmlspecialchars($row['role']) ?>', '<?= $row['id'] ?>');">
														<i class="dw dw-edit2"></i> Edit
													</label>
												</a>
												<a href="src/handlers/delete_user.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this client?');">
								                	<label for="deleteClientToggle_<?= $row['id'] ?>" class="dropdown-item text-danger" style="cursor: pointer;">
								                	    <i class="dw dw-delete-3"></i> Delete
								                	</label>
												</a>
											</div>
										</div>
									</td>
								</tr>
								<?php } ?>
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

	<!-- Hidden checkbox for add user modal toggle -->
	<input type="checkbox" id="addUserToggle" class="add-user-toggle">

	<!-- Add User Modal Overlay -->
	<label for="addUserToggle" class="css-modal-overlay add-user-overlay"></label>

	<!-- ADD USER MODAL (Pure CSS) -->
	<div class="add-user-modal-container">
		<div class="css-modal-content">
			<!-- Modal Header -->
			<div class="css-modal-header">
				<h5 class="css-modal-title">Add New User</h5>
				<label for="addUserToggle" class="css-modal-close">&times;</label>
			</div>

			<!-- Modal Body -->
			<div class="css-modal-body">
				<?php if ($update_message): ?>
					<div class="alert alert-success">
						<i class="dw dw-checked"></i> <?= htmlspecialchars($update_message) ?>
					</div>
				<?php endif; ?>
				<?php if ($add_user_error): ?>
					<div class="alert alert-danger">
						<i class="dw dw-close"></i> <?= htmlspecialchars($add_user_error) ?>
					</div>
				<?php endif; ?>

				<!-- Form -->
				<form method="POST" action="src/handlers/add_user.php">

					<div class="form-group">
						<label class="form-label">Username</label>
						<input type="text" class="form-control" placeholder="Username" name="username" required autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">First Name</label>
						<input type="text" class="form-control" placeholder="First Name" name="first_name" required autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">Last Name</label>
						<input type="text" class="form-control" placeholder="Last Name" name="last_name" required autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">Contact</label>
						<input type="text" class="form-control" placeholder="Phone Number" name="contact_num" required autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">Email</label>
						<input type="email" class="form-control" placeholder="Email" name="email" required autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">Password</label>
						<input type="password" class="form-control" placeholder="Password" name="password" required autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">Role</label>
						<select class="form-control" name="role" required autocomplete="off">
							<option value="">Select Role</option>
							<option value="Administrator">Administrator</option>
							<option value="Technician">Technician</option>
							<option value="Staff">Staff</option>
						</select>
					</div>

					<!-- Modal Footer -->
					<div class="css-modal-footer">
						<button type="button" class="btn btn-secondary"
							onclick="document.getElementById('addUserToggle').checked = false;">
							Cancel
						</button>
						<button type="submit" name="add_user" class="btn btn-primary">Add User</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</html>

<script>
	// Open add user modal if there was an error
	<?php if (!empty($_SESSION['add_user_error'])): ?>
		document.getElementById('addUserToggle').checked = true;
	<?php endif; ?>

	// Function to populate edit modal and open it
	function editUser(username, firstName, lastName, contact, email, role, userId) {
		document.getElementById('editUsername').value = username
		document.getElementById('editFirstName').value = firstName;
		document.getElementById('editLastName').value = lastName;
		document.getElementById('editContactNum').value = contact;
		document.getElementById('editEmail').value = email;
		document.getElementById('editRole').value = role;
		document.getElementById('userIdField').value = userId;
		
		// Trigger checkbox to open modal
		document.getElementById('editUserToggle').checked = true;
	}

	// Close edit modal when clicking outside
	document.getElementById('editUserToggle').addEventListener('change', function() {
		if (!this.checked) {
			// Clear form when modal closes
			document.getElementById('editUsername').value = '';
			document.getElementById('editFirstName').value = '';
			document.getElementById('editLastName').value = '';
			document.getElementById('editEmail').value = '';
			document.getElementById('editContactNum').value = '';
			document.getElementById('editRole').value = '';
			document.getElementById('userIdField').value = '';
		}
	});
</script>