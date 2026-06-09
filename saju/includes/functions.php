<?php
/**
 * 공통 유틸리티 함수 모음
 */

/**
 * 현재 로그인 상태 확인
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * 현재 로그인한 사용자 정보 조회
 * @return array|null
 */
function getCurrentUser($forceRefresh = false) {
    static $cachedUserLoaded = false;
    static $cachedUser = null;

    if (!isLoggedIn()) return null;

    if (!$forceRefresh && $cachedUserLoaded) {
        return $cachedUser;
    }

    $fetchUser = function($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM saju_users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    };

    try {
        $cachedUser = $fetchUser(getDBConnection());
    } catch (PDOException $e) {
        if (!isConnectionLostPDOException($e)) {
            throw $e;
        }
        $cachedUser = $fetchUser(getDBConnection(true));
    }

    $cachedUserLoaded = true;
    return $cachedUser;
}

/**
 * 관리자 여부 확인
 * @return bool
 */
function isAdmin() {
    if (!isLoggedIn()) return false;
    $user = getCurrentUser();
    return $user && $user['role'] === 'admin';
}

/**
 * CSRF 토큰 생성
 * @return string
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF 토큰 검증
 * @param string $token
 * @return bool
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 안전한 출력 (XSS 방지)
 * @param string $str
 * @return string
 */
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * 사용자 티켓 수 조회
 * @param int $userId
 * @return int
 */
function getUserTickets($userId) {
    $fetchTickets = function($pdo) use ($userId) {
        $stmt = $pdo->prepare("SELECT tickets FROM saju_users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ? (int)$result['tickets'] : 0;
    };

    try {
        return $fetchTickets(getDBConnection());
    } catch (PDOException $e) {
        if (!isConnectionLostPDOException($e)) {
            throw $e;
        }
        return $fetchTickets(getDBConnection(true));
    }
}

/**
 * 티켓 차감
 * @param int $userId
 * @param int $amount
 * @param string $description
 * @return bool
 */
function useTickets($userId, $amount, $description = '') {
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $pdo = getDBConnection($attempt === 1);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT tickets FROM saju_users WHERE id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $currentTickets = (int)($stmt->fetch()['tickets'] ?? 0);
            if ($currentTickets < $amount) {
                $pdo->rollBack();
                return false;
            }

            $stmt = $pdo->prepare("UPDATE saju_users SET tickets = tickets - ? WHERE id = ? AND tickets >= ?");
            $stmt->execute([$amount, $userId, $amount]);

            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                return false;
            }

            $stmt = $pdo->prepare("INSERT INTO saju_ticket_logs (user_id, action, amount, reason, created_at) VALUES (?, 'use', ?, ?, NOW())");
            $stmt->execute([$userId, $amount, $description]);

            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($attempt === 0 && isConnectionLostPDOException($e)) {
                continue;
            }
            return false;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    return false;
}

/**
 * 티켓 추가
 * @param int $userId
 * @param int $amount
 * @param string $reason
 * @param int|null $adminId
 * @return bool
 */
function addTickets($userId, $amount, $reason = '', $adminId = null) {
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $pdo = getDBConnection($attempt === 1);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE saju_users SET tickets = tickets + ? WHERE id = ?");
            $stmt->execute([$amount, $userId]);

            $stmt = $pdo->prepare("INSERT INTO saju_ticket_logs (user_id, admin_id, action, amount, reason, created_at) VALUES (?, ?, 'add', ?, ?, NOW())");
            $stmt->execute([$userId, $adminId, $amount, $reason]);

            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($attempt === 0 && isConnectionLostPDOException($e)) {
                continue;
            }
            return false;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    return false;
}

/**
 * 랜덤 닉네임 생성
 * @return string
 */
function generateNickname() {
    $adjectives = ['행복한', '밝은', '지혜로운', '용감한', '따뜻한', '빛나는', '상냥한', '씩씩한', '멋진', '귀여운'];
    $nouns = ['별', '달', '해', '구름', '바람', '나무', '꽃', '산', '바다', '하늘'];
    $number = rand(100, 999);
    
    return $adjectives[array_rand($adjectives)] . $nouns[array_rand($nouns)] . $number;
}

/**
 * 날짜 포맷 변환
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'Y년 m월 d일') {
    return date($format, strtotime($date));
}

/**
 * 상대적 시간 표시
 * @param string $date
 * @return string
 */
function timeAgo($date) {
    $timestamp = strtotime($date);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return '방금 전';
    if ($diff < 3600) return floor($diff / 60) . '분 전';
    if ($diff < 86400) return floor($diff / 3600) . '시간 전';
    if ($diff < 604800) return floor($diff / 86400) . '일 전';
    if ($diff < 2592000) return floor($diff / 604800) . '주 전';
    
    return formatDate($date);
}

/**
 * 플래시 메시지 설정
 * @param string $type (success, error, warning, info)
 * @param string $message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * 플래시 메시지 가져오기 (한 번만 표시)
 * @return array|null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * 리다이렉트
 * @param string $url
 */
function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit;
}

/**
 * 현재 사이트의 절대 Origin 반환
 * @return string
 */
function getSiteOrigin() {
    $forwardedProto = trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwardedProto !== '') {
        $scheme = strtolower(trim(explode(',', $forwardedProto)[0]));
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if ($host === '') {
        $host = 'localhost';
    }

    return $scheme . '://' . $host;
}

/**
 * SITE_URL 기준 절대 경로 생성
 * @param string $path
 * @return string
 */
function getAbsoluteSiteUrl($path = '') {
    $sitePath = trim((string)SITE_URL, '/');
    $base = rtrim(getSiteOrigin(), '/');

    if ($sitePath !== '') {
        $base .= '/' . $sitePath;
    }

    $path = trim((string)$path);
    if ($path === '' || $path === '/') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}

/**
 * JSON 응답 반환
 * @param array $data
 * @param int $statusCode
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 입력값 정리 (XSS 방지)
 * @param string $input
 * @return string
 */
function sanitize($input) {
    return trim(htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8'));
}

/**
 * 이메일 유효성 검사
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * 비밀번호 강도 검사
 * @param string $password
 * @return array ['valid' => bool, 'message' => string]
 */
function validatePassword($password) {
    if (strlen($password) < 8) {
        return ['valid' => false, 'message' => '비밀번호는 8자 이상이어야 합니다.'];
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        return ['valid' => false, 'message' => '영문을 포함해야 합니다.'];
    }
    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'message' => '숫자를 포함해야 합니다.'];
    }
    return ['valid' => true, 'message' => ''];
}
