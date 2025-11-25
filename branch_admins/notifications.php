<?php
require_once '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and is a branch admin
if (!isLoggedIn() || !hasRole('branch_admin')) {
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

// Get all notifications for the branch admin
$stmt = $conn->prepare("
    SELECT n.*, 
           CASE 
               WHEN n.type = 'maintenance' THEN (
                   SELECT CONCAT('Schedule ID: ', ms.schedule_id)
                   FROM maintenance_schedule ms
                   WHERE ms.branch_id = ?
                   AND n.message LIKE CONCAT('%', ms.schedule_id, '%')
                   LIMIT 1
               )
               ELSE NULL
           END as related_schedule
    FROM notifications n
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
");
$stmt->bind_param("ii", $user['branch_id'], $user['user_id']);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count unread notifications
$unread_count = array_reduce($notifications, function($carry, $item) {
    return $carry + ($item['is_read'] ? 0 : 1);
}, 0);

// Get active maintenance schedules for this branch
$stmt = $conn->prepare("
    SELECT * FROM maintenance_schedule 
    WHERE branch_id = ? 
    AND status IN ('scheduled', 'in_progress')
    AND end_date >= NOW()
    ORDER BY start_date ASC
");
$stmt->bind_param("i", $user['branch_id']);
$stmt->execute();
$maintenance_schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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

    <?php if (!empty($maintenance_schedules)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tools"></i> Active Maintenance Schedules
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maintenance_schedules as $schedule): ?>
                                <tr>
                                    <td><?= htmlspecialchars($schedule['title']) ?></td>
                                    <td><?= date('M d, Y h:i A', strtotime($schedule['start_date'])) ?></td>
                                    <td><?= date('M d, Y h:i A', strtotime($schedule['end_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $schedule['status'] === 'in_progress' ? 'info' : 'warning' ?>">
                                            <?= ucfirst($schedule['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($schedule['description']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                            <?php if ($notification['related_schedule']): ?>
                                <small class="text-muted"><?= htmlspecialchars($notification['related_schedule']) ?></small>
                            <?php endif; ?>
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