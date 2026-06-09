<?php
/**
 * 프로필 CRUD 통합 테스트
 */
require_once __DIR__ . '/config/config.php';

$pdo = getDBConnection();
$passed = 0;
$failed = 0;

function test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] {$name}\n";
        $passed++;
    } else {
        echo "[FAIL] {$name}\n";
        $failed++;
    }
}

echo "=== saju_profiles 테이블 확인 ===\n";
$stmt = $pdo->query('DESCRIBE saju_profiles');
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "컬럼: " . implode(', ', $cols) . "\n";
test('테이블 컬럼 수', count($cols) === 12);

echo "\n=== 프로필 CRUD 기능 테스트 ===\n";
$userId = 2;

// 1) 추가
$stmt = $pdo->prepare('INSERT INTO saju_profiles (user_id, profile_name, birth_year, birth_month, birth_day, birth_hour, gender, calendar_type, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$userId, '테스트프로필', 1990, 5, 15, 9, 'male', 'solar', 0]);
$testId = $pdo->lastInsertId();
test('프로필 추가', $testId > 0);

// 2) 조회
$stmt = $pdo->prepare('SELECT * FROM saju_profiles WHERE id = ? AND user_id = ?');
$stmt->execute([$testId, $userId]);
$p = $stmt->fetch();
test('프로필 조회', $p && $p['profile_name'] === '테스트프로필');
test('생년월일 확인', $p['birth_year'] == 1990 && $p['birth_month'] == 5 && $p['birth_day'] == 15);
test('시간/성별/역법', $p['birth_hour'] == 9 && $p['gender'] === 'male' && $p['calendar_type'] === 'solar');

// 3) 수정
$stmt = $pdo->prepare('UPDATE saju_profiles SET profile_name = ?, birth_year = ? WHERE id = ? AND user_id = ?');
$stmt->execute(['수정프로필', 1995, $testId, $userId]);
$stmt = $pdo->prepare('SELECT profile_name, birth_year FROM saju_profiles WHERE id = ?');
$stmt->execute([$testId]);
$p2 = $stmt->fetch();
test('프로필 수정', $p2['profile_name'] === '수정프로필' && $p2['birth_year'] == 1995);

// 4) 기본 설정
$pdo->prepare('UPDATE saju_profiles SET is_default = 1 WHERE id = ? AND user_id = ?')->execute([$testId, $userId]);
$stmt = $pdo->prepare('SELECT is_default FROM saju_profiles WHERE id = ?');
$stmt->execute([$testId]);
test('기본 프로필 설정', $stmt->fetch()['is_default'] == 1);

// 5) 목록
$stmt = $pdo->prepare('SELECT COUNT(*) FROM saju_profiles WHERE user_id = ?');
$stmt->execute([$userId]);
$cnt = $stmt->fetchColumn();
test('프로필 목록', $cnt >= 1);

// 6) NULL 시간 테스트
$stmt = $pdo->prepare('INSERT INTO saju_profiles (user_id, profile_name, birth_year, birth_month, birth_day, birth_hour, gender, calendar_type, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$userId, '시간모름', 2000, 1, 1, null, 'female', 'lunar', 0]);
$nullId = $pdo->lastInsertId();
$stmt = $pdo->prepare('SELECT birth_hour FROM saju_profiles WHERE id = ?');
$stmt->execute([$nullId]);
test('NULL 시간 허용', $stmt->fetch()['birth_hour'] === null);

// 7) 삭제
$pdo->prepare('DELETE FROM saju_profiles WHERE id = ? AND user_id = ?')->execute([$testId, $userId]);
$pdo->prepare('DELETE FROM saju_profiles WHERE id = ? AND user_id = ?')->execute([$nullId, $userId]);
$stmt = $pdo->prepare('SELECT COUNT(*) FROM saju_profiles WHERE id IN (?, ?)');
$stmt->execute([$testId, $nullId]);
test('프로필 삭제', $stmt->fetchColumn() == 0);

// 8) 최대 10개 제한 테스트
echo "\n=== 제한 테스트 ===\n";
$ids = [];
for ($i = 0; $i < 10; $i++) {
    $stmt = $pdo->prepare('INSERT INTO saju_profiles (user_id, profile_name, birth_year, birth_month, birth_day, gender) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, "프로필{$i}", 1990 + $i, 1, 1, 'male']);
    $ids[] = $pdo->lastInsertId();
}
$stmt = $pdo->prepare('SELECT COUNT(*) FROM saju_profiles WHERE user_id = ?');
$stmt->execute([$userId]);
test('10개 프로필 생성', $stmt->fetchColumn() == 10);

// 정리
foreach ($ids as $id) {
    $pdo->prepare('DELETE FROM saju_profiles WHERE id = ?')->execute([$id]);
}

echo "\n=== 결과 ===\n";
echo "통과: {$passed} / 실패: {$failed}\n";
echo ($failed === 0) ? "✅ 모든 테스트 통과!\n" : "❌ 일부 테스트 실패\n";
