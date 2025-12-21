<?php
// Migration script to add missing columns to existing tables
require_once 'config/database.php';

$conn = getDBConnection();

echo "<h2>Database Migration</h2>";
echo "<pre>";

// Check and fix normal_rooms table
$checkTable = $conn->query("SHOW TABLES LIKE 'normal_rooms'");
if ($checkTable && $checkTable->num_rows > 0) {
    echo "Checking normal_rooms table...\n";
    
    // Get existing columns
    $columns = $conn->query("SHOW COLUMNS FROM normal_rooms");
    $existingColumns = [];
    while ($col = $columns->fetch_assoc()) {
        $existingColumns[] = $col['Field'];
    }
    
    // Add missing columns
    if (!in_array('room_type', $existingColumns)) {
        echo "Adding room_type column...\n";
        $sql = "ALTER TABLE normal_rooms ADD COLUMN room_type ENUM('deluxe', 'standard', 'normal') NOT NULL DEFAULT 'normal' AFTER room_number";
        if ($conn->query($sql)) {
            echo "✓ room_type added successfully\n";
        } else {
            echo "✗ Error adding room_type: " . $conn->error . "\n";
        }
    } else {
        echo "✓ room_type column exists\n";
    }
    
    if (!in_array('capacity_type', $existingColumns)) {
        echo "Adding capacity_type column...\n";
        $sql = "ALTER TABLE normal_rooms ADD COLUMN capacity_type ENUM('single bed', 'double bed', 'group') NOT NULL DEFAULT 'single bed' AFTER room_type";
        if ($conn->query($sql)) {
            echo "✓ capacity_type added successfully\n";
        } else {
            echo "✗ Error adding capacity_type: " . $conn->error . "\n";
        }
    } else {
        echo "✓ capacity_type column exists\n";
    }
    
    if (!in_array('status', $existingColumns)) {
        echo "Adding status column...\n";
        $sql = "ALTER TABLE normal_rooms ADD COLUMN status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available' AFTER capacity_type";
        if ($conn->query($sql)) {
            echo "✓ status added successfully\n";
        } else {
            echo "✗ Error adding status: " . $conn->error . "\n";
        }
    } else {
        echo "✓ status column exists\n";
    }
    
    if (!in_array('amenities', $existingColumns)) {
        echo "Adding amenities column...\n";
        $sql = "ALTER TABLE normal_rooms ADD COLUMN amenities TEXT AFTER status";
        if ($conn->query($sql)) {
            echo "✓ amenities added successfully\n";
        } else {
            echo "✗ Error adding amenities: " . $conn->error . "\n";
        }
    } else {
        echo "✓ amenities column exists\n";
    }
    
    // Remove old capacity column if it exists (we use capacity_type instead)
    if (in_array('capacity', $existingColumns)) {
        echo "Removing old capacity column (using capacity_type instead)...\n";
        $sql = "ALTER TABLE normal_rooms DROP COLUMN capacity";
        if ($conn->query($sql)) {
            echo "✓ capacity column removed successfully\n";
        } else {
            echo "✗ Error removing capacity: " . $conn->error . "\n";
        }
    }
    
    // Remove price_per_night column if it exists (not in current schema)
    if (in_array('price_per_night', $existingColumns)) {
        echo "Removing old price_per_night column...\n";
        $sql = "ALTER TABLE normal_rooms DROP COLUMN price_per_night";
        if ($conn->query($sql)) {
            echo "✓ price_per_night column removed successfully\n";
        } else {
            echo "✗ Error removing price_per_night: " . $conn->error . "\n";
        }
    }
    
    echo "\nMigration completed!\n";
} else {
    echo "normal_rooms table does not exist. It will be created automatically.\n";
}

$conn->close();
echo "</pre>";
echo "<p><a href='normal_rooms.php'>Go to Normal Rooms</a></p>";
?>

