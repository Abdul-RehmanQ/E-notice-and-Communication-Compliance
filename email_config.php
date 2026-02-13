<?php
// Load optional local config (not committed)
if (file_exists(__DIR__ . '/email_config.local.php')) {
	include __DIR__ . '/email_config.local.php';
}

if (!defined('SMTP_HOST')) {
	define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
}

if (!defined('SMTP_PORT')) {
	define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
}

if (!defined('SMTP_USERNAME')) {
	define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
}

if (!defined('SMTP_PASSWORD')) {
	define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
}

if (!defined('SMTP_FROM_NAME')) {
	define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'University Portal');
}
?>
