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
        $success_msg = "Expense added successfully!";
    } else {
        $error_msg = "Error: " . mysqli_error($conn);
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

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses - PharmaCare RX</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #1a2980;
            --secondary: #26d0ce;
            --accent: #00ffea;
            --dark: #0c2461;
            --success: #2ecc71;
            --danger: #ff6b6b;
            --warning: #f39c12;
            --text-dark: #2c3e50;
            --text-muted: #7f8c8d;
            --bg-light: #f4f7fb;
            --card-shadow: 0 4px 20px rgba(26, 41, 128, 0.08);
            --card-shadow-hover: 0 8px 30px rgba(26, 41, 128, 0.15);
            --border-radius: 14px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 30px 40px;
            background: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
            width: 85%;
        }

        /* ===== Page Header ===== */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 {
            font-size: 1.8rem;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header h1 i {
            color: var(--secondary);
        }

        .page-header .subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        /* ===== Stats Cards ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 22px;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 18px;
            transition: all 0.3s ease;
            border-left: 4px solid var(--secondary);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--card-shadow-hover);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
            flex-shrink: 0;
        }

        .stat-icon.total {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stat-icon.count {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }

        .stat-icon.today {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }

        .stat-info h3 {
            margin: 0;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .stat-info p {
            margin: 6px 0 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* ===== Layout: Form + Table ===== */
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
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--card-shadow);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eef2f7;
        }

        .card-header i {
            color: var(--secondary);
            font-size: 1.2rem;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.15rem;
            color: var(--primary);
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
            color: var(--text-dark);
        }

        .form-group label i {
            color: var(--secondary);
            margin-right: 5px;
            width: 16px;
        }

        .form-group input {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #e4e9f2;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.25s ease;
            font-family: inherit;
            background: #fafbfd;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--secondary);
            background: white;
            box-shadow: 0 0 0 4px rgba(38, 208, 206, 0.12);
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
            transition: all 0.25s ease;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            width: 100%;
            box-shadow: 0 4px 15px rgba(26, 41, 128, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 41, 128, 0.35);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary), var(--accent));
            color: var(--primary);
            width: 100%;
            box-shadow: 0 4px 15px rgba(38, 208, 206, 0.25);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(38, 208, 206, 0.4);
        }

        /* ===== Alerts ===== */
        .alert {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4f5e2;
            color: #1e7e4a;
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: #fde2e2;
            color: #b03030;
            border-left: 4px solid var(--danger);
        }

        /* ===== Filter Tabs ===== */
        .filter-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }

        .filter-tabs a {
            padding: 9px 16px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            background: #eef2f7;
            transition: all 0.25s ease;
            border: 2px solid transparent;
        }

        .filter-tabs a:hover {
            background: #e0e7f0;
            color: var(--primary);
            transform: translateY(-2px);
        }

        .filter-tabs a.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 4px 12px rgba(26, 41, 128, 0.25);
        }

        /* ===== Custom Date Filter ===== */
        .custom-filter {
            background: #f8fafd;
            border: 2px dashed #d8e0ec;
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
            padding: 11px 22px;
        }

        /* ===== Table ===== */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #eef2f7;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
        }

        thead {
            background: linear-gradient(135deg, var(--primary), var(--dark));
        }

        th {
            padding: 14px 16px;
            text-align: left;
            color: white;
            font-weight: 600;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 13px 16px;
            border-bottom: 1px solid #eef2f7;
            color: var(--text-dark);
        }

        tbody tr {
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: #f8fafd;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .amount-cell {
            font-weight: 700;
            color: var(--primary);
        }

        .id-badge {
            display: inline-block;
            background: #eef2f7;
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
            color: #d8e0ec;
            margin-bottom: 12px;
            display: block;
        }

        /* ===== Total Footer ===== */
        .total-footer {
            margin-top: 20px;
            padding: 18px 24px;
            background: linear-gradient(135deg, var(--primary), var(--dark));
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: white;
            box-shadow: 0 6px 20px rgba(26, 41, 128, 0.25);
        }

        .total-footer .total-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .total-footer .total-label i {
            color: var(--accent);
            font-size: 1.3rem;
        }

        .total-footer .total-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--accent);
        }

        /* ===== Responsive ===== */
        @media (max-width: 768px) {
            body {
                padding: 80px 18px 30px;
            }
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
    </style>
</head>

<body>

<?php include 'sidebar.php'; ?>

<!-- ===== Page Header ===== -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-wallet"></i> Expenses</h1>
        <div class="subtitle">Track and manage all pharmacy expenses</div>
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
<?php
$count_sql = "SELECT COUNT(*) AS cnt FROM expenses $where";
$count_result = mysqli_query($conn, $count_sql);
$count_data = mysqli_fetch_assoc($count_result);
$expense_count = $count_data['cnt'] ?? 0;

$today_sql = "SELECT SUM(amount) AS today_total FROM expenses WHERE expense_date = CURDATE()";
$today_result = mysqli_query($conn, $today_sql);
$today_data = mysqli_fetch_assoc($today_result);
$today_total = $today_data['today_total'] ?? 0;
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon total">
            <i class="fas fa-coins"></i>
        </div>
        <div class="stat-info">
            <h3>Total Expenses</h3>
            <p><?php echo number_format($total, 2); ?></p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon count">
            <i class="fas fa-receipt"></i>
        </div>
        <div class="stat-info">
            <h3>Records</h3>
            <p><?php echo $expense_count; ?></p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon today">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-info">
            <h3>Today</h3>
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
            <h2>Add New Expense</h2>
        </div>

        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-dollar-sign"></i> Amount</label>
                <input type="number" name="amount" step="0.01" placeholder="0.00" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-tag"></i> Used For</label>
                <input type="text" name="used_for" placeholder="Example: Food" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-user"></i> Person</label>
                <input type="text" name="person" placeholder="Example: Ahmad" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Date</label>
                <input type="date" name="expense_date" required>
            </div>

            <button type="submit" name="add_expense" class="btn btn-primary">
                <i class="fas fa-save"></i> Add Expense
            </button>
        </form>
    </div>

    <!-- ===== Expenses List ===== -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list"></i>
            <h2>Expense Records</h2>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="expenses.php?filter=all" class="<?php echo $filter == 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> All
            </a>
            <a href="expenses.php?filter=today" class="<?php echo $filter == 'today' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="expenses.php?filter=yesterday" class="<?php echo $filter == 'yesterday' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-minus"></i> Yesterday
            </a>
            <a href="expenses.php?filter=week" class="<?php echo $filter == 'week' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-week"></i> This Week
            </a>
            <a href="expenses.php?filter=month" class="<?php echo $filter == 'month' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> This Month
            </a>
        </div>

        <!-- Custom Date Filter -->
        <div class="custom-filter">
            <form method="GET">
                <input type="hidden" name="filter" value="custom">
                <div class="custom-row">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-plus"></i> From Date</label>
                        <input type="date" name="from_date" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar-check"></i> To Date</label>
                        <input type="date" name="to_date" required>
                    </div>
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-filter"></i> Filter
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
                        <th>Amount</th>
                        <th>Used For</th>
                        <th>Person</th>
                        <th>Date</th>
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
                                    No expenses found for this filter.
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
                Total Expenses
            </div>
            <div class="total-value">
                <?php echo number_format($total, 2); ?>
            </div>
        </div>
    </div>

</div>

</body>
</html>