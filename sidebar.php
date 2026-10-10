<?php
// sidebar.php
// NOTE: Do NOT call session_start() here.
// Session is started in db.php BEFORE any output.
$current_user = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$is_admin = ($current_user === 'admin');
?>

<!-- Include Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Pashto Font -->
<link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root {
        --sidebar-primary: #1e3c72;
        --sidebar-secondary: #2b4a7a;
        --sidebar-accent: #e6b12e;
        --sidebar-text: #ffffff;
        --sidebar-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        --sidebar-transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    * {
        font-family: 'Noto Naskh Arabic', 'Segoe UI', Tahoma, sans-serif;
    }

    /* Mobile Toggle Button */
    .sidebar-toggle {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1002;
        background: var(--sidebar-primary);
        color: white;
        border: none;
        border-radius: 12px;
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: var(--sidebar-shadow);
        transition: var(--sidebar-transition);
    }

    .sidebar-toggle:hover {
        transform: scale(1.08);
        background: var(--sidebar-secondary);
    }

    .sidebar-toggle i {
        transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    /* ===== SIDEBAR CONTAINER ===== */
    .sidebar {
        width: 280px;
        background: var(--sidebar-primary);
        color: var(--sidebar-text);
        height: 100vh;
        max-height: 100vh;
        padding: 25px 20px 15px;
        position: fixed;
        right: 0;
        top: 0;
        z-index: 1000;
        box-shadow: var(--sidebar-shadow);
        border-left: 3px solid var(--sidebar-accent);
        overflow: hidden;
        transform: translateX(0);
        transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
    }

    /* Logo Section */
    .sidebar-logo {
        text-align: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid rgba(255, 255, 255, 0.12);
        position: relative;
        flex-shrink: 0;
        z-index: 1;
    }

    .sidebar-logo::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 25%;
        width: 50%;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--sidebar-accent), transparent);
    }

    .logo-icon {
        font-size: 2.2rem;
        color: var(--sidebar-accent);
        margin-bottom: 8px;
        display: inline-block;
    }

    .sidebar-logo h2 {
        color: white;
        font-size: 1.3rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin-bottom: 3px;
    }

    .sidebar-logo p {
        color: var(--sidebar-accent);
        font-size: 0.8rem;
        opacity: 0.9;
    }

    /* User Info Section */
    .user-info {
        background: rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        padding: 14px 12px;
        margin-bottom: 15px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.1);
        flex-shrink: 0;
        z-index: 1;
    }

    .user-avatar {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: var(--sidebar-secondary);
        margin: 0 auto 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        color: white;
        border: 2px solid var(--sidebar-accent);
    }

    .user-name {
        font-weight: 600;
        margin-bottom: 3px;
        font-size: 0.95rem;
    }

    .user-role {
        font-size: 0.78rem;
        opacity: 0.85;
        color: var(--sidebar-accent);
    }

    .admin-badge {
        display: inline-block;
        background: var(--sidebar-accent);
        color: var(--sidebar-primary);
        font-size: 0.65rem;
        padding: 2px 10px;
        border-radius: 10px;
        margin-top: 6px;
        font-weight: bold;
        text-transform: uppercase;
    }

    /* ===== SCROLLABLE MENU AREA ===== */
    .sidebar-menu-container {
        flex: 1 1 auto;
        overflow-y: scroll;
        overflow-x: hidden;
        min-height: 0;
        padding-right: 8px;
        padding-left: 2px;
        z-index: 1;
        scrollbar-width: auto;
        scrollbar-color: var(--sidebar-accent) rgba(255, 255, 255, 0.1);
    }

    .sidebar-menu-container::-webkit-scrollbar {
        width: 8px;
    }

    .sidebar-menu-container::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.08);
        border-radius: 10px;
    }

    .sidebar-menu-container::-webkit-scrollbar-thumb {
        background: var(--sidebar-accent);
        border-radius: 10px;
    }

    .sidebar-menu-container::-webkit-scrollbar-thumb:hover {
        background: #f5d97b;
    }

    /* Menu List */
    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-menu li {
        margin-bottom: 6px;
        position: relative;
    }

    .sidebar-menu li a {
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        padding: 12px 14px;
        border-radius: 10px;
        transition: all 0.25s ease;
        position: relative;
        font-weight: 500;
        white-space: nowrap;
    }

    .sidebar-menu li a i {
        margin-left: 12px;
        font-size: 1.1rem;
        width: 24px;
        text-align: center;
        flex-shrink: 0;
    }

    .sidebar-menu li a:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        transform: translateX(-4px);
    }

    .sidebar-menu li a:hover i {
        color: var(--sidebar-accent);
    }

    .sidebar-menu li.active a {
        background: rgba(230, 177, 46, 0.15);
        color: var(--sidebar-accent);
        border-right: 4px solid var(--sidebar-accent);
    }

    .sidebar-menu li.active a i {
        color: var(--sidebar-accent);
    }

    .sidebar-menu li.logout-item a {
        background: rgba(255, 107, 107, 0.1);
        border-right: 4px solid #ff6b6b;
        margin-top: 10px;
    }

    .sidebar-menu li.logout-item a:hover {
        background: rgba(255, 107, 107, 0.2);
        color: #ff6b6b;
    }

    .sidebar-menu li.logout-item a i {
        color: #ff6b6b;
    }

    /* Sidebar Footer */
    .sidebar-footer {
        padding-top: 12px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        text-align: center;
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.6);
        flex-shrink: 0;
        z-index: 1;
    }

    .system-status {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-bottom: 8px;
    }

    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #22c55e;
        position: relative;
    }

    .status-indicator::after {
        content: '';
        position: absolute;
        top: -3px; left: -3px; right: -3px; bottom: -3px;
        border-radius: 50%;
        border: 2px solid #22c55e;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(0.8); opacity: 0.8; }
        50% { transform: scale(1.2); opacity: 0.4; }
        100% { transform: scale(0.8); opacity: 0.8; }
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .sidebar {
            width: 260px;
            padding: 20px 15px 12px;
        }
    }

    @media (max-width: 768px) {
        .sidebar-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar {
            width: 280px;
            transform: translateX(100%);
            box-shadow: -10px 0 50px rgba(0, 0, 0, 0.4);
        }
        .sidebar.active {
            transform: translateX(0);
        }
        .sidebar-toggle.active i {
            transform: rotate(180deg);
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 999;
        }
        .sidebar-overlay.active {
            display: block;
        }
    }

    @media (max-width: 480px) {
        .sidebar {
            width: 100%;
            max-width: 320px;
        }
        .sidebar-toggle {
            top: 15px;
            left: 15px;
            width: 45px;
            height: 45px;
        }
    }

    /* Main Content */
    .main-content {
        transition: margin-right 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    @media (min-width: 769px) {
        .main-content {
            margin-right: 280px;
        }
    }
    @media (max-width: 768px) {
        .main-content {
            margin-right: 0;
        }
    }
</style>

<!-- Mobile Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar Container -->
<div class="sidebar" id="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <div class="logo-icon">
            <i class="fas fa-pen-fancy"></i>
        </div>
              <h2>حاجی نادر او نقیب حقمل </h1>
         <p>بنجاره پلورنحٔی  </p>
    </div>

    <!-- User Info -->
    <div class="user-info">
        <div class="user-avatar">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="user-name">
            <?php echo !empty($current_user) ? htmlspecialchars($current_user) : 'میلمه'; ?>
        </div>
        <div class="user-role">
            <i class="fas fa-shield-alt"></i>
            <?php echo $is_admin ? 'مدیر' : 'کارکوونکی'; ?>
        </div>
        <?php if($is_admin): ?>
            <div class="admin-badge">
                <i class="fas fa-crown"></i> مدیر
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== SCROLLABLE MENU AREA ===== -->
    <div class="sidebar-menu-container">
        <ul class="sidebar-menu">
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> ډشبورډ</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                <a href="products.php"><i class="fas fa-boxes"></i> توکي اضافه کړئ</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'save.php' ? 'active' : ''; ?>">
                <a href="save.php"><i class="fas fa-cash-register"></i> پلور</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'register.php' ? 'active' : ''; ?>">
                <a href="register.php"><i class="fas fa-users"></i> کاروونکي</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'active' : ''; ?>">
                <a href="expenses.php"><i class="fas fa-wallet"></i> لګښتونه</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'item.php' ? 'active' : ''; ?>">
                <a href="item.php"><i class="fas fa-warehouse"></i> ګدام</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'companies.php' ? 'active' : ''; ?>">
                <a href="sarafe.php"><i class="fas fa-building"></i> صراف پیسی</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'backup_manager.php' ? 'active' : ''; ?>">
                <a href="backup_manager.php"><i class="fas fa-database"></i> بیک اپ مدیر</a>
            </li>
            <li class="logout-item <?php echo basename($_SERVER['PHP_SELF']) == 'logout.php' ? 'active' : ''; ?>">
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> وتل</a>
            </li>
        </ul>
    </div>

    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="system-status">
            <div class="status-indicator"></div>
            <span>سیسټم فعال</span>
        </div>
        <p>© <?php echo date('Y'); ?> قرطاسیه</p>
        <p>v2.1.0</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    let isSidebarOpen = false;

    sidebarToggle.addEventListener('click', function() {
        if (isSidebarOpen) closeSidebar();
        else openSidebar();
    });

    sidebarOverlay.addEventListener('click', closeSidebar);

    function openSidebar() {
        sidebar.classList.add('active');
        sidebarToggle.classList.add('active');
        sidebarOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        isSidebarOpen = true;
    }

    function closeSidebar() {
        sidebar.classList.remove('active');
        sidebarToggle.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
        isSidebarOpen = false;
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isSidebarOpen) closeSidebar();
    });

    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            if (window.innerWidth > 768) {
                sidebar.classList.add('active');
                sidebarToggle.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                isSidebarOpen = false;
                document.body.style.overflow = '';
            } else {
                closeSidebar();
            }
        }, 250);
    });

    if (window.innerWidth <= 768) closeSidebar();
});
</script>

<div class="main-content">
 
</div>