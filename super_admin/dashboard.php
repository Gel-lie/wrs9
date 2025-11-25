<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

// Check if user is logged in and has super admin role
if (!isLoggedIn() || !hasRole('super_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

// Get system statistics
$conn = getConnection();

// Get total number of branches
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM branches");
$stmt->execute();
$branches = $stmt->get_result()->fetch_assoc()['total'];

// Get total number of users by role
$stmt = $conn->prepare("SELECT role, COUNT(*) as total FROM users GROUP BY role");
$stmt->execute();
$users_by_role = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total orders and revenue
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue
    FROM orders");
$stmt->execute();
$orders_stats = $stmt->get_result()->fetch_assoc();

// Get branch comparison data
$sql = "SELECT
    b.branch_id,
    b.branch_name,
    COUNT(o.order_id) as total_orders,
    SUM(o.total_amount) as total_revenue,
    COUNT(DISTINCT o.customer_id) as unique_customers,
    COUNT(CASE WHEN o.status = 'delivered' THEN 1 END) as completed_orders
FROM branches b
LEFT JOIN users u ON b.branch_id = u.branch_id
LEFT JOIN orders o ON u.user_id = o.customer_id
GROUP BY b.branch_id
ORDER BY total_orders DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$branch_comparison = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get daily orders trend by product
$sql = "SELECT
    DATE(o.order_date) as date,
    p.product_id,
    p.product_name,
    p.category,
    SUM(od.quantity) as total_quantity,
    COUNT(DISTINCT o.order_id) as order_count
FROM orders o
JOIN order_details od ON o.order_id = od.order_id
JOIN products p ON od.product_id = p.product_id
WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND o.status != 'cancelled'
GROUP BY DATE(o.order_date), p.product_id, p.product_name, p.category
ORDER BY date ASC";

$result = $conn->query($sql);
$daily_orders = [];
if ($result) {
    $daily_orders = $result->fetch_all(MYSQLI_ASSOC);
}

// Organize daily orders by product
$product_daily_data = [];
$all_dates = [];

// First, collect all unique dates
foreach ($daily_orders as $order) {
    $date = date('M d', strtotime($order['date']));
    if (!in_array($date, $all_dates)) {
        $all_dates[] = $date;
    }
}
sort($all_dates); // Ensure dates are in order

// Initialize product data structure with all dates set to zero
foreach ($daily_orders as $order) {
    $product = $order['product_name'];
    if (!isset($product_daily_data[$product])) {
        $product_daily_data[$product] = [
            'name' => $product,
            'category' => $order['category'],
            'quantities' => array_fill(0, count($all_dates), 0),
            'orders' => array_fill(0, count($all_dates), 0)
        ];
    }

    $date = date('M d', strtotime($order['date']));
    $date_index = array_search($date, $all_dates);
    $product_daily_data[$product]['quantities'][$date_index] = (int)$order['total_quantity'];
    $product_daily_data[$product]['orders'][$date_index] = (int)$order['order_count'];
}

// Sort products by total quantity
uasort($product_daily_data, function($a, $b) {
    return array_sum($b['quantities']) - array_sum($a['quantities']);
});

// Limit to top 10 products for better visualization
$product_daily_data = array_slice($product_daily_data, 0, 10, true);

// Get customer distribution by branch
$stmt = $conn->prepare("SELECT b.branch_name, COUNT(u.user_id) as customer_count FROM branches b LEFT JOIN users u ON b.branch_id = u.branch_id WHERE u.role = 'customer' GROUP BY b.branch_id");
$stmt->execute();
$customer_distribution = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get product performance by branch (products in inventory with sales data)
$sql = "SELECT
    b.branch_id,
    b.branch_name,
    p.product_name,
    COUNT(od.order_detail_id) as order_count,
    SUM(od.quantity) as total_quantity,
    SUM(od.quantity * od.price) as total_revenue
FROM branches b
INNER JOIN inventory i ON b.branch_id = i.branch_id
INNER JOIN products p ON i.product_id = p.product_id
LEFT JOIN users u ON b.branch_id = u.branch_id
LEFT JOIN orders o ON u.user_id = o.customer_id AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
LEFT JOIN order_details od ON o.order_id = od.order_id AND od.product_id = p.product_id
GROUP BY b.branch_id, p.product_id
ORDER BY b.branch_id, total_quantity DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$product_by_branch = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Organize product data by branch
$branch_products = [];
foreach ($product_by_branch as $item) {
    if (!isset($branch_products[$item['branch_id']])) {
        $branch_products[$item['branch_id']] = [
            'branch_name' => $item['branch_name'],
            'products' => []
        ];
    }
    $branch_products[$item['branch_id']]['products'][] = $item;
}

// Get pricing data for Lazareto branch from inventory
$lazareto_pricing = [];
$lazareto_labels = [];
$lazareto_prices = [];
$sql = "SELECT p.product_name, p.price FROM inventory i JOIN products p ON i.product_id = p.product_id JOIN branches b ON i.branch_id = b.branch_id WHERE b.branch_name = ? AND p.price IS NOT NULL ORDER BY p.product_name";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $branch_name = 'Lazareto';
    $stmt->bind_param('s', $branch_name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $lazareto_pricing = $res->fetch_all(MYSQLI_ASSOC);
        $lazareto_labels = array_column($lazareto_pricing, 'product_name');
        $lazareto_prices = array_map(function($r) { return (float)$r['price']; }, $lazareto_pricing);
    }
}

// Get pricing data for Calero branch from inventory
$calero_pricing = [];
$calero_labels = [];
$calero_prices = [];
$sql = "SELECT p.product_name, p.price FROM inventory i JOIN products p ON i.product_id = p.product_id JOIN branches b ON i.branch_id = b.branch_id WHERE b.branch_name = ? AND p.price IS NOT NULL ORDER BY p.product_name";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $branch_name = 'Calero';
    $stmt->bind_param('s', $branch_name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $calero_pricing = $res->fetch_all(MYSQLI_ASSOC);
        $calero_labels = array_column($calero_pricing, 'product_name');
        $calero_prices = array_map(function($r) { return (float)$r['price']; }, $calero_pricing);
    }
}

// Get current stock quantities for Lazareto branch from inventory
$lazareto_stock = [];
$lazareto_stock_labels = [];
$lazareto_stock_qty = [];
$sql = "SELECT p.product_name, i.quantity FROM inventory i JOIN products p ON i.product_id = p.product_id JOIN branches b ON i.branch_id = b.branch_id WHERE b.branch_name = ? ORDER BY p.product_name";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $branch_name = 'Lazareto';
    $stmt->bind_param('s', $branch_name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $lazareto_stock = $res->fetch_all(MYSQLI_ASSOC);
        $lazareto_stock_labels = array_column($lazareto_stock, 'product_name');
        $lazareto_stock_qty = array_map(function($r) { return (int)$r['quantity']; }, $lazareto_stock);
    }
}

// Get current stock quantities for Calero branch from inventory
$calero_stock = [];
$calero_stock_labels = [];
$calero_stock_qty = [];
$sql = "SELECT p.product_name, i.quantity FROM inventory i JOIN products p ON i.product_id = p.product_id JOIN branches b ON i.branch_id = b.branch_id WHERE b.branch_name = ? ORDER BY p.product_name";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $branch_name = 'Calero';
    $stmt->bind_param('s', $branch_name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $calero_stock = $res->fetch_all(MYSQLI_ASSOC);
        $calero_stock_labels = array_column($calero_stock, 'product_name');
        $calero_stock_qty = array_map(function($r) { return (int)$r['quantity']; }, $calero_stock);
    }
}

// Build combined arrays (ensure products align even if one query misses some products)
$price_map = [];
foreach ($lazareto_pricing as $p) {
    $price_map[$p['product_name']] = (float)$p['price'];
}
$stock_map = [];
foreach ($lazareto_stock as $s) {
    $stock_map[$s['product_name']] = (int)$s['quantity'];
}
$combined_labels = array_values(array_unique(array_merge(array_keys($price_map), array_keys($stock_map))));
sort($combined_labels);
$combined_prices = array_map(function($name) use ($price_map) {
    return isset($price_map[$name]) ? $price_map[$name] : 0;
}, $combined_labels);
$combined_stocks = array_map(function($name) use ($stock_map) {
    return isset($stock_map[$name]) ? $stock_map[$name] : 0;
}, $combined_labels);

// Build combined arrays for Calero
$price_map_c = [];
foreach ($calero_pricing as $p) {
    $price_map_c[$p['product_name']] = (float)$p['price'];
}
$stock_map_c = [];
foreach ($calero_stock as $s) {
    $stock_map_c[$s['product_name']] = (int)$s['quantity'];
}
$combined_labels_calero = array_values(array_unique(array_merge(array_keys($price_map_c), array_keys($stock_map_c))));
sort($combined_labels_calero);
$combined_prices_calero = array_map(function($name) use ($price_map_c) {
    return isset($price_map_c[$name]) ? $price_map_c[$name] : 0;
}, $combined_labels_calero);
$combined_stocks_calero = array_map(function($name) use ($stock_map_c) {
    return isset($stock_map_c[$name]) ? $stock_map_c[$name] : 0;
}, $combined_labels_calero);

// Get recent activities
$stmt = $conn->prepare("SELECT
    a.*, u.username, u.role
    FROM activity_log a
    JOIN users u ON a.user_id = u.user_id
    ORDER BY a.created_at DESC
    LIMIT 10");
$stmt->execute();
$recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid py-4">
    <h1 class="h3 mb-4">Super Admin Dashboard</h1>
    
    <!-- Quick Stats -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Branches</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $branches ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-store fa-2x text-gray-300"></i>
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
                                <?= formatCurrency($orders_stats['total_revenue'] ?? 0) ?>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $orders_stats['total_orders'] ?? 0 ?>
                            </div>
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
                                Total Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php
                                $total_users = array_reduce($users_by_role, function($carry, $item) {
                                    return $carry + $item['total'];
                                }, 0);
                                echo $total_users;
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <a href="manage_branches.php" class="btn btn-primary mr-2">
                        <i class="fas fa-store"></i> Manage Branches
                    </a>
                    <a href="manage_admins.php" class="btn btn-success mr-2">
                        <i class="fas fa-users-cog"></i> Manage Branch Admins
                    </a>
                    <a href="system_settings.php" class="btn btn-info mr-2">
                        <i class="fas fa-cogs"></i> System Settings
                    </a>
                    <a href="reports.php" class="btn btn-warning">
                        <i class="fas fa-chart-bar"></i> View Reports
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Branch Performance Comparison -->
        <div class="col-md-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Branch Performance Comparison</h6>
                </div>
                <div class="card-body">
                    <div class="chart-bar" style="height: 300px;">
                        <canvas id="branchComparisonChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Product Orders Trend -->
        <div class="col-md-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Daily Product Orders Trend</h6>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-primary active" onclick="toggleChartView('quantity')">Show Quantities</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleChartView('orders')">Show Order Count</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-line" style="height: 300px;">
                        <canvas id="dailyOrdersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Performance by Branch -->
    <!-- Lazareto Pricing Chart removed (combined chart used) -->
    <!-- Lazareto Current Stocks Chart -->
    <!-- Combined Charts: Lazareto (left) and Calero (right) -->
    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Lazareto - Inventory (Price & Current Stock)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 420px;">
                        <canvas id="lazaretoCombinedChart"></canvas>
                    </div>
                    <div class="small text-muted mt-2">Product Name.</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Calero - Inventory (Price & Current Stock)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 420px;">
                        <canvas id="caleroCombinedChart"></canvas>
                    </div>
                    <div class="small text-muted mt-2">Product Name</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lazareto Current Stocks card removed (combined chart used) -->
    <div class="row">
        <?php foreach ($branch_products as $branch_id => $branch_data): ?>
        <div class="col-xl-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Product Performance - <?= htmlspecialchars($branch_data['branch_name']) ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie mb-4" style="height: 250px;">
                        <canvas id="productChart<?= $branch_id ?>"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <?php
                        $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#6610f2'];
                        foreach ($branch_data['products'] as $index => $product):
                        ?>
                        <span class="mr-2">
                            <i class="fas fa-circle" style="color: <?= $colors[$index % count($colors)] ?>"></i> <?= htmlspecialchars($product['product_name']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>


    

    <!-- Recent Activities -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activities</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Activity</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activities as $activity): ?>
                                <tr>
                                    <td><?= htmlspecialchars($activity['username']) ?></td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($activity['role']) ?></span></td>
                                    <td><?= htmlspecialchars($activity['activity_type']) ?></td>
                                    <td><?= htmlspecialchars($activity['description']) ?></td>
                                    <td><?= date('M d, Y H:i:s', strtotime($activity['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="activity_log.php" class="btn btn-primary">See More</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<style>
/* Royal blue background for the dashboard */
body {
    background: white;
    min-height: 100vh;
}

/* Transparent grey cards with blur effect */
.card {
    background-color: whitesmoke!important;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

/* Neon blue glow effect on hover */
.card:hover {
    box-shadow: 0 0 20px rgba(0, 191, 255, 0.8), 0 0 40px rgba(0, 191, 255, 0.4), 0 0 60px rgba(0, 191, 255, 0.2);
    border-color: rgba(0, 191, 255, 0.5);
    transform: translateY(-7px);
}

/* Ensure text remains readable */
.card .card-header,
.card .card-body {
    color: black;
}

/* Adjust button colors for better visibility */
.btn-primary {
    background-color: rgba(0, 123, 255, 0.8);
    border-color: rgba(0, 123, 255, 0.8);
}

.btn-primary:hover {
    background-color: rgba(0, 123, 255, 1);
    border-color: rgba(0, 123, 255, 1);
}

/* Table styling for better contrast */
.table {
    color: #ffffff;
}

.table thead th {
    background-color: rgba(0, 0, 0, 0.3);
    border-color: rgba(255, 255, 255, 0.2);
}

.table tbody td {
    border-color: rgba(255, 255, 255, 0.1);
}

/* Badge styling */
.badge {
    background-color: rgba(0, 123, 255, 0.8);
}
</style>

<script>
$(document).ready(function() {
    // Initialize Branch Comparison Chart
    new Chart(document.getElementById('branchComparisonChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($branch_comparison, 'branch_name')) ?>,
            datasets: [{
                label: 'Total Orders',
                data: <?= json_encode(array_column($branch_comparison, 'total_orders')) ?>,
                backgroundColor: 'rgba(78, 115, 223, 0.8)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Orders'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    });

    // Daily Orders Trend Chart
    let dailyOrdersChart;
    const chartColors = [
        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
        '#6610f2', '#20c9a6', '#2c9faf', '#f39c12', '#e83e8c'
    ];

    function createDailyOrdersChart(dataType = 'quantity') {
        const ctx = document.getElementById('dailyOrdersChart');
        const dates = <?= json_encode($all_dates) ?>;
        const productData = <?= json_encode($product_daily_data) ?>;

        console.log('Dates:', dates);
        console.log('Product Data:', productData);

        // Destroy existing chart if it exists
        if (dailyOrdersChart) {
            dailyOrdersChart.destroy();
        }

        // Update button states
        $('.btn-group .btn').removeClass('active');
        $(`.btn-group .btn:contains('${dataType === 'quantity' ? 'Quantities' : 'Order Count'}')`).addClass('active');

        // Prepare datasets
        const datasets = Object.entries(productData).map(([product, data], index) => ({
            label: data.name,
            data: dataType === 'quantity' ? data.quantities : data.orders,
            borderColor: chartColors[index % chartColors.length],
            backgroundColor: chartColors[index % chartColors.length],
            fill: false,
            tension: 0.1
        }));

        console.log('Datasets:', datasets);

        dailyOrdersChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: dataType === 'quantity' ? 'Quantity Ordered' : 'Number of Orders'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.parsed.y;
                                return `${label}: ${value} ${dataType === 'quantity' ? 'units' : 'orders'}`;
                            }
                        }
                    }
                }
            }
        });
    }

    // Initial chart creation
    createDailyOrdersChart('quantity');

    // Function to toggle between quantity and orders view
    window.toggleChartView = function(dataType) {
        createDailyOrdersChart(dataType);
    };

    // Lazareto pricing chart removed (combined chart is used)

    // Lazareto Combined Chart (Price + Stock)
    (function() {
        const labels = <?= json_encode($combined_labels) ?>;
        const prices = <?= json_encode($combined_prices) ?>;
        const stocks = <?= json_encode($combined_stocks) ?>;
        const ctx = document.getElementById('lazaretoCombinedChart');

        if (ctx && labels && labels.length) {
            // determine suggested max for price axis rounded up to nearest 100
            const maxPrice = Math.max(...prices, 0);
            const suggestedPriceMax = Math.ceil(maxPrice / 100) * 100 || 100;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Price (PHP)',
                            data: prices,
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            yAxisID: 'y1'
                        },
                        {
                            type: 'line',
                            label: 'Current Stock',
                            data: stocks,
                            borderColor: 'rgba(75, 192, 192, 0.9)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            fill: false,
                            tension: 0.15,
                            pointRadius: 4,
                            yAxisID: 'y'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: { display: true, text: 'Quantity (pcs)' },
                            beginAtZero: true
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: { display: true, text: 'Price (PHP)' },
                            beginAtZero: true,
                            suggestedMax: suggestedPriceMax,
                            ticks: {
                                stepSize: 100,
                                callback: function(value) { return '₱ ' + value; }
                            }
                        }
                    },
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.parsed.y !== undefined ? context.parsed.y : context.raw;
                                    if (label && label.toLowerCase().includes('price')) {
                                        return label + ': ₱ ' + Number(value).toLocaleString();
                                    }
                                    return label + ': ' + value;
                                }
                            }
                        }
                    }
                }
            });
        }
    })();

    // Calero Combined Chart (Price + Stock) - reversed color scheme
    (function() {
        const labels = <?= json_encode($combined_labels_calero) ?>;
        const prices = <?= json_encode($combined_prices_calero) ?>;
        const stocks = <?= json_encode($combined_stocks_calero) ?>;
        const ctx = document.getElementById('caleroCombinedChart');

        if (ctx && labels && labels.length) {
            const maxPrice = Math.max(...prices, 0);
            const suggestedPriceMax = Math.ceil(maxPrice / 100) * 100 || 100;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Price (PHP)',
                            data: prices,
                            // reversed color scheme: use Calero price color swapped
                            backgroundColor: 'rgba(75, 192, 192, 0.7)',
                            yAxisID: 'y1'
                        },
                        {
                            type: 'line',
                            label: 'Current Stock',
                            data: stocks,
                            // reversed color scheme: stock line uses Lazareto bar color
                            borderColor: 'rgba(54, 162, 235, 0.9)',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            fill: false,
                            tension: 0.15,
                            pointRadius: 4,
                            yAxisID: 'y'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: { display: true, text: 'Quantity (pcs)' },
                            beginAtZero: true
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: { display: true, text: 'Price (PHP)' },
                            beginAtZero: true,
                            suggestedMax: suggestedPriceMax,
                            ticks: {
                                stepSize: 100,
                                callback: function(value) { return '₱ ' + value; }
                            }
                        }
                    },
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.parsed.y !== undefined ? context.parsed.y : context.raw;
                                    if (label && label.toLowerCase().includes('price')) {
                                        return label + ': ₱ ' + Number(value).toLocaleString();
                                    }
                                    return label + ': ' + value;
                                }
                            }
                        }
                    }
                }
            });
        }
    })();

    // Lazareto stock chart removed (combined chart is used)

    // Product Charts for each branch
    <?php foreach ($branch_products as $branch_id => $branch_data): ?>
    new Chart(document.getElementById('productChart<?= $branch_id ?>'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(function($p) {
                return $p['product_name'];
            }, $branch_data['products'])) ?>,
            datasets: [{
                data: <?= json_encode(array_map(function($p) {
                    return $p['total_quantity'];
                }, $branch_data['products'])) ?>,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#6610f2']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12
                    }
                }
            },
            cutout: '70%'
        }
    });
    <?php endforeach; ?>
});
</script>
