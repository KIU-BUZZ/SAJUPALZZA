<?php
/**
 * 공통 헤더 (사용자 페이지)
 * 모든 사용자 페이지에서 include
 */
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$pageTitle = $pageTitle ?? SITE_NAME;
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFD43B">
    <meta name="description" content="사주포춘 - 정통 사주팔자 분석 서비스">
    <title><?= h($pageTitle) ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
    <!-- 상단 헤더 -->
    <header class="app-header">
        <div class="header-inner">
            <a href="<?= SITE_URL ?>/pages/home.php" class="header-logo">
                <span class="logo-icon">☯</span>
                <span class="logo-text"><?= SITE_NAME ?></span>
            </a>
            <div class="header-actions">
                <?php if ($currentUser): ?>
                    <span class="ticket-badge" title="보유 티켓">
                        <i class="fas fa-ticket-alt"></i>
                        <span><?= (int)$currentUser['tickets'] ?></span>
                    </span>
                    <a href="<?= SITE_URL ?>/pages/mypage.php" class="header-icon" title="마이페이지">
                        <i class="fas fa-user-circle"></i>
                    </a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-sm btn-primary">로그인</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- 플래시 메시지 -->
    <?php if ($flash): ?>
    <div class="flash-message flash-<?= h($flash['type']) ?>" id="flashMessage">
        <div class="flash-inner">
            <?php
            $icons = ['success' => 'check-circle', 'error' => 'exclamation-circle', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'];
            $icon = $icons[$flash['type']] ?? 'info-circle';
            ?>
            <i class="fas fa-<?= $icon ?>"></i>
            <span><?= h($flash['message']) ?></span>
            <button class="flash-close" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- 메인 콘텐츠 -->
    <main class="app-main">
