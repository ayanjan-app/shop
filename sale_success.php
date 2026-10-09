<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$sale_id = $_GET['id'] ?? 0;
$additional_info = [];

// Fetch additional sale info if available
if($sale_id > 0) {
    try {
        // First check if the table exists and has the expected columns
        $stmt = $conn->prepare("SELECT customer_name, total_amount, payment_method FROM sales WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $sale_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result && $row = $result->fetch_assoc()) {
                $additional_info = $row;
            }
            $stmt->close();
        }
    } catch(Exception $e) {
        // Silently fail - additional info is optional
        error_log("Error fetching sale info: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Successful - PharmaCare RX</title>
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
            --border-radius: 15px;
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .success-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }

        .success-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 50px 40px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: cardAppear 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .success-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--secondary-color), var(--accent-color));
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, var(--success-color), #43A047);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 0 10px 25px rgba(76, 175, 80, 0.3);
            animation: iconPulse 2s infinite;
        }

        .success-icon i {
            color: white;
            font-size: 3.5rem;
        }

        .success-title {
            color: var(--primary-color);
            font-size: 2.2rem;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .success-message {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .sale-info {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            padding: 25px;
            border-radius: 10px;
            margin: 30px 0;
            border-left: 5px solid var(--primary-color);
            text-align: left;
        }

        .sale-id {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .sale-id-label {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .sale-id-value {
            background: var(--primary-color);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 1.2rem;
            letter-spacing: 1px;
        }

        .sale-time {
            color: #666;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sale-time i {
            color: var(--secondary-color);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            white-space: nowrap;
            min-width: 160px;
            justify-content: center;
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

        .btn-success {
            background: linear-gradient(45deg, var(--success-color), #43A047);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(76, 175, 80, 0.3);
            background: linear-gradient(45deg, #43A047, var(--success-color));
        }

        .btn-print {
            background: linear-gradient(45deg, #6c757d, #495057);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-print:hover {
            background: linear-gradient(45deg, #495057, #6c757d);
            transform: translateY(-3px);
        }

        .btn i {
            font-size: 1.1rem;
        }

        .back-home {
            margin-top: 30px;
            text-align: center;
        }

        .back-home a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .back-home a:hover {
            color: var(--primary-color);
            transform: translateX(-5px);
        }

        .additional-info {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed #ddd;
            text-align: left;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .info-label {
            color: #666;
            font-weight: 500;
        }

        .info-value {
            color: var(--primary-color);
            font-weight: 600;
        }

        /* Confetti Animation */
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            animation: confettiFall 3s ease-in-out infinite;
            opacity: 0.8;
            pointer-events: none;
            z-index: -1;
        }

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes iconPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 10px 25px rgba(76, 175, 80, 0.3);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 15px 35px rgba(76, 175, 80, 0.4);
            }
        }

        @keyframes confettiFall {
            0% {
                transform: translateY(-100px) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.8;
            }
            90% {
                opacity: 0.8;
            }
            100% {
                transform: translateY(calc(100vh + 100px)) rotate(720deg);
                opacity: 0;
            }
        }

        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        @media (max-width: 768px) {
            .success-card {
                padding: 40px 25px;
            }
            
            .success-title {
                font-size: 1.8rem;
            }
            
            .success-icon {
                width: 80px;
                height: 80px;
            }
            
            .success-icon i {
                font-size: 2.8rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
            
            .sale-info {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
            
            .success-card {
                padding: 30px 20px;
            }
            
            .success-title {
                font-size: 1.6rem;
            }
            
            .success-message {
                font-size: 1rem;
            }
            
            .sale-id {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }

        @media print {
            .confetti, .back-home, .success-icon {
                display: none !important;
            }
            
            .success-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            
            .success-title {
                color: black !important;
            }
            
            .sale-id-value {
                background: white !important;
                color: black !important;
                border: 1px solid #333 !important;
            }
            
            .btn {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Confetti animation elements will be added by JavaScript -->

    <div class="success-container">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h1 class="success-title">Sale Completed Successfully!</h1>
            
            <p class="success-message">
                Your sale has been processed successfully. The receipt has been saved to the database 
                and inventory has been updated accordingly.
            </p>
            
            <div class="sale-info">
                <div class="sale-id">
                    <span class="sale-id-label">Sale Reference:</span>
                    <span class="sale-id-value">#<?php echo str_pad($sale_id, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                
                <div class="sale-time">
                    <i class="far fa-clock"></i>
                    <span><?php echo date('F j, Y \a\t g:i A'); ?></span>
                </div>
                
                <?php if(!empty($additional_info)): ?>
                <div class="additional-info">
                    <div class="info-item">
                        <span class="info-label">Customer:</span>
                        <span class="info-value"><?php echo htmlspecialchars($additional_info['customer_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Amount:</span>
                        <span class="info-value">$<?php echo number_format($additional_info['total_amount'], 2); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Payment Method:</span>
                        <span class="info-value"><?php echo htmlspecialchars($additional_info['payment_method']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="action-buttons">
                <a href="save.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> New Sale
                </a>
                
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                
                <?php if($sale_id > 0): ?>
                <a href="view_sale.php?id=<?php echo $sale_id; ?>" target="_blank" class="btn btn-success">
                    <i class="fas fa-receipt"></i> View Receipt
                </a>
                
                <button onclick="window.print()" class="btn btn-print">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <?php endif; ?>
            </div>
            
            <div class="back-home">
                <a href="dashboard.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create confetti elements
            createConfetti();
            
            // Add ripple effect to buttons
            addRippleEffect();
            
            // Auto-redirect after 60 seconds (optional)
            setTimeout(() => {
                if(window.location.pathname.includes('sale_success')) {
                    window.location.href = 'new_sale.php';
                }
            }, 60000);
        });
        
        function createConfetti() {
            const colors = [
                'linear-gradient(45deg, var(--primary-color), var(--secondary-color))',
                'linear-gradient(45deg, var(--secondary-color), var(--accent-color))',
                'linear-gradient(45deg, var(--accent-color), var(--primary-color))',
                'linear-gradient(45deg, var(--success-color), var(--secondary-color))',
                'linear-gradient(45deg, var(--warning-color), var(--primary-color))'
            ];
            
            for(let i = 0; i < 9; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = `${Math.random() * 100}%`;
                confetti.style.width = `${Math.random() * 10 + 5}px`;
                confetti.style.height = confetti.style.width;
                confetti.style.animationDelay = `${Math.random() * 5}s`;
                confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                
                document.body.appendChild(confetti);
            }
        }
        
        function addRippleEffect() {
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const rect = this.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    const ripple = document.createElement('span');
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.5);
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        width: 100px;
                        height: 100px;
                        top: ${y - 50}px;
                        left: ${x - 50}px;
                        pointer-events: none;
                    `;
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                   this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        }
    </script>
</body>
</html>