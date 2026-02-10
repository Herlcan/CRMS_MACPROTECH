
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
								<h4><i class="micon dw dw-shopping-basket mtext"></i> Work Order</h4>
							</div>
							
						</div>
					</div>
				</div>
				<!-- Simple Datatable start -->
				<div class="card-box mb-30">
					<div class="pd-20">
						<h4 class="text-blue h4">Work Order List</h4>
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
									<th>Code</th>
									<th>Request</th>
									<th>Service Name</th>
									<th>Amount</th>
									<th>Customer</th>
									<th>Technician</th>
									<th>Completion Date</th>
									<th>Status</th>
									<th class="datatable-nosort">Action</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>123-456</td>
									<td>05-27-2021</td>
									<td>Service name</td>
									<td><span class="badge bg-warning">1,250.00</span></td>
									<td>Juan Dela Cruz</td>
									<td>John Doe</td>
									<td>05-30-2021</td>
									<td><span class="badge bg-info">Pending</span></td>
									<td>
										<div class="dropdown">
											<a class="btn btn-link font-24 p-0 line-height-1 no-arrow dropdown-toggle" href="#" role="button" data-toggle="dropdown">
												<img src="src/images/menu-dots.png" width="25px" style="border: none">
											</a>
											<div class="dropdown-menu dropdown-menu-right dropdown-menu-icon-list">
												<a class="dropdown-item" href="#"><i class="dw dw-eye"></i> View</a>
												<a class="dropdown-item" href="#" data-toggle="modal" data-target="#add_technician"><i class="dw dw-edit2"></i> Edit</a>
												<a class="dropdown-item" href="#" data-toggle="modal" data-target="#delete"><i class="dw dw-delete-3"></i> Delete</a>
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
	<!-- Add Work orerd Modal -->
					<div class="col-md-12 col-sm-12 mb-30">
							<div class="modal fade" id="add_technician" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
								<div class="modal-dialog modal-lg modal-dialog-centered">
									<div class="modal-content">
										<div class=" border-radius-10">
											<div class="login-title"><br>
												<div class="col-md-12 col-sm-12 mb-30">
												<h2 class="text-center text-primary">Add Work Order</h2>
												</div>
											<form>

												<div class="input-group custom">
												<div class="col-md-4 col-sm-12">
													<div class="form-group">
																<label>Work Order Code</label>
																<input class="form-control form-control-lg" type="text">
															</div>
												</div>
												<div class="col-md-4 col-sm-12">
													<div class="form-group">
																<label>Request Date</label>
																<input class="form-control form-control-lg" type="date">
															</div>
												</div>
												<div class="col-md-4 col-sm-12">
													<div class="form-group">
																<label>Estimated Date completion</label>
																<input class="form-control form-control-lg" type="date">
															</div>
												</div>
												<div class="col-md-4 col-sm-12">
															<div class="form-group">
																<label>Service</label>
																<select class="selectpicker form-control form-control-lg" data-style="btn-outline-secondary btn-lg" title="Not Chosen">
																	<option>Service 1</option>
																	<option>Service 2</option>
																	<option>Service 3</option>
																</select>
															</div>
												</div>
												<div class="col-md-4 col-sm-12">
															<div class="form-group">
																<label>Customer</label>
																<select class="selectpicker form-control form-control-lg" data-style="btn-outline-secondary btn-lg" title="Not Chosen">
																	<option>John Doe</option>
																	<option>Juan Dela Cruz</option>
																	<option>Jane Doe</option>
																</select>
															</div>
												</div>
												<div class="col-md-4 col-sm-12">
															<div class="form-group">
																<label>Technician</label>
																<select class="selectpicker form-control form-control-lg" data-style="btn-outline-secondary btn-lg" title="Not Chosen">
																	<option>John Doe</option>
																	<option>Juan Dela Cruz</option>
																	<option>Jane Doe</option>
																</select>
															</div>
												</div>
												<div class="col-md-12 col-sm-12">
													<div class="form-group">
																<label>Amount</label>
																<input class="form-control form-control-lg" type="number">
															</div>
												</div>
												<div class="col-md-12 col-sm-12">
													<div class="form-group">
																<input type="submit" class="btn btn-primary" value="Submit">
																<input type="submit" class="btn btn-danger" value="Cancel">
															</div>
												</div>
												</div>
											</form>
										</div>
									</div>
								</div>
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
	<!-- js -->
	<script src="vendors/scripts/core.js"></script>
	<script src="vendors/scripts/script.min.js"></script>
	<script src="vendors/scripts/process.js"></script>
	<script src="vendors/scripts/layout-settings.js"></script>
	<script src="src/plugins/datatables/js/jquery.dataTables.min.js"></script>
	<script src="src/plugins/datatables/js/dataTables.bootstrap4.min.js"></script>
	<script src="src/plugins/datatables/js/dataTables.responsive.min.js"></script>
	<script src="src/plugins/datatables/js/responsive.bootstrap4.min.js"></script>
	<!-- Datatable Setting js -->
	<script src="vendors/scripts/datatable-setting.js"></script></body>
</html>