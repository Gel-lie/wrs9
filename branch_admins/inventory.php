<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

// Check if user is logged in and is a branch admin
if (!isLoggedIn() || !hasRole('branch_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();
$user = getUserInfo($_SESSION['user_id']);
$branch_id = $user['branch_id'];

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/products';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle inventory updates and product creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_product':
            $product_name = trim($_POST['product_name']);
            $description = trim($_POST['description']);
            $price = (float)$_POST['price'];
            $category = $_POST['category'];
            $initial_stock = (int)$_POST['initial_stock'];
            $threshold = (int)$_POST['threshold'];
            
            // Input validation
            if (empty($product_name) || $price <= 0 || $initial_stock < 0 || $threshold <= 0) {
                redirectWith('inventory.php', 'Invalid input values', 'danger');
                exit();
            }

            // Handle image upload
            $image_path = null;
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $file_info = pathinfo($_FILES['product_image']['name']);
                $ext = strtolower($file_info['extension']);
                
                // Validate file type
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($ext, $allowed_types)) {
                    redirectWith('inventory.php', 'Invalid image format. Allowed: JPG, PNG, GIF', 'danger');
                    exit();
                }
                
                // Generate unique filename
                $filename = uniqid('product_') . '.' . $ext;
                $upload_path = $upload_dir . '/' . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                    $image_path = 'uploads/products/' . $filename;
                } else {
                    // Log the error for debugging
                    error_log("Failed to move uploaded file. Upload path: " . $upload_path);
                    error_log("Upload error: " . print_r($_FILES['product_image']['error'], true));
                    redirectWith('inventory.php', 'Failed to upload image', 'danger');
                    exit();
                }
            }

            // Start transaction
            $conn->begin_transaction();
            try {
                // Insert new product
                $stmt = $conn->prepare("
                    INSERT INTO products (product_name, description, price, category, image_path)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("ssdss", $product_name, $description, $price, $category, $image_path);
                $stmt->execute();
                $product_id = $conn->insert_id;

                // Add to inventory
                $stmt = $conn->prepare("
                    INSERT INTO inventory (branch_id, product_id, quantity, low_stock_threshold)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("iiii", $branch_id, $product_id, $initial_stock, $threshold);
                $stmt->execute();

                $conn->commit();
                logActivity($_SESSION['user_id'], 'product_created', "Added new product: $product_name");
                redirectWith('inventory.php', 'Product added successfully', 'success');
            } catch (Exception $e) {
                $conn->rollback();
                // Delete uploaded image if exists
                if ($image_path && file_exists('../' . $image_path)) {
                    unlink('../' . $image_path);
                }
                redirectWith('inventory.php', 'Error adding product: ' . $e->getMessage(), 'danger');
            }
            break;

        case 'update_stock':
            $inventory_id = (int)$_POST['inventory_id'];
            $quantity = (int)$_POST['quantity'];
            $threshold = (int)$_POST['low_stock_threshold'];
            
            // Verify inventory belongs to this branch
            $stmt = $conn->prepare("SELECT * FROM inventory WHERE inventory_id = ? AND branch_id = ?");
            $stmt->bind_param("ii", $inventory_id, $branch_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                redirectWith('inventory.php', 'Inventory item not found', 'danger');
                exit();
            }

            // Update inventory
            $stmt = $conn->prepare("
                UPDATE inventory 
                SET quantity = ?, low_stock_threshold = ?
                WHERE inventory_id = ?
            ");
            $stmt->bind_param("iii", $quantity, $threshold, $inventory_id);
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'inventory_update', "Updated inventory #$inventory_id: Quantity=$quantity, Threshold=$threshold");
                redirectWith('inventory.php', 'Inventory updated successfully', 'success');
            } else {
                redirectWith('inventory.php', 'Error updating inventory', 'danger');
            }
            break;

        case 'delete_product':
            $product_id = (int)$_POST['product_id'];
            
            // Get product image path before deletion
            $stmt = $conn->prepare("SELECT image_path FROM products WHERE product_id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            $image_path = $product['image_path'] ?? null;
            
            // Verify product belongs to this branch
            $stmt = $conn->prepare("
                SELECT i.inventory_id 
                FROM inventory i 
                WHERE i.product_id = ? AND i.branch_id = ?
            ");
            $stmt->bind_param("ii", $product_id, $branch_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                redirectWith('inventory.php', 'Product not found', 'danger');
                exit();
            }

            // Start transaction
            $conn->begin_transaction();
            try {
                // Delete from inventory first (due to foreign key constraint)
                $stmt = $conn->prepare("DELETE FROM inventory WHERE product_id = ? AND branch_id = ?");
                $stmt->bind_param("ii", $product_id, $branch_id);
                $stmt->execute();

                // Delete the product
                $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();

                // Delete product image if exists
                if ($image_path && file_exists("../$image_path")) {
                    unlink("../$image_path");
                }

                $conn->commit();
                logActivity($_SESSION['user_id'], 'product_deleted', "Deleted product ID: $product_id");
                redirectWith('inventory.php', 'Product deleted successfully', 'success');
            } catch (Exception $e) {
                $conn->rollback();
                redirectWith('inventory.php', 'Error deleting product: ' . $e->getMessage(), 'danger');
            }
            break;

        case 'update_product':
            $product_id = (int)$_POST['product_id'];
            $inventory_id = (int)$_POST['inventory_id'];
            $product_name = trim($_POST['product_name']);
            $description = trim($_POST['description']);
            $price = (float)$_POST['price'];
            $category = $_POST['category'];
            $quantity = (int)$_POST['quantity'];
            $threshold = (int)$_POST['low_stock_threshold'];
            
            // Input validation
            if (empty($product_name) || $price <= 0 || $quantity < 0 || $threshold <= 0) {
                redirectWith('inventory.php', 'Invalid input values', 'danger');
                exit();
            }

            // Verify product belongs to this branch
            $stmt = $conn->prepare("
                SELECT p.*, i.inventory_id, i.branch_id, p.image_path
                FROM products p
                JOIN inventory i ON p.product_id = i.product_id
                WHERE p.product_id = ? AND i.branch_id = ?
            ");
            $stmt->bind_param("ii", $product_id, $branch_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                redirectWith('inventory.php', 'Product not found', 'danger');
                exit();
            }
            $existing_product = $result->fetch_assoc();

            // Handle image upload if new image is provided
            $image_path = $existing_product['image_path'];
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $file_info = pathinfo($_FILES['product_image']['name']);
                $ext = strtolower($file_info['extension']);
                
                // Validate file type
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($ext, $allowed_types)) {
                    redirectWith('inventory.php', 'Invalid image format. Allowed: JPG, PNG, GIF', 'danger');
                    exit();
                }
                
                // Generate unique filename
                $filename = uniqid('product_') . '.' . $ext;
                $upload_path = $upload_dir . '/' . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                    // Delete old image if exists
                    if ($existing_product['image_path'] && file_exists('../' . $existing_product['image_path'])) {
                        unlink('../' . $existing_product['image_path']);
                    }
                    $image_path = 'uploads/products/' . $filename;
                }
            }

            // Start transaction
            $conn->begin_transaction();
            try {
                // Update product details
                $stmt = $conn->prepare("
                    UPDATE products 
                    SET product_name = ?, 
                        description = ?, 
                        price = ?, 
                        category = ?,
                        image_path = ?
                    WHERE product_id = ?
                ");
                $stmt->bind_param("ssdssi", $product_name, $description, $price, $category, $image_path, $product_id);
                $stmt->execute();

                // Update inventory
                $stmt = $conn->prepare("
                    UPDATE inventory 
                    SET quantity = ?, 
                        low_stock_threshold = ?
                    WHERE inventory_id = ?
                ");
                $stmt->bind_param("iii", $quantity, $threshold, $inventory_id);
                $stmt->execute();

                $conn->commit();
                logActivity($_SESSION['user_id'], 'product_updated', "Updated product: $product_name");
                redirectWith('inventory.php', 'Product updated successfully', 'success');
            } catch (Exception $e) {
                $conn->rollback();
                // Delete newly uploaded image if exists and transaction failed
                if ($image_path !== $existing_product['image_path'] && file_exists('../' . $image_path)) {
                    unlink('../' . $image_path);
                }
                redirectWith('inventory.php', 'Error updating product: ' . $e->getMessage(), 'danger');
            }
            break;
    }
}

// Get inventory items with product details
$query = "
    SELECT 
        i.*,
        p.product_name,
        p.description,
        p.price,
        p.category,
        p.image_path,
        p.status as product_status,
        CASE 
            WHEN i.quantity <= i.low_stock_threshold THEN 'low'
            WHEN i.quantity = 0 THEN 'out'
            ELSE 'normal'
        END as stock_status
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    WHERE i.branch_id = ?
    ORDER BY p.category, p.product_name
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$inventory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get inventory statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_items,
        COUNT(CASE WHEN quantity <= low_stock_threshold THEN 1 END) as low_stock_items,
        COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock_items,
        COALESCE(SUM(quantity * p.price), 0) as total_value
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    WHERE i.branch_id = ?
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$notes = trim($_POST['notes'] ?? '');
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Manage Inventory</h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="fas fa-plus"></i> Add Product
            </button>
            <a href="export_inventory.php" class="btn btn-primary" target="_blank">
                <i class="fas fa-print"></i> Print Inventory
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Items</h6>
                    <h2 class="mb-0"><?= $stats['total_items'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Low Stock Items</h6>
                    <h2 class="mb-0"><?= $stats['low_stock_items'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title">Out of Stock</h6>
                    <h2 class="mb-0"><?= $stats['out_of_stock_items'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Value</h6>
                    <h2 class="mb-0">₱<?= number_format($stats['total_value'] ?? 0, 2) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory List -->
    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Current Stock</th>
                            <th>Low Stock Alert</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if ($item['image_path']): ?>
                                        <img src="<?= SITE_URL . '/' . $item['image_path'] ?>" 
                                             alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                             class="me-2" 
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                    <?php endif; ?>
                                    <div>
                                        <?= htmlspecialchars($item['product_name']) ?>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($item['description']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= ucfirst($item['category']) ?></td>
                            <td>₱<?= number_format($item['price'], 2) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td><?= $item['low_stock_threshold'] ?></td>
                            <td>
                                <?php
                                $status_class = match($item['stock_status']) {
                                    'low' => 'bg-warning',
                                    'out' => 'bg-danger',
                                    default => 'bg-success'
                                };
                                $status_text = match($item['stock_status']) {
                                    'low' => 'Low Stock',
                                    'out' => 'Out of Stock',
                                    default => 'In Stock'
                                };
                                ?>
                                <span class="badge <?= $status_class ?>">
                                    <?= $status_text ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y h:ia', strtotime($item['last_updated'])) ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info me-1" 
                                        onclick="updateProduct({
                                            product_id: '<?= $item['product_id'] ?>',
                                            inventory_id: '<?= $item['inventory_id'] ?>',
                                            product_name: '<?= addslashes($item['product_name']) ?>',
                                            description: '<?= addslashes($item['description']) ?>',
                                            price: '<?= $item['price'] ?>',
                                            category: '<?= $item['category'] ?>',
                                            quantity: '<?= $item['quantity'] ?>',
                                            low_stock_threshold: '<?= $item['low_stock_threshold'] ?>',
                                            image_path: '<?= $item['image_path'] ?>'
                                        })">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-primary me-1"
                                        onclick="updateStock({
                                            inventory_id: '<?= $item['inventory_id'] ?>',
                                            product_name: '<?= addslashes($item['product_name']) ?>',
                                            quantity: '<?= $item['quantity'] ?>',
                                            low_stock_threshold: '<?= $item['low_stock_threshold'] ?>'
                                        })">
                                    <i class="fas fa-boxes"></i> Stock
                                </button>
                                <button type="button" class="btn btn-sm btn-danger"
                                        onclick="deleteProduct({
                                            product_id: '<?= $item['product_id'] ?>',
                                            product_name: '<?= addslashes($item['product_name']) ?>'
                                        })">
                                    <i class="fas fa-trash"></i>
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

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="inventory.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="inventory_id" id="update_inventory_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <input type="text" class="form-control" id="update_product_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="update_quantity" class="form-label">Current Stock</label>
                        <input type="number" class="form-control" name="quantity" id="update_quantity" 
                               min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="update_threshold" class="form-label">Low Stock Alert Threshold</label>
                        <input type="number" class="form-control" name="low_stock_threshold" 
                               id="update_threshold" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="inventory.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_product">
                    
                    <div class="mb-3">
                        <label for="product_name" class="form-label">Product Name</label>
                        <input type="text" class="form-control" name="product_name" id="product_name" 
                               required maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label for="product_image" class="form-label">Product Image</label>
                        <input type="file" class="form-control" name="product_image" id="product_image" 
                               accept="image/jpeg,image/png,image/gif">
                        <small class="text-muted">Supported formats: JPG, PNG, GIF</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" 
                                rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="price" class="form-label">Price (₱)</label>
                        <input type="number" class="form-control" name="price" id="price" 
                               min="0.01" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-control" name="category" id="category" required>
                            <option value="container">Container</option>
                            <option value="dispenser">Dispenser</option>
                            <option value="refill">Refill</option>
                            <option value="accessory">Accessory</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="initial_stock" class="form-label">Initial Stock</label>
                        <input type="number" class="form-control" name="initial_stock" id="initial_stock" 
                               min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="threshold" class="form-label">Low Stock Alert Threshold</label>
                        <input type="number" class="form-control" name="threshold" id="threshold" 
                               min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Product Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="inventory.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" id="delete_product_id">
                    <p>Are you sure you want to delete this product: <strong id="delete_product_name"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        This action cannot be undone. All inventory records for this product will also be deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Product Modal -->
<div class="modal fade" id="updateProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="inventory.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_product">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    <input type="hidden" name="inventory_id" id="edit_inventory_id">
                    
                    <div class="mb-3">
                        <label for="edit_product_name" class="form-label">Product Name</label>
                        <input type="text" class="form-control" name="product_name" id="edit_product_name" 
                               required maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_product_image" class="form-label">Product Image</label>
                        <div class="mb-2" id="current_image_preview"></div>
                        <input type="file" class="form-control" name="product_image" id="edit_product_image" 
                               accept="image/jpeg,image/png,image/gif">
                        <small class="text-muted">Supported formats: JPG, PNG, GIF. Leave empty to keep current image.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" 
                                rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_price" class="form-label">Price (₱)</label>
                        <input type="number" class="form-control" name="price" id="edit_price" 
                               min="0.01" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_category" class="form-label">Category</label>
                        <select class="form-control" name="category" id="edit_category" required>
                            <option value="container">Container</option>
                            <option value="dispenser">Dispenser</option>
                            <option value="refill">Refill</option>
                            <option value="accessory">Accessory</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_quantity" class="form-label">Current Stock</label>
                        <input type="number" class="form-control" name="quantity" id="edit_quantity" 
                               min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_threshold" class="form-label">Low Stock Alert Threshold</label>
                        <input type="number" class="form-control" name="low_stock_threshold" 
                               id="edit_threshold" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize DataTables and Bootstrap components
$(document).ready(function() {
    $('#inventoryTable').DataTable({
        order: [[1, 'asc'], [0, 'asc']], // Sort by category then product name
        pageLength: 25,
        columnDefs: [
            { orderable: false, targets: -1 } // Disable sorting on actions column
        ]
    });
});

// Function to update stock
function updateStock(item) {
    const stockModal = document.getElementById('updateStockModal');
    if (!stockModal) return;
    
    document.getElementById('update_inventory_id').value = item.inventory_id;
    document.getElementById('update_product_name').value = item.product_name;
    document.getElementById('update_quantity').value = item.quantity;
    document.getElementById('update_threshold').value = item.low_stock_threshold;
    
    const modal = new bootstrap.Modal(stockModal);
    modal.show();
}

// Function to update product details
function updateProduct(item) {
    const editModal = document.getElementById('updateProductModal');
    if (!editModal) return;
    
    // Set form values
    document.getElementById('edit_product_id').value = item.product_id;
    document.getElementById('edit_inventory_id').value = item.inventory_id;
    document.getElementById('edit_product_name').value = item.product_name;
    document.getElementById('edit_description').value = item.description || '';
    document.getElementById('edit_price').value = item.price;
    document.getElementById('edit_category').value = item.category;
    document.getElementById('edit_quantity').value = item.quantity;
    document.getElementById('edit_threshold').value = item.low_stock_threshold;
    
    // Update image preview
    const imagePreview = document.getElementById('current_image_preview');
    if (imagePreview) {
        if (item.image_path) {
            imagePreview.innerHTML = `
                <img src="${SITE_URL}/${item.image_path}" 
                     alt="${item.product_name}" 
                     style="max-width: 100px; max-height: 100px; object-fit: cover; border-radius: 4px;">
                <br>
                <small class="text-muted">Current image</small>
            `;
        } else {
            imagePreview.innerHTML = '<small class="text-muted">No current image</small>';
        }
    }
    
    const modal = new bootstrap.Modal(editModal);
    modal.show();
}

// Function to delete product
function deleteProduct(item) {
    const deleteModal = document.getElementById('deleteProductModal');
    if (!deleteModal) return;
    
    document.getElementById('delete_product_id').value = item.product_id;
    document.getElementById('delete_product_name').textContent = item.product_name;
    
    const modal = new bootstrap.Modal(deleteModal);
    modal.show();
}
</script>

<?php
// Add SITE_URL to JavaScript
echo "<script>const SITE_URL = '" . SITE_URL . "';</script>";
?>

<?php require_once '../includes/footer.php'; ?> 