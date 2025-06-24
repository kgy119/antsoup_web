<?php

class ResponseUtils {
    
    /**
     * 성공 응답
     */
    public static function success($data = null, $message = '성공', $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => true,
            'message' => $message
        ];
        
        if ($data !== null) {
            if (is_array($data) && isset($data['user'])) {
                $response = array_merge($response, $data);
            } else {
                $response['data'] = $data;
            }
        }
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * 에러 응답
     */
    public static function error($message = '오류가 발생했습니다.', $statusCode = 400, $errors = null) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'error' => $message,
            'message' => $message
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * 유효성 검사 에러 응답
     */
    public static function validationError($errors, $message = '입력값을 확인해주세요.') {
        self::error($message, 422, $errors);
    }
    
    /**
     * 인증 에러 응답
     */
    public static function unauthorized($message = '인증이 필요합니다.') {
        self::error($message, 401);
    }
    
    /**
     * 권한 없음 에러 응답
     */
    public static function forbidden($message = '권한이 없습니다.') {
        self::error($message, 403);
    }
    
    /**
     * 찾을 수 없음 에러 응답
     */
    public static function notFound($message = '요청한 리소스를 찾을 수 없습니다.') {
        self::error($message, 404);
    }
    
    /**
     * 서버 에러 응답
     */
    public static function serverError($message = '서버 내부 오류가 발생했습니다.') {
        self::error($message, 500);
    }
    
    /**
     * CORS 헤더 설정
     */
    public static function setCorsHeaders() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}