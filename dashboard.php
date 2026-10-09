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

?>
<!DOCTYPE html>
<html lang="en">
<head>

</head>
<body>
    <?php include 'sidebar.php'; ?>

</body>
</html>