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
                
                $result = $conn->query("SELECT COUNT(*) as total FROM order_details WHERE payment_status = 'pending'");
                $pending_orders = $result->fetch_assoc()['total'];
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
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-orange-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Pending Orders</p>
                            <p class="text-3xl font-bold text-orange-600 mt-2"><?php echo $pending_orders; ?></p>
                        </div>
                        <div class="text-4xl opacity-20">📋</div>
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
