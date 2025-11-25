<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a branch admin
if (!isLoggedIn() || !hasRole('branch_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

$conn = getConnection();
$user = getUserInfo($_SESSION['user_id']);
$branch_id = $user['branch_id'];

// Get date range
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get branch name
$stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ?");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$branch_name = $stmt->get_result()->fetch_assoc()['branch_name'];

// === SALES SUMMARY ===
$sql = "SELECT
    COUNT(o.order_id) as total_orders,
    SUM(o.total_amount) as total_revenue,
    COUNT(DISTINCT o.customer_id) as unique_customers,
    COUNT(CASE WHEN o.status = 'delivered' THEN 1 END) as completed_orders
FROM orders o
JOIN users u ON o.customer_id = u.user_id
WHERE u.branch_id = ? AND o.order_date BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $branch_id, $start_date, $end_date);
$stmt->execute();
$sales_summary = $stmt->get_result()->fetch_assoc();

// === PRODUCT PERFORMANCE ===
$sql = "SELECT
    p.product_name,
    COUNT(od.order_detail_id) as order_count,
    SUM(od.quantity) as total_quantity,
    SUM(od.quantity * od.price) as total_revenue
FROM order_details od
JOIN orders o ON od.order_id = o.order_id
JOIN products p ON od.product_id = p.product_id
JOIN users u ON o.customer_id = u.user_id
WHERE u.branch_id = ? AND o.order_date BETWEEN ? AND ?
GROUP BY p.product_id
ORDER BY total_quantity DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $branch_id, $start_date, $end_date);
$stmt->execute();
$product_performance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// === REFILL REQUEST PERFORMANCE ===
$all_container_types = [
    'Round (5 gallons)' => 'round_5g',
    'Slim (5 gallons)' => 'slim_5g',
    '10 liters' => '10l',
    '8 liters' => '8l',
    '7 liters' => '7l',
    '6.6 liters' => '6.6l',
    '6 liters' => '6l',
    '5 liters' => '5l'
];

$container_prices = [
    'round_5g' => 30,
    'slim_5g' => 30,
    '10l' => 20,
    '8l' => 16,
    '7l' => 14,
    '6.6l' => 13,
    '6l' => 12,
    '5l' => 10
];

$refill_counts = array_fill_keys(array_keys($all_container_types), 0);
$refill_revenue = array_fill_keys(array_keys($all_container_types), 0);

$sql = "SELECT o.notes, od.quantity
        FROM orders o
        JOIN order_details od ON o.order_id = od.order_id
        JOIN users u ON o.customer_id = u.user_id
        WHERE u.branch_id = ? AND od.product_id = 1 AND o.order_date BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $branch_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if (preg_match('/Container:\s*(\w+)/', $row['notes'], $matches)) {
        $container_key = $matches[1];
        if (isset($refill_counts[$container_key])) {
            $refill_counts[$container_key] += $row['quantity'];
            $refill_revenue[$container_key] += $row['quantity'] * $container_prices[$container_key];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Branch Performance Report</title>
<style>
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #f5f7fa;
    margin: 0;
    padding: 40px;
}

.report-wrapper {
    background: white;
    max-width: 900px;
    margin: auto;
    padding: 40px 50px;
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0,0,0,0.1);
}

/* HEADER STYLES */
.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #e0e0e0;
    padding-bottom: 15px;
    margin-bottom: 30px;
    margin-top: -10px;
}

.header-left {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    text-align: left;
    line-height: 1.2;
}

.header-left img {
    height: 50px;
    width: auto;
    display: block;
    margin-bottom: 2px;
}

.header-left h4 {
    margin: 0;
    font-size: 18px;
    color: #333;
    font-weight: 700;
    line-height: 1.1;
}

.header-left p {
    margin: 1px 0;
    font-size: 9px;
    color: #333;
    line-height: 1.1;
}

.header-left small {
    font-size: 9px;
    color: #777;
    margin-top: 1px;
    line-height: 1;
}

.header-right {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    justify-content: flex-end;
    margin-top: 0;
    padding-top: 0;
}

.header-right img {
    display: block;
    height: 40px;
    width: auto;
    border-radius: 8px;
    margin-top: -10px;
}

/* PRINT BUTTON */
.print-btn {
    text-align: right;
    margin-bottom: 20px;
}
.print-btn button {
    background: #0078d7;
    color: white;
    border: none;
    padding: 8px 14px;
    border-radius: 5px;
    cursor: pointer;
}
.print-btn button:hover {
    background: #005ea6;
}

/* TABLES AND TITLES */
.section-title {
    background: #0078d7;
    color: white;
    padding: 10px;
    text-align: center;
    margin-top: 40px;
    border-radius: 5px;
}
h3 {
    margin-top: 25px;
    color: #333;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 14px;
}
th, td {
    border: 1px solid #ddd;
    padding: 8px 10px;
    text-align: center;
}
th {
    background: #e9f3ff;
}

@media print {
    body { background: white; padding: 0; }
    .report-wrapper { box-shadow: none; border-radius: 0; }
    .print-btn { display: none; }
}
</style>
</head>
<body>

<div class="report-wrapper">

<header>
    <div class="header-container">
        <!-- LEFT SIDE: PureFlow Info -->
        <div class="header-left">
            <img src="../uploads/pureflow_logo.png" alt="PureFlow Logo">
            <h4>PureFlow Water Refilling Station</h4>
            <p><?= htmlspecialchars($branch_name) ?> Branch</p>
            <small>Performance Report (<?= htmlspecialchars($start_date) ?> - <?= htmlspecialchars($end_date) ?>)</small>
        </div>

        <!-- RIGHT SIDE: Logos -->
        <div class="header-right">
            <img src="../uploads/calapan.png" alt="Calapan Logo">
            <img src="../uploads/oriental.png" alt="Oriental Mindoro Logo">
        </div>
    </div>
</header>

<div class="print-btn">
    <button onclick="window.print()">🖨️ Print Report</button>
</div>

<div class="section-title">Sales Summary</div>
<table>
    <thead>
        <tr>
            <th>Branch</th>
            <th>Total Orders</th>
            <th>Total Revenue (₱)</th>
            <th>Average Order Value (₱)</th>
            <th>Unique Customers</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><?= htmlspecialchars($branch_name) ?></td>
            <td><?= $sales_summary['total_orders'] ?></td>
            <td><?= number_format($sales_summary['total_revenue'], 2) ?></td>
            <td><?= number_format(($sales_summary['total_orders'] > 0 ? $sales_summary['total_revenue'] / $sales_summary['total_orders'] : 0), 2) ?></td>
            <td><?= $sales_summary['unique_customers'] ?></td>
        </tr>
    </tbody>
</table>

<div class="section-title">Product Performance</div>
<table>
    <thead>
        <tr>
            <th>Product</th>
            <th>Orders</th>
            <th>Quantity Sold</th>
            <th>Revenue (₱)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($product_performance as $product): ?>
        <tr>
            <td><?= htmlspecialchars($product['product_name']) ?></td>
            <td><?= $product['order_count'] ?></td>
            <td><?= $product['total_quantity'] ?></td>
            <td><?= number_format($product['total_revenue'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="section-title">Refill Request Performance</div>
<table>
    <thead>
        <tr>
            <th>Container Type</th>
            <th>Count</th>
            <th>Total Revenue (₱)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($refill_counts as $container_type => $count): ?>
        <tr>
            <td><?= htmlspecialchars($container_type) ?></td>
            <td><?= $count ?></td>
            <td><?= number_format($refill_revenue[$container_type], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</div>

</body>
</html>
