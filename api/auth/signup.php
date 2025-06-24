<?php
// api/auth/signup.php
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
require_once '../../config/database.php';
require_once '../../models/User.php';

try {
    // JSON 입력 데이터 받기
    $input = json_decode(file_get_contents('php://input'), true);

    // JSON 파싱 에러 체크
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('잘못된 JSON 형식입니다.');
    }

    // 입력 검증
    $required_fields = ['username', 'email', 'password'];
    $missing_fields = [];

    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        throw new Exception('다음 필드들이 필요합니다: ' . implode(', ', $missing_fields));
    }

    // 입력값 정리 및 검증
    $username = trim($input['username']);
    $email = trim($input['email']);
    $password = $input['password'];
    $phone_number = isset($input['phone_number']) ? trim($input['phone_number']) : null;

    // 기본 검증
    if (mb_strlen($username, 'UTF-8') < 3 || mb_strlen($username, 'UTF-8') > 20) {
        throw new Exception('사용자명은 3-20자 사이여야 합니다.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('올바른 이메일 형식을 입력해주세요.');
    }

    if (strlen($password) < 6) {
        throw new Exception('비밀번호는 최소 6자 이상이어야 합니다.');
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

    // 비밀번호 복잡성 검증 (완화된 요구사항)
    if (!validatePassword($password)) {
        throw new Exception('비밀번호에 영문자, 숫자, 특수문자가 하나 이상 포함되어야 합니다.');
    }

    // 전화번호 검증 (입력된 경우)
    if ($phone_number && !empty($phone_number)) {
        $phone_digits = preg_replace('/[^0-9]/', '', $phone_number);
        if (strlen($phone_digits) !== 11 || !preg_match('/^010[0-9]{8}$/', $phone_digits)) {
            throw new Exception('올바른 전화번호 형식을 입력해주세요. (010-xxxx-xxxx)');
        }
        // 전화번호 형식 정리
        $phone_number = substr($phone_digits, 0, 3) . '-' . substr($phone_digits, 3, 4) . '-' . substr($phone_digits, 7, 4);
    }

    // User 객체 생성
    $user = new User($pdo);

    // 중복 확인
    if ($user->emailExists($email)) {
        throw new Exception('이미 가입된 이메일 주소입니다.');
    }

    if ($user->usernameExists($username)) {
        throw new Exception('이미 사용 중인 사용자명입니다.');
    }

    // 비밀번호 해싱
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 사용자 데이터 준비
    $user_data = [
        'username' => $username,
        'email' => $email,
        'password' => $hashed_password,
        'phone_number' => $phone_number,
        'provider' => 'local'
    ];

    // 사용자 생성
    $user_id = $user->create($user_data);

    if (!$user_id) {
        throw new Exception('회원가입 중 오류가 발생했습니다.');
    }

    // 생성된 사용자 정보 조회
    $created_user = $user->getById($user_id);
    if (!$created_user) {
        throw new Exception('사용자 정보를 조회할 수 없습니다.');
    }

    // 비밀번호 제거
    unset($created_user['password']);

    // JWT 토큰 생성 (간단한 버전)
    $token = generateSimpleToken($user_id);

    // 성공 응답
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => '회원가입이 완료되었습니다.',
        'user' => $created_user,
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

// 완화된 비밀번호 검증 함수
function validatePassword($password) {
    // 영문자 확인 (대소문자 구분 없음)
    if (!preg_match('/[a-zA-Z]/', $password)) {
        return false;
    }
    
    // 숫자 확인
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    
    // 특수문자 확인
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        return false;
    }
    
    // 연속된 문자나 반복 문자 확인 (선택사항)
    if (hasConsecutiveChars($password)) {
        return false;
    }
    
    return true;
}

// 연속된 문자 확인 함수
function hasConsecutiveChars($password) {
    $len = strlen($password);
    
    for ($i = 0; $i < $len - 2; $i++) {
        $char1 = ord($password[$i]);
        $char2 = ord($password[$i + 1]);
        $char3 = ord($password[$i + 2]);
        
        // 연속된 숫자나 문자 확인 (123, abc, 321, cba 등)
        if (($char2 == $char1 + 1 && $char3 == $char2 + 1) ||
            ($char2 == $char1 - 1 && $char3 == $char2 - 1)) {
            return true;
        }
        
        // 반복된 문자 확인 (111, aaa 등)
        if ($char1 == $char2 && $char2 == $char3) {
            return true;
        }
    }
    
    return false;
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