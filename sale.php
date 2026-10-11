<?php
session_start();
require_once 'db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Create upload folders
$uploadDir = __DIR__ . '/uploads/sales/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$success_msg = '';
$error_msg = '';

if (isset($_SESSION['flash_success'])) {
    $success_msg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $error_msg = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// =========================
// DELETE SALE
// =========================
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmtGet = $conn->prepare("SELECT invoice_picture FROM sales WHERE id = ?");
    $stmtGet->bind_param("i", $delete_id);
    $stmtGet->execute();
    $resGet = $stmtGet->get_result();
    if ($rowGet = $resGet->fetch_assoc()) {
        if (!empty($rowGet['invoice_picture']) && file_exists(__DIR__ . '/' . $rowGet['invoice_picture'])) {
            @unlink(__DIR__ . '/' . $rowGet['invoice_picture']);
        }
    }
    $stmtGet->close();

    $stmt = $conn->prepare("DELETE FROM sales WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $_SESSION['flash_success'] = "پلورل شوې فاکتور په بریالیتوب سره ړنګ شو!";
    } else {
        $_SESSION['flash_error'] = "تېروتنه: " . $stmt->error;
    }
    $stmt->close();

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// =========================
// ADD SALE
// =========================
if (isset($_POST['add_sale'])) {
    $client_id       = intval($_POST['client_id'] ?? 0);
    $invoice_number  = trim($_POST['invoice_number'] ?? '');
    $invoice_date    = trim($_POST['invoice_date'] ?? '');
    $currency        = trim($_POST['currency'] ?? '');
    $total_amount    = trim($_POST['total_amount'] ?? '');
    $note            = trim($_POST['note'] ?? '');
    $invoicePicture  = null;
    $hasError        = false;

    $allowed_currencies = ['USD', 'AFN'];

    if ($client_id <= 0) {
        $_SESSION['flash_error'] = "پیرودونکی اړین دی";
        $hasError = true;
    } elseif (empty($invoice_number)) {
        $_SESSION['flash_error'] = "د فاکتور شمېره اړینه ده";
        $hasError = true;
    } elseif (empty($invoice_date)) {
        $_SESSION['flash_error'] = "نېټه اړینه ده";
        $hasError = true;
    } elseif (!in_array($currency, $allowed_currencies)) {
        $_SESSION['flash_error'] = "د پیسو ډول سم نه دی";
        $hasError = true;
    } elseif ($total_amount === '') {
        $_SESSION['flash_error'] = "ټوله اندازه اړینه ده";
        $hasError = true;
    }

    if (!$hasError && isset($_FILES['invoice_picture']) && $_FILES['invoice_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['invoice_picture']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_ext)) {
            $newName = 'invoice_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target  = $uploadDir . $newName;
            if (move_uploaded_file($_FILES['invoice_picture']['tmp_name'], $target)) {
                $invoicePicture = 'uploads/sales/' . $newName;
            } else {
                $_SESSION['flash_error'] = "د عکس پورته کول ناکام شول!";
                $hasError = true;
            }
        } else {
            $_SESSION['flash_error'] = "یوازې JPG، PNG، GIF، WEBP عکسونه اجازه لري.";
            $hasError = true;
        }
    }

    if (!$hasError) {
        $stmt = $conn->prepare("INSERT INTO sales (client_id, invoice_number, invoice_date, currency, total_amount, invoice_picture, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssdss", $client_id, $invoice_number, $invoice_date, $currency, $total_amount, $invoicePicture, $note);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "فاکتور په بریالیتوب سره اضافه شو!";
        } else {
            $_SESSION['flash_error'] = "تېروتنه: " . $stmt->error;
        }
        $stmt->close();
    }

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// =========================
// FILTERS
// =========================
$filter_client   = isset($_GET['filter_client']) ? intval($_GET['filter_client']) : 0;
$filter_from     = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$filter_to       = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';
$search_invoice  = isset($_GET['search_invoice']) ? trim($_GET['search_invoice']) : '';

// Build WHERE for the sales list
$saleWhere = [];
$clientParam = 0;
if ($filter_client > 0) {
    $saleWhere[] = "s.client_id = " . $filter_client;
}
if ($filter_from !== '') {
    $saleWhere[] = "s.invoice_date >= '" . $conn->real_escape_string($filter_from) . "'";
}
if ($filter_to !== '') {
    $saleWhere[] = "s.invoice_date <= '" . $conn->real_escape_string($filter_to) . "'";
}
if ($search_invoice !== '') {
    $saleWhere[] = "s.invoice_number LIKE '%" . $conn->real_escape_string($search_invoice) . "%'";
}
$whereSql = !empty($saleWhere) ? "WHERE " . implode(" AND ", $saleWhere) : "";

// =========================
// GET SALES LIST
// =========================
$sql = "SELECT s.*, c.full_name AS client_name 
        FROM sales s 
        LEFT JOIN clients c ON s.client_id = c.id 
        $whereSql 
        ORDER BY s.id DESC";
$result = mysqli_query($conn, $sql);
$record_count = mysqli_num_rows($result);

// =========================
// TOTALS OF SALES (filtered)
// =========================
$total_usd = 0;
$total_afn = 0;
$total_sql = "SELECT s.currency, SUM(s.total_amount) AS total FROM sales s $whereSql GROUP BY s.currency";
$total_result = mysqli_query($conn, $total_sql);
if ($total_result) {
    while ($t = mysqli_fetch_assoc($total_result)) {
        if ($t['currency'] === 'USD') $total_usd = $t['total'];
        if ($t['currency'] === 'AFN') $total_afn = $t['total'];
    }
}

// =========================
// CLIENT SUMMARY TABLE
// =========================
// Same date filter applies to the summary table.
// Total Sales (invoices), Total Paid (payments), Total Unpaid

$dateCondSales = "";
$dateCondPay = "";

if ($filter_from !== '') {
    $dateCondSales .= " AND s.invoice_date >= '" . $conn->real_escape_string($filter_from) . "'";
    $dateCondPay   .= " AND p.payment_date >= '" . $conn->real_escape_string($filter_from) . "'";
}
if ($filter_to !== '') {
    $dateCondSales .= " AND s.invoice_date <= '" . $conn->real_escape_string($filter_to) . "'";
    $dateCondPay   .= " AND p.payment_date <= '" . $conn->real_escape_string($filter_to) . "'";
}

$clientFilterCond = "";
if ($filter_client > 0) {
    $clientFilterCond = " AND c.id = " . $filter_client;
}

$summarySql = "
    SELECT 
        c.id,
        c.full_name,
        COALESCE(sales_usd.total, 0) AS usd_invoiced,
        COALESCE(pays_usd.total, 0) AS usd_paid,
        COALESCE(sales_afn.total, 0) AS afn_invoiced,
        COALESCE(pays_afn.total, 0) AS afn_paid
    FROM clients c
    LEFT JOIN (
        SELECT client_id, SUM(total_amount) AS total 
        FROM sales s 
        WHERE currency = 'USD' $dateCondSales
        GROUP BY client_id
    ) sales_usd ON c.id = sales_usd.client_id
    LEFT JOIN (
        SELECT client_id, SUM(amount) AS total 
        FROM client_payments p 
        WHERE currency = 'USD' $dateCondPay
        GROUP BY client_id
    ) pays_usd ON c.id = pays_usd.client_id
    LEFT JOIN (
        SELECT client_id, SUM(total_amount) AS total 
        FROM sales s 
        WHERE currency = 'AFN' $dateCondSales
        GROUP BY client_id
    ) sales_afn ON c.id = sales_afn.client_id
    LEFT JOIN (
        SELECT client_id, SUM(amount) AS total 
        FROM client_payments p 
        WHERE currency = 'AFN' $dateCondPay
        GROUP BY client_id
    ) pays_afn ON c.id = pays_afn.client_id
    WHERE 1=1 $clientFilterCond
    ORDER BY c.full_name ASC
";

$summaryResult = $conn->query($summarySql);
$client_summary = [];
$grand_usd_inv = 0; $grand_usd_paid = 0; $grand_usd_unpaid = 0;
$grand_afn_inv = 0; $grand_afn_paid = 0; $grand_afn_unpaid = 0;

if ($summaryResult) {
    while ($r = $summaryResult->fetch_assoc()) {
        $r['usd_unpaid'] = $r['usd_invoiced'] - $r['usd_paid'];
        $r['afn_unpaid'] = $r['afn_invoiced'] - $r['afn_paid'];
        
        // Skip clients with no activity if filters are applied
        if (($filter_from !== '' || $filter_to !== '' || $filter_client > 0) 
            && $r['usd_invoiced'] == 0 && $r['afn_invoiced'] == 0 
            && $r['usd_paid'] == 0 && $r['afn_paid'] == 0) {
            continue;
        }
        
        $grand_usd_inv += $r['usd_invoiced'];
        $grand_usd_paid += $r['usd_paid'];
        $grand_usd_unpaid += $r['usd_unpaid'];
        $grand_afn_inv += $r['afn_invoiced'];
        $grand_afn_paid += $r['afn_paid'];
        $grand_afn_unpaid += $r['afn_unpaid'];
        $client_summary[] = $r;
    }
}

// =========================
// CLIENTS FOR DROPDOWN
// =========================
$clients_list = [];
$clientResult = $conn->query("SELECT id, full_name FROM clients ORDER BY full_name ASC");
if ($clientResult) {
    while ($c = $clientResult->fetch_assoc()) {
        $clients_list[] = $c;
    }
}

$today_date = date('Y-m-d');

// Active client name for display
$active_client_name = '';
if ($filter_client > 0) {
    foreach ($clients_list as $cl) {
        if ($cl['id'] == $filter_client) {
            $active_client_name = $cl['full_name'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>د پیرودونکو فاکتورونه · قرطاسیه</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e3c72;
            --secondary-color: #2b4a7a;
            --accent-color: #e6b12e;
            --success-color: #22c55e;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --text-color: #1f2937;
            --text-muted: #64748b;
            --card-bg: #ffffff;
            --bg-light: #f1f5f9;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            --shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.1);
            --border-radius: 16px;
            --transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Noto Naskh Arabic', 'Segoe UI', Tahoma, sans-serif; }
        body { background: var(--bg-light); color: var(--text-color); min-height: 100vh; font-size: 14px; line-height: 1.6; }
        .main-content { padding: 5px; }
        @media (max-width: 768px) { .main-content { padding: 5px; padding-top: 5px; } }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; padding: 18px 24px; background: var(--primary-color); border-radius: var(--border-radius); color: white; box-shadow: var(--shadow); }
        .page-header h1 { font-size: 1.6rem; display: flex; align-items: center; gap: 12px; font-weight: 700; margin: 0; }
        .page-header h1 i { color: var(--accent-color); }
        .page-header .subtitle { color: rgba(255,255,255,0.8); font-size: 0.85rem; margin-top: 5px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: var(--card-bg); border-radius: var(--border-radius); padding: 22px 18px; box-shadow: var(--shadow); transition: var(--transition); border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 15px; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); }
        .stat-icon { width: 55px; height: 55px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: white; flex-shrink: 0; }
        .stat-icon.usd { background: #16a34a; }
        .stat-icon.afn { background: #dc2626; }
        .stat-icon.unpaid-usd { background: #f59e0b; }
        .stat-icon.unpaid-afn { background: #8b5cf6; }
        .stat-icon.count { background: var(--accent-color); }
        .stat-info h3 { margin: 0; font-size: 0.78rem; color: var(--text-muted); font-weight: 600; }
        .stat-info p { margin: 6px 0 0; font-size: 1.35rem; font-weight: 700; color: var(--primary-color); }

        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 12px; }
        .alert-success { background-color: #dcfce7; color: #166534; border-right: 4px solid var(--success-color); }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border-right: 4px solid var(--danger-color); }

        .content-grid { display: grid; grid-template-columns: 380px 1fr; gap: 25px; align-items: start; }
        @media (max-width: 1100px) { .content-grid { grid-template-columns: 1fr; } }

        .card { background: var(--card-bg); border-radius: var(--border-radius); padding: 25px; box-shadow: var(--shadow); position: relative; overflow: hidden; border: 1px solid #e2e8f0; margin-bottom: 25px; }
        .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--accent-color); }
        .card.full-width { grid-column: 1 / -1; }
        .card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f1f5f9; flex-wrap: wrap; }
        .card-header i { color: var(--accent-color); font-size: 1.3rem; }
        .card-header h2 { margin: 0; font-size: 1.15rem; color: var(--primary-color); font-weight: 700; }
        .card-header .active-client-badge {
            background: var(--accent-color); color: var(--primary-color);
            padding: 4px 14px; border-radius: 20px; font-size: 0.82rem; font-weight: 700;
            margin-right: auto;
        }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 0.85rem; font-weight: 600; color: var(--primary-color); }
        .form-group label i { color: var(--accent-color); margin-left: 6px; width: 16px; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 10px;
            font-size: 0.95rem; transition: var(--transition); font-family: inherit;
            background: var(--card-bg); color: var(--text-color); text-align: right;
            min-height: 48px;
        }
        .form-group select {
            -webkit-appearance: none !important;
            appearance: none !important;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%231e3c72' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>") !important;
            background-repeat: no-repeat !important;
            background-position: left 12px center !important;
            background-size: 16px !important;
            padding: 12px 14px 12px 44px !important;
            cursor: pointer;
            box-sizing: border-box !important;
            display: block;
            direction: rtl;
        }
        .form-group textarea { resize: vertical; min-height: 70px; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(30,60,114,0.1);
        }
        .form-group input[type="date"] { direction: ltr; text-align: right; }
        .form-group input[type="file"] { padding: 8px; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 20px; border: none; border-radius: 10px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: var(--transition); font-family: inherit; text-decoration: none; }
        .btn-primary { background: var(--primary-color); color: white; width: 100%; box-shadow: var(--shadow); }
        .btn-primary:hover { background: var(--secondary-color); transform: translateY(-2px); box-shadow: 0 15px 30px rgba(30,60,114,0.2); }
        .btn-secondary { background: var(--accent-color); color: var(--primary-color); box-shadow: var(--shadow); }
        .btn-secondary:hover { background: #d4a017; transform: translateY(-2px); }
        .btn-reset { background: #f1f5f9; color: var(--text-muted); border: 2px solid #e2e8f0; }
        .btn-reset:hover { background: #e2e8f0; color: var(--primary-color); }

        /* ===== FILTER BAR ===== */
        .filter-bar {
            background: linear-gradient(135deg, #f8fafc, #eef2f7);
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .filter-bar-title {
            display: flex; align-items: center; gap: 8px;
            color: var(--primary-color);
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 15px;
        }
        .filter-bar-title i { color: var(--accent-color); }
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto auto;
            gap: 12px;
            align-items: end;
        }
        @media (max-width: 1100px) {
            .filter-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px) {
            .filter-grid { grid-template-columns: 1fr; }
        }
        .filter-grid .form-group { margin-bottom: 0; }

        .active-filters {
            display: flex; flex-wrap: wrap; gap: 8px;
            margin-top: 15px; padding-top: 15px;
            border-top: 1px dashed #cbd5e1;
        }
        .filter-chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--primary-color); color: white;
            padding: 5px 12px; border-radius: 20px;
            font-size: 0.82rem; font-weight: 600;
        }
        .filter-chip i { color: var(--accent-color); }
        .filter-chip .remove-chip {
            background: rgba(255,255,255,0.2);
            border-radius: 50%; width: 18px; height: 18px;
            display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; color: white; font-size: 0.7rem;
            text-decoration: none;
        }
        .filter-chip .remove-chip:hover { background: var(--danger-color); }

        /* ===== SUMMARY TABLE ===== */
        .summary-table-wrapper {
            overflow-x: auto;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        .summary-table thead {
            background: var(--primary-color);
        }
        .summary-table th {
            padding: 14px 12px;
            color: white;
            font-weight: 700;
            font-size: 0.82rem;
            text-align: center;
            border-left: 1px solid rgba(255,255,255,0.1);
        }
        .summary-table th:first-child { border-left: none; }
        .summary-table th.usd-header { background: #16a34a; }
        .summary-table th.afn-header { background: #dc2626; }
        .summary-table th.unpaid-header { background: #f59e0b; }

        .summary-table td {
            padding: 13px 12px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
            border-left: 1px solid #f1f5f9;
            font-weight: 600;
        }
        .summary-table td:first-child { border-left: none; }
        .summary-table td.client-name-cell {
            text-align: right;
            font-weight: 700;
            color: var(--primary-color);
            background: #f8fafc;
            font-size: 0.92rem;
        }
        .summary-table .paid-value {
            color: #16a34a;
            font-weight: 700;
        }
        .summary-table .unpaid-value {
            color: #d97706;
            font-weight: 700;
        }
        .summary-table .unpaid-value.zero {
            color: #16a34a;
        }
        .summary-table .unpaid-value.big {
            color: #dc2626;
        }
        .summary-table tbody tr:hover {
            background: #f8fafc;
        }
        .summary-table tbody tr:hover td.client-name-cell {
            background: #eef2f7;
        }
        .summary-table tbody tr.row-has-unpaid {
            background: #fffbeb;
        }
        .summary-table tbody tr.row-has-unpaid:hover {
            background: #fef3c7;
        }
        .summary-table tfoot td {
            background: #f1f5f9;
            font-weight: 800;
            color: var(--primary-color);
            border-top: 3px solid var(--primary-color);
            font-size: 0.9rem;
            padding: 15px 12px;
        }
        .summary-table tfoot td.client-name-cell {
            background: #e2e8f0;
        }

        .paid-badge {
            display: inline-flex; align-items: center; gap: 3px;
            background: #dcfce7; color: #166534;
            padding: 2px 8px; border-radius: 10px;
            font-size: 0.7rem; font-weight: 700;
            margin-right: 4px;
        }
        .unpaid-badge {
            display: inline-flex; align-items: center; gap: 3px;
            background: #fef3c7; color: #92400e;
            padding: 2px 8px; border-radius: 10px;
            font-size: 0.7rem; font-weight: 700;
            margin-right: 4px;
        }

        /* ===== SALES TABLE ===== */
        .table-wrapper { overflow-x: auto; border-radius: 12px; border: 1px solid #e2e8f0; }
        table.main-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        table.main-table thead { background: var(--primary-color); }
        table.main-table th { padding: 14px 12px; text-align: right; color: white; font-weight: 600; font-size: 0.82rem; }
        table.main-table td { padding: 12px 12px; border-bottom: 1px solid #e2e8f0; color: var(--text-color); text-align: right; vertical-align: middle; }
        table.main-table tbody tr:hover { background: #f8fafc; }
        table.main-table tbody tr:last-child td { border-bottom: none; }

        .amount-cell { font-weight: 700; color: var(--primary-color); }
        .id-badge { display: inline-block; background: #f1f5f9; color: var(--text-muted); padding: 3px 10px; border-radius: 12px; font-size: 0.78rem; font-weight: 600; }
        .currency-badge { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 0.78rem; font-weight: 700; }
        .currency-USD { background: #dcfce7; color: #166534; }
        .currency-AFN { background: #fee2e2; color: #991b1b; }
        .invoice-thumb { width: 42px; height: 42px; border-radius: 8px; object-fit: cover; border: 2px solid var(--accent-color); cursor: pointer; transition: var(--transition); }
        .invoice-thumb:hover { transform: scale(1.1); }
        .no-picture { width: 42px; height: 42px; border-radius: 8px; background: #f1f5f9; display: inline-flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 0.95rem; }
        .empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px; display: block; }

        .btn-delete { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; background: var(--danger-color); color: white; text-decoration: none; transition: var(--transition); font-size: 0.82rem; }
        .btn-delete:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(239,68,68,0.4); }

        /* Image Lightbox */
        .image-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(6px); }
        .image-modal.active { display: flex; }
        .image-modal img { max-width: 90vw; max-height: 85vh; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.6); border: 3px solid var(--accent-color); }
        .image-modal-close { position: absolute; top: 20px; right: 25px; background: var(--danger-color); color: white; border: none; width: 48px; height: 48px; border-radius: 50%; font-size: 1.3rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }

        @media (max-width: 768px) {
            .page-header h1 { font-size: 1.4rem; }
            .stat-info p { font-size: 1.15rem; }
            .card { padding: 18px; }
            .form-group select { width: 100% !important; font-size: 16px !important; }
            .summary-table { font-size: 0.78rem; }
            .summary-table th, .summary-table td { padding: 8px 6px; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1><i class="fas fa-file-invoice-dollar"></i> د پیرودونکو فاکتورونه</h1>
            <div class="subtitle">د پیرودونکو فاکتورونه، تادیات او پاتې پیسې</div>
        </div>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <!-- ===== FILTER BAR ===== -->
    <div class="filter-bar">
        <div class="filter-bar-title">
            <i class="fas fa-filter"></i>
            فلټر او پلټنه
        </div>
        <form method="GET">
            <div class="filter-grid">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> پیرودونکی</label>
                    <select name="filter_client">
                        <option value="">ټول پیرودونکي</option>
                        <?php foreach ($clients_list as $cl): ?>
                            <option value="<?php echo $cl['id']; ?>" <?php echo ($filter_client == $cl['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cl['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-plus"></i> له نېټې</label>
                    <input type="date" name="from_date" value="<?php echo htmlspecialchars($filter_from); ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-check"></i> تر نېټې</label>
                    <input type="date" name="to_date" value="<?php echo htmlspecialchars($filter_to); ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-search"></i> فاکتور شمېره</label>
                    <input type="text" name="search_invoice" placeholder="INV-..." value="<?php echo htmlspecialchars($search_invoice); ?>">
                </div>
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-search"></i> پلټنه
                </button>
                <a href="sales.php" class="btn btn-reset">
                    <i class="fas fa-times"></i> پاکول
                </a>
            </div>
        </form>

        <?php if ($filter_client > 0 || $filter_from !== '' || $filter_to !== '' || $search_invoice !== ''): ?>
            <div class="active-filters">
                <span style="font-size:0.85rem; color:#64748b; font-weight:600; align-self:center;">فعال فلټر:</span>
                <?php if ($filter_client > 0 && $active_client_name): ?>
                    <span class="filter-chip">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($active_client_name); ?>
                    </span>
                <?php endif; ?>
                <?php if ($filter_from !== ''): ?>
                    <span class="filter-chip">
                        <i class="fas fa-calendar-plus"></i> له: <?php echo htmlspecialchars($filter_from); ?>
                    </span>
                <?php endif; ?>
                <?php if ($filter_to !== ''): ?>
                    <span class="filter-chip">
                        <i class="fas fa-calendar-check"></i> تر: <?php echo htmlspecialchars($filter_to); ?>
                    </span>
                <?php endif; ?>
                <?php if ($search_invoice !== ''): ?>
                    <span class="filter-chip">
                        <i class="fas fa-search"></i> <?php echo htmlspecialchars($search_invoice); ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== STATS ===== -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon usd"><i class="fas fa-dollar-sign"></i></div>
            <div class="stat-info">
                <h3>ټول ډالر (فاکتورونه)</h3>
                <p><?php echo number_format($grand_usd_inv, 2); ?> $</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon afn"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <h3>ټول افغاني (فاکتورونه)</h3>
                <p><?php echo number_format($grand_afn_inv, 2); ?> ؋</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon unpaid-usd"><i class="fas fa-exclamation-circle"></i></div>
            <div class="stat-info">
                <h3>پاتې ډالر (بې تادیه)</h3>
                <p><?php echo number_format($grand_usd_unpaid, 2); ?> $</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon unpaid-afn"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-info">
                <h3>پاتې افغاني (بې تادیه)</h3>
                <p><?php echo number_format($grand_afn_unpaid, 2); ?> ؋</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon count"><i class="fas fa-receipt"></i></div>
            <div class="stat-info">
                <h3>فاکتورونه</h3>
                <p><?php echo $record_count; ?></p>
            </div>
        </div>
    </div>

    <!-- ===== CLIENT SUMMARY TABLE ===== -->
    <div class="card full-width">
        <div class="card-header">
            <i class="fas fa-table"></i>
            <h2>د هر پیرودونکي لنډیز</h2>
            <?php if ($active_client_name): ?>
                <span class="active-client-badge">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($active_client_name); ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="summary-table-wrapper">
            <table class="summary-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="vertical-align: middle;">پیرودونکی</th>
                        <th colspan="3" class="usd-header">💵 ډالر (USD)</th>
                        <th colspan="3" class="afn-header">💴 افغاني (AFN)</th>
                    </tr>
                    <tr>
                        <th class="usd-header">فاکتورونه</th>
                        <th class="usd-header">تادیه شوي</th>
                        <th class="unpaid-header">پاتې</th>
                        <th class="afn-header">فاکتورونه</th>
                        <th class="afn-header">تادیه شوي</th>
                        <th class="unpaid-header">پاتې</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($client_summary) > 0): ?>
                        <?php foreach ($client_summary as $cs): 
                            $has_unpaid = ($cs['usd_unpaid'] > 0.01) || ($cs['afn_unpaid'] > 0.01);
                        ?>
                            <tr class="<?php echo $has_unpaid ? 'row-has-unpaid' : ''; ?>">
                                <td class="client-name-cell"><?php echo htmlspecialchars($cs['full_name']); ?></td>

                                <!-- USD -->
                                <td><?php echo number_format($cs['usd_invoiced'], 2); ?> $</td>
                                <td class="paid-value"><?php echo number_format($cs['usd_paid'], 2); ?> $</td>
                                <td class="unpaid-value <?php echo ($cs['usd_unpaid'] <= 0.01) ? 'zero' : ''; ?>">
                                    <?php echo number_format($cs['usd_unpaid'], 2); ?> $
                                    <?php if ($cs['usd_unpaid'] <= 0.01 && $cs['usd_invoiced'] > 0): ?>
                                        <span class="paid-badge"><i class="fas fa-check"></i> پوره</span>
                                    <?php endif; ?>
                                </td>

                                <!-- AFN -->
                                <td><?php echo number_format($cs['afn_invoiced'], 2); ?> ؋</td>
                                <td class="paid-value"><?php echo number_format($cs['afn_paid'], 2); ?> ؋</td>
                                <td class="unpaid-value <?php echo ($cs['afn_unpaid'] <= 0.01) ? 'zero' : ''; ?>">
                                    <?php echo number_format($cs['afn_unpaid'], 2); ?> ؋
                                    <?php if ($cs['afn_unpaid'] <= 0.01 && $cs['afn_invoiced'] > 0): ?>
                                        <span class="paid-badge"><i class="fas fa-check"></i> پوره</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    د دې فلټر لپاره هیڅ معلومات ونه موندل شول.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <?php if (count($client_summary) > 0): ?>
                <tfoot>
                    <tr>
                        <td class="client-name-cell">ټول</td>
                        <td><?php echo number_format($grand_usd_inv, 2); ?> $</td>
                        <td><?php echo number_format($grand_usd_paid, 2); ?> $</td>
                        <td><?php echo number_format($grand_usd_unpaid, 2); ?> $</td>
                        <td><?php echo number_format($grand_afn_inv, 2); ?> ؋</td>
                        <td><?php echo number_format($grand_afn_paid, 2); ?> ؋</td>
                        <td><?php echo number_format($grand_afn_unpaid, 2); ?> ؋</td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- ===== CONTENT GRID ===== -->
    <div class="content-grid">

        <!-- Add Sale Form -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus-circle"></i>
                <h2>نوی فاکتور اضافه کړئ</h2>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="add_sale" value="1">

                <div class="form-group">
                    <label><i class="fas fa-user"></i> پیرودونکی *</label>
                    <select name="client_id" required>
                        <option value="">-- پیرودونکی غوره کړئ --</option>
                        <?php foreach ($clients_list as $cl): ?>
                            <option value="<?php echo $cl['id']; ?>"><?php echo htmlspecialchars($cl['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> د فاکتور شمېره *</label>
                    <input type="text" name="invoice_number" placeholder="مثال: INV-001" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> نېټه *</label>
                    <input type="date" name="invoice_date" value="<?php echo $today_date; ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> د پیسو ډول *</label>
                    <select name="currency" required>
                        <option value="">-- د پیسو ډول غوره کړئ --</option>
                        <option value="USD">ډالر (USD)</option>
                        <option value="AFN">افغاني (AFN)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-dollar-sign"></i> ټوله اندازه *</label>
                    <input type="number" name="total_amount" step="0.01" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-image"></i> د فاکتور عکس</label>
                    <input type="file" name="invoice_picture" accept="image/*">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> یادونه</label>
                    <textarea name="note" placeholder="اضافي یادونه"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> فاکتور اضافه کړئ
                </button>
            </form>
        </div>

        <!-- Sales List -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i>
                <h2>د فاکتورونو لیست (<?php echo $record_count; ?>)</h2>
            </div>

            <div class="table-wrapper">
                <table class="main-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>پیرودونکی</th>
                            <th>فاکتور</th>
                            <th>نېټه</th>
                            <th>ډول</th>
                            <th>اندازه</th>
                            <th>عکس</th>
                            <th>کړنې</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><span class="id-badge">#<?php echo $row['id']; ?></span></td>
                                    <td><?php echo htmlspecialchars($row['client_name'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['invoice_date']); ?></td>
                                    <td>
                                        <span class="currency-badge currency-<?php echo $row['currency']; ?>">
                                            <?php echo $row['currency']; ?>
                                        </span>
                                    </td>
                                    <td class="amount-cell"><?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td>
                                        <?php if (!empty($row['invoice_picture']) && file_exists(__DIR__ . '/' . $row['invoice_picture'])): ?>
                                            <img src="<?php echo htmlspecialchars($row['invoice_picture']); ?>" 
                                                 class="invoice-thumb" 
                                                 alt="عکس"
                                                 onclick="openImage('<?php echo htmlspecialchars($row['invoice_picture']); ?>')">
                                        <?php else: ?>
                                            <span class="no-picture"><i class="fas fa-image"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?delete_id=<?php echo $row['id']; ?>" 
                                           class="btn-delete" 
                                           title="ړنګول"
                                           onclick="return confirm('ایا تاسو ډاډه یاست چې دا فاکتور ړنګ کړئ؟');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        د دې فلټر لپاره هیڅ فاکتور ونه موندل شو.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<!-- Image Lightbox -->
<div class="image-modal" id="imageModal">
    <button class="image-modal-close" onclick="closeImage()"><i class="fas fa-times"></i></button>
    <img id="modalImage" src="" alt="عکس">
</div>

<script>
    const imageModal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');

    function openImage(src) {
        modalImage.src = src;
        imageModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeImage() {
        imageModal.classList.remove('active');
        document.body.style.overflow = '';
        modalImage.src = '';
    }
    imageModal.addEventListener('click', function(e) {
        if (e.target === imageModal) closeImage();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && imageModal.classList.contains('active')) closeImage();
    });
</script>

</body>
</html>