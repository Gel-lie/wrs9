<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

// Check if user is logged in and has super admin role
if (!isLoggedIn() || !hasRole('super_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();

// Get branch ID from URL
$branch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validate branch exists
$stmt = $conn->prepare("SELECT * FROM branches WHERE branch_id = ?");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$branch = $stmt->get_result()->fetch_assoc();

if (!$branch) {
    redirectWith('manage_branches.php', 'Branch not found', 'danger');
}

// Get branch statistics
$stats = [
    'customers' => 0,
    'orders' => 0,
    'revenue' => 0,
    'avg_order' => 0,
    'products' => 0,
    'appointments' => 0
];

// Get customer count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE branch_id = ? AND role = 'customer'
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$stats['customers'] = $stmt->get_result()->fetch_assoc()['count'];

// Get orders statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as avg_order
    FROM orders o
    JOIN users u ON o.customer_id = u.user_id
    WHERE u.branch_id = ?
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$order_stats = $stmt->get_result()->fetch_assoc();
$stats['orders'] = $order_stats['total_orders'];
$stats['revenue'] = $order_stats['total_revenue'];
$stats['avg_order'] = $order_stats['avg_order'];

// Get product count in inventory
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM inventory 
    WHERE branch_id = ? AND quantity > 0
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$stats['products'] = $stmt->get_result()->fetch_assoc()['count'];

// Get appointments count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE branch_id = ?
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$stats['appointments'] = $stmt->get_result()->fetch_assoc()['count'];

// Get branch admin
$stmt = $conn->prepare("
    SELECT user_id, name, email, contact_number, last_login, status
    FROM users 
    WHERE branch_id = ? AND role = 'branch_admin'
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$branch_admin = $stmt->get_result()->fetch_assoc();

// Get recent orders
$stmt = $conn->prepare("
    SELECT 
        o.*, 
        u.name as customer_name,
        u.contact_number
    FROM orders o
    JOIN users u ON o.customer_id = u.user_id
    WHERE u.branch_id = ?
    ORDER BY o.order_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get low stock products
$stmt = $conn->prepare("
    SELECT 
        p.product_name,
        i.quantity,
        i.low_stock_threshold
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    WHERE i.branch_id = ? 
    AND i.quantity <= i.low_stock_threshold
    ORDER BY i.quantity ASC
    LIMIT 5
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$low_stock = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get upcoming appointments
$stmt = $conn->prepare("
    SELECT 
        a.*,
        u.name as customer_name,
        u.contact_number
    FROM appointments a
    JOIN users u ON a.customer_id = u.user_id
    WHERE a.branch_id = ?
    AND a.appointment_date >= CURDATE()
    AND a.status = 'scheduled'
    ORDER BY a.appointment_date ASC
    LIMIT 5
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$upcoming_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get monthly revenue data for chart
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(o.order_date, '%Y-%m') as month,
        COUNT(*) as order_count,
        SUM(o.total_amount) as revenue
    FROM orders o
    JOIN users u ON o.customer_id = u.user_id
    WHERE u.branch_id = ?
    AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$monthly_revenue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Branch Details: <?= htmlspecialchars($branch['branch_name']) ?></h1>
        <div>
            <a href="manage_branches.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Branches
            </a>
            <button class="btn btn-primary" onclick="printReport()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    </div>

    <?= displayFlashMessage() ?>

    <!-- Quick Stats -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Customers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['customers'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Revenue</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= formatCurrency($stats['revenue']) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-peso-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Orders</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['orders'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Average Order Value</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= formatCurrency($stats['avg_order']) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Branch Admin Info -->
        <div class="col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Branch Administrator</h6>
                </div>
                <div class="card-body">
                    <?php if ($branch_admin): ?>
                        <div class="text-center mb-3">
                            <img class="img-profile rounded-circle mb-3" 
                                 src="https://ui-avatars.com/api/?name=<?= urlencode($branch_admin['name']) ?>&background=random" 
                                 width="100">
                            <h5><?= htmlspecialchars($branch_admin['name']) ?></h5>
                            <span class="badge bg-<?= $branch_admin['status'] === 'active' ? 'success' : 'danger' ?>">
                                <?= ucfirst($branch_admin['status']) ?>
                            </span>
                        </div>
                        <hr>
                        <div class="mb-2">
                            <strong>Email:</strong> <?= htmlspecialchars($branch_admin['email']) ?>
                        </div>
                        <div class="mb-2">
                            <strong>Contact:</strong> <?= htmlspecialchars($branch_admin['contact_number']) ?>
                        </div>
                        <div class="mb-2">
                            <strong>Last Login:</strong> 
                            <?= $branch_admin['last_login'] ? date('M d, Y H:i:s', strtotime($branch_admin['last_login'])) : 'Never' ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center">
                            <p class="mb-0">No branch administrator assigned</p>
                            <a href="manage_admins.php?branch=<?= $branch_id ?>" class="btn btn-primary mt-3">
                                <i class="fas fa-user-plus"></i> Assign Admin
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Low Stock Alert</h6>
                </div>
                <div class="card-body">
                    <?php if ($low_stock): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Threshold</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($low_stock as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['product_name']) ?></td>
                                        <td>
                                            <span class="badge bg-danger"><?= $product['quantity'] ?></span>
                                        </td>
                                        <td><?= $product['low_stock_threshold'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center mb-0">No products are low in stock</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Revenue Chart -->
        <div class="col-xl-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Monthly Revenue</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Orders</h6>
                </div>
                <div class="card-body">
                    <?php if ($recent_orders): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?= $order['order_id'] ?></td>
                                        <td>
                                            <?= htmlspecialchars($order['customer_name']) ?>
                                            <small class="d-block text-muted">
                                                <?= htmlspecialchars($order['contact_number']) ?>
                                            </small>
                                        </td>
                                        <td><?= formatCurrency($order['total_amount']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= getStatusColor($order['status']) ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center mb-0">No recent orders</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Appointments -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Upcoming Appointments</h6>
        </div>
        <div class="card-body">
            <?php if ($upcoming_appointments): ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Service Type</th>
                                <th>Date & Time</th>
                                <th>Contact</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                            <tr>
                                <td><?= htmlspecialchars($appointment['customer_name']) ?></td>
                                <td><?= ucfirst($appointment['service_type']) ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'])) ?></td>
                                <td><?= htmlspecialchars($appointment['contact_number']) ?></td>
                                <td><?= htmlspecialchars($appointment['notes'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center mb-0">No upcoming appointments</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Helper function for order status colors
function getStatusColor(status) {
    switch(status) {
        case 'pending': return 'warning';
        case 'processing': return 'info';
        case 'delivered': return 'success';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
}

// Initialize Revenue Chart
var ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthly_revenue, 'month')) ?>,
        datasets: [{
            label: 'Revenue',
            data: <?= json_encode(array_column($monthly_revenue, 'revenue')) ?>,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }, {
            label: 'Orders',
            data: <?= json_encode(array_column($monthly_revenue, 'order_count')) ?>,
            borderColor: 'rgb(255, 99, 132)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Print report function
function printReport() {
    window.print();
}
</script>

<?php require_once '../includes/footer.php'; ?> 