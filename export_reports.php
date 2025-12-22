<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$type = $_GET['type'] ?? ''; // 'orders' or 'bookings'
$period = $_GET['period'] ?? 'today'; // 'today', '7days', '30days', 'custom'
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Calculate date range based on period
$date_condition = '';
$date_range_text = '';

switch ($period) {
    case 'today':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        $date_condition = "DATE(order_date) = CURDATE()";
        $date_range_text = date('Y-m-d');
        break;
    case '7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
        $date_condition = "DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $date_range_text = $start_date . ' to ' . $end_date;
        break;
    case '30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
        $date_condition = "DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $date_range_text = $start_date . ' to ' . $end_date;
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $date_condition = "DATE(order_date) >= '" . $conn->real_escape_string($start_date) . "' AND DATE(order_date) <= '" . $conn->real_escape_string($end_date) . "'";
            $date_range_text = $start_date . ' to ' . $end_date;
        } else {
            die("Please provide both start and end dates for custom range.");
        }
        break;
    default:
        $date_condition = "DATE(order_date) = CURDATE()";
        $date_range_text = date('Y-m-d');
}

if ($type === 'orders') {
    // Export Order Details
    $query = "
        SELECT 
            o.id,
            o.order_number,
            t.table_number,
            o.order_date,
            o.items,
            o.subtotal,
            o.tax,
            o.discount,
            o.total_amount,
            o.order_status,
            o.payment_status,
            o.payment_method,
            o.notes,
            o.created_at
        FROM order_details o 
        LEFT JOIN tables t ON o.table_id = t.id 
        WHERE " . $date_condition . "
        ORDER BY o.order_date DESC
    ";
    
    $result = $conn->query($query);
    if (!$result) {
        die("Error fetching orders: " . $conn->error);
    }
    
    // Set headers for Excel download
    $filename = 'Order_Details_' . str_replace(' ', '_', $date_range_text) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Add BOM for UTF-8 to ensure Excel displays correctly
    echo "\xEF\xBB\xBF";
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add header row
    fputcsv($output, [
        'ID',
        'Order Number',
        'Table Number',
        'Order Date',
        'Items',
        'Subtotal',
        'Tax',
        'Discount',
        'Total Amount',
        'Order Status',
        'Payment Status',
        'Payment Method',
        'Notes',
        'Created At'
    ]);
    
    // Add data rows
    while ($row = $result->fetch_assoc()) {
        // Decode items JSON for better readability
        $items = json_decode($row['items'], true);
        $items_text = '';
        if (is_array($items)) {
            $item_parts = [];
            foreach ($items as $item) {
                $item_parts[] = $item['name'] . ' (Qty: ' . $item['quantity'] . ', Price: Rs ' . number_format($item['price'], 2) . ')';
            }
            $items_text = implode('; ', $item_parts);
        } else {
            $items_text = $row['items'];
        }
        
        fputcsv($output, [
            $row['id'],
            $row['order_number'],
            $row['table_number'] ?? 'N/A',
            $row['order_date'] ? date('Y-m-d H:i:s', strtotime($row['order_date'])) : 'N/A',
            $items_text,
            number_format($row['subtotal'], 2),
            number_format($row['tax'], 2),
            number_format($row['discount'], 2),
            number_format($row['total_amount'], 2),
            $row['order_status'],
            $row['payment_status'],
            $row['payment_method'],
            $row['notes'] ?? '',
            $row['created_at'] ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : 'N/A'
        ]);
    }
    
    fclose($output);
    
} elseif ($type === 'bookings') {
    // Export Booking Details - recalculate date condition for bookings
    $booking_date_condition = '';
    switch ($period) {
        case 'today':
            $booking_date_condition = "DATE(check_in_date) = CURDATE()";
            break;
        case '7days':
            $booking_date_condition = "DATE(check_in_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case '30days':
            $booking_date_condition = "DATE(check_in_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        case 'custom':
            if (!empty($start_date) && !empty($end_date)) {
                $booking_date_condition = "DATE(check_in_date) >= '" . $conn->real_escape_string($start_date) . "' AND DATE(check_in_date) <= '" . $conn->real_escape_string($end_date) . "'";
            }
            break;
        default:
            $booking_date_condition = "DATE(check_in_date) = CURDATE()";
    }
    
    $query = "
        SELECT 
            b.id,
            b.booking_reference,
            b.customer_name,
            b.customer_email,
            b.customer_phone,
            nr.room_number,
            nr.room_type,
            nr.capacity_type,
            b.check_in_date,
            b.check_out_date,
            b.number_of_guests,
            b.total_amount,
            b.status,
            b.special_requests,
            b.created_at
        FROM bookings b 
        LEFT JOIN normal_rooms nr ON b.room_id = nr.id 
        WHERE " . $booking_date_condition . "
        ORDER BY b.check_in_date DESC
    ";
    
    $result = $conn->query($query);
    if (!$result) {
        die("Error fetching bookings: " . $conn->error);
    }
    
    // Set headers for Excel download
    $filename = 'Booking_Details_' . str_replace(' ', '_', $date_range_text) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Add BOM for UTF-8 to ensure Excel displays correctly
    echo "\xEF\xBB\xBF";
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add header row
    fputcsv($output, [
        'ID',
        'Booking Reference',
        'Customer Name',
        'Customer Email',
        'Customer Phone',
        'Room Number',
        'Room Type',
        'Capacity Type',
        'Check-in Date',
        'Check-out Date',
        'Number of Guests',
        'Total Amount',
        'Status',
        'Special Requests',
        'Created At'
    ]);
    
    // Add data rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['booking_reference'],
            $row['customer_name'] ?? 'N/A',
            $row['customer_email'] ?? 'N/A',
            $row['customer_phone'] ?? 'N/A',
            $row['room_number'] ?? 'N/A',
            $row['room_type'] ?? 'N/A',
            $row['capacity_type'] ?? 'N/A',
            $row['check_in_date'] ? date('Y-m-d', strtotime($row['check_in_date'])) : 'N/A',
            $row['check_out_date'] ? date('Y-m-d', strtotime($row['check_out_date'])) : 'N/A',
            $row['number_of_guests'],
            number_format($row['total_amount'], 2),
            $row['status'],
            $row['special_requests'] ?? '',
            $row['created_at'] ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : 'N/A'
        ]);
    }
    
    fclose($output);
} else {
    die("Invalid export type. Please specify 'orders' or 'bookings'.");
}

$conn->close();
exit;
?>

