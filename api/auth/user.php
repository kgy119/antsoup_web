<?php
require_once '../../includes/auth.php';
require_once '../../includes/response.php';
require_once '../../includes/validation.php';
require_once '../../config/database.php';

// CORS 헤더 설정
ResponseUtils::setCorsHeaders();

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // 현재 사용자 정보 조회
            getUserInfo($db);
            break;
            
        case 'PUT':
            // 사용자 정보 업데이트
            updateUserInfo($db);
            break;
            
        default:
            ResponseUtils::error('지원하지 않는 HTTP 메소드입니다.', 405);
    }
    
} catch (Exception $e) {
    error_log('User API Error: ' . $e->getMessage());
    ResponseUtils::serverError();
}

/**
 * 현재 사용자 정보 조회
 */
function getUserInfo($db) {
    // 인증 필요
    $currentUser = AuthUtils::requireAuth();
    
    try {
        $stmt = $db->prepare("
            SELECT id, username, email, phone_number, provider, 
                   email_verified, phone_verified, profile_image,
                   created_at, updated_at
            FROM users 
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$currentUser['id']]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            ResponseUtils::notFound('사용자를 찾을 수 없습니다.');
        }
        
        // 불린 값 변환
        $user['email_verified'] = (bool)$user['email_verified'];
        $user['phone_verified'] = (bool)$user['phone_verified'];
        
        ResponseUtils::success(['user' => $user]);
        
    } catch (Exception $e) {
        error_log('Get user info error: ' . $e->getMessage());
        ResponseUtils::serverError();
    }
}

/**
 * 사용자 정보 업데이트
 */
function updateUserInfo($db) {
    // 인증 필요
    $currentUser = AuthUtils::requireAuth();
    
    // 입력 데이터 검증
    $input = ValidationUtils::validateJsonInput();
    if (isset($input['error'])) {
        ResponseUtils::error($input['error']);
    }
    
    $allowedFields = ['username', 'phone_number', 'profile_image'];
    $updateFields = [];
    $updateValues = [];
    $errors = [];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $value = $input[$field];
            
            // 필드별 유효성 검사
            switch ($field) {
                case 'username':
                    $error = ValidationUtils::validateUsername($value);
                    if ($error) {
                        $errors['username'] = $error;
                        continue 2;
                    }
                    
                    // 사용자명 중복 확인 (자신 제외)
                    if (isUsernameTaken($db, $value, $currentUser['id'])) {
                        $errors['username'] = '이미 사용 중인 사용자명입니다.';
                        continue 2;
                    }
                    break;
                    
                case 'phone_number':
                    $error = ValidationUtils::validatePhoneNumber($value);
                    if ($error) {
                        $errors['phone_number'] = $error;
                        continue 2;
                    }
                    break;
                    
                case 'profile_image':
                    // URL 형식 검증 (간단한 체크)
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors['profile_image'] = '올바른 URL 형식이 아닙니다.';
                        continue 2;
                    }
                    break;
            }
            
            $updateFields[] = "{$field} = ?";
            $updateValues[] = $value;
        }
    }
    
    if (!empty($errors)) {
        ResponseUtils::validationError($errors);
    }
    
    if (empty($updateFields)) {
        ResponseUtils::error('업데이트할 항목이 없습니다.');
    }
    
    try {
        // 업데이트 실행
        $updateValues[] = $currentUser['id'];
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($updateValues);
        
        // 업데이트된 사용자 정보 조회
        $stmt = $db->prepare("
            SELECT id, username, email, phone_number, provider, 
                   email_verified, phone_verified, profile_image,
                   created_at, updated_at
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$currentUser['id']]);
        
        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 불린 값 변환
        $updatedUser['email_verified'] = (bool)$updatedUser['email_verified'];
        $updatedUser['phone_verified'] = (bool)$updatedUser['phone_verified'];
        
        ResponseUtils::success(['user' => $updatedUser], '사용자 정보가 업데이트되었습니다.');
        
    } catch (Exception $e) {
        error_log('Update user info error: ' . $e->getMessage());
        ResponseUtils::serverError();
    }
}

/**
 * 사용자명 중복 확인 (자신 제외)
 */
function isUsernameTaken($db, $username, $excludeUserId) {
    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ? AND deleted_at IS NULL");
        $stmt->execute([$username, $excludeUserId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}