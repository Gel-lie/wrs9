<?php
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('branch_admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

$conn = getConnection();
$admin = getUserInfo($_SESSION['user_id']);
$branch_id = (int)$admin['branch_id'];

$action = $_GET['action'] ?? 'barangays';

switch ($action) {
    case 'barangays':
        // Return barangay totals for this branch
        $stmt = $conn->prepare(
            "SELECT b.barangay_id, b.barangay_name, COALESCE(SUM(od.quantity),0) as total_qty
             FROM barangays b
             LEFT JOIN users u ON u.barangay_id = b.barangay_id
             LEFT JOIN orders o ON o.customer_id = u.user_id AND o.branch_id = ? AND o.status IN ('pending','processing','delivered')
             LEFT JOIN order_details od ON od.order_id = o.order_id
             WHERE b.branch_id = ?
             GROUP BY b.barangay_id, b.barangay_name
             ORDER BY total_qty DESC"
        );
        $stmt->bind_param("ii", $branch_id, $branch_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['data' => $res]);
        break;

    case 'sitios':
        $barangay_id = isset($_GET['barangay_id']) ? (int)$_GET['barangay_id'] : 0;
        if (!$barangay_id) {
            echo json_encode(['data' => []]);
            exit();
        }

        $stmt = $conn->prepare(
            "SELECT COALESCE(NULLIF(u.sitio_purok, ''), NULLIF(o.sitio_purok, ''), 'Unknown') as sitio,
                    COALESCE(SUM(od.quantity),0) as total_qty
             FROM orders o
             JOIN order_details od ON od.order_id = o.order_id
             LEFT JOIN users u ON o.customer_id = u.user_id
             WHERE o.branch_id = ? AND u.barangay_id = ? AND o.status IN ('pending','processing','delivered')
             GROUP BY sitio
             ORDER BY total_qty DESC"
        );
        $stmt->bind_param("ii", $branch_id, $barangay_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['data' => $res]);
        break;

    case 'households':
        $barangay_id = isset($_GET['barangay_id']) ? (int)$_GET['barangay_id'] : 0;
        $sitio = isset($_GET['sitio']) ? $_GET['sitio'] : '';
        if (!$barangay_id || $sitio === '') {
            echo json_encode(['data' => []]);
            exit();
        }

        // Get aggregates per household surname and also attach last delivery date
        $stmt = $conn->prepare(
            "SELECT
                SUBSTRING_INDEX(u.name, ' ', -1) AS surname,
                COALESCE(SUM(od.quantity),0) AS total_qty,
                COALESCE(SUM(od.quantity * od.price),0) AS total_price,
                MAX(o.delivery_date) AS last_delivery
             FROM orders o
             JOIN order_details od ON od.order_id = o.order_id
             LEFT JOIN users u ON o.customer_id = u.user_id
             WHERE o.branch_id = ?
               AND u.barangay_id = ?
               AND COALESCE(NULLIF(u.sitio_purok, ''), NULLIF(o.sitio_purok, ''), 'Unknown') = ?
               AND o.status IN ('pending','processing','delivered')
             GROUP BY surname
             ORDER BY total_qty DESC"
        );
        $stmt->bind_param("iis", $branch_id, $barangay_id, $sitio);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Also get individual orders per surname for details (optional)
        $details_stmt = $conn->prepare(
            "SELECT
                SUBSTRING_INDEX(u.name, ' ', -1) AS surname,
                o.order_id,
                o.delivery_date,
                od.quantity,
                od.price,
                (od.quantity * od.price) AS line_total
             FROM orders o
             JOIN order_details od ON od.order_id = o.order_id
             LEFT JOIN users u ON o.customer_id = u.user_id
             WHERE o.branch_id = ?
               AND u.barangay_id = ?
               AND COALESCE(NULLIF(u.sitio_purok, ''), NULLIF(o.sitio_purok, ''), 'Unknown') = ?
               AND o.status IN ('pending','processing','delivered')
             ORDER BY surname, o.delivery_date DESC"
        );
        $details_stmt->bind_param("iis", $branch_id, $barangay_id, $sitio);
        $details_stmt->execute();
        $details = $details_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Group details by surname
        $grouped = [];
        foreach ($details as $d) {
            $s = $d['surname'] ?: 'Unknown';
            if (!isset($grouped[$s])) $grouped[$s] = [];
            $grouped[$s][] = $d;
        }

        echo json_encode(['data' => $rows, 'details' => $grouped]);
        break;

    case 'order_progress':
        // Return order status counts for Lazareto branch
        $lazaretoBranchId = null;
        $branchStmt = $conn->prepare("SELECT branch_id FROM branches WHERE branch_name = 'Lazareto'");
        if ($branchStmt) {
            $branchStmt->execute();
            $branchRes = $branchStmt->get_result();
            if ($branchRes && $branchRes->num_rows > 0) {
                $lazaretoBranchId = $branchRes->fetch_assoc()['branch_id'];
            }
        }

        if ($lazaretoBranchId) {
            $stmt = $conn->prepare("
                SELECT status, COUNT(*) as count
                FROM orders
                WHERE branch_id = ?
                GROUP BY status
            ");
            $stmt->bind_param("i", $lazaretoBranchId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $data = [];
            foreach ($res as $row) {
                $data[$row['status']] = (int)$row['count'];
            }
        } else {
            $data = [];
        }

        echo json_encode(['data' => $data]);
        break;

    case 'stock_status':
        // Return stock status counts for Lazareto branch
        $lazaretoBranchId = null;
        $branchStmt = $conn->prepare("SELECT branch_id FROM branches WHERE branch_name = 'Lazareto'");
        if ($branchStmt) {
            $branchStmt->execute();
            $branchRes = $branchStmt->get_result();
            if ($branchRes && $branchRes->num_rows > 0) {
                $lazaretoBranchId = $branchRes->fetch_assoc()['branch_id'];
            }
        }

        if ($lazaretoBranchId) {
            $stmt = $conn->prepare("
                SELECT
                    CASE
                        WHEN i.quantity = 0 THEN 'Out of Stock'
                        WHEN i.quantity <= i.low_stock_threshold THEN 'Low Stock'
                        ELSE 'In Stock'
                    END as status,
                    COUNT(*) as count
                FROM inventory i
                WHERE i.branch_id = ?
                GROUP BY status
            ");
            $stmt->bind_param("i", $lazaretoBranchId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $data = [];
            foreach ($res as $row) {
                $data[$row['status']] = (int)$row['count'];
            }
        } else {
            $data = [];
        }

        echo json_encode(['data' => $data]);
        break;

    case 'customer_metrics':
        // Return customer metrics for Lazareto branch (matching customers.php logic)
        $lazaretoBranchId = null;
        $branchStmt = $conn->prepare("SELECT branch_id FROM branches WHERE branch_name = 'Lazareto'");
        if ($branchStmt) {
            $branchStmt->execute();
            $branchRes = $branchStmt->get_result();
            if ($branchRes && $branchRes->num_rows > 0) {
                $lazaretoBranchId = $branchRes->fetch_assoc()['branch_id'];
            }
        }

        if ($lazaretoBranchId) {
            // Get customer statistics matching customers.php
            $stmt = $conn->prepare("
                SELECT
                    COUNT(*) as total_customers,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_customers,
                    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_customers,
                    COUNT(CASE WHEN registration_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_customers
                FROM users
                WHERE branch_id = ? AND role = 'customer'
            ");
            $stmt->bind_param("i", $lazaretoBranchId);
            $stmt->execute();
            $stats = $stmt->get_result()->fetch_assoc();

            $data = [
                'Total Customers' => (int)$stats['total_customers'],
                'Active Customers' => (int)$stats['active_customers'],
                'Inactive Customers' => (int)$stats['inactive_customers'],
                'New Customers' => (int)$stats['new_customers']
            ];
        } else {
            $data = [];
        }

        echo json_encode(['data' => $data]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

?>
