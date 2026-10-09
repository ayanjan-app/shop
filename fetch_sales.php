<?php
// fetch_sales.php - CORRECTED VERSION
require_once 'db.php';
session_start();

if(!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

$user_id = $_SESSION['user_id'];

// Get parameters with defaults
$page = $_POST['page'] ?? 1;
$per_page = $_POST['per_page'] ?? 10;
$search = $_POST['search'] ?? '';
$status = $_POST['status'] ?? 'all';
$period = $_POST['period'] ?? 'all';

$offset = ($page - 1) * $per_page;

// Build SQL with placeholders
$sql = "SELECT s.* FROM sales s WHERE s.user_id = ?";
$params = [$user_id];
$types = "i";

// Add search
if (!empty($search)) {
    $sql .= " AND (s.customer_name LIKE ?)";
    $params[] = "%$search%";
    $types .= "s";
}

// Add status filter
// First, we need to calculate balance
$sql = "SELECT 
            s.*,
            (s.total_amount - COALESCE(SUM(p.payment_amount), 0)) as balance,
            COALESCE(SUM(p.payment_amount), 0) as paid_amount
        FROM sales s 
        LEFT JOIN payments p ON s.sale_id = p.sale_id 
        WHERE s.user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $sql .= " AND (s.customer_name LIKE ?)";
    $params[] = "%$search%";
    $types .= "s";
}

// Group by sale_id
$sql .= " GROUP BY s.sale_id";

// Add HAVING clause for status (after GROUP BY)
if ($status == 'completed') {
    $sql .= " HAVING balance <= 0";
} elseif ($status == 'unpaid') {
    $sql .= " HAVING balance > 0";
} else {
    $sql .= " HAVING 1=1"; // For all status
}

// Add period filter in WHERE clause (before GROUP BY)
// We need to modify the query structure
// In fetch_sales.php, update the SQL to calculate balance properly:
$sql = "SELECT 
            s.*,
            (s.total_amount - COALESCE(SUM(p.payment_amount), 0)) as balance,
            COALESCE(SUM(p.payment_amount), 0) as paid_amount
        FROM sales s 
        LEFT JOIN payments p ON s.sale_id = p.sale_id 
        WHERE 1=1"; // no user filter
$params = [];
$types = "";


if (!empty($search)) {
    $sql .= " AND (s.customer_name LIKE ?)";
    $params[] = "%$search%";
    $types .= "s";
}

// Add period filter
if ($period == 'today') {
    $sql .= " AND DATE(s.sale_date) = ?";
    $params[] = date('Y-m-d');
    $types .= "s";
} elseif ($period == 'yesterday') {
    $sql .= " AND DATE(s.sale_date) = ?";
    $params[] = date('Y-m-d', strtotime('-1 day'));
    $types .= "s";
} elseif ($period == 'last7') {
    $sql .= " AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($period == 'last30') {
    $sql .= " AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($period == 'thisMonth') {
    $sql .= " AND MONTH(s.sale_date) = MONTH(CURRENT_DATE()) AND YEAR(s.sale_date) = YEAR(CURRENT_DATE())";
}

// Group by
$sql .= " GROUP BY s.sale_id";

// Add HAVING clause for status
if ($status == 'completed') {
    $sql .= " HAVING balance <= 0 OR balance IS NULL";
} elseif ($status == 'unpaid') {
    $sql .= " HAVING balance > 0";
}

// Count total rows - we need a subquery
$countSql = "SELECT COUNT(*) as total FROM (
    SELECT s.sale_id
    FROM sales s 
    LEFT JOIN payments p ON s.sale_id = p.sale_id 
    WHERE s.user_id = ?";
    
$countParams = [$user_id];
$countTypes = "i";

if (!empty($search)) {
    $countSql .= " AND (s.customer_name LIKE ?)";
    $countParams[] = "%$search%";
    $countTypes .= "s";
}

if ($period == 'today') {
    $countSql .= " AND DATE(s.sale_date) = ?";
    $countParams[] = date('Y-m-d');
    $countTypes .= "s";
} elseif ($period == 'yesterday') {
    $countSql .= " AND DATE(s.sale_date) = ?";
    $countParams[] = date('Y-m-d', strtotime('-1 day'));
    $countTypes .= "s";
} elseif ($period == 'last7') {
    $countSql .= " AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($period == 'last30') {
    $countSql .= " AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($period == 'thisMonth') {
    $countSql .= " AND MONTH(s.sale_date) = MONTH(CURRENT_DATE()) AND YEAR(s.sale_date) = YEAR(CURRENT_DATE())";
}

$countSql .= " GROUP BY s.sale_id";

if ($status == 'completed') {
    $countSql .= " HAVING COALESCE(SUM(p.payment_amount), 0) >= s.total_amount";
} elseif ($status == 'unpaid') {
    $countSql .= " HAVING COALESCE(SUM(p.payment_amount), 0) < s.total_amount";
}

$countSql .= ") as subquery";

$countStmt = $conn->prepare($countSql);
if (!empty($countParams)) {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $per_page);

// Add pagination to main query
$sql .= " ORDER BY s.sale_date DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Execute main query
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$sales = [];
while ($row = $result->fetch_assoc()) {
    // FIXED: Use 'sale_id' instead of 'id'
    $sale_id = $row['sale_id'];
    
    // Get items count for each sale
    $itemsStmt = $conn->prepare("SELECT COUNT(*) as count FROM sale_items WHERE sale_id = ?");
    $itemsStmt->bind_param("i", $sale_id);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    $row['items_count'] = $itemsResult->fetch_assoc()['count'];
    $itemsStmt->close();
    
    // Get payment method
    $paymentStmt = $conn->prepare("
        SELECT payment_method 
        FROM payments 
        WHERE sale_id = ? 
        ORDER BY payment_date DESC 
        LIMIT 1
    ");
    $paymentStmt->bind_param("i", $sale_id);
    $paymentStmt->execute();
    $paymentResult = $paymentStmt->get_result();
    $payment = $paymentResult->fetch_assoc();
    $row['payment_method'] = $payment['payment_method'] ?? 'Cash';
    $paymentStmt->close();
    
    // Get medicine names
    $medStmt = $conn->prepare("
        SELECT GROUP_CONCAT(m.name SEPARATOR ', ') as names 
        FROM sale_items si 
        JOIN medicines m ON si.medicine_id = m.id 
        WHERE si.sale_id = ?
        LIMIT 3
    ");
    $medStmt->bind_param("i", $sale_id);
    $medStmt->execute();
    $medResult = $medStmt->get_result();
    $row['medicine_names'] = $medResult->fetch_assoc()['names'] ?? '';
    $medStmt->close();
    
    // Add the sale ID to the response
    $row['id'] = $sale_id; // This is what JavaScript expects
    
    $sales[] = $row;
}

echo json_encode([
    'success' => true,
    'sales' => $sales,
    'totalPages' => $totalPages,
    'currentPage' => $page,
    'totalRows' => $totalRows
]);

$conn->close();
?>