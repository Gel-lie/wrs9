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

// Get system settings
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'max_appointments_per_day'");
$stmt->execute();
$max_appointments = (int)$stmt->get_result()->fetch_assoc()['setting_value'];

// Process appointment request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug POST data
        error_log("Appointment POST data: " . print_r($_POST, true));

        if (empty($_POST['service_type'])) {
            throw new Exception("Please select a service type");
        }
        if (empty($_POST['appointment_date'])) {
            throw new Exception("Please select an appointment date");
        }
        if (empty($_POST['appointment_time'])) {
            throw new Exception("Please select an appointment time");
        }

        $service_type = $_POST['service_type'];
        $appointment_date = trim($_POST['appointment_date']);
        $appointment_time = trim($_POST['appointment_time']);
        $notes = sanitize($_POST['notes'] ?? '');
        
        error_log("Raw input - Date: $appointment_date, Time: $appointment_time");

        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
            throw new Exception("Invalid date format");
        }

        // Validate time format (HH:MM)
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $appointment_time)) {
            throw new Exception("Invalid time format");
        }

        // Ensure time has leading zeros
        $time_parts = explode(':', $appointment_time);
        $hour = str_pad($time_parts[0], 2, '0', STR_PAD_LEFT);
        $minute = str_pad($time_parts[1], 2, '0', STR_PAD_LEFT);
        $appointment_time = "$hour:$minute";

        error_log("Formatted time: $appointment_time");
        
        // Create datetime string
        $datetime_string = "$appointment_date $appointment_time:00";
        error_log("DateTime string before parsing: $datetime_string");
        
        // Parse the datetime
        $appointment_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $datetime_string);
        if (!$appointment_datetime) {
            $errors = DateTime::getLastErrors();
            error_log("DateTime parsing errors: " . print_r($errors, true));
            throw new Exception("Invalid date/time format");
        }
        
        error_log("Parsed DateTime: " . $appointment_datetime->format('Y-m-d H:i:s'));

        // Validate if appointment is in the future
        $now = new DateTime();
        if ($appointment_datetime <= $now) {
            throw new Exception("Appointment date and time must be in the future");
        }

        // Validate business hours (8 AM to 5 PM)
        $hour = (int)$appointment_datetime->format('H');
        $minute = (int)$appointment_datetime->format('i');
        
        if ($hour < 8 || ($hour === 17 && $minute > 0) || $hour > 17) {
            throw new Exception("Appointments must be scheduled between 8:00 AM and 8:00 PM");
        }

        // Validate 30-minute intervals
        if ($minute % 30 !== 0) {
            throw new Exception("Appointments must be scheduled at 30-minute intervals");
        }
        
        // Check if maximum appointments for the day is reached
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM appointments 
            WHERE DATE(appointment_date) = ? 
            AND branch_id = ? 
            AND status = 'scheduled'
        ");
        $date = $appointment_datetime->format('Y-m-d');
        $stmt->bind_param("si", $date, $user['branch_id']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] >= $max_appointments) {
            throw new Exception("Maximum appointments for this day has been reached. Please select another date.");
        }

        // Format datetime for MySQL
        $mysql_datetime = $appointment_datetime->format('Y-m-d H:i:s');
        
        // Create appointment
        $stmt = $conn->prepare("
            INSERT INTO appointments (
                customer_id,
                branch_id,
                appointment_date,
                service_type,
                notes,
                status
            ) VALUES (?, ?, ?, ?, ?, 'scheduled')
        ");
        
        $stmt->bind_param("iisss", 
            $_SESSION['user_id'], 
            $user['branch_id'], 
            $mysql_datetime, 
            $service_type, 
            $notes
        );
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'appointment_scheduled', "New $service_type appointment scheduled for $mysql_datetime");
            $success = "Your appointment has been scheduled successfully!";
        } else {
            throw new Exception("Failed to schedule appointment: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Appointment creation error: " . $e->getMessage());
    }
}

// Get user's appointments
$stmt = $conn->prepare("
    SELECT * 
    FROM appointments 
    WHERE customer_id = ? 
    ORDER BY appointment_date DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="container">
    <div class="row">
        <!-- Schedule Appointment Form -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title mb-4">Schedule an Appointment</h2>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="service_type" class="form-label">Service Type</label>
                            <select class="form-select" id="service_type" name="service_type" required>
                                <option value="">Choose a service...</option>
                                <option value="maintenance" <?php echo isset($_POST['service_type']) && $_POST['service_type'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="installation" <?php echo isset($_POST['service_type']) && $_POST['service_type'] === 'installation' ? 'selected' : ''; ?>>Installation</option>
                            </select>
                            <div class="invalid-feedback">Please select a service type</div>
                        </div>

                        <div class="mb-3">
                            <label for="appointment_date" class="form-label">Appointment Date</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="appointment_date" 
                                   name="appointment_date"
                                   value="<?php echo isset($_POST['appointment_date']) ? htmlspecialchars($_POST['appointment_date']) : ''; ?>"
                                   min="<?php echo date('Y-m-d'); ?>"
                                   required>
                            <div class="invalid-feedback">Please select an appointment date</div>
                        </div>

                        <div class="mb-3">
                            <label for="appointment_time" class="form-label">Appointment Time</label>
                            <select class="form-select" id="appointment_time" name="appointment_time" required>
                                <option value="">Select a time...</option>
                                <?php
                                // Generate time slots from 8 AM to 5 PM in 30-minute intervals
                                $start = new DateTime('08:00');
                                $end = new DateTime('17:00');
                                $interval = new DateInterval('PT30M');
                                $selected_time = isset($_POST['appointment_time']) ? $_POST['appointment_time'] : '';
                                
                                while ($start <= $end) {
                                    $time_value = $start->format('H:i');
                                    $time_display = $start->format('h:i A');
                                    $selected = ($selected_time === $time_value) ? 'selected' : '';
                                    echo "<option value=\"$time_value\" $selected>$time_display</option>";
                                    $start->add($interval);
                                }
                                ?>
                            </select>
                            <div class="form-text">Business hours: 8:00 AM - 8:00 PM (30-minute intervals)</div>
                            <div class="invalid-feedback">Please select an appointment time</div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" 
                                     id="notes" 
                                     name="notes" 
                                     rows="3"
                                     placeholder="Any specific requirements or concerns"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Schedule Appointment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Appointments List -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title mb-4">Your Appointments</h2>
                    
                    <?php if (empty($appointments)): ?>
                        <p class="text-muted">No appointments found.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($appointments as $appointment): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1">
                                            <?php echo ucfirst($appointment['service_type']); ?>
                                        </h5>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y h:i A', strtotime($appointment['appointment_date'])); ?>
                                        </small>
                                    </div>
                                    
                                    <p class="mb-1">
                                        Status: 
                                        <span class="badge bg-<?php 
                                            echo match($appointment['status']) {
                                                'scheduled' => 'primary',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </p>
                                    
                                    <?php if ($appointment['notes']): ?>
                                        <small class="text-muted">
                                            Notes: <?php echo htmlspecialchars($appointment['notes']); ?>
                                        </small>
                                    <?php endif; ?>
                                    
                                    <?php if ($appointment['status'] === 'scheduled'): ?>
                                        <div class="mt-2">
                                            <a href="cancel_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                Cancel Appointment
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    
    // Form validation
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
    
    // Set min date to today
    const appointmentDate = document.getElementById('appointment_date');
    appointmentDate.min = new Date().toISOString().split('T')[0];
});
</script>

<?php require_once '../includes/footer.php'; ?> 