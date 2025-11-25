<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

$conn = getConnection();

// Get all branches
$branches = [];
$result = $conn->query("SELECT * FROM branches ORDER BY branch_name");
while ($row = $result->fetch_assoc()) {
    $branches[$row['branch_id']] = $row;
}

// Get all products with their inventory levels for each branch
$branch_products = [];
$query = "
    SELECT
        p.*,
        i.branch_id,
        i.quantity,
        i.low_stock_threshold,
        b.branch_name
    FROM products p
    LEFT JOIN inventory i ON p.product_id = i.product_id
    LEFT JOIN branches b ON i.branch_id = b.branch_id
    ORDER BY b.branch_name, p.product_name
";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    if ($row['branch_id']) {
        if (!isset($branch_products[$row['branch_id']])) {
            $branch_products[$row['branch_id']] = [
                'branch_name' => $row['branch_name'],
                'products' => []
            ];
        }
        $branch_products[$row['branch_id']]['products'][] = [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'description' => $row['description'],
            'price' => $row['price'],
            'category' => $row['category'],
            'image_path' => $row['image_path'],
            'quantity' => $row['quantity'],
            'threshold' => $row['low_stock_threshold']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Branch Inventory Report</title>
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
    font-size: 12px;
}
th, td {
    border: 1px solid #ddd;
    padding: 6px 8px;
    text-align: center;
}
th {
    background: #e9f3ff;
}

/* LANDSCAPE PRINT STYLES */
@media print {
    @page {
        size: A4 landscape;
        margin: 0.5in;
    }
    body {
        background: white;
        padding: 0;
        margin: 0;
    }
    .report-wrapper {
        box-shadow: none;
        border-radius: 0;
        max-width: none;
        padding: 20px;
    }
    .print-btn {
        display: none;
    }
    table {
        font-size: 10px;
    }
    th, td {
        padding: 4px 6px;
    }
    h3 {
        font-size: 14px;
        margin-top: 15px;
    }
    .section-title {
        font-size: 12px;
        padding: 8px;
    }
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
            <small>Inventory Report (<?= date('Y-m-d') ?>)</small>
        </div>

        <!-- RIGHT SIDE: Logos -->
        <div class="header-right">
            <img src="../uploads/calapan.png" alt="Calapan Logo">
            <img src="../uploads/oriental.png" alt="Oriental Mindoro Logo">
        </div>
    </div>
</header>

<div class="print-btn">
    <button onclick="window.print()">🖨️ Print Inventory Report</button>
</div>

<?php foreach ($branch_products as $branch_id => $branch_data): ?>
<div class="section-title">Inventory - <?= htmlspecialchars($branch_data['branch_name']) ?> Branch</div>
<table>
    <thead>
        <tr>
            <th>Product Name</th>
            <th>Description</th>
            <th>Category</th>
            <th>Price (₱)</th>
            <th>Stock Level</th>
            <th>Low Stock Threshold</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($branch_data['products'] as $product): ?>
        <tr>
            <td style="text-align: left;"><?= htmlspecialchars($product['product_name']) ?></td>
            <td style="text-align: left;"><?= htmlspecialchars($product['description']) ?></td>
            <td><?= htmlspecialchars($product['category']) ?></td>
            <td><?= number_format($product['price'], 2) ?></td>
            <td><?= $product['quantity'] ?> units</td>
            <td><?= $product['threshold'] ?> units</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endforeach; ?>

</div>

</body>
