<?php
require_once '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Check if user is logged in and is a super admin
if (!isLoggedIn() || !hasRole('super_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $branch_id = (int)$_POST['branch_id'];
                $title = $_POST['title'];
                $description = $_POST['description'];
                
                // Convert datetime strings to PHP DateTime objects with PH timezone
                $start_date = DateTime::createFromFormat('Y-m-d H:i', $_POST['start_date'], new DateTimeZone('Asia/Manila'));
                $end_date = DateTime::createFromFormat('Y-m-d H:i', $_POST['end_date'], new DateTimeZone('Asia/Manila'));
                
                if (!$start_date || !$end_date) {
                    redirectWith('maintenance.php', 'Invalid date format', 'danger');
                    exit;
                }
                
                // Format for MySQL
                $start_date_sql = $start_date->format('Y-m-d H:i:s');
                $end_date_sql = $end_date->format('Y-m-d H:i:s');
                
                // Insert maintenance schedule
                $stmt = $conn->prepare("
                    INSERT INTO maintenance_schedule 
                    (branch_id, title, description, start_date, end_date, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("issssi", $branch_id, $title, $description, $start_date_sql, $end_date_sql, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $schedule_id = $conn->insert_id;
                    
                    // Notify branch admin
                    $stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, title, message, type)
                        SELECT 
                            u.user_id,
                            'New Maintenance Schedule',
                            CONCAT(
                                'A new maintenance has been scheduled for your branch from ',
                                DATE_FORMAT(?, '%M %d, %Y %h:%i %p'),
                                ' to ',
                                DATE_FORMAT(?, '%M %d, %Y %h:%i %p'),
                                '.\n\nDetails: ',
                                ?,
                                '\n\nSchedule ID: ',
                                ?
                            ),
                            'maintenance'
                        FROM users u
                        WHERE u.role = 'branch_admin'
                        AND u.branch_id = ?
                    ");
                    $stmt->bind_param("sssii", $start_date_sql, $end_date_sql, $description, $schedule_id, $branch_id);
                    $stmt->execute();
                    
                    redirectWith('maintenance.php', 'Maintenance schedule created successfully', 'success');
                } else {
                    redirectWith('maintenance.php', 'Error creating maintenance schedule', 'danger');
                }
                break;
                
            case 'update_status':
                $schedule_id = (int)$_POST['schedule_id'];
                $new_status = $_POST['status'];
                
                // Validate the new status
                $valid_statuses = ['scheduled', 'in_progress', 'completed', 'cancelled'];
                if (!in_array($new_status, $valid_statuses)) {
                    redirectWith('maintenance.php', 'Invalid status', 'danger');
                    exit;
                }
                
                // Get current status
                $stmt = $conn->prepare("SELECT status FROM maintenance_schedule WHERE schedule_id = ?");
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $current_status = $result->fetch_assoc()['status'];
                
                // Validate status transition
                $valid_transition = match($current_status) {
                    'scheduled' => ['in_progress', 'cancelled'],
                    'in_progress' => ['completed'],
                    default => []
                };
                
                if (!in_array($new_status, $valid_transition)) {
                    redirectWith('maintenance.php', 'Invalid status transition', 'danger');
                    exit;
                }
                
                // Update maintenance status
                $stmt = $conn->prepare("
                    UPDATE maintenance_schedule 
                    SET status = ? 
                    WHERE schedule_id = ?
                ");
                $stmt->bind_param("si", $new_status, $schedule_id);
                
                if ($stmt->execute()) {
                    // Get maintenance details for notification
                    $stmt = $conn->prepare("
                        SELECT ms.*, b.branch_id, b.branch_name
                        FROM maintenance_schedule ms
                        JOIN branches b ON ms.branch_id = b.branch_id
                        WHERE ms.schedule_id = ?
                    ");
                    $stmt->bind_param("i", $schedule_id);
                    $stmt->execute();
                    $schedule = $stmt->get_result()->fetch_assoc();
                    
                    if ($schedule) {
                        // Prepare notification message
                        $status_message = match($new_status) {
                            'in_progress' => 'has started',
                            'completed' => 'has been completed',
                            'cancelled' => 'has been cancelled',
                            default => 'has been updated'
                        };
                        
                        $notification_title = 'Maintenance ' . ucfirst($status_message);
                        $notification_message = sprintf(
                            "Maintenance schedule for %s %s.\n\nDetails: %s\nPeriod: %s to %s",
                            $schedule['branch_name'],
                            $status_message,
                            $schedule['description'],
                            date('M d, Y h:i A', strtotime($schedule['start_date'])),
                            date('M d, Y h:i A', strtotime($schedule['end_date']))
                        );
                        
                        // Send notifications to branch admin only
                        $stmt = $conn->prepare("
                            INSERT INTO notifications (user_id, title, message, type)
                            SELECT 
                                u.user_id,
                                ?,
                                ?,
                                'maintenance'
                            FROM users u
                            WHERE u.role = 'branch_admin'
                            AND u.branch_id = ?
                        ");
                        $stmt->bind_param("ssi", $notification_title, $notification_message, $schedule['branch_id']);
                        $stmt->execute();
                    }
                    
                    redirectWith('maintenance.php', 'Maintenance status updated successfully', 'success');
                } else {
                    redirectWith('maintenance.php', 'Error updating maintenance status', 'danger');
                }
                break;
        }
    }
}

// Get all branches
$branches = $conn->query("SELECT * FROM branches ORDER BY branch_name")->fetch_all(MYSQLI_ASSOC);

// Get all maintenance schedules with proper datetime formatting
$maintenance_schedules = $conn->query("
    SELECT 
        ms.*,
        b.branch_name,
        CONCAT(u.name, ' (', u.username, ')') as created_by_name,
        DATE_FORMAT(ms.start_date, '%Y-%m-%d %H:%i') as start_date_formatted,
        DATE_FORMAT(ms.end_date, '%Y-%m-%d %H:%i') as end_date_formatted
    FROM maintenance_schedule ms
    JOIN branches b ON ms.branch_id = b.branch_id
    JOIN users u ON ms.created_by = u.user_id
    ORDER BY ms.start_date DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3">Maintenance Management</h1>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleMaintenanceModal">
                <i class="fas fa-plus"></i> Schedule Maintenance
            </button>
        </div>
    </div>

    <!-- Active Maintenance -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tools"></i> Active Maintenance
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Branch</th>
                                    <th>Title</th>
                                    <th>Period</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $active_found = false;
                                foreach ($maintenance_schedules as $schedule):
                                    if ($schedule['status'] === 'completed' || $schedule['status'] === 'cancelled') continue;
                                    $active_found = true;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($schedule['branch_name']) ?></td>
                                    <td><?= htmlspecialchars($schedule['title']) ?></td>
                                    <td>
                                        <?= date('M d, Y h:i A', strtotime($schedule['start_date'])) ?><br>
                                        <small class="text-muted">to</small><br>
                                        <?= date('M d, Y h:i A', strtotime($schedule['end_date'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $schedule['status'] === 'in_progress' ? 'info' : 'warning' ?>">
                                            <?= ucfirst($schedule['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-info"
                                                    onclick="viewSchedule(<?= htmlspecialchars(json_encode($schedule)) ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <?php if ($schedule['status'] === 'scheduled'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-primary"
                                                    onclick="updateMaintenanceStatus(<?= $schedule['schedule_id'] ?>, 'in_progress')">
                                                <i class="fas fa-play"></i> Start
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger"
                                                    onclick="updateMaintenanceStatus(<?= $schedule['schedule_id'] ?>, 'cancelled')">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                            <?php elseif ($schedule['status'] === 'in_progress'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-success"
                                                    onclick="updateMaintenanceStatus(<?= $schedule['schedule_id'] ?>, 'completed')">
                                                <i class="fas fa-check"></i> Complete
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (!$active_found): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No active maintenance schedules</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance History -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history"></i> Maintenance History
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="maintenanceTable">
                            <thead>
                                <tr>
                                    <th>Branch</th>
                                    <th>Title</th>
                                    <th>Period</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maintenance_schedules as $schedule): ?>
                                <tr>
                                    <td><?= htmlspecialchars($schedule['branch_name']) ?></td>
                                    <td><?= htmlspecialchars($schedule['title']) ?></td>
                                    <td>
                                        <?= date('M d, Y h:i A', strtotime($schedule['start_date'])) ?><br>
                                        <small class="text-muted">to</small><br>
                                        <?= date('M d, Y h:i A', strtotime($schedule['end_date'])) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = match($schedule['status']) {
                                            'scheduled' => 'warning',
                                            'in_progress' => 'info',
                                            'completed' => 'success',
                                            'cancelled' => 'danger',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $status_class ?>">
                                            <?= ucfirst($schedule['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($schedule['created_by_name']) ?></small><br>
                                        <small class="text-muted">
                                            <?= date('M d, Y h:i A', strtotime($schedule['created_at'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <button type="button" 
                                                class="btn btn-sm btn-info"
                                                onclick="viewSchedule(<?= htmlspecialchars(json_encode($schedule)) ?>)">
                                            <i class="fas fa-eye"></i> View
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
    </div>
</div>

<!-- Schedule Maintenance Modal -->
<div class="modal fade" id="scheduleMaintenanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="post">
                <input type="hidden" name="action" value="create">
                
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Maintenance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-select" required>
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['branch_id'] ?>">
                                <?= htmlspecialchars($branch['branch_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Start Date & Time (PH Time)</label>
                                <input type="text" name="start_date" class="form-control datetime-picker" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">End Date & Time (PH Time)</label>
                                <input type="text" name="end_date" class="form-control datetime-picker" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Maintenance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Schedule Modal -->
<div class="modal fade" id="viewScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Content will be dynamically inserted here -->
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
// Initialize Flatpickr datetime pickers
document.addEventListener('DOMContentLoaded', function() {
    flatpickr('.datetime-picker', {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        time_24hr: false,
        minDate: "today",
        defaultHour: new Date().getHours(),
        defaultMinute: new Date().getMinutes(),
        allowInput: true
    });
});

// Initialize DataTables
$(document).ready(function() {
    $('#maintenanceTable').DataTable({
        order: [[2, 'desc']], // Sort by period descending
        pageLength: 10
    });
});

function updateMaintenanceStatus(scheduleId, newStatus) {
    const statusText = {
        'in_progress': 'start',
        'completed': 'complete',
        'cancelled': 'cancel'
    };
    
    if (confirm(`Are you sure you want to ${statusText[newStatus]} this maintenance schedule?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'maintenance.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'update_status';
        
        const scheduleInput = document.createElement('input');
        scheduleInput.type = 'hidden';
        scheduleInput.name = 'schedule_id';
        scheduleInput.value = scheduleId;
        
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'status';
        statusInput.value = newStatus;
        
        form.appendChild(actionInput);
        form.appendChild(scheduleInput);
        form.appendChild(statusInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function viewSchedule(schedule) {
    // Format dates for display in PH timezone
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
        hour12: true,
        timeZone: 'Asia/Manila'
    };
    
    const startDate = new Date(schedule.start_date).toLocaleString('en-US', options);
    const endDate = new Date(schedule.end_date).toLocaleString('en-US', options);
    
    const content = `
        <div class="modal-header">
            <h5 class="modal-title">Maintenance Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <dl class="row">
                <dt class="col-sm-4">Branch:</dt>
                <dd class="col-sm-8">${schedule.branch_name}</dd>
                
                <dt class="col-sm-4">Title:</dt>
                <dd class="col-sm-8">${schedule.title}</dd>
                
                <dt class="col-sm-4">Description:</dt>
                <dd class="col-sm-8">${schedule.description}</dd>
                
                <dt class="col-sm-4">Start Date:</dt>
                <dd class="col-sm-8">${startDate}</dd>
                
                <dt class="col-sm-4">End Date:</dt>
                <dd class="col-sm-8">${endDate}</dd>
                
                <dt class="col-sm-4">Status:</dt>
                <dd class="col-sm-8">
                    <span class="badge bg-${schedule.status === 'in_progress' ? 'info' : 'warning'}">
                        ${schedule.status.charAt(0).toUpperCase() + schedule.status.slice(1)}
                    </span>
                </dd>
                
                <dt class="col-sm-4">Created By:</dt>
                <dd class="col-sm-8">${schedule.created_by_name}</dd>
            </dl>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('viewScheduleModal'));
    document.querySelector('#viewScheduleModal .modal-content').innerHTML = content;
    modal.show();
}

// Validate dates when scheduling maintenance
document.querySelector('#scheduleMaintenanceModal form').addEventListener('submit', function(e) {
    const startDate = new Date(this.start_date.value);
    const endDate = new Date(this.end_date.value);
    
    if (endDate <= startDate) {
        e.preventDefault();
        alert('End date must be after start date');
        return;
    }
    
    const now = new Date();
    if (startDate < now) {
        e.preventDefault();
        alert('Start date cannot be in the past');
        return;
    }
});
</script>

<?php require_once '../includes/footer.php'; ?> 