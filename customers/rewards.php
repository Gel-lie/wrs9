<?php
require_once '../includes/functions.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !hasRole('customer')) {
    header("Location: /login.php");
    exit();
}

require_once '../includes/db.php';

// Get user's information
$user_id = $_SESSION['user_id'];

// Get loyalty information
$stmt = $conn->prepare("
    SELECT l.*, u.registration_date
    FROM loyalty l
    JOIN users u ON l.customer_id = u.user_id
    WHERE l.customer_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$loyalty = $stmt->get_result()->fetch_assoc();

// If no loyalty record exists, create one
if (!$loyalty) {
    $stmt = $conn->prepare("INSERT INTO loyalty (customer_id, points, reward_level) VALUES (?, 0, 'Bronze')");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Fetch the newly created loyalty record
    $stmt = $conn->prepare("
        SELECT l.*, u.registration_date
        FROM loyalty l
        JOIN users u ON l.customer_id = u.user_id
        WHERE l.customer_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $loyalty = $stmt->get_result()->fetch_assoc();
}

// Calculate years of loyalty
$registration_date = new DateTime($loyalty['registration_date']);
$now = new DateTime();
$years_of_loyalty = $registration_date->diff($now)->y;

// Update years of loyalty if different
if ($years_of_loyalty != $loyalty['years_of_loyalty']) {
    $stmt = $conn->prepare("UPDATE loyalty SET years_of_loyalty = ? WHERE customer_id = ?");
    $stmt->bind_param("ii", $years_of_loyalty, $user_id);
    $stmt->execute();
}

// Define reward levels and their benefits
$reward_levels = [
    'Bronze' => [
        'min_points' => 0,
        'benefits' => [
            'Basic delivery service',
            '1 point per 100 pesos spent',
            'Access to regular promotions'
        ]
    ],
    'Silver' => [
        'min_points' => 1000,
        'benefits' => [
            'All Bronze benefits',
            'Priority delivery service',
            '2 points per 100 pesos spent',
            '5% discount on maintenance services'
        ]
    ],
    'Gold' => [
        'min_points' => 5000,
        'benefits' => [
            'All Silver benefits',
            'VIP delivery service',
            '3 points per 100 pesos spent',
            '10% discount on maintenance services',
            'Free annual maintenance check'
        ]
    ],
    'Platinum' => [
        'min_points' => 10000,
        'benefits' => [
            'All Gold benefits',
            'Premium VIP delivery service',
            '4 points per 100 pesos spent',
            '15% discount on maintenance services',
            'Free bi-annual maintenance check',
            'Exclusive access to new products'
        ]
    ]
];

// Get transaction history
$stmt = $conn->prepare("
    SELECT o.order_id, o.order_date, o.total_amount, o.status,
           GROUP_CONCAT(CONCAT(od.quantity, 'x ', p.product_name) SEPARATOR ', ') as items
    FROM orders o
    JOIN order_details od ON o.order_id = od.order_id
    JOIN products p ON od.product_id = p.product_id
    WHERE o.customer_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="container">
    <div class="row">
        <!-- Loyalty Status Card -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title">Your Loyalty Status</h2>
                    <div class="loyalty-info">
                        <p class="h4 mb-3">Level: <span class="badge bg-primary"><?php echo htmlspecialchars($loyalty['reward_level'] ?? 'Bronze'); ?></span></p>
                        <p>Points: <strong><?php echo number_format($loyalty['points'] ?? 0); ?></strong></p>
                        <p>Years of Loyalty: <strong><?php echo $years_of_loyalty; ?></strong></p>
                        
                        <?php if (isset($reward_levels[$loyalty['reward_level']]['benefits'])): ?>
                            <h5 class="mt-4">Your Benefits:</h5>
                            <ul class="list-unstyled">
                                <?php foreach ($reward_levels[$loyalty['reward_level']]['benefits'] as $benefit): ?>
                                    <li><i class="fas fa-check text-success me-2"></i><?php echo htmlspecialchars($benefit); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Next Level Progress Card -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title">Next Level Progress</h2>
                    <?php
                    $current_level = $loyalty['reward_level'] ?? 'Bronze';
                    $next_level = '';
                    $points_needed = 0;
                    
                    $levels = array_keys($reward_levels);
                    $current_index = array_search($current_level, $levels);
                    
                    if ($current_index !== false && $current_index < count($levels) - 1) {
                        $next_level = $levels[$current_index + 1];
                        $points_needed = $reward_levels[$next_level]['min_points'] - ($loyalty['points'] ?? 0);
                    }
                    
                    if ($next_level):
                    ?>
                        <p>Next Level: <span class="badge bg-success"><?php echo htmlspecialchars($next_level); ?></span></p>
                        <p>Points needed: <strong><?php echo number_format($points_needed); ?></strong></p>
                        <div class="progress">
                            <?php
                            $current_points = $loyalty['points'] ?? 0;
                            $next_level_points = $reward_levels[$next_level]['min_points'];
                            $progress = min(100, ($current_points / $next_level_points) * 100);
                            ?>
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%"
                                 aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo round($progress); ?>%
                            </div>
                        </div>
                    <?php else: ?>
                        <p>Congratulations! You've reached the highest level!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction History -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title">Recent Transactions</h2>
                    <?php if (!empty($transactions)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td>#<?php echo htmlspecialchars($transaction['order_id']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($transaction['order_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['items']); ?></td>
                                            <td>₱<?php echo number_format($transaction['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($transaction['status']) {
                                                        'delivered' => 'success',
                                                        'processing' => 'primary',
                                                        'pending' => 'warning',
                                                        'cancelled' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($transaction['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No transactions found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 