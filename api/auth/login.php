<?php
// api/auth/login.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS 요청 처리 (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// 필요한 파일들 include
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

try {
    // JSON 입력 데이터 받기
    $input = json_decode(file_get_contents('php://input'), true);

    // JSON 파싱 에러 체크
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('잘못된 JSON 형식입니다.');
    }

    // 입력 검증
    if (empty($input['email']) || empty($input['password'])) {
        throw new Exception('이메일과 비밀번호를 입력해주세요.');
    }

    $email = trim($input['email']);
    $password = $input['password'];

    // 이메일 형식 검증
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('올바른 이메일 형식을 입력해주세요.');
    }

    // User 객체 생성
    $user = new User($pdo);

    // 사용자 인증
    $authenticated_user = $user->authenticate($email, $password);

    if (!$authenticated_user) {
        throw new Exception('이메일 또는 비밀번호가 올바르지 않습니다.');
    }

    // 계정 상태 확인
    if ($authenticated_user['status'] !== 'active') {
        throw new Exception('비활성화된 계정입니다. 고객센터에 문의해주세요.');
    }

    // JWT 토큰 생성
    $token = generateSimpleToken($authenticated_user['id']);

    // 성공 응답
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => '로그인이 완료되었습니다.',
        'user' => $authenticated_user,
        'token' => $token
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// 간단한 토큰 생성 함수
function generateSimpleToken($user_id) {
    $payload = [
        'user_id' => $user_id,
        'exp' => time() + (24 * 60 * 60), // 24시간
        'iat' => time()
    ];
    
    return base64_encode(json_encode($payload));
}
?>