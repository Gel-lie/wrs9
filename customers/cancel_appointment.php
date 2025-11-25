<?php
require_once '../includes/functions.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !hasRole('customer')) {
    header("Location: /login.php");
    exit();
}

require_once '../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectWith('appointments.php', 'Invalid appointment ID', 'danger');
}

$appointment_id = (int)$_GET['id'];

try {
    // Verify the appointment belongs to the user and is scheduled
    $stmt = $conn->prepare("
        SELECT * 
        FROM appointments 
        WHERE appointment_id = ? 
        AND customer_id = ? 
        AND status = 'scheduled'
    ");
    $stmt->bind_param("ii", $appointment_id, $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception("Invalid appointment or already cancelled");
    }
    
    // Cancel the appointment
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET status = 'cancelled' 
        WHERE appointment_id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    
    if ($stmt->execute()) {
        // Log activity
        logActivity($_SESSION['user_id'], 'appointment_cancelled', "Appointment #$appointment_id cancelled");
        redirectWith('appointments.php', 'Appointment cancelled successfully', 'success');
    } else {
        throw new Exception("Failed to cancel appointment");
    }
    
} catch (Exception $e) {
    redirectWith('appointments.php', $e->getMessage(), 'danger');
}
?> 