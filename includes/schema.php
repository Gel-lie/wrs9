<?php
require_once 'db.php';

$conn = getConnection();

// Create branches table
$sql = "CREATE TABLE IF NOT EXISTS branches (
    branch_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Create barangays table
$sql = "CREATE TABLE IF NOT EXISTS barangays (
    barangay_id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_name VARCHAR(100) NOT NULL,
    branch_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
)";
$conn->query($sql);

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('customer', 'branch_admin', 'super_admin') NOT NULL,
    branch_id INT,
    barangay_id INT,
    name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20),
    sitio_purok VARCHAR(100),
    registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
    FOREIGN KEY (barangay_id) REFERENCES barangays(barangay_id)
)";
$conn->query($sql);

// Create products table
$sql = "CREATE TABLE IF NOT EXISTS products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    image_path VARCHAR(255),
    category ENUM('container', 'dispenser', 'refill', 'accessory') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Create inventory table
$sql = "CREATE TABLE IF NOT EXISTS inventory (
    inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT,
    product_id INT,
    quantity INT NOT NULL DEFAULT 0,
    low_stock_threshold INT DEFAULT 10,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
)";
$conn->query($sql);

// Create orders table
$sql = "CREATE TABLE IF NOT EXISTS orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    branch_id INT,
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'processing', 'delivered', 'cancelled') DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL,
    delivery_date DATETIME,
    sitio_purok VARCHAR(100),
    delivery_address TEXT,
    notes TEXT,
    FOREIGN KEY (customer_id) REFERENCES users(user_id),
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
)";
$conn->query($sql);

// Create order_details table
$sql = "CREATE TABLE IF NOT EXISTS order_details (
    order_detail_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
)";
$conn->query($sql);

// Create notifications table
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('maintenance', 'loyalty_reward', 'order_update', 'system') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)";
$conn->query($sql);

// Create maintenance_schedule table
$sql = "CREATE TABLE IF NOT EXISTS maintenance_schedule (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
)";
$conn->query($sql);

// Create loyalty table
$sql = "CREATE TABLE IF NOT EXISTS loyalty (
    loyalty_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNIQUE,
    years_of_loyalty INT DEFAULT 0,
    reward_level VARCHAR(50),
    points INT DEFAULT 0,
    last_calculated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_reward_claim DATETIME NULL,
    years_registered INT DEFAULT 0,
    FOREIGN KEY (customer_id) REFERENCES users(user_id)
)";
$conn->query($sql);

// Create messages table
$sql = "CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT,
    receiver_id INT,
    message_text TEXT NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id),
    FOREIGN KEY (receiver_id) REFERENCES users(user_id)
)";
$conn->query($sql);

// Create tasks table
$sql = "CREATE TABLE IF NOT EXISTS tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT,
    task_type ENUM('maintenance', 'compliance') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATETIME NOT NULL,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
)";
$conn->query($sql);

// Create activity_log table
$sql = "CREATE TABLE IF NOT EXISTS activity_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    activity_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)";
$conn->query($sql);

// Create system_settings table
$sql = "CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Create announcements table
$sql = "CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('price_update', 'promo', 'general', 'maintenance') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    start_date DATETIME NOT NULL,
    end_date DATETIME,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
)";
$conn->query($sql);

// Insert default system settings
$default_settings = [
    ['company_name', 'Water Refilling Station', 'Company name displayed throughout the system'],
    ['contact_email', '', 'Primary contact email for the business'],
    ['contact_phone', '', 'Primary contact phone number'],
    ['business_hours', '8:00 AM - 8:00 PM', 'Regular business hours'],
    ['minimum_order', '1', 'Minimum quantity per order'],
    ['delivery_fee', '50', 'Standard delivery fee in pesos'],
    ['loyalty_points_ratio', '1', 'Points earned per peso spent'],
    ['maintenance_alert_days', '30', 'Days before maintenance due to show alert'],
    ['low_stock_threshold', '10', 'Product quantity to trigger low stock alert'],
    ['max_appointments_per_day', '10', 'Maximum number of appointments allowed per day'],
    ['sms_notifications', '0', 'Enable SMS notifications (1 for enabled, 0 for disabled)'],
    ['email_notifications', '1', 'Enable email notifications (1 for enabled, 0 for disabled)']
];

$stmt = $conn->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)");
foreach ($default_settings as $setting) {
    $stmt->bind_param("sss", $setting[0], $setting[1], $setting[2]);
    $stmt->execute();
}

// Insert default branches
$sql = "INSERT IGNORE INTO branches (branch_id, branch_name) VALUES 
(1, 'Lazareto'),
(2, 'Calero')";
$conn->query($sql);

// Insert default barangays
$sql = "INSERT IGNORE INTO barangays (barangay_id, barangay_name, branch_id) VALUES 
(1, 'Barangay 1', 1),
(2, 'Barangay 2', 1),
(3, 'Barangay 3', 1),
(4, 'Barangay 4', 2),
(5, 'Barangay 5', 2),
(6, 'Barangay 6', 2)";
$conn->query($sql);

// Insert super admin account
$superadmin_password = password_hash('admin123', PASSWORD_DEFAULT);
$sql = "INSERT IGNORE INTO users (username, password, email, role, name) VALUES 
('superadmin', '$superadmin_password', 'superadmin@water.com', 'super_admin', 'Super Admin')";
$conn->query($sql);

// Drop appointments table since it's no longer needed
$sql = "DROP TABLE IF EXISTS appointments";
$conn->query($sql);

echo "Database schema created successfully!";
?> 