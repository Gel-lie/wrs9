<?php
require_once '../includes/functions.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !hasRole('customer')) {
    header("Location: /login.php");
    exit();
}

require_once '../includes/db.php';

// Get user's information
$user = getUserInfo($_SESSION['user_id']);

// Get user's loyalty information
$stmt = $conn->prepare("
    SELECT * FROM loyalty 
    WHERE customer_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$loyalty = $stmt->get_result()->fetch_assoc();

if (!$loyalty) {
    // Create loyalty record if it doesn't exist
    $stmt = $conn->prepare("
        INSERT INTO loyalty (customer_id, points, reward_level) 
        VALUES (?, 0, 'Bronze')
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    
    $loyalty = [
        'points' => 0,
        'reward_level' => 'Bronze'
    ];
}

// Get recent orders with more details
$stmt = $conn->prepare("
    SELECT
        o.order_id,
        o.order_date,
        o.delivery_date,
        o.total_amount,
        o.status,
        o.notes,
        o.delivery_address,
        b.branch_name,
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
        ) as items,
        GROUP_CONCAT(
            CONCAT(
                CASE
                    WHEN p.product_name IS NOT NULL THEN p.product_name
                    WHEN od.product_id = 1 THEN 'Water Refill'
                    WHEN od.product_id = 2 THEN 'Dispenser Rental'
                    WHEN od.product_id = 3 THEN 'Delivery Charge'
                    ELSE 'Unknown Item'
                END, ':',
                od.quantity, ':',
                od.price
            ) SEPARATOR '|'
        ) as items_json
    FROM orders o
    JOIN order_details od ON o.order_id = od.order_id
    LEFT JOIN products p ON od.product_id = p.product_id
    JOIN branches b ON o.branch_id = b.branch_id
    WHERE o.customer_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
    LIMIT 10
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get upcoming deliveries
$stmt = $conn->prepare("
    SELECT
        o.order_id,
        o.delivery_date,
        o.total_amount,
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
                END
            ) SEPARATOR ', '
        ) as items
    FROM orders o
    JOIN order_details od ON o.order_id = od.order_id
    LEFT JOIN products p ON od.product_id = p.product_id
    WHERE o.customer_id = ?
    AND o.status IN ('pending', 'processing')
    AND o.delivery_date >= NOW()
    GROUP BY o.order_id
    ORDER BY o.delivery_date ASC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$upcoming_deliveries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="container py-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3">Welcome, <?php echo htmlspecialchars($user['name'] ?? ''); ?>!</h1>
            <p class="text-muted">
                Your Loyalty Level: 
                <span class="badge bg-primary"><?php echo htmlspecialchars($loyalty['reward_level'] ?? 'Bronze'); ?></span>
                Points: <strong><?php echo number_format($loyalty['points'] ?? 0); ?></strong>
            </p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="refill_request.php" class="btn btn-primary me-2">
                <i class="fas fa-plus"></i> New Refill Request
            </a>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-water fa-2x text-primary mb-2"></i>
                    <h5 class="card-title">Refill Request</h5>
                    <a href="refill_request.php" class="btn btn-sm btn-outline-primary">Order Now</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-shopping-cart fa-2x text-info mb-2"></i>
                    <h5 class="card-title">Shop Products</h5>
                    <a href="products.php" class="btn btn-sm btn-outline-info">Shop Now</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-star fa-2x text-warning mb-2"></i>
                    <h5 class="card-title">Rewards</h5>
                    <a href="rewards.php" class="btn btn-sm btn-outline-warning">View Points</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Deliveries -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-truck me-2"></i>Upcoming Deliveries
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_deliveries)): ?>
                        <p class="text-muted mb-0">No upcoming deliveries scheduled.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Delivery Date</th>
                                        <th>Items</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_deliveries as $delivery): ?>
                                        <tr>
                                            <td>#<?php echo $delivery['order_id']; ?></td>
                                            <td>
                                                <?php echo date('M d, Y h:i A', strtotime($delivery['delivery_date'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($delivery['items'] ?? 'No items'); ?></td>
                                            <td>₱<?php echo number_format($delivery['total_amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2"></i>Recent Orders
                        </h5>
                        <a href="orders.php" class="btn btn-sm btn-light">View All Orders</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_orders)): ?>
                        <p class="text-muted mb-0">No orders found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['order_id']; ?></td>
                                            <td>
                                                <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?>
                                            </td>
                                            <td><?php echo $order['items']; ?></td>
                                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <?php
                                                $status_class = match($order['status']) {
                                                    'pending' => 'bg-warning',
                                                    'processing' => 'bg-info',
                                                    'delivered' => 'bg-success',
                                                    'cancelled' => 'bg-danger',
                                                    default => 'bg-secondary'
                                                };
                                                ?>
                                                <span class="badge <?= $status_class ?>">
                                                    <?= ucfirst($order['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" 
                                                        class="btn btn-sm btn-primary"
                                                        onclick="viewOrder(<?= htmlspecialchars(json_encode($order)) ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if ($order['status'] === 'pending'): ?>
                                                    <button type="button"
                                                            class="btn btn-sm btn-danger"
                                                            onclick="confirmCancelOrder(<?= $order['order_id'] ?>)">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
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
    </div>
</div>

<!-- View Order Modal -->
<div class="modal fade" id="viewOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Order Information</h6>
                        <p>
                            <strong>Order ID:</strong> <span id="modal_order_id"></span><br>
                            <strong>Date:</strong> <span id="modal_order_date"></span><br>
                            <strong>Status:</strong> <span id="modal_status"></span><br>
                            <strong>Branch:</strong> <span id="modal_branch"></span><br>
                            <strong>Container Type:</strong> <span id="modal_container_type"></span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>Delivery Information</h6>
                        <p>
                            <strong>Delivery Date:</strong> <span id="modal_delivery_date"></span><br>
                            <strong>Address:</strong> <span id="modal_address"></span>
                        </p>
                    </div>
                </div>
                
                <h6>Order Items</h6>
                <div class="table-responsive">
                    <table class="table table-sm" id="modal_items_table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <th id="modal_total"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-3">
                    <h6>Notes</h6>
                    <p id="modal_notes" class="text-muted"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
            
            // Show success message
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

function viewOrder(order) {
    // Update modal content
    document.getElementById('modal_order_id').textContent = '#' + order.order_id;
    document.getElementById('modal_order_date').textContent = new Date(order.order_date).toLocaleString();
    document.getElementById('modal_delivery_date').textContent = new Date(order.delivery_date).toLocaleString();
    document.getElementById('modal_address').textContent = order.delivery_address;
    document.getElementById('modal_branch').textContent = order.branch_name;

    // Extract container type from notes
    const notes = order.notes || '';
    const containerMatch = notes.match(/Container:\s*(.+?)(?:\n|$)/);
    const containerType = containerMatch ? containerMatch[1] : 'N/A';
    document.getElementById('modal_container_type').textContent = containerType;

    // Remove container type from notes display
    const cleanNotes = notes.replace(/Container:\s*.+?(?:\n|$)/, '').trim();
    document.getElementById('modal_notes').textContent = cleanNotes || 'No additional notes';
    
    // Update status with badge
    const statusClass = {
        'pending': 'bg-warning',
        'processing': 'bg-info',
        'delivered': 'bg-success',
        'cancelled': 'bg-danger'
    }[order.status] || 'bg-secondary';
    
    document.getElementById('modal_status').innerHTML = 
        `<span class="badge ${statusClass}">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span>`;
    
    // Parse items and update table
    const itemsTable = document.getElementById('modal_items_table').getElementsByTagName('tbody')[0];
    itemsTable.innerHTML = '';
    
    const items = order.items_json.split('|').map(item => {
        const [name, quantity, price] = item.split(':');
        return { name, quantity: parseInt(quantity), price: parseFloat(price) };
    });
    
    items.forEach(item => {
        const row = itemsTable.insertRow();
        row.innerHTML = `
            <td>${item.name}</td>
            <td>${item.quantity}</td>
            <td>₱${item.price.toFixed(2)}</td>
            <td>₱${(item.quantity * item.price).toFixed(2)}</td>
        `;
    });
    
    document.getElementById('modal_total').textContent = `₱${parseFloat(order.total_amount).toFixed(2)}`;
    
    // Show modal
    new bootstrap.Modal(document.getElementById('viewOrderModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?> 