<?php
/**
 * 비밀번호 찾기 페이지
 */
require_once __DIR__ . '/../config/config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !isValidEmail($email)) {
        $message = '올바른 이메일을 입력해 주세요.';
        $messageType = 'error';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM saju_users WHERE email = ?");
        $stmt->execute([$email]);
        
        // 보안상 존재 여부와 관계없이 동일한 메시지 표시
        $message = '해당 이메일로 비밀번호 재설정 안내를 발송했습니다. 이메일을 확인해 주세요.';
        $messageType = 'success';
    }
}

$pageTitle = '비밀번호 찾기 - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body style="padding-top: 0; padding-bottom: 0;">
    <div class="auth-container animate-fade">
        <div class="auth-logo">☯</div>
        <div class="auth-card">
            <h1 class="auth-title">비밀번호 찾기</h1>
            <p class="auth-subtitle">가입한 이메일을 입력하면<br>비밀번호 재설정 메일을 보내드립니다</p>
            
            <?php if ($message): ?>
            <div style="background: <?= $messageType === 'success' ? '#E8F5E9' : '#FFEBEE' ?>; color: <?= $messageType === 'success' ? '#2E7D32' : '#C62828' ?>; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 0.85rem;">
                <p><?= h($message) ?></p>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">이메일</label>
                    <input type="email" name="email" class="form-input" placeholder="가입한 이메일 주소" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg">비밀번호 재설정 요청</button>
            </form>
            
            <div class="auth-links">
                <a href="<?= SITE_URL ?>/auth/login.php">← 로그인으로 돌아가기</a>
            </div>
        </div>
    </div>
</body>
</html>
