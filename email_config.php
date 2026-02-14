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

if (!defined('SMTP_SECURE')) {
	define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'starttls');
}

if (!defined('SMTP_AUTH')) {
	define('SMTP_AUTH', (bool)(getenv('SMTP_AUTH') ?: true));
}

if (!defined('SMTP_TIMEOUT')) {
	define('SMTP_TIMEOUT', (int)(getenv('SMTP_TIMEOUT') ?: 30));
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

if (!defined('SMTP_FROM_EMAIL')) {
	define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: SMTP_USERNAME);
}

if (!defined('SMTP_BOUNCE_EMAIL')) {
	define('SMTP_BOUNCE_EMAIL', getenv('SMTP_BOUNCE_EMAIL') ?: SMTP_USERNAME);
}

if (!defined('EMAIL_VALIDATE_STRICT')) {
	define('EMAIL_VALIDATE_STRICT', (bool)(getenv('EMAIL_VALIDATE_STRICT') ?: true));
}

if (!defined('EMAIL_BLOCKED_DOMAINS')) {
	define('EMAIL_BLOCKED_DOMAINS', getenv('EMAIL_BLOCKED_DOMAINS') ?: 'example.com,example.org,test.com,invalid.local,localhost');
}
?>
