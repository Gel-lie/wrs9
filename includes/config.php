<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'waterrefillingstation');

// URL Configuration
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];

// Get the directory path
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$baseDir = '';

// If we're in the includes directory, go up one level
if(basename($scriptDir) === 'includes') {
    $baseDir = dirname($scriptDir);
} 
// If we're in a subdirectory (like super_admin), go up one level
elseif(in_array(basename($scriptDir), ['super_admin', 'branch_admins', 'customers'])) {
    $baseDir = dirname($scriptDir);
}
// If we're already at the root level of our application
else {
    $baseDir = $scriptDir;
}

// Ensure the base directory ends with a single slash
$baseDir = rtrim($baseDir, '/');
if (!empty($baseDir)) {
    $baseDir .= '/';
}

// Define the base URL and site URL
define('BASE_URL', $protocol . $host . $baseDir);
define('SITE_URL', rtrim(BASE_URL, '/'));

// Directory Configuration
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// Function to get relative URL
function getRelativeURL($path) {
    return str_replace(BASE_URL, '', $path);
}

// Function to get absolute URL
function getAbsoluteURL($path) {
    return SITE_URL . '/' . ltrim($path, '/');
}

// Function to get current page name
function getCurrentPage() {
    return basename($_SERVER['PHP_SELF']);
}

// Function to check if current page matches
function isCurrentPage($page) {
    return getCurrentPage() === $page;
}
?> 