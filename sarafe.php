<?php
// ======================================
// 1. SESSION + DATABASE CONNECTION (via db.php)
// ======================================

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['username'] ?? 'User';

date_default_timezone_set("Asia/Kabul");

$message = "";
$error = "";


// ======================================
// 1b. AFGHAN (SOLAR HIJRI) DATE CONVERTER
// ======================================

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

function faDate($gregorian, $withMonthName = false) {
    if (empty($gregorian) || $gregorian === '0000-00-00') return '—';

    $ts = strtotime($gregorian);
    if ($ts === false) return htmlspecialchars((string)$gregorian);

    $gy = (int)date('Y', $ts);
    $gm = (int)date('n', $ts);
    $gd = (int)date('j', $ts);

    [$jy, $jm, $jd] = gregorianToAfghan($gy, $gm, $gd);

    if ($withMonthName) {
        $months = [
            1  => 'حمل', 2  => 'ثور', 3  => 'جوزا', 4  => 'سرطان',
            5  => 'اسد', 6  => 'سنبله', 7  => 'میزان', 8  => 'عقرب',
            9  => 'قوس', 10 => 'جدي', 11 => 'دلو', 12 => 'حوت',
        ];
        return sprintf('%d %s %d', $jd, $months[$jm], $jy);
    }

    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}

function faToday($withMonthName = false) {
    return faDate(date('Y-m-d'), $withMonthName);
}


// ======================================
// 1c. AJAX: DATE CONVERTER
// ======================================
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
            'afghan'  => formatAfghanDate($jy, $jm, $jd),
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


// ======================================
// 2. COMMON VALIDATION FUNCTION
// ======================================

function validTransaction($type, $currency, $date, $amount, $rate) {

    $validType =
        ($type === "received_from_saraf" &&
            in_array($currency, ["CNY", "USD"], true)) ||

        ($type === "paid_to_saraf" &&
            in_array($currency, ["AFN", "USD"], true));

    $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) &&
        checkdate(
            (int)substr($date, 5, 2),
            (int)substr($date, 8, 2),
            (int)substr($date, 0, 4)
        );

    if ($currency === "USD") {
        $rate = 1;
    }

    return $validType && $validDate && $amount > 0 && $rate > 0;
}


// ======================================
// 3. DELETE TRANSACTION
// ======================================

if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "delete") {

    $id = (int)($_POST["id"] ?? 0);

    if ($id > 0) {

        $stmt = $conn->prepare(
            "DELETE FROM saraf_transactions WHERE id = ?"
        );

        $stmt->bind_param("i", $id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            header("Location: sarafe.php?deleted=1");
            exit;
        } else {
            $error = "معامله ونه موندل شوه یا حذف نه شوه.";
        }

        $stmt->close();

    } else {
        $error = "د معاملې ID سم نه دی.";
    }
}


// ======================================
// 4. SAVE OR UPDATE TRANSACTION
// ======================================

if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    in_array($_POST["action"] ?? "", ["save", "update"], true)) {

    $action = $_POST["action"];

    $id = (int)($_POST["id"] ?? 0);

    $type = $_POST["transaction_type"] ?? "";
    $date = $_POST["transaction_date"] ?? "";
    $currency = $_POST["currency"] ?? "";

    $amount = (float)($_POST["amount"] ?? 0);
    $rate = (float)($_POST["exchange_rate"] ?? 0);

    $note = trim($_POST["note"] ?? "");

    if ($currency === "USD") {
        $rate = 1;
    }

    if (!validTransaction($type, $currency, $date, $amount, $rate)) {

        $error = "مهرباني وکړئ سم معلومات ولیکئ.";

    } elseif ($action === "update" && $id <= 0) {

        $error = "د معاملې ID سم نه دی.";

    } else {

        $usdAmount = round($amount / $rate, 2);

        if ($action === "save") {

            $sql = "INSERT INTO saraf_transactions
                    (transaction_type, transaction_date, currency,
                     amount, exchange_rate, usd_amount, note)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "sssddds",
                $type, $date, $currency,
                $amount, $rate, $usdAmount, $note
            );

        } else {

            $sql = "UPDATE saraf_transactions SET
                    transaction_type = ?,
                    transaction_date = ?,
                    currency = ?,
                    amount = ?,
                    exchange_rate = ?,
                    usd_amount = ?,
                    note = ?
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "sssdddsi",
                $type, $date, $currency,
                $amount, $rate, $usdAmount, $note, $id
            );
        }

        if ($stmt->execute()) {

            if ($action === "save") {
                header("Location: sarafe.php?success=1");
            } else {
                header("Location: sarafe.php?updated=1");
            }

            exit;

        } else {
            $error = "معامله ثبت نه شوه.";
        }

        $stmt->close();
    }
}


// ======================================
// 5. SUCCESS MESSAGES (Pashto)
// ======================================

if (isset($_GET["success"])) $message = "معامله په بریالیتوب سره ثبت شوه!";
if (isset($_GET["updated"])) $message = "معامله په بریالیتوب سره تازه شوه!";
if (isset($_GET["deleted"])) $message = "معامله په بریالیتوب سره حذف شوه!";


// ======================================
// 6. GET TRANSACTION FOR EDITING
// ======================================

$editData = null;

if (isset($_GET["edit"])) {

    $editId = (int)$_GET["edit"];

    if ($editId > 0) {

        $stmt = $conn->prepare(
            "SELECT * FROM saraf_transactions WHERE id = ?"
        );

        $stmt->bind_param("i", $editId);
        $stmt->execute();

        $result = $stmt->get_result();
        $editData = $result->fetch_assoc();

        $stmt->close();

        if (!$editData) {
            $error = "معامله ونه موندل شوه.";
        }
    }
}


// ======================================
// 7. CALCULATE BALANCE
// ======================================

$sql = "SELECT
    COALESCE(SUM(CASE WHEN transaction_type = 'received_from_saraf' THEN usd_amount ELSE 0 END), 0) AS total_received,
    COALESCE(SUM(CASE WHEN transaction_type = 'paid_to_saraf'     THEN usd_amount ELSE 0 END), 0) AS total_paid
    FROM saraf_transactions";

$result = $conn->query($sql);
$summary = $result->fetch_assoc();

$totalReceived = (float)$summary["total_received"];
$totalPaid = (float)$summary["total_paid"];
$balance = $totalReceived - $totalPaid;


// ======================================
// 8. TRANSACTION HISTORY
// ======================================

$history = $conn->query(
    "SELECT * FROM saraf_transactions
     ORDER BY transaction_date DESC, id DESC"
);

// Today's dates for JS init
$today_date = date('Y-m-d');
[$today_jy, $today_jm, $today_jd] = gregorianToAfghan((int)date('Y'), (int)date('n'), (int)date('j'));


function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

?>

<!DOCTYPE html>
<html lang="ps" dir="rtl">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>صرافي حساب · د صرافۍ مدیریت</title>

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
            --warning-color: #f59e0b;
            --purple-color: #8b5cf6;
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
        .main-content { padding: 5px; max-width: 1800px; margin: 0 auto; }
        @media (max-width: 768px) { .main-content { padding: 5px; padding-top: 5px; } }

        /* ===== WELCOME HEADER ===== */
        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            padding: 22px 28px;
            background: linear-gradient(135deg, #1e3c72 0%, #2b4a7a 100%);
            border-radius: var(--border-radius);
            color: white;
            box-shadow: var(--shadow);
        }
        .welcome-header .welcome-text h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0 0 5px;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .welcome-header .welcome-text h1 i { color: var(--accent-color); }
        .welcome-header .welcome-text p {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            margin: 0;
        }
        .welcome-header .welcome-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .welcome-header .welcome-info .user-chip,
        .welcome-header .welcome-info .date-chip {
            background: rgba(255,255,255,0.15);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .welcome-header .welcome-info .user-chip i { color: var(--accent-color); }
        .welcome-header .welcome-info .date-chip i { color: var(--accent-color); }

        /* ===== ALERTS ===== */
        .alert {
            padding: 14px 20px;
            margin-bottom: 20px;
            border-radius: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert.success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 22px 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--card-accent, var(--accent-color));
        }
        .stat-card .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .stat-card .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            color: white;
            background: var(--card-accent, var(--accent-color));
            flex-shrink: 0;
        }
        .stat-card .stat-badge {
            background: #f1f5f9;
            color: var(--text-muted);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .stat-card .stat-label {
            color: var(--text-muted);
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .stat-card .stat-value {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--primary-color);
            line-height: 1.2;
        }
        .stat-card .stat-sub {
            color: var(--text-muted);
            font-size: 0.78rem;
            margin-top: 6px;
        }
        .stat-card.received { --card-accent: #dc2626; }
        .stat-card.received .stat-value { color: #dc2626; }
        .stat-card.paid { --card-accent: #16a34a; }
        .stat-card.paid .stat-value { color: #16a34a; }
        .stat-card.balance { --card-accent: #f59e0b; }

        /* ===== SECTION GRID / CARDS ===== */
        .section-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        @media (max-width: 1000px) {
            .section-grid { grid-template-columns: 1fr; }
        }
        .section-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 22px 24px;
            box-shadow: var(--shadow);
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
            margin-bottom: 25px;
        }
        .section-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--accent-color);
        }
        .section-card.green::before { background: #dc2626; }
        .section-card.green .section-header .title i { color: #dc2626; }
        .section-card.red::before { background: #16a34a; }
        .section-card.red .section-header .title i { color: #16a34a; }
        .section-card.blue::before { background: #3b82f6; }

        .section-card .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 14px;
            margin-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
            flex-wrap: wrap;
            gap: 8px;
        }
        .section-card .section-header .title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .section-card .section-header .title i {
            color: var(--accent-color);
            font-size: 1.15rem;
        }
        .section-card.blue .section-header .title i { color: #3b82f6; }

        .section-card .section-header .view-link {
            color: var(--primary-color);
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 20px;
            background: #f1f5f9;
            transition: var(--transition);
        }
        .section-card .section-header .view-link:hover {
            background: var(--primary-color);
            color: white;
        }

        /* ===== FORM ELEMENTS ===== */
        .form-group { margin-bottom: 14px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 700;
            color: var(--text-color);
            font-size: 0.88rem;
        }
        .form-group label i {
            color: var(--accent-color);
            margin-left: 5px;
        }
        input, select, textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: inherit;
            transition: var(--transition);
            background: #fff;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(30,60,114,0.12);
        }
        textarea { min-height: 65px; resize: vertical; }

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
            flex-wrap: wrap;
        }
        .date-converter .converter-title i { color: #0ea5e9; }
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

        /* 3-dropdown grid — same for US and AF */
        .date-field .triple-date-input {
            display: grid;
            grid-template-columns: 1fr 1.4fr 1fr;
            gap: 6px;
            width: 100%;
        }
        .date-field .triple-date-input select {
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
        .date-field .triple-date-input select:focus {
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

        .preview {
            background: #f1f5f9;
            padding: 13px 16px;
            margin: 15px 0;
            border-radius: 10px;
            font-weight: 700;
            color: var(--primary-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px dashed #cbd5e1;
        }
        .preview span:last-child {
            font-size: 1.1rem;
            color: #16a34a;
        }

        /* ===== BUTTONS ===== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            border: none;
            border-radius: 10px;
            color: white;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 700;
            font-family: inherit;
            transition: var(--transition);
            text-decoration: none;
            width: 100%;
            margin-top: 6px;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); }
        .btn-green { background: #dc2626; }
        .btn-green:hover { background: #b91c1c; }
        .btn-red { background: #16a34a; }
        .btn-red:hover { background: #15803d; }
        .btn-blue { background: var(--primary-color); }
        .btn-blue:hover { background: var(--secondary-color); }

        .btn-sm {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 12px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            color: white;
            border: none;
            cursor: pointer;
            font-family: inherit;
            transition: var(--transition);
        }
        .btn-sm:hover { transform: translateY(-2px); }
        .btn-edit { background: var(--primary-color); }
        .btn-delete { background: #dc2626; }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 12px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .btn-cancel:hover { color: var(--primary-color); }

        /* ===== TABLE ===== */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            -webkit-overflow-scrolling: touch;
        }
        table {
            width: 100%;
            min-width: 1150px;
            border-collapse: collapse;
            font-size: 0.88rem;
            table-layout: auto;
        }
        thead { background: var(--primary-color); }
        th {
            padding: 12px 10px;
            text-align: right;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
            white-space: nowrap;
        }
        td {
            padding: 11px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: right;
            vertical-align: top;
            word-break: break-word;
            overflow-wrap: anywhere;
            white-space: normal;
        }
        tbody tr:hover { background: #f8fafc; }
        tbody tr:last-child td { border-bottom: none; }

        .amount-usd { color: #16a34a; font-weight: 700; white-space: nowrap; }
        .amount-afn { color: #dc2626; font-weight: 700; white-space: nowrap; }
        .amount-unpaid { color: #d97706; font-weight: 800; white-space: nowrap; }
        .type-received { color: #dc2626; font-weight: 700; }
        .type-paid { color: #16a34a; font-weight: 700; }
        .date-us { color: #16a34a; font-weight: 600; white-space: nowrap; }
        .date-af { color: #d97706; font-weight: 600; white-space: nowrap; }

        td.note-cell {
            min-width: 140px;
            max-width: 260px;
            word-break: break-word;
            overflow-wrap: anywhere;
            white-space: normal;
            line-height: 1.5;
            color: #475569;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }
        .actions form { margin: 0; }

        .mini-empty {
            text-align: center;
            padding: 35px 15px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .mini-empty i {
            display: block;
            font-size: 2rem;
            color: #cbd5e1;
            margin-bottom: 10px;
        }

        /* ===== RESPONSIVE TABLE (KEPT AS TABLE, HORIZONTAL SCROLL) ===== */
        @media (max-width: 800px) {
            .table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
            }
            table {
                min-width: 900px;
                font-size: 0.78rem;
            }
            th, td {
                padding: 8px 6px;
                font-size: 0.75rem;
            }
            th {
                font-size: 0.7rem;
                white-space: nowrap;
            }
            td.note-cell {
                min-width: 100px;
                max-width: 160px;
                font-size: 0.75rem;
            }
            .actions {
                flex-direction: column;
                gap: 4px;
            }
            .actions a,
            .actions button {
                width: 100%;
                justify-content: center;
                padding: 6px 8px;
                font-size: 0.7rem;
            }
            .btn-sm {
                padding: 5px 8px;
                font-size: 0.7rem;
            }
            .amount-usd, .amount-afn, .amount-unpaid,
            .date-us, .date-af, .type-received, .type-paid {
                white-space: nowrap;
            }
        }

        @media (max-width: 480px) {
            table {
                min-width: 800px;
                font-size: 0.7rem;
            }
            th, td {
                padding: 6px 4px;
                font-size: 0.68rem;
            }
            th {
                font-size: 0.65rem;
            }
            td.note-cell {
                min-width: 80px;
                max-width: 120px;
                font-size: 0.68rem;
            }
            .btn-sm {
                padding: 4px 6px;
                font-size: 0.65rem;
            }
            .actions {
                gap: 3px;
            }
        }

        @media (max-width: 600px) {
            .welcome-header .welcome-text h1 { font-size: 1.25rem; }
            .stat-card .stat-value { font-size: 1.3rem; }
            .section-card { padding: 18px; }
        }
</style>

</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <!-- ===== WELCOME HEADER ===== -->
    <div class="welcome-header">
        <div class="welcome-text">
            <h1><i class="fas fa-exchange-alt"></i> صرافي حساب</h1>
            <p>د صرافۍ د پیسو د راکړې ورکړې بشپړ مدیریت</p>
        </div>
        <div class="welcome-info">
            <div class="user-chip">
                <i class="fas fa-user-circle"></i>
                <?php echo h($current_user); ?>
            </div>
            <div class="date-chip">
                <i class="fas fa-calendar-day"></i>
                <?php echo faToday(true); ?>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i>
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i>
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <!-- ===== BALANCE SUMMARY ===== -->
    <div class="stats-grid">

        <div class="stat-card received">
            <div class="stat-top">
                <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
                <span class="stat-badge">ترلاسه شوي</span>
            </div>
            <div class="stat-label">له صراف څخه ترلاسه شوي</div>
            <div class="stat-value">$<?= number_format($totalReceived, 2) ?></div>
            <div class="stat-sub">
                <i class="fas fa-money-bill-wave"></i> ټول وخت
            </div>
        </div>

        <div class="stat-card paid">
            <div class="stat-top">
                <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
                <span class="stat-badge">ورکړل شوي</span>
            </div>
            <div class="stat-label">صراف ته ورکړل شوي</div>
            <div class="stat-value">$<?= number_format($totalPaid, 2) ?></div>
            <div class="stat-sub">
                <i class="fas fa-hand-holding-usd"></i> ټول وخت
            </div>
        </div>

        <div class="stat-card balance">
            <div class="stat-top">
                <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                <span class="stat-badge">موجود</span>
            </div>
            <div class="stat-label">اوسنی بیلانس (ډالر)</div>
            <div class="stat-value">$<?= number_format($balance, 2) ?></div>
            <div class="stat-sub">
                <i class="fas fa-balance-scale"></i> ترلاسه شوي − ورکړل شوي
            </div>
        </div>

    </div>

    <!-- ===== EDIT TRANSACTION FORM ===== -->
    <?php if ($editData): ?>

    <div class="section-card blue">
        <div class="section-header">
            <div class="title">
                <i class="fas fa-edit"></i>
                د معاملې سمول #<?= h($editData["id"]) ?>
            </div>
            <a href="sarafe.php" class="view-link">بېرته</a>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= h($editData["id"]) ?>">

            <div class="section-grid" style="margin-bottom:0;">
                <div>
                    <div class="form-group">
                        <label><i class="fas fa-tags"></i> د معاملې ډول</label>
                        <select name="transaction_type" id="edit_type" onchange="changeEditType()" required>
                            <option value="received_from_saraf" <?= $editData["transaction_type"] === "received_from_saraf" ? "selected" : "" ?>>
                                له صراف څخه ترلاسه شوي
                            </option>
                            <option value="paid_to_saraf" <?= $editData["transaction_type"] === "paid_to_saraf" ? "selected" : "" ?>>
                                صراف ته ورکړل شوي
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> نېټه</label>

                        <!-- ===== DATE CONVERTER (EDIT FORM) ===== -->
                        <div class="date-converter" data-form="edit">
                            <div class="converter-title">
                                <i class="fas fa-calendar-alt"></i>
                                نیټه (دواړه تقویمونه)
                                <span class="converter-badge"><i class="fas fa-sync-alt"></i> اتومات تړاو</span>
                            </div>
                            <div class="date-converter-row">
                                <div class="date-field">
                                    <label><i class="fas fa-globe"></i> میلادی (US)</label>
                                    <div class="triple-date-input">
                                        <select id="edit_usYearSelect"></select>
                                        <select id="edit_usMonthSelect"></select>
                                        <select id="edit_usDaySelect"></select>
                                    </div>
                                </div>
                                <div class="date-field">
                                    <label><i class="fas fa-moon"></i> هجري شمسي (افغاني)</label>
                                    <div class="triple-date-input">
                                        <select id="edit_afghanYearSelect"></select>
                                        <select id="edit_afghanMonthSelect"></select>
                                        <select id="edit_afghanDaySelect"></select>
                                    </div>
                                </div>
                            </div>
                            <div class="date-sync-hint">
                                <i class="fas fa-info-circle"></i>
                                یو تقویم بدل کړئ، بل به اتومات ډک شي
                            </div>
                        </div>

                        <input type="hidden" name="transaction_date" id="edit_dateHidden" value="<?= h($editData["transaction_date"]) ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-coins"></i> اسعار</label>
                        <select name="currency" id="edit_currency" onchange="changeEditCurrency()" required>
                            <option value="CNY" <?= $editData["currency"] === "CNY" ? "selected" : "" ?>>Chinese Yuan (CNY)</option>
                            <option value="AFN" <?= $editData["currency"] === "AFN" ? "selected" : "" ?>>Afghan Afghani (AFN)</option>
                            <option value="USD" <?= $editData["currency"] === "USD" ? "selected" : "" ?>>US Dollar (USD)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <div class="form-group">
                        <label id="edit_amount_label"><i class="fas fa-money-bill"></i> مقدار</label>
                        <input type="number" name="amount" id="edit_amount" step="0.01" min="0.01" value="<?= h($editData["amount"]) ?>" required>
                    </div>

                    <div class="form-group" id="edit_rate_box">
                        <label id="edit_rate_label"><i class="fas fa-percentage"></i> د تبادلې نرخ</label>
                        <input type="number" name="exchange_rate" id="edit_rate" step="0.0001" min="0.0001" value="<?= h($editData["exchange_rate"]) ?>">
                    </div>

                    <div class="preview">
                        <span>ټول ډالر:</span>
                        <span>$<span id="edit_total">0.00</span></span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> یادونه</label>
                <textarea name="note"><?= h($editData["note"]) ?></textarea>
            </div>

            <button type="submit" class="btn btn-blue">
                <i class="fas fa-save"></i> معامله تازه کړه
            </button>
            <a href="sarafe.php" class="btn-cancel">
                <i class="fas fa-times"></i> سمول لغوه کړه
            </a>
        </form>
    </div>

    <?php else: ?>

    <!-- ===== NEW TRANSACTION FORMS ===== -->
    <div class="section-grid">

        <!-- MONEY RECEIVED = RED -->
        <div class="section-card green">
            <div class="section-header">
                <div class="title"><i class="fas fa-arrow-down"></i> له صراف څخه ترلاسه شوي</div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="transaction_type" value="received_from_saraf">

                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> نېټه</label>

                    <!-- ===== DATE CONVERTER (RECEIVED FORM) ===== -->
                    <div class="date-converter" data-form="receive">
                        <div class="converter-title">
                            <i class="fas fa-calendar-alt"></i>
                            نیټه (دواړه تقویمونه)
                            <span class="converter-badge"><i class="fas fa-sync-alt"></i> اتومات تړاو</span>
                        </div>
                        <div class="date-converter-row">
                            <div class="date-field">
                                <label><i class="fas fa-globe"></i> میلادی (US)</label>
                                <div class="triple-date-input">
                                    <select id="receive_usYearSelect"></select>
                                    <select id="receive_usMonthSelect"></select>
                                    <select id="receive_usDaySelect"></select>
                                </div>
                            </div>
                            <div class="date-field">
                                <label><i class="fas fa-moon"></i> هجري شمسي (افغاني)</label>
                                <div class="triple-date-input">
                                    <select id="receive_afghanYearSelect"></select>
                                    <select id="receive_afghanMonthSelect"></select>
                                    <select id="receive_afghanDaySelect"></select>
                                </div>
                            </div>
                        </div>
                        <div class="date-sync-hint">
                            <i class="fas fa-info-circle"></i>
                            یو تقویم بدل کړئ، بل به اتومات ډک شي
                        </div>
                    </div>

                    <input type="hidden" name="transaction_date" id="receive_dateHidden" value="<?= $today_date ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-coins"></i> اسعار وټاکه</label>
                    <select name="currency" id="receive_currency" onchange="changeReceiveCurrency()">
                        <option value="CNY">Chinese Yuan (CNY)</option>
                        <option value="USD">US Dollar (USD)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label id="receive_amount_label"><i class="fas fa-money-bill"></i> CNY مقدار</label>
                    <input type="number" name="amount" id="receive_amount" step="0.01" min="0.01" placeholder="مقدار ولیکئ" required>
                </div>

                <div class="form-group" id="receive_rate_box">
                    <label><i class="fas fa-percentage"></i> ۱ ډالر = څو یوان؟</label>
                    <input type="number" name="exchange_rate" id="receive_rate" step="0.0001" min="0.0001" placeholder="مثال: 7.20" required>
                </div>

                <div class="preview">
                    <span>ټول ډالر:</span>
                    <span>$<span id="receive_total">0.00</span></span>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> یادونه</label>
                    <textarea name="note" placeholder="یادونه ولیکئ..."></textarea>
                </div>

                <button type="submit" class="btn btn-green">
                    <i class="fas fa-save"></i> ترلاسه شوي ثبت کړه
                </button>
            </form>
        </div>

        <!-- MONEY PAID = GREEN -->
        <div class="section-card red">
            <div class="section-header">
                <div class="title"><i class="fas fa-arrow-up"></i> صراف ته ورکړل شوي</div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="transaction_type" value="paid_to_saraf">

                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> نېټه</label>

                    <!-- ===== DATE CONVERTER (PAID FORM) ===== -->
                    <div class="date-converter" data-form="paid">
                        <div class="converter-title">
                            <i class="fas fa-calendar-alt"></i>
                            نیټه (دواړه تقویمونه)
                            <span class="converter-badge"><i class="fas fa-sync-alt"></i> اتومات تړاو</span>
                        </div>
                        <div class="date-converter-row">
                            <div class="date-field">
                                <label><i class="fas fa-globe"></i> میلادی (US)</label>
                                <div class="triple-date-input">
                                    <select id="paid_usYearSelect"></select>
                                    <select id="paid_usMonthSelect"></select>
                                    <select id="paid_usDaySelect"></select>
                                </div>
                            </div>
                            <div class="date-field">
                                <label><i class="fas fa-moon"></i> هجري شمسي (افغاني)</label>
                                <div class="triple-date-input">
                                    <select id="paid_afghanYearSelect"></select>
                                    <select id="paid_afghanMonthSelect"></select>
                                    <select id="paid_afghanDaySelect"></select>
                                </div>
                            </div>
                        </div>
                        <div class="date-sync-hint">
                            <i class="fas fa-info-circle"></i>
                            یو تقویم بدل کړئ، بل به اتومات ډک شي
                        </div>
                    </div>

                    <input type="hidden" name="transaction_date" id="paid_dateHidden" value="<?= $today_date ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-coins"></i> اسعار وټاکه</label>
                    <select name="currency" id="paid_currency" onchange="changePaidCurrency()">
                        <option value="AFN">Afghan Afghani (AFN)</option>
                        <option value="USD">US Dollar (USD)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label id="paid_amount_label"><i class="fas fa-money-bill"></i> AFN مقدار</label>
                    <input type="number" name="amount" id="paid_amount" step="0.01" min="0.01" placeholder="مقدار ولیکئ" required>
                </div>

                <div class="form-group" id="paid_rate_box">
                    <label><i class="fas fa-percentage"></i> ۱ ډالر = څو افغانۍ؟</label>
                    <input type="number" name="exchange_rate" id="paid_rate" step="0.0001" min="0.0001" placeholder="مثال: 70" required>
                </div>

                <div class="preview">
                    <span>ټول ډالر:</span>
                    <span>$<span id="paid_total">0.00</span></span>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> یادونه</label>
                    <textarea name="note" placeholder="یادونه ولیکئ..."></textarea>
                </div>

                <button type="submit" class="btn btn-red">
                    <i class="fas fa-save"></i> ورکړل شوي ثبت کړه
                </button>
            </form>
        </div>

    </div>

    <?php endif; ?>

    <!-- ===== TRANSACTION HISTORY ===== -->
    <div class="section-card">
        <div class="section-header">
            <div class="title"><i class="fas fa-history"></i> د معاملو تاریخ</div>
            <span class="view-link">
                <i class="fas fa-list"></i>
                <?= $history ? $history->num_rows : 0 ?> ریکارډونه
            </span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>نېټه (میلادي)</th>
                        <th>نېټه (هجري شمسي)</th>
                        <th>معامله</th>
                        <th>اسعار</th>
                        <th>مقدار</th>
                        <th>د تبادلې نرخ</th>
                        <th>ټول ډالر</th>
                        <th>یادونه</th>
                        <th>کړنې</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($history && $history->num_rows > 0): ?>
                        <?php while ($row = $history->fetch_assoc()): ?>
                            <tr>
                                <td data-label="ID"><?= h($row["id"]) ?></td>
                                <td class="date-us" data-label="نېټه (میلادي)">
                                    <?= h($row["transaction_date"]) ?>
                                </td>
                                <td class="date-af" data-label="نېټه (هجري)">
                                    <?= h(faDate($row["transaction_date"], true)) ?>
                                </td>
                                <td data-label="معامله">
                                    <?php if ($row["transaction_type"] === "received_from_saraf"): ?>
                                        <span class="type-received">
                                            <i class="fas fa-arrow-down"></i> له صراف څخه
                                        </span>
                                    <?php else: ?>
                                        <span class="type-paid">
                                            <i class="fas fa-arrow-up"></i> صراف ته
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="اسعار"><strong><?= h($row["currency"]) ?></strong></td>
                                <td data-label="مقدار"><?= number_format((float)$row["amount"], 2) ?></td>
                                <td data-label="د تبادلې نرخ"><?= number_format((float)$row["exchange_rate"], 4) ?></td>
                                <td class="amount-usd" data-label="ټول ډالر">$<?= number_format((float)$row["usd_amount"], 2) ?></td>
                                <td class="note-cell" data-label="یادونه"><?= h($row["note"]) ?></td>
                                <td data-label="کړنې">
                                    <div class="actions">
                                        <a class="btn-sm btn-edit" href="sarafe.php?edit=<?= (int)$row["id"] ?>">
                                            <i class="fas fa-edit"></i> سمول
                                        </a>
                                        <form method="POST" onsubmit="return confirm('ایا تاسو ډاډه یاست چې دا معامله حذف کړئ؟');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$row["id"] ?>">
                                            <button type="submit" class="btn-sm btn-delete">
                                                <i class="fas fa-trash"></i> حذف
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10">
                                <div class="mini-empty">
                                    <i class="fas fa-inbox"></i>
                                    هیڅ معامله ونه موندل شوې.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>

// ======================================
// CONVERTER CONFIG
// ======================================
const AFGHAN_MONTHS = [
    'حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله',
    'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت'
];

const US_MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

const TODAY_JY = <?php echo (int)$today_jy; ?>;
const TODAY_JM = <?php echo (int)$today_jm; ?>;
const TODAY_JD = <?php echo (int)$today_jd; ?>;

const TODAY_GY = <?php echo (int)date('Y'); ?>;
const TODAY_GM = <?php echo (int)date('n'); ?>;
const TODAY_GD = <?php echo (int)date('j'); ?>;


// ======================================
// POPULATE HELPERS
// ======================================
function populateYears(selectEl, centerYear) {
    selectEl.innerHTML = '';
    for (let y = centerYear - 30; y <= centerYear + 30; y++) {
        const opt = document.createElement('option');
        opt.value = y;
        opt.textContent = y;
        selectEl.appendChild(opt);
    }
}

function populateAfghanMonths(selectEl) {
    selectEl.innerHTML = '';
    AFGHAN_MONTHS.forEach((name, idx) => {
        const opt = document.createElement('option');
        opt.value = idx + 1;
        opt.textContent = (idx + 1) + ' - ' + name;
        selectEl.appendChild(opt);
    });
}

function populateUsMonths(selectEl) {
    selectEl.innerHTML = '';
    US_MONTHS.forEach((name, idx) => {
        const opt = document.createElement('option');
        opt.value = idx + 1;
        opt.textContent = (idx + 1) + ' - ' + name;
        selectEl.appendChild(opt);
    });
}

function populateAfghanDays(yearSelect, monthSelect, daySelect, jy, jm, selectedDay) {
    daySelect.innerHTML = '';
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
        daySelect.appendChild(opt);
    }
    if (selectedDay && selectedDay <= maxDays) {
        daySelect.value = selectedDay;
    }
}

function populateUsDays(yearSelect, monthSelect, daySelect, gy, gm, selectedDay) {
    daySelect.innerHTML = '';
    // Gregorian month lengths
    let maxDays;
    if (gm === 2) {
        maxDays = ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28;
    } else if ([4,6,9,11].includes(gm)) {
        maxDays = 30;
    } else {
        maxDays = 31;
    }
    for (let d = 1; d <= maxDays; d++) {
        const opt = document.createElement('option');
        opt.value = d;
        opt.textContent = d;
        daySelect.appendChild(opt);
    }
    if (selectedDay && selectedDay <= maxDays) {
        daySelect.value = selectedDay;
    }
}


// ======================================
// SETUP ONE DATE CONVERTER (both sides triple)
// ======================================
function setupDateConverter(prefix) {
    const usYear   = document.getElementById(prefix + '_usYearSelect');
    const usMonth  = document.getElementById(prefix + '_usMonthSelect');
    const usDay    = document.getElementById(prefix + '_usDaySelect');
    const afYear   = document.getElementById(prefix + '_afghanYearSelect');
    const afMonth  = document.getElementById(prefix + '_afghanMonthSelect');
    const afDay    = document.getElementById(prefix + '_afghanDaySelect');
    const hidden   = document.getElementById(prefix + '_dateHidden');

    if (!usYear || !usMonth || !usDay || !afYear || !afMonth || !afDay || !hidden) return;

    // Populate all six dropdowns
    populateYears(usYear, TODAY_GY);
    populateUsMonths(usMonth);
    populateYears(afYear, TODAY_JY);
    populateAfghanMonths(afMonth);

    // Initialize from hidden (which contains the current Gregorian Y-m-d)
    const initGreg = hidden.value || '';

    if (initGreg) {
        const [gy, gm, gd] = initGreg.split('-').map(Number);
        // Set US side immediately
        usYear.value = gy;
        usMonth.value = gm;
        populateUsDays(usYear, usMonth, usDay, gy, gm, gd);
        usDay.value = gd;

        // Sync AF side from the Gregorian value
        fetch('?ajax_date_convert=1&type=to_afghan&gy=' + gy + '&gm=' + gm + '&gd=' + gd)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                afYear.value = data.jy;
                afMonth.value = data.jm;
                populateAfghanDays(afYear, afMonth, afDay, data.jy, data.jm, data.jd);
                afDay.value = data.jd;
            })
            .catch(err => console.error('Init convert error:', err));
    } else {
        // Fallback to today
        usYear.value = TODAY_GY;
        usMonth.value = TODAY_GM;
        populateUsDays(usYear, usMonth, usDay, TODAY_GY, TODAY_GM, TODAY_GD);
        usDay.value = TODAY_GD;

        afYear.value = TODAY_JY;
        afMonth.value = TODAY_JM;
        populateAfghanDays(afYear, afMonth, afDay, TODAY_JY, TODAY_JM, TODAY_JD);
        afDay.value = TODAY_JD;

        hidden.value = TODAY_GY + '-' + String(TODAY_GM).padStart(2,'0') + '-' + String(TODAY_GD).padStart(2,'0');
    }

    // ===== US → AF (when any US dropdown changes) =====
    function onUsChange() {
        const gy = parseInt(usYear.value);
        const gm = parseInt(usMonth.value);
        let gd = parseInt(usDay.value);

        // Rebuild days based on new year/month
        populateUsDays(usYear, usMonth, usDay, gy, gm, gd);
        gd = parseInt(usDay.value);

        // Update hidden immediately
        hidden.value = gy + '-' + String(gm).padStart(2,'0') + '-' + String(gd).padStart(2,'0');

        // Convert to Afghan
        fetch('?ajax_date_convert=1&type=to_afghan&gy=' + gy + '&gm=' + gm + '&gd=' + gd)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                afYear.value = data.jy;
                afMonth.value = data.jm;
                populateAfghanDays(afYear, afMonth, afDay, data.jy, data.jm, data.jd);
                afDay.value = data.jd;
            })
            .catch(err => console.error('Convert error:', err));
    }

    usYear.addEventListener('change', onUsChange);
    usMonth.addEventListener('change', onUsChange);
    usDay.addEventListener('change', onUsChange);

    // ===== AF → US (when any AF dropdown changes) =====
    function onAfChange() {
        const jy = parseInt(afYear.value);
        const jm = parseInt(afMonth.value);
        let jd = parseInt(afDay.value);

        // Rebuild days based on new year/month
        populateAfghanDays(afYear, afMonth, afDay, jy, jm, jd);
        jd = parseInt(afDay.value);

        fetch('?ajax_date_convert=1&type=to_gregorian&jy=' + jy + '&jm=' + jm + '&jd=' + jd)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                usYear.value = data.gy;
                usMonth.value = data.gm;
                populateUsDays(usYear, usMonth, usDay, data.gy, data.gm, data.gd);
                usDay.value = data.gd;
                hidden.value = data.gregorian;
            })
            .catch(err => console.error('Convert error:', err));
    }

    afYear.addEventListener('change', onAfChange);
    afMonth.addEventListener('change', onAfChange);
    afDay.addEventListener('change', onAfChange);
}

// ===== INIT ALL 3 CONVERTERS =====
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('edit_usYearSelect')) {
        setupDateConverter('edit');
    }
    if (document.getElementById('receive_usYearSelect')) {
        setupDateConverter('receive');
    }
    if (document.getElementById('paid_usYearSelect')) {
        setupDateConverter('paid');
    }
});


// ======================================
// RECEIVED FROM SARAF — CURRENCY LOGIC
// ======================================

function changeReceiveCurrency() {
    let currency = document.getElementById("receive_currency").value;
    let rateBox = document.getElementById("receive_rate_box");
    let rate = document.getElementById("receive_rate");
    let label = document.getElementById("receive_amount_label");

    if (currency === "USD") {
        rateBox.style.display = "none";
        rate.required = false;
        rate.value = "1";
        label.innerHTML = '<i class="fas fa-money-bill"></i> USD مقدار';
    } else {
        rateBox.style.display = "block";
        rate.required = true;
        rate.value = "";
        label.innerHTML = '<i class="fas fa-money-bill"></i> CNY مقدار';
    }
    calculateReceive();
}

function calculateReceive() {
    let amount = parseFloat(document.getElementById("receive_amount").value) || 0;
    let currency = document.getElementById("receive_currency").value;
    let rate = currency === "USD" ? 1 : parseFloat(document.getElementById("receive_rate").value) || 0;
    let total = rate > 0 ? amount / rate : 0;
    document.getElementById("receive_total").innerText = total.toFixed(2);
}

document.getElementById("receive_amount").addEventListener("input", calculateReceive);
document.getElementById("receive_rate").addEventListener("input", calculateReceive);


// ======================================
// PAID TO SARAF — CURRENCY LOGIC
// ======================================

function changePaidCurrency() {
    let currency = document.getElementById("paid_currency").value;
    let rateBox = document.getElementById("paid_rate_box");
    let rate = document.getElementById("paid_rate");
    let label = document.getElementById("paid_amount_label");

    if (currency === "USD") {
        rateBox.style.display = "none";
        rate.required = false;
        rate.value = "1";
        label.innerHTML = '<i class="fas fa-money-bill"></i> USD مقدار';
    } else {
        rateBox.style.display = "block";
        rate.required = true;
        rate.value = "";
        label.innerHTML = '<i class="fas fa-money-bill"></i> AFN مقدار';
    }
    calculatePaid();
}

function calculatePaid() {
    let amount = parseFloat(document.getElementById("paid_amount").value) || 0;
    let currency = document.getElementById("paid_currency").value;
    let rate = currency === "USD" ? 1 : parseFloat(document.getElementById("paid_rate").value) || 0;
    let total = rate > 0 ? amount / rate : 0;
    document.getElementById("paid_total").innerText = total.toFixed(2);
}

document.getElementById("paid_amount").addEventListener("input", calculatePaid);
document.getElementById("paid_rate").addEventListener("input", calculatePaid);


// ======================================
// EDIT FORM — CURRENCY LOGIC
// ======================================

function changeEditType() {
    let type = document.getElementById("edit_type").value;
    let currency = document.getElementById("edit_currency");

    if (type === "received_from_saraf") {
        currency.innerHTML = `
            <option value="CNY">Chinese Yuan (CNY)</option>
            <option value="USD">US Dollar (USD)</option>
        `;
    } else {
        currency.innerHTML = `
            <option value="AFN">Afghan Afghani (AFN)</option>
            <option value="USD">US Dollar (USD)</option>
        `;
    }
    changeEditCurrency();
}

function changeEditCurrency() {
    let currency = document.getElementById("edit_currency").value;
    let rateBox = document.getElementById("edit_rate_box");
    let rate = document.getElementById("edit_rate");
    let label = document.getElementById("edit_amount_label");

    if (currency === "USD") {
        rateBox.style.display = "none";
        rate.required = false;
        rate.value = "1";
        label.innerHTML = '<i class="fas fa-money-bill"></i> USD مقدار';
    } else {
        rateBox.style.display = "block";
        rate.required = true;
        if (rate.value === "1") rate.value = "";
        label.innerHTML = '<i class="fas fa-money-bill"></i> ' + currency + ' مقدار';
    }
    calculateEdit();
}

function calculateEdit() {
    let amount = parseFloat(document.getElementById("edit_amount").value) || 0;
    let currency = document.getElementById("edit_currency").value;
    let rate = currency === "USD" ? 1 : parseFloat(document.getElementById("edit_rate").value) || 0;
    let total = rate > 0 ? amount / rate : 0;
    document.getElementById("edit_total").innerText = total.toFixed(2);
}

<?php if ($editData): ?>
document.getElementById("edit_amount").addEventListener("input", calculateEdit);
document.getElementById("edit_rate").addEventListener("input", calculateEdit);
changeEditCurrency();
<?php endif; ?>

</script>

</body>
</html>