
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
								<h4><i class="micon dw dw-money mtext"></i> Payment</h4>
							</div>
						</div>
						<div class="col-md-6 col-sm-12 text-right">
							<div class="dropdown">
								<a href="#" class="btn btn-primary" data-backdrop="static" data-toggle="modal" data-target="#add_payment">
									Add New
								</a>
							</div>
						</div>
					</div>
				</div>
				<!-- Simple Datatable start -->
				<div class="card-box mb-30">
					<div class="pd-20">
						<h4 class="text-blue h4">Payments List</h4>
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
									<th>OR No.</th>
									<th>Work Order Code</th>
									<th>Amount</th>
									<th>Status</th>
									<th>Date Paid</th>
									<th>Paid By</th>
									<th>Remarks</th>
									<th class="datatable-nosort">Action</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>OR-12345</td>
									<td>WO-1234</td>
									<td><span class="badge bg-warning">1,250.00</span></td>
									<td>unpaid</td>
									<td>--</td>
									<td>--</td>
									<td>--</td>
									<td>
										<div class="dropdown">
											<a class="btn btn-link font-24 p-0 line-height-1 no-arrow dropdown-toggle" href="#" role="button" data-toggle="dropdown">
												<i class="dw dw-more"></i>
											</a>
											<div class="dropdown-menu dropdown-menu-right dropdown-menu-icon-list">
												<a class="dropdown-item" href="#"><i class="dw dw-eye"></i> View</a>
												<a class="dropdown-item" href="#"><i class="dw dw-edit2"></i> Edit</a>
												<a class="dropdown-item" href="#"><i class="dw dw-delete-3"></i> Delete</a>
											</div>
										</div>
									</td>
								</tr>
								<tr>
									<td>OR-12344</td>
									<td>WO-3435</td>
									<td><span class="badge bg-success">1,250.00</span></td>
									<td>paid</td>
									<td>05-30-2021</td>
									<td>John Doe</td>
									<td>Done</td>
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

				<!-- Add Payment Modal -->
					<div class="col-md-12 col-sm-12 mb-30">
							<div class="modal fade" id="add_payment" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
								<div class="modal-dialog modal-dialog-centered">
									<div class="modal-content">
										<div class=" border-radius-10">
											<div class="login-title"><br>
												<div class="col-md-12 col-sm-12 mb-30">
												<h2 class="text-center text-primary">Add Payment</h2>
												</div>
											<form>

												<div class="input-group custom">
												<div class="col-md-6 col-sm-12">
													<div class="form-group">
																<label>OR No.</label>
																<input class="form-control form-control-lg" type="text">
															</div>
												</div>
												<div class="col-md-6 col-sm-12">
															<div class="form-group">
																<label>Work Code</label>
																<select class="selectpicker form-control form-control-lg" data-style="btn-outline-secondary btn-lg" title="Not Chosen">
																	<option>123AS</option>
																	<option>GBV23</option>
																	<option>56SDD</option>
																</select>
															</div>
												</div>
												<div class="col-md-6 col-sm-12">
													<div class="form-group">
																<label>Date Of Payment</label>
																<input class="form-control form-control-lg" type="date">
															</div>
												</div>
												<div class="col-md-6 col-sm-12">
													<div class="form-group">
																<label>Paid By</label>
																<input class="form-control form-control-lg" type="text">
															</div>
												</div>
												<div class="col-md-12 col-sm-12">
													<div class="form-group">
																<label>Remarks</label>
																<input class="form-control form-control-lg" type="text">
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