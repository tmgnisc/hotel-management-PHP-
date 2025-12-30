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
        @page {
            size: 80mm auto;
            margin: 0;
            padding: 0;
        }
        @media print {
            .no-print { display: none; }
            body { 
                background: #fff;
                margin: 0;
                padding: 0;
                width: 80mm;
                font-size: 10px;
            }
            .bill-container {
                width: 80mm;
                max-width: 80mm;
                margin: 0;
                padding: 5mm 4mm;
                box-shadow: none;
                border: none;
                font-size: 10px;
            }
            * {
                box-sizing: border-box;
            }
            table {
                font-size: 9px;
            }
            h1 {
                font-size: 14px;
            }
        }
        @media screen {
            .bill-container {
                max-width: 80mm;
                margin: 20px auto;
                padding: 8mm 5mm;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                border: 1px solid #e5e7eb;
            }
        }
        .devanagari-font {
            font-family: 'Noto Sans Devanagari', sans-serif;
        }
        body {
            font-size: 11px;
        }
        .bill-container {
            font-size: 11px;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@500;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="bill-container bg-white">
        <div class="text-center border-b border-gray-300 pb-2 mb-3">
            <h1 class="text-base font-bold devanagari-font mb-1"><?php echo htmlspecialchars($companyName); ?></h1>
            <p class="text-xs text-gray-600">Order Bill / Tax Invoice</p>
        </div>

        <div class="space-y-1 text-xs text-gray-800 mb-3">
            <p><span class="font-semibold">Bill No:</span> <?php echo htmlspecialchars($order['order_number']); ?></p>
            <p><span class="font-semibold">Date:</span> <?php echo $orderDate; ?></p>
            <p><span class="font-semibold">Table:</span> <?php echo htmlspecialchars($order['table_number'] ?? 'N/A'); ?></p>
            <p><span class="font-semibold">Payment:</span> <?php echo htmlspecialchars($order['payment_status'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?>)</p>
            <?php if (!empty($order['notes'])): ?>
                <p><span class="font-semibold">Notes:</span> <?php echo htmlspecialchars($order['notes']); ?></p>
            <?php endif; ?>
        </div>

        <div class="border-t border-b border-gray-300 py-2 my-2">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-300">
                        <th class="text-left py-1 font-semibold">Item</th>
                        <th class="text-right py-1 font-semibold">Qty</th>
                        <th class="text-right py-1 font-semibold">Rate</th>
                        <th class="text-right py-1 font-semibold">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($items) === 0): ?>
                        <tr>
                            <td colspan="4" class="py-2 text-center text-gray-500">No items found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php $portionLabel = (isset($item['portion']) && $item['portion'] === 'half') ? ' (Half)' : ''; ?>
                            <tr class="border-b border-gray-200">
                                <td class="py-1 text-gray-800">
                                    <?php echo htmlspecialchars($item['food_name'] ?? ''); ?>
                                    <?php if ($portionLabel): ?>
                                        <span class="text-gray-500"><?php echo $portionLabel; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-1 text-right text-gray-800"><?php echo intval($item['qty'] ?? 0); ?></td>
                                <td class="py-1 text-right text-gray-800"><?php echo number_format(isset($item['price']) ? (float)$item['price'] : 0, 2); ?></td>
                                <td class="py-1 text-right text-gray-900 font-semibold"><?php echo number_format(isset($item['total']) ? (float)$item['total'] : 0, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-3 space-y-1">
            <div class="flex justify-between text-xs border-t border-gray-300 pt-2">
                <span class="font-semibold">Subtotal:</span>
                <span class="font-semibold">Rs <?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="flex justify-between text-sm border-t-2 border-gray-900 pt-2">
                <span class="font-bold">TOTAL:</span>
                <span class="font-bold">Rs <?php echo number_format($total, 2); ?></span>
            </div>
        </div>

        <div class="text-center mt-4 pt-3 border-t border-gray-300">
            <p class="text-xs text-gray-600 mb-3">Thank you for dining with us!</p>
            <button class="no-print px-3 py-1.5 bg-indigo-600 text-white text-xs rounded shadow hover:bg-indigo-700 transition" onclick="window.print()">
                Print / Save as PDF
            </button>
        </div>
    </div>
</body>
</html>

