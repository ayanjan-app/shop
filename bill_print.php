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
        background: #e5e7eb;
        padding: 20px 15px;
        color: #1f2937;
        font-size: 14px;
    }

    /* Top action bar (hidden when printing) */
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
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        text-decoration: none;
        font-family: inherit;
        transition: all 0.25s ease;
    }
    .btn-print {
        background: linear-gradient(135deg, #1e3c72, #2b4a7a);
        color: white;
    }
    .btn-print:hover {
        background: linear-gradient(135deg, #16a34a, #15803d);
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(30,60,114,0.35);
    }
    .btn-back {
        background: #ffffff;
        color: #1e3c72;
        border: 2px solid #cbd5e1;
    }
    .btn-back:hover {
        background: #f1f5f9;
        transform: translateY(-2px);
    }

    /* ===== BILL ===== */
    .bill {
        max-width: 900px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 16px;
        padding: 30px 35px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        border-top: 6px solid #1e3c72;
    }
    .bill-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding-bottom: 18px;
        border-bottom: 2px solid #f1f5f9;
        margin-bottom: 20px;
        gap: 15px;
        flex-wrap: wrap;
    }
    .bill-header .brand {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .bill-header .brand-icon {
        width: 60px;
        height: 60px;
        border-radius: 14px;
        background: linear-gradient(135deg, #1e3c72, #2b4a7a);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        flex-shrink: 0;
    }
    .bill-header .brand-text h1 {
        font-size: 1.5rem;
        color: #1e3c72;
        margin: 0 0 4px;
        font-weight: 800;
    }
    .bill-header .brand-text p {
        color: #64748b;
        font-size: 0.85rem;
        margin: 0;
        font-weight: 600;
    }
    .bill-header .bill-meta {
        text-align: left;
        font-size: 0.82rem;
        color: #4b5563;
        font-weight: 600;
    }
    .bill-header .bill-meta div {
        margin-bottom: 4px;
    }
    .bill-header .bill-meta .label {
        color: #94a3b8;
        font-weight: 500;
    }
    .bill-header .bill-meta .dual-date {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .bill-header .bill-meta .dual-date i {
        font-size: 0.7rem;
    }
    .bill-header .bill-meta .dual-date .us-i { color: #16a34a; }
    .bill-header .bill-meta .dual-date .af-i { color: #e6b12e; }

    .client-info {
        background: #f8fafc;
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 22px;
        border: 2px dashed #cbd5e1;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }
    .client-info .info-item .label {
        font-size: 0.75rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        margin-bottom: 3px;
    }
    .client-info .info-item .value {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1f2937;
    }

    /* ===== CURRENCY SUMMARY CARDS ===== */
    .summary-cards {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 25px;
    }
    @media (max-width: 600px) {
        .summary-cards { grid-template-columns: 1fr; }
    }
    .summary-card {
        border-radius: 12px;
        padding: 16px 18px;
        color: white;
        position: relative;
        overflow: hidden;
    }
    .summary-card.usd {
        background: linear-gradient(135deg, #16a34a, #15803d);
    }
    .summary-card.afn {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
    }
    .summary-card .currency-label {
        font-size: 0.85rem;
        font-weight: 700;
        opacity: 0.95;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .summary-card .rows {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .summary-card .row {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        padding: 4px 0;
    }
    .summary-card .row .k { opacity: 0.9; }
    .summary-card .row .v { font-weight: 800; }
    .summary-card .row.total {
        border-top: 2px solid rgba(255,255,255,0.3);
        padding-top: 8px;
        margin-top: 4px;
        font-size: 1rem;
    }
    .summary-card .row.total .v {
        font-size: 1.15rem;
    }

    /* ===== SECTION TITLES ===== */
    .section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.05rem;
        color: #1e3c72;
        font-weight: 800;
        padding-bottom: 10px;
        margin-bottom: 12px;
        border-bottom: 2px solid #f1f5f9;
    }
    .section-title i {
        color: #e6b12e;
    }

    /* ===== TABLES ===== */
    .bill-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        margin-bottom: 25px;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    .bill-table thead {
        background: #1e3c72;
    }
    .bill-table th {
        padding: 11px 10px;
        color: white;
        font-weight: 700;
        text-align: right;
        font-size: 0.78rem;
        white-space: nowrap;
    }
    .bill-table th small {
        font-weight: 400;
        opacity: 0.85;
        font-size: 0.7rem;
    }
    .bill-table td {
        padding: 10px;
        text-align: right;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 600;
        color: #1f2937;
    }
    .bill-table tbody tr:nth-child(even) {
        background: #f8fafc;
    }
    .bill-table tbody tr:last-child td {
        border-bottom: none;
    }
    .bill-table .amt-usd { color: #16a34a; }
    .bill-table .amt-afn { color: #dc2626; }
    .bill-table .badge-USD { background: #dcfce7; color: #166534; padding: 3px 10px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; }
    .bill-table .badge-AFN { background: #fee2e2; color: #991b1b; padding: 3px 10px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; }
    .bill-table .empty-row td {
        text-align: center;
        padding: 20px;
        color: #94a3b8;
        font-weight: 600;
    }

    /* ===== DUAL DATE CELLS ===== */
    .dual-date-cell {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem;
        font-weight: 700;
    }
    .dual-date-cell .us-date {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #1e3c72;
    }
    .dual-date-cell .us-date i {
        color: #16a34a;
        font-size: 0.68rem;
    }
    .dual-date-cell .af-date {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #991b1b;
    }
    .dual-date-cell .af-date i {
        color: #e6b12e;
        font-size: 0.68rem;
    }
    .dual-date-cell .sep {
        color: #cbd5e1;
        font-weight: 400;
    }

    /* ===== FOOTER ===== */
    .bill-footer {
        margin-top: 30px;
        padding-top: 18px;
        border-top: 2px dashed #e2e8f0;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 15px;
        font-size: 0.8rem;
        color: #64748b;
        font-weight: 600;
    }
    .bill-footer .thanks {
        color: #1e3c72;
        font-weight: 700;
        font-size: 0.9rem;
    }

    /* ===== PRINT STYLES ===== */
    @media print {
        body {
            background: #ffffff;
            padding: 0;
        }
        .top-bar {
            display: none !important;
        }
        .bill {
            max-width: 100%;
            margin: 0;
            padding: 15mm;
            border-radius: 0;
            box-shadow: none;
            border-top-width: 4px;
        }
        .summary-card {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .bill-table thead {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        @page {
            margin: 0;
        }
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
        <button onclick="window.print()" class="btn-print">
            <i class="fas fa-print"></i> بیل چاپ کړئ
        </button>
    </div>
</div>

<!-- ===== BILL ===== -->
<div class="bill">

    <!-- HEADER -->
    <div class="bill-header">
        <div class="brand">
            <div class="brand-icon">
                <i class="fas fa-pen-fancy"></i>
            </div>
            <div class="brand-text">
                <h1>قرطاسیه</h1>
                <p>د دفترونو توکي</p>
            </div>
        </div>
        <div class="bill-meta">
            <div>
                <span class="label">د بیل نېټه:</span>
                <span class="dual-date">
                    <i class="fas fa-globe us-i"></i> <?php echo $today; ?>
                    &nbsp;|&nbsp;
                    <i class="fas fa-moon af-i"></i> <?php echo $today_afghan; ?>
                </span>
            </div>
            <div><span class="label">د بیل شمېره:</span> BILL-<?php echo str_pad($client['id'], 5, '0', STR_PAD_LEFT); ?></div>
        </div>
    </div>

    <!-- CLIENT INFO -->
    <div class="client-info">
        <div class="info-item">
            <div class="label">پیرودونکی</div>
            <div class="value"><?php echo htmlspecialchars($client['full_name']); ?></div>
        </div>
        <?php if (!empty($client['phone'])): ?>
        <div class="info-item">
            <div class="label">تلیفون</div>
            <div class="value"><?php echo htmlspecialchars($client['phone']); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($client['province'])): ?>
        <div class="info-item">
            <div class="label">ولایت</div>
            <div class="value"><?php echo htmlspecialchars($client['province']); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($client['address'])): ?>
        <div class="info-item">
            <div class="label">پته</div>
            <div class="value"><?php echo htmlspecialchars($client['address']); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="summary-cards">
        <!-- USD -->
        <div class="summary-card usd">
            <div class="currency-label"><i class="fas fa-dollar-sign"></i> ډالر (USD)</div>
            <div class="rows">
                <div class="row"><span class="k">ټول فاکتورونه:</span><span class="v"><?php echo number_format($usd['invoiced'], 2); ?> $</span></div>
                <div class="row"><span class="k">تادیه شوي:</span><span class="v"><?php echo number_format($usd['paid'], 2); ?> $</span></div>
                <div class="row total"><span class="k">پاتې:</span><span class="v"><?php echo number_format($usd['unpaid'], 2); ?> $</span></div>
            </div>
        </div>

        <!-- AFN -->
        <div class="summary-card afn">
            <div class="currency-label"><i class="fas fa-money-bill-wave"></i> افغاني (AFN)</div>
            <div class="rows">
                <div class="row"><span class="k">ټول فاکتورونه:</span><span class="v"><?php echo number_format($afn['invoiced'], 2); ?> ؋</span></div>
                <div class="row"><span class="k">تادیه شوي:</span><span class="v"><?php echo number_format($afn['paid'], 2); ?> ؋</span></div>
                <div class="row total"><span class="k">پاتې:</span><span class="v"><?php echo number_format($afn['unpaid'], 2); ?> ؋</span></div>
            </div>
        </div>
    </div>

    <!-- INVOICES TABLE -->
    <div class="section-title">
        <i class="fas fa-file-invoice"></i> د فاکتورونو لیست (<?php echo count($invList); ?>)
    </div>
    <table class="bill-table">
        <thead>
            <tr>
                <th>#</th>
                <th>فاکتور شمېره</th>
                <th>میادي نېټه<br><small>(US)</small></th>
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
                                <span class="us-date">
                                    <i class="fas fa-globe"></i>
                                    <?php echo htmlspecialchars($inv['invoice_date']); ?>
                                </span>
                            </span>
                        </td>
                        <td>
                            <span class="dual-date-cell">
                                <span class="af-date">
                                    <i class="fas fa-moon"></i>
                                    <?php echo formatAfghanDateFromGregorian($inv['invoice_date']); ?>
                                </span>
                            </span>
                        </td>
                        <td>
                            <span class="badge-<?php echo $inv['currency']; ?>">
                                <?php echo $inv['currency'] === 'USD' ? 'ډالر' : 'افغاني'; ?>
                            </span>
                        </td>
                        <td class="amt-<?php echo strtolower($inv['currency']); ?>">
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

    <!-- PAYMENTS TABLE -->
    <div class="section-title">
        <i class="fas fa-hand-holding-usd"></i> د تادیاتو لیست (<?php echo count($payList); ?>)
    </div>
    <table class="bill-table">
        <thead>
            <tr>
                <th>#</th>
                <th>میادي نېټه<br><small>(US)</small></th>
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
                                <span class="us-date">
                                    <i class="fas fa-globe"></i>
                                    <?php echo htmlspecialchars($p['payment_date']); ?>
                                </span>
                            </span>
                        </td>
                        <td>
                            <span class="dual-date-cell">
                                <span class="af-date">
                                    <i class="fas fa-moon"></i>
                                    <?php echo formatAfghanDateFromGregorian($p['payment_date']); ?>
                                </span>
                            </span>
                        </td>
                        <td><?php echo !empty($p['hawala_number']) ? htmlspecialchars($p['hawala_number']) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($p['invoice_number'] ?? '—'); ?></td>
                        <td>
                            <span class="badge-<?php echo $p['currency']; ?>">
                                <?php echo $p['currency'] === 'USD' ? 'ډالر' : 'افغاني'; ?>
                            </span>
                        </td>
                        <td class="amt-<?php echo strtolower($p['currency']); ?>">
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

    <!-- FOOTER -->
    <div class="bill-footer">
        <div class="thanks">
            <i class="fas fa-heart"></i> له تاسو سره د کار کولو مننه!
        </div>
        <div>
            <i class="fas fa-store"></i> قرطاسیه — د دفترونو توکي
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
</script>

</body>
</html>