<?php
// includes/auth.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';

class AuthUtils {
    
    /**
     * 요청에서 토큰 추출
     */
    public static function getTokenFromRequest() {
        $headers = getallheaders();
        
        // Authorization 헤더에서 토큰 추출
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        
        // authorization 헤더 (소문자)
        if (isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * 토큰 검증
     */
    public static function verifyToken($token) {
        try {
            // 간단한 Base64 토큰 디코딩 (실제로는 JWT 라이브러리 사용 권장)
            $payload = json_decode(base64_decode($token), true);
            
            if (!$payload || !isset($payload['user_id']) || !isset($payload['exp'])) {
                return false;
            }
            
            // 토큰 만료 확인
            if ($payload['exp'] < time()) {
                return false;
            }
            
            return $payload;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 현재 사용자 정보 가져오기
     */
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
            $database = new Database();
            $pdo = $database->getConnection();
            
            $stmt = $pdo->prepare("
                SELECT id, username, email, phone_number, profile_picture,
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
            
            // 불린 값 변환
            $user['email_verified'] = (bool)$user['email_verified'];
            $user['phone_verified'] = (bool)$user['phone_verified'];
            
            return $user;
        } catch (Exception $e) {
            error_log('Get current user error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 인증 필요 (로그인 체크)
     */
    public static function requireAuth() {
        $user = self::getCurrentUser();
        
        if (!$user) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => '인증이 필요합니다.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
        
        return $user;
    }
    
    /**
     * 토큰 생성
     */
    public static function generateToken($userId, $email) {
        $payload = [
            'user_id' => $userId,
            'email' => $email,
            'exp' => time() + (24 * 60 * 60), // 24시간
            'iat' => time(),
            'iss' => 'antsoup.co.kr'
        ];
        
        return base64_encode(json_encode($payload));
    }
    
    /**
     * 인증 코드 생성 (6자리 숫자)
     */
    public static function generateVerificationCode() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * 랜덤 토큰 생성 (비밀번호 재설정 등)
     */
    public static function generateRandomToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * 토큰 블랙리스트 확인
     */
    public static function isTokenBlacklisted($token) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            $tokenHash = hash('sha256', $token);
            
            $stmt = $pdo->prepare("
                SELECT id FROM blacklisted_tokens 
                WHERE token_hash = ? AND expires_at > NOW()
            ");
            $stmt->execute([$tokenHash]);
            
            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 사용자 로그인 시도 기록
     */
    public static function logLoginAttempt($email, $success, $failureReason = null) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            $stmt = $pdo->prepare("
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
    
    /**
     * 사용자 활동 로그
     */
    public static function logUserActivity($userId, $action, $resourceType = null, $resourceId = null, $metadata = null) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            $stmt = $pdo->prepare("
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
}

// 전역 상수 정의
if (!defined('EMAIL_VERIFICATION_EXPIRY')) {
    define('EMAIL_VERIFICATION_EXPIRY', 600); // 10분
}

if (!defined('PASSWORD_RESET_EXPIRY')) {
    define('PASSWORD_RESET_EXPIRY', 3600); // 1시간
}
?>