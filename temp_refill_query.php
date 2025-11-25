<?php
require_once 'includes/db.php';

$conn = getConnection();

// Get Lazareto branch refill data
$lazareto_refill_stmt = $conn->prepare("
    SELECT
        CASE
            WHEN o.notes LIKE '%Round (5 gallons)%' THEN 'Round (5 gallons)'
            WHEN o.notes LIKE '%Slim (5 gallons)%' THEN 'Slim (5 gallons)'
            WHEN o.notes LIKE '%10 liters%' THEN '10 liters'
            WHEN o.notes LIKE '%8 liters%' THEN '8 liters'
            WHEN o.notes LIKE '%7 liters%' THEN '7 liters'
            WHEN o.notes LIKE '%6.6 liters%' THEN '6.6 liters'
            WHEN o.notes LIKE '%6 liters%' THEN '6 liters'
            WHEN o.notes LIKE '%5 liters%' THEN '5 liters'
            ELSE 'Other'
        END as container_type,
        SUM(od.quantity) as total_quantity
    FROM orders o
    JOIN order_details od ON o.order_id = od.order_id
    JOIN users u ON o.customer_id = u.user_id
    JOIN branches b ON u.branch_id = b.branch_id
    WHERE od.product_id = 1
        AND o.status = 'delivered'
        AND b.branch_name = 'Lazareto'
        AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY container_type
    ORDER BY total_quantity DESC
");
$lazareto_refill_stmt->execute();
$lazareto_refills = $lazareto_refill_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get Calero branch refill data
$calero_refill_stmt = $conn->prepare("
    SELECT
        CASE
            WHEN o.notes LIKE '%Round (5 gallons)%' THEN 'Round (5 gallons)'
            WHEN o.notes LIKE '%Slim (5 gallons)%' THEN 'Slim (5 gallons)'
            WHEN o.notes LIKE '%10 liters%' THEN '10 liters'
            WHEN o.notes LIKE '%8 liters%' THEN '8 liters'
            WHEN o.notes LIKE '%7 liters%' THEN '7 liters'
            WHEN o.notes LIKE '%6.6 liters%' THEN '6.6 liters'
            WHEN o.notes LIKE '%6 liters%' THEN '6 liters'
            WHEN o.notes LIKE '%5 liters%' THEN '5 liters'
            ELSE 'Other'
        END as container_type,
        SUM(od.quantity) as total_quantity
    FROM orders o
    JOIN order_details od ON o.order_id = od.order_id
    JOIN users u ON o.customer_id = u.user_id
    JOIN branches b ON u.branch_id = b.branch_id
    WHERE od.product_id = 1
        AND o.status = 'delivered'
        AND b.branch_name = 'Calero'
        AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY container_type
    ORDER BY total_quantity DESC
");
$calero_refill_stmt->execute();
$calero_refills = $calero_refill_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "Lazareto Refill Quantities:\n";
foreach ($lazareto_refills as $refill) {
    echo $refill['container_type'] . ": " . $refill['total_quantity'] . "\n";
}

echo "\nCalero Refill Quantities:\n";
foreach ($calero_refills as $refill) {
    echo $refill['container_type'] . ": " . $refill['total_quantity'] . "\n";
}

echo "\nNote: These quantities only include orders with status = 'delivered' (not cancelled or processing).\n";
?>
