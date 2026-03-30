let currentPeriod = 'today';

function setPeriod(period) {
    currentPeriod = period;

    // Update button styles
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.classList.remove('bg-indigo-600', 'text-white');
        btn.classList.add('bg-gray-200', 'text-gray-700');
    });

    // Show/hide custom date range
    const customRange = document.getElementById('custom-date-range');
    if (period === 'custom') {
        customRange.classList.remove('hidden');
        customRange.classList.add('grid');
        document.getElementById('btn-custom').classList.remove('bg-gray-200', 'text-gray-700');
        document.getElementById('btn-custom').classList.add('bg-indigo-600', 'text-white');
    } else {
        customRange.classList.add('hidden');
        customRange.classList.remove('grid');
        document.getElementById('btn-' + period).classList.remove('bg-gray-200', 'text-gray-700');
        document.getElementById('btn-' + period).classList.add('bg-indigo-600', 'text-white');
    }
}

function exportReport(type) {
    let url = 'export_reports.php?type=' + type + '&period=' + currentPeriod;

    if (currentPeriod === 'custom') {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;

        if (!startDate || !endDate) {
            alert('Please select both start and end dates for custom range.');
            return;
        }

        if (new Date(startDate) > new Date(endDate)) {
            alert('Start date cannot be after end date.');
            return;
        }

        url += '&start_date=' + startDate + '&end_date=' + endDate;
    }

    // Open in new window to trigger download
    window.open(url, '_blank');
}

// Initialize with today selected
document.addEventListener('DOMContentLoaded', function() {
    setPeriod('today');
    loadProfit('today');       // Load profit summary on page load
    loadChart('30days');       // Load 30 days chart by default
});

// ── Profit Summary ────────────────────────────────────────────
let currentProfitPeriod = 'today';

function loadProfit(period) {
    currentProfitPeriod = period;

    // Update button styles
    document.querySelectorAll('.profit-period-btn').forEach(btn => {
        btn.classList.remove('bg-indigo-600', 'text-white');
        btn.classList.add('bg-gray-200', 'text-gray-700');
    });
    document.getElementById('profit-btn-' + period).classList.remove('bg-gray-200', 'text-gray-700');
    document.getElementById('profit-btn-' + period).classList.add('bg-indigo-600', 'text-white');

    // Show loading
    document.getElementById('profit-loading').classList.remove('hidden');

    fetch('api/analytics.php?period=' + period)
        .then(async (r) => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok || data.error) {
                throw new Error(data.error || ('HTTP ' + r.status));
            }
            return data;
        })
        .then(data => {
            const s = data.summary;
            const fmt = v => 'Rs ' + parseFloat(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('profit-total').textContent  = fmt(s.total_revenue);
            document.getElementById('profit-cash').textContent   = fmt(s.cash_revenue);
            document.getElementById('profit-online').textContent = fmt(s.online_revenue + s.card_revenue);
            document.getElementById('profit-loading').classList.add('hidden');
        })
        .catch((error) => {
            console.error('Error loading profit summary:', error);
            document.getElementById('profit-loading').classList.add('hidden');
        });
}

// Chart functionality
let analyticsChart = null;
let currentChartPeriod = '30days';

function loadChart(period) {
    currentChartPeriod = period;

    // Update button styles
    document.querySelectorAll('.chart-period-btn').forEach(btn => {
        btn.classList.remove('bg-indigo-600', 'text-white');
        btn.classList.add('bg-gray-200', 'text-gray-700');
    });

    // Show/hide custom date range
    const customRange = document.getElementById('chart-custom-range');
    if (period === 'custom') {
        customRange.classList.remove('hidden');
        customRange.classList.add('grid');
        document.getElementById('chart-btn-custom').classList.remove('bg-gray-200', 'text-gray-700');
        document.getElementById('chart-btn-custom').classList.add('bg-indigo-600', 'text-white');
    } else {
        customRange.classList.add('hidden');
        customRange.classList.remove('grid');
        document.getElementById('chart-btn-' + period).classList.remove('bg-gray-200', 'text-gray-700');
        document.getElementById('chart-btn-' + period).classList.add('bg-indigo-600', 'text-white');
    }

    // Show loading
    document.getElementById('chart-loading').classList.remove('hidden');
    document.getElementById('analyticsChart').style.display = 'none';

    // Build URL
    let url = 'api/analytics.php?period=' + period;
    if (period === 'custom') {
        const startDate = document.getElementById('chart_start_date').value;
        const endDate = document.getElementById('chart_end_date').value;
        if (startDate && endDate) {
            url += '&start_date=' + startDate + '&end_date=' + endDate;
        } else {
            document.getElementById('chart-loading').classList.add('hidden');
            document.getElementById('analyticsChart').style.display = 'block';
            alert('Please select both start and end dates.');
            return;
        }
    }

    // Fetch data
    fetch(url)
        .then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.error) {
                throw new Error(data.error || ('HTTP ' + response.status));
            }
            return data;
        })
        .then(data => {
            updateChart(data);
            updateSummary(data.summary);
            document.getElementById('chart-loading').classList.add('hidden');
            document.getElementById('analyticsChart').style.display = 'block';
        })
        .catch(error => {
            console.error('Error loading chart:', error);
            document.getElementById('chart-loading').classList.add('hidden');
            document.getElementById('analyticsChart').style.display = 'block';
            alert('Error loading analytics data: ' + (error?.message || 'Please try again.'));
        });
}

function updateChart(data) {
    const ctx = document.getElementById('analyticsChart').getContext('2d');

    if (analyticsChart) {
        analyticsChart.destroy();
    }

    analyticsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: data.datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 12,
                            weight: '500'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.dataset.label === 'Revenue (Rs)') {
                                label += 'Rs ' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            } else {
                                label += context.parsed.y;
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    ticks: {
                        precision: 0
                    },
                    title: {
                        display: true,
                        text: 'Orders & Bookings'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rs ' + value.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                        }
                    },
                    title: {
                        display: true,
                        text: 'Revenue (Rs)'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

function updateSummary(summary) {
    const summaryHtml = `
        <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-4 border border-blue-200">
            <p class="text-xs text-blue-600 font-medium uppercase mb-1">Total Orders</p>
            <p class="text-2xl font-bold text-blue-900">${summary.total_orders}</p>
            <p class="text-xs text-blue-600 mt-1">Avg: ${summary.avg_orders_per_day}/day</p>
        </div>
        <div class="bg-gradient-to-r from-green-50 to-green-100 rounded-lg p-4 border border-green-200">
            <p class="text-xs text-green-600 font-medium uppercase mb-1">Total Revenue</p>
            <p class="text-2xl font-bold text-green-900">Rs ${summary.total_revenue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
            <p class="text-xs text-green-600 mt-1">Avg: Rs ${summary.avg_revenue_per_day.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}/day</p>
        </div>
        <div class="bg-gradient-to-r from-pink-50 to-pink-100 rounded-lg p-4 border border-pink-200">
            <p class="text-xs text-pink-600 font-medium uppercase mb-1">Total Bookings</p>
            <p class="text-2xl font-bold text-pink-900">${summary.total_bookings}</p>
            <p class="text-xs text-pink-600 mt-1">Active days: ${summary.active_days}</p>
        </div>
        <div class="bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg p-4 border border-purple-200">
            <p class="text-xs text-purple-600 font-medium uppercase mb-1">Active Days</p>
            <p class="text-2xl font-bold text-purple-900">${summary.active_days}</p>
            <p class="text-xs text-purple-600 mt-1">Days with activity</p>
        </div>
    `;
    document.getElementById('chart-summary').innerHTML = summaryHtml;
}

function showCustomChartRange() {
    const r = document.getElementById('chart-custom-range');
    r.classList.remove('hidden');
    r.classList.add('grid');
    document.getElementById('chart-btn-custom').classList.remove('bg-gray-200', 'text-gray-700');
    document.getElementById('chart-btn-custom').classList.add('bg-indigo-600', 'text-white');
}
