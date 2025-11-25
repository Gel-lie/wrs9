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

    <div class="row">
        <!-- Interactive Location Charts Card -->
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Orders by Location (Barangay → Sitio → Household)</h5>
                    <small class="text-muted">Click a barangay bar to view sitios; click a sitio point to view households.</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <canvas id="barChartBarangays" style="height:220px;"></canvas>
                        </div>
                        <div class="col-md-4">
                            <canvas id="splineChartSitios" style="height:220px;"></canvas>
                        </div>
                        <div class="col-md-4">
                            <canvas id="pieChartHouseholds" style="height:220px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                    backgroundColor: labels.map((_,i)=>`hsl(${i*40 % 360} 70% 50%)`)
                }]
            },
            options: {
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const i = context.dataIndex;
                                const qty = values[i];
                                const price = prices[i];
                                const date = lastDelivery[i];
                                return `${labels[i]} — Qty: ${qty}, Price: ₱${parseFloat(price).toFixed(2)}, Last Delivery: ${date}`;
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
            $('#pieChartHouseholds').replaceWith('<canvas id="pieChartHouseholds" style="height:220px;"></canvas>');
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
</script>

<?php require_once '../includes/footer.php'; ?> 