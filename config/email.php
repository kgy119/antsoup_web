<?php
require_once __DIR__ . '/env.php';

define('SMTP_HOST', Env::get('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', Env::get('SMTP_PORT', 587));
define('SMTP_USERNAME', Env::get('SMTP_USERNAME'));
define('SMTP_PASSWORD', Env::get('SMTP_PASSWORD'));
define('SMTP_ENCRYPTION', Env::get('SMTP_ENCRYPTION', 'tls'));

define('SMTP_FROM_EMAIL', Env::get('SMTP_FROM_EMAIL'));
define('SMTP_FROM_NAME', Env::get('SMTP_FROM_NAME', 'AntSoup'));

define('FRONTEND_URL', Env::get('APP_URL', 'https://antsoup.co.kr'));
define('EMAIL_VERIFICATION_EXPIRY', Env::get('EMAIL_VERIFICATION_EXPIRY', 600));
define('PASSWORD_RESET_EXPIRY', Env::get('PASSWORD_RESET_EXPIRY', 3600));
?>