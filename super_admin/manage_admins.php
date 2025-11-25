<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

// Check if user is logged in and has super admin role
if (!isLoggedIn() || !hasRole('super_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();

// Handle admin operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = sanitize($_POST['username']);
                $email = sanitize($_POST['email']);
                $name = sanitize($_POST['name']);
                $branch_id = (int)$_POST['branch_id'];
                $contact_number = sanitize($_POST['contact_number']);
                
                // Validate input
                if (!isValidEmail($email)) {
                    redirectWith('manage_admins.php', 'Invalid email format', 'danger');
                    exit();
                }
                
                if (!isValidPhoneNumber($contact_number)) {
                    redirectWith('manage_admins.php', 'Invalid phone number format', 'danger');
                    exit();
                }

                // Check if username or email already exists
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    redirectWith('manage_admins.php', 'Username or email already exists', 'danger');
                    exit();
                }

                // Verify branch exists
                $stmt = $conn->prepare("SELECT branch_id FROM branches WHERE branch_id = ?");
                $stmt->bind_param("i", $branch_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    redirectWith('manage_admins.php', 'Selected branch does not exist', 'danger');
                    exit();
                }

                // Generate random password
                $password = bin2hex(random_bytes(8)); // 16 characters
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new admin
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, name, role, branch_id, contact_number) VALUES (?, ?, ?, ?, 'branch_admin', ?, ?)");
                $stmt->bind_param("ssssss", $username, $email, $hashed_password, $name, $branch_id, $contact_number);
                if ($stmt->execute()) {
                    logActivity($_SESSION['user_id'], 'admin_create', "Created new branch admin: $username");
                    $_SESSION['temp_password'] = $password; // Store temporarily for display
                    redirectWith('manage_admins.php', 'Branch admin added successfully', 'success');
                } else {
                    redirectWith('manage_admins.php', 'Error adding branch admin', 'danger');
                }
                break;

            case 'edit':
                $user_id = (int)$_POST['user_id'];
                $name = sanitize($_POST['name']);
                $email = sanitize($_POST['email']);
                $branch_id = (int)$_POST['branch_id'];
                $contact_number = sanitize($_POST['contact_number']);
                $status = $_POST['status'];
                
                // Validate input
                if (!isValidEmail($email)) {
                    redirectWith('manage_admins.php', 'Invalid email format', 'danger');
                    exit();
                }
                
                if (!isValidPhoneNumber($contact_number)) {
                    redirectWith('manage_admins.php', 'Invalid phone number format', 'danger');
                    exit();
                }

                // Check if email exists for other users
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $stmt->bind_param("si", $email, $user_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    redirectWith('manage_admins.php', 'Email already exists', 'danger');
                    exit();
                }

                // Verify branch exists
                $stmt = $conn->prepare("SELECT branch_id FROM branches WHERE branch_id = ?");
                $stmt->bind_param("i", $branch_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    redirectWith('manage_admins.php', 'Selected branch does not exist', 'danger');
                    exit();
                }

                // Update admin info
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, branch_id = ?, contact_number = ?, status = ? WHERE user_id = ? AND role = 'branch_admin'");
                $stmt->bind_param("sssssi", $name, $email, $branch_id, $contact_number, $status, $user_id);
                if ($stmt->execute()) {
                    // Update password if provided
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $stmt->bind_param("si", $password, $user_id);
                        $stmt->execute();
                    }
                    logActivity($_SESSION['user_id'], 'admin_update', "Updated branch admin info: $name");
                    redirectWith('manage_admins.php', 'Branch admin updated successfully', 'success');
                } else {
                    redirectWith('manage_admins.php', 'Error updating branch admin', 'danger');
                }
                break;

            case 'delete':
                $user_id = (int)$_POST['user_id'];
                
                // Check if admin exists and get their info
                $stmt = $conn->prepare("SELECT name, branch_id FROM users WHERE user_id = ? AND role = 'branch_admin'");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $admin = $stmt->get_result()->fetch_assoc();
                
                if (!$admin) {
                    redirectWith('manage_admins.php', 'Admin not found', 'danger');
                    exit();
                }
                
                // Check if this is the only admin for the branch
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE branch_id = ? AND role = 'branch_admin' AND status = 'active'");
                $stmt->bind_param("i", $admin['branch_id']);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                if ($result['count'] <= 1) {
                    redirectWith('manage_admins.php', 'Cannot delete the only active admin for this branch', 'danger');
                    exit();
                }

                // Soft delete by setting status to inactive
                $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ? AND role = 'branch_admin'");
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    logActivity($_SESSION['user_id'], 'admin_delete', "Deactivated branch admin: {$admin['name']}");
                    redirectWith('manage_admins.php', 'Branch admin deactivated successfully', 'success');
                } else {
                    redirectWith('manage_admins.php', 'Error deactivating branch admin', 'danger');
                }
                break;
        }
    }
}

// Get all branch admins with their branch info
$stmt = $conn->prepare("
    SELECT 
        u.*,
        b.branch_name,
        (SELECT COUNT(*) FROM orders o 
         JOIN users c ON o.customer_id = c.user_id 
         WHERE c.branch_id = u.branch_id) as total_orders,
        (SELECT COUNT(*) FROM users c 
         WHERE c.branch_id = u.branch_id 
         AND c.role = 'customer') as total_customers
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.branch_id
    WHERE u.role = 'branch_admin'
    ORDER BY u.status DESC, u.name
");
$stmt->execute();
$admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all branches for dropdown
$stmt = $conn->prepare("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
$stmt->execute();
$branches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Manage Branch Administrators</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
            <i class="fas fa-plus"></i> Add New Admin
        </button>
    </div>

    <?php 
    if (isset($_SESSION['flash'])) {
        echo displayFlashMessage();
        // Display temporary password if available
        if (isset($_SESSION['temp_password'])) {
            echo '<div class="alert alert-info">
                    Temporary password for new admin: <strong>' . $_SESSION['temp_password'] . '</strong>
                    <br>Please make sure to copy this password and share it securely with the admin.
                  </div>';
            unset($_SESSION['temp_password']);
        }
    }
    ?>

    <!-- Branch Admins List -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="adminsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Branch</th>
                            <th>Total Customers</th>
                            <th>Total Orders</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td><?= htmlspecialchars($admin['name']) ?></td>
                            <td><?= htmlspecialchars($admin['username']) ?></td>
                            <td><?= htmlspecialchars($admin['email']) ?></td>
                            <td><?= htmlspecialchars($admin['branch_name']) ?></td>
                            <td><?= $admin['total_customers'] ?></td>
                            <td><?= $admin['total_orders'] ?></td>
                            <td>
                                <span class="badge bg-<?= $admin['status'] === 'active' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($admin['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" 
                                        class="btn btn-sm btn-info edit-admin" 
                                        onclick="editAdmin(
                                            '<?= $admin['user_id'] ?>',
                                            '<?= htmlspecialchars($admin['name'], ENT_QUOTES) ?>',
                                            '<?= htmlspecialchars($admin['email'], ENT_QUOTES) ?>',
                                            '<?= $admin['branch_id'] ?>',
                                            '<?= htmlspecialchars($admin['contact_number'], ENT_QUOTES) ?>',
                                            '<?= $admin['status'] ?>'
                                        )">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($admin['status'] === 'active'): ?>
                                <button type="button" class="btn btn-sm btn-danger delete-admin"
                                        onclick="deleteAdmin('<?= $admin['user_id'] ?>', '<?= htmlspecialchars($admin['name'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
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

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Branch Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="manage_admins.php" method="post" id="addAdminForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="branch_id" class="form-label">Branch</label>
                        <select class="form-control" name="branch_id" required>
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['branch_id'] ?>">
                                <?= htmlspecialchars($branch['branch_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label for="contact_number" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" name="contact_number" 
                               placeholder="09XXXXXXXXX" required>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        A temporary password will be generated automatically.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Branch Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="manage_admins.php" method="post" id="editAdminForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="form-group mb-3">
                        <label for="edit_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" name="password" id="edit_password">
                    </div>
                    <div class="form-group mb-3">
                        <label for="edit_branch" class="form-label">Branch</label>
                        <select class="form-control" name="branch_id" id="edit_branch" required>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['branch_id'] ?>">
                                <?= htmlspecialchars($branch['branch_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label for="edit_contact" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" name="contact_number" 
                               id="edit_contact" placeholder="09XXXXXXXXX" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-control" name="status" id="edit_status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
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

<!-- Delete Admin Modal -->
<div class="modal fade" id="deleteAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Deactivate Branch Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="manage_admins.php" method="post" id="deleteAdminForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <p>Are you sure you want to deactivate admin: <strong id="delete_admin_name"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        This will prevent the admin from accessing the system. Make sure there is another active admin for their branch.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Deactivate Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#adminsTable').DataTable({
        order: [[0, 'asc']], // Sort by name by default
        columnDefs: [
            { orderable: false, targets: -1 } // Disable sorting on actions column
        ]
    });

    // Form validation
    function validatePhoneNumber(phone) {
        return /^(09|\+639)\d{9}$/.test(phone);
    }

    $('#addAdminForm, #editAdminForm').on('submit', function(e) {
        var phone = $(this).find('input[name="contact_number"]').val();
        if (!validatePhoneNumber(phone)) {
            e.preventDefault();
            alert('Please enter a valid Philippine phone number (e.g., 09123456789)');
            return false;
        }
    });

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});

// Function to handle edit admin
function editAdmin(id, name, email, branch, contact, status) {
    console.log('Editing admin:', { id, name, email, branch, contact, status });
    
    // Show the modal
    const editModal = new bootstrap.Modal(document.getElementById('editAdminModal'));
    editModal.show();
    
    // Set the form values
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_branch').value = branch;
    document.getElementById('edit_contact').value = contact;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit_password').value = ''; // Clear password field
    
    // Debug: Log the values after setting
    console.log('Form values set:', {
        id: document.getElementById('edit_user_id').value,
        name: document.getElementById('edit_name').value,
        email: document.getElementById('edit_email').value,
        branch: document.getElementById('edit_branch').value,
        contact: document.getElementById('edit_contact').value,
        status: document.getElementById('edit_status').value
    });
}

// Function to handle delete admin
function deleteAdmin(id, name) {
    console.log('Deleting admin:', { id, name });
    
    // Show the modal
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteAdminModal'));
    deleteModal.show();
    
    // Set the form values
    document.getElementById('delete_user_id').value = id;
    document.getElementById('delete_admin_name').textContent = name;
}
</script>

<?php require_once '../includes/footer.php'; ?> 