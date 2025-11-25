<?php
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

require_once 'includes/db.php';

// Get user's role and information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get the last message timestamp from the request
$last_timestamp = isset($_GET['last_timestamp']) ? $_GET['last_timestamp'] : date('Y-m-d H:i:s');

try {
    if ($role === 'customer') {
        // Get branch admin ID
        $stmt = $conn->prepare("
            SELECT u.user_id 
            FROM users u
            JOIN users c ON c.branch_id = u.branch_id
            WHERE u.role = 'branch_admin' 
            AND u.status = 'active'
            AND c.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $branch_admin = $stmt->get_result()->fetch_assoc();
        
        if (!$branch_admin) {
            throw new Exception('Branch admin not found');
        }
        
        // Get new messages
        $stmt = $conn->prepare("
            SELECT m.*, 
                   CASE 
                       WHEN m.sender_id = ? THEN 'sent'
                       ELSE 'received'
                   END as message_type,
                   u.name as sender_name
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE ((m.sender_id = ? AND m.receiver_id = ?)
                  OR (m.sender_id = ? AND m.receiver_id = ?))
            AND m.timestamp > ?
            ORDER BY m.timestamp ASC
        ");
        $stmt->bind_param("iiiiss", 
            $user_id,
            $user_id, $branch_admin['user_id'],
            $branch_admin['user_id'], $user_id,
            $last_timestamp
        );
        
    } elseif ($role === 'branch_admin') {
        // Get customer ID from request
        $customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
        
        if (!$customer_id) {
            throw new Exception('Customer ID not provided');
        }
        
        // Verify customer belongs to admin's branch
        $stmt = $conn->prepare("
            SELECT user_id 
            FROM users 
            WHERE user_id = ? 
            AND branch_id = (SELECT branch_id FROM users WHERE user_id = ?)
        ");
        $stmt->bind_param("ii", $customer_id, $user_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new Exception('Invalid customer ID');
        }
        
        // Get new messages
        $stmt = $conn->prepare("
            SELECT m.*, 
                   CASE 
                       WHEN m.sender_id = ? THEN 'sent'
                       ELSE 'received'
                   END as message_type,
                   u.name as sender_name
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE ((m.sender_id = ? AND m.receiver_id = ?)
                  OR (m.sender_id = ? AND m.receiver_id = ?))
            AND m.timestamp > ?
            ORDER BY m.timestamp ASC
        ");
        $stmt->bind_param("iiiiss", 
            $user_id,
            $user_id, $customer_id,
            $customer_id, $user_id,
            $last_timestamp
        );
    } else {
        throw new Exception('Invalid user role');
    }
    
    $stmt->execute();
    $new_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Mark messages as read
    if (!empty($new_messages)) {
        $stmt = $conn->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE receiver_id = ? 
            AND timestamp <= NOW()
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    
    // Return messages as HTML
    foreach ($new_messages as $message) {
        ?>
        <div class="message <?php echo $message['message_type']; ?> mb-3">
            <div class="message-content <?php echo $message['message_type'] === 'sent' ? 'bg-primary text-white' : 'bg-light'; ?> p-2 rounded">
                <?php echo nl2br(htmlspecialchars($message['message_text'])); ?>
                <div class="message-meta small <?php echo $message['message_type'] === 'sent' ? 'text-white-50' : 'text-muted'; ?>">
                    <?php echo date('M d, Y h:i A', strtotime($message['timestamp'])); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
} catch (Exception $e) {
    http_response_code(400);
    exit($e->getMessage());
}
?> 