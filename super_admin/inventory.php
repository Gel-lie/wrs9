<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

// Check if user is logged in and is a super admin
if (!isLoggedIn() || !hasRole('super_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();

// Get all branches
$branches = [];
$result = $conn->query("SELECT * FROM branches ORDER BY branch_name");
while ($row = $result->fetch_assoc()) {
    $branches[$row['branch_id']] = $row;
}

// Get all products with their inventory levels for each branch
$branch_products = [];
$query = "
    SELECT 
        p.*,
        i.branch_id,
        i.quantity,
        i.low_stock_threshold,
        b.branch_name
    FROM products p
    LEFT JOIN inventory i ON p.product_id = i.product_id
    LEFT JOIN branches b ON i.branch_id = b.branch_id
    ORDER BY b.branch_name, p.product_name
";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    if ($row['branch_id']) {
        if (!isset($branch_products[$row['branch_id']])) {
            $branch_products[$row['branch_id']] = [
                'branch_name' => $row['branch_name'],
                'products' => []
            ];
        }
        $branch_products[$row['branch_id']]['products'][] = [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'description' => $row['description'],
            'price' => $row['price'],
            'category' => $row['category'],
            'image_path' => $row['image_path'],
            'quantity' => $row['quantity'],
            'threshold' => $row['low_stock_threshold']
        ];
    }
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Branch Inventory Management</h2>
        <div>
            <a href="export_inventory.php" class="btn btn-success">
                <i class="fas fa-print"></i> Print Inventory Report
            </a>
        </div>
    </div>

    <!-- Branch selection dropdown -->
    <div class="mb-4">
        <select class="form-select" id="branchSelector" style="max-width: 300px;">
            <option value="all">Show All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="branch-<?php echo $branch['branch_id']; ?>">
                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Branch inventory cards -->
    <?php foreach ($branch_products as $branch_id => $branch_data): ?>
    <div class="branch-section" id="branch-<?php echo $branch_id; ?>">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3 class="card-title h5 mb-0">
                    <i class="fas fa-store me-2"></i>
                    <?php echo htmlspecialchars($branch_data['branch_name']); ?> Branch Inventory
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped inventory-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock Level</th>
                                <th>Status</th>
                                <th>Image</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branch_data['products'] as $product): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($product['description']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <?php echo $product['quantity']; ?> units
                                        <br>
                                        <small class="text-muted">Threshold: <?php echo $product['threshold']; ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = $product['quantity'] <= $product['threshold'] ? 'danger' : 'success';
                                        $status_text = $product['quantity'] <= $product['threshold'] ? 'Low Stock' : 'In Stock';
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($product['image_path']): ?>
                                            <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                                 class="img-thumbnail" 
                                                 style="max-width: 50px; cursor: pointer;"
                                                 onclick="showImageModal(this.src, '<?php echo htmlspecialchars($product['product_name']); ?>')">
                                        <?php else: ?>
                                            <span class="text-muted">No image</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Product Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img src="" id="modalImage" class="img-fluid" alt="Product Image">
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables for all inventory tables
    $('.inventory-table').each(function() {
        $(this).DataTable({
            order: [[0, 'asc']],
            pageLength: 10,
            responsive: true
        });
    });

    // Branch selector functionality
    $('#branchSelector').change(function() {
        const selectedValue = $(this).val();
        if (selectedValue === 'all') {
            $('.branch-section').show();
        } else {
            $('.branch-section').hide();
            $('#' + selectedValue).show();
        }
    });
});

function showImageModal(src, productName) {
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    document.getElementById('modalImage').src = src;
    document.querySelector('#imageModal .modal-title').textContent = productName;
    modal.show();
}
</script>

<style>
.card-header {
    background: linear-gradient(45deg, #0077be, #005c91) !important;
}

.badge {
    padding: 8px 12px;
    font-size: 0.9rem;
}

.form-select {
    border-radius: 20px;
    padding: 10px 15px;
    border-color: #0077be;
}

.form-select:focus {
    border-color: #005c91;
    box-shadow: 0 0 0 0.25rem rgba(0, 119, 190, 0.25);
}
</style>

<?php require_once '../includes/footer.php'; ?> 