<?php

include "db.php";

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
// DELETE EXPENSE
// =========================

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_sql = "DELETE FROM expenses WHERE id = $delete_id";
    if (mysqli_query($conn, $delete_sql)) {
        $success_msg = "لګښت په بریالیتوب سره ړنګ شو!";
    } else {
        $error_msg = "تېروتنه: " . mysqli_error($conn);
    }
}

// =========================
// ADD EXPENSE
// =========================

if (isset($_POST['add_expense'])) {

    $amount = $_POST['amount'];
    $used_for = $_POST['used_for'];
    $person = $_POST['person'];
    $expense_date = $_POST['expense_date'];

    $sql = "INSERT INTO expenses (amount, used_for, person, expense_date)
            VALUES ('$amount', '$used_for', '$person', '$expense_date')";

    if (mysqli_query($conn, $sql)) {
        $success_msg = "لګښت په بریالیتوب سره اضافه شو!";
    } else {
        $error_msg = "تېروتنه: " . mysqli_error($conn);
    }
}

// =========================
// FILTER
// =========================

$filter = $_GET['filter'] ?? 'all';

$where = "";

if ($filter == "today") {
    $where = "WHERE expense_date = CURDATE()";
} elseif ($filter == "yesterday") {
    $where = "WHERE expense_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($filter == "week") {
    $where = "WHERE YEARWEEK(expense_date, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter == "month") {
    $where = "WHERE YEAR(expense_date) = YEAR(CURDATE())
              AND MONTH(expense_date) = MONTH(CURDATE())";
} elseif ($filter == "custom") {
    $from_date = $_GET['from_date'] ?? '';
    $to_date = $_GET['to_date'] ?? '';
    if (!empty($from_date) && !empty($to_date)) {
        $where = "WHERE expense_date BETWEEN '$from_date' AND '$to_date'";
    }
}

// =========================
// GET EXPENSES
// =========================

$sql = "SELECT * FROM expenses
        $where
        ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

// =========================
// CALCULATE TOTAL
// =========================

$total_sql = "SELECT SUM(amount) AS total
              FROM expenses
              $where";

$total_result = mysqli_query($conn, $total_sql);
$total_data = mysqli_fetch_assoc($total_result);
$total = $total_data['total'] ?? 0;

// =========================
// STATS
// =========================

$count_sql = "SELECT COUNT(*) AS cnt FROM expenses $where";
$count_result = mysqli_query($conn, $count_sql);
$count_data = mysqli_fetch_assoc($count_result);
$expense_count = $count_data['cnt'] ?? 0;

$today_sql = "SELECT SUM(amount) AS today_total FROM expenses WHERE expense_date = CURDATE()";
$today_result = mysqli_query($conn, $today_sql);
$today_data = mysqli_fetch_assoc($today_result);
$today_total = $today_data['today_total'] ?? 0;

// Today's date in Y-m-d format for the date input default value
$today_date = date('Y-m-d');
list($today_jy, $today_jm, $today_jd) = gregorianToAfghan((int)date('Y'), (int)date('n'), (int)date('j'));
$today_afghan = formatAfghanDate($today_jy, $today_jm, $today_jd);

?>

<!DOCTYPE html>
<html lang="ps" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لګښتونه · قرطاسیه</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #1e3c72;
            --secondary-color: #2b4a7a;
            --accent-color: #e6b12e;
            --success-color: #22c55e;
            --warning-color: #f59e0b;
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Noto Naskh Arabic', 'Segoe UI', Tahoma, sans-serif;
        }

        body {
            background: var(--bg-light);
            color: var(--text-color);
            min-height: 100vh;
            font-size: 14px;
            line-height: 1.6;
        }

        /* ===== Main Content ===== */
        .main-content {
            padding: 5px;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 5px;
                padding-top: 5px;
            }
        }

        /* ===== Page Header ===== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
            padding: 18px 24px;
            background: var(--primary-color);
            border-radius: var(--border-radius);
            color: white;
            box-shadow: var(--shadow);
        }

        .page-header h1 {
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            margin: 0;
        }

        .page-header h1 i {
            color: var(--accent-color);
        }

        .page-header .subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
            margin-top: 5px;
        }
          #gregorianDateInput{
            margin-left: -30px;
        }

        /* ===== Stats Cards ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 24px 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }

        .stat-icon.total {
            background: var(--primary-color);
        }

        .stat-icon.count {
            background: var(--accent-color);
        }

        .stat-icon.today {
            background: var(--success-color);
        }

        .stat-info h3 {
            margin: 0;
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .stat-info p {
            margin: 6px 0 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-card:nth-child(1) { border-top: 4px solid var(--primary-color); }
        .stat-card:nth-child(2) { border-top: 4px solid var(--accent-color); }
        .stat-card:nth-child(3) { border-top: 4px solid var(--success-color); }

        /* ===== Content Grid ===== */
        .content-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 25px;
            align-items: start;
        }

        @media (max-width: 1100px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ===== Card ===== */
        .card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        .card-header i {
            color: var(--accent-color);
            font-size: 1.3rem;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--primary-color);
            font-weight: 700;
        }

        /* ===== Form ===== */
        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .form-group label i {
            color: var(--accent-color);
            margin-left: 6px;
            width: 16px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: var(--transition);
            font-family: inherit;
            background: var(--card-bg);
            color: var(--text-color);
            text-align: right;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(30, 60, 114, 0.1);
        }

        /* Highlight today's date field */
        .form-group input[type="date"] {
            direction: ltr;
            text-align: right;
        }

        /* ===== DATE CONVERTER ===== */
        .date-converter {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 2px solid #7dd3fc;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
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
        .date-field .afghan-date-input {
            display: flex;
            gap: 4px;
        }
        .date-field .afghan-date-input select {
            flex: 1;
            padding: 10px 6px;
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
            padding-right: 6px !important;
            cursor: pointer;
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

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            width: 100%;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(30, 60, 114, 0.2);
        }

        .btn-secondary {
            background: var(--accent-color);
            color: var(--primary-color);
            width: 100%;
            box-shadow: var(--shadow);
        }

        .btn-secondary:hover {
            background: #d4a017;
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(230, 177, 46, 0.3);
        }

        /* ===== Alerts ===== */
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.3s ease;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border-right: 4px solid var(--success-color);
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border-right: 4px solid var(--danger-color);
        }

        /* ===== Filter Tabs ===== */
        .filter-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }

        .filter-tabs a {
            padding: 9px 18px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            background: #f1f5f9;
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .filter-tabs a:hover {
            background: #e2e8f0;
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        .filter-tabs a.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(30, 60, 114, 0.25);
        }

        /* ===== Custom Date Filter ===== */
        .custom-filter {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 20px;
        }

        .custom-filter .custom-row {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .custom-filter .form-group {
            flex: 1;
            min-width: 140px;
            margin-bottom: 0;
        }

        .custom-filter .btn {
            width: auto;
            padding: 12px 24px;
        }

        /* ===== Table ===== */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
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

        th {
            padding: 15px;
            text-align: right;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        th small {
            font-weight: 400;
            opacity: 0.85;
            font-size: 0.7rem;
        }

        td {
            padding: 14px 15px;
            border-bottom: 1px solid #e2e8f0;
            color: var(--text-color);
            text-align: right;
        }

        tbody tr {
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .amount-cell {
            font-weight: 700;
            color: var(--primary-color);
        }

        .id-badge {
            display: inline-block;
            background: #f1f5f9;
            color: var(--text-muted);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 2.5rem;
            color: #cbd5e1;
            margin-bottom: 12px;
            display: block;
        }

        /* ===== Total Footer ===== */
        .total-footer {
            margin-top: 20px;
            padding: 18px 24px;
            background: var(--primary-color);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: white;
            box-shadow: 0 6px 20px rgba(30, 60, 114, 0.2);
        }

        .total-footer .total-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            font-weight: 600;
        }

        .total-footer .total-label i {
            color: var(--accent-color);
            font-size: 1.3rem;
        }

        .total-footer .total-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--accent-color);
        }

        /* ===== Delete Button ===== */
        .btn-delete {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: var(--danger-color);
            color: white;
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.85rem;
        }

        .btn-delete:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(239, 68, 68, 0.4);
        }

        /* ===== Animation ===== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== Responsive ===== */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.4rem;
            }
            .stat-info p {
                font-size: 1.2rem;
            }
            .card {
                padding: 18px;
            }
            .custom-filter .custom-row {
                flex-direction: column;
                align-items: stretch;
            }
            .custom-filter .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.3rem;
            }
            .stat-card {
                padding: 18px 16px;
            }
            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 1.3rem;
            }
            .stat-info p {
                font-size: 1.1rem;
            }
        }
    </style>
</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <!-- ===== Page Header ===== -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-wallet"></i> لګښتونه</h1>
            <div class="subtitle">د قرطاسیه ټول لګښتونه تعقیب او مدیریت کړئ</div>
        </div>
    </div>

    <!-- ===== Alerts ===== -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <!-- ===== Stats Cards ===== -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total">
                <i class="fas fa-coins"></i>
            </div>
            <div class="stat-info">
                <h3>ټول لګښتونه</h3>
                <p><?php echo number_format($total, 2); ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon count">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="stat-info">
                <h3>ریکارډونه</h3>
                <p><?php echo $expense_count; ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon today">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-info">
                <h3>نن</h3>
                <p><?php echo number_format($today_total, 2); ?></p>
            </div>
        </div>
    </div>

    <!-- ===== Content Grid ===== -->
    <div class="content-grid">

        <!-- ===== Add Expense Form ===== -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus-circle"></i>
                <h2>نوی لګښت اضافه کړئ</h2>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-dollar-sign"></i> اندازه</label>
                    <input type="number" name="amount" step="0.01" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-tag"></i> د څه لپاره</label>
                    <input type="text" name="used_for" placeholder="مثال: خواړه" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-user"></i> کس</label>
                    <input type="text" name="person" placeholder="مثال: احمد" required>
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

                <!-- Hidden input for the actual expense_date sent to server -->
                <input type="hidden" name="expense_date" id="expenseDateHidden" value="<?php echo $today_date; ?>">

                <button type="submit" name="add_expense" class="btn btn-primary">
                    <i class="fas fa-save"></i> لګښت اضافه کړئ
                </button>
            </form>
        </div>

        <!-- ===== Expenses List ===== -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i>
                <h2>د لګښتونو ریکارډونه</h2>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="expenses.php?filter=all" class="<?php echo $filter == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> ټول
                </a>
                <a href="expenses.php?filter=today" class="<?php echo $filter == 'today' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-day"></i> نن
                </a>
                <a href="expenses.php?filter=yesterday" class="<?php echo $filter == 'yesterday' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-minus"></i> پرون
                </a>
                <a href="expenses.php?filter=week" class="<?php echo $filter == 'week' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-week"></i> دې اونۍ
                </a>
                <a href="expenses.php?filter=month" class="<?php echo $filter == 'month' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> دې میاشت
                </a>
            </div>

            <!-- Custom Date Filter -->
            <div class="custom-filter">
                <form method="GET">
                    <input type="hidden" name="filter" value="custom">
                    <div class="custom-row">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-plus"></i> له نېټې</label>
                            <input type="date" name="from_date" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar-check"></i> تر نېټې</label>
                            <input type="date" name="to_date" required>
                        </div>
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-filter"></i> فلټر
                        </button>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>اندازه</th>
                            <th>د څه لپاره</th>
                            <th>کس</th>
                            <th>میادي نېټه<br><small>(US)</small></th>
                            <th>افغاني نېټه<br><small>(هجري شمسي)</small></th>
                            <th>کړنې</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><span class="id-badge">#<?php echo $row['id']; ?></span></td>
                                    <td class="amount-cell"><?php echo number_format($row['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['used_for']); ?></td>
                                    <td><?php echo htmlspecialchars($row['person']); ?></td>
                                    <td>
                                        <span class="date-us-cell">
                                            <i class="fas fa-globe"></i>
                                            <?php echo htmlspecialchars($row['expense_date']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="date-af-cell">
                                            <i class="fas fa-moon"></i>
                                            <?php echo formatAfghanDateFromGregorian($row['expense_date']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- ✅ Delete button -->
                                        <a href="?delete_id=<?php echo $row['id']; ?>" 
                                           class="btn-delete" 
                                           title="ړنګول"
                                           onclick="return confirm('ایا تاسو ډاډه یاست چې دا لګښت ړنګ کړئ؟');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        د دې فلټر لپاره هیڅ لګښت ونه موندل شو.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Total Footer -->
            <div class="total-footer">
                <div class="total-label">
                    <i class="fas fa-calculator"></i>
                    ټول لګښتونه
                </div>
                <div class="total-value">
                    <?php echo number_format($total, 2); ?>
                </div>
            </div>
        </div>

    </div>

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
    const expenseDateHidden = document.getElementById('expenseDateHidden');

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
                expenseDateHidden.value = val;
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
                expenseDateHidden.value = data.gregorian;
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

        expenseDateHidden.value = gregorianDateInput.value;
    })();
</script>

</body>
</html>