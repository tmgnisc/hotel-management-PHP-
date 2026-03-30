<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
	http_response_code(401);
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

$conn = getDBConnection();
if (!$conn) {
	http_response_code(500);
	echo json_encode(['error' => 'Database connection failed']);
	exit;
}

$period = $_GET['period'] ?? '30days'; // 'today', 'yesterday', '7days', '30days', 'custom'
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Calculate date range
$date_condition = '';

switch ($period) {
	case 'today':
		$start_date = date('Y-m-d');
		$end_date = date('Y-m-d');
		$date_condition = "DATE(order_date) = CURDATE()";
		break;
	case 'yesterday':
		$start_date = date('Y-m-d', strtotime('-1 day'));
		$end_date = date('Y-m-d', strtotime('-1 day'));
		$date_condition = "DATE(order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
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
			$safeStart = $conn->real_escape_string($start_date);
			$safeEnd = $conn->real_escape_string($end_date);
			$date_condition = "DATE(order_date) >= '" . $safeStart . "' AND DATE(order_date) <= '" . $safeEnd . "'";
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

try {
	$start = new DateTime($start_date);
	$end = new DateTime($end_date);
} catch (Throwable $e) {
	$start = new DateTime(date('Y-m-d', strtotime('-30 days')));
	$end = new DateTime(date('Y-m-d'));
}

$interval = new DateInterval('P1D');
$dateRange = new DatePeriod($start, $interval, (clone $end)->modify('+1 day'));

$labels = [];
$ordersData = [];
$revenueData = [];
$bookingsData = [];

foreach ($dateRange as $date) {
	$dateStr = $date->format('Y-m-d');
	$safeDate = $conn->real_escape_string($dateStr);
	$labels[] = $date->format('M d');

	$ordersQuery = "SELECT COUNT(*) as count FROM order_details WHERE DATE(order_date) = '" . $safeDate . "'";
	$ordersResult = $conn->query($ordersQuery);
	$ordersCount = $ordersResult ? $ordersResult->fetch_assoc()['count'] : 0;
	$ordersData[] = (int)$ordersCount;

	$revenueQuery = "SELECT COALESCE(SUM(total_amount), 0) as total FROM order_details WHERE DATE(order_date) = '" . $safeDate . "' AND order_status = 'completed' AND payment_status = 'paid'";
	$revenueResult = $conn->query($revenueQuery);
	$revenueAmount = $revenueResult ? $revenueResult->fetch_assoc()['total'] : 0;
	$revenueData[] = (float)$revenueAmount;

	$bookingsQuery = "SELECT COUNT(*) as count FROM bookings WHERE DATE(check_in_date) = '" . $safeDate . "'";
	$bookingsResult = $conn->query($bookingsQuery);
	$bookingsCount = $bookingsResult ? $bookingsResult->fetch_assoc()['count'] : 0;
	$bookingsData[] = (int)$bookingsCount;
}

$summaryQuery = "
	SELECT
		COUNT(*) as total_orders,
		COALESCE(SUM(CASE WHEN order_status = 'completed' AND payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as total_revenue,
		COALESCE(SUM(CASE WHEN order_status = 'completed' AND payment_status = 'paid' AND payment_method = 'cash'   THEN total_amount ELSE 0 END), 0) as cash_revenue,
		COALESCE(SUM(CASE WHEN order_status = 'completed' AND payment_status = 'paid' AND payment_method = 'online' THEN total_amount ELSE 0 END), 0) as online_revenue,
		COALESCE(SUM(CASE WHEN order_status = 'completed' AND payment_status = 'paid' AND payment_method = 'card'   THEN total_amount ELSE 0 END), 0) as card_revenue,
		COUNT(DISTINCT DATE(order_date)) as active_days
	FROM order_details
	WHERE " . $date_condition;

$summaryResult = $conn->query($summaryQuery);
$summary = $summaryResult
	? $summaryResult->fetch_assoc()
	: ['total_orders' => 0, 'total_revenue' => 0, 'cash_revenue' => 0, 'online_revenue' => 0, 'card_revenue' => 0, 'active_days' => 0];

$safeStartDate = $conn->real_escape_string($start_date);
$safeEndDate = $conn->real_escape_string($end_date);
$bookingsSummaryQuery = "
	SELECT COUNT(*) as total_bookings
	FROM bookings
	WHERE DATE(check_in_date) >= '" . $safeStartDate . "'
	  AND DATE(check_in_date) <= '" . $safeEndDate . "'";

$bookingsSummaryResult = $conn->query($bookingsSummaryQuery);
$bookingsSummary = $bookingsSummaryResult ? $bookingsSummaryResult->fetch_assoc() : ['total_bookings' => 0];

$conn->close();

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
		'total_orders'        => (int)$summary['total_orders'],
		'total_revenue'       => (float)$summary['total_revenue'],
		'cash_revenue'        => (float)$summary['cash_revenue'],
		'online_revenue'      => (float)$summary['online_revenue'],
		'card_revenue'        => (float)$summary['card_revenue'],
		'total_bookings'      => (int)$bookingsSummary['total_bookings'],
		'active_days'         => (int)$summary['active_days'],
		'avg_orders_per_day'  => ((int)$summary['active_days'] > 0) ? round(((int)$summary['total_orders']) / ((int)$summary['active_days']), 2) : 0,
		'avg_revenue_per_day' => ((int)$summary['active_days'] > 0) ? round(((float)$summary['total_revenue']) / ((int)$summary['active_days']), 2) : 0
	]
]);
exit;
