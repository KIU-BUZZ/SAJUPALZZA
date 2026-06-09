<?php
/**
 * 사이트 전역 설정 파일
 */

// 세션 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 사이트 기본 설정
define('SITE_NAME', '사주포춘');
define('SITE_URL', '/saju');
define('SITE_VERSION', '1.0.0');

// 경로 설정
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('SAJU_ENGINE_PATH', BASE_PATH . '/saju');
define('PAGES_PATH', BASE_PATH . '/pages');
define('ADMIN_PATH', BASE_PATH . '/admin');
define('ASSETS_PATH', BASE_PATH . '/assets');

// 기본 티켓 수 (회원가입 시 지급)
define('DEFAULT_TICKETS', 3);

// 무료 기능 목록
define('FREE_FEATURES', ['basic_saju', 'ohang']);

// 유료 기능 (티켓 필요)
define('PREMIUM_FEATURES', [
    'sipsin'      => ['name' => '십신 분석', 'tickets' => 1, 'icon' => 'fa-yin-yang'],
    'gyeokguk'    => ['name' => '격국 분석', 'tickets' => 1, 'icon' => 'fa-chess-queen'],
    'daeun'       => ['name' => '대운 분석', 'tickets' => 1, 'icon' => 'fa-road'],
    'seun'        => ['name' => '세운 분석', 'tickets' => 1, 'icon' => 'fa-calendar-alt'],
    'love'        => ['name' => '연애운 분석', 'tickets' => 1, 'icon' => 'fa-heart'],
    'career'      => ['name' => '직업운 분석', 'tickets' => 1, 'icon' => 'fa-briefcase'],
    'wealth'      => ['name' => '재물운 분석', 'tickets' => 1, 'icon' => 'fa-coins'],
    'comprehensive' => ['name' => '인생 종합 분석', 'tickets' => 2, 'icon' => 'fa-star'],
]);

// 데이터베이스 설정 로드
require_once CONFIG_PATH . '/database.php';

// 공통 함수 로드
require_once INCLUDES_PATH . '/functions.php';

// 타임존 설정
date_default_timezone_set('Asia/Seoul');

// 에러 표시 (개발 환경)
error_reporting(E_ALL);
ini_set('display_errors', 1);
