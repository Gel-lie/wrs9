<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

$conn = getConnection();

// Get date range
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// === FETCH BRANCHES ===
$stmt = $conn->prepare("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
$stmt->execute();
$branches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// === SALES SUMMARY ===
$sql = "SELECT 
    b.branch_id,
    b.branch_name,
    COUNT(o.order_id) as total_orders,
    SUM(o.total_amount) as total_revenue,
    COUNT(DISTINCT o.customer_id) as unique_customers
FROM branches b
LEFT JOIN users u ON b.branch_id = u.branch_id
LEFT JOIN orders o ON u.user_id = o.customer_id AND o.order_date BETWEEN ? AND ?
GROUP BY b.branch_id";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$sales_summary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// === PRODUCT PERFORMANCE ===
$sql = "SELECT
    b.branch_id,
    b.branch_name,
    p.product_name,
    COUNT(od.order_detail_id) as order_count,
    SUM(od.quantity) as total_quantity,
    SUM(od.quantity * od.price) as total_revenue
FROM branches b
INNER JOIN inventory i ON b.branch_id = i.branch_id
INNER JOIN products p ON i.product_id = p.product_id
LEFT JOIN users u ON b.branch_id = u.branch_id
LEFT JOIN orders o ON u.user_id = o.customer_id AND o.order_date BETWEEN ? AND ?
LEFT JOIN order_details od ON o.order_id = od.order_id AND od.product_id = p.product_id
GROUP BY b.branch_id, p.product_id
ORDER BY b.branch_id, total_quantity DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$product_by_branch = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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

$branch_refills = [];
foreach ($branches as $branch) {
    $branch_refills[$branch['branch_id']] = [
        'branch_name' => $branch['branch_name'],
        'containers' => []
    ];
    foreach ($all_container_types as $display_name => $key) {
        $branch_refills[$branch['branch_id']]['containers'][$key] = [
            'name' => $display_name,
            'count' => 0,
            'revenue' => 0
        ];
    }
}

$sql = "SELECT u.branch_id, o.notes, od.quantity, od.price
        FROM orders o
        JOIN order_details od ON o.order_id = od.order_id
        JOIN users u ON o.customer_id = u.user_id
        WHERE od.product_id = 1 AND o.order_date BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $branch_id = $row['branch_id'];
    $notes = $row['notes'];
    $quantity = $row['quantity'];
    $price = $row['price'];

    if (preg_match('/Container:\s*(\w+)/', $notes, $matches)) {
        $container_key = $matches[1];
        if (isset($branch_refills[$branch_id]['containers'][$container_key])) {
            $branch_refills[$branch_id]['containers'][$container_key]['count'] += $quantity;
            $branch_refills[$branch_id]['containers'][$container_key]['revenue'] += $quantity * $price;
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
    margin-bottom: 2px; /* ✅ tightened spacing between logo and text */
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
    align-items: flex-start; /* Aligns the images to the top */
    justify-content: flex-end;
    margin-top: 0; /* Removes top margin */
    padding-top: 0; /* Removes top padding */
}

.header-right img {
    display: block;
    height: 40px;
    width: auto;
    border-radius: 8px;
    margin-top: -10px; /* Ensures no image offset */
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
            <p>Lazareto Branch | Calero Branch</p>
            <p>0917-795-6906 | 0917-714-1537</p>
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
        <?php foreach ($sales_summary as $summary): ?>
        <tr>
            <td><?= htmlspecialchars($summary['branch_name']) ?></td>
            <td><?= $summary['total_orders'] ?></td>
            <td><?= number_format($summary['total_revenue'], 2) ?></td>
            <td><?= number_format(($summary['total_orders'] > 0 ? $summary['total_revenue'] / $summary['total_orders'] : 0), 2) ?></td>
            <td><?= $summary['unique_customers'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="section-title">Product Performance by Branch</div>
<?php
$current_branch = null;
foreach ($product_by_branch as $product):
    if ($current_branch !== $product['branch_name']):
        if ($current_branch !== null) echo "</tbody></table>";
        $current_branch = $product['branch_name'];
        echo "<h3>{$current_branch}</h3>";
        echo "<table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Orders</th>
                        <th>Quantity Sold</th>
                        <th>Revenue (₱)</th>
                    </tr>
                </thead>
                <tbody>";
    endif;
?>
<tr>
    <td><?= htmlspecialchars($product['product_name']) ?></td>
    <td><?= $product['order_count'] ?></td>
    <td><?= $product['total_quantity'] ?></td>
    <td><?= number_format($product['total_revenue'], 2) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>

<div class="section-title">Refill Request Performance by Branch</div>
<?php foreach ($branch_refills as $branch_id => $branch_data): ?>
<h3><?= htmlspecialchars($branch_data['branch_name']) ?></h3>
<table>
    <thead>
        <tr>
            <th>Container Type</th>
            <th>Count</th>
            <th>Total Revenue (₱)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($branch_data['containers'] as $container): ?>
        <tr>
            <td><?= htmlspecialchars($container['name']) ?></td>
            <td><?= $container['count'] ?></td>
            <td><?= number_format($container['revenue'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endforeach; ?>

</div>



</body>
</html>
