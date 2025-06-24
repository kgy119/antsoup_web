<?php
// 세션 시작
session_start();

// 데이터베이스 연결
require_once 'config/database.php';

// 공통 함수 포함
require_once 'includes/functions.php';

// 페이지 제목 설정
$page_title = "홈페이지";
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <h1>웹사이트에 오신 것을 환영합니다!</h1>
            
            
            
            <div class="content">
                <p>여기에 메인 콘텐츠를 작성하세요.</p>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/script.js"></script>
</body>
</html>