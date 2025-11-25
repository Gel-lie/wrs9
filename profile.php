<?php
require_once 'includes/header.php';
require_once 'includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirectWith('login.php', 'Please login to access your profile', 'warning');
}

$conn = getConnection();
$user = getUserInfo($_SESSION['user_id']);
$user_role = $user['role'];

// Get role-specific information
switch ($user_role) {
    case 'super_admin':
        // Get system-wide statistics
        $stmt = $conn->prepare("
            SELECT 
                (SELECT COUNT(*) FROM branches) as total_branches,
                (SELECT COUNT(*) FROM users WHERE role = 'branch_admin') as total_admins,
                (SELECT COUNT(*) FROM users WHERE role = 'customer') as total_customers,
                (SELECT COUNT(*) FROM orders) as total_orders
        ");
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        break;

    case 'branch_admin':
        // Get branch statistics
        $stmt = $conn->prepare("
            SELECT b.*, 
                   COUNT(DISTINCT u.user_id) as total_customers,
                   COUNT(DISTINCT o.order_id) as total_orders,
                   SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as total_revenue
            FROM branches b
            LEFT JOIN users u ON b.branch_id = u.branch_id AND u.role = 'customer'
            LEFT JOIN orders o ON u.user_id = o.customer_id
            WHERE b.branch_id = ?
            GROUP BY b.branch_id
        ");
        $stmt->bind_param("i", $user['branch_id']);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        break;

    case 'customer':
        // Get customer statistics
        $stmt = $conn->prepare("
            SELECT 
                COUNT(o.order_id) as total_orders,
                SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as total_spent,
                MAX(o.order_date) as last_order_date,
                l.points as loyalty_points,
                l.reward_level,
                b.branch_name,
                br.barangay_name
            FROM users u
            LEFT JOIN orders o ON u.user_id = o.customer_id
            LEFT JOIN loyalty l ON u.user_id = l.customer_id
            LEFT JOIN branches b ON u.branch_id = b.branch_id
            LEFT JOIN barangays br ON u.barangay_id = br.barangay_id
            WHERE u.user_id = ?
            GROUP BY u.user_id
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        break;
}

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action']) {
        case 'update_profile':
            $name = sanitize($_POST['name']);
            $email = sanitize($_POST['email']);
            $contact = sanitize($_POST['contact_number']);
            
            // For customers, also update address information
            $barangay_id = ($user_role === 'customer') ? (int)$_POST['barangay_id'] : null;
            $sitio_purok = ($user_role === 'customer') ? sanitize($_POST['sitio_purok']) : null;
            
            // Check if email is already taken by another user
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->bind_param("si", $email, $_SESSION['user_id']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                redirectWith('profile.php', 'Email is already taken', 'danger');
                exit();
            }

            // Update query based on role
            if ($user_role === 'customer') {
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, contact_number = ?, barangay_id = ?, sitio_purok = ?
                    WHERE user_id = ?
                ");
                $stmt->bind_param("sssisi", $name, $email, $contact, $barangay_id, $sitio_purok, $_SESSION['user_id']);
            } else {
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, contact_number = ?
                    WHERE user_id = ?
                ");
                $stmt->bind_param("sssi", $name, $email, $contact, $_SESSION['user_id']);
            }

            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'profile_update', "Updated profile information");
                redirectWith('profile.php', 'Profile updated successfully', 'success');
            } else {
                redirectWith('profile.php', 'Error updating profile', 'danger');
            }
            break;

        case 'change_password':
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if (!password_verify($current_password, $result['password'])) {
                redirectWith('profile.php', 'Current password is incorrect', 'danger');
                exit();
            }
            
            // Validate new password
            if ($new_password !== $confirm_password) {
                redirectWith('profile.php', 'New passwords do not match', 'danger');
                exit();
            }
            
            if (strlen($new_password) < 8) {
                redirectWith('profile.php', 'Password must be at least 8 characters long', 'danger');
                exit();
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'password_change', "Changed account password");
                redirectWith('profile.php', 'Password changed successfully', 'success');
            } else {
                redirectWith('profile.php', 'Error changing password', 'danger');
            }
            break;
    }
}

// Get recent activity logs
$stmt = $conn->prepare("
    SELECT * FROM activity_log 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$activity_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get barangays for customer form
if ($user_role === 'customer') {
    $stmt = $conn->prepare("SELECT * FROM barangays WHERE branch_id = ? ORDER BY barangay_name");
    $stmt->bind_param("i", $user['branch_id']);
    $stmt->execute();
    $barangays = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Profile Information -->
        <div class="col-md-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-circle mb-3">
                            <span class="avatar-initials">
                                <?= strtoupper(substr($user['name'], 0, 2)) ?>
                            </span>
                        </div>
                        <h4><?= htmlspecialchars($user['name']) ?></h4>
                        <p class="text-muted">
                            <?php
                            switch ($user_role) {
                                case 'super_admin':
                                    echo 'Super Administrator';
                                    break;
                                case 'branch_admin':
                                    echo 'Branch Administrator';
                                    break;
                                case 'customer':
                                    echo 'Customer';
                                    break;
                            }
                            ?>
                        </p>
                    </div>
                    
                    <form action="profile.php" method="post">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contact_number" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="contact_number" name="contact_number" 
                                   value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>">
                        </div>

                        <?php if ($user_role === 'customer'): ?>
                        <div class="mb-3">
                            <label for="barangay_id" class="form-label">Barangay</label>
                            <select class="form-select" id="barangay_id" name="barangay_id" required>
                                <option value="">Select Barangay</option>
                                <?php foreach ($barangays as $barangay): ?>
                                <option value="<?= $barangay['barangay_id'] ?>" 
                                        <?= $user['barangay_id'] == $barangay['barangay_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($barangay['barangay_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="sitio_purok" class="form-label">Sitio/Purok</label>
                            <input type="text" class="form-control" id="sitio_purok" name="sitio_purok" 
                                   value="<?= htmlspecialchars($user['sitio_purok'] ?? '') ?>">
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary w-100">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Role-Specific Information -->
        <div class="col-md-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <?php
                        switch ($user_role) {
                            case 'super_admin':
                                echo 'System Overview';
                                break;
                            case 'branch_admin':
                                echo 'Branch Information';
                                break;
                            case 'customer':
                                echo 'Account Information';
                                break;
                        }
                        ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($user_role === 'super_admin'): ?>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="text-primary mb-1"><?= $stats['total_branches'] ?? 0 ?></h4>
                                    <small class="text-muted">Total Branches</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="text-primary mb-1"><?= $stats['total_admins'] ?? 0 ?></h4>
                                    <small class="text-muted">Branch Admins</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="text-primary mb-1"><?= $stats['total_customers'] ?? 0 ?></h4>
                                    <small class="text-muted">Total Customers</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="text-primary mb-1"><?= $stats['total_orders'] ?? 0 ?></h4>
                                    <small class="text-muted">Total Orders</small>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($user_role === 'branch_admin'): ?>
                        <h3 class="text-primary mb-4"><?= htmlspecialchars($stats['branch_name'] ?? '') ?> Branch</h3>
                        
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="text-primary mb-1"><?= $stats['total_customers'] ?? 0 ?></h4>
                                    <small class="text-muted">Total Customers</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="text-primary mb-1"><?= $stats['total_orders'] ?? 0 ?></h4>
                                    <small class="text-muted">Total Orders</small>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="text-primary mb-1">₱<?= number_format($stats['total_revenue'] ?? 0, 2) ?></h4>
                                    <small class="text-muted">Total Revenue</small>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">Branch</h6>
                            <p class="h5"><?= $stats['branch_name'] ? htmlspecialchars($stats['branch_name']) : '' ?></p>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">Barangay</h6>
                            <p class="h5"><?= $stats['barangay_name'] ? htmlspecialchars($stats['barangay_name']) : '' ?></p>
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="text-primary mb-1"><?= $stats['total_orders'] ?? 0 ?></h4>
                                    <small class="text-muted">Total Orders</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="text-primary mb-1">₱<?= number_format($stats['total_spent'] ?? 0, 2) ?></h4>
                                    <small class="text-muted">Total Spent</small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="mb-4">
                            <h6 class="text-muted mb-2">Loyalty Program</h6>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="mb-0">Points: <?= number_format($stats['loyalty_points'] ?? 0) ?></p>
                                    <p class="mb-0">Level: <?= $stats['reward_level'] ? htmlspecialchars($stats['reward_level']) : 'None' ?></p>
                                </div>
                                <a href="rewards.php" class="btn btn-sm btn-outline-primary">View Rewards</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <hr>

                    <div class="mb-3">
                        <label class="form-label text-muted">Last Login</label>
                        <p class="mb-0">
                            <?= $user['last_login'] ? date('F d, Y h:ia', strtotime($user['last_login'])) : 'Never' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="col-md-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Security Settings</h5>
                </div>
                <div class="card-body">
                    <form action="profile.php" method="post">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" 
                                   name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" 
                                   name="new_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Change Password</button>
                    </form>

                    <hr>

                    <h6 class="mb-3">Password Requirements:</h6>
                    <ul class="small text-muted">
                        <li>Minimum 8 characters long</li>
                        <li>Include both uppercase and lowercase letters</li>
                        <li>Include at least one number</li>
                        <li>Include at least one special character</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card shadow">
        <div class="card-header">
            <h5 class="card-title mb-0">Recent Activity</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Activity</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity_logs as $log): ?>
                        <tr>
                            <td><?= date('M d, Y h:ia', strtotime($log['created_at'])) ?></td>
                            <td><?= htmlspecialchars($log['description']) ?></td>
                            <td><?= htmlspecialchars($log['ip_address']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 100px;
    height: 100px;
    background-color: #007bff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.avatar-initials {
    color: white;
    font-size: 36px;
    font-weight: bold;
}
</style>

<script>
// Password validation
document.querySelector('form[action="profile.php"]').addEventListener('submit', function(e) {
    if (this.querySelector('input[name="action"]').value === 'change_password') {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('New passwords do not match!');
            return;
        }
        
        if (newPassword.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters long!');
            return;
        }
        
        // Check for uppercase, lowercase, number and special character
        const hasUpperCase = /[A-Z]/.test(newPassword);
        const hasLowerCase = /[a-z]/.test(newPassword);
        const hasNumbers = /\d/.test(newPassword);
        const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(newPassword);
        
        if (!hasUpperCase || !hasLowerCase || !hasNumbers || !hasSpecialChar) {
            e.preventDefault();
            alert('Password must include uppercase and lowercase letters, numbers, and special characters!');
            return;
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?> 