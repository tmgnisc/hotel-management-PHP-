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
                        <div class="text-4xl opacity-20">🍽️</div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-pink-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Normal Rooms</p>
                            <p class="text-3xl font-bold text-pink-600 mt-2"><?php echo $normal_count; ?></p>
                        </div>
                        <div class="text-4xl opacity-20">🛏️</div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Active Bookings</p>
                            <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $bookings_count; ?></p>
                        </div>
                        <div class="text-4xl opacity-20">📅</div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Completed Orders</p>
                            <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $completed_orders; ?></p>
                        </div>
                        <div class="text-4xl opacity-20">✅</div>
                    </div>
                </div>
            </div>

            <!-- Profit Card -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-6 md:mb-8">
                <div class="bg-gradient-to-r from-green-500 to-emerald-600 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 p-6 md:p-8 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-green-100 uppercase tracking-wide mb-2">Total Profit</p>
                            <p class="text-4xl md:text-5xl font-bold text-white">Rs <?php echo number_format($profit_amount, 2); ?></p>
                            <p class="text-sm text-green-100 mt-2">From completed & paid orders</p>
                        </div>
                        <div class="text-6xl opacity-30">💰</div>
                    </div>
                </div>
                
                <!-- Reports Export Section -->
                <div class="bg-white rounded-xl shadow-md p-6 md:p-8 mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">📊 Download Reports</h3>
                    
                    <!-- Date Filter -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Select Date Range</label>
                        <div class="flex flex-wrap gap-3 mb-4">
                            <button onclick="setPeriod('today')" id="btn-today" class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-all text-sm period-btn">
                                Today
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
                        <div id="custom-date-range" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4">
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
                            <span>Export Order Details</span>
                        </button>
                        <button onclick="exportReport('bookings')" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg font-semibold hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg hover:shadow-xl flex items-center justify-center gap-2">
                            <span>📅</span>
                            <span>Export Booking Details</span>
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
        });
    </script>
</body>
</html>
