<?php
require_once 'includes/functions.php';

if (isLoggedIn()) {
    // Log the logout activity
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    
    // Destroy the session
    session_destroy();
}

// Redirect to login page
header("Location: login.php");
exit();
?> 