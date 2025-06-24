<?php
require_once __DIR__ . '/env.php';

// .env 파일 로드
Env::load();

// JWT 설정
define('JWT_SECRET_KEY',              Env::get('JWT_SECRET_KEY'));
define('JWT_ALGORITHM',               Env::get('JWT_ALGORITHM', 'HS256'));
define('JWT_EXPIRATION_TIME',         (int) Env::get('JWT_EXPIRATION_TIME', 86400));         // 1일
define('JWT_REFRESH_EXPIRATION_TIME', (int) Env::get('JWT_REFRESH_EXPIRATION_TIME', 604800)); // 7일
define('JWT_ISSUER',                  Env::get('JWT_ISSUER', 'antsoup.co.kr'));
define('JWT_AUDIENCE',                Env::get('JWT_AUDIENCE', 'antsoup-app'));
?>