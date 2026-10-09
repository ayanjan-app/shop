<?php
// add_payment.php - SIMPLIFIED VERSION
require_once 'db.php';
session_start();

if(!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not logged in']));
}

// Get POST data
$sale_id = $_POST['sale_id'] ?? 0;
$payment_amount = $_POST['payment_amount'] ?? 0;
$payment_method = $_POST['payment_method'] ?? 'Cash';
$note = $_POST['note'] ?? '';

if($sale_id <= 0 || $payment_amount <= 0) {
    die(json_encode(['success' => false, 'message' => 'Invalid payment data']));
}

// Start transaction
$conn->begin_transaction();

try {
    $user_id = $_SESSION['user_id'];
    
    // 1. Get sale details to verify
    $sale_stmt = $conn->prepare("SELECT * FROM sales WHERE sale_id = ? AND user_id = ?");
    $sale_stmt->bind_param("ii", $sale_id, $user_id);
    $sale_stmt->execute();
    $sale_result = $sale_stmt->get_result();
    
    if($sale_result->num_rows === 0) {
        throw new Exception("Sale not found or access denied");
    }
    
    $sale = $sale_result->fetch_assoc();
    $sale_stmt->close();
    
    // 2. Calculate total paid so far
    $total_paid_stmt = $conn->prepare("SELECT COALESCE(SUM(payment_amount), 0) as total_paid FROM payments WHERE sale_id = ?");
    $total_paid_stmt->bind_param("i", $sale_id);
    $total_paid_stmt->execute();
    $total_paid_result = $total_paid_stmt->get_result();
    $total_paid_data = $total_paid_result->fetch_assoc();
    $total_paid = $total_paid_data['total_paid'] ?? 0;
    $total_paid_stmt->close();
    
    $total_amount = $sale['total_amount'];
    $new_total_paid = $total_paid + $payment_amount;
    $remaining_balance = $total_amount - $new_total_paid;
    
    // Check if payment exceeds total amount
    if($payment_amount > ($total_amount - $total_paid)) {
        throw new Exception("Payment amount exceeds remaining balance");
    }
    
    // 3. Insert payment record
    $payment_stmt = $conn->prepare("
        INSERT INTO payments 
        (sale_id, user_id, payment_amount, payment_method, note, payment_date) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $payment_stmt->bind_param("iidss", $sale_id, $user_id, $payment_amount, $payment_method, $note);
    
    if(!$payment_stmt->execute()) {
        throw new Exception("Failed to save payment: " . $payment_stmt->error);
    }
    $payment_stmt->close();
    
    // 4. If fully paid, remove from customer_unpaid table
    if($remaining_balance <= 0) {
        $unpaid_stmt = $conn->prepare("DELETE FROM customer_unpaid WHERE sale_id = ?");
        $unpaid_stmt->bind_param("i", $sale_id);
        $unpaid_stmt->execute();
        $unpaid_stmt->close();
        
        $message = "Payment recorded successfully. Sale is now fully paid!";
    } else {
        // Update unpaid amount if exists, otherwise insert
        $check_unpaid_stmt = $conn->prepare("SELECT unpaid_id FROM customer_unpaid WHERE sale_id = ?");
        $check_unpaid_stmt->bind_param("i", $sale_id);
        $check_unpaid_stmt->execute();
        $check_unpaid_result = $check_unpaid_stmt->get_result();
        
        if($check_unpaid_result->num_rows > 0) {
            // Update existing
            $update_unpaid_stmt = $conn->prepare("UPDATE customer_unpaid SET unpaid_amount = ? WHERE sale_id = ?");
            $update_unpaid_stmt->bind_param("di", $remaining_balance, $sale_id);
            $update_unpaid_stmt->execute();
            $update_unpaid_stmt->close();
        } else {
            // Insert new
            $insert_unpaid_stmt = $conn->prepare("INSERT INTO customer_unpaid (sale_id, customer_name, unpaid_amount) VALUES (?, ?, ?)");
            $insert_unpaid_stmt->bind_param("isd", $sale_id, $sale['customer_name'], $remaining_balance);
            $insert_unpaid_stmt->execute();
            $insert_unpaid_stmt->close();
        }
        $check_unpaid_stmt->close();
        
        $message = "Payment recorded successfully. Remaining balance: $" . number_format($remaining_balance, 2);
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'remaining_balance' => $remaining_balance,
        'total_paid' => $new_total_paid
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>