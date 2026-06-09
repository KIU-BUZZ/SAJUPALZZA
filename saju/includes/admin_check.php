<?php
/**
 * 관리자 권한 확인 미들웨어
 * 관리자 페이지에서 include
 */
require_once __DIR__ . '/../config/config.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}

if (!isAdmin()) {
    setFlashMessage('error', '관리자 권한이 필요합니다.');
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}
