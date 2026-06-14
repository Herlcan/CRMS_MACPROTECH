(function () {
	function initDashboardCharts() {
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

	function escapeHtml(value) {
		return String(value || "").replace(/[&<>"']/g, function (char) {
			return {
				"&": "&amp;",
				"<": "&lt;",
				">": "&gt;",
				'"': "&quot;",
				"'": "&#039;"
			}[char];
		});
	}

	const palette = {
		blue: "#0036bf",
		blueBright: "#0050ff",
		blueDark: "#002a96",
		blueSoft: "#e6ecff",
		red: "#e80409",
		orange: "#f97316",
		orangeDark: "#c2410c",
		orangeSoft: "#ffedd5",
		statusPending: "#1e3a8a",
		statusDiagnosing: "#1d4ed8",
		statusWaiting: "#2563eb",
		statusInProgress: "#60a5fa",
		statusRepaired: "#bfdbfe",
		statusReleased: "#e0f2fe",
		ink: "#111214",
		inkSoft: "#d6d8dc",
		white: "#ffffff"
	};

	function statusColor(label) {
		const status = String(label || "").toLowerCase();

		if (status === "cancelled" || status === "canceled") return palette.red;
		if (status === "pending") return palette.statusPending;
		if (status === "diagnosing") return palette.statusDiagnosing;
		if (status === "waiting for parts") return palette.statusWaiting;
		if (status === "in progress") return palette.statusInProgress;
		if (status === "repaired" || status === "ready for release") return palette.statusRepaired;
		if (status === "released") return palette.statusReleased;

		return palette.ink;
	}

	function renderStatusBreakdown(container, labels, data, colors) {
		if (!container) return;

		const total = data.reduce(function (sum, value) {
			return sum + Number(value || 0);
		}, 0);

		if (!labels.length || !data.length || total <= 0) {
			container.innerHTML = '<div class="dashboard-empty">No work orders yet.</div>';
			return;
		}

		container.innerHTML = labels.map(function (label, index) {
			const count = Number(data[index] || 0);
			const percent = total > 0 ? ((count / total) * 100).toFixed(1) : "0.0";
			const color = colors[index] || palette.ink;

			return [
				'<div class="status-breakdown-item">',
				'  <span class="status-breakdown-dot" style="background-color: ' + color + '"></span>',
				'  <span class="status-breakdown-main">',
				'    <span class="status-breakdown-label">' + escapeHtml(label) + ' (' + count.toLocaleString() + ')</span>',
				'    <span class="status-breakdown-bar"><span style="width: ' + percent + '%; background-color: ' + color + '"></span></span>',
				'  </span>',
				'  <span class="status-breakdown-percent">' + percent + '%</span>',
				'</div>'
			].join('');
		}).join('');
	}

	const statusChart = document.getElementById("statusChart");

	if (statusChart && statusChart.dataset.macproDashboardChartReady !== "true") {
		statusChart.dataset.macproDashboardChartReady = "true";
		const labels = parseJson(statusChart.dataset.labels, []);
		const data = parseJson(statusChart.dataset.data, []);
		const colors = labels.map(statusColor);
		const statusBreakdown = document.getElementById("statusBreakdown");

		renderStatusBreakdown(statusBreakdown, labels, data, colors);

		new Chart(statusChart, {
			type: "doughnut",
			data: {
				labels: labels.length ? labels : ["No work orders"],
				datasets: [{
					data: data.length ? data : [1],
					backgroundColor: data.length ? colors : [palette.inkSoft],
					borderWidth: 3,
					borderColor: palette.white,
					hoverBorderWidth: 4
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutout: "68%",
				plugins: {
					legend: {
						display: false
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

	if (statusChart && statusChart.dataset.macproDashboardSparklinesReady !== "true") {
		statusChart.dataset.macproDashboardSparklinesReady = "true";
		const woData = parseJson(statusChart.dataset.woTrend, [0, 0, 0, 0, 0, 0]);
		const lowStockData = parseJson(statusChart.dataset.lowStockTrend, [0, 0, 0, 0, 0, 0]);
		const openData = parseJson(statusChart.dataset.openTrend, [0, 0, 0, 0, 0, 0]);
		const revenueData = parseJson(statusChart.dataset.revenueTrend, [0, 0, 0, 0, 0, 0]);

		sparkline("spark-wo", woData, palette.blue);
		sparkline("spark-open", openData, palette.blueDark);
		sparkline("spark-rev", revenueData, palette.blueBright);
		sparkline("spark-cl", lowStockData, palette.orange);
	}

	const monthlyChart = document.getElementById("monthlyChart");
	if (monthlyChart && monthlyChart.dataset.macproDashboardChartReady !== "true") {
		monthlyChart.dataset.macproDashboardChartReady = "true";
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
						backgroundColor: palette.blue,
						borderRadius: 6,
						yAxisID: "orders"
					},
					{
						type: "line",
						label: "Revenue",
						data: revenue,
						borderColor: palette.orange,
						backgroundColor: palette.orange + "22",
						tension: 0.35,
						pointRadius: 3,
						pointBackgroundColor: palette.orange,
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
						grid: { color: "#edeef0" }
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
	}

	window.MacproDashboardCharts = {
		init: initDashboardCharts
	};

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initDashboardCharts);
	} else {
		initDashboardCharts();
	}

	document.addEventListener("macpro:content-ready", initDashboardCharts);
})();
