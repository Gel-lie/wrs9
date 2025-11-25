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

// Handle task actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_task':
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            $due_date = sanitize($_POST['due_date']);
            $task_type = sanitize($_POST['task_type']);
            
            $stmt = $conn->prepare("
                INSERT INTO tasks (branch_id, task_type, title, description, due_date)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issss", $branch_id, $task_type, $title, $description, $due_date);
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'task_create', "Created new task: $title");
                redirectWith('maintenance.php', 'Task added successfully', 'success');
            } else {
                redirectWith('maintenance.php', 'Error adding task', 'danger');
            }
            break;
            
        case 'update_status':
            $task_id = (int)$_POST['task_id'];
            $status = sanitize($_POST['status']);
            
            // Verify task belongs to this branch
            $stmt = $conn->prepare("SELECT * FROM tasks WHERE task_id = ? AND branch_id = ?");
            $stmt->bind_param("ii", $task_id, $branch_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                redirectWith('maintenance.php', 'Task not found', 'danger');
                exit();
            }

            $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE task_id = ?");
            $stmt->bind_param("si", $status, $task_id);
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'task_update', "Updated task #$task_id status to: $status");
                redirectWith('maintenance.php', 'Task status updated successfully', 'success');
            } else {
                redirectWith('maintenance.php', 'Error updating task status', 'danger');
            }
            break;
            
        case 'delete_task':
            $task_id = (int)$_POST['task_id'];
            
            // Verify task belongs to this branch
            $stmt = $conn->prepare("SELECT * FROM tasks WHERE task_id = ? AND branch_id = ?");
            $stmt->bind_param("ii", $task_id, $branch_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                redirectWith('maintenance.php', 'Task not found', 'danger');
                exit();
            }

            $stmt = $conn->prepare("DELETE FROM tasks WHERE task_id = ?");
            $stmt->bind_param("i", $task_id);
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'task_delete', "Deleted task #$task_id");
                redirectWith('maintenance.php', 'Task deleted successfully', 'success');
            } else {
                redirectWith('maintenance.php', 'Error deleting task', 'danger');
            }
            break;
    }
}

// Get filters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';

// Get tasks
$query = "
    SELECT * FROM tasks 
    WHERE branch_id = ?
";

$params = [$branch_id];
$types = "i";

if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($type_filter) {
    $query .= " AND task_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if ($date_filter) {
    $query .= " AND DATE(due_date) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$query .= " ORDER BY due_date ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get task statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_tasks,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_tasks,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_tasks,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tasks,
        COUNT(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 END) as overdue_tasks
    FROM tasks
    WHERE branch_id = ?
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Maintenance Tasks</h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                <i class="fas fa-plus"></i> Add Task
            </button>
            <button type="button" class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Print List
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Tasks</h6>
                    <h2 class="mb-0"><?= $stats['total_tasks'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Pending</h6>
                    <h2 class="mb-0"><?= $stats['pending_tasks'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">In Progress</h6>
                    <h2 class="mb-0"><?= $stats['in_progress_tasks'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Completed</h6>
                    <h2 class="mb-0"><?= $stats['completed_tasks'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title">Overdue</h6>
                    <h2 class="mb-0"><?= $stats['overdue_tasks'] ?></h2>
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
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label">Type</label>
                    <select class="form-select" name="type" id="type">
                        <option value="">All Types</option>
                        <option value="maintenance" <?= $type_filter === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                        <option value="compliance" <?= $type_filter === 'compliance' ? 'selected' : '' ?>>Compliance</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date" class="form-label">Due Date</label>
                    <input type="date" class="form-control" name="date" id="date" value="<?= $date_filter ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="maintenance.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tasks List -->
    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="tasksTable">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                        <tr class="<?= isOverdue($task) ? 'table-danger' : '' ?>">
                            <td><?= htmlspecialchars($task['title']) ?></td>
                            <td><?= ucfirst($task['task_type']) ?></td>
                            <td><?= htmlspecialchars($task['description']) ?></td>
                            <td>
                                <?= date('M d, Y', strtotime($task['due_date'])) ?>
                                <?php if (isOverdue($task)): ?>
                                <span class="badge bg-danger">Overdue</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= getStatusColor($task['status']) ?>">
                                    <?= ucwords(str_replace('_', ' ', $task['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" 
                                        class="btn btn-sm btn-primary"
                                        onclick="updateStatus(<?= $task['task_id'] ?>, '<?= $task['status'] ?>')">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <button type="button" 
                                        class="btn btn-sm btn-danger"
                                        onclick="deleteTask(<?= $task['task_id'] ?>)">
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

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="maintenance.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_task">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" id="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="task_type" class="form-label">Type</label>
                        <select class="form-select" name="task_type" id="task_type" required>
                            <option value="maintenance">Maintenance</option>
                            <option value="compliance">Compliance</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="due_date" class="form-label">Due Date</label>
                        <input type="datetime-local" class="form-control" name="due_date" id="due_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Task Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="maintenance.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="task_id" id="update_task_id">
                    
                    <div class="mb-3">
                        <label for="update_status" class="form-label">Status</label>
                        <select class="form-select" name="status" id="update_status" required>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
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

<!-- Delete Task Modal -->
<div class="modal fade" id="deleteTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="maintenance.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_task">
                    <input type="hidden" name="task_id" id="delete_task_id">
                    <p>Are you sure you want to delete this task? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize DataTables
$(document).ready(function() {
    $('#tasksTable').DataTable({
        order: [[3, 'asc']], // Sort by due date by default
        pageLength: 25,
        columnDefs: [
            { orderable: false, targets: -1 } // Disable sorting on actions column
        ]
    });

    // Set minimum date for due date input
    document.getElementById('due_date').min = new Date().toISOString().slice(0, 16);
});

// Function to update task status
function updateStatus(taskId, currentStatus) {
    const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
    document.getElementById('update_task_id').value = taskId;
    document.getElementById('update_status').value = currentStatus;
    modal.show();
}

// Function to delete task
function deleteTask(taskId) {
    const modal = new bootstrap.Modal(document.getElementById('deleteTaskModal'));
    document.getElementById('delete_task_id').value = taskId;
    modal.show();
}
</script>

<?php
// Helper function to check if task is overdue
function isOverdue($task) {
    return $task['status'] !== 'completed' && strtotime($task['due_date']) < time();
}

// Helper function to get status color
function getStatusColor($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'in_progress': return 'info';
        case 'completed': return 'success';
        default: return 'secondary';
    }
}

require_once '../includes/footer.php';
?> 