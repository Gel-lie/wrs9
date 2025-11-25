<?php
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !hasRole('customer')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if order ID is provided
if (!isset($_POST['order_id']) || !is_numeric($_POST['order_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

$order_id = (int)$_POST['order_id'];
$user_id = $_SESSION['user_id'];
$conn = getConnection();

// Start transaction
$conn->begin_transaction();

try {
    // Check if order exists and belongs to the user
    $stmt = $conn->prepare("
        SELECT status, branch_id 
        FROM orders 
        WHERE order_id = ? AND customer_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    if (!$order) {
        throw new Exception('Order not found or does not belong to you');
    }

    if ($order['status'] !== 'pending') {
        throw new Exception('Only pending orders can be cancelled');
    }

    // Update order status to cancelled
    $stmt = $conn->prepare("
        UPDATE orders 
        SET status = 'cancelled',
            notes = CONCAT(COALESCE(notes, ''), '\nCancelled by customer on ', NOW())
        WHERE order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    // Log the activity
    $activity_desc = "Order #$order_id cancelled by customer";
    $stmt = $conn->prepare("
        INSERT INTO activity_log (user_id, activity_type, description) 
        VALUES (?, 'order_cancelled', ?)
    ");
    $stmt->bind_param("is", $user_id, $activity_desc);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Order cancelled successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} 