<?php
require_once '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !hasRole('customer')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();
$user = getUserInfo($_SESSION['user_id']);

// Mark notifications as read if requested
if (isset($_POST['mark_read']) && is_array($_POST['mark_read'])) {
    $notification_ids = array_map('intval', $_POST['mark_read']);
    $ids_string = implode(',', $notification_ids);
    $conn->query("UPDATE notifications SET is_read = TRUE WHERE notification_id IN ($ids_string) AND user_id = {$user['user_id']}");
}

// Get all notifications for the user
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count unread notifications
$unread_count = array_reduce($notifications, function($carry, $item) {
    return $carry + ($item['is_read'] ? 0 : 1);
}, 0);
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3">Notifications</h1>
            <p class="text-muted">You have <?= $unread_count ?> unread notification<?= $unread_count !== 1 ? 's' : '' ?></p>
        </div>
        <?php if ($unread_count > 0): ?>
        <div class="col-md-4 text-end">
            <form action="" method="post" id="markReadForm">
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-check-double"></i> Mark All as Read
                </button>
                <?php foreach ($notifications as $notification): ?>
                    <?php if (!$notification['is_read']): ?>
                        <input type="hidden" name="mark_read[]" value="<?= $notification['notification_id'] ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-12">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                    <h5>No Notifications</h5>
                    <p class="text-muted">You don't have any notifications yet.</p>
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        $type_class = match($notification['type']) {
                            'maintenance' => 'text-danger',
                            'loyalty_reward' => 'text-success',
                            'order_update' => 'text-primary',
                            default => 'text-secondary'
                        };
                        $type_icon = match($notification['type']) {
                            'maintenance' => 'tools',
                            'loyalty_reward' => 'gift',
                            'order_update' => 'shopping-cart',
                            default => 'info-circle'
                        };
                        ?>
                        <div class="list-group-item list-group-item-action <?= $notification['is_read'] ? '' : 'active' ?>">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <h5 class="mb-1">
                                    <i class="fas fa-<?= $type_icon ?> <?= $type_class ?> me-2"></i>
                                    <?= htmlspecialchars($notification['title']) ?>
                                </h5>
                                <small class="text-muted">
                                    <?= timeAgo($notification['created_at']) ?>
                                </small>
                            </div>
                            <p class="mb-1"><?= htmlspecialchars($notification['message']) ?></p>
                            <?php if (!$notification['is_read']): ?>
                                <form method="post" class="mt-2">
                                    <input type="hidden" name="mark_read[]" value="<?= $notification['notification_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-light">
                                        <i class="fas fa-check"></i> Mark as Read
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Helper function to format time ago
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    }
    if ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    }
    if ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    }
    if ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    }
    if ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    }
    return 'just now';
}

require_once '../includes/footer.php';
?> 