<?php
/**
 * 데이터베이스 설정 파일
 * XAMPP MySQL 연결 설정
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'saju_db');
define('DB_CHARSET', 'utf8mb4');

/**
 * 내부 PDO 생성 함수
 * @return PDO
 */
function createPDOConnection() {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    return new PDO($dsn, DB_USER, DB_PASS, $options);
}

/**
 * MySQL 연결 끊김 예외 여부 확인
 * @param Throwable $e
 * @return bool
 */
function isConnectionLostPDOException($e) {
    if (!$e instanceof Throwable) return false;

    $message = $e->getMessage();
    $code = (string)$e->getCode();

    return $code === '2006'
        || $code === '2013'
        || strpos($message, 'MySQL server has gone away') !== false
        || strpos($message, 'Lost connection to MySQL server') !== false
        || strpos($message, 'Error while sending') !== false;
}

/**
 * PDO 데이터베이스 연결 함수
 * @param bool $forceReconnect
 * @return PDO
 */
function getDBConnection($forceReconnect = false) {
    static $pdo = null;

    if ($forceReconnect) {
        $pdo = null;
    }

    if ($pdo === null) {
        try {
            $pdo = createPDOConnection();
        } catch (PDOException $e) {
            // 설치 페이지로 리다이렉트
            if (strpos($_SERVER['REQUEST_URI'], 'install.php') === false) {
                header('Location: /saju/install.php');
                exit;
            }
            throw $e;
        }
    } else {
        try {
            $pdo->query('SELECT 1');
        } catch (PDOException $e) {
            if (!isConnectionLostPDOException($e)) {
                throw $e;
            }
            $pdo = createPDOConnection();
        }
    }

    return $pdo;
}

/**
 * 데이터베이스 연결 (설치 시 DB 없이 연결)
 * @return PDO
 */
function getDBConnectionWithoutDB() {
    $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES    => false,
    ];
    return new PDO($dsn, DB_USER, DB_PASS, $options);
}
