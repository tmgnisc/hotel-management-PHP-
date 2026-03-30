<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();

$period = $_GET['period'] ?? '30days';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$allowed_periods = ['5days', '10days', '15days', '30days', '40days', 'custom'];
if (!in_array($period, $allowed_periods, true)) {
    $period = '30days';
}

$today = new DateTime('today');
$filter_start = clone $today;
$filter_end = clone $today;

$days_map = [
    '5days' => 5,
    '10days' => 10,
    '15days' => 15,
    '30days' => 30,
    '40days' => 40,
];

if ($period === 'custom') {
    $custom_start = DateTime::createFromFormat('Y-m-d', $start_date);
    $custom_end = DateTime::createFromFormat('Y-m-d', $end_date);

    if ($custom_start && $custom_end) {
        if ($custom_start > $custom_end) {
            $temp = $custom_start;
            $custom_start = $custom_end;
            $custom_end = $temp;
        }
        $filter_start = $custom_start;
        $filter_end = $custom_end;
    } else {
        die('Please provide valid custom start and end dates.');
    }
} else {
    $days = $days_map[$period] ?? 30;
    $filter_start = clone $today;
    $filter_start->modify('-' . ($days - 1) . ' days');
    $filter_end = clone $today;
}

$filter_start_sql = $filter_start->format('Y-m-d');
$filter_end_sql = $filter_end->format('Y-m-d');

$stmt = $conn->prepare("SELECT item_name, quantity, rate, amount, purchase_date, supplier_name, remarks, created_at FROM purchase_details WHERE purchase_date BETWEEN ? AND ? ORDER BY purchase_date DESC, id DESC");
if (!$stmt) {
    die('Unable to prepare export query.');
}

$stmt->bind_param('ss', $filter_start_sql, $filter_end_sql);
$stmt->execute();
$result = $stmt->get_result();

$filename = 'Purchase_Details_' . $filter_start_sql . '_to_' . $filter_end_sql . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

fputcsv($output, [
    'S.N',
    'Item Name',
    'Quantity',
    'Rate',
    'Amount',
    'Purchase Date',
    'Supplier Name',
    'Remarks',
    'Created At'
]);

$sn = 1;
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $sn++,
        $row['item_name'],
        number_format((float)$row['quantity'], 2, '.', ''),
        number_format((float)$row['rate'], 2, '.', ''),
        number_format((float)$row['amount'], 2, '.', ''),
        $row['purchase_date'],
        $row['supplier_name'] ?? '',
        $row['remarks'] ?? '',
        $row['created_at']
    ]);
}

fclose($output);
$stmt->close();
$conn->close();
exit;
