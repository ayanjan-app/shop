<?php
session_start();
require_once 'db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Retrieve flash messages from session (PRG pattern)
if (isset($_SESSION['flash_success'])) {
    $success_msg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $error_msg = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// =========================
// DELETE SARAFE (GET → redirect)
// =========================
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM sarafe WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $_SESSION['flash_success'] = "ریکارډ په بریالیتوب سره ړنګ شو!";
    } else {
        $_SESSION['flash_error'] = "تېروتنه: " . $stmt->error;
    }
    $stmt->close();
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// =========================
// ADD SARAFE (POST → redirect)
// =========================
if (isset($_POST['add_sarafe'])) {
    $item_name  = trim($_POST['item_name'] ?? '');
    $amount     = trim($_POST['amount'] ?? '');
    $date       = trim($_POST['date'] ?? '');
    $money_type = trim($_POST['money_type'] ?? '');
    $note       = trim($_POST['note'] ?? '');
    $hasError   = false;

    // Allowed money types
    $allowed_types = ['چیني', 'افغاني', 'ډالر'];

    if (empty($item_name)) {
        $_SESSION['flash_error'] = "د توکي نوم اړین دی";
        $hasError = true;
    } elseif ($amount === '') {
        $_SESSION['flash_error'] = "اندازه اړینه ده";
        $hasError = true;
    } elseif (empty($date)) {
        $_SESSION['flash_error'] = "نېټه اړینه ده";
        $hasError = true;
    } elseif (empty($money_type)) {
        $_SESSION['flash_error'] = "د پیسو ډول اړین دی";
        $hasError = true;
    } elseif (!in_array($money_type, $allowed_types)) {
        $_SESSION['flash_error'] = "د پیسو ډول سم نه دی";
        $hasError = true;
    }

    if (!$hasError) {
        $stmt = $conn->prepare("INSERT INTO sarafe (name, amount, date, money_type, note) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdsss", $item_name, $amount, $date, $money_type, $note);

        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "ریکارډ په بریالیتوب سره اضافه شو!";
        } else {
            $_SESSION['flash_error'] = "تېروتنه: " . $stmt->error;
        }
        $stmt->close();
    }

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// =========================
// FILTER
// =========================
$filter = $_GET['filter'] ?? 'all';
$where = "";

if ($filter == "today") {
    $where = "WHERE date = CURDATE()";
} elseif ($filter == "yesterday") {
    $where = "WHERE date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($filter == "week") {
    $where = "WHERE YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter == "month") {
    $where = "WHERE YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())";
} elseif ($filter == "custom") {
    $from_date = $_GET['from_date'] ?? '';
    $to_date = $_GET['to_date'] ?? '';
    if (!empty($from_date) && !empty($to_date)) {
        $where = "WHERE date BETWEEN '$from_date' AND '$to_date'";
    }
}

// Search
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$filter_type = isset($_GET['filter_type']) ? trim($_GET['filter_type']) : '';

$extraWhere = [];
if ($search_name !== '') {
    $extraWhere[] = "name LIKE '%" . $conn->real_escape_string($search_name) . "%'";
}
if ($filter_type !== '') {
    $extraWhere[] = "money_type = '" . $conn->real_escape_string($filter_type) . "'";
}

if (!empty($extraWhere)) {
    if ($where === "") {
        $where = "WHERE " . implode(" AND ", $extraWhere);
    } else {
        $where .= " AND " . implode(" AND ", $extraWhere);
    }
}

// =========================
// GET RECORDS
// =========================
$sql = "SELECT * FROM sarafe $where ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
$record_count = mysqli_num_rows($result);

// =========================
// CALCULATE TOTAL
// =========================
$total_sql = "SELECT SUM(amount) AS total FROM sarafe $where";
$total_result = mysqli_query($conn, $total_sql);
$total_data = mysqli_fetch_assoc($total_result);
$total = $total_data['total'] ?? 0;

// =========================
// STATS
// =========================
$count_sql = "SELECT COUNT(*) AS cnt FROM sarafe $where";
$count_result = mysqli_query($conn, $count_sql);
$count_data = mysqli_fetch_assoc($count_result);
$stats_count = $count_data['cnt'] ?? 0;

$today_sql = "SELECT SUM(amount) AS today_total FROM sarafe WHERE date = CURDATE()";
$today_result = mysqli_query($conn, $today_sql);
$today_data = mysqli_fetch_assoc($today_result);
$today_total = $today_data['today_total'] ?? 0;

// Today's date for default
$today_date = date('Y-m-d');

// Fixed money types for dropdown
$money_types_list = ['چیني', 'افغاني', 'ډالر'];
?>
<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>صرافي · قرطاسیه</title>
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
        .stat-icon.total { background: var(--primary-color); }
        .stat-icon.count { background: var(--accent-color); }
        .stat-icon.today { background: var(--success-color); }
        .stat-info h3 { margin: 0; font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }
        .stat-info p { margin: 6px 0 0; font-size: 1.5rem; font-weight: 700; color: var(--primary-color); }
        .stat-card:nth-child(1) { border-top: 4px solid var(--primary-color); }
        .stat-card:nth-child(2) { border-top: 4px solid var(--accent-color); }
        .stat-card:nth-child(3) { border-top: 4px solid var(--success-color); }

        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 12px; }
        .alert-success { background-color: #dcfce7; color: #166534; border-right: 4px solid var(--success-color); }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border-right: 4px solid var(--danger-color); }

        .content-grid { display: grid; grid-template-columns: 380px 1fr; gap: 25px; align-items: start; }
        @media (max-width: 1100px) { .content-grid { grid-template-columns: 1fr; } }

        .card { background: var(--card-bg); border-radius: var(--border-radius); padding: 25px; box-shadow: var(--shadow); position: relative; overflow: hidden; border: 1px solid #e2e8f0; }
        .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--accent-color); }
        .card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f1f5f9; }
        .card-header i { color: var(--accent-color); font-size: 1.3rem; }
        .card-header h2 { margin: 0; font-size: 1.2rem; color: var(--primary-color); font-weight: 700; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 0.85rem; font-weight: 600; color: var(--primary-color); }
        .form-group label i { color: var(--accent-color); margin-left: 6px; width: 16px; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 10px;
            font-size: 0.95rem; transition: var(--transition); font-family: inherit;
            background: var(--card-bg); color: var(--text-color); text-align: right;
            min-height: 48px;
        }
        /* ✅ Fix: responsive select dropdown */
        .form-group select,
        .search-row select,
        .custom-filter select {
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            appearance: none !important;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%231e3c72' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>") !important;
            background-repeat: no-repeat !important;
            background-position: left 12px center !important;
            background-size: 16px !important;
            background-color: #ffffff !important;
            padding: 12px 14px 12px 44px !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
            cursor: pointer;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
            line-height: 1.4;
            display: block;
            direction: rtl;
            font-family: 'Noto Naskh Arabic', 'Segoe UI', Tahoma, sans-serif !important;
        }

        .form-group textarea { resize: vertical; min-height: 70px; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(30,60,114,0.1);
        }
        .form-group input[type="date"] { direction: ltr; text-align: right; }

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

        .custom-filter { background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 18px; margin-bottom: 20px; }
        .custom-filter .custom-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .custom-filter .form-group { flex: 1; min-width: 140px; margin-bottom: 0; }
        .custom-filter .btn { width: auto; padding: 12px 24px; }

        .search-bar { background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 18px; margin-bottom: 20px; }
        .search-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .search-row .form-group { flex: 1; min-width: 160px; margin-bottom: 0; }
        .search-row .btn { width: auto; padding: 12px 24px; }

        .table-wrapper { overflow-x: auto; border-radius: 12px; border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
        thead { background: var(--primary-color); }
        th { padding: 15px; text-align: right; color: white; font-weight: 600; font-size: 0.85rem; }
        td { padding: 14px 15px; border-bottom: 1px solid #e2e8f0; color: var(--text-color); text-align: right; }
        tbody tr:hover { background: #f8fafc; }
        tbody tr:last-child td { border-bottom: none; }

        .amount-cell { font-weight: 700; color: var(--primary-color); }
        .id-badge { display: inline-block; background: #f1f5f9; color: var(--text-muted); padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .type-badge { display: inline-block; background: #eff6ff; color: var(--primary-color); padding: 3px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px; display: block; }

        .btn-delete { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 8px; background: var(--danger-color); color: white; text-decoration: none; transition: var(--transition); font-size: 0.85rem; }
        .btn-delete:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(239,68,68,0.4); }

        .total-footer { margin-top: 20px; padding: 18px 24px; background: var(--primary-color); border-radius: var(--border-radius); display: flex; align-items: center; justify-content: space-between; color: white; box-shadow: 0 6px 20px rgba(30,60,114,0.2); }
        .total-footer .total-label { display: flex; align-items: center; gap: 10px; font-size: 1rem; font-weight: 600; }
        .total-footer .total-label i { color: var(--accent-color); font-size: 1.3rem; }
        .total-footer .total-value { font-size: 1.6rem; font-weight: 800; color: var(--accent-color); }
        @media (max-width: 768px) {
            .page-header h1 { font-size: 1.4rem; }
            .stat-info p { font-size: 1.2rem; }
            .card { padding: 18px; }

            .custom-filter .custom-row, 
            .search-row { 
                flex-direction: column; 
                align-items: stretch; 
                gap: 12px;
            }
            .custom-filter .btn, 
            .search-row .btn { 
                width: 100%; 
            }

            /* ✅ Mobile select full width fix */
            .form-group select,
            .search-row select,
            .custom-filter select {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                box-sizing: border-box !important;
                font-size: 16px !important;
                padding: 12px 14px 12px 44px !important;
                display: block !important;
            }

            /* ✅ Search row items full width */
            .search-row .form-group,
            .custom-filter .form-group {
                width: 100% !important;
                min-width: 0 !important;
                flex: 1 1 100% !important;
            }

            /* ✅ Ensure wrapper doesn't overflow */
            .form-group {
                width: 100%;
                max-width: 100%;
            }
        }
                /* ✅ Global select fix for very small screens */
        @media (max-width: 480px) {
            .form-group select,
            .search-row select {
                font-size: 16px !important;
                padding: 12px 12px 12px 42px !important;
                background-position: left 10px center !important;
                background-size: 14px !important;
            }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-money-bill-exchange"></i> صرافي</h1>
            <div class="subtitle">د پیسو راکړې ورکړې مدیریت کړئ</div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total"><i class="fas fa-coins"></i></div>
            <div class="stat-info">
                <h3>ټوله اندازه</h3>
                <p><?php echo number_format($total, 2); ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon count"><i class="fas fa-receipt"></i></div>
            <div class="stat-info">
                <h3>ریکارډونه</h3>
                <p><?php echo $stats_count; ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon today"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-info">
                <h3>نن</h3>
                <p><?php echo number_format($today_total, 2); ?></p>
            </div>
        </div>
    </div>

    <div class="content-grid">

        <!-- Add Sarafe Form -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus-circle"></i>
                <h2>نوی ریکارډ اضافه کړئ</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="add_sarafe" value="1">

                <div class="form-group">
                    <label><i class="fas fa-box"></i> د توکي نوم *</label>
                    <input type="text" name="item_name" placeholder="د توکي نوم ولیکئ" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-dollar-sign"></i> اندازه *</label>
                    <input type="number" name="amount" step="0.01" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> نېټه *</label>
                    <input type="date" name="date" value="<?php echo $today_date; ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> د پیسو ډول *</label>
                    <select name="money_type" required>
                        <option value="">-- د پیسو ډول غوره کړئ --</option>
                        <option value="چیني">چیني</option>
                        <option value="افغاني">افغاني</option>
                        <option value="ډالر">ډالر</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> یادونه</label>
                    <textarea name="note" placeholder="اضافي یادونه"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> ریکارډ اضافه کړئ
                </button>
            </form>
        </div>

        <!-- Records List -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i>
                <h2>د صرافي ریکارډونه (<?php echo $record_count; ?>)</h2>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="sarafe.php?filter=all" class="<?php echo $filter == 'all' ? 'active' : ''; ?>"><i class="fas fa-list"></i> ټول</a>
                <a href="sarafe.php?filter=today" class="<?php echo $filter == 'today' ? 'active' : ''; ?>"><i class="fas fa-calendar-day"></i> نن</a>
                <a href="sarafe.php?filter=yesterday" class="<?php echo $filter == 'yesterday' ? 'active' : ''; ?>"><i class="fas fa-calendar-minus"></i> پرون</a>
                <a href="sarafe.php?filter=week" class="<?php echo $filter == 'week' ? 'active' : ''; ?>"><i class="fas fa-calendar-week"></i> دې اونۍ</a>
                <a href="sarafe.php?filter=month" class="<?php echo $filter == 'month' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> دې میاشت</a>
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

            <!-- Search -->
            <div class="search-bar">
                <form method="GET">
                    <div class="search-row">
                        <div class="form-group">
                            <label><i class="fas fa-search"></i> د توکي نوم پلټنه</label>
                            <input type="text" name="search_name" placeholder="د توکي نوم ولیکئ" value="<?php echo htmlspecialchars($search_name); ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-money-bill-wave"></i> د پیسو ډول</label>
                            <select name="filter_type">
                                <option value="">ټول ډولونه</option>
                                <option value="چیني" <?php echo ($filter_type === 'چیني') ? 'selected' : ''; ?>>چیني</option>
                                <option value="افغاني" <?php echo ($filter_type === 'افغاني') ? 'selected' : ''; ?>>افغاني</option>
                                <option value="ډالر" <?php echo ($filter_type === 'ډالر') ? 'selected' : ''; ?>>ډالر</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-search"></i> پلټنه
                        </button>
                        <?php if ($search_name !== '' || $filter_type !== ''): ?>
                            <a href="sarafe.php" class="btn btn-reset">
                                <i class="fas fa-times"></i> پاکول
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>د توکي نوم</th>
                            <th>اندازه</th>
                            <th>نېټه</th>
                            <th>د پیسو ډول</th>
                            <th>یادونه</th>
                            <th>کړنې</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><span class="id-badge">#<?php echo $row['id']; ?></span></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td class="amount-cell"><?php echo number_format($row['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['date']); ?></td>
                                    <td><span class="type-badge"><?php echo htmlspecialchars($row['money_type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['note']); ?></td>
                                    <td>
                                        <a href="?delete_id=<?php echo $row['id']; ?>" 
                                           class="btn-delete" 
                                           title="ړنګول"
                                           onclick="return confirm('ایا تاسو ډاډه یاست چې دا ریکارډ ړنګ کړئ؟');">
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
                                        د دې فلټر لپاره هیڅ ریکارډ ونه موندل شو.
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
                    ټوله اندازه
                </div>
                <div class="total-value">
                    <?php echo number_format($total, 2); ?>
                </div>
            </div>
        </div>

    </div>

</div>

</body>
</html>