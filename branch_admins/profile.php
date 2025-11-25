<?php
require_once '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and is a branch admin
if (!isLoggedIn() || !hasRole('branch_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $contact_number = $_POST['contact_number'];
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
        SET name = ?, email = ?, contact_number = ?
        WHERE user_id = ?
    ");
    $stmt->bind_param("sssi", $name, $email, $contact_number, $user_id);
    
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

// Get user and branch data
$stmt = $conn->prepare("
    SELECT u.*, b.branch_name,
    (SELECT COUNT(*) FROM users WHERE branch_id = u.branch_id AND role = 'customer') as customer_count,
    (SELECT COUNT(*) FROM orders WHERE branch_id = u.branch_id) as order_count
    FROM users u
    JOIN branches b ON u.branch_id = b.branch_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

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

// Get branch statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed_orders
    FROM orders 
    WHERE branch_id = ?
");
$stmt->bind_param("i", $user['branch_id']);
$stmt->execute();
$branch_stats = $stmt->get_result()->fetch_assoc();
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
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                            <div class="form-text">Username cannot be changed</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="contact_number" value="<?= htmlspecialchars($user['contact_number']) ?>">
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
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Branch Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Branch</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars($user['branch_name']) ?></dd>
                        
                        <dt class="col-sm-5">Role</dt>
                        <dd class="col-sm-7">Branch Admin</dd>
                        
                        <dt class="col-sm-5">Customers</dt>
                        <dd class="col-sm-7"><?= number_format($user['customer_count']) ?></dd>
                        
                        <dt class="col-sm-5">Total Orders</dt>
                        <dd class="col-sm-7"><?= number_format($user['order_count']) ?></dd>
                    </dl>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Branch Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row g-0">
                        <div class="col-4 text-center border-end">
                            <h6 class="text-muted mb-1">Pending</h6>
                            <h3 class="mb-0"><?= number_format($branch_stats['pending_orders']) ?></h3>
                        </div>
                        <div class="col-4 text-center border-end">
                            <h6 class="text-muted mb-1">Processing</h6>
                            <h3 class="mb-0"><?= number_format($branch_stats['processing_orders']) ?></h3>
                        </div>
                        <div class="col-4 text-center">
                            <h6 class="text-muted mb-1">Completed</h6>
                            <h3 class="mb-0"><?= number_format($branch_stats['completed_orders']) ?></h3>
                        </div>
                    </div>
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
</style>

<?php require_once '../includes/footer.php'; ?> 