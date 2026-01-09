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
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'staff';
            
            if (empty($username) || empty($email) || empty($password)) {
                $message = "Please fill all required fields!";
                $messageType = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "Please enter a valid email address!";
                $messageType = 'error';
            } elseif (strlen($password) < 6) {
                $message = "Password must be at least 6 characters long!";
                $messageType = 'error';
            } else {
                // Check if username or email already exists
                $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $checkStmt->bind_param("ss", $username, $email);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    $message = "Username or email already exists!";
                    $messageType = 'error';
                    $checkStmt->close();
                } else {
                    $checkStmt->close();
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("ssss", $username, $email, $hashedPassword, $role);
                        if ($stmt->execute()) {
                            $message = "User created successfully!";
                            $messageType = 'success';
                            $stmt->close();
                            $conn->close();
                            header('Location: users.php?msg=' . urlencode($message) . '&type=' . $messageType);
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
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'staff';
            
            if (empty($username) || empty($email) || $id <= 0) {
                $message = "Please fill all required fields!";
                $messageType = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "Please enter a valid email address!";
                $messageType = 'error';
            } else {
                // Check if username or email already exists for other users
                $checkStmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $checkStmt->bind_param("ssi", $username, $email, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    $message = "Username or email already exists!";
                    $messageType = 'error';
                    $checkStmt->close();
                } else {
                    $checkStmt->close();
                    if (!empty($password)) {
                        if (strlen($password) < 6) {
                            $message = "Password must be at least 6 characters long!";
                            $messageType = 'error';
                        } else {
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, password=?, role=? WHERE id=?");
                            if ($stmt) {
                                $stmt->bind_param("ssssi", $username, $email, $hashedPassword, $role, $id);
                                if ($stmt->execute()) {
                                    $message = "User updated successfully!";
                                    $messageType = 'success';
                                    $stmt->close();
                                    $conn->close();
                                    header('Location: users.php?msg=' . urlencode($message) . '&type=' . $messageType);
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
                    } else {
                        // Update without password
                        $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=? WHERE id=?");
                        if ($stmt) {
                            $stmt->bind_param("sssi", $username, $email, $role, $id);
                            if ($stmt->execute()) {
                                $message = "User updated successfully!";
                                $messageType = 'success';
                                $stmt->close();
                                $conn->close();
                                header('Location: users.php?msg=' . urlencode($message) . '&type=' . $messageType);
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
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $message = "User deleted successfully!";
                        $messageType = 'success';
                        $stmt->close();
                        $conn->close();
                        header('Location: users.php?msg=' . urlencode($message) . '&type=' . $messageType);
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

// Fetch all users
$users = $conn->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
if (!$users) {
    die("Error fetching users: " . $conn->error);
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Admin Dashboard</title>
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
                var form = document.getElementById('userForm');
                
                if (!modal || !form) {
                    console.error('Modal or form not found');
                    return;
                }
                
                // Set form action: 'create' for new, 'update' for edit
                var formAction = action === 'edit' ? 'update' : 'create';
                document.getElementById('formAction').value = formAction;
                document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New User' : 'Edit User';
                
                if (action === 'edit' && data) {
                    document.getElementById('formId').value = data.id || '';
                    document.getElementById('username').value = data.username || '';
                    document.getElementById('email').value = data.email || '';
                    document.getElementById('role').value = data.role || 'staff';
                    document.getElementById('password').value = '';
                    document.getElementById('password').required = false;
                    document.getElementById('passwordLabel').innerHTML = 'Password <span class="text-gray-500 text-xs">(leave blank to keep current)</span>';
                } else {
                    form.reset();
                    document.getElementById('formId').value = '';
                    document.getElementById('password').required = true;
                    document.getElementById('passwordLabel').innerHTML = 'Password <span class="text-red-500">*</span>';
                }
                
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };
            
            closeModal = function() {
                var modal = document.getElementById('modal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    document.getElementById('userForm').reset();
                }
            };
            
            deleteRecord = function(id) {
                if (confirm('Are you sure you want to delete this user?')) {
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
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">Users Management</h1>
                            <p class="text-gray-600">Manage staff and manager accounts</p>
                        </div>
                        <button 
                            onclick="openModal('create', null)" 
                            class="px-4 md:px-6 py-2 md:py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg font-semibold hover:from-indigo-700 hover:to-purple-700 transform hover:scale-[1.02] transition-all duration-200 shadow-lg hover:shadow-xl flex items-center gap-2"
                        >
                            <span>➕</span>
                            <span>Add New User</span>
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800 border border-green-300' : 'bg-red-100 text-red-800 border border-red-300'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <!-- Users Table -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Username</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Created At</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($users->num_rows > 0): ?>
                                    <?php while ($row = $users->fetch_assoc()): ?>
                                    <tr class="hover:bg-indigo-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $row['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $roleClass = $row['role'] === 'manager' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800';
                                            ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $roleClass; ?>">
                                                <?php echo ucfirst($row['role']); ?>
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
                                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                            No users found. Click "Add New User" to create one.
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
        <div class="bg-white rounded-lg shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 id="modalTitle" class="text-xl font-bold text-gray-900">Add New User</h2>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <form id="userForm" method="POST" class="p-6 space-y-4">
                <input type="hidden" id="formAction" name="action" value="create">
                <input type="hidden" id="formId" name="id" value="">
                
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        Username <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                        placeholder="Enter username"
                    >
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                        placeholder="Enter email address"
                    >
                </div>
                
                <div>
                    <label for="password" id="passwordLabel" class="block text-sm font-medium text-gray-700 mb-2">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        minlength="6"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                        placeholder="Enter password (min 6 characters)"
                    >
                </div>
                
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                        Role <span class="text-red-500">*</span>
                    </label>
                    <select 
                        id="role" 
                        name="role" 
                        required 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                    >
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                    </select>
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











