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
            $room_number = $_POST['room_number'];
            $room_type = $_POST['room_type'];
            $capacity = $_POST['capacity'];
            $price_per_night = $_POST['price_per_night'];
            $amenities = $_POST['amenities'] ?? '';
            $status = $_POST['status'];
            
            $stmt = $conn->prepare("INSERT INTO normal_rooms (room_number, room_type, capacity, price_per_night, amenities, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssidss", $room_number, $room_type, $capacity, $price_per_night, $amenities, $status);
            if ($stmt->execute()) {
                $message = "Normal room created successfully!";
            } else {
                $message = "Error: " . $conn->error;
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'update') {
            $id = $_POST['id'];
            $room_number = $_POST['room_number'];
            $room_type = $_POST['room_type'];
            $capacity = $_POST['capacity'];
            $price_per_night = $_POST['price_per_night'];
            $amenities = $_POST['amenities'] ?? '';
            $status = $_POST['status'];
            
            $stmt = $conn->prepare("UPDATE normal_rooms SET room_number=?, room_type=?, capacity=?, price_per_night=?, amenities=?, status=? WHERE id=?");
            $stmt->bind_param("ssidssi", $room_number, $room_type, $capacity, $price_per_night, $amenities, $status, $id);
            if ($stmt->execute()) {
                $message = "Normal room updated successfully!";
            } else {
                $message = "Error: " . $conn->error;
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM normal_rooms WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "Normal room deleted successfully!";
            } else {
                $message = "Error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch all normal rooms
$rooms = $conn->query("SELECT * FROM normal_rooms ORDER BY room_number");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Normal Rooms - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Hotel Management System</h1>
            <div class="header-actions">
                <span class="admin-name">Admin</span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <nav class="dashboard-nav">
            <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>
            <ul class="nav-menu" id="navMenu">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="tables.php">Restaurant Tables</a></li>
                <li><a href="cabin_rooms.php">Cabin Rooms</a></li>
                <li><a href="normal_rooms.php" class="active">Normal Rooms</a></li>
                <li><a href="bookings.php">Bookings</a></li>
                <li><a href="order_details.php">Order Details</a></li>
            </ul>
        </nav>

        <main class="dashboard-main">
            <div class="page-header">
                <h2>Normal Rooms Management</h2>
                <button class="btn btn-primary" onclick="openModal('create')">Add New Room</button>
            </div>

            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Room Number</th>
                            <th>Room Type</th>
                            <th>Capacity</th>
                            <th>Price/Night</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $rooms->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['room_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['room_type']); ?></td>
                            <td><?php echo $row['capacity']; ?></td>
                            <td>$<?php echo number_format($row['price_per_night'], 2); ?></td>
                            <td><span class="status-badge status-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
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
            <h3 id="modalTitle">Add New Room</h3>
            <form method="POST" id="roomForm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId">
                
                <div class="form-group">
                    <label for="room_number">Room Number *</label>
                    <input type="text" id="room_number" name="room_number" required>
                </div>
                
                <div class="form-group">
                    <label for="room_type">Room Type *</label>
                    <input type="text" id="room_type" name="room_type" required>
                </div>
                
                <div class="form-group">
                    <label for="capacity">Capacity *</label>
                    <input type="number" id="capacity" name="capacity" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="price_per_night">Price Per Night *</label>
                    <input type="number" id="price_per_night" name="price_per_night" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="amenities">Amenities</label>
                    <textarea id="amenities" name="amenities" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
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
        function openModal(action, data = null) {
            const modal = document.getElementById('modal');
            const form = document.getElementById('roomForm');
            document.getElementById('formAction').value = action;
            document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New Room' : 'Edit Room';
            
            if (action === 'edit' && data) {
                document.getElementById('formId').value = data.id;
                document.getElementById('room_number').value = data.room_number;
                document.getElementById('room_type').value = data.room_type;
                document.getElementById('capacity').value = data.capacity;
                document.getElementById('price_per_night').value = data.price_per_night;
                document.getElementById('amenities').value = data.amenities || '';
                document.getElementById('status').value = data.status;
            } else {
                form.reset();
                document.getElementById('formId').value = '';
            }
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }
        
        function deleteRecord(id) {
            if (confirm('Are you sure you want to delete this room?')) {
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

