<?php
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and is a branch admin
if (!isLoggedIn() || !hasRole('branch_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();
$user = getUserInfo($_SESSION['user_id']);
$branch_id = $user['branch_id'];

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $title = trim($_POST['title']);
            $message = trim($_POST['message']);
            $type = $_POST['type'];
            $start_date = $_POST['start_date'];
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            
            // Validate inputs
            if (empty($title) || empty($message) || empty($type) || empty($start_date)) {
                setFlashMessage('All required fields must be filled', 'danger');
                break;
            }
            
            // Insert announcement
            $stmt = $conn->prepare("
                INSERT INTO announcements (branch_id, title, message, type, start_date, end_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssssi", $branch_id, $title, $message, $type, $start_date, $end_date, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                // Get all active customers from this branch
                $customer_stmt = $conn->prepare("
                    SELECT user_id 
                    FROM users 
                    WHERE branch_id = ? AND role = 'customer' AND status = 'active'
                ");
                $customer_stmt->bind_param("i", $branch_id);
                $customer_stmt->execute();
                $customers = $customer_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Create notifications for each customer
                $notification_stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, title, message, type)
                    VALUES (?, ?, ?, 'system')
                ");
                
                foreach ($customers as $customer) {
                    $notification_stmt->bind_param("iss", 
                        $customer['user_id'],
                        $title,
                        $message
                    );
                    $notification_stmt->execute();
                }
                
                setFlashMessage('Announcement added and notifications sent successfully', 'success');
            } else {
                setFlashMessage('Error adding announcement', 'danger');
            }
            break;
            
        case 'update':
            $announcement_id = $_POST['announcement_id'];
            $title = trim($_POST['title']);
            $message = trim($_POST['message']);
            $type = $_POST['type'];
            $status = $_POST['status'];
            $start_date = $_POST['start_date'];
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            
            // Validate inputs
            if (empty($title) || empty($message) || empty($type) || empty($start_date)) {
                setFlashMessage('All required fields must be filled', 'danger');
                break;
            }
            
            // Update announcement
            $stmt = $conn->prepare("
                UPDATE announcements 
                SET title = ?, message = ?, type = ?, status = ?, start_date = ?, end_date = ?
                WHERE announcement_id = ? AND branch_id = ?
            ");
            $stmt->bind_param("ssssssii", $title, $message, $type, $status, $start_date, $end_date, $announcement_id, $branch_id);
            
            if ($stmt->execute()) {
                setFlashMessage('Announcement updated successfully', 'success');
            } else {
                setFlashMessage('Error updating announcement', 'danger');
            }
            break;
            
        case 'delete':
            $announcement_id = $_POST['announcement_id'];
            
            $stmt = $conn->prepare("
                DELETE FROM announcements 
                WHERE announcement_id = ? AND branch_id = ?
            ");
            $stmt->bind_param("ii", $announcement_id, $branch_id);
            
            if ($stmt->execute()) {
                setFlashMessage('Announcement deleted successfully', 'success');
            } else {
                setFlashMessage('Error deleting announcement', 'danger');
            }
            break;
    }
}

// Get all announcements for this branch
$stmt = $conn->prepare("
    SELECT a.*, 
           CONCAT(u.name, ' (', u.username, ')') as created_by_name
    FROM announcements a
    JOIN users u ON a.created_by = u.user_id
    WHERE a.branch_id = ?
    ORDER BY a.created_at DESC
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Announcements</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus"></i> New Announcement
        </button>
    </div>
    
    <?php displayFlashMessages(); ?>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="announcementsTable">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($announcements as $announcement): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($announcement['type']) {
                                            'price_update' => 'warning',
                                            'promo' => 'success',
                                            'maintenance' => 'danger',
                                            default => 'info'
                                        };
                                    ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $announcement['type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $announcement['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($announcement['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($announcement['start_date'])); ?></td>
                                <td>
                                    <?php echo $announcement['end_date'] ? date('M d, Y', strtotime($announcement['end_date'])) : 'No End Date'; ?>
                                </td>
                                <td><?php echo htmlspecialchars($announcement['created_by_name']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info view-btn" 
                                            data-announcement='<?php echo htmlspecialchars(json_encode($announcement), ENT_QUOTES, 'UTF-8'); ?>'
                                            data-bs-toggle="modal" data-bs-target="#viewModal">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary edit-btn"
                                            data-announcement='<?php echo htmlspecialchars(json_encode($announcement), ENT_QUOTES, 'UTF-8'); ?>'
                                            data-bs-toggle="modal" data-bs-target="#editModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger delete-btn"
                                            data-id="<?php echo $announcement['announcement_id']; ?>"
                                            data-title="<?php echo htmlspecialchars($announcement['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal">
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

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="announcements.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="type" class="form-label">Type</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="price_update">Price Update</option>
                                    <option value="promo">Promo</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="general">General</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date (Optional)</label>
                                <input type="date" class="form-control" id="end_date" name="end_date"
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="announcements.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="announcement_id" id="edit_announcement_id">
                    
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_message" class="form-label">Message</label>
                        <textarea class="form-control" id="edit_message" name="message" rows="5" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="edit_type" class="form-label">Type</label>
                                <select class="form-select" id="edit_type" name="type" required>
                                    <option value="price_update">Price Update</option>
                                    <option value="promo">Promo</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="general">General</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="edit_start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="edit_end_date" class="form-label">End Date (Optional)</label>
                                <input type="date" class="form-control" id="edit_end_date" name="end_date">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">View Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="fw-bold">Title:</label>
                    <div id="view_title"></div>
                </div>
                
                <div class="mb-3">
                    <label class="fw-bold">Message:</label>
                    <div id="view_message" style="white-space: pre-line;"></div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="fw-bold">Type:</label>
                            <div id="view_type"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="fw-bold">Status:</label>
                            <div id="view_status"></div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="fw-bold">Start Date:</label>
                            <div id="view_start_date"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="fw-bold">End Date:</label>
                            <div id="view_end_date"></div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="fw-bold">Created By:</label>
                    <div id="view_created_by"></div>
                </div>
                
                <div class="mb-3">
                    <label class="fw-bold">Created At:</label>
                    <div id="view_created_at"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="announcements.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="announcement_id" id="delete_announcement_id">
                    <p>Are you sure you want to delete the announcement "<span id="delete_title"></span>"?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    $('#announcementsTable').DataTable({
        order: [[3, 'desc']], // Sort by start date by default
        responsive: true
    });
    
    // Handle view button clicks
    document.querySelectorAll('.view-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const announcement = JSON.parse(this.getAttribute('data-announcement'));
            
            document.getElementById('view_title').textContent = announcement.title;
            document.getElementById('view_message').textContent = announcement.message;
            document.getElementById('view_type').textContent = announcement.type.replace('_', ' ').toUpperCase();
            document.getElementById('view_status').textContent = announcement.status.toUpperCase();
            document.getElementById('view_start_date').textContent = new Date(announcement.start_date).toLocaleDateString();
            document.getElementById('view_end_date').textContent = announcement.end_date ? 
                new Date(announcement.end_date).toLocaleDateString() : 'No End Date';
            document.getElementById('view_created_by').textContent = announcement.created_by_name;
            document.getElementById('view_created_at').textContent = new Date(announcement.created_at).toLocaleString();
        });
    });
    
    // Handle edit button clicks
    document.querySelectorAll('.edit-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const announcement = JSON.parse(this.getAttribute('data-announcement'));
            
            document.getElementById('edit_announcement_id').value = announcement.announcement_id;
            document.getElementById('edit_title').value = announcement.title;
            document.getElementById('edit_message').value = announcement.message;
            document.getElementById('edit_type').value = announcement.type;
            document.getElementById('edit_status').value = announcement.status;
            document.getElementById('edit_start_date').value = announcement.start_date.split(' ')[0];
            if (announcement.end_date) {
                document.getElementById('edit_end_date').value = announcement.end_date.split(' ')[0];
            }
        });
    });
    
    // Handle delete button clicks
    document.querySelectorAll('.delete-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            
            document.getElementById('delete_announcement_id').value = id;
            document.getElementById('delete_title').textContent = title;
        });
    });
    
    // Validate end date is after start date
    function validateDates(startInput, endInput) {
        endInput.addEventListener('change', function() {
            if (startInput.value && this.value && this.value < startInput.value) {
                alert('End date must be after start date');
                this.value = '';
            }
        });
        
        startInput.addEventListener('change', function() {
            if (endInput.value && this.value && endInput.value < this.value) {
                alert('End date must be after start date');
                endInput.value = '';
            }
        });
    }
    
    // Add form validation
    validateDates(
        document.getElementById('start_date'),
        document.getElementById('end_date')
    );
    
    validateDates(
        document.getElementById('edit_start_date'),
        document.getElementById('edit_end_date')
    );
});
</script>

<?php require_once '../includes/footer.php'; ?> 