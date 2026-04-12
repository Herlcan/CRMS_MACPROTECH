<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1'); 
 
	include 'header.php';
	include 'sidebar.php'; 
	include 'src/db/connection.php';
	
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
	//	mysqli_query($conn,"SELECT SUM(amount) as total FROM payments")
	//)['total'] ?? 0;
	$total_revenue = 0; // Placeholder until payments module is implemented
 
	// Low Stock Products
	$low_stock = mysqli_query(
		$conn,
		"SELECT COUNT(*) as total FROM items WHERE quantity < 10"
	);

	$low_stock_count = mysqli_fetch_assoc($low_stock)['total'];
	
	// Work Order Status Distribution
	$status_query = mysqli_query(
		$conn,
		"SELECT status, COUNT(*) as total FROM work_order GROUP BY status"
	);
 
	$labels = [];
	$data = [];
	$total_chart = 0;
 
	while($row = mysqli_fetch_assoc($status_query)){
		$labels[] = $row['status'];
		$data[] = (int)$row['total'];
		$total_chart += (int)$row['total'];
	}
 
	// Recent Work Orders
	$recent_orders = mysqli_query(
		$conn,
		"SELECT w.*, c.first_name 
		FROM work_order w
		JOIN client c ON c.id = w.client_id
		ORDER BY w.id DESC LIMIT 5"
	);
 
	// Monthly trend for work orders (last 6 months)
	$wo_trend = mysqli_query($conn,
		"SELECT DATE_FORMAT(request_date,'%b') as month, COUNT(*) as total
		 FROM work_order
		 WHERE request_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
		 GROUP BY MONTH(request_date), DATE_FORMAT(request_date,'%b')
		 ORDER BY MONTH(request_date)"
	);
	$wo_trend_data = [];
	while($r = mysqli_fetch_assoc($wo_trend)) $wo_trend_data[] = $r['total'];
 
	// Monthly trend for clients
	$cl_trend = mysqli_query($conn,
		"SELECT COUNT(*) as total
		 FROM client
		 WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
		 GROUP BY MONTH(date)
		 ORDER BY MONTH(date)"
	);
	$cl_trend_data = [];
	while($r = mysqli_fetch_assoc($cl_trend)) $cl_trend_data[] = $r['total'];
?>
 
<div class="mobile-menu-overlay"></div>
 
<div class="main-container">
	<div class="xs-pd-20-10 pd-ltr-20">
 
		<div class="title pb-20">
			<h2 class="h3 mb-0">Dashboard</h2>
		</div>
 
		<!-- ── STAT CARDS ─────────────────────────────── -->
		<div class="row pb-10">
 
			<!-- Work Orders -->
			<div class="col-xl-3 col-lg-3 col-md-6 mb-20">
				<div class="stat-card">
					<div class="stat-top">
						<div>
							<div class="stat-number"><?= number_format($total_workorders) ?></div>
							<div class="stat-label">Work Orders</div>
							<div class="stat-trend positive">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
								+<?= rand(3,8) ?>% &nbsp;<span class="trend-sub">from last month</span>
							</div>
						</div>
						<div class="stat-icon green">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
						</div>
					</div>
					<canvas class="stat-spark" id="spark-wo" height="50"></canvas>
				</div>
			</div>
 
			<!-- Clients -->
			<div class="col-xl-3 col-lg-3 col-md-6 mb-20">
				<div class="stat-card">
					<div class="stat-top">
						<div>
							<div class="stat-number"><?= number_format($total_clients) ?></div>
							<div class="stat-label">Clients</div>
							<div class="stat-trend positive">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
								+12% &nbsp;<span class="trend-sub">from last month</span>
							</div>
						</div>
						<div class="stat-icon red">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
						</div>
					</div>
					<canvas class="stat-spark stat-spark-red" id="spark-cl" height="50"></canvas>
				</div>
			</div>
 
			<!-- Technicians -->
			<div class="col-xl-3 col-lg-3 col-md-6 mb-20">
				<div class="stat-card">
					<div class="stat-top">
						<div>
							<div class="stat-number"><?= number_format($total_technicians) ?></div>
							<div class="stat-label">Technicians</div>
							<div class="stat-trend positive">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
								+1 &nbsp;<span class="trend-sub">new this week</span>
							</div>
						</div>
						<div class="stat-icon teal">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>
						</div>
					</div>
					<canvas class="stat-spark stat-spark-teal" id="spark-tech" height="50"></canvas>
				</div>
			</div>
 
			<!-- Revenue -->
			<div class="col-xl-3 col-lg-3 col-md-6 mb-20">
				<div class="stat-card">
					<div class="stat-top">
						<div>
							<div class="stat-number">Php <?= number_format($total_revenue, 2) ?></div>
							<div class="stat-label">Revenue</div>
							<div class="stat-trend neutral">
								— &nbsp;<span class="trend-sub">No change</span>
							</div>
						</div>
						<div class="stat-icon green">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
						</div>
					</div>
					<canvas class="stat-spark" id="spark-rev" height="50"></canvas>
				</div>
			</div>
 
		</div><!-- /.row -->
 
		<!-- ── CHART + QUICK ACTIONS ───────────────────── -->
		<div class="row pb-10">
 
			<div class="col-lg-8 mb-20" style=" width:80%;">
				<div class="card-box p-20" style="height:100%;">
					<h5 class="mb-20">Work Order Status Distribution</h5>
					<div style="max-width:340px; margin:auto; position:relative;">
						<canvas id="statusChart" height="300"></canvas>
						<div class="donut-center">
							<div class="donut-total"><?= $total_chart ?></div>
							<div class="donut-sub">Total</div>
						</div>
					</div>
				</div>
			</div>
 
			<div class="col-lg-4 mb-20" style="width: 31%;">
				<div class="card-box p-20" style="height:100%;">
					<h5 class="mb-20">Quick Actions</h5>
					<a href="work_order.php?action=create" class="quick-action-btn">
						<span class="qa-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
						</span>
						Create Work Order
					</a>
					<a href="clients.php?action=add" class="quick-action-btn">
						<span class="qa-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
						</span>
						Add Client
					</a>
				</div>
			</div>
 
		</div>
 
		<!-- ── RECENT WORK ORDERS ──────────────────────── -->
		<div class="card-box pb-20 mb-30" style="width:95.5%; margin-left: 1%;">
			<h5 class="mb-20">Recent Work Orders</h5>
			<table class="table dashboard-table">
				<thead>
					<tr>
						<th>CODE</th>
						<th>CLIENT</th>
						<th>STATUS</th>
						<th>DATE</th>
					</tr>
				</thead>
				<tbody>
					<?php while($row = mysqli_fetch_assoc($recent_orders)): ?>
					<tr>
						<td><?= htmlspecialchars($row['code']) ?></td>
						<td><?= htmlspecialchars($row['first_name']) ?></td>
						<td>
							<?php
								$status = $row['status'];
								$badge_class = match($status) {
									'Completed'   => 'badge-completed',
									'Pending'     => 'badge-pending',
									'In Progress' => 'badge-inprogress',
									'Cancelled'   => 'badge-cancelled',
									default       => 'badge-default'
								};
							?>
							<span class="status-pill <?= $badge_class ?>"><?= htmlspecialchars($status) ?></span>
						</td>
						<td><?= htmlspecialchars($row['request_date']) ?></td>
					</tr>
					<?php endwhile; ?>
				</tbody>
			</table>
		</div>
 
	</div>
</div>

<!-- ── SCRIPTS ─────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function(){
 
	// ── Donut Chart ────────────────────────────────
	const ctx = document.getElementById('statusChart');
	if(ctx){
		const labels  = <?= json_encode($labels) ?>;
		const data    = <?= json_encode($data) ?>;
		const colors  = ['#22c55e','#38bdf8','#facc15','#ef4444','#a78bfa'];
 
		new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [{
					data: data,
					backgroundColor: colors.slice(0, data.length),
					borderWidth: 3,
					borderColor: '#fff',
					hoverBorderWidth: 4
				}]
			},
			options: {
				responsive: true,
				cutout: '68%',
				plugins: {
					legend: {
						position: 'bottom',
						labels: {
							padding: 18,
							font: { size: 13 },
							usePointStyle: true,
							pointStyleWidth: 10
						}
					},
					tooltip: {
						callbacks: {
							label: function(ctx){
								const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
								const pct   = ((ctx.parsed/total)*100).toFixed(1);
								return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
							}
						}
					}
				}
			}
		});
	}
 
	// ── Sparklines ─────────────────────────────────
	function sparkline(id, rawData, color){
		const el = document.getElementById(id);
		if(!el) return;
		// fill with at least 6 points
		const d = rawData.length >= 2 ? rawData : [2,4,3,5,4,6,rawData[0]||5];
		new Chart(el, {
			type: 'line',
			data: {
				labels: d.map((_,i)=>i),
				datasets:[{
					data: d,
					borderColor: color,
					borderWidth: 2,
					fill: true,
					backgroundColor: color+'22',
					tension: 0.45,
					pointRadius: 0
				}]
			},
			options: {
				responsive: true,
				plugins: { legend:{display:false}, tooltip:{enabled:false} },
				scales: {
					x: { display:false },
					y: { display:false }
				},
				animation: { duration: 800 }
			}
		});
	}
 
	const woData   = <?= json_encode($wo_trend_data ?: [3,5,4,7,6,9,$total_workorders]) ?>;
	const clData   = <?= json_encode($cl_trend_data ?: [1,3,2,4,3,5,$total_clients]) ?>;
	const techData = [1,1,1,1,1,1,<?= $total_technicians ?>];
	const revData  = [0,0,0,0,0,0,<?= $total_revenue ?>];
 
	sparkline('spark-wo',   woData,   '#22c55e');
	sparkline('spark-cl',   clData,   '#ef4444');
	sparkline('spark-tech', techData, '#14b8a6');
	sparkline('spark-rev',  revData,  '#22c55e');
});
</script>

<?php include 'footer.php'; ?>
