<?php
session_start();
require_once 'db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$backupDir = __DIR__ . "/backups/";

// Create backup directory if it doesn't exist
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0777, true);
}

$success_message = '';
$error_message = '';

// Take backup
if (isset($_POST['backup'])) {
    $file = $backupDir . "db_backup_" . date("Y-m-d_H-i-s") . ".sql";
    $mysqldump = "C:\\xampp\\mysql\\bin\\mysqldump.exe";

    $cmd = "\"$mysqldump\" -u root ph > \"$file\" 2>&1";
    exec($cmd, $output, $return_var);
    
    if($return_var === 0) {
        $success_message = "Backup created successfully: " . basename($file);
    } else {
        $error_message = "Backup failed! Error: " . implode("\n", $output);
    }
}

// Restore
if (isset($_GET['restore'])) {
    $filename = basename($_GET['restore']);
    $file = $backupDir . $filename;
    
    if(file_exists($file)) {
        $mysql = "C:\\xampp\\mysql\\bin\\mysql.exe";
        $cmd = "\"$mysql\" -u root ph < \"$file\" 2>&1";
        exec($cmd, $output, $return_var);
        
        if($return_var === 0) {
            $success_message = "Database restored successfully from: " . $filename;
        } else {
            $error_message = "Restore failed! Error: " . implode("\n", $output);
        }
    } else {
        $error_message = "Backup file not found!";
    }
}

// Delete
if (isset($_GET['delete'])) {
    $filename = basename($_GET['delete']);
    $file = $backupDir . $filename;
    
    if(file_exists($file)) {
        if(unlink($file)) {
            $success_message = "Backup deleted successfully: " . $filename;
        } else {
            $error_message = "Failed to delete backup file!";
        }
    } else {
        $error_message = "Backup file not found!";
    }
    
    // Redirect to clear URL parameters
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - PharmaCare RX</title>
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

        .backup-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .backup-table thead {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .backup-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .backup-table th:first-child {
            border-top-left-radius: 8px;
        }

        .backup-table th:last-child {
            border-top-right-radius: 8px;
        }

        .backup-table tbody tr {
            border-bottom: 1px solid #e0e0e0;
            transition: var(--transition);
        }

        .backup-table tbody tr:hover {
            background-color: rgba(38, 208, 206, 0.05);
        }

        .backup-table td {
            padding: 15px;
            color: var(--text-color);
        }

        .backup-table .backup-name {
            font-weight: 600;
            color: var(--primary-color);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }

        .backup-table .backup-size {
            color: #666;
            font-size: 0.9rem;
        }

        .backup-table .backup-date {
            color: #666;
            font-size: 0.9rem;
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

        .btn-download {
            background: linear-gradient(45deg, var(--success-color), #2e7d32);
            color: white;
        }

        .btn-download:hover {
            background: linear-gradient(45deg, #2e7d32, var(--success-color));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .btn-restore {
            background: linear-gradient(45deg, var(--info-color), #138496);
            color: white;
        }

        .btn-restore:hover {
            background: linear-gradient(45deg, #138496, var(--info-color));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.3);
        }

        .btn-delete {
            background: linear-gradient(45deg, var(--danger-color), #c82333);
            color: white;
        }

        .btn-delete:hover {
            background: linear-gradient(45deg, #c82333, var(--danger-color));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        /* Backup Controls Container */
        .backup-controls-container {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .backup-controls-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary-color), var(--accent-color));
        }

        .controls-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }

        .controls-header h2 {
            color: var(--primary-color);
            font-size: 1.3rem;
        }

        .controls-header i {
            color: var(--secondary-color);
            font-size: 1.3rem;
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

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        /* Backup Info Section */
        .backup-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 25px;
            border-left: 4px solid var(--info-color);
        }

        .backup-info h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .backup-info ul {
            list-style: none;
            padding-left: 0;
        }

        .backup-info li {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .backup-info li i {
            color: var(--secondary-color);
            font-size: 0.8rem;
            margin-top: 3px;
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

        /* Confirmation Modal */
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
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: var(--shadow-hover);
            animation: modalFadeIn 0.3s ease;
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
            background: var(--card-bg);
            color: var(--text-color);
            transition: var(--transition);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(38, 208, 206, 0.15);
        }

        .filter-select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: var(--card-bg);
            font-size: 0.95rem;
            min-width: 150px;
            color: var(--text-color);
            transition: var(--transition);
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(38, 208, 206, 0.15);
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
                padding: 15px;
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
                padding: 15px;
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

        /* Backup Status Indicators */
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
            background: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }

        .status-warning {
            background: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
        }

        /* Size Formatter */
        .size-formatter {
            font-family: 'Courier New', monospace;
            font-weight: 600;
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
                Database Backup Manager
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

        <!-- Backup Statistics -->
        <div class="stats-cards">
            <div class="stat-card">
                <i class="fas fa-database"></i>
                <div class="stat-number"><?php echo count($backup_files); ?></div>
                <div class="stat-label">Total Backups</div>
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
                <div class="stat-label">Total Size</div>
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
                <div class="stat-label">Latest Backup</div>
            </div>
        </div>

        <!-- Backup Controls -->
        <div class="backup-controls-container">
            <div class="controls-header">
                <i class="fas fa-cogs"></i>
                <h2>Backup Operations</h2>
            </div>

            <form method="POST" id="backupForm">
                <div class="action-buttons">
                    <button type="submit" name="backup" class="btn btn-primary" onclick="return confirmBackup()">
                        <i class="fas fa-save"></i> Create New Backup
                    </button>
                    
                    <button type="button" class="btn btn-secondary" onclick="refreshPage()">
                        <i class="fas fa-sync-alt"></i> Refresh List
                    </button>
                    
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>

            <!-- Backup Information -->
            <div class="backup-info">
                <h3><i class="fas fa-info-circle"></i> Backup Information</h3>
                <ul>
                    <li><i class="fas fa-check"></i> Backups are stored in: <code><?php echo htmlspecialchars($backupDir); ?></code></li>
                    <li><i class="fas fa-check"></i> Each backup file contains the complete database structure and data</li>
                    <li><i class="fas fa-check"></i> Restoring will replace all current data with the backup data</li>
                    <li><i class="fas fa-check"></i> Regular backups are recommended for data safety</li>
                </ul>
            </div>
        </div>

        <!-- Backup Files Table -->
        <div class="table-container">
            <div class="table-header">
                <h2><i class="fas fa-list"></i> Backup Files</h2>
                <div class="table-actions">
                    <span class="backup-status status-success">
                        <i class="fas fa-circle"></i>
                        <?php echo count($backup_files); ?> backups available
                    </span>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="table-controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search backup files..." onkeyup="searchBackups()">
                </div>
                <select class="filter-select" id="filterSelect" onchange="filterBackups()">
                    <option value="all">All Backups</option>
                    <option value="recent">Last 7 Days</option>
                    <option value="large">Large Files (>10MB)</option>
                </select>
            </div>

            <div class="table-wrapper">
                <?php if(count($backup_files) > 0): ?>
                    <table class="backup-table" id="backupTable">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Size</th>
                                <th>Modified Date</th>
                                <th>Actions</th>
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
                                                <i class="fas fa-clock"></i> Recent
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons-table">
                                            <a href="backups/<?php echo urlencode($file['name']); ?>" 
                                               download 
                                               class="btn-action btn-download">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <button class="btn-action btn-restore" 
                                                    onclick="confirmRestore('<?php echo htmlspecialchars($file['name']); ?>')">
                                                <i class="fas fa-history"></i> Restore
                                            </button>
                                            <button class="btn-action btn-delete" 
                                                    onclick="confirmDelete('<?php echo htmlspecialchars($file['name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
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
                        <h3>No Backup Files Found</h3>
                        <p>Create your first backup using the "Create New Backup" button above.</p>
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
                <h2>Confirm Restore</h2>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to restore from backup <strong id="restoreFilename"></strong>?</p>
                <p style="color: var(--warning-color); font-size: 0.9rem; margin-top: 10px;">
                    <i class="fas fa-warning"></i> This will replace ALL current data with the backup data!
                </p>
                <p style="color: var(--danger-color); font-size: 0.9rem; margin-top: 10px;">
                    <i class="fas fa-exclamation-triangle"></i> This action cannot be undone!
                </p>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-secondary" onclick="closeModal('restoreModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn btn-restore" id="confirmRestoreBtn">
                    <i class="fas fa-history"></i> Restore Backup
                </button>
            </div>
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
                <p>Are you sure you want to delete backup file <strong id="deleteFilename"></strong>?</p>
                <p style="color: var(--danger-color); font-size: 0.9rem; margin-top: 10px;">
                    <i class="fas fa-warning"></i> This action cannot be undone!
                </p>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn btn-delete" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> Delete Backup
                </button>
            </div>
        </div>
    </div>

    <script>
        // Helper function to format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // DOM Elements
        const searchInput = document.getElementById('searchInput');
        const filterSelect = document.getElementById('filterSelect');
        let allBackups = [];
        let backupToRestore = null;
        let backupToDelete = null;

        // Initialize all backups data
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

        // Search functionality
        function searchBackups() {
            const searchTerm = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('#backupTableBody tr');
            
            rows.forEach(row => {
                const name = row.dataset.name.toLowerCase();
                const shouldShow = name.includes(searchTerm);
                row.style.display = shouldShow ? '' : 'none';
            });
        }

        // Filter functionality
        function filterBackups() {
            const filter = filterSelect.value;
            const rows = document.querySelectorAll('#backupTableBody tr');
            const oneWeekAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
            
            rows.forEach(row => {
                const date = parseInt(row.dataset.date);
                const size = parseInt(row.dataset.size);
                const isRecent = date > oneWeekAgo;
                const isLarge = size > 10 * 1024 * 1024; // > 10MB
                
                let shouldShow = true;
                
                if(filter === 'recent') {
                    shouldShow = isRecent;
                } else if(filter === 'large') {
                    shouldShow = isLarge;
                }
                
                row.style.display = shouldShow ? '' : 'none';
            });
        }

        // Refresh page
        function refreshPage() {
            window.location.reload();
        }

        // Confirm backup creation
        function confirmBackup() {
            return confirm('Are you sure you want to create a new backup? This may take a few moments.');
        }

        // Restore functionality
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

        // Delete functionality
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

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if(modalId === 'restoreModal') backupToRestore = null;
            if(modalId === 'deleteModal') backupToDelete = null;
        }

        // Modal close handlers
        window.onclick = function(event) {
            const restoreModal = document.getElementById('restoreModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if(event.target == restoreModal) closeModal('restoreModal');
            if(event.target == deleteModal) closeModal('deleteModal');
        }

        // Initialize buttons
        document.getElementById('confirmRestoreBtn').addEventListener('click', restoreBackup);
        document.getElementById('confirmDeleteBtn').addEventListener('click', deleteBackup);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape') {
                closeModal('restoreModal');
                closeModal('deleteModal');
            }
        });

        // Mobile touch handling
        document.addEventListener('touchstart', function() {}, {passive: true});

        // Auto-refresh on success message
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

    <?php
    // Helper function to format file size
    function formatFileSize($bytes) {
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    ?>
</body>
</html>