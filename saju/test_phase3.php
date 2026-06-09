<?php
/**
 * Phase 3 통합 테스트 — SajuStoryGenerator
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Seoul');

require_once __DIR__ . '/saju/SajuStoryGenerator.php';

echo "=== Phase 3: SajuStoryGenerator 통합 테스트 ===\n\n";

$pass = 0;
$fail = 0;
$total = 0;

function test($name, $condition) {
    global $pass, $fail, $total;
    $total++;
    if ($condition) {
        $pass++;
        echo "  ✅ {$name}\n";
    } else {
        $fail++;
        echo "  ❌ {$name}\n";
    }
}

// ============================================================
// 테스트 1: 남성 신강 사주
// ============================================================
echo "--- 테스트 1: 남성 신강 사주 (1990-05-15 14시) ---\n";
$engine1 = new SajuEngine(1990, 5, 15, 14, 'male');
$story1 = new SajuStoryGenerator($engine1);

// 생성 테스트
$result1 = $story1->generate();

test('generate()가 배열 반환', is_array($result1));
test('title 존재', !empty($result1['title']));
test('subtitle 존재', !empty($result1['subtitle']));
test('sections 7개', count($result1['sections']) === 7);
test('full_text 존재', !empty($result1['full_text']));
test('char_count 존재', $result1['char_count'] > 0);
test('meta 존재', is_array($result1['meta']));
test('최소 3000자 충족', $result1['char_count'] >= 3000);

echo "\n  [결과] 제목: {$result1['title']}\n";
echo "  [결과] 부제: {$result1['subtitle']}\n";
echo "  [결과] 글자 수: {$result1['char_count']}자\n";
echo "  [결과] 감지 패턴: {$result1['meta']['patterns_detected']}개\n";
echo "  [결과] 사용 패턴: {$result1['meta']['patterns_used']}개\n";

// 섹션 검증
echo "\n  --- 섹션 검증 ---\n";
$expectedSections = [
    'saju_structure', 'personality', 'talent', 'career',
    'relationship', 'wealth', 'life_flow'
];
foreach ($result1['sections'] as $i => $section) {
    test("섹션 {$section['order']}: {$section['title']} 존재", 
         $section['id'] === $expectedSections[$i]);
    test("섹션 {$section['order']}: 내용 비어있지 않음", 
         !empty($section['content']));
    $charLen = mb_strlen($section['content']);
    echo "    → {$section['title']}: {$charLen}자, 패턴 " . count($section['patterns_used']) . "개 사용\n";
}

// ============================================================
// 테스트 2: 여성 신약 사주
// ============================================================
echo "\n--- 테스트 2: 여성 신약 사주 (1985-11-23 02시) ---\n";
$engine2 = new SajuEngine(1985, 11, 23, 2, 'female');
$story2 = new SajuStoryGenerator($engine2);
$result2 = $story2->generate();

test('여성 사주 generate() 성공', is_array($result2));
test('여성 사주 7개 섹션', count($result2['sections']) === 7);
test('여성 사주 최소 3000자', $result2['char_count'] >= 3000);
test('여성 사주 gender=female', $result2['meta']['gender'] === 'female');

echo "\n  [결과] 글자 수: {$result2['char_count']}자\n";
echo "  [결과] 감지 패턴: {$result2['meta']['patterns_detected']}개\n";

// ============================================================
// 테스트 3: 다른 사주
// ============================================================
echo "\n--- 테스트 3: 다양한 사주 생성 ---\n";
$testCases = [
    ['year' => 2000, 'month' => 1, 'day' => 1, 'hour' => 0, 'gender' => 'male', 'label' => '2000-01-01 자시 남'],
    ['year' => 1975, 'month' => 8, 'day' => 20, 'hour' => 10, 'gender' => 'female', 'label' => '1975-08-20 사시 여'],
    ['year' => 1998, 'month' => 3, 'day' => 7, 'hour' => 18, 'gender' => 'male', 'label' => '1998-03-07 유시 남'],
];

foreach ($testCases as $tc) {
    $eng = new SajuEngine($tc['year'], $tc['month'], $tc['day'], $tc['hour'], $tc['gender']);
    $gen = new SajuStoryGenerator($eng);
    $res = $gen->generate();
    
    test("{$tc['label']} — 생성 성공", is_array($res));
    test("{$tc['label']} — 3000자 이상 ({$res['char_count']}자)", $res['char_count'] >= 3000);
    test("{$tc['label']} — 7개 섹션", count($res['sections']) === 7);
    
    echo "    → 글자 수: {$res['char_count']}자 | 패턴: {$res['meta']['patterns_detected']}개\n";
}

// ============================================================
// 테스트 4: 개별 섹션 생성
// ============================================================
echo "\n--- 테스트 4: 개별 섹션 생성 ---\n";
$engine4 = new SajuEngine(1990, 5, 15, 14, 'male');
$story4 = new SajuStoryGenerator($engine4);

$structSection = $story4->generateSection('saju_structure');
test('사주_구조 개별 생성 성공', $structSection !== null);
test('사주_구조 id 일치', $structSection['id'] === 'saju_structure');

$persSection = $story4->generateSection('personality');
test('성격 개별 생성 성공', $persSection !== null);
test('성격 id 일치', $persSection['id'] === 'personality');

$nullSection = $story4->generateSection('nonexistent');
test('잘못된 섹션 ID → null', $nullSection === null);

// ============================================================
// 테스트 5: 디버그 API
// ============================================================
echo "\n--- 테스트 5: 디버그 API ---\n";
$patterns = $story4->getDetectedPatterns();
test('getDetectedPatterns() 배열 반환', is_array($patterns));
test('getDetectedPatterns() 비어있지 않음', count($patterns) > 0);

$ctx = $story4->getStoryContext();
test('getStoryContext() 배열 반환', is_array($ctx));
test('context에 dayMaster 존재', !empty($ctx['dayMaster']));
test('context에 isStrong 존재', isset($ctx['isStrong']));
test('context에 gender 존재', !empty($ctx['gender']));
test('context에 pillars 존재', !empty($ctx['pillars']));
test('context에 fourGods 존재', isset($ctx['fourGods']));
test('context에 allPatterns 존재', is_array($ctx['allPatterns']));
test('context에 patternsBySection 존재', is_array($ctx['patternsBySection']));

// ============================================================
// 테스트 6: 스토리 내용 품질 검증
// ============================================================
echo "\n--- 테스트 6: 스토리 내용 품질 검증 ---\n";
$fullText = $result1['full_text'];

test('일간 정보 포함', mb_strpos($fullText, '일간') !== false);
test('오행 언급 포함', mb_strpos($fullText, '오행') !== false);
test('신강/신약 언급 포함', 
     mb_strpos($fullText, '신강') !== false || mb_strpos($fullText, '신약') !== false);
test('용신 언급 포함', mb_strpos($fullText, '용신') !== false);
test('섹션 구분자 포함', mb_strpos($fullText, '【') !== false);

// 각 섹션 제목이 포함되는지 확인
$sectionTitles = ['사주 구조 분석', '성격과 기질', '재능과 적성', 
                   '직업과 사회생활', '인간관계와 연애·결혼', '재물과 금전운', '인생의 흐름'];
foreach ($sectionTitles as $title) {
    test("'{$title}' 섹션 제목 포함", mb_strpos($fullText, $title) !== false);
}

// ============================================================
// 최종 결과
// ============================================================
echo "\n" . str_repeat('=', 50) . "\n";
echo "📊 최종 결과: {$pass}/{$total} 통과";
if ($fail > 0) {
    echo " ({$fail}개 실패)";
}
echo "\n";

if ($fail === 0) {
    echo "🎉 모든 테스트 통과!\n";
} else {
    echo "⚠️ 실패한 테스트가 있습니다.\n";
}

// ============================================================
// 스토리 샘플 출력
// ============================================================
echo "\n" . str_repeat('=', 50) . "\n";
echo "📝 스토리 샘플 (테스트 1: 남성 1990-05-15)\n";
echo str_repeat('=', 50) . "\n\n";

// 전체 텍스트 중 처음 2000자만 미리보기
$preview = mb_substr($result1['full_text'], 0, 2000);
echo $preview . "\n\n... (이하 생략, 총 {$result1['char_count']}자)\n";
