<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();

$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$messages = [];
$result = $conn->query("SELECT id, customer_name, message, created_at FROM contact_messages ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-50">
<div class="min-h-screen">
    <?php include 'includes/nav.php'; ?>

    <main class="md:ml-64 p-4 md:p-6 lg:p-8">
        <div class="mb-6">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900">📨 Contact Messages</h2>
            <p class="text-gray-600 mt-1">Customer messages submitted from `contact.php`.</p>
        </div>

        <div class="bg-white rounded-xl shadow p-4 md:p-6">
            <?php if (count($messages) === 0): ?>
                <div class="text-center text-gray-500 py-10">No contact messages yet.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left">S.N</th>
                                <th class="px-3 py-2 text-left">Customer Name</th>
                                <th class="px-3 py-2 text-left">Message</th>
                                <th class="px-3 py-2 text-left">Submitted At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($messages as $index => $msg): ?>
                                <tr class="hover:bg-gray-50 align-top">
                                    <td class="px-3 py-2"><?php echo (int)($index + 1); ?></td>
                                    <td class="px-3 py-2 font-medium text-gray-900"><?php echo htmlspecialchars($msg['customer_name']); ?></td>
                                    <td class="px-3 py-2 whitespace-pre-line text-gray-700"><?php echo htmlspecialchars($msg['message']); ?></td>
                                    <td class="px-3 py-2 text-gray-500"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($msg['created_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
