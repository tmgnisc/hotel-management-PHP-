<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$message = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'create') {
            $order_number = 'ORD' . date('Ymd') . rand(1000, 9999);
            $table_id = $_POST['table_id'] ?: null;
            $booking_id = $_POST['booking_id'] ?: null;
            $customer_name = $_POST['customer_name'];
            $order_date = $_POST['order_date'];
            $items = $_POST['items'];
            $subtotal = $_POST['subtotal'];
            $tax = $_POST['tax'] ?? 0;
            $discount = $_POST['discount'] ?? 0;
            $total_amount = $_POST['total_amount'];
            $payment_status = $_POST['payment_status'];
            $payment_method = $_POST['payment_method'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO order_details (order_number, table_id, booking_id, customer_name, order_date, items, subtotal, tax, discount, total_amount, payment_status, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siisssddddsss", $order_number, $table_id, $booking_id, $customer_name, $order_date, $items, $subtotal, $tax, $discount, $total_amount, $payment_status, $payment_method, $notes);
            if ($stmt->execute()) {
                $message = "Order created successfully!";
            } else {
                $message = "Error: " . $conn->error;
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'update') {
            $id = $_POST['id'];
            $table_id = $_POST['table_id'] ?: null;
            $booking_id = $_POST['booking_id'] ?: null;
            $customer_name = $_POST['customer_name'];
            $order_date = $_POST['order_date'];
            $items = $_POST['items'];
            $subtotal = $_POST['subtotal'];
            $tax = $_POST['tax'] ?? 0;
            $discount = $_POST['discount'] ?? 0;
            $total_amount = $_POST['total_amount'];
            $payment_status = $_POST['payment_status'];
            $payment_method = $_POST['payment_method'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            $stmt = $conn->prepare("UPDATE order_details SET table_id=?, booking_id=?, customer_name=?, order_date=?, items=?, subtotal=?, tax=?, discount=?, total_amount=?, payment_status=?, payment_method=?, notes=? WHERE id=?");
            $stmt->bind_param("iisssddddsssi", $table_id, $booking_id, $customer_name, $order_date, $items, $subtotal, $tax, $discount, $total_amount, $payment_status, $payment_method, $notes, $id);
            if ($stmt->execute()) {
                $message = "Order updated successfully!";
            } else {
                $message = "Error: " . $conn->error;
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM order_details WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "Order deleted successfully!";
            } else {
                $message = "Error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch all orders
$orders = $conn->query("SELECT o.*, t.table_number, b.booking_reference FROM order_details o LEFT JOIN tables t ON o.table_id = t.id LEFT JOIN bookings b ON o.booking_id = b.id ORDER BY o.order_date DESC");

// Fetch tables and bookings for dropdowns
$tables = $conn->query("SELECT id, table_number FROM tables ORDER BY table_number");
$bookings = $conn->query("SELECT id, booking_reference FROM bookings ORDER BY booking_reference");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/nav.php'; ?>

        <main class="dashboard-main">
            <div class="page-header">
                <h2>Order Details Management</h2>
                <button class="btn btn-primary" onclick="openModal('create')">Add New Order</button>
            </div>

            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Order Number</th>
                            <th>Customer</th>
                            <th>Table</th>
                            <th>Booking</th>
                            <th>Order Date</th>
                            <th>Total Amount</th>
                            <th>Payment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $orders->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['order_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                            <td><?php echo $row['table_number'] ?? 'N/A'; ?></td>
                            <td><?php echo $row['booking_reference'] ?? 'N/A'; ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($row['order_date'])); ?></td>
                            <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                            <td><span class="status-badge status-<?php echo $row['payment_status']; ?>"><?php echo ucfirst($row['payment_status']); ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-edit" onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($row)); ?>)">Edit</button>
                                <button class="btn btn-sm btn-delete" onclick="deleteRecord(<?php echo $row['id']; ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 id="modalTitle">Add New Order</h3>
            <form method="POST" id="orderForm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId">
                
                <div class="form-group">
                    <label for="customer_name">Customer Name *</label>
                    <input type="text" id="customer_name" name="customer_name" required>
                </div>
                
                <div class="form-group">
                    <label for="table_id">Table (Optional)</label>
                    <select id="table_id" name="table_id">
                        <option value="">None</option>
                        <?php
                        $tables->data_seek(0);
                        while ($table = $tables->fetch_assoc()): ?>
                            <option value="<?php echo $table['id']; ?>"><?php echo htmlspecialchars($table['table_number']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="booking_id">Booking (Optional)</label>
                    <select id="booking_id" name="booking_id">
                        <option value="">None</option>
                        <?php
                        $bookings->data_seek(0);
                        while ($booking = $bookings->fetch_assoc()): ?>
                            <option value="<?php echo $booking['id']; ?>"><?php echo htmlspecialchars($booking['booking_reference']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="order_date">Order Date *</label>
                    <input type="datetime-local" id="order_date" name="order_date" required>
                </div>
                
                <div class="form-group">
                    <label for="items">Items *</label>
                    <textarea id="items" name="items" rows="4" required placeholder="e.g., Pizza x2, Pasta x1, Drinks x3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="subtotal">Subtotal *</label>
                    <input type="number" id="subtotal" name="subtotal" step="0.01" min="0" required onchange="calculateTotal()">
                </div>
                
                <div class="form-group">
                    <label for="tax">Tax</label>
                    <input type="number" id="tax" name="tax" step="0.01" min="0" value="0" onchange="calculateTotal()">
                </div>
                
                <div class="form-group">
                    <label for="discount">Discount</label>
                    <input type="number" id="discount" name="discount" step="0.01" min="0" value="0" onchange="calculateTotal()">
                </div>
                
                <div class="form-group">
                    <label for="total_amount">Total Amount *</label>
                    <input type="number" id="total_amount" name="total_amount" step="0.01" min="0" required readonly>
                </div>
                
                <div class="form-group">
                    <label for="payment_status">Payment Status *</label>
                    <select id="payment_status" name="payment_status" required>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <input type="text" id="payment_method" name="payment_method" placeholder="e.g., Cash, Card, Online">
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
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
        function calculateTotal() {
            const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
            const tax = parseFloat(document.getElementById('tax').value) || 0;
            const discount = parseFloat(document.getElementById('discount').value) || 0;
            const total = subtotal + tax - discount;
            document.getElementById('total_amount').value = total.toFixed(2);
        }
        
        function openModal(action, data = null) {
            const modal = document.getElementById('modal');
            const form = document.getElementById('orderForm');
            document.getElementById('formAction').value = action;
            document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New Order' : 'Edit Order';
            
            if (action === 'edit' && data) {
                document.getElementById('formId').value = data.id;
                document.getElementById('customer_name').value = data.customer_name;
                document.getElementById('table_id').value = data.table_id || '';
                document.getElementById('booking_id').value = data.booking_id || '';
                const orderDate = new Date(data.order_date);
                document.getElementById('order_date').value = orderDate.toISOString().slice(0, 16);
                document.getElementById('items').value = data.items || '';
                document.getElementById('subtotal').value = data.subtotal;
                document.getElementById('tax').value = data.tax || 0;
                document.getElementById('discount').value = data.discount || 0;
                document.getElementById('total_amount').value = data.total_amount;
                document.getElementById('payment_status').value = data.payment_status;
                document.getElementById('payment_method').value = data.payment_method || '';
                document.getElementById('notes').value = data.notes || '';
            } else {
                form.reset();
                document.getElementById('formId').value = '';
                const now = new Date();
                document.getElementById('order_date').value = now.toISOString().slice(0, 16);
                calculateTotal();
            }
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }
        
        function deleteRecord(id) {
            if (confirm('Are you sure you want to delete this order?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('modal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>

