<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// All logged-in users can access this page (removed admin-only restriction)
$current_user = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$is_admin = ($current_user === 'ali');

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
            $success_message = "کارن په بریالیتوب سره ړنګ شو!";
        } else {
            $error_message = "د کارن ړنګولو کې تېروتنه: " . $conn->error;
        }
        
        $delete_stmt->close();
    } else {
        $error_message = "تاسو خپل حساب نه شئ ړنګولی!";
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
        $error_message = "کارن نوم اړین دی";
    } elseif(strlen($new_username) < 3) {
        $error_message = "کارن نوم باید لږ تر لږه ۳ توري وي";
    } else {
        // Check if username already exists (excluding current user)
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $new_username, $update_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0) {
            $error_message = "کارن نوم لا دمخه شتون لري";
        } else {
            // Prepare update query based on whether password is provided
            if(!empty($new_password)) {
                // Update both username and password
                if(strlen($new_password) < 6) {
                    $error_message = "پټ‌نوم باید لږ تر لږه ۶ توري وي";
                } elseif($new_password !== $confirm_password) {
                    $error_message = "پټ‌نومونه سره سمون نه لري";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                    $update_stmt->bind_param("ssi", $new_username, $hashed_password, $update_id);
                    
                    if($update_stmt->execute()) {
                        $success_message = "کارن په بریالیتوب سره تازه شو!";
                        
                        // If updating own account, update session username
                        if($update_id == $_SESSION['user_id']) {
                            $_SESSION['username'] = $new_username;
                        }
                    } else {
                        $error_message = "د کارن تازه کولو کې تېروتنه: " . $conn->error;
                    }
                    $update_stmt->close();
                }
            } else {
                // Update only username
                $update_stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_username, $update_id);
                
                if($update_stmt->execute()) {
                    $success_message = "کارن په بریالیتوب سره تازه شو!";
                    
                    // If updating own account, update session username
                    if($update_id == $_SESSION['user_id']) {
                        $_SESSION['username'] = $new_username;
                    }
                } else {
                    $error_message = "د کارن تازه کولو کې تېروتنه: " . $conn->error;
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
        $error_message = "کارن نوم اړین دی";
    } elseif(strlen($username) < 3) {
        $error_message = "کارن نوم باید لږ تر لږه ۳ توري وي";
    } elseif(empty($password)) {
        $error_message = "پټ‌نوم اړین دی";
    } elseif(strlen($password) < 6) {
        $error_message = "پټ‌نوم باید لږ تر لږه ۶ توري وي";
    } elseif($password !== $confirm_password) {
        $error_message = "پټ‌نومونه سره سمون نه لري";
    } else {
        // check username exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if($check_result->num_rows > 0) {
            $error_message = "کارن نوم لا دمخه شتون لري";
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
                $success_message = "کارن په بریالیتوب سره جوړ شو!";
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
                $error_message = "د کارن جوړولو کې تېروتنه: " . $conn->error;
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
<html lang="ps" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>د کاروونکو مدیریت · قرطاسیه</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e3c72;
            --secondary-color: #2b4a7a;
            --accent-color: #e6b12e;
            --success-color: #22c55e;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --edit-color: #f59e0b;
            --text-color: #1f2937;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            --shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.1);
            --border-radius: 16px;
            --transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Noto Naskh Arabic', 'Segoe UI', Tahoma, sans-serif;
        }

        body {
            background: #f1f5f9;
            color: var(--text-color);
            min-height: 100vh;
            font-size: 14px;
            line-height: 1.6;
        }

        /* Admin Header */
        .admin-header {
            background: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            border: 2px solid var(--accent-color);
            box-shadow: var(--shadow);
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
            margin-right: 10px;
            border: 1px solid var(--accent-color);
            font-size: 0.85rem;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
            padding: 18px 24px;
            background: var(--primary-color);
            border-radius: var(--border-radius);
            color: white;
            box-shadow: var(--shadow);
        }

        .page-header h1 {
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
        }

        .page-header h1 i {
            color: var(--accent-color);
        }

        .today {
            background: rgba(255, 255, 255, 0.1);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        /* Alert Messages */
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.3s ease;
            font-size: 0.95rem;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border-right: 4px solid var(--success-color);
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-right: 4px solid var(--danger-color);
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
            padding: 24px 20px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid #e2e8f0;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }

        .stat-card i {
            font-size: 2.2rem;
            color: var(--accent-color);
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 10px 0;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
        }

        .stat-card:nth-child(1) { border-top: 4px solid var(--primary-color); }
        .stat-card:nth-child(2) { border-top: 4px solid var(--accent-color); }
        .stat-card:nth-child(3) { border-top: 4px solid var(--success-color); }

        /* Table Container */
        .table-container {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            animation: fadeIn 0.4s ease;
        }

        .table-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color);
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
            font-weight: 700;
        }

        .table-header i {
            color: var(--accent-color);
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .user-table thead {
            background: var(--primary-color);
            color: white;
        }

        .user-table th {
            padding: 15px;
            text-align: right;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-table th:first-child {
            border-top-right-radius: 8px;
        }

        .user-table th:last-child {
            border-top-left-radius: 8px;
        }

        .user-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: var(--transition);
        }

        .user-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .user-table td {
            padding: 15px;
            color: var(--text-color);
            text-align: right;
        }

        .user-table .user-id {
            font-weight: 600;
            color: var(--primary-color);
            font-family: 'Courier New', monospace;
        }

        .user-table .username {
            font-weight: 600;
            color: var(--primary-color);
        }

        .user-table .created-at {
            color: #64748b;
            font-size: 0.9rem;
        }

        .user-table .current-user {
            background-color: #f0fdf4;
        }

        .user-table .current-user td {
            color: var(--success-color);
        }

        .current-badge {
            background: var(--success-color);
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
            margin-right: 5px;
        }

        /* Action Buttons */
        .action-buttons-table {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 8px 14px;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-edit {
            background: var(--edit-color);
            color: white;
        }

        .btn-edit:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.3);
        }

        .btn-delete {
            background: var(--danger-color);
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-view {
            background: var(--info-color);
            color: white;
        }

        .btn-view:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
        }

        .btn-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
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
            border: 1px solid #e2e8f0;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color);
        }

        .form-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        .form-header h2 {
            color: var(--primary-color);
            font-size: 1.3rem;
            font-weight: 700;
        }

        .form-header i {
            color: var(--accent-color);
            font-size: 1.3rem;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: var(--card-bg);
            font-size: 0.95rem;
            color: var(--text-color);
            transition: var(--transition);
            text-align: right;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(30, 60, 114, 0.1);
        }

        .form-input-group {
            position: relative;
        }

        .form-input-group i {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
            pointer-events: none;
        }

        .form-input-group .form-input {
            padding-right: 48px;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            white-space: nowrap;
            min-height: 46px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(30, 60, 114, 0.2);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: var(--primary-color);
            border: 2px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-3px);
        }

        .btn-edit {
            background: var(--edit-color);
            color: white;
        }

        .btn-edit:hover {
            background: #d97706;
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(245, 158, 11, 0.3);
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
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
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
            background: #f8fafc;
            padding: 16px;
            border-radius: 10px;
            margin-top: 10px;
            border-right: 3px solid var(--accent-color);
        }

        .requirements-list h4 {
            color: var(--primary-color);
            font-size: 0.9rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .requirements-list ul {
            list-style: none;
            padding-right: 0;
        }

        .requirements-list li {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .requirements-list li i {
            font-size: 0.8rem;
        }

        .requirements-list li i.fa-check {
            color: var(--success-color);
        }

        .requirements-list li i.fa-times {
            color: var(--danger-color);
        }

        /* Password strength indicator */
        .password-strength {
            margin-top: 6px;
            height: 5px;
            border-radius: 3px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 3px;
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
            margin-top: 4px;
            color: #64748b;
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
            border-top: 4px solid var(--accent-color);
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
            color: var(--accent-color);
            margin-bottom: 15px;
        }

        .modal-header h2 {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 700;
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
            background: #eff6ff;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            color: var(--info-color);
            margin-top: 5px;
            border-right: 3px solid var(--info-color);
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
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-box input {
            width: 100%;
            padding: 10px 45px 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            text-align: right;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(30, 60, 114, 0.1);
        }

        .filter-select {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: white;
            font-size: 0.95rem;
            min-width: 150px;
            font-family: 'Noto Naskh Arabic', sans-serif;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(30, 60, 114, 0.1);
        }

        /* User Details */
        .user-details {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #f8fafc;
            border-radius: 10px;
        }

        .detail-label {
            font-weight: 600;
            color: #64748b;
            font-size: 0.9rem;
        }

        .detail-value {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.95rem;
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
                padding: 14px 16px;
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
                padding: 16px;
            }
            
            .form-input {
                padding: 10px 12px 10px 40px;
                font-size: 0.9rem;
            }
            
            .modal-content {
                padding: 20px;
                width: 95%;
            }
        }

        /* Main Content spacing for sidebar */
        .main-content {
            padding: 20px;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
                padding-top: 70px;
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
            <span>د مدیریت کنټرول پینل</span>
            <span class="admin-badge-large">
                <i class="fas fa-user"></i>
                ننوتل د: <?php echo htmlspecialchars($current_user); ?>
            </span>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-users-cog"></i>
                د کاروونکو مدیریت
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
                <div class="stat-label">ټول کاروونکي</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-plus"></i>
                <div class="stat-number"><?php echo count(array_filter($users, function($user) {
                    return strtotime($user['created_at']) > strtotime('-7 days');
                })); ?></div>
                <div class="stat-label">دې اونۍ نوي</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-check"></i>
                <div class="stat-number">1</div>
                <div class="stat-label">فعال اوس</div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="table-container">
            <div class="table-header">
                <h2><i class="fas fa-list"></i> ټول کاروونکي</h2>
                <div class="table-actions">
                    <button class="btn btn-secondary" onclick="refreshTable()">
                        <i class="fas fa-sync-alt"></i> تازه کړئ
                    </button>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="table-controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="کاروونکي پلټئ..." onkeyup="searchUsers()">
                </div>
                <select class="filter-select" id="filterSelect" onchange="filterUsers()">
                    <option value="all">ټول کاروونکي</option>
                    <option value="new">دې اونۍ نوي</option>
                    <option value="current">اوسنی کارن</option>
                </select>
            </div>

            <div class="table-wrapper">
                <?php if(count($users) > 0): ?>
                    <table class="user-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>کارن نوم</th>
                                <th>جوړولو نېټه</th>
                                <th>کړنې</th>
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
                                                <i class="fas fa-user-check"></i> تاسو
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
                                                <i class="fas fa-eye"></i> کتل
                                            </button>
                                            <button class="btn-action btn-edit" onclick="editUser(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-edit"></i> سمول
                                            </button>
                                            <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                <button class="btn-action btn-delete" 
                                                        onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-trash"></i> ړنګول
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-action btn-delete" disabled>
                                                    <i class="fas fa-trash"></i> ړنګول
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
                        <h3>هیڅ کارن ونه موندل شو</h3>
                        <p>د لومړي کارن حساب جوړولو سره پیل وکړئ.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Create User Form -->
        <div class="form-container">
            <div class="form-header">
                <i class="fas fa-user-plus"></i>
                <h2>نوی کارن حساب اضافه کړئ</h2>
            </div>

            <form method="POST" id="createUserForm">
                <div class="form-grid">
                    <!-- Username -->
                    <div class="form-group">
                        <label class="form-label">کارن نوم *</label>
                        <div class="form-input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" class="form-input" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                   placeholder="کارن نوم ولیکئ (لږ تر لږه ۳ توري)" required
                                   oninput="checkUsername()">
                        </div>
                        <div id="username-feedback" class="strength-text"></div>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label class="form-label">پټ‌نوم *</label>
                        <div class="form-input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" class="form-input" id="password" name="password" 
                                   placeholder="پټ‌نوم ولیکئ (لږ تر لږه ۶ توري)" required
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
                        <label class="form-label">پټ‌نوم تایید کړئ *</label>
                        <div class="form-input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" class="form-input" id="confirm_password" name="confirm_password" 
                                   placeholder="پټ‌نوم تایید کړئ" required oninput="checkPasswordMatch()">
                            <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="password-match-feedback" class="strength-text"></div>
                    </div>
                </div>

                <!-- Password Requirements -->
                <div class="requirements-list">
                    <h4>د پټ‌نوم اړتیاوې:</h4>
                    <ul>
                        <li id="req-length"><i class="fas fa-check"></i> لږ تر لږه ۶ توري</li>
                        <li id="req-uppercase"><i class="fas fa-times"></i> لوی توری لري</li>
                        <li id="req-lowercase"><i class="fas fa-check"></i> کوچنی توری لري</li>
                        <li id="req-number"><i class="fas fa-times"></i> شمېره لري</li>
                        <li id="req-special"><i class="fas fa-times"></i> ځانګړی توری لري</li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                        <i class="fas fa-redo"></i> فورم پاک کړئ
                    </button>
                    
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='dashboard.php'">
                        <i class="fas fa-arrow-right"></i> ډشبورډ ته ستنیدل
                    </button>
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> کارن حساب جوړ کړئ
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
                <h2>د ړنګولو تایید</h2>
            </div>
            <div class="modal-body">
                <p>ایا تاسو ډاډه یاست چې غواړئ د <strong id="deleteUsername"></strong> کارن ړنګ کړئ؟</p>
                <p style="color: var(--danger-color); font-size: 0.9rem; margin-top: 10px;">
                    <i class="fas fa-warning"></i> دا عمل بیرته نه شي راګرځیدلی!
                </p>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> لغوه
                </button>
                <button class="btn btn-delete" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> کارن ړنګ کړئ
                </button>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal edit-modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-user-edit"></i>
                <h2>کارن سمول</h2>
            </div>
            <div class="modal-body">
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="form-group">
                        <label class="form-label">کارن نوم *</label>
                        <div class="form-input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" class="form-input" id="edit_username" name="username" 
                                   placeholder="کارن نوم ولیکئ (لږ تر لږه ۳ توري)" required
                                   oninput="checkEditUsername()">
                        </div>
                        <div id="edit-username-feedback" class="strength-text"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">نوی پټ‌نوم (د اوسني ساتلو لپاره خالي پریږدئ)</label>
                        <div class="form-input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" class="form-input" id="edit_password" name="password" 
                                   placeholder="نوی پټ‌نوم ولیکئ" oninput="checkEditPasswordStrength()">
                            <button type="button" class="password-toggle" id="toggleEditPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-hint">
                            <i class="fas fa-info-circle"></i> یوازې هغه وخت ډک کړئ چې غواړئ پټ‌نوم بدل کړئ
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">نوی پټ‌نوم تایید کړئ</label>
                        <div class="form-input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" class="form-input" id="edit_confirm_password" name="confirm_password"
                                   placeholder="نوی پټ‌نوم تایید کړئ" oninput="checkEditPasswordMatch()">
                            <button type="button" class="password-toggle" id="toggleEditConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="edit-password-match-feedback" class="strength-text"></div>
                    </div>

                    <div id="edit-password-requirements" class="requirements-list" style="display: none;">
                        <h4>د پټ‌نوم اړتیاوې:</h4>
                        <ul>
                            <li id="edit-req-length"><i class="fas fa-times"></i> لږ تر لږه ۶ توري</li>
                            <li id="edit-req-uppercase"><i class="fas fa-times"></i> لوی توری لري</li>
                            <li id="edit-req-lowercase"><i class="fas fa-times"></i> کوچنی توری لري</li>
                            <li id="edit-req-number"><i class="fas fa-times"></i> شمېره لري</li>
                            <li id="edit-req-special"><i class="fas fa-times"></i> ځانګړی توری لري</li>
                        </ul>
                    </div>

                    <div class="modal-buttons">
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                            <i class="fas fa-times"></i> لغوه
                        </button>
                        <button type="submit" class="btn btn-edit" id="editSubmitBtn">
                            <i class="fas fa-save"></i> کارن تازه کړئ
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
                <h2>د کارن توضیحات</h2>
            </div>
            <div class="modal-body">
                <div id="userDetailsContent">
                    <!-- Dynamic content will be loaded here -->
                </div>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-secondary" onclick="closeViewModal()">
                    <i class="fas fa-times"></i> بندول
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
                feedback.textContent = 'کارن نوم باید لږ تر لږه ۳ توري وي';
                feedback.style.color = 'var(--danger-color)';
                return;
            }
            
            const isTaken = allUsers.some(user => user.username.toLowerCase() === username.toLowerCase());
            
            if(isTaken) {
                feedback.textContent = 'کارن نوم لا دمخه اخیستل شوی';
                feedback.style.color = 'var(--danger-color)';
            } else {
                feedback.textContent = 'کارن نوم شتون لري';
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
                feedback.textContent = 'کارن نوم باید لږ تر لږه ۳ توري وي';
                feedback.style.color = 'var(--danger-color)';
                return;
            }
            
            const isTaken = allUsers.some(user => 
                user.username.toLowerCase() === username.toLowerCase() && 
                user.id !== currentUserId
            );
            
            if(isTaken) {
                feedback.textContent = 'کارن نوم لا دمخه اخیستل شوی';
                feedback.style.color = 'var(--danger-color)';
            } else {
                feedback.textContent = 'کارن نوم شتون لري';
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
                strengthText.textContent = 'کمزوری پټ‌نوم';
                strengthText.style.color = 'var(--danger-color)';
            } else if(strength <= 80) {
                strengthBar.className = 'strength-bar strength-medium';
                strengthText.textContent = 'منځنی پټ‌نوم';
                strengthText.style.color = 'var(--warning-color)';
            } else {
                strengthBar.className = 'strength-bar strength-strong';
                strengthText.textContent = 'قوي پټ‌نوم';
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
                feedback.textContent = 'پټ‌نومونه سره سمون لري';
                feedback.style.color = 'var(--success-color)';
            } else {
                feedback.textContent = 'پټ‌نومونه سره سمون نه لري';
                feedback.style.color = 'var(--danger-color)';
            }
        }

        // Check password match for edit form
        function checkEditPasswordMatch() {
            const password = editPassword.value;
            const confirmPassword = editConfirmPassword.value;
            const feedback = document.getElementById('edit-password-match-feedback');
            
            if(password.length === 0 && confirmPassword.length === 0) {
                feedback.textContent = '';
                return;
            }
            
            if(password.length === 0 && confirmPassword.length > 0) {
                feedback.textContent = 'لومړی نوی پټ‌نوم ولیکئ';
                feedback.style.color = 'var(--warning-color)';
                return;
            }
            
            if(password === confirmPassword) {
                feedback.textContent = '✓ پټ‌نومونه سره سمون لري';
                feedback.style.color = 'var(--success-color)';
            } else {
                feedback.textContent = '✗ پټ‌نومونه سره سمون نه لري';
                feedback.style.color = 'var(--danger-color)';
            }
        }

        // Validate create form
        function validateForm() {
            const username = usernameInput.value.trim();
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if(username.length < 3) {
                alert('کارن نوم باید لږ تر لږه ۳ توري وي');
                usernameInput.focus();
                return false;
            }
            
            if(password.length < 6) {
                alert('پټ‌نوم باید لږ تر لږه ۶ توري وي');
                passwordInput.focus();
                return false;
            }
            
            if(password !== confirmPassword) {
                alert('پټ‌نومونه سره سمون نه لري');
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
                alert('کارن نوم باید لږ تر لږه ۳ توري وي');
                editUsername.focus();
                return false;
            }
            
            if(password.length > 0 || confirmPassword.length > 0) {
                if(password.length < 6) {
                    alert('پټ‌نوم باید لږ تر لږه ۶ توري وي');
                    editPassword.focus();
                    return false;
                }
                
                if(password !== confirmPassword) {
                    alert('پټ‌نومونه سره سمون نه لري');
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
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> کارن جوړیږي...';
            submitBtn.disabled = true;
        });

        // Edit form submission
        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            if(!validateEditForm()) {
                e.preventDefault();
                return;
            }
            
            editSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> کارن تازه کیږي...';
            editSubmitBtn.disabled = true;
        });

        // Reset create form
        function resetForm() {
            if(confirm('ایا تاسو ډاډه یاست چې غواړئ فورم پاک کړئ؟ ټول معلومات به له منځه لاړ شي.')) {
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
            const formattedDate = createdDate.toLocaleDateString('ps-AF', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const details = `
                <div class="user-details">
                    <div class="detail-item">
                        <div class="detail-label">د کارن ID</div>
                        <div class="detail-value">#${user.id}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">کارن نوم</div>
                        <div class="detail-value">${user.username}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">جوړولو نېټه</div>
                        <div class="detail-value">${formattedDate}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">د حساب عمر</div>
                        <div class="detail-value">${daysAgo} ورځې</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">حالت</div>
                        <div class="detail-value" style="color: var(--success-color);">
                            <i class="fas fa-check-circle"></i> فعال
                        </div>
                    </div>
                </div>
                <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 10px;">
                    <div class="detail-label">ژر کړنې</div>
                    <div style="margin-top: 10px; display: flex; gap: 10px;">
                        <button class="btn-action btn-edit" style="flex: 1;" onclick="closeViewModal(); editUser(${user.id})">
                            <i class="fas fa-edit"></i> کارن سمول
                        </button>
                        ${userId != <?php echo $_SESSION['user_id']; ?> ? 
                            `<button class="btn-action btn-delete" style="flex: 1;" onclick="closeViewModal(); confirmDelete(${userId}, '${user.username}')">
                                <i class="fas fa-trash"></i> کارن ړنګول
                            </button>` : 
                            `<button class="btn-action btn-delete" style="flex: 1;" disabled>
                                <i class="fas fa-trash"></i> کارن ړنګول
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