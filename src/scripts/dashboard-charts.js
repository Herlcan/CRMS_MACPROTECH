document.addEventListener("DOMContentLoaded", function(){

	// ── Donut Chart ────────────────────────────────
	const ctx = document.getElementById('statusChart');
	if(ctx){
		const labels  = JSON.parse(document.getElementById('statusChart').dataset.labels);
		const data    = JSON.parse(document.getElementById('statusChart').dataset.data);
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

	const woData   = JSON.parse(document.getElementById('statusChart').dataset.woTrend || '[3,5,4,7,6,9,0]');
	const clData   = JSON.parse(document.getElementById('statusChart').dataset.clTrend || '[1,3,2,4,3,5,0]');
	const techData = [1,1,1,1,1,1,parseInt(document.getElementById('statusChart').dataset.techTotal, 10) || 0];
	const revData  = [0,0,0,0,0,0,parseFloat(document.getElementById('statusChart').dataset.revTotal) || 0];

	sparkline('spark-wo',   woData,   '#22c55e');
	sparkline('spark-cl',   clData,   '#ef4444');
	sparkline('spark-tech', techData, '#14b8a6');
	sparkline('spark-rev',  revData,  '#22c55e');
});
