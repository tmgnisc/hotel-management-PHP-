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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Hotel Management System</h1>
            <div class="header-actions">
                <span class="admin-name">Admin</span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <nav class="dashboard-nav">
            <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>
            <ul class="nav-menu" id="navMenu">
                <li><a href="index.php" class="active">Dashboard</a></li>
                <li><a href="tables.php">Restaurant Tables</a></li>
                <li><a href="cabin_rooms.php">Cabin Rooms</a></li>
                <li><a href="normal_rooms.php">Normal Rooms</a></li>
                <li><a href="bookings.php">Bookings</a></li>
                <li><a href="order_details.php">Order Details</a></li>
            </ul>
        </nav>

        <main class="dashboard-main">
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>Total Tables</h3>
                    <?php
                    $conn = getDBConnection();
                    $result = $conn->query("SELECT COUNT(*) as total FROM tables");
                    $row = $result->fetch_assoc();
                    echo "<p class=\"stat-number\">" . $row['total'] . "</p>";
                    $conn->close();
                    ?>
                </div>
                <div class="stat-card">
                    <h3>Cabin Rooms</h3>
                    <?php
                    $conn = getDBConnection();
                    $result = $conn->query("SELECT COUNT(*) as total FROM cabin_rooms");
                    $row = $result->fetch_assoc();
                    echo "<p class=\"stat-number\">" . $row['total'] . "</p>";
                    $conn->close();
                    ?>
                </div>
                <div class="stat-card">
                    <h3>Normal Rooms</h3>
                    <?php
                    $conn = getDBConnection();
                    $result = $conn->query("SELECT COUNT(*) as total FROM normal_rooms");
                    $row = $result->fetch_assoc();
                    echo "<p class=\"stat-number\">" . $row['total'] . "</p>";
                    $conn->close();
                    ?>
                </div>
                <div class="stat-card">
                    <h3>Active Bookings</h3>
                    <?php
                    $conn = getDBConnection();
                    $result = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE status IN ('pending', 'confirmed', 'checked_in')");
                    $row = $result->fetch_assoc();
                    echo "<p class=\"stat-number\">" . $row['total'] . "</p>";
                    $conn->close();
                    ?>
                </div>
            </div>

            <div class="dashboard-content">
                <h2>Welcome to Admin Dashboard</h2>
                <p>Manage your hotel operations efficiently using the navigation menu above.</p>
            </div>
        </main>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>

