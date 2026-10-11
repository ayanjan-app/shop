<?php
session_start();
require_once 'db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Create upload folders
$uploadDir = __DIR__ . '/uploads/payments/';
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
// AJAX: GET CLIENT BALANCE
// =========================
if (isset($_GET['ajax_balance'])) {
    header('Content-Type: application/json');
    
    $client_id = intval($_GET['client_id'] ?? 0);
    $currency  = trim($_GET['currency'] ?? '');
    
    if ($client_id <= 0 || !in_array($currency, ['USD', 'AFN'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit();
    }
    
    $invStmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE client_id = ? AND currency = ?");
    $invStmt->bind_param("is", $client_id, $currency);
    $invStmt->execute();
    $invoice_total = $invStmt->get_result()->fetch_assoc()['total'] ?? 0;
    $invStmt->close();
    
    $payStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM client_payments WHERE client_id = ? AND currency = ?");
    $payStmt->bind_param("is", $client_id, $currency);
    $payStmt->execute();
    $paid_total = $payStmt->get_result()->fetch_assoc()['total'] ?? 0;
    $payStmt->close();
    
    $unpaid = $invoice_total - $paid_total;
    
    $nameStmt = $conn->prepare("SELECT full_name FROM clients WHERE id = ?");
    $nameStmt->bind_param("i", $client_id);
    $nameStmt->execute();
    $client_name = $nameStmt->get_result()->fetch_assoc()['full_name'] ?? '';
    $nameStmt->close();
    
    echo json_encode([
        'success'        => true,
        'client_name'    => $client_name,
        'invoice_total'  => round($invoice_total, 2),
        'paid_total'     => round($paid_total, 2),
        'unpaid_total'   => round($unpaid, 2),
        'currency'       => $currency
    ]);
    exit();
}

// =========================
// DELETE PAYMENT (GET → redirect)
// =========================
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    $stmtGet = $conn->prepare("SELECT receipt_picture, notebook_picture FROM client_payments WHERE id = ?");
    $stmtGet->bind_param("i", $delete_id);
    $stmtGet->execute();
    $resGet = $stmtGet->get_result();
    if ($rowGet = $resGet->fetch_assoc()) {
        if (!empty($rowGet['receipt_picture']) && file_exists(__DIR__ . '/' . $rowGet['receipt_picture'])) {
            @unlink(__DIR__ . '/' . $rowGet['receipt_picture']);
        }
        if (!empty($rowGet['notebook_picture']) && file_exists(__DIR__ . '/' . $rowGet['notebook_picture'])) {
            @unlink(__DIR__ . '/' . $rowGet['notebook_picture']);
        }
    }
    $stmtGet->close();

    $stmt = $conn->prepare("DELETE FROM client_payments WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $_SESSION['flash_success'] = "تادیه په بریالیتوب سره ړنګ شوه!";
    } else {
        $_SESSION['flash_error'] = "تېروتنه: " . $stmt->error;
    }
    $stmt->close();

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// =========================
// ADD PAYMENT (POST → redirect)
// =========================
if (isset($_POST['add_payment'])) {
    $client_id       = intval($_POST['client_id'] ?? 0);
    $sale_id         = !empty($_POST['sale_id']) ? intval($_POST['sale_id']) : null;
    $hawala_number   = trim($_POST['hawala_number'] ?? '');
    $currency        = trim($_POST['currency'] ?? '');
    $amount          = trim($_POST['amount'] ?? '');
    $payment_date    = trim($_POST['payment_date'] ?? '');
    $note            = trim($_POST['note'] ?? '');
    $receiptPicture  = null;
    $notebookPicture = null;
    $hasError        = false;

    $allowed_currencies = ['USD', 'AFN'];

    if ($client_id <= 0) {
        $_SESSION['flash_error'] = "پیرودونکی اړین دی";
        $hasError = true;
    } elseif (!in_array($currency, $allowed_currencies)) {
        $_SESSION['flash_error'] = "د پیسو ډول سم نه دی";
        $hasError = true;
    } elseif ($amount === '' || floatval($amount) <= 0) {
        $_SESSION['flash_error'] = "اندازه باید له صفر څخه زیاته وي";
        $hasError = true;
    } elseif (empty($payment_date)) {
        $_SESSION['flash_error'] = "نېټه اړینه ده";
        $hasError = true;
    }

    if (!$hasError && isset($_FILES['receipt_picture']) && $_FILES['receipt_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['receipt_picture']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_ext)) {
            $newName = 'receipt_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target  = $uploadDir . $newName;
            if (move_uploaded_file($_FILES['receipt_picture']['tmp_name'], $target)) {
                $receiptPicture = 'uploads/payments/' . $newName;
            }
        }
    }

    if (!$hasError && isset($_FILES['notebook_picture']) && $_FILES['notebook_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['notebook_picture']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_ext)) {
            $newName = 'notebook_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target  = $uploadDir . $newName;
            if (move_uploaded_file($_FILES['notebook_picture']['tmp_name'], $target)) {
                $notebookPicture = 'uploads/payments/' . $newName;
            }
        }
    }

    if (!$hasError) {
        if ($sale_id === null) {
            $stmt = $conn->prepare("INSERT INTO client_payments (client_id, sale_id, hawala_number, currency, amount, payment_date, receipt_picture, notebook_picture, note) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssss", $client_id, $hawala_number, $currency, $amount, $payment_date, $receiptPicture, $notebookPicture, $note);
        } else {
            $stmt = $conn->prepare("INSERT INTO client_payments (client_id, sale_id, hawala_number, currency, amount, payment_date, receipt_picture, notebook_picture, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssssss", $client_id, $sale_id, $hawala_number, $currency, $amount, $payment_date, $receiptPicture, $notebookPicture, $note);
        }

        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "تادیه په بریالیتوب سره اضافه شوه!";
        } else {
            $_SESSION['flash_error'] = "تېروتنه: " . $stmt->error;
        }
        $stmt->close();
    }

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// =========================
// CLIENT BALANCES (for summary table)
// =========================
$clients_balances = [];
$balSql = "
    SELECT 
        c.id,
        c.full_name,
        COALESCE(inv_usd.total, 0) AS usd_invoiced,
        COALESCE(pay_usd.total, 0) AS usd_paid,
        COALESCE(inv_afn.total, 0) AS afn_invoiced,
        COALESCE(pay_afn.total, 0) AS afn_paid
    FROM clients c
    LEFT JOIN (
        SELECT client_id, SUM(total_amount) AS total 
        FROM sales WHERE currency = 'USD' GROUP BY client_id
    ) inv_usd ON c.id = inv_usd.client_id
    LEFT JOIN (
        SELECT client_id, SUM(amount) AS total 
        FROM client_payments WHERE currency = 'USD' GROUP BY client_id
    ) pay_usd ON c.id = pay_usd.client_id
    LEFT JOIN (
        SELECT client_id, SUM(total_amount) AS total 
        FROM sales WHERE currency = 'AFN' GROUP BY client_id
    ) inv_afn ON c.id = inv_afn.client_id
    LEFT JOIN (
        SELECT client_id, SUM(amount) AS total 
        FROM client_payments WHERE currency = 'AFN' GROUP BY client_id
    ) pay_afn ON c.id = pay_afn.client_id
    ORDER BY c.full_name ASC
";
$balResult = $conn->query($balSql);
if ($balResult) {
    while ($r = $balResult->fetch_assoc()) {
        $r['usd_unpaid'] = $r['usd_invoiced'] - $r['usd_paid'];
        $r['afn_unpaid'] = $r['afn_invoiced'] - $r['afn_paid'];
        $clients_balances[] = $r;
    }
}

$grand_usd_invoiced = 0; $grand_usd_paid = 0; $grand_usd_unpaid = 0;
$grand_afn_invoiced = 0; $grand_afn_paid = 0; $grand_afn_unpaid = 0;
foreach ($clients_balances as $cb) {
    $grand_usd_invoiced += $cb['usd_invoiced'];
    $grand_usd_paid     += $cb['usd_paid'];
    $grand_usd_unpaid   += $cb['usd_unpaid'];
    $grand_afn_invoiced += $cb['afn_invoiced'];
    $grand_afn_paid     += $cb['afn_paid'];
    $grand_afn_unpaid   += $cb['afn_unpaid'];
}

// =========================
// FILTER (for payments table)
// =========================
$filter = $_GET['filter'] ?? 'all';
$where = "";

if ($filter == "today") {
    $where = "WHERE p.payment_date = CURDATE()";
} elseif ($filter == "week") {
    $where = "WHERE YEARWEEK(p.payment_date, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter == "month") {
    $where = "WHERE YEAR(p.payment_date) = YEAR(CURDATE()) AND MONTH(p.payment_date) = MONTH(CURDATE())";
} elseif ($filter == "usd") {
    $where = "WHERE p.currency = 'USD'";
} elseif ($filter == "afn") {
    $where = "WHERE p.currency = 'AFN'";
}

$filter_client = isset($_GET['filter_client']) ? intval($_GET['filter_client']) : 0;
if ($filter_client > 0) {
    if ($where === "") {
        $where = "WHERE p.client_id = " . $filter_client;
    } else {
        $where .= " AND p.client_id = " . $filter_client;
    }
}

// =========================
// GET PAYMENTS
// =========================
$sql = "SELECT p.*, c.full_name AS client_name, s.invoice_number AS invoice_number
        FROM client_payments p
        LEFT JOIN clients c ON p.client_id = c.id
        LEFT JOIN sales s ON p.sale_id = s.id
        $where
        ORDER BY p.id DESC";
$result = mysqli_query($conn, $sql);
$record_count = mysqli_num_rows($result);

// =========================
// TOTALS (filtered)
// =========================
$total_usd = 0;
$total_afn = 0;
$total_sql = "SELECT p.currency, SUM(p.amount) AS total FROM client_payments p $where GROUP BY p.currency";
$total_result = mysqli_query($conn, $total_sql);
if ($total_result) {
    while ($t = mysqli_fetch_assoc($total_result)) {
        if ($t['currency'] === 'USD') $total_usd = $t['total'];
        if ($t['currency'] === 'AFN') $total_afn = $t['total'];
    }
}

// =========================
// CLIENTS (for dropdowns)
// =========================
$clients_list = [];
$clientResult = $conn->query("SELECT id, full_name FROM clients ORDER BY full_name ASC");
if ($clientResult) {
    while ($c = $clientResult->fetch_assoc()) {
        $clients_list[] = $c;
    }
}

// =========================
// SALES (for dropdown)
// =========================
$sales_list = [];
$salesResult = $conn->query("SELECT s.id, s.invoice_number, s.currency, s.total_amount, s.client_id, c.full_name AS client_name 
                            FROM sales s 
                            LEFT JOIN clients c ON s.client_id = c.id 
                            ORDER BY s.id DESC");
if ($salesResult) {
    while ($s = $salesResult->fetch_assoc()) {
        $sales_list[] = $s;
    }
}

$today_date = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>د پیرودونکو تادیات · قرطاسیه</title>
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

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: var(--card-bg); border-radius: var(--border-radius); padding: 24px 20px; box-shadow: var(--shadow); transition: var(--transition); border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 18px; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); }
        .stat-icon { width: 60px; height: 60px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; flex-shrink: 0; }
        .stat-icon.usd { background: #16a34a; }
        .stat-icon.afn { background: #dc2626; }
        .stat-icon.unpaid-usd { background: #f59e0b; }
        .stat-icon.unpaid-afn { background: #8b5cf6; }
        .stat-info h3 { margin: 0; font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }
        .stat-info p { margin: 6px 0 0; font-size: 1.4rem; font-weight: 700; color: var(--primary-color); }

        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 12px; }
        .alert-success { background-color: #dcfce7; color: #166534; border-right: 4px solid var(--success-color); }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border-right: 4px solid var(--danger-color); }

        .content-grid { display: grid; grid-template-columns: 400px 1fr; gap: 25px; align-items: start; }
        @media (max-width: 1100px) { .content-grid { grid-template-columns: 1fr; } }

        .card { background: var(--card-bg); border-radius: var(--border-radius); padding: 25px; box-shadow: var(--shadow); position: relative; overflow: hidden; border: 1px solid #e2e8f0; margin-bottom: 25px; }
        .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--accent-color); }
        .card.full-width { grid-column: 1 / -1; }
        .card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f1f5f9; }
        .card-header i { color: var(--accent-color); font-size: 1.3rem; }
        .card-header h2 { margin: 0; font-size: 1.2rem; color: var(--primary-color); font-weight: 700; }
        .card-header .header-actions { margin-right: auto; display: flex; gap: 8px; }

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

        /* ===== LIVE BALANCE PANEL ===== */
        .balance-box {
            background: linear-gradient(135deg, #f8fafc, #eef2f7);
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 18px;
            display: none;
        }
        .balance-box.active { display: block; animation: fadeIn 0.3s ease; }
        .balance-box .balance-client {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .balance-box .balance-client i { color: var(--accent-color); }
        .balance-rows { display: flex; flex-direction: column; gap: 8px; }
        .balance-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            background: #ffffff;
            border-radius: 8px;
            font-size: 0.9rem;
            border-right: 4px solid #cbd5e1;
        }
        .balance-row .label { color: var(--text-muted); font-weight: 600; }
        .balance-row .value { font-weight: 700; }
        .balance-row.invoice { border-right-color: var(--primary-color); }
        .balance-row.invoice .value { color: var(--primary-color); }
        .balance-row.paid { border-right-color: #16a34a; }
        .balance-row.paid .value { color: #16a34a; }
        .balance-row.unpaid { background: #fef3c7; border-right-color: #d97706; }
        .balance-row.unpaid .value { color: #d97706; font-size: 1.05rem; }
        .balance-row.remaining { background: #dcfce7; border-right-color: #16a34a; }
        .balance-row.remaining .value { color: #16a34a; font-size: 1.05rem; }
        .balance-row.overpaid { background: #fee2e2; border-right-color: #dc2626; }
        .balance-row.overpaid .value { color: #dc2626; font-size: 1.05rem; }

        .balance-loading { display: none; padding: 15px; text-align: center; color: var(--text-muted); font-size: 0.9rem; }
        .balance-loading.active { display: block; }

        .balance-error {
            display: none;
            padding: 12px 16px;
            background: #fee2e2;
            color: #991b1b;
            border-right: 4px solid #dc2626;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.88rem;
            font-weight: 600;
        }
        .balance-error.active { display: block; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 20px; border: none; border-radius: 10px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: var(--transition); font-family: inherit; text-decoration: none; }
        .btn-primary { background: var(--primary-color); color: white; width: 100%; box-shadow: var(--shadow); }
        .btn-primary:hover { background: var(--secondary-color); transform: translateY(-2px); box-shadow: 0 15px 30px rgba(30,60,114,0.2); }
        .btn-secondary { background: var(--accent-color); color: var(--primary-color); box-shadow: var(--shadow); }
        .btn-secondary:hover { background: #d4a017; transform: translateY(-2px); }
        .btn-reset { background: #f1f5f9; color: var(--text-muted); border: 2px solid #e2e8f0; }
        .btn-reset:hover { background: #e2e8f0; color: var(--primary-color); }

        .filter-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
        .filter-tabs a { padding: 9px 18px; border-radius: 25px; text-decoration: none; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); background: #f1f5f9; transition: var(--transition); border: 2px solid transparent; }
        .filter-tabs a:hover { background: #e2e8f0; color: var(--primary-color); transform: translateY(-2px); }
        .filter-tabs a.active { background: var(--primary-color); color: white; box-shadow: 0 4px 12px rgba(30,60,114,0.25); }
        .filter-tabs a.active.usd-tab { background: #16a34a; }
        .filter-tabs a.active.afn-tab { background: #dc2626; }

        .search-bar { background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 18px; margin-bottom: 20px; }
        .search-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .search-row .form-group { flex: 1; min-width: 160px; margin-bottom: 0; }
        .search-row .btn { width: auto; padding: 12px 24px; }

        /* ===== TABLE WITH HORIZONTAL SCROLL ===== */
        .table-scroll {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 600px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
        }
        .table-scroll::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }
        .table-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 8px;
        }
        .table-scroll::-webkit-scrollbar-thumb {
            background: var(--accent-color);
            border-radius: 8px;
            border: 2px solid #f1f5f9;
        }
        .table-scroll::-webkit-scrollbar-thumb:hover {
            background: #d4a017;
        }

        /* ===== BALANCE TABLE (the summary at the top) ===== */
        .balance-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            min-width: 1000px; /* forces horizontal scroll */
        }
        .balance-table thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background: var(--primary-color);
            color: white;
            font-weight: 700;
            font-size: 0.82rem;
            padding: 14px 12px;
            text-align: center;
            white-space: nowrap;
            border: none;
        }
        .balance-table thead th.client-col {
            background: #0f2a52;
            text-align: right;
        }
        /* AFN side (right side in RTL = first columns) */
        .balance-table thead th.afn-header {
            background: #991b1b;
        }
        /* USD side (left side in RTL = last columns) */
        .balance-table thead th.usd-header {
            background: #166534;
        }

        .balance-table tbody td {
            padding: 12px 10px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
            border-left: 1px solid #f1f5f9;
            white-space: nowrap;
            font-weight: 600;
        }
        .balance-table tbody td:first-child {
            border-left: none;
        }
        .balance-table tbody td.client-name-cell {
            text-align: right;
            font-weight: 700;
            color: var(--primary-color);
            background: #f8fafc;
            position: sticky;
            right: 0;
            z-index: 2;
            box-shadow: -2px 0 5px rgba(0,0,0,0.05);
        }
        .balance-table tbody tr:hover {
            background: #f8fafc;
        }
        .balance-table tbody tr.row-has-unpaid {
            background: #fffbeb;
        }
        .balance-table tbody tr.row-has-unpaid:hover {
            background: #fef3c7;
        }
        .balance-table tbody tr.row-has-unpaid td.client-name-cell {
            background: #fef3c7;
        }
        .balance-table .paid-value {
            color: #16a34a;
            font-weight: 700;
        }
        .balance-table .unpaid-value {
            color: #d97706;
            font-weight: 700;
        }
        .balance-table .unpaid-value.zero {
            color: #16a34a;
        }
        .balance-table .invoiced-value {
            color: var(--primary-color);
            font-weight: 700;
        }

        .balance-table tfoot td {
            position: sticky;
            bottom: 0;
            background: #e2e8f0;
            font-weight: 800;
            color: var(--primary-color);
            border-top: 3px solid var(--primary-color);
            font-size: 0.9rem;
            padding: 14px 10px;
            text-align: center;
            white-space: nowrap;
            z-index: 4;
        }
        .balance-table tfoot td:first-child {
            background: #cbd5e1;
            text-align: right;
            position: sticky;
            right: 0;
            z-index: 6;
            box-shadow: -2px 0 5px rgba(0,0,0,0.08);
        }

        .paid-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: #dcfce7;
            color: #166534;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-right: 4px;
        }

        /* ===== PAYMENTS TABLE ===== */
        .table-wrapper {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 600px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
        }
        .table-wrapper::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        .table-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 8px;
        }
        .table-wrapper::-webkit-scrollbar-thumb {
            background: var(--accent-color);
            border-radius: 8px;
        }
        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #d4a017;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
            min-width: 900px;
        }
        thead {
            background: var(--primary-color);
        }
        thead th {
            position: sticky;
            top: 0;
            z-index: 3;
        }
        th {
            padding: 14px 12px;
            text-align: right;
            color: white;
            font-weight: 600;
            font-size: 0.82rem;
            white-space: nowrap;
        }
        td {
            padding: 13px 12px;
            border-bottom: 1px solid #e2e8f0;
            color: var(--text-color);
            text-align: right;
            vertical-align: middle;
        }
        tbody tr:hover { background: #f8fafc; }
        tbody tr:last-child td { border-bottom: none; }

        .amount-cell { font-weight: 700; color: var(--primary-color); }
        .id-badge { display: inline-block; background: #f1f5f9; color: var(--text-muted); padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .currency-badge { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 700; }
        .currency-USD { background: #dcfce7; color: #166534; }
        .currency-AFN { background: #fee2e2; color: #991b1b; }
        .hawala-badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 12px; font-size: 0.78rem; font-weight: 700; }
        .thumb { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; border: 2px solid var(--accent-color); cursor: pointer; transition: var(--transition); margin-left: 4px; }
        .thumb:hover { transform: scale(1.1); }
        .no-picture { width: 40px; height: 40px; border-radius: 8px; background: #f1f5f9; display: inline-flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 0.9rem; }
        .empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px; display: block; }

        .btn-delete { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 8px; background: var(--danger-color); color: white; text-decoration: none; transition: var(--transition); font-size: 0.85rem; }
        .btn-delete:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(239,68,68,0.4); }

        .total-footer { margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .total-box { padding: 18px 24px; border-radius: var(--border-radius); display: flex; align-items: center; justify-content: space-between; color: white; }
        .total-box.usd { background: linear-gradient(135deg, #16a34a, #15803d); }
        .total-box.afn { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .total-box .total-label { display: flex; align-items: center; gap: 10px; font-size: 0.95rem; font-weight: 600; }
        .total-box .total-label i { font-size: 1.3rem; }
        .total-box .total-value { font-size: 1.5rem; font-weight: 800; }
        @media (max-width: 480px) { .total-footer { grid-template-columns: 1fr; } }

        /* ===== SUMMARY HINT BOX ===== */
        .summary-hint {
            background: #eff6ff;
            border: 2px dashed #93c5fd;
            color: #1e40af;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .summary-hint i { font-size: 1.2rem; color: #3b82f6; }

        .image-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(6px); }
        .image-modal.active { display: flex; }
        .image-modal img { max-width: 90vw; max-height: 85vh; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.6); border: 3px solid var(--accent-color); }
        .image-modal-close { position: absolute; top: 20px; right: 25px; background: var(--danger-color); color: white; border: none; width: 48px; height: 48px; border-radius: 50%; font-size: 1.3rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }

        @media (max-width: 768px) {
            .page-header h1 { font-size: 1.4rem; }
            .stat-info p { font-size: 1.15rem; }
            .card { padding: 18px; }
            .search-row { flex-direction: column; align-items: stretch; }
            .search-row .btn { width: 100%; }
            .form-group select { width: 100% !important; font-size: 16px !important; }
            .balance-table tbody td, .balance-table thead th { font-size: 0.78rem; padding: 8px 6px; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1><i class="fas fa-hand-holding-usd"></i> د پیرودونکو تادیات</h1>
            <div class="subtitle">له پیرودونکو څخه ترلاسه شوې تادیات ثبت او مدیریت کړئ</div>
        </div>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <!-- ===== STATS ===== -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon usd"><i class="fas fa-dollar-sign"></i></div>
            <div class="stat-info">
                <h3>ټول ډالري فاکتورونه</h3>
                <p><?php echo number_format($grand_usd_invoiced, 2); ?> $</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon afn"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <h3>ټول افغاني فاکتورونه</h3>
                <p><?php echo number_format($grand_afn_invoiced, 2); ?> ؋</p>
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
    </div>

    <!-- ===== CLIENT BALANCES TABLE ===== -->
    <div class="card full-width">
        <div class="card-header">
            <i class="fas fa-balance-scale"></i>
            <h2>د هر پیرودونکي د تادیاتو لنډیز</h2>
        </div>

        <div class="summary-hint">
            <i class="fas fa-info-circle"></i>
            <span>
                <strong>ښي اړخ (سره):</strong> افغاني حساب &nbsp;|&nbsp;
                <strong>کیڼ اړخ (شین):</strong> ډالري حساب &nbsp;|&nbsp;
                <strong>ژېړه کرښه:</strong> پاتې پیسې لري
            </span>
        </div>

        <div class="table-scroll">
            <table class="balance-table">
                <thead>
                    <tr>
                        <!-- AFN side (Right in RTL) -->
                        <th class="client-col">پیرودونکی</th>
                        <th class="afn-header">افغاني<br>فاکتورونه</th>
                        <th class="afn-header">افغاني<br>تادیه شوي</th>
                        <th class="afn-header">پاتې افغاني</th>
                        <!-- USD side (Left in RTL) -->
                        <th class="usd-header">ډالر<br>فاکتورونه</th>
                        <th class="usd-header">ډالر<br>تادیه شوي</th>
                        <th class="usd-header">پاتې ډالر</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($clients_balances) > 0): ?>
                        <?php foreach ($clients_balances as $cb): 
                            $has_unpaid = ($cb['usd_unpaid'] > 0.01) || ($cb['afn_unpaid'] > 0.01);
                        ?>
                            <tr class="<?php echo $has_unpaid ? 'row-has-unpaid' : ''; ?>">
                                <td class="client-name-cell"><?php echo htmlspecialchars($cb['full_name']); ?></td>

                                <!-- AFN -->
                                <td class="invoiced-value"><?php echo number_format($cb['afn_invoiced'], 2); ?> ؋</td>
                                <td class="paid-value"><?php echo number_format($cb['afn_paid'], 2); ?> ؋</td>
                                <td class="unpaid-value <?php echo ($cb['afn_unpaid'] <= 0.01) ? 'zero' : ''; ?>">
                                    <?php echo number_format($cb['afn_unpaid'], 2); ?> ؋
                                    <?php if ($cb['afn_unpaid'] <= 0.01 && $cb['afn_invoiced'] > 0): ?>
                                        <span class="paid-badge"><i class="fas fa-check"></i> پوره</span>
                                    <?php endif; ?>
                                </td>

                                <!-- USD -->
                                <td class="invoiced-value"><?php echo number_format($cb['usd_invoiced'], 2); ?> $</td>
                                <td class="paid-value"><?php echo number_format($cb['usd_paid'], 2); ?> $</td>
                                <td class="unpaid-value <?php echo ($cb['usd_unpaid'] <= 0.01) ? 'zero' : ''; ?>">
                                    <?php echo number_format($cb['usd_unpaid'], 2); ?> $
                                    <?php if ($cb['usd_unpaid'] <= 0.01 && $cb['usd_invoiced'] > 0): ?>
                                        <span class="paid-badge"><i class="fas fa-check"></i> پوره</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-users-slash"></i>
                                    هیڅ پیرودونکی ونه موندل شو.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>ټول</td>
                        <td><?php echo number_format($grand_afn_invoiced, 2); ?> ؋</td>
                        <td><?php echo number_format($grand_afn_paid, 2); ?> ؋</td>
                        <td><?php echo number_format($grand_afn_unpaid, 2); ?> ؋</td>
                        <td><?php echo number_format($grand_usd_invoiced, 2); ?> $</td>
                        <td><?php echo number_format($grand_usd_paid, 2); ?> $</td>
                        <td><?php echo number_format($grand_usd_unpaid, 2); ?> $</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="content-grid">

        <!-- Add Payment Form -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus-circle"></i>
                <h2>نوې تادیه اضافه کړئ</h2>
            </div>
            <form method="POST" enctype="multipart/form-data" id="paymentForm">
                <input type="hidden" name="add_payment" value="1">

                <div class="form-group">
                    <label><i class="fas fa-user"></i> پیرودونکی *</label>
                    <select name="client_id" id="clientSelect" required>
                        <option value="">-- پیرودونکی غوره کړئ --</option>
                        <?php foreach ($clients_list as $cl): ?>
                            <option value="<?php echo $cl['id']; ?>">
                                <?php echo htmlspecialchars($cl['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> د پیسو ډول *</label>
                    <select name="currency" id="currencySelect" required>
                        <option value="">-- د پیسو ډول غوره کړئ --</option>
                        <option value="USD">ډالر (USD)</option>
                        <option value="AFN">افغاني (AFN)</option>
                    </select>
                </div>

                <!-- LIVE BALANCE BOX -->
                <div class="balance-error" id="balanceError"></div>
                <div class="balance-loading" id="balanceLoading">
                    <i class="fas fa-spinner fa-spin"></i> محاسبه روانه ده...
                </div>
                <div class="balance-box" id="balanceBox">
                    <div class="balance-client">
                        <i class="fas fa-user-circle"></i>
                        <span id="balanceClientName"></span>
                    </div>
                    <div class="balance-rows" id="balanceRows"></div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> د حوالې شمېره</label>
                    <input type="text" name="hawala_number" placeholder="د حوالې شمېره ولیکئ">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-file-invoice"></i> فاکتور (اختیاري)</label>
                    <select name="sale_id" id="saleSelect">
                        <option value="">-- فاکتور سره تړاو نه لري --</option>
                        <?php foreach ($sales_list as $sl): ?>
                            <option value="<?php echo $sl['id']; ?>"
                                    data-client="<?php echo $sl['client_id']; ?>"
                                    data-currency="<?php echo htmlspecialchars($sl['currency']); ?>">
                                <?php echo htmlspecialchars($sl['invoice_number']); ?> 
                                (<?php echo htmlspecialchars($sl['client_name'] ?? ''); ?> - 
                                <?php echo number_format($sl['total_amount'], 2); ?> <?php echo $sl['currency']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-dollar-sign"></i> اندازه *</label>
                    <input type="number" name="amount" id="amountInput" step="0.01" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> نېټه *</label>
                    <input type="date" name="payment_date" value="<?php echo $today_date; ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-image"></i> د رسید عکس (اختیاري)</label>
                    <input type="file" name="receipt_picture" accept="image/*">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-book"></i> د نوټ بوک عکس (اختیاري)</label>
                    <input type="file" name="notebook_picture" accept="image/*">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> یادونه</label>
                    <textarea name="note" placeholder="اضافي یادونه"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> تادیه اضافه کړئ
                </button>
            </form>
        </div>

        <!-- Payments List -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i>
                <h2>د تادیاتو لیست (<?php echo $record_count; ?>)</h2>
            </div>

            <div class="filter-tabs">
                <a href="?filter=all" class="<?php echo $filter == 'all' ? 'active' : ''; ?>"><i class="fas fa-list"></i> ټول</a>
                <a href="?filter=today" class="<?php echo $filter == 'today' ? 'active' : ''; ?>"><i class="fas fa-calendar-day"></i> نن</a>
                <a href="?filter=week" class="<?php echo $filter == 'week' ? 'active' : ''; ?>"><i class="fas fa-calendar-week"></i> دې اونۍ</a>
                <a href="?filter=month" class="<?php echo $filter == 'month' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> دې میاشت</a>
                <a href="?filter=usd" class="<?php echo $filter == 'usd' ? 'active usd-tab' : ''; ?>"><i class="fas fa-dollar-sign"></i> ډالر</a>
                <a href="?filter=afn" class="<?php echo $filter == 'afn' ? 'active afn-tab' : ''; ?>"><i class="fas fa-money-bill-wave"></i> افغاني</a>
            </div>

            <div class="search-bar">
                <form method="GET">
                    <div class="search-row">
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
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-search"></i> پلټنه
                        </button>
                        <?php if ($filter_client > 0): ?>
                            <a href="?" class="btn btn-reset">
                                <i class="fas fa-times"></i> پاکول
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>پیرودونکی</th>
                            <th>حواله</th>
                            <th>فاکتور</th>
                            <th>ډول</th>
                            <th>اندازه</th>
                            <th>نېټه</th>
                            <th>عکسونه</th>
                            <th>کړنې</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><span class="id-badge">#<?php echo $row['id']; ?></span></td>
                                    <td><?php echo htmlspecialchars($row['client_name'] ?? '—'); ?></td>
                                    <td>
                                        <?php if (!empty($row['hawala_number'])): ?>
                                            <span class="hawala-badge"><?php echo htmlspecialchars($row['hawala_number']); ?></span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['invoice_number'] ?? '—'); ?></td>
                                    <td>
                                        <span class="currency-badge currency-<?php echo $row['currency']; ?>">
                                            <?php echo $row['currency']; ?>
                                        </span>
                                    </td>
                                    <td class="amount-cell"><?php echo number_format($row['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['payment_date']); ?></td>
                                    <td>
                                        <?php if (!empty($row['receipt_picture']) && file_exists(__DIR__ . '/' . $row['receipt_picture'])): ?>
                                            <img src="<?php echo htmlspecialchars($row['receipt_picture']); ?>" 
                                                 class="thumb" 
                                                 alt="رسید"
                                                 title="رسید"
                                                 onclick="openImage('<?php echo htmlspecialchars($row['receipt_picture']); ?>')">
                                        <?php endif; ?>
                                        <?php if (!empty($row['notebook_picture']) && file_exists(__DIR__ . '/' . $row['notebook_picture'])): ?>
                                            <img src="<?php echo htmlspecialchars($row['notebook_picture']); ?>" 
                                                 class="thumb" 
                                                 alt="نوټ بوک"
                                                 title="نوټ بوک"
                                                 onclick="openImage('<?php echo htmlspecialchars($row['notebook_picture']); ?>')">
                                        <?php endif; ?>
                                        <?php if (empty($row['receipt_picture']) && empty($row['notebook_picture'])): ?>
                                            <span class="no-picture"><i class="fas fa-image"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?delete_id=<?php echo $row['id']; ?>" 
                                           class="btn-delete" 
                                           title="ړنګول"
                                           onclick="return confirm('ایا تاسو ډاډه یاست چې دا تادیه ړنګ کړئ؟');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        د دې فلټر لپاره هیڅ تادیه ونه موندل شوه.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="total-footer">
                <div class="total-box usd">
                    <div class="total-label"><i class="fas fa-dollar-sign"></i> ټول ډالر</div>
                    <div class="total-value"><?php echo number_format($total_usd, 2); ?> $</div>
                </div>
                <div class="total-box afn">
                    <div class="total-label"><i class="fas fa-money-bill-wave"></i> ټول افغاني</div>
                    <div class="total-value"><?php echo number_format($total_afn, 2); ?> ؋</div>
                </div>
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
    // ===== Image Lightbox =====
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

    // ===== LIVE BALANCE =====
    const clientSelect = document.getElementById('clientSelect');
    const currencySelect = document.getElementById('currencySelect');
    const amountInput = document.getElementById('amountInput');
    const balanceBox = document.getElementById('balanceBox');
    const balanceLoading = document.getElementById('balanceLoading');
    const balanceError = document.getElementById('balanceError');
    const balanceClientName = document.getElementById('balanceClientName');
    const balanceRows = document.getElementById('balanceRows');
    const saleSelect = document.getElementById('saleSelect');

    let currentBalance = null;

    const AJAX_URL = window.location.pathname;

    function showError(msg) {
        balanceError.textContent = '⚠ ' + msg;
        balanceError.classList.add('active');
    }
    function hideError() {
        balanceError.classList.remove('active');
    }

    function fetchBalance() {
        const clientId = clientSelect.value;
        const currency = currencySelect.value;

        if (!clientId || !currency) {
            balanceBox.classList.remove('active');
            currentBalance = null;
            return;
        }

        const clientName = clientSelect.options[clientSelect.selectedIndex].text;
        balanceClientName.textContent = clientName;

        balanceLoading.classList.add('active');
        balanceBox.classList.remove('active');
        hideError();

        const url = AJAX_URL + '?ajax_balance=1&client_id=' +
                    encodeURIComponent(clientId) +
                    '&currency=' + encodeURIComponent(currency);

        fetch(url)
            .then(response => response.text())
            .then(text => {
                balanceLoading.classList.remove('active');

                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    showError('Server did not return JSON.');
                    console.error('Raw response:', text);
                    return;
                }

                if (!data.success) {
                    showError(data.message || 'Unknown error');
                    return;
                }

                currentBalance = data;
                renderBalance(data, 0);
                balanceBox.classList.add('active');
            })
            .catch(err => {
                balanceLoading.classList.remove('active');
                showError('Network error: ' + err.message);
            });
    }

    function renderBalance(data, payAmount) {
        const currencySymbol = data.currency === 'USD' ? '$' : '؋';
        const unpaid = data.unpaid_total;
        const remaining = unpaid - payAmount;
        const isOverpaid = remaining < -0.001;

        let html = `
            <div class="balance-row invoice">
                <span class="label">ټول فاکتورونه</span>
                <span class="value">${data.invoice_total.toLocaleString('en-US', {minimumFractionDigits: 2})} ${currencySymbol}</span>
            </div>
            <div class="balance-row paid">
                <span class="label">ترلاسه شوي</span>
                <span class="value">${data.paid_total.toLocaleString('en-US', {minimumFractionDigits: 2})} ${currencySymbol}</span>
            </div>
            <div class="balance-row unpaid">
                <span class="label">پاتې (بې تادیه)</span>
                <span class="value">${unpaid.toLocaleString('en-US', {minimumFractionDigits: 2})} ${currencySymbol}</span>
            </div>
        `;

        if (payAmount > 0) {
            if (isOverpaid) {
                html += `
                    <div class="balance-row overpaid">
                        <span class="label">⚠️ اضافه تادیه</span>
                        <span class="value">${Math.abs(remaining).toLocaleString('en-US', {minimumFractionDigits: 2})} ${currencySymbol}</span>
                    </div>
                `;
            } else {
                html += `
                    <div class="balance-row remaining">
                        <span class="label">✓ د تادیې وروسته پاتې</span>
                        <span class="value">${remaining.toLocaleString('en-US', {minimumFractionDigits: 2})} ${currencySymbol}</span>
                    </div>
                `;
            }
        }

        balanceRows.innerHTML = html;
    }

    clientSelect.addEventListener('change', fetchBalance);
    currencySelect.addEventListener('change', fetchBalance);

    amountInput.addEventListener('input', function() {
        if (currentBalance) {
            const val = parseFloat(this.value) || 0;
            renderBalance(currentBalance, val);
        }
    });

    if (saleSelect) {
        saleSelect.addEventListener('change', function() {
            if (!this.value) return;
            const opt = this.options[this.selectedIndex];
            const invCurrency = opt.dataset.currency;
            const invClient = opt.dataset.client;

            if (invCurrency === 'USD' || invCurrency === 'AFN') {
                currencySelect.value = invCurrency;
            }
            if (invClient) {
                clientSelect.value = invClient;
            }
            fetchBalance();
        });
    }
</script>

</body>
</html>