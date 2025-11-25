<?php
require_once '../includes/functions.php';

// Check if user is logged in and is a branch admin
if (!isLoggedIn() || !hasRole('branch_admin')) {
    header("Location: /login.php");
    exit();
}

require_once '../includes/db.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Get admin's information
$admin = getUserInfo($_SESSION['user_id']);
$branch_id = $admin['branch_id'];

// Get filter parameters
$status_filter = $_GET['status'] ?? 'pending'; // Default to pending orders
$date_filter = $_GET['date'] ?? date('Y-m-d'); // Default to today
$search = $_GET['search'] ?? '';

// Prepare the base query
$query = "
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
";

$params = [$branch_id];
$types = "i";

// Add status filter if not 'all'
if ($status_filter !== 'all') {
    $query .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Add date filter if not 'all'
if ($date_filter !== 'all') {
    $query .= " AND DATE(o.order_date) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

// Add search filter if provided
if (!empty($search)) {
    $query .= " AND (
        u.name LIKE ? OR 
        o.order_id LIKE ? OR 
        u.contact_number LIKE ? OR
        b.barangay_name LIKE ?
    )";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}

$query .= " GROUP BY o.order_id ORDER BY o.order_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get order counts by status
$stmt = $conn->prepare("
    SELECT status, COUNT(*) as count
    FROM orders
    WHERE branch_id = ?
    GROUP BY status
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$status_counts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$counts = [];
foreach ($status_counts as $count) {
    $counts[$count['status']] = $count['count'];
}

require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3">Order Management</h1>
            <p class="text-muted">Manage and track all orders for <?php echo htmlspecialchars($admin['branch_name'] ?? ''); ?></p>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card <?php echo $status_filter === 'pending' ? 'bg-warning text-white' : ''; ?>">
                <div class="card-body">
                    <h5 class="card-title">Pending</h5>
                    <h2 class="mb-0"><?php echo $counts['pending'] ?? 0; ?></h2>
                    <a href="/waterrefillingstation/branch_admins/orders.php?status=pending<?php echo $date_filter !== 'all' ? '&date=' . $date_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="stretched-link"></a>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card <?php echo $status_filter === 'processing' ? 'bg-primary text-white' : ''; ?>">
                <div class="card-body">
                    <h5 class="card-title">Processing</h5>
                    <h2 class="mb-0"><?php echo $counts['processing'] ?? 0; ?></h2>
                    <a href="/waterrefillingstation/branch_admins/orders.php?status=processing<?php echo $date_filter !== 'all' ? '&date=' . $date_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="stretched-link"></a>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card <?php echo $status_filter === 'delivered' ? 'bg-success text-white' : ''; ?>">
                <div class="card-body">
                    <h5 class="card-title">Delivered</h5>
                    <h2 class="mb-0"><?php echo $counts['delivered'] ?? 0; ?></h2>
                    <a href="/waterrefillingstation/branch_admins/orders.php?status=delivered<?php echo $date_filter !== 'all' ? '&date=' . $date_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="stretched-link"></a>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card <?php echo $status_filter === 'cancelled' ? 'bg-danger text-white' : ''; ?>">
                <div class="card-body">
                    <h5 class="card-title">Cancelled</h5>
                    <h2 class="mb-0"><?php echo $counts['cancelled'] ?? 0; ?></h2>
                    <a href="/waterrefillingstation/branch_admins/orders.php?status=cancelled<?php echo $date_filter !== 'all' ? '&date=' . $date_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="stretched-link"></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date" class="form-label">Date</label>
                    <input type="date" class="form-control" id="date" name="date" 
                           value="<?php echo $date_filter !== 'all' ? $date_filter : ''; ?>">
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Search by order ID, customer name, or contact">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($orders)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No orders found matching your filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date & Time</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Delivery Info</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($order['order_date'])); ?><br>
                                        <small class="text-muted">
                                            <?php echo date('h:i A', strtotime($order['order_date'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['customer_name'] ?? ''); ?></strong><br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($order['contact_number'] ?? 'No contact'); ?><br>
                                            <?php echo htmlspecialchars($order['barangay_name'] ?? 'No barangay'); ?>
                                        </small>
                                    </td>
                                    <td><?php echo $order['items'] ?? 'No items'; ?></td>
                                    <td>
                                        <?php if ($order['delivery_date']): ?>
                                            Delivery: <?php echo date('M d, Y h:i A', strtotime($order['delivery_date'])); ?>
                                        <?php endif; ?>
                                        <?php if ($order['notes']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($order['notes']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($order['status']) {
                                                'pending' => 'warning',
                                                'processing' => 'primary',
                                                'delivered' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <div class="btn-group">
                                                <a href="process_order.php?id=<?php echo $order['order_id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    Process
                                                </a>
                                            </div>
                                        <?php elseif ($order['status'] === 'processing'): ?>
                                            <a href="complete_order.php?id=<?php echo $order['order_id']; ?>" 
                                               class="btn btn-sm btn-success">
                                                Complete
                                            </a>
                                        <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form when filters change
    document.querySelectorAll('#status, #date').forEach(function(element) {
        element.addEventListener('change', function() {
            this.form.submit();
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?> 