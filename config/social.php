<?php
require_once __DIR__ . '/env.php';

Env::load(); // 반드시 호출 (한 번만 실행됨)

// Google OAuth 설정
define('GOOGLE_CLIENT_ID', Env::get('GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', Env::get('GOOGLE_CLIENT_SECRET'));
define('GOOGLE_REDIRECT_URI', Env::get('GOOGLE_REDIRECT_URI'));

// Facebook OAuth 설정  
define('FACEBOOK_APP_ID', Env::get('FACEBOOK_APP_ID'));
define('FACEBOOK_APP_SECRET', Env::get('FACEBOOK_APP_SECRET'));
define('FACEBOOK_REDIRECT_URI', Env::get('FACEBOOK_REDIRECT_URI'));

// OAuth API URLs (기본값 지정 가능)
define('GOOGLE_TOKEN_VERIFY_URL', Env::get('GOOGLE_TOKEN_VERIFY_URL', 'https://oauth2.googleapis.com/tokeninfo'));
define('GOOGLE_USERINFO_URL', Env::get('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo'));
define('FACEBOOK_TOKEN_VERIFY_URL', Env::get('FACEBOOK_TOKEN_VERIFY_URL', 'https://graph.facebook.com/me'));
define('FACEBOOK_USERINFO_URL', Env::get('FACEBOOK_USERINFO_URL', 'https://graph.facebook.com/me?fields=id,name,email,picture'));
?>