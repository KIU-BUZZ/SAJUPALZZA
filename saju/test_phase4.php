<?php
/**
 * Phase 4 통합 테스트 — DaeunAnalyzer + DaeunInterpretationData
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Seoul');

require_once __DIR__ . '/saju/DaeunAnalyzer.php';

echo "=== Phase 4: 대운 분석 시스템 통합 테스트 ===\n\n";

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
// 테스트 1: DaeunInterpretationData 데이터 검증
// ============================================================
echo "--- 테스트 1: 해석 데이터 시스템 ---\n";

$data = new DaeunInterpretationData();

// 데이터 로딩 확인
$stats = $data->getStatistics();
test('데이터 전체 문장 수 > 300', $stats['total_sentences'] > 300);
test('데이터 키 수 >= 25', $stats['total_keys'] >= 25);
echo "  [정보] 전체 문장 수: {$stats['total_sentences']}\n";
echo "  [정보] 전체 키 수: {$stats['total_keys']}\n";

// 5개 대운 유형별 데이터 확인
$types = ['jaesung', 'gwansung', 'siksang', 'insung', 'bigyeop'];
$categories = ['life_change', 'career_change', 'relationship_change', 'wealth_change'];

foreach ($types as $type) {
    $typeSentences = $stats['by_type'][$type] ?? 0;
    test("{$type} 그룹 문장 수 >= 30", $typeSentences >= 30);
}

// 카테고리별 데이터 확인
foreach ($categories as $cat) {
    $catSentences = $stats['by_category'][$cat] ?? 0;
    test("{$cat} 카테고리 문장 수 >= 50", $catSentences >= 50);
}

// 서브타입 데이터 확인
$subtypes = ['pyeonjae', 'jeongjae', 'pyeongwan', 'jeonggwan', 'siksin', 'sanggwan', 'pyeonin', 'jeongin', 'bigyeon', 'geopjae'];
$subtypeHasData = 0;
foreach ($subtypes as $sub) {
    $subSentences = $data->getSubtypeSentences($sub, 'life_change', []);
    if (count($subSentences) > 0) $subtypeHasData++;
}
test("서브타입 데이터 >= 8개 유형 보유", $subtypeHasData >= 8);
echo "  [정보] 서브타입 데이터 있는 유형: {$subtypeHasData}/10\n";

// 교차(cross) 데이터 확인
$crossCount = 0;
foreach ($types as $type) {
    $strongCross = $data->getCrossSentences($type, 'strong', 'life_change', []);
    $weakCross = $data->getCrossSentences($type, 'weak', 'life_change', []);
    if (count($strongCross) > 0 && count($weakCross) > 0) $crossCount++;
}
test("교차 데이터 5개 유형 모두 존재", $crossCount === 5);

// 필터링 테스트
echo "\n--- 테스트 2: 필터링 기능 ---\n";

$allSentences = $data->getFilteredSentences('jaesung', 'life_change', []);
test('전체 필터 → 문장 반환', count($allSentences) > 0);
echo "  [정보] 재성/인생 전체: " . count($allSentences) . "문장\n";

$strongFilter = $data->getFilteredSentences('jaesung', 'life_change', ['strength' => 'strong']);
test('신강 필터링 적용', count($strongFilter) > 0);
test('신강 필터 <= 전체', count($strongFilter) <= count($allSentences));
echo "  [정보] 재성/인생/신강: " . count($strongFilter) . "문장\n";

$weakFilter = $data->getFilteredSentences('jaesung', 'life_change', ['strength' => 'weak']);
test('신약 필터링 적용', count($weakFilter) > 0);
echo "  [정보] 재성/인생/신약: " . count($weakFilter) . "문장\n";

$maleFilter = $data->getFilteredSentences('jaesung', 'life_change', ['gender' => 'male']);
test('남성 필터링 적용', count($maleFilter) > 0);

$femaleFilter = $data->getFilteredSentences('gwansung', 'relationship_change', ['gender' => 'female']);
test('여성 필터링 적용 (관성)', count($femaleFilter) > 0);

// 반환 타입 확인 (필터링 후 문자열 배열)
$firstSentence = $allSentences[0] ?? null;
test('필터된 문장이 문자열', is_string($firstSentence));

// countAllSentences 확인
$totalCount = $data->countAllSentences();
test('countAllSentences = getStatistics total', $totalCount === $stats['total_sentences']);

// ============================================================
// 테스트 3: DaeunAnalyzer 남성 신강 사주
// ============================================================
echo "\n--- 테스트 3: 남성 신강 사주 (1990-05-15 14시) ---\n";
$engine1 = new SajuEngine(1990, 5, 15, 14, 'male');
$analyzer1 = new DaeunAnalyzer($engine1);

// 대운 기간 조회
$periods1 = $analyzer1->getDaeunPeriods();
test('대운 기간 10개', count($periods1) === 10);

// 기간 데이터 구조 확인
$p0 = $periods1[0];
test('period.index 존재', isset($p0['index']));
test('period.age_start 존재', isset($p0['age_start']));
test('period.age_end 존재', isset($p0['age_end']));
test('period.stem 존재', !empty($p0['stem']));
test('period.branch 존재', !empty($p0['branch']));
test('period.stem_element 존재', !empty($p0['stem_element']));
test('period.branch_element 존재', !empty($p0['branch_element']));
test('period.stem_sipsin 존재', !empty($p0['stem_sipsin']));
test('period.branch_sipsin 존재', !empty($p0['branch_sipsin']));
test('period.primary_group 존재', !empty($p0['primary_group']));
test('period.primary_subtype 존재', !empty($p0['primary_subtype']));
test('period.twelve_stage 존재', !empty($p0['twelve_stage']));
test('period.score 범위 10-95', $p0['score'] >= 10 && $p0['score'] <= 95);
test('period.score_level 존재', is_array($p0['score_level']));
test('period.is_yongshin bool 타입', is_bool($p0['is_yongshin']));
test('period.is_gishin bool 타입', is_bool($p0['is_gishin']));
test('period.is_pure bool 타입', is_bool($p0['is_pure']));

// 모든 기간 점수 범위 검증
$allScoresValid = true;
foreach ($periods1 as $p) {
    if ($p['score'] < 10 || $p['score'] > 95) {
        $allScoresValid = false;
        break;
    }
}
test('모든 기간 점수 10-95 범위', $allScoresValid);

// 원국 컨텍스트 확인
$ctx1 = $analyzer1->getWongukContext();
test('wonguk.dayMaster 존재', !empty($ctx1['dayMaster']));
test('wonguk.dayElement 존재', !empty($ctx1['dayElement']));
test('wonguk.isStrong bool 타입', is_bool($ctx1['isStrong']));
test('wonguk.gender = male', $ctx1['gender'] === 'male');
test('wonguk.sipsinGroups 5개', count($ctx1['sipsinGroups']) === 5);
test('wonguk.yongshin 존재', !empty($ctx1['yongshin']));

echo "  [정보] 일간: {$ctx1['dayMaster']}({$ctx1['dayElement']})\n";
echo "  [정보] 신강/신약: " . ($ctx1['isStrong'] ? '신강' : '신약') . "\n";

// 전체 분석 실행
$result1 = $analyzer1->analyze();
test('analyze() 배열 반환', is_array($result1));
test('overview 존재', isset($result1['overview']));
test('periods 10개', count($result1['periods']) === 10);
test('timeline 문자열', is_string($result1['timeline']));
test('statistics 존재', isset($result1['statistics']));

// overview 구조 확인
$ov1 = $result1['overview'];
test('overview.total_periods = 10', $ov1['total_periods'] === 10);
test('overview.average_score 존재', isset($ov1['average_score']));
test('overview.best_period 존재', $ov1['best_period'] !== null);
test('overview.worst_period 존재', $ov1['worst_period'] !== null);
test('overview.type_distribution 존재', !empty($ov1['type_distribution']));
test('overview.trend 존재', !empty($ov1['trend']));
test('overview.trend_label 존재', !empty($ov1['trend_label']));
test('overview.trend_description 존재', !empty($ov1['trend_description']));

echo "  [정보] 평균 점수: {$ov1['average_score']}점\n";
echo "  [정보] 대운 추세: {$ov1['trend_label']}\n";
echo "  [정보] 용신 대운 수: {$ov1['yongshin_periods']}\n";
echo "  [정보] 기신 대운 수: {$ov1['gishin_periods']}\n";

// 개별 기간 분석 구조 확인
$ap0 = $result1['periods'][0];
test('period.group_label 존재', !empty($ap0['group_label']));
test('period.subtype_label 존재', !empty($ap0['subtype_label']));
test('period.strength_combo 존재', !empty($ap0['strength_combo']));
test('period.narrative 존재', !empty($ap0['narrative']));
test('period.advice 존재', !empty($ap0['advice']));
test('period.interpretations 4개 영역', count($ap0['interpretations']) === 4);

// 영역별 해석 구조 확인
foreach ($categories as $cat) {
    $interp = $ap0['interpretations'][$cat];
    test("{$cat} main_text 존재", !empty($interp['main_text']));
    test("{$cat} full_text 존재", !empty($interp['full_text']));
    test("{$cat} sentence_count > 0", $interp['sentence_count'] > 0);
}

// 내러티브 길이 확인
$narrativeLen1 = mb_strlen($ap0['narrative']);
test('첫 대운 내러티브 >= 500자', $narrativeLen1 >= 500);
echo "  [정보] 첫 대운 내러티브 길이: {$narrativeLen1}자\n";

// 타임라인 길이 확인
$timelineLen1 = mb_strlen($result1['timeline']);
test('타임라인 >= 1000자', $timelineLen1 >= 1000);
echo "  [정보] 타임라인 길이: {$timelineLen1}자\n";

// 통계 확인
$stat1 = $result1['statistics'];
test('총 기간 = 10', $stat1['total_periods'] === 10);
test('총 문장 수 > 50', $stat1['total_sentences'] > 50);
test('총 캐릭터 수 > 5000', $stat1['total_characters'] > 5000);
echo "  [정보] 총 사용 문장: {$stat1['total_sentences']}\n";
echo "  [정보] 총 문자 수: {$stat1['total_characters']}\n";

// ============================================================
// 테스트 4: 여성 신약 사주
// ============================================================
echo "\n--- 테스트 4: 여성 신약 사주 (1985-11-20 06시) ---\n";
$engine2 = new SajuEngine(1985, 11, 20, 6, 'female');
$analyzer2 = new DaeunAnalyzer($engine2);

$ctx2 = $analyzer2->getWongukContext();
test('여성 사주 gender = female', $ctx2['gender'] === 'female');
echo "  [정보] 일간: {$ctx2['dayMaster']}({$ctx2['dayElement']})\n";
echo "  [정보] 신강/신약: " . ($ctx2['isStrong'] ? '신강' : '신약') . "\n";

$result2 = $analyzer2->analyze();
test('여성 사주 분석 성공', is_array($result2) && count($result2['periods']) === 10);

// 대운 유형 다양성 확인
$groupSet2 = [];
foreach ($result2['periods'] as $ap) {
    $groupSet2[$ap['period']['primary_group']] = true;
}
$groupTypes2 = count($groupSet2);
test('다양한 대운 유형 >= 2', $groupTypes2 >= 2);
echo "  [정보] 등장 대운 유형 수: {$groupTypes2}\n";

// 여성 관련 해석 포함 확인 (관성 대운에서 남편 관련 문장이 있는지)
$femaleRelated = false;
foreach ($result2['periods'] as $ap) {
    if (mb_strpos($ap['narrative'], '남편') !== false ||
        mb_strpos($ap['narrative'], '여성') !== false ||
        mb_strpos($ap['narrative'], '이성') !== false) {
        $femaleRelated = true;
        break;
    }
}
test('여성 맞춤 해석 문장 포함', $femaleRelated);

// ============================================================
// 테스트 5: analyzeSinglePeriod
// ============================================================
echo "\n--- 테스트 5: 단일 기간 분석 ---\n";

$single = $analyzer1->analyzeSinglePeriod(0);
test('analyzeSinglePeriod(0) 성공', $single !== null);
test('단일 분석 = 전체[0]과 같은 구조', 
    isset($single['period']) && isset($single['narrative']) && isset($single['interpretations']));

$singleNull = $analyzer1->analyzeSinglePeriod(99);
test('analyzeSinglePeriod(99) = null', $singleNull === null);

// ============================================================
// 테스트 6: 다양한 사주 테스트
// ============================================================
echo "\n--- 테스트 6: 다양한 사주 분석 ---\n";

$testCases = [
    ['year' => 1970, 'month' => 3,  'day' => 10, 'hour' => 2,  'gender' => 'male',   'label' => '1970년 남성'],
    ['year' => 1995, 'month' => 8,  'day' => 25, 'hour' => 22, 'gender' => 'female', 'label' => '1995년 여성'],
    ['year' => 2000, 'month' => 1,  'day' => 1,  'hour' => 0,  'gender' => 'male',   'label' => '2000년 남성(자시)'],
    ['year' => 1978, 'month' => 12, 'day' => 31, 'hour' => 18, 'gender' => 'female', 'label' => '1978년 여성(유시)'],
    ['year' => 1988, 'month' => 6,  'day' => 15, 'hour' => 10, 'gender' => 'male',   'label' => '1988년 남성(사시)'],
];

foreach ($testCases as $tc) {
    $eng = new SajuEngine($tc['year'], $tc['month'], $tc['day'], $tc['hour'], $tc['gender']);
    $ana = new DaeunAnalyzer($eng);
    $res = $ana->analyze();
    
    $ok = is_array($res) && count($res['periods']) === 10 && !empty($res['timeline']);
    test("{$tc['label']} 분석 성공", $ok);
    
    if ($ok) {
        $ov = $res['overview'];
        echo "    → 평균: {$ov['average_score']}점, 추세: {$ov['trend_label']}, 유형 수: " . count($ov['type_distribution']) . "\n";
    }
}

// ============================================================
// 테스트 7: 해석 품질 검증
// ============================================================
echo "\n--- 테스트 7: 해석 품질 ---\n";

// 전체 내러티브 합산 길이
$totalNarrativeLen = 0;
foreach ($result1['periods'] as $ap) {
    $totalNarrativeLen += mb_strlen($ap['narrative']);
}
$totalNarrativeLen += mb_strlen($result1['timeline']);
test('전체 내러티브 >= 10000자', $totalNarrativeLen >= 10000);
echo "  [정보] 전체 내러티브 합산: {$totalNarrativeLen}자\n";

// 어드바이스 존재 확인
$adviceCount = 0;
foreach ($result1['periods'] as $ap) {
    if (!empty($ap['advice'])) $adviceCount++;
}
test('모든 기간에 조언 존재', $adviceCount === 10);

// 최고·최저 대운간 점수 차이
$bestScore = $ov1['best_period']['score'];
$worstScore = $ov1['worst_period']['score'];
test('최고-최저 점수 차이 >= 5', ($bestScore - $worstScore) >= 5);
echo "  [정보] 최고: {$bestScore}점, 최저: {$worstScore}점, 차이: " . ($bestScore - $worstScore) . "점\n";

// 용신/기신 마커 확인 (10개 기간 중 하나라도 있어야)
$hasYongOrGi = false;
foreach ($result1['periods'] as $ap) {
    if ($ap['period']['is_yongshin'] || $ap['period']['is_gishin']) {
        $hasYongOrGi = true;
        break;
    }
}
test('용신 또는 기신 대운 존재', $hasYongOrGi);

// 12운성 정보 포함 확인
$hasStageInfo = false;
foreach ($result1['periods'] as $ap) {
    if (mb_strpos($ap['narrative'], '12운성') !== false) {
        $hasStageInfo = true;
        break;
    }
}
test('12운성 정보 내러티브에 포함', $hasStageInfo);

// ============================================================
// 테스트 8: 대운 기간 요약 출력
// ============================================================
echo "\n--- 테스트 8: 대운 타임라인 미리보기 ---\n";

echo "\n" . mb_substr($result1['timeline'], 0, 2000) . "\n";
echo "\n  [정보] 타임라인 전체 길이: " . mb_strlen($result1['timeline']) . "자\n";

// ============================================================
// 결과 요약
// ============================================================
echo "\n═══════════════════════════════════════════\n";
echo "   Phase 4 테스트 결과: {$pass}/{$total} 통과";
if ($fail > 0) echo " ({$fail}개 실패)";
echo "\n═══════════════════════════════════════════\n\n";

if ($fail === 0) {
    echo "🎉 모든 테스트 통과! 대운 분석 시스템이 정상 작동합니다.\n\n";
} else {
    echo "⚠️ {$fail}개의 테스트가 실패했습니다. 확인이 필요합니다.\n\n";
}

// 전체 데이터 통계 출력
echo "--- 전체 데이터 통계 ---\n";
$finalStats = $data->getStatistics();
echo "  전체 해석 문장 수: {$finalStats['total_sentences']}\n";
echo "  데이터 키 수: {$finalStats['total_keys']}\n";
echo "  유형별 문장 분포:\n";
foreach ($finalStats['by_type'] as $key => $count) {
    echo "    {$key}: {$count}\n";
}
echo "  카테고리별 문장 분포:\n";
foreach ($finalStats['by_category'] as $cat => $count) {
    echo "    {$cat}: {$count}\n";
}
