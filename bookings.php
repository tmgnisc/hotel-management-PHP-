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
            $booking_reference = 'BK' . date('Ymd') . rand(1000, 9999);
            $customer_name = $_POST['customer_name'];
            $customer_email = $_POST['customer_email'] ?? '';
            $customer_phone = $_POST['customer_phone'] ?? '';
            $room_type = $_POST['room_type'];
            $room_id = $_POST['room_id'];
            $check_in_date = $_POST['check_in_date'];
            $check_out_date = $_POST['check_out_date'];
            $number_of_guests = $_POST['number_of_guests'];
            $total_amount = $_POST['total_amount'];
            $status = $_POST['status'];
            $special_requests = $_POST['special_requests'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO bookings (booking_reference, customer_name, customer_email, customer_phone, room_type, room_id, check_in_date, check_out_date, number_of_guests, total_amount, status, special_requests) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssisssiss", $booking_reference, $customer_name, $customer_email, $customer_phone, $room_type, $room_id, $check_in_date, $check_out_date, $number_of_guests, $total_amount, $status, $special_requests);
            if ($stmt->execute()) {
                $message = "Booking created successfully!";
            } else {
                $message = "Error: " . $conn->error;
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'update') {
            $id = $_POST['id'];
            $customer_name = $_POST['customer_name'];
            $customer_email = $_POST['customer_email'] ?? '';
            $customer_phone = $_POST['customer_phone'] ?? '';
            $room_type = $_POST['room_type'];
            $room_id = $_POST['room_id'];
            $check_in_date = $_POST['check_in_date'];
            $check_out_date = $_POST['check_out_date'];
            $number_of_guests = $_POST['number_of_guests'];
            $total_amount = $_POST['total_amount'];
            $status = $_POST['status'];
            $special_requests = $_POST['special_requests'] ?? '';
            
            $stmt = $conn->prepare("UPDATE bookings SET customer_name=?, customer_email=?, customer_phone=?, room_type=?, room_id=?, check_in_date=?, check_out_date=?, number_of_guests=?, total_amount=?, status=?, special_requests=? WHERE id=?");
            $stmt->bind_param("ssssisssissi", $customer_name, $customer_email, $customer_phone, $room_type, $room_id, $check_in_date, $check_out_date, $number_of_guests, $total_amount, $status, $special_requests, $id);
            if ($stmt->execute()) {
                $message = "Booking updated successfully!";
            } else {
                $message = "Error: " . $conn->error;
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM bookings WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "Booking deleted successfully!";
            } else {
                $message = "Error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch all bookings
$bookings = $conn->query("SELECT * FROM bookings ORDER BY created_at DESC");

// Fetch rooms for dropdown
$normal_rooms = $conn->query("SELECT id, room_number FROM normal_rooms ORDER BY room_number");
$cabin_rooms = $conn->query("SELECT id, room_number FROM cabin_rooms ORDER BY room_number");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/nav.php'; ?>

        <main class="dashboard-main">
            <div class="page-header">
                <h2>Bookings Management</h2>
                <button class="btn btn-primary" onclick="openModal('create')">Add New Booking</button>
            </div>

            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Room Type</th>
                            <th>Room ID</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Guests</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $bookings->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['booking_reference']); ?></td>
                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $row['room_type'])); ?></td>
                            <td><?php echo $row['room_id']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['check_in_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['check_out_date'])); ?></td>
                            <td><?php echo $row['number_of_guests']; ?></td>
                            <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                            <td><span class="status-badge status-<?php echo $row['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?></span></td>
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
            <h3 id="modalTitle">Add New Booking</h3>
            <form method="POST" id="bookingForm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId">
                
                <div class="form-group">
                    <label for="customer_name">Customer Name *</label>
                    <input type="text" id="customer_name" name="customer_name" required>
                </div>
                
                <div class="form-group">
                    <label for="customer_email">Customer Email</label>
                    <input type="email" id="customer_email" name="customer_email">
                </div>
                
                <div class="form-group">
                    <label for="customer_phone">Customer Phone</label>
                    <input type="text" id="customer_phone" name="customer_phone">
                </div>
                
                <div class="form-group">
                    <label for="room_type">Room Type *</label>
                    <select id="room_type" name="room_type" required onchange="updateRoomOptions()">
                        <option value="normal_room">Normal Room</option>
                        <option value="cabin_room">Cabin Room</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="room_id">Room *</label>
                    <select id="room_id" name="room_id" required>
                        <?php
                        $normal_rooms->data_seek(0);
                        while ($room = $normal_rooms->fetch_assoc()): ?>
                            <option value="<?php echo $room['id']; ?>" data-type="normal_room"><?php echo htmlspecialchars($room['room_number']); ?></option>
                        <?php endwhile; ?>
                        <?php
                        $cabin_rooms->data_seek(0);
                        while ($room = $cabin_rooms->fetch_assoc()): ?>
                            <option value="<?php echo $room['id']; ?>" data-type="cabin_room"><?php echo htmlspecialchars($room['room_number']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="check_in_date">Check In Date *</label>
                    <input type="date" id="check_in_date" name="check_in_date" required>
                </div>
                
                <div class="form-group">
                    <label for="check_out_date">Check Out Date *</label>
                    <input type="date" id="check_out_date" name="check_out_date" required>
                </div>
                
                <div class="form-group">
                    <label for="number_of_guests">Number of Guests *</label>
                    <input type="number" id="number_of_guests" name="number_of_guests" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="total_amount">Total Amount *</label>
                    <input type="number" id="total_amount" name="total_amount" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="checked_in">Checked In</option>
                        <option value="checked_out">Checked Out</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="special_requests">Special Requests</label>
                    <textarea id="special_requests" name="special_requests" rows="3"></textarea>
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
        function updateRoomOptions() {
            const roomType = document.getElementById('room_type').value;
            const roomSelect = document.getElementById('room_id');
            const options = roomSelect.querySelectorAll('option');
            
            options.forEach(option => {
                if (option.dataset.type === roomType) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
            
            // Select first visible option
            const visibleOptions = Array.from(options).filter(opt => opt.style.display !== 'none');
            if (visibleOptions.length > 0) {
                roomSelect.value = visibleOptions[0].value;
            }
        }
        
        function openModal(action, data = null) {
            const modal = document.getElementById('modal');
            const form = document.getElementById('bookingForm');
            document.getElementById('formAction').value = action;
            document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New Booking' : 'Edit Booking';
            
            if (action === 'edit' && data) {
                document.getElementById('formId').value = data.id;
                document.getElementById('customer_name').value = data.customer_name;
                document.getElementById('customer_email').value = data.customer_email || '';
                document.getElementById('customer_phone').value = data.customer_phone || '';
                document.getElementById('room_type').value = data.room_type;
                updateRoomOptions();
                document.getElementById('room_id').value = data.room_id;
                document.getElementById('check_in_date').value = data.check_in_date;
                document.getElementById('check_out_date').value = data.check_out_date;
                document.getElementById('number_of_guests').value = data.number_of_guests;
                document.getElementById('total_amount').value = data.total_amount;
                document.getElementById('status').value = data.status;
                document.getElementById('special_requests').value = data.special_requests || '';
            } else {
                form.reset();
                document.getElementById('formId').value = '';
                updateRoomOptions();
            }
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }
        
        function deleteRecord(id) {
            if (confirm('Are you sure you want to delete this booking?')) {
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

