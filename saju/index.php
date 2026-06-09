<?php
/**
 * 사주포춘 - 메인 라우터
 * /saju/ 접속 시 로그인 상태에 따라 분기
 */
session_start();

// 설치 확인 - install.lock 파일 또는 DB 존재 여부로 판단
$installed = false;
if (file_exists(__DIR__ . '/install.lock')) {
    $installed = true;
} else {
    // DB 연결 시도로 설치 여부 확인
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=saju_db', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $stmt = $pdo->query("SHOW TABLES LIKE 'saju_users'");
        if ($stmt->rowCount() > 0) {
            $installed = true;
            // lock 파일이 없으면 생성 시도
            @file_put_contents(__DIR__ . '/install.lock', date('Y-m-d H:i:s'));
        }
    } catch (Exception $e) {
        // DB 없음 = 미설치
    }
}

if (!$installed) {
    header('Location: /saju/install.php');
    exit;
}

// 로그인 상태 확인
if (isset($_SESSION['user_id'])) {
    header('Location: /saju/pages/home.php');
} else {
    header('Location: /saju/auth/login.php');
}
exit;
