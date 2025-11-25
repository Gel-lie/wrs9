<?php
require_once '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !hasRole('customer')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Get customer's branch_id and branch name
$stmt = $conn->prepare("
    SELECT u.branch_id, b.branch_name 
    FROM users u 
    JOIN branches b ON u.branch_id = b.branch_id 
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$branch_id = $user['branch_id'];
$branch_name = $user['branch_name'];

// Get only products that exist in the customer's branch inventory
$sql = "
    SELECT 
        p.*,
        i.quantity as stock_quantity,
        CASE 
            WHEN i.quantity > 0 THEN 'In Stock'
            ELSE 'Out of Stock'
        END as stock_status
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    WHERE i.branch_id = ?
    AND p.status = 'active'
    ORDER BY p.category, p.product_name
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);

// Group products by category
$categorized_products = [];
foreach ($products as $product) {
    $categorized_products[$product['category']][] = $product;
}

// Process add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    
    // Validate stock
    $stmt = $conn->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND branch_id = ?");
    $stmt->bind_param("ii", $product_id, $branch_id);
    $stmt->execute();
    $stock = $stmt->get_result()->fetch_assoc();
    
    if ($stock && $stock['quantity'] >= $quantity) {
        // Initialize cart if not exists
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Check if product already in cart
        if (isset($_SESSION['cart'][$product_id])) {
            $new_quantity = $quantity + $_SESSION['cart'][$product_id]['quantity'];
            if ($new_quantity > $stock['quantity']) {
                $error = "Cannot add more items. Exceeds available stock.";
            } else {
                $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
                $success = "Cart updated successfully!";
            }
        } else {
            // Add new item to cart
            $_SESSION['cart'][$product_id] = [
                'quantity' => $quantity,
                'branch_id' => $branch_id
            ];
            $success = "Product added to cart successfully!";
        }
        
        // Log activity
        logActivity($user_id, 'cart_update', "Added $quantity item(s) to cart");
        
    } else {
        $error = "Sorry, insufficient stock available.";
    }
}
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3">Products Available at <?= htmlspecialchars($branch_name) ?> Branch</h1>
            <p class="text-muted">Showing all products available at your branch</p>
        </div>
    </div>

    <?php if (empty($products)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> No products are currently available at your branch. Please check back later.
    </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Cart Summary -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Your Cart</h5>
                    <div>
                        <span class="badge bg-primary">
                            <?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?> items
                        </span>
                        <a href="cart.php" class="btn btn-primary ms-2">
                            <i class="fas fa-shopping-cart"></i> View Cart
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php foreach ($categorized_products as $category => $category_products): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0"><?= ucfirst($category) ?></h2>
        </div>
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($category_products as $product): ?>
                <div class="col">
                    <div class="card h-100">
                        <?php if ($product['image_path']): ?>
                        <div class="product-image-container" style="cursor: pointer;" 
                             onclick="showEnlargedImage('<?= htmlspecialchars($product['product_name']) ?>', '<?= htmlspecialchars($product['image_path']) ?>')">
                            <img src="../<?= htmlspecialchars($product['image_path']) ?>" 
                                 class="card-img-top" 
                                 alt="<?= htmlspecialchars($product['product_name']) ?>"
                                 style="height: 200px; object-fit: cover;">
                            <div class="zoom-overlay">
                                <i class="fas fa-search-plus"></i> Click to enlarge
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($product['product_name']) ?></h5>
                            <p class="card-text">
                                <?= htmlspecialchars($product['description']) ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="h5 mb-0">₱<?= number_format($product['price'], 2) ?></span>
                                <span class="badge bg-<?= $product['stock_quantity'] > 0 ? 'success' : 'danger' ?>">
                                    <?= $product['stock_status'] ?>
                                    <?php if ($product['stock_quantity'] > 0): ?>
                                    (<?= $product['stock_quantity'] ?> available)
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <button type="button" 
                                    class="btn btn-primary w-100"
                                    onclick="addToCart(<?= $product['product_id'] ?>, <?= $product['stock_quantity'] ?>)"
                                    <?= $product['stock_quantity'] > 0 ? '' : 'disabled' ?>>
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Add to Cart Modal -->
<div class="modal fade" id="addToCartModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add to Cart</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="product_id" id="cartProductId">
                    <input type="hidden" name="add_to_cart" value="1">
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" 
                               class="form-control" 
                               id="quantity" 
                               name="quantity" 
                               min="1" 
                               value="1" 
                               required>
                        <div class="form-text">
                            Maximum available: <span id="maxQuantity">0</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add to Cart</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Image Enlargement Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="enlargedImageTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="enlargedImage" src="" alt="" style="max-width: 100%; max-height: 80vh;">
            </div>
        </div>
    </div>
</div>

<style>
.product-image-container {
    position: relative;
    overflow: hidden;
}

.zoom-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0, 0, 0, 0.5);
    color: white;
    padding: 8px;
    text-align: center;
    font-size: 0.9rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.product-image-container:hover .zoom-overlay {
    opacity: 1;
}
</style>

<script>
function showEnlargedImage(title, imagePath) {
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    document.getElementById('enlargedImageTitle').textContent = title;
    document.getElementById('enlargedImage').src = '../' + imagePath;
    modal.show();
}

function addToCart(productId, maxQuantity) {
    document.getElementById('cartProductId').value = productId;
    document.getElementById('maxQuantity').textContent = maxQuantity;
    document.getElementById('quantity').max = maxQuantity;
    document.getElementById('quantity').value = 1;
    
    const modal = new bootstrap.Modal(document.getElementById('addToCartModal'));
    modal.show();
}
</script>

<?php require_once '../includes/footer.php'; ?>