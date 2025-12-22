<?php
// Database configuration for PRODUCTION (cPanel)
// Copy this file to database.php and update with your cPanel credentials

define('DB_HOST', 'localhost'); // Usually 'localhost' on cPanel
define('DB_USER', 'your_cpanel_db_username'); // Your cPanel database username
define('DB_PASS', 'your_secure_password'); // Your cPanel database password
define('DB_NAME', 'your_cpanel_db_name'); // Your cPanel database name

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Initialize database and tables
    initializeDatabase();
    
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
    
    // Create tables (same as your current database.php)
    // ... (copy the rest from your current database.php)
}


