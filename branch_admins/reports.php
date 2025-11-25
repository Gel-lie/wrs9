<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

// Check if user is logged in and is a branch admin
if (!isLoggedIn() || !hasRole('branch_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();
$user = getUserInfo($_SESSION['user_id']);
$branch_id = $user['branch_id'];

// Get date range filters
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d'); // Today

// Sales Overview
$stmt = $conn->prepare("
    SELECT
        COUNT(*) as total_orders,
        COUNT(CASE WHEN o.status = 'delivered' THEN 1 END) as completed_orders,
        COUNT(CASE WHEN o.status = 'cancelled' THEN 1 END) as cancelled_orders,
        COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END), 0) as total_revenue,
        COALESCE(AVG(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE NULL END), 0) as average_order_value
    FROM orders o
    JOIN users u ON o.customer_id = u.user_id
    WHERE u.branch_id = ?
    AND DATE(o.order_date) BETWEEN ? AND ?
");
$stmt->bind_param("iss", $branch_id, $start_date, $end_date);
$stmt->execute();
$sales_overview = $stmt->get_result()->fetch_assoc();

// Daily Sales Trend
$stmt = $conn->prepare("
    SELECT
        DATE(o.order_date) as date,
        COUNT(*) as total_orders,
        SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as revenue
    FROM orders o
    JOIN users u ON o.customer_id = u.user_id
    WHERE u.branch_id = ?
    AND DATE(o.order_date) BETWEEN ? AND ?
    GROUP BY DATE(o.order_date)
    ORDER BY date
");
$stmt->bind_param("iss", $branch_id, $start_date, $end_date);
$stmt->execute();
$daily_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Product Performance
$stmt = $conn->prepare("
    SELECT
        p.product_name,
        p.category,
        SUM(od.quantity) as total_quantity,
        SUM(od.quantity * od.price) as total_revenue,
        COUNT(DISTINCT o.order_id) as order_count
    FROM order_details od
    JOIN orders o ON od.order_id = o.order_id
    JOIN products p ON od.product_id = p.product_id
    JOIN users u ON o.customer_id = u.user_id
    WHERE u.branch_id = ?
    AND DATE(o.order_date) BETWEEN ? AND ?
    AND o.status = 'delivered'
    GROUP BY p.product_id
    ORDER BY total_revenue DESC
");
$stmt->bind_param("iss", $branch_id, $start_date, $end_date);
$stmt->execute();
$product_performance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Customer Insights
$stmt = $conn->prepare("
    SELECT
        b.barangay_name,
        COUNT(DISTINCT o.customer_id) as customer_count,
        COUNT(*) as order_count,
        SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as total_revenue,
        AVG(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE NULL END) as avg_order_value
    FROM orders o
    JOIN users u ON o.customer_id = u.user_id
    JOIN barangays b ON u.barangay_id = b.barangay_id
    WHERE u.branch_id = ?
    AND DATE(o.order_date) BETWEEN ? AND ?
    GROUP BY b.barangay_id
    ORDER BY total_revenue DESC
");
$stmt->bind_param("iss", $branch_id, $start_date, $end_date);
$stmt->execute();
$customer_insights = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Define all available container types from refill request form
$all_container_types = [
    'Round (5 gallons)' => 'round_5g',
    'Slim (5 gallons)' => 'slim_5g',
    '10 liters' => '10l',
    '8 liters' => '8l',
    '7 liters' => '7l',
    '6.6 liters' => '6.6l',
    '6 liters' => '6l',
    '5 liters' => '5l'
];

// Refill Request Analysis - Get data from orders table
$stmt = $conn->prepare("
    SELECT o.notes, od.quantity
    FROM orders o
    JOIN users u ON o.customer_id = u.user_id
    JOIN order_details od ON o.order_id = od.order_id
    WHERE u.branch_id = ?
    AND DATE(o.order_date) BETWEEN ? AND ?
    AND od.product_id = 1
    AND o.status = 'delivered'
");
$stmt->bind_param("iss", $branch_id, $start_date, $end_date);
$stmt->execute();
$refill_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Map container keys to display names and prices
$container_key_to_name = [
    'round_5g' => 'Round (5 gallons)',
    'slim_5g' => 'Slim (5 gallons)',
    '10l' => '10 liters',
    '8l' => '8 liters',
    '7l' => '7 liters',
    '6.6l' => '6.6 liters',
    '6l' => '6 liters',
    '5l' => '5 liters'
];

$container_prices = [
    'round_5g' => 30,
    'slim_5g' => 30,
    '10l' => 20,
    '8l' => 16,
    '7l' => 14,
    '6.6l' => 13,
    '6l' => 12,
    '5l' => 10
];

// Initialize refill counts and revenue with all container types set to 0
$refill_counts = array_fill_keys(array_keys($all_container_types), 0);
$refill_revenue = array_fill_keys(array_keys($all_container_types), 0);

foreach ($refill_orders as $order) {
    // Extract container type from notes like "Container: round_5g"
    if (preg_match('/Container:\s*([^\n]+)/', $order['notes'], $matches)) {
        $container_key = trim($matches[1]);
        $quantity = (int)$order['quantity'];

        // Find the display name for this container key
        if (isset($container_key_to_name[$container_key])) {
            $container_name = $container_key_to_name[$container_key];
            if (isset($refill_counts[$container_name])) {
                $refill_counts[$container_name] += $quantity;
                $refill_revenue[$container_name] += $quantity * $container_prices[$container_key];
            }
        }
    }
}

// Prepare data for charts
$dates = [];
$revenues = [];
$order_counts = [];
foreach ($daily_sales as $day) {
    $dates[] = date('M d', strtotime($day['date']));
    $revenues[] = $day['revenue'];
    $order_counts[] = $day['total_orders'];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Branch Reports</h1>
        <div class="d-flex gap-2">
            <a href="export_preview.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-success">
                <i class="fas fa-print"></i> Print Report
            </a>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" id="start_date"
                           value="<?= $start_date ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" id="end_date"
                           value="<?= $end_date ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Generate Report</button>
                    <a href="reports.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Sales Overview Cards -->
    <div class="row mb-4">
        <div class="col-md">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Orders</h6>
                    <h2 class="mb-0"><?= $sales_overview['total_orders'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Completed Orders</h6>
                    <h2 class="mb-0"><?= $sales_overview['completed_orders'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title">Cancelled Orders</h6>
                    <h2 class="mb-0"><?= $sales_overview['cancelled_orders'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Revenue</h6>
                    <h2 class="mb-0">₱<?= number_format($sales_overview['total_revenue'] ?? 0, 2) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <h6 class="card-title">Average Order Value</h6>
                    <h2 class="mb-0">₱<?= number_format($sales_overview['average_order_value'] ?? 0, 2) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Products -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top Products</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($product_performance, 0, 10) as $product): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($product['product_name']) ?>
                                        <br>
                                        <small class="text-muted"><?= ucfirst($product['category']) ?></small>
                                    </td>
                                    <td><?= $product['total_quantity'] ?></td>
                                    <td>₱<?= number_format($product['total_revenue'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Product Performance -->
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title mb-0">Product Performance</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="productTable">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Orders</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($product_performance as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['product_name']) ?></td>
                                    <td><?= ucfirst($product['category']) ?></td>
                                    <td><?= $product['total_quantity'] ?></td>
                                    <td><?= $product['order_count'] ?></td>
                                    <td>₱<?= number_format($product['total_revenue'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Insights -->
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title mb-0">Customer Insights by Barangay</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Barangay</th>
                                    <th>Customers</th>
                                    <th>Orders</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customer_insights as $insight): ?>
                                <tr>
                                    <td><?= htmlspecialchars($insight['barangay_name']) ?></td>
                                    <td><?= $insight['customer_count'] ?></td>
                                    <td><?= $insight['order_count'] ?></td>
                                    <td>₱<?= number_format($insight['total_revenue'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Refill Request Analysis -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title mb-0">Refill Request Analysis</h5>
                </div>
                <div class="card-body">
                    <canvas id="refillChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Refill Request Summary Table -->
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title mb-0">Refill Request Summary</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Container Type</th>
                                    <th>Quantity</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($refill_counts as $container_type => $count): ?>
                                <tr>
                                    <td><?= htmlspecialchars($container_type) ?></td>
                                    <td><?= $count ?></td>
                                    <td>₱<?= number_format($refill_revenue[$container_type], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize DataTables
$(document).ready(function() {
    $('#productTable').DataTable({
        order: [[4, 'desc']], // Sort by revenue by default
        pageLength: 10
    });
});



// Refill Request Chart
const refillCtx = document.getElementById('refillChart').getContext('2d');
new Chart(refillCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($refill_counts)) ?>,
        datasets: [{
            label: 'Number of Refill Requests',
            data: <?= json_encode(array_values($refill_counts)) ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                min: 0,
                max: 15,
                ticks: {
                    stepSize: 1,
                    callback: function(value) {
                        return Number.isInteger(value) ? value : '';
                    }
                },
                title: {
                    display: true,
                    text: 'Number of Requests'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Container Type'
                }
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        }
    }
});

// Date range validation
document.querySelector('form').addEventListener('submit', function(e) {
    const startDate = new Date(document.getElementById('start_date').value);
    const endDate = new Date(document.getElementById('end_date').value);

    if (startDate > endDate) {
        e.preventDefault();
        alert('Start date cannot be later than end date');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
