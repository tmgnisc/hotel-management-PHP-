<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$message = '';
$messageType = 'success';
$userInfo = null;

// Get current user information
$adminId = $_SESSION['admin_id'] ?? null;
$adminUsername = $_SESSION['admin_username'] ?? '';

if ($adminId && $adminUsername) {
    // Check if user is in superadmin table
    $stmt = $conn->prepare("SELECT id, username, email, full_name FROM superadmin WHERE id = ? AND username = ?");
    $stmt->bind_param("is", $adminId, $adminUsername);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $userInfo = $result->fetch_assoc();
        $userInfo['type'] = 'superadmin';
    } else {
        // Check if user is in users table
        $stmt->close();
        $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ? AND username = ?");
        $stmt->bind_param("is", $adminId, $adminUsername);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $userInfo = $result->fetch_assoc();
            $userInfo['type'] = 'user';
        }
    }
    $stmt->close();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = "Please fill all password fields!";
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = "New password and confirm password do not match!";
        $messageType = 'error';
    } elseif (strlen($newPassword) < 6) {
        $message = "New password must be at least 6 characters long!";
        $messageType = 'error';
    } elseif ($currentPassword === $newPassword) {
        $message = "New password must be different from current password!";
        $messageType = 'error';
    } else {
        // Verify current password
        if ($userInfo['type'] == 'superadmin') {
            $stmt = $conn->prepare("SELECT password FROM superadmin WHERE id = ?");
        } else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        }
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($currentPassword, $user['password'])) {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                if ($userInfo['type'] == 'superadmin') {
                    $updateStmt = $conn->prepare("UPDATE superadmin SET password = ? WHERE id = ?");
                } else {
                    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                }
                
                $updateStmt->bind_param("si", $hashedPassword, $adminId);
                
                if ($updateStmt->execute()) {
                    $message = "Password changed successfully!";
                    $messageType = 'success';
                } else {
                    $message = "Error updating password: " . $updateStmt->error;
                    $messageType = 'error';
                }
                $updateStmt->close();
            } else {
                $message = "Current password is incorrect!";
                $messageType = 'error';
            }
        } else {
            $message = "User not found!";
            $messageType = 'error';
        }
        $stmt->close();
    }
}

$conn->close();

// Redirect if user info not found
if (!$userInfo) {
    header('Location: logout.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .devanagari-font {
            font-family: 'Noto Sans Devanagari', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <?php include 'includes/nav.php'; ?>

        <main class="md:ml-64 p-4 md:p-6 lg:p-8">
            <div class="mb-6">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900">My Profile</h2>
                <p class="text-gray-600 mt-1">Manage your account settings and change your password</p>
            </div>

            <?php if ($message): ?>
                <div class="mb-6 p-4 <?php echo $messageType === 'success' ? 'bg-green-50 border-l-4 border-green-500 text-green-700' : 'bg-red-50 border-l-4 border-red-500 text-red-700'; ?> rounded-lg animate-slide-up">
                    <?php echo htmlspecialchars($message); ?>
                    <button onclick="this.parentElement.remove()" class="float-right text-gray-500 hover:text-gray-700">×</button>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- User Information Card -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <span class="mr-2">👤</span>
                        Account Information
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Username</label>
                            <p class="text-gray-900 bg-gray-50 px-4 py-2 rounded-lg"><?php echo htmlspecialchars($userInfo['username'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                            <p class="text-gray-900 bg-gray-50 px-4 py-2 rounded-lg"><?php echo htmlspecialchars($userInfo['email'] ?? 'N/A'); ?></p>
                        </div>
                        <?php if (isset($userInfo['full_name'])): ?>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Full Name</label>
                            <p class="text-gray-900 bg-gray-50 px-4 py-2 rounded-lg"><?php echo htmlspecialchars($userInfo['full_name']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($userInfo['role'])): ?>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Role</label>
                            <p class="text-gray-900 bg-gray-50 px-4 py-2 rounded-lg capitalize"><?php echo htmlspecialchars($userInfo['role']); ?></p>
                        </div>
                        <?php endif; ?>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Account Type</label>
                            <p class="text-gray-900 bg-gray-50 px-4 py-2 rounded-lg capitalize">
                                <?php echo ($userInfo['type'] ?? 'user') == 'superadmin' ? 'Super Admin' : 'User'; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Change Password Card -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <span class="mr-2">🔒</span>
                        Change Password
                    </h3>
                    <form method="POST" action="profile.php" class="space-y-4" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div>
                            <label for="current_password" class="block text-sm font-semibold text-gray-700 mb-2">Current Password *</label>
                            <input 
                                type="password" 
                                id="current_password" 
                                name="current_password" 
                                required 
                                autocomplete="current-password"
                                placeholder="Enter your current password"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                            >
                        </div>
                        
                        <div>
                            <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2">New Password *</label>
                            <input 
                                type="password" 
                                id="new_password" 
                                name="new_password" 
                                required 
                                autocomplete="new-password"
                                placeholder="Enter new password (min. 6 characters)"
                                minlength="6"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                            >
                            <p class="text-xs text-gray-500 mt-1">Password must be at least 6 characters long</p>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password *</label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                required 
                                autocomplete="new-password"
                                placeholder="Confirm your new password"
                                minlength="6"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                            >
                        </div>
                        
                        <div class="pt-4">
                            <button 
                                type="submit" 
                                class="w-full px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all shadow-md hover:shadow-lg transform hover:scale-[1.02]"
                            >
                                Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Password validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            var newPassword = document.getElementById('new_password').value;
            var confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New password and confirm password do not match!');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>

