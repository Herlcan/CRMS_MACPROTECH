document.addEventListener("DOMContentLoaded", function () {
	function parseJson(value, fallback) {
		try {
			return JSON.parse(value || "");
		} catch (error) {
			return fallback;
		}
	}

	function money(value) {
		return "Php " + Number(value || 0).toLocaleString(undefined, {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		});
	}

	const statusChart = document.getElementById("statusChart");

	if (statusChart) {
		const labels = parseJson(statusChart.dataset.labels, []);
		const data = parseJson(statusChart.dataset.data, []);
		const colors = ["#2563eb", "#f59e0b", "#22c55e", "#ef4444", "#14b8a6", "#8b5cf6", "#64748b"];

		new Chart(statusChart, {
			type: "doughnut",
			data: {
				labels: labels.length ? labels : ["No work orders"],
				datasets: [{
					data: data.length ? data : [1],
					backgroundColor: data.length ? colors.slice(0, data.length) : ["#e5e7eb"],
					borderWidth: 3,
					borderColor: "#fff",
					hoverBorderWidth: 4
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutout: "68%",
				plugins: {
					legend: {
						position: "bottom",
						labels: {
							padding: 18,
							font: { size: 13 },
							usePointStyle: true,
							pointStyleWidth: 10
						}
					},
					tooltip: {
						callbacks: {
							label: function (ctx) {
								if (!data.length) return " No work orders yet";

								const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
								const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : "0.0";
								return " " + ctx.label + ": " + ctx.parsed + " (" + pct + "%)";
							}
						}
					}
				}
			}
		});
	}

	function sparkline(id, rawData, color) {
		const el = document.getElementById(id);
		if (!el) return;

		const data = Array.isArray(rawData) && rawData.length >= 2 ? rawData : [0, 0, 0, 0, 0, 0];

		new Chart(el, {
			type: "line",
			data: {
				labels: data.map((_, i) => i),
				datasets: [{
					data: data,
					borderColor: color,
					borderWidth: 2,
					fill: true,
					backgroundColor: color + "22",
					tension: 0.45,
					pointRadius: 0
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false }, tooltip: { enabled: false } },
				scales: {
					x: { display: false },
					y: { display: false }
				},
				animation: { duration: 800 }
			}
		});
	}

	if (statusChart) {
		const woData = parseJson(statusChart.dataset.woTrend, [0, 0, 0, 0, 0, 0]);
		const lowStockData = parseJson(statusChart.dataset.lowStockTrend, [0, 0, 0, 0, 0, 0]);
		const openData = parseJson(statusChart.dataset.openTrend, [0, 0, 0, 0, 0, 0]);
		const revTotal = parseFloat(statusChart.dataset.revTotal || "0");
		const revSpark = [0, 0, 0, 0, 0, revTotal];

		sparkline("spark-wo", woData, "#22c55e");
		sparkline("spark-open", openData, "#f59e0b");
		sparkline("spark-rev", revSpark, "#14b8a6");
		sparkline("spark-cl", lowStockData, "#ef4444");
	}

	const monthlyChart = document.getElementById("monthlyChart");
	if (monthlyChart) {
		const labels = parseJson(monthlyChart.dataset.labels, []);
		const workorders = parseJson(monthlyChart.dataset.workorders, []);
		const revenue = parseJson(monthlyChart.dataset.revenue, []);

		new Chart(monthlyChart, {
			type: "bar",
			data: {
				labels: labels,
				datasets: [
					{
						type: "bar",
						label: "Work Orders",
						data: workorders,
						backgroundColor: "#2563eb",
						borderRadius: 6,
						yAxisID: "orders"
					},
					{
						type: "line",
						label: "Revenue",
						data: revenue,
						borderColor: "#22c55e",
						backgroundColor: "#22c55e22",
						tension: 0.35,
						pointRadius: 3,
						pointBackgroundColor: "#22c55e",
						yAxisID: "revenue"
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: "index", intersect: false },
				plugins: {
					legend: {
						position: "bottom",
						labels: { usePointStyle: true, padding: 18 }
					},
					tooltip: {
						callbacks: {
							label: function (ctx) {
								if (ctx.dataset.yAxisID === "revenue") {
									return " Revenue: " + money(ctx.parsed.y);
								}

								return " Work Orders: " + ctx.parsed.y;
							}
						}
					}
				},
				scales: {
					orders: {
						beginAtZero: true,
						ticks: { precision: 0 },
						grid: { color: "#f1f5f9" }
					},
					revenue: {
						beginAtZero: true,
						position: "right",
						grid: { drawOnChartArea: false },
						ticks: {
							callback: function (value) {
								return "Php " + Number(value || 0).toLocaleString();
							}
						}
					},
					x: {
						grid: { display: false }
					}
				}
			}
		});
	}
});
