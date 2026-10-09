<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// =====================================================
// DATE RANGE FILTER (from the bottom form)
// =====================================================

$filter_active = false;
$filter_from = '';
$filter_to = '';

if (isset($_GET['filter_range'])) {
    $filter_from = $_GET['from_date'] ?? '';
    $filter_to   = $_GET['to_date'] ?? '';

    if (!empty($filter_from) && !empty($filter_to)) {
        $filter_active = true;
    }
}

// Get current date and time periods
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$startOfWeek = date('Y-m-d', strtotime('monday this week'));
$startOfMonth = date('Y-m-d', strtotime('first day of this month'));

// =====================================================
// CUSTOM RANGE STATS (only if filter is active)
// =====================================================

$range_stats = [
    'sales_total' => 0,
    'sales_paid' => 0,
    'sales_unpaid' => 0,
    'transactions' => 0,
    'profit' => 0,
    'expenses' => 0,
    'profit_after_expenses' => 0,
    'avg_sale' => 0
];

if ($filter_active) {
    $from_safe = mysqli_real_escape_string($conn, $filter_from);
    $to_safe   = mysqli_real_escape_string($conn, $filter_to);

    // Total sales, paid, and transactions in range
    $range_sales_result = $conn->query("
        SELECT
            s.sale_id,
            s.total_amount,
            COALESCE(SUM(p.payment_amount), 0) as paid_amount
        FROM sales s
        LEFT JOIN payments p ON s.sale_id = p.sale_id
        WHERE DATE(s.sale_date) BETWEEN '$from_safe' AND '$to_safe'
        GROUP BY s.sale_id
    ");

    if ($range_sales_result) {
        while ($row = $range_sales_result->fetch_assoc()) {
            $total = floatval($row['total_amount']);
            $paid  = floatval($row['paid_amount']);
            $range_stats['sales_total'] += $total;
            $range_stats['sales_paid']  += $paid;
            $range_stats['sales_unpaid'] += ($total - $paid);
            $range_stats['transactions']++;
        }
        if ($range_stats['transactions'] > 0) {
            $range_stats['avg_sale'] = $range_stats['sales_total'] / $range_stats['transactions'];
        }
    }

    // Profit in range
    $range_profit_result = $conn->query("
        SELECT
            COALESCE(SUM((si.price_out - m.price_in - si.bonus_or_discount) * si.quantity), 0) AS range_profit
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.sale_id
        JOIN medicines m ON si.medicine_id = m.id
        WHERE DATE(s.sale_date) BETWEEN '$from_safe' AND '$to_safe'
    ");
    if ($range_profit_result) {
        $range_stats['profit'] = floatval($range_profit_result->fetch_assoc()['range_profit'] ?? 0);
    }

    // Expenses in range
    $range_expense_result = $conn->query("
        SELECT COALESCE(SUM(amount), 0) AS range_expenses
        FROM expenses
        WHERE expense_date BETWEEN '$from_safe' AND '$to_safe'
    ");
    if ($range_expense_result) {
        $range_stats['expenses'] = floatval($range_expense_result->fetch_assoc()['range_expenses'] ?? 0);
    }

    $range_stats['profit_after_expenses'] = $range_stats['profit'] - $range_stats['expenses'];
}


// =====================================================
// STANDARD DASHBOARD STATS (unchanged)
// =====================================================

// Calculate medicine inventory value
$inventory_result = $conn->query("
    SELECT
        COUNT(*) as total_medicines,
        SUM(amount) as total_units,
        SUM(price_in * amount) as total_investment,
        SUM(price_out * amount) as total_potential_value,
        SUM((price_out - price_in) * amount) as total_potential_profit
    FROM medicines
");

$inventory_stats = $inventory_result ? $inventory_result->fetch_assoc() : [
    'total_medicines' => 0,
    'total_units' => 0,
    'total_investment' => 0,
    'total_potential_value' => 0,
    'total_potential_profit' => 0
];

// Calculate unpaid amounts for different periods
$unpaid_result = $conn->query("
    SELECT
        SUM(CASE WHEN DATE(sale_date) = '$today' THEN unpaid_amount ELSE 0 END) as today_unpaid,
        SUM(CASE WHEN DATE(sale_date) = '$yesterday' THEN unpaid_amount ELSE 0 END) as yesterday_unpaid,
        SUM(CASE WHEN sale_date >= '$startOfWeek' THEN unpaid_amount ELSE 0 END) as week_unpaid,
        SUM(CASE WHEN sale_date >= '$startOfMonth' THEN unpaid_amount ELSE 0 END) as month_unpaid,
        SUM(unpaid_amount) as total_unpaid
    FROM customer_unpaid
");

$unpaid_totals = $unpaid_result ? $unpaid_result->fetch_assoc() : [
    'today_unpaid' => 0,
    'yesterday_unpaid' => 0,
    'week_unpaid' => 0,
    'month_unpaid' => 0,
    'total_unpaid' => 0
];

// Calculate sales statistics
$sales_totals_result = $conn->query("
    SELECT
        s.sale_id,
        s.sale_date,
        s.total_amount,
        s.doctor_fee,
        s.operation_fee,
        COALESCE(SUM(p.payment_amount), 0) as paid_amount
    FROM sales s
    LEFT JOIN payments p ON s.sale_id = p.sale_id
    GROUP BY s.sale_id
");

$sales_stats = [
    'today_total' => 0, 'today_paid' => 0, 'today_unpaid' => 0, 'today_fees' => 0,
    'yesterday_total' => 0, 'yesterday_paid' => 0,
    'week_total' => 0, 'week_paid' => 0, 'week_unpaid' => 0,
    'month_total' => 0, 'month_paid' => 0, 'month_unpaid' => 0,
    'all_time_total' => 0, 'all_time_paid' => 0,
    'total_transactions' => 0, 'avg_sale_amount' => 0
];

if ($sales_totals_result) {
    $total_sales = 0;
    $transaction_count = 0;

    while($sale = $sales_totals_result->fetch_assoc()) {
        $sale_date = date('Y-m-d', strtotime($sale['sale_date']));
        $paid = floatval($sale['paid_amount']);
        $total = floatval($sale['total_amount']);
        $fees = floatval($sale['doctor_fee']) + floatval($sale['operation_fee']);
        $unpaid = $total - $paid;

        if ($sale_date == $today) {
            $sales_stats['today_total'] += $total;
            $sales_stats['today_paid'] += $paid;
            $sales_stats['today_unpaid'] += $unpaid;
            $sales_stats['today_fees'] += $fees;
        }
        if ($sale_date == $yesterday) {
            $sales_stats['yesterday_total'] += $total;
            $sales_stats['yesterday_paid'] += $paid;
        }
        if ($sale_date >= $startOfWeek) {
            $sales_stats['week_total'] += $total;
            $sales_stats['week_paid'] += $paid;
            $sales_stats['week_unpaid'] += $unpaid;
        }
        if ($sale_date >= $startOfMonth) {
            $sales_stats['month_total'] += $total;
            $sales_stats['month_paid'] += $paid;
            $sales_stats['month_unpaid'] += $unpaid;
        }

        $sales_stats['all_time_total'] += $total;
        $sales_stats['all_time_paid'] += $paid;
        $transaction_count++;
    }

    $sales_stats['total_transactions'] = $transaction_count;
    $sales_stats['avg_sale_amount'] = $transaction_count > 0 ? $sales_stats['all_time_total'] / $transaction_count : 0;
}

// Calculate profit
$profit_result = $conn->query("
    SELECT
        SUM(CASE WHEN DATE(s.sale_date) = '$today'
            THEN (si.price_out - m.price_in - si.bonus_or_discount) * si.quantity
            ELSE 0 END) as today_profit,
        SUM(CASE WHEN DATE(s.sale_date) = '$yesterday'
            THEN (si.price_out - m.price_in - si.bonus_or_discount) * si.quantity
            ELSE 0 END) as yesterday_profit,
        SUM(CASE WHEN s.sale_date >= '$startOfWeek'
            THEN (si.price_out - m.price_in - si.bonus_or_discount) * si.quantity
            ELSE 0 END) as week_profit,
        SUM(CASE WHEN s.sale_date >= '$startOfMonth'
            THEN (si.price_out - m.price_in - si.bonus_or_discount) * si.quantity
            ELSE 0 END) as month_profit,
        SUM((si.price_out - m.price_in - si.bonus_or_discount) * si.quantity) as all_time_profit
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    JOIN medicines m ON si.medicine_id = m.id
");

$profit_stats = $profit_result ? $profit_result->fetch_assoc() : [
    'today_profit' => 0, 'yesterday_profit' => 0,
    'week_profit' => 0, 'month_profit' => 0, 'all_time_profit' => 0
];

// Expenses
$expense_result = $conn->query("
    SELECT
        COALESCE(SUM(CASE WHEN expense_date = '$today' THEN amount ELSE 0 END), 0) AS today_expenses,
        COALESCE(SUM(CASE WHEN expense_date >= '$startOfWeek' THEN amount ELSE 0 END), 0) AS week_expenses,
        COALESCE(SUM(CASE WHEN expense_date >= '$startOfMonth' THEN amount ELSE 0 END), 0) AS month_expenses
    FROM expenses
");

$expense_stats = $expense_result ? $expense_result->fetch_assoc() : [
    'today_expenses' => 0, 'week_expenses' => 0, 'month_expenses' => 0
];

$today_profit_after_expenses = floatval($profit_stats['today_profit']) - floatval($expense_stats['today_expenses']);
$week_profit_after_expenses  = floatval($profit_stats['week_profit'])  - floatval($expense_stats['week_expenses']);
$month_profit_after_expenses = floatval($profit_stats['month_profit']) - floatval($expense_stats['month_expenses']);

$safe_result = $conn->query("
    SELECT
        COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_method = 'Cash'), 0)
        -
        COALESCE((SELECT SUM(unpaid_amount) FROM customer_unpaid WHERE paid_date IS NULL), 0)
        AS total_cash_in_safe
");
$safe_money = $safe_result ? $safe_result->fetch_assoc()['total_cash_in_safe'] : 0;

// Low stock medicines
$low_stock_medicines = $conn->query("
    SELECT name, amount, price_in, price_out
    FROM medicines
    WHERE amount <= 2
    ORDER BY amount ASC
    LIMIT 10
");

// Recent sales
$recent_sales = $conn->query("
    SELECT
        s.sale_id,
        s.customer_name,
        s.total_amount,
        s.sale_date,
        COALESCE(SUM(p.payment_amount), 0) as paid_amount,
        (s.total_amount - COALESCE(SUM(p.payment_amount), 0)) as balance
    FROM sales s
    LEFT JOIN payments p ON s.sale_id = p.sale_id
    GROUP BY s.sale_id
    ORDER BY s.sale_date DESC
    LIMIT 10
");

// Top selling medicines
$top_medicines = $conn->query("
    SELECT
        m.name,
        SUM(si.quantity) as total_sold,
        SUM(si.subtotal) as total_revenue,
        m.amount as current_stock
    FROM sale_items si
    JOIN medicines m ON si.medicine_id = m.id
    GROUP BY si.medicine_id
    ORDER BY total_sold DESC
    LIMIT 5
");

// Expiring medicines
$expiring_medicines = $conn->query("
    SELECT
        name,
        expire_date,
        amount,
        TIMESTAMPDIFF(MONTH, CURDATE(), expire_date) as months_left
    FROM medicines
    WHERE expire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
    ORDER BY expire_date ASC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Dashboard - PharmaCare RX</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a2980;
            --secondary-color: #26d0ce;
            --accent-color: #00ffea;
            --success-color: #4CAF50;
            --warning-color: #FF9800;
            --danger-color: #f44336;
            --info-color: #2196F3;
            --text-color: #333;
            --light-bg: #f8f9fa;
            --card-bg: #ffffff;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.15);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            color: var(--text-color);
            min-height: 100vh;
            font-size: 14px;
        }

        .main-content {
            padding: 5px 10px;
            transition: margin-right 0.4s ease;
        }

        @media (min-width: 769px) {
            .main-content {
                margin-right: 280px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 80px 15px 25px;
                margin-right: 0;
            }
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
            padding: 15px 20px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border-radius: var(--border-radius);
            color: white;
            box-shadow: var(--shadow);
        }

        .page-header h1 {
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h1 i { color: var(--accent-color); }

        .today {
            background: rgba(255, 255, 255, 0.1);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        /* Main Stats Grid */
        .main-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .main-stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .main-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .main-stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
        }

        .main-stat-card.inventory::before { background: linear-gradient(90deg, #9C27B0, #673AB7); }
        .main-stat-card.sales::before { background: linear-gradient(90deg, #4CAF50, #8BC34A); }
        .main-stat-card.profit::before { background: linear-gradient(90deg, #FF9800, #FFC107); }
        .main-stat-card.safe::before { background: linear-gradient(90deg, #2196F3, #03A9F4); }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: white;
        }

        .stat-icon.inventory { background: linear-gradient(45deg, #9C27B0, #673AB7); }
        .stat-icon.sales { background: linear-gradient(45deg, #4CAF50, #8BC34A); }
        .stat-icon.profit { background: linear-gradient(45deg, #FF9800, #FFC107); }
        .stat-icon.safe { background: linear-gradient(45deg, #2196F3, #03A9F4); }

        .main-stat-value {
            font-size: 2.2rem;
            font-weight: 800;
            margin: 10px 0;
            color: var(--primary-color);
        }

        .main-stat-label {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .main-stat-desc {
            font-size: 0.85rem;
            color: #888;
            margin-top: 10px;
            line-height: 1.4;
        }

        /* Period Tabs */
        .period-tabs {
            display: flex;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 5px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        .period-tab {
            flex: 1;
            padding: 15px 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            border-radius: 8px;
            font-weight: 600;
            white-space: nowrap;
            min-width: 120px;
        }

        .period-tab:hover { background: rgba(38, 208, 206, 0.1); }

        .period-tab.active {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        /* Period Stats Grid */
        .period-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .period-stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .period-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .period-stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .period-stat-title {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .period-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .period-stat-icon.total { background: linear-gradient(45deg, #4CAF50, #8BC34A); }
        .period-stat-icon.paid { background: linear-gradient(45deg, #2196F3, #03A9F4); }
        .period-stat-icon.unpaid { background: linear-gradient(45deg, #FF9800, #FFC107); }
        .period-stat-icon.profit { background: linear-gradient(45deg, #9C27B0, #673AB7); }
        .period-stat-icon.fees { background: linear-gradient(45deg, #00BCD4, #0097A7); }

        .period-stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .period-stat-change {
            font-size: 0.85rem;
            font-weight: 600;
        }

        .period-stat-change.positive { color: var(--success-color); }
        .period-stat-change.negative { color: var(--danger-color); }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .dashboard-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .dashboard-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }

        .card-header h3 {
            color: var(--primary-color);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h3 i { color: var(--secondary-color); }

        .view-all {
            color: var(--secondary-color);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .view-all:hover { color: var(--primary-color); }

        /* Tables */
        .compact-table {
            width: 100%;
            border-collapse: collapse;
        }

        .compact-table th {
            text-align: left;
            padding: 12px 10px;
            font-weight: 600;
            font-size: 0.85rem;
            color: #666;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .compact-table td {
            padding: 10px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .compact-table tbody tr:hover { background: rgba(38, 208, 206, 0.05); }

        /* Status Badges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-paid { background: rgba(76, 175, 80, 0.1); color: var(--success-color); }
        .status-unpaid { background: rgba(255, 152, 0, 0.1); color: var(--warning-color); }
        .status-critical { background: rgba(244, 67, 54, 0.1); color: var(--danger-color); }
        .status-warning { background: rgba(255, 193, 7, 0.1); color: #ff9800; }

        /* Stock Bars */
        .stock-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }

        .stock-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .stock-full { background: var(--success-color); }
        .stock-medium { background: var(--warning-color); }
        .stock-low { background: var(--danger-color); }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 25px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-color);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .action-btn:hover .action-icon { background: rgba(255, 255, 255, 0.2); }

        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: rgba(38, 208, 206, 0.1);
            color: var(--secondary-color);
            transition: var(--transition);
        }

        .action-text { flex: 1; }
        .action-title { font-weight: 600; margin-bottom: 3px; }
        .action-desc { font-size: 0.85rem; opacity: 0.8; }

        /* Profit after expenses */
        .profit-expense-section {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }

        .profit-expense-section h2 {
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }

        .profit-expense-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .profit-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid var(--secondary-color);
        }

        .profit-box h3 {
            color: var(--primary-color);
            font-size: 1rem;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .profit-box p {
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 6px;
        }

        .profit-box .final-profit {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary-color);
            margin: 12px 0 6px;
        }

        .profit-box small {
            color: #888;
            font-size: 0.8rem;
        }

        /* =====================================================
           DATE RANGE FILTER (NEW)
        ===================================================== */
        .range-filter-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fb 100%);
            border-radius: var(--border-radius);
            padding: 28px 30px;
            box-shadow: var(--shadow);
            margin-top: 30px;
            margin-bottom: 25px;
            border: 1px solid #e4e8ee;
            position: relative;
            overflow: hidden;
        }

        .range-filter-section::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color), var(--accent-color));
        }

        .range-filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        .range-filter-header h2 {
            color: var(--primary-color);
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }

        .range-filter-header h2 i {
            color: var(--secondary-color);
            background: rgba(38, 208, 206, 0.1);
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .range-filter-header .filter-badge {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .range-filter-form {
            display: flex;
            align-items: flex-end;
            gap: 15px;
            flex-wrap: wrap;
        }

        .range-filter-form .field {
            flex: 1;
            min-width: 180px;
        }

        .range-filter-form .field label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 6px;
        }

        .range-filter-form .field label i {
            color: var(--secondary-color);
            margin-right: 5px;
        }

        .range-filter-form input[type="date"] {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #cdd3dc;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            background: #fbfcfe;
            color: var(--text-color);
            transition: var(--transition);
        }

        .range-filter-form input[type="date"]:focus {
            outline: none;
            border-color: var(--secondary-color);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(38, 208, 206, 0.15);
        }

        .range-filter-form .btn {
            padding: 11px 22px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            letter-spacing: 0.3px;
            text-decoration: none;
        }

        .range-filter-form .btn-apply {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 15px rgba(26, 41, 128, 0.25);
        }

        .range-filter-form .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 41, 128, 0.35);
        }

        .range-filter-form .btn-reset {
            background: #eef1f5;
            color: #555;
        }

        .range-filter-form .btn-reset:hover {
            background: #e2e7ee;
            color: var(--primary-color);
        }

        /* Range results */
        .range-results {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 2px dashed #e4e8ee;
        }

        .range-results-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .range-results-title i { color: var(--secondary-color); }

        .range-results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .range-card {
            background: #fff;
            border-radius: 10px;
            padding: 18px;
            border: 1px solid #e4e8ee;
            transition: var(--transition);
            border-top: 3px solid var(--primary-color);
        }

        .range-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }

        .range-card.green { border-top-color: var(--success-color); }
        .range-card.orange { border-top-color: var(--warning-color); }
        .range-card.red { border-top-color: var(--danger-color); }
        .range-card.purple { border-top-color: #9C27B0; }
        .range-card.blue { border-top-color: #2196F3; }

        .range-card .label {
            font-size: 0.75rem;
            color: #777;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 8px;
        }

        .range-card .value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 4px;
        }

        .range-card.green .value { color: var(--success-color); }
        .range-card.orange .value { color: var(--warning-color); }
        .range-card.red .value { color: var(--danger-color); }
        .range-card.purple .value { color: #9C27B0; }
        .range-card.blue .value { color: #2196F3; }

        .range-card .hint {
            font-size: 0.75rem;
            color: #888;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .main-stats-grid,
            .period-stats-grid,
            .profit-expense-container,
            .quick-actions { grid-template-columns: 1fr; }

            .period-tabs { flex-wrap: wrap; }
            .period-tab { min-width: 100px; padding: 10px 15px; font-size: 0.9rem; }

            .compact-table { font-size: 0.85rem; }
            .compact-table th,
            .compact-table td { padding: 8px 6px; }

            .range-filter-form { flex-direction: column; align-items: stretch; }
            .range-filter-form .btn { width: 100%; justify-content: center; }
        }

        @media (max-width: 480px) {
            .page-header h1 { font-size: 1.3rem; }
            .main-stat-value { font-size: 1.8rem; }
            .period-stat-value { font-size: 1.5rem; }
            .dashboard-card { padding: 15px; }
            .range-filter-section { padding: 20px; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .main-stat-card,
        .period-stat-card,
        .dashboard-card,
        .range-filter-section {
            animation: fadeIn 0.4s ease;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #f44336;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @media print {
            body { background: white !important; color: black !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .page-header,
            .action-btn,
            .view-all,
            .range-filter-form { display: none !important; }
            .dashboard-card,
            .range-filter-section {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-chart-line"></i>
                Pharmacy Dashboard
            </h1>
            <div class="today">
                <i class="far fa-calendar"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <?php if($conn->error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Database Error:</strong> <?php echo $conn->error; ?>
                    <br><small>Please check your database connection and table structures.</small>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Statistics -->
        <div class="main-stats-grid">
            <div class="main-stat-card inventory">
                <div class="stat-icon inventory"><i class="fas fa-pills"></i></div>
                <div class="main-stat-label">Total Inventory Value</div>
                <div class="main-stat-value"><?php echo number_format($inventory_stats['total_investment'] ?? 0, 2); ?></div>
                <div class="main-stat-desc">
                    <?php echo $inventory_stats['total_medicines'] ?? 0; ?> medicines,
                    <?php echo number_format($inventory_stats['total_units'] ?? 0, 2); ?> units total
                </div>
            </div>

            <div class="main-stat-card sales">
                <div class="stat-icon sales"><i class="fas fa-shopping-cart"></i></div>
                <div class="main-stat-label">All Time Sales</div>
                <div class="main-stat-value"><?php echo number_format($sales_stats['all_time_total'] ?? 0, 2); ?></div>
                <div class="main-stat-desc">
                    <?php echo $sales_stats['total_transactions'] ?? 0; ?> transactions,
                    Avg: $<?php echo number_format($sales_stats['avg_sale_amount'] ?? 0, 2); ?>
                </div>
            </div>

            <div class="main-stat-card profit">
                <div class="stat-icon profit"><i class="fas fa-money-bill-wave"></i></div>
                <div class="main-stat-label">Total Profit</div>
                <div class="main-stat-value"><?php echo number_format($profit_stats['all_time_profit'] ?? 0, 2); ?></div>
                <div class="main-stat-desc">
                    Investment: $<?php echo number_format($inventory_stats['total_investment'] ?? 0, 2); ?>,
                    ROI: <?php
                        $investment = $inventory_stats['total_investment'] ?? 0;
                        $profit = $profit_stats['all_time_profit'] ?? 0;
                        echo $investment > 0 ? number_format(($profit / $investment) * 100, 2) : '0.00';
                    ?>%
                </div>
            </div>

            <div class="main-stat-card safe">
                <div class="stat-icon safe"><i class="fas fa-vault"></i></div>
                <div class="main-stat-label">Total UNPAID</div>
                <div class="main-stat-value" style="color: #ff0000;"><?php echo number_format($unpaid_totals['total_unpaid'] ?? 0, 2); ?></div>
                <div class="main-stat-desc"></div>
            </div>
        </div>

        <!-- Period Tabs -->
        <div class="period-tabs">
            <div class="period-tab active" onclick="showPeriod('today')">
                <i class="fas fa-calendar-day"></i> Today
            </div>
            <div class="period-tab" onclick="showPeriod('yesterday')">
                <i class="fas fa-calendar-minus"></i> Yesterday
            </div>
            <div class="period-tab" onclick="showPeriod('week')">
                <i class="fas fa-calendar-week"></i> This Week
            </div>
            <div class="period-tab" onclick="showPeriod('month')">
                <i class="fas fa-calendar-alt"></i> This Month
            </div>
            <div class="period-tab" onclick="showPeriod('all')">
                <i class="fas fa-chart-bar"></i> All Time
            </div>
        </div>

        <!-- Today Stats -->
        <div class="period-stats-grid" id="todayStats">
            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Today's Total Sales</div>
                    <div class="period-stat-icon total"><i class="fas fa-receipt"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($sales_stats['today_total'] ?? 0, 2); ?></div>
                <div class="period-stat-change positive">+$<?php echo number_format($sales_stats['today_fees'] ?? 0, 2); ?> in fees</div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Today's Paid Amount</div>
                    <div class="period-stat-icon paid"><i class="fas fa-money-check"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($sales_stats['today_paid'] ?? 0, 2); ?></div>
                <div class="period-stat-change positive">Added to safe</div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Today's Unpaid</div>
                    <div class="period-stat-icon unpaid"><i class="fas fa-clock"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($unpaid_totals['today_unpaid'] ?? 0, 2); ?></div>
                <div class="period-stat-change negative">Needs collection</div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Today's Profit</div>
                    <div class="period-stat-icon profit"><i class="fas fa-chart-line"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($profit_stats['today_profit'] ?? 0, 2); ?></div>
                <div class="period-stat-change positive">
                    Margin: <?php
                        $todayRevenue = $sales_stats['today_total'] ?? 0;
                        $todayProfit = $profit_stats['today_profit'] ?? 0;
                        echo $todayRevenue > 0 ? number_format(($todayProfit / $todayRevenue) * 100, 2) : '0.00';
                    ?>%
                </div>
            </div>
        </div>

        <!-- Yesterday Stats -->
        <div class="period-stats-grid" id="yesterdayStats" style="display: none;">
            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Yesterday's Total</div>
                    <div class="period-stat-icon total"><i class="fas fa-receipt"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($sales_stats['yesterday_total'] ?? 0, 2); ?></div>
                <div class="period-stat-change">
                    <?php
                        $change = ($sales_stats['today_total'] ?? 0) - ($sales_stats['yesterday_total'] ?? 0);
                        $changeClass = $change >= 0 ? 'positive' : 'negative';
                        $changeSymbol = $change >= 0 ? '+' : '';
                    ?>
                    <span class="<?php echo $changeClass; ?>"><?php echo $changeSymbol . number_format($change, 2); ?> from yesterday</span>
                </div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Yesterday's Paid</div>
                    <div class="period-stat-icon paid"><i class="fas fa-money-check"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($sales_stats['yesterday_paid'] ?? 0, 2); ?></div>
                <div class="period-stat-change">In safe</div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Yesterday's Unpaid</div>
                    <div class="period-stat-icon unpaid"><i class="fas fa-clock"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($unpaid_totals['yesterday_unpaid'] ?? 0, 2); ?></div>
                <div class="period-stat-change negative">Outstanding</div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Yesterday's Profit</div>
                    <div class="period-stat-icon profit"><i class="fas fa-chart-line"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($profit_stats['yesterday_profit'] ?? 0, 2); ?></div>
                <div class="period-stat-change">
                    <?php
                        $profitChange = ($profit_stats['today_profit'] ?? 0) - ($profit_stats['yesterday_profit'] ?? 0);
                        $profitChangeClass = $profitChange >= 0 ? 'positive' : 'negative';
                        $profitChangeSymbol = $profitChange >= 0 ? '+' : '';
                    ?>
                    <span class="<?php echo $profitChangeClass; ?>"><?php echo $profitChangeSymbol . number_format($profitChange, 2); ?> from yesterday</span>
                </div>
            </div>
        </div>

        <!-- Week Stats -->
        <div class="period-stats-grid" id="weekStats" style="display: none;">
            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Week's Total Sales</div>
                    <div class="period-stat-icon total"><i class="fas fa-receipt"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($sales_stats['week_total'] ?? 0, 2); ?></div>
                <div class="period-stat-change positive">Since <?php echo date('M d', strtotime($startOfWeek)); ?></div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Week's Paid Amount</div>
                    <div class="period-stat-icon paid"><i class="fas fa-money-check"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($sales_stats['week_paid'] ?? 0, 2); ?></div>
                <div class="period-stat-change positive">Cash collected</div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Week's Unpaid</div>
                    <div class="period-stat-icon unpaid"><i class="fas fa-clock"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($unpaid_totals['week_unpaid'] ?? 0, 2); ?></div>
                <div class="period-stat-change negative">Needs follow-up</div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Week's Profit</div>
                    <div class="period-stat-icon profit"><i class="fas fa-chart-line"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($profit_stats['week_profit'] ?? 0, 2); ?></div>
                <div class="period-stat-change positive">
                    <?php $avgDailyProfit = ($profit_stats['week_profit'] ?? 0) / 7; ?>
                    Avg: $<?php echo number_format($avgDailyProfit, 2); ?>/day
                </div>
            </div>
        </div>

        <!-- Month Stats -->
        <div class="period-stats-grid" id="monthStats" style="display: none;">
            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Month's Total Sales</div>
                    <div class="period-stat-icon total"><i class="fas fa-receipt"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($sales_stats['month_total'] ?? 0, 2); ?></div>
                <div class="period-stat-change positive">Since <?php echo date('M d', strtotime($startOfMonth)); ?></div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Month's Paid Amount</div>
                    <div class="period-stat-icon paid"><i class="fas fa-money-check"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($sales_stats['month_paid'] ?? 0, 2); ?></div>
                <div class="period-stat-change positive">In safe</div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Month's Unpaid</div>
                    <div class="period-stat-icon unpaid"><i class="fas fa-clock"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($unpaid_totals['month_unpaid'] ?? 0, 2); ?></div>
                <div class="period-stat-change negative">Receivables</div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">Month's Profit</div>
                    <div class="period-stat-icon profit"><i class="fas fa-chart-line"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($profit_stats['month_profit'] ?? 0, 2); ?></div>
                <div class="period-stat-change positive">
                    <?php
                        $daysInMonth = date('t');
                        $daysPassed = date('j');
                        $projectedProfit = ($profit_stats['month_profit'] ?? 0) / max($daysPassed, 1) * $daysInMonth;
                    ?>
                    Projected: $<?php echo number_format($projectedProfit, 2); ?>
                </div>
            </div>
        </div>

        <!-- All Time Stats -->
        <div class="period-stats-grid" id="allStats" style="display: none;">
            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">All Time Sales</div>
                    <div class="period-stat-icon total"><i class="fas fa-receipt"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($sales_stats['all_time_total'] ?? 0, 2); ?></div>
                <div class="period-stat-change positive"><?php echo $sales_stats['total_transactions'] ?? 0; ?> transactions</div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">All Time Paid</div>
                    <div class="period-stat-icon paid"><i class="fas fa-money-check"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($sales_stats['all_time_paid'] ?? 0, 2); ?></div>
                <div class="period-stat-change positive">Total cash collected</div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">All Time Unpaid</div>
                    <div class="period-stat-icon unpaid"><i class="fas fa-clock"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($unpaid_totals['total_unpaid'] ?? 0, 2); ?></div>
                <div class="period-stat-change negative">Outstanding receivables</div>
            </div>

            <div class="period-stat-card">
                <div class="period-stat-header">
                    <div class="period-stat-title">All Time Profit</div>
                    <div class="period-stat-icon profit"><i class="fas fa-chart-line"></i></div>
                </div>
                <div class="period-stat-value"><?php echo number_format($profit_stats['all_time_profit'] ?? 0, 2); ?></div>
                <div class="period-stat-change positive">
                    ROI: <?php
                        $investment = $inventory_stats['total_investment'] ?? 0;
                        $profit = $profit_stats['all_time_profit'] ?? 0;
                        echo $investment > 0 ? number_format(($profit / $investment) * 100, 2) : '0.00';
                    ?>%
                </div>
            </div>
        </div>

        <!-- Dashboard Grid: Low Stock + Expiring -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h3>
                    <a href="item.php" class="view-all">View All</a>
                </div>
                <?php if($low_stock_medicines && $low_stock_medicines->num_rows > 0): ?>
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Stock</th>
                                <th>Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $low_stock_medicines->fetch_assoc()): ?>
                                <?php
                                    $stock_value = $row['price_out'] * $row['amount'];
                                    $status = $row['amount'] == 0 ? 'critical' : ($row['amount'] <= 2 ? 'warning' : 'normal');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td>
                                        <div><?php echo number_format($row['amount'], 2); ?></div>
                                        <div class="stock-bar">
                                            <div class="stock-fill stock-low" style="width: <?php echo min(($row['amount'] / 10) * 100, 100); ?>%"></div>
                                        </div>
                                    </td>
                                    <td>$<?php echo number_format($stock_value, 2); ?></td>
                                    <td><span class="status-badge status-<?php echo $status; ?>"><?php echo strtoupper($status); ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px 0;">
                        <i class="fas fa-check-circle" style="color: #4CAF50; font-size: 2rem; display: block; margin-bottom: 10px;"></i>
                        All stock levels are good!
                    </p>
                <?php endif; ?>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Expiring Soon</h3>
                </div>
                <?php if($expiring_medicines && $expiring_medicines->num_rows > 0): ?>
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Expiry Date</th>
                                <th>Months Left</th>
                                <th>Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while($row = $expiring_medicines->fetch_assoc()): ?>
                            <?php
                                $statusClass = $row['months_left'] <= 1 ? 'critical' :
                                             ($row['months_left'] <= 3 ? 'warning' : 'normal');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($row['expire_date'])); ?></td>
                                <td><span class="status-badge status-<?php echo $statusClass; ?>"><?php echo $row['months_left']; ?> months</span></td>
                                <td><?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px 0;">No medicines expiring soon</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Profit After Expenses -->
        <div class="profit-expense-section">
            <h2><i class="fas fa-balance-scale" style="color: var(--secondary-color); margin-right: 8px;"></i> Profit After Expenses</h2>
            <div class="profit-expense-container">
                <div class="profit-box">
                    <h3>Today</h3>
                    <p>Profit: <strong><?= number_format($profit_stats['today_profit'], 2) ?></strong></p>
                    <p>Expenses: <strong><?= number_format($expense_stats['today_expenses'], 2) ?></strong></p>
                    <div class="final-profit"><?= number_format($today_profit_after_expenses, 2) ?></div>
                    <small>Profit - Expenses</small>
                </div>

                <div class="profit-box">
                    <h3>This Week</h3>
                    <p>Profit: <strong><?= number_format($profit_stats['week_profit'], 2) ?></strong></p>
                    <p>Expenses: <strong><?= number_format($expense_stats['week_expenses'], 2) ?></strong></p>
                    <div class="final-profit"><?= number_format($week_profit_after_expenses, 2) ?></div>
                    <small>Profit - Expenses</small>
                </div>

                <div class="profit-box">
                    <h3>This Month</h3>
                    <p>Profit: <strong><?= number_format($profit_stats['month_profit'], 2) ?></strong></p>
                    <p>Expenses: <strong><?= number_format($expense_stats['month_expenses'], 2) ?></strong></p>
                    <div class="final-profit"><?= number_format($month_profit_after_expenses, 2) ?></div>
                    <small>Profit - Expenses</small>
                </div>
            </div>
        </div>

        <!-- =====================================================
             DATE RANGE FILTER (NEW)
        ===================================================== -->
        <div class="range-filter-section">
            <div class="range-filter-header">
                <h2>
                    <i class="fas fa-calendar-alt"></i>
                    Custom Date Range Report
                </h2>
                <?php if ($filter_active): ?>
                    <span class="filter-badge">
                        <i class="fas fa-filter"></i>
                        <?php echo date('M d, Y', strtotime($filter_from)); ?> → <?php echo date('M d, Y', strtotime($filter_to)); ?>
                    </span>
                <?php endif; ?>
            </div>

            <form method="GET" class="range-filter-form">
                <input type="hidden" name="filter_range" value="1">

                <div class="field">
                    <label><i class="fas fa-calendar-day"></i> From Date</label>
                    <input type="date" name="from_date"
                           value="<?php echo htmlspecialchars($filter_from); ?>"
                           max="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="field">
                    <label><i class="fas fa-calendar-check"></i> To Date</label>
                    <input type="date" name="to_date"
                           value="<?php echo htmlspecialchars($filter_to); ?>"
                           max="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <button type="submit" class="btn btn-apply">
                    <i class="fas fa-search"></i> Apply Filter
                </button>

                <?php if ($filter_active): ?>
                    <a href="dashboard.php" class="btn btn-reset">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>

            <?php if ($filter_active): ?>
                <div class="range-results">
                    <div class="range-results-title">
                        <i class="fas fa-chart-bar"></i>
                        Results for the selected range
                    </div>

                    <div class="range-results-grid">
                        <div class="range-card">
                            <div class="label">Total Sales</div>
                            <div class="value"><?php echo number_format($range_stats['sales_total'], 2); ?></div>
                            <div class="hint"><?php echo $range_stats['transactions']; ?> transactions</div>
                        </div>

                        <div class="range-card green">
                            <div class="label">Paid Amount</div>
                            <div class="value"><?php echo number_format($range_stats['sales_paid'], 2); ?></div>
                            <div class="hint">Cash collected</div>
                        </div>

                        <div class="range-card red">
                            <div class="label">Unpaid Amount</div>
                            <div class="value"><?php echo number_format($range_stats['sales_unpaid'], 2); ?></div>
                            <div class="hint">Outstanding</div>
                        </div>

                        <div class="range-card purple">
                            <div class="label">Gross Profit</div>
                            <div class="value"><?php echo number_format($range_stats['profit'], 2); ?></div>
                            <div class="hint">Before expenses</div>
                        </div>

                        <div class="range-card orange">
                            <div class="label">Expenses</div>
                            <div class="value"><?php echo number_format($range_stats['expenses'], 2); ?></div>
                            <div class="hint">In this period</div>
                        </div>

                        <div class="range-card blue">
                            <div class="label">Net Profit</div>
                            <div class="value"><?php echo number_format($range_stats['profit_after_expenses'], 2); ?></div>
                            <div class="hint">Profit − Expenses</div>
                        </div>

                        <div class="range-card green">
                            <div class="label">Average Sale</div>
                            <div class="value"><?php echo number_format($range_stats['avg_sale'], 2); ?></div>
                            <div class="hint">Per transaction</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="new_sale.php" class="action-btn">
                <div class="action-icon"><i class="fas fa-cart-plus"></i></div>
                <div class="action-text">
                    <div class="action-title">New Sale</div>
                    <div class="action-desc">Create a new sale transaction</div>
                </div>
            </a>

            <a href="add_medicine.php" class="action-btn">
                <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
                <div class="action-text">
                    <div class="action-title">Add Medicine</div>
                    <div class="action-desc">Add new medicine to inventory</div>
                </div>
            </a>

            <a href="low_stock.php" class="action-btn">
                <div class="action-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="action-text">
                    <div class="action-title">Low Stock</div>
                    <div class="action-desc">View and manage low stock items</div>
                </div>
            </a>

            <a href="reports.php" class="action-btn">
                <div class="action-icon"><i class="fas fa-chart-pie"></i></div>
                <div class="action-text">
                    <div class="action-title">Reports</div>
                    <div class="action-desc">View detailed reports and analytics</div>
                </div>
            </a>
        </div>
    </div>

    <script>
        function showPeriod(period) {
            document.getElementById('todayStats').style.display = 'none';
            document.getElementById('yesterdayStats').style.display = 'none';
            document.getElementById('weekStats').style.display = 'none';
            document.getElementById('monthStats').style.display = 'none';
            document.getElementById('allStats').style.display = 'none';

            document.getElementById(period + 'Stats').style.display = 'grid';

            document.querySelectorAll('.period-tab').forEach(tab => {
                tab.classList.remove('active');
            });

            const tabs = document.querySelectorAll('.period-tab');
            const periodIndex = ['today', 'yesterday', 'week', 'month', 'all'].indexOf(period);
            if (periodIndex !== -1) {
                tabs[periodIndex].classList.add('active');
            }
        }

        // Auto-refresh every 5 minutes
        setTimeout(() => { location.reload(); }, 300000);

        document.addEventListener('DOMContentLoaded', function() {
            showPeriod('today');

            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
                if (e.key >= '1' && e.key <= '5') {
                    const periods = ['today', 'yesterday', 'week', 'month', 'all'];
                    showPeriod(periods[parseInt(e.key) - 1]);
                }
            });
        });
    </script>
</body>
</html>