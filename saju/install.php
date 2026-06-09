<?php
/**
 * 사주포춘 - 데이터베이스 설치 스크립트
 * 브라우저에서 /saju/install.php 접속하여 실행
 */

// 설치 완료 후 이 파일을 삭제하거나 접근 차단하세요
$installLock = __DIR__ . '/install.lock';

$message = '';
$success = false;
$step = $_GET['step'] ?? 'check';

// DB 접속 설정
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'saju_db';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        // 1. DB 생성
        $pdo = new PDO("mysql:host={$dbHost}", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");
        
        // 2. 테이블 생성
        $sql = "
        -- 회원 테이블
        CREATE TABLE IF NOT EXISTS `saju_users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `nickname` VARCHAR(50) NOT NULL,
            `phone` VARCHAR(20) DEFAULT NULL,
            `tickets` INT DEFAULT 3 COMMENT '보유 티켓 수',
            `terms_agreed` TINYINT(1) DEFAULT 0,
            `marketing_agreed` TINYINT(1) DEFAULT 0,
            `role` ENUM('user', 'admin') DEFAULT 'user',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_email` (`email`),
            INDEX `idx_role` (`role`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        -- 사주 분석 기록 테이블
        CREATE TABLE IF NOT EXISTS `saju_fortune_history` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `birth_year` INT NOT NULL,
            `birth_month` INT NOT NULL,
            `birth_day` INT NOT NULL,
            `birth_hour` INT DEFAULT NULL COMMENT '태어난 시 (0-23, NULL=모름)',
            `gender` ENUM('male', 'female') NOT NULL,
            `calendar_type` ENUM('solar', 'lunar') DEFAULT 'solar',
            `year_pillar` VARCHAR(10) DEFAULT NULL COMMENT '년주 (예: 甲子)',
            `month_pillar` VARCHAR(10) DEFAULT NULL COMMENT '월주',
            `day_pillar` VARCHAR(10) DEFAULT NULL COMMENT '일주',
            `hour_pillar` VARCHAR(10) DEFAULT NULL COMMENT '시주',
            `ohang_analysis` JSON DEFAULT NULL COMMENT '오행 분석 결과',
            `sipsin_analysis` JSON DEFAULT NULL COMMENT '십신 분석 결과',
            `gyeokguk_analysis` JSON DEFAULT NULL COMMENT '격국 분석 결과',
            `daeun_analysis` JSON DEFAULT NULL COMMENT '대운 분석 결과',
            `seun_analysis` JSON DEFAULT NULL COMMENT '세운 분석 결과',
            `fortune_result` JSON DEFAULT NULL COMMENT '종합 운세 결과',
            `analysis_type` VARCHAR(50) NOT NULL DEFAULT 'basic_saju' COMMENT '분석 유형',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user` (`user_id`),
            INDEX `idx_type` (`analysis_type`),
            INDEX `idx_created` (`created_at`),
            FOREIGN KEY (`user_id`) REFERENCES `saju_users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        -- 티켓 로그 테이블
        CREATE TABLE IF NOT EXISTS `saju_ticket_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `admin_id` INT DEFAULT NULL COMMENT '관리자 지급 시 관리자 ID',
            `action` ENUM('add', 'use') NOT NULL,
            `amount` INT NOT NULL,
            `reason` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user` (`user_id`),
            INDEX `idx_action` (`action`),
            INDEX `idx_created` (`created_at`),
            FOREIGN KEY (`user_id`) REFERENCES `saju_users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        -- 사주 프로필 테이블 (다중 프로필 지원)
        CREATE TABLE IF NOT EXISTS `saju_profiles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `profile_name` VARCHAR(50) NOT NULL COMMENT '프로필 이름 (본인, 아버지 등)',
            `birth_year` INT NOT NULL,
            `birth_month` INT NOT NULL,
            `birth_day` INT NOT NULL,
            `birth_hour` INT DEFAULT NULL COMMENT '태어난 시 (0-23, NULL=모름)',
            `gender` ENUM('male', 'female') NOT NULL,
            `calendar_type` ENUM('solar', 'lunar') DEFAULT 'solar',
            `is_default` TINYINT(1) DEFAULT 0 COMMENT '기본 프로필 여부',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_user` (`user_id`),
            INDEX `idx_default` (`user_id`, `is_default`),
            FOREIGN KEY (`user_id`) REFERENCES `saju_users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        // 각 CREATE TABLE 실행
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($s) { return !empty($s) && stripos($s, 'CREATE') !== false; }
        );
        
        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
        }
        
        // 3. 테스트 데이터 삽입
        // 관리자 계정
        $adminPwd = password_hash('admin1234', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO saju_users (email, password, nickname, phone, tickets, terms_agreed, role) 
            VALUES (?, ?, ?, ?, ?, 1, 'admin')
        ");
        $stmt->execute(['admin@saju.com', $adminPwd, '관리자', '010-0000-0000', 99]);
        
        // 일반 회원
        $userPwd = password_hash('user1234', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO saju_users (email, password, nickname, phone, tickets, terms_agreed, role) 
            VALUES (?, ?, ?, ?, ?, 1, 'user')
        ");
        $stmt->execute(['user@saju.com', $userPwd, '테스트사용자', '010-1234-5678', 10]);
        
        // 티켓 로그 샘플 (중복 방지)
        $stmt = $pdo->query("SELECT COUNT(*) FROM saju_ticket_logs");
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("
                INSERT INTO saju_ticket_logs (user_id, action, amount, reason) 
                VALUES (1, 'add', 99, '관리자 초기 지급'),
                       (2, 'add', 10, '회원가입 보너스')
            ");
            $stmt->execute();
        }
        
        // 설치 완료 잠금 파일 생성
        $lockResult = @file_put_contents($installLock, date('Y-m-d H:i:s'));
        if ($lockResult === false) {
            // 권한 문제로 lock 파일 생성 실패 시에도 설치는 완료
            // index.php에서 DB 확인으로 대체
        }
        
        $success = true;
        $message = '데이터베이스 설치가 완료되었습니다!';
        if ($lockResult === false) {
            $message .= ' (lock 파일 생성은 실패했으나 정상 작동합니다)';
        }
        
    } catch (PDOException $e) {
        $message = '설치 오류: ' . $e->getMessage();
    }
}

// 이미 설치된 경우
$alreadyInstalled = file_exists($installLock);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>사주포춘 - 설치</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Noto Sans KR', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            color: #333;
        }
        .install-box {
            background: #fff; border-radius: 20px; padding: 2.5rem;
            max-width: 600px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .install-box h1 { text-align: center; margin-bottom: 0.5rem; color: #1a1a2e; font-size: 1.5rem; }
        .install-box .subtitle { text-align: center; color: #7f8c8d; font-size: 0.9rem; margin-bottom: 2rem; }
        .check-list { list-style: none; padding: 0; margin-bottom: 2rem; }
        .check-list li {
            padding: 0.75rem 1rem; border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; gap: 0.75rem;
        }
        .check-list li .icon { font-size: 1.2rem; }
        .check-list li .ok { color: #27ae60; }
        .check-list li .fail { color: #e74c3c; }
        .check-list li .label { flex: 1; font-size: 0.9rem; }
        .check-list li .status { font-size: 0.8rem; font-weight: 500; }
        .btn-install {
            width: 100%; padding: 1rem; font-size: 1.1rem; font-weight: 600;
            background: linear-gradient(135deg, #9b59b6, #e74c3c);
            color: #fff; border: none; border-radius: 12px; cursor: pointer;
            transition: 0.3s; font-family: inherit;
        }
        .btn-install:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(155,89,182,0.3); }
        .btn-install:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .info-box {
            background: #f8f9fa; border-radius: 10px; padding: 1rem;
            margin-top: 1.5rem; font-size: 0.85rem;
        }
        .info-box h4 { margin-bottom: 0.5rem; color: #2c3e50; }
        .info-box p { color: #7f8c8d; line-height: 1.6; }
        .info-box code { background: #e8e8e8; padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.8rem; }
        a.link-btn {
            display: block; text-align: center; padding: 0.75rem; margin-top: 1rem;
            color: #9b59b6; text-decoration: none; font-weight: 500; font-size: 0.95rem;
        }
    </style>
</head>
<body>
<div class="install-box">
    <h1>🔮 사주포춘 설치</h1>
    <p class="subtitle">사주팔자 분석 웹 서비스</p>
    
    <?php if ($success): ?>
    <div class="alert alert-success">
        ✅ <?= $message ?>
    </div>
    
    <div class="info-box">
        <h4>테스트 계정</h4>
        <p>
            <strong>관리자:</strong> admin@saju.com / admin1234<br>
            <strong>일반회원:</strong> user@saju.com / user1234
        </p>
    </div>
    
    <a href="/saju/" class="link-btn">🏠 사이트로 이동 →</a>
    
    <?php elseif ($alreadyInstalled): ?>
    <div class="alert alert-success">
        ✅ 이미 설치가 완료되었습니다. (<?= file_get_contents($installLock) ?>)
    </div>
    <a href="/saju/" class="link-btn">🏠 사이트로 이동 →</a>
    
    <form method="POST" style="margin-top:1rem;">
        <input type="hidden" name="install" value="1">
        <button type="submit" class="btn-install" style="background:#e74c3c;" onclick="return confirm('기존 데이터를 유지하며 재설치합니다. 계속하시겠습니까?')">
            🔄 재설치
        </button>
    </form>
    
    <?php elseif (!empty($message)): ?>
    <div class="alert alert-error">
        ❌ <?= htmlspecialchars($message) ?>
    </div>
    
    <?php else: ?>
    
    <?php
    // 환경 체크
    $checks = [];
    $checks['php'] = ['label' => 'PHP 7.4+', 'ok' => version_compare(PHP_VERSION, '7.4.0', '>='), 'detail' => 'v' . PHP_VERSION];
    $checks['pdo'] = ['label' => 'PDO MySQL', 'ok' => extension_loaded('pdo_mysql'), 'detail' => extension_loaded('pdo_mysql') ? '활성화' : '비활성화'];
    $checks['json'] = ['label' => 'JSON 확장', 'ok' => extension_loaded('json'), 'detail' => extension_loaded('json') ? '활성화' : '비활성화'];
    $checks['mbstring'] = ['label' => 'mbstring 확장', 'ok' => extension_loaded('mbstring'), 'detail' => extension_loaded('mbstring') ? '활성화' : '비활성화'];
    
    // MySQL 접속 체크
    try {
        $testPdo = new PDO("mysql:host={$dbHost}", $dbUser, $dbPass);
        $checks['mysql'] = ['label' => 'MySQL 접속', 'ok' => true, 'detail' => '정상'];
    } catch (Exception $e) {
        $checks['mysql'] = ['label' => 'MySQL 접속', 'ok' => false, 'detail' => '실패'];
    }
    
    $allOk = array_reduce($checks, function($carry, $item) { return $carry && $item['ok']; }, true);
    ?>
    
    <ul class="check-list">
        <?php foreach ($checks as $check): ?>
        <li>
            <span class="icon <?= $check['ok'] ? 'ok' : 'fail' ?>"><?= $check['ok'] ? '✅' : '❌' ?></span>
            <span class="label"><?= $check['label'] ?></span>
            <span class="status" style="color:<?= $check['ok']?'#27ae60':'#e74c3c' ?>"><?= $check['detail'] ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    
    <form method="POST">
        <input type="hidden" name="install" value="1">
        <button type="submit" class="btn-install" <?= !$allOk ? 'disabled' : '' ?>>
            <?= $allOk ? '🚀 설치 시작' : '❌ 환경 요구사항 미충족' ?>
        </button>
    </form>
    
    <div class="info-box">
        <h4>설치 정보</h4>
        <p>
            데이터베이스: <code><?= $dbName ?></code><br>
            테이블 접두사: <code>saju_</code><br>
            생성 테이블: <code>saju_users</code>, <code>saju_fortune_history</code>, <code>saju_ticket_logs</code>, <code>saju_profiles</code>
        </p>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
