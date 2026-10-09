<?php
// view_sale.php - TWO-COPY WHOLESALE INVOICE
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// For testing - auto-login if not set
if(!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'ali';
}

// Get the sale ID
$sale_id = 0;
if(isset($_GET['id'])) {
    $id_value = $_GET['id'];

    if($id_value === 'null' || $id_value === null) {
        die("<h2>Error: Sale ID is 'null' (string)</h2>");
    }

    $sale_id = (int)$id_value;

    if($sale_id <= 0) {
        die("<h2>Error: Invalid Sale ID</h2>");
    }
} else {
    die("<h2>Error: No Sale ID Provided</h2>");
}

// Fetch the sale
$stmt = $conn->prepare("SELECT * FROM sales WHERE sale_id = ?");
if(!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    die("<h2>Error: Sale #$sale_id not found in database.</h2>");
}

$sale = $result->fetch_assoc();

// Get sale items
$items = [];
$itemsStmt = $conn->prepare("
    SELECT si.*, m.name as medicine_name, m.price_out as medicine_price
    FROM sale_items si
    JOIN medicines m ON si.medicine_id = m.id
    WHERE si.sale_id = ?
");

if($itemsStmt) {
    $itemsStmt->bind_param("i", $sale_id);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    while($item = $itemsResult->fetch_assoc()) {
        $items[] = $item;
    }
    $itemsStmt->close();
} else {
    die("Error preparing items query: " . $conn->error);
}

// Get user who made the sale
$user = ['username' => 'Unknown'];
$userStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
if($userStmt) {
    $userStmt->bind_param("i", $sale['user_id']);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    if($userData = $userResult->fetch_assoc()) {
        $user = $userData;
    }
    $userStmt->close();
}

// Calculate paid amount
$paid_amount = 0;
$balanceStmt = $conn->prepare("SELECT COALESCE(SUM(payment_amount), 0) as paid_amount FROM payments WHERE sale_id = ?");
if($balanceStmt) {
    $balanceStmt->bind_param("i", $sale_id);
    $balanceStmt->execute();
    $balanceResult = $balanceStmt->get_result();
    if($balanceData = $balanceResult->fetch_assoc()) {
        $paid_amount = $balanceData['paid_amount'] ?? 0;
    }
    $balanceStmt->close();
}

$total_amount = $sale['total_amount'] ?? 0;
$balance = $total_amount - $paid_amount;

// Calculate totals
$items_total = 0;
$total_discount_display = 0;
$before_discount_total = 0;
foreach($items as $item) {
    $quantity = $item['quantity'] ?? 0;
    $unit_price = $item['price_out'] ?? 0;
    $discount_per_unit = $item['bonus_or_discount'] ?? 0;
    $discount_total = $discount_per_unit * $quantity;
    $subtotal = ($unit_price * $quantity) - $discount_total;

    $items_total += $subtotal;
    $total_discount_display += $discount_total;
    $before_discount_total += ($unit_price * $quantity);
}

// Additional fees
$additional_fees = 0;
$additional_fees += isset($sale['doctor_fee']) ? $sale['doctor_fee'] : 0;
$additional_fees += isset($sale['operation_fee']) ? $sale['operation_fee'] : 0;
$additional_fees += isset($sale['other_fee']) ? $sale['other_fee'] : 0;

// Split items into two columns if there are more than 10
$split_items = count($items) > 10;
$half = ceil(count($items) / 2);
$left_items = $split_items ? array_slice($items, 0, $half) : $items;
$right_items = $split_items ? array_slice($items, $half) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $sale_id; ?> - MUJAHID KARWAN MEDICINE WHOLE SELLER</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a2980;
            --secondary-color: #26d0ce;
            --success-color: #4CAF50;
            --warning-color: #FF9800;
            --danger-color: #f44336;
            --text-color: #222;
            --border-color: #d8dde5;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: #eef1f5;
            color: var(--text-color);
            font-size: 11px;
            padding: 20px;
        }

        /* ===== Screen Controls ===== */
        .print-controls {
            background: #fff;
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            max-width: 900px;
            margin: 0 auto 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .print-controls h1 {
            font-size: 16px;
            color: var(--primary-color);
            margin-right: auto;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn {
            padding: 9px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary { background: var(--primary-color); color: #fff; }
        .btn-primary:hover { background: #14206a; }
        .btn-secondary { background: #eef1f5; color: #444; }
        .btn-secondary:hover { background: #e2e7ee; }
        .btn-secondary-outline { background: #fff; color: var(--primary-color); border: 1px solid var(--primary-color); }
        .btn-secondary-outline:hover { background: #f5f7fa; }

        /* ===== Invoice Wrapper ===== */
        .invoice-page {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 22px;
            padding: 8mm 10mm;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: relative;
            border-radius: 4px;
        }

        /* ===== Copy Badge ===== */
        .copy-badge {
            position: absolute;
            top: 8mm;
            right: 10mm;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.5px;
            padding: 4px 10px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .copy-badge.pharmacy {
            background: var(--primary-color);
            color: #fff;
        }
        .copy-badge.customer {
            background: var(--secondary-color);
            color: #fff;
        }

        /* ===== Header ===== */
        .inv-header {
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 2.5px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .inv-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            flex-shrink: 0;
            border-radius: 8px;
            background: #f7f9fc;
            padding: 4px;
            border: 1px solid var(--border-color);
        }

        .inv-brand {
            flex: 1;
            min-width: 0;
        }

        .inv-brand h1 {
            font-size: 20px;
            color: var(--primary-color);
            font-weight: 800;
            letter-spacing: 0.3px;
            line-height: 1.15;
            margin-bottom: 6px;
        }

        .inv-brand .tagline {
            font-size: 10px;
            color: var(--secondary-color);
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        /* Contact info in two columns to save space */
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3px 14px;
            font-size: 9.5px;
            color: #444;
            line-height: 1.45;
        }

        .contact-grid .item {
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .contact-grid .item i {
            color: var(--secondary-color);
            width: 12px;
            flex-shrink: 0;
            text-align: center;
            font-size: 9px;
        }

        .contact-grid .item strong {
            color: var(--primary-color);
            font-weight: 700;
        }

        /* ===== Invoice Meta Row ===== */
        .inv-meta-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px dashed var(--border-color);
        }

        .meta-block {
            font-size: 10px;
            line-height: 1.6;
        }

        .meta-block .label {
            color: #777;
            font-weight: 500;
        }

        .meta-block .value {
            color: var(--text-color);
            font-weight: 700;
        }

        .invoice-no {
            text-align: right;
        }

        .invoice-no .big {
            font-size: 20px;
            font-weight: 900;
            color: var(--primary-color);
            letter-spacing: 1px;
            line-height: 1;
            margin-bottom: 3px;
        }

        .invoice-no .sub {
            font-size: 9px;
            color: #777;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ===== Party Info ===== */
        .party-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 10px;
        }

        .party-box {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 8px 10px;
            background: #fafbfd;
        }

        .party-box .title {
            font-size: 9px;
            font-weight: 800;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 1px;
            padding-bottom: 4px;
            margin-bottom: 6px;
            border-bottom: 1px solid var(--border-color);
        }

        .party-box .row {
            display: flex;
            font-size: 10.5px;
            margin-bottom: 2px;
        }

        .party-box .row .lbl {
            width: 32mm;
            color: #777;
        }

        .party-box .row .val {
            flex: 1;
            font-weight: 600;
        }

        /* ===== Items Table ===== */
        .items-title {
            font-size: 10px;
            font-weight: 800;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .items-title i {
            color: var(--secondary-color);
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 10px;
        }

        .items-table th {
            background: var(--primary-color);
            color: #fff;
            padding: 6px 6px;
            text-align: left;
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid var(--primary-color);
        }

        .items-table td {
            padding: 5px 6px;
            border: 1px solid var(--border-color);
            vertical-align: top;
        }

        .items-table tbody tr:nth-child(even) {
            background: #f8fafd;
        }

        .items-table tbody tr:hover {
            background: #f0f5fc;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .medicine-name { font-weight: 600; color: #222; }
        .discount { color: var(--danger-color); font-weight: 600; }

        /* Two-column items layout */
        .items-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }

        .items-two-col .items-table {
            margin-bottom: 0;
        }

        /* ===== Summary ===== */
        .summary-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 10px;
        }

        .summary-box {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 10px 12px;
        }

        .summary-box .title {
            font-size: 9px;
            font-weight: 800;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 1px;
            padding-bottom: 4px;
            margin-bottom: 6px;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-box .line {
            display: flex;
            justify-content: space-between;
            font-size: 10.5px;
            margin-bottom: 3px;
        }

        .summary-box .line .lbl { color: #666; }
        .summary-box .line .val { font-weight: 600; }

        .summary-box .grand {
            border-top: 1.5px solid var(--primary-color);
            margin-top: 5px;
            padding-top: 5px;
            font-size: 12px;
            font-weight: 800;
            color: var(--primary-color);
        }

        .balance-alert {
            background: #fff4e5;
            border: 1px solid #ffd9a0;
            border-radius: 5px;
            padding: 6px 10px;
            font-size: 10px;
            color: #8a5a00;
            font-weight: 700;
            text-align: center;
            margin-top: 6px;
        }

        /* ===== Footer ===== */
        .inv-footer {
            border-top: 2px solid var(--border-color);
            padding-top: 8px;
            margin-top: 10px;
            font-size: 9.5px;
            color: #666;
            text-align: center;
            line-height: 1.6;
        }

        .inv-footer .thank {
            font-size: 11px;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 3px;
        }

        /* ===== Signatures ===== */
        .sign-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30mm;
            margin-top: 14mm;
            padding-top: 6px;
        }

        .sign-box {
            text-align: center;
            position: relative;
        }

        .sign-box::before {
            content: '';
            position: absolute;
            top: -4mm;
            left: 0;
            right: 0;
            border-top: 1px solid #333;
        }

        .sign-box .lbl {
            font-size: 10px;
            color: #555;
            font-weight: 600;
        }

        /* ===== Page Break between copies ===== */
        .page-break {
            page-break-before: always;
            height: 0;
        }

        /* ===== Print Rules ===== */
        @media print {
            body {
                background: #fff;
                padding: 0;
                font-size: 10px;
            }

            .print-controls { display: none !important; }

            .invoice-page {
                box-shadow: none;
                margin: 0;
                border-radius: 0;
                width: 100%;
                min-height: auto;
                padding: 0;
                page-break-after: always;
            }

            .invoice-page:last-child {
                page-break-after: auto;
            }

            .items-table tbody tr:nth-child(even) {
                background: #f5f5f5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .items-table th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            @page {
                size: A4;
                margin: 8mm;
            }
        }
    </style>
</head>
<body>

<!-- ===== On-screen controls ===== -->
<div class="print-controls">
    <h1>
        <i class="fas fa-file-invoice"></i>
        Invoice #<?php echo str_pad($sale_id, 6, '0', STR_PAD_LEFT); ?>
    </h1>
    <button class="btn btn-primary" onclick="window.print()">
        <i class="fas fa-print"></i> Print Both Copies
    </button>
    <button class="btn btn-secondary-outline" onclick="window.print()">
        <i class="fas fa-file-alt"></i> Print
    </button>
    <a href="javascript:history.back()" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<?php
// =========================================================
// Helper function to render a complete invoice copy
// =========================================================
function render_invoice($copy_type, $sale_id, $sale, $items, $user, $paid_amount, $balance,
                        $total_amount, $before_discount_total, $total_discount_display,
                        $additional_fees, $left_items, $right_items, $split_items) {
?>
    <div class="invoice-page">

        <!-- Copy Badge -->
        <div class="copy-badge <?php echo $copy_type === 'pharmacy' ? 'pharmacy' : 'customer'; ?>">
            <?php echo $copy_type === 'pharmacy' ? 'Pharmacy Copy' : 'Customer Copy'; ?>
        </div>

        <!-- ===== Header with Logo + Contact Grid ===== -->
        <div class="inv-header">
            <img src="WhatsApp Image 2026-09-30 at 9.05.16 AM.jpeg" alt="Logo" class="inv-logo"
                 onerror="this.onerror=null; this.style.display='none';">
            <div class="inv-brand">
                <h1>MUJAHID KARWAN MEDICINE WHOLE SELLER</h1>
                <div class="tagline">Wholesale Medicine Supplier</div>

                <div class="contact-grid">
                    <div class="item">
                        <i class="fas fa-user"></i>
                        <strong>Mujahid Ullah Ahmadzai</strong>
                    </div>
                    <div class="item">
                        <i class="fas fa-phone"></i>
                        <span>0789555455</span>
                    </div>

                    <div class="item">
                        <i class="fas fa-user"></i>
                        <strong>Shafiq Ullah Ahmadzai</strong>
                    </div>
                    <div class="item">
                        <i class="fas fa-phone"></i>
                        <span>0764144019   <i class="fab fa-whatsapp"></i>
                        <span>WhatsApp: 0789561879</span></span>
                    </div>

                    
                  

                    <div class="item">
                        <i class="fas fa-user-md"></i>
                        <strong>Pharmacist: Rafiullah Ahmadzai</strong>
                    </div>
                    <div class="item">
                        <i class="fas fa-phone"></i>
                        <span>0773146979    <i class="fas fa-phone"></i>
                        <span>0778191930   </span>  </span>
                    </div>
                      <div class="item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>ارزان قیمت چاراهی بتخاک بنارس مارکیت</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Meta Row ===== -->
        <div class="inv-meta-row">
            <div class="meta-block">
                <div><span class="label">Date:</span> <span class="value"><?php echo date('d/m/Y', strtotime($sale['sale_date'] ?? 'now')); ?></span></div>
                <div><span class="label">Time:</span> <span class="value"><?php echo date('h:i A', strtotime($sale['sale_date'] ?? 'now')); ?></span></div>
                <div><span class="label">Cashier:</span> <span class="value"><?php echo htmlspecialchars($user['username']); ?></span></div>
            </div>
            <div class="invoice-no">
                <div class="big">#<?php echo str_pad($sale_id, 6, '0', STR_PAD_LEFT); ?></div>
                <div class="sub">Tax Invoice</div>
            </div>
        </div>

        <!-- ===== Customer + Seller ===== -->
        <div class="party-row">
            <div class="party-box">
                <div class="title"><i class="fas fa-user"></i> Bill To</div>
                <div class="row"><div class="lbl">Customer:</div><div class="val"><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></div></div>
                <div class="row"><div class="lbl">Invoice #:</div><div class="val"><?php echo str_pad($sale_id, 6, '0', STR_PAD_LEFT); ?></div></div>
                <div class="row"><div class="lbl">Date:</div><div class="val"><?php echo date('d/m/Y', strtotime($sale['sale_date'] ?? 'now')); ?></div></div>
            </div>
            <div class="party-box">
                <div class="title"><i class="fas fa-store"></i> Seller</div>
                <div class="row"><div class="lbl">Company:</div><div class="val">MUJAHID KARWAN MEDICINE</div></div>
                <div class="row"><div class="lbl">Type:</div><div class="val">Whole Seller</div></div>
                <div class="row"><div class="lbl">Location:</div><div class="val">Shamshaad Street, 3rd Mosque</div></div>
            </div>
        </div>

        <!-- ===== Items ===== -->
        <div class="items-title"><i class="fas fa-pills"></i> Items / Medicines (<?php echo count($items); ?>)</div>

        <?php if ($split_items && count($right_items) > 0): ?>
            <!-- Two-column layout for many items -->
            <div class="items-two-col">
                <?php render_items_table($left_items); ?>
                <?php render_items_table($right_items); ?>
            </div>
        <?php else: ?>
            <?php render_items_table($items); ?>
        <?php endif; ?>

        <!-- ===== Summary ===== -->
        <div class="summary-row">
            <div class="summary-box">
                <div class="title"><i class="fas fa-calculator"></i> Amount Breakdown</div>
                <div class="line">
                    <span class="lbl">Items Total:</span>
                    <span class="val">$<?php echo number_format($before_discount_total, 2); ?></span>
                </div>
                <?php if($total_discount_display > 0): ?>
                <div class="line">
                    <span class="lbl">Total Discount:</span>
                    <span class="val discount">- $<?php echo number_format($total_discount_display, 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if($additional_fees > 0): ?>
                <div class="line">
                    <span class="lbl">Additional Fees:</span>
                    <span class="val">+ $<?php echo number_format($additional_fees, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="line grand">
                    <span>TOTAL:</span>
                    <span>$<?php echo number_format($total_amount, 2); ?></span>
                </div>
            </div>

            <div class="summary-box">
                <div class="title"><i class="fas fa-money-bill-wave"></i> Payment Summary</div>
                <div class="line">
                    <span class="lbl">Total Amount:</span>
                    <span class="val">$<?php echo number_format($total_amount, 2); ?></span>
                </div>
                <div class="line">
                    <span class="lbl">Paid Amount:</span>
                    <span class="val">$<?php echo number_format($paid_amount, 2); ?></span>
                </div>
                <div class="line grand" style="color: <?php echo $balance > 0 ? 'var(--danger-color)' : 'var(--success-color)'; ?>;">
                    <span>Balance:</span>
                    <span>$<?php echo number_format(abs($balance), 2); ?></span>
                </div>
                <?php if($balance > 0): ?>
                <div class="balance-alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    Outstanding: $<?php echo number_format($balance, 2); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Footer ===== -->
        <div class="inv-footer">
            <div class="thank">Thank you for your business!</div>
            <div>For any queries, please contact: 0789555455 / 0789561879 / 0778191930</div>
            <div>Goods once sold are not returnable unless defective.</div>
            <div style="margin-top: 4px; font-size: 8.5px; color: #999;">
                This is a computer-generated invoice, no signature required.
            </div>
        </div>

        <!-- ===== Signatures ===== -->
        <div class="sign-row">
            <div class="sign-box"><div class="lbl">Customer Signature</div></div>
            <div class="sign-box"><div class="lbl">Authorized Signature</div></div>
        </div>

    </div>
<?php
}

// =========================================================
// Helper: render a compact items table (used for both layouts)
// =========================================================
function render_items_table($items) {
    if (count($items) === 0) return;
?>
    <table class="items-table">
        <thead>
            <tr>
                <th width="42%">Description</th>
                <th width="10%" class="text-center">Qty</th>
                <th width="16%" class="text-right">Unit</th>
                <th width="14%" class="text-right">Disc.</th>
                <th width="18%" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($items as $item):
                $quantity = $item['quantity'] ?? 0;
                $unit_price = $item['price_out'] ?? 0;
                $discount_per_unit = $item['bonus_or_discount'] ?? 0;
                $discount_total = $discount_per_unit * $quantity;
                $subtotal = ($unit_price * $quantity) - $discount_total;
            ?>
            <tr>
                <td class="medicine-name"><?php echo htmlspecialchars($item['medicine_name'] ?? 'N/A'); ?></td>
                <td class="text-center"><?php echo $quantity; ?></td>
                <td class="text-right">$<?php echo number_format($unit_price, 2); ?></td>
                <td class="text-right discount">- $<?php echo number_format($discount_total, 2); ?></td>
                <td class="text-right">$<?php echo number_format($subtotal, 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php
}
?>

<!-- =========================================================
     COPY 1 — PHARMACY COPY (for you)
========================================================= -->
<?php
render_invoice(
    'pharmacy', $sale_id, $sale, $items, $user, $paid_amount, $balance,
    $total_amount, $before_discount_total, $total_discount_display,
    $additional_fees, $left_items, $right_items, $split_items
);
?>

<!-- =========================================================
     COPY 2 — CUSTOMER COPY (for customer)
========================================================= -->
<div class="page-break"></div>
<?php
render_invoice(
    'customer', $sale_id, $sale, $items, $user, $paid_amount, $balance,
    $total_amount, $before_discount_total, $total_discount_display,
    $additional_fees, $left_items, $right_items, $split_items
);
?>

<script>
    // Auto-print when ?print=true is in URL
    if (window.location.search.includes('print=true')) {
        setTimeout(() => window.print(), 500);
    }

    // Ctrl+P / Cmd+P to print
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
    });
</script>
</body>
</html>