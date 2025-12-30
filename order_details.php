<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$message = '';
$messageType = 'success';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'create') {
            $order_number = 'ORD' . date('Ymd') . rand(1000, 9999);
            $table_id = !empty($_POST['table_id']) ? intval($_POST['table_id']) : null;
            // Auto-set to current date/time if not provided
            $order_date = !empty(trim($_POST['order_date'] ?? '')) ? trim($_POST['order_date']) : date('Y-m-d H:i:s');
            $order_status = trim($_POST['order_status'] ?? 'pending');
            $payment_status = trim($_POST['payment_status'] ?? 'pending');
            $payment_method = trim($_POST['payment_method'] ?? 'cash');
            $notes = trim($_POST['notes'] ?? '');
            
            // Process food items
            $items = [];
            $subtotal = 0;
            
            if (isset($_POST['food_items']) && is_array($_POST['food_items'])) {
                foreach ($_POST['food_items'] as $food_id) {
                    $qty = intval($_POST['qty_' . $food_id] ?? 0);
                    $portionKey = 'portion_' . $food_id;
                    $portion = isset($_POST[$portionKey]) && $_POST[$portionKey] === 'half' ? 'half' : 'full';
                    if ($qty > 0) {
                        // Get food details
                        $foodStmt = $conn->prepare("SELECT food_name, price FROM menu WHERE id = ?");
                        $foodStmt->bind_param("i", $food_id);
                        $foodStmt->execute();
                        $foodResult = $foodStmt->get_result();
                        if ($foodRow = $foodResult->fetch_assoc()) {
                            $basePrice = (float)$foodRow['price'];
                            $unitPrice = $portion === 'half' ? ($basePrice / 2) : $basePrice;
                            $itemTotal = $unitPrice * $qty;
                            $items[] = [
                                'food_id' => $food_id,
                                'food_name' => $foodRow['food_name'],
                                'price' => $unitPrice,
                                'base_price' => $basePrice,
                                'portion' => $portion,
                                'qty' => $qty,
                                'total' => $itemTotal
                            ];
                            $subtotal += $itemTotal;
                        }
                        $foodStmt->close();
                    }
                }
            }
            
            if (count($items) == 0) {
                $message = "Please select at least one food item!";
                $messageType = 'error';
            } else {
                $itemsJson = json_encode($items);
                $total_amount = $subtotal; // Can add tax/discount later if needed
                
                $stmt = $conn->prepare("INSERT INTO order_details (order_number, table_id, order_date, items, subtotal, total_amount, order_status, payment_status, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("sissddssss", $order_number, $table_id, $order_date, $itemsJson, $subtotal, $total_amount, $order_status, $payment_status, $payment_method, $notes);
                    if ($stmt->execute()) {
                        $message = "Order created successfully!";
                        $messageType = 'success';
                        $stmt->close();
                        $conn->close();
                        header('Location: order_details.php?msg=' . urlencode($message) . '&type=' . $messageType);
                        exit;
                    } else {
                        $message = "Error executing query: " . $stmt->error;
                        $messageType = 'error';
                    }
                    $stmt->close();
                } else {
                    $message = "Error preparing statement: " . $conn->error;
                    $messageType = 'error';
                }
            }
        } elseif ($_POST['action'] == 'update') {
            $id = intval($_POST['id'] ?? 0);
            $table_id = !empty($_POST['table_id']) ? intval($_POST['table_id']) : null;
            // Auto-set to current date/time if not provided
            $order_date = !empty(trim($_POST['order_date'] ?? '')) ? trim($_POST['order_date']) : date('Y-m-d H:i:s');
            $order_status = trim($_POST['order_status'] ?? 'pending');
            $payment_status = trim($_POST['payment_status'] ?? 'pending');
            $payment_method = trim($_POST['payment_method'] ?? 'cash');
            $notes = trim($_POST['notes'] ?? '');
            
            // Process food items
            $items = [];
            $subtotal = 0;
            
            if (isset($_POST['food_items']) && is_array($_POST['food_items'])) {
                foreach ($_POST['food_items'] as $food_id) {
                    $qty = intval($_POST['qty_' . $food_id] ?? 0);
                    $portionKey = 'portion_' . $food_id;
                    $portion = isset($_POST[$portionKey]) && $_POST[$portionKey] === 'half' ? 'half' : 'full';
                    if ($qty > 0) {
                        // Get food details
                        $foodStmt = $conn->prepare("SELECT food_name, price FROM menu WHERE id = ?");
                        $foodStmt->bind_param("i", $food_id);
                        $foodStmt->execute();
                        $foodResult = $foodStmt->get_result();
                        if ($foodRow = $foodResult->fetch_assoc()) {
                            $basePrice = (float)$foodRow['price'];
                            $unitPrice = $portion === 'half' ? ($basePrice / 2) : $basePrice;
                            $itemTotal = $unitPrice * $qty;
                            $items[] = [
                                'food_id' => $food_id,
                                'food_name' => $foodRow['food_name'],
                                'price' => $unitPrice,
                                'base_price' => $basePrice,
                                'portion' => $portion,
                                'qty' => $qty,
                                'total' => $itemTotal
                            ];
                            $subtotal += $itemTotal;
                        }
                        $foodStmt->close();
                    }
                }
            }
            
            if (count($items) == 0 || $id <= 0) {
                $message = "Please select at least one food item!";
                $messageType = 'error';
            } else {
                $itemsJson = json_encode($items);
                $total_amount = $subtotal;
                
                $stmt = $conn->prepare("UPDATE order_details SET table_id=?, order_date=?, items=?, subtotal=?, total_amount=?, order_status=?, payment_status=?, payment_method=?, notes=? WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("issddssssi", $table_id, $order_date, $itemsJson, $subtotal, $total_amount, $order_status, $payment_status, $payment_method, $notes, $id);
                    if ($stmt->execute()) {
                        $message = "Order updated successfully!";
                        $messageType = 'success';
                        $stmt->close();
                        $conn->close();
                        header('Location: order_details.php?msg=' . urlencode($message) . '&type=' . $messageType);
                        exit;
                    } else {
                        $message = "Error executing update: " . $stmt->error;
                        $messageType = 'error';
                    }
                    $stmt->close();
                } else {
                    $message = "Error preparing update statement: " . $conn->error;
                    $messageType = 'error';
                }
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM order_details WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $message = "Order deleted successfully!";
                        $messageType = 'success';
                        $stmt->close();
                        $conn->close();
                        header('Location: order_details.php?msg=' . urlencode($message) . '&type=' . $messageType);
                        exit;
                    } else {
                        $message = "Error deleting: " . $stmt->error;
                        $messageType = 'error';
                    }
                    $stmt->close();
                } else {
                    $message = "Error preparing delete statement: " . $conn->error;
                    $messageType = 'error';
                }
            }
        }
    }
}

// Get message from URL if redirected
if (isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
    $messageType = $_GET['type'] ?? 'success';
}

// Fetch pending orders separately
// Show orders as pending if: order_status is pending OR (order_status is completed AND payment_status is pending)
$pendingOrders = $conn->query("
    SELECT o.*, t.table_number 
    FROM order_details o 
    LEFT JOIN tables t ON o.table_id = t.id 
    WHERE o.order_status = 'pending' OR (o.order_status = 'completed' AND o.payment_status = 'pending')
    ORDER BY o.order_date DESC
");
if (!$pendingOrders) {
    die("Error fetching pending orders: " . $conn->error);
}

// Fetch all orders with table information
$orders = $conn->query("
    SELECT o.*, t.table_number 
    FROM order_details o 
    LEFT JOIN tables t ON o.table_id = t.id 
    ORDER BY o.order_date DESC
");
if (!$orders) {
    die("Error fetching orders: " . $conn->error);
}

// Fetch tables for dropdown
$tables = $conn->query("SELECT id, table_number FROM tables ORDER BY table_number");
if (!$tables) {
    die("Error fetching tables: " . $conn->error);
}

// Fetch menu items for dropdown
$menuItems = $conn->query("SELECT id, food_name, price FROM menu WHERE status = 'available' ORDER BY food_name");
if (!$menuItems) {
    die("Error fetching menu items: " . $conn->error);
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        var openModal, closeModal, deleteRecord, calculateTotal, addFoodItem, removeFoodItem, initFoodSearch;
        var selectedFoods = [];
        var menuData = {};
        
        (function() {
            // Load menu data
            <?php
            $menuItems->data_seek(0);
            while ($menu = $menuItems->fetch_assoc()): ?>
                menuData[<?php echo $menu['id']; ?>] = {
                    id: <?php echo $menu['id']; ?>,
                    name: <?php echo json_encode($menu['food_name']); ?>,
                    price: <?php echo $menu['price']; ?>
                };
            <?php endwhile; ?>
            
            openModal = function(action, data) {
                var modal = document.getElementById('modal');
                var form = document.getElementById('orderForm');
                
                if (!modal || !form) {
                    console.error('Modal or form not found');
                    return;
                }
                
                // Set form action: 'create' for new, 'update' for edit
                var formAction = action === 'edit' ? 'update' : 'create';
                document.getElementById('formAction').value = formAction;
                document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New Order' : 'Edit Order';
                
                // Clear food items container
                var foodItemsContainer = document.getElementById('foodItemsContainer');
                foodItemsContainer.innerHTML = '';
                selectedFoods = [];
                
                if (action === 'edit' && data) {
                    document.getElementById('formId').value = data.id || '';
                    document.getElementById('table_id').value = data.table_id || '';
                    
                    // Format datetime for input
                    var orderDate = data.order_date ? new Date(data.order_date.replace(' ', 'T')).toISOString().slice(0, 16) : '';
                    document.getElementById('order_date').value = orderDate;
                    
                    document.getElementById('order_status').value = data.order_status || 'pending';
                    document.getElementById('payment_status').value = data.payment_status || 'pending';
                    document.getElementById('payment_method').value = data.payment_method || 'cash';
                    document.getElementById('notes').value = data.notes || '';
                    
                    // Load existing items
                    if (data.items) {
                        try {
                            var items = typeof data.items === 'string' ? JSON.parse(data.items) : data.items;
                            items.forEach(function(item, index) {
                                var foodId = item.food_id || item.id;
                                var qty = item.qty || 1;
                                var portion = item.portion || 'full';
                                var uniqueId = foodId + '_' + index + '_' + Date.now();
                                addFoodItem(foodId, qty, uniqueId, portion);
                            });
                        } catch(e) {
                            console.error('Error parsing items:', e);
                        }
                    }
                } else {
                    form.reset();
                    document.getElementById('formId').value = '';
                    // Always set current date/time for new orders
                    var now = new Date();
                    var year = now.getFullYear();
                    var month = String(now.getMonth() + 1).padStart(2, '0');
                    var day = String(now.getDate()).padStart(2, '0');
                    var hours = String(now.getHours()).padStart(2, '0');
                    var minutes = String(now.getMinutes()).padStart(2, '0');
                    document.getElementById('order_date').value = year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
                }
                
                calculateTotal();
                modal.classList.remove('hidden');
                
                // Initialize food search after modal opens
                setTimeout(function() {
                    if (typeof initFoodSearch === 'function') {
                        initFoodSearch();
                    }
                    // Clear search input
                    var searchInput = document.getElementById('foodSearch');
                    if (searchInput) {
                        searchInput.value = '';
                    }
                    var searchResults = document.getElementById('foodSearchResults');
                    if (searchResults) {
                        searchResults.classList.add('hidden');
                    }
                }, 100);
            };
            
            closeModal = function() {
                var modal = document.getElementById('modal');
                if (modal) {
                    modal.classList.add('hidden');
                }
            };
            
            deleteRecord = function(id) {
                if (confirm('Are you sure you want to delete this order?')) {
                    document.getElementById('deleteId').value = id;
                    document.getElementById('deleteForm').submit();
                }
            };
            
            addFoodItem = function(foodId, qty, existingFoodId, portion) {
                if (!menuData[foodId]) return;
                
                var food = menuData[foodId];
                var foodIdToUse = existingFoodId || foodId;
                var portionValue = portion || 'full';
                
                if (selectedFoods.indexOf(foodIdToUse) !== -1) return;
                
                selectedFoods.push(foodIdToUse);
                
                var container = document.getElementById('foodItemsContainer');
                var itemDiv = document.createElement('div');
                itemDiv.className = 'food-item flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200';
                itemDiv.id = 'foodItem_' + foodIdToUse;
                itemDiv.innerHTML = `
                    <input type="hidden" name="food_items[]" value="${foodId}">
                    <div class="flex-1">
                        <div class="font-medium text-gray-900">${food.name}</div>
                        <div class="text-xs text-gray-500">Base Price: Rs ${food.price.toFixed(2)}</div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-700">Portion:</label>
                            <select name="portion_${foodId}" class="portion-select px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-xs" onchange="calculateTotal()">
                                <option value="full" ${portionValue === 'full' ? 'selected' : ''}>Full</option>
                                <option value="half" ${portionValue === 'half' ? 'selected' : ''}>Half</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-700">Qty:</label>
                            <input type="number" name="qty_${foodId}" value="${qty || 1}" min="1" class="w-20 px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" onchange="calculateTotal()" required>
                        </div>
                    </div>
                    <button type="button" onclick="removeFoodItem(${foodIdToUse})" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors text-sm">
                        Remove
                    </button>
                `;
                container.appendChild(itemDiv);
                calculateTotal();
            };
            
            removeFoodItem = function(foodId) {
                var itemDiv = document.getElementById('foodItem_' + foodId);
                if (itemDiv) {
                    itemDiv.remove();
                    selectedFoods = selectedFoods.filter(id => id !== foodId);
                    calculateTotal();
                }
            };
            
            calculateTotal = function() {
                var subtotal = 0;
                var foodItems = document.querySelectorAll('.food-item');
                
                foodItems.forEach(function(item) {
                    var qtyInput = item.querySelector('input[type="number"]');
                    var foodId = qtyInput.name.replace('qty_', '');
                    var qty = parseInt(qtyInput.value) || 0;
                    var portionSelect = item.querySelector('.portion-select');
                    var portion = portionSelect ? portionSelect.value : 'full';
                    
                    if (menuData[foodId]) {
                        var basePrice = menuData[foodId].price;
                        var unitPrice = portion === 'half' ? (basePrice / 2) : basePrice;
                        subtotal += unitPrice * qty;
                    }
                });
                
                document.getElementById('subtotal').value = subtotal.toFixed(2);
                document.getElementById('total_amount').value = subtotal.toFixed(2);
            };
            
            // Food search functionality
            var foodSearchInput = null;
            var foodSearchResults = null;
            
            initFoodSearch = function() {
                foodSearchInput = document.getElementById('foodSearch');
                foodSearchResults = document.getElementById('foodSearchResults');
                
                if (!foodSearchInput || !foodSearchResults) return;
                
                foodSearchInput.addEventListener('input', function() {
                    var searchTerm = this.value.toLowerCase().trim();
                    
                    if (searchTerm.length === 0) {
                        foodSearchResults.classList.add('hidden');
                        return;
                    }
                    
                    // Filter menu items
                    var filteredItems = [];
                    for (var foodId in menuData) {
                        var food = menuData[foodId];
                        if (food.name.toLowerCase().includes(searchTerm)) {
                            filteredItems.push(food);
                        }
                    }
                    
                    // Display results
                    if (filteredItems.length > 0) {
                        foodSearchResults.innerHTML = '';
                        filteredItems.forEach(function(food) {
                            var itemDiv = document.createElement('div');
                            itemDiv.className = 'px-4 py-3 hover:bg-indigo-50 cursor-pointer border-b border-gray-100 transition-colors flex justify-between items-center';
                            itemDiv.innerHTML = `
                                <div>
                                    <div class="font-medium text-gray-900">${food.name}</div>
                                    <div class="text-sm text-gray-600">Rs ${food.price.toFixed(2)}</div>
                                </div>
                                <div class="text-indigo-600 font-semibold">+ Add</div>
                            `;
                            itemDiv.addEventListener('click', function() {
                                addFoodItem(food.id, 1, null, 'full');
                                foodSearchInput.value = '';
                                foodSearchResults.classList.add('hidden');
                            });
                            foodSearchResults.appendChild(itemDiv);
                        });
                        foodSearchResults.classList.remove('hidden');
                    } else {
                        foodSearchResults.innerHTML = '<div class="px-4 py-3 text-gray-500 text-center">No items found matching your search</div>';
                        foodSearchResults.classList.remove('hidden');
                    }
                });
                
                // Hide results when clicking outside
                document.addEventListener('click', function(e) {
                    if (foodSearchInput && foodSearchResults && 
                        !foodSearchInput.contains(e.target) && 
                        !foodSearchResults.contains(e.target)) {
                        foodSearchResults.classList.add('hidden');
                    }
                });
            }
            
            // Initialize search when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(initFoodSearch, 200);
                });
            } else {
                setTimeout(initFoodSearch, 200);
            }
        })();
    </script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <?php include 'includes/nav.php'; ?>

        <main class="md:ml-64 p-4 md:p-6 lg:p-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900">Order Details Management</h2>
                <button onclick="openModal('create')" class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                    + Add New Order
                </button>
            </div>

            <?php if ($message): ?>
                <div class="mb-6 p-4 <?php echo $messageType === 'success' ? 'bg-green-50 border-l-4 border-green-500 text-green-700' : 'bg-red-50 border-l-4 border-red-500 text-red-700'; ?> rounded-lg animate-slide-up">
                    <?php echo htmlspecialchars($message); ?>
                    <button onclick="this.parentElement.remove()" class="float-right text-gray-500 hover:text-gray-700">×</button>
                </div>
            <?php endif; ?>

            <!-- Pending Orders Section -->
            <div class="mb-8">
                <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></span>
                    Pending Orders
                </h3>
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-yellow-500 to-orange-500 text-white">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Order Number</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Table</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Order Date</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Payment</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $pendingOrders->data_seek(0);
                                $hasPendingOrders = false;
                                while ($row = $pendingOrders->fetch_assoc()): 
                                    $hasPendingOrders = true;
                                ?>
                                <tr class="hover:bg-yellow-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $row['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['order_number']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($row['table_number'] ?? 'N/A'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo $row['order_date'] ? date('M d, Y H:i', strtotime($row['order_date'])) : 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                        Rs <?php echo number_format($row['total_amount'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $paymentStatusClass = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'paid' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800'
                                        ];
                                        $paymentStatus = $row['payment_status'];
                                        $paymentClass = $paymentStatusClass[$paymentStatus] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <div class="flex flex-col gap-1">
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold capitalize <?php echo $paymentClass; ?>">
                                                <?php echo $paymentStatus; ?>
                                            </span>
                                            <span class="text-xs text-gray-600 capitalize">
                                                <?php echo $row['payment_method'] ?? 'N/A'; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex flex-wrap gap-2">
                                        <button onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($row)); ?>)" class="px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors text-xs">
                                            Edit
                                        </button>
                                        <button onclick="deleteRecord(<?php echo $row['id']; ?>)" class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors text-xs">
                                            Delete
                                        </button>
                                        <a href="order_bill.php?id=<?php echo $row['id']; ?>" target="_blank" class="px-3 py-1 bg-emerald-500 text-white rounded-md hover:bg-emerald-600 transition-colors text-xs inline-flex items-center gap-1">
                                            🧾 Bill
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if (!$hasPendingOrders): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                        No pending orders at the moment.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- All Orders Section -->
            <div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">All Orders</h3>
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">ID</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Order Number</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Table</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Order Date</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Total</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Order Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Payment</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($row = $orders->fetch_assoc()): ?>
                            <tr class="hover:bg-indigo-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $row['id']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['order_number']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($row['table_number'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo $row['order_date'] ? date('M d, Y H:i', strtotime($row['order_date'])) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                    Rs <?php echo number_format($row['total_amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $orderStatusClass = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'completed' => 'bg-green-100 text-green-800'
                                    ];
                                    $orderStatus = $row['order_status'] ?? 'pending';
                                    $orderClass = $orderStatusClass[$orderStatus] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold capitalize <?php echo $orderClass; ?>">
                                        <?php echo $orderStatus; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $paymentStatusClass = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'paid' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800'
                                    ];
                                    $paymentStatus = $row['payment_status'];
                                    $paymentClass = $paymentStatusClass[$paymentStatus] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <div class="flex flex-col gap-1">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold capitalize <?php echo $paymentClass; ?>">
                                            <?php echo $paymentStatus; ?>
                                        </span>
                                        <span class="text-xs text-gray-600 capitalize">
                                            <?php echo $row['payment_method'] ?? 'N/A'; ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex flex-wrap gap-2">
                                    <button onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($row)); ?>)" class="px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors text-xs">
                                        Edit
                                    </button>
                                    <button onclick="deleteRecord(<?php echo $row['id']; ?>)" class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors text-xs">
                                        Delete
                                    </button>
                                    <a href="order_bill.php?id=<?php echo $row['id']; ?>" target="_blank" class="px-3 py-1 bg-emerald-500 text-white rounded-md hover:bg-emerald-600 transition-colors text-xs inline-flex items-center gap-1">
                                        🧾 Bill
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full p-6 md:p-8 animate-slide-up max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 id="modalTitle" class="text-2xl font-bold text-gray-900">Add New Order</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <form method="POST" id="orderForm" class="space-y-4">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId">
                
                <div>
                    <label for="table_id" class="block text-sm font-semibold text-gray-700 mb-2">Table Number</label>
                    <select id="table_id" name="table_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        <option value="">Select Table (Optional)</option>
                        <?php
                        $tables->data_seek(0);
                        while ($table = $tables->fetch_assoc()): ?>
                            <option value="<?php echo $table['id']; ?>"><?php echo htmlspecialchars($table['table_number']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div>
                    <label for="order_date" class="block text-sm font-semibold text-gray-700 mb-2">Order Date & Time <span class="text-gray-500 text-xs">(Auto-filled with current date/time)</span></label>
                    <input type="datetime-local" id="order_date" name="order_date" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Select Food Items *</label>
                    <div class="relative mb-3">
                        <div class="relative">
                            <input 
                                type="text" 
                                id="foodSearch" 
                                placeholder="🔍 Search food items by name..." 
                                class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                                autocomplete="off"
                            >
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <div id="foodSearchResults" class="hidden absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            <!-- Search results will appear here -->
                        </div>
                    </div>
                    <div id="foodItemsContainer" class="space-y-2 min-h-[100px] p-3 border border-gray-200 rounded-lg bg-gray-50">
                        <p class="text-sm text-gray-500 text-center py-4">No food items selected. Search and select items to add them.</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="subtotal" class="block text-sm font-semibold text-gray-700 mb-2">Subtotal</label>
                        <input type="number" id="subtotal" name="subtotal" step="0.01" readonly class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 outline-none" value="0.00">
                    </div>
                    
                    <div>
                        <label for="total_amount" class="block text-sm font-semibold text-gray-700 mb-2">Total Amount</label>
                        <input type="number" id="total_amount" name="total_amount" step="0.01" readonly class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 outline-none" value="0.00">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="order_status" class="block text-sm font-semibold text-gray-700 mb-2">Order Status *</label>
                        <select id="order_status" name="order_status" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="payment_status" class="block text-sm font-semibold text-gray-700 mb-2">Payment Status *</label>
                        <select id="payment_status" name="payment_status" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="payment_method" class="block text-sm font-semibold text-gray-700 mb-2">Payment Method *</label>
                        <select id="payment_method" name="payment_method" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label for="notes" class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Additional notes or special instructions..." class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"></textarea>
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all">
                        Save
                    </button>
                    <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-all">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Form -->
    <form method="POST" id="deleteForm" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script src="assets/js/main.js"></script>
    <script>
        // Modal click outside to close
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('modal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal();
                    }
                });
            }
        });
    </script>
</body>
</html>
