
<?php include 'header.php';?>

	<div class="header">
		<div class="header-left">
			<div class="menu-icon dw dw-menu"></div>
		</div>
		<div class="header-right">
			<div class="user-info-dropdown">
				<div class="dropdown">
					<a class="dropdown-toggle" href="#" role="button" data-toggle="dropdown">
						<span class="user-icon">
							<img src="src/images/admin.png" width="50">
						</span>
						<span class="user-name">John Doe</span>
					</a>
					<div class="dropdown-menu dropdown-menu-right dropdown-menu-icon-list">
						<a class="dropdown-item" href="#"><i class="dw dw-user1"></i> Profile</a>
						<a class="dropdown-item" href="#"><i class="dw dw-settings2"></i> Setting</a>
						<hr>
						<a class="dropdown-item" href="login.php"><i class="dw dw-logout"></i> Log Out</a>
					</div>
				</div>
			</div>
		</div>
	</div>

<?php include 'sidebar.php'; ?>

	<div class="mobile-menu-overlay"></div>

	<div class="main-container">
		<div class="pd-ltr-20 xs-pd-20-10">
			<div class="min-height-200px">
				<div class="page-header">
					<div class="row">
						<div class="col-md-6 col-sm-12">
							<div class="title">
								<h4><i class="micon dw dw-settings2 mtext"></i> Settings</h4>
							</div>
							<nav aria-label="breadcrumb" role="navigation">
								<ol class="breadcrumb">
									<li class="breadcrumb-item"><a href="index.php">Home</a></li>
									<li class="breadcrumb-item active" aria-current="page">Settings</li>
								</ol>
							</nav>
						</div>
					</div>
				</div>
				<!-- Simple Datatable start -->
				<div class="card-box mb-30">
					<div class="pd-20">
						<h4 class="text-blue h4">Change Settings</h4>
					</div>
					<div class="pb-20">
							<div class="pd-20 ">
								<form>
									<div class="row">
										<div class="col-md-6 col-sm-12">
											<div class="form-group">
											<label>Shop Name</label>
											<input class="form-control" type="text" placeholder="input shop name">
											</div>
										</div>
										<div class="col-md-6 col-sm-12">
											<div class="form-group">
											<label>Owner Name</label>
											<input class="form-control" type="text" placeholder="input owner name">
											</div>
										</div>
										<div class="col-md-12 col-sm-12">
											<div class="form-group">
											<label>Address</label>
											<input class="form-control" type="text" placeholder="input address">
											</div>
										</div>
										<div class="col-md-6 col-sm-12">
											<div class="form-group">
											<label>Email</label>
											<input class="form-control" type="text" placeholder="input email">
											</div>
										</div>
										<div class="col-md-6 col-sm-12">
											<div class="form-group">
											<label>Contact</label>
											<input class="form-control" type="text" placeholder="input contact number">
											</div>
										</div>
										<div class="col-md-12 col-sm-12">
											<div class="form-group">
											<label>Website</label>
											<input class="form-control" type="text" placeholder="www.website.com">
											</div>
										</div>
									</div>
									<div class="form-group">
										<input class="btn btn-primary" type="button" value="Save Changes">
									</div>
								</form>
							</div>
					</div>
					</div>
				</div>
				<!-- Simple Datatable End -->
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