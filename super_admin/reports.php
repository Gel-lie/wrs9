<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

// Check if user is logged in and has super admin role
if (!isLoggedIn() || !hasRole('super_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$branch_id = isset($_GET['branch_id']) ? $_GET['branch_id'] : '';

// Get all branches for filter
$stmt = $conn->prepare("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
$stmt->execute();
$branches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Prepare branch condition for queries
$branch_condition = $branch_id ? "AND b.branch_id = ?" : "";

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
LEFT JOIN orders o ON u.user_id = o.customer_id AND o.order_date BETWEEN ? AND ?
GROUP BY b.branch_id
ORDER BY total_orders DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$branch_comparison = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
LEFT JOIN orders o ON u.user_id = o.customer_id AND o.order_date BETWEEN ? AND ?
LEFT JOIN order_details od ON o.order_id = od.order_id AND od.product_id = p.product_id
GROUP BY b.branch_id, p.product_id
ORDER BY b.branch_id, total_quantity DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$product_by_branch = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Define all available container types from refill request form (8 types)
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

// Initialize refill data per branch (show all container types, even with 0 count)
$branch_refills = [];
foreach ($branches as $branch) {
    $branch_refills[$branch['branch_id']] = [
        'branch_name' => $branch['branch_name'],
        'containers' => []
    ];
    foreach ($all_container_types as $display_name => $key) {
        $branch_refills[$branch['branch_id']]['containers'][$key] = [
            'name' => $display_name,
            'count' => 0,
            'revenue' => 0
        ];
    }
}

// Query refill orders per branch
$sql = "SELECT u.branch_id, o.notes, od.quantity, od.price
        FROM orders o
        JOIN order_details od ON o.order_id = od.order_id
        JOIN users u ON o.customer_id = u.user_id
        WHERE od.product_id = 1 AND o.order_date BETWEEN ? AND ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $branch_id = $row['branch_id'];
    $notes = $row['notes'];
    $quantity = $row['quantity'];
    $price = $row['price'];

    // Extract container type from notes
    if (preg_match('/Container:\s*(\w+)/', $notes, $matches)) {
        $container_key = $matches[1];
        if (isset($branch_refills[$branch_id]['containers'][$container_key])) {
            $branch_refills[$branch_id]['containers'][$container_key]['count'] += $quantity;
            $branch_refills[$branch_id]['containers'][$container_key]['revenue'] += $quantity * $price;
        }
    }
}

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

// Note: Container data is now organized in $branch_refills

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

// Debug output
echo "<!-- Debug: Found " . count($daily_orders) . " order records -->\n";

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

// Debug output
echo "<!-- Debug: Found " . count($product_daily_data) . " products with orders -->\n";
foreach ($product_daily_data as $product => $data) {
    echo "<!-- Debug: $product - Total quantity: " . array_sum($data['quantities']) . " -->\n";
}

// Sort products by total quantity
uasort($product_daily_data, function($a, $b) {
    return array_sum($b['quantities']) - array_sum($a['quantities']);
});

// Limit to top 10 products for better visualization
$product_daily_data = array_slice($product_daily_data, 0, 10, true);

// Debug output
echo "<!-- Debug: Final dates: " . implode(', ', $all_dates) . " -->\n";


?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Reports & Analytics</h1>
        <div>
        <a href="export_preview.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
            class="btn btn-success">
            Print Report
        </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="get" class="row align-items-end">
                <div class="col-md-3">
                    <label>Start Date</label>
                    <input type="date" class="form-control" name="start_date" 
                           value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-3">
                    <label>End Date</label>
                    <input type="date" class="form-control" name="end_date" 
                           value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-3">
                    <label>Branch</label>
                    <select class="form-control" name="branch_id">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                        <option value="<?= $branch['branch_id'] ?>" 
                                <?= $branch_id == $branch['branch_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($branch['branch_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sales Summary -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Sales Summary</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="salesTable">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>Total Orders</th>
                            <th>Total Revenue</th>
                            <th>Average Order Value</th>
                            <th>Unique Customers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branch_comparison as $summary): ?>
                        <tr>
                            <td><?= htmlspecialchars($summary['branch_name']) ?></td>
                            <td><?= $summary['total_orders'] ?></td>
                            <td><?= formatCurrency($summary['total_revenue']) ?></td>
                            <td><?= formatCurrency($summary['total_orders'] > 0 ? $summary['total_revenue'] / $summary['total_orders'] : 0) ?></td>
                            <td><?= $summary['unique_customers'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Product Performance by Branch -->
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
                    <div class="table-responsive mt-3">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Orders</th>
                                    <th>Quantity</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($branch_data['products'] as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['product_name']) ?></td>
                                    <td><?= (int)$product['order_count'] ?></td>
                                    <td><?= (int)$product['total_quantity'] ?></td>
                                    <td><?= formatCurrency($product['total_revenue']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Refill Request Performance by Branch -->
    <div class="row">
        <?php
        // Custom sort for refill request performance: reverse Calero and Lazareto positions
        $sorted_branch_refills = $branch_refills;
        uksort($sorted_branch_refills, function($a, $b) use ($sorted_branch_refills) {
            $name_a = $sorted_branch_refills[$a]['branch_name'];
            $name_b = $sorted_branch_refills[$b]['branch_name'];
            if ($name_a === 'Calero' && $name_b === 'Lazareto') return 1;
            if ($name_a === 'Lazareto' && $name_b === 'Calero') return -1;
            return strcmp($name_a, $name_b);
        });
        ?>
        <?php foreach ($sorted_branch_refills as $branch_id => $branch_data): ?>
        <div class="col-xl-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">
                        Refill Request Performance - <?= htmlspecialchars($branch_data['branch_name']) ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie mb-4" style="height: 250px;">
                        <canvas id="containerChart<?= $branch_id ?>"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <?php
                        $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'];
                        $container_items = array_values($branch_data['containers']);
                        foreach ($container_items as $index => $container):
                        ?>
                        <span class="mr-2">
                            <i class="fas fa-circle" style="color: <?= $colors[$index % count($colors)] ?>"></i> <?= htmlspecialchars($container['name']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="table-responsive mt-3">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Container Type</th>
                                    <th>Count</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($branch_data['containers'] as $container_key => $container): ?>
                                <tr>
                                    <td><?= htmlspecialchars($container['name']) ?></td>
                                    <td><?= (int)$container['count'] ?></td>
                                    <td><?= formatCurrency($container['revenue']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#salesTable').DataTable();

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

    // Container Charts for each branch
    <?php foreach ($branch_refills as $branch_id => $branch_data): ?>
    new Chart(document.getElementById('containerChart<?= $branch_id ?>'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(function($c) {
                return $c['name'];
            }, array_values($branch_data['containers']))) ?>,
            datasets: [{
                data: <?= json_encode(array_map(function($c) {
                    return $c['count'];
                }, array_values($branch_data['containers']))) ?>,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b']
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

// Export functions
function exportToPDF() {
    // Implementation for PDF export
    alert('PDF export functionality will be implemented');
}
</script> 