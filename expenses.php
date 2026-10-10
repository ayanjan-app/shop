<?php

include "db.php";

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

                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> نېټه</label>
                    <!-- ✅ Default value set to today's date -->
                    <input type="date" name="expense_date" value="<?php echo $today_date; ?>" required>
                </div>

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
                            <th>نېټه</th>
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
                                    <td><?php echo htmlspecialchars($row['expense_date']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
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

</body>
</html>