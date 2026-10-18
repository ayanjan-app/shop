<?php
session_start();
require_once 'db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id <= 0) {
    die("Invalid client ID.");
}

// Client info
$cStmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$cStmt->bind_param("i", $client_id);
$cStmt->execute();
$client = $cStmt->get_result()->fetch_assoc();
$cStmt->close();

if (!$client) {
    die("Client not found.");
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

function formatAfghanDateFromGregorian($gregorianDate) {
    if (empty($gregorianDate) || $gregorianDate === '0000-00-00') return '—';
    $parts = explode('-', $gregorianDate);
    if (count($parts) < 3) return $gregorianDate;
    [$gy, $gm, $gd] = [(int)$parts[0], (int)$parts[1], (int)$parts[2]];
    [$jy, $jm, $jd] = gregorianToAfghan($gy, $gm, $gd);
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}

// ==== Totals per currency ====
function getTotals($conn, $client_id, $currency) {
    $invStmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE client_id = ? AND currency = ?");
    $invStmt->bind_param("is", $client_id, $currency);
    $invStmt->execute();
    $invoiced = $invStmt->get_result()->fetch_assoc()['total'] ?? 0;
    $invStmt->close();

    $payStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM client_payments WHERE client_id = ? AND currency = ?");
    $payStmt->bind_param("is", $client_id, $currency);
    $payStmt->execute();
    $paid = $payStmt->get_result()->fetch_assoc()['total'] ?? 0;
    $payStmt->close();

    return [
        'invoiced' => $invoiced,
        'paid'     => $paid,
        'unpaid'   => $invoiced - $paid
    ];
}

$usd = getTotals($conn, $client_id, 'USD');
$afn = getTotals($conn, $client_id, 'AFN');

// Determine which currencies are "active" (have any activity)
$has_usd = ($usd['invoiced'] > 0 || $usd['paid'] > 0);
$has_afn = ($afn['invoiced'] > 0 || $afn['paid'] > 0);
$has_any_currency = ($has_usd || $has_afn);

// ==== All invoices ====
$invList = [];
$invQ = $conn->prepare("SELECT id, invoice_number, invoice_date, currency, total_amount, note FROM sales WHERE client_id = ? ORDER BY invoice_date DESC, id DESC");
$invQ->bind_param("i", $client_id);
$invQ->execute();
$res = $invQ->get_result();
while ($r = $res->fetch_assoc()) $invList[] = $r;
$invQ->close();

// ==== All payments ====
$payList = [];
$payQ = $conn->prepare("SELECT p.id, p.payment_date, p.currency, p.amount, p.hawala_number, p.note, s.invoice_number 
                        FROM client_payments p 
                        LEFT JOIN sales s ON p.sale_id = s.id 
                        WHERE p.client_id = ? 
                        ORDER BY p.payment_date DESC, p.id DESC");
$payQ->bind_param("i", $client_id);
$payQ->execute();
$res = $payQ->get_result();
while ($r = $res->fetch_assoc()) $payList[] = $r;
$payQ->close();

// ==== Client photo detection (supports multiple possible column names) ====
$client_photo = '';
foreach (['photo', 'profile_picture', 'image', 'avatar', 'picture'] as $col) {
    if (!empty($client[$col])) {
        // If it's a filename, prefix with uploads/clients/
        if (strpos($client[$col], '/') === false) {
            $candidate = 'uploads/clients/' . $client[$col];
            if (file_exists(__DIR__ . '/' . $candidate)) {
                $client_photo = $candidate;
                break;
            }
        } else {
            if (file_exists(__DIR__ . '/' . $client[$col])) {
                $client_photo = $client[$col];
                break;
            }
        }
    }
}

// Generate initials from full name (for fallback avatar)
$initials = '';
$name_parts = preg_split('/\s+/', trim($client['full_name']));
if (count($name_parts) >= 2) {
    $initials = mb_substr($name_parts[0], 0, 1, 'UTF-8') . mb_substr($name_parts[1], 0, 1, 'UTF-8');
} elseif (count($name_parts) === 1) {
    $initials = mb_substr($name_parts[0], 0, 2, 'UTF-8');
}
if ($initials === '') $initials = '?';

$today = date('Y-m-d H:i');
$today_afghan = formatAfghanDateFromGregorian(date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>د بیل چاپ · <?php echo htmlspecialchars($client['full_name']); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Noto Naskh Arabic', 'Segoe UI', Tahoma, sans-serif; }
    body {
        background: #eef1f5;
        padding: 20px 15px;
        color: #111827;
        font-size: 14px;
        line-height: 1.5;
    }

    /* ===== TOP ACTION BAR (hidden when printing) ===== */
    .top-bar {
        max-width: 900px;
        margin: 0 auto 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
    }
    .top-bar .info {
        font-weight: 600;
        color: #4b5563;
        font-size: 0.85rem;
    }
    .top-bar .actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .top-bar button, .top-bar a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border: none;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        text-decoration: none;
        font-family: inherit;
        transition: all 0.2s ease;
    }
    .btn-print {
        background: #111827;
        color: white;
    }
    .btn-print:hover { background: #1e3c72; }
    .btn-back {
        background: #ffffff;
        color: #111827;
        border: 1px solid #d1d5db;
    }
    .btn-back:hover { background: #f3f4f6; }
    .btn-share {
        background: #25D366;
        color: white;
    }
    .btn-share:hover { background: #1da851; }

    /* ===== INVOICE SHEET (standard like Amazon / Microsoft) ===== */
    .bill {
        max-width: 900px;
        margin: 0 auto;
        background: #ffffff;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        overflow: hidden;
    }

    /* ===== HEADER ===== */
    .inv-header {
        padding: 28px 32px 20px;
        border-bottom: 2px solid #111827;
    }
    .inv-header-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        flex-wrap: wrap;
    }
    .inv-brand {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .inv-brand-icon {
        width: 52px;
        height: 52px;
        border-radius: 8px;
        background: #111827;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    .inv-brand-text h1 {
        font-size: 1.35rem;
        color: #111827;
        margin: 0 0 3px;
        font-weight: 800;
        letter-spacing: -0.3px;
    }
    .inv-brand-text p {
        color: #6b7280;
        font-size: 0.82rem;
        margin: 0;
        font-weight: 500;
    }
    .inv-doc-meta {
        text-align: left;
        font-size: 0.82rem;
        color: #374151;
        font-weight: 500;
        min-width: 220px;
    }
    .inv-doc-meta .doc-type {
        font-size: 1.6rem;
        color: #111827;
        font-weight: 800;
        letter-spacing: -0.5px;
        margin-bottom: 8px;
    }
    .inv-doc-meta .meta-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 3px 0;
        border-bottom: 1px dotted #e5e7eb;
    }
    .inv-doc-meta .meta-row:last-child { border-bottom: none; }
    .inv-doc-meta .meta-label { color: #6b7280; }
    .inv-doc-meta .meta-value { font-weight: 700; color: #111827; }

    /* ===== BILL-TO SECTION (client card with photo) ===== */
    .bill-to-section {
        padding: 20px 32px;
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
    }
    .bill-to-card {
        display: flex;
        align-items: flex-start;
        gap: 18px;
        flex-wrap: wrap;
    }
    .client-avatar {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        flex-shrink: 0;
        border: 3px solid #ffffff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        background: #1e3c72;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .client-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .client-avatar .avatar-initials {
        color: #ffffff;
        font-size: 1.6rem;
        font-weight: 800;
        letter-spacing: 0.5px;
    }
    .client-details {
        flex: 1;
        min-width: 200px;
    }
    .client-details .section-label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #6b7280;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .client-details .client-name {
        font-size: 1.15rem;
        font-weight: 800;
        color: #111827;
        margin-bottom: 8px;
    }
    .client-details .client-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 20px;
        font-size: 0.83rem;
        color: #4b5563;
    }
    .client-details .client-meta span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .client-details .client-meta i {
        color: #6b7280;
        font-size: 0.78rem;
    }

    /* ===== NO-ACTIVITY BANNER ===== */
    .no-activity-banner {
        margin: 20px 32px;
        padding: 22px 24px;
        background: #fef3c7;
        border: 1px solid #fcd34d;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 14px;
        color: #92400e;
        font-weight: 600;
        font-size: 0.92rem;
    }
    .no-activity-banner i {
        font-size: 1.6rem;
        color: #d97706;
        flex-shrink: 0;
    }

    /* ===== SUMMARY CARDS (only show active currencies) ===== */
    .summary-section {
        padding: 22px 32px;
        border-bottom: 1px solid #e5e7eb;
    }
    .summary-section .section-heading {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #6b7280;
        font-weight: 700;
        margin-bottom: 14px;
    }
    .summary-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 14px;
    }
    .summary-grid.two { grid-template-columns: 1fr 1fr; }
    @media (max-width: 600px) {
        .summary-grid.two { grid-template-columns: 1fr; }
    }

    .currency-block {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        background: #ffffff;
    }
    .currency-block .block-header {
        padding: 10px 14px;
        font-size: 0.85rem;
        font-weight: 800;
        color: #ffffff;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .currency-block.usd .block-header { background: #16a34a; }
    .currency-block.afn .block-header { background: #dc2626; }
    .currency-block .block-body {
        padding: 12px 14px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .currency-block .kv-row {
        display: flex;
        justify-content: space-between;
        font-size: 0.86rem;
        padding: 3px 0;
    }
    .currency-block .kv-row .k { color: #6b7280; }
    .currency-block .kv-row .v { font-weight: 700; color: #111827; }
    .currency-block .kv-row.total {
        border-top: 1px dashed #d1d5db;
        padding-top: 8px;
        margin-top: 4px;
        font-size: 0.95rem;
    }
    .currency-block .kv-row.total .k { color: #111827; font-weight: 700; }
    .currency-block .kv-row.total .v { font-size: 1.05rem; }
    .currency-block .kv-row.total.unpaid .v { color: #dc2626; }
    .currency-block .kv-row.total.paid-full .v { color: #16a34a; }

    /* ===== TABLE SECTIONS ===== */
    .table-section {
        padding: 20px 32px 8px;
    }
    .table-section .section-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #6b7280;
        font-weight: 700;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 2px solid #111827;
    }
    .table-section .section-heading .count {
        background: #f3f4f6;
        color: #374151;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: none;
        letter-spacing: 0;
    }

    .table-scroll { width: 100%; }

    .invoice-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .invoice-table thead th {
        background: #f3f4f6;
        color: #374151;
        padding: 10px 10px;
        font-weight: 700;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: right;
        border-bottom: 1px solid #d1d5db;
        white-space: nowrap;
    }
    .invoice-table thead th small {
        font-weight: 400;
        opacity: 0.7;
        font-size: 0.68rem;
        text-transform: none;
        letter-spacing: 0;
    }
    .invoice-table tbody td {
        padding: 10px 10px;
        text-align: right;
        border-bottom: 1px solid #e5e7eb;
        color: #111827;
        font-weight: 500;
        vertical-align: middle;
    }
    .invoice-table tbody tr:last-child td { border-bottom: none; }
    .invoice-table tbody tr:nth-child(even) { background: #fafbfc; }
    .invoice-table tbody tr:hover { background: #f3f4f6; }

    .invoice-table .badge {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 4px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.3px;
    }
    .invoice-table .badge-USD { background: #dcfce7; color: #166534; }
    .invoice-table .badge-AFN { background: #fee2e2; color: #991b1b; }

    .invoice-table .amt-USD { color: #166534; font-weight: 700; }
    .invoice-table .amt-AFN { color: #991b1b; font-weight: 700; }

    .invoice-table .dual-date-cell {
        display: inline-flex;
        flex-direction: column;
        gap: 2px;
        font-size: 0.78rem;
        line-height: 1.35;
    }
    .invoice-table .dual-date-cell .d-us {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #111827;
        font-weight: 600;
    }
    .invoice-table .dual-date-cell .d-us i { color: #16a34a; font-size: 0.66rem; }
    .invoice-table .dual-date-cell .d-af {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #991b1b;
        font-weight: 600;
    }
    .invoice-table .dual-date-cell .d-af i { color: #e6b12e; font-size: 0.66rem; }

    .invoice-table .empty-row td {
        text-align: center;
        padding: 24px;
        color: #9ca3af;
        font-weight: 500;
        font-size: 0.85rem;
    }

    /* ===== FOOTER ===== */
    .inv-footer {
        padding: 20px 32px 26px;
        border-top: 2px solid #111827;
        margin-top: 20px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 15px;
        font-size: 0.8rem;
        color: #6b7280;
    }
    .inv-footer .thanks {
        color: #111827;
        font-weight: 800;
        font-size: 0.92rem;
    }
    .inv-footer .store {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #374151;
        font-weight: 600;
    }

    /* ===== MOBILE ===== */
    @media (max-width: 600px) {
        .bill { border-radius: 0; border-left: none; border-right: none; }
        .inv-header, .bill-to-section, .summary-section, .table-section, .inv-footer { padding-left: 16px; padding-right: 16px; }
        .inv-brand-text h1 { font-size: 1.1rem; }
        .inv-doc-meta .doc-type { font-size: 1.3rem; }
        .inv-doc-meta { min-width: 0; text-align: right; width: 100%; }

        .table-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 6px;
            margin-bottom: 4px;
        }
        .table-scroll::-webkit-scrollbar { height: 6px; }
        .table-scroll::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 3px; }

        .invoice-table { min-width: 560px; }
        .client-avatar { width: 56px; height: 56px; }
        .client-avatar .avatar-initials { font-size: 1.2rem; }
    }

    /* ===== PRINT ===== */
    @media print {
        body { background: #ffffff; padding: 0; }
        .top-bar { display: none !important; }
        .bill {
            max-width: 100%;
            margin: 0;
            padding: 0;
            border: none;
            border-radius: 0;
            box-shadow: none;
        }
        .inv-header { padding: 0 0 14px; }
        .bill-to-section { padding: 14px 0; background: #f9fafb !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .summary-section { padding: 14px 0; }
        .table-section { padding: 14px 0 4px; }
        .inv-footer { padding: 14px 0; }
        .currency-block .block-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .invoice-table thead th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .invoice-table .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .no-activity-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .client-avatar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .table-scroll { overflow: visible !important; }
        .invoice-table { min-width: 0 !important; }
        @page { margin: 12mm; }
    }
</style>
</head>
<body>

<!-- Top Action Bar -->
<div class="top-bar">
    <div class="info">
        <i class="fas fa-receipt"></i> د بیل مخکتنه — <?php echo htmlspecialchars($client['full_name']); ?>
    </div>
    <div class="actions">
        <a href="pay.php" class="btn-back">
            <i class="fas fa-arrow-right"></i> بیرته
        </a>
        <button onclick="shareBill()" class="btn-share">
            <i class="fas fa-share-alt"></i> شریکول
        </button>
        <button onclick="window.print()" class="btn-print">
            <i class="fas fa-print"></i> بیل چاپ کړئ
        </button>
    </div>
</div>

<!-- ===== INVOICE ===== -->
<div class="bill">

    <!-- HEADER -->
    <div class="inv-header">
        <div class="inv-header-top">
            <div class="inv-brand">
                <div class="inv-brand-icon">
                    <i class="fas fa-pen-fancy"></i>
                </div>
                <div class="inv-brand-text">
                    <h1>حاجی نادر او نقیب حقمل</h1>
                    <p>بنجاره پلورنحٔی</p>
                </div>
            </div>
            <div class="inv-doc-meta">
                <div class="doc-type">INVOICE</div>
                <div class="meta-row">
                    <span class="meta-label">د بیل شمېره:</span>
                    <span class="meta-value">BILL-<?php echo str_pad($client['id'], 5, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">میلادي نېټه:</span>
                    <span class="meta-value"><?php echo date('Y-m-d'); ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">افغاني نېټه:</span>
                    <span class="meta-value"><?php echo $today_afghan; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- BILL TO (client with photo) -->
    <div class="bill-to-section">
        <div class="bill-to-card">
            <div class="client-avatar">
                <?php if (!empty($client_photo)): ?>
                    <img src="<?php echo htmlspecialchars($client_photo); ?>" alt="<?php echo htmlspecialchars($client['full_name']); ?>">
                <?php else: ?>
                    <span class="avatar-initials"><?php echo htmlspecialchars($initials); ?></span>
                <?php endif; ?>
            </div>
            <div class="client-details">
                <div class="section-label">پیرودونکی</div>
                <div class="client-name"><?php echo htmlspecialchars($client['full_name']); ?></div>
                <div class="client-meta">
                    <?php if (!empty($client['phone'])): ?>
                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($client['phone']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($client['province'])): ?>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($client['province']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($client['address'])): ?>
                        <span><i class="fas fa-home"></i> <?php echo htmlspecialchars($client['address']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($has_any_currency): ?>
        <!-- ===== SUMMARY (only active currencies) ===== -->
        <div class="summary-section">
            <div class="section-heading">د حساب لنډیز</div>
            <div class="summary-grid <?php echo ($has_usd && $has_afn) ? 'two' : ''; ?>">
                <?php if ($has_usd): ?>
                    <div class="currency-block usd">
                        <div class="block-header">
                            <i class="fas fa-dollar-sign"></i> ډالر (USD)
                        </div>
                        <div class="block-body">
                            <div class="kv-row"><span class="k">ټول فاکتورونه</span><span class="v"><?php echo number_format($usd['invoiced'], 2); ?> $</span></div>
                            <div class="kv-row"><span class="k">تادیه شوي</span><span class="v"><?php echo number_format($usd['paid'], 2); ?> $</span></div>
                            <div class="kv-row total <?php echo ($usd['unpaid'] > 0.01) ? 'unpaid' : 'paid-full'; ?>">
                                <span class="k">پاتې</span>
                                <span class="v"><?php echo number_format($usd['unpaid'], 2); ?> $</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($has_afn): ?>
                    <div class="currency-block afn">
                        <div class="block-header">
                            <i class="fas fa-money-bill-wave"></i> افغاني (AFN)
                        </div>
                        <div class="block-body">
                            <div class="kv-row"><span class="k">ټول فاکتورونه</span><span class="v"><?php echo number_format($afn['invoiced'], 2); ?> ؋</span></div>
                            <div class="kv-row"><span class="k">تادیه شوي</span><span class="v"><?php echo number_format($afn['paid'], 2); ?> ؋</span></div>
                            <div class="kv-row total <?php echo ($afn['unpaid'] > 0.01) ? 'unpaid' : 'paid-full'; ?>">
                                <span class="k">پاتې</span>
                                <span class="v"><?php echo number_format($afn['unpaid'], 2); ?> ؋</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== INVOICES TABLE ===== -->
        <div class="table-section">
            <div class="section-heading">
                <span><i class="fas fa-file-invoice"></i> د فاکتورونو لیست</span>
                <span class="count"><?php echo count($invList); ?></span>
            </div>
            <div class="table-scroll">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>فاکتور</th>
                            <th>میلادي نېټه<br><small>(US)</small></th>
                            <th>افغاني نېټه<br><small>(هجري شمسي)</small></th>
                            <th>ډول</th>
                            <th>اندازه</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($invList) > 0): ?>
                            <?php foreach ($invList as $i => $inv): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                                    <td>
                                        <span class="dual-date-cell">
                                            <span class="d-us"><i class="fas fa-globe"></i> <?php echo htmlspecialchars($inv['invoice_date']); ?></span>
                                            <span class="d-af"><i class="fas fa-moon"></i> <?php echo formatAfghanDateFromGregorian($inv['invoice_date']); ?></span>
                                        </span>
                                    </td>
                                    <td>—</td>
                                    <td>
                                        <span class="badge badge-<?php echo $inv['currency']; ?>">
                                            <?php echo $inv['currency'] === 'USD' ? 'ډالر' : 'افغاني'; ?>
                                        </span>
                                    </td>
                                    <td class="amt-<?php echo $inv['currency']; ?>">
                                        <?php echo number_format($inv['total_amount'], 2); ?>
                                        <?php echo $inv['currency'] === 'USD' ? '$' : '؋'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-row"><td colspan="6">هیڅ فاکتور نشته</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== PAYMENTS TABLE ===== -->
        <div class="table-section">
            <div class="section-heading">
                <span><i class="fas fa-hand-holding-usd"></i> د تادیاتو لیست</span>
                <span class="count"><?php echo count($payList); ?></span>
            </div>
            <div class="table-scroll">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>میلادي نېټه<br><small>(US)</small></th>
                            <th>افغاني نېټه<br><small>(هجري شمسي)</small></th>
                            <th>حواله</th>
                            <th>فاکتور</th>
                            <th>ډول</th>
                            <th>اندازه</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($payList) > 0): ?>
                            <?php foreach ($payList as $i => $p): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td>
                                        <span class="dual-date-cell">
                                            <span class="d-us"><i class="fas fa-globe"></i> <?php echo htmlspecialchars($p['payment_date']); ?></span>
                                            <span class="d-af"><i class="fas fa-moon"></i> <?php echo formatAfghanDateFromGregorian($p['payment_date']); ?></span>
                                        </span>
                                    </td>
                                    <td>—</td>
                                    <td><?php echo !empty($p['hawala_number']) ? htmlspecialchars($p['hawala_number']) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($p['invoice_number'] ?? '—'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $p['currency']; ?>">
                                            <?php echo $p['currency'] === 'USD' ? 'ډالر' : 'افغاني'; ?>
                                        </span>
                                    </td>
                                    <td class="amt-<?php echo $p['currency']; ?>">
                                        <?php echo number_format($p['amount'], 2); ?>
                                        <?php echo $p['currency'] === 'USD' ? '$' : '؋'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-row"><td colspan="7">هیڅ تادیه نشته</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- ===== NO CURRENCY ACTIVITY ===== -->
        <div class="no-activity-banner">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>هیڅ فاکتور یا تادیه نشته.</strong><br>
                دا پیرودونکی لا تر اوسه هیڅ فاکتور یا تادیه نه لري — یوازې د پیرودونکي معلومات ښودل کیږي.
            </div>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <div class="inv-footer">
        <div class="thanks">
            <i class="fas fa-heart"></i> له تاسو سره د کار کولو مننه!
        </div>
        <div class="store">
            <i class="fas fa-store"></i> حاجی نادر او نقیب حقمل
        </div>
    </div>

</div>

<script>
    // Auto-trigger print when opened with ?auto_print=1
    const params = new URLSearchParams(window.location.search);
    if (params.get('auto_print') === '1') {
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 400);
        });
    }

    // Share bill function
    function shareBill() {
        const clientName = <?php echo json_encode($client['full_name']); ?>;
        const billNumber = 'BILL-<?php echo str_pad($client['id'], 5, '0', STR_PAD_LEFT); ?>';
        const today = '<?php echo date("Y-m-d"); ?>';
        const todayAfghan = '<?php echo $today_afghan; ?>';
        const hasUsd = <?php echo $has_usd ? 'true' : 'false'; ?>;
        const hasAfn = <?php echo $has_afn ? 'true' : 'false'; ?>;

        let shareText =
            '📄 د بیل معلومات\n' +
            '━━━━━━━━━━━━━━━━━━\n' +
            '👤 پیرودونکی: ' + clientName + '\n' +
            '🔢 د بیل شمېره: ' + billNumber + '\n' +
            '📅 نېټه: ' + today + ' | ' + todayAfghan + '\n';

        if (!hasUsd && !hasAfn) {
            shareText += '━━━━━━━━━━━━━━━━━━\nℹ️ هیڅ فاکتور یا تادیه نشته.\n';
        } else {
            if (hasUsd) {
                shareText +=
                    '━━━━━━━━━━━━━━━━━━\n' +
                    '💵 ډالر (USD)\n' +
                    '   ټول فاکتورونه: <?php echo number_format($usd['invoiced'], 2); ?> $\n' +
                    '   تادیه شوي: <?php echo number_format($usd['paid'], 2); ?> $\n' +
                    '   پاتې: <?php echo number_format($usd['unpaid'], 2); ?> $\n';
            }
            if (hasAfn) {
                shareText +=
                    '━━━━━━━━━━━━━━━━━━\n' +
                    '💴 افغاني (AFN)\n' +
                    '   ټول فاکتورونه: <?php echo number_format($afn['invoiced'], 2); ?> ؋\n' +
                    '   تادیه شوي: <?php echo number_format($afn['paid'], 2); ?> ؋\n' +
                    '   پاتې: <?php echo number_format($afn['unpaid'], 2); ?> ؋\n';
            }
            shareText += '━━━━━━━━━━━━━━━━━━\n';
        }

        if (navigator.share) {
            navigator.share({
                title: 'د بیل معلومات — ' + clientName,
                text: shareText
            }).catch(function(err) {
                console.log('Share cancelled or failed:', err);
            });
        } else {
            showShareFallback(shareText, clientName, billNumber);
        }
    }

    function showShareFallback(shareText, clientName, billNumber) {
        const existing = document.getElementById('shareModal');
        if (existing) existing.remove();

        const encodedText = encodeURIComponent(shareText);

        const modal = document.createElement('div');
        modal.id = 'shareModal';
        modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);display:flex;align-items:center;justify-content:center;z-index:9999;padding:20px;';
        modal.innerHTML =
            '<div style="background:#fff;border-radius:16px;padding:25px;max-width:400px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.3);text-align:center;">' +
                '<h3 style="margin:0 0 8px;color:#1e3c72;font-size:1.15rem;">📤 بیل شریک کړئ</h3>' +
                '<p style="margin:0 0 20px;color:#64748b;font-size:0.85rem;">د شریکولو لپاره یو اپلیکیشن غوره کړئ</p>' +
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:15px;">' +
                    '<a href="https://wa.me/?text=' + encodedText + '" target="_blank" rel="noopener" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:14px;background:#25D366;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.9rem;">' +
                        '<i class="fab fa-whatsapp" style="font-size:1.2rem;"></i> WhatsApp' +
                    '</a>' +
                    '<a href="https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(window.location.href) + '&quote=' + encodedText + '" target="_blank" rel="noopener" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:14px;background:#1877F2;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.9rem;">' +
                        '<i class="fab fa-facebook-f" style="font-size:1.2rem;"></i> Facebook' +
                    '</a>' +
                    '<a href="https://t.me/share/url?url=' + encodeURIComponent(window.location.href) + '&text=' + encodedText + '" target="_blank" rel="noopener" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:14px;background:#0088cc;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.9rem;">' +
                        '<i class="fab fa-telegram-plane" style="font-size:1.2rem;"></i> Telegram' +
                    '</a>' +
                    '<a href="https://twitter.com/intent/tweet?text=' + encodedText + '" target="_blank" rel="noopener" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:14px;background:#1DA1F2;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.9rem;">' +
                        '<i class="fab fa-twitter" style="font-size:1.2rem;"></i> Twitter' +
                    '</a>' +
                '</div>' +
                '<button onclick="copyShareText()" style="width:100%;padding:12px;background:#f1f5f9;color:#1e3c72;border:2px solid #cbd5e1;border-radius:10px;font-weight:700;font-size:0.9rem;cursor:pointer;margin-bottom:10px;font-family:inherit;">' +
                    '<i class="fas fa-copy"></i> متن کاپي کړئ' +
                '</button>' +
                '<button onclick="document.getElementById(\'shareModal\').remove()" style="width:100%;padding:12px;background:#1e3c72;color:#fff;border:none;border-radius:10px;font-weight:700;font-size:0.9rem;cursor:pointer;font-family:inherit;">' +
                    'بندول' +
                '</button>' +
                '<p id="copyStatus" style="margin:10px 0 0;color:#16a34a;font-size:0.8rem;font-weight:600;display:none;">✓ کاپي شو!</p>' +
            '</div>';

        document.body.appendChild(modal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.remove();
        });

        window._shareText = shareText;
    }

    function copyShareText() {
        const text = window._shareText || '';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                const status = document.getElementById('copyStatus');
                if (status) { status.style.display = 'block'; setTimeout(function(){ status.style.display = 'none'; }, 2000); }
            });
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            const status = document.getElementById('copyStatus');
            if (status) { status.style.display = 'block'; setTimeout(function(){ status.style.display = 'none'; }, 2000); }
        }
    }
</script>

</body>
</html>