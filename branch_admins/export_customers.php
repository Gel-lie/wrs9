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

// Get filters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$barangay_filter = isset($_GET['barangay']) ? sanitize($_GET['barangay']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Get customers with their details
$query = "
    SELECT
        u.*,
        b.barangay_name,
        l.points as loyalty_points,
        l.reward_level,
        COUNT(DISTINCT o.order_id) as total_orders,
        SUM(CASE WHEN o.status != 'cancelled' THEN o.total_amount ELSE 0 END) as total_spent,
        MAX(o.order_date) as last_order_date
    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.barangay_id
    LEFT JOIN loyalty l ON u.user_id = l.customer_id
    LEFT JOIN orders o ON u.user_id = o.customer_id
    WHERE u.branch_id = ? AND u.role = 'customer'
";

$params = [$branch_id];
$types = "i";

if ($status_filter) {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($barangay_filter) {
    $query .= " AND u.barangay_id = ?";
    $params[] = $barangay_filter;
    $types .= "i";
}

if ($search) {
    $search_term = "%$search%";
    $query .= " AND (
        u.name LIKE ? OR
        u.email LIKE ? OR
        u.contact_number LIKE ? OR
        b.barangay_name LIKE ?
    )";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= "ssss";
}

$query .= " GROUP BY u.user_id ORDER BY u.name";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customer List - <?= htmlspecialchars($branch_name) ?></title>
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
            <p>Customer List</p>
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

<div class="section-title">Customer Details</div>
<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Contact Info</th>
            <th>Location</th>
            <th>Orders</th>
            <th>Loyalty</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($customers as $customer): ?>
        <tr>
            <td>
                <?= htmlspecialchars($customer['name']) ?>
                <br><small style="color: #666; font-size: 10px;">Since <?= date('M Y', strtotime($customer['registration_date'])) ?></small>
            </td>
            <td>
                <?= htmlspecialchars($customer['contact_number']) ?>
            </td>
            <td><?= htmlspecialchars($customer['barangay_name']) ?></td>
            <td>
                Orders: <?= $customer['total_orders'] ?><br>
                Total: ₱<?= number_format($customer['total_spent'], 2) ?><br>
                <?php if ($customer['last_order_date']): ?>
                Last: <?= date('M d, Y', strtotime($customer['last_order_date'])) ?>
                <?php endif; ?>
            </td>
            <td>
                Points: <?= number_format($customer['loyalty_points'] ?? 0) ?><br>
                Level: <?= $customer['reward_level'] ?? 'None' ?>
            </td>
            <td>
                <span class="badge bg-<?= $customer['status'] === 'active' ? 'success' : 'danger' ?>">
                    <?= ucfirst($customer['status']) ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</div>

</body>
</html>
