<?php
require_once '../includes/functions.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !hasRole('customer')) {
    header("Location: /login.php");
    exit();
}

require_once '../includes/db.php';

// Validate order ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectWith('dashboard.php', 'Invalid order ID', 'danger');
}

$order_id = (int)$_GET['id'];

// Get order details with branch name
$stmt = $conn->prepare("
    SELECT o.*, 
           b.branch_name,
           GROUP_CONCAT(
               CONCAT(od.quantity, 'x ', p.product_name, ' (₱', FORMAT(od.price, 2), ')')
               SEPARATOR '\n'
           ) as items,
           GROUP_CONCAT(
               CONCAT(od.quantity, ' x ', p.product_name, ' @ ₱', FORMAT(od.price, 2), ' = ₱', FORMAT(od.quantity * od.price, 2))
               SEPARATOR '\n'
           ) as items_detailed
    FROM orders o
    JOIN order_details od ON o.order_id = od.order_id
    JOIN products p ON od.product_id = p.product_id
    JOIN branches b ON o.branch_id = b.branch_id
    WHERE o.order_id = ? AND o.customer_id = ?
    GROUP BY o.order_id
");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    redirectWith('dashboard.php', 'Order not found', 'danger');
}

require_once '../includes/header.php';
?>

<style>
.receipt {
    background: white;
    padding: 20px;
    max-width: 400px;
    margin: 0 auto;
    font-family: 'Courier New', monospace;
}

.receipt-header {
    text-align: center;
    margin-bottom: 20px;
    border-bottom: 1px dashed #000;
    padding-bottom: 10px;
}

.receipt-title {
    font-size: 1.2em;
    font-weight: bold;
    margin: 5px 0;
}

.receipt-content {
    margin-bottom: 20px;
}

.receipt-row {
    display: flex;
    justify-content: space-between;
    margin: 5px 0;
}

.receipt-footer {
    text-align: center;
    border-top: 1px dashed #000;
    padding-top: 10px;
    margin-top: 20px;
}

.receipt-items {
    margin: 15px 0;
    white-space: pre-line;
}

.download-btn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
}

@media print {
    .no-print {
        display: none !important;
    }
}
</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        <h2 class="mt-3">Order Confirmed!</h2>
                        <p class="text-muted">Thank you for your order. We'll process it right away!</p>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-sm-6">
                            <h5>Order Details</h5>
                            <p class="mb-1">Order #: <?php echo $order['order_id']; ?></p>
                            <p class="mb-1">Date: <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></p>
                            <p class="mb-1">Status: 
                                <span class="badge bg-<?php 
                                    echo match($order['status']) {
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'delivered' => 'success',
                                        'cancelled' => 'danger',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </p>
                        </div>
                        
                        <div class="col-sm-6">
                            <h5>Delivery Information</h5>
                            <p class="mb-1">
                                Date: <?php echo date('M d, Y', strtotime($order['delivery_date'])); ?>
                            </p>
                            <p class="mb-1">
                                Time: <?php echo date('h:i A', strtotime($order['delivery_date'])); ?>
                            </p>
                            <p class="mb-1">
                                Address: <?php echo htmlspecialchars($order['delivery_address']); ?>
                            </p>
                            <?php if ($order['notes']): ?>
                                <p class="mb-1">
                                    Notes: <?php echo htmlspecialchars($order['notes']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h5>Order Summary</h5>
                    <div class="card mb-4">
                        <div class="card-body">
                            <pre class="mb-0"><?php echo htmlspecialchars($order['items']); ?></pre>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-sm-6">
                            <h5>Payment</h5>
                            <p class="mb-1">Total Amount: ₱<?php echo number_format($order['total_amount'], 2); ?></p>
                            <p class="mb-1">Points Earned: <?php echo floor($order['total_amount'] / 100); ?></p>
                            <p class="mb-0">Payment Method: Cash on Delivery</p>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <a href="orders.php" class="btn btn-primary me-2">
                            <i class="fas fa-list"></i> View All Orders
                        </a>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Receipt for download -->
            <div class="card mt-4">
                <div class="card-body">
                    <div id="receipt" class="receipt">
                        <div class="receipt-header">
                            <div class="receipt-title">WATER REFILLING STATION</div>
                            <div><?php echo htmlspecialchars($order['branch_name']); ?> Branch</div>
                            <div>Official Receipt</div>
                        </div>
                        
                        <div class="receipt-content">
                            <div class="receipt-row">
                                <span>Order #:</span>
                                <span><?php echo str_pad($order['order_id'], 8, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="receipt-row">
                                <span>Date:</span>
                                <span><?php echo date('Y-m-d h:i A', strtotime($order['order_date'])); ?></span>
                            </div>
                            <div class="receipt-row">
                                <span>Status:</span>
                                <span><?php echo ucfirst($order['status']); ?></span>
                            </div>
                            
                            <div style="margin: 15px 0; border-top: 1px dashed #000;"></div>
                            
                            <div class="receipt-items">
<?php echo htmlspecialchars($order['items_detailed']); ?>
                            </div>
                            
                            <div style="margin: 15px 0; border-top: 1px dashed #000;"></div>
                            
                            <div class="receipt-row">
                                <strong>Total Amount:</strong>
                                <strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong>
                            </div>
                            <div class="receipt-row">
                                <span>Points Earned:</span>
                                <span><?php echo floor($order['total_amount'] / 100); ?> pts</span>
                            </div>
                        </div>
                        
                        <div class="receipt-footer">
                            <div>Thank you for your order!</div>
                            <div>Please keep this receipt</div>
                            <div style="margin-top: 10px;">
                                <?php echo date('Y-m-d h:i:s A'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Download button -->
            <button class="btn btn-primary download-btn no-print" onclick="downloadReceipt()">
                <i class="fas fa-download"></i> Download Receipt
            </button>
            
            <!-- Additional Information -->
            <div class="card mt-4 no-print">
                <div class="card-body">
                    <h5 class="card-title">What's Next?</h5>
                    <ol class="mb-0">
                        <li>We'll process your order and prepare it for delivery.</li>
                        <li>You'll receive updates about your order status.</li>
                        <li>Our delivery personnel will contact you before delivery.</li>
                        <li>Please prepare the exact amount for cash on delivery.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add html2canvas library -->
<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

<script>
function downloadReceipt() {
    const receipt = document.getElementById('receipt');
    const downloadBtn = document.querySelector('.download-btn');
    
    // Temporarily hide the download button
    downloadBtn.style.display = 'none';
    
    html2canvas(receipt, {
        scale: 2, // Higher quality
        backgroundColor: '#ffffff',
        logging: false,
        useCORS: true
    }).then(canvas => {
        // Show the download button again
        downloadBtn.style.display = 'block';
        
        // Create download link
        const link = document.createElement('a');
        link.download = 'receipt-<?php echo str_pad($order['order_id'], 8, '0', STR_PAD_LEFT); ?>.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }).catch(err => {
        console.error('Error generating receipt:', err);
        alert('Error generating receipt. Please try again.');
        downloadBtn.style.display = 'block';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?> 