<?php
require_once '../../includes/auth.php';
require_once '../../includes/response.php';
require_once '../../includes/validation.php';
require_once '../../config/database.php';
require_once '../../config/social.php';

// CORS 헤더 설정
ResponseUtils::setCorsHeaders();

// POST 메소드만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseUtils::error('지원하지 않는 HTTP 메소드입니다.', 405);
}

try {
    $db = Database::getInstance()->getConnection();
    
    // 입력 데이터 검증
    $input = ValidationUtils::validateJsonInput();
    if (isset($input['error'])) {
        ResponseUtils::error($input['error']);
    }
    
    // 필수 필드 확인
    if (!isset($input['google_token'])) {
        ResponseUtils::error('구글 토큰이 필요합니다.');
    }
    
    $googleToken = trim($input['google_token']);
    $username = isset($input['username']) ? trim($input['username']) : null;
    
    // 구글 토큰 검증 및 사용자 정보 가져오기
    $googleUser = verifyGoogleToken($googleToken);
    
    if (!$googleUser) {
        ResponseUtils::error('유효하지 않은 구글 토큰입니다.');
    }
    
    // 이메일로 기존 사용자 확인
    $existingUser = getUserByEmail($db, $googleUser['email']);
    
    if ($existingUser) {
        // 기존 사용자 로그인
        if ($existingUser['provider'] !== 'google' && $existingUser['provider'] !== 'local') {
            ResponseUtils::error('이 이메일은 다른 소셜 계정으로 가입되어 있습니다.');
        }
        
        // 프로바이더 정보 업데이트 (local에서 google로 변경될 수 있음)
        updateUserProvider($db, $existingUser['id'], 'google', $googleUser['id']);
        
        $user = $existingUser;
    } else {
        // 새 사용자 등록
        if (!$username) {
            // 사용자명이 제공되지 않은 경우 구글 이름에서 생성
            $username = generateUsernameFromName($db, $googleUser['name']);
        } else {
            // 사용자명 유효성 검사
            $usernameError = ValidationUtils::validateUsername($username);
            if ($usernameError) {
                ResponseUtils::error($usernameError);
            }
            
            // 사용자명 중복 확인
            if (isUsernameTaken($db, $username)) {
                ResponseUtils::error('이미 사용 중인 사용자명입니다.');
            }
        }
        
        $user = createGoogleUser($db, $googleUser, $username);
    }
    
    // JWT 토큰 생성
    $token = AuthUtils::generateToken($user['id'], $user['email']);
    
    // 사용자 정보에서 민감한 데이터 제거
    unset($user['password']);
    $user['email_verified'] = (bool)$user['email_verified'];
    $user['phone_verified'] = (bool)$user['phone_verified'];
    
    ResponseUtils::success([
        'user' => $user,
        'token' => $token
    ], '구글 로그인에 성공했습니다.');
    
} catch (Exception $e) {
    error_log('Google auth error: ' . $e->getMessage());
    ResponseUtils::serverError();
}

/**
 * 구글 토큰 검증 및 사용자 정보 가져오기
 */
function verifyGoogleToken($token) {
    try {
        // Google OAuth2 API로 사용자 정보 가져오기
        $url = GOOGLE_USERINFO_URL . '?access_token=' . $token;
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $token
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            return false;
        }
        
        $userData = json_decode($response, true);
        
        if (!isset($userData['id']) || !isset($userData['email'])) {
            return false;
        }
        
        return [
            'id' => $userData['id'],
            'email' => $userData['email'],
            'name' => $userData['name'] ?? '',
            'picture' => $userData['picture'] ?? null,
            'verified_email' => $userData['verified_email'] ?? false
        ];
        
    } catch (Exception $e) {
        error_log('Google token verification error: ' . $e->getMessage());
        return false;
    }
}

/**
 * 이메일로 사용자 조회
 */
function getUserByEmail($db, $email) {
    try {
        $stmt = $db->prepare("
            SELECT id, username, email, provider, email_verified, phone_verified, 
                   profile_image, created_at, updated_at
            FROM users 
            WHERE email = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$email]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 사용자 프로바이더 정보 업데이트
 */
function updateUserProvider($db, $userId, $provider, $providerId) {
    try {
        $stmt = $db->prepare("
            UPDATE users 
            SET provider = ?, provider_id = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$provider, $providerId, $userId]);
    } catch (Exception $e) {
        error_log('Update user provider error: ' . $e->getMessage());
    }
}

/**
 * 이름에서 사용자명 생성
 */
function generateUsernameFromName($db, $name) {
    // 이름을 영문자와 숫자만 남기고 정리
    $baseUsername = preg_replace('/[^a-zA-Z0-9]/', '', $name);
    $baseUsername = strtolower($baseUsername);
    
    if (strlen($baseUsername) < 3) {
        $baseUsername = 'user' . mt_rand(1000, 9999);
    }
    
    // 사용자명이 너무 길면 자르기
    if (strlen($baseUsername) > 15) {
        $baseUsername = substr($baseUsername, 0, 15);
    }
    
    // 중복 확인 및 숫자 추가
    $username = $baseUsername;
    $counter = 1;
    
    while (isUsernameTaken($db, $username)) {
        $username = $baseUsername . $counter;
        $counter++;
        
        // 무한 루프 방지
        if ($counter > 9999) {
            $username = 'user' . mt_rand(10000, 99999);
            break;
        }
    }
    
    return $username;
}

/**
 * 사용자명 중복 확인
 */
function isUsernameTaken($db, $username) {
    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND deleted_at IS NULL");
        $stmt->execute([$username]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 구글 사용자 생성
 */
function createGoogleUser($db, $googleUser, $username) {
    try {
        $stmt = $db->prepare("
            INSERT INTO users (
                username, email, provider, provider_id, 
                email_verified, email_verified_at, profile_image,
                created_at, updated_at
            ) VALUES (?, ?, 'google', ?, 1, NOW(), ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $username,
            $googleUser['email'],
            $googleUser['id'],
            $googleUser['picture']
        ]);
        
        $userId = $db->lastInsertId();
        
        // 생성된 사용자 정보 반환
        $stmt = $db->prepare("
            SELECT id, username, email, provider, email_verified, phone_verified,
                   profile_image, created_at, updated_at
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        throw new Exception('구글 사용자 생성 실패: ' . $e->getMessage());
    }
}