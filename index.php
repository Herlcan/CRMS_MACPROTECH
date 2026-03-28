
<?php 

	include 'header.php';
	include 'sidebar.php'; 
	
	// Clients
	$total_clients = mysqli_fetch_assoc(
		mysqli_query($conn,"SELECT COUNT(*) as total FROM client")
	)['total'];

	// Work Orders
	$total_workorders = mysqli_fetch_assoc(
		mysqli_query($conn,"SELECT COUNT(*) as total FROM work_order")
	)['total'];

	$pending_workorders = mysqli_fetch_assoc(
		mysqli_query($conn,"SELECT COUNT(*) as total FROM work_order WHERE status='Pending'")
	)['total'];

	$completed_workorders = mysqli_fetch_assoc(
		mysqli_query($conn,"SELECT COUNT(*) as total FROM work_order WHERE status='Completed'")
	)['total'];

	// Technicians / Users
	$total_technicians = mysqli_fetch_assoc(
		mysqli_query($conn,"SELECT COUNT(*) as total FROM users WHERE role='Technician'")
	)['total'];

	// Revenue (Payments Table)
	//$total_revenue = mysqli_fetch_assoc(
		//mysqli_query($conn,"SELECT SUM(amount) as total FROM payments")
	//)['total'] ?? 0;

	// Low Stock Products
	$low_stock = mysqli_query(
		$conn,
		"SELECT COUNT(*) as total FROM items WHERE quantity < 10"
	);

	$low_stock_count = mysqli_fetch_assoc($low_stock)['total'];
	
	$status_query = mysqli_query(
	$conn,
	"SELECT status, COUNT(*) as total 
	 FROM work_order 
	 GROUP BY status"
	);

	$labels = [];
	$data = [];

	while($row = mysqli_fetch_assoc($status_query)){
		$labels[] = $row['status'];
		$data[] = $row['total'];
	}

	$recent_orders = mysqli_query(
		$conn,
		"SELECT w.*, c.first_name 
		FROM work_order w
		JOIN client c ON c.id = w.client_id
		ORDER BY w.id DESC LIMIT 5"
	);
?>

	<div class="mobile-menu-overlay"></div>

		<div class="main-container">
			<div class="xs-pd-20-10 pd-ltr-20">

				<div class="title pb-20">
					<h2 class="h3 mb-0">Dashboard</h2>
				</div>

				<div class="row pb-10">

					<!-- Work Orders -->
					<div class="col-xl-3 col-lg-3 col-md-6 mb-20">
						<div class="card-box height-100-p widget-style3">
							<div class="d-flex flex-wrap">

								<div class="widget-data">
									<div class="weight-700 font-24 text-dark">
										<?= number_format($total_workorders) ?>
									</div>
									<div class="font-14 text-secondary">Work Orders</div>
								</div>

								<div class="widget-icon">
									<div class="icon" data-color="#00eccf">
										<i class="fa fa-tasks"></i>
									</div>
								</div>

							</div>
						</div>
					</div>

					<!-- Clients -->
					<div class="col-xl-3 col-lg-3 col-md-6 mb-20">
						<div class="card-box height-100-p widget-style3">
							<div class="d-flex flex-wrap">

								<div class="widget-data">
									<div class="weight-700 font-24 text-dark">
										<?= number_format($total_clients) ?>
									</div>
								<div class="font-14 text-secondary">Clients</div>
							</div>

							<div class="widget-icon">
								<div class="icon" data-color="#ff5b5b">
									<i class="fa fa-users"></i>
								</div>
							</div>

							</div>
						</div>
					</div>

					<!-- Technicians -->
					<div class="col-xl-3 col-lg-3 col-md-6 mb-20">
						<div class="card-box height-100-p widget-style3">
							<div class="d-flex flex-wrap">

								<div class="widget-data">
									<div class="weight-700 font-24 text-dark">
										<?= number_format($total_technicians) ?>
									</div>
									<div class="font-14 text-secondary">Technicians</div>
								</div>

								<div class="widget-icon">
									<div class="icon" data-color="#2c515b">
										<i class="fa fa-wrench"></i>
									</div>
								</div>

							</div>
						</div>
					</div>

					<!-- Revenue -->
					<div class="col-xl-3 col-lg-3 col-md-6 mb-20">
						<div class="card-box height-100-p widget-style3">
							<div class="d-flex flex-wrap">

								<div class="widget-data">
									<div class="weight-700 font-24 text-dark">
										Php <?= number_format($total_revenue,2) ?>
									</div>
									<div class="font-14 text-secondary">Revenue</div>
								</div>

								<div class="widget-icon">
									<div class="icon" data-color="#09cc06">
										<i class="fa fa-money"></i>
									</div>
								</div>

							</div>
						</div>
					</div>

				</div>
				<div class="card-box mb-30 p-20">
					<h5 class="mb-20">Work Order Status Distribution</h5>

					<div style="max-width:450px; margin:auto;">
						<canvas id="statusChart" style="height:300px;"></canvas>
					</div>
				</div>
				<script>
				document.addEventListener("DOMContentLoaded", function(){

					const ctx = document.getElementById('statusChart');

					if(!ctx) return;

					new Chart(ctx, {
						type: 'pie',
						data: {
							labels: <?= json_encode($labels) ?>,
							datasets: [{
								data: <?= json_encode($data) ?>,
								backgroundColor: [
									'#ff5b5b',
									'#09cc06',
									'#00eccf',
									'#ffcc00'
								]
							}]
						},
						options:{
							responsive:true,
							plugins:{
								legend:{
									position:'bottom'
								}
							}
						}
					});

				});
				</script>
				<div class="card-box p-20">
					<h5>Recent Work Orders</h5>

					<table class="table">
						<tr>
							<th>Code</th>
							<th>Client</th>
							<th>Status</th>
							<th>Date</th>
						</tr>

						<?php while($row=mysqli_fetch_assoc($recent_orders)){ ?>
						<tr>
							<td><?= $row['code'] ?></td>
							<td><?= $row['first_name'] ?></td>
							<td><?= $row['status'] ?></td>
							<td><?= $row['request_date'] ?></td>
						</tr>
						<?php } ?>

					</table>
				</div>
			</div>
		</div>
	</div>

<?php include 'footer.php'; ?>
