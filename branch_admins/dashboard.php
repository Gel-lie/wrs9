<?php
require_once '../includes/functions.php';

// Check if user is logged in and is a branch admin
if (!isLoggedIn() || !hasRole('branch_admin')) {
    header("Location: /login.php");
    exit();
}

require_once '../includes/db.php';

// Get admin's information
$admin = getUserInfo($_SESSION['user_id']);
$branch_id = $admin['branch_id'];

// Get today's statistics
$today = date('Y-m-d');

// Get pending orders count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM orders 
    WHERE branch_id = ? 
    AND status = 'pending'
    AND DATE(order_date) = ?
");
$stmt->bind_param("is", $branch_id, $today);
$stmt->execute();
$pending_orders = $stmt->get_result()->fetch_assoc()['count'];

// Get today's completed orders
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM orders 
    WHERE branch_id = ? 
    AND status = 'delivered' 
    AND DATE(order_date) = ?
");
$stmt->bind_param("is", $branch_id, $today);
$stmt->execute();
$completed_orders = $stmt->get_result()->fetch_assoc()['count'];

// Get today's revenue
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as revenue 
    FROM orders 
    WHERE branch_id = ? 
    AND status = 'delivered' 
    AND DATE(order_date) = ?
");
$stmt->bind_param("is", $branch_id, $today);
$stmt->execute();
$today_revenue = $stmt->get_result()->fetch_assoc()['revenue'];

// Get recent orders with customer details
$stmt = $conn->prepare("
    SELECT
        o.order_id,
        o.order_date,
        o.delivery_date,
        o.total_amount,
        o.status,
        o.notes,
        u.name as customer_name,
        u.contact_number,
        b.barangay_name,
        GROUP_CONCAT(
            CONCAT(
                od.quantity,
                'x ',
                CASE
                    WHEN p.product_name IS NOT NULL THEN p.product_name
                    WHEN od.product_id = 1 THEN 'Water Refill'
                    WHEN od.product_id = 2 THEN 'Dispenser Rental'
                    WHEN od.product_id = 3 THEN 'Delivery Charge'
                    ELSE 'Unknown Item'
                END,
                ' (₱',
                FORMAT(od.price, 2),
                ')'
            ) SEPARATOR '<br>'
        ) as items
    FROM orders o
    JOIN users u ON o.customer_id = u.user_id
    LEFT JOIN barangays b ON u.barangay_id = b.barangay_id
    JOIN order_details od ON o.order_id = od.order_id
    LEFT JOIN products p ON od.product_id = p.product_id
    WHERE o.branch_id = ?
    GROUP BY o.order_id
    ORDER BY
        CASE o.status
            WHEN 'pending' THEN 1
            WHEN 'processing' THEN 2
            WHEN 'delivered' THEN 3
            WHEN 'cancelled' THEN 4
        END,
        o.order_date DESC
    LIMIT 10
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
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$low_stock = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';

// Prepare combined inventory (price + stock) for this branch
$branch_name = $admin['branch_name'] ?? '';
$combined_labels = [];
$combined_prices = [];
$combined_stocks = [];
if ($branch_name) {
    // Pricing
    $stmt = $conn->prepare("SELECT p.product_name, p.price FROM inventory i JOIN products p ON i.product_id = p.product_id JOIN branches b ON i.branch_id = b.branch_id WHERE b.branch_name = ? AND p.price IS NOT NULL ORDER BY p.product_name");
    if ($stmt) {
        $stmt->bind_param('s', $branch_name);
        $stmt->execute();
        $res = $stmt->get_result();
        $pricing = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } else { $pricing = []; }

    // Stock
    $stmt = $conn->prepare("SELECT p.product_name, i.quantity FROM inventory i JOIN products p ON i.product_id = p.product_id JOIN branches b ON i.branch_id = b.branch_id WHERE b.branch_name = ? ORDER BY p.product_name");
    if ($stmt) {
        $stmt->bind_param('s', $branch_name);
        $stmt->execute();
        $res = $stmt->get_result();
        $stock = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } else { $stock = []; }

    $price_map = [];
    foreach ($pricing as $p) { $price_map[$p['product_name']] = (float)$p['price']; }
    $stock_map = [];
    foreach ($stock as $s) { $stock_map[$s['product_name']] = (int)$s['quantity']; }

    $combined_labels = array_values(array_unique(array_merge(array_keys($price_map), array_keys($stock_map))));
    sort($combined_labels);
    $combined_prices = array_map(function($name) use ($price_map) { return isset($price_map[$name]) ? $price_map[$name] : 0; }, $combined_labels);
    $combined_stocks = array_map(function($name) use ($stock_map) { return isset($stock_map[$name]) ? $stock_map[$name] : 0; }, $combined_labels);
}
?>

<div class="container py-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3">Welcome, <?php echo htmlspecialchars($admin['name'] ?? ''); ?>!</h1>
            <p class="text-muted">Branch: <?php echo htmlspecialchars($admin['branch_name'] ?? ''); ?></p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="inventory.php" class="btn btn-primary">
                <i class="fas fa-box"></i> Manage Inventory
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Pending Orders</h6>
                            <h2 class="mb-0"><?php echo $pending_orders; ?></h2>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Completed Today</h6>
                            <h2 class="mb-0"><?php echo $completed_orders; ?></h2>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Today's Revenue</h6>
                            <h2 class="mb-0">₱<?php echo number_format($today_revenue, 2); ?></h2>
                        </div>
                        <i class="fas fa-peso-sign fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Combined Inventory Chart for this Branch -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Inventory — Price & Current Stock</h5>
                    <small class="text-muted">Product price (bars) and current stock (line) for this branch</small>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height:420px;">
                        <canvas id="branchCombinedChart"></canvas>
                    </div>
                    <div class="small text-muted mt-2">Products listed alphabetically.</div>
                </div>
            </div>
        </div>

        <!-- Donut Charts beside Inventory -->
        <div class="col-md-8 mb-4">
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Order Progress Status</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="orderProgressDonut"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Stock Status</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="stockStatusDonut"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Customer Metrics</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="customerMetricsDonut"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Location Charts Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Bulk Order Location (Barangay - Sitio - Household)</h5>
                    <small class="text-muted">Click a barangay bar to view sitios; click a sitio point to view households.</small>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-5">
                            <canvas id="barChartBarangays" style="height:220px;"></canvas>
                        </div>
                        <div class="col-5">
                            <canvas id="splineChartSitios" style="height:220px;"></canvas>
                        </div>
                        <div class="col-2">
                            <div style="height:220px;">
                                <canvas id="pieChartHouseholds" style="height:220px; width:100%;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Login Timeline Chart -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Customer Login Timeline</h5>
                    <small class="text-muted">Daily login activity for all Lazareto branch customers over the last 30 days</small>
                </div>
                <div class="card-body">
                    <canvas id="customerLoginTimeline" style="height:300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        <!-- Recent Orders -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Recent Orders</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_orders)): ?>
                        <p class="text-muted mb-0">No recent orders found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Items</th>
                                        <th>Delivery Info</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['order_id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($order['customer_name'] ?? ''); ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($order['contact_number'] ?? 'No contact'); ?><br>
                                                    <?php echo htmlspecialchars($order['barangay_name'] ?? 'No barangay'); ?>
                                                </small>
                                            </td>
                                            <td><?php echo $order['items']; ?></td>
                                            <td>
                                                <strong>Order:</strong> <?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?><br>
                                                <strong>Delivery:</strong> <?php echo date('M j, Y g:i A', strtotime($order['delivery_date'])); ?>
                                            </td>
                                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'pending' => 'warning',
                                                    'processing' => 'info',
                                                    'delivered' => 'success',
                                                    'cancelled' => 'danger'
                                                ][$order['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="orders.php?id=<?php echo $order['order_id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Low Stock Alert</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($low_stock)): ?>
                        <p class="text-muted mb-0">All products are well stocked.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($low_stock as $product): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                                            <small class="text-muted">
                                                Threshold: <?php echo $product['low_stock_threshold']; ?> units
                                            </small>
                                        </div>
                                        <span class="badge bg-danger">
                                            <?php echo $product['quantity']; ?> left
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Charts: Bar (barangays) -> Spline (sitios) -> Pie (households)
    let barChart, splineChart, pieChart;
    let barangayIds = [];

    function fetchBarangays() {
        return $.getJSON('get_branch_stats.php', { action: 'barangays' });
    }

    function fetchSitios(barangay_id) {
        return $.getJSON('get_branch_stats.php', { action: 'sitios', barangay_id: barangay_id });
    }

    function fetchHouseholds(barangay_id, sitio) {
        return $.getJSON('get_branch_stats.php', { action: 'households', barangay_id: barangay_id, sitio: sitio });
    }

    function renderBarChart(data) {
        const labels = data.map(d => d.barangay_name);
        const values = data.map(d => parseInt(d.total_qty) || 0);
        barangayIds = data.map(d => d.barangay_id);

        const ctx = document.getElementById('barChartBarangays').getContext('2d');
        if (barChart) barChart.destroy();
        barChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Quantity',
                    data: values,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                onClick: (evt, elements) => {
                    if (!elements.length) return;
                    const idx = elements[0].index;
                    const barangay_id = barangayIds[idx];
                    loadSitios(barangay_id);
                }
            }
        });
    }

    function renderSplineChart(data) {
        const labels = data.map(d => d.sitio);
        const values = data.map(d => parseInt(d.total_qty) || 0);

        const ctx = document.getElementById('splineChartSitios').getContext('2d');
        if (splineChart) splineChart.destroy();
        splineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Quantity',
                    data: values,
                    fill: false,
                    tension: 0.4,
                    borderColor: 'rgba(75, 192, 192, 0.9)',
                    backgroundColor: 'rgba(75, 192, 192, 0.6)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                onClick: (evt, elements) => {
                    if (!elements.length) return;
                    const idx = elements[0].index;
                    const sitio = labels[idx];
                    // Need to know current barangay_id (stored on element)
                    const currentBarangayId = $("#splineChartSitios").data('barangay-id');
                    if (currentBarangayId) {
                        loadHouseholds(currentBarangayId, sitio);
                    }
                }
            }
        });
    }

    function renderPieChart(rows, details) {
        const labels = rows.map(r => r.surname || 'Unknown');
        const values = rows.map(r => parseInt(r.total_qty) || 0);
        const prices = rows.map(r => parseFloat(r.total_price) || 0);
        const lastDelivery = rows.map(r => r.last_delivery || '');

        const ctx = document.getElementById('pieChartHouseholds').getContext('2d');
        if (pieChart) pieChart.destroy();
        pieChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    // Use indigo palette (material indigo #3f51b5 -> rgb(63,81,181)) with varying alpha
                    backgroundColor: labels.map((_,i)=> `rgba(63,81,181,${Math.max(0.35, 0.95 - (i * 0.08))})`),
                    borderColor: labels.map(() => 'rgba(48,63,159,1)'),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            title: function() {
                                return '';
                            },
                            label: function(context) {
                                const i = context.dataIndex;
                                const qty = values[i];
                                const price = prices[i];
                                const dateTime = lastDelivery[i];
                                return [
                                    `${labels[i]}`,
                                    `Quantity: ${qty}`,
                                    `Price: ₱${parseFloat(price).toFixed(2)}`,
                                    `Date: ${dateTime.split(' ')[0] || ''}`,
                                    `Time: ${dateTime.split(' ')[1] || ''}`
                                ];
                            }
                        }
                    }
                },
                onClick: (evt, elements) => {
                    if (!elements.length) return;
                    const idx = elements[0].index;
                    const surname = labels[idx];
                    // Show details in simple alert (or can build modal)
                    const entries = details[surname] || [];
                    let msg = `${surname} — Orders:\n`;
                    entries.forEach(e => {
                        msg += `Order #${e.order_id}: Qty ${e.quantity}, Line total ₱${parseFloat(e.line_total).toFixed(2)}, Delivery ${e.delivery_date}\n`;
                    });
                    alert(msg);
                }
            }
        });
    }

    function loadSitios(barangay_id) {
            fetchSitios(barangay_id).done(function(resp) {
            const rows = resp.data || [];
            // store barangay id on spline canvas for next step
            $("#splineChartSitios").data('barangay-id', barangay_id);
            renderSplineChart(rows);
            // Clear pie chart
            if (pieChart) pieChart.destroy();
            $('#pieChartHouseholds').replaceWith('<canvas id="pieChartHouseholds" style="height:220px; width:100%;"></canvas>');
        });
    }

    function loadHouseholds(barangay_id, sitio) {
        fetchHouseholds(barangay_id, sitio).done(function(resp) {
            const rows = resp.data || [];
            const details = resp.details || {};
            renderPieChart(rows, details);
        });
    }

    $(function() {
        // Initial load
        fetchBarangays().done(function(resp) {
            const rows = resp.data || [];
            renderBarChart(rows);
            // Optionally auto-load first barangay's sitios
            if (rows.length) {
                loadSitios(rows[0].barangay_id);
            }
        });
    });

    // Branch combined inventory chart (price + stock)
    (function() {
        const labels = <?= json_encode($combined_labels) ?>;
        const prices = <?= json_encode($combined_prices) ?>;
        const stocks = <?= json_encode($combined_stocks) ?>;
        const ctx = document.getElementById('branchCombinedChart');

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
                            ticks: { stepSize: 100, callback: function(value) { return '₱ ' + value; } }
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

    // Donut Charts for Lazareto Branch
    (function() {
        // Order Progress Donut Chart
        $.getJSON('get_branch_stats.php', { action: 'order_progress' })
            .done(function(resp) {
                const data = resp.data || {};
                const labels = Object.keys(data);
                const values = Object.values(data);

                const ctx = document.getElementById('orderProgressDonut');
                if (ctx && labels.length > 0) {
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: values,
                                backgroundColor: [
                                    'rgba(255, 193, 7, 0.8)',   // pending - yellow
                                    'rgba(0, 123, 255, 0.8)',   // processing - blue
                                    'rgba(40, 167, 69, 0.8)',   // delivered - green
                                    'rgba(220, 53, 69, 0.8)'    // cancelled - red
                                ],
                                borderColor: [
                                    'rgba(255, 193, 7, 1)',
                                    'rgba(0, 123, 255, 1)',
                                    'rgba(40, 167, 69, 1)',
                                    'rgba(220, 53, 69, 1)'
                                ],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom' },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.parsed;
                                            return label + ': ' + value;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            })
            .fail(function() {
                console.error('Failed to load order progress data');
            });
    })();

    (function() {
        // Stock Status Donut Chart
        $.getJSON('get_branch_stats.php', { action: 'stock_status' })
            .done(function(resp) {
                const data = resp.data || {};
                const labels = Object.keys(data);
                const values = Object.values(data);

                const ctx = document.getElementById('stockStatusDonut');
                if (ctx && labels.length > 0) {
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: values,
                                backgroundColor: [
                                    'rgba(220, 53, 69, 0.8)',   // Out of Stock - red
                                    'rgba(255, 193, 7, 0.8)',   // Low Stock - yellow
                                    'rgba(40, 167, 69, 0.8)'    // In Stock - green
                                ],
                                borderColor: [
                                    'rgba(220, 53, 69, 1)',
                                    'rgba(255, 193, 7, 1)',
                                    'rgba(40, 167, 69, 1)'
                                ],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom' },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.parsed;
                                            return label + ': ' + value + ' products';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            })
            .fail(function() {
                console.error('Failed to load stock status data');
            });
    })();

    (function() {
        // Customer Metrics Donut Chart
        $.getJSON('get_branch_stats.php', { action: 'customer_metrics' })
            .done(function(resp) {
                const data = resp.data || {};
                const labels = Object.keys(data);
                const values = Object.values(data);

                const ctx = document.getElementById('customerMetricsDonut');
                if (ctx && labels.length > 0) {
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: values,
                                backgroundColor: [
                                    'rgba(0, 123, 255, 0.8)',   // Total Customers - blue
                                    'rgba(40, 167, 69, 0.8)',   // Active Customers - green
                                    'rgba(108, 117, 125, 0.8)', // Non-Active Customers - gray
                                    'rgba(255, 193, 7, 0.8)'    // New Customers - yellow
                                ],
                                borderColor: [
                                    'rgba(0, 123, 255, 1)',
                                    'rgba(40, 167, 69, 1)',
                                    'rgba(108, 117, 125, 1)',
                                    'rgba(255, 193, 7, 1)'
                                ],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom' },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.parsed;
                                            return label + ': ' + value;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            })
            .fail(function() {
                console.error('Failed to load customer metrics data');
            });
    })();

    (function() {
        // Customer Timeline Chart
        $.getJSON('get_branch_stats.php', { action: 'customer_timeline' })
            .done(function(resp) {
                const timeline = resp.timeline || [];

                const ctx = document.getElementById('customerLoginTimeline');
                if (ctx && timeline.length > 0) {
                    const dates = timeline.map(item => item.date);
                    const loginCounts = timeline.map(item => item.login_count);
                    const transactionCounts = timeline.map(item => item.transaction_count);

                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: dates,
                            datasets: [
                                {
                                    label: 'Login Count',
                                    data: loginCounts,
                                    borderColor: 'rgba(138, 43, 226, 0.9)', // Violet
                                    backgroundColor: 'rgba(138, 43, 226, 0.1)',
                                    fill: true,
                                    tension: 0.4,
                                    pointRadius: 3,
                                    pointHoverRadius: 5
                                },
                                {
                                    label: 'Transaction Count',
                                    data: transactionCounts,
                                    borderColor: 'rgba(63, 81, 181, 0.9)', // Indigo
                                    backgroundColor: 'rgba(63, 81, 181, 0.1)',
                                    fill: true,
                                    tension: 0.4,
                                    pointRadius: 3,
                                    pointHoverRadius: 5
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            },
                            plugins: {
                                legend: { position: 'top' },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    callbacks: {
                                        title: function(context) {
                                            const dateIndex = context[0].dataIndex;
                                            const dateData = timeline[dateIndex];
                                            return 'Date: ' + dateData.date;
                                        },
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            })
            .fail(function() {
                console.error('Failed to load customer timeline data');
            });
    })();
</script>

<?php require_once '../includes/footer.php'; ?> 