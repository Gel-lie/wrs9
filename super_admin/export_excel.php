<?php
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and has super admin role
if (!isLoggedIn() || !hasRole('super_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

$conn = getConnection();

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$branch_id = isset($_GET['branch_id']) ? $_GET['branch_id'] : '';

// Prepare branch condition for queries
$branch_condition = $branch_id ? "AND b.branch_id = ?" : "";

// Get sales data
$sql = "SELECT 
    b.branch_name,
    o.order_id,
    o.order_date,
    u.name as customer_name,
    u.contact_number,
    p.product_name,
    od.quantity,
    od.price as unit_price,
    (od.quantity * od.price) as total_amount,
    o.status
FROM orders o
JOIN users u ON o.customer_id = u.user_id
JOIN branches b ON u.branch_id = b.branch_id
JOIN order_details od ON o.order_id = od.order_id
JOIN products p ON od.product_id = p.product_id
WHERE o.order_date BETWEEN ? AND ?
$branch_condition
ORDER BY o.order_date DESC, o.order_id, p.product_name";

$stmt = $conn->prepare($sql);
if ($branch_id) {
    $stmt->bind_param("ssi", $start_date, $end_date, $branch_id);
} else {
    $stmt->bind_param("ss", $start_date, $end_date);
}
$stmt->execute();
$result = $stmt->get_result();

// Set headers for Excel download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create file pointer for output
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add report title and date range
fputcsv($output, ['Sales Report']);
fputcsv($output, ['Date Range:', date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date))]);
fputcsv($output, []); // Empty line

// Add headers
fputcsv($output, [
    'Order ID',
    'Order Date',
    'Branch',
    'Customer Name',
    'Contact Number',
    'Product',
    'Quantity',
    'Unit Price',
    'Total Amount',
    'Status'
]);

// Add data rows
$total_sales = 0;
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        '#' . $row['order_id'],
        date('M d, Y h:i A', strtotime($row['order_date'])),
        $row['branch_name'],
        $row['customer_name'],
        $row['contact_number'],
        $row['product_name'],
        $row['quantity'],
        number_format($row['unit_price'], 2),
        number_format($row['total_amount'], 2),
        ucfirst($row['status'])
    ]);
    
    if ($row['status'] === 'delivered') {
        $total_sales += $row['total_amount'];
    }
}

// Add summary
fputcsv($output, []); // Empty line
fputcsv($output, ['Total Sales:', '', '', '', '', '', '', '', number_format($total_sales, 2)]);

// Close the file pointer
fclose($output);
exit(); 