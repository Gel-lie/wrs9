<?php
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and is a branch admin
if (!isLoggedIn() || !hasRole('branch_admin')) {
    redirectWith('/waterrefillingstation/login.php', 'Unauthorized access', 'danger');
    exit();
}

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

$conn = getConnection();
$user = getUserInfo($_SESSION['user_id']);
$branch_id = $user['branch_id'];

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectWith('/waterrefillingstation/branch_admins/orders.php', 'Invalid order ID', 'danger');
    exit();
}

$order_id = (int)$_GET['id'];

try {
    // Start transaction
    $conn->begin_transaction();

    // Get order details and verify it belongs to this branch
    $stmt = $conn->prepare("
        SELECT o.*, u.name as customer_name, u.contact_number, u.email, u.user_id as customer_id
        FROM orders o
        JOIN users u ON o.customer_id = u.user_id
        WHERE o.order_id = ? AND o.branch_id = ? AND o.status = 'processing'
    ");
    $stmt->bind_param("ii", $order_id, $branch_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception("Order not found or cannot be completed");
    }

    // Update order status to delivered
    $timestamp = date('Y-m-d H:i:s');
    $note = "[" . $timestamp . "] Order marked as delivered by " . $user['name'];
    
    $stmt = $conn->prepare("
        UPDATE orders 
        SET 
            status = 'delivered',
            notes = IF(notes IS NULL OR notes = '', ?, CONCAT(notes, '\n', ?))
        WHERE order_id = ? AND branch_id = ?
    ");
    $stmt->bind_param("ssii", $note, $note, $order_id, $branch_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update order status");
    }

    // Update inventory for the ordered items
    $stmt = $conn->prepare("
        SELECT od.product_id, od.quantity
        FROM order_details od
        WHERE od.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $ordered_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Update inventory quantities
    foreach ($ordered_items as $item) {
        $stmt = $conn->prepare("
            UPDATE inventory 
            SET quantity = quantity - ?
            WHERE branch_id = ? AND product_id = ?
        ");
        $stmt->bind_param("iii", $item['quantity'], $branch_id, $item['product_id']);
        $stmt->execute();
    }

    // Add loyalty points for the customer (1 point per 100 pesos spent)
    $points_earned = floor($order['total_amount'] / 100);
    if ($points_earned > 0) {
        $stmt = $conn->prepare("
            INSERT INTO loyalty (customer_id, points) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE points = points + VALUES(points)
        ");
        $stmt->bind_param("ii", $order['customer_id'], $points_earned);
        $stmt->execute();
    }

    // Log the activity
    logActivity($user['user_id'], 'order_completed', "Completed order #$order_id for customer " . $order['customer_name']);

    // Commit transaction
    $conn->commit();

    // Prepare notification message
    $message = "Dear " . $order['customer_name'] . ",\n\n";
    $message .= "Your order #$order_id has been delivered successfully.\n";
    $message .= "Thank you for your business!\n\n";
    if ($points_earned > 0) {
        $message .= "You earned $points_earned loyalty points from this purchase.\n";
    }
    $message .= "\nRegards,\nWater Refilling Station";

    // Store message in the messages table
    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, receiver_id, message_text)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iis", $user['user_id'], $order['customer_id'], $message);
    $stmt->execute();

    // Redirect back with success message
    redirectWith(
        '/waterrefillingstation/branch_admins/orders.php', 
        "Order #$order_id has been marked as delivered successfully. " . 
        ($points_earned > 0 ? "Customer earned $points_earned loyalty points." : ""),
        'success'
    );

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Redirect back with error message
    redirectWith(
        '/waterrefillingstation/branch_admins/orders.php', 
        'Error completing order: ' . $e->getMessage(),
        'danger'
    );
} 