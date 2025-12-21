<?php
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
            $table_number = trim($_POST['table_number'] ?? '');
            $capacity = intval($_POST['capacity'] ?? 0);
            
            if (empty($table_number) || $capacity <= 0) {
                $message = "Please fill all required fields correctly!";
                $messageType = 'error';
            } else {
                $stmt = $conn->prepare("INSERT INTO tables (table_number, capacity) VALUES (?, ?)");
                $stmt->bind_param("si", $table_number, $capacity);
                if ($stmt->execute()) {
                    $message = "Table created successfully!";
                    $messageType = 'success';
                    $stmt->close();
                    $conn->close();
                    header('Location: tables.php?msg=' . urlencode($message) . '&type=' . $messageType);
                    exit;
                } else {
                    $message = "Error: " . $conn->error;
                    $messageType = 'error';
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] == 'update') {
            $id = intval($_POST['id'] ?? 0);
            $table_number = trim($_POST['table_number'] ?? '');
            $capacity = intval($_POST['capacity'] ?? 0);
            
            if (empty($table_number) || $capacity <= 0 || $id <= 0) {
                $message = "Please fill all required fields correctly!";
                $messageType = 'error';
            } else {
                $stmt = $conn->prepare("UPDATE tables SET table_number=?, capacity=? WHERE id=?");
                $stmt->bind_param("sii", $table_number, $capacity, $id);
                if ($stmt->execute()) {
                    $message = "Table updated successfully!";
                    $messageType = 'success';
                    $stmt->close();
                    $conn->close();
                    header('Location: tables.php?msg=' . urlencode($message) . '&type=' . $messageType);
                    exit;
                } else {
                    $message = "Error: " . $conn->error;
                    $messageType = 'error';
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM tables WHERE id=?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $message = "Table deleted successfully!";
                    $messageType = 'success';
                    $stmt->close();
                    $conn->close();
                    header('Location: tables.php?msg=' . urlencode($message) . '&type=' . $messageType);
                    exit;
                } else {
                    $message = "Error: " . $conn->error;
                    $messageType = 'error';
                }
                $stmt->close();
            }
        }
    }
}

// Get message from URL if redirected
if (isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
    $messageType = $_GET['type'] ?? 'success';
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <?php include 'includes/nav.php'; ?>

        <main class="md:ml-64 p-4 md:p-6 lg:p-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900">Restaurant Tables Management</h2>
                <button onclick="openModal('create')" class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                    + Add New Table
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
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Table Number</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Capacity</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($row = $tables->fetch_assoc()): ?>
                            <tr class="hover:bg-indigo-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $row['id']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['table_number']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo $row['capacity']; ?> persons</td>
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
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 md:p-8 animate-slide-up">
            <div class="flex justify-between items-center mb-6">
                <h3 id="modalTitle" class="text-2xl font-bold text-gray-900">Add New Table</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <form method="POST" id="tableForm" class="space-y-4">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId">
                
                <div>
                    <label for="table_number" class="block text-sm font-semibold text-gray-700 mb-2">Table Number *</label>
                    <input type="text" id="table_number" name="table_number" required placeholder="e.g., T-01, Table 1" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
                
                <div>
                    <label for="capacity" class="block text-sm font-semibold text-gray-700 mb-2">Capacity *</label>
                    <input type="number" id="capacity" name="capacity" min="1" required placeholder="Number of persons" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
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
        // Ensure functions are available globally
        window.openModal = function(action, data = null) {
            const modal = document.getElementById('modal');
            if (!modal) {
                console.error('Modal element not found');
                return;
            }
            const form = document.getElementById('tableForm');
            if (!form) {
                console.error('Form element not found');
                return;
            }
            
            document.getElementById('formAction').value = action;
            document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New Table' : 'Edit Table';
            
            if (action === 'edit' && data) {
                document.getElementById('formId').value = data.id;
                document.getElementById('table_number').value = data.table_number || '';
                document.getElementById('capacity').value = data.capacity || '';
            } else {
                form.reset();
                document.getElementById('formId').value = '';
            }
            
            modal.classList.remove('hidden');
        };
        
        window.closeModal = function() {
            const modal = document.getElementById('modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        };
        
        window.deleteRecord = function(id) {
            if (confirm('Are you sure you want to delete this table?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        };
        
        // Modal click outside to close
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('modal');
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
