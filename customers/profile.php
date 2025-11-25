<?php
require_once '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !hasRole('customer')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $contact_number = $_POST['contact_number'];
    $sitio_purok = $_POST['sitio_purok'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get current user data
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        redirectWith('profile.php', 'Current password is incorrect', 'danger');
        exit;
    }
    
    // Update basic info
    $stmt = $conn->prepare("
        UPDATE users 
        SET name = ?, email = ?, contact_number = ?, sitio_purok = ?
        WHERE user_id = ?
    ");
    $stmt->bind_param("ssssi", $name, $email, $contact_number, $sitio_purok, $user_id);
    
    if ($stmt->execute()) {
        $success = true;
        
        // Update password if provided
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                redirectWith('profile.php', 'New passwords do not match', 'danger');
                exit;
            }
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if (!$stmt->execute()) {
                redirectWith('profile.php', 'Error updating password', 'danger');
                exit;
            }
        }
        
        // Log the activity
        logActivity($user_id, 'profile_update', 'Updated profile information');
        redirectWith('profile.php', 'Profile updated successfully', 'success');
    } else {
        redirectWith('profile.php', 'Error updating profile', 'danger');
    }
}

// Get user, branch, and loyalty data
$stmt = $conn->prepare("
    SELECT 
        u.*,
        b.branch_name,
        l.points,
        l.reward_level,
        l.years_of_loyalty,
        CASE 
            WHEN l.reward_level = 'Platinum' THEN '20%'
            WHEN l.reward_level = 'Gold' THEN '15%'
            WHEN l.reward_level = 'Silver' THEN '10%'
            ELSE '5%'
        END as discount_rate,
        (SELECT COUNT(*) FROM orders WHERE customer_id = u.user_id) as total_orders,
        (SELECT COUNT(*) FROM orders WHERE customer_id = u.user_id AND status = 'delivered') as completed_orders
    FROM users u
    JOIN branches b ON u.branch_id = b.branch_id
    LEFT JOIN loyalty l ON u.user_id = l.customer_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get recent orders
$stmt = $conn->prepare("
    SELECT o.*, 
           SUM(od.quantity * od.price) as total_amount,
           COUNT(od.order_detail_id) as total_items
    FROM orders o
    JOIN order_details od ON o.order_id = od.order_id
    WHERE o.customer_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent activity
$stmt = $conn->prepare("
    SELECT activity_type, description, created_at, ip_address
    FROM activity_log
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['username'] ?? '') ?>" readonly>
                            <div class="form-text">Username cannot be changed</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="contact_number" value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Sitio/Purok</label>
                            <input type="text" class="form-control" name="sitio_purok" value="<?= htmlspecialchars($user['sitio_purok'] ?? '') ?>">
                        </div>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password">
                            <div class="form-text">Leave blank to keep current password</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Orders</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_orders)): ?>
                    <p class="text-muted mb-0">No orders yet</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>#<?= $order['order_id'] ?></td>
                                    <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                                    <td><?= $order['total_items'] ?> items</td>
                                    <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= getStatusColor($order['status']) ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end">
                        <a href="orders.php" class="btn btn-sm btn-primary">View All Orders</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-crown"></i> Loyalty Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h3 class="mb-0"><?= htmlspecialchars($user['reward_level'] ?? 'Basic') ?></h3>
                        <p class="text-muted">Membership Level</p>
                        
                        <div class="progress mb-2">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?= ($user['points'] ?? 0) % 100 ?>%"
                                 aria-valuenow="<?= ($user['points'] ?? 0) % 100 ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                        <small class="text-muted">
                            <?= $user['points'] ?? 0 ?> points • <?= $user['discount_rate'] ?? '5%' ?> discount
                        </small>
                    </div>
                    
                    <dl class="row mb-0">
                        <dt class="col-sm-7">Years as Member</dt>
                        <dd class="col-sm-5"><?= $user['years_of_loyalty'] ?? 0 ?></dd>
                        
                        <dt class="col-sm-7">Total Orders</dt>
                        <dd class="col-sm-5"><?= number_format($user['total_orders'] ?? 0) ?></dd>
                        
                        <dt class="col-sm-7">Completed Orders</dt>
                        <dd class="col-sm-5"><?= number_format($user['completed_orders'] ?? 0) ?></dd>
                        
                        <dt class="col-sm-7">Branch</dt>
                        <dd class="col-sm-5"><?= htmlspecialchars($user['branch_name'] ?? 'Not Assigned') ?></dd>
                    </dl>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Activity</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_activity)): ?>
                    <p class="text-muted mb-0">No recent activity</p>
                    <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($recent_activity as $activity): ?>
                        <div class="timeline-item">
                            <small class="text-muted">
                                <?= date('M d, Y h:i A', strtotime($activity['created_at'])) ?>
                            </small>
                            <p class="mb-1"><?= htmlspecialchars($activity['description']) ?></p>
                            <small class="text-muted">
                                IP: <?= htmlspecialchars($activity['ip_address'] ?? 'localhost') ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding: 0;
    margin: 0;
    list-style: none;
}

.timeline-item {
    position: relative;
    padding-bottom: 1rem;
    padding-left: 1.5rem;
    border-left: 2px solid #e9ecef;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -0.5rem;
    top: 0.25rem;
    height: 0.75rem;
    width: 0.75rem;
    border-radius: 50%;
    background: #6c757d;
    border: 2px solid #fff;
}

<?php
function getStatusColor($status) {
    return match($status) {
        'pending' => 'warning',
        'processing' => 'info',
        'delivered' => 'success',
        'cancelled' => 'danger',
        default => 'secondary'
    };
}
?>
</style>

<?php require_once '../includes/footer.php'; ?> 