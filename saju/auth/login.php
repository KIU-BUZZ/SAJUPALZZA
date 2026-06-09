<?php
/**
 * 로그인 페이지
 */
require_once __DIR__ . '/../config/config.php';

// 이미 로그인했으면 홈으로
if (isLoggedIn()) {
    redirect('/pages/home.php');
}

$errors = [];
$email = '';

// POST 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // CSRF 검증
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = '잘못된 요청입니다. 다시 시도해 주세요.';
    }
    
    if (empty($email) || empty($password)) {
        $errors[] = '이메일과 비밀번호를 입력해 주세요.';
    }
    
    if (empty($errors)) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM saju_users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // 로그인 성공
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login_time'] = time();
            
            setFlashMessage('success', $user['nickname'] . '님 환영합니다!');
            
            // 관리자면 관리자 페이지로
            if ($user['role'] === 'admin') {
                header('Location: ' . SITE_URL . '/admin/index.php');
            } else {
                header('Location: ' . SITE_URL . '/pages/home.php');
            }
            exit;
        } else {
            $errors[] = '이메일 또는 비밀번호가 올바르지 않습니다.';
        }
    }
}

$pageTitle = '로그인 - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= h($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body style="padding-top: 0; padding-bottom: 0;">

    <?php $flash = getFlashMessage(); if ($flash): ?>
    <div class="flash-message flash-<?= h($flash['type']) ?>" id="flashMessage">
        <div class="flash-inner">
            <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <span><?= h($flash['message']) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <div class="auth-container animate-fade">
        <div class="auth-logo">☯</div>
        <div class="auth-card">
            <h1 class="auth-title">로그인</h1>
            <p class="auth-subtitle">계정에 로그인하세요</p>
            
            <?php if (!empty($errors)): ?>
            <div style="background: #FFEBEE; color: #C62828; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 0.85rem;">
                <?php foreach ($errors as $error): ?>
                    <p>• <?= h($error) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <div class="form-group">
                    <label class="form-label">이메일</label>
                    <input type="email" name="email" class="form-input" placeholder="이메일을 입력하세요" 
                           value="<?= h($email) ?>" required autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label">비밀번호</label>
                    <input type="password" name="password" class="form-input" placeholder="비밀번호를 입력하세요" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 8px;">로그인</button>
            </form>
            
            <div class="auth-links">
                <a href="<?= SITE_URL ?>/auth/register.php">회원가입</a>
                <span class="auth-divider">|</span>
                <a href="<?= SITE_URL ?>/auth/forgot_password.php">비밀번호 찾기</a>
            </div>
            
            <!-- 테스트 계정 안내 -->
            <div style="margin-top: 24px; padding: 14px; background: #F5F5F7; border-radius: 10px; font-size: 0.8rem; color: var(--text-secondary);">
                <p style="font-weight: 600; margin-bottom: 6px;">테스트 계정</p>
                <p>관리자: admin@saju.com / admin1234</p>
                <p>사용자: user@saju.com / user1234</p>
            </div>
        </div>
    </div>
    
    <script>
        setTimeout(() => {
            const flash = document.getElementById('flashMessage');
            if (flash) {
                flash.style.opacity = '0';
                setTimeout(() => flash.remove(), 300);
            }
        }, 3000);
    </script>
</body>
</html>
