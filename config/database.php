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
            room_type ENUM('normal_room', 'cabin_room') NOT NULL,
            room_id INT NOT NULL,
            check_in_date DATE NOT NULL,
            check_out_date DATE NOT NULL,
            number_of_guests INT NOT NULL,
            total_amount DECIMAL(10, 2) NOT NULL,
            status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending',
            special_requests TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS order_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(100) NOT NULL UNIQUE,
            table_id INT,
            booking_id INT,
            customer_name VARCHAR(255) NOT NULL,
            order_date DATETIME NOT NULL,
            items TEXT NOT NULL,
            subtotal DECIMAL(10, 2) NOT NULL,
            tax DECIMAL(10, 2) DEFAULT 0,
            discount DECIMAL(10, 2) DEFAULT 0,
            total_amount DECIMAL(10, 2) NOT NULL,
            payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
            payment_method VARCHAR(50),
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

