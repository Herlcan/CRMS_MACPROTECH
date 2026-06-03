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

	const statusChart = document.getElementById("statusChart");

	if (statusChart) {
		const labels = parseJson(statusChart.dataset.labels, []);
		const data = parseJson(statusChart.dataset.data, []);
		const colors = labels.map(statusColor);

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

		sparkline("spark-wo", woData, palette.blue);
		sparkline("spark-open", openData, palette.blueDark);
		sparkline("spark-rev", revSpark, palette.blueBright);
		sparkline("spark-cl", lowStockData, palette.orange);
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
});
