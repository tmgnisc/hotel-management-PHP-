<?php
// Script to fix the capacity column issue in normal_rooms table
require_once 'config/database.php';

$conn = getDBConnection();

echo "<h2>Fixing capacity column issue</h2>";
echo "<pre>";

// Check current table structure
$result = $conn->query("DESCRIBE normal_rooms");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
    echo "Column: " . $row['Field'] . " - Type: " . $row['Type'] . " - Null: " . $row['Null'] . " - Default: " . ($row['Default'] ?? 'NULL') . "\n";
}

echo "\n--- Fixing table structure ---\n";

// Remove old capacity column if it exists
if (in_array('capacity', $columns)) {
    echo "Removing old 'capacity' column...\n";
    $sql = "ALTER TABLE normal_rooms DROP COLUMN capacity";
    if ($conn->query($sql)) {
        echo "✓ Successfully removed 'capacity' column\n";
    } else {
        echo "✗ Error removing 'capacity' column: " . $conn->error . "\n";
    }
} else {
    echo "✓ 'capacity' column does not exist (already removed or never existed)\n";
}

// Remove price_per_night column if it exists (not in current schema)
if (in_array('price_per_night', $columns)) {
    echo "Removing old 'price_per_night' column...\n";
    $sql = "ALTER TABLE normal_rooms DROP COLUMN price_per_night";
    if ($conn->query($sql)) {
        echo "✓ Successfully removed 'price_per_night' column\n";
    } else {
        echo "✗ Error removing 'price_per_night' column: " . $conn->error . "\n";
    }
} else {
    echo "✓ 'price_per_night' column does not exist (already removed or never existed)\n";
}

// Ensure capacity_type exists
if (!in_array('capacity_type', $columns)) {
    echo "Adding 'capacity_type' column...\n";
    $sql = "ALTER TABLE normal_rooms ADD COLUMN capacity_type ENUM('single bed', 'double bed', 'group') NOT NULL DEFAULT 'single bed' AFTER room_type";
    if ($conn->query($sql)) {
        echo "✓ Successfully added 'capacity_type' column\n";
    } else {
        echo "✗ Error adding 'capacity_type' column: " . $conn->error . "\n";
    }
} else {
    echo "✓ 'capacity_type' column exists\n";
}

// Ensure room_type exists
if (!in_array('room_type', $columns)) {
    echo "Adding 'room_type' column...\n";
    $sql = "ALTER TABLE normal_rooms ADD COLUMN room_type ENUM('deluxe', 'standard', 'normal') NOT NULL DEFAULT 'normal' AFTER room_number";
    if ($conn->query($sql)) {
        echo "✓ Successfully added 'room_type' column\n";
    } else {
        echo "✗ Error adding 'room_type' column: " . $conn->error . "\n";
    }
} else {
    echo "✓ 'room_type' column exists\n";
}

echo "\n--- Final table structure ---\n";
$result = $conn->query("DESCRIBE normal_rooms");
while ($row = $result->fetch_assoc()) {
    echo "Column: " . $row['Field'] . " - Type: " . $row['Type'] . " - Null: " . $row['Null'] . " - Default: " . ($row['Default'] ?? 'NULL') . "\n";
}

$conn->close();
echo "</pre>";
echo "<p><a href='normal_rooms.php'>Go to Normal Rooms</a></p>";
?>

