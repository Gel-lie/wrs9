<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

require_once 'includes/functions.php';
require_once 'includes/db.php';

// Debug connection
try {
    $test_conn = getConnection();
    error_log("Database connection successful");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
}

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: /");
    exit();
}

$error = '';
$success = '';

// Get list of branches
try {
    $stmt = $conn->prepare("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
    $stmt->execute();
    $branches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    error_log("Fetched branches: " . print_r($branches, true));
} catch (Exception $e) {
    error_log("Error fetching branches: " . $e->getMessage());
    $branches = [];
}

// Debug POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST Data received: " . print_r($_POST, true));
    
    try {
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $email = sanitize($_POST['email']);
        $name = sanitize($_POST['name']);
        $contact_number = sanitize($_POST['contact_number']);
        $branch_id = (int)$_POST['branch_id'];
        $barangay_id = (int)$_POST['barangay_id'];
        $sitio_purok = sanitize($_POST['sitio_purok']);

        // Debug sanitized data
        error_log("Sanitized Data: " . print_r([
            'username' => $username,
            'email' => $email,
            'name' => $name,
            'contact_number' => $contact_number,
            'branch_id' => $branch_id,
            'barangay_id' => $barangay_id,
            'sitio_purok' => $sitio_purok
        ], true));

        // Enhanced validation
        if (empty($username) || empty($password) || empty($confirm_password) || empty($email) || 
            empty($name) || empty($contact_number) || empty($branch_id) || empty($barangay_id) || empty($sitio_purok)) {
            $error = 'Please fill in all fields';
            error_log("Validation Error: Empty fields detected");
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
            error_log("Validation Error: Passwords do not match");
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long';
            error_log("Validation Error: Password too short");
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || 
                  !preg_match('/[0-9]/', $password) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $error = 'Password must include uppercase and lowercase letters, numbers, and special characters';
            error_log("Validation Error: Password requirements not met");
        } elseif (!isValidEmail($email)) {
            $error = 'Please enter a valid email address';
            error_log("Validation Error: Invalid email format");
        } elseif (!isValidPhoneNumber($contact_number)) {
            $error = 'Please enter a valid Philippine phone number (e.g., 09123456789)';
            error_log("Validation Error: Invalid phone number format");
        } else {
            try {
                // Start transaction
                $conn->begin_transaction();
                error_log("Started transaction");

                // Check if username exists
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = 'Username already exists';
                    error_log("Error: Username already exists");
                } else {
                    // Check if email exists
                    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $error = 'Email already exists';
                        error_log("Error: Email already exists");
                    } else {
                        // Verify barangay belongs to selected branch
                        $stmt = $conn->prepare("SELECT barangay_id FROM barangays WHERE barangay_id = ? AND branch_id = ?");
                        $stmt->bind_param("ii", $barangay_id, $branch_id);
                        $stmt->execute();
                        if ($stmt->get_result()->num_rows === 0) {
                            $error = 'Invalid barangay selected for the chosen branch';
                            error_log("Error: Invalid barangay-branch combination");
                        } else {
                            // Insert new user with sitio/purok
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $conn->prepare("
                                INSERT INTO users (username, password, email, role, branch_id, barangay_id, name, contact_number, sitio_purok) 
                                VALUES (?, ?, ?, 'customer', ?, ?, ?, ?, ?)
                            ");
                            $stmt->bind_param("sssiisss", $username, $hashed_password, $email, $branch_id, $barangay_id, $name, $contact_number, $sitio_purok);
                            
                            error_log("Attempting to insert user with query: " . $stmt->sqlstate);
                            
                            if ($stmt->execute()) {
                                $user_id = $stmt->insert_id;
                                error_log("User inserted successfully with ID: " . $user_id);
                                
                                // Create loyalty record
                                $stmt = $conn->prepare("INSERT INTO loyalty (customer_id) VALUES (?)");
                                $stmt->bind_param("i", $user_id);
                                if (!$stmt->execute()) {
                                    throw new Exception("Failed to create loyalty record: " . $stmt->error);
                                }
                                error_log("Loyalty record created");
                                
                                // Log activity
                                logActivity($user_id, 'registration', 'New customer registered');
                                error_log("Activity logged");
                                
                                // Commit transaction
                                $conn->commit();
                                error_log("Transaction committed");
                                
                                $success = 'Registration successful! You can now login.';
                                error_log("Registration successful for user: $username");
                            } else {
                                throw new Exception("Failed to insert user: " . $stmt->error);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                error_log("Transaction rolled back due to error: " . $e->getMessage());
                $error = 'An error occurred. Please try again.';
            }
        }
    } catch (Exception $e) {
        error_log("Registration Exception: " . $e->getMessage());
        $error = 'An error occurred. Please try again.';
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Customer Registration</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <br>
                        <a href="login.php" class="alert-link">Click here to login</a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                       required>
                                <div class="invalid-feedback">Please enter your full name</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="contact_number" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="contact_number" name="contact_number" 
                                       value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>" 
                                       pattern="^09[0-9]{9}$"
                                       placeholder="09123456789" required>
                                <div class="form-text">Enter a valid Philippine mobile number</div>
                                <div class="invalid-feedback">Please enter a valid contact number</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       required>
                                <div class="invalid-feedback">Please choose a username</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                       required>
                                <div class="invalid-feedback">Please enter a valid email address</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?:{}|<>])[A-Za-z\d!@#$%^&*(),.?:{}|<>]{8,}$"
                                       required>
                                <div class="form-text">
                                    Password must:
                                    <ul class="mb-0">
                                        <li>Be at least 8 characters long</li>
                                        <li>Include uppercase and lowercase letters</li>
                                        <li>Include numbers</li>
                                        <li>Include special characters</li>
                                    </ul>
                                </div>
                                <div class="invalid-feedback">Please enter a valid password</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <div class="invalid-feedback">Passwords do not match</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="branch_id" class="form-label">Branch</label>
                                <select class="form-select" id="branch_id" name="branch_id" required>
                                    <option value="">Select Branch</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?= $branch['branch_id'] ?>" 
                                                <?= isset($_POST['branch_id']) && $_POST['branch_id'] == $branch['branch_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($branch['branch_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a branch</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="barangay_id" class="form-label">Barangay</label>
                                <select class="form-select" id="barangay_id" name="barangay_id" required>
                                    <option value="">Select Branch First</option>
                                </select>
                                <div class="invalid-feedback">Please select a barangay</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="sitio_purok" class="form-label">Sitio/Purok</label>
                            <input type="text" class="form-control" id="sitio_purok" name="sitio_purok" 
                                   value="<?php echo isset($_POST['sitio_purok']) ? htmlspecialchars($_POST['sitio_purok']) : ''; ?>" 
                                   required>
                            <div class="invalid-feedback">Please enter your Sitio/Purok</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Register</button>
                            <a href="login.php" class="btn btn-light">Already have an account? Login</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    'use strict'
    
    const form = document.querySelector('form');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const branchSelect = document.getElementById('branch_id');
    const barangaySelect = document.getElementById('barangay_id');

    // Form validation
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Check if passwords match
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
            event.preventDefault();
        } else {
            confirmPassword.setCustomValidity('');
        }
        
        // Check if barangay is selected when branch is selected
        if (branchSelect.value && !barangaySelect.value) {
            barangaySelect.setCustomValidity('Please select a barangay');
            event.preventDefault();
        } else {
            barangaySelect.setCustomValidity('');
        }

        form.classList.add('was-validated');
        
        // Log form data for debugging
        if (form.checkValidity()) {
            console.log('Form is valid, submitting...');
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }
        } else {
            console.log('Form validation failed');
        }
    });

    // Password validation
    password.addEventListener('input', validatePassword);
    confirmPassword.addEventListener('input', validatePassword);

    function validatePassword() {
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }

    // Phone number validation
    const phoneInput = document.getElementById('contact_number');
    phoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 0) {
            if (value.length === 11 && value.startsWith('09')) {
                this.setCustomValidity('');
            } else {
                this.setCustomValidity('Please enter a valid Philippine mobile number (e.g., 09123456789)');
            }
        }
    });

    // Dynamic barangay loading
    branchSelect.addEventListener('change', function() {
        const branchId = this.value;
        
        if (branchId) {
            // Enable the barangay select
            barangaySelect.disabled = false;
            
            // Fetch barangays for the selected branch
            fetch(`get_barangays.php?branch_id=${branchId}`)
                .then(response => response.json())
                .then(data => {
                    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                    data.forEach(barangay => {
                        const option = document.createElement('option');
                        option.value = barangay.barangay_id;
                        option.textContent = barangay.barangay_name;
                        barangaySelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    barangaySelect.innerHTML = '<option value="">Error loading barangays</option>';
                });
        } else {
            // Disable and reset the barangay select if no branch is selected
            barangaySelect.disabled = true;
            barangaySelect.innerHTML = '<option value="">Select Branch First</option>';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 