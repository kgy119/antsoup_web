<?php
require_once '../../includes/auth.php';
require_once '../../includes/response.php';
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
    
    // 토큰 가져오기
    $token = AuthUtils::getTokenFromRequest();
    
    if ($token) {
        // 토큰을 블랙리스트에 추가 (선택사항)
        addTokenToBlacklist($db, $token, $currentUser['id']);
    }
    
    // 사용자의 모든 활성 세션 무효화 (선택사항)
    invalidateUserSessions($db, $currentUser['id']);
    
    ResponseUtils::success(null, '로그아웃되었습니다.');
    
} catch (Exception $e) {
    error_log('Logout error: ' . $e->getMessage());
    ResponseUtils::serverError();
}

/**
 * 토큰을 블랙리스트에 추가
 */
function addTokenToBlacklist($db, $token, $userId) {
    try {
        // 토큰 블랙리스트 테이블이 있는 경우
        $stmt = $db->prepare("
            INSERT INTO token_blacklist (token_hash, user_id, created_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE created_at = NOW()
        ");
        
        // 토큰을 해시화하여 저장 (보안상 전체 토큰을 저장하지 않음)
        $tokenHash = hash('sha256', $token);
        $stmt->execute([$tokenHash, $userId]);
        
    } catch (Exception $e) {
        // 블랙리스트 테이블이 없거나 오류가 발생해도 로그아웃은 성공으로 처리
        error_log('Token blacklist error: ' . $e->getMessage());
    }
}

/**
 * 사용자의 모든 세션 무효화
 */
function invalidateUserSessions($db, $userId) {
    try {
        // 사용자 세션 테이블이 있는 경우
        $stmt = $db->prepare("
            UPDATE user_sessions 
            SET is_active = 0, updated_at = NOW() 
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute([$userId]);
        
    } catch (Exception $e) {
        // 세션 테이블이 없거나 오류가 발생해도 로그아웃은 성공으로 처리
        error_log('Session invalidation error: ' . $e->getMessage());
    }
}