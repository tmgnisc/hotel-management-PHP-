<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($orderId <= 0) {
    die('Invalid order ID');
}

$stmt = $conn->prepare("
    SELECT o.*, t.table_number
    FROM order_details o
    LEFT JOIN tables t ON o.table_id = t.id
    WHERE o.id = ?
");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$order) {
    die('Order not found');
}

$items = [];
if (!empty($order['items'])) {
    $decoded = json_decode($order['items'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $items = $decoded;
    }
}

$companyName = 'हाम्रो थकाली भान्छा घर';
$today = date('M d, Y h:i A');
$orderDate = $order['order_date'] ? date('M d, Y h:i A', strtotime($order['order_date'])) : 'N/A';
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += isset($item['total']) ? (float)$item['total'] : 0;
}
$total = isset($order['total_amount']) ? (float)$order['total_amount'] : $subtotal;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Bill - <?php echo htmlspecialchars($order['order_number']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none; }
            body { background: #fff; }
        }
        .devanagari-font {
            font-family: 'Noto Sans Devanagari', sans-serif;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@500;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="max-w-3xl mx-auto my-6 bg-white shadow-lg rounded-xl overflow-hidden">
        <div class="px-6 py-5 bg-gray-900 text-white flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold devanagari-font"><?php echo htmlspecialchars($companyName); ?></h1>
                <p class="text-sm text-gray-200">Order Bill / Tax Invoice</p>
            </div>
            <div class="text-right text-sm">
                <p class="font-semibold">Generated: <?php echo $today; ?></p>
                <p>Bill No: <?php echo htmlspecialchars($order['order_number']); ?></p>
            </div>
        </div>

        <div class="px-6 py-5 space-y-3 text-sm text-gray-800">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p><span class="font-semibold">Order Date:</span> <?php echo $orderDate; ?></p>
                    <p><span class="font-semibold">Table:</span> <?php echo htmlspecialchars($order['table_number'] ?? 'N/A'); ?></p>
                    <p><span class="font-semibold">Payment:</span> <?php echo htmlspecialchars($order['payment_status'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?>)</p>
                </div>
                <div>
                    <p><span class="font-semibold">Order Status:</span> <?php echo htmlspecialchars($order['order_status'] ?? 'N/A'); ?></p>
                    <p><span class="font-semibold">Notes:</span> <?php echo htmlspecialchars($order['notes'] ?? '-'); ?></p>
                </div>
            </div>
        </div>

        <div class="px-6 pb-6">
            <table class="w-full text-sm border border-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-3 px-4 text-left font-semibold text-gray-700 border-b">Item</th>
                        <th class="py-3 px-4 text-right font-semibold text-gray-700 border-b">Qty</th>
                        <th class="py-3 px-4 text-right font-semibold text-gray-700 border-b">Rate (Rs)</th>
                        <th class="py-3 px-4 text-right font-semibold text-gray-700 border-b">Total (Rs)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($items) === 0): ?>
                        <tr>
                            <td colspan="4" class="py-4 px-4 text-center text-gray-500">No items found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php $portionLabel = (isset($item['portion']) && $item['portion'] === 'half') ? ' (Half)' : ''; ?>
                            <tr class="border-b">
                                <td class="py-3 px-4 text-gray-800">
                                    <?php echo htmlspecialchars($item['food_name'] ?? ''); ?>
                                    <span class="text-xs text-gray-500"><?php echo $portionLabel; ?></span>
                                </td>
                                <td class="py-3 px-4 text-right text-gray-800"><?php echo intval($item['qty'] ?? 0); ?></td>
                                <td class="py-3 px-4 text-right text-gray-800"><?php echo number_format(isset($item['price']) ? (float)$item['price'] : 0, 2); ?></td>
                                <td class="py-3 px-4 text-right text-gray-900 font-semibold"><?php echo number_format(isset($item['total']) ? (float)$item['total'] : 0, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="py-3 px-4 text-right font-semibold text-gray-700">Subtotal</td>
                        <td class="py-3 px-4 text-right font-bold text-gray-900">Rs <?php echo number_format($subtotal, 2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="py-3 px-4 text-right font-semibold text-gray-700">Total</td>
                        <td class="py-3 px-4 text-right font-bold text-gray-900">Rs <?php echo number_format($total, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="px-6 pb-6 flex justify-between items-center text-sm text-gray-600">
            <p>Thank you for dining with us!</p>
            <button class="no-print px-4 py-2 bg-indigo-600 text-white rounded-lg shadow hover:bg-indigo-700 transition" onclick="window.print()">
                Print / Save as PDF
            </button>
        </div>
    </div>
</body>
</html>

