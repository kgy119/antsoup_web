<header class="site-header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="index.php">
                    <h1>로고</h1>
                </a>
            </div>
            
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">홈</a></li>
                    <li><a href="pages/about.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>">소개</a></li>
                    <li><a href="pages/board.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'board.php' ? 'active' : ''; ?>">게시판</a></li>
                    <li><a href="pages/contact.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">연락처</a></li>
                </ul>
            </nav>
            
            <div class="user-menu">
                <?php if(is_logged_in()): ?>
                    <span>환영합니다, <?php echo escape($_SESSION['username']); ?>님!</span>
                    <a href="pages/logout.php">로그아웃</a>
                    <?php if(is_admin()): ?>
                        <a href="admin/">관리자</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="pages/login.php">로그인</a>
                    <a href="pages/register.php">회원가입</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>