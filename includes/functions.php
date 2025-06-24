<?php
// 공통 함수 모음

// XSS 방지를 위한 문자열 이스케이프
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// 입력값 정리
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// 로그인 확인
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// 관리자 권한 확인
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// 페이지 리다이렉트
function redirect($url) {
    header("Location: $url");
    exit();
}

// 현재 URL 가져오기
function current_url() {
    return "http" . (isset($_SERVER['HTTPS']) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// 날짜 포매팅
function format_date($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

// 한국 날짜 포매팅
function korean_date($date) {
    return date('Y년 m월 d일', strtotime($date));
}

// 파일 업로드 함수
function upload_file($file, $upload_dir = 'uploads/') {
    $target_dir = $upload_dir;
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // 허용된 파일 확장자
    $allowed_extensions = array("jpg", "jpeg", "png", "gif", "pdf", "doc", "docx");
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return false;
    }
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return $new_filename;
    }
    
    return false;
}

// 이메일 유효성 검사
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// 비밀번호 해시화
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// 비밀번호 확인
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// CSRF 토큰 생성
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF 토큰 확인
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 에러 메시지 표시
function show_error($message) {
    echo "<div class='alert alert-error'>$message</div>";
}

// 성공 메시지 표시
function show_success($message) {
    echo "<div class='alert alert-success'>$message</div>";
}
?>