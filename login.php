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
            $login_error = "Wrong password"; 
        } 
    } else { 
        $login_error = "User not found"; 
    } 
    $stmt->close(); 
} 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>PharmaCare RX - Login</title>
    <!-- Include Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-color: #1a2980;
            --secondary-color: #26d0ce;
            --accent-color: #00ffea;
            --error-color: #ff6b6b;
            --text-color: #333;
            --light-bg: rgba(255, 255, 255, 0.95);
            --shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            --border-radius: 25px;
        }

        body {
            background: linear-gradient(135deg, #0c2461, #1e3799, #4a69bd);
            min-height: 100vh;
            min-height: -webkit-fill-available;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow-x: hidden;
            position: relative;
            padding: 15px;
            width: 100%;
        }

        html {
            height: -webkit-fill-available;
        }

        /* Floating Pharmacy Items - Responsive */
        .pharmacy-item {
            position: absolute;
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            pointer-events: none;
            z-index: 1;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.3));
            display: none; /* Hidden on very small screens */
        }

        /* Show items on tablets and above */
        @media (min-width: 480px) {
            .pharmacy-item {
                display: block;
                font-size: 1.8rem;
            }
        }

        @media (min-width: 768px) {
            .pharmacy-item {
                font-size: 2rem;
            }
        }

        .pharmacy-item:hover {
            transform: scale(1.8) rotate(20deg);
            color: #fff;
            text-shadow: 0 0 20px var(--accent-color), 0 0 40px var(--accent-color);
            z-index: 10;
            pointer-events: auto;
        }

        /* Position pharmacy items for different screen sizes */
        .item-1 { top: 10%; left: 5%; }
        .item-2 { top: 15%; right: 8%; }
        .item-3 { bottom: 20%; left: 7%; }
        .item-4 { bottom: 15%; right: 10%; }
        .item-5 { top: 40%; left: 3%; }
        .item-6 { top: 35%; right: 5%; }
        .item-7 { bottom: 40%; left: 12%; }
        .item-8 { bottom: 35%; right: 3%; }
        .item-9 { top: 60%; left: 10%; }
        .item-10 { top: 65%; right: 12%; }

        /* Adjust positions for mobile */
        @media (max-width: 767px) {
            .pharmacy-item {
                font-size: 1.5rem;
            }
            .item-1 { top: 5%; left: 5%; }
            .item-2 { top: 5%; right: 5%; }
            .item-3 { bottom: 5%; left: 5%; }
            .item-4 { bottom: 5%; right: 5%; }
            .item-5 { top: 20%; left: 3%; }
            .item-6 { top: 20%; right: 3%; }
            .item-7 { bottom: 20%; left: 10%; }
            .item-8 { bottom: 20%; right: 3%; }
            .item-9 { top: 70%; left: 5%; }
            .item-10 { top: 70%; right: 5%; }
        }

        @media (max-width: 480px) {
            .pharmacy-item {
                display: none; /* Hide on very small screens to reduce clutter */
            }
        }

        /* Login Container - Responsive */
        .login-container {
            background: var(--light-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 450px;
            min-width: 300px;
            padding: 30px 25px;
            box-shadow: var(--shadow);
            position: relative;
            z-index: 2;
            overflow: hidden;
            animation: containerAppear 1s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin: 0 auto;
        }

        @media (min-width: 480px) {
            .login-container {
                padding: 35px 30px;
            }
        }

        @media (min-width: 768px) {
            .login-container {
                padding: 40px;
                max-width: 420px;
            }
        }

        @keyframes containerAppear {
            0% { opacity: 0; transform: translateY(30px) scale(0.95); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: -2px; left: -2px; right: -2px; bottom: -2px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color), var(--accent-color), var(--primary-color));
            z-index: -1;
            border-radius: calc(var(--border-radius) + 2px);
            animation: borderGlow 3s linear infinite;
            background-size: 400%;
        }

        @keyframes borderGlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .login-container::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: var(--light-bg);
            border-radius: var(--border-radius);
            z-index: -1;
        }

        /* Logo - Responsive */
        .logo {
            text-align: center;
            margin-bottom: 25px;
            animation: logoFloat 3s ease-in-out infinite;
        }

        @media (max-width: 480px) {
            .logo {
                margin-bottom: 20px;
            }
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .logo-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 10px;
            display: inline-block;
            animation: spin 20s linear infinite;
        }

        @media (min-width: 480px) {
            .logo-icon {
                font-size: 3rem;
            }
        }

        @media (min-width: 768px) {
            .logo-icon {
                font-size: 3.5rem;
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .logo h1 { 
            color: var(--primary-color); 
            font-size: 1.5rem;
            font-weight: 700; 
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        @media (min-width: 480px) {
            .logo h1 {
                font-size: 1.8rem;
            }
        }
        
        @media (min-width: 768px) {
            .logo h1 {
                font-size: 2rem;
            }
        }

        .logo p { 
            color: var(--secondary-color); 
            font-size: 0.9rem;
            margin-top: 5px; 
            font-weight: 500; 
        }
        
        @media (min-width: 480px) {
            .logo p {
                font-size: 1rem;
            }
        }

        /* Error Message - Responsive */
        .error-message {
            background: linear-gradient(45deg, var(--error-color), #ee5a52);
            color: white;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            animation: errorShake 0.5s ease;
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
            font-size: 0.9rem;
        }

        @media (min-width: 480px) {
            .error-message {
                padding: 15px;
                font-size: 1rem;
            }
        }

        @keyframes errorShake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .error-message i { 
            margin-right: 10px; 
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        /* Form Styling - Responsive */
        .form-group { 
            margin-bottom: 20px; 
            position: relative; 
        }
        
        @media (min-width: 480px) {
            .form-group {
                margin-bottom: 25px;
            }
        }

        .form-label { 
            display: block; 
            margin-bottom: 8px; 
            color: var(--primary-color); 
            font-weight: 600; 
            font-size: 0.85rem;
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }
        
        @media (min-width: 480px) {
            .form-label {
                font-size: 0.9rem;
            }
        }

        .input-group { 
            position: relative; 
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
            font-size: 1.1rem;
            transition: all 0.3s;
            z-index: 1;
        }
        
        @media (min-width: 480px) {
            .input-icon {
                font-size: 1.2rem;
            }
        }

        .form-input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid transparent;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            color: var(--text-color);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            -webkit-appearance: none;
            appearance: none;
        }
        
        @media (min-width: 480px) {
            .form-input {
                padding: 15px 15px 15px 50px;
                font-size: 1.05rem;
            }
        }

        .form-input:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(38, 208, 206, 0.3), 0 10px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .form-input:focus + .input-icon {
            color: var(--primary-color);
            transform: translateY(-50%) scale(1.1);
        }

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            cursor: pointer;
            transition: color 0.3s;
            z-index: 2;
            font-size: 1.1rem;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        @media (min-width: 480px) {
            .password-toggle {
                font-size: 1.2rem;
            }
        }
        
        .password-toggle:hover { 
            color: var(--secondary-color); 
        }

        /* Login Button - Responsive */
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            margin-top: 10px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 10px 20px rgba(26, 41, 128, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            -webkit-tap-highlight-color: transparent;
        }
        
        @media (min-width: 480px) {
            .btn-login {
                padding: 16px;
                font-size: 1.05rem;
            }
        }

        .btn-login:hover,
        .btn-login:focus {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(26, 41, 128, 0.3);
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
        }
        
        .btn-login:active { 
            transform: translateY(-1px); 
        }
        
        .btn-login:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }
        
        .btn-login:hover:before { 
            left: 100%; 
        }

        /* Footer - Responsive */
        .login-footer { 
            text-align: center; 
            margin-top: 20px; 
            color: #666; 
            font-size: 0.8rem;
            line-height: 1.4;
        }
        
        @media (min-width: 480px) {
            .login-footer {
                font-size: 0.85rem;
                margin-top: 25px;
            }
        }

        .forgot-password { 
            color: var(--secondary-color); 
            text-decoration: none; 
            font-weight: 500; 
            transition: all 0.3s; 
            position: relative; 
            display: inline-block; 
            padding: 3px 0;
            white-space: nowrap;
        }
        
        .forgot-password:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background: var(--secondary-color);
            transition: width 0.3s;
        }
        
        .forgot-password:hover:after { 
            width: 100%; 
        }

        /* Security Message - Responsive */
        .security-message { 
            text-align: center; 
            margin-top: 15px; 
            font-size: 0.75rem;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: #666; 
            flex-wrap: wrap;
            gap: 5px;
        }
        
        @media (min-width: 480px) {
            .security-message {
                font-size: 0.8rem;
                margin-top: 20px;
            }
        }
        
        .security-message i { 
            color: var(--secondary-color); 
            animation: pulse 2s infinite; 
            font-size: 0.9rem;
        }
        
        @keyframes pulse { 
            0% { transform: scale(1); } 
            50% { transform: scale(1.1); } 
            100% { transform: scale(1); } 
        }

        /* Loading Overlay - Responsive */
        .loading { 
            display: none; 
            position: absolute; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background: rgba(255, 255, 255, 0.95); 
            border-radius: var(--border-radius); 
            z-index: 10; 
            justify-content: center; 
            align-items: center; 
            flex-direction: column; 
        }
        
        .spinner { 
            width: 40px; 
            height: 40px; 
            border: 4px solid #f3f3f3; 
            border-top: 4px solid var(--secondary-color); 
            border-radius: 50%; 
            animation: spin 1s linear infinite; 
            margin-bottom: 15px; 
        }
        
        @media (min-width: 480px) {
            .spinner {
                width: 50px;
                height: 50px;
                border-width: 5px;
            }
        }
        
        .loading-text { 
            color: var(--primary-color); 
            font-weight: 600; 
            font-size: 1rem;
        }
        
        @media (min-width: 480px) {
            .loading-text {
                font-size: 1.1rem;
            }
        }

        /* Special Mobile Optimizations */
        @media (max-width: 320px) {
            .login-container {
                padding: 20px 15px;
                border-radius: 20px;
            }
            
            .logo-icon {
                font-size: 2.2rem;
            }
            
            .logo h1 {
                font-size: 1.3rem;
            }
            
            .form-input {
                padding: 12px 12px 12px 40px;
                font-size: 0.95rem;
            }
            
            .btn-login {
                padding: 14px;
                font-size: 0.95rem;
            }
            
            .input-icon {
                font-size: 1rem;
                left: 12px;
            }
        }

        /* Tablet Optimizations */
        @media (min-width: 768px) and (max-width: 1024px) {
            .login-container {
                max-width: 400px;
            }
            
            body {
                padding: 30px;
            }
        }

        /* Landscape Mode */
        @media (max-height: 600px) and (orientation: landscape) {
            body {
                padding: 10px;
                align-items: flex-start;
                min-height: auto;
            }
            
            .login-container {
                margin: 10px auto;
                max-width: 400px;
            }
            
            .logo {
                margin-bottom: 15px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .login-footer {
                margin-top: 15px;
            }
            
            .pharmacy-item {
                display: none; /* Hide in landscape to save space */
            }
        }

        /* Large Screens */
        @media (min-width: 1200px) {
            .login-container {
                max-width: 450px;
            }
        }

        /* Very Large Screens */
        @media (min-width: 1600px) {
            .login-container {
                max-width: 500px;
                padding: 50px;
            }
            
            .logo-icon {
                font-size: 4rem;
            }
            
            .logo h1 {
                font-size: 2.5rem;
            }
            
            .logo p {
                font-size: 1.2rem;
            }
            
            .form-input {
                font-size: 1.1rem;
                padding: 18px 18px 18px 60px;
            }
            
            .input-icon {
                font-size: 1.4rem;
                left: 20px;
            }
            
            .password-toggle {
                font-size: 1.4rem;
                right: 20px;
            }
            
            .btn-login {
                padding: 20px;
                font-size: 1.2rem;
            }
        }

        /* Prevent zoom on iOS input focus */
        @media (max-width: 767px) {
            input, select, textarea {
                font-size: 16px !important;
            }
            
            .form-input:focus {
                font-size: 16px !important;
            }
        }

        /* Touch device optimizations */
        @media (hover: none) and (pointer: coarse) {
            .btn-login:hover {
                transform: none;
            }
            
            .password-toggle:hover {
                color: #999;
            }
            
            .forgot-password:hover:after {
                width: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Pharmacy Items -->
    <div class="pharmacy-item item-1"><i class="fas fa-pills"></i></div>
    <div class="pharmacy-item item-2"><i class="fas fa-prescription-bottle-alt"></i></div>
    <div class="pharmacy-item item-3"><i class="fas fa-syringe"></i></div>
    <div class="pharmacy-item item-4"><i class="fas fa-heartbeat"></i></div>
    <div class="pharmacy-item item-5"><i class="fas fa-capsules"></i></div>
    <div class="pharmacy-item item-6"><i class="fas fa-stethoscope"></i></div>
    <div class="pharmacy-item item-7"><i class="fas fa-thermometer"></i></div>
    <div class="pharmacy-item item-8"><i class="fas fa-band-aid"></i></div>
    <div class="pharmacy-item item-9"><i class="fas fa-hospital"></i></div>
    <div class="pharmacy-item item-10"><i class="fas fa-tablets"></i></div>

    <!-- Login Container -->
    <div class="login-container">
        <div class="loading" id="loadingOverlay">
            <div class="spinner"></div>
            <div class="loading-text">Authenticating...</div>
        </div>

        <!-- Logo -->
        <div class="logo">
            <div class="logo-icon"><i class="fas fa-clinic-medical"></i></div>
            <h1>PharmaCare RX</h1>
            <p>Pharmacy Management System</p>
        </div>

        <!-- Error Message -->
        <?php if(!empty($login_error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?php echo htmlspecialchars($login_error); ?></span>
        </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" id="loginForm">
            <div class="form-group">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <div class="input-icon"><i class="fas fa-user-md"></i></div>
                    <input type="text" name="username" class="form-input" placeholder="Enter your username" required autocomplete="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <div class="input-icon"><i class="fas fa-lock"></i></div>
                    <input type="password" name="password" class="form-input" id="passwordInput" placeholder="Enter your password" required autocomplete="current-password">
                    <div class="password-toggle" id="passwordToggle" aria-label="Show password"><i class="fas fa-eye"></i></div>
                </div>
            </div>

            <button type="submit" class="btn-login" aria-label="Login to dashboard">
                <i class="fas fa-sign-in-alt"></i> 
                <span>Login to Dashboard</span>
            </button>
        </form>

        <!-- Security Message -->
        <div class="security-message">
            <i class="fas fa-shield-alt"></i>
            <span>Secure SSL Encrypted Connection</span>
        </div>

        <!-- Footer -->
        <div class="login-footer">
            <p>© <?php echo date('Y'); ?> PharmaCare RX. All rights reserved.</p>
            <p><a href="#" class="forgot-password">Forgot Password?</a></p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.getElementById('loginForm');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const passwordInput = document.getElementById('passwordInput');
        const passwordToggle = document.getElementById('passwordToggle');
        const pharmacyItems = document.querySelectorAll('.pharmacy-item');
        const btnLoginText = document.querySelector('.btn-login span');
        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

        // Password toggle
        passwordToggle.addEventListener('click', function() {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            this.setAttribute('aria-label', type === 'password' ? 'Show password' : 'Hide password');
        });

        // Show loading overlay on submit
        loginForm.addEventListener('submit', function(e) {
            loadingOverlay.style.display = 'flex';
            btnLoginText.textContent = 'Logging in...';
            
            // Disable form resubmission
            setTimeout(() => {
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
            }, 100);
        });

        // Interactive floating items (only on non-touch devices)
        if (!isTouchDevice && window.innerWidth > 480) {
            pharmacyItems.forEach(item => {
                const randomX = (Math.random() - 0.5) * 20;
                const randomY = (Math.random() - 0.5) * 20;
                item.style.transform = `translate(${randomX}px, ${randomY}px)`;

                document.addEventListener('mousemove', (e) => {
                    const rect = item.getBoundingClientRect();
                    const itemCenterX = rect.left + rect.width / 2;
                    const itemCenterY = rect.top + rect.height / 2;
                    const mouseX = e.clientX;
                    const mouseY = e.clientY;
                    const distance = Math.sqrt((mouseX - itemCenterX)**2 + (mouseY - itemCenterY)**2);

                    if(distance < 150) {
                        const force = (150 - distance)/150;
                        const angle = Math.atan2(mouseY - itemCenterY, mouseX - itemCenterX);
                        const moveX = Math.cos(angle + Math.PI) * force * 30;
                        const moveY = Math.sin(angle + Math.PI) * force * 30;
                        item.style.transform = `translate(${moveX}px, ${moveY}px) scale(${1 + force * 0.3})`;
                        item.style.color = `rgba(255,255,255,${0.7 + force * 0.3})`;
                    } else {
                        item.style.transform = `translate(${randomX}px, ${randomY}px) scale(1)`;
                        item.style.color = 'rgba(255, 255, 255, 0.7)';
                    }
                });

                item.addEventListener('click', function(){
                    this.style.transform += ' scale(1.5)';
                    this.style.color = '#00ffea';
                    this.style.textShadow = '0 0 20px #00ffea';
                    setTimeout(() => {
                        this.style.transform = `translate(${randomX}px, ${randomY}px) scale(1)`;
                        this.style.color = 'rgba(255, 255, 255, 0.7)';
                        this.style.textShadow = 'none';
                    }, 500);
                });
            });
        } else {
            // For touch devices, make items clickable but don't add mouse move effects
            pharmacyItems.forEach(item => {
                item.style.pointerEvents = 'auto';
                item.addEventListener('click', function() {
                    this.style.transform = 'scale(1.5)';
                    this.style.color = '#00ffea';
                    this.style.textShadow = '0 0 20px #00ffea';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                        this.style.color = 'rgba(255, 255, 255, 0.7)';
                        this.style.textShadow = 'none';
                    }, 500);
                });
            });
        }

        // Adjust floating items on window resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                if (!isTouchDevice && window.innerWidth > 480) {
                    pharmacyItems.forEach(item => {
                        const randomX = (Math.random() - 0.5) * 20;
                        const randomY = (Math.random() - 0.5) * 20;
                        item.style.transform = `translate(${randomX}px, ${randomY}px)`;
                    });
                }
            }, 250);
        });

        // Focus on username field on page load
        const usernameInput = document.querySelector('input[name="username"]');
        if (usernameInput && !usernameInput.value) {
            setTimeout(() => {
                usernameInput.focus();
            }, 300);
        }

        // Prevent form submission on Enter key in password field if not on mobile
        if (window.innerWidth > 768) {
            passwordInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    loginForm.requestSubmit();
                }
            });
        }
    });

    // Prevent iOS zoom on input focus
    document.addEventListener('touchstart', function() {}, {passive: true});
    </script>
</body>
</html>