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

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Process cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_started = false;
    try {
        if (isset($_POST['update_cart'])) {
            foreach ($_POST['quantity'] as $product_id => $quantity) {
                if ($quantity > 0) {
                    $_SESSION['cart'][$product_id]['quantity'] = (int)$quantity;
                } else {
                    unset($_SESSION['cart'][$product_id]);
                }
            }
            $success = "Cart updated successfully!";
            
        } else if (isset($_POST['remove_item'])) {
            $product_id = (int)$_POST['remove_item'];
            unset($_SESSION['cart'][$product_id]);
            $success = "Item removed from cart!";
            
        } else if (isset($_POST['checkout'])) {
            // Validate stock availability
            foreach ($_SESSION['cart'] as $product_id => $item) {
                $stmt = $conn->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND branch_id = ?");
                $stmt->bind_param("ii", $product_id, $user['branch_id']);
                $stmt->execute();
                $stock = $stmt->get_result()->fetch_assoc();

                if (!$stock || $stock['quantity'] < $item['quantity']) {
                    throw new Exception("Some items are no longer in stock. Please review your cart.");
                }
            }

            if (empty($_POST['delivery_date']) || empty($_POST['delivery_time'])) {
                throw new Exception("Please select delivery date and time.");
            }

            // Start transaction
            $conn->begin_transaction();
            $transaction_started = true;

            // Calculate total amount
            $total_amount = 0;
            foreach ($_SESSION['cart'] as $product_id => $item) {
                $stmt = $conn->prepare("SELECT price FROM products WHERE product_id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                $total_amount += $product['price'] * $item['quantity'];
            }

            // Create order and capture household inputs
            $delivery_datetime = date('Y-m-d H:i:s', strtotime($_POST['delivery_date'] . ' ' . $_POST['delivery_time']));
            $notes = sanitize($_POST['notes']);

            // Capture household inputs
            $household_id_number = isset($_POST['household_id_number']) ? sanitize($_POST['household_id_number']) : '';
            $household_surname = isset($_POST['household_surname']) ? sanitize($_POST['household_surname']) : '';

            // Ensure users table has household columns (add if missing)
            try {
                $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'household_id_number'");
                if ($colCheck->num_rows == 0) {
                    $conn->query("ALTER TABLE users ADD COLUMN household_id_number VARCHAR(100) NULL");
                }
                $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'household_surname'");
                if ($colCheck->num_rows == 0) {
                    $conn->query("ALTER TABLE users ADD COLUMN household_surname VARCHAR(100) NULL");
                }
            } catch (Exception $e) {
                // ignore alter errors; columns may already exist or permission not granted
            }

            // Update user's household info so customer table can show it
            if ($household_id_number !== '' || $household_surname !== '') {
                $upd = $conn->prepare("UPDATE users SET household_id_number = ?, household_surname = ? WHERE user_id = ?");
                $upd->bind_param("ssi", $household_id_number, $household_surname, $_SESSION['user_id']);
                $upd->execute();
            }

            // Build delivery address in requested format: household id number, household surname, sitio, barangay
            $delivery_address_parts = [];
            if ($household_id_number) $delivery_address_parts[] = $household_id_number;
            if ($household_surname) $delivery_address_parts[] = $household_surname;
            if (!empty($user['sitio_purok'])) $delivery_address_parts[] = $user['sitio_purok'];
            if (!empty($user['barangay_name'])) $delivery_address_parts[] = $user['barangay_name'];
            $delivery_address = implode(', ', $delivery_address_parts);

            $stmt = $conn->prepare("INSERT INTO orders (
                    customer_id,
                    branch_id,
                    order_date,
                    delivery_date,
                    total_amount,
                    status,
                    sitio_purok,
                    delivery_address,
                    notes
                ) VALUES (?, ?, NOW(), ?, ?, 'pending', ?, ?, ?)");

            $stmt->bind_param("iisdsss",
                $_SESSION['user_id'],
                $user['branch_id'],
                $delivery_datetime,
                $total_amount,
                $user['sitio_purok'],
                $delivery_address,
                $notes
            );

            $stmt->execute();
            $order_id = $stmt->insert_id;

            // Create order details and update inventory
            foreach ($_SESSION['cart'] as $product_id => $item) {
                // Get product price
                $stmt = $conn->prepare("SELECT price FROM products WHERE product_id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();

                // Create order detail
                $stmt = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiid", $order_id, $product_id, $item['quantity'], $product['price']);
                $stmt->execute();

                // Update inventory
                $stmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND branch_id = ?");
                $stmt->bind_param("iii", $item['quantity'], $product_id, $user['branch_id']);
                $stmt->execute();
            }
            
            // Add loyalty points (1 point per 100 pesos spent)
            $points = floor($total_amount / 100);
            if ($points > 0) {
                $stmt = $conn->prepare("
                    UPDATE loyalty 
                    SET points = points + ? 
                    WHERE customer_id = ?
                ");
                $stmt->bind_param("ii", $points, $_SESSION['user_id']);
                $stmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            $transaction_started = false;
            
            // Clear cart
            $_SESSION['cart'] = [];
            
            // Log activity
            logActivity($_SESSION['user_id'], 'order_placed', "Order #$order_id placed");
            
            // Redirect to order confirmation
            redirectWith('order_confirmation.php?id=' . $order_id, 'Order placed successfully!');
        }
        
    } catch (Exception $e) {
        if ($transaction_started) {
            $conn->rollback();
        }
        $error = $e->getMessage();
    }
}

// Get cart items details
$cart_items = [];
$total_amount = 0;

if (!empty($_SESSION['cart'])) {
    $product_ids = array_keys($_SESSION['cart']);
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    
    $stmt = $conn->prepare("
        SELECT p.*, i.quantity as stock_quantity
        FROM products p
        LEFT JOIN inventory i ON p.product_id = i.product_id AND i.branch_id = ?
        WHERE p.product_id IN ($placeholders)
    ");
    
    $params = array_merge([$user['branch_id']], $product_ids);
    $types = str_repeat('i', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($products as $product) {
        $quantity = $_SESSION['cart'][$product['product_id']]['quantity'];
        $subtotal = $product['price'] * $quantity;
        $total_amount += $subtotal;
        
        $cart_items[] = [
            'product' => $product,
            'quantity' => $quantity,
            'subtotal' => $subtotal
        ];
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title">Shopping Cart</h2>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (empty($cart_items)): ?>
                        <p class="text-muted">Your cart is empty.</p>
                        <a href="products.php" class="btn btn-primary">Browse Products</a>
                    <?php else: ?>
                        <form method="POST">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Subtotal</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cart_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($item['product']['product_name']); ?>
                                                    <?php if ($item['quantity'] > $item['product']['stock_quantity']): ?>
                                                        <div class="text-danger">
                                                            <small>Only <?php echo $item['product']['stock_quantity']; ?> available</small>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>₱<?php echo number_format($item['product']['price'], 2); ?></td>
                                                <td>
                                                    <input type="number" 
                                                           name="quantity[<?php echo $item['product']['product_id']; ?>]" 
                                                           value="<?php echo $item['quantity']; ?>"
                                                           min="0"
                                                           max="<?php echo $item['product']['stock_quantity']; ?>"
                                                           class="form-control form-control-sm"
                                                           style="width: 80px;">
                                                </td>
                                                <td>₱<?php echo number_format($item['subtotal'], 2); ?></td>
                                                <td>
                                                    <button type="submit" 
                                                            name="remove_item" 
                                                            value="<?php echo $item['product']['product_id']; ?>" 
                                                            class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                            <td colspan="2"><strong>₱<?php echo number_format($total_amount, 2); ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="submit" name="update_cart" class="btn btn-secondary">
                                    <i class="fas fa-sync-alt"></i> Update Cart
                                </button>
                                <a href="products.php" class="btn btn-primary">
                                    <i class="fas fa-shopping-basket"></i> Continue Shopping
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($cart_items)): ?>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Checkout</h3>
                        <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Household ID Number</label>
                                        <input type="text" name="household_id_number" class="form-control"
                                               value="<?php echo htmlspecialchars($user['household_id_number'] ?? ''); ?>"
                                               placeholder="e.g. HH-12345" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Household Surname</label>
                                        <input type="text" name="household_surname" class="form-control"
                                               value="<?php echo htmlspecialchars($user['household_surname'] ?? ''); ?>"
                                               placeholder="Family surname" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Delivery Address Preview</label>
                                        <p class="form-control-static" id="delivery_address_preview">
                                            <?php
                                                $preview_household_id = $user['household_id_number'] ?? '';
                                                $preview_household_surname = $user['household_surname'] ?? '';
                                                $preview = trim(($preview_household_id ? $preview_household_id . ', ' : '') .
                                                               ($preview_household_surname ? $preview_household_surname . ', ' : '') .
                                                               ($user['sitio_purok'] ? $user['sitio_purok'] . ', ' : '') .
                                                               ($user['barangay_name'] ? $user['barangay_name'] : ''));
                                                echo htmlspecialchars($preview);
                                            ?>
                                        </p>
                                    </div>
                            
                            <div class="mb-3">
                                <label for="delivery_date" class="form-label">Delivery Date</label>
                                <input type="date" 
                                       class="form-control" 
                                       id="delivery_date" 
                                       name="delivery_date"
                                       min="<?php echo date('Y-m-d'); ?>"
                                       required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="delivery_time" class="form-label">Delivery Time</label>
                                <input type="time"
                                       class="form-control"
                                       id="delivery_time"
                                       name="delivery_time"
                                       min="08:00"
                                       max="20:00"
                                       required>
                                <div class="form-text">Delivery hours: 8:00 AM - 8:00 PM</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Delivery Notes</label>
                                <textarea class="form-control" 
                                          id="notes" 
                                          name="notes" 
                                          rows="3"
                                          placeholder="Special instructions for delivery"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <h4>Order Summary</h4>
                                <table class="table table-sm">
                                    <tr>
                                        <td>Subtotal:</td>
                                        <td class="text-end">₱<?php echo number_format($total_amount, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Points to Earn:</td>
                                        <td class="text-end"><?php echo floor($total_amount / 100); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total:</strong></td>
                                        <td class="text-end"><strong>₱<?php echo number_format($total_amount, 2); ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="checkout" class="btn btn-success btn-lg">
                                    <i class="fas fa-check"></i> Place Order
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set min delivery date to today
    const deliveryDate = document.getElementById('delivery_date');
    if (deliveryDate) {
        deliveryDate.min = new Date().toISOString().split('T')[0];
    }
    
    // Form validation
    const checkoutForm = document.querySelector('form[name="checkout"]');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(event) {
            const deliveryTime = document.getElementById('delivery_time').value;
            const [hours, minutes] = deliveryTime.split(':').map(Number);

            if (hours < 8 || (hours === 20 && minutes > 0) || hours > 20) {
                event.preventDefault();
                alert('Please select a delivery time between 8:00 AM and 8:00 PM');
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?> 