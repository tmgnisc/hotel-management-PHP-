<?php
session_start();
require_once '../config/database.php';
require_once '../config/cloudinary.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();

// Ensure table exists for environments where initializer has not run recently.
$conn->query("CREATE TABLE IF NOT EXISTS gallery_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255),
    category ENUM('food', 'interior', 'team', 'events', 'other') DEFAULT 'food',
    image_url TEXT NOT NULL,
    public_id VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$message = '';
$messageType = 'success';
$allowedCategories = ['food', 'interior', 'team', 'events', 'other'];
$categoryLabels = [
    'food' => 'Food & Dishes',
    'interior' => 'Interior',
    'team' => 'Our Team',
    'events' => 'Events',
    'other' => 'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $category = trim($_POST['category'] ?? 'food');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($title === '') {
            $message = 'Image title is required.';
            $messageType = 'error';
        } elseif (!in_array($category, $allowedCategories, true)) {
            $message = 'Invalid category selected.';
            $messageType = 'error';
        } elseif (!isset($_FILES['image_file']) || ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $message = 'Please select an image file to upload.';
            $messageType = 'error';
        } else {
            $tmpPath = $_FILES['image_file']['tmp_name'];
            $mimeType = mime_content_type($tmpPath);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

            if (!in_array($mimeType, $allowedMimes, true)) {
                $message = 'Unsupported image format. Use JPG, PNG, WEBP, or GIF.';
                $messageType = 'error';
            } else {
                $uploadResult = cloudinaryUploadImage($tmpPath, CLOUDINARY_FOLDER . '/gallery');
                if (!($uploadResult['success'] ?? false)) {
                    $message = 'Cloudinary upload failed: ' . ($uploadResult['error'] ?? 'Unknown error');
                    $messageType = 'error';
                } else {
                    $imageUrl = $uploadResult['secure_url'];
                    $publicId = $uploadResult['public_id'];

                    $stmt = $conn->prepare("INSERT INTO gallery_images (title, subtitle, category, image_url, public_id, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    if ($stmt) {
                        $stmt->bind_param('sssssi', $title, $subtitle, $category, $imageUrl, $publicId, $sortOrder);
                        if ($stmt->execute()) {
                            $message = 'Gallery image uploaded successfully.';
                            $messageType = 'success';
                        } else {
                            // Roll back Cloudinary upload if DB insert fails.
                            cloudinaryDeleteImage($publicId);
                            $message = 'Image saved on Cloudinary but failed to save in database.';
                            $messageType = 'error';
                        }
                        $stmt->close();
                    } else {
                        cloudinaryDeleteImage($publicId);
                        $message = 'Failed to prepare gallery insert query.';
                        $messageType = 'error';
                    }
                }
            }
        }
    }

    if ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE gallery_images SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $message = 'Image visibility updated.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update image visibility.';
                    $messageType = 'error';
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("SELECT public_id FROM gallery_images WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result ? $result->fetch_assoc() : null;
                $stmt->close();

                if ($row) {
                    $publicId = $row['public_id'];

                    $deleteStmt = $conn->prepare("DELETE FROM gallery_images WHERE id = ?");
                    if ($deleteStmt) {
                        $deleteStmt->bind_param('i', $id);
                        if ($deleteStmt->execute()) {
                            cloudinaryDeleteImage($publicId);
                            $message = 'Gallery image deleted.';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to delete image record.';
                            $messageType = 'error';
                        }
                        $deleteStmt->close();
                    }
                }
            }
        }
    }
}

$galleryRows = [];
$query = "SELECT id, title, subtitle, category, image_url, public_id, sort_order, is_active, created_at FROM gallery_images ORDER BY sort_order ASC, id DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $galleryRows[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery Images - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-50">
<div class="min-h-screen">
    <?php include 'includes/nav.php'; ?>

    <main class="md:ml-64 p-4 md:p-6 lg:p-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900">🖼️ Gallery Images</h2>
                <p class="text-gray-600">Upload images to Cloudinary and manage gallery visibility.</p>
            </div>
            <div class="text-xs text-gray-500 bg-gray-100 px-3 py-2 rounded-lg">
                Folder: <?php echo htmlspecialchars(CLOUDINARY_FOLDER . '/gallery'); ?>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="mb-5 rounded-lg px-4 py-3 border <?php echo $messageType === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <section class="xl:col-span-1 bg-white rounded-xl shadow p-5">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Upload New Image</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="upload">

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Title *</label>
                        <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Subtitle</label>
                        <input type="text" name="subtitle" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Category *</label>
                        <select name="category" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            <?php foreach ($categoryLabels as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Image File *</label>
                        <input type="file" name="image_file" required accept="image/jpeg,image/png,image/webp,image/gif" class="w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="text-xs text-gray-500 mt-1">Allowed: JPG, PNG, WEBP, GIF.</p>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 text-white font-semibold py-2.5 rounded-lg hover:bg-indigo-700 transition-colors">
                        Upload to Cloudinary
                    </button>
                </form>
            </section>

            <section class="xl:col-span-2 bg-white rounded-xl shadow p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Uploaded Gallery Images</h3>
                    <span class="text-sm text-gray-500">Total: <?php echo count($galleryRows); ?></span>
                </div>

                <?php if (count($galleryRows) === 0): ?>
                    <div class="border border-dashed border-gray-300 rounded-lg p-8 text-center text-gray-500">
                        No gallery images uploaded yet.
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($galleryRows as $row): ?>
                            <div class="border rounded-lg p-3 md:p-4 flex flex-col md:flex-row gap-4 md:items-start">
                                <img src="<?php echo htmlspecialchars($row['image_url']); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>" class="w-full md:w-36 h-28 object-cover rounded-md border">

                                <div class="flex-1 min-w-0">
                                    <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($row['title']); ?></h4>
                                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($row['subtitle'] ?: '-'); ?></p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                        <span class="px-2 py-1 rounded bg-indigo-50 text-indigo-700">Category: <?php echo htmlspecialchars($categoryLabels[$row['category']] ?? $row['category']); ?></span>
                                        <span class="px-2 py-1 rounded bg-gray-100 text-gray-700">Sort: <?php echo (int)$row['sort_order']; ?></span>
                                        <span class="px-2 py-1 rounded <?php echo ((int)$row['is_active'] === 1) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                            <?php echo ((int)$row['is_active'] === 1) ? 'Active' : 'Hidden'; ?>
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-2 truncate">public_id: <?php echo htmlspecialchars($row['public_id']); ?></p>
                                </div>

                                <div class="flex md:flex-col gap-2 md:min-w-[110px]">
                                    <form method="POST" class="w-full">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <button type="submit" class="w-full px-3 py-2 text-xs rounded-md font-semibold <?php echo ((int)$row['is_active'] === 1) ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-green-100 text-green-800 hover:bg-green-200'; ?>">
                                            <?php echo ((int)$row['is_active'] === 1) ? 'Hide' : 'Show'; ?>
                                        </button>
                                    </form>

                                    <form method="POST" class="w-full" onsubmit="return confirm('Delete this image from gallery and Cloudinary?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <button type="submit" class="w-full px-3 py-2 text-xs rounded-md font-semibold bg-red-100 text-red-700 hover:bg-red-200">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
</body>
</html>
