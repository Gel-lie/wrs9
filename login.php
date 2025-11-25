<?php
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    $baseURL = rtrim(SITE_URL, '/'); // Get the base URL from config
    
    $redirect = match($role) {
        'customer' => $baseURL . '/customers/dashboard.php',
        'branch_admin' => $baseURL . '/branch_admins/dashboard.php',
        'super_admin' => $baseURL . '/super_admin/dashboard.php',
        default => $baseURL . '/'
    };
    header("Location: $redirect");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        require_once 'includes/db.php';
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                if ($user['branch_id']) {
                    $_SESSION['branch_id'] = $user['branch_id'];
                }
                
                // Update last login
                $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $stmt->bind_param("i", $user['user_id']);
                $stmt->execute();
                
                // Log activity
                logActivity($user['user_id'], 'login', 'User logged in');
                
                // Check loyalty rewards for customers
                if ($user['role'] === 'customer') {
                    checkCustomerLoyaltyRewards($user['user_id']);
                }
                
                // Redirect based on role
                $baseURL = rtrim(SITE_URL, '/');
                $redirect = match($user['role']) {
                    'customer' => $baseURL . '/customers/dashboard.php',
                    'branch_admin' => $baseURL . '/branch_admins/dashboard.php',
                    'super_admin' => $baseURL . '/super_admin/dashboard.php',
                    default => $baseURL . '/'
                };
                header("Location: $redirect");
                exit();
            } else {
                $error = 'Invalid password';
            }
        } else {
            $error = 'Username not found or account is inactive';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body">
                    <h2 class="text-center mb-4">Login</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="login.php">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-3">
                        <p>Don't have an account? <a href="register.php">Register here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 