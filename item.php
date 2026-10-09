<?php
require_once 'db.php';
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch low stock medicines (amount <= 2)
$low_stock_medicines = [];
$result = $conn->query("SELECT * FROM medicines WHERE amount <= 2 ORDER BY amount ASC, name ASC");
while($row = $result->fetch_assoc()) {
    $low_stock_medicines[] = $row;
}

// Count statistics
$total_medicines = $conn->query("SELECT COUNT(*) as count FROM medicines")->fetch_assoc()['count'];
$low_stock_count = count($low_stock_medicines);
$out_of_stock = $conn->query("SELECT COUNT(*) as count FROM medicines WHERE amount = 0")->fetch_assoc()['count'];
$critical_stock = $conn->query("SELECT COUNT(*) as count FROM medicines WHERE amount > 0 AND amount <= 2")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Low Stock Alert - PharmaCare RX</title>
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



        @media (max-width: 768px) {
         
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

        .page-header h1 i {
            color: var(--accent-color);
        }

        .today {
            background: rgba(255, 255, 255, 0.1);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card:nth-child(1) { 
            border-top: 4px solid var(--danger-color); 
            color: var(--danger-color);
        }
        .stat-card:nth-child(2) { 
            border-top: 4px solid var(--warning-color); 
            color: var(--warning-color);
        }
        .stat-card:nth-child(3) { 
            border-top: 4px solid var(--info-color); 
            color: var(--info-color);
        }
        .stat-card:nth-child(4) { 
            border-top: 4px solid var(--primary-color); 
            color: var(--primary-color);
        }

        /* Alert Banner */
        .alert-banner {
            background: linear-gradient(45deg, #ff6b6b, #ff8e53);
            color: white;
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: var(--shadow);
        }

        .alert-banner-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .alert-banner i {
            font-size: 1.8rem;
        }

        .alert-banner h3 {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .alert-banner p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* Table Container */
        .table-container {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .table-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--danger-color), var(--warning-color));
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-header h2 {
            color: var(--primary-color);
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-header i {
            color: var(--secondary-color);
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stock-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .stock-table thead {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .stock-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stock-table th:first-child {
            border-top-left-radius: 8px;
        }

        .stock-table th:last-child {
            border-top-right-radius: 8px;
        }

        .stock-table tbody tr {
            border-bottom: 1px solid #e0e0e0;
            transition: var(--transition);
        }

        .stock-table tbody tr:hover {
            background-color: rgba(38, 208, 206, 0.05);
        }

        .stock-table td {
            padding: 12px 15px;
            color: var(--text-color);
        }

        /* Stock status badges */
        .stock-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 70px;
        }

        .stock-out {
            background-color: #ffebee;
            color: var(--danger-color);
            border: 1px solid #ffcdd2;
        }

        .stock-low {
            background-color: #fff3e0;
            color: var(--warning-color);
            border: 1px solid #ffe0b2;
        }

        .stock-normal {
            background-color: #e8f5e9;
            color: var(--success-color);
            border: 1px solid #c8e6c9;
        }

        /* Stock amount styling */
        .stock-amount {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .stock-amount.out-of-stock {
            color: var(--danger-color);
        }

        .stock-amount.low-stock {
            color: var(--warning-color);
        }

        .stock-amount.normal-stock {
            color: var(--success-color);
        }

        /* Days to expire */
        .days-to-expire {
            font-weight: 600;
        }

        .expired {
            color: var(--danger-color);
        }

        .expiring-soon {
            color: var(--warning-color);
        }

        .expiry-ok {
            color: var(--success-color);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 41, 128, 0.3);
        }

        .btn-warning {
            background: linear-gradient(45deg, var(--warning-color), #ffb74d);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.3);
        }

        .btn-secondary {
            background: rgba(38, 208, 206, 0.1);
            color: var(--secondary-color);
            border: 1px solid var(--secondary-color);
        }

        .btn-secondary:hover {
            background: rgba(38, 208, 206, 0.2);
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            color: #e0e0e0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 1.5rem;
        }

        .empty-state p {
            font-size: 1rem;
            max-width: 500px;
            margin: 0 auto 20px;
        }

        /* Filter Controls */
        .filter-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
            z-index: 1;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
            position: relative;
        }

        .filter-select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            font-size: 0.95rem;
            min-width: 150px;
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .table-container {
            animation: fadeIn 0.4s ease;
        }

        /* Print button */
        .print-btn {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
        }

        .print-btn:hover {
            background: linear-gradient(45deg, #45a049, #4CAF50);
            transform: translateY(-2px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stock-table th, 
            .stock-table td {
                padding: 10px 8px;
                font-size: 0.85rem;
            }
            
            .btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            
            .action-buttons {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .filter-controls {
                flex-direction: column;
            }
            
            .search-box,
            .filter-select {
                min-width: 100%;
            }
            
            .alert-banner {
                padding: 12px 15px;
            }
            
            .alert-banner-content {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.3rem;
            }
            
            .table-container {
                padding: 15px;
            }
            
            .empty-state i {
                font-size: 3rem;
            }
            
            .empty-state h3 {
                font-size: 1.2rem;
            }
        }

        /* Export buttons */
        .export-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-export {
            background: linear-gradient(45deg, #9C27B0, #7B1FA2);
            color: white;
        }

        .btn-export:hover {
            background: linear-gradient(45deg, #7B1FA2, #9C27B0);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-exclamation-triangle"></i>
                Low Stock Alert
            </h1>
            <div class="today">
                <i class="far fa-calendar"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-pills"></i>
                <div class="stat-number"><?php echo $low_stock_count; ?></div>
                <div class="stat-label">Low Stock Items</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-ban"></i>
                <div class="stat-number"><?php echo $out_of_stock; ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-exclamation-circle"></i>
                <div class="stat-number"><?php echo $critical_stock; ?></div>
                <div class="stat-label">Critical Stock (≤2)</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-boxes"></i>
                <div class="stat-number"><?php echo $total_medicines; ?></div>
                <div class="stat-label">Total Medicines</div>
            </div>
        </div>

        <?php if($low_stock_count > 0): ?>
            <!-- Alert Banner -->
            <div class="alert-banner">
                <div class="alert-banner-content">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <h3>Attention Required!</h3>
                        <p>You have <?php echo $low_stock_count; ?> medicine(s) with low stock. Please restock these items as soon as possible.</p>
                    </div>
                </div>
                <div class="export-buttons">
                    <button class="btn btn-export" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </button>
                    <button class="btn btn-export" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Low Stock Table -->
        <div class="table-container">
            <div class="table-header">
                <h2><i class="fas fa-clipboard-list"></i> Low Stock Medicines (Amount ≤ 2)</h2>
                <div class="action-buttons">
                    <button class="btn btn-secondary" onclick="refreshTable()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn print-btn" onclick="printTable()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="btn btn-primary" onclick="window.location.href='medicines.php'">
                        <i class="fas fa-plus"></i> Add New Medicine
                    </button>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="filter-controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search medicines..." onkeyup="filterTable()">
                </div>
                <select class="filter-select" id="stockFilter" onchange="filterTable()">
                    <option value="all">All Low Stock</option>
                    <option value="0">Out of Stock (0)</option>
                    <option value="1">Only 1 Left</option>
                    <option value="2">Only 2 Left</option>
                </select>
                <select class="filter-select" id="sortBy" onchange="sortTable()">
                    <option value="amount-asc">Sort by: Stock (Low to High)</option>
                    <option value="amount-desc">Sort by: Stock (High to Low)</option>
                    <option value="name-asc">Sort by: Name (A-Z)</option>
                    <option value="name-desc">Sort by: Name (Z-A)</option>
                    <option value="expire-asc">Sort by: Expiry Date (Near to Far)</option>
                </select>
            </div>

            <div class="table-wrapper">
                <?php if($low_stock_count > 0): ?>
                    <table class="stock-table" id="lowStockTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Medicine Name</th>
                                <th>Company</th>
                                <th>Type/Gram</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Price In</th>
                                <th>Price Out</th>
                                <th>Expiry Date</th>
                                <th>Days Left</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach($low_stock_medicines as $medicine):
                                $current_date = new DateTime();
                                $expire_date = new DateTime($medicine['expire_date']);
                                $days_diff = $current_date->diff($expire_date)->days;
                                
                                // Determine stock status
                                $stock_class = '';
                                $status_badge = '';
                                if($medicine['amount'] == 0) {
                                    $stock_class = 'out-of-stock';
                                    $status_badge = '<span class="stock-badge stock-out">Out of Stock</span>';
                                } elseif($medicine['amount'] <= 2) {
                                    $stock_class = 'low-stock';
                                    $status_badge = '<span class="stock-badge stock-low">Low Stock</span>';
                                }
                                
                                // Determine expiry status
                                $expiry_class = '';
                                if($expire_date < $current_date) {
                                    $expiry_class = 'expired';
                                    $expiry_text = 'Expired';
                                } elseif($days_diff <= 30) {
                                    $expiry_class = 'expiring-soon';
                                    $expiry_text = $days_diff . ' days';
                                } else {
                                    $expiry_class = 'expiry-ok';
                                    $expiry_text = $days_diff . ' days';
                                }
                            ?>
                            <tr data-stock="<?php echo $medicine['amount']; ?>"
                                data-name="<?php echo htmlspecialchars(strtolower($medicine['name'])); ?>"
                                data-expire="<?php echo $medicine['expire_date']; ?>">
                                <td style="font-weight: 600; color: var(--primary-color);">#<?php echo $medicine['id']; ?></td>
                                <td style="font-weight: 500;">
                                    <?php echo htmlspecialchars($medicine['name']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($medicine['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($medicine['type'] . ' / ' . $medicine['gram']); ?></td>
                                <td>
                                    <span class="stock-amount <?php echo $stock_class; ?>">
                                        <?php echo $medicine['amount']; ?>
                                    </span>
                                </td>
                                <td><?php echo $status_badge; ?></td>
                                <td style="color: var(--info-color); font-weight: 600;">
                                    $<?php echo number_format($medicine['price_in'], 2); ?>
                                </td>
                                <td style="color: var(--success-color); font-weight: 600;">
                                    $<?php echo number_format($medicine['price_out'], 2); ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($medicine['expire_date'])); ?>
                                </td>
                                <td class="days-to-expire <?php echo $expiry_class; ?>">
                                    <?php echo $expiry_text; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                    
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle" style="color: #4CAF50;"></i>
                        <h3>All Stock Levels are Good!</h3>
                        <p>No medicines have low stock levels. All items have more than 2 units available.</p>
                        <div style="margin-top: 20px;">
                            <button class="btn btn-primary" onclick="window.location.href='all_medicines.php'">
                                <i class="fas fa-pills"></i> View All Medicines
                            </button>
                            <button class="btn btn-secondary" onclick="window.location.href='dashboard.php'">
                                <i class="fas fa-home"></i> Go to Dashboard
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Restock Section -->
        <?php if($low_stock_count > 0): ?>
        <div class="table-container">
            <div class="table-header">
                <h2><i class="fas fa-truck"></i> Quick Restock List</h2>
            </div>
            <div class="quick-restock">
                <p style="margin-bottom: 15px; color: #666;">The following medicines need immediate restocking:</p>
                <ul style="list-style: none; padding-left: 0;">
                    <?php 
                    $critical_items = array_filter($low_stock_medicines, function($m) {
                        return $m['amount'] == 0;
                    });
                    
                    if(count($critical_items) > 0): 
                        foreach($critical_items as $item): ?>
                            <li style="padding: 10px; background: #ffebee; margin-bottom: 8px; border-radius: 6px; border-left: 4px solid var(--danger-color);">
                                <i class="fas fa-exclamation-circle" style="color: var(--danger-color); margin-right: 10px;"></i>
                                <strong><?php echo htmlspecialchars($item['name']); ?></strong> - 
                                <span style="color: var(--danger-color); font-weight: 600;">OUT OF STOCK</span>
                                <span style="float: right;">
                                    <button class="btn btn-warning btn-sm" onclick="restockMedicine(<?php echo $item['id']; ?>)">
                                        <i class="fas fa-plus-circle"></i> Restock Now
                                    </button>
                                </span>
                            </li>
                        <?php endforeach; 
                    else: ?>
                        <li style="padding: 15px; text-align: center; color: #666; font-style: italic;">
                            <i class="fas fa-info-circle" style="margin-right: 10px;"></i>
                            No medicines are completely out of stock. Good job!
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Filter table function
        function filterTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const stockFilter = document.getElementById('stockFilter').value;
            const rows = document.querySelectorAll('#lowStockTable tbody tr');
            
            rows.forEach(row => {
                const medicineName = row.dataset.name;
                const stockAmount = row.dataset.stock;
                const showBySearch = medicineName.includes(searchInput);
                const showByStock = stockFilter === 'all' || stockFilter === stockAmount;
                
                row.style.display = (showBySearch && showByStock) ? '' : 'none';
            });
        }

        // Sort table function
        function sortTable() {
            const sortBy = document.getElementById('sortBy').value;
            const tbody = document.querySelector('#lowStockTable tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            rows.sort((a, b) => {
                if(sortBy === 'amount-asc') {
                    return parseFloat(a.dataset.stock) - parseFloat(b.dataset.stock);
                } else if(sortBy === 'amount-desc') {
                    return parseFloat(b.dataset.stock) - parseFloat(a.dataset.stock);
                } else if(sortBy === 'name-asc') {
                    return a.dataset.name.localeCompare(b.dataset.name);
                } else if(sortBy === 'name-desc') {
                    return b.dataset.name.localeCompare(a.dataset.name);
                } else if(sortBy === 'expire-asc') {
                    return new Date(a.dataset.expire) - new Date(b.dataset.expire);
                }
                return 0;
            });
            
            // Clear and re-append sorted rows
            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));
        }

        // Restock medicine
        function restockMedicine(medicineId) {
            const quantity = prompt('Enter quantity to add:', '10');
            if(quantity && !isNaN(quantity) && parseInt(quantity) > 0) {
                // In real implementation, make AJAX call to update stock
                fetch('restock_medicine.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `medicine_id=${medicineId}&quantity=${quantity}`
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        alert('Stock updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error. Please try again.');
                });
            }
        }

        // View medicine details
        function viewMedicine(medicineId) {
            window.open(`view_medicine.php?id=${medicineId}`, '_blank');
        }

        // Refresh table
        function refreshTable() {
            location.reload();
        }

        // Print table
        function printTable() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Low Stock Report - <?php echo date('Y-m-d'); ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th { background-color: #f2f2f2; padding: 10px; text-align: left; border: 1px solid #ddd; }
                        td { padding: 8px; border: 1px solid #ddd; }
                        .out-of-stock { color: #ff0000; font-weight: bold; }
                        .low-stock { color: #ff9900; font-weight: bold; }
                        .print-date { margin-bottom: 20px; color: #666; }
                    </style>
                </head>
                <body>
                    <h1>Low Stock Medicines Report</h1>
                    <div class="print-date">Generated on: <?php echo date('F j, Y H:i:s'); ?></div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Medicine Name</th>
                                <th>Company</th>
                                <th>Stock</th>
                                <th>Price Out</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach($low_stock_medicines as $medicine): 
                                $status = $medicine['amount'] == 0 ? 'Out of Stock' : 'Low Stock';
                                $status_class = $medicine['amount'] == 0 ? 'out-of-stock' : 'low-stock';
                            ?>
                            <tr>
                                <td>#<?php echo $medicine['id']; ?></td>
                                <td><?php echo htmlspecialchars($medicine['name']); ?></td>
                                <td><?php echo htmlspecialchars($medicine['company_name']); ?></td>
                                <td class="<?php echo $status_class; ?>"><?php echo $medicine['amount']; ?></td>
                                <td>$<?php echo number_format($medicine['price_out'], 2); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($medicine['expire_date'])); ?></td>
                                <td class="<?php echo $status_class; ?>"><?php echo $status; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Export to PDF (simplified version - in production use a library like jsPDF)
        function exportToPDF() {
            alert('PDF export functionality would require a PDF library like jsPDF.');
            // Example with jsPDF:
            // const doc = new jsPDF();
            // doc.text('Low Stock Report', 10, 10);
            // doc.save('low-stock-report.pdf');
        }

        // Export to Excel (simplified version)
        function exportToExcel() {
            // Create CSV content
            let csv = 'ID,Medicine Name,Company,Type/Gram,Stock,Status,Price In,Price Out,Expiry Date\n';
            
            <?php foreach($low_stock_medicines as $medicine): ?>
                csv += '<?php echo $medicine['id']; ?>,';
                csv += '"<?php echo addslashes($medicine['name']); ?>",';
                csv += '"<?php echo addslashes($medicine['company_name']); ?>",';
                csv += '"<?php echo addslashes($medicine['type'] . ' / ' . $medicine['gram']); ?>",';
                csv += '<?php echo $medicine['amount']; ?>,';
                csv += '"<?php echo $medicine['amount'] == 0 ? 'Out of Stock' : 'Low Stock'; ?>",';
                csv += '<?php echo $medicine['price_in']; ?>,';
                csv += '<?php echo $medicine['price_out']; ?>,';
                csv += '<?php echo $medicine['expire_date']; ?>\n';
            <?php endforeach; ?>
            
            // Create download link
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'low-stock-report-<?php echo date('Y-m-d'); ?>.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // Auto-refresh every 5 minutes
        setInterval(() => {
            if(!document.hidden) {
                location.reload();
            }
        }, 300000); // 5 minutes
    </script>
</body>
</html>