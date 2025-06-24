<?php
require_once '../../includes/auth.php';
require_once '../../includes/response.php';
require_once '../../includes/validation.php';
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
    
    // 입력 데이터 검증
    $input = ValidationUtils::validateJsonInput();
    if (isset($input['error'])) {
        ResponseUtils::error($input['error']);
    }
    
    // 필수 필드 확인
    if (!isset($input['verification_code'])) {
        ResponseUtils::error('인증 코드를 입력해주세요.');
    }
    
    $verificationCode = trim($input['verification_code']);
    
    // 인증 코드 형식 검증
    $codeError = ValidationUtils::validateVerificationCode($verificationCode);
    if ($codeError) {
        ResponseUtils::error($codeError);
    }
    
    // 이미 인증된 사용자인지 확인
    if ($currentUser['email_verified']) {
        ResponseUtils::error('이미 이메일 인증이 완료되었습니다.');
    }
    
    // 인증 코드 확인
    $verification = getValidVerificationCode($db, $currentUser['id'], $verificationCode);
    
    if (!$verification) {
        ResponseUtils::error('유효하지 않거나 만료된 인증 코드입니다.');
    }
    
    // 이메일 인증 처리
    $db->beginTransaction();
    
    try {
        // 사용자 이메일 인증 상태 업데이트
        $stmt = $db->prepare("
            UPDATE users 
            SET email_verified = 1, email_verified_at = NOW(), updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$currentUser['id']]);
        
        // 인증 코드 사용 처리
        $stmt = $db->prepare("
            UPDATE email_verifications 
            SET is_used = 1, used_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$verification['id']]);
        
        $db->commit();
        
        ResponseUtils::success(null, '이메일 인증이 완료되었습니다.');
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Email verification confirm error: ' . $e->getMessage());
    ResponseUtils::serverError();
}

/**
 * 유효한 인증 코드 조회
 */
function getValidVerificationCode($db, $userId, $code) {
    try {
        $stmt = $db->prepare("
            SELECT id, verification_code 
            FROM email_verifications 
            WHERE user_id = ? 
            AND verification_code = ? 
            AND is_used = 0 
            AND expires_at > NOW()
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId, $code]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}