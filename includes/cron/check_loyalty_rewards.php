<?php
require_once __DIR__ . '/../db.php';

$conn = getConnection();

// Get all customers with their loyalty info
$sql = "
    SELECT 
        u.user_id,
        u.name,
        u.branch_id,
        l.registration_date,
        l.last_reward_claim,
        l.years_registered,
        TIMESTAMPDIFF(YEAR, l.registration_date, NOW()) as actual_years,
        b.branch_name
    FROM users u
    JOIN loyalty l ON u.user_id = l.customer_id
    JOIN branches b ON u.branch_id = b.branch_id
    WHERE u.role = 'customer'
    AND u.status = 'active'
";

$customers = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

foreach ($customers as $customer) {
    // Check if customer has been registered for more years than recorded
    if ($customer['actual_years'] > $customer['years_registered']) {
        // Calculate years since last claim
        $years_since_claim = $customer['last_reward_claim'] 
            ? floor((time() - strtotime($customer['last_reward_claim'])) / (365 * 24 * 60 * 60))
            : $customer['actual_years'];
            
        if ($years_since_claim > 0) {
            // Update years registered
            $stmt = $conn->prepare("
                UPDATE loyalty 
                SET years_registered = ? 
                WHERE customer_id = ?
            ");
            $stmt->bind_param("ii", $customer['actual_years'], $customer['user_id']);
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
                    'Loyalty Reward Available!',
                    ?,
                    'loyalty_reward'
                )
            ");
            
            $message = "Congratulations! You've been our valued customer for {$customer['actual_years']} year" . 
                      ($customer['actual_years'] > 1 ? 's' : '') . 
                      "! Please visit {$customer['branch_name']} branch to claim your loyalty reward.";
            
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
            
            $admin_message = "Customer {$customer['name']} has been registered for {$customer['actual_years']} year" .
                           ($customer['actual_years'] > 1 ? 's' : '') .
                           " and is eligible for a loyalty reward.";
            
            $stmt->bind_param("si", $admin_message, $customer['branch_id']);
            $stmt->execute();
        }
    }
}

// Log script execution
$conn->query("
    INSERT INTO activity_log (
        activity_type, 
        description
    ) VALUES (
        'system',
        'Loyalty rewards check completed'
    )
");

echo "Loyalty rewards check completed successfully.\n"; 