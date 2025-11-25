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

// Get branch name
$stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ?");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$branch_result = $stmt->get_result();
$branch = $branch_result->fetch_assoc();
$branch_name = $branch['branch_name'] ?? 'Unknown Branch';

// Get inventory items with product details
$query = "
    SELECT
        i.*,
        p.product_name,
        p.description,
        p.price,
        p.category,
        p.image_path,
        p.status as product_status,
        CASE
            WHEN i.quantity <= i.low_stock_threshold THEN 'low'
            WHEN i.quantity = 0 THEN 'out'
            ELSE 'normal'
        END as stock_status
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    WHERE i.branch_id = ?
    ORDER BY p.category, p.product_name
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$inventory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get inventory statistics
$stmt = $conn->prepare("
    SELECT
        COUNT(*) as total_items,
        COUNT(CASE WHEN quantity <= low_stock_threshold THEN 1 END) as low_stock_items,
        COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock_items,
        COALESCE(SUM(quantity * p.price), 0) as total_value
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    WHERE i.branch_id = ?
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory Report - <?= htmlspecialchars($branch_name) ?></title>
<style>
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #f5f7fa;
    margin: 0;
    padding: 40px;
}

.report-wrapper {
    background: white;
    max-width: 1200px;
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
    font-size: 12px;
}
th, td {
    border: 1px solid #ddd;
    padding: 6px 8px;
    text-align: center;
}
th {
    background: #e9f3ff;
    font-weight: 600;
}

/* Statistics Cards */
.stats-container {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    flex: 1;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
}

.stat-card h5 {
    margin: 0 0 5px 0;
    color: #495057;
    font-size: 14px;
}

.stat-card .value {
    font-size: 24px;
    font-weight: bold;
    color: #0078d7;
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
            <p>Inventory Report</p>
            <small>Generated on: <?= date('M d, Y h:i A') ?></small>
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

<div class="section-title">Inventory Details</div>
<table>
    <thead>
        <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Price</th>
            <th>Current Stock</th>
            <th>Low Stock Alert</th>
            <th>Status</th>
            <th>Last Updated</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($inventory as $item): ?>
        <tr>
            <td>
                <?= htmlspecialchars($item['product_name']) ?>
                <?php if ($item['description']): ?>
                    <br><small style="color: #666; font-size: 10px;"><?= htmlspecialchars($item['description']) ?></small>
                <?php endif; ?>
            </td>
            <td><?= ucfirst($item['category']) ?></td>
            <td>₱<?= number_format($item['price'], 2) ?></td>
            <td><?= $item['quantity'] ?></td>
            <td><?= $item['low_stock_threshold'] ?></td>
            <td>
                <?php
                $status_text = match($item['stock_status']) {
                    'low' => 'Low Stock',
                    'out' => 'Out of Stock',
                    default => 'In Stock'
                };
                echo $status_text;
                ?>
            </td>
            <td><?= date('M d, Y h:ia', strtotime($item['last_updated'])) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</div>

</body>
</html>
