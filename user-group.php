
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
								<h4><i class="micon fa fa-users mtext"></i> User group</h4>
							</div>
						</div>
						<div class="col-md-6 col-sm-12 text-right">
							<div class="dropdown">
								<a href="#" class="btn btn-primary" data-backdrop="static" data-toggle="modal" data-target="#add_user">
									Add New
								</a>
							</div>
						</div>
					</div>
				</div>
				<!-- Simple Datatable start -->
				<div class="card-box mb-30">
					<div class="pd-20">
						<h4 class="text-blue h4">User Groups List</h4>
					</div>
					<!-- Table Controls -->
					<div class="row mb-20">
						<div class="col-sm-12 col-md-6">
							<div class="dataTables_length" id="DataTables_Table_0_length">
								<label>Show 
									<select name="DataTables_Table_0_length" aria-controls="DataTables_Table_0" class="custom-select custom-select-sm form-control form-control-sm">
										<option value="10">10</option>
										<option value="25">25</option>
										<option value="50">50</option>
										<option value="-1">All</option>
									</select> entries
								</label>
							</div>
						</div>
						<div class="col-sm-12 col-md-6">
							<div id="DataTables_Table_0_filter" class="dataTables_filter">
								<label>Search:
									<input type="search" class="form-control form-control-sm" placeholder="Search" aria-controls="DataTables_Table_0">
								</label>
							</div>
						</div>
					</div>
					<div class="pb-20">
						<table class="data-table table responsive">
							<thead>
								<tr>
									<th>Group Name</th>
									<th>Description</th>
									<th>Allow Add</th>
									<th>Allow Edit</th>
									<th>Allow Delete</th>
									<th>Allow Print</th>
									<th>Allow Import</th>
									<th>Allow Export</th>
									<th class="datatable-nosort">Action</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>Group 1</td>
									<td>Description</td>
									<td>yes</td>
									<td>yes</td>
									<td>yes</td>
									<td>yes</td>
									<td>yes</td>
									<td>yes</td>
									<td>
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
								<tr>
									<td>Group 1</td>
									<td>Description</td>
									<td>yes</td>
									<td>no</td>
									<td>yes</td>
									<td>yes</td>
									<td>no</td>
									<td>yes</td>
									<td>
										<div class="dropdown">
											<a class="btn btn-link font-24 p-0 line-height-1 no-arrow dropdown-toggle" href="#" role="button" data-toggle="dropdown">
												<i class="dw dw-more"></i>
											</a>
											<div class="dropdown-menu dropdown-menu-right dropdown-menu-icon-list">
												<a class="dropdown-item" href="#"><i class="dw dw-edit2"></i> Edit</a>
												<a class="dropdown-item" href="#"><i class="dw dw-delete-3"></i> Delete</a>
											</div>
										</div>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					<!-- Pagination -->
					<div class="row">
						<div class="col-sm-12 col-md-5">
							<div class="dataTables_info" id="DataTables_Table_0_info" role="status" aria-live="polite">
								1-1 of 1 entries
							</div>
						</div>
						<div class="col-sm-12 col-md-7">
							<div class="dataTables_paginate paging_simple_numbers" id="DataTables_Table_0_paginate">
								<ul class="pagination justify-content-end">
									<li class="paginate_button page-item previous disabled" id="DataTables_Table_0_previous">
										<a href="#" aria-controls="DataTables_Table_0" class="page-link">
											<i class="ion-chevron-left"></i> Previous
										</a>
									</li>
									<li class="paginate_button page-item active">
										<a href="#" aria-controls="DataTables_Table_0" class="page-link">1</a>
									</li>
									<li class="paginate_button page-item next disabled" id="DataTables_Table_0_next">
										<a href="#" aria-controls="DataTables_Table_0" class="page-link">
											Next <i class="ion-chevron-right"></i>
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

	<!-- Add User Group Modal -->
	<div class="modal fade" id="add_user" tabindex="-1" role="dialog" aria-labelledby="addUserGroupLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="addUserGroupLabel">Add Group</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<form>
						<div class="form-group">
							<label for="groupName">Group Name</label>
							<input type="text" class="form-control" id="groupName" placeholder="Enter group name">
						</div>
						<div class="form-group">
							<label for="groupDesc">Description</label>
							<input type="text" class="form-control" id="groupDesc" placeholder="Enter description">
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="allowAdd">Allow Add</label>
									<select class="form-control" id="allowAdd">
										<option>Yes</option>
										<option>No</option>
									</select>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="allowEdit">Allow Edit</label>
									<select class="form-control" id="allowEdit">
										<option>Yes</option>
										<option>No</option>
									</select>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="allowDelete">Allow Delete</label>
									<select class="form-control" id="allowDelete">
										<option>Yes</option>
										<option>No</option>
									</select>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="allowPrint">Allow Print</label>
									<select class="form-control" id="allowPrint">
										<option>Yes</option>
										<option>No</option>
									</select>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="allowImport">Allow Import</label>
									<select class="form-control" id="allowImport">
										<option>Yes</option>
										<option>No</option>
									</select>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="allowExport">Allow Export</label>
									<select class="form-control" id="allowExport">
										<option>Yes</option>
										<option>No</option>
									</select>
								</div>
							</div>
						</div>
					</form>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-primary">Submit</button>
				</div>
			</div>
		</div>
	</div>
</html>