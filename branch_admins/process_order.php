<?php
require_once '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and is a branch admin
if (!isLoggedIn() || !hasRole('branch_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();
$user = getUserInfo($_SESSION['user_id']);
$branch_id = $user['branch_id'];

// Process order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'update_status':
                $order_id = (int)$_POST['order_id'];
                $new_status = $_POST['status'];
                $notes = trim($_POST['notes'] ?? '');

                // Validate order belongs to this branch
                $stmt = $conn->prepare("
                    SELECT o.*, u.name as customer_name, u.contact_number 
                    FROM orders o
                    JOIN users u ON o.customer_id = u.user_id
                    WHERE o.order_id = ? AND o.branch_id = ?
                ");
                $stmt->bind_param("ii", $order_id, $branch_id);
                $stmt->execute();
                $order = $stmt->get_result()->fetch_assoc();

                if (!$order) {
                    throw new Exception("Order not found or unauthorized access");
                }

                // Update order status
                $stmt = $conn->prepare("
                    UPDATE orders 
                    SET status = ?, notes = CONCAT(IFNULL(notes, ''), '\n[', NOW(), '] Status updated to ', ?, '. Notes: ', ?)
                    WHERE order_id = ?
                ");
                $stmt->bind_param("sssi", $new_status, $new_status, $notes, $order_id);
                
                if ($stmt->execute()) {
                    // Log the activity
                    logActivity($user['user_id'], 'order_status_update', "Updated order #$order_id status to $new_status");
                    
                    // Send notification to customer (you can implement SMS/email notification here)
                    
                    $success = "Order #$order_id status updated successfully";
                } else {
                    throw new Exception("Failed to update order status");
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get orders for this branch with customer details
$query = "
    SELECT
        o.*,
        COALESCE(u.name, 'Unknown Customer') as customer_name,
        COALESCE(u.contact_number, 'Not provided') as contact_number,
        COALESCE(u.email, 'Not provided') as email,
        COUNT(od.order_detail_id) as total_items,
        COALESCE(GROUP_CONCAT(
            CONCAT(
                CASE
                    WHEN p.product_name IS NOT NULL THEN p.product_name
                    WHEN od.product_id = 1 THEN 'Water Refill'
                    WHEN od.product_id = 2 THEN 'Dispenser Rental'
                    WHEN od.product_id = 3 THEN 'Delivery Charge'
                    ELSE 'Unknown Item'
                END,
                ' (', od.quantity, ')'
            )
            SEPARATOR ', '
        ), 'No items') as items_list,
        COALESCE(o.delivery_address, 'Not specified') as delivery_address,
        COALESCE(o.notes, '') as notes
    FROM orders o
    JOIN users u ON o.customer_id = u.user_id
    LEFT JOIN order_details od ON o.order_id = od.order_id
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
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get order statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed_orders,
        COALESCE(SUM(total_amount), 0) as total_revenue
    FROM orders
    WHERE branch_id = ? AND status != 'cancelled'
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Process Orders</h1>
        <!--button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> Print Orders
        </button-->
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Orders</h6>
                    <h2 class="mb-0"><?= $stats['total_orders'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Pending Orders</h6>
                    <h2 class="mb-0"><?= $stats['pending_orders'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Processing</h6>
                    <h2 class="mb-0"><?= $stats['processing_orders'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Revenue</h6>
                    <h2 class="mb-0">₱<?= number_format($stats['total_revenue'] ?? 0, 2) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="ordersTable">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Delivery Info</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                #<?= $order['order_id'] ?><br>
                                <small class="text-muted">
                                    <?= date('M d, Y h:ia', strtotime($order['order_date'])) ?>
                                </small>
                            </td>
                            <td>
                                <?= htmlspecialchars($order['customer_name'] ?? 'Unknown Customer') ?><br>
                                <small class="text-muted">
                                    <?= htmlspecialchars($order['contact_number'] ?? 'Not provided') ?><br>
                                    <?= htmlspecialchars($order['email'] ?? 'Not provided') ?>
                                </small>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($order['items_list'] ?? 'No items') ?></small>
                            </td>
                            <td>₱<?= number_format($order['total_amount'] ?? 0, 2) ?></td>
                            <td>
                                <strong>Address:</strong><br>
                                <?= htmlspecialchars($order['delivery_address'] ?? 'Not specified') ?><br>
                                <strong>Delivery Date:</strong><br>
                                <?= date('M d, Y h:ia', strtotime($order['delivery_date'])) ?>
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
                                ?>
                                <span class="badge <?= $status_class ?>">
                                    <?= ucfirst($order['status'] ?? 'unknown') ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" 
                                        class="btn btn-sm btn-primary"
                                        onclick="updateOrderStatus(<?= htmlspecialchars(json_encode($order)) ?>)">
                                    <i class="fas fa-edit"></i> Update
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Update Order Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Order Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_order.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" id="update_order_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Order Details</label>
                        <div id="order_details" class="form-text"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control" name="status" id="status" required>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize DataTables
$(document).ready(function() {
    $('#ordersTable').DataTable({
        order: [[0, 'desc']], // Sort by order ID descending
        pageLength: 25
    });
});

// Function to update order status
function updateOrderStatus(order) {
    $('#update_order_id').val(order.order_id);
    $('#status').val(order.status);
    
    // Update order details in modal
    const details = `
        <strong>Order #${order.order_id}</strong><br>
        Customer: ${order.customer_name}<br>
        Contact: ${order.contact_number}<br>
        Items: ${order.items_list}<br>
        Total: ₱${parseFloat(order.total_amount).toFixed(2)}
    `;
    $('#order_details').html(details);
    
    // Show modal
    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?> 