<?php

include "db.php";

// =====================================================
// ADD COMPANY
// =====================================================

if (isset($_POST['add_company'])) {

    $company_name = $_POST['company_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    $sql = "INSERT INTO companies (company_name, phone, address)
            VALUES ('$company_name', '$phone', '$address')";

    if (mysqli_query($conn, $sql)) {
        header("Location: companies.php?msg=company_added");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($conn);
    }
}


// =====================================================
// UPDATE COMPANY
// =====================================================

if (isset($_POST['update_company'])) {

    $id = $_POST['company_id'];
    $company_name = $_POST['company_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    $sql = "UPDATE companies
            SET company_name = '$company_name',
                phone = '$phone',
                address = '$address'
            WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        header("Location: companies.php?msg=company_updated");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($conn);
    }
}


// =====================================================
// DELETE COMPANY (also deletes its purchases & payments)
// =====================================================

if (isset($_GET['delete_company'])) {

    $id = intval($_GET['delete_company']);

    mysqli_query($conn, "DELETE FROM company_purchases WHERE company_id = $id");
    mysqli_query($conn, "DELETE FROM company_payments WHERE company_id = $id");
    mysqli_query($conn, "DELETE FROM companies WHERE id = $id");

    header("Location: companies.php?msg=company_deleted");
    exit();
}


// =====================================================
// ADD PURCHASE / COMPANY DEBT
// =====================================================

if (isset($_POST['add_purchase'])) {

    $company_id = $_POST['company_id'];

    $product_name = !empty($_POST['product_name']) ? $_POST['product_name'] : NULL;
    $quantity     = !empty($_POST['quantity'])     ? $_POST['quantity']     : NULL;
    $price        = !empty($_POST['price'])        ? $_POST['price']        : NULL;
    $total_amount = $_POST['total_amount'];
    $purchase_date = $_POST['purchase_date'];

    $sql = "INSERT INTO company_purchases
            (company_id, product_name, quantity, price, total_amount, purchase_date)
            VALUES
            (
                '$company_id',
                " . ($product_name === NULL ? "NULL" : "'$product_name'") . ",
                " . ($quantity === NULL ? "NULL" : "'$quantity'") . ",
                " . ($price === NULL ? "NULL" : "'$price'") . ",
                '$total_amount',
                '$purchase_date'
            )";

    if (mysqli_query($conn, $sql)) {
        header("Location: companies.php?msg=purchase_added");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($conn);
    }
}


// =====================================================
// DELETE PURCHASE
// =====================================================

if (isset($_GET['delete_purchase'])) {

    $id = intval($_GET['delete_purchase']);
    mysqli_query($conn, "DELETE FROM company_purchases WHERE id = $id");

    header("Location: companies.php?msg=purchase_deleted");
    exit();
}


// =====================================================
// ADD PAYMENT (now with note)
// =====================================================

if (isset($_POST['add_payment'])) {

    $company_id   = $_POST['payment_company_id'];
    $amount       = $_POST['payment_amount'];
    $payment_date = $_POST['payment_date'];
    $note         = !empty($_POST['payment_note']) ? $_POST['payment_note'] : NULL;

    $sql = "INSERT INTO company_payments
            (company_id, amount, payment_date, note)
            VALUES
            (
                '$company_id',
                '$amount',
                '$payment_date',
                " . ($note === NULL ? "NULL" : "'$note'") . "
            )";

    if (mysqli_query($conn, $sql)) {
        header("Location: companies.php?msg=payment_added");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($conn);
    }
}


// =====================================================
// DELETE PAYMENT
// =====================================================

if (isset($_GET['delete_payment'])) {

    $id = intval($_GET['delete_payment']);
    mysqli_query($conn, "DELETE FROM company_payments WHERE id = $id");

    header("Location: companies.php?msg=payment_deleted");
    exit();
}


// =====================================================
// TOTALS
// =====================================================

$total_purchase_sql = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM company_purchases";
$total_purchase_result = mysqli_query($conn, $total_purchase_sql);
$total_purchase_data = mysqli_fetch_assoc($total_purchase_result);
$total_all_purchases = $total_purchase_data['total'];

$total_payment_sql = "SELECT COALESCE(SUM(amount), 0) AS total FROM company_payments";
$total_payment_result = mysqli_query($conn, $total_payment_sql);
$total_payment_data = mysqli_fetch_assoc($total_payment_result);
$total_all_paid = $total_payment_data['total'];

$total_unpaid = $total_all_purchases - $total_all_paid;


// =====================================================
// SEARCH
// =====================================================

$search = $_GET['search'] ?? '';

if ($search != '') {
    $search_safe = mysqli_real_escape_string($conn, $search);
    $company_sql = "
        SELECT * FROM companies
        WHERE company_name LIKE '%$search_safe%'
        OR phone LIKE '%$search_safe%'
        ORDER BY id DESC
    ";
} else {
    $company_sql = "SELECT * FROM companies ORDER BY id DESC";
}

$companies = mysqli_query($conn, $company_sql);


// =====================================================
// ALL COMPANIES FOR DROPDOWNS
// =====================================================

$all_companies_sql = "SELECT * FROM companies ORDER BY company_name ASC";
$all_companies = mysqli_query($conn, $all_companies_sql);


// =====================================================
// EDIT MODE — load company for editing
// =====================================================

$edit_company = null;

if (isset($_GET['edit_company'])) {
    $edit_id = intval($_GET['edit_company']);
    $edit_result = mysqli_query($conn, "SELECT * FROM companies WHERE id = $edit_id");
    if (mysqli_num_rows($edit_result) > 0) {
        $edit_company = mysqli_fetch_assoc($edit_result);
    }
}


// =====================================================
// RECENT PURCHASES AND PAYMENTS (for management)
// =====================================================

$recent_purchases = mysqli_query($conn, "
    SELECT cp.*, c.company_name
    FROM company_purchases cp
    LEFT JOIN companies c ON c.id = cp.company_id
    ORDER BY cp.id DESC
    LIMIT 20
");

$recent_payments = mysqli_query($conn, "
    SELECT cp.*, c.company_name
    FROM company_payments cp
    LEFT JOIN companies c ON c.id = cp.company_id
    ORDER BY cp.id DESC
    LIMIT 20
");

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Companies & Credit</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #1a2980;
            --primary-dark: #14206a;
            --secondary: #26d0ce;
            --secondary-dark: #1eb5b3;
            --success: #2ecc71;
            --danger: #e74c3c;
            --warning: #f39c12;
            --text-dark: #2c3e50;
            --text-mid: #5a6c7d;
            --text-light: #8a97a6;
            --border: #e4e8ee;
            --bg: #f4f6fa;
            --card-shadow: 0 1px 3px rgba(26, 41, 128, 0.06);
            --card-shadow-hover: 0 4px 14px rgba(26, 41, 128, 0.10);
            --radius: 10px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background: var(--bg);
            color: var(--text-dark);
            margin: 0;
            padding: 32px 40px;
            font-size: 14px;
            line-height: 1.5;
        }

        @media (min-width: 769px) { body { margin-right: 280px; } }
        @media (max-width: 768px) { body { padding: 80px 18px 30px; } }

        .page-title {
            font-size: 24px;
            color: var(--primary);
            font-weight: 700;
            margin: 0 0 6px 0;
            letter-spacing: -0.3px;
        }

        .page-subtitle {
            color: var(--text-light);
            font-size: 13px;
            margin: 0 0 28px 0;
        }

        /* ===== Alerts ===== */
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 22px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #e8f9f0;
            color: #1e7e4a;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #fdecec;
            color: #b03030;
            border-left: 4px solid var(--danger);
        }

        /* ===== Summary ===== */
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .summary-box {
            background: #fff;
            border-radius: var(--radius);
            padding: 22px 24px;
            box-shadow: var(--card-shadow);
            border-top: 3px solid var(--primary);
            transition: all 0.25s ease;
        }

        .summary-box:hover {
            box-shadow: var(--card-shadow-hover);
            transform: translateY(-2px);
        }

        .summary-box.paid { border-top-color: var(--success); }
        .summary-box.unpaid { border-top-color: var(--danger); }

        .summary-title {
            font-size: 12px;
            color: var(--text-light);
            margin-bottom: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .summary-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: -0.5px;
        }

        .summary-box.paid .summary-number { color: var(--success); }
        .summary-box.unpaid .summary-number { color: var(--danger); }

        /* ===== Cards ===== */
        .card {
            background: #fff;
            border-radius: var(--radius);
            padding: 26px 28px;
            box-shadow: var(--card-shadow);
            margin-bottom: 22px;
            border: 1px solid var(--border);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 16px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .card-header i {
            color: var(--secondary);
            font-size: 16px;
        }

        .card-header h2 {
            font-size: 16px;
            color: var(--primary);
            margin: 0;
            font-weight: 700;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-bottom: 22px;
        }

        @media (max-width: 1000px) { .grid-2 { grid-template-columns: 1fr; } }

        /* ===== Form ===== */
        .form-group { margin-bottom: 16px; }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-mid);
        }

        .form-group label small {
            color: var(--text-light);
            font-weight: 400;
            font-size: 12px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            background: #fbfcfe;
            color: var(--text-dark);
            transition: all 0.2s ease;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder { color: #b0b9c5; }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--secondary);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(38, 208, 206, 0.15);
        }

        /* ===== Buttons ===== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 22px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s ease;
            letter-spacing: 0.2px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 2px 6px rgba(26, 41, 128, 0.2);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            box-shadow: 0 4px 12px rgba(26, 41, 128, 0.3);
        }

        .btn-secondary {
            background: var(--secondary);
            color: #fff;
            box-shadow: 0 2px 6px rgba(38, 208, 206, 0.25);
        }

        .btn-secondary:hover {
            background: var(--secondary-dark);
            box-shadow: 0 4px 12px rgba(38, 208, 206, 0.35);
        }

        .btn-grey {
            background: #eef1f5;
            color: var(--text-mid);
        }

        .btn-grey:hover { background: #e2e7ee; color: var(--text-dark); }

        .btn-danger {
            background: var(--danger);
            color: #fff;
        }

        .btn-danger:hover { background: #c0392b; }

        .btn-warning {
            background: var(--warning);
            color: #fff;
        }

        .btn-warning:hover { background: #d68910; }

        .btn-full { width: 100%; }

        .btn-sm {
            padding: 7px 12px;
            font-size: 12px;
            border-radius: 6px;
        }

        /* ===== Search ===== */
        .search-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .search-row input {
            flex: 1;
            min-width: 200px;
            padding: 11px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            background: #fbfcfe;
            transition: all 0.2s ease;
        }

        .search-row input:focus {
            outline: none;
            border-color: var(--secondary);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(38, 208, 206, 0.15);
        }

        /* ===== Table ===== */
        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        thead { background: #f7f9fc; }

        th {
            text-align: left;
            padding: 14px 16px;
            font-size: 12px;
            color: var(--text-mid);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f0f3f7;
            color: var(--text-dark);
            vertical-align: middle;
        }

        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #f9fbfd; }

        .company-name { font-weight: 600; color: var(--primary); }
        .debt { color: var(--danger); font-weight: 700; }
        .paid { color: var(--success); font-weight: 700; }

        .note-cell {
            color: var(--text-mid);
            font-style: italic;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .note-cell.muted {
            color: #b0b9c5;
            font-style: normal;
        }

        .empty {
            text-align: center;
            padding: 40px 15px;
            color: var(--text-light);
            font-style: italic;
        }

        /* ===== Action buttons inside tables ===== */
        .actions {
            display: flex;
            gap: 6px;
            flex-wrap: nowrap;
        }

        /* ===== Edit banner ===== */
        .edit-banner {
            background: linear-gradient(90deg, #fff8e6, #fffdf7);
            border-left: 4px solid var(--warning);
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .edit-banner span {
            font-weight: 600;
            color: #9c6f0a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ===== Tabs ===== */
        .tabs {
            display: flex;
            gap: 4px;
            border-bottom: 2px solid var(--border);
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .tabs a {
            padding: 12px 20px;
            text-decoration: none;
            color: var(--text-mid);
            font-weight: 600;
            font-size: 14px;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .tabs a:hover { color: var(--primary); }

        .tabs a.active {
            color: var(--primary);
            border-bottom-color: var(--secondary);
        }
    </style>

</head>

<body>

<?php include "sidebar.php"; ?>

<h1 class="page-title">Companies & Credit</h1>
<p class="page-subtitle">Manage suppliers, purchases, and payments</p>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
    </div>
<?php endif; ?>

<?php if (!empty($_GET['msg'])): ?>
    <?php
    $messages = [
        'company_added'    => ['Company added successfully.', 'success'],
        'company_updated'  => ['Company updated successfully.', 'success'],
        'company_deleted'  => ['Company deleted successfully.', 'success'],
        'purchase_added'   => ['Purchase added successfully.', 'success'],
        'purchase_deleted' => ['Purchase deleted successfully.', 'success'],
        'payment_added'    => ['Payment added successfully.', 'success'],
        'payment_deleted'  => ['Payment deleted successfully.', 'success'],
    ];
    $msg = $messages[$_GET['msg']] ?? null;
    if ($msg):
    ?>
        <div class="alert alert-<?php echo $msg[1]; ?>">
            <i class="fas fa-check-circle"></i> <?php echo $msg[0]; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>


<!-- =====================================================
     SUMMARY
===================================================== -->

<div class="summary">
    <div class="summary-box">
        <div class="summary-title">Total Purchases</div>
        <div class="summary-number"><?php echo number_format($total_all_purchases, 2); ?></div>
    </div>
    <div class="summary-box paid">
        <div class="summary-title">Total Paid</div>
        <div class="summary-number"><?php echo number_format($total_all_paid, 2); ?></div>
    </div>
    <div class="summary-box unpaid">
        <div class="summary-title">Total Unpaid</div>
        <div class="summary-number"><?php echo number_format($total_unpaid, 2); ?></div>
    </div>
</div>


<!-- =====================================================
     ADD / EDIT COMPANY + ADD PURCHASE
===================================================== -->

<div class="grid-2">

    <!-- Company form (Add or Edit) -->
    <div class="card">
        <div class="card-header">
            <i class="fas <?php echo $edit_company ? 'fa-edit' : 'fa-building'; ?>"></i>
            <h2><?php echo $edit_company ? 'Edit Company' : 'Add Company'; ?></h2>
        </div>

        <?php if ($edit_company): ?>
            <div class="edit-banner">
                <span><i class="fas fa-pencil-alt"></i> Editing: <?php echo htmlspecialchars($edit_company['company_name']); ?></span>
                <a href="companies.php" class="btn btn-grey btn-sm">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        <?php endif; ?>

        <form method="POST">

            <?php if ($edit_company): ?>
                <input type="hidden" name="company_id" value="<?php echo $edit_company['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Company Name</label>
                <input type="text" name="company_name"
                       value="<?php echo $edit_company ? htmlspecialchars($edit_company['company_name']) : ''; ?>"
                       placeholder="Example: ABC Pharmaceutical" required>
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone"
                       value="<?php echo $edit_company ? htmlspecialchars($edit_company['phone']) : ''; ?>"
                       placeholder="0700000000">
            </div>

            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address"
                       value="<?php echo $edit_company ? htmlspecialchars($edit_company['address']) : ''; ?>"
                       placeholder="Company address">
            </div>

            <?php if ($edit_company): ?>
                <button type="submit" name="update_company" class="btn btn-warning btn-full">
                    <i class="fas fa-save"></i> Update Company
                </button>
            <?php else: ?>
                <button type="submit" name="add_company" class="btn btn-primary btn-full">
                    <i class="fas fa-plus"></i> Add Company
                </button>
            <?php endif; ?>

        </form>
    </div>

    <!-- Add Purchase -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-shopping-cart"></i>
            <h2>Buy From Company / Add Debt</h2>
        </div>

        <form method="POST">

            <div class="form-group">
                <label>Company</label>
                <select name="company_id" required>
                    <option value="">Select Company</option>
                    <?php
                    mysqli_data_seek($all_companies, 0);
                    while ($company = mysqli_fetch_assoc($all_companies)) { ?>
                        <option value="<?php echo $company['id']; ?>">
                            <?php echo htmlspecialchars($company['company_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group">
                <label>Product Name <small>(Optional)</small></label>
                <input type="text" name="product_name" placeholder="Example: Paracetamol">
            </div>

            <div class="form-group">
                <label>Quantity <small>(Optional)</small></label>
                <input type="number" name="quantity" step="0.01" placeholder="Example: 100">
            </div>

            <div class="form-group">
                <label>Price <small>(Optional)</small></label>
                <input type="number" name="price" step="0.01" placeholder="Example: 50">
            </div>

            <div class="form-group">
                <label>Total Amount</label>
                <input type="number" name="total_amount" step="0.01" placeholder="Example: 5000" required>
            </div>

            <div class="form-group">
                <label>Purchase Date</label>
                <input type="date" name="purchase_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <button type="submit" name="add_purchase" class="btn btn-primary btn-full">
                <i class="fas fa-plus"></i> Add Purchase / Debt
            </button>

        </form>
    </div>

</div>


<!-- =====================================================
     ADD PAYMENT (now with Note field)
===================================================== -->

<div class="card">
    <div class="card-header">
        <i class="fas fa-money-bill-wave"></i>
        <h2>Pay Company</h2>
    </div>

    <form method="POST">
        <div class="grid-2" style="margin-bottom:18px; gap:18px;">

            <div class="form-group" style="margin-bottom:0;">
                <label>Company</label>
                <select name="payment_company_id" required>
                    <option value="">Select Company</option>
                    <?php
                    mysqli_data_seek($all_companies, 0);
                    while ($company = mysqli_fetch_assoc($all_companies)) { ?>
                        <option value="<?php echo $company['id']; ?>">
                            <?php echo htmlspecialchars($company['company_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label>Payment Amount</label>
                <input type="number" name="payment_amount" step="0.01" placeholder="Example: 2000" required>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label>Payment Date</label>
                <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label>Note <small>(Optional)</small></label>
                <textarea name="payment_note" placeholder="Example: Partial payment via bank transfer"></textarea>
            </div>

        </div>

        <div style="margin-top:6px;">
            <button type="submit" name="add_payment" class="btn btn-secondary">
                <i class="fas fa-check"></i> Add Payment
            </button>
        </div>
    </form>
</div>


<!-- =====================================================
     COMPANIES LIST
===================================================== -->

<div class="card">
    <div class="card-header">
        <i class="fas fa-list"></i>
        <h2>Companies</h2>
    </div>

    <form method="GET" class="search-row">
        <input type="text" name="search"
               value="<?php echo htmlspecialchars($search); ?>"
               placeholder="Search company name or phone...">
        <button type="submit" class="btn btn-secondary">
            <i class="fas fa-search"></i> Search
        </button>
        <a href="companies.php" class="btn btn-grey">
            <i class="fas fa-times"></i> Clear
        </a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Company</th>
                    <th>Phone</th>
                    <th>Total Purchases</th>
                    <th>Total Paid</th>
                    <th>Remaining Debt</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($companies) > 0): ?>
                    <?php while ($company = mysqli_fetch_assoc($companies)): ?>
                        <?php
                        $company_id = $company['id'];

                        $purchase_sql = "SELECT COALESCE(SUM(total_amount), 0) AS total
                                         FROM company_purchases WHERE company_id = $company_id";
                        $purchase_result = mysqli_query($conn, $purchase_sql);
                        $purchase_data = mysqli_fetch_assoc($purchase_result);
                        $total_purchases = $purchase_data['total'];

                        $payment_sql = "SELECT COALESCE(SUM(amount), 0) AS total
                                        FROM company_payments WHERE company_id = $company_id";
                        $payment_result = mysqli_query($conn, $payment_sql);
                        $payment_data = mysqli_fetch_assoc($payment_result);
                        $total_paid = $payment_data['total'];

                        $remaining = $total_purchases - $total_paid;
                        ?>

                        <tr>
                            <td><?php echo $company['id']; ?></td>
                            <td class="company-name"><?php echo htmlspecialchars($company['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($company['phone']); ?></td>
                            <td><?php echo number_format($total_purchases, 2); ?></td>
                            <td class="paid"><?php echo number_format($total_paid, 2); ?></td>
                            <td class="debt"><?php echo number_format($remaining, 2); ?></td>
                            <td>
                                <div class="actions" style="justify-content:center;">
                                    <a href="companies.php?edit_company=<?php echo $company['id']; ?>"
                                       class="btn btn-warning btn-sm" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <a href="companies.php?delete_company=<?php echo $company['id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       title="Delete"
                                       onclick="return confirm('Delete this company and all its purchases/payments?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>

                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty">No companies found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- =====================================================
     RECENT PURCHASES
===================================================== -->

<div class="card">
    <div class="card-header">
        <i class="fas fa-receipt"></i>
        <h2>Recent Purchases / Debts</h2>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Company</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                    <th>Date</th>
                    <th style="text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($recent_purchases) > 0): ?>
                    <?php while ($p = mysqli_fetch_assoc($recent_purchases)): ?>
                        <tr>
                            <td><?php echo $p['id']; ?></td>
                            <td class="company-name"><?php echo htmlspecialchars($p['company_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($p['product_name'] ?? '—'); ?></td>
                            <td><?php echo $p['quantity'] ?? '—'; ?></td>
                            <td><?php echo $p['price'] !== null ? number_format($p['price'], 2) : '—'; ?></td>
                            <td class="debt"><?php echo number_format($p['total_amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($p['purchase_date']); ?></td>
                            <td style="text-align:center;">
                                <a href="companies.php?delete_purchase=<?php echo $p['id']; ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this purchase record?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="empty">No purchases recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- =====================================================
     RECENT PAYMENTS (now with Note column)
===================================================== -->

<div class="card">
    <div class="card-header">
        <i class="fas fa-hand-holding-usd"></i>
        <h2>Recent Payments</h2>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Company</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Note</th>
                    <th style="text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($recent_payments) > 0): ?>
                    <?php while ($p = mysqli_fetch_assoc($recent_payments)): ?>
                        <tr>
                            <td><?php echo $p['id']; ?></td>
                            <td class="company-name"><?php echo htmlspecialchars($p['company_name'] ?? '—'); ?></td>
                            <td class="paid"><?php echo number_format($p['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($p['payment_date']); ?></td>
                            <td>
                                <?php if (!empty($p['note'])): ?>
                                    <span class="note-cell" title="<?php echo htmlspecialchars($p['note']); ?>">
                                        <?php echo htmlspecialchars($p['note']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="note-cell muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <a href="companies.php?delete_payment=<?php echo $p['id']; ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this payment record?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="empty">No payments recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>

</html>