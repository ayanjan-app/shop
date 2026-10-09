<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if current user is admin (username = admin)
$current_user = isset($_SESSION['username']) ? $_SESSION['username'] : '';

if($current_user !== 'ali') {
    // Set error message in session to display on login page
    $_SESSION['login_error'] = "Access denied. Only admin users can access the user management page. Please login with admin credentials.";
    
    // Clear current session
    session_destroy();
    
    // Redirect to login page
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle user deletion
if(isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Prevent deleting own account
    if($delete_id != $_SESSION['user_id']) {
        $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delete_stmt->bind_param("i", $delete_id);
        
        if($delete_stmt->execute()) {
            $success_message = "User deleted successfully!";
        } else {
            $error_message = "Error deleting user: " . $conn->error;
        }
        
        $delete_stmt->close();
    } else {
        $error_message = "You cannot delete your own account!";
    }
    
    // Redirect to clear URL parameters
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// Handle user update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_user'])) {
    $update_id = intval($_POST['user_id']);
    $new_username = trim($_POST['username']);
    $new_password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate username
    if(empty($new_username)) {
        $error_message = "Username is required";
    } elseif(strlen($new_username) < 3) {
        $error_message = "Username must be at least 3 characters";
    } else {
        // Check if username already exists (excluding current user)
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $new_username, $update_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0) {
            $error_message = "Username already exists";
        } else {
            // Prepare update query based on whether password is provided
            if(!empty($new_password)) {
                // Update both username and password
                if(strlen($new_password) < 6) {
                    $error_message = "Password must be at least 6 characters";
                } elseif($new_password !== $confirm_password) {
                    $error_message = "Passwords do not match";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                    $update_stmt->bind_param("ssi", $new_username, $hashed_password, $update_id);
                    
                    if($update_stmt->execute()) {
                        $success_message = "User updated successfully!";
                        
                        // If updating own account, update session username
                        if($update_id == $_SESSION['user_id']) {
                            $_SESSION['username'] = $new_username;
                        }
                    } else {
                        $error_message = "Error updating user: " . $conn->error;
                    }
                    $update_stmt->close();
                }
            } else {
                // Update only username
                $update_stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_username, $update_id);
                
                if($update_stmt->execute()) {
                    $success_message = "User updated successfully!";
                    
                    // If updating own account, update session username
                    if($update_id == $_SESSION['user_id']) {
                        $_SESSION['username'] = $new_username;
                    }
                } else {
                    $error_message = "Error updating user: " . $conn->error;
                }
                $update_stmt->close();
            }
        }
        $check_stmt->close();
    }
}

// Handle user creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['update_user'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // validation
    if(empty($username)) {
        $error_message = "Username is required";
    } elseif(strlen($username) < 3) {
        $error_message = "Username must be at least 3 characters";
    } elseif(empty($password)) {
        $error_message = "Password is required";
    } elseif(strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters";
    } elseif($password !== $confirm_password) {
        $error_message = "Passwords do not match";
    } else {
        // check username exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if($check_result->num_rows > 0) {
            $error_message = "Username already exists";
        } else {
            // hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $insert_stmt = $conn->prepare(
                "INSERT INTO users (username, password, created_at) VALUES (?, ?, NOW())"
            );

            if(!$insert_stmt){
                die("Prepare failed: " . $conn->error);
            }

            $insert_stmt->bind_param("ss", $username, $hashed_password);

            if($insert_stmt->execute()) {
                $success_message = "User created successfully!";
                $_POST = array();
                
                // Refresh users list
                $users = [];
                $users_stmt = $conn->prepare("SELECT id, username, created_at FROM users ORDER BY created_at DESC");
                $users_stmt->execute();
                $result = $users_stmt->get_result();
                while($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
                $users_stmt->close();
            } else {
                $error_message = "Error creating user: " . $conn->error;
            }

            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Fetch all users
$users = [];
$users_stmt = $conn->prepare("SELECT id, username, created_at FROM users ORDER BY created_at DESC");
if($users_stmt->execute()) {
    $result = $users_stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
$users_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - PharmaCare RX (Admin Only)</title>
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
            --edit-color: #ffc107;
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

        /* Admin Header */
        .admin-header {
            background: linear-gradient(135deg, var(--primary-color), #0c2461);
            color: white;
            padding: 10px 20px;
            text-align: center;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 2px solid var(--accent-color);
            animation: glow 2s infinite;
        }

        @keyframes glow {
            0%, 100% { border-color: var(--accent-color); }
            50% { border-color: var(--secondary-color); }
        }

        .admin-header i {
            font-size: 1.2rem;
            color: var(--accent-color);
        }

        .admin-badge-large {
            background: rgba(255, 255, 255, 0.1);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-left: 10px;
            border: 1px solid var(--accent-color);
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

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            color: var(--secondary-color);
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 10px 0;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card:nth-child(1) { border-top: 4px solid var(--primary-color); }
        .stat-card:nth-child(2) { border-top: 4px solid var(--secondary-color); }
        .stat-card:nth-child(3) { border-top: 4px solid var(--accent-color); }

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
            animation: fadeIn 0.4s ease;
        }

        .table-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
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

        .user-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .user-table thead {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .user-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-table th:first-child {
            border-top-left-radius: 8px;
        }

        .user-table th:last-child {
            border-top-right-radius: 8px;
        }

        .user-table tbody tr {
            border-bottom: 1px solid #e0e0e0;
            transition: var(--transition);
        }

        .user-table tbody tr:hover {
            background-color: rgba(38, 208, 206, 0.05);
        }

        .user-table td {
            padding: 15px;
            color: var(--text-color);
        }

        .user-table .user-id {
            font-weight: 600;
            color: var(--primary-color);
            font-family: 'Courier New', monospace;
        }

        .user-table .username {
            font-weight: 500;
            color: var(--primary-color);
        }

        .user-table .created-at {
            color: #666;
            font-size: 0.9rem;
        }

        .user-table .current-user {
            background-color: rgba(76, 175, 80, 0.1);
        }

        .user-table .current-user td {
            color: var(--success-color);
        }

        /* Action Buttons */
        .action-buttons-table {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 8px 12px;
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

        .btn-edit {
            background: linear-gradient(45deg, var(--edit-color), #ffb300);
            color: white;
        }

        .btn-edit:hover {
            background: linear-gradient(45deg, #ffb300, var(--edit-color));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
        }

        .btn-delete {
            background: linear-gradient(45deg, #dc3545, #c82333);
            color: white;
        }

        .btn-delete:hover {
            background: linear-gradient(45deg, #c82333, #dc3545);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-view {
            background: linear-gradient(45deg, var(--info-color), #138496);
            color: white;
        }

        .btn-view:hover {
            background: linear-gradient(45deg, #138496, var(--info-color));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.3);
        }

        .btn-action:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #e0e0e0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
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
            background: linear-gradient(90deg, var(--secondary-color), var(--accent-color));
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
            color: var(--primary-color);
            font-size: 1.3rem;
        }

        .form-header i {
            color: var(--secondary-color);
            font-size: 1.3rem;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: var(--card-bg);
            font-size: 0.95rem;
            color: var(--text-color);
            transition: var(--transition);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(38, 208, 206, 0.15);
        }

        .form-input-group {
            position: relative;
        }

        .form-input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
            font-size: 1rem;
        }

        .form-input-group .form-input {
            padding-left: 45px;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            white-space: nowrap;
            min-height: 44px;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(26, 41, 128, 0.3);
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
        }

        .btn-secondary {
            background: rgba(38, 208, 206, 0.1);
            color: var(--secondary-color);
            border: 2px solid var(--secondary-color);
        }

        .btn-secondary:hover {
            background: rgba(38, 208, 206, 0.2);
            transform: translateY(-3px);
        }

        .btn-edit {
            background: linear-gradient(45deg, var(--edit-color), #ffb300);
            color: white;
        }

        .btn-edit:hover {
            background: linear-gradient(45deg, #ffb300, var(--edit-color));
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(255, 193, 7, 0.3);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Password toggle */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
            cursor: pointer;
            font-size: 1rem;
            background: none;
            border: none;
            padding: 5px;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        /* Requirements list */
        .requirements-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 3px solid var(--secondary-color);
        }

        .requirements-list h4 {
            color: var(--primary-color);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .requirements-list ul {
            list-style: none;
            padding-left: 0;
        }

        .requirements-list li {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .requirements-list li i {
            font-size: 0.8rem;
            color: var(--success-color);
        }

        .requirements-list li.invalid i {
            color: var(--danger-color);
        }

        /* Password strength indicator */
        .password-strength {
            margin-top: 5px;
            height: 4px;
            border-radius: 2px;
            background: #e0e0e0;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak {
            background-color: var(--danger-color);
        }

        .strength-medium {
            background-color: var(--warning-color);
        }

        .strength-strong {
            background-color: var(--success-color);
        }

        .strength-text {
            font-size: 0.8rem;
            margin-top: 3px;
            color: #666;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-hover);
            animation: modalFadeIn 0.3s ease;
            border: 3px solid transparent;
            border-image: linear-gradient(45deg, var(--secondary-color), var(--accent-color)) 1;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-header {
            margin-bottom: 25px;
            text-align: center;
            position: relative;
        }

        .modal-header i {
            font-size: 3rem;
            color: var(--secondary-color);
            margin-bottom: 15px;
        }

        .modal-header h2 {
            color: var(--primary-color);
            font-size: 1.5rem;
        }

        .modal-body {
            margin-bottom: 25px;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .modal-buttons .btn {
            min-width: 120px;
        }

        /* Edit Modal specific */
        .edit-modal .modal-content {
            max-width: 600px;
        }

        .password-hint {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: var(--info-color);
            margin-top: 5px;
            border-left: 3px solid var(--info-color);
        }

        /* Search and Filter */
        .table-controls {
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
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-table th, 
            .user-table td {
                padding: 12px 10px;
                font-size: 0.85rem;
            }
            
            .btn-action {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .page-header {
                padding: 12px 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .modal-buttons {
                flex-direction: column;
            }
            
            .modal-buttons .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.3rem;
            }
            
            .table-container,
            .form-container {
                padding: 15px;
            }
            
            .form-input, .form-select {
                padding: 10px 12px;
                font-size: 0.9rem;
            }
            
            .modal-content {
                padding: 20px;
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Admin Header -->
        <div class="admin-header">
            <i class="fas fa-shield-alt"></i>
            <span>Admin Control Panel</span>
            <span class="admin-badge-large">
                <i class="fas fa-crown"></i>
                Logged in as: <?php echo htmlspecialchars($current_user); ?>
            </span>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-users-cog"></i>
                User Management
            </h1>
            <div class="today">
                <i class="far fa-calendar"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- User Statistics -->
        <div class="stats-cards">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-number"><?php echo count($users); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-plus"></i>
                <div class="stat-number"><?php echo count(array_filter($users, function($user) {
                    return strtotime($user['created_at']) > strtotime('-7 days');
                })); ?></div>
                <div class="stat-label">New This Week</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-check"></i>
                <div class="stat-number">1</div>
                <div class="stat-label">Active Now</div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="table-container">
            <div class="table-header">
                <h2><i class="fas fa-list"></i> All Users</h2>
                <div class="table-actions">
                    <button class="btn btn-secondary" onclick="refreshTable()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="table-controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search users..." onkeyup="searchUsers()">
                </div>
                <select class="filter-select" id="filterSelect" onchange="filterUsers()">
                    <option value="all">All Users</option>
                    <option value="new">New This Week</option>
                    <option value="current">Current User</option>
                </select>
            </div>

            <div class="table-wrapper">
                <?php if(count($users) > 0): ?>
                    <table class="user-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <?php foreach($users as $user): ?>
                                <tr class="<?php echo $user['id'] == $_SESSION['user_id'] ? 'current-user' : ''; ?>"
                                    data-id="<?php echo $user['id']; ?>"
                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                    data-created="<?php echo $user['created_at']; ?>">
                                    <td class="user-id">#<?php echo htmlspecialchars($user['id']); ?></td>
                                    <td class="username">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="current-badge">
                                                <i class="fas fa-user-check"></i> (You)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="created-at">
                                        <i class="far fa-calendar-alt"></i>
                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                        <br>
                                        <small><?php echo date('h:i A', strtotime($user['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="action-buttons-table">
                                            <button class="btn-action btn-view" onclick="viewUserDetails(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn-action btn-edit" onclick="editUser(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                <button class="btn-action btn-delete" 
                                                        onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-action btn-delete" disabled>
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <h3>No Users Found</h3>
                        <p>Start by creating your first user account below.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Create User Form -->
        <div class="form-container">
            <div class="form-header">
                <i class="fas fa-user-plus"></i>
                <h2>Add New User Account</h2>
            </div>

            <form method="POST" id="createUserForm">
                <div class="form-grid">
                    <!-- Username -->
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <div class="form-input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" class="form-input" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                   placeholder="Enter username (min. 3 characters)" required
                                   oninput="checkUsername()">
                        </div>
                        <div id="username-feedback" class="strength-text"></div>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <div class="form-input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" class="form-input" id="password" name="password" 
                                   placeholder="Enter password (min. 6 characters)" required
                                   oninput="checkPasswordStrength()">
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div id="strength-bar" class="strength-bar"></div>
                        </div>
                        <div id="strength-text" class="strength-text"></div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label class="form-label">Confirm Password *</label>
                        <div class="form-input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" class="form-input" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm password" required oninput="checkPasswordMatch()">
                            <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="password-match-feedback" class="strength-text"></div>
                    </div>
                </div>

                <!-- Password Requirements -->
                <div class="requirements-list">
                    <h4>Password Requirements:</h4>
                    <ul>
                        <li id="req-length"><i class="fas fa-check"></i> At least 6 characters</li>
                        <li id="req-uppercase"><i class="fas fa-times"></i> Contains uppercase letter</li>
                        <li id="req-lowercase"><i class="fas fa-check"></i> Contains lowercase letter</li>
                        <li id="req-number"><i class="fas fa-times"></i> Contains number</li>
                        <li id="req-special"><i class="fas fa-times"></i> Contains special character</li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                        <i class="fas fa-redo"></i> Reset Form
                    </button>
                    
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='dashboard.php'">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </button>
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Create User Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h2>Confirm Delete</h2>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete user <strong id="deleteUsername"></strong>?</p>
                <p style="color: var(--danger-color); font-size: 0.9rem; margin-top: 10px;">
                    <i class="fas fa-warning"></i> This action cannot be undone!
                </p>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn btn-delete" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> Delete User
                </button>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal edit-modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-user-edit"></i>
                <h2>Edit User</h2>
            </div>
            <div class="modal-body">
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <div class="form-input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" class="form-input" id="edit_username" name="username" 
                                   placeholder="Enter username (min. 3 characters)" required
                                   oninput="checkEditUsername()">
                        </div>
                        <div id="edit-username-feedback" class="strength-text"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">New Password (leave blank to keep current)</label>
                        <div class="form-input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" class="form-input" id="edit_password" name="password" 
                                   placeholder="Enter new password" oninput="checkEditPasswordStrength()">
                            <button type="button" class="password-toggle" id="toggleEditPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-hint">
                            <i class="fas fa-info-circle"></i> Only fill this if you want to change the password
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <div class="form-input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" class="form-input" id="edit_confirm_password" name="confirm_password"
                                   placeholder="Confirm new password" oninput="checkEditPasswordMatch()">
                            <button type="button" class="password-toggle" id="toggleEditConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="edit-password-match-feedback" class="strength-text"></div>
                    </div>

                    <div id="edit-password-requirements" class="requirements-list" style="display: none;">
                        <h4>Password Requirements:</h4>
                        <ul>
                            <li id="edit-req-length"><i class="fas fa-times"></i> At least 6 characters</li>
                            <li id="edit-req-uppercase"><i class="fas fa-times"></i> Contains uppercase letter</li>
                            <li id="edit-req-lowercase"><i class="fas fa-times"></i> Contains lowercase letter</li>
                            <li id="edit-req-number"><i class="fas fa-times"></i> Contains number</li>
                            <li id="edit-req-special"><i class="fas fa-times"></i> Contains special character</li>
                        </ul>
                    </div>

                    <div class="modal-buttons">
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-edit" id="editSubmitBtn">
                            <i class="fas fa-save"></i> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div id="viewModal" class="modal view-modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-user-circle"></i>
                <h2>User Details</h2>
            </div>
            <div class="modal-body">
                <div id="userDetailsContent">
                    <!-- Dynamic content will be loaded here -->
                </div>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-secondary" onclick="closeViewModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // DOM Elements
        const form = document.getElementById('createUserForm');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const togglePasswordBtn = document.getElementById('togglePassword');
        const toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');
        const strengthBar = document.getElementById('strength-bar');
        const strengthText = document.getElementById('strength-text');
        const submitBtn = document.getElementById('submitBtn');
        const searchInput = document.getElementById('searchInput');
        const filterSelect = document.getElementById('filterSelect');
        
        // Edit modal elements
        const editModal = document.getElementById('editModal');
        const editUsername = document.getElementById('edit_username');
        const editPassword = document.getElementById('edit_password');
        const editConfirmPassword = document.getElementById('edit_confirm_password');
        const toggleEditPasswordBtn = document.getElementById('toggleEditPassword');
        const toggleEditConfirmPasswordBtn = document.getElementById('toggleEditConfirmPassword');
        const editSubmitBtn = document.getElementById('editSubmitBtn');
        const editPasswordRequirements = document.getElementById('edit-password-requirements');
        
        // Password requirements elements
        const reqLength = document.getElementById('req-length');
        const reqUppercase = document.getElementById('req-uppercase');
        const reqLowercase = document.getElementById('req-lowercase');
        const reqNumber = document.getElementById('req-number');
        const reqSpecial = document.getElementById('req-special');
        
        // Edit password requirements elements
        const editReqLength = document.getElementById('edit-req-length');
        const editReqUppercase = document.getElementById('edit-req-uppercase');
        const editReqLowercase = document.getElementById('edit-req-lowercase');
        const editReqNumber = document.getElementById('edit-req-number');
        const editReqSpecial = document.getElementById('edit-req-special');

        let userToDelete = null;
        let allUsers = [];

        // Initialize all users data
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('#usersTableBody tr');
            allUsers = Array.from(rows).map(row => ({
                id: row.dataset.id,
                username: row.dataset.username,
                created: row.dataset.created,
                element: row
            }));
            
            usernameInput.focus();
        });

        // Toggle password visibility for create form
        togglePasswordBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

        toggleConfirmPasswordBtn.addEventListener('click', function() {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

        // Toggle password visibility for edit form
        toggleEditPasswordBtn.addEventListener('click', function() {
            const type = editPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            editPassword.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

        toggleEditConfirmPasswordBtn.addEventListener('click', function() {
            const type = editConfirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            editConfirmPassword.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

        // Check username availability for create form
        function checkUsername() {
            const username = usernameInput.value.trim();
            const feedback = document.getElementById('username-feedback');
            
            if(username.length === 0) {
                feedback.textContent = '';
                feedback.style.color = '';
                return;
            }
            
            if(username.length < 3) {
                feedback.textContent = 'Username must be at least 3 characters';
                feedback.style.color = 'var(--danger-color)';
                return;
            }
            
            const isTaken = allUsers.some(user => user.username.toLowerCase() === username.toLowerCase());
            
            if(isTaken) {
                feedback.textContent = 'Username already taken';
                feedback.style.color = 'var(--danger-color)';
            } else {
                feedback.textContent = 'Username is available';
                feedback.style.color = 'var(--success-color)';
            }
        }

        // Check username availability for edit form
        function checkEditUsername() {
            const username = editUsername.value.trim();
            const feedback = document.getElementById('edit-username-feedback');
            const currentUserId = document.getElementById('edit_user_id').value;
            
            if(username.length === 0) {
                feedback.textContent = '';
                feedback.style.color = '';
                return;
            }
            
            if(username.length < 3) {
                feedback.textContent = 'Username must be at least 3 characters';
                feedback.style.color = 'var(--danger-color)';
                return;
            }
            
            const isTaken = allUsers.some(user => 
                user.username.toLowerCase() === username.toLowerCase() && 
                user.id !== currentUserId
            );
            
            if(isTaken) {
                feedback.textContent = 'Username already taken';
                feedback.style.color = 'var(--danger-color)';
            } else {
                feedback.textContent = 'Username is available';
                feedback.style.color = 'var(--success-color)';
            }
        }

        // Check password strength for create form
        function checkPasswordStrength() {
            const password = passwordInput.value;
            let strength = 0;
            
            const hasLength = password.length >= 6;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[^A-Za-z0-9]/.test(password);
            
            updateRequirement(reqLength, hasLength);
            updateRequirement(reqUppercase, hasUppercase);
            updateRequirement(reqLowercase, hasLowercase);
            updateRequirement(reqNumber, hasNumber);
            updateRequirement(reqSpecial, hasSpecial);
            
            if(hasLength) strength += 20;
            if(hasUppercase) strength += 20;
            if(hasLowercase) strength += 20;
            if(hasNumber) strength += 20;
            if(hasSpecial) strength += 20;
            
            strengthBar.style.width = strength + '%';
            
            if(strength <= 40) {
                strengthBar.className = 'strength-bar strength-weak';
                strengthText.textContent = 'Weak password';
                strengthText.style.color = 'var(--danger-color)';
            } else if(strength <= 80) {
                strengthBar.className = 'strength-bar strength-medium';
                strengthText.textContent = 'Medium password';
                strengthText.style.color = 'var(--warning-color)';
            } else {
                strengthBar.className = 'strength-bar strength-strong';
                strengthText.textContent = 'Strong password';
                strengthText.style.color = 'var(--success-color)';
            }
            
            if(confirmPasswordInput.value) {
                checkPasswordMatch();
            }
        }

        // Check password strength for edit form
        function checkEditPasswordStrength() {
            const password = editPassword.value;
            
            if(password.length > 0) {
                editPasswordRequirements.style.display = 'block';
                
                const hasLength = password.length >= 6;
                const hasUppercase = /[A-Z]/.test(password);
                const hasLowercase = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                const hasSpecial = /[^A-Za-z0-9]/.test(password);
                
                updateRequirement(editReqLength, hasLength);
                updateRequirement(editReqUppercase, hasUppercase);
                updateRequirement(editReqLowercase, hasLowercase);
                updateRequirement(editReqNumber, hasNumber);
                updateRequirement(editReqSpecial, hasSpecial);
            } else {
                editPasswordRequirements.style.display = 'none';
            }
            
            // Trigger password match check
            if(editConfirmPassword.value) {
                checkEditPasswordMatch();
            }
        }

        function updateRequirement(element, isValid) {
            const icon = element.querySelector('i');
            if(isValid) {
                icon.className = 'fas fa-check';
                icon.style.color = 'var(--success-color)';
                element.classList.remove('invalid');
            } else {
                icon.className = 'fas fa-times';
                icon.style.color = 'var(--danger-color)';
                element.classList.add('invalid');
            }
        }

        // Check password match for create form
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const feedback = document.getElementById('password-match-feedback');
            
            if(confirmPassword.length === 0) {
                feedback.textContent = '';
                return;
            }
            
            if(password === confirmPassword) {
                feedback.textContent = 'Passwords match';
                feedback.style.color = 'var(--success-color)';
            } else {
                feedback.textContent = 'Passwords do not match';
                feedback.style.color = 'var(--danger-color)';
            }
        }

        // Check password match for edit form
        function checkEditPasswordMatch() {
            const password = editPassword.value;
            const confirmPassword = editConfirmPassword.value;
            const feedback = document.getElementById('edit-password-match-feedback');
            
            // If both fields are empty, don't show any message
            if(password.length === 0 && confirmPassword.length === 0) {
                feedback.textContent = '';
                return;
            }
            
            // If only confirm password is filled but password is empty
            if(password.length === 0 && confirmPassword.length > 0) {
                feedback.textContent = 'Please enter a new password first';
                feedback.style.color = 'var(--warning-color)';
                return;
            }
            
            // Check if passwords match
            if(password === confirmPassword) {
                feedback.textContent = '✓ Passwords match';
                feedback.style.color = 'var(--success-color)';
            } else {
                feedback.textContent = '✗ Passwords do not match';
                feedback.style.color = 'var(--danger-color)';
            }
        }

        // Validate create form
        function validateForm() {
            const username = usernameInput.value.trim();
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if(username.length < 3) {
                alert('Username must be at least 3 characters long');
                usernameInput.focus();
                return false;
            }
            
            if(password.length < 6) {
                alert('Password must be at least 6 characters long');
                passwordInput.focus();
                return false;
            }
            
            if(password !== confirmPassword) {
                alert('Passwords do not match');
                confirmPasswordInput.focus();
                return false;
            }
            
            return true;
        }

        // Validate edit form
        function validateEditForm() {
            const username = editUsername.value.trim();
            const password = editPassword.value;
            const confirmPassword = editConfirmPassword.value;
            
            if(username.length < 3) {
                alert('Username must be at least 3 characters long');
                editUsername.focus();
                return false;
            }
            
            if(password.length > 0 || confirmPassword.length > 0) {
                if(password.length < 6) {
                    alert('Password must be at least 6 characters long');
                    editPassword.focus();
                    return false;
                }
                
                if(password !== confirmPassword) {
                    alert('Passwords do not match');
                    editConfirmPassword.focus();
                    return false;
                }
            }
            
            return true;
        }

        // Create form submission
        form.addEventListener('submit', function(e) {
            if(!validateForm()) {
                e.preventDefault();
                return;
            }
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating User...';
            submitBtn.disabled = true;
        });

        // Edit form submission
        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            if(!validateEditForm()) {
                e.preventDefault();
                return;
            }
            
            editSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating User...';
            editSubmitBtn.disabled = true;
        });

        // Reset create form
        function resetForm() {
            if(confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                form.reset();
                strengthBar.style.width = '0%';
                strengthText.textContent = '';
                document.getElementById('username-feedback').textContent = '';
                document.getElementById('password-match-feedback').textContent = '';
                
                const requirements = [reqLength, reqUppercase, reqLowercase, reqNumber, reqSpecial];
                requirements.forEach(req => {
                    const icon = req.querySelector('i');
                    icon.className = 'fas fa-times';
                    icon.style.color = 'var(--danger-color)';
                    req.classList.add('invalid');
                });
            }
        }

        // Edit user function
        function editUser(userId) {
            const user = allUsers.find(u => u.id == userId);
            if(!user) return;
            
            document.getElementById('edit_user_id').value = user.id;
            editUsername.value = user.username;
            editPassword.value = '';
            editConfirmPassword.value = '';
            
            document.getElementById('edit-username-feedback').textContent = '';
            document.getElementById('edit-password-match-feedback').textContent = '';
            editPasswordRequirements.style.display = 'none';
            
            editModal.style.display = 'flex';
        }

        function closeEditModal() {
            editModal.style.display = 'none';
        }

        // Delete user functionality
        function confirmDelete(userId, username) {
            userToDelete = userId;
            document.getElementById('deleteUsername').textContent = username;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
            userToDelete = null;
        }

        function deleteUser() {
            if(userToDelete) {
                window.location.href = '?delete_id=' + userToDelete;
            }
        }

        // View user details
        function viewUserDetails(userId) {
            const user = allUsers.find(u => u.id == userId);
            if(!user) return;
            
            const createdDate = new Date(user.created);
            const daysAgo = Math.floor((new Date() - createdDate) / (1000 * 60 * 60 * 24));
            const formattedDate = createdDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const details = `
                <div class="user-details">
                    <div class="detail-item">
                        <div class="detail-label">User ID</div>
                        <div class="detail-value">#${user.id}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Username</div>
                        <div class="detail-value">${user.username}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Created Date</div>
                        <div class="detail-value">${formattedDate}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Account Age</div>
                        <div class="detail-value">${daysAgo} days</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Status</div>
                        <div class="detail-value" style="color: var(--success-color);">
                            <i class="fas fa-check-circle"></i> Active
                        </div>
                    </div>
                </div>
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <div class="detail-label">Quick Actions</div>
                    <div style="margin-top: 10px; display: flex; gap: 10px;">
                        <button class="btn-action btn-edit" style="flex: 1;" onclick="closeViewModal(); editUser(${user.id})">
                            <i class="fas fa-edit"></i> Edit User
                        </button>
                        ${userId != <?php echo $_SESSION['user_id']; ?> ? 
                            `<button class="btn-action btn-delete" style="flex: 1;" onclick="closeViewModal(); confirmDelete(${userId}, '${user.username}')">
                                <i class="fas fa-trash"></i> Delete User
                            </button>` : 
                            `<button class="btn-action btn-delete" style="flex: 1;" disabled>
                                <i class="fas fa-trash"></i> Delete User
                            </button>`
                        }
                    </div>
                </div>
            `;
            
            document.getElementById('userDetailsContent').innerHTML = details;
            document.getElementById('viewModal').style.display = 'flex';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        // Search functionality
        function searchUsers() {
            const searchTerm = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTableBody tr');
            
            rows.forEach(row => {
                const username = row.dataset.username.toLowerCase();
                const id = row.dataset.id;
                const shouldShow = username.includes(searchTerm) || id.includes(searchTerm);
                row.style.display = shouldShow ? '' : 'none';
            });
        }

        // Filter functionality
        function filterUsers() {
            const filter = filterSelect.value;
            const rows = document.querySelectorAll('#usersTableBody tr');
            const currentDate = new Date();
            const weekAgo = new Date(currentDate.getTime() - 7 * 24 * 60 * 60 * 1000);
            
            rows.forEach(row => {
                const createdDate = new Date(row.dataset.created);
                const isCurrentUser = row.dataset.id == <?php echo $_SESSION['user_id']; ?>;
                const isNewThisWeek = createdDate > weekAgo;
                
                let shouldShow = true;
                
                if(filter === 'new') {
                    shouldShow = isNewThisWeek;
                } else if(filter === 'current') {
                    shouldShow = isCurrentUser;
                }
                
                row.style.display = shouldShow ? '' : 'none';
            });
        }

        // Refresh table
        function refreshTable() {
            window.location.reload();
        }

        // Modal close handlers
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            const editModal = document.getElementById('editModal');
            const viewModal = document.getElementById('viewModal');
            
            if(event.target == deleteModal) closeModal();
            if(event.target == editModal) closeEditModal();
            if(event.target == viewModal) closeViewModal();
        }

        // Initialize delete button
        document.getElementById('confirmDeleteBtn').addEventListener('click', deleteUser);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape') {
                closeModal();
                closeEditModal();
                closeViewModal();
            }
            if(e.key === 'Enter' && document.getElementById('deleteModal').style.display === 'flex') {
                deleteUser();
            }
        });

        // Mobile touch handling
        document.addEventListener('touchstart', function() {}, {passive: true});
    </script>
</body>
</html>