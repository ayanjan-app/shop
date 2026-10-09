<?php
// config.php - Email Configuration

// Email Settings
define('SMTP_HOST', 'mail.yourdomain.com'); // Your cPanel mail server
define('SMTP_PORT', 587); // or 465 for SSL
define('SMTP_USERNAME', 'alerts@yourdomain.com'); // Create this in cPanel
define('SMTP_PASSWORD', 'your_email_password');
define('SMTP_SECURE', 'tls'); // tls or ssl

// Notification Settings
define('ADMIN_EMAIL', 'your-personal-email@gmail.com'); // Your personal email
define('ADMIN_NAME', 'PharmaCare Admin');
define('FROM_EMAIL', 'alerts@yourdomain.com');
define('FROM_NAME', 'PharmaCare RX Stock Alert');

// Stock Threshold
define('LOW_STOCK_THRESHOLD', 2);
define('CRITICAL_STOCK_THRESHOLD', 1);

// Notification Frequency
define('NOTIFY_EVERY_HOURS', 24); // Send max once every 24 hours per item
?>