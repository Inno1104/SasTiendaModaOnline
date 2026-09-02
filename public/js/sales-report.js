document.addEventListener('DOMContentLoaded', () => {
    const node = document.getElementById('salesReportChartData');
    if (!node || typeof Chart === 'undefined') return;
    const data = JSON.parse(node.textContent);
    const colors = ['#e72d86', '#7651e5', '#2f80ed', '#18b9bd', '#f58a2a', '#11a96f'];
    const money = (value) => `${data.symbol} ${Number(value).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const daily = document.getElementById('salesDailyChart');
    if (daily) new Chart(daily, {
        type: 'line',
        data: { labels: data.daily.labels, datasets: [{ data: data.daily.values, borderColor: '#e72d86', backgroundColor: 'rgba(231,45,134,.10)', fill: true, tension: .36, pointRadius: 3, pointBackgroundColor: '#e72d86' }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => money(context.raw) } } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: money }, grid: { color: '#edf0f5' } } } }
    });

    const payment = document.getElementById('salesPaymentChart');
    if (payment) new Chart(payment, {
        type: 'doughnut',
        data: { labels: data.payments.labels, datasets: [{ data: data.payments.values, backgroundColor: colors, borderWidth: 0, hoverOffset: 5 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => `${context.label}: ${money(context.raw)}` } } } }
    });
});
