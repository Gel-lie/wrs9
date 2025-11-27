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

// Handle customer status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_status':
            $customer_id = (int)$_POST['customer_id'];
            $status = sanitize($_POST['status']);
            
            // Verify customer belongs to this branch
            $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND branch_id = ?");
            $stmt->bind_param("ii", $customer_id, $branch_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                redirectWith('customers.php', 'Customer not found', 'danger');
                exit();
            }

            // Update customer status
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->bind_param("si", $status, $customer_id);
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'customer_update', "Updated customer #$customer_id status to: $status");
                redirectWith('customers.php', 'Customer status updated successfully', 'success');
            } else {
                redirectWith('customers.php', 'Error updating customer status', 'danger');
            }
            break;
    }
}

// Get filters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$barangay_filter = isset($_GET['barangay']) ? sanitize($_GET['barangay']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Get customers with their details
$query = "
    SELECT 
        u.*,
        b.barangay_name,
        l.points as loyalty_points,
        l.reward_level,
        COUNT(DISTINCT o.order_id) as total_orders,
        SUM(CASE WHEN o.status != 'cancelled' THEN o.total_amount ELSE 0 END) as total_spent,
        MAX(o.order_date) as last_order_date
    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.barangay_id
    LEFT JOIN loyalty l ON u.user_id = l.customer_id
    LEFT JOIN orders o ON u.user_id = o.customer_id
    WHERE u.branch_id = ? AND u.role = 'customer'
";

$params = [$branch_id];
$types = "i";

if ($status_filter) {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($barangay_filter) {
    $query .= " AND u.barangay_id = ?";
    $params[] = $barangay_filter;
    $types .= "i";
}

if ($search) {
    $search_term = "%$search%";
    $query .= " AND (
        u.name LIKE ? OR 
        u.email LIKE ? OR 
        u.contact_number LIKE ? OR
        b.barangay_name LIKE ? OR
        u.sitio_purok LIKE ? OR
        u.household_surname LIKE ? OR
        u.household_id_number LIKE ?
    )";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
    $types .= "sssssss";
}

$query .= " GROUP BY u.user_id ORDER BY u.name";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get customer statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_customers,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_customers,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_customers,
        COUNT(CASE WHEN registration_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_customers
    FROM users
    WHERE branch_id = ? AND role = 'customer'
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get barangays for filter
$stmt = $conn->prepare("SELECT * FROM barangays WHERE branch_id = ? ORDER BY barangay_name");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$barangays = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Manage Customers</h1>
        <div class="d-flex gap-2">
            <a href="export_customers.php?<?= http_build_query($_GET) ?>" class="btn btn-primary" target="_blank">
                <i class="fas fa-print"></i> Print List
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Customers</h6>
                    <h2 class="mb-0"><?= $stats['total_customers'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Active Customers</h6>
                    <h2 class="mb-0"><?= $stats['active_customers'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title">Inactive Customers</h6>
                    <h2 class="mb-0"><?= $stats['inactive_customers'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">New Customers (30d)</h6>
                    <h2 class="mb-0"><?= $stats['new_customers'] ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" name="status" id="status">
                        <option value="">All Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="barangay" class="form-label">Barangay</label>
                    <select class="form-select" name="barangay" id="barangay">
                        <option value="">All Barangays</option>
                        <?php foreach ($barangays as $barangay): ?>
                        <option value="<?= $barangay['barangay_id'] ?>" 
                                <?= $barangay_filter == $barangay['barangay_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($barangay['barangay_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" id="search" 
                           value="<?= $search ?>" placeholder="Search name, email, or contact...">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="customers.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Customers List -->
    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="customersTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact Info</th>
                            <th>Location</th>
                            <th>Orders</th>
                            <th>Loyalty</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($customer['name']) ?>
                                <br>
                                <small class="text-muted">Since <?= date('M Y', strtotime($customer['registration_date'])) ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($customer['email']) ?><br>
                                <?= htmlspecialchars($customer['contact_number']) ?>
                            </td>
                            <td>
                                <?php
                                    $parts = [];
                                    if (!empty($customer['household_id_number'])) $parts[] = $customer['household_id_number'];
                                    if (!empty($customer['household_surname'])) $parts[] = $customer['household_surname'];
                                    if (!empty($customer['sitio_purok'])) $parts[] = $customer['sitio_purok'];
                                    if (!empty($customer['barangay_name'])) $parts[] = $customer['barangay_name'];
                                    echo htmlspecialchars(implode(', ', $parts));
                                ?>
                            </td>
                            <td>
                                Orders: <?= $customer['total_orders'] ?><br>
                                Total: ₱<?= number_format($customer['total_spent'], 2) ?><br>
                                <?php if ($customer['last_order_date']): ?>
                                Last: <?= date('M d, Y', strtotime($customer['last_order_date'])) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                Points: <?= number_format($customer['loyalty_points'] ?? 0) ?><br>
                                Level: <?= $customer['reward_level'] ?? 'None' ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $customer['status'] === 'active' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($customer['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" 
                                        class="btn btn-sm btn-info" 
                                        onclick="viewCustomer(<?= htmlspecialchars(json_encode($customer)) ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" 
                                        class="btn btn-sm btn-primary"
                                        onclick="updateStatus(<?= $customer['user_id'] ?>, '<?= $customer['status'] ?>')">
                                    <i class="fas fa-sync-alt"></i>
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

<!-- View Customer Modal -->
<div class="modal fade" id="viewCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Customer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="customerDetails">
                <!-- Customer details will be populated here -->
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Customer Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="customers.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="customer_id" id="update_customer_id">
                    <div class="mb-3">
                        <label for="update_status" class="form-label">Status</label>
                        <select class="form-select" name="status" id="update_status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
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
    $('#customersTable').DataTable({
        order: [[0, 'asc']], // Sort by name by default
        pageLength: 25,
        columnDefs: [
            { orderable: false, targets: -1 } // Disable sorting on actions column
        ]
    });
});

// Function to view customer details
function viewCustomer(customer) {
    const modal = new bootstrap.Modal(document.getElementById('viewCustomerModal'));
    const statusClass = customer.status === 'active' ? 'success' : 'danger';
    
    let html = `
        <div class="row mb-3">
            <div class="col-md-6">
                <h6>Personal Information</h6>
                <p>
                    <strong>Name:</strong> ${customer.name}<br>
                    <strong>Email:</strong> ${customer.email}<br>
                    <strong>Contact:</strong> ${customer.contact_number}<br>
                    <strong>Status:</strong> <span class="badge bg-${statusClass}">${customer.status.toUpperCase()}</span>
                </p>
            </div>
                    <div class="col-md-6">
                        <h6>Location Information</h6>
                        <p>
                            <strong>Address:</strong> ${[customer.household_id_number, customer.household_surname, customer.sitio_purok, customer.barangay_name].filter(Boolean).join(', ')}<br>
                            <strong>Registration Date:</strong> ${new Date(customer.registration_date).toLocaleDateString()}<br>
                            <strong>Last Login:</strong> ${customer.last_login ? new Date(customer.last_login).toLocaleString() : 'Never'}
                        </p>
                    </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <h6>Order Statistics</h6>
                <p>
                    <strong>Total Orders:</strong> ${customer.total_orders}<br>
                    <strong>Total Spent:</strong> ₱${parseFloat(customer.total_spent).toFixed(2)}<br>
                    <strong>Last Order:</strong> ${customer.last_order_date ? new Date(customer.last_order_date).toLocaleDateString() : 'Never'}
                </p>
            </div>
            <div class="col-md-6">
                <h6>Loyalty Information</h6>
                <p>
                    <strong>Points:</strong> ${customer.loyalty_points || 0}<br>
                    <strong>Reward Level:</strong> ${customer.reward_level || 'None'}<br>
                </p>
            </div>
        </div>
    `;
    
    document.getElementById('customerDetails').innerHTML = html;
    modal.show();
}

// Function to update customer status
function updateStatus(customerId, currentStatus) {
    const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
    document.getElementById('update_customer_id').value = customerId;
    document.getElementById('update_status').value = currentStatus;
    modal.show();
}
</script>

<?php require_once '../includes/footer.php'; ?> 