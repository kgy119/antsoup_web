<?php
require_once __DIR__ . '/env.php';

// Google OAuth 설정
define('GOOGLE_CLIENT_ID', Env::get('GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', Env::get('GOOGLE_CLIENT_SECRET'));
define('GOOGLE_REDIRECT_URI', Env::get('GOOGLE_REDIRECT_URI'));

// Facebook OAuth 설정  
define('FACEBOOK_APP_ID', Env::get('FACEBOOK_APP_ID'));
define('FACEBOOK_APP_SECRET', Env::get('FACEBOOK_APP_SECRET'));
define('FACEBOOK_REDIRECT_URI', Env::get('FACEBOOK_REDIRECT_URI'));

// OAuth API URLs (일반적으로 고정값이므로 .env에서 가져오거나 기본값 사용)
define('GOOGLE_TOKEN_VERIFY_URL', Env::get('GOOGLE_TOKEN_VERIFY_URL', 'https://oauth2.googleapis.com/tokeninfo'));
define('GOOGLE_USERINFO_URL', Env::get('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo'));
define('FACEBOOK_TOKEN_VERIFY_URL', Env::get('FACEBOOK_TOKEN_VERIFY_URL', 'https://graph.facebook.com/me'));
define('FACEBOOK_USERINFO_URL', Env::get('FACEBOOK_USERINFO_URL', 'https://graph.facebook.com/me?fields=id,name,email,picture'));
?>