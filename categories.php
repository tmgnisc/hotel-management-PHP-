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
            $category_name = trim($_POST['category_name'] ?? '');
            
            if (empty($category_name)) {
                $message = "Please enter a category name!";
                $messageType = 'error';
            } else {
                // Check if category already exists
                $checkStmt = $conn->prepare("SELECT id FROM categories WHERE category_name = ?");
                $checkStmt->bind_param("s", $category_name);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    $message = "Category already exists!";
                    $messageType = 'error';
                    $checkStmt->close();
                } else {
                    $checkStmt->close();
                    $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
                    if ($stmt) {
                        $stmt->bind_param("s", $category_name);
                        if ($stmt->execute()) {
                            $message = "Category created successfully!";
                            $messageType = 'success';
                            $stmt->close();
                            $conn->close();
                            header('Location: categories.php?msg=' . urlencode($message) . '&type=' . $messageType);
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
            $category_name = trim($_POST['category_name'] ?? '');
            
            if (empty($category_name) || $id <= 0) {
                $message = "Please fill all required fields!";
                $messageType = 'error';
            } else {
                // Check if category name already exists for other categories
                $checkStmt = $conn->prepare("SELECT id FROM categories WHERE category_name = ? AND id != ?");
                $checkStmt->bind_param("si", $category_name, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    $message = "Category name already exists!";
                    $messageType = 'error';
                    $checkStmt->close();
                } else {
                    $checkStmt->close();
                    $stmt = $conn->prepare("UPDATE categories SET category_name=? WHERE id=?");
                    if ($stmt) {
                        $stmt->bind_param("si", $category_name, $id);
                        if ($stmt->execute()) {
                            $message = "Category updated successfully!";
                            $messageType = 'success';
                            $stmt->close();
                            $conn->close();
                            header('Location: categories.php?msg=' . urlencode($message) . '&type=' . $messageType);
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
                // Check if category has subcategories
                $checkSub = $conn->prepare("SELECT COUNT(*) as count FROM subcategories WHERE category_id = ?");
                $checkSub->bind_param("i", $id);
                $checkSub->execute();
                $subResult = $checkSub->get_result();
                $subCount = $subResult->fetch_assoc()['count'];
                $checkSub->close();
                
                if ($subCount > 0) {
                    $message = "Cannot delete category! It has " . $subCount . " subcategory(ies). Please delete subcategories first.";
                    $messageType = 'error';
                } else {
                    $stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
                    if ($stmt) {
                        $stmt->bind_param("i", $id);
                        if ($stmt->execute()) {
                            $message = "Category deleted successfully!";
                            $messageType = 'success';
                            $stmt->close();
                            $conn->close();
                            header('Location: categories.php?msg=' . urlencode($message) . '&type=' . $messageType);
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
}

// Get message from URL if redirected
if (isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
    $messageType = $_GET['type'] ?? 'success';
}

// Fetch all categories
$categories = $conn->query("SELECT c.*, COUNT(s.id) as subcategory_count FROM categories c LEFT JOIN subcategories s ON c.id = s.category_id GROUP BY c.id ORDER BY c.category_name");
if (!$categories) {
    die("Error fetching categories: " . $conn->error);
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        var openModal, closeModal, deleteRecord;
        
        (function() {
            openModal = function(action, data) {
                var modal = document.getElementById('modal');
                var form = document.getElementById('categoryForm');
                
                if (!modal || !form) {
                    console.error('Modal or form not found');
                    return;
                }
                
                var formAction = action === 'edit' ? 'update' : 'create';
                document.getElementById('formAction').value = formAction;
                document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New Category' : 'Edit Category';
                
                if (action === 'edit' && data) {
                    document.getElementById('formId').value = data.id || '';
                    document.getElementById('category_name').value = data.category_name || '';
                } else {
                    form.reset();
                    document.getElementById('formId').value = '';
                }
                
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };
            
            closeModal = function() {
                var modal = document.getElementById('modal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    document.getElementById('categoryForm').reset();
                }
            };
            
            deleteRecord = function(id) {
                if (confirm('Are you sure you want to delete this category? This will also delete all its subcategories.')) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '">';
                    document.body.appendChild(form);
                    form.submit();
                }
            };
            
            window.onclick = function(event) {
                var modal = document.getElementById('modal');
                if (event.target == modal) {
                    closeModal();
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
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">Categories Management</h1>
                            <p class="text-gray-600">Manage menu categories</p>
                        </div>
                        <div class="flex gap-3">
                            <a href="subcategories.php" class="px-4 md:px-6 py-2 md:py-3 bg-purple-600 text-white rounded-lg font-semibold hover:bg-purple-700 transform hover:scale-[1.02] transition-all duration-200 shadow-lg hover:shadow-xl flex items-center gap-2">
                                <span>📁</span>
                                <span>Manage Subcategories</span>
                            </a>
                            <button 
                                onclick="openModal('create', null)" 
                                class="px-4 md:px-6 py-2 md:py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg font-semibold hover:from-indigo-700 hover:to-purple-700 transform hover:scale-[1.02] transition-all duration-200 shadow-lg hover:shadow-xl flex items-center gap-2"
                            >
                                <span>➕</span>
                                <span>Add New Category</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800 border border-green-300' : 'bg-red-100 text-red-800 border border-red-300'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <!-- Categories Table -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Category Name</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Subcategories</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Created At</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($categories->num_rows > 0): ?>
                                    <?php while ($row = $categories->fetch_assoc()): ?>
                                    <tr class="hover:bg-indigo-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $row['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                                                <?php echo $row['subcategory_count']; ?> subcategory(ies)
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            <?php echo $row['created_at'] ? date('M d, Y', strtotime($row['created_at'])) : 'N/A'; ?>
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
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                            No categories found. Click "Add New Category" to create one.
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
        <div class="bg-white rounded-lg shadow-2xl max-w-md w-full">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 id="modalTitle" class="text-xl font-bold text-gray-900">Add New Category</h2>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <form id="categoryForm" method="POST" class="p-6 space-y-4">
                <input type="hidden" id="formAction" name="action" value="create">
                <input type="hidden" id="formId" name="id" value="">
                
                <div>
                    <label for="category_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Category Name <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="category_name" 
                        name="category_name" 
                        required 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                        placeholder="e.g., Momo, Pizza, Burger"
                    >
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

    <script src="assets/js/main.js"></script>
</body>
</html>








