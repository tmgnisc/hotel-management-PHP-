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
            $booking_reference = 'BK' . date('Ymd') . rand(1000, 9999);
            $customer_name = trim($_POST['customer_name'] ?? '');
            $customer_phone = trim($_POST['customer_phone'] ?? '');
            $room_id = intval($_POST['room_id'] ?? 0);
            $check_in_datetime = trim($_POST['check_in_datetime'] ?? '');
            $check_out_datetime = trim($_POST['check_out_datetime'] ?? '');
            $status = trim($_POST['status'] ?? '');
            $details = trim($_POST['details'] ?? '');
            $payment_status = trim($_POST['payment_status'] ?? 'pending');
            $payment_method = trim($_POST['payment_method'] ?? 'cash');
            $payment_amount = floatval($_POST['payment_amount'] ?? 0);
            $total_amount = floatval($_POST['total_amount'] ?? 0);
            
            if (empty($customer_name) || empty($customer_phone) || $room_id <= 0 || empty($check_in_datetime) || empty($check_out_datetime) || empty($status)) {
                $message = "Please fill all required fields correctly!";
                $messageType = 'error';
            } elseif ($payment_amount < 0) {
                $message = "Payment amount cannot be negative!";
                $messageType = 'error';
            } elseif ($payment_amount > $total_amount) {
                $message = "Payment amount cannot exceed total amount!";
                $messageType = 'error';
            } else {
                $stmt = $conn->prepare("INSERT INTO bookings (booking_reference, customer_name, customer_phone, room_id, check_in_date, check_out_date, status, special_requests, room_type, number_of_guests, total_amount, payment_status, payment_method, payment_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'normal_room', 1, ?, ?, ?, ?)");
                if ($stmt) {
                    // Convert datetime to date format for database
                    $check_in_date = date('Y-m-d', strtotime($check_in_datetime));
                    $check_out_date = date('Y-m-d', strtotime($check_out_datetime));
                    
                    $stmt->bind_param("sssissssdssd", $booking_reference, $customer_name, $customer_phone, $room_id, $check_in_date, $check_out_date, $status, $details, $total_amount, $payment_status, $payment_method, $payment_amount);
                    if ($stmt->execute()) {
                        $message = "Booking created successfully!";
                        $messageType = 'success';
                        $stmt->close();
                        $conn->close();
                        header('Location: bookings.php?msg=' . urlencode($message) . '&type=' . $messageType);
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
            $customer_name = trim($_POST['customer_name'] ?? '');
            $customer_phone = trim($_POST['customer_phone'] ?? '');
            $room_id = intval($_POST['room_id'] ?? 0);
            $check_in_datetime = trim($_POST['check_in_datetime'] ?? '');
            $check_out_datetime = trim($_POST['check_out_datetime'] ?? '');
            $status = trim($_POST['status'] ?? '');
            $details = trim($_POST['details'] ?? '');
            $payment_status = trim($_POST['payment_status'] ?? 'pending');
            $payment_method = trim($_POST['payment_method'] ?? 'cash');
            $payment_amount = floatval($_POST['payment_amount'] ?? 0);
            $total_amount = floatval($_POST['total_amount'] ?? 0);
            
            if (empty($customer_name) || empty($customer_phone) || $room_id <= 0 || empty($check_in_datetime) || empty($check_out_datetime) || empty($status) || $id <= 0) {
                $message = "Please fill all required fields correctly!";
                $messageType = 'error';
            } elseif ($payment_amount < 0) {
                $message = "Payment amount cannot be negative!";
                $messageType = 'error';
            } elseif ($payment_amount > $total_amount) {
                $message = "Payment amount cannot exceed total amount!";
                $messageType = 'error';
            } else {
                // Convert datetime to date format for database
                $check_in_date = date('Y-m-d', strtotime($check_in_datetime));
                $check_out_date = date('Y-m-d', strtotime($check_out_datetime));
                
                $stmt = $conn->prepare("UPDATE bookings SET customer_name=?, customer_phone=?, room_id=?, check_in_date=?, check_out_date=?, status=?, special_requests=?, total_amount=?, payment_status=?, payment_method=?, payment_amount=? WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("ssissssdssdi", $customer_name, $customer_phone, $room_id, $check_in_date, $check_out_date, $status, $details, $total_amount, $payment_status, $payment_method, $payment_amount, $id);
                    if ($stmt->execute()) {
                        $message = "Booking updated successfully!";
                        $messageType = 'success';
                        $stmt->close();
                        $conn->close();
                        header('Location: bookings.php?msg=' . urlencode($message) . '&type=' . $messageType);
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
                $stmt = $conn->prepare("DELETE FROM bookings WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $message = "Booking deleted successfully!";
                        $messageType = 'success';
                        $stmt->close();
                        $conn->close();
                        header('Location: bookings.php?msg=' . urlencode($message) . '&type=' . $messageType);
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

// Fetch all bookings with room information
$bookings = $conn->query("
    SELECT b.*, nr.room_number, nr.room_type, nr.capacity_type 
    FROM bookings b 
    LEFT JOIN normal_rooms nr ON b.room_id = nr.id 
    ORDER BY b.created_at DESC
");
if (!$bookings) {
    die("Error fetching bookings: " . $conn->error);
}

// Fetch rooms for dropdown
$rooms = $conn->query("SELECT id, room_number, room_type, capacity_type, status FROM normal_rooms WHERE status != 'maintenance' ORDER BY room_number");
if (!$rooms) {
    die("Error fetching rooms: " . $conn->error);
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        var openModal, closeModal, deleteRecord;
        
        (function() {
            openModal = function(action, data) {
                var modal = document.getElementById('modal');
                var form = document.getElementById('bookingForm');
                
                if (!modal || !form) {
                    console.error('Modal or form not found');
                    return;
                }
                
                // Set form action: 'create' for new, 'update' for edit
                var formAction = action === 'edit' ? 'update' : 'create';
                document.getElementById('formAction').value = formAction;
                document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New Booking' : 'Edit Booking';
                
                if (action === 'edit' && data) {
                    document.getElementById('formId').value = data.id || '';
                    document.getElementById('customer_name').value = data.customer_name || '';
                    document.getElementById('customer_phone').value = data.customer_phone || '';
                    document.getElementById('room_id').value = data.room_id || '';
                    
                    // Format dates for datetime-local input
                    var checkInDate = data.check_in_date ? new Date(data.check_in_date + 'T00:00:00').toISOString().slice(0, 16) : '';
                    var checkOutDate = data.check_out_date ? new Date(data.check_out_date + 'T00:00:00').toISOString().slice(0, 16) : '';
                    
                    document.getElementById('check_in_datetime').value = checkInDate;
                    document.getElementById('check_out_datetime').value = checkOutDate;
                    document.getElementById('status').value = data.status || '';
                    document.getElementById('total_amount').value = data.total_amount || '0';
                    document.getElementById('payment_status').value = data.payment_status || 'pending';
                    document.getElementById('payment_method').value = data.payment_method || 'cash';
                    document.getElementById('payment_amount').value = data.payment_amount || '0';
                    document.getElementById('details').value = data.special_requests || '';
                } else {
                    form.reset();
                    document.getElementById('formId').value = '';
                    document.getElementById('total_amount').value = '0';
                    document.getElementById('payment_status').value = 'pending';
                    document.getElementById('payment_method').value = 'cash';
                    document.getElementById('payment_amount').value = '0';
                }
                
                modal.classList.remove('hidden');
            };
            
            closeModal = function() {
                var modal = document.getElementById('modal');
                if (modal) {
                    modal.classList.add('hidden');
                }
            };
            
            deleteRecord = function(id) {
                if (confirm('Are you sure you want to delete this booking?')) {
                    document.getElementById('deleteId').value = id;
                    document.getElementById('deleteForm').submit();
                }
            };
        })();
    </script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <?php include 'includes/nav.php'; ?>

        <main class="md:ml-64 p-4 md:p-6 lg:p-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900">Bookings Management</h2>
                <button onclick="openModal('create')" class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                    + Add New Booking
                </button>
            </div>

            <?php if ($message): ?>
                <div class="mb-6 p-4 <?php echo $messageType === 'success' ? 'bg-green-50 border-l-4 border-green-500 text-green-700' : 'bg-red-50 border-l-4 border-red-500 text-red-700'; ?> rounded-lg animate-slide-up">
                    <?php echo htmlspecialchars($message); ?>
                    <button onclick="this.parentElement.remove()" class="float-right text-gray-500 hover:text-gray-700">×</button>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">ID</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Reference</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Phone</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Room</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Check In</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Check Out</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Total Amount</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Payment</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($row = $bookings->fetch_assoc()): ?>
                            <tr class="hover:bg-indigo-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $row['id']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['booking_reference']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($row['customer_phone'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php if ($row['room_number']): ?>
                                        <span class="font-medium"><?php echo htmlspecialchars($row['room_number']); ?></span>
                                        <span class="text-xs text-gray-500 ml-1">(<?php echo htmlspecialchars($row['room_type'] ?? 'N/A'); ?>)</span>
                                    <?php else: ?>
                                        <span class="text-gray-400">Room #<?php echo $row['room_id']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo $row['check_in_date'] ? date('M d, Y', strtotime($row['check_in_date'])) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo $row['check_out_date'] ? date('M d, Y', strtotime($row['check_out_date'])) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                    Rs <?php echo number_format($row['total_amount'] ?? 0, 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $paymentStatusClass = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'paid' => 'bg-green-100 text-green-800',
                                        'partial' => 'bg-orange-100 text-orange-800',
                                        'cancelled' => 'bg-red-100 text-red-800'
                                    ];
                                    $paymentStatus = $row['payment_status'] ?? 'pending';
                                    $paymentClass = $paymentStatusClass[$paymentStatus] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <div class="flex flex-col gap-1">
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold capitalize <?php echo $paymentClass; ?>">
                                            <?php echo $paymentStatus; ?>
                                        </span>
                                        <span class="text-xs text-gray-600">
                                            <?php echo htmlspecialchars($row['payment_method'] ?? 'N/A'); ?> - Rs <?php echo number_format($row['payment_amount'] ?? 0, 2); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusClass = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'confirmed' => 'bg-blue-100 text-blue-800',
                                        'checked_in' => 'bg-green-100 text-green-800',
                                        'checked_out' => 'bg-gray-100 text-gray-800',
                                        'cancelled' => 'bg-red-100 text-red-800'
                                    ];
                                    $status = $row['status'];
                                    $class = $statusClass[$status] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold capitalize <?php echo $class; ?>">
                                        <?php echo str_replace('_', ' ', $status); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($row)); ?>)" class="px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors text-xs">
                                        Edit
                                    </button>
                                    <button onclick="deleteRecord(<?php echo $row['id']; ?>)" class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors text-xs">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-6 md:p-8 animate-slide-up max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 id="modalTitle" class="text-2xl font-bold text-gray-900">Add New Booking</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <form method="POST" id="bookingForm" class="space-y-4">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId">
                
                <div>
                    <label for="customer_name" class="block text-sm font-semibold text-gray-700 mb-2">Customer Name *</label>
                    <input type="text" id="customer_name" name="customer_name" required placeholder="Enter customer name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
                
                <div>
                    <label for="customer_phone" class="block text-sm font-semibold text-gray-700 mb-2">Customer Phone Number *</label>
                    <input type="tel" id="customer_phone" name="customer_phone" required placeholder="Enter phone number" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
                
                <div>
                    <label for="room_id" class="block text-sm font-semibold text-gray-700 mb-2">Room Number *</label>
                    <select id="room_id" name="room_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        <option value="">Select Room</option>
                        <?php
                        $rooms->data_seek(0);
                        while ($room = $rooms->fetch_assoc()): ?>
                            <option value="<?php echo $room['id']; ?>">
                                <?php echo htmlspecialchars($room['room_number']); ?> - <?php echo ucfirst($room['room_type']); ?> (<?php echo ucfirst($room['capacity_type']); ?>) - <?php echo ucfirst($room['status']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="check_in_datetime" class="block text-sm font-semibold text-gray-700 mb-2">Check-In Date & Time *</label>
                        <input type="datetime-local" id="check_in_datetime" name="check_in_datetime" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>
                    
                    <div>
                        <label for="check_out_datetime" class="block text-sm font-semibold text-gray-700 mb-2">Check-Out Date & Time *</label>
                        <input type="datetime-local" id="check_out_datetime" name="check_out_datetime" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-semibold text-gray-700 mb-2">Status *</label>
                    <select id="status" name="status" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="checked_in">Checked In</option>
                        <option value="checked_out">Checked Out</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="border-t border-gray-200 pt-4 mt-4">
                    <h4 class="text-lg font-semibold text-gray-800 mb-3">Payment Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="total_amount" class="block text-sm font-semibold text-gray-700 mb-2">Total Amount (Rs) *</label>
                            <input type="number" id="total_amount" name="total_amount" step="0.01" min="0" value="0" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" placeholder="0.00">
                        </div>
                        
                        <div>
                            <label for="payment_status" class="block text-sm font-semibold text-gray-700 mb-2">Payment Status *</label>
                            <select id="payment_status" name="payment_status" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="partial">Partial</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="payment_method" class="block text-sm font-semibold text-gray-700 mb-2">Payment Method *</label>
                            <select id="payment_method" name="payment_method" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="online">Online</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label for="payment_amount" class="block text-sm font-semibold text-gray-700 mb-2">Payment Amount (Rs) *</label>
                        <input type="number" id="payment_amount" name="payment_amount" step="0.01" min="0" value="0" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" placeholder="0.00">
                        <p class="text-xs text-gray-500 mt-1">Enter the amount paid. For partial payments, enter the amount received.</p>
                    </div>
                </div>
                
                <div>
                    <label for="details" class="block text-sm font-semibold text-gray-700 mb-2">Details</label>
                    <textarea id="details" name="details" rows="4" placeholder="Enter booking details, special requests, or notes..." class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"></textarea>
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
            
            // Validate check-out date is after check-in date
            var checkInInput = document.getElementById('check_in_datetime');
            var checkOutInput = document.getElementById('check_out_datetime');
            
            function validateDates() {
                if (checkInInput.value && checkOutInput.value) {
                    var checkIn = new Date(checkInInput.value);
                    var checkOut = new Date(checkOutInput.value);
                    
                    if (checkOut <= checkIn) {
                        checkOutInput.setCustomValidity('Check-out date must be after check-in date');
                    } else {
                        checkOutInput.setCustomValidity('');
                    }
                }
            }
            
            checkInInput.addEventListener('change', validateDates);
            checkOutInput.addEventListener('change', validateDates);
        });
    </script>
</body>
</html>
