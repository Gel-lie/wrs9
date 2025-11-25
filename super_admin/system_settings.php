<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

// Check if user is logged in and has super admin role
if (!isLoggedIn() || !hasRole('super_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create settings table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS system_settings (
        setting_id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->query($sql);

    // Update settings
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $setting_key = substr($key, 8); // Remove 'setting_' prefix
            $setting_value = sanitize($value);
            
            // Insert or update setting
            $stmt = $conn->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->bind_param("sss", $setting_key, $setting_value, $setting_value);
            $stmt->execute();
        }
    }

    logActivity($_SESSION['user_id'], 'settings_update', "Updated system settings");
    redirectWith('system_settings.php', 'Settings updated successfully', 'success');
}

// Get current settings
$settings = [];
$sql = "SELECT setting_key, setting_value FROM system_settings";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Default settings if not set
$default_settings = [
    'company_name' => 'Water Refilling Station',
    'contact_email' => '',
    'contact_phone' => '',
    'business_hours' => '8:00 AM - 8:00 PM',
    'minimum_order' => '1',
    'delivery_fee' => '50',
    'loyalty_points_ratio' => '1', // Points per peso spent
    'maintenance_alert_days' => '30', // Days before maintenance due
    'low_stock_threshold' => '10',
    'max_appointments_per_day' => '10',
    'sms_notifications' => '0',
    'email_notifications' => '1'
];

// Merge default with saved settings
$settings = array_merge($default_settings, $settings);
?>

<div class="container-fluid py-4">
    <h1 class="h3 mb-4">System Settings</h1>

    <?= displayFlashMessage() ?>

    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">General Settings</h6>
                </div>
                <div class="card-body">
                    <form action="system_settings.php" method="post">
                        <!-- Company Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="mb-3">Company Information</h5>
                                <div class="form-group">
                                    <label>Company Name</label>
                                    <input type="text" class="form-control" name="setting_company_name"
                                           value="<?= htmlspecialchars($settings['company_name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Contact Email</label>
                                    <input type="email" class="form-control" name="setting_contact_email"
                                           value="<?= htmlspecialchars($settings['contact_email']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Contact Phone</label>
                                    <input type="text" class="form-control" name="setting_contact_phone"
                                           value="<?= htmlspecialchars($settings['contact_phone']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Business Hours</label>
                                    <input type="text" class="form-control" name="setting_business_hours"
                                           value="<?= htmlspecialchars($settings['business_hours']) ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-3">Order Settings</h5>
                                <div class="form-group">
                                    <label>Minimum Order Quantity</label>
                                    <input type="number" class="form-control" name="setting_minimum_order"
                                           value="<?= htmlspecialchars($settings['minimum_order']) ?>" min="1">
                                </div>
                                <div class="form-group">
                                    <label>Delivery Fee (₱)</label>
                                    <input type="number" class="form-control" name="setting_delivery_fee"
                                           value="<?= htmlspecialchars($settings['delivery_fee']) ?>" min="0">
                                </div>
                                <div class="form-group">
                                    <label>Low Stock Threshold</label>
                                    <input type="number" class="form-control" name="setting_low_stock_threshold"
                                           value="<?= htmlspecialchars($settings['low_stock_threshold']) ?>" min="1">
                                </div>
                                <div class="form-group">
                                    <label>Maximum Appointments Per Day</label>
                                    <input type="number" class="form-control" name="setting_max_appointments_per_day"
                                           value="<?= htmlspecialchars($settings['max_appointments_per_day']) ?>" min="1">
                                </div>
                            </div>
                        </div>

                        <!-- Loyalty Program Settings -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="mb-3">Loyalty Program</h5>
                                <div class="form-group">
                                    <label>Points per Peso Spent</label>
                                    <input type="number" class="form-control" name="setting_loyalty_points_ratio"
                                           value="<?= htmlspecialchars($settings['loyalty_points_ratio']) ?>" 
                                           step="0.01" min="0">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-3">Maintenance</h5>
                                <div class="form-group">
                                    <label>Maintenance Alert (Days Before Due)</label>
                                    <input type="number" class="form-control" name="setting_maintenance_alert_days"
                                           value="<?= htmlspecialchars($settings['maintenance_alert_days']) ?>" min="1">
                                </div>
                            </div>
                        </div>

                        <!-- Notification Settings -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="mb-3">Notifications</h5>
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" 
                                               id="smsNotifications" name="setting_sms_notifications" 
                                               value="1" <?= $settings['sms_notifications'] ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="smsNotifications">
                                            Enable SMS Notifications
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" 
                                               id="emailNotifications" name="setting_email_notifications" 
                                               value="1" <?= $settings['email_notifications'] ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="emailNotifications">
                                            Enable Email Notifications
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 