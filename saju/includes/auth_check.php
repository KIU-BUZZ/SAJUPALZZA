<?php
/**
 * 인증 확인 미들웨어
 * 로그인이 필요한 페이지에서 include
 */
require_once __DIR__ . '/../config/config.php';

if (!isLoggedIn()) {
    setFlashMessage('warning', '로그인이 필요한 서비스입니다.');
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}

// 세션에 user_id가 있지만 DB에 없는 경우 (삭제된 계정 등) 세션 초기화
$_currentUser = getCurrentUser();
if (!$_currentUser) {
    session_destroy();
    session_start();
    setFlashMessage('warning', '세션이 만료되었습니다. 다시 로그인해 주세요.');
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}
