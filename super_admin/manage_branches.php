<?php
require_once '../includes/db.php';  // First include db.php
require_once '../includes/functions.php';  // Then include functions.php
require_once '../includes/header.php';  // Then include header.php

// Check if user is logged in and has super admin role
if (!isLoggedIn() || !hasRole('super_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();
if (!$conn) {
    die("Database connection failed. Please try again later.");
}

// Handle branch operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $branch_name = sanitize($_POST['branch_name']);
                
                // Check if branch name already exists
                $stmt = $conn->prepare("SELECT branch_id FROM branches WHERE branch_name = ?");
                $stmt->bind_param("s", $branch_name);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    redirectWith('manage_branches.php', 'Branch name already exists', 'danger');
                    exit();
                }

                // Insert new branch
                $stmt = $conn->prepare("INSERT INTO branches (branch_name) VALUES (?)");
                $stmt->bind_param("s", $branch_name);
                if ($stmt->execute()) {
                    logActivity($_SESSION['user_id'], 'branch_create', "Created new branch: $branch_name");
                    redirectWith('manage_branches.php', 'Branch added successfully', 'success');
                } else {
                    redirectWith('manage_branches.php', 'Error adding branch', 'danger');
                }
                break;

            case 'edit':
                $branch_id = (int)$_POST['branch_id'];
                $branch_name = sanitize($_POST['branch_name']);
                
                // Check if branch exists
                $stmt = $conn->prepare("SELECT branch_id FROM branches WHERE branch_id = ?");
                $stmt->bind_param("i", $branch_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    redirectWith('manage_branches.php', 'Branch not found', 'danger');
                    exit();
                }

                // Check if new name already exists for other branches
                $stmt = $conn->prepare("SELECT branch_id FROM branches WHERE branch_name = ? AND branch_id != ?");
                $stmt->bind_param("si", $branch_name, $branch_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    redirectWith('manage_branches.php', 'Branch name already exists', 'danger');
                    exit();
                }

                // Update branch
                $stmt = $conn->prepare("UPDATE branches SET branch_name = ? WHERE branch_id = ?");
                $stmt->bind_param("si", $branch_name, $branch_id);
                if ($stmt->execute()) {
                    logActivity($_SESSION['user_id'], 'branch_update', "Updated branch name to: $branch_name");
                    redirectWith('manage_branches.php', 'Branch updated successfully', 'success');
                } else {
                    redirectWith('manage_branches.php', 'Error updating branch', 'danger');
                }
                break;

            case 'delete':
                $branch_id = (int)$_POST['branch_id'];
                
                // Check if branch exists
                $stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ?");
                $stmt->bind_param("i", $branch_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    redirectWith('manage_branches.php', 'Branch not found', 'danger');
                    exit();
                }
                $branch_name = $result->fetch_assoc()['branch_name'];

                // Check for related records
                $related_tables = [
                    'users' => "SELECT COUNT(*) as count FROM users WHERE branch_id = ?",
                    'barangays' => "SELECT COUNT(*) as count FROM barangays WHERE branch_id = ?",
                    'inventory' => "SELECT COUNT(*) as count FROM inventory WHERE branch_id = ?",
                    'appointments' => "SELECT COUNT(*) as count FROM appointments WHERE branch_id = ?",
                    'tasks' => "SELECT COUNT(*) as count FROM tasks WHERE branch_id = ?"
                ];

                $has_related_records = false;
                $related_records_msg = "Cannot delete branch. Found:";

                foreach ($related_tables as $table => $query) {
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $branch_id);
                    $stmt->execute();
                    $count = $stmt->get_result()->fetch_assoc()['count'];
                    if ($count > 0) {
                        $has_related_records = true;
                        $related_records_msg .= " $count record(s) in $table,";
                    }
                }

                if ($has_related_records) {
                    redirectWith('manage_branches.php', rtrim($related_records_msg, ','), 'danger');
                    exit();
                }

                // Delete branch if no related records
                $stmt = $conn->prepare("DELETE FROM branches WHERE branch_id = ?");
                $stmt->bind_param("i", $branch_id);
                if ($stmt->execute()) {
                    logActivity($_SESSION['user_id'], 'branch_delete', "Deleted branch: $branch_name");
                    redirectWith('manage_branches.php', 'Branch deleted successfully', 'success');
                } else {
                    redirectWith('manage_branches.php', 'Error deleting branch', 'danger');
                }
                break;
        }
    }
}

// Get all branches with their statistics
$stmt = $conn->prepare("
    SELECT 
        b.*,
        (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.branch_id AND u.role = 'customer') as total_customers,
        (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.branch_id AND u.role = 'branch_admin') as total_admins,
        (SELECT COUNT(*) FROM orders o JOIN users u ON o.customer_id = u.user_id WHERE u.branch_id = b.branch_id) as total_orders
    FROM branches b
    ORDER BY b.branch_name
");
$stmt->execute();
$branches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Manage Branches</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBranchModal">
            <i class="fas fa-plus"></i> Add New Branch
        </button>
    </div>

    <?php if (isset($_SESSION['flash'])) echo displayFlashMessage(); ?>

    <!-- Branches List -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="branchesTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Branch Name</th>
                            <th>Total Customers</th>
                            <th>Total Admins</th>
                            <th>Total Orders</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $branch): ?>
                        <tr>
                            <td><?= htmlspecialchars($branch['branch_name']) ?></td>
                            <td><?= $branch['total_customers'] ?></td>
                            <td><?= $branch['total_admins'] ?></td>
                            <td><?= $branch['total_orders'] ?></td>
                            <td>
                                <button type="button" 
                                        class="btn btn-sm btn-info" 
                                        onclick="editBranch(<?= $branch['branch_id'] ?>, '<?= htmlspecialchars($branch['branch_name'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="branch_details.php?id=<?= $branch['branch_id'] ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" 
                                        class="btn btn-sm btn-danger" 
                                        onclick="deleteBranch(<?= $branch['branch_id'] ?>, '<?= htmlspecialchars($branch['branch_name'], ENT_QUOTES) ?>')">
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

<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="manage_branches.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group mb-3">
                        <label for="branch_name" class="form-label">Branch Name</label>
                        <input type="text" class="form-control" name="branch_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="manage_branches.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="branch_id" id="edit_branch_id">
                    <div class="form-group mb-3">
                        <label for="edit_branch_name" class="form-label">Branch Name</label>
                        <input type="text" class="form-control" name="branch_name" id="edit_branch_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Branch Modal -->
<div class="modal fade" id="deleteBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="manage_branches.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="branch_id" id="delete_branch_id">
                    <p>Are you sure you want to delete branch: <strong id="delete_branch_name"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        This action cannot be undone. Make sure there are no users, orders, or other data associated with this branch.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#branchesTable').DataTable({
        order: [[0, 'asc']], // Sort by branch name by default
        columnDefs: [
            { orderable: false, targets: -1 } // Disable sorting on actions column
        ]
    });
});

// Function to handle edit branch
function editBranch(id, name) {
    console.log('Editing branch:', { id, name });
    
    // Show the modal
    const editModal = new bootstrap.Modal(document.getElementById('editBranchModal'));
    editModal.show();
    
    // Set the form values
    document.getElementById('edit_branch_id').value = id;
    document.getElementById('edit_branch_name').value = name;
    
    // Debug: Log the values after setting
    console.log('Form values set:', {
        id: document.getElementById('edit_branch_id').value,
        name: document.getElementById('edit_branch_name').value
    });
}

// Function to handle delete branch
function deleteBranch(id, name) {
    console.log('Deleting branch:', { id, name });
    
    // Show the modal
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteBranchModal'));
    deleteModal.show();
    
    // Set the form values
    document.getElementById('delete_branch_id').value = id;
    document.getElementById('delete_branch_name').textContent = name;
}
</script>

<?php require_once '../includes/footer.php'; ?> 