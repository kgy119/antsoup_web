<?php
require_once __DIR__ . '/env.php';

define('JWT_SECRET_KEY', Env::get('JWT_SECRET_KEY'));
define('JWT_ALGORITHM', Env::get('JWT_ALGORITHM', 'HS256'));
define('JWT_EXPIRATION_TIME', Env::get('JWT_EXPIRATION_TIME', 86400));
define('JWT_REFRESH_EXPIRATION_TIME', Env::get('JWT_REFRESH_EXPIRATION_TIME', 604800));
define('JWT_ISSUER', Env::get('JWT_ISSUER', 'antsoup.co.kr'));
define('JWT_AUDIENCE', Env::get('JWT_AUDIENCE', 'antsoup-app'));
?>