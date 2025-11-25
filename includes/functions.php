<?php
session_start();
require_once 'db.php';  // Add this line to ensure database connection is available

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check user role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Function to sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Function to redirect with message
function redirectWith($url, $message, $type = 'success') {
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type
    ];
    
    if (headers_sent()) {
        echo "<script>window.location.href = '$url';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=$url'></noscript>";
        exit();
    } else {
        header("Location: $url");
        exit();
    }
}

// Function to display flash message
function displayFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $message = $_SESSION['flash']['message'];
        $type = $_SESSION['flash']['type'];
        unset($_SESSION['flash']);
        return "<div class='alert alert-$type'>$message</div>";
    }
    return '';
}

// Function to check if user has access to branch
function hasAccessToBranch($branch_id) {
    if ($_SESSION['role'] === 'super_admin') {
        return true;
    }
    return isset($_SESSION['branch_id']) && $_SESSION['branch_id'] == $branch_id;
}

// Function to calculate loyalty years
function calculateLoyaltyYears($registration_date) {
    $reg_date = new DateTime($registration_date);
    $now = new DateTime();
    $interval = $reg_date->diff($now);
    return $interval->y;
}

// Function to get loyalty level
function getLoyaltyLevel($years) {
    if ($years >= 5) return 'Platinum';
    if ($years >= 3) return 'Gold';
    if ($years >= 1) return 'Silver';
    return 'Bronze';
}

// Function to format currency
function formatCurrency($amount) {
    if ($amount === null || $amount === '') {
        return '₱0.00';
    }
    return '₱' . number_format((float)$amount, 2);
}

// Function to generate random reference number
function generateReferenceNumber() {
    return 'WRS-' . date('Ymd') . '-' . substr(uniqid(), -5);
}

// Function to check if email is valid
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to check if phone number is valid (Philippine format)
function isValidPhoneNumber($phone) {
    return preg_match('/^(09|\+639)\d{9}$/', $phone);
}

// Function to get user's branch name
function getBranchName($branch_id) {
    global $conn;
    if (!$conn) {
        require_once 'db.php';
        $conn = getConnection();
    }
    $stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $branch = $result->fetch_assoc();
    return $branch ? $branch['branch_name'] : 'Unknown Branch';
}

// Function to check if product is in stock
function isInStock($product_id, $branch_id, $quantity = 1) {
    global $conn;
    if (!$conn) {
        require_once 'db.php';
        $conn = getConnection();
    }
    $stmt = $conn->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND branch_id = ?");
    $stmt->bind_param("ii", $product_id, $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $inventory = $result->fetch_assoc();
    return $inventory && $inventory['quantity'] >= $quantity;
}

// Function to log activity
function logActivity($user_id, $activity_type, $description) {
    global $conn;
    if (!$conn) {
        require_once 'db.php';
        $conn = getConnection();
    }
    
    // Get IP address with proper handling for various scenarios
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip_address == '::1' || $ip_address == '127.0.0.1') {
        $ip_address = 'localhost';
    }
    
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $activity_type, $description, $ip_address);
    $stmt->execute();
}

// Function to get user's full information
function getUserInfo($user_id) {
    global $conn;
    if (!$conn) {
        require_once 'db.php';
        $conn = getConnection();
    }
    $stmt = $conn->prepare("
        SELECT u.*, b.branch_name, bg.barangay_name 
        FROM users u 
        LEFT JOIN branches b ON u.branch_id = b.branch_id 
        LEFT JOIN barangays bg ON u.barangay_id = bg.barangay_id 
        WHERE u.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Function to check if maintenance is due
function isMaintenanceDue($branch_id) {
    global $conn;
    if (!$conn) {
        require_once 'db.php';
        $conn = getConnection();
    }
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tasks 
        WHERE branch_id = ? 
        AND task_type = 'maintenance' 
        AND status = 'pending' 
        AND due_date <= NOW()
    ");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'] > 0;
}

// Function to get unread messages count
function getUnreadMessagesCount($user_id) {
    global $conn;
    if (!$conn) {
        require_once 'db.php';
        $conn = getConnection();
    }
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM messages 
        WHERE receiver_id = ? 
        AND is_read = 0
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'];
}

function checkCustomerLoyaltyRewards($user_id) {
    $conn = getConnection();
    
    // Get customer info with loyalty data
    $stmt = $conn->prepare("
        SELECT 
            u.user_id,
            u.name,
            u.branch_id,
            u.registration_date,
            l.years_of_loyalty,
            l.points,
            TIMESTAMPDIFF(YEAR, u.registration_date, NOW()) as actual_years,
            b.branch_name,
            b.branch_id
        FROM users u
        JOIN loyalty l ON u.user_id = l.customer_id
        JOIN branches b ON u.branch_id = b.branch_id
        WHERE u.user_id = ?
        AND u.role = 'customer'
        AND u.status = 'active'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    
    if ($customer && $customer['actual_years'] > $customer['years_of_loyalty']) {
        // Calculate reward tier and points
        $new_years = $customer['actual_years'] - $customer['years_of_loyalty'];
        $points_per_year = 100;
        $new_points = $new_years * $points_per_year;
        
        // Update years of loyalty and points
        $stmt = $conn->prepare("
            UPDATE loyalty 
            SET years_of_loyalty = ?,
                points = points + ?
            WHERE customer_id = ?
        ");
        $stmt->bind_param("iii", $customer['actual_years'], $new_points, $customer['user_id']);
        $stmt->execute();
        
        // Create notification for customer
        $stmt = $conn->prepare("
            INSERT INTO notifications (
                user_id, 
                title, 
                message, 
                type
            ) VALUES (
                ?,
                'Loyalty Reward Available! 🎉',
                ?,
                'loyalty_reward'
            )
        ");
        
        // Determine reward tier message
        $reward_message = match(true) {
            $customer['actual_years'] >= 5 => "Platinum Member: 20% discount on your next order",
            $customer['actual_years'] >= 3 => "Gold Member: 15% discount on your next order",
            $customer['actual_years'] >= 1 => "Silver Member: 10% discount on your next order",
            default => "5% discount on your next order"
        };
        
        $message = "Congratulations {$customer['name']}! 🎉\n\n" .
                  "Your account is now {$customer['actual_years']} year" . 
                  ($customer['actual_years'] > 1 ? 's' : '') . " old!\n\n" .
                  "You've earned:\n" .
                  "- {$new_points} loyalty points\n" .
                  "- {$reward_message}\n\n" .
                  "Visit {$customer['branch_name']} branch to claim your loyalty rewards. " .
                  "Don't forget to show this notification to our staff!\n\n" .
                  "Thank you for your continued trust in our service! 💧";
        
        $stmt->bind_param("is", $customer['user_id'], $message);
        $stmt->execute();
        
        // Notify branch admin
        $stmt = $conn->prepare("
            INSERT INTO notifications (
                user_id,
                title,
                message,
                type
            )
            SELECT 
                u.user_id,
                'Customer Loyalty Reward Due',
                ?,
                'loyalty_reward'
            FROM users u
            WHERE u.role = 'branch_admin'
            AND u.branch_id = ?
        ");
        
        $admin_message = "Customer {$customer['name']} has reached {$customer['actual_years']} year" .
                       ($customer['actual_years'] > 1 ? 's' : '') . " of loyalty!\n\n" .
                       "Reward Details:\n" .
                       "- Added {$new_points} loyalty points\n" .
                       "- Eligible for: {$reward_message}\n\n" .
                       "Please assist when they visit to claim their rewards.";
        
        $stmt->bind_param("si", $admin_message, $customer['branch_id']);
        $stmt->execute();
        
        return true;
    }
    
    return false;
}

// Function to set flash message
function setFlashMessage($message, $type = 'success') {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'message' => $message,
        'type' => $type
    ];
}

// Function to display all flash messages
function displayFlashMessages() {
    $output = '';
    if (isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
        foreach ($_SESSION['flash_messages'] as $flash) {
            $output .= "<div class='alert alert-{$flash['type']} alert-dismissible fade show' role='alert'>";
            $output .= htmlspecialchars($flash['message']);
            $output .= "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
            $output .= "</div>";
        }
        unset($_SESSION['flash_messages']);
    }
    echo $output;
}
?> 