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
    <title>Hotel Management System - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
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
                            <p class="text-4xl md:text-5xl font-bold text-white">$<?php echo number_format($profit_amount, 2); ?></p>
                            <p class="text-sm text-green-100 mt-2">From completed & paid orders</p>
                        </div>
                        <div class="text-6xl opacity-30">💰</div>
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
            <div class="bg-white rounded-xl shadow-md p-6 md:p-8">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-3">Welcome to Admin Dashboard</h2>
                <p class="text-gray-600 leading-relaxed">
                    Manage your hotel operations efficiently using the navigation menu. You can manage restaurant tables, 
                    normal rooms, bookings, and order details all from this centralized dashboard.
                </p>
            </div>
        </main>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>
