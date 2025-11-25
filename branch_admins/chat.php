<?php
require_once '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a branch admin
if (!isLoggedIn() || !hasRole('branch_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();
$user = getUserInfo($_SESSION['user_id']);
$branch_id = $user['branch_id'];

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'send_message') {
            $receiver_id = (int)$_POST['receiver_id'];
            $message_text = trim($_POST['message']);
            
            if (empty($message_text)) {
                throw new Exception("Message cannot be empty");
            }

            // Verify customer belongs to this branch
            $stmt = $conn->prepare("
                SELECT user_id 
                FROM users 
                WHERE user_id = ? AND branch_id = ? AND role = 'customer'
            ");
            $stmt->bind_param("ii", $receiver_id, $branch_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                throw new Exception("Invalid recipient");
            }

            // Insert message
            $stmt = $conn->prepare("
                INSERT INTO messages (sender_id, receiver_id, message_text)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iis", $_SESSION['user_id'], $receiver_id, $message_text);
            
            if ($stmt->execute()) {
                $success = "Message sent successfully";
            } else {
                throw new Exception("Failed to send message");
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get list of customers for this branch with their latest message
$customers_query = "
    SELECT 
        u.user_id,
        u.name,
        u.email,
        u.contact_number,
        COALESCE(u.sitio_purok, 'Not specified') as address,
        COALESCE(
            (SELECT message_text 
             FROM messages 
             WHERE (sender_id = u.user_id OR receiver_id = u.user_id)
             ORDER BY timestamp DESC 
             LIMIT 1
            ), 'No messages yet'
        ) as latest_message,
        COALESCE(
            (SELECT timestamp 
             FROM messages 
             WHERE (sender_id = u.user_id OR receiver_id = u.user_id)
             ORDER BY timestamp DESC 
             LIMIT 1
            ), '1970-01-01 00:00:00'
        ) as latest_message_time,
        (
            SELECT COUNT(*) 
            FROM messages 
            WHERE receiver_id = ? 
            AND sender_id = u.user_id 
            AND is_read = 0
        ) as unread_count
    FROM users u
    WHERE u.branch_id = ? AND u.role = 'customer'
    ORDER BY latest_message_time DESC
";

$stmt = $conn->prepare($customers_query);
$stmt->bind_param("ii", $_SESSION['user_id'], $branch_id);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get chat history if customer is selected
$selected_customer = null;
$chat_history = [];

if (isset($_GET['customer_id'])) {
    $customer_id = (int)$_GET['customer_id'];
    
    // Get customer details
    $stmt = $conn->prepare("
        SELECT user_id, name, email, contact_number, sitio_purok
        FROM users 
        WHERE user_id = ? AND branch_id = ? AND role = 'customer'
    ");
    $stmt->bind_param("ii", $customer_id, $branch_id);
    $stmt->execute();
    $selected_customer = $stmt->get_result()->fetch_assoc();

    if ($selected_customer) {
        // Get chat history
        $chat_query = "
            SELECT 
                m.*,
                CASE 
                    WHEN m.sender_id = ? THEN 'sent'
                    ELSE 'received'
                END as message_type
            FROM messages m
            WHERE (m.sender_id = ? AND m.receiver_id = ?)
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.timestamp ASC
        ";
        
        $stmt = $conn->prepare($chat_query);
        if (!$stmt) {
            die('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("iiiii", 
            $_SESSION['user_id'],
            $_SESSION['user_id'], $customer_id,
            $customer_id, $_SESSION['user_id']
        );
        
        if (!$stmt->execute()) {
            die('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if (!$result) {
            die('Get result failed: ' . $stmt->error);
        }
        
        $chat_history = $result->fetch_all(MYSQLI_ASSOC);

        // Mark messages as read
        $stmt = $conn->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $stmt->bind_param("ii", $customer_id, $_SESSION['user_id']);
        $stmt->execute();
    }
}

// Debug information
if (isset($_GET['debug'])) {
    echo '<pre>';
    echo "Session user_id: " . $_SESSION['user_id'] . "\n";
    echo "Branch ID: " . $branch_id . "\n";
    if (isset($customer_id)) {
        echo "Selected customer ID: " . $customer_id . "\n";
    }
    echo "Number of messages in chat history: " . count($chat_history) . "\n";
    echo '</pre>';
}
?>

<div class="container-fluid py-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Customers List -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>Customers
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($customers as $customer): ?>
                            <a href="chat.php?customer_id=<?= (int)$customer['user_id'] ?>" 
                               class="list-group-item list-group-item-action <?= isset($_GET['customer_id']) && $_GET['customer_id'] == $customer['user_id'] ? 'active' : '' ?>">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <h6 class="mb-1"><?= htmlspecialchars($customer['name'] ?? 'Unknown Customer') ?></h6>
                                    <?php if ($customer['unread_count'] > 0): ?>
                                        <span class="badge bg-danger rounded-pill"><?= $customer['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <?= htmlspecialchars(substr($customer['latest_message'], 0, 50)) ?>...
                                </small>
                                <?php if ($customer['latest_message_time'] !== '1970-01-01 00:00:00'): ?>
                                    <br>
                                    <small class="text-muted">
                                        <?= date('M d, Y h:ia', strtotime($customer['latest_message_time'])) ?>
                                    </small>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($customers)): ?>
                            <div class="list-group-item text-center text-muted">
                                No customers found
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="col-md-8">
            <?php if ($selected_customer): ?>
                <div class="card">
                    <!-- Customer Info Header -->
                    <div class="card-header bg-info text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-0">
                                    <?= htmlspecialchars($selected_customer['name']) ?>
                                </h5>
                                <small>
                                    <?= htmlspecialchars($selected_customer['email']) ?> |
                                    <?= htmlspecialchars($selected_customer['contact_number'] ?? 'No contact number') ?>
                                </small>
                            </div>
                            <div>
                                <button type="button" 
                                        class="btn btn-light btn-sm" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#customerDetails">
                                    <i class="fas fa-info-circle"></i> Details
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Collapsible Customer Details -->
                    <div class="collapse" id="customerDetails">
                        <div class="card-body bg-light border-bottom">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Address:</strong><br>
                                    <?= htmlspecialchars($selected_customer['sitio_purok'] ?? 'Not specified') ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Contact:</strong><br>
                                    <?= htmlspecialchars($selected_customer['contact_number'] ?? 'Not provided') ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Messages -->
                    <div class="card-body chat-messages" style="height: 400px; overflow-y: auto;">
                        <?php if (!empty($chat_history)): ?>
                            <?php foreach ($chat_history as $message): ?>
                                <div class="chat-message <?= htmlspecialchars($message['message_type']) ?>">
                                    <div class="message-content">
                                        <?= htmlspecialchars($message['message_text']) ?>
                                        <div class="message-time">
                                            <small class="text-muted">
                                                <?= date('M d, Y h:ia', strtotime($message['timestamp'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted mt-4">
                                <i class="fas fa-comments fa-3x mb-3"></i>
                                <p>No messages yet. Start the conversation!</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Message Input -->
                    <div class="card-footer">
                        <form action="chat.php?customer_id=<?= (int)$selected_customer['user_id'] ?>" 
                              method="post" 
                              class="message-form">
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="receiver_id" value="<?= $selected_customer['user_id'] ?>">
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control" 
                                       name="message" 
                                       placeholder="Type your message..." 
                                       required>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                        <h5>Select a customer to start chatting</h5>
                        <p class="text-muted">Choose a customer from the list to view chat history and send messages.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.chat-messages {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.chat-message {
    display: flex;
    margin-bottom: 1rem;
}

.chat-message.received {
    justify-content: flex-start;
}

.chat-message.sent {
    justify-content: flex-end;
}

.message-content {
    max-width: 70%;
    padding: 0.75rem 1rem;
    border-radius: 1rem;
    position: relative;
}

.chat-message.received .message-content {
    background-color: #f0f2f5;
    border-top-left-radius: 0.25rem;
}

.chat-message.sent .message-content {
    background-color: #0d6efd;
    color: white;
    border-top-right-radius: 0.25rem;
}

.chat-message.sent .message-time {
    color: rgba(255, 255, 255, 0.7) !important;
}

.message-time {
    margin-top: 0.25rem;
    font-size: 0.75rem;
}

.list-group-item.active {
    z-index: 2;
    color: #fff;
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.list-group-item.active small {
    color: rgba(255, 255, 255, 0.7) !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Scroll chat to bottom
    const chatMessages = document.querySelector('.chat-messages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Auto-submit on enter in message input
    const messageForm = document.querySelector('.message-form');
    if (messageForm) {
        const messageInput = messageForm.querySelector('input[name="message"]');
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                messageForm.submit();
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>