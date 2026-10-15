<?php
session_start();
require_once 'db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// =========================
// PHP CONFIG FOR FAST UPLOADS
// =========================
@ini_set('upload_max_filesize', '10M');
@ini_set('post_max_size', '12M');
@ini_set('memory_limit', '128M');
@ini_set('max_execution_time', '60');

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
// AFGHAN (SOLAR HIJRI) DATE CONVERTER
// =========================
function gregorianToAfghan($gy, $gm, $gd) {
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function afghanToGregorian($jy, $jm, $jd) {
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (((int)($jy / 33)) * 8) + ((int)((($jy % 33) + 3) / 4)) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * ((int)($days / 146097));
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * ((int)(--$days / 36524));
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $gy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = [0,31,($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0) ? 29 : 28,31,30,31,30,31,31,30,31,30,31];
    for ($gm = 0; $gm < 13 && $gd > $sal_a[$gm]; $gm++) {
        $gd -= $sal_a[$gm];
    }
    return [$gy, $gm, $gd];
}

function formatAfghanDate($gy, $gm, $gd) {
    return sprintf('%04d/%02d/%02d', $gy, $gm, $gd);
}

function formatAfghanDateFromGregorian($gregorianDate) {
    if (empty($gregorianDate) || $gregorianDate === '0000-00-00') return '—';
    $parts = explode('-', $gregorianDate);
    if (count($parts) < 3) return $gregorianDate;
    [$gy, $gm, $gd] = [(int)$parts[0], (int)$parts[1], (int)$parts[2]];
    [$jy, $jm, $jd] = gregorianToAfghan($gy, $gm, $gd);
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}

// =========================
// AJAX: DATE CONVERTER
// =========================
if (isset($_GET['ajax_date_convert'])) {
    header('Content-Type: application/json');
    
    $type = $_GET['type'] ?? '';
    
    if ($type === 'to_afghan') {
        $gy = intval($_GET['gy'] ?? 0);
        $gm = intval($_GET['gm'] ?? 0);
        $gd = intval($_GET['gd'] ?? 0);
        
        if ($gy <= 0 || $gm <= 0 || $gm > 12 || $gd <= 0 || $gd > 31) {
            echo json_encode(['success' => false, 'message' => 'Invalid Gregorian date']);
            exit();
        }
        
        [$jy, $jm, $jd] = gregorianToAfghan($gy, $gm, $gd);
        echo json_encode([
            'success' => true,
            'afghan' => formatAfghanDate($jy, $jm, $jd),
            'jy' => $jy, 'jm' => $jm, 'jd' => $jd
        ]);
        exit();
    }
    
    if ($type === 'to_gregorian') {
        $jy = intval($_GET['jy'] ?? 0);
        $jm = intval($_GET['jm'] ?? 0);
        $jd = intval($_GET['jd'] ?? 0);
        
        if ($jy <= 0 || $jm <= 0 || $jm > 12 || $jd <= 0 || $jd > 31) {
            echo json_encode(['success' => false, 'message' => 'Invalid Afghan date']);
            exit();
        }
        
        [$gy, $gm, $gd] = afghanToGregorian($jy, $jm, $jd);
        echo json_encode([
            'success' => true,
            'gregorian' => sprintf('%04d-%02d-%02d', $gy, $gm, $gd),
            'gy' => $gy, 'gm' => $gm, 'gd' => $gd
        ]);
        exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit();
}

// =========================
// SERVER-SIDE IMAGE COMPRESSION (GD)
// =========================
function compressImageServer($sourcePath, $destPath, $maxSizeKB = 50, $maxDimension = 800) {
    if (!function_exists('imagecreatefromstring')) return false;

    $info = @getimagesize($sourcePath);
    if (!$info) return false;

    $mime = $info['mime'];
    $data = file_get_contents($sourcePath);
    if ($data === false) return false;

    $src = @imagecreatefromstring($data);
    if (!$src) return false;

    $origW = imagesx($src);
    $origH = imagesy($src);

    $ratio = min($maxDimension / $origW, $maxDimension / $origH, 1);
    $newW = max(1, (int)round($origW * $ratio));
    $newH = max(1, (int)round($origH * $ratio));

    $dst = imagecreatetruecolor($newW, $newH);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);

    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        imagealphablending($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    $minQ = 10; $maxQ = 95; $bestQ = 60;
    for ($i = 0; $i < 8; $i++) {
        $midQ = (int)(($minQ + $maxQ) / 2);
        ob_start();
        imagejpeg($dst, null, $midQ);
        $size = ob_get_clean();
        if (strlen($size) / 1024 <= $maxSizeKB) {
            $bestQ = $midQ;
            $minQ = $midQ + 1;
        } else {
            $maxQ = $midQ - 1;
        }
        if ($minQ > $maxQ) break;
    }

    $saved = imagejpeg($dst, $destPath, $bestQ);
    imagedestroy($src);
    imagedestroy($dst);
    return $saved;
}

// =========================
// AJAX: GET CLIENT BALANCE FOR SALES PAGE
// =========================
if (isset($_GET['ajax_client_balance'])) {
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

    // ===== INVOICE PICTURE (with compression) =====
    if (!$hasError && isset($_FILES['invoice_picture']) && $_FILES['invoice_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['invoice_picture']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_ext)) {
            $newName = 'invoice_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
            $target  = $uploadDir . $newName;
            if (compressImageServer($_FILES['invoice_picture']['tmp_name'], $target, 50, 800)) {
                $invoicePicture = 'uploads/sales/' . $newName;
            } else {
                $_SESSION['flash_error'] = "د عکس پروسس کول ناکام شول!";
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

$saleWhere = [];
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
list($today_jy, $today_jm, $today_jd) = gregorianToAfghan((int)date('Y'), (int)date('n'), (int)date('j'));
$today_afghan = formatAfghanDate($today_jy, $today_jm, $today_jd);

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

        .compress-hint {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .compress-hint i { color: var(--accent-color); }

        /* ===== SEARCHABLE CLIENT COMBO (input + dropdown) ===== */
        .client-combo {
            position: relative;
            width: 100%;
        }
        .client-combo input[type="text"] {
            width: 100%;
            padding: 12px 44px 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            background: var(--card-bg);
            color: var(--text-color);
            text-align: right;
            min-height: 48px;
            transition: var(--transition);
        }
        .client-combo input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(30,60,114,0.1);
        }
        .client-combo .combo-arrow {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            font-size: 0.85rem;
            pointer-events: none;
        }
        .client-combo .combo-list {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: #ffffff;
            border: 2px solid var(--primary-color);
            border-radius: 10px;
            max-height: 240px;
            overflow-y: auto;
            z-index: 50;
            display: none;
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }
        .client-combo .combo-list.active { display: block; }
        .client-combo .combo-list::-webkit-scrollbar { width: 8px; }
        .client-combo .combo-list::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 8px; }
        .client-combo .combo-list::-webkit-scrollbar-thumb { background: var(--accent-color); border-radius: 8px; }
        .client-combo .combo-item {
            padding: 10px 14px;
            cursor: pointer;
            font-size: 0.92rem;
            text-align: right;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s ease;
        }
        .client-combo .combo-item:last-child { border-bottom: none; }
        .client-combo .combo-item:hover,
        .client-combo .combo-item.highlight {
            background: #eff6ff;
            color: var(--primary-color);
            font-weight: 700;
        }
        .client-combo .combo-item .client-id {
            color: var(--text-muted);
            font-size: 0.78rem;
            margin-left: 8px;
            font-weight: 600;
        }
        .client-combo .combo-empty {
            padding: 14px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.88rem;
        }

        /* ===== DATE CONVERTER ===== */
        .date-converter {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 2px solid #7dd3fc;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 18px;
        }
        .date-converter .converter-title {
            font-weight: 700;
            color: #0369a1;
            font-size: 0.9rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .date-converter .converter-title i { color: #0ea5e9; }
        .date-converter-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            align-items: end;
        }
        @media (max-width: 480px) {
            .date-converter-row { grid-template-columns: 1fr; }
        }
        .date-field { min-width: 0; }
        .date-field label {
            display: block;
            margin-bottom: 5px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #0369a1;
        }
        .date-field label i { color: #0ea5e9; margin-left: 4px; }
        .date-field input[type="date"] {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #bae6fd;
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: inherit;
            background: #ffffff;
            color: #0c4a6e;
            transition: var(--transition);
            direction: ltr;
            text-align: left;
            min-height: 44px;
        }
        .date-field input[type="date"]:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(14,165,233,0.15);
        }
        /* FIX: equal-width selects that never overflow the container */
        .date-field .afghan-date-input {
            display: grid;
            grid-template-columns: 1fr 1.4fr 1fr;
            gap: 6px;
            width: 100%;
        }
        .date-field .afghan-date-input select {
            width: 100%;
            min-width: 0;
            padding: 10px 4px;
            border: 2px solid #bae6fd;
            border-radius: 10px;
            font-size: 0.85rem;
            font-family: inherit;
            background: #ffffff;
            color: #0c4a6e;
            transition: var(--transition);
            text-align: center;
            min-height: 44px;
            -webkit-appearance: auto !important;
            appearance: auto !important;
            background-image: none !important;
            cursor: pointer;
            box-sizing: border-box;
            text-overflow: ellipsis;
        }
        .date-field .afghan-date-input select:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(14,165,233,0.15);
        }
        .date-sync-hint {
            font-size: 0.7rem;
            color: #0369a1;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
        }
        .date-sync-hint i { color: #0ea5e9; font-size: 0.75rem; }
        .date-converter .converter-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #0ea5e9;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 700;
            margin-right: 6px;
        }

        /* ===== DUAL DATE CELLS IN TABLE ===== */
        .date-us-cell {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 700;
            color: #1e3c72;
            font-size: 0.85rem;
        }
        .date-us-cell i {
            color: #16a34a;
            font-size: 0.72rem;
        }
        .date-af-cell {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 700;
            color: #991b1b;
            font-size: 0.85rem;
        }
        .date-af-cell i {
            color: #e6b12e;
            font-size: 0.72rem;
        }

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
        .balance-row.new-total { background: #dbeafe; border-right-color: #2563eb; }
        .balance-row.new-total .value { color: #2563eb; font-size: 1.05rem; }

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
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
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

        /* ===== SUMMARY TABLE SEARCH ===== */
        .summary-search-bar {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .summary-search-bar .search-input-wrap {
            flex: 1;
            min-width: 240px;
            position: relative;
        }
        .summary-search-bar .search-input-wrap i {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--accent-color);
            font-size: 0.95rem;
            pointer-events: none;
        }
        .summary-search-bar input[type="text"] {
            width: 100%;
            padding: 12px 42px 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            background: #ffffff;
            transition: var(--transition);
            text-align: right;
        }
        .summary-search-bar input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(30,60,114,0.1);
        }
        .summary-search-bar .filter-info {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: #eff6ff;
            color: #1e40af;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid #bfdbfe;
        }

        /* ===== SUMMARY TABLE (fixed 300px height with scroll) ===== */
        .summary-table-wrapper {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 300px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            background: #ffffff;
        }
        .summary-table-wrapper::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        .summary-table-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 8px;
        }
        .summary-table-wrapper::-webkit-scrollbar-thumb {
            background: var(--accent-color);
            border-radius: 8px;
        }
        .summary-table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #d4a017;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            min-width: 900px;
        }
        .summary-table thead {
            background: var(--primary-color);
        }
        .summary-table thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background: var(--primary-color);
        }
        .summary-table th {
            padding: 14px 12px;
            color: white;
            font-weight: 700;
            font-size: 0.82rem;
            text-align: center;
            border-left: 1px solid rgba(255,255,255,0.1);
            white-space: nowrap;
        }
        .summary-table th:first-child { border-left: none; }
        .summary-table th.usd-header { background: #16a34a; }
        .summary-table th.afn-header { background: #dc2626; }
        .summary-table th.unpaid-header { background: #f59e0b; }

        .summary-table td {
            padding: 12px 12px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
            border-left: 1px solid #f1f5f9;
            font-weight: 600;
            white-space: nowrap;
        }
        .summary-table td:first-child { border-left: none; }
        .summary-table td.client-name-cell {
            text-align: right;
            font-weight: 700;
            color: var(--primary-color);
            background: #f8fafc;
            font-size: 0.92rem;
            position: sticky;
            right: 0;
            z-index: 2;
            box-shadow: -2px 0 5px rgba(0,0,0,0.05);
        }
        .summary-table .paid-value { color: #16a34a; font-weight: 700; }
        .summary-table .unpaid-value { color: #d97706; font-weight: 700; }
        .summary-table .unpaid-value.zero { color: #16a34a; }
        .summary-table tbody tr:hover { background: #f8fafc; }
        .summary-table tbody tr:hover td.client-name-cell { background: #eef2f7; }
        .summary-table tbody tr.row-has-unpaid { background: #fffbeb; }
        .summary-table tbody tr.row-has-unpaid:hover { background: #fef3c7; }
        .summary-table tbody tr.row-has-unpaid td.client-name-cell { background: #fef3c7; }
        .summary-table tfoot td {
            position: sticky;
            bottom: 0;
            background: #e2e8f0;
            font-weight: 800;
            color: var(--primary-color);
            border-top: 3px solid var(--primary-color);
            font-size: 0.9rem;
            padding: 14px 12px;
            text-align: center;
            white-space: nowrap;
            z-index: 4;
        }
        .summary-table tfoot td:first-child {
            background: #cbd5e1;
            text-align: right;
            position: sticky;
            right: 0;
            z-index: 6;
            box-shadow: -2px 0 5px rgba(0,0,0,0.08);
        }
        .paid-badge {
            display: inline-flex; align-items: center; gap: 3px;
            background: #dcfce7; color: #166534;
            padding: 2px 8px; border-radius: 10px;
            font-size: 0.7rem; font-weight: 700;
            margin-right: 4px;
        }
        .no-result-row {
            display: none;
        }

        /* ===== SALES TABLE ===== */
        .table-wrapper { overflow-x: auto; border-radius: 12px; border: 1px solid #e2e8f0; }
        table.main-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; min-width: 1000px; }
        table.main-table thead { background: var(--primary-color); }
        table.main-table th { padding: 14px 12px; text-align: right; color: white; font-weight: 600; font-size: 0.82rem; white-space: nowrap; }
        table.main-table th small { font-weight: 400; opacity: 0.85; font-size: 0.7rem; }
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

        <div class="summary-search-bar">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" 
                       id="clientSearchInput" 
                       placeholder="د پیرودونکي نوم ولیکئ د پلټنې لپاره..."
                       oninput="filterClientRows()">
            </div>
            <span class="filter-info" id="filterInfo">
                <i class="fas fa-users"></i>
                <span id="visibleCount"><?php echo count($client_summary); ?></span> پیرودونکي
            </span>
        </div>

        <div class="summary-table-wrapper" id="summaryWrapper">
            <table class="summary-table" id="summaryTable">
                <thead>
                    <tr>
                        <th rowspan="2" style="vertical-align: middle;">پیرودونکي</th>
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
                <tbody id="summaryTableBody">
                    <?php if (count($client_summary) > 0): ?>
                        <?php foreach ($client_summary as $cs): 
                            $has_unpaid = ($cs['usd_unpaid'] > 0.01) || ($cs['afn_unpaid'] > 0.01);
                        ?>
                            <tr class="client-row <?php echo $has_unpaid ? 'row-has-unpaid' : ''; ?>" 
                                data-client-name="<?php echo htmlspecialchars(mb_strtolower($cs['full_name'], 'UTF-8')); ?>">
                                <td class="client-name-cell"><?php echo htmlspecialchars($cs['full_name']); ?></td>

                                <td><?php echo number_format($cs['usd_invoiced'], 2); ?> $</td>
                                <td class="paid-value"><?php echo number_format($cs['usd_paid'], 2); ?> $</td>
                                <td class="unpaid-value <?php echo ($cs['usd_unpaid'] <= 0.01) ? 'zero' : ''; ?>">
                                    <?php echo number_format($cs['usd_unpaid'], 2); ?> $
                                    <?php if ($cs['usd_unpaid'] <= 0.01 && $cs['usd_invoiced'] > 0): ?>
                                        <span class="paid-badge"><i class="fas fa-check"></i> پوره</span>
                                    <?php endif; ?>
                                </td>

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
                <tfoot id="summaryTableFoot">
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
            <form method="POST" enctype="multipart/form-data" id="saleForm">
                <input type="hidden" name="add_sale" value="1">

                <!-- ===== SEARCHABLE CLIENT COMBO ===== -->
                <div class="form-group">
                    <label><i class="fas fa-user"></i> پیرودونکی *</label>
                    <div class="client-combo" id="clientCombo">
                        <input type="text"
                               id="clientSearchInputCombo"
                               placeholder="د پیرودونکي نوم ولیکئ یا له لیست څخه غوره کړئ..."
                               autocomplete="off">
                        <i class="fas fa-chevron-down combo-arrow"></i>
                        <div class="combo-list" id="clientComboList"></div>
                    </div>
                    <input type="hidden" name="client_id" id="saleClientSelect" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> د فاکتور شمېره *</label>
                    <input type="text" name="invoice_number" placeholder="مثال: INV-001" required>
                </div>

                <!-- ===== DATE CONVERTER ===== -->
                <div class="date-converter">
                    <div class="converter-title">
                        <i class="fas fa-calendar-alt"></i>
                        نیټه (دواړه تقویمونه)
                        <span class="converter-badge"><i class="fas fa-sync-alt"></i> اتومات تړاو</span>
                    </div>
                    <div class="date-converter-row">
                        <div class="date-field">
                            <label><i class="fas fa-globe"></i> میادي (US)</label>
                            <input type="date" 
                                   id="gregorianDateInput" 
                                   value="<?php echo $today_date; ?>">
                        </div>
                        <div class="date-field">
                            <label><i class="fas fa-moon"></i> هجري شمسي (افغاني)</label>
                            <div class="afghan-date-input">
                                <select id="afghanYearSelect"></select>
                                <select id="afghanMonthSelect"></select>
                                <select id="afghanDaySelect"></select>
                            </div>
                        </div>
                    </div>
                    <div class="date-sync-hint">
                        <i class="fas fa-info-circle"></i>
                        یو تقویم بدل کړئ، بل به اتومات ډک شي
                    </div>
                </div>

                <!-- Hidden input for the actual invoice_date sent to server -->
                <input type="hidden" name="invoice_date" id="invoiceDateHidden" value="<?php echo $today_date; ?>">

                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> د پیسو ډول *</label>
                    <select name="currency" id="saleCurrencySelect" required>
                        <option value="">-- د پیسو ډول غوره کړئ --</option>
                        <option value="USD">ډالر (USD)</option>
                        <option value="AFN">افغاني (AFN)</option>
                    </select>
                </div>

                <div class="balance-error" id="saleBalanceError"></div>
                <div class="balance-loading" id="saleBalanceLoading">
                    <i class="fas fa-spinner fa-spin"></i> محاسبه روانه ده...
                </div>
                <div class="balance-box" id="saleBalanceBox">
                    <div class="balance-client">
                        <i class="fas fa-user-circle"></i>
                        <span id="saleBalanceClientName"></span>
                    </div>
                    <div class="balance-rows" id="saleBalanceRows"></div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-dollar-sign"></i> ټوله اندازه *</label>
                    <input type="number" name="total_amount" id="saleTotalInput" step="0.01" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-image"></i> د فاکتور عکس</label>
                    <input type="file" name="invoice_picture" accept="image/*" data-compress>
                    <div class="compress-hint">
                        <i class="fas fa-magic"></i>
                        عکس به اتوماتیک ~50KB ته کمپریس شي
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> یادونه</label>
                    <textarea name="note" placeholder="اضافي یادونه"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" id="saleSubmitBtn">
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
                            <th>میادي نېټه<br><small>(US)</small></th>
                            <th>افغاني نېټه<br><small>(هجري شمسي)</small></th>
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
                                    <td>
                                        <span class="date-us-cell">
                                            <i class="fas fa-globe"></i>
                                            <?php echo htmlspecialchars($row['invoice_date']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="date-af-cell">
                                            <i class="fas fa-moon"></i>
                                            <?php echo formatAfghanDateFromGregorian($row['invoice_date']); ?>
                                        </span>
                                    </td>
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
                                                 loading="lazy"
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
                                <td colspan="9">
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
    // ============================================
    // AFGHAN DATE CONVERTER
    // ============================================
    const AFGHAN_MONTHS = [
        'حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله',
        'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت'
    ];

    const gregorianDateInput = document.getElementById('gregorianDateInput');
    const afghanYearSelect = document.getElementById('afghanYearSelect');
    const afghanMonthSelect = document.getElementById('afghanMonthSelect');
    const afghanDaySelect = document.getElementById('afghanDaySelect');
    const invoiceDateHidden = document.getElementById('invoiceDateHidden');

    function populateAfghanYears() {
        const currentYear = parseInt('<?php echo $today_jy; ?>');
        afghanYearSelect.innerHTML = '';
        for (let y = currentYear - 30; y <= currentYear + 30; y++) {
            const opt = document.createElement('option');
            opt.value = y;
            opt.textContent = y;
            afghanYearSelect.appendChild(opt);
        }
    }

    function populateAfghanMonths() {
        afghanMonthSelect.innerHTML = '';
        AFGHAN_MONTHS.forEach((name, idx) => {
            const opt = document.createElement('option');
            opt.value = idx + 1;
            opt.textContent = (idx + 1) + ' - ' + name;
            afghanMonthSelect.appendChild(opt);
        });
    }

    function populateAfghanDays(jy, jm, selectedDay) {
        afghanDaySelect.innerHTML = '';
        let maxDays;
        if (jm <= 6) maxDays = 31;
        else if (jm <= 11) maxDays = 30;
        else {
            const isLeap = ((jy % 33) % 4 === 1);
            maxDays = isLeap ? 30 : 29;
        }
        for (let d = 1; d <= maxDays; d++) {
            const opt = document.createElement('option');
            opt.value = d;
            opt.textContent = d;
            afghanDaySelect.appendChild(opt);
        }
        if (selectedDay && selectedDay <= maxDays) {
            afghanDaySelect.value = selectedDay;
        }
    }

    function syncToAfghan() {
        const val = gregorianDateInput.value;
        if (!val) return;
        const [gy, gm, gd] = val.split('-').map(Number);

        fetch('?ajax_date_convert=1&type=to_afghan&gy=' + gy + '&gm=' + gm + '&gd=' + gd)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                afghanYearSelect.value = data.jy;
                afghanMonthSelect.value = data.jm;
                populateAfghanDays(data.jy, data.jm, data.jd);
                afghanDaySelect.value = data.jd;
                invoiceDateHidden.value = val;
            })
            .catch(err => console.error('Date convert error:', err));
    }

    function syncToGregorian() {
        const jy = parseInt(afghanYearSelect.value);
        const jm = parseInt(afghanMonthSelect.value);
        const jd = parseInt(afghanDaySelect.value);

        if (!jy || !jm || !jd) return;

        fetch('?ajax_date_convert=1&type=to_gregorian&jy=' + jy + '&jm=' + jm + '&jd=' + jd)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                gregorianDateInput.value = data.gregorian;
                invoiceDateHidden.value = data.gregorian;
            })
            .catch(err => console.error('Date convert error:', err));
    }

    gregorianDateInput.addEventListener('change', syncToAfghan);

    afghanYearSelect.addEventListener('change', function() {
        populateAfghanDays(
            parseInt(afghanYearSelect.value),
            parseInt(afghanMonthSelect.value),
            parseInt(afghanDaySelect.value)
        );
        syncToGregorian();
    });
    afghanMonthSelect.addEventListener('change', function() {
        populateAfghanDays(
            parseInt(afghanYearSelect.value),
            parseInt(afghanMonthSelect.value),
            parseInt(afghanDaySelect.value)
        );
        syncToGregorian();
    });
    afghanDaySelect.addEventListener('change', syncToGregorian);

    (function initDateConverter() {
        populateAfghanYears();
        populateAfghanMonths();

        const initJy = parseInt('<?php echo $today_jy; ?>');
        const initJm = parseInt('<?php echo $today_jm; ?>');
        const initJd = parseInt('<?php echo $today_jd; ?>');

        afghanYearSelect.value = initJy;
        afghanMonthSelect.value = initJm;
        populateAfghanDays(initJy, initJm, initJd);
        afghanDaySelect.value = initJd;

        invoiceDateHidden.value = gregorianDateInput.value;
    })();

    // ============================================
    // SEARCHABLE CLIENT COMBO (input + dropdown)
    // ============================================
    const ALL_CLIENTS = <?php
        echo json_encode(array_map(function($c){
            return ['id' => (int)$c['id'], 'name' => $c['full_name']];
        }, $clients_list), JSON_UNESCAPED_UNICODE);
    ?>;

    const clientCombo        = document.getElementById('clientCombo');
    const clientComboInput   = document.getElementById('clientSearchInputCombo');
    const clientComboList    = document.getElementById('clientComboList');
    const saleClientSelect   = document.getElementById('saleClientSelect');

    function renderComboList(filterText) {
        const q = (filterText || '').toLowerCase().trim();
        let matches;
        if (q === '') {
            matches = ALL_CLIENTS.slice(0, 100);
        } else {
            matches = ALL_CLIENTS.filter(c => c.name.toLowerCase().includes(q));
        }

        clientComboList.innerHTML = '';

        if (matches.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'combo-empty';
            empty.textContent = 'هیڅ پیرودونکی ونه موندل شو';
            clientComboList.appendChild(empty);
            return;
        }

        matches.forEach(c => {
            const item = document.createElement('div');
            item.className = 'combo-item';
            item.dataset.id = c.id;
            item.innerHTML = `${escapeHtml(c.name)} <span class="client-id">#${c.id}</span>`;
            item.addEventListener('mousedown', function(e) {
                e.preventDefault();
                selectClient(c.id, c.name);
            });
            clientComboList.appendChild(item);
        });
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function(m) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
        });
    }

    function selectClient(id, name) {
        clientComboInput.value = name;
        saleClientSelect.value = id;
        clientComboList.classList.remove('active');
        // Trigger the balance fetch
        fetchSaleBalance();
    }

    function openCombo() {
        renderComboList(clientComboInput.value);
        clientComboList.classList.add('active');
    }

    clientComboInput.addEventListener('focus', openCombo);
    clientComboInput.addEventListener('input', function() {
        // When typing, reset the selected client_id until a real selection happens
        saleClientSelect.value = '';
        renderComboList(this.value);
        clientComboList.classList.add('active');
        // Hide the balance box while no valid client is selected
        saleBalanceBox.classList.remove('active');
        saleCurrentBalance = null;
    });

    clientComboInput.addEventListener('blur', function() {
        // Delay closing so click on item registers
        setTimeout(() => clientComboList.classList.remove('active'), 150);
    });

    document.addEventListener('click', function(e) {
        if (!clientCombo.contains(e.target)) {
            clientComboList.classList.remove('active');
        }
    });

    // ============================================
    // IMAGE COMPRESSION - Compress to ~50KB before upload
    // ============================================
    async function compressImage(file, maxSizeKB = 50, maxWidth = 800, maxHeight = 800) {
        return new Promise((resolve, reject) => {
            if (file.size <= maxSizeKB * 1024) {
                resolve(file);
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    let width = img.width;
                    let height = img.height;

                    if (width > maxWidth || height > maxHeight) {
                        const ratio = Math.min(maxWidth / width, maxHeight / height);
                        width = Math.round(width * ratio);
                        height = Math.round(height * ratio);
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');

                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(0, 0, width, height);
                    ctx.drawImage(img, 0, 0, width, height);

                    let minQ = 0.1, maxQ = 0.95, bestBlob = null;

                    const tryQuality = (q) => {
                        return new Promise(res => {
                            canvas.toBlob(blob => res(blob), 'image/jpeg', q);
                        });
                    };

                    (async () => {
                        for (let i = 0; i < 8; i++) {
                            const midQ = (minQ + maxQ) / 2;
                            const blob = await tryQuality(midQ);
                            if (blob.size / 1024 <= maxSizeKB) {
                                bestBlob = blob;
                                minQ = midQ;
                            } else {
                                maxQ = midQ;
                            }
                        }
                        if (!bestBlob) bestBlob = await tryQuality(0.1);

                        const compressedFile = new File(
                            [bestBlob],
                            file.name.replace(/\.[^.]+$/, '.jpg'),
                            { type: 'image/jpeg', lastModified: Date.now() }
                        );
                        resolve(compressedFile);
                    })();
                };
                img.onerror = reject;
                img.src = e.target.result;
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    // ===== Compress invoice picture in sale form on submit =====
    (function() {
        const form = document.getElementById('saleForm');
        const submitBtn = document.getElementById('saleSubmitBtn');
        if (!form || !submitBtn) return;

        const fileInput = form.querySelector('input[type="file"][name="invoice_picture"]');
        let isCompressing = false;

        form.addEventListener('submit', async function(e) {
            if (isCompressing) {
                e.preventDefault();
                return;
            }

            if (fileInput && fileInput.files.length > 0) {
                e.preventDefault();
                isCompressing = true;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> عکس کمپریس کیږي...';

                try {
                    const original = fileInput.files[0];
                    const compressed = await compressImage(original, 50);
                    const dt = new DataTransfer();
                    dt.items.add(compressed);
                    fileInput.files = dt.files;

                    console.log(`✅ invoice_picture: ${(original.size/1024).toFixed(1)}KB → ${(compressed.size/1024).toFixed(1)}KB`);

                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> خوندي کیږي...';
                    form.submit();
                } catch (err) {
                    console.error('Compression failed:', err);
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> خوندي کیږي...';
                    form.submit();
                }
            } else {
                setTimeout(() => {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> خوندي کیږي...';
                }, 0);
            }
        });
    })();

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

    // ===== LIVE BALANCE FOR SALES PAGE =====
    const saleCurrencySelect = document.getElementById('saleCurrencySelect');
    const saleTotalInput = document.getElementById('saleTotalInput');
    const saleBalanceBox = document.getElementById('saleBalanceBox');
    const saleBalanceLoading = document.getElementById('saleBalanceLoading');
    const saleBalanceError = document.getElementById('saleBalanceError');
    const saleBalanceClientName = document.getElementById('saleBalanceClientName');
    const saleBalanceRows = document.getElementById('saleBalanceRows');

    let saleCurrentBalance = null;
    const SALES_AJAX_URL = window.location.pathname;

    function saleShowError(msg) {
        saleBalanceError.textContent = '⚠ ' + msg;
        saleBalanceError.classList.add('active');
    }
    function saleHideError() {
        saleBalanceError.classList.remove('active');
    }

    function fetchSaleBalance() {
        const clientId = saleClientSelect.value;
        const currency = saleCurrencySelect.value;

        if (!clientId || !currency) {
            saleBalanceBox.classList.remove('active');
            saleCurrentBalance = null;
            return;
        }

        const clientName = clientComboInput.value || '';
        saleBalanceClientName.textContent = clientName;

        saleBalanceLoading.classList.add('active');
        saleBalanceBox.classList.remove('active');
        saleHideError();

        const url = SALES_AJAX_URL + '?ajax_client_balance=1&client_id=' +
                    encodeURIComponent(clientId) +
                    '&currency=' + encodeURIComponent(currency);

        fetch(url)
            .then(response => response.text())
            .then(text => {
                saleBalanceLoading.classList.remove('active');

                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    saleShowError('Server did not return JSON.');
                    console.error('Raw response:', text);
                    return;
                }

                if (!data.success) {
                    saleShowError(data.message || 'Unknown error');
                    return;
                }

                saleCurrentBalance = data;
                renderSaleBalance(data, 0);
                saleBalanceBox.classList.add('active');
            })
            .catch(err => {
                saleBalanceLoading.classList.remove('active');
                saleShowError('Network error: ' + err.message);
            });
    }

    function renderSaleBalance(data, newAmount) {
        const symbol = data.currency === 'USD' ? '$' : '؋';
        const invoiced = data.invoice_total;
        const paid = data.paid_total;
        const unpaid = data.unpaid_total;
        const newTotal = unpaid + newAmount;

        let html = `
            <div class="balance-row invoice">
                <span class="label">ټول فاکتورونه</span>
                <span class="value">${invoiced.toLocaleString('en-US', {minimumFractionDigits: 2})} ${symbol}</span>
            </div>
            <div class="balance-row paid">
                <span class="label">تادیه شوي</span>
                <span class="value">${paid.toLocaleString('en-US', {minimumFractionDigits: 2})} ${symbol}</span>
            </div>
            <div class="balance-row unpaid">
                <span class="label">پاتې (بې تادیه)</span>
                <span class="value">${unpaid.toLocaleString('en-US', {minimumFractionDigits: 2})} ${symbol}</span>
            </div>
        `;

        if (newAmount > 0) {
            html += `
                <div class="balance-row new-total">
                    <span class="label">+ د نوي فاکتور سره</span>
                    <span class="value">${newTotal.toLocaleString('en-US', {minimumFractionDigits: 2})} ${symbol}</span>
                </div>
            `;
        }

        saleBalanceRows.innerHTML = html;
    }

    saleCurrencySelect.addEventListener('change', fetchSaleBalance);

    saleTotalInput.addEventListener('input', function() {
        if (saleCurrentBalance) {
            const val = parseFloat(this.value) || 0;
            renderSaleBalance(saleCurrentBalance, val);
        }
    });

    // ===== SEARCH IN CLIENT SUMMARY TABLE =====
    function filterClientRows() {
        const searchValue = document.getElementById('clientSearchInput').value.toLowerCase().trim();
        const rows = document.querySelectorAll('#summaryTableBody tr.client-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const name = row.getAttribute('data-client-name') || '';
            if (searchValue === '' || name.includes(searchValue)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        document.getElementById('visibleCount').textContent = visibleCount;
    }
</script>

</body>
</html>