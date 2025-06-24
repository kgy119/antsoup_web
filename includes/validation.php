<?php
// includes/validation.php

class ValidationUtils {
    
    /**
     * JSON 입력 데이터 검증
     */
    public static function validateJsonInput() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => '잘못된 JSON 형식입니다.'];
        }
        
        return $input;
    }
    
    /**
     * 이메일 유효성 검사
     */
    public static function validateEmail($email) {
        if (empty($email)) {
            return '이메일을 입력해주세요.';
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '올바른 이메일 형식을 입력해주세요.';
        }
        
        if (strlen($email) > 255) {
            return '이메일 주소가 너무 깁니다.';
        }
        
        return null;
    }
    
    /**
     * 비밀번호 유효성 검사
     */
    public static function validatePassword($password) {
        if (empty($password)) {
            return '비밀번호를 입력해주세요.';
        }
        
        if (strlen($password) < 6) {
            return '비밀번호는 최소 6자 이상이어야 합니다.';
        }
        
        if (strlen($password) > 255) {
            return '비밀번호가 너무 깁니다.';
        }
        
        // 영문자 확인
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return '비밀번호에 영문자가 포함되어야 합니다.';
        }
        
        // 숫자 확인
        if (!preg_match('/[0-9]/', $password)) {
            return '비밀번호에 숫자가 포함되어야 합니다.';
        }
        
        // 특수문자 확인
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            return '비밀번호에 특수문자가 포함되어야 합니다.';
        }
        
        return null;
    }
    
    /**
     * 사용자명 유효성 검사
     */
    public static function validateUsername($username) {
        if (empty($username)) {
            return '사용자명을 입력해주세요.';
        }
        
        $length = mb_strlen($username, 'UTF-8');
        if ($length < 3 || $length > 20) {
            return '사용자명은 3-20자 사이여야 합니다.';
        }
        
        // 사용자명 형식 검증 (한글, 영문, 숫자, 언더스코어 허용)
        if (!preg_match('/^[가-힣a-zA-Z0-9_]+$/u', $username)) {
            return '사용자명은 한글, 영문, 숫자, 언더스코어(_)만 사용 가능합니다.';
        }
        
        // 언더스코어 시작/종료 제한
        if (preg_match('/^_|_$/', $username)) {
            return '사용자명은 언더스코어(_)로 시작하거나 끝날 수 없습니다.';
        }
        
        // 연속된 언더스코어 제한
        if (strpos($username, '__') !== false) {
            return '연속된 언더스코어(_)는 사용할 수 없습니다.';
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
            return '사용할 수 없는 사용자명입니다.';
        }
        
        return null;
    }
    
    /**
     * 전화번호 유효성 검사
     */
    public static function validatePhoneNumber($phoneNumber) {
        if (empty($phoneNumber)) {
            return null; // 선택사항이므로 빈 값 허용
        }
        
        // 숫자만 추출
        $digits = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // 한국 휴대폰 번호 형식 확인 (010으로 시작하는 11자리)
        if (!preg_match('/^010[0-9]{8}$/', $digits)) {
            return '올바른 전화번호 형식을 입력해주세요. (010-xxxx-xxxx)';
        }
        
        return null;
    }
    
    /**
     * 인증 코드 유효성 검사
     */
    public static function validateVerificationCode($code) {
        if (empty($code)) {
            return '인증 코드를 입력해주세요.';
        }
        
        // 6자리 숫자 코드 확인
        if (!preg_match('/^[0-9]{6}$/', $code)) {
            return '인증 코드는 6자리 숫자여야 합니다.';
        }
        
        return null;
    }
    
    /**
     * 이름 유효성 검사
     */
    public static function validateName($name, $fieldName = '이름') {
        if (empty($name)) {
            return null; // 선택사항
        }
        
        $length = mb_strlen($name, 'UTF-8');
        if ($length < 2 || $length > 50) {
            return "{$fieldName}은 2-50자 사이여야 합니다.";
        }
        
        // 한글, 영문만 허용
        if (!preg_match('/^[가-힣a-zA-Z\s]+$/u', $name)) {
            return "{$fieldName}은 한글 또는 영문만 사용 가능합니다.";
        }
        
        return null;
    }
    
    /**
     * URL 유효성 검사
     */
    public static function validateUrl($url) {
        if (empty($url)) {
            return null; // 선택사항
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '올바른 URL 형식이 아닙니다.';
        }
        
        return null;
    }
    
    /**
     * 날짜 유효성 검사
     */
    public static function validateDate($date) {
        if (empty($date)) {
            return null; // 선택사항
        }
        
        $dateTime = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateTime || $dateTime->format('Y-m-d') !== $date) {
            return '올바른 날짜 형식이 아닙니다. (YYYY-MM-DD)';
        }
        
        // 미래 날짜 제한 (생년월일 등)
        $today = new DateTime();
        if ($dateTime > $today) {
            return '미래 날짜는 입력할 수 없습니다.';
        }
        
        return null;
    }
    
    /**
     * 성별 유효성 검사
     */
    public static function validateGender($gender) {
        if (empty($gender)) {
            return null; // 선택사항
        }
        
        $allowedGenders = ['남성', '여성', '기타', 'male', 'female', 'other'];
        
        if (!in_array($gender, $allowedGenders)) {
            return '올바른 성별을 선택해주세요.';
        }
        
        return null;
    }
}
?>