<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hotel_management');

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Initialize database and tables
function initializeDatabase() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create database if not exists
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    $conn->query($sql);
    $conn->select_db(DB_NAME);
    
    // Create tables
    $tables = [
        "CREATE TABLE IF NOT EXISTS superadmin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            full_name VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS tables (
            id INT AUTO_INCREMENT PRIMARY KEY,
            table_number VARCHAR(50) NOT NULL UNIQUE,
            capacity INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS cabin_rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cabin_number VARCHAR(50) NOT NULL UNIQUE,
            capacity INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS normal_rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_number VARCHAR(50) NOT NULL UNIQUE,
            room_type ENUM('deluxe', 'standard', 'normal') NOT NULL,
            capacity_type ENUM('single bed', 'double bed', 'group') NOT NULL,
            status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
            amenities TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_reference VARCHAR(100) NOT NULL UNIQUE,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255),
            customer_phone VARCHAR(50),
            room_type ENUM('normal_room', 'cabin_room') DEFAULT 'normal_room',
            room_id INT NOT NULL,
            check_in_date DATE NOT NULL,
            check_out_date DATE NOT NULL,
            number_of_guests INT DEFAULT 1,
            total_amount DECIMAL(10, 2) DEFAULT 0.00,
            status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending',
            special_requests TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS menu (
            id INT AUTO_INCREMENT PRIMARY KEY,
            food_name VARCHAR(255) NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            description TEXT,
            category VARCHAR(100),
            status ENUM('available', 'unavailable') DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS order_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(100) NOT NULL UNIQUE,
            table_id INT,
            booking_id INT,
            customer_name VARCHAR(255),
            order_date DATETIME NOT NULL,
            items TEXT NOT NULL,
            subtotal DECIMAL(10, 2) NOT NULL,
            tax DECIMAL(10, 2) DEFAULT 0,
            discount DECIMAL(10, 2) DEFAULT 0,
            total_amount DECIMAL(10, 2) NOT NULL,
            order_status ENUM('pending', 'completed') DEFAULT 'pending',
            payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
            payment_method ENUM('cash', 'card', 'online') DEFAULT 'cash',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL,
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
        )"
    ];
    
    foreach ($tables as $sql) {
        if (!$conn->query($sql)) {
            echo "Error creating table: " . $conn->error . "<br>";
        }
    }
    
    // Check and add missing columns to existing tables
    $conn->select_db(DB_NAME);
    
    // Check if normal_rooms table exists and add missing columns
    $checkTable = $conn->query("SHOW TABLES LIKE 'normal_rooms'");
    if ($checkTable && $checkTable->num_rows > 0) {
        // Get all existing columns
        $columns = $conn->query("SHOW COLUMNS FROM normal_rooms");
        $existingColumns = [];
        while ($col = $columns->fetch_assoc()) {
            $existingColumns[] = $col['Field'];
        }
        
        // Remove capacity column if it exists (we use capacity_type instead)
        if (in_array('capacity', $existingColumns) && !in_array('capacity_type', $existingColumns)) {
            $alterSql = "ALTER TABLE normal_rooms DROP COLUMN capacity";
            $conn->query($alterSql);
        }
        
        // Check if capacity_type column exists
        if (!in_array('capacity_type', $existingColumns)) {
            $alterSql = "ALTER TABLE normal_rooms ADD COLUMN capacity_type ENUM('single bed', 'double bed', 'group') NOT NULL DEFAULT 'single bed' AFTER room_type";
            $conn->query($alterSql);
        }
        
        // Check if room_type column exists
        if (!in_array('room_type', $existingColumns)) {
            $alterSql = "ALTER TABLE normal_rooms ADD COLUMN room_type ENUM('deluxe', 'standard', 'normal') NOT NULL DEFAULT 'normal' AFTER room_number";
            $conn->query($alterSql);
        }
        
        // Check if status column exists
        if (!in_array('status', $existingColumns)) {
            $alterSql = "ALTER TABLE normal_rooms ADD COLUMN status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available' AFTER capacity_type";
            $conn->query($alterSql);
        }
        
        // Check if amenities column exists
        if (!in_array('amenities', $existingColumns)) {
            $alterSql = "ALTER TABLE normal_rooms ADD COLUMN amenities TEXT AFTER status";
            $conn->query($alterSql);
        }
        
        // If capacity column still exists and we have capacity_type, remove capacity
        if (in_array('capacity', $existingColumns) && in_array('capacity_type', $existingColumns)) {
            $alterSql = "ALTER TABLE normal_rooms DROP COLUMN capacity";
            $conn->query($alterSql);
        }
        
        // Remove price_per_night column if it exists (not in current schema)
        if (in_array('price_per_night', $existingColumns)) {
            $alterSql = "ALTER TABLE normal_rooms DROP COLUMN price_per_night";
            $conn->query($alterSql);
        }
    }
    
    // Check and update bookings table structure
    $checkBookingsTable = $conn->query("SHOW TABLES LIKE 'bookings'");
    if ($checkBookingsTable && $checkBookingsTable->num_rows > 0) {
        $bookingsColumns = $conn->query("SHOW COLUMNS FROM bookings");
        $existingBookingsColumns = [];
        while ($col = $bookingsColumns->fetch_assoc()) {
            $existingBookingsColumns[] = $col['Field'];
        }
        
        // Update room_type to have default if it doesn't
        if (in_array('room_type', $existingBookingsColumns)) {
            $alterSql = "ALTER TABLE bookings MODIFY COLUMN room_type ENUM('normal_room', 'cabin_room') DEFAULT 'normal_room'";
            $conn->query($alterSql);
        }
        
        // Update number_of_guests to have default if it doesn't
        if (in_array('number_of_guests', $existingBookingsColumns)) {
            $alterSql = "ALTER TABLE bookings MODIFY COLUMN number_of_guests INT DEFAULT 1";
            $conn->query($alterSql);
        }
        
        // Update total_amount to have default if it doesn't
        if (in_array('total_amount', $existingBookingsColumns)) {
            $alterSql = "ALTER TABLE bookings MODIFY COLUMN total_amount DECIMAL(10, 2) DEFAULT 0.00";
            $conn->query($alterSql);
        }
    }
    
    // Check and update order_details table structure
    $checkOrderDetailsTable = $conn->query("SHOW TABLES LIKE 'order_details'");
    if ($checkOrderDetailsTable && $checkOrderDetailsTable->num_rows > 0) {
        $orderDetailsColumns = $conn->query("SHOW COLUMNS FROM order_details");
        $existingOrderDetailsColumns = [];
        while ($col = $orderDetailsColumns->fetch_assoc()) {
            $existingOrderDetailsColumns[] = $col['Field'];
        }
        
        // Add order_status if it doesn't exist
        if (!in_array('order_status', $existingOrderDetailsColumns)) {
            $alterSql = "ALTER TABLE order_details ADD COLUMN order_status ENUM('pending', 'completed') DEFAULT 'pending' AFTER total_amount";
            $conn->query($alterSql);
        }
        
        // Update payment_method to ENUM if it's not already
        if (in_array('payment_method', $existingOrderDetailsColumns)) {
            // Check if it's already ENUM
            $checkColumn = $conn->query("SHOW COLUMNS FROM order_details WHERE Field = 'payment_method'");
            if ($checkColumn && $row = $checkColumn->fetch_assoc()) {
                if (strpos($row['Type'], 'enum') === false) {
                    $alterSql = "ALTER TABLE order_details MODIFY COLUMN payment_method ENUM('cash', 'card', 'online') DEFAULT 'cash'";
                    $conn->query($alterSql);
                }
            }
        }
        
        // Make customer_name nullable if it's not already
        if (in_array('customer_name', $existingOrderDetailsColumns)) {
            $checkColumn = $conn->query("SHOW COLUMNS FROM order_details WHERE Field = 'customer_name'");
            if ($checkColumn && $row = $checkColumn->fetch_assoc()) {
                if ($row['Null'] === 'NO') {
                    $alterSql = "ALTER TABLE order_details MODIFY COLUMN customer_name VARCHAR(255) NULL";
                    $conn->query($alterSql);
                }
            }
        }
        
        // Ensure payment_status has 'pending' as default
        if (in_array('payment_status', $existingOrderDetailsColumns)) {
            $checkColumn = $conn->query("SHOW COLUMNS FROM order_details WHERE Field = 'payment_status'");
            if ($checkColumn && $row = $checkColumn->fetch_assoc()) {
                if ($row['Default'] !== 'pending') {
                    $alterSql = "ALTER TABLE order_details MODIFY COLUMN payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending'";
                    $conn->query($alterSql);
                }
            }
        }
    }
    
    // Create default superadmin if not exists
    $conn->select_db(DB_NAME);
    $checkAdmin = $conn->query("SELECT COUNT(*) as count FROM superadmin");
    $adminExists = $checkAdmin->fetch_assoc()['count'];
    
    if ($adminExists == 0) {
        // Default admin: admin/admin (password is hashed)
        $defaultPassword = password_hash('admin', PASSWORD_DEFAULT);
        $insertAdmin = "INSERT INTO superadmin (username, password, email, full_name) VALUES ('admin', '$defaultPassword', 'admin@hotel.com', 'Super Admin')";
        $conn->query($insertAdmin);
    }
    
    $conn->close();
}

// Initialize on first run
initializeDatabase();
?>

