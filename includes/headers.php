<?php
require_once 'config.php';
require_once 'functions.php';

// Get the base URL dynamically
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$baseURL = dirname($_SERVER['PHP_SELF']);
if(basename($baseURL) == 'includes') {
    $baseURL = dirname($baseURL);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Refilling Station</title>
    <!-- Base URL -->
    <base href="<?php echo $protocol . $host . $baseURL . '/'; ?>">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #0077be;
            --secondary-color: #005c91;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar {
            background-color: var(--primary-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
        }
        
        .nav-link:hover {
            color: white !important;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .alert {
            border-radius: 10px;
        }
        
        .dashboard-card {
            transition: transform 0.2s;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            padding: 3px 6px;
            border-radius: 50%;
            background: red;
            color: white;
            font-size: 10px;
        }
        
        .notification-dropdown {
            min-width: 300px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-item {
            border-left: 3px solid transparent;
            padding: 10px 15px;
        }
        
        .notification-item.unread {
            background-color: rgba(13, 110, 253, 0.05);
            border-left-color: #0d6efd;
        }
        
        .notification-item .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .notification-item .notification-title {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .notification-item .notification-message {
            font-size: 0.9rem;
            color: #4a4a4a;
        }
    </style>
</head>
<body>
    <?php
    // Get unread notifications count if user is logged in
    $unread_notifications = 0;
    $recent_notifications = [];
    
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $conn = getConnection();
        
        // Check if notifications table exists
        $table_exists = false;
        try {
            $result = $conn->query("SHOW TABLES LIKE 'notifications'");
            $table_exists = ($result->num_rows > 0);
        } catch (Exception $e) {
            // Silently handle the error
            $table_exists = false;
        }
        
        if ($table_exists) {
            try {
                // Get unread count
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM notifications 
                    WHERE user_id = ? AND is_read = FALSE
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $unread_notifications = $stmt->get_result()->fetch_assoc()['count'];
                
                // Get 5 most recent notifications
                $stmt = $conn->prepare("
                    SELECT *
                    FROM notifications 
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                    LIMIT 5
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $recent_notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            } catch (Exception $e) {
                // Silently handle any database errors
                $unread_notifications = 0;
                $recent_notifications = [];
            }
        }
    }
    ?>

    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-water me-2"></i>
                Water Refilling Station
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isLoggedIn()): ?>
                        <?php if (hasRole('customer')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/customers/dashboard.php">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/customers/products.php">Products</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/customers/refill_request.php">Request Refill</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/customers/rewards.php">Rewards</a>
                            </li>
                            <li class="nav-item position-relative">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/customers/chat.php">
                                    <i class="fas fa-comments"></i>
                                    <?php 
                                    $unread = getUnreadMessagesCount($_SESSION['user_id']);
                                    if ($unread > 0): 
                                    ?>
                                    <span class="notification-badge"><?php echo $unread; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php elseif (hasRole('branch_admin')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/branch_admins/dashboard.php">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/branch_admins/orders.php">Orders</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/branch_admins/inventory.php">Inventory</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/branch_admins/announcements.php">Announcements</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/branch_admins/customers.php">Customers</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/branch_admins/reports.php">Reports</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/branch_admins/chat.php">Chat</a>
                            </li>
                        <?php elseif (hasRole('super_admin')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/super_admin/dashboard.php">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/super_admin/manage_branches.php">Branches</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/super_admin/manage_admins.php">Branch Admins</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/super_admin/inventory.php">Inventory</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/super_admin/reports.php">Reports</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/super_admin/maintenance.php">
                                    <i class="fas fa-tools"></i> Maintenance
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/super_admin/system_settings.php">Settings</a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Notifications Dropdown -->
                        <li class="nav-item dropdown me-3">
                            <a class="nav-link dropdown-toggle position-relative" href="#" role="button" 
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <?php if ($unread_notifications > 0): ?>
                                    <span class="notification-badge"><?= $unread_notifications ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                                <h6 class="dropdown-header">Notifications</h6>
                                <?php if (empty($recent_notifications)): ?>
                                    <div class="dropdown-item text-muted">No notifications</div>
                                <?php else: ?>
                                    <?php foreach ($recent_notifications as $notification): ?>
                                        <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>">
                                            <?php
                                            $type_icon = match($notification['type']) {
                                                'maintenance' => 'tools',
                                                'loyalty_reward' => 'gift',
                                                'order_update' => 'shopping-cart',
                                                default => 'info-circle'
                                            };
                                            ?>
                                            <div class="notification-title">
                                                <i class="fas fa-<?= $type_icon ?> me-2"></i>
                                                <?= htmlspecialchars($notification['title']) ?>
                                            </div>
                                            <div class="notification-message">
                                                <?= htmlspecialchars(substr($notification['message'], 0, 100)) ?>...
                                            </div>
                                            <div class="notification-time">
                                                <?php
                                                $date = new DateTime($notification['created_at']);
                                                $now = new DateTime();
                                                $diff = $now->diff($date);
                                                
                                                if ($diff->y > 0) {
                                                    echo $diff->y . 'y ago';
                                                } elseif ($diff->m > 0) {
                                                    echo $diff->m . 'mo ago';
                                                } elseif ($diff->d > 0) {
                                                    echo $diff->d . 'd ago';
                                                } elseif ($diff->h > 0) {
                                                    echo $diff->h . 'h ago';
                                                } elseif ($diff->i > 0) {
                                                    echo $diff->i . 'm ago';
                                                } else {
                                                    echo 'Just now';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-center" href="<?= getNotificationsUrl() ?>">
                                        View All Notifications
                                    </a>
                                <?php endif; ?>
                            </div>
                        </li>
                        
                        <!-- Profile and Logout Links -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo $_SESSION['username']; ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <?php if (hasRole('super_admin')): ?>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/super_admin/profile.php">
                                        <i class="fas fa-user-cog me-2"></i>Profile
                                    </a>
                                <?php elseif (hasRole('branch_admin')): ?>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/branch_admins/profile.php">
                                        <i class="fas fa-user-cog me-2"></i>Profile
                                    </a>
                                <?php else: ?>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/customers/profile.php">
                                        <i class="fas fa-user me-2"></i>Profile
                                    </a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </div>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo SITE_URL; ?>/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo SITE_URL; ?>/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mb-4">
        <?php echo displayFlashMessage(); ?> 
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    
</body>
</html>

<?php
// Helper function to get notifications page URL based on user role
function getNotificationsUrl() {
    if (hasRole('customer')) {
        return '/waterrefillingstation/customers/notifications.php';
    } elseif (hasRole('branch_admin')) {
        return '/waterrefillingstation/branch_admins/notifications.php';
    } elseif (hasRole('super_admin')) {
        return '/waterrefillingstation/super_admin/notifications.php';
    }
    return '#';
}
?> 