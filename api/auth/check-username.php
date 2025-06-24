<?php
// api/auth/check-username.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS 요청 처리 (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// GET 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// 필요한 파일들 include
require_once '../../config/database.php';
require_once '../../models/User.php';

try {
    // 쿼리 파라미터에서 username 가져오기
    $username = isset($_GET['username']) ? trim($_GET['username']) : '';

    if (empty($username)) {
        throw new Exception('사용자명을 입력해주세요.');
    }

    // 기본 검증
    if (mb_strlen($username, 'UTF-8') < 3 || mb_strlen($username, 'UTF-8') > 20) {
        throw new Exception('사용자명은 3-20자 사이여야 합니다.');
    }

    // 사용자명 형식 검증 (한글, 영문, 숫자, 언더스코어 허용)
    if (!preg_match('/^[가-힣a-zA-Z0-9_]+$/u', $username)) {
        throw new Exception('사용자명은 한글, 영문, 숫자, 언더스코어(_)만 사용 가능합니다.');
    }

    // 언더스코어 시작/종료 제한
    if (preg_match('/^_|_$/', $username)) {
        throw new Exception('사용자명은 언더스코어(_)로 시작하거나 끝날 수 없습니다.');
    }

    // 연속된 언더스코어 제한
    if (strpos($username, '__') !== false) {
        throw new Exception('연속된 언더스코어(_)는 사용할 수 없습니다.');
    }

    // 예약어 확인
    $reserved_words = [
        'admin', 'administrator', 'root', 'user', 'guest', 'anonymous',
        'null', 'undefined', 'system', 'api', 'www', 'mail', 'ftp',
        'test', 'demo', 'support', 'help', 'info', 'contact',
        '관리자', '운영자', '테스트', '시스템', '고객센터'
    ];

    if (in_array(strtolower($username), array_map('strtolower', $reserved_words)) || 
        in_array($username, $reserved_words)) {
        throw new Exception('사용할 수 없는 사용자명입니다.');
    }

    // User 객체 생성
    $user = new User($pdo);

    // 중복 확인
    $exists = $user->usernameExists($username);

    // 응답
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'exists' => $exists,
        'message' => $exists ? '이미 사용 중인 사용자명입니다.' : '사용 가능한 사용자명입니다.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>