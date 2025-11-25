<?php
require_once 'includes/db.php';

// Ensure branch_id is provided and is numeric
if (!isset($_GET['branch_id']) || !is_numeric($_GET['branch_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid branch ID']);
    exit();
}

$branch_id = (int)$_GET['branch_id'];

// Get barangays for the selected branch
$conn = getConnection();
$stmt = $conn->prepare("
    SELECT barangay_id, barangay_name 
    FROM barangays 
    WHERE branch_id = ? 
    ORDER BY barangay_name
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$result = $stmt->get_result();

// Convert to array
$barangays = [];
while ($row = $result->fetch_assoc()) {
    $barangays[] = [
        'barangay_id' => $row['barangay_id'],
        'barangay_name' => $row['barangay_name']
    ];
}

// Set JSON header and output
header('Content-Type: application/json');
echo json_encode($barangays);
?> 