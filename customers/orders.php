<?php
require_once '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !hasRole('customer')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();
$user = getUserInfo($_SESSION['user_id']);

// Get customer's orders with details
$query = "
    SELECT 
        o.*,
        b.branch_name,
        COUNT(od.order_detail_id) as total_items,
        COALESCE(GROUP_CONCAT(
            CONCAT(p.product_name, ' (', od.quantity, ')')
            SEPARATOR ', '
        ), 'No items') as items_list,
        COALESCE(o.delivery_address, 'Not specified') as delivery_address,
        COALESCE(o.notes, '') as notes,
        COALESCE(b.branch_name, 'Unknown Branch') as branch_name
    FROM orders o
    JOIN branches b ON o.branch_id = b.branch_id
    LEFT JOIN order_details od ON o.order_id = od.order_id
    LEFT JOIN products p ON od.product_id = p.product_id
    WHERE o.customer_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get order statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed_orders,
        COALESCE(SUM(CASE WHEN status = 'delivered' THEN total_amount END), 0) as total_spent,
        COALESCE((
            SELECT points 
            FROM loyalty 
            WHERE customer_id = o.customer_id
            LIMIT 1
        ), 0) as loyalty_points
    FROM orders o
    WHERE o.customer_id = ?
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">My Orders</h1>
        <div class="d-flex gap-2">
            <a href="cart.php" class="btn btn-primary">
                <i class="fas fa-shopping-cart"></i> View Cart
            </a>
            <a href="refill_request.php" class="btn btn-success">
                <i class="fas fa-plus"></i> New Refill Request
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Orders</h6>
                    <h2 class="mb-0"><?= $stats['total_orders'] ?? 0 ?></h2>
                    <small>
                        <?= $stats['pending_orders'] ?? 0 ?> Pending,
                        <?= $stats['processing_orders'] ?? 0 ?> Processing
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Spent</h6>
                    <h2 class="mb-0">₱<?= number_format($stats['total_spent'] ?? 0, 2) ?></h2>
                    <small><?= $stats['completed_orders'] ?? 0 ?> Completed Orders</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Loyalty Points</h6>
                    <h2 class="mb-0"><?= $stats['loyalty_points'] ?? 0 ?></h2>
                    <small>Points Available</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders List -->
    <div class="card shadow">
        <div class="card-body">
            <?php if (empty($orders)): ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                <h5>No Orders Yet</h5>
                <p class="text-muted">Start by making your first refill request!</p>
                <a href="refill_request.php" class="btn btn-primary">
                    Make a Refill Request
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table" id="ordersTable">
                    <thead>
                        <tr>
                            <th>Order Info</th>
                            <th>Items</th>
                            <th>Branch</th>
                            <th>Delivery Details</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <strong>#<?= $order['order_id'] ?></strong><br>
                                <small class="text-muted">
                                    Ordered: <?= date('M d, Y h:ia', strtotime($order['order_date'])) ?><br>
                                    Total: ₱<?= number_format($order['total_amount'], 2) ?>
                                </small>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($order['items_list'] ?? 'No items') ?></small>
                            </td>
                            <td><?= htmlspecialchars($order['branch_name'] ?? 'Unknown Branch') ?></td>
                            <td>
                                <small>
                                    <strong>Address:</strong><br>
                                    <?= htmlspecialchars($order['delivery_address'] ?? 'Not specified') ?><br>
                                    <strong>Delivery Date:</strong><br>
                                    <?= date('M d, Y h:ia', strtotime($order['delivery_date'])) ?>
                                </small>
                            </td>
                            <td>
                                <?php
                                $status_class = match($order['status']) {
                                    'pending' => 'bg-warning',
                                    'processing' => 'bg-info',
                                    'delivered' => 'bg-success',
                                    'cancelled' => 'bg-danger',
                                    default => 'bg-secondary'
                                };
                                $status_icon = match($order['status']) {
                                    'pending' => 'clock',
                                    'processing' => 'truck',
                                    'delivered' => 'check-circle',
                                    'cancelled' => 'times-circle',
                                    default => 'question-circle'
                                };
                                ?>
                                <span class="badge <?= $status_class ?>">
                                    <i class="fas fa-<?= $status_icon ?>"></i>
                                    <?= ucfirst($order['status']) ?>
                                </span>
                                <?php if ($order['status'] === 'pending'): ?>
                                    <br>
                                    <button type="button"
                                            class="btn btn-sm btn-danger mt-2"
                                            onclick="confirmCancelOrder(<?= $order['order_id'] ?>)">
                                        <i class="fas fa-times"></i> Cancel Order
                                    </button>
                                <?php endif; ?>
                                <?php if (!empty($order['notes'])): ?>
                                    <br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($order['notes'] ?? '') ?>
                                    </small>
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

    <!-- Order Tips -->
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-info-circle text-primary"></i>
                        Order Status Guide
                    </h5>
                    <ul class="list-unstyled mb-0">
                        <li><span class="badge bg-warning">Pending</span> - Order received, awaiting processing</li>
                        <li><span class="badge bg-info">Processing</span> - Order is being prepared</li>
                        <li><span class="badge bg-success">Delivered</span> - Order completed and delivered</li>
                        <li><span class="badge bg-danger">Cancelled</span> - Order has been cancelled</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-gift text-success"></i>
                        Loyalty Program
                    </h5>
                    <p class="card-text">
                        Earn 1 point for every ₱100 spent on orders.<br>
                        Redeem points for discounts on future orders!
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-clock text-warning"></i>
                        Delivery Times
                    </h5>
                    <p class="card-text">
                        Orders are typically delivered within 24 hours.<br>
                        You can track your order status here.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Order Modal -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this order? This action cannot be undone.</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Note: Only pending orders can be cancelled.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep Order</button>
                <button type="button" class="btn btn-danger" onclick="cancelOrder()">Yes, Cancel Order</button>
            </div>
        </div>
    </div>
</div>

<script>
let orderToCancelId = null;

function confirmCancelOrder(orderId) {
    orderToCancelId = orderId;
    new bootstrap.Modal(document.getElementById('cancelOrderModal')).show();
}

function cancelOrder() {
    if (!orderToCancelId) return;

    // Send cancel request
    fetch('cancel_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'order_id=' + orderToCancelId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('cancelOrderModal')).hide();
            
            // Show success message using SweetAlert or similar
            alert(data.message);
            
            // Reload page to reflect changes
            window.location.reload();
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

// Initialize DataTables
$(document).ready(function() {
    $('#ordersTable').DataTable({
        order: [[0, 'desc']], // Sort by order ID descending
        pageLength: 10,
        language: {
            emptyTable: "No orders found"
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?> 