<?php
require_once '../../includes/auth.php';
require_once '../../includes/response.php';
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
    
    // 인증 필요
    $currentUser = AuthUtils::requireAuth();
    
    // 이미 인증된 사용자인지 확인
    if ($currentUser['email_verified']) {
        ResponseUtils::error('이미 이메일 인증이 완료되었습니다.');
    }
    
    // 최근 인증 코드 전송 확인 (스팸 방지)
    if (hasRecentVerificationCode($db, $currentUser['id'])) {
        ResponseUtils::error('인증 코드는 1분에 한 번만 전송할 수 있습니다.');
    }
    
    // 인증 코드 생성
    $verificationCode = AuthUtils::generateVerificationCode();
    
    // 데이터베이스에 인증 코드 저장
    saveVerificationCode($db, $currentUser['id'], $verificationCode);
    
    // 이메일 전송
    $emailSent = EmailUtils::sendVerificationCode(
        $currentUser['email'], 
        $verificationCode, 
        $currentUser['username']
    );
    
    if (!$emailSent) {
        ResponseUtils::error('이메일 전송에 실패했습니다. 나중에 다시 시도해주세요.');
    }
    
    ResponseUtils::success(null, '인증 코드가 이메일로 전송되었습니다.');
    
} catch (Exception $e) {
    error_log('Email verification send error: ' . $e->getMessage());
    ResponseUtils::serverError();
}

/**
 * 최근 인증 코드 전송 여부 확인
 */
function hasRecentVerificationCode($db, $userId) {
    try {
        $stmt = $db->prepare("
            SELECT id FROM email_verifications 
            WHERE user_id = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            AND is_used = 0
        ");
        $stmt->execute([$userId]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 인증 코드 저장
 */
function saveVerificationCode($db, $userId, $code) {
    try {
        // 기존 미사용 인증 코드 무효화
        $stmt = $db->prepare("
            UPDATE email_verifications 
            SET is_used = 1 
            WHERE user_id = ? AND is_used = 0
        ");
        $stmt->execute([$userId]);
        
        // 새 인증 코드 저장
        $stmt = $db->prepare("
            INSERT INTO email_verifications (user_id, verification_code, expires_at, created_at) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
        ");
        $stmt->execute([$userId, $code, EMAIL_VERIFICATION_EXPIRY]);
        
    } catch (Exception $e) {
        throw new Exception('인증 코드 저장 실패: ' . $e->getMessage());
    }
}