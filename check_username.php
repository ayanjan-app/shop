<?php
// check_username.php
require_once 'db.php';

header('Content-Type: application/json');

if(isset($_GET['username'])) {
    $username = trim($_GET['username']);
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        echo json_encode(['available' => false]);
    } else {
        echo json_encode(['available' => true]);
    }
    $stmt->close();
} else {
    echo json_encode(['available' => false]);
}