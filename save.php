<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Kabul');

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch medicines from DB
$medicines = [];
$result = $conn->query("SELECT id, name, gram, company_name, type, price_out, amount 
        FROM medicines 
        WHERE amount > 0 
        ORDER BY name");

while($row = $result->fetch_assoc()) {
    $medicines[] = $row;
}

// Fetch recent customers for autocomplete
$customers = [];
$customer_result = $conn->query("SELECT DISTINCT customer_name FROM sales ORDER BY customer_name LIMIT 50");
while($row = $customer_result->fetch_assoc()) {
    $customers[] = $row['customer_name'];
}


// =====================================================
// CUSTOMER SUMMARY (when searching a customer name)
// =====================================================

$search_customer = trim($_GET['search_customer'] ?? '');
$from_date       = trim($_GET['from_date'] ?? '');
$to_date         = trim($_GET['to_date'] ?? '');
$customer_summary = null;

if ($search_customer !== '') {

    $search_safe = mysqli_real_escape_string($conn, $search_customer);

    // ---- Build date filter clause (optional) ----
    $date_clause = "";
    $date_label  = "All time";

    if ($from_date !== '' && $to_date !== '') {
        $from_safe = mysqli_real_escape_string($conn, $from_date);
        $to_safe   = mysqli_real_escape_string($conn, $to_date);
        // Include the full "to" day
        $date_clause = " AND DATE(s.sale_date) BETWEEN '$from_safe' AND '$to_safe' ";
        $date_label  = date('d/m/Y', strtotime($from_safe)) . " → " . date('d/m/Y', strtotime($to_safe));
    } elseif ($from_date !== '') {
        $from_safe = mysqli_real_escape_string($conn, $from_date);
        $date_clause = " AND DATE(s.sale_date) >= '$from_safe' ";
        $date_label  = "From " . date('d/m/Y', strtotime($from_safe));
    } elseif ($to_date !== '') {
        $to_safe = mysqli_real_escape_string($conn, $to_date);
        $date_clause = " AND DATE(s.sale_date) <= '$to_safe' ";
        $date_label  = "Until " . date('d/m/Y', strtotime($to_safe));
    }

    // ---- Query 1: total sales amount + invoice count ----
    $sales_sql = "
        SELECT
            COUNT(*) AS total_sales,
            COALESCE(SUM(s.total_amount), 0) AS total_amount
        FROM sales s
        WHERE s.customer_name LIKE '%$search_safe%'
        $date_clause
    ";

    $sales_result = mysqli_query($conn, $sales_sql);
    $sales_row    = $sales_result ? mysqli_fetch_assoc($sales_result) : ['total_sales' => 0, 'total_amount' => 0];

    $total_sales_count  = intval($sales_row['total_sales']);
    $total_sales_amount = floatval($sales_row['total_amount']);

    // ---- Query 2: total paid for those sales ----
    $paid_sql = "
        SELECT COALESCE(SUM(p.payment_amount), 0) AS total_paid
        FROM payments p
        INNER JOIN sales s ON s.sale_id = p.sale_id
        WHERE s.customer_name LIKE '%$search_safe%'
        $date_clause
    ";

    $paid_result = mysqli_query($conn, $paid_sql);
    $paid_row    = $paid_result ? mysqli_fetch_assoc($paid_result) : ['total_paid' => 0];

    $total_paid_amount = floatval($paid_row['total_paid']);

    // ---- Unpaid = Sales − Paid ----
    $total_unpaid_amount = $total_sales_amount - $total_paid_amount;

    $customer_summary = [
        'name'         => $search_customer,
        'total_sales'  => $total_sales_count,
        'total_amount' => $total_sales_amount,
        'total_paid'   => $total_paid_amount,
        'total_unpaid' => $total_unpaid_amount,
        'date_label'   => $date_label,
        'from_date'    => $from_date,
        'to_date'      => $to_date,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Sale - PharmaCare RX</title>

    <!-- OFFLINE: no CDN links (Font Awesome + Select2 removed) -->

    <link rel="stylesheet" href="st.css">

    <style>
        /* ===== Offline table styling for medicines ===== */
        #medicinesTable {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        #medicinesTable th,
        #medicinesTable td {
            padding: 6px 8px;
            border-bottom: 1px solid #e4e8ee;
            font-size: 13px;
            text-align: left;
        }
        #medicinesTable th {
            background: #f4f6fa;
            font-weight: 700;
            color: #1a2980;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        .medicine-select,
        .price-out,
        .qty,
        .bonus {
            border: 1px solid #d5dce6;
            border-radius: 6px;
            padding: 6px;
            font-size: 13px;
            box-sizing: border-box;
        }
        .medicine-select { width: 100%; }
        .price-out { background: #f8f9fa; text-align: center; }
        .stock-amount { color: #8a97a6; font-size: 12px; }
        .subtotal { font-weight: 700; color: #1a2980; }
        .btn-remove {
            background: #e74c3c;
            color: #fff;
            border: none;
            padding: 5px 9px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 700;
        }
        .btn-remove:hover { background: #c0392b; }

        /* ===== Customer Summary Banner ===== */
        .customer-summary-banner {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fb 100%);
            border-radius: 12px;
            padding: 18px 22px;
            margin-bottom: 18px;
            box-shadow: 0 2px 10px rgba(26, 41, 128, 0.08);
            border: 1px solid #e4e8ee;
            border-left: 5px solid #1a2980;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 20px;
            animation: summarySlide 0.35s ease;
        }

        @keyframes summarySlide {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .customer-summary-banner .banner-title {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-right: 20px;
            border-right: 1px solid #e4e8ee;
            min-width: 220px;
        }

        .customer-summary-banner .banner-title .icon {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: linear-gradient(45deg, #1a2980, #26d0ce);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .customer-summary-banner .banner-title .text .label {
            font-size: 11px;
            color: #8a97a6;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .customer-summary-banner .banner-title .text .name {
            font-size: 18px;
            font-weight: 800;
            color: #1a2980;
            line-height: 1.2;
            word-break: break-word;
        }

        .customer-summary-banner .banner-title .text .date-range {
            font-size: 11px;
            color: #5a6c7d;
            font-weight: 600;
            margin-top: 2px;
        }

        .customer-summary-banner .stats-row {
            display: flex;
            gap: 28px;
            flex: 1;
            flex-wrap: wrap;
        }

        .customer-summary-banner .stat {
            min-width: 130px;
        }

        .customer-summary-banner .stat .lbl {
            font-size: 11px;
            color: #8a97a6;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .customer-summary-banner .stat .val {
            font-size: 20px;
            font-weight: 800;
            color: #1a2980;
            line-height: 1.1;
        }

        .customer-summary-banner .stat.paid .val { color: #2ecc71; }
        .customer-summary-banner .stat.unpaid .val { color: #e74c3c; }

        .customer-summary-banner .stat .sub {
            font-size: 11px;
            color: #8a97a6;
            margin-top: 2px;
        }

        /* ===== Date Range Filter ===== */
        .customer-summary-banner .date-filter {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: #f4f6fa;
            border-radius: 10px;
            flex-wrap: wrap;
        }

        .customer-summary-banner .date-filter label {
            font-size: 11px;
            font-weight: 700;
            color: #5a6c7d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .customer-summary-banner .date-filter input[type="date"] {
            border: 1px solid #d5dce6;
            border-radius: 7px;
            padding: 6px 8px;
            font-size: 13px;
            color: #1a2980;
            background: #fff;
            outline: none;
            font-weight: 600;
        }

        .customer-summary-banner .date-filter input[type="date"]:focus {
            border-color: #1a2980;
            box-shadow: 0 0 0 2px rgba(26, 41, 128, 0.1);
        }

        .customer-summary-banner .date-filter .btn-apply {
            background: linear-gradient(45deg, #1a2980, #26d0ce);
            color: #fff;
            border: none;
            border-radius: 7px;
            padding: 7px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: opacity 0.2s ease;
        }

        .customer-summary-banner .date-filter .btn-apply:hover {
            opacity: 0.9;
        }

        .customer-summary-banner .clear-btn {
            padding: 9px 16px;
            background: #eef1f5;
            color: #5a6c7d;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .customer-summary-banner .clear-btn:hover {
            background: #e2e7ee;
            color: #1a2980;
        }

        @media (max-width: 768px) {
            .customer-summary-banner {
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
            }
            .customer-summary-banner .banner-title {
                border-right: none;
                border-bottom: 1px solid #e4e8ee;
                padding-right: 0;
                padding-bottom: 12px;
            }
            .customer-summary-banner .stats-row {
                gap: 18px;
            }
            .customer-summary-banner .stat {
                min-width: calc(50% - 9px);
            }
            .customer-summary-banner .date-filter {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header with Shortcut Info -->
        <div class="page-header">
            <h1>
                ⚡
                Quick Sale - Fast Mode
            </h1>
            <div class="shortcut-info">
                <span class="shortcut-badge">⌨️ Ctrl+B: Add Medicine | Ctrl+M: Remove Last | Ctrl+L: Save Sale</span>
            </div>
            <div class="today">
                📅
                <?php echo date('F j, Y - h:i A'); ?>
            </div>
        </div>

        <!-- Compact Sale Form - Always Visible -->
        <div class="compact-sale-form">
            <form id="saleForm" method="POST" action="save_sale.php">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="items" id="itemsInput">
                <input type="hidden" name="total_amount" id="totalAmountInput">
                <input type="hidden" name="is_unpaid" id="isUnpaidInput" value="0">

                <!-- Top Bar: Customer and Quick Actions -->
                <div class="quick-top-bar">
                    <div class="customer-quick-input">
                        👤
                        <input 
                            type="text" 
                            id="customer_name" 
                            name="customer_name"
                            value="Empty"
                            placeholder="Customer name..."
                            autocomplete="off"
                            class="quick-customer-input"
                            list="customerList"
                        >
                        <datalist id="customerList">
                            <?php foreach($customers as $customer): ?>
                                <option value="<?php echo htmlspecialchars($customer); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="quick-fees">
                        <div class="fee-item">
                            🩺
                            <input type="number" step="0.01" name="doctor_fee" id="doctor_fee" 
                                   value="0" oninput="updateTotal()" placeholder="Doctor fee" class="quick-fee-input">
                        </div>
                        <div class="fee-item">
                            🏥
                            <input type="number" step="0.01" name="operation_fee" id="operation_fee" 
                                   value="0" oninput="updateTotal()" placeholder="Checkup fee" class="quick-fee-input">
                        </div>
                    </div>
                    
                    <div class="quick-totals">
                        <span class="total-label">Total:</span>
                        <span class="total-value">$<span id="totalSale">0.00</span></span>
                    </div>
                    
                    <button type="submit" class="btn-save-quick">
                        💾 Save (Ctrl+L)
                    </button>
                </div>

                <!-- Medicines Entry - Compact Row -->
                <div class="medicines-entry">
                    <table id="medicinesTable">
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Price</th>
                                <th>Bonus</th>
                                <th>Qty</th>
                                <th>Stock</th>
                                <th>Subtotal</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="medicinesTableBody">
                            <!-- Rows will be added here -->
                        </tbody>
                    </table>
                </div>

                <!-- Payment Quick Row -->
                <div class="payment-quick-row">
                    <div class="payment-methods-quick">
                        <label class="radio-quick">
                            <input type="radio" name="payment_method" value="Cash" checked>
                            <span>💵 Cash</span>
                        </label>
                        <label class="radio-quick">
                            <input type="radio" name="payment_method" value="Card">
                            <span>💳 Card</span>
                        </label>
                    </div>
                    
                    <div class="payment-inputs">
                        <div class="paid-input">
                            💲
                            <input type="number" step="0.01" name="paid_amount" id="paid_amount" 
                                   value="0" oninput="calculateBalance()" placeholder="Paid amount">
                        </div>
                        <div class="balance-display">
                            <span>Balance: $<span id="balance">0.00</span></span>
                        </div>
                    </div>
                    
                    <button type="button" class="btn-clear-all" onclick="clearAllRows()">
                        🗑️ Clear All
                    </button>
                </div>

                <!-- Hidden inputs -->
                <input type="hidden" name="sale_date" value="<?php echo date('Y-m-d H:i:s'); ?>">
                <input type="hidden" name="note" value="Quick sale">
                <input type="hidden" name="payment_note" value="">
            </form>
        </div>

        <!-- Scrollable Sales History Section -->
        <div class="sales-history-scrollable">
            <div class="history-header">
                <h3>🕐 Recent Sales</h3>
                <div class="history-controls">
                    <form method="GET" id="searchFormTop" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                        <input 
                            type="text" 
                            name="search_customer"
                            id="searchSales" 
                            value="<?php echo htmlspecialchars($search_customer); ?>"
                            placeholder="Search customer..." 
                            class="history-search"
                        >
                        <input 
                            type="hidden" 
                            name="from_date" 
                            id="fromDateTop"
                            value="<?php echo htmlspecialchars($from_date); ?>"
                        >
                        <input 
                            type="hidden" 
                            name="to_date" 
                            id="toDateTop"
                            value="<?php echo htmlspecialchars($to_date); ?>"
                        >
                        <button type="submit" class="btn-refresh" title="Search">
                            🔍
                        </button>
                        <?php if ($search_customer !== '' || $from_date !== '' || $to_date !== ''): ?>
                            <a href="new_sale.php" class="btn-refresh" title="Clear">
                                ✕
                            </a>
                        <?php endif; ?>
                    </form>
                    <select id="filterStatus" class="history-filter">
                        <option value="all">All</option>
                        <option value="completed">Completed</option>
                        <option value="unpaid">Unpaid</option>
                    </select>
                    <button type="button" id="refreshSales" class="btn-refresh">
                        🔄
                    </button>
                </div>
            </div>

            <!-- =====================================================
                 CUSTOMER SUMMARY BANNER (only when searching)
            ===================================================== -->
            <?php if ($customer_summary !== null): ?>
                <div class="customer-summary-banner">
                    <div class="banner-title">
                        <div class="icon">
                            👤
                        </div>
                        <div class="text">
                            <div class="label">Customer</div>
                            <div class="name"><?php echo htmlspecialchars($customer_summary['name']); ?></div>
                            <div class="date-range">
                                📅
                                <?php echo htmlspecialchars($customer_summary['date_label']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="stats-row">
                        <!-- Total Sales (money) -->
                        <div class="stat">
                            <div class="lbl">Total Sales</div>
                            <div class="val"><?php echo number_format($customer_summary['total_amount'], 2); ?></div>
                            <div class="sub"><?php echo $customer_summary['total_sales']; ?> invoices</div>
                        </div>

                        <!-- Total Paid (money, green) -->
                        <div class="stat paid">
                            <div class="lbl">Total Paid</div>
                            <div class="val"><?php echo number_format($customer_summary['total_paid'], 2); ?></div>
                            <div class="sub">Cash collected</div>
                        </div>

                        <!-- Total Unpaid (money, red) -->
                        <div class="stat unpaid">
                            <div class="lbl">Total Unpaid</div>
                            <div class="val"><?php echo number_format($customer_summary['total_unpaid'], 2); ?></div>
                            <div class="sub">Outstanding balance</div>
                        </div>
                    </div>

                    <!-- Date Range Filter -->
                    <form method="GET" class="date-filter" id="dateFilterForm">
                        <input type="hidden" name="search_customer" value="<?php echo htmlspecialchars($search_customer); ?>">
                        <label>📅 From</label>
                        <input 
                            type="date" 
                            name="from_date" 
                            id="fromDateInput"
                            value="<?php echo htmlspecialchars($from_date); ?>"
                        >
                        <label>To</label>
                        <input 
                            type="date" 
                            name="to_date" 
                            id="toDateInput"
                            value="<?php echo htmlspecialchars($to_date); ?>"
                        >
                        <button type="submit" class="btn-apply">
                            🔽 Apply
                        </button>
                        <?php if ($from_date !== '' || $to_date !== ''): ?>
                            <a href="?search_customer=<?php echo urlencode($search_customer); ?>" class="clear-btn" style="padding: 7px 12px; font-size: 12px;">
                                ✕ Reset
                            </a>
                        <?php endif; ?>
                    </form>

                    <a href="save.php" class="clear-btn">
                        ✕ Clear
                    </a>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-mini" id="salesStats">
                <div class="stat-mini">
                    <span class="stat-label">Total</span>
                    <span class="stat-value" id="statTotal">0</span>
                </div>
                <div class="stat-mini">
                    <span class="stat-label">Completed</span>
                    <span class="stat-value" id="statCompleted">0</span>
                </div>
                <div class="stat-mini">
                    <span class="stat-label">Unpaid</span>
                    <span class="stat-value" id="statUnpaid">0</span>
                </div>
                <div class="stat-mini">
                    <span class="stat-label">Amount</span>
                    <span class="stat-value">$<span id="statAmount">0.00</span></span>
                </div>
            </div>

            <!-- Sales Table - This is the ONLY scrollable part -->
            <div class="history-table-container">
                <table id="salesHistoryTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="salesHistoryBody">
                        <!-- Data will be loaded here -->
                    </tbody>
                </table>
                
                <div class="loading" id="salesLoading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                
                <div class="empty-state" id="salesEmpty">
                    📋
                    <h3>No Sales Found</h3>
                </div>
            </div>

            <!-- Pagination -->
            <div class="pagination-mini" id="salesPagination"></div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal-content">
            💵
            <h3>Add Payment</h3>
            <p>Enter payment details for sale #<span id="paymentSaleId"></span></p>
            
            <div class="form-group">
                <label class="form-label">Payment Amount ($)</label>
                <input type="number" step="0.01" min="0.01" id="paymentAmount" 
                       class="form-input" placeholder="Enter amount" autofocus>
            </div>
            
            <div class="form-group">
                <label class="form-label">Payment Method</label>
                <select id="paymentMethod" class="form-select">
                    <option value="Cash">Cash</option>
                    <option value="Card">Card</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closePaymentModal()">
                    ✕ Cancel
                </button>
                <button class="btn btn-primary" onclick="submitPayment()">
                    ✓ Record
                </button>
            </div>
        </div>
    </div>

    <script>
        // ============ DATA FROM PHP ============
        window.medicineOptions = <?php echo json_encode(array_map(function($m) {
            return [
                'id'      => $m['id'],
                'name'    => $m['name'],
                'gram'    => $m['gram'],
                'type'    => $m['type'],
                'company' => $m['company_name'],
                'price'   => (float)$m['price_out'],
                'stock'   => (int)$m['amount']
            ];
        }, $medicines)); ?>;

        const SEARCH_CUSTOMER = <?php echo json_encode($search_customer); ?>;
        const FROM_DATE = <?php echo json_encode($from_date); ?>;
        const TO_DATE = <?php echo json_encode($to_date); ?>;

        // If the search box has a value, make sure the JS refresh also passes it
        document.addEventListener('DOMContentLoaded', function() {
            const refreshBtn = document.getElementById('refreshSales');

            if (refreshBtn && (SEARCH_CUSTOMER || FROM_DATE || TO_DATE)) {
                refreshBtn.addEventListener('click', function() {
                    const url = new URL(window.location.href);
                    if (SEARCH_CUSTOMER) url.searchParams.set('search_customer', SEARCH_CUSTOMER);
                    if (FROM_DATE) url.searchParams.set('from_date', FROM_DATE);
                    if (TO_DATE) url.searchParams.set('to_date', TO_DATE);
                    window.location.href = url.toString();
                });
            }
        });
    </script>

    <!-- ============ OFFLINE SCRIPT (no jQuery, no Select2) ============ -->
    <script src="sale_offline.js"></script>

</body>
</html>