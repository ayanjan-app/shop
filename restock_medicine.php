<?php
require_once 'db.php';
session_start();

if(!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $medicine_id = intval($_POST['medicine_id']);
    $quantity = intval($_POST['quantity']);
    
    if($medicine_id <= 0 || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }
    
    // Update medicine stock
    $sql = "UPDATE medicines SET amount = amount + ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $quantity, $medicine_id);
    
    if($stmt->execute()) {
        // Get medicine name for log
        $med_result = $conn->query("SELECT name FROM medicines WHERE id = $medicine_id");
        $medicine_name = $med_result->fetch_assoc()['name'] ?? 'Unknown';
        
        // Log the restock (you could create a restock_logs table)
        $log_sql = "INSERT INTO activity_logs (user_id, action, details, timestamp) 
                    VALUES (?, 'RESTOCK', ?, NOW())";
        $log_stmt = $conn->prepare($log_sql);
        $details = "Restocked $quantity units of $medicine_name (ID: $medicine_id)";
        $log_stmt->bind_param("is", $_SESSION['user_id'], $details);
        $log_stmt->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => "Successfully added $quantity units to stock"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>