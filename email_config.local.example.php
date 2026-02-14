<?php
// Copy this file to email_config.local.php and fill your SMTP credentials.
define('SMTP_HOST', 'smtp.yourdomain.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'starttls'); // starttls | ssl | none
define('SMTP_AUTH', true);
define('SMTP_TIMEOUT', 30);
define('SMTP_USERNAME', 'notifications@yourdomain.com');
define('SMTP_PASSWORD', 'your-secure-password');
define('SMTP_FROM_NAME', 'University Portal');
define('SMTP_FROM_EMAIL', 'notifications@yourdomain.com');
define('SMTP_BOUNCE_EMAIL', 'bounces@yourdomain.com');
define('EMAIL_VALIDATE_STRICT', true);
define('EMAIL_BLOCKED_DOMAINS', 'example.com,example.org,test.com,invalid.local,localhost');
?>
