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
            $customer_name = trim($_POST['customer_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $status = trim($_POST['status'] ?? 'active');
            $discount_percentage = floatval($_POST['discount_percentage'] ?? 0);
            $discount_amount = floatval($_POST['discount_amount'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');
            
            if (empty($customer_name) || empty($phone)) {
                $message = "Please fill all required fields!";
                $messageType = 'error';
            } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "Please enter a valid email address!";
                $messageType = 'error';
            } elseif ($discount_percentage < 0 || $discount_percentage > 100) {
                $message = "Discount percentage must be between 0 and 100!";
                $messageType = 'error';
            } elseif ($discount_amount < 0) {
                $message = "Discount amount cannot be negative!";
                $messageType = 'error';
            } else {
                // Check if phone already exists
                $checkStmt = $conn->prepare("SELECT id FROM regular_customers WHERE phone = ?");
                $checkStmt->bind_param("s", $phone);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    $message = "A customer with this phone number already exists!";
                    $messageType = 'error';
                    $checkStmt->close();
                } else {
                    $checkStmt->close();
                    $stmt = $conn->prepare("INSERT INTO regular_customers (customer_name, phone, email, address, status, discount_percentage, discount_amount, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("sssssdds", $customer_name, $phone, $email, $address, $status, $discount_percentage, $discount_amount, $notes);
                        if ($stmt->execute()) {
                            $message = "Regular customer created successfully!";
                            $messageType = 'success';
                            $stmt->close();
                            $conn->close();
                            header('Location: regular_customers.php?msg=' . urlencode($message) . '&type=' . $messageType);
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
            }
        } elseif ($_POST['action'] == 'update') {
            $id = intval($_POST['id'] ?? 0);
            $customer_name = trim($_POST['customer_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $status = trim($_POST['status'] ?? 'active');
            $discount_percentage = floatval($_POST['discount_percentage'] ?? 0);
            $discount_amount = floatval($_POST['discount_amount'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');
            
            if (empty($customer_name) || empty($phone) || $id <= 0) {
                $message = "Please fill all required fields!";
                $messageType = 'error';
            } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "Please enter a valid email address!";
                $messageType = 'error';
            } elseif ($discount_percentage < 0 || $discount_percentage > 100) {
                $message = "Discount percentage must be between 0 and 100!";
                $messageType = 'error';
            } elseif ($discount_amount < 0) {
                $message = "Discount amount cannot be negative!";
                $messageType = 'error';
            } else {
                // Check if phone already exists for other customers
                $checkStmt = $conn->prepare("SELECT id FROM regular_customers WHERE phone = ? AND id != ?");
                $checkStmt->bind_param("si", $phone, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    $message = "A customer with this phone number already exists!";
                    $messageType = 'error';
                    $checkStmt->close();
                } else {
                    $checkStmt->close();
                    $stmt = $conn->prepare("UPDATE regular_customers SET customer_name=?, phone=?, email=?, address=?, status=?, discount_percentage=?, discount_amount=?, notes=? WHERE id=?");
                    if ($stmt) {
                        $stmt->bind_param("sssssddsi", $customer_name, $phone, $email, $address, $status, $discount_percentage, $discount_amount, $notes, $id);
                        if ($stmt->execute()) {
                            $message = "Regular customer updated successfully!";
                            $messageType = 'success';
                            $stmt->close();
                            $conn->close();
                            header('Location: regular_customers.php?msg=' . urlencode($message) . '&type=' . $messageType);
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
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM regular_customers WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $message = "Regular customer deleted successfully!";
                        $messageType = 'success';
                        $stmt->close();
                        $conn->close();
                        header('Location: regular_customers.php?msg=' . urlencode($message) . '&type=' . $messageType);
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
        } elseif ($_POST['action'] == 'add_transaction') {
            $customer_id = intval($_POST['customer_id'] ?? 0);
            $transaction_type = trim($_POST['transaction_type'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $order_id = !empty($_POST['order_id']) ? intval($_POST['order_id']) : null;
            $reference_number = trim($_POST['reference_number'] ?? '');
            
            if ($customer_id <= 0 || empty($transaction_type) || $amount <= 0) {
                $message = "Please fill all required fields correctly!";
                $messageType = 'error';
            } else {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Insert transaction
                    $reference_number = !empty($reference_number) ? $reference_number : null;
                    $description = !empty($description) ? $description : null;
                    $stmt = $conn->prepare("INSERT INTO customer_transactions (customer_id, transaction_type, amount, description, order_id, reference_number) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("isdsis", $customer_id, $transaction_type, $amount, $description, $order_id, $reference_number);
                        if (!$stmt->execute()) {
                            throw new Exception("Error inserting transaction: " . $stmt->error);
                        }
                        $stmt->close();
                    } else {
                        throw new Exception("Error preparing transaction statement: " . $conn->error);
                    }
                    
                    // Update customer amounts
                    if ($transaction_type == 'credit') {
                        // Credit increases due amount
                        $updateStmt = $conn->prepare("UPDATE regular_customers SET due_amount = due_amount + ?, total_amount = total_amount + ? WHERE id = ?");
                    } elseif ($transaction_type == 'payment') {
                        // Payment decreases due amount
                        $updateStmt = $conn->prepare("UPDATE regular_customers SET due_amount = GREATEST(0, due_amount - ?) WHERE id = ?");
                    } else {
                        // Order increases both due and total
                        $updateStmt = $conn->prepare("UPDATE regular_customers SET due_amount = due_amount + ?, total_amount = total_amount + ? WHERE id = ?");
                    }
                    
                    if ($updateStmt) {
                        if ($transaction_type == 'payment') {
                            $updateStmt->bind_param("di", $amount, $customer_id);
                        } else {
                            $updateStmt->bind_param("ddi", $amount, $amount, $customer_id);
                        }
                        if (!$updateStmt->execute()) {
                            throw new Exception("Error updating customer amounts: " . $updateStmt->error);
                        }
                        $updateStmt->close();
                    } else {
                        throw new Exception("Error preparing update statement: " . $conn->error);
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    $message = "Transaction added successfully!";
                    $messageType = 'success';
                    $conn->close();
                    header('Location: regular_customers.php?msg=' . urlencode($message) . '&type=' . $messageType);
                    exit;
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = $e->getMessage();
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

// Fetch all regular customers with amounts
$customers = $conn->query("SELECT id, customer_name, phone, email, address, status, discount_percentage, discount_amount, due_amount, total_amount, notes, created_at FROM regular_customers ORDER BY created_at DESC");
if (!$customers) {
    die("Error fetching customers: " . $conn->error);
}

// Fetch orders for linking to transactions (optional)
$orders = $conn->query("SELECT id, order_number, total_amount, order_date FROM order_details ORDER BY order_date DESC LIMIT 100");
$ordersList = [];
if ($orders) {
    while ($order = $orders->fetch_assoc()) {
        $ordersList[] = $order;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regular Customers - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        var openModal, closeModal, deleteRecord, openTransactionModal, closeTransactionModal;
        var ordersList = <?php echo json_encode($ordersList); ?>;
        
        (function() {
            openModal = function(action, data) {
                var modal = document.getElementById('modal');
                var form = document.getElementById('customerForm');
                
                if (!modal || !form) {
                    console.error('Modal or form not found');
                    return;
                }
                
                // Set form action: 'create' for new, 'update' for edit
                var formAction = action === 'edit' ? 'update' : 'create';
                document.getElementById('formAction').value = formAction;
                document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New Regular Customer' : 'Edit Regular Customer';
                
                if (action === 'edit' && data) {
                    document.getElementById('formId').value = data.id || '';
                    document.getElementById('customer_name').value = data.customer_name || '';
                    document.getElementById('phone').value = data.phone || '';
                    document.getElementById('email').value = data.email || '';
                    document.getElementById('address').value = data.address || '';
                    document.getElementById('status').value = data.status || 'active';
                    document.getElementById('discount_percentage').value = data.discount_percentage || '0';
                    document.getElementById('discount_amount').value = data.discount_amount || '0';
                    document.getElementById('notes').value = data.notes || '';
                } else {
                    form.reset();
                    document.getElementById('formId').value = '';
                    document.getElementById('status').value = 'active';
                    document.getElementById('discount_percentage').value = '0';
                    document.getElementById('discount_amount').value = '0';
                }
                
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };
            
            closeModal = function() {
                var modal = document.getElementById('modal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    document.getElementById('customerForm').reset();
                }
            };
            
            openTransactionModal = function(customerId, customerName) {
                var modal = document.getElementById('transactionModal');
                if (!modal) {
                    console.error('Transaction modal not found');
                    return;
                }
                
                document.getElementById('transactionCustomerId').value = customerId;
                document.getElementById('transactionCustomerName').textContent = customerName;
                document.getElementById('transactionForm').reset();
                document.getElementById('transactionType').value = 'credit';
                document.getElementById('transactionOrderId').value = '';
                
                // Populate order dropdown
                var orderSelect = document.getElementById('transactionOrderId');
                orderSelect.innerHTML = '<option value="">Select Order (Optional)</option>';
                ordersList.forEach(function(order) {
                    var option = document.createElement('option');
                    option.value = order.id;
                    option.textContent = order.order_number + ' - Rs ' + parseFloat(order.total_amount).toFixed(2) + ' (' + order.order_date + ')';
                    orderSelect.appendChild(option);
                });
                
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };
            
            closeTransactionModal = function() {
                var modal = document.getElementById('transactionModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    document.getElementById('transactionForm').reset();
                }
            };
            
            deleteRecord = function(id) {
                if (confirm('Are you sure you want to delete this regular customer?')) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '">';
                    document.body.appendChild(form);
                    form.submit();
                }
            };
            
            // Close modal when clicking outside
            window.onclick = function(event) {
                var modal = document.getElementById('modal');
                var transactionModal = document.getElementById('transactionModal');
                if (event.target == modal) {
                    closeModal();
                }
                if (event.target == transactionModal) {
                    closeTransactionModal();
                }
            };
        })();
    </script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <?php include 'includes/nav.php'; ?>

        <main class="md:ml-64 p-4 md:p-6 lg:p-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-6 md:mb-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">Regular Customers</h1>
                            <p class="text-gray-600">Manage your regular customers database</p>
                        </div>
                        <button 
                            onclick="openModal('create', null)" 
                            class="px-4 md:px-6 py-2 md:py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg font-semibold hover:from-indigo-700 hover:to-purple-700 transform hover:scale-[1.02] transition-all duration-200 shadow-lg hover:shadow-xl flex items-center gap-2"
                        >
                            <span>➕</span>
                            <span>Add New Customer</span>
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800 border border-green-300' : 'bg-red-100 text-red-800 border border-red-300'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <!-- Customers Table -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Customer Name</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Phone</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Discount %</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Discount Amount</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Due Amount</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Total Amount</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($customers->num_rows > 0): ?>
                                    <?php while ($row = $customers->fetch_assoc()): ?>
                                    <tr class="hover:bg-indigo-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $row['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($row['phone']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($row['email'] ?: 'N/A'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-indigo-600">
                                            <?php echo number_format($row['discount_percentage'] ?? 0, 2); ?>%
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-purple-600">
                                            Rs <?php echo number_format($row['discount_amount'] ?? 0, 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold <?php echo ($row['due_amount'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                            Rs <?php echo number_format($row['due_amount'] ?? 0, 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                            Rs <?php echo number_format($row['total_amount'] ?? 0, 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $statusClass = $row['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <button onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($row)); ?>)" class="px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors text-xs">
                                                Edit
                                            </button>
                                            <button onclick="openTransactionModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['customer_name']); ?>')" class="px-3 py-1 bg-purple-500 text-white rounded-md hover:bg-purple-600 transition-colors text-xs">
                                                💳 Transaction
                                            </button>
                                            <button onclick="deleteRecord(<?php echo $row['id']; ?>)" class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors text-xs">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="px-6 py-8 text-center text-gray-500">
                                            No regular customers found. Click "Add New Customer" to create one.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 id="modalTitle" class="text-xl font-bold text-gray-900">Add New Regular Customer</h2>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <form id="customerForm" method="POST" class="p-6 space-y-4">
                <input type="hidden" id="formAction" name="action" value="create">
                <input type="hidden" id="formId" name="id" value="">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Customer Name <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="customer_name" 
                            name="customer_name" 
                            required 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                            placeholder="Enter customer name"
                        >
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Phone <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            required 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                            placeholder="Enter phone number"
                        >
                    </div>
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email <span class="text-gray-500 text-xs">(Optional)</span>
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                        placeholder="Enter email address"
                    >
                </div>
                
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                        Address <span class="text-gray-500 text-xs">(Optional)</span>
                    </label>
                    <textarea 
                        id="address" 
                        name="address" 
                        rows="2"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                        placeholder="Enter customer address"
                    ></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select 
                            id="status" 
                            name="status" 
                            required 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="discount_percentage" class="block text-sm font-medium text-gray-700 mb-2">
                            Discount Percentage <span class="text-gray-500 text-xs">(0-100%)</span>
                        </label>
                        <input 
                            type="number" 
                            id="discount_percentage" 
                            name="discount_percentage" 
                            min="0" 
                            max="100" 
                            step="0.01"
                            value="0"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                            placeholder="Enter discount percentage (e.g., 10 for 10%)"
                        >
                    </div>
                    
                    <div>
                        <label for="discount_amount" class="block text-sm font-medium text-gray-700 mb-2">
                            Discount Amount <span class="text-gray-500 text-xs">(Fixed amount in Rs)</span>
                        </label>
                        <input 
                            type="number" 
                            id="discount_amount" 
                            name="discount_amount" 
                            min="0" 
                            step="0.01"
                            value="0"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                            placeholder="Enter fixed discount amount (e.g., 50 for Rs 50)"
                        >
                    </div>
                </div>
                
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                        Notes <span class="text-gray-500 text-xs">(Optional)</span>
                    </label>
                    <textarea 
                        id="notes" 
                        name="notes" 
                        rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                        placeholder="Additional notes about the customer"
                    ></textarea>
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button 
                        type="submit" 
                        class="flex-1 bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-2 rounded-lg font-semibold hover:from-indigo-700 hover:to-purple-700 transition-all duration-200"
                    >
                        Save
                    </button>
                    <button 
                        type="button" 
                        onclick="closeModal()" 
                        class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg font-semibold hover:bg-gray-300 transition-all duration-200"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transaction Modal -->
    <div id="transactionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Add Transaction</h2>
                        <p class="text-sm text-gray-600 mt-1">Customer: <span id="transactionCustomerName" class="font-semibold"></span></p>
                    </div>
                    <button onclick="closeTransactionModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <form id="transactionForm" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="add_transaction">
                <input type="hidden" id="transactionCustomerId" name="customer_id" value="">
                
                <div>
                    <label for="transactionType" class="block text-sm font-medium text-gray-700 mb-2">
                        Transaction Type <span class="text-red-500">*</span>
                    </label>
                    <select 
                        id="transactionType" 
                        name="transaction_type" 
                        required 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                    >
                        <option value="credit">Credit (Add to Due Amount)</option>
                        <option value="payment">Payment (Reduce Due Amount)</option>
                        <option value="order">Order (Add to Both Due & Total)</option>
                    </select>
                </div>
                
                <div>
                    <label for="transactionAmount" class="block text-sm font-medium text-gray-700 mb-2">
                        Amount <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="number" 
                        id="transactionAmount" 
                        name="amount" 
                        required 
                        step="0.01"
                        min="0.01"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                        placeholder="Enter amount"
                    >
                </div>
                
                <div>
                    <label for="transactionOrderId" class="block text-sm font-medium text-gray-700 mb-2">
                        Link to Order <span class="text-gray-500 text-xs">(Optional)</span>
                    </label>
                    <select 
                        id="transactionOrderId" 
                        name="order_id" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                    >
                        <option value="">Select Order (Optional)</option>
                    </select>
                </div>
                
                <div>
                    <label for="transactionReference" class="block text-sm font-medium text-gray-700 mb-2">
                        Reference Number <span class="text-gray-500 text-xs">(Optional)</span>
                    </label>
                    <input 
                        type="text" 
                        id="transactionReference" 
                        name="reference_number" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                        placeholder="Enter reference number (e.g., receipt number)"
                    >
                </div>
                
                <div>
                    <label for="transactionDescription" class="block text-sm font-medium text-gray-700 mb-2">
                        Description <span class="text-gray-500 text-xs">(Optional)</span>
                    </label>
                    <textarea 
                        id="transactionDescription" 
                        name="description" 
                        rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                        placeholder="Enter transaction description or notes"
                    ></textarea>
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button 
                        type="submit" 
                        class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-2 rounded-lg font-semibold hover:from-purple-700 hover:to-indigo-700 transition-all duration-200"
                    >
                        Add Transaction
                    </button>
                    <button 
                        type="button" 
                        onclick="closeTransactionModal()" 
                        class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg font-semibold hover:bg-gray-300 transition-all duration-200"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>

