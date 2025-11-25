<?php
require_once '../includes/functions.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !hasRole('customer')) {
    header("Location: /login.php");
    exit();
}

require_once '../includes/db.php';

// Get user's information
$user = getUserInfo($_SESSION['user_id']);

// Get branch admin
$stmt = $conn->prepare("
    SELECT user_id, name
    FROM users
    WHERE role = 'branch_admin'
    AND branch_id = ?
    AND status = 'active'
");
$stmt->bind_param("i", $user['branch_id']);
$stmt->execute();
$branch_admin = $stmt->get_result()->fetch_assoc();

// Process new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = sanitize($_POST['message']);

    if (!empty($message)) {
        $stmt = $conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, message_text)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iis", $_SESSION['user_id'], $branch_admin['user_id'], $message);

        if ($stmt->execute()) {
            // Redirect to prevent form resubmission
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}

// Get chat history
$stmt = $conn->prepare("
    SELECT m.*,
           s.name as sender_name,
           r.name as receiver_name
    FROM messages m
    JOIN users s ON m.sender_id = s.user_id
    JOIN users r ON m.receiver_id = r.user_id
    WHERE (m.sender_id = ? AND m.receiver_id = ?)
    OR (m.sender_id = ? AND m.receiver_id = ?)
    ORDER BY m.timestamp DESC
    LIMIT 50
");
$stmt->bind_param("iiii",
    $_SESSION['user_id'], $branch_admin['user_id'],
    $branch_admin['user_id'], $_SESSION['user_id']
);
$stmt->execute();
$messages = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));

// Mark messages as read
$stmt = $conn->prepare("
    UPDATE messages
    SET is_read = 1
    WHERE receiver_id = ?
    AND sender_id = ?
");
$stmt->bind_param("ii", $_SESSION['user_id'], $branch_admin['user_id']);
$stmt->execute();

require_once '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <!-- put here the link in chatbot -->
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Chat with Branch Admin
                            <small class="d-block"><?php echo htmlspecialchars($branch_admin['name']); ?></small>
                        </h5>


                    </div>
                </div>

                <div class="card-body">
                    <!-- Chat Messages -->
                    <div class="chat-messages mb-4" style="height: 400px; overflow-y: auto;">
                        <?php if (empty($messages)): ?>
                            <p class="text-muted text-center">No messages yet. Start the conversation!</p>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="chat-message <?php echo $message['sender_id'] === $_SESSION['user_id'] ? 'sent' : 'received'; ?>">
                                    <div class="message-content">
                                        <?php echo nl2br(htmlspecialchars($message['message_text'])); ?>
                                        <div class="message-time">
                                            <small class="text-muted">
                                                <?php echo date('M d, Y h:i A', strtotime($message['timestamp'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Message Form -->
                    <form method="POST" class="message-form">
                        <div class="input-group">
                            <textarea class="form-control"
                                      name="message"
                                      placeholder="Type your message..."
                                      rows="2"
                                      required></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Chat Guidelines -->
            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title">Chat Guidelines</h5>
                    <ul class="mb-0">
                        <li>Keep conversations professional and respectful</li>
                        <li>Response time may vary during non-business hours</li>
                        <li>For urgent matters, please contact us directly</li>
                        <li>Do not share sensitive personal information</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Scroll to bottom of chat
    const chatMessages = document.querySelector('.chat-messages');
    chatMessages.scrollTop = chatMessages.scrollHeight;

    // Auto-resize textarea
    const textarea = document.querySelector('textarea');
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    // Auto-refresh chat (every 30 seconds)
    setInterval(function() {
        location.reload();
    }, 30000);
});

// Chatbot functionality
const chatContainer = document.createElement('div');
chatContainer.className = 'chat-container';
chatContainer.id = 'chat-container';
chatContainer.innerHTML = `
    <div class="chat-header">
        <h2>WRS Chat Support</h2>
        <button class="close-btn" id="close-btn">&times;</button>
    </div>
    <div class="chat-body" id="chat-body"></div>
    <div class="chat-footer">
        <input type="text" id="user-input" placeholder="Type a message...">
        <button onclick="sendMessage()">Send</button>
    </div>
`;
document.body.appendChild(chatContainer);

// Floating button
const floatingButton = document.createElement('button');
floatingButton.className = 'floating-chatbot-btn';
floatingButton.innerHTML = '<i class="fas fa-robot"></i>';
document.body.appendChild(floatingButton);

// Chat styles
const chatStyles = `
<style>
.chat-container { position: fixed; right: 20px; bottom: 80px; width: 380px; max-width: 90vw; height: 520px; max-height: 80vh; background: #fff; border-radius: 15px; box-shadow: 0 0 15px rgba(0,0,0,0.15); display: none; flex-direction: column; overflow: hidden; z-index: 1050; animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.chat-header { background: linear-gradient(135deg, #007bff, #00c3a3); color: #fff; padding: 12px; display: flex; justify-content: space-between; align-items: center; }
.chat-header h2 { font-size: 16px; margin: 0; }
.close-btn { background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; }
.chat-body { flex: 1; padding: 10px; overflow-y: auto; background: #f4faff; display: flex; flex-direction: column; gap: 8px; }
.message { display: flex; margin: 8px 0; }
.bot { justify-content: flex-start; }
.user { justify-content: flex-end; }
.bubble { max-width: 70%; padding: 10px 14px; border-radius: 15px; font-size: 15px; word-wrap: break-word; }
.bot .bubble { background-color: #e6f0ff; color: #333; border-top-left-radius: 0; }
.user .bubble { background-color: #c4f5d1; color: #000; border-top-right-radius: 0; }
.chat-footer { display: flex; padding: 10px; background: #fff; border-top: 1px solid #eee; }
.chat-footer input { flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 20px; outline: none; }
.chat-footer button { background: #007bff; color: #fff; border: none; border-radius: 20px; padding: 8px 14px; margin-left: 10px; cursor: pointer; }
.chat-footer button:hover { background: #00c3a3; }
.floating-chatbot-btn { position: fixed; bottom: 20px; right: 20px; width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #007bff, #00c3a3); border: none; color: #fff; font-size: 24px; cursor: pointer; box-shadow: 0 4px 8px rgba(0,0,0,0.2); z-index: 1000; display: flex; align-items: center; justify-content: center; }
.floating-chatbot-btn:hover { transform: scale(1.1); }
@media (max-width: 768px) { .chat-container { width: 90vw; height: 60vh; bottom: 70px; } .chat-footer input { font-size: 14px; } .chat-footer button { padding: 6px 10px; font-size: 14px; } }
@media (max-width: 480px) { .chat-container { width: 95vw; height: 55vh; bottom: 60px; } .chat-header h2 { font-size: 14px; } .chat-footer input { font-size: 13px; } .chat-footer button { padding: 5px 8px; font-size: 13px; } }
</style>
`;
document.head.insertAdjacentHTML('beforeend', chatStyles);

floatingButton.addEventListener('click', (e) => {
    e.preventDefault();
    chatContainer.style.display = 'flex';
});

document.getElementById('close-btn').addEventListener('click', () => {
    chatContainer.style.display = 'none';
});

async function sendMessage() {
    const input = document.getElementById('user-input');
    const message = input.value.trim();
    if (!message) return;
    const chatBody = document.getElementById('chat-body');
    chatBody.innerHTML += `<div class="message user"><div class="bubble">${message}</div></div>`;
    input.value = '';
    chatBody.scrollTop = chatBody.scrollHeight;
    try {
        const res = await fetch('../chatbot_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'message=' + encodeURIComponent(message)
        });
        const data = await res.json();
        chatBody.innerHTML += `<div class="message bot"><div class="bubble">${data.reply.replace(/\n/g, '<br>')}</div></div>`;
        chatBody.scrollTop = chatBody.scrollHeight;
    } catch {
        chatBody.innerHTML += `<div class="message bot"><div class="bubble">Error contacting bot.</div></div>`;
    }
}
</script>

<style>
.chat-messages {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 0, 0, 0.2) transparent;
}
.chat-messages::-webkit-scrollbar {
    width: 6px;
}
.chat-messages::-webkit-scrollbar-track {
    background: transparent;
}
.chat-messages::-webkit-scrollbar-thumb {
    background-color: rgba(0, 0, 0, 0.2);
    border-radius: 3px;
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
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
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

textarea {
    resize: none;
    overflow: hidden;
}
</style>

<?php require_once '../includes/footer.php'; ?>
