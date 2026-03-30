<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();

// Ensure table exists for environments where initializer may not have run recently.
$conn->query("CREATE TABLE IF NOT EXISTS purchase_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    rate DECIMAL(10, 2) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    purchase_date DATE NOT NULL,
    supplier_name VARCHAR(255),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_purchase'])) {
    $item_name = trim($_POST['item_name'] ?? '');
    $quantity = (float)($_POST['quantity'] ?? 0);
    $rate = (float)($_POST['rate'] ?? 0);
    $purchase_date = trim($_POST['purchase_date'] ?? '');
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($item_name === '') {
        $error = 'Item name is required.';
    } elseif ($quantity <= 0) {
        $error = 'Quantity must be greater than 0.';
    } elseif ($rate < 0) {
        $error = 'Rate must be 0 or greater.';
    } elseif (!$purchase_date || !DateTime::createFromFormat('Y-m-d', $purchase_date)) {
        $error = 'Please provide a valid purchase date.';
    } else {
        $amount = round($quantity * $rate, 2);
        $supplier_db = ($supplier_name === '') ? null : $supplier_name;
        $remarks_db = ($remarks === '') ? null : $remarks;

        $stmt = $conn->prepare("INSERT INTO purchase_details (item_name, quantity, rate, amount, purchase_date, supplier_name, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('sdddsss', $item_name, $quantity, $rate, $amount, $purchase_date, $supplier_db, $remarks_db);
            if ($stmt->execute()) {
                $success = 'Purchase item added successfully.';
                $_POST = [];
            } else {
                $error = 'Failed to add purchase item. Please try again.';
            }
            $stmt->close();
        } else {
            $error = 'Unable to prepare purchase insert query.';
        }
    }
}

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
            $start_date = $custom_start->format('Y-m-d');
            $end_date = $custom_end->format('Y-m-d');
        }

        $filter_start = $custom_start;
        $filter_end = $custom_end;
    } else {
        $period = '30days';
    }
}

if ($period !== 'custom') {
    $days = $days_map[$period] ?? 30;
    $filter_start = clone $today;
    $filter_start->modify('-' . ($days - 1) . ' days');
    $filter_end = clone $today;
    $start_date = $filter_start->format('Y-m-d');
    $end_date = $filter_end->format('Y-m-d');
}

$filter_start_sql = $filter_start->format('Y-m-d');
$filter_end_sql = $filter_end->format('Y-m-d');

$purchases = [];
$total_amount = 0.0;

$list_stmt = $conn->prepare("SELECT id, item_name, quantity, rate, amount, purchase_date, supplier_name, remarks, created_at FROM purchase_details WHERE purchase_date BETWEEN ? AND ? ORDER BY purchase_date DESC, id DESC");
if ($list_stmt) {
    $list_stmt->bind_param('ss', $filter_start_sql, $filter_end_sql);
    $list_stmt->execute();
    $result = $list_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $purchases[] = $row;
        $total_amount += (float)$row['amount'];
    }
    $list_stmt->close();
} else {
    $error = $error ?: 'Unable to load purchase data.';
}

$conn->close();

$export_query = http_build_query([
    'period' => $period,
    'start_date' => $start_date,
    'end_date' => $end_date,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Details - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/purchase_details.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <?php include 'includes/nav.php'; ?>

        <main class="md:ml-64 p-4 md:p-6 lg:p-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-900">🧾 Purchase Details</h2>
                    <p class="text-gray-600 mt-1">Track daily purchase items with optional supplier and remarks.</p>
                </div>
                <a href="export_purchase_details.php?<?php echo htmlspecialchars($export_query); ?>" class="inline-flex items-center justify-center px-5 py-3 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition-colors shadow">
                    Export Excel (CSV)
                </a>
            </div>

            <?php if ($success): ?>
                <div class="mb-5 px-4 py-3 rounded-lg bg-green-100 text-green-800 border border-green-200">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-5 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-200">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                <div class="xl:col-span-1 bg-white rounded-xl shadow p-5 md:p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Add Purchase Item</h3>
                    <form method="POST" class="space-y-4" id="purchaseForm">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Item Name *</label>
                            <input type="text" name="item_name" value="<?php echo htmlspecialchars($_POST['item_name'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Quantity *</label>
                                <input type="number" min="0.01" step="0.01" id="quantity" name="quantity" value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Rate *</label>
                                <input type="number" min="0" step="0.01" id="rate" name="rate" value="<?php echo htmlspecialchars($_POST['rate'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Amount (Auto)</label>
                            <input type="number" id="amount_display" readonly class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-100 text-gray-700" value="0.00">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Purchase Date *</label>
                            <input type="date" name="purchase_date" value="<?php echo htmlspecialchars($_POST['purchase_date'] ?? date('Y-m-d')); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Supplier Name (Optional)</label>
                            <input type="text" name="supplier_name" value="<?php echo htmlspecialchars($_POST['supplier_name'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Remarks (Optional)</label>
                            <textarea name="remarks" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" name="add_purchase" class="w-full px-4 py-2.5 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors">
                            Save Purchase
                        </button>
                    </form>
                </div>

                <div class="xl:col-span-2 bg-white rounded-xl shadow p-5 md:p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-5">
                        <h3 class="text-lg font-bold text-gray-900">Purchase Records</h3>
                        <div class="text-sm text-gray-600">
                            Showing: <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($filter_start_sql); ?></span> to <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($filter_end_sql); ?></span>
                        </div>
                    </div>

                    <form method="GET" class="mb-5 space-y-4 border border-gray-200 rounded-lg p-4">
                        <div class="flex flex-wrap gap-2">
                            <?php
                            $filter_labels = [
                                '5days' => 'Last 5 Days',
                                '10days' => 'Last 10 Days',
                                '15days' => 'Last 15 Days',
                                '30days' => 'Last 30 Days',
                                '40days' => 'Last 40 Days',
                                'custom' => 'Custom',
                            ];
                            foreach ($filter_labels as $key => $label):
                            ?>
                                <button type="button" onclick="setFilter('<?php echo $key; ?>')" id="btn-<?php echo $key; ?>" class="filter-btn px-3 py-1.5 rounded-md text-sm font-medium <?php echo ($period === $key) ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                                    <?php echo htmlspecialchars($label); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <input type="hidden" name="period" id="period" value="<?php echo htmlspecialchars($period); ?>">

                        <div id="customRange" class="<?php echo ($period === 'custom') ? 'grid' : 'hidden'; ?> grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>

                        <div>
                            <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg font-medium hover:bg-black transition-colors">Apply Filter</button>
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="purchase-records-table min-w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                            <thead class="bg-gray-100 text-gray-700">
                                <tr>
                                    <th class="px-3 py-2 text-left">S.N</th>
                                    <th class="px-3 py-2 text-left">Item Name</th>
                                    <th class="px-3 py-2 text-right">Quantity</th>
                                    <th class="px-3 py-2 text-right">Rate</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2 text-left">Purchase Date</th>
                                    <th class="px-3 py-2 text-left">Supplier Name</th>
                                    <th class="px-3 py-2 text-left">Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (count($purchases) > 0): ?>
                                    <?php foreach ($purchases as $index => $purchase): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2"><?php echo (int)($index + 1); ?></td>
                                            <td class="px-3 py-2 font-medium text-gray-900"><?php echo htmlspecialchars($purchase['item_name']); ?></td>
                                            <td class="px-3 py-2 text-right"><?php echo number_format((float)$purchase['quantity'], 2); ?></td>
                                            <td class="px-3 py-2 text-right">Rs <?php echo number_format((float)$purchase['rate'], 2); ?></td>
                                            <td class="px-3 py-2 text-right font-semibold text-green-700">Rs <?php echo number_format((float)$purchase['amount'], 2); ?></td>
                                            <td class="px-3 py-2"><?php echo htmlspecialchars($purchase['purchase_date']); ?></td>
                                            <td class="px-3 py-2"><?php echo htmlspecialchars($purchase['supplier_name'] ?: '-'); ?></td>
                                            <td class="px-3 py-2"><?php echo htmlspecialchars($purchase['remarks'] ?: '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">No purchase records found for selected filter.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="bg-gray-50 border-t border-gray-200">
                                <tr>
                                    <td colspan="4" class="px-3 py-3 text-right font-semibold text-gray-700">Total Amount</td>
                                    <td class="px-3 py-3 text-right font-bold text-green-700">Rs <?php echo number_format($total_amount, 2); ?></td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/purchase_details.js" defer></script>
</body>
</html>
