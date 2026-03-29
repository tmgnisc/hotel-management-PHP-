<?php
session_start();
require_once 'config/database.php';

// Simple authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>हाम्रो थकाली भान्छा घर - Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .devanagari-font {
            font-family: 'Noto Sans Devanagari', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <?php include 'includes/nav.php'; ?>

        <main class="md:ml-64 p-4 md:p-6 lg:p-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
                <?php
                $conn = getDBConnection();
                $result = $conn->query("SELECT COUNT(*) as total FROM tables");
                $tables_count = $result->fetch_assoc()['total'];
                
                $result = $conn->query("SELECT COUNT(*) as total FROM normal_rooms");
                $normal_count = $result->fetch_assoc()['total'];
                
                $result = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE status IN ('pending', 'confirmed', 'checked_in')");
                $bookings_count = $result->fetch_assoc()['total'];
                
                // Completed orders count
                $result = $conn->query("SELECT COUNT(*) as total FROM order_details WHERE order_status = 'completed'");
                $completed_orders = $result->fetch_assoc()['total'];
                
                // Profit amount - total of completed and paid orders
                $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM order_details WHERE order_status = 'completed' AND payment_status = 'paid'");
                $profit_amount = $result->fetch_assoc()['total'];
                $conn->close();
                ?>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-indigo-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Total Tables</p>
                            <p class="text-3xl font-bold text-indigo-600 mt-2"><?php echo $tables_count; ?></p>
                        </div>
                        <div class="text-4xl opacity-20"></div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-pink-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Normal Rooms</p>
                            <p class="text-3xl font-bold text-pink-600 mt-2"><?php echo $normal_count; ?></p>
                        </div>
                        <div class="text-4xl opacity-20"></div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Active Bookings</p>
                            <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $bookings_count; ?></p>
                        </div>
                        <div class="text-4xl opacity-20"></div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Completed Orders</p>
                            <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $completed_orders; ?></p>
                        </div>
                        <div class="text-4xl opacity-20"></div>
                    </div>
                </div>
            </div>

            <!-- Profit Summary Section -->
            <div class="bg-white rounded-xl shadow-md p-6 md:p-8 mb-6 md:mb-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-5">
                    <div>
                        <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-1">💰 Profit Summary</h3>
                        <p class="text-gray-500 text-sm">Total, cash & online profit from completed paid orders</p>
                    </div>
                    <!-- Period Selector -->
                    <div class="mt-4 md:mt-0 flex gap-2 flex-wrap">
                        <button onclick="loadProfit('today')" id="profit-btn-today" class="profit-period-btn px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium text-sm transition-all">
                            Today
                        </button>
                        <button onclick="loadProfit('yesterday')" id="profit-btn-yesterday" class="profit-period-btn px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium text-sm transition-all">
                            Yesterday
                        </button>
                        <button onclick="loadProfit('7days')" id="profit-btn-7days" class="profit-period-btn px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium text-sm transition-all">
                            Last 7 Days
                        </button>
                        <button onclick="loadProfit('30days')" id="profit-btn-30days" class="profit-period-btn px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium text-sm transition-all">
                            Last 30 Days
                        </button>
                    </div>
                </div>

                <!-- Profit Cards Row -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" id="profit-cards">
                    <!-- Total Profit -->
                    <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-6 text-white shadow-lg">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm font-semibold text-green-100 uppercase tracking-wide">Total Profit</p>
                            <span class="text-3xl opacity-30">💰</span>
                        </div>
                        <p id="profit-total" class="text-3xl md:text-4xl font-bold">Rs 0.00</p>
                        <p class="text-xs text-green-100 mt-2">All payment methods combined</p>
                    </div>

                    <!-- Cash Profit -->
                    <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl p-6 text-white shadow-lg">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm font-semibold text-blue-100 uppercase tracking-wide">Cash Profit</p>
                            <span class="text-3xl opacity-30">🪙</span>
                        </div>
                        <p id="profit-cash" class="text-3xl md:text-4xl font-bold">Rs 0.00</p>
                        <p class="text-xs text-blue-100 mt-2">Paid by cash</p>
                    </div>

                    <!-- Online Profit -->
                    <div class="bg-gradient-to-br from-purple-500 to-violet-700 rounded-xl p-6 text-white shadow-lg">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm font-semibold text-purple-100 uppercase tracking-wide">Online / Card Profit</p>
                            <span class="text-3xl opacity-30">💳</span>
                        </div>
                        <p id="profit-online" class="text-3xl md:text-4xl font-bold">Rs 0.00</p>
                        <p class="text-xs text-purple-100 mt-2">Paid online or by card</p>
                    </div>
                </div>

                <!-- Loading spinner -->
                <div id="profit-loading" class="hidden text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                    <span class="ml-2 text-gray-500 text-sm">Loading...</span>
                </div>
            </div>
                
            <!-- Reports & Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-6 md:mb-8">
                <!-- Reports Export Section -->
                <div class="bg-white rounded-xl shadow-md p-6 md:p-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">📊 Download Reports</h3>
                    
                    <!-- Date Filter -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Select Date Range</label>
                        <div class="flex flex-wrap gap-3 mb-4">
                            <button onclick="setPeriod('today')" id="btn-today" class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-all text-sm period-btn">
                                Today
                            </button>
                            <button onclick="setPeriod('yesterday')" id="btn-yesterday" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-all text-sm period-btn">
                                Yesterday
                            </button>
                            <button onclick="setPeriod('7days')" id="btn-7days" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-all text-sm period-btn">
                                Last 7 Days
                            </button>
                            <button onclick="setPeriod('30days')" id="btn-30days" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-all text-sm period-btn">
                                Last 30 Days
                            </button>
                            <button onclick="setPeriod('custom')" id="btn-custom" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-all text-sm period-btn">
                                Custom Range
                            </button>
                        </div>
                        
                        <!-- Custom Date Range -->
                        <div id="custom-date-range" class="hidden grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                                <input type="date" id="start_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                                <input type="date" id="end_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Export Buttons -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <button onclick="exportReport('orders')" class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg font-semibold hover:from-green-700 hover:to-green-800 transition-all shadow-lg hover:shadow-xl flex items-center justify-center gap-2">
                            <span>📋</span>
                            <span>Export Order Details (Excel)</span>
                        </button>
                        <button onclick="exportReport('bookings')" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg font-semibold hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg hover:shadow-xl flex items-center justify-center gap-2">
                            <span>📅</span>
                            <span>Export Booking Details (Excel)</span>
                        </button>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 md:p-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <a href="order_details.php" class="px-4 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all text-center text-sm">
                            View Orders
                        </a>
                        <a href="bookings.php" class="px-4 py-3 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition-all text-center text-sm">
                            View Bookings
                        </a>
                        <a href="menu.php" class="px-4 py-3 bg-purple-600 text-white rounded-lg font-semibold hover:bg-purple-700 transition-all text-center text-sm">
                            Manage Menu
                        </a>
                        <a href="normal_rooms.php" class="px-4 py-3 bg-pink-600 text-white rounded-lg font-semibold hover:bg-pink-700 transition-all text-center text-sm">
                            Manage Rooms
                        </a>
                    </div>
                </div>
            </div>

            <!-- Analytics Chart Section -->
            <div class="bg-white rounded-xl shadow-md p-6 md:p-8 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                    <div>
                        <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">📈 Analytics Dashboard</h3>
                        <p class="text-gray-600 text-sm">Track orders, revenue, and bookings over time</p>
                    </div>
                    <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                        <button onclick="loadChart('today')" id="chart-btn-today" class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-all text-xs chart-period-btn">
                            Today
                        </button>
                        <button onclick="loadChart('yesterday')" id="chart-btn-yesterday" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-all text-xs chart-period-btn">
                            Yesterday
                        </button>
                        <button onclick="loadChart('7days')" id="chart-btn-7days" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-all text-xs chart-period-btn">
                            7 Days
                        </button>
                        <button onclick="loadChart('30days')" id="chart-btn-30days" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-all text-xs chart-period-btn">
                            30 Days
                        </button>
                        <button onclick="showCustomChartRange()" id="chart-btn-custom" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-all text-xs chart-period-btn">
                            Custom
                        </button>
                    </div>
                </div>
                
                <!-- Custom Date Range for Chart -->
                <div id="chart-custom-range" class="hidden grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                        <input type="date" id="chart_start_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                        <div class="flex gap-2">
                            <input type="date" id="chart_end_date" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            <button onclick="loadChart('custom')" class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-all">
                                Apply
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Chart Summary Cards -->
                <div id="chart-summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <!-- Summary cards will be populated by JavaScript -->
                </div>
                
                <!-- Chart Container -->
                <div class="relative h-64 md:h-96">
                    <canvas id="analyticsChart"></canvas>
                </div>
                
                <!-- Loading Indicator -->
                <div id="chart-loading" class="hidden text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    <p class="mt-2 text-gray-600">Loading analytics...</p>
                </div>
            </div>

            <!-- Welcome Card -->
            <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl shadow-md p-6 md:p-8 border-l-4 border-indigo-600">
                <h2 class="text-3xl md:text-4xl font-bold text-indigo-600 mb-2 devanagari-font">
                    हाम्रो थकाली भान्छा घर
                </h2>
                <h3 class="text-xl md:text-2xl font-semibold text-gray-800 mb-4">Welcome to Admin Dashboard</h3>
                <p class="text-gray-600 leading-relaxed">
                    Manage your hotel operations efficiently using the navigation menu. You can manage restaurant tables, 
                    normal rooms, bookings, menu items, and order details all from this centralized dashboard.
                </p>
            </div>
        </main>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
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
                .then(r => r.json())
                .then(data => {
                    const s = data.summary;
                    const fmt = v => 'Rs ' + parseFloat(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    document.getElementById('profit-total').textContent  = fmt(s.total_revenue);
                    document.getElementById('profit-cash').textContent   = fmt(s.cash_revenue);
                    document.getElementById('profit-online').textContent = fmt(s.online_revenue + s.card_revenue);
                    document.getElementById('profit-loading').classList.add('hidden');
                })
                .catch(() => {
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
                .then(response => response.json())
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
                    alert('Error loading analytics data. Please try again.');
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
    </script>
</body>
</html>