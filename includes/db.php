<?php
require_once 'config.php';

// Create connection
$conn = null;

try {
    // Create connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Create database if not exists
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    if (!$conn->query($sql)) {
        throw new Exception("Error creating database: " . $conn->error);
    }

    // Select the database
    if (!$conn->select_db(DB_NAME)) {
        throw new Exception("Error selecting database: " . $conn->error);
    }
    
    // Set charset to utf8mb4
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error setting charset: " . $conn->error);
    }

    // Test the connection with a simple query
    if (!$conn->query("SELECT 1")) {
        throw new Exception("Error testing connection: " . $conn->error);
    }

} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    throw new Exception("Database connection failed. Please try again later.");
}

// Function to get database connection
function getConnection() {
    global $conn;
    if (!$conn) {
        throw new Exception("Database connection is not initialized");
    }
    return $conn;
}
?> 