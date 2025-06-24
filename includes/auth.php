<?php
// 기존 데이터베이스 구조에 맞춘 API 수정 사항들

// 1. AuthUtils::getCurrentUser() 수정 - 기존 컬럼명에 맞춤
class AuthUtils {
    public static function getCurrentUser() {
        $token = self::getTokenFromRequest();
        if (!$token) {
            return null;
        }
        
        $payload = self::verifyToken($token);
        if (!$payload) {
            return null;
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT id, username, email, phone_number, profile_picture as profile_image,
                       provider, provider_id, email_verified, phone_verified, status,
                       last_login, created_at, updated_at
                FROM users 
                WHERE id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$payload['user_id']]);
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                return null;
            }
            
            return $user;
        } catch (Exception $e) {
            return null;
        }
    }
}

// 2. 토큰 블랙리스트 함수 수정 - 기존 blacklisted_tokens 테이블 사용
function addTokenToBlacklist($db, $token, $userId) {
    try {
        $stmt = $db->prepare("
            INSERT INTO blacklisted_tokens (token_hash, user_id, expires_at) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
            ON DUPLICATE KEY UPDATE blacklisted_at = NOW()
        ");
        
        $tokenHash = hash('sha256', $token);
        $stmt->execute([$tokenHash, $userId]);
        
    } catch (Exception $e) {
        error_log('Token blacklist error: ' . $e->getMessage());
    }
}

// 3. 소셜 로그인 시 social_accounts 테이블도 업데이트
function createGoogleUser($db, $googleUser, $username) {
    try {
        $db->beginTransaction();
        
        // users 테이블에 사용자 생성
        $stmt = $db->prepare("
            INSERT INTO users (
                username, email, provider, provider_id, 
                email_verified, email_verified_at, profile_picture,
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
        
        // social_accounts 테이블에도 정보 저장
        $stmt = $db->prepare("
            INSERT INTO social_accounts (
                user_id, provider, provider_id, provider_email, 
                provider_name, provider_avatar, created_at, updated_at
            ) VALUES (?, 'google', ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $userId,
            $googleUser['id'],
            $googleUser['email'],
            $googleUser['name'],
            $googleUser['picture']
        ]);
        
        // user_settings 기본값 생성
        $stmt = $db->prepare("
            INSERT INTO user_settings (user_id, created_at, updated_at) 
            VALUES (?, NOW(), NOW())
        ");
        $stmt->execute([$userId]);
        
        // user_profiles 기본값 생성
        $stmt = $db->prepare("
            INSERT INTO user_profiles (user_id, created_at, updated_at) 
            VALUES (?, NOW(), NOW())
        ");
        $stmt->execute([$userId]);
        
        // 기본 'user' 역할 할당
        $stmt = $db->prepare("
            INSERT INTO user_role_assignments (user_id, role_id, assigned_at) 
            VALUES (?, 1, NOW())
        ");
        $stmt->execute([$userId]);
        
        $db->commit();
        
        // 생성된 사용자 정보 반환
        $stmt = $db->prepare("
            SELECT id, username, email, provider, email_verified, phone_verified,
                   profile_picture as profile_image, created_at, updated_at
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw new Exception('구글 사용자 생성 실패: ' . $e->getMessage());
    }
}

// 4. 이메일 인증 상태 업데이트 시 users 테이블도 함께 업데이트
function verifyEmailWithUserTable($db, $currentUser, $verificationCode) {
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
            WHERE user_id = ? AND verification_code = ? AND is_used = 0
        ");
        $stmt->execute([$currentUser['id'], $verificationCode]);
        
        // 활동 로그 기록
        $stmt = $db->prepare("
            INSERT INTO user_activity_logs (user_id, action, ip_address, user_agent, created_at) 
            VALUES (?, 'email_verified', ?, ?, NOW())
        ");
        $stmt->execute([
            $currentUser['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        $db->commit();
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

// 5. 로그인 시도 기록 함수
function logLoginAttempt($db, $email, $success, $failureReason = null) {
    try {
        $stmt = $db->prepare("
            INSERT INTO login_attempts (
                email, ip_address, user_agent, success, failure_reason, attempted_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $email,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $success ? 1 : 0,
            $failureReason
        ]);
    } catch (Exception $e) {
        error_log('Login attempt logging error: ' . $e->getMessage());
    }
}

// 6. 사용자 활동 로그 함수
function logUserActivity($db, $userId, $action, $resourceType = null, $resourceId = null, $metadata = null) {
    try {
        $stmt = $db->prepare("
            INSERT INTO user_activity_logs (
                user_id, action, resource_type, resource_id, 
                ip_address, user_agent, metadata, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $resourceType,
            $resourceId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $metadata ? json_encode($metadata) : null
        ]);
    } catch (Exception $e) {
        error_log('User activity logging error: ' . $e->getMessage());
    }
}
?>