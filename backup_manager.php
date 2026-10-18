<?php
session_start();
require_once 'db.php'; // Must define $conn (MySQLi connection)

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$backupDir = __DIR__ . "/backups/";

// Create backup directory if it doesn't exist
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$success_message = '';
$error_message = '';

// Retrieve flash messages from session (PRG pattern)
if (isset($_SESSION['flash_success'])) {
    $success_message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $error_message = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

/* ============================================================
   PURE PHP BACKUP FUNCTION (Works on cPanel - no exec needed)
   ============================================================ */
function backupDatabase($conn, $backupDir) {
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    if (!$result) {
        return ['success' => false, 'error' => $conn->error];
    }
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }

    $sql  = "-- Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Host: " . $conn->host_info . "\n\n";
    $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sql .= "SET time_zone = \"+00:00\";\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Table structure
        $res = $conn->query("SHOW CREATE TABLE `$table`");
        if (!$res) continue;
        $row = $res->fetch_array();
        $sql .= "\n-- --------------------------------------------------------\n";
        $sql .= "-- Table structure for `$table`\n";
        $sql .= "-- --------------------------------------------------------\n\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $row[1] . ";\n\n";

        // Table data
        $res = $conn->query("SELECT * FROM `$table`");
        if (!$res) continue;
        $numFields = $res->field_count;

        if ($res->num_rows > 0) {
            $sql .= "--\n-- Dumping data for table `$table`\n--\n\n";
            while ($row = $res->fetch_row()) {
                $sql .= "INSERT INTO `$table` VALUES(";
                for ($j = 0; $j < $numFields; $j++) {
                    if (is_null($row[$j])) {
                        $sql .= "NULL";
                    } else {
                        $sql .= "'" . $conn->real_escape_string($row[$j]) . "'";
                    }
                    if ($j < ($numFields - 1)) $sql .= ",";
                }
                $sql .= ");\n";
            }
            $sql .= "\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $filename = "db_backup_" . date("Y-m-d_H-i-s") . ".sql";
    $filepath = $backupDir . $filename;

    if (file_put_contents($filepath, $sql) !== false) {
        return ['success' => true, 'file' => $filename];
    }
    return ['success' => false, 'error' => 'د فایل د لیکلو اجازه نشته'];
}

/* ============================================================
   PURE PHP RESTORE FUNCTION
   ============================================================ */
function restoreDatabase($conn, $filepath) {
    $sql = file_get_contents($filepath);
    if ($sql === false) return false;

    // Remove comments and split into statements safely
    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        return true;
    }
    return false;
}

/* ============================================================
   TAKE BACKUP (POST → redirect to prevent duplicate on refresh)
   ============================================================ */
if (isset($_POST['backup'])) {
    // Increase limits for large DBs
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');

    $result = backupDatabase($conn, $backupDir);

    if ($result['success']) {
        $_SESSION['flash_success'] = "بیک اپ په بریالیتوب سره جوړ شو: " . $result['file'];
    } else {
        $_SESSION['flash_error'] = "بیک اپ ناکام شو! تېروتنه: " . $result['error'];
    }

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

/* ============================================================
   RESTORE
   ============================================================ */
if (isset($_GET['restore'])) {
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');

    $filename = basename($_GET['restore']);
    $file = $backupDir . $filename;

    if (file_exists($file)) {
        if (restoreDatabase($conn, $file)) {
            $_SESSION['flash_success'] = "ډیټابیس په بریالیتوب سره بحال شو د: " . $filename;
        } else {
            $_SESSION['flash_error'] = "بحالول ناکام شول! تېروتنه: " . $conn->error;
        }
    } else {
        $_SESSION['flash_error'] = "د بیک اپ فایل ونه موندل شو!";
    }

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

/* ============================================================
   DELETE
   ============================================================ */
if (isset($_GET['delete'])) {
    $filename = basename($_GET['delete']);
    $file = $backupDir . $filename;

    if (file_exists($file)) {
        if (unlink($file)) {
            $_SESSION['flash_success'] = "بیک اپ په بریالیتوب سره ړنګ شو: " . $filename;
        } else {
            $_SESSION['flash_error'] = "د بیک اپ فایل ړنګول ناکام شول!";
        }
    } else {
        $_SESSION['flash_error'] = "د بیک اپ فایل ونه موندل شو!";
    }

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// Get all backup files
$files = glob($backupDir . "*.sql");
$backup_files = [];
foreach ($files as $f) {
    $backup_files[] = [
        'name' => basename($f),
        'path' => $f,
        'size' => filesize($f),
        'modified' => filemtime($f)
    ];
}

// Sort by modified time (newest first)
usort($backup_files, function($a, $b) {
    return $b['modified'] - $a['modified'];
});

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['بایټ', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>

<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>د ډیټابیس بیک اپ · </title>
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
            --text-color: #1f2937;
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

        .backup-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .backup-table thead {
            background: var(--primary-color);
            color: white;
        }

        .backup-table th {
            padding: 15px;
            text-align: right;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .backup-table th:first-child {
            border-top-right-radius: 8px;
        }

        .backup-table th:last-child {
            border-top-left-radius: 8px;
        }

        .backup-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: var(--transition);
        }

        .backup-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .backup-table td {
            padding: 15px;
            color: var(--text-color);
            text-align: right;
        }

        .backup-table .backup-name {
            font-weight: 600;
            color: var(--primary-color);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }

        .backup-table .backup-size,
        .backup-table .backup-date {
            color: #64748b;
            font-size: 0.9rem;
        }

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

        .btn-download {
            background: var(--success-color);
            color: white;
        }

        .btn-download:hover {
            background: #16a34a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34, 197, 94, 0.3);
        }

        .btn-restore {
            background: var(--info-color);
            color: white;
        }

        .btn-restore:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
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

        .backup-controls-container {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .backup-controls-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color);
        }

        .controls-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        .controls-header h2 {
            color: var(--primary-color);
            font-size: 1.3rem;
            font-weight: 700;
        }

        .controls-header i {
            color: var(--accent-color);
            font-size: 1.3rem;
        }

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

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .backup-info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            margin-top: 25px;
            border-right: 4px solid var(--info-color);
        }

        .backup-info h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }

        .backup-info ul {
            list-style: none;
            padding-right: 0;
        }

        .backup-info li {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .backup-info li i {
            color: var(--accent-color);
            font-size: 0.8rem;
            margin-top: 5px;
        }

        .backup-info code {
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            direction: ltr;
            display: inline-block;
        }

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
            box-shadow: var(--shadow-hover);
            animation: modalFadeIn 0.3s ease;
            border-top: 4px solid var(--accent-color);
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-header {
            margin-bottom: 20px;
            text-align: center;
        }

        .modal-header i {
            font-size: 3rem;
            color: var(--info-color);
            margin-bottom: 15px;
        }

        .modal-header h2 {
            color: var(--primary-color);
            font-weight: 700;
        }

        .modal-body {
            text-align: center;
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
            background: var(--card-bg);
            color: var(--text-color);
            transition: var(--transition);
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
            background: var(--card-bg);
            font-size: 0.95rem;
            min-width: 150px;
            color: var(--text-color);
            transition: var(--transition);
            font-family: 'Noto Naskh Arabic', sans-serif;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(30, 60, 114, 0.1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .backup-table th, 
            .backup-table td {
                padding: 12px 10px;
                font-size: 0.85rem;
            }
            
            .btn-action {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            
            .backup-controls-container,
            .table-container {
                padding: 16px;
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
            
            .table-controls {
                flex-direction: column;
            }
            
            .search-box,
            .filter-select {
                min-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.3rem;
            }
            
            .table-container,
            .backup-controls-container {
                padding: 16px;
            }
            
            .modal-content {
                padding: 20px;
                width: 95%;
            }
            
            .action-buttons-table {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
        }

        .backup-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success-color);
        }

        .size-formatter {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }

        .main-content {
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-database"></i>
                د ډیټابیس بیک اپ مدیر
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
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Backup Statistics -->
        <div class="stats-cards">
            <div class="stat-card">
                <i class="fas fa-database"></i>
                <div class="stat-number"><?php echo count($backup_files); ?></div>
                <div class="stat-label">ټول بیک اپونه</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-hdd"></i>
                <div class="stat-number">
                    <?php 
                    $totalSize = 0;
                    foreach($backup_files as $file) {
                        $totalSize += $file['size'];
                    }
                    echo round($totalSize / 1024 / 1024, 2) . ' MB';
                    ?>
                </div>
                <div class="stat-label">ټول اندازه</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <div class="stat-number">
                    <?php 
                    if(count($backup_files) > 0) {
                        echo date('M j', $backup_files[0]['modified']);
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </div>
                <div class="stat-label">وروستی بیک اپ</div>
            </div>
        </div>

        <!-- Backup Controls -->
        <div class="backup-controls-container">
            <div class="controls-header">
                <i class="fas fa-cogs"></i>
                <h2>د بیک اپ عملیات</h2>
            </div>

            <form method="POST" id="backupForm">
                <div class="action-buttons">
                    <button type="submit" name="backup" class="btn btn-primary" onclick="return confirmBackup()">
                        <i class="fas fa-save"></i> نوی بیک اپ جوړ کړئ
                    </button>
                    
                    <button type="button" class="btn btn-secondary" onclick="refreshPage()">
                        <i class="fas fa-sync-alt"></i> لیست تازه کړئ
                    </button>
                    
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> ډشبورډ ته ستنیدل
                    </a>
                </div>
            </form>

            <!-- Backup Information -->
            <div class="backup-info">
                <h3><i class="fas fa-info-circle"></i> د بیک اپ معلومات</h3>
                <ul>
                    <li><i class="fas fa-check"></i> بیک اپونه دلته خوندي کیږي: <code><?php echo htmlspecialchars($backupDir); ?></code></li>
                    <li><i class="fas fa-check"></i> هر بیک اپ فایل د ډیټابیس بشپړ جوړښت او معلومات لري</li>
                    <li><i class="fas fa-check"></i> بحالول به ټول اوسني معلومات د بیک اپ معلوماتو سره بدل کړي</li>
                    <li><i class="fas fa-check"></i> د معلوماتو خوندیتوب لپاره منظم بیک اپونه وړاندیز کیږي</li>
                </ul>
            </div>
        </div>

        <!-- Backup Files Table -->
        <div class="table-container">
            <div class="table-header">
                <h2><i class="fas fa-list"></i> د بیک اپ فایلونه</h2>
                <div class="table-actions">
                    <span class="backup-status status-success">
                        <i class="fas fa-circle"></i>
                        <?php echo count($backup_files); ?> بیک اپونه شتون لري
                    </span>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="table-controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="د بیک اپ فایلونه پلټئ..." onkeyup="searchBackups()">
                </div>
                <select class="filter-select" id="filterSelect" onchange="filterBackups()">
                    <option value="all">ټول بیک اپونه</option>
                    <option value="recent">وروستۍ ۷ ورځې</option>
                    <option value="large">لوی فایلونه (>10MB)</option>
                </select>
            </div>

            <div class="table-wrapper">
                <?php if(count($backup_files) > 0): ?>
                    <table class="backup-table" id="backupTable">
                        <thead>
                            <tr>
                                <th>د فایل نوم</th>
                                <th>اندازه</th>
                                <th>د بدلون نېټه</th>
                                <th>کړنې</th>
                            </tr>
                        </thead>
                        <tbody id="backupTableBody">
                            <?php foreach($backup_files as $file): 
                                $size = $file['size'];
                                $sizeFormatted = formatFileSize($size);
                                $modifiedDate = date('M j, Y H:i', $file['modified']);
                                $daysAgo = floor((time() - $file['modified']) / (86400));
                                $isRecent = $daysAgo <= 7;
                            ?>
                                <tr data-name="<?php echo htmlspecialchars($file['name']); ?>"
                                    data-size="<?php echo $size; ?>"
                                    data-date="<?php echo $file['modified']; ?>"
                                    data-recent="<?php echo $isRecent ? 'yes' : 'no'; ?>"
                                    data-large="<?php echo $size > 10 * 1024 * 1024 ? 'yes' : 'no'; ?>">
                                    <td class="backup-name">
                                        <i class="fas fa-file-code"></i>
                                        <?php echo htmlspecialchars($file['name']); ?>
                                    </td>
                                    <td class="backup-size size-formatter">
                                        <?php echo $sizeFormatted; ?>
                                    </td>
                                    <td class="backup-date">
                                        <i class="far fa-calendar"></i>
                                        <?php echo $modifiedDate; ?>
                                        <?php if($isRecent): ?>
                                            <span class="backup-status status-success">
                                                <i class="fas fa-clock"></i> نوی
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons-table">
                                            <a href="backups/<?php echo urlencode($file['name']); ?>" 
                                               download 
                                               class="btn-action btn-download">
                                                <i class="fas fa-download"></i> ډاونلوډ
                                            </a>
                                            <button class="btn-action btn-restore" 
                                                    onclick="confirmRestore('<?php echo htmlspecialchars($file['name']); ?>')">
                                                <i class="fas fa-history"></i> بحالول
                                            </button>
                                            <button class="btn-action btn-delete" 
                                                    onclick="confirmDelete('<?php echo htmlspecialchars($file['name']); ?>')">
                                                <i class="fas fa-trash"></i> ړنګول
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-database"></i>
                        <h3>هیڅ بیک اپ فایل ونه موندل شو</h3>
                        <p>د پورته "نوی بیک اپ جوړ کړئ" تڼۍ په کارولو سره خپل لومړی بیک اپ جوړ کړئ.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div id="restoreModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-history"></i>
                <h2>د بحالولو تایید</h2>
            </div>
            <div class="modal-body">
                <p>ایا تاسو ډاډه یاست چې غواړئ د <strong id="restoreFilename"></strong> بیک اپ بحال کړئ؟</p>
                <p style="color: var(--warning-color); font-size: 0.9rem; margin-top: 10px;">
                    <i class="fas fa-warning"></i> دا به ټول اوسني معلومات د بیک اپ معلوماتو سره بدل کړي!
                </p>
                <p style="color: var(--danger-color); font-size: 0.9rem; margin-top: 10px;">
                    <i class="fas fa-exclamation-triangle"></i> دا عمل بیرته نه شي راګرځیدلی!
                </p>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-secondary" onclick="closeModal('restoreModal')">
                    <i class="fas fa-times"></i> لغوه
                </button>
                <button class="btn btn-restore" id="confirmRestoreBtn">
                    <i class="fas fa-history"></i> بیک اپ بحال کړئ
                </button>
            </div>
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
                <p>ایا تاسو ډاډه یاست چې غواړئ د <strong id="deleteFilename"></strong> بیک اپ فایل ړنګ کړئ؟</p>
                <p style="color: var(--danger-color); font-size: 0.9rem; margin-top: 10px;">
                    <i class="fas fa-warning"></i> دا عمل بیرته نه شي راګرځیدلی!
                </p>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">
                    <i class="fas fa-times"></i> لغوه
                </button>
                <button class="btn btn-delete" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> بیک اپ ړنګ کړئ
                </button>
            </div>
        </div>
    </div>

    <script>
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        const searchInput = document.getElementById('searchInput');
        const filterSelect = document.getElementById('filterSelect');
        let allBackups = [];
        let backupToRestore = null;
        let backupToDelete = null;

        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('#backupTableBody tr');
            allBackups = Array.from(rows).map(row => ({
                name: row.dataset.name,
                size: parseInt(row.dataset.size),
                date: parseInt(row.dataset.date),
                recent: row.dataset.recent === 'yes',
                large: row.dataset.large === 'yes',
                element: row
            }));
        });

        function searchBackups() {
            const searchTerm = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('#backupTableBody tr');
            
            rows.forEach(row => {
                const name = row.dataset.name.toLowerCase();
                const shouldShow = name.includes(searchTerm);
                row.style.display = shouldShow ? '' : 'none';
            });
        }

        function filterBackups() {
            const filter = filterSelect.value;
            const rows = document.querySelectorAll('#backupTableBody tr');
            const oneWeekAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
            
            rows.forEach(row => {
                const date = parseInt(row.dataset.date) * 1000;
                const size = parseInt(row.dataset.size);
                const isRecent = date > oneWeekAgo;
                const isLarge = size > 10 * 1024 * 1024;
                
                let shouldShow = true;
                
                if(filter === 'recent') {
                    shouldShow = isRecent;
                } else if(filter === 'large') {
                    shouldShow = isLarge;
                }
                
                row.style.display = shouldShow ? '' : 'none';
            });
        }

        function refreshPage() {
            window.location.href = window.location.pathname;
        }

        function confirmBackup() {
            return confirm('ایا تاسو ډاډه یاست چې غواړئ نوی بیک اپ جوړ کړئ؟ دا ممکن څو شېبې وخت ونیسي.');
        }

        function confirmRestore(filename) {
            backupToRestore = filename;
            document.getElementById('restoreFilename').textContent = filename;
            document.getElementById('restoreModal').style.display = 'flex';
        }

        function restoreBackup() {
            if(backupToRestore) {
                window.location.href = '?restore=' + encodeURIComponent(backupToRestore);
            }
        }

        function confirmDelete(filename) {
            backupToDelete = filename;
            document.getElementById('deleteFilename').textContent = filename;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function deleteBackup() {
            if(backupToDelete) {
                window.location.href = '?delete=' + encodeURIComponent(backupToDelete);
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if(modalId === 'restoreModal') backupToRestore = null;
            if(modalId === 'deleteModal') backupToDelete = null;
        }

        window.onclick = function(event) {
            const restoreModal = document.getElementById('restoreModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if(event.target == restoreModal) closeModal('restoreModal');
            if(event.target == deleteModal) closeModal('deleteModal');
        }

        document.getElementById('confirmRestoreBtn').addEventListener('click', restoreBackup);
        document.getElementById('confirmDeleteBtn').addEventListener('click', deleteBackup);

        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape') {
                closeModal('restoreModal');
                closeModal('deleteModal');
            }
        });

        document.addEventListener('touchstart', function() {}, {passive: true});

        <?php if($success_message): ?>
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => alert.style.display = 'none', 500);
                });
            }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>