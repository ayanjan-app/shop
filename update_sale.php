<?php
require_once 'db.php';
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if sale ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: save.php");
    exit();
}

$sale_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Fetch sale details
$sale_query = $conn->prepare("SELECT * FROM sales WHERE sale_id = ? AND user_id = ?");
if (!$sale_query) {
    die("Prepare failed: " . $conn->error);
}

$sale_query->bind_param("ii", $sale_id, $user_id);
$sale_query->execute();
$sale_result = $sale_query->get_result();

if($sale_result->num_rows === 0) {
    header("Location: save.php");
    exit();
}

$sale = $sale_result->fetch_assoc();

// Fetch sale items
$items_query = $conn->prepare("
    SELECT si.*, m.name as medicine_name, m.price_out as original_price, m.amount as current_stock 
    FROM sale_items si 
    LEFT JOIN medicines m ON si.medicine_id = m.id 
    WHERE si.sale_id = ?
");
if (!$items_query) {
    die("Prepare failed: " . $conn->error);
}

$items_query->bind_param("i", $sale_id);
$items_query->execute();
$items_result = $items_query->get_result();
$sale_items = [];
while($row = $items_result->fetch_assoc()) {
    $sale_items[] = $row;
}

// Fetch all medicines for dropdown
$medicines = [];
$med_result = $conn->query("SELECT id, name, price_out, amount FROM medicines ORDER BY name");
while($row = $med_result->fetch_assoc()) {
    $medicines[] = $row;
}

// Fetch recent customers
$customers = [];
$customer_result = $conn->query("SELECT DISTINCT customer_name FROM sales ORDER BY customer_name LIMIT 50");
while($row = $customer_result->fetch_assoc()) {
    $customers[] = $row['customer_name'];
}
// Handle form submission for update
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update sale basic info
        $customer_name = $conn->real_escape_string($_POST['customer_name']);
        $sale_date = $conn->real_escape_string($_POST['sale_date']);
        $note = $conn->real_escape_string($_POST['note']);
        $doctor_fee = floatval($_POST['doctor_fee']);
        $operation_fee = floatval($_POST['operation_fee']);
        $other_fee = floatval($_POST['other_fee']);  // Changed from payment_note to other_fee
        $total_amount = floatval($_POST['total_amount']);
        $paid_amount = floatval($_POST['paid_amount']);
        $balance = floatval($_POST['balance']);
        $payment_method = $conn->real_escape_string($_POST['payment_method']);
        $payment_note = $conn->real_escape_string($_POST['payment_note']);
        $is_unpaid = $balance > 0 ? 1 : 0;
        
        // ================== PUT BACK THE ORIGINAL DYNAMIC COLUMN CHECKING ==================
        // Check if columns exist in the sales table
        $check_columns = $conn->query("SHOW COLUMNS FROM sales");
        $columns = [];
        while($col = $check_columns->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        
        // Build update query based on available columns
        $update_fields = [];
        $update_types = "";
        $update_values = [];
        
        // Always update these fields
        $update_fields[] = "customer_name = ?";
        $update_types .= "s";
        $update_values[] = $customer_name;
        
        $update_fields[] = "sale_date = ?";
        $update_types .= "s";
        $update_values[] = $sale_date;
        
        // Check and add optional fields if they exist
        if(in_array('note', $columns)) {
            $update_fields[] = "note = ?";
            $update_types .= "s";
            $update_values[] = $note;
        }
        
        if(in_array('doctor_fee', $columns)) {
            $update_fields[] = "doctor_fee = ?";
            $update_types .= "d";
            $update_values[] = $doctor_fee;
        }
        
        if(in_array('operation_fee', $columns)) {
            $update_fields[] = "operation_fee = ?";
            $update_types .= "d";
            $update_values[] = $operation_fee;
        }
        
        if(in_array('other_fee', $columns)) {
            $update_fields[] = "other_fee = ?";
            $update_types .= "d";
            $update_values[] = $other_fee;
        }
        
        $update_fields[] = "total_amount = ?";
        $update_types .= "d";
        $update_values[] = $total_amount;
        
        // Add these fields if they exist
        if(in_array('paid_amount', $columns)) {
            $update_fields[] = "paid_amount = ?";
            $update_types .= "d";
            $update_values[] = $paid_amount;
        }
        
        if(in_array('balance', $columns)) {
            $update_fields[] = "balance = ?";
            $update_types .= "d";
            $update_values[] = $balance;
        }
        
        if(in_array('payment_method', $columns)) {
            $update_fields[] = "payment_method = ?";
            $update_types .= "s";
            $update_values[] = $payment_method;
        }
        
        if(in_array('payment_note', $columns)) {
            $update_fields[] = "payment_note = ?";
            $update_types .= "s";
            $update_values[] = $payment_note;
        }
        
        if(in_array('is_unpaid', $columns)) {
            $update_fields[] = "is_unpaid = ?";
            $update_types .= "i";
            $update_values[] = $is_unpaid;
        }
        
        // Add WHERE clause values
        $update_types .= "ii";
        $update_values[] = $sale_id;
        $update_values[] = $user_id;
        
        // Build the final query
        $update_query = "UPDATE sales SET " . implode(", ", $update_fields) . " WHERE sale_id = ? AND user_id = ?";
        
        $update_sale = $conn->prepare($update_query);
        if (!$update_sale) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Bind all parameters dynamically
        $update_sale->bind_param($update_types, ...$update_values);
        
        if(!$update_sale->execute()) {
            throw new Exception("Failed to update sale: " . $update_sale->error);
        }
        
        // Handle sale items - ALLOW EMPTY ARRAY FOR REFUNDS/RETURNS
        // Always restore stock for old items first
        foreach($sale_items as $old_item) {
            $restore_stock = $conn->prepare("UPDATE medicines SET amount = amount + ? WHERE id = ?");
            if (!$restore_stock) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
          $qty = (float)$old_item['quantity'];
$restore_stock->bind_param("di", $qty, $old_item['medicine_id']);

            $restore_stock->execute();
        }
        
        // Remove old sale items
        $delete_items = $conn->prepare("DELETE FROM sale_items WHERE sale_id = ?");
        if (!$delete_items) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $delete_items->bind_param("i", $sale_id);
        $delete_items->execute();
        
        // Add new sale items if they exist
        if(isset($_POST['items']) && !empty($_POST['items'])) {
            $items = json_decode($_POST['items'], true);
            
            foreach($items as $item) {
                // Check stock
                $check_stock = $conn->prepare("SELECT amount FROM medicines WHERE id = ?");
                if (!$check_stock) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
             $check_stock->bind_param("i", $item['medicine_id']);

                $check_stock->execute();
                $stock_result = $check_stock->get_result();
                $stock = $stock_result->fetch_assoc()['amount'];
                
                if($stock < $item['quantity']) {
                    throw new Exception("Insufficient stock for " . $item['medicine_name'] . ". Available: " . $stock);
                }
                
                // Insert sale item
                $insert_item = $conn->prepare("
                    INSERT INTO sale_items (sale_id, medicine_id, quantity, price_out, bonus_or_discount, subtotal) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                if (!$insert_item) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $subtotal = $item['quantity'] * ($item['price_out'] - $item['bonus_or_discount']);
              $qty = (float)$item['quantity'];

$insert_item->bind_param("iidddd", $sale_id, $item['medicine_id'], $qty,

                    $item['price_out'], $item['bonus_or_discount'], $subtotal);
                $insert_item->execute();
                
                // Update stock (deduct for new items)
                $update_stock = $conn->prepare("UPDATE medicines SET amount = amount - ? WHERE id = ?");
                if (!$update_stock) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
              $qty = (float)$item['quantity'];
$update_stock->bind_param("di", $qty, $item['medicine_id']);

                $update_stock->execute();
            }
        }
        // If items array is empty, the sale will have no medicines (full refund)
        
        $conn->commit();
        
        // Redirect back to new sale page with success message
        $_SESSION['success_message'] = "Sale #" . $sale_id . " has been updated successfully!";
        header("Location: save.php");
        exit();
        
    } catch(Exception $e) {
        $conn->rollback();
        $error_message = "Error updating sale: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Sale - PharmaCare RX</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Update Sale Styles - Matching Medicines Page */
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
            background: linear-gradient(45deg, var(--warning-color), #f57c00);
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
            color: white;
        }

        .today {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        /* Alert Messages */
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.3s ease;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Form Container */
        .form-container {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--warning-color), #f57c00);
        }

        .form-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }

        .form-header h2 {
            color: var(--warning-color);
            font-size: 1.3rem;
        }

        .form-header i {
            color: var(--warning-color);
            font-size: 1.3rem;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            color: var(--warning-color);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input, .form-select, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: var(--card-bg);
            font-size: 0.95rem;
            color: var(--text-color);
            transition: var(--transition);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            resize: vertical;
        }

        .form-input:focus, .form-select:focus, textarea:focus {
            outline: none;
            border-color: var(--warning-color);
            box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.15);
        }

        .form-input-group {
            position: relative;
        }

        .form-input-group i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--warning-color);
            font-size: 1rem;
        }

        .form-input-group .form-input,
        .form-input-group textarea,
        .form-input-group .form-select {
            padding-left: 40px;
        }

        .form-input-group textarea {
            padding-top: 10px;
            min-height: 50px;
        }

        /* Custom dropdown arrow */
        .form-select {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23FF9800' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }

        /* Fees Section */
        .section-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }

        .section-header h3 {
            color: var(--warning-color);
            font-size: 1.2rem;
            flex: 1;
        }

        .section-header i {
            color: var(--warning-color);
            font-size: 1.1rem;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            white-space: nowrap;
            min-height: 42px;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--warning-color), #f57c00);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 152, 0, 0.2);
            background: linear-gradient(45deg, #f57c00, var(--warning-color));
        }

        .btn-secondary {
            background: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
            border: 2px solid var(--warning-color);
        }

        .btn-secondary:hover {
            background: rgba(255, 152, 0, 0.2);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(45deg, var(--success-color), #43A047);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(45deg, #43A047, var(--success-color));
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
            min-height: 36px;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            position: relative;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            min-width: 1100px;
        }

        thead {
            background: linear-gradient(45deg, var(--warning-color), #e68900);
            color: white;
        }

        th {
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
            position: relative;
            font-size: 0.85rem;
        }

        th:not(:last-child)::after {
            content: '';
            position: absolute;
            right: 0;
            top: 20%;
            height: 60%;
            width: 1px;
            background: rgba(255, 255, 255, 0.3);
        }

        tbody tr {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }

        tbody tr:hover {
            background: rgba(255, 152, 0, 0.05);
        }

        td {
            padding: 12px;
            color: var(--text-color);
            white-space: nowrap;
            vertical-align: middle;
        }

        td .form-input {
            padding: 6px 8px;
            font-size: 0.85rem;
            border-radius: 6px;
            min-width: 60px;
            text-align: center;
        }

        td .form-select {
            padding: 6px 30px 6px 8px;
            font-size: 0.85rem;
            height: 36px;
            background-size: 14px;
            background-position: right 8px center;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 2.5rem;
            color: #ddd;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            font-size: 1.1rem;
            margin-bottom: 8px;
            color: var(--warning-color);
        }

        .empty-state p {
            font-size: 0.9rem;
        }

        /* Totals Card */
        .totals-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            border-left: 4px solid var(--warning-color);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 16px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #ccc;
        }

        .total-row .amount {
            font-weight: 600;
            color: #2c3e50;
        }

        .grand-total {
            font-size: 20px;
            font-weight: bold;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 2px solid #ccc;
        }

        .grand-total .amount {
            color: var(--warning-color);
            font-size: 24px;
        }

        /* Payment Section */
        .payment-section {
            background: #fff8e1;
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            border: 2px dashed var(--warning-color);
        }

        .payment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }

        .payment-methods h4 {
            margin-bottom: 12px;
            color: var(--warning-color);
            font-size: 1rem;
        }

        .radio-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .radio-label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .radio-label input[type="radio"] {
            display: none;
        }

        .radio-custom {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 15px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 6px;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .radio-label input[type="radio"]:checked + .radio-custom {
            background: rgba(255, 152, 0, 0.1);
            border-color: var(--warning-color);
            color: var(--warning-color);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Stock Warning */
        .stock-warning {
            color: var(--danger-color);
            font-size: 0.75rem;
            margin-top: 4px;
            display: none;
        }

        .stock-warning i {
            margin-right: 4px;
            font-size: 0.8rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            body {
                font-size: 13px;
            }
            
         
            
            .form-container {
                padding: 18px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                padding: 12px 15px;
            }
            
            .table-container {
                margin: 0 -12px;
                padding: 0 12px;
            }
            
            table {
                min-width: 900px;
                font-size: 0.85rem;
            }
            
            th, td {
                padding: 10px 8px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .payment-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.3rem;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            table {
                min-width: 800px;
            }
            
            td {
                padding: 8px 6px;
            }
            
            td .form-input,
            td .form-select {
                padding: 5px 6px;
                font-size: 0.8rem;
            }
            
            .form-input, .form-select, textarea {
                font-size: 0.9rem;
                padding: 8px 10px;
            }
        }

        /* Custom Scrollbar */
        .table-container::-webkit-scrollbar {
            height: 6px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 8px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: var(--warning-color);
            border-radius: 8px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #e68900;
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-container, .section-card {
            animation: fadeIn 0.4s ease;
        }

        /* Print Styles */
        @media print {
            .sidebar, .action-buttons {
                display: none;
            }
            
         
            table {
                font-size: 11px;
                min-width: auto;
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
                <i class="fas fa-edit"></i>
                Update Sale #<?php echo $sale_id; ?>
            </h1>
            <div class="today">
                <i class="far fa-calendar"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success_message']; ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <!-- Form Container -->
        <div class="form-container">
            <form id="updateSaleForm" method="POST" action="">
                <div class="form-header">
                    <i class="fas fa-edit"></i>
                    <h2>Update Sale Information</h2>
                </div>

                <!-- Basic Information -->
                <div class="form-grid">
                    <!-- Customer Name -->
                    <div class="form-group">
                        <label class="form-label">Customer Name *</label>
                        <div class="form-input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" class="form-input" id="customer_name" name="customer_name" required 
                                   placeholder="Enter customer name" list="customerList"
                                   autocomplete="off" value="<?php echo htmlspecialchars($sale['customer_name']); ?>">
                            <datalist id="customerList">
                                <?php foreach($customers as $customer): ?>
                                    <option value="<?php echo htmlspecialchars($customer); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <!-- Sale Date -->
                    <div class="form-group">
                        <label class="form-label">Sale Date & Time</label>
                        <div class="form-input-group">
                            <i class="far fa-calendar-alt"></i>
                            <input type="datetime-local" class="form-input" id="sale_date" name="sale_date" 
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($sale['sale_date'])); ?>">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="form-group full-width">
                        <label class="form-label">Notes (Optional)</label>
                        <div class="form-input-group">
                            <i class="fas fa-sticky-note"></i>
                            <textarea id="note" name="note" rows="2" placeholder="Any additional notes..."><?php echo isset($sale['note']) ? htmlspecialchars($sale['note']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Fees Section -->
                <div class="section-card">
                    <div class="section-header">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <h3>Additional Fees</h3>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Doctor Fee ($)</label>
                            <div class="form-input-group">
                                <i class="fas fa-user-md"></i>
                                <input type="number" step="0.01" class="form-input" name="doctor_fee" id="doctor_fee" 
                                       value="<?php echo isset($sale['doctor_fee']) ? number_format($sale['doctor_fee'], 2) : '0.00'; ?>" oninput="updateTotal()" min="0">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Operation Fee ($)</label>
                            <div class="form-input-group">
                                <i class="fas fa-procedures"></i>
                                <input type="number" step="0.01" class="form-input" name="operation_fee" id="operation_fee" 
                                       value="<?php echo isset($sale['operation_fee']) ? number_format($sale['operation_fee'], 2) : '0.00'; ?>" oninput="updateTotal()" min="0">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medicines Section -->
                <div class="section-card">
                    <div class="section-header">
                        <i class="fas fa-pills"></i>
                        <h3>Medicines</h3>
                        <div class="section-actions">
                            <button type="button" class="btn btn-primary btn-sm" onclick="addRow()">
                                <i class="fas fa-plus-circle"></i> Add Medicine
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="clearAllRows()">
                                <i class="fas fa-trash-alt"></i> Clear All
                            </button>
                        </div>
                    </div>

                    <!-- Table Container -->
                    <div class="table-container">
                        <table id="medicinesTable">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Price ($)</th>
                                    <th>Bonus/Discount ($)</th>
                                    <th>Quantity</th>
                                    <th>Stock</th>
                                    <th>Subtotal ($)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="medicinesTableBody">
                                <!-- Rows will be added here -->
                            </tbody>
                        </table>
                        
                        <div class="empty-state" id="emptyTableState">
                            <i class="fas fa-capsules"></i>
                            <h3>No Medicines Added</h3>
                            <p>Add medicines to this sale using the button above</p>
                        </div>
                    </div>
                </div>

                <!-- Totals Section -->
                <div class="totals-card">
                    <div class="total-row">
                        <span>Medicines Subtotal:</span>
                        <span class="amount">$<span id="medicinesSubtotal">0.00</span></span>
                    </div>
                    
                    <div class="total-row">
                        <span>Doctor Fee:</span>
                        <span class="amount">$<span id="displayDoctorFee"><?php echo isset($sale['doctor_fee']) ? number_format($sale['doctor_fee'], 2) : '0.00'; ?></span></span>
                    </div>
                    
                    <div class="total-row">
                        <span>Operation Fee:</span>
                        <span class="amount">$<span id="displayOperationFee"><?php echo isset($sale['operation_fee']) ? number_format($sale['operation_fee'], 2) : '0.00'; ?></span></span>
                    </div>
                    
                    <div class="total-row grand-total">
                        <span>TOTAL SALE:</span>
                        <span class="amount">$<span id="totalSale"><?php echo number_format($sale['total_amount'], 2); ?></span></span>
                    </div>
                </div>

                <!-- Payment Section -->
                <div class="payment-section">
                    <div class="section-header">
                        <i class="fas fa-credit-card"></i>
                        <h3>Payment Information</h3>
                    </div>

                    <div class="payment-grid">
                        <div class="payment-methods">
                            <h4>Payment Method *</h4>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="payment_method" value="Cash" <?php echo (isset($sale['payment_method']) && $sale['payment_method'] == 'Cash') ? 'checked' : 'checked'; ?>>
                                    <span class="radio-custom">
                                        <i class="fas fa-money-bill-wave"></i> Cash
                                    </span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="payment_method" value="Card" <?php echo (isset($sale['payment_method']) && $sale['payment_method'] == 'Card') ? 'checked' : ''; ?>>
                                    <span class="radio-custom">
                                        <i class="fas fa-credit-card"></i> Card
                                    </span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="payment_method" value="Other" <?php echo (isset($sale['payment_method']) && $sale['payment_method'] == 'Other') ? 'checked' : ''; ?>>
                                    <span class="radio-custom">
                                        <i class="fas fa-ellipsis-h"></i> Other
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Paid Amount ($)</label>
                            <div class="form-input-group">
                                <i class="fas fa-dollar-sign"></i>
                                <input type="number" step="0.01" class="form-input" name="paid_amount" id="paid_amount" 
                                       value="<?php echo isset($sale['paid_amount']) ? number_format($sale['paid_amount'], 2) : '0.00'; ?>" oninput="calculateBalance()" min="0">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Balance ($)</label>
                            <div class="form-input-group">
                                <i class="fas fa-balance-scale"></i>
                                <input type="number" step="0.01" class="form-input" name="balance" id="balance" 
                                       value="<?php echo isset($sale['balance']) ? number_format($sale['balance'], 2) : '0.00'; ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Payment Notes</label>
                        <div class="form-input-group">
                            <i class="fas fa-comment"></i>
                            <textarea id="payment_note" name="payment_note" rows="2" 
                                      placeholder="Payment details..."><?php echo isset($sale['payment_note']) ? htmlspecialchars($sale['payment_note']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Hidden Inputs -->
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="items" id="itemsInput">
                <input type="hidden" name="total_amount" id="totalAmountInput" value="<?php echo $sale['total_amount']; ?>">
                <input type="hidden" name="is_unpaid" id="isUnpaidInput" value="<?php echo isset($sale['is_unpaid']) ? $sale['is_unpaid'] : '0'; ?>">

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='save.php'">
                        <i class="fas fa-arrow-left"></i> Back to Sales
                    </button>
                    
                    <button type="button" class="btn btn-danger" onclick="processFullRefund()">
                        <i class="fas fa-money-bill-wave"></i> Full Refund
                    </button>
                    
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                        <i class="fas fa-redo"></i> Reset Changes
                    </button>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Sale
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Use PHP fetched medicines
        const medicinesData = <?php echo json_encode($medicines); ?>;
        // Convert sale items to array
        const existingItems = <?php echo json_encode($sale_items); ?>;
        let rowCounter = 0;

        // Toggle empty state for medicines table
        function toggleEmptyState() {
            const tbody = document.getElementById('medicinesTableBody');
            const emptyState = document.getElementById('emptyTableState');
            const tableContainer = document.querySelector('.table-container table');
            
            if (tbody.children.length === 0) {
                emptyState.style.display = 'block';
                if (tableContainer) tableContainer.style.display = 'none';
            } else {
                emptyState.style.display = 'none';
                if (tableContainer) tableContainer.style.display = 'table';
            }
        }

        // Add medicine row
        function addRow(itemData = null) {
            const tbody = document.getElementById('medicinesTableBody');
            const tr = document.createElement('tr');
            rowCounter++;
            tr.id = 'row_' + rowCounter;
            
            const isExisting = itemData !== null;
            const medId = isExisting ? itemData.medicine_id : '';
            const medName = isExisting ? itemData.medicine_name : '';
            const price = isExisting ? parseFloat(itemData.price_out) : 0;
            const bonus = isExisting ? parseFloat(itemData.bonus_or_discount) : 0;
  const qty = isExisting ? parseFloat(itemData.quantity) : 1;

           const stock = isExisting ? parseFloat(itemData.current_stock) + qty : 0;

            
            let optionsHTML = '<option value="">Select Medicine</option>';
            medicinesData.forEach(m => {
                const selected = (medId == m.id) ? 'selected' : '';
                optionsHTML += `
                    <option value="${m.id}" 
                            data-price="${m.price_out}" 
                            data-stock="${m.amount}"
                            data-name="${m.name}"
                            ${selected}>
                        ${m.name} ($${parseFloat(m.price_out).toFixed(2)})
                    </option>
                `;
            });
            
            tr.innerHTML = `
                <td>
                    <div class="medicine-select-wrapper">
                        <select class="form-select medicine-select" onchange="updateRow(this)" required>
                            ${optionsHTML}
                        </select>
                        <div class="stock-warning" id="stock_warning_${rowCounter}">
                            <i class="fas fa-exclamation-triangle"></i> Exceeds available stock!
                        </div>
                    </div>
                </td>
                <td>
                    <input type="number" class="form-input price-out" style="text-align: center;" readonly value="${price.toFixed(2)}">
                </td>
                <td>
                    <input type="number" class="form-input bonus" value="${bonus.toFixed(2)}" step="0.01" min="0" 
                           oninput="updateRow(this)" style="text-align: center;">
                </td>
                <td>
                  <input type="number" class="form-input qty" value="${qty}" min="0.01" step="0.01">

                </td>
                <td>
                    <span class="stock-amount" style="display: block; text-align: center; font-weight: 600;">${stock}</span>
                </td>
                <td class="subtotal" style="text-align: center; font-weight: bold; color: #2c3e50;">${(qty * (price - bonus)).toFixed(2)}</td>
                <td style="text-align: center;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(tr);
            toggleEmptyState();
            if (isExisting) {
                updateRow(tr.querySelector('.medicine-select'));
            }
        }

        // Remove medicine row
        function removeRow(btn) {
            if (confirm('Are you sure you want to remove this medicine?')) {
                btn.closest('tr').remove();
                updateTotal();
                toggleEmptyState();
            }
        }

        // Clear all rows
        function clearAllRows() {
            const tbody = document.getElementById('medicinesTableBody');
            if (tbody.children.length === 0) return;
            
            if (confirm('Are you sure you want to remove all medicines from this sale?')) {
                tbody.innerHTML = '';
                updateTotal();
                toggleEmptyState();
            }
        }

        // Update row calculations
        function updateRow(el) {
            const tr = el.closest('tr');
            const select = tr.querySelector('.medicine-select');
            const option = select.selectedOptions[0];
            
            if (option && option.value) {
                const priceOut = parseFloat(option.dataset.price) || 0;
             const stock = parseFloat(option.dataset.stock) || 0;

                const qtyInput = tr.querySelector('.qty');
                const bonusInput = tr.querySelector('.bonus');
                const priceInput = tr.querySelector('.price-out');
                const stockSpan = tr.querySelector('.stock-amount');
                const warning = tr.querySelector('.stock-warning');
                
                priceInput.value = priceOut.toFixed(2);
                stockSpan.textContent = stock;
                
                // Set max quantity based on stock
                qtyInput.max = stock;
                
               const qty = parseFloat(qtyInput.value) || 1;

                const bonus = parseFloat(bonusInput.value) || 0;
                
                // Show warning if quantity exceeds stock
                if (qty > stock) {
                    warning.style.display = 'block';
                    qtyInput.style.borderColor = 'var(--danger-color)';
                    qtyInput.value = stock; // Auto-correct to max stock
                } else {
                    warning.style.display = 'none';
                    qtyInput.style.borderColor = '';
                }
                
                // Calculate subtotal
                const subtotal = qty * (priceOut - bonus);
                tr.querySelector('.subtotal').textContent = subtotal.toFixed(2);
            } else {
                tr.querySelector('.price-out').value = '';
                tr.querySelector('.stock-amount').textContent = '-';
                tr.querySelector('.subtotal').textContent = '0.00';
            }
            
            updateTotal();
        }

        // Update total calculations
        function updateTotal() {
            // Calculate medicines subtotal
            const subtotals = document.querySelectorAll('.subtotal');
            let medicinesTotal = 0;
            subtotals.forEach(s => medicinesTotal += parseFloat(s.textContent) || 0);
            
            // Get fees
            const doctorFee = parseFloat(document.getElementById('doctor_fee').value) || 0;
            const operationFee = parseFloat(document.getElementById('operation_fee').value) || 0;
            
            // Calculate grand total
            const grandTotal = medicinesTotal + doctorFee + operationFee;

            // Update display
            document.getElementById('medicinesSubtotal').textContent = medicinesTotal.toFixed(2);
            document.getElementById('displayDoctorFee').textContent = doctorFee.toFixed(2);
            document.getElementById('displayOperationFee').textContent = operationFee.toFixed(2);
            document.getElementById('totalSale').textContent = grandTotal.toFixed(2);
            
            // Update balance if paid amount is set
            calculateBalance();
            
            // Update hidden inputs
            document.getElementById('totalAmountInput').value = grandTotal.toFixed(2);
        }

        // Calculate balance
        function calculateBalance() {
            const total = parseFloat(document.getElementById('totalSale').textContent) || 0;
            const paid = parseFloat(document.getElementById('paid_amount').value) || 0;
            const balance = total - paid;
            
            document.getElementById('balance').value = balance.toFixed(2);
            document.getElementById('isUnpaidInput').value = balance > 0 ? '1' : '0';
        }

        // Process full refund
        function processFullRefund() {
            if (confirm('Are you sure you want to process a FULL REFUND?\n\nThis will:' +
                        '\n1. Remove ALL medicines from this sale' +
                        '\n2. Restore ALL stock to inventory' +
                        '\n3. Set total amount to $0.00' +
                        '\n4. Customer will get full money back')) {
                
                // Remove all medicine rows
                document.getElementById('medicinesTableBody').innerHTML = '';
                
                // Set all fees to 0
                document.getElementById('doctor_fee').value = '0.00';
                document.getElementById('operation_fee').value = '0.00';
                
                // Set paid amount to 0
                document.getElementById('paid_amount').value = '0.00';
                
                // Update totals
                updateTotal();
                calculateBalance();
                
                // Show message
                alert('Sale prepared for full refund. Click "Update Sale" to save changes.');
                toggleEmptyState();
            }
        }

        // Reset form
        function resetForm() {
            if (confirm('Are you sure you want to reset all changes? Current data will be lost.')) {
                // Reload the page to get original data
                window.location.reload();
            }
        }

        // Form validation
        function validateForm() {
            const customerName = document.getElementById('customer_name').value.trim();
            if (!customerName) {
                alert('Please enter customer name');
                document.getElementById('customer_name').focus();
                return false;
            }
            
            // Check if all medicine selections are valid (only if medicines exist)
            const rows = document.querySelectorAll('#medicinesTableBody tr');
            let hasInvalid = false;
            rows.forEach(row => {
                const select = row.querySelector('.medicine-select');
                if (!select.value) {
                    hasInvalid = true;
                    select.style.borderColor = 'var(--danger-color)';
                } else {
                    select.style.borderColor = '';
                }
            });
            
            if (hasInvalid) {
                alert('Please select a medicine for all rows or remove empty rows');
                return false;
            }
            
            return true;
        }

        // Form submission handler
        document.getElementById('updateSaleForm').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            
            // Build items JSON
            const items = [];
            let allValid = true;
            
            document.querySelectorAll('#medicinesTableBody tr').forEach((tr, index) => {
                const medId = tr.querySelector('.medicine-select').value;
                const medName = tr.querySelector('.medicine-select').selectedOptions[0]?.dataset.name || '';
                const qty = parseInt(tr.querySelector('.qty').value) || 1;
                const price_out = parseFloat(tr.querySelector('.price-out').value) || 0;
                const bonus = parseFloat(tr.querySelector('.bonus').value) || 0;
                const stock = parseInt(tr.querySelector('.stock-amount').textContent) || 0;
                
                if (medId) {
                    if (qty > stock) {
                        alert(`Quantity for ${medName} exceeds available stock (${stock})`);
                        allValid = false;
                    }
                    
                    items.push({
                        medicine_id: medId,
                        medicine_name: medName,
                        quantity: qty,
                        price_out: price_out,
                        bonus_or_discount: bonus,
                        stock: stock
                    });
                }
            });
            
            if (!allValid) {
                e.preventDefault();
                return false;
            }
            
            // Allow empty items array for refunds/returns
            document.getElementById('itemsInput').value = JSON.stringify(items);
            
            // Show loading
            const submitBtn = document.querySelector('.btn-primary[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
            
            // Re-enable button after 3 seconds if form submission fails
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });

        // Initialize page
        window.onload = function() {
            // Add existing items as rows
            if (existingItems.length > 0) {
                existingItems.forEach(item => {
                    addRow(item);
                });
            } else {
                // Add one empty row if no existing items
                addRow();
            }
            
            // Set focus to customer name field
            document.getElementById('customer_name').focus();
            updateTotal();
        };
    </script>
</body>
</html>