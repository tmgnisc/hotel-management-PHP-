<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
$period = $_GET['period'] ?? '30days'; // 'today', '7days', '30days', 'custom'
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Calculate date range
$date_condition = '';
$date_range = [];

switch ($period) {
    case 'today':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        $date_condition = "DATE(order_date) = CURDATE()";
        break;
    case '7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
        $date_condition = "DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case '30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
        $date_condition = "DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $date_condition = "DATE(order_date) >= '" . $conn->real_escape_string($start_date) . "' AND DATE(order_date) <= '" . $conn->real_escape_string($end_date) . "'";
        } else {
            $date_condition = "DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $end_date = date('Y-m-d');
        }
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
        $date_condition = "DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

// Generate date range array
$start = new DateTime($start_date);
$end = new DateTime($end_date);
$interval = new DateInterval('P1D'); // 1 day interval
$dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));

$labels = [];
$ordersData = [];
$revenueData = [];
$bookingsData = [];

foreach ($dateRange as $date) {
    $dateStr = $date->format('Y-m-d');
    $labels[] = $date->format('M d');
    
    // Get orders count for this date
    $ordersQuery = "SELECT COUNT(*) as count FROM order_details WHERE DATE(order_date) = '" . $conn->real_escape_string($dateStr) . "'";
    $ordersResult = $conn->query($ordersQuery);
    $ordersCount = $ordersResult ? $ordersResult->fetch_assoc()['count'] : 0;
    $ordersData[] = (int)$ordersCount;
    
    // Get revenue for this date (completed and paid orders)
    $revenueQuery = "SELECT COALESCE(SUM(total_amount), 0) as total FROM order_details WHERE DATE(order_date) = '" . $conn->real_escape_string($dateStr) . "' AND order_status = 'completed' AND payment_status = 'paid'";
    $revenueResult = $conn->query($revenueQuery);
    $revenueAmount = $revenueResult ? $revenueResult->fetch_assoc()['total'] : 0;
    $revenueData[] = (float)$revenueAmount;
    
    // Get bookings count for this date (based on check-in date)
    $bookingsQuery = "SELECT COUNT(*) as count FROM bookings WHERE DATE(check_in_date) = '" . $conn->real_escape_string($dateStr) . "'";
    $bookingsResult = $conn->query($bookingsQuery);
    $bookingsCount = $bookingsResult ? $bookingsResult->fetch_assoc()['count'] : 0;
    $bookingsData[] = (int)$bookingsCount;
}

// Get summary statistics
$summaryQuery = "
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(CASE WHEN order_status = 'completed' AND payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as total_revenue,
        COUNT(DISTINCT DATE(order_date)) as active_days
    FROM order_details 
    WHERE " . $date_condition . "
";
$summaryResult = $conn->query($summaryQuery);
$summary = $summaryResult ? $summaryResult->fetch_assoc() : ['total_orders' => 0, 'total_revenue' => 0, 'active_days' => 0];

// Get bookings summary
$bookingsSummaryQuery = "
    SELECT COUNT(*) as total_bookings
    FROM bookings 
    WHERE DATE(check_in_date) >= '" . $conn->real_escape_string($start_date) . "' 
    AND DATE(check_in_date) <= '" . $conn->real_escape_string($end_date) . "'
";
$bookingsSummaryResult = $conn->query($bookingsSummaryQuery);
$bookingsSummary = $bookingsSummaryResult ? $bookingsSummaryResult->fetch_assoc() : ['total_bookings' => 0];

$conn->close();

header('Content-Type: application/json');
echo json_encode([
    'labels' => $labels,
    'datasets' => [
        [
            'label' => 'Orders',
            'data' => $ordersData,
            'borderColor' => 'rgb(99, 102, 241)',
            'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
            'tension' => 0.4
        ],
        [
            'label' => 'Revenue (Rs)',
            'data' => $revenueData,
            'borderColor' => 'rgb(34, 197, 94)',
            'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
            'tension' => 0.4,
            'yAxisID' => 'y1'
        ],
        [
            'label' => 'Bookings',
            'data' => $bookingsData,
            'borderColor' => 'rgb(236, 72, 153)',
            'backgroundColor' => 'rgba(236, 72, 153, 0.1)',
            'tension' => 0.4
        ]
    ],
    'summary' => [
        'total_orders' => (int)$summary['total_orders'],
        'total_revenue' => (float)$summary['total_revenue'],
        'total_bookings' => (int)$bookingsSummary['total_bookings'],
        'active_days' => (int)$summary['active_days'],
        'avg_orders_per_day' => $summary['active_days'] > 0 ? round($summary['total_orders'] / $summary['active_days'], 2) : 0,
        'avg_revenue_per_day' => $summary['active_days'] > 0 ? round($summary['total_revenue'] / $summary['active_days'], 2) : 0
    ]
]);
?>








