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
            $capacity_type = $_POST['capacity_type'];
            $status = $_POST['status'];
            $amenities = $_POST['amenities'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO normal_rooms (room_number, room_type, capacity_type, status, amenities) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $room_number, $room_type, $capacity_type, $status, $amenities);
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
            $capacity_type = $_POST['capacity_type'];
            $status = $_POST['status'];
            $amenities = $_POST['amenities'] ?? '';
            
            $stmt = $conn->prepare("UPDATE normal_rooms SET room_number=?, room_type=?, capacity_type=?, status=?, amenities=? WHERE id=?");
            $stmt->bind_param("sssssi", $room_number, $room_type, $capacity_type, $status, $amenities, $id);
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <?php include 'includes/nav.php'; ?>

        <main class="md:ml-64 p-4 md:p-6 lg:p-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900">Normal Rooms Management</h2>
                <button onclick="openModal('create')" class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                    + Add New Room
                </button>
            </div>

            <?php if ($message): ?>
                <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded-lg animate-slide-up">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">ID</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Room Number</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Room Type</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Capacity Type</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($row = $rooms->fetch_assoc()): ?>
                            <tr class="hover:bg-indigo-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $row['id']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['room_number']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 capitalize"><?php echo htmlspecialchars($row['room_type']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 capitalize"><?php echo htmlspecialchars($row['capacity_type']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusClass = [
                                        'available' => 'bg-green-100 text-green-800',
                                        'occupied' => 'bg-blue-100 text-blue-800',
                                        'maintenance' => 'bg-red-100 text-red-800'
                                    ];
                                    $status = $row['status'];
                                    $class = $statusClass[$status] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold capitalize <?php echo $class; ?>">
                                        <?php echo $status; ?>
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
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 md:p-8 animate-slide-up max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 id="modalTitle" class="text-2xl font-bold text-gray-900">Add New Room</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <form method="POST" id="roomForm" class="space-y-4">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId">
                
                <div>
                    <label for="room_number" class="block text-sm font-semibold text-gray-700 mb-2">Room Number *</label>
                    <input type="text" id="room_number" name="room_number" required placeholder="e.g., R-101, Room 201" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
                
                <div>
                    <label for="room_type" class="block text-sm font-semibold text-gray-700 mb-2">Room Type *</label>
                    <select id="room_type" name="room_type" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        <option value="">Select Room Type</option>
                        <option value="deluxe">Deluxe</option>
                        <option value="standard">Standard</option>
                        <option value="normal">Normal</option>
                    </select>
                </div>
                
                <div>
                    <label for="capacity_type" class="block text-sm font-semibold text-gray-700 mb-2">Capacity Type *</label>
                    <select id="capacity_type" name="capacity_type" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        <option value="">Select Capacity Type</option>
                        <option value="single bed">Single Bed</option>
                        <option value="double bed">Double Bed</option>
                        <option value="group">Group</option>
                    </select>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-semibold text-gray-700 mb-2">Status *</label>
                    <select id="status" name="status" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                
                <div>
                    <label for="amenities" class="block text-sm font-semibold text-gray-700 mb-2">Amenities</label>
                    <textarea id="amenities" name="amenities" rows="4" placeholder="e.g., WiFi, TV, AC, Mini Bar" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"></textarea>
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
        function openModal(action, data = null) {
            const modal = document.getElementById('modal');
            const form = document.getElementById('roomForm');
            document.getElementById('formAction').value = action;
            document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New Room' : 'Edit Room';
            
            if (action === 'edit' && data) {
                document.getElementById('formId').value = data.id;
                document.getElementById('room_number').value = data.room_number;
                document.getElementById('room_type').value = data.room_type;
                document.getElementById('capacity_type').value = data.capacity_type;
                document.getElementById('status').value = data.status;
                document.getElementById('amenities').value = data.amenities || '';
            } else {
                form.reset();
                document.getElementById('formId').value = '';
            }
            
            modal.classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }
        
        function deleteRecord(id) {
            if (confirm('Are you sure you want to delete this room?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        document.getElementById('modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
