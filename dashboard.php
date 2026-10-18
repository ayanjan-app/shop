<?php
session_start();
require_once 'db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['username'] ?? 'User';

// =========================
// PERIOD FILTER
// =========================
$period = $_GET['period'] ?? 'all'; // today | week | month | all

$dateFrom = null;
$dateTo = date('Y-m-d');

switch ($period) {
    case 'today':
        $dateFrom = date('Y-m-d');
        break;
    case 'week':
        $dateFrom = date('Y-m-d', strtotime('monday this week'));
        break;
    case 'month':
        $dateFrom = date('Y-m-01');
        break;
    case 'all':
    default:
        $dateFrom = null;
        break;
}

// Helper: build WHERE clauses
function dateWhere($field, $from, $to, $prefix = '') {
    $w = [];
    if ($from) $w[] = "$field >= '$from'";
    if ($to)   $w[] = "$field <= '$to'";
    return empty($w) ? '' : ($prefix . implode(' AND ', $w));
}

// =========================
// SALES TOTALS (Invoices)
// =========================
$salesWhere = dateWhere('invoice_date', $dateFrom, $dateTo);
$salesSql = "SELECT currency, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total 
             FROM sales " . ($salesWhere ? "WHERE $salesWhere" : "") . "
             GROUP BY currency";
$salesRes = $conn->query($salesSql);
$salesUSD = 0; $salesAFN = 0; $salesCount = 0;
while ($r = $salesRes->fetch_assoc()) {
    if ($r['currency'] === 'USD') $salesUSD = $r['total'];
    if ($r['currency'] === 'AFN') $salesAFN = $r['total'];
    $salesCount += $r['cnt'];
}

// =========================
// PAYMENTS TOTALS (Received)
// =========================
$payWhere = dateWhere('payment_date', $dateFrom, $dateTo);
$paySql = "SELECT currency, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total 
           FROM client_payments " . ($payWhere ? "WHERE $payWhere" : "") . "
           GROUP BY currency";
$payRes = $conn->query($paySql);
$paidUSD = 0; $paidAFN = 0; $payCount = 0;
while ($r = $payRes->fetch_assoc()) {
    if ($r['currency'] === 'USD') $paidUSD = $r['total'];
    if ($r['currency'] === 'AFN') $paidAFN = $r['total'];
    $payCount += $r['cnt'];
}

// =========================
// UNPAID (All-time balance across all clients)
// =========================
$totalInvSql = "SELECT currency, COALESCE(SUM(total_amount),0) AS total FROM sales GROUP BY currency";
$totalInvRes = $conn->query($totalInvSql);
$allInvUSD = 0; $allInvAFN = 0;
while ($r = $totalInvRes->fetch_assoc()) {
    if ($r['currency'] === 'USD') $allInvUSD = $r['total'];
    if ($r['currency'] === 'AFN') $allInvAFN = $r['total'];
}

$totalPaySql = "SELECT currency, COALESCE(SUM(amount),0) AS total FROM client_payments GROUP BY currency";
$totalPayRes = $conn->query($totalPaySql);
$allPayUSD = 0; $allPayAFN = 0;
while ($r = $totalPayRes->fetch_assoc()) {
    if ($r['currency'] === 'USD') $allPayUSD = $r['total'];
    if ($r['currency'] === 'AFN') $allPayAFN = $r['total'];
}

$unpaidUSD = $allInvUSD - $allPayUSD;
$unpaidAFN = $allInvAFN - $allPayAFN;

// =========================
// EXPENSES TOTALS
// =========================
$expWhere = dateWhere('expense_date', $dateFrom, $dateTo);
$expSql = "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM expenses " . ($expWhere ? "WHERE $expWhere" : "");
$expRes = $conn->query($expSql);
$expRow = $expRes->fetch_assoc();
$expenseTotal = $expRow['total'] ?? 0;
$expenseCount = $expRow['cnt'] ?? 0;

// =========================
// SARAFE TOTALS
// =========================
$sarafeWhere = dateWhere('date', $dateFrom, $dateTo);
$sarafeSql = "SELECT money_type, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total 
              FROM sarafe " . ($sarafeWhere ? "WHERE $sarafeWhere" : "") . "
              GROUP BY money_type";
$sarafeRes = $conn->query($sarafeSql);
$sarafeTypes = [];
$sarafeTotal = 0;
$sarafeCount = 0;
while ($r = $sarafeRes->fetch_assoc()) {
    $sarafeTypes[$r['money_type']] = $r['total'];
    $sarafeTotal += $r['total'];
    $sarafeCount += $r['cnt'];
}

// =========================
// TOP CLIENTS BY UNPAID
// =========================
$topSql = "
    SELECT 
        c.id,
        c.full_name,
        COALESCE(inv_usd.total, 0) AS usd_inv,
        COALESCE(pay_usd.total, 0) AS usd_paid,
        COALESCE(inv_afn.total, 0) AS afn_inv,
        COALESCE(pay_afn.total, 0) AS afn_paid
    FROM clients c
    LEFT JOIN (SELECT client_id, SUM(total_amount) AS total FROM sales WHERE currency='USD' GROUP BY client_id) inv_usd ON c.id = inv_usd.client_id
    LEFT JOIN (SELECT client_id, SUM(amount) AS total FROM client_payments WHERE currency='USD' GROUP BY client_id) pay_usd ON c.id = pay_usd.client_id
    LEFT JOIN (SELECT client_id, SUM(total_amount) AS total FROM sales WHERE currency='AFN' GROUP BY client_id) inv_afn ON c.id = inv_afn.client_id
    LEFT JOIN (SELECT client_id, SUM(amount) AS total FROM client_payments WHERE currency='AFN' GROUP BY client_id) pay_afn ON c.id = pay_afn.client_id
    ORDER BY c.full_name ASC
";
$topRes = $conn->query($topSql);
$topClients = [];
while ($r = $topRes->fetch_assoc()) {
    $r['usd_unpaid'] = $r['usd_inv'] - $r['usd_paid'];
    $r['afn_unpaid'] = $r['afn_inv'] - $r['afn_paid'];
    $r['total_unpaid'] = $r['usd_unpaid'] + $r['afn_unpaid'];
    $topClients[] = $r;
}
usort($topClients, function($a, $b) {
    return $b['total_unpaid'] <=> $a['total_unpaid'];
});
$topClients = array_slice($topClients, 0, 5);

// =========================
// RECENT PAYMENTS (last 5)
// =========================
$recentPaySql = "
    SELECT p.*, c.full_name 
    FROM client_payments p
    LEFT JOIN clients c ON p.client_id = c.id
    ORDER BY p.id DESC
    LIMIT 5
";
$recentPayRes = $conn->query($recentPaySql);
$recentPayments = [];
while ($r = $recentPayRes->fetch_assoc()) {
    $recentPayments[] = $r;
}

// =========================
// RECENT SALES (last 5)
// =========================
$recentSalesSql = "
    SELECT s.*, c.full_name 
    FROM sales s
    LEFT JOIN clients c ON s.client_id = c.id
    ORDER BY s.id DESC
    LIMIT 5
";
$recentSalesRes = $conn->query($recentSalesSql);
$recentSales = [];
while ($r = $recentSalesRes->fetch_assoc()) {
    $recentSales[] = $r;
}

// =========================
// PERIOD LABEL
// =========================
$periodLabels = [
    'today' => 'نن ورځ',
    'week'  => 'دا اونۍ',
    'month' => 'دا میاشت',
    'all'   => 'ټول وختونه'
];
$periodLabel = $periodLabels[$period];
?>
<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ډشبورډ · </title>
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
        .welcome-header .welcome-info .user-chip {
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

        /* ===== PERIOD FILTER ===== */
        .period-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            background: var(--card-bg);
            padding: 12px 16px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            align-items: center;
        }
        .period-filter .filter-label {
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: 10px;
        }
        .period-filter .filter-label i { color: var(--accent-color); }
        .period-filter a {
            padding: 9px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-muted);
            background: #f1f5f9;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .period-filter a:hover {
            background: #e2e8f0;
            color: var(--primary-color);
            transform: translateY(-2px);
        }
        .period-filter a.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(30,60,114,0.3);
        }

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
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
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

        /* Currency variant cards */
        .stat-card.usd { --card-accent: #16a34a; }
        .stat-card.afn { --card-accent: #dc2626; }
        .stat-card.paid-usd { --card-accent: #22c55e; }
        .stat-card.paid-afn { --card-accent: #f87171; }
        .stat-card.unpaid-usd { --card-accent: #f59e0b; }
        .stat-card.unpaid-afn { --card-accent: #8b5cf6; }
        .stat-card.expense { --card-accent: #ef4444; }
        .stat-card.sarafe { --card-accent: #0ea5e9; }
        .stat-card.count { --card-accent: var(--accent-color); }

        /* ===== SECTIONS ===== */
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
        }
        .section-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--accent-color);
        }
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

        /* ===== MINI LIST ===== */
        .mini-list { list-style: none; padding: 0; margin: 0; }
        .mini-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 11px 0;
            border-bottom: 1px solid #f1f5f9;
            gap: 12px;
        }
        .mini-list li:last-child { border-bottom: none; }
        .mini-list .mini-name {
            font-weight: 600;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .mini-list .mini-name .mini-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--accent-color);
            flex-shrink: 0;
        }
        .mini-list .mini-meta {
            font-size: 0.78rem;
            color: var(--text-muted);
            display: block;
        }
        .mini-list .mini-value {
            font-weight: 700;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        .mini-list .mini-value.usd { color: #16a34a; }
        .mini-list .mini-value.afn { color: #dc2626; }
        .mini-list .mini-value.danger { color: #dc2626; }

        .mini-empty {
            text-align: center;
            padding: 25px 15px;
            color: var(--text-muted);
            font-size: 0.88rem;
        }
        .mini-empty i {
            display: block;
            font-size: 1.8rem;
            color: #cbd5e1;
            margin-bottom: 8px;
        }

        /* ===== SUMMARY BAR (top clients) ===== */
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #f1f5f9;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 4px;
        }
        .progress-bar .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-color), var(--primary-color));
            border-radius: 3px;
            transition: width 0.5s ease;
        }

        /* ===== SARAFE BREAKDOWN ===== */
        .sarafe-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }
        .sarafe-item {
            background: #f8fafc;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            border: 2px solid #e2e8f0;
        }
        .sarafe-item .sarafe-type {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 6px;
        }
        .sarafe-item .sarafe-amount {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--primary-color);
        }

        /* ===== TABLE ===== */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
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
            vertical-align: middle;
        }
        tbody tr:hover { background: #f8fafc; }
        tbody tr:last-child td { border-bottom: none; }

        .amount-usd { color: #16a34a; font-weight: 700; }
        .amount-afn { color: #dc2626; font-weight: 700; }
        .amount-unpaid { color: #d97706; font-weight: 800; }
        .amount-zero { color: #16a34a; font-weight: 600; }
        .client-name-cell { font-weight: 700; color: var(--primary-color); }

        @media (max-width: 600px) {
            .welcome-header .welcome-text h1 { font-size: 1.25rem; }
            .stat-card .stat-value { font-size: 1.3rem; }
            .section-card { padding: 18px; }
            .period-filter a { padding: 7px 14px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <!-- ===== WELCOME HEADER ===== -->
    <div class="welcome-header">
        <div class="welcome-text">
            <h1><i class="fas fa-chart-line"></i> ډشبورډ</h1>
            <p>د  د کاروبار لنډیز — <?php echo htmlspecialchars($periodLabel); ?></p>
        </div>
        <div class="welcome-info">
            <div class="user-chip">
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($current_user); ?>
            </div>
            <div class="date-chip">
                <i class="fas fa-calendar-day"></i>
                <?php echo date('Y-m-d'); ?>
            </div>
        </div>
    </div>

    <!-- ===== PERIOD FILTER ===== -->
    <div class="period-filter">
        <span class="filter-label"><i class="fas fa-filter"></i> موده:</span>
        <a href="?period=today" class="<?php echo $period === 'today' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-day"></i> نن ورځ
        </a>
        <a href="?period=week" class="<?php echo $period === 'week' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-week"></i> دا اونۍ
        </a>
        <a href="?period=month" class="<?php echo $period === 'month' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i> دا میاشت
        </a>
        <a href="?period=all" class="<?php echo $period === 'all' ? 'active' : ''; ?>">
            <i class="fas fa-infinity"></i> ټول وختونه
        </a>
    </div>

    <!-- ===== MAIN STATS ===== -->
    <div class="stats-grid">

        <!-- Sales USD -->
        <div class="stat-card usd">
            <div class="stat-top">
                <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                <span class="stat-badge">فاکتورونه</span>
            </div>
            <div class="stat-label">پلورل شوي (ډالر)</div>
            <div class="stat-value"><?php echo number_format($salesUSD, 2); ?> $</div>
            <div class="stat-sub">
                <i class="fas fa-receipt"></i>
                <?php echo number_format($salesCount); ?> فاکتورونه
            </div>
        </div>

        <!-- Sales AFN -->
        <div class="stat-card afn">
            <div class="stat-top">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <span class="stat-badge">فاکتورونه</span>
            </div>
            <div class="stat-label">پلورل شوي (افغاني)</div>
            <div class="stat-value"><?php echo number_format($salesAFN, 2); ?> ؋</div>
            <div class="stat-sub">
                <i class="fas fa-receipt"></i>
                <?php echo number_format($salesCount); ?> فاکتورونه
            </div>
        </div>

        <!-- Payments USD -->
        <div class="stat-card paid-usd">
            <div class="stat-top">
                <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                <span class="stat-badge">ترلاسه شوي</span>
            </div>
            <div class="stat-label">ترلاسه شوي (ډالر)</div>
            <div class="stat-value"><?php echo number_format($paidUSD, 2); ?> $</div>
            <div class="stat-sub">
                <i class="fas fa-check-circle"></i>
                <?php echo number_format($payCount); ?> تادیات
            </div>
        </div>

        <!-- Payments AFN -->
        <div class="stat-card paid-afn">
            <div class="stat-top">
                <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                <span class="stat-badge">ترلاسه شوي</span>
            </div>
            <div class="stat-label">ترلاسه شوي (افغاني)</div>
            <div class="stat-value"><?php echo number_format($paidAFN, 2); ?> ؋</div>
            <div class="stat-sub">
                <i class="fas fa-check-circle"></i>
                <?php echo number_format($payCount); ?> تادیات
            </div>
        </div>

        <!-- Unpaid USD (all-time) -->
        <div class="stat-card unpaid-usd">
            <div class="stat-top">
                <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                <span class="stat-badge">ټول وخت</span>
            </div>
            <div class="stat-label">پاتې (ډالر)</div>
            <div class="stat-value"><?php echo number_format($unpaidUSD, 2); ?> $</div>
            <div class="stat-sub">له ټولو پیرودونکو</div>
        </div>

        <!-- Unpaid AFN (all-time) -->
        <div class="stat-card unpaid-afn">
            <div class="stat-top">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <span class="stat-badge">ټول وخت</span>
            </div>
            <div class="stat-label">پاتې (افغاني)</div>
            <div class="stat-value"><?php echo number_format($unpaidAFN, 2); ?> ؋</div>
            <div class="stat-sub">له ټولو پیرودونکو</div>
        </div>

        <!-- Expenses -->
        <div class="stat-card expense">
            <div class="stat-top">
                <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                <span class="stat-badge">لګښتونه</span>
            </div>
            <div class="stat-label">ټول لګښتونه</div>
            <div class="stat-value"><?php echo number_format($expenseTotal, 2); ?></div>
            <div class="stat-sub">
                <i class="fas fa-receipt"></i>
                <?php echo number_format($expenseCount); ?> ریکارډونه
            </div>
        </div>

        <!-- Sarafe -->
        <div class="stat-card sarafe">
            <div class="stat-top">
                <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
                <span class="stat-badge">صرافي</span>
            </div>
            <div class="stat-label">صرافي پیسې</div>
            <div class="stat-value"><?php echo number_format($sarafeTotal, 2); ?></div>
            <div class="stat-sub">
                <i class="fas fa-list"></i>
                <?php echo number_format($sarafeCount); ?> ریکارډونه
            </div>
        </div>

    </div>

    <!-- ===== SECTIONS ===== -->
    <div class="section-grid">

        <!-- Top Clients by Unpaid -->
        <div class="section-card">
            <div class="section-header">
                <div class="title"><i class="fas fa-user-tag"></i> تر ټولو ډېر پاتې پیرودونکي</div>
                <a href="client_payments.php" class="view-link">ټول وګوره</a>
            </div>

            <?php if (count($topClients) > 0 && $topClients[0]['total_unpaid'] > 0): ?>
                <ul class="mini-list">
                    <?php 
                    $maxUnpaid = max(array_column($topClients, 'total_unpaid')) ?: 1;
                    foreach ($topClients as $tc): 
                        if ($tc['total_unpaid'] <= 0.01) continue;
                        $percent = ($tc['total_unpaid'] / $maxUnpaid) * 100;
                    ?>
                        <li>
                            <div style="flex:1; min-width:0;">
                                <div class="mini-name">
                                    <span class="mini-dot"></span>
                                    <?php echo htmlspecialchars($tc['full_name']); ?>
                                </div>
                                <div class="mini-meta">
                                    <?php if ($tc['usd_unpaid'] > 0.01): ?>
                                        ډالر: <?php echo number_format($tc['usd_unpaid'], 2); ?> $
                                    <?php endif; ?>
                                    <?php if ($tc['afn_unpaid'] > 0.01): ?>
                                        <?php if ($tc['usd_unpaid'] > 0.01) echo ' | '; ?>
                                        افغاني: <?php echo number_format($tc['afn_unpaid'], 2); ?> ؋
                                    <?php endif; ?>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $percent; ?>%;"></div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="mini-empty">
                    <i class="fas fa-check-circle"></i>
                    ټول پیرودونکي پوره دي — هیڅ پاتې نشته!
                </div>
            <?php endif; ?>
        </div>

        <!-- Sarafe Breakdown -->
        <div class="section-card">
            <div class="section-header">
                <div class="title"><i class="fas fa-exchange-alt"></i> د صرافي تفصیل</div>
                <a href="sarafe.php" class="view-link">ټول وګوره</a>
            </div>

            <?php if ($sarafeCount > 0): ?>
                <div class="sarafe-breakdown">
                    <?php foreach ($sarafeTypes as $type => $amt): ?>
                        <div class="sarafe-item">
                            <div class="sarafe-type"><?php echo htmlspecialchars($type); ?></div>
                            <div class="sarafe-amount"><?php echo number_format($amt, 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="mini-empty">
                    <i class="fas fa-inbox"></i>
                    د دې مودې لپاره هیڅ صرافي ریکارډ نشته.
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ===== RECENT ACTIVITY ===== -->
    <div class="section-grid">

        <!-- Recent Payments -->
        <div class="section-card">
            <div class="section-header">
                <div class="title"><i class="fas fa-history"></i> وروستي تادیات</div>
                <a href="client_payments.php" class="view-link">ټول وګوره</a>
            </div>

            <?php if (count($recentPayments) > 0): ?>
                <ul class="mini-list">
                    <?php foreach ($recentPayments as $p): ?>
                        <li>
                            <div style="flex:1; min-width:0;">
                                <div class="mini-name">
                                    <span class="mini-dot"></span>
                                    <?php echo htmlspecialchars($p['full_name'] ?? '—'); ?>
                                </div>
                                <div class="mini-meta">
                                    <?php echo htmlspecialchars($p['payment_date']); ?>
                                    <?php if (!empty($p['hawala_number'])): ?>
                                        · حواله: <?php echo htmlspecialchars($p['hawala_number']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mini-value <?php echo strtolower($p['currency']); ?>">
                                <?php echo number_format($p['amount'], 2); ?> <?php echo $p['currency']; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="mini-empty">
                    <i class="fas fa-inbox"></i>
                    هیڅ تادیه نشته.
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Sales -->
        <div class="section-card">
            <div class="section-header">
                <div class="title"><i class="fas fa-file-invoice"></i> وروستي فاکتورونه</div>
                <a href="sales.php" class="view-link">ټول وګوره</a>
            </div>

            <?php if (count($recentSales) > 0): ?>
                <ul class="mini-list">
                    <?php foreach ($recentSales as $s): ?>
                        <li>
                            <div style="flex:1; min-width:0;">
                                <div class="mini-name">
                                    <span class="mini-dot"></span>
                                    <?php echo htmlspecialchars($s['full_name'] ?? '—'); ?>
                                </div>
                                <div class="mini-meta">
                                    فاکتور: <?php echo htmlspecialchars($s['invoice_number']); ?>
                                    · <?php echo htmlspecialchars($s['invoice_date']); ?>
                                </div>
                            </div>
                            <div class="mini-value <?php echo strtolower($s['currency']); ?>">
                                <?php echo number_format($s['total_amount'], 2); ?> <?php echo $s['currency']; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="mini-empty">
                    <i class="fas fa-inbox"></i>
                    هیڅ فاکتور نشته.
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ===== FULL CLIENT SUMMARY TABLE ===== -->
    <div class="section-card">
        <div class="section-header">
            <div class="title"><i class="fas fa-table"></i> د پیرودونکو بشپړ لنډیز</div>
            <a href="client_payments.php" class="view-link">تفصیل وګوره</a>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>پیرودونکی</th>
                        <th>ډالر فاکتورونه</th>
                        <th>ډالر تادیه</th>
                        <th>پاتې ډالر</th>
                        <th>افغاني فاکتورونه</th>
                        <th>افغاني تادیه</th>
                        <th>پاتې افغاني</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($topClients) > 0): ?>
                        <?php foreach ($topClients as $tc): ?>
                            <tr>
                                <td class="client-name-cell"><?php echo htmlspecialchars($tc['full_name']); ?></td>
                                <td class="amount-usd"><?php echo number_format($tc['usd_inv'], 2); ?> $</td>
                                <td class="amount-usd"><?php echo number_format($tc['usd_paid'], 2); ?> $</td>
                                <td class="<?php echo ($tc['usd_unpaid'] <= 0.01) ? 'amount-zero' : 'amount-unpaid'; ?>">
                                    <?php echo number_format($tc['usd_unpaid'], 2); ?> $
                                </td>
                                <td class="amount-afn"><?php echo number_format($tc['afn_inv'], 2); ?> ؋</td>
                                <td class="amount-afn"><?php echo number_format($tc['afn_paid'], 2); ?> ؋</td>
                                <td class="<?php echo ($tc['afn_unpaid'] <= 0.01) ? 'amount-zero' : 'amount-unpaid'; ?>">
                                    <?php echo number_format($tc['afn_unpaid'], 2); ?> ؋
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding: 25px; color: #64748b;">
                                هیڅ پیرودونکی ونه موندل شو.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>