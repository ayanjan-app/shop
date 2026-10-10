<?php
session_start();
require_once 'db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Create upload folder if it doesn't exist
$uploadDir = __DIR__ . '/uploads/clients/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$success_msg = '';
$error_msg = '';

// Retrieve flash messages from session (PRG pattern)
if (isset($_SESSION['flash_success'])) {
    $success_msg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $error_msg = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// =========================
// ADD CLIENT (POST → redirect)
// =========================
if (isset($_POST['add_client'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $province  = trim($_POST['province'] ?? '');
    $note      = trim($_POST['note'] ?? '');
    $picturePath = null;
    $hasError = false;

    // Validate full name
    if (empty($full_name)) {
        $_SESSION['flash_error'] = "بشپړ نوم اړین دی";
        $hasError = true;
    }

    // ✅ Check for duplicate full_name (case-insensitive)
    if (!$hasError) {
        $checkStmt = $conn->prepare("SELECT id FROM clients WHERE LOWER(full_name) = LOWER(?) LIMIT 1");
        $checkStmt->bind_param("s", $full_name);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $_SESSION['flash_error'] = "دا نوم لا دمخه ثبت شوی دی. مهرباني وکړئ بل نوم وکاروئ.";
            $hasError = true;
        }
        $checkStmt->close();
    }

    // Handle picture upload
    if (!$hasError && isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $newName = 'client_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target  = $uploadDir . $newName;

            if (move_uploaded_file($_FILES['picture']['tmp_name'], $target)) {
                $picturePath = 'uploads/clients/' . $newName;
            } else {
                $_SESSION['flash_error'] = "د عکس پورته کول ناکام شول!";
                $hasError = true;
            }
        } else {
            $_SESSION['flash_error'] = "یوازې JPG، PNG، GIF، WEBP عکسونه اجازه لري.";
            $hasError = true;
        }
    }

    if (!$hasError) {
        $stmt = $conn->prepare("INSERT INTO clients (full_name, picture, phone, address, province, note) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $full_name, $picturePath, $phone, $address, $province, $note);

        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "پیرودونکی په بریالیتوب سره اضافه شو!";
        } else {
            $_SESSION['flash_error'] = "تېروتنه: " . $stmt->error;
        }
        $stmt->close();
    }

    // PRG: redirect to prevent duplicate submission on refresh
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// =========================
// UPDATE CLIENT (POST → redirect)
// =========================
if (isset($_POST['update_client'])) {
    $id        = intval($_POST['id']);
    $full_name = trim($_POST['full_name']);
    $phone     = trim($_POST['phone']);
    $address   = trim($_POST['address']);
    $province  = trim($_POST['province']);
    $note      = trim($_POST['note']);
    $hasError  = false;

    if (empty($full_name)) {
        $_SESSION['flash_error'] = "بشپړ نوم اړین دی";
        $hasError = true;
    }

    // ✅ Check for duplicate full_name (excluding this client)
    if (!$hasError) {
        $checkStmt = $conn->prepare("SELECT id FROM clients WHERE LOWER(full_name) = LOWER(?) AND id != ? LIMIT 1");
        $checkStmt->bind_param("si", $full_name, $id);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $_SESSION['flash_error'] = "دا نوم لا دمخه ثبت شوی دی. مهرباني وکړئ بل نوم وکاروئ.";
            $hasError = true;
        }
        $checkStmt->close();
    }

    // Get current picture
    $currentPicture = null;
    $stmtGet = $conn->prepare("SELECT picture FROM clients WHERE id = ?");
    $stmtGet->bind_param("i", $id);
    $stmtGet->execute();
    $resGet = $stmtGet->get_result();
    if ($rowGet = $resGet->fetch_assoc()) {
        $currentPicture = $rowGet['picture'];
    }
    $stmtGet->close();

    $newPicturePath = $currentPicture;

    // Handle new picture upload
    if (!$hasError && isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $newName = 'client_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target  = $uploadDir . $newName;

            if (move_uploaded_file($_FILES['picture']['tmp_name'], $target)) {
                if (!empty($currentPicture) && file_exists(__DIR__ . '/' . $currentPicture)) {
                    @unlink(__DIR__ . '/' . $currentPicture);
                }
                $newPicturePath = 'uploads/clients/' . $newName;
            } else {
                $_SESSION['flash_error'] = "د عکس پورته کول ناکام شول!";
                $hasError = true;
            }
        } else {
            $_SESSION['flash_error'] = "یوازې JPG، PNG، GIF، WEBP عکسونه اجازه لري.";
            $hasError = true;
        }
    }

    if (!$hasError) {
        $stmt = $conn->prepare("UPDATE clients SET full_name = ?, picture = ?, phone = ?, address = ?, province = ?, note = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $full_name, $newPicturePath, $phone, $address, $province, $note, $id);

        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "پیرودونکی په بریالیتوب سره تازه شو!";
        } else {
            $_SESSION['flash_error'] = "تېروتنه: " . $stmt->error;
        }
        $stmt->close();
    }

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// =========================
// DELETE CLIENT (GET → redirect)
// =========================
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    $stmtGet = $conn->prepare("SELECT picture FROM clients WHERE id = ?");
    $stmtGet->bind_param("i", $delete_id);
    $stmtGet->execute();
    $resGet = $stmtGet->get_result();
    if ($rowGet = $resGet->fetch_assoc()) {
        if (!empty($rowGet['picture']) && file_exists(__DIR__ . '/' . $rowGet['picture'])) {
            @unlink(__DIR__ . '/' . $rowGet['picture']);
        }
    }
    $stmtGet->close();

    $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $_SESSION['flash_success'] = "پیرودونکی په بریالیتوب سره ړنګ شو!";
    } else {
        $_SESSION['flash_error'] = "تېروتنه: " . $stmt->error;
    }
    $stmt->close();

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// =========================
// SEARCH / FILTER
// =========================
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$filter_province = isset($_GET['filter_province']) ? trim($_GET['filter_province']) : '';

$where = [];
$params = [];
$types = '';

if ($search_name !== '') {
    $where[] = "full_name LIKE ?";
    $params[] = "%" . $search_name . "%";
    $types .= 's';
}
if ($filter_province !== '') {
    $where[] = "province = ?";
    $params[] = $filter_province;
    $types .= 's';
}

$sql = "SELECT * FROM clients";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$clients = [];
while ($row = $result->fetch_assoc()) {
    $clients[] = $row;
}
$stmt->close();

// =========================
// GET DISTINCT PROVINCES FOR FILTER DROPDOWN
// =========================
$provinces = [];
$provResult = $conn->query("SELECT DISTINCT province FROM clients WHERE province IS NOT NULL AND province != '' ORDER BY province ASC");
if ($provResult) {
    while ($p = $provResult->fetch_assoc()) {
        $provinces[] = $p['province'];
    }
}
?>
<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیرودونکي · قرطاسیہ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e3c72;
            --secondary-color: #2b4a7a;
            --accent-color: #e6b12e;
            --success-color: #22c55e;
            --danger-color: #ef4444;
            --text-color: #1f2937;
            --text-muted: #64748b;
            --card-bg: #ffffff;
            --bg-light: #f1f5f9;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            --shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.1);
            --border-radius: 16px;
            --transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Noto Naskh Arabic', 'Segoe UI', Tahoma, sans-serif; }
        body { background: var(--bg-light); color: var(--text-color); min-height: 100vh; font-size: 14px; line-height: 1.6; }
        .main-content { padding: 5px; }
        @media (max-width: 768px) { .main-content { padding: 5px; padding-top: 5px; } }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; padding: 18px 24px; background: var(--primary-color); border-radius: var(--border-radius); color: white; box-shadow: var(--shadow); }
        .page-header h1 { font-size: 1.6rem; display: flex; align-items: center; gap: 12px; font-weight: 700; margin: 0; }
        .page-header h1 i { color: var(--accent-color); }
        .page-header .subtitle { color: rgba(255,255,255,0.8); font-size: 0.85rem; margin-top: 5px; }

        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 12px; }
        .alert-success { background-color: #dcfce7; color: #166534; border-right: 4px solid var(--success-color); }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border-right: 4px solid var(--danger-color); }

        .content-grid { display: grid; grid-template-columns: 400px 1fr; gap: 25px; align-items: start; }
        @media (max-width: 1100px) { .content-grid { grid-template-columns: 1fr; } }

        .card { background: var(--card-bg); border-radius: var(--border-radius); padding: 25px; box-shadow: var(--shadow); position: relative; overflow: hidden; border: 1px solid #e2e8f0; }
        .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--accent-color); }
        .card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f1f5f9; }
        .card-header i { color: var(--accent-color); font-size: 1.3rem; }
        .card-header h2 { margin: 0; font-size: 1.2rem; color: var(--primary-color); font-weight: 700; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 0.85rem; font-weight: 600; color: var(--primary-color); }
        .form-group label i { color: var(--accent-color); margin-left: 6px; width: 16px; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 10px;
            font-size: 0.95rem; transition: var(--transition); font-family: inherit;
            background: var(--card-bg); color: var(--text-color); text-align: right;
        }
        .form-group textarea { resize: vertical; min-height: 70px; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(30,60,114,0.1);
        }
        .form-group input[type="file"] { padding: 8px; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 20px; border: none; border-radius: 10px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: var(--transition); font-family: inherit; text-decoration: none; }
        .btn-primary { background: var(--primary-color); color: white; width: 100%; box-shadow: var(--shadow); }
        .btn-primary:hover { background: var(--secondary-color); transform: translateY(-2px); box-shadow: 0 15px 30px rgba(30,60,114,0.2); }
        .btn-secondary { background: var(--accent-color); color: var(--primary-color); box-shadow: var(--shadow); }
        .btn-secondary:hover { background: #d4a017; transform: translateY(-2px); }
        .btn-reset { background: #f1f5f9; color: var(--text-muted); border: 2px solid #e2e8f0; }
        .btn-reset:hover { background: #e2e8f0; color: var(--primary-color); }

        .filter-bar { background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 18px; margin-bottom: 20px; }
        .filter-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .filter-row .form-group { flex: 1; min-width: 160px; margin-bottom: 0; }
        .filter-row .btn { width: auto; padding: 12px 24px; }
        .filter-badge { display: inline-flex; align-items: center; gap: 6px; background: var(--primary-color); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; margin-bottom: 15px; }

        .table-wrapper { overflow-x: auto; border-radius: 12px; border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
        thead { background: var(--primary-color); }
        th { padding: 15px; text-align: right; color: white; font-weight: 600; font-size: 0.85rem; }
        td { padding: 14px 15px; border-bottom: 1px solid #e2e8f0; color: var(--text-color); text-align: right; vertical-align: middle; }
        tbody tr:hover { background: #f8fafc; }
        tbody tr:last-child td { border-bottom: none; }

        .client-avatar {
            width: 45px; height: 45px; border-radius: 50%; object-fit: cover;
            border: 2px solid var(--accent-color); cursor: pointer;
            transition: var(--transition);
        }
        .client-avatar:hover { transform: scale(1.1); box-shadow: 0 4px 15px rgba(230,177,46,0.5); }
        .client-avatar-placeholder {
            width: 45px; height: 45px; border-radius: 50%; background: var(--primary-color);
            color: white; display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.1rem; border: 2px solid var(--accent-color);
        }
        .province-badge { display: inline-block; background: #eff6ff; color: var(--primary-color); padding: 3px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px; display: block; }

        .action-buttons { display: flex; gap: 6px; justify-content: center; flex-wrap: wrap; }
        .btn-action {
            width: 34px; height: 34px; border-radius: 8px; border: none; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.85rem; color: white; transition: var(--transition);
            text-decoration: none;
        }
        .btn-action-edit { background: var(--accent-color); color: var(--primary-color); }
        .btn-action-edit:hover { background: #d4a017; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(230,177,46,0.4); }
        .btn-action-delete { background: var(--danger-color); }
        .btn-action-delete:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(239,68,68,0.4); }

        .image-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(6px);
        }
        .image-modal.active { display: flex; }
        .image-modal img {
            max-width: 90vw;
            max-height: 85vh;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
            border: 3px solid var(--accent-color);
        }
        .image-modal-close {
            position: absolute;
            top: 20px;
            right: 25px;
            background: var(--danger-color);
            color: white;
            border: none;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            font-size: 1.3rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .image-modal-caption {
            position: absolute;
            bottom: 25px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 600;
            border: 2px solid var(--accent-color);
        }

        .edit-modal, .delete-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
        }
        .edit-modal.active, .delete-modal.active { display: flex; }
        .edit-modal-content {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            border-top: 4px solid var(--accent-color);
            position: relative;
        }
        .edit-modal-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f1f5f9; }
        .edit-modal-header i { color: var(--accent-color); font-size: 1.4rem; }
        .edit-modal-header h2 { margin: 0; font-size: 1.25rem; color: var(--primary-color); }
        .edit-modal-close {
            position: absolute;
            top: 15px; left: 15px;
            background: #f1f5f9;
            color: var(--text-muted);
            border: none;
            width: 36px; height: 36px;
            border-radius: 50%;
            cursor: pointer;
        }
        .current-picture-preview {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 2px dashed #cbd5e1;
        }
        .current-picture-preview img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-color); }
        .current-picture-preview .info { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }
        .edit-actions { display: flex; gap: 12px; margin-top: 20px; flex-wrap: wrap; }
        .edit-actions .btn { flex: 1; min-width: 140px; }

        .delete-modal-content {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            max-width: 450px;
            width: 100%;
            text-align: center;
            border-top: 4px solid var(--danger-color);
        }
        .delete-modal-icon {
            width: 70px; height: 70px; border-radius: 50%;
            background: #fee2e2; color: var(--danger-color);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; margin: 0 auto 15px;
        }
        .delete-modal-content h2 { color: var(--primary-color); margin-bottom: 10px; }
        .delete-modal-content .client-name-highlight { font-weight: 700; color: var(--primary-color); background: #f1f5f9; padding: 4px 12px; border-radius: 8px; display: inline-block; margin: 8px 0; }
        .delete-modal-content .warning-text { color: var(--danger-color); font-size: 0.85rem; font-weight: 600; margin-top: 10px; }
        .delete-actions { display: flex; gap: 12px; margin-top: 25px; flex-wrap: wrap; }
        .delete-actions .btn { flex: 1; min-width: 120px; }
        .btn-danger { background: var(--danger-color); color: white; }
        .btn-danger:hover { background: #dc2626; }

        @media (max-width: 768px) {
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-row .btn { flex: 1; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-users"></i> پیرودونکي</h1>
            <div class="subtitle">د قرطاسیہ پیرودونکي مدیریت کړئ</div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <div class="content-grid">

        <!-- Add Client Form -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-plus"></i>
                <h2>نوی پیرودونکی اضافه کړئ</h2>
            </div>
            <form method="POST" enctype="multipart/form-data" id="clientForm">
                <input type="hidden" name="add_client" value="1">

                <div class="form-group">
                    <label><i class="fas fa-user"></i> بشپړ نوم *</label>
                    <input type="text" name="full_name" placeholder="بشپړ نوم ولیکئ" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-image"></i> عکس</label>
                    <input type="file" name="picture" accept="image/*">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> تلیفون</label>
                    <input type="text" name="phone" placeholder="07XX XXX XXX">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> پته</label>
                    <textarea name="address" placeholder="بشپړه پته ولیکئ"></textarea>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-map"></i> ولایت</label>
                    <input type="text" name="province" placeholder="مثال: کابل">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> یادونه</label>
                    <textarea name="note" placeholder="اضافي یادونه"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> پیرودونکی اضافه کړئ
                </button>
            </form>
        </div>

        <!-- Clients List -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i>
                <h2>د پیرودونکو لیست (<?php echo count($clients); ?>)</h2>
            </div>

            <div class="filter-bar">
                <form method="GET">
                    <div class="filter-row">
                        <div class="form-group">
                            <label><i class="fas fa-search"></i> د نوم له مخې پلټنه</label>
                            <input type="text" name="search_name" placeholder="د پیرودونکي نوم ولیکئ" value="<?php echo htmlspecialchars($search_name); ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-map"></i> ولایت</label>
                            <select name="filter_province">
                                <option value="">ټول ولایتونه</option>
                                <?php foreach ($provinces as $prov): ?>
                                    <option value="<?php echo htmlspecialchars($prov); ?>" <?php echo ($filter_province === $prov) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prov); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-filter"></i> فلټر
                        </button>
                        <?php if ($search_name !== '' || $filter_province !== ''): ?>
                            <a href="clients.php" class="btn btn-reset">
                                <i class="fas fa-times"></i> پاکول
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if ($search_name !== '' || $filter_province !== ''): ?>
                <div class="filter-badge">
                    <i class="fas fa-filter"></i>
                    فعال فلټر:
                    <?php 
                    $parts = [];
                    if ($search_name !== '') $parts[] = 'نوم: ' . htmlspecialchars($search_name);
                    if ($filter_province !== '') $parts[] = 'ولایت: ' . htmlspecialchars($filter_province);
                    echo implode(' | ', $parts);
                    ?>
                </div>
            <?php endif; ?>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>عکس</th>
                            <th>بشپړ نوم</th>
                            <th>تلیفون</th>
                            <th>ولایت</th>
                            <th>پته</th>
                            <th>یادونه</th>
                            <th>کړنې</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($clients) > 0): ?>
                            <?php foreach ($clients as $c): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($c['picture']) && file_exists(__DIR__ . '/' . $c['picture'])): ?>
                                            <img src="<?php echo htmlspecialchars($c['picture']); ?>" 
                                                 class="client-avatar" 
                                                 alt="عکس"
                                                 onclick="openImage('<?php echo htmlspecialchars($c['picture']); ?>', '<?php echo htmlspecialchars($c['full_name']); ?>')">
                                        <?php else: ?>
                                            <div class="client-avatar-placeholder"><i class="fas fa-user"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($c['phone']); ?></td>
                                    <td><span class="province-badge"><?php echo htmlspecialchars($c['province']); ?></span></td>
                                    <td><?php echo htmlspecialchars($c['address']); ?></td>
                                    <td><?php echo htmlspecialchars($c['note']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" 
                                                    class="btn-action btn-action-edit" 
                                                    title="سمول"
                                                    onclick='openEditModal(<?php echo json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn-action btn-action-delete" 
                                                    title="ړنګول"
                                                    onclick='openDeleteModal(<?php echo $c['id']; ?>, <?php echo json_encode($c['full_name'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-users-slash"></i>
                                        <?php if ($search_name !== '' || $filter_province !== ''): ?>
                                            د دې فلټر سره سم هیڅ پیرودونکی ونه موندل شو.
                                        <?php else: ?>
                                            هیڅ پیرودونکی ونه موندل شو.
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<!-- ===== Image Lightbox Modal ===== -->
<div class="image-modal" id="imageModal">
    <button class="image-modal-close" onclick="closeImage()" aria-label="بندول">
        <i class="fas fa-times"></i>
    </button>
    <img id="modalImage" src="" alt="د پیرودونکي عکس">
    <div class="image-modal-caption" id="modalCaption"></div>
</div>

<!-- ===== Edit Client Modal ===== -->
<div class="edit-modal" id="editModal">
    <div class="edit-modal-content">
        <button class="edit-modal-close" onclick="closeEditModal()" aria-label="بندول">
            <i class="fas fa-times"></i>
        </button>
        <div class="edit-modal-header">
            <i class="fas fa-user-edit"></i>
            <h2>پیرودونکی سمول</h2>
        </div>
        <form method="POST" enctype="multipart/form-data" id="editForm">
            <input type="hidden" name="update_client" value="1">
            <input type="hidden" name="id" id="edit_id">

            <div id="edit_current_picture_wrap" class="current-picture-preview" style="display:none;">
                <img id="edit_current_picture_img" src="" alt="اوسنی عکس">
                <div class="info">
                    <i class="fas fa-info-circle"></i> اوسنی عکس (د نوي عکس غوره کول اختیاري دي)
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-user"></i> بشپړ نوم *</label>
                <input type="text" name="full_name" id="edit_full_name" placeholder="بشپړ نوم ولیکئ" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-image"></i> نوی عکس (اختیاري)</label>
                <input type="file" name="picture" accept="image/*">
            </div>
            <div class="form-group">
                <label><i class="fas fa-phone"></i> تلیفون</label>
                <input type="text" name="phone" id="edit_phone" placeholder="07XX XXX XXX">
            </div>
            <div class="form-group">
                <label><i class="fas fa-map-marker-alt"></i> پته</label>
                <textarea name="address" id="edit_address" placeholder="بشپړه پته ولیکئ"></textarea>
            </div>
            <div class="form-group">
                <label><i class="fas fa-map"></i> ولایت</label>
                <input type="text" name="province" id="edit_province" placeholder="مثال: کابل">
            </div>
            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> یادونه</label>
                <textarea name="note" id="edit_note" placeholder="اضافي یادونه"></textarea>
            </div>
            <div class="edit-actions">
                <button type="button" class="btn btn-reset" onclick="closeEditModal()">
                    <i class="fas fa-times"></i> لغوه
                </button>
                <button type="submit" class="btn btn-primary" id="editSubmitBtn">
                    <i class="fas fa-save"></i> تازه کړئ
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===== Delete Confirmation Modal ===== -->
<div class="delete-modal" id="deleteModal">
    <div class="delete-modal-content">
        <div class="delete-modal-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h2>د ړنګولو تایید</h2>
        <p>ایا تاسو ډاډه یاست چې غواړئ دا پیرودونکی ړنګ کړئ؟</p>
        <div class="client-name-highlight" id="deleteClientName"></div>
        <p class="warning-text">
            <i class="fas fa-warning"></i> دا عمل بیرته نه شي راګرځیدلی!
        </p>
        <div class="delete-actions">
            <button type="button" class="btn btn-reset" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i> لغوه
            </button>
            <a href="#" class="btn btn-danger" id="confirmDeleteBtn">
                <i class="fas fa-trash"></i> ړنګ کړئ
            </a>
        </div>
    </div>
</div>

<script>
    // ===== Image Lightbox =====
    const imageModal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const modalCaption = document.getElementById('modalCaption');

    function openImage(src, name) {
        modalImage.src = src;
        modalCaption.textContent = name || '';
        imageModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeImage() {
        imageModal.classList.remove('active');
        document.body.style.overflow = '';
        modalImage.src = '';
    }

    imageModal.addEventListener('click', function(e) {
        if (e.target === imageModal) closeImage();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && imageModal.classList.contains('active')) {
            closeImage();
        }
    });

    // ===== Prevent double submit & show loading =====
    const clientForm = document.getElementById('clientForm');
    const submitBtn = document.getElementById('submitBtn');

    if (clientForm) {
        clientForm.addEventListener('submit', function() {
            setTimeout(function() {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> خوندي کیږي...';
            }, 0);
        });
    }

    // ===== Edit Modal =====
    const editModal = document.getElementById('editModal');

    function openEditModal(client) {
        document.getElementById('edit_id').value = client.id;
        document.getElementById('edit_full_name').value = client.full_name || '';
        document.getElementById('edit_phone').value = client.phone || '';
        document.getElementById('edit_address').value = client.address || '';
        document.getElementById('edit_province').value = client.province || '';
        document.getElementById('edit_note').value = client.note || '';

        const previewWrap = document.getElementById('edit_current_picture_wrap');
        const previewImg = document.getElementById('edit_current_picture_img');
        if (client.picture && client.picture !== '') {
            previewImg.src = client.picture;
            previewWrap.style.display = 'flex';
        } else {
            previewWrap.style.display = 'none';
            previewImg.src = '';
        }

        editModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        editModal.classList.remove('active');
        document.body.style.overflow = '';
    }

    editModal.addEventListener('click', function(e) {
        if (e.target === editModal) closeEditModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && editModal.classList.contains('active')) {
            closeEditModal();
        }
    });

    const editForm = document.getElementById('editForm');
    const editSubmitBtn = document.getElementById('editSubmitBtn');
    if (editForm) {
        editForm.addEventListener('submit', function() {
            setTimeout(function() {
                editSubmitBtn.disabled = true;
                editSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> تازه کیږي...';
            }, 0);
        });
    }

    // ===== Delete Modal =====
    const deleteModal = document.getElementById('deleteModal');

    function openDeleteModal(id, name) {
        document.getElementById('deleteClientName').textContent = name;
        document.getElementById('confirmDeleteBtn').href = '?delete_id=' + encodeURIComponent(id);
        deleteModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeDeleteModal() {
        deleteModal.classList.remove('active');
        document.body.style.overflow = '';
    }

    deleteModal.addEventListener('click', function(e) {
        if (e.target === deleteModal) closeDeleteModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && deleteModal.classList.contains('active')) {
            closeDeleteModal();
        }
    });
</script>

</body>
</html>