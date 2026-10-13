<?php 
error_reporting(E_ALL); 
ini_set('display_errors', 1); 
session_start(); 
require_once 'db.php'; 
$login_error = ""; 

if ($_SERVER["REQUEST_METHOD"] === "POST") { 
    $username = trim($_POST['username']); 
    $password = trim($_POST['password']); 
    
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?"); 
    $stmt->bind_param("s", $username); 
    $stmt->execute(); 
    $result = $stmt->get_result(); 
    
    if ($result->num_rows === 1) { 
        $row = $result->fetch_assoc(); 
        if (password_verify($password, $row['password'])) { 
            $_SESSION['loggedin'] = true; 
            $_SESSION['user_id'] = $row['id']; 
            $_SESSION['username'] = $username; 
            header("Location: dashboard.php"); 
            exit; 
        } else { 
            $login_error = "پټ‌نوم غلط دی"; 
        } 
    } else { 
        $login_error = "نوم ونه موندل شو"; 
    } 
    $stmt->close(); 
} 
?>

<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>قرطاسیه · ننوتل</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Pashto font (Noto Naskh Arabic) -->
  <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Noto Naskh Arabic', 'Segoe UI', Tahoma, sans-serif;
    }

    :root {
      --card-bg: #ffffff;
      --primary: #1e3c72;
      --primary-soft: #2b4a7a;
      --accent: #e6b12e;
      --text-dark: #1f2937;
      --text-soft: #4b5563;
      --border-light: #e2e8f0;
      --error-bg: #fee2e2;
      --error-text: #b91c1c;
      --shadow-sm: 0 8px 20px rgba(0, 0, 0, 0.04), 0 2px 6px rgba(0, 0, 0, 0.02);
      --shadow-md: 0 16px 30px -8px rgba(0, 0, 0, 0.1), 0 4px 12px rgba(0, 0, 0, 0.04);
      --radius-card: 28px;
      --radius-input: 14px;
    }

    body {
      background: linear-gradient(145deg, #eef2f9 0%, #f9fcff 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      margin: 0;
    }

    /* ----- کارت اصلی ننوتلو ----- */
    .login-card {
      background: var(--card-bg);
      max-width: 440px;
      width: 100%;
      border-radius: var(--radius-card);
      box-shadow: var(--shadow-md);
      padding: 2.4rem 2rem 2rem;
      transition: transform 0.2s ease;
      border: 1px solid rgba(255, 255, 255, 0.6);
      backdrop-filter: blur(2px);
      position: relative;
    }

    @media (max-width: 480px) {
      .login-card {
        padding: 2rem 1.4rem 1.8rem;
        border-radius: 24px;
      }
    }

    /* ----- برانډ / لوګو ----- */
    .brand {
      text-align: center;
      margin-bottom: 2rem;
    }

    .brand-icon {
      background: var(--primary);
      width: 72px;
      height: 72px;
      border-radius: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.1rem;
      box-shadow: 0 12px 18px -6px rgba(30, 60, 114, 0.25);
      color: white;
      font-size: 2.2rem;
      transition: background 0.2s;
    }

    .brand h1 {
      font-size: 2rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      color: var(--primary);
      margin-bottom: 0.3rem;
    }

    .brand .subhead {
      color: var(--text-soft);
      font-size: 0.95rem;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #f1f5f9;
      padding: 0.3rem 1.2rem;
      border-radius: 50px;
      margin-top: 0.4rem;
    }

    .brand .subhead i {
      color: var(--accent);
      font-size: 0.9rem;
    }

    /* ----- د تېروتنې پیغام ----- */
    .error-message {
      background: var(--error-bg);
      color: var(--error-text);
      padding: 0.9rem 1.1rem;
      border-radius: 14px;
      font-size: 0.95rem;
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 1.7rem;
      border-right: 5px solid #b91c1c;
      font-weight: 600;
      box-shadow: var(--shadow-sm);
      animation: gentleShake 0.4s ease;
    }

    .error-message i {
      font-size: 1.2rem;
      color: #b91c1c;
    }

    @keyframes gentleShake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-4px); }
      75% { transform: translateX(4px); }
    }

    /* ----- د فورمې ډکې ----- */
    .form-group {
      margin-bottom: 1.6rem;
    }

    .form-label {
      display: block;
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--primary-soft);
      margin-bottom: 0.6rem;
    }

    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    /* آیکون په ښي اړخ کې (د RTL لپاره) */
    .input-icon {
      position: absolute;
      right: 18px;
      color: #94a3b8;
      font-size: 1.1rem;
      transition: color 0.2s;
      pointer-events: none;
    }

    .form-input {
      width: 100%;
      padding: 1rem 3.2rem 1rem 1rem;
      font-size: 1rem;
      border: 1.5px solid var(--border-light);
      border-radius: var(--radius-input);
      background: #ffffff;
      color: var(--text-dark);
      transition: border 0.2s, box-shadow 0.2s;
      outline: none;
      font-weight: 500;
      -webkit-appearance: none;
      appearance: none;
      text-align: right;
    }

    .form-input::placeholder {
      color: #9ca3af;
      font-weight: 400;
      font-size: 0.95rem;
    }

    .form-input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 4px rgba(30, 60, 114, 0.12);
    }

    .form-input:focus + .input-icon {
      color: var(--primary);
    }

    /* د پټ‌نوم ښودلو تڼۍ */
    .toggle-password {
      position: absolute;
      left: 18px;
      color: #94a3b8;
      font-size: 1.15rem;
      cursor: pointer;
      transition: color 0.2s;
      background: transparent;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      padding: 0;
    }

    .toggle-password:hover {
      color: var(--primary);
    }

    /* ----- د ننوتلو تڼۍ ----- */
    .btn-login {
      width: 100%;
      background: var(--primary);
      color: white;
      font-weight: 700;
      font-size: 1.1rem;
      padding: 1rem 1.8rem;
      border: none;
      border-radius: var(--radius-input);
      cursor: pointer;
      transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      margin-top: 0.6rem;
      box-shadow: 0 8px 16px -6px rgba(30, 60, 114, 0.3);
      -webkit-tap-highlight-color: transparent;
    }

    .btn-login i {
      font-size: 1.15rem;
    }

    .btn-login:hover {
      background: #15315a;
      box-shadow: 0 12px 22px -8px rgba(30, 60, 114, 0.4);
    }

    .btn-login:active {
      transform: scale(0.98);
    }

    .btn-login:disabled {
      opacity: 0.7;
      cursor: not-allowed;
      transform: none;
      background: #4b5e7a;
      box-shadow: none;
    }

    /* ----- د پاڼې ښکته برخه ----- */
    .login-footer {
      margin-top: 1.8rem;
      text-align: center;
      color: var(--text-soft);
      font-size: 0.8rem;
      border-top: 1px solid var(--border-light);
      padding-top: 1.4rem;
    }

    .security-badge {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 1rem;
      color: #64748b;
      font-size: 0.78rem;
      background: #f1f5f9;
      padding: 0.5rem 1.2rem;
      border-radius: 40px;
      width: fit-content;
      margin-left: auto;
      margin-right: auto;
    }

    .security-badge i {
      color: #22a06b;
      font-size: 0.85rem;
    }

    /* ----- د بارولو پوښ ----- */
    .loading-overlay {
      display: none;
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(255, 255, 255, 0.95);
      border-radius: var(--radius-card);
      z-index: 20;
      justify-content: center;
      align-items: center;
      flex-direction: column;
      backdrop-filter: blur(3px);
    }

    .spinner {
      width: 46px;
      height: 46px;
      border: 4px solid #e2e8f0;
      border-top: 4px solid var(--primary);
      border-radius: 50%;
      animation: spin 0.9s linear infinite;
      margin-bottom: 1rem;
    }

    .loading-text {
      color: var(--primary);
      font-weight: 600;
      font-size: 0.95rem;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* کوچني سکرین لپاره سمونونه */
    @media (max-width: 360px) {
      .brand h1 {
        font-size: 1.7rem;
      }
      .form-input {
        padding-right: 2.8rem;
        padding-left: 0.8rem;
        font-size: 0.95rem;
      }
      .input-icon {
        right: 14px;
      }
      .toggle-password {
        left: 14px;
      }
    }
  </style>
</head>
<body>

  <div class="login-card">
    <!-- د بارولو پوښ -->
    <div class="loading-overlay" id="loadingOverlay">
      <div class="spinner"></div>
      <div class="loading-text">ننوتل روان دي…</div>
    </div>

    <!-- برانډ -->
    <div class="brand">
      <div class="brand-icon">
        <i class="fas fa-pen-fancy"></i>
      </div>
      <h1>حاجی نادر او نقیب حقمل </h1>
      <div class="subhead">
        <i class="fas fa-book-open"></i> 
        <span>بنجاره پلورنحٔی  </span>
      </div>
    </div>

    <!-- د تېروتنې پیغام (د PHP له مخې) -->
    <?php if (!empty($login_error)): ?>
    <div class="error-message">
      <i class="fas fa-circle-exclamation"></i>
      <span><?php echo htmlspecialchars($login_error); ?></span>
    </div>
    <?php endif; ?>

    <!-- د ننوتلو فورمه -->
    <form method="POST" id="loginForm">
      <div class="form-group">
        <label class="form-label" for="username"> نوم</label>
        <div class="input-wrapper">
          <i class="fas fa-user input-icon"></i>
          <input type="text" name="username" id="username" class="form-input" 
                 placeholder="خپل  نوم ولیکئ" 
                 required autocomplete="username"
                 value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="passwordInput">پټ‌نوم</label>
        <div class="input-wrapper">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" name="password" id="passwordInput" class="form-input" 
                 placeholder="••••••••" required autocomplete="current-password">
          <button type="button" class="toggle-password" id="passwordToggle" aria-label="پټ‌نوم ښودل">
            <i class="fas fa-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login" id="loginBtn">
        <i class="fas fa-arrow-right-to-bracket"></i>
        <span>ننوتل</span>
      </button>
    </form>

    <!-- د پاڼې ښکته برخه (پرته د پټ‌نوم هېرولو لینک) -->
    <div class="login-footer">
      <div class="security-badge">
        <i class="fas fa-shield-halved"></i>
        <span>خوندي اړیکه · 0707843558  کوډ شوی</span>
      </div>
      <p style="margin-top: 1.2rem; font-size: 0.75rem; color: #94a3b8;">
        © <?php echo date('Y'); ?> رفیع الله احمدزی. ټول حقوق خوندي دي.
      </p>
    </div>
  </div>

  <script>
    (function() {
      // د پټ‌نوم ښودل/پټول
      const passwordInput = document.getElementById('passwordInput');
      const toggleBtn = document.getElementById('passwordToggle');
      
      if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function() {
          const type = passwordInput.type === 'password' ? 'text' : 'password';
          passwordInput.type = type;
          this.innerHTML = type === 'password' 
            ? '<i class="fas fa-eye"></i>' 
            : '<i class="fas fa-eye-slash"></i>';
          this.setAttribute('aria-label', type === 'password' ? 'پټ‌نوم ښودل' : 'پټ‌نوم پټول');
        });
      }

      // د بارولو پوښ او د تڼۍ غیر فعالول
      const loginForm = document.getElementById('loginForm');
      const loadingOverlay = document.getElementById('loadingOverlay');
      const loginBtn = document.getElementById('loginBtn');
      
      if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
          if (loadingOverlay) loadingOverlay.style.display = 'flex';
          
          if (loginBtn) {
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i><span>ننوتل روان دي…</span>';
          }
        });
      }

      // فوکس پر کارن نوم که خالي وي
      const usernameField = document.getElementById('username');
      if (usernameField && !usernameField.value) {
        setTimeout(() => {
          usernameField.focus();
        }, 200);
      }

      // د iOS د زوم مخنیوی لپاره
      document.addEventListener('touchstart', function(){}, { passive: true });
    })();
  </script>
</body>
</html>