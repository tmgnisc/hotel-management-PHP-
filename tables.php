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
            $table_number = $_POST['table_number'];
            $capacity = $_POST['capacity'];
            $status = $_POST['status'];
            $location = $_POST['location'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO tables (table_number, capacity, status, location) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siss", $table_number, $capacity, $status, $location);
            if ($stmt->execute()) {
                $message = "Table created successfully!";
            } else {
                $message = "Error: " . $conn->error;
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'update') {
            $id = $_POST['id'];
            $table_number = $_POST['table_number'];
            $capacity = $_POST['capacity'];
            $status = $_POST['status'];
            $location = $_POST['location'] ?? '';
            
            $stmt = $conn->prepare("UPDATE tables SET table_number=?, capacity=?, status=?, location=? WHERE id=?");
            $stmt->bind_param("sissi", $table_number, $capacity, $status, $location, $id);
            if ($stmt->execute()) {
                $message = "Table updated successfully!";
            } else {
                $message = "Error: " . $conn->error;
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM tables WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "Table deleted successfully!";
            } else {
                $message = "Error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch all tables
$tables = $conn->query("SELECT * FROM tables ORDER BY table_number");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Tables - Admin Dashboard</title>
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
                <li><a href="tables.php" class="active">Restaurant Tables</a></li>
                <li><a href="cabin_rooms.php">Cabin Rooms</a></li>
                <li><a href="normal_rooms.php">Normal Rooms</a></li>
                <li><a href="bookings.php">Bookings</a></li>
                <li><a href="order_details.php">Order Details</a></li>
            </ul>
        </nav>

        <main class="dashboard-main">
            <div class="page-header">
                <h2>Restaurant Tables Management</h2>
                <button class="btn btn-primary" onclick="openModal('create')">Add New Table</button>
            </div>

            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Table Number</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $tables->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['table_number']); ?></td>
                            <td><?php echo $row['capacity']; ?></td>
                            <td><span class="status-badge status-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['location']); ?></td>
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
            <h3 id="modalTitle">Add New Table</h3>
            <form method="POST" id="tableForm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId">
                
                <div class="form-group">
                    <label for="table_number">Table Number *</label>
                    <input type="text" id="table_number" name="table_number" required>
                </div>
                
                <div class="form-group">
                    <label for="capacity">Capacity *</label>
                    <input type="number" id="capacity" name="capacity" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="reserved">Reserved</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location">
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
            const form = document.getElementById('tableForm');
            document.getElementById('formAction').value = action;
            document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New Table' : 'Edit Table';
            
            if (action === 'edit' && data) {
                document.getElementById('formId').value = data.id;
                document.getElementById('table_number').value = data.table_number;
                document.getElementById('capacity').value = data.capacity;
                document.getElementById('status').value = data.status;
                document.getElementById('location').value = data.location || '';
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
            if (confirm('Are you sure you want to delete this table?')) {
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

