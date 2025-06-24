<?php
require_once '../../includes/auth.php';
require_once '../../includes/response.php';
require_once '../../includes/validation.php';
require_once '../../includes/email.php';
require_once '../../config/database.php';

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
    if (!isset($input['email'])) {
        ResponseUtils::error('이메일을 입력해주세요.');
    }
    
    $email = trim($input['email']);
    
    // 이메일 형식 검증
    $emailError = ValidationUtils::validateEmail($email);
    if ($emailError) {
        ResponseUtils::error($emailError);
    }
    
    // 사용자 조회
    $user = getUserByEmail($db, $email);
    
    // 보안상 사용자가 존재하지 않아도 성공 메시지 반환
    if (!$user) {
        ResponseUtils::success(null, '비밀번호 재설정 이메일이 전송되었습니다.');
    }
    
    // 최근 재설정 요청 확인 (스팸 방지)
    if (hasRecentPasswordReset($db, $user['id'])) {
        ResponseUtils::error('비밀번호 재설정 이메일은 5분에 한 번만 전송할 수 있습니다.');
    }
    
    // 재설정 토큰 생성
    $resetToken = AuthUtils::generateRandomToken();
    
    // 데이터베이스에 재설정 토큰 저장
    savePasswordResetToken($db, $user['id'], $resetToken);
    
    // 이메일 전송
    $emailSent = EmailUtils::sendPasswordResetEmail(
        $user['email'], 
        $resetToken, 
        $user['username']
    );
    
    if (!$emailSent) {
        ResponseUtils::error('이메일 전송에 실패했습니다. 나중에 다시 시도해주세요.');
    }
    
    ResponseUtils::success(null, '비밀번호 재설정 이메일이 전송되었습니다.');
    
} catch (Exception $e) {
    error_log('Password reset error: ' . $e->getMessage());
    ResponseUtils::serverError();
}

/**
 * 이메일로 사용자 조회
 */
function getUserByEmail($db, $email) {
    try {
        $stmt = $db->prepare("
            SELECT id, username, email 
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
 * 최근 비밀번호 재설정 요청 확인
 */
function hasRecentPasswordReset($db, $userId) {
    try {
        $stmt = $db->prepare("
            SELECT id FROM password_resets 
            WHERE user_id = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            AND is_used = 0
        ");
        $stmt->execute([$userId]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 비밀번호 재설정 토큰 저장
 */
function savePasswordResetToken($db, $userId, $token) {
    try {
        // 기존 미사용 토큰 무효화
        $stmt = $db->prepare("
            UPDATE password_resets 
            SET is_used = 1 
            WHERE user_id = ? AND is_used = 0
        ");
        $stmt->execute([$userId]);
        
        // 새 재설정 토큰 저장
        $stmt = $db->prepare("
            INSERT INTO password_resets (user_id, reset_token, expires_at, created_at) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
        ");
        $stmt->execute([$userId, $token, PASSWORD_RESET_EXPIRY]);
        
    } catch (Exception $e) {
        throw new Exception('재설정 토큰 저장 실패: ' . $e->getMessage());
    }
}