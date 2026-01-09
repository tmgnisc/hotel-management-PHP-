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
            payment_status ENUM('pending', 'paid', 'partial', 'cancelled') DEFAULT 'pending',
            payment_method ENUM('cash', 'card', 'online', 'bank_transfer') DEFAULT 'cash',
            payment_amount DECIMAL(10, 2) DEFAULT 0.00,
            status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending',
            special_requests TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS subcategories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            subcategory_name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
            UNIQUE KEY unique_subcategory (category_id, subcategory_name)
        )",
        
        "CREATE TABLE IF NOT EXISTS menu (
            id INT AUTO_INCREMENT PRIMARY KEY,
            food_name VARCHAR(255) NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            category_id INT,
            subcategory_id INT,
            description TEXT,
            status ENUM('available', 'unavailable') DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
            FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS order_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(100) NOT NULL UNIQUE,
            table_id INT,
            booking_id INT,
            customer_name VARCHAR(255),
            regular_customer_id INT,
            order_date DATETIME NOT NULL,
            items TEXT NOT NULL,
            subtotal DECIMAL(10, 2) NOT NULL,
            tax DECIMAL(10, 2) DEFAULT 0,
            discount_percentage DECIMAL(5, 2) DEFAULT 0.00,
            discount_amount DECIMAL(10, 2) DEFAULT 0.00,
            total_amount DECIMAL(10, 2) NOT NULL,
            order_status ENUM('pending', 'completed') DEFAULT 'pending',
            payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
            payment_method ENUM('cash', 'card', 'online') DEFAULT 'cash',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL,
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
            FOREIGN KEY (regular_customer_id) REFERENCES regular_customers(id) ON DELETE SET NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('staff', 'manager') NOT NULL DEFAULT 'staff',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS regular_customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(255),
            address TEXT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            discount_percentage DECIMAL(5, 2) DEFAULT 0.00,
            discount_amount DECIMAL(10, 2) DEFAULT 0.00,
            due_amount DECIMAL(10, 2) DEFAULT 0.00,
            total_amount DECIMAL(10, 2) DEFAULT 0.00,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS customer_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            transaction_type ENUM('credit', 'payment', 'order') NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            description TEXT,
            order_id INT,
            reference_number VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES regular_customers(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES order_details(id) ON DELETE SET NULL
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
        
        // Add payment_status if it doesn't exist
        if (!in_array('payment_status', $existingBookingsColumns)) {
            $alterSql = "ALTER TABLE bookings ADD COLUMN payment_status ENUM('pending', 'paid', 'partial', 'cancelled') DEFAULT 'pending' AFTER total_amount";
            $conn->query($alterSql);
        }
        
        // Add payment_method if it doesn't exist
        if (!in_array('payment_method', $existingBookingsColumns)) {
            $alterSql = "ALTER TABLE bookings ADD COLUMN payment_method ENUM('cash', 'card', 'online', 'bank_transfer') DEFAULT 'cash' AFTER payment_status";
            $conn->query($alterSql);
        }
        
        // Add payment_amount if it doesn't exist
        if (!in_array('payment_amount', $existingBookingsColumns)) {
            $alterSql = "ALTER TABLE bookings ADD COLUMN payment_amount DECIMAL(10, 2) DEFAULT 0.00 AFTER payment_method";
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
        
        // Add discount_percentage if it doesn't exist
        if (!in_array('discount_percentage', $existingOrderDetailsColumns)) {
            $alterSql = "ALTER TABLE order_details ADD COLUMN discount_percentage DECIMAL(5, 2) DEFAULT 0.00 AFTER tax";
            $conn->query($alterSql);
        }
        
        // Add discount_amount if it doesn't exist (or rename existing discount column)
        if (!in_array('discount_amount', $existingOrderDetailsColumns)) {
            if (in_array('discount', $existingOrderDetailsColumns)) {
                // Rename existing discount column to discount_amount
                $alterSql = "ALTER TABLE order_details CHANGE COLUMN discount discount_amount DECIMAL(10, 2) DEFAULT 0.00";
                $conn->query($alterSql);
            } else {
                $alterSql = "ALTER TABLE order_details ADD COLUMN discount_amount DECIMAL(10, 2) DEFAULT 0.00 AFTER discount_percentage";
                $conn->query($alterSql);
            }
        }
        
        // Add regular_customer_id if it doesn't exist
        if (!in_array('regular_customer_id', $existingOrderDetailsColumns)) {
            $alterSql = "ALTER TABLE order_details ADD COLUMN regular_customer_id INT NULL AFTER customer_name";
            $conn->query($alterSql);
            // Add foreign key constraint if regular_customers table exists
            $checkRegularCustomersTable = $conn->query("SHOW TABLES LIKE 'regular_customers'");
            if ($checkRegularCustomersTable && $checkRegularCustomersTable->num_rows > 0) {
                $fkCheck = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_details' AND COLUMN_NAME = 'regular_customer_id' AND REFERENCED_TABLE_NAME = 'regular_customers'");
                if (!$fkCheck || $fkCheck->num_rows == 0) {
                    $alterSql = "ALTER TABLE order_details ADD CONSTRAINT fk_order_regular_customer FOREIGN KEY (regular_customer_id) REFERENCES regular_customers(id) ON DELETE SET NULL";
                    $conn->query($alterSql); // Ignore errors if constraint already exists
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
    
    // Check and update menu table structure
    $checkMenuTable = $conn->query("SHOW TABLES LIKE 'menu'");
    if ($checkMenuTable && $checkMenuTable->num_rows > 0) {
        $menuColumns = $conn->query("SHOW COLUMNS FROM menu");
        $existingMenuColumns = [];
        while ($col = $menuColumns->fetch_assoc()) {
            $existingMenuColumns[] = $col['Field'];
        }
        
        // Add category_id if it doesn't exist
        if (!in_array('category_id', $existingMenuColumns)) {
            $alterSql = "ALTER TABLE menu ADD COLUMN category_id INT NULL AFTER price";
            if (!$conn->query($alterSql)) {
                // Ignore error if column already exists or other issues
            }
        }
        
        // Add subcategory_id if it doesn't exist
        if (!in_array('subcategory_id', $existingMenuColumns)) {
            $alterSql = "ALTER TABLE menu ADD COLUMN subcategory_id INT NULL AFTER category_id";
            if (!$conn->query($alterSql)) {
                // Ignore error if column already exists or other issues
            }
        }
        
        // Add foreign key constraints if tables exist and constraints don't exist
        $checkCategoriesTable = $conn->query("SHOW TABLES LIKE 'categories'");
        $checkSubcategoriesTable = $conn->query("SHOW TABLES LIKE 'subcategories'");
        
        if ($checkCategoriesTable && $checkCategoriesTable->num_rows > 0) {
            // Check if foreign key already exists for category_id
            $fkCheck = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'menu' AND COLUMN_NAME = 'category_id' AND REFERENCED_TABLE_NAME = 'categories'");
            if (!$fkCheck || $fkCheck->num_rows == 0) {
                $alterSql = "ALTER TABLE menu ADD CONSTRAINT fk_menu_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL";
                $conn->query($alterSql); // Ignore errors if constraint already exists
            }
        }
        
        if ($checkSubcategoriesTable && $checkSubcategoriesTable->num_rows > 0) {
            // Check if foreign key already exists for subcategory_id
            $fkCheck = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'menu' AND COLUMN_NAME = 'subcategory_id' AND REFERENCED_TABLE_NAME = 'subcategories'");
            if (!$fkCheck || $fkCheck->num_rows == 0) {
                $alterSql = "ALTER TABLE menu ADD CONSTRAINT fk_menu_subcategory FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL";
                $conn->query($alterSql); // Ignore errors if constraint already exists
            }
        }
        
        // Remove old category column if it exists (we use category_id instead)
        if (in_array('category', $existingMenuColumns) && in_array('category_id', $existingMenuColumns)) {
            $alterSql = "ALTER TABLE menu DROP COLUMN category";
            $conn->query($alterSql); // Ignore errors
        }
    }
    
    // Check and update regular_customers table structure
    $checkRegularCustomersTable = $conn->query("SHOW TABLES LIKE 'regular_customers'");
    if ($checkRegularCustomersTable && $checkRegularCustomersTable->num_rows > 0) {
        $regularCustomersColumns = $conn->query("SHOW COLUMNS FROM regular_customers");
        $existingRegularCustomersColumns = [];
        while ($col = $regularCustomersColumns->fetch_assoc()) {
            $existingRegularCustomersColumns[] = $col['Field'];
        }
        
        // Add discount_percentage if it doesn't exist
        if (!in_array('discount_percentage', $existingRegularCustomersColumns)) {
            $alterSql = "ALTER TABLE regular_customers ADD COLUMN discount_percentage DECIMAL(5, 2) DEFAULT 0.00 AFTER status";
            $conn->query($alterSql);
        }
        
        // Add discount_amount if it doesn't exist
        if (!in_array('discount_amount', $existingRegularCustomersColumns)) {
            $alterSql = "ALTER TABLE regular_customers ADD COLUMN discount_amount DECIMAL(10, 2) DEFAULT 0.00 AFTER discount_percentage";
            $conn->query($alterSql);
        }
        
        // Add due_amount if it doesn't exist
        if (!in_array('due_amount', $existingRegularCustomersColumns)) {
            $alterSql = "ALTER TABLE regular_customers ADD COLUMN due_amount DECIMAL(10, 2) DEFAULT 0.00 AFTER discount_amount";
            $conn->query($alterSql);
        }
        
        // Add total_amount if it doesn't exist
        if (!in_array('total_amount', $existingRegularCustomersColumns)) {
            $alterSql = "ALTER TABLE regular_customers ADD COLUMN total_amount DECIMAL(10, 2) DEFAULT 0.00 AFTER due_amount";
            $conn->query($alterSql);
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

