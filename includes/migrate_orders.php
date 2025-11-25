<?php
require_once 'db.php';

$conn = getConnection();

// Backup existing orders
$conn->query("CREATE TABLE IF NOT EXISTS orders_backup LIKE orders");
$conn->query("INSERT INTO orders_backup SELECT * FROM orders");

// Drop existing orders table
$conn->query("DROP TABLE IF EXISTS orders");

// Create new orders table with updated schema
$sql = "CREATE TABLE IF NOT EXISTS orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    branch_id INT,
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'processing', 'delivered', 'cancelled') DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL,
    delivery_date DATETIME,
    notes TEXT,
    FOREIGN KEY (customer_id) REFERENCES users(user_id),
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
)";
$conn->query($sql);

// Restore data from backup, setting branch_id based on customer's branch
$sql = "INSERT INTO orders (
    order_id, 
    customer_id, 
    branch_id,
    order_date, 
    status, 
    total_amount, 
    delivery_date, 
    notes
)
SELECT 
    o.order_id,
    o.customer_id,
    u.branch_id,
    o.order_date,
    o.status,
    o.total_amount,
    o.delivery_date,
    o.notes
FROM orders_backup o
JOIN users u ON o.customer_id = u.user_id";

$conn->query($sql);

// Drop backup table
$conn->query("DROP TABLE IF EXISTS orders_backup");

echo "Orders table migration completed successfully!";
?> 