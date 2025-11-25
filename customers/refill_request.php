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

// Define container types and prices
$container_types = [
    'round_5g' => [
        'name' => 'Round (5 gallons)',
        'price' => 30,
        'capacity' => 5,
        'unit' => 'gallons'
    ],
    'slim_5g' => [
        'name' => 'Slim (5 gallons)',
        'price' => 30,
        'capacity' => 5,
        'unit' => 'gallons'
    ],
    '10l' => [
        'name' => '10 liters',
        'price' => 20,
        'capacity' => 10,
        'unit' => 'liters'
    ],
    '8l' => [
        'name' => '8 liters',
        'price' => 16,
        'capacity' => 8,
        'unit' => 'liters'
    ],
    '7l' => [
        'name' => '7 liters',
        'price' => 14,
        'capacity' => 7,
        'unit' => 'liters'
    ],
    '6.6l' => [
        'name' => '6.6 liters',
        'price' => 13,
        'capacity' => 6.6,
        'unit' => 'liters'
    ],
    '6l' => [
        'name' => '6 liters',
        'price' => 12,
        'capacity' => 6,
        'unit' => 'liters'
    ],
    '5l' => [
        'name' => '5 liters',
        'price' => 10,
        'capacity' => 5,
        'unit' => 'liters'
    ]
];

// Rental options
$rental_options = [
    'dispenser' => [
        'name' => 'Water Dispenser',
        'price' => 200,
        'unit' => 'per day'
    ]
];

// Delivery charge
$delivery_charge = 5;

// Process refill request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_POST['container_type'])) {
            throw new Exception("Please select a container type");
        }
        if (!isset($_POST['quantity']) || $_POST['quantity'] < 1) {
            throw new Exception("Please enter a valid quantity");
        }
        if (empty($_POST['delivery_date'])) {
            throw new Exception("Please select a delivery date");
        }
        if (empty($_POST['delivery_time'])) {
            throw new Exception("Please select a delivery time");
        }

        $container_type = $_POST['container_type'];
        $quantity = (int)$_POST['quantity'];
        $delivery_date = $_POST['delivery_date'];
        $delivery_time = $_POST['delivery_time'];
        $need_dispenser = isset($_POST['need_dispenser']) ? 1 : 0;
        $rental_days = $need_dispenser ? ((int)$_POST['rental_days'] ?: 1) : 0;
        $notes = sanitize($_POST['notes'] ?? '');

        // Validate container type
        if (!isset($container_types[$container_type])) {
            throw new Exception("Invalid container type selected");
        }

        // Calculate total amount
        $container_price = $container_types[$container_type]['price'];
        $refill_amount = $container_price * $quantity;
        $rental_amount = $need_dispenser ? ($rental_options['dispenser']['price'] * $rental_days) : 0;
        $total_amount = $refill_amount + $rental_amount + $delivery_charge;

        // Create delivery datetime
        $delivery_datetime = DateTime::createFromFormat('Y-m-d H:i', "$delivery_date $delivery_time");
        if (!$delivery_datetime) {
            throw new Exception("Invalid delivery date/time format");
        }

        // Validate if delivery is in the future
        $now = new DateTime();
        if ($delivery_datetime <= $now) {
            throw new Exception("Delivery date and time must be in the future");
        }

        // Add container type to notes
        $container_name = $container_types[$container_type]['name'];
        $notes = "Container: $container_name\n" . $notes;

        // Start transaction
        $conn->begin_transaction();

        try {
            // Create order
            $stmt = $conn->prepare("
                INSERT INTO orders (
                    customer_id,
                    branch_id,
                    order_date,
                    total_amount,
                    delivery_date,
                    status,
                    notes
                ) VALUES (?, ?, NOW(), ?, ?, 'pending', ?)
            ");

            $mysql_delivery_datetime = $delivery_datetime->format('Y-m-d H:i:s');
            $stmt->bind_param("iidss",
                $_SESSION['user_id'],
                $user['branch_id'],
                $total_amount,
                $mysql_delivery_datetime,
                $notes
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create order: " . $stmt->error);
            }

            $order_id = $conn->insert_id;

            // Add order details
            $stmt = $conn->prepare("
                INSERT INTO order_details (
                    order_id,
                    product_id,
                    quantity,
                    price
                ) VALUES (?, ?, ?, ?)
            ");

            // Add refill containers to order details
            $refill_product_id = 1; // Assuming ID 1 is for refill service
            $stmt->bind_param("iiid", $order_id, $refill_product_id, $quantity, $container_price);
            if (!$stmt->execute()) {
                throw new Exception("Failed to add refill details: " . $stmt->error);
            }

            // Add dispenser rental if requested
            if ($need_dispenser) {
                $dispenser_product_id = 2; // Assuming ID 2 is for dispenser rental
                $dispenser_quantity = 1;
                $dispenser_price = $rental_options['dispenser']['price'] * $rental_days;
                $stmt->bind_param("iiid", $order_id, $dispenser_product_id, $dispenser_quantity, $dispenser_price);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to add dispenser rental details: " . $stmt->error);
                }
            }

            // Add delivery charge
            $delivery_product_id = 3; // Assuming ID 3 is for delivery service
            $delivery_quantity = 1;
            $stmt->bind_param("iiid", $order_id, $delivery_product_id, $delivery_quantity, $delivery_charge);
            if (!$stmt->execute()) {
                throw new Exception("Failed to add delivery charge details: " . $stmt->error);
            }

            // Calculate and update loyalty points (1 point per 100 pesos)
            $points_earned = floor($total_amount / 100);
            if ($points_earned > 0) {
                $stmt = $conn->prepare("
                    UPDATE loyalty 
                    SET points = points + ? 
                    WHERE customer_id = ?
                ");
                $stmt->bind_param("ii", $points_earned, $_SESSION['user_id']);
                $stmt->execute();
            }

            $conn->commit();
            $success = "Your refill request has been submitted successfully! Order ID: #$order_id";
            
            // Log activity
            logActivity($_SESSION['user_id'], 'refill_requested', "Refill order #$order_id created for " . $container_types[$container_type]['name']);

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title mb-4">Water Refill Request</h2>

                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <!-- Container Type Selection -->
                        <div class="mb-3">
                            <label for="container_type" class="form-label">Container Type</label>
                            <select class="form-select" id="container_type" name="container_type" required>
                                <option value="">Choose container type...</option>
                                <?php foreach ($container_types as $key => $container): ?>
                                    <option value="<?php echo $key; ?>" 
                                            data-price="<?php echo $container['price']; ?>"
                                            <?php echo isset($_POST['container_type']) && $_POST['container_type'] === $key ? 'selected' : ''; ?>>
                                        <?php echo $container['name']; ?> - ₱<?php echo number_format($container['price'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a container type</div>
                        </div>

                        <!-- Quantity -->
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="quantity" 
                                   name="quantity" 
                                   min="1" 
                                   value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : '1'; ?>"
                                   required>
                            <div class="invalid-feedback">Please enter a valid quantity</div>
                        </div>

                        <!-- Dispenser Rental -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" 
                                       class="form-check-input" 
                                       id="need_dispenser" 
                                       name="need_dispenser"
                                       <?php echo isset($_POST['need_dispenser']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="need_dispenser">
                                    Need water dispenser? (₱<?php echo $rental_options['dispenser']['price']; ?> per day)
                                </label>
                            </div>
                        </div>

                        <!-- Rental Days (shown only when dispenser is needed) -->
                        <div class="mb-3" id="rental_days_container" style="display: none;">
                            <label for="rental_days" class="form-label">Number of Days for Rental</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="rental_days" 
                                   name="rental_days" 
                                   min="1" 
                                   value="<?php echo isset($_POST['rental_days']) ? htmlspecialchars($_POST['rental_days']) : '1'; ?>">
                            <div class="invalid-feedback">Please enter number of days</div>
                        </div>

                        <!-- Delivery Date -->
                        <div class="mb-3">
                            <label for="delivery_date" class="form-label">Delivery Date</label>
                            <input type="date"
                                   class="form-control"
                                   id="delivery_date"
                                   name="delivery_date"
                                   min="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo isset($_POST['delivery_date']) ? htmlspecialchars($_POST['delivery_date']) : ''; ?>"
                                   required>
                            <div class="invalid-feedback">Please select a delivery date</div>
                        </div>

                        <!-- Delivery Time -->
                        <div class="mb-3">
                            <label for="delivery_time" class="form-label">Delivery Time</label>
                            <select class="form-select" id="delivery_time" name="delivery_time" required>
                                <option value="">Choose delivery time...</option>
                                <?php
                                $start = new DateTime('08:00');
                                $end = new DateTime('17:00');
                                $interval = new DateInterval('PT30M');
                                $selected_time = isset($_POST['delivery_time']) ? $_POST['delivery_time'] : '';
                                
                                while ($start <= $end) {
                                    $time_value = $start->format('H:i');
                                    $time_display = $start->format('h:i A');
                                    $selected = ($selected_time === $time_value) ? 'selected' : '';
                                    echo "<option value=\"$time_value\" $selected>$time_display</option>";
                                    $start->add($interval);
                                }
                                ?>
                            </select>
                            <div class="form-text">Delivery hours: 8:00 AM - 8:00 PM (30-minute intervals)</div>
                            <div class="invalid-feedback">Please select a delivery time</div>
                        </div>

                        <!-- Additional Notes -->
                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" 
                                      id="notes" 
                                      name="notes" 
                                      rows="3"
                                      placeholder="Any specific instructions for delivery"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                        </div>

                        <!-- Order Summary -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Order Summary</h5>
                                <div id="order_summary">
                                    <div class="d-flex justify-content-between">
                                        <span>Refill Cost:</span>
                                        <span id="refill_cost">₱0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Dispenser Rental:</span>
                                        <span id="rental_cost">₱0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Delivery Fee:</span>
                                        <span>₱<?php echo number_format($delivery_charge, 2); ?></span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <strong>Total Amount:</strong>
                                        <strong id="total_amount">₱0.00</strong>
                                    </div>
                                    <div class="text-muted small mt-2">
                                        <i class="fas fa-info-circle"></i> You will earn 1 point for every ₱100 spent
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Submit Refill Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const containerSelect = document.getElementById('container_type');
    const quantityInput = document.getElementById('quantity');
    const needDispenserCheckbox = document.getElementById('need_dispenser');
    const rentalDaysContainer = document.getElementById('rental_days_container');
    const rentalDaysInput = document.getElementById('rental_days');
    const refillCostSpan = document.getElementById('refill_cost');
    const rentalCostSpan = document.getElementById('rental_cost');
    const totalAmountSpan = document.getElementById('total_amount');
    
    const deliveryCharge = <?php echo $delivery_charge; ?>;
    const dispenserPrice = <?php echo $rental_options['dispenser']['price']; ?>;
    
    function updateOrderSummary() {
        const containerPrice = containerSelect.selectedOptions[0]?.dataset?.price || 0;
        const quantity = parseInt(quantityInput.value) || 0;
        const rentalDays = needDispenserCheckbox.checked ? (parseInt(rentalDaysInput.value) || 1) : 0;
        
        const refillCost = containerPrice * quantity;
        const rentalCost = rentalDays * dispenserPrice;
        const totalCost = refillCost + rentalCost + deliveryCharge;
        
        refillCostSpan.textContent = `₱${refillCost.toFixed(2)}`;
        rentalCostSpan.textContent = `₱${rentalCost.toFixed(2)}`;
        totalAmountSpan.textContent = `₱${totalCost.toFixed(2)}`;
    }
    
    // Show/hide rental days input
    needDispenserCheckbox.addEventListener('change', function() {
        rentalDaysContainer.style.display = this.checked ? 'block' : 'none';
        updateOrderSummary();
    });
    
    // Initialize rental days container visibility
    rentalDaysContainer.style.display = needDispenserCheckbox.checked ? 'block' : 'none';
    
    // Update order summary when inputs change
    containerSelect.addEventListener('change', updateOrderSummary);
    quantityInput.addEventListener('input', updateOrderSummary);
    rentalDaysInput.addEventListener('input', updateOrderSummary);
    
    // Form validation
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
    
    // Initialize order summary
    updateOrderSummary();
});
</script>

<?php require_once '../includes/footer.php'; ?> 