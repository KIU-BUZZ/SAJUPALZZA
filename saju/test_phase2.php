<?php
/**
 * Phase 2 Integration Test
 * PatternInterpretationData 전체 테스트
 */
require_once __DIR__ . '/saju/PatternInterpretationData.php';

echo "=== Phase 2: PatternInterpretationData 통합 테스트 ===\n\n";

// 1. 인스턴스 생성 및 데이터 로드
$interp = new PatternInterpretationData();
echo "1. 인스턴스 생성 완료\n";

// 2. 통계 출력
$stats = $interp->getStatistics();
echo "\n2. 전체 통계:\n";
echo "   - 총 패턴 수: {$stats['total_patterns']}\n";
echo "   - 총 문장 수: {$stats['total_sentences']}\n";
echo "   - 카테고리별:\n";
foreach ($stats['by_category'] as $cat => $count) {
    echo "     * {$cat}: {$count}문장\n";
}

// 3. 각 패턴별 문장 수 확인
echo "\n3. 패턴별 문장 수:\n";
$allPatterns = $interp->getAvailablePatterns();
$totalSentences = 0;
foreach ($allPatterns as $pid) {
    $data = $interp->getInterpretation($pid);
    if ($data) {
        $count = 0;
        foreach (['personality','talent','career','wealth','relationship','life_flow'] as $cat) {
            if (isset($data[$cat])) {
                $count += count($data[$cat]);
            }
        }
        $totalSentences += $count;
        echo "   [{$pid}]: {$count}문장\n";
    }
}
echo "\n   >>> 전체 합계: {$totalSentences}문장\n";

// 4. 필터링 테스트 (intensity, gender)
echo "\n4. 필터링 테스트:\n";
$filtered = $interp->getFilteredInterpretation('siksang_gwada', [
    'intensity' => 90,
    'gender' => 'female',
    'isStrong' => false
]);
if ($filtered) {
    $fCount = 0;
    foreach (['personality','talent','career','wealth','relationship','life_flow'] as $cat) {
        if (isset($filtered[$cat])) {
            $fCount += count($filtered[$cat]);
        }
    }
    echo "   siksang_gwada (intensity=90, female, sinyak): {$fCount}문장\n";
}

$filtered2 = $interp->getFilteredInterpretation('female_gwansal_honjap', [
    'intensity' => 50,
    'gender' => 'male',
    'isStrong' => true
]);
if ($filtered2) {
    $fCount2 = 0;
    foreach (['personality','talent','career','wealth','relationship','life_flow'] as $cat) {
        if (isset($filtered2[$cat])) {
            $fCount2 += count($filtered2[$cat]);
        }
    }
    echo "   female_gwansal_honjap (male filter → 성별 불일치): {$fCount2}문장 (여성전용 필터링됨)\n";
}

// 5. 병합 해석 테스트
echo "\n5. 병합 해석 테스트:\n";
$patterns = [
    ['id' => 'bigyeop_gwada', 'intensity' => 70],
    ['id' => 'siksang_saengjae', 'intensity' => 60],
    ['id' => 'ohang_johwa', 'intensity' => 50],
];
$merged = $interp->getMergedInterpretations($patterns, ['gender' => 'male', 'isStrong' => true]);
$mCount = 0;
foreach (['personality','talent','career','wealth','relationship','life_flow'] as $cat) {
    if (isset($merged[$cat])) {
        $catCount = count($merged[$cat]);
        $mCount += $catCount;
        echo "   {$cat}: {$catCount}문장\n";
    }
}
echo "   총 병합: {$mCount}문장\n";

// 6. 존재하지 않는 패턴 테스트
echo "\n6. 에지 케이스 테스트:\n";
$none = $interp->getInterpretation('nonexistent_pattern');
echo "   존재하지 않는 패턴: " . ($none === null ? 'null (정상)' : '오류') . "\n";

echo "\n=== 테스트 완료 ===\n";
