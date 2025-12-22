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
            $food_name = trim($_POST['food_name'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $category_id = intval($_POST['category_id'] ?? 0);
            $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
            
            if (empty($food_name) || $price <= 0 || $category_id <= 0 || $subcategory_id <= 0) {
                $message = "Please fill all required fields correctly!";
                $messageType = 'error';
            } else {
                $stmt = $conn->prepare("INSERT INTO menu (food_name, price, category_id, subcategory_id) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("sdii", $food_name, $price, $category_id, $subcategory_id);
                    if ($stmt->execute()) {
                        $message = "Menu item created successfully!";
                        $messageType = 'success';
                        $stmt->close();
                        $conn->close();
                        header('Location: menu.php?msg=' . urlencode($message) . '&type=' . $messageType);
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
            $food_name = trim($_POST['food_name'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $category_id = intval($_POST['category_id'] ?? 0);
            $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
            
            if (empty($food_name) || $price <= 0 || $id <= 0 || $category_id <= 0 || $subcategory_id <= 0) {
                $message = "Please fill all required fields correctly!";
                $messageType = 'error';
            } else {
                $stmt = $conn->prepare("UPDATE menu SET food_name=?, price=?, category_id=?, subcategory_id=? WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("sdiii", $food_name, $price, $category_id, $subcategory_id, $id);
                    if ($stmt->execute()) {
                        $message = "Menu item updated successfully!";
                        $messageType = 'success';
                        $stmt->close();
                        $conn->close();
                        header('Location: menu.php?msg=' . urlencode($message) . '&type=' . $messageType);
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
                $stmt = $conn->prepare("DELETE FROM menu WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $message = "Menu item deleted successfully!";
                        $messageType = 'success';
                        $stmt->close();
                        $conn->close();
                        header('Location: menu.php?msg=' . urlencode($message) . '&type=' . $messageType);
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

// Fetch all categories for dropdown
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
if (!$categories) {
    die("Error fetching categories: " . $conn->error);
}

// Fetch all subcategories for dropdown
$allSubcategories = $conn->query("SELECT * FROM subcategories ORDER BY category_id, subcategory_name");
if (!$allSubcategories) {
    die("Error fetching subcategories: " . $conn->error);
}

// Fetch all menu items with category and subcategory names
$menuItems = $conn->query("SELECT m.*, c.category_name, s.subcategory_name FROM menu m LEFT JOIN categories c ON m.category_id = c.id LEFT JOIN subcategories s ON m.subcategory_id = s.id ORDER BY c.category_name, s.subcategory_name, m.food_name");
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
    <title>Menu Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        var openModal, closeModal, deleteRecord, updateSubcategories;
        
        // Subcategories data
        var subcategoriesData = <?php 
            $subcategoriesArray = [];
            $allSubcategories->data_seek(0);
            while ($sub = $allSubcategories->fetch_assoc()) {
                if (!isset($subcategoriesArray[$sub['category_id']])) {
                    $subcategoriesArray[$sub['category_id']] = [];
                }
                $subcategoriesArray[$sub['category_id']][] = $sub;
            }
            echo json_encode($subcategoriesArray);
        ?>;
        
        // Function to update subcategories dropdown based on selected category
        updateSubcategories = function(categoryId) {
            var subcategorySelect = document.getElementById('subcategory_id');
            if (!subcategorySelect) return;
            
            subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
            
            if (categoryId && subcategoriesData[categoryId]) {
                subcategoriesData[categoryId].forEach(function(sub) {
                    var option = document.createElement('option');
                    option.value = sub.id;
                    option.textContent = sub.subcategory_name;
                    subcategorySelect.appendChild(option);
                });
            }
        };
        
        (function() {
            openModal = function(action, data) {
                var modal = document.getElementById('modal');
                var form = document.getElementById('menuForm');
                
                if (!modal || !form) {
                    console.error('Modal or form not found');
                    return;
                }
                
                // Set form action: 'create' for new, 'update' for edit
                var formAction = action === 'edit' ? 'update' : 'create';
                document.getElementById('formAction').value = formAction;
                document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New Menu Item' : 'Edit Menu Item';
                
                if (action === 'edit' && data) {
                    document.getElementById('formId').value = data.id || '';
                    document.getElementById('food_name').value = data.food_name || '';
                    document.getElementById('price').value = data.price || '';
                    document.getElementById('category_id').value = data.category_id || '';
                    // Trigger subcategory update
                    updateSubcategories(data.category_id);
                    setTimeout(function() {
                        document.getElementById('subcategory_id').value = data.subcategory_id || '';
                    }, 100);
                } else {
                    form.reset();
                    document.getElementById('formId').value = '';
                    document.getElementById('subcategory_id').innerHTML = '<option value="">Select Category First</option>';
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
                if (confirm('Are you sure you want to delete this menu item?')) {
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
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900">Menu Management</h2>
                <button onclick="openModal('create')" class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                    + Add New Menu Item
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
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Category</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Subcategory</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Food Name</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Price</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php 
                            $menuItems->data_seek(0);
                            while ($row = $menuItems->fetch_assoc()): ?>
                            <tr class="hover:bg-indigo-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $row['id']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-semibold">
                                        <?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                                        <?php echo htmlspecialchars($row['subcategory_name'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['food_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-semibold">
                                    Rs <?php echo number_format($row['price'], 2); ?>
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
                <h3 id="modalTitle" class="text-2xl font-bold text-gray-900">Add New Menu Item</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <form method="POST" id="menuForm" class="space-y-4">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId">
                
                <div>
                    <label for="category_id" class="block text-sm font-semibold text-gray-700 mb-2">Category *</label>
                    <select id="category_id" name="category_id" required onchange="updateSubcategories(this.value)" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        <option value="">Select Category</option>
                        <?php
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div>
                    <label for="subcategory_id" class="block text-sm font-semibold text-gray-700 mb-2">Subcategory *</label>
                    <select id="subcategory_id" name="subcategory_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        <option value="">Select Category First</option>
                    </select>
                </div>
                
                <div>
                    <label for="food_name" class="block text-sm font-semibold text-gray-700 mb-2">Food Name *</label>
                    <input type="text" id="food_name" name="food_name" required placeholder="e.g., Chicken Biryani, Pizza Margherita" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
                
                <div>
                    <label for="price" class="block text-sm font-semibold text-gray-700 mb-2">Price *</label>
                    <input type="number" id="price" name="price" step="0.01" min="0.01" required placeholder="0.00" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
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

