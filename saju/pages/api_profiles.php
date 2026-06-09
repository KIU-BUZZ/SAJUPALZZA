<?php
/**
 * 사주 프로필 관리 API
 * AJAX 요청으로 프로필 CRUD 처리
 */
require_once __DIR__ . '/../config/config.php';

// 로그인 확인
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => '로그인이 필요합니다.'], 401);
}

$user = getCurrentUser();
$pdo = getDBConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ─── 프로필 목록 조회 ───
    case 'list':
        $stmt = $pdo->prepare("
            SELECT id, profile_name, birth_year, birth_month, birth_day, birth_hour, 
                   gender, calendar_type, is_default, created_at
            FROM saju_profiles 
            WHERE user_id = ? 
            ORDER BY is_default DESC, created_at ASC
        ");
        $stmt->execute([$user['id']]);
        $profiles = $stmt->fetchAll();
        
        jsonResponse(['success' => true, 'profiles' => $profiles]);
        break;

    // ─── 프로필 추가 ───
    case 'add':
        $name = trim($_POST['profile_name'] ?? '');
        $birthYear = (int)($_POST['birth_year'] ?? 0);
        $birthMonth = (int)($_POST['birth_month'] ?? 0);
        $birthDay = (int)($_POST['birth_day'] ?? 0);
        $birthHour = $_POST['birth_hour'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $calendarType = $_POST['calendar_type'] ?? 'solar';
        $isDefault = (int)($_POST['is_default'] ?? 0);

        // 검증
        $errors = [];
        if (empty($name)) $errors[] = '프로필 이름을 입력해 주세요.';
        if (mb_strlen($name) > 50) $errors[] = '프로필 이름은 50자 이내로 입력해 주세요.';
        if ($birthYear < 1900 || $birthYear > (int)date('Y')) $errors[] = '올바른 출생 연도를 입력해 주세요.';
        if ($birthMonth < 1 || $birthMonth > 12) $errors[] = '올바른 출생 월을 선택해 주세요.';
        if ($birthDay < 1 || $birthDay > 31) $errors[] = '올바른 출생 일을 입력해 주세요.';
        if (!in_array($gender, ['male', 'female'])) $errors[] = '성별을 선택해 주세요.';
        
        // 최대 10개 제한
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM saju_profiles WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        if ($stmt->fetchColumn() >= 10) {
            $errors[] = '프로필은 최대 10개까지 등록할 수 있습니다.';
        }

        if (!empty($errors)) {
            jsonResponse(['success' => false, 'message' => implode(' ', $errors)]);
        }

        $birthHourVal = ($birthHour !== '' && $birthHour !== '-1') ? (int)$birthHour : null;

        try {
            $pdo->beginTransaction();
            
            // 기본 프로필로 설정 시, 기존 기본 해제
            if ($isDefault) {
                $pdo->prepare("UPDATE saju_profiles SET is_default = 0 WHERE user_id = ?")->execute([$user['id']]);
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO saju_profiles (user_id, profile_name, birth_year, birth_month, birth_day, birth_hour, gender, calendar_type, is_default, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user['id'], $name, $birthYear, $birthMonth, $birthDay,
                $birthHourVal, $gender, $calendarType, $isDefault
            ]);
            
            $profileId = $pdo->lastInsertId();
            $pdo->commit();
            
            jsonResponse(['success' => true, 'message' => '프로필이 추가되었습니다.', 'profile_id' => $profileId]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => '프로필 추가 중 오류가 발생했습니다.']);
        }
        break;

    // ─── 프로필 수정 ───
    case 'update':
        $profileId = (int)($_POST['profile_id'] ?? 0);
        $name = trim($_POST['profile_name'] ?? '');
        $birthYear = (int)($_POST['birth_year'] ?? 0);
        $birthMonth = (int)($_POST['birth_month'] ?? 0);
        $birthDay = (int)($_POST['birth_day'] ?? 0);
        $birthHour = $_POST['birth_hour'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $calendarType = $_POST['calendar_type'] ?? 'solar';
        $isDefault = (int)($_POST['is_default'] ?? 0);

        // 소유권 확인
        $stmt = $pdo->prepare("SELECT id FROM saju_profiles WHERE id = ? AND user_id = ?");
        $stmt->execute([$profileId, $user['id']]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => '프로필을 찾을 수 없습니다.']);
        }

        // 검증
        $errors = [];
        if (empty($name)) $errors[] = '프로필 이름을 입력해 주세요.';
        if ($birthYear < 1900 || $birthYear > (int)date('Y')) $errors[] = '올바른 출생 연도를 입력해 주세요.';
        if ($birthMonth < 1 || $birthMonth > 12) $errors[] = '올바른 출생 월을 선택해 주세요.';
        if ($birthDay < 1 || $birthDay > 31) $errors[] = '올바른 출생 일을 입력해 주세요.';
        if (!in_array($gender, ['male', 'female'])) $errors[] = '성별을 선택해 주세요.';

        if (!empty($errors)) {
            jsonResponse(['success' => false, 'message' => implode(' ', $errors)]);
        }

        $birthHourVal = ($birthHour !== '' && $birthHour !== '-1') ? (int)$birthHour : null;

        try {
            $pdo->beginTransaction();
            
            if ($isDefault) {
                $pdo->prepare("UPDATE saju_profiles SET is_default = 0 WHERE user_id = ?")->execute([$user['id']]);
            }
            
            $stmt = $pdo->prepare("
                UPDATE saju_profiles 
                SET profile_name = ?, birth_year = ?, birth_month = ?, birth_day = ?, 
                    birth_hour = ?, gender = ?, calendar_type = ?, is_default = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $name, $birthYear, $birthMonth, $birthDay,
                $birthHourVal, $gender, $calendarType, $isDefault,
                $profileId, $user['id']
            ]);
            
            $pdo->commit();
            jsonResponse(['success' => true, 'message' => '프로필이 수정되었습니다.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => '프로필 수정 중 오류가 발생했습니다.']);
        }
        break;

    // ─── 프로필 삭제 ───
    case 'delete':
        $profileId = (int)($_POST['profile_id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT id FROM saju_profiles WHERE id = ? AND user_id = ?");
        $stmt->execute([$profileId, $user['id']]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => '프로필을 찾을 수 없습니다.']);
        }

        $stmt = $pdo->prepare("DELETE FROM saju_profiles WHERE id = ? AND user_id = ?");
        $stmt->execute([$profileId, $user['id']]);
        
        jsonResponse(['success' => true, 'message' => '프로필이 삭제되었습니다.']);
        break;

    // ─── 기본 프로필 설정 ───
    case 'set_default':
        $profileId = (int)($_POST['profile_id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT id FROM saju_profiles WHERE id = ? AND user_id = ?");
        $stmt->execute([$profileId, $user['id']]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => '프로필을 찾을 수 없습니다.']);
        }

        $pdo->prepare("UPDATE saju_profiles SET is_default = 0 WHERE user_id = ?")->execute([$user['id']]);
        $pdo->prepare("UPDATE saju_profiles SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$profileId, $user['id']]);
        
        jsonResponse(['success' => true, 'message' => '기본 프로필이 변경되었습니다.']);
        break;

    // ─── 단일 프로필 조회 ───
    case 'get':
        $profileId = (int)($_GET['profile_id'] ?? $_POST['profile_id'] ?? 0);
        
        $stmt = $pdo->prepare("
            SELECT id, profile_name, birth_year, birth_month, birth_day, birth_hour, 
                   gender, calendar_type, is_default
            FROM saju_profiles 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$profileId, $user['id']]);
        $profile = $stmt->fetch();
        
        if (!$profile) {
            jsonResponse(['success' => false, 'message' => '프로필을 찾을 수 없습니다.']);
        }
        
        jsonResponse(['success' => true, 'profile' => $profile]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => '잘못된 요청입니다.'], 400);
}
