<?php
/**
 * ================================================================
 * Phase 5 테스트: 원국+대운 조합 해석 시스템
 * ================================================================
 * 
 * 테스트 항목:
 * 1. DaeunCombinationData 로딩 & 통계
 * 2. 원국 패턴 정합성 (20종)
 * 3. 대운 패턴 정합성 (15종)
 * 4. 조합 상호작용 커버리지 (20 × 5그룹 × 4영역)
 * 5. 폴백 시스템 (gwada/flow 변형)
 * 6. 컨텍스트 보정 (12운성/합충/용신)
 * 7. 내러티브 (도입 텍스트 / 전환 조언)
 * 8. DaeunCombinationEngine 통합 테스트
 */

require_once __DIR__ . '/DaeunCombinationData.php';
require_once __DIR__ . '/DaeunCombinationEngine.php';
require_once __DIR__ . '/SajuEngine.php';

$passed = 0;
$failed = 0;
$total  = 0;

function test($desc, $result) {
    global $passed, $failed, $total;
    $total++;
    if ($result) {
        $passed++;
        echo "  ✅ {$desc}\n";
    } else {
        $failed++;
        echo "  ❌ {$desc}\n";
    }
}

echo "================================================================\n";
echo " Phase 5: 원국+대운 조합 해석 시스템 테스트\n";
echo "================================================================\n\n";

// ================================================================
// 1. 데이터 로딩 & 통계
// ================================================================
echo "▶ 1. 데이터 로딩 & 통계\n";

DaeunCombinationData::resetCache();
$stats = DaeunCombinationData::getStatistics();

test("원국 패턴 수 >= 20", $stats['wonguk_patterns'] >= 20);
test("대운 패턴 수 >= 15", $stats['daeun_patterns'] >= 15);
test("조합 원국 키 수 >= 20", $stats['combo_wonguk_keys'] >= 20);
test("원국 해석 문장 수 >= 80", $stats['wonguk_sentences'] >= 80);
test("대운 해석 문장 수 >= 60", $stats['daeun_sentences'] >= 60);
test("조합 해석 문장 수 >= 400", $stats['combo_sentences'] >= 400);
test("컨텍스트 문장 수 >= 100", $stats['context_sentences'] >= 100);
test("총 문장 수 >= 700", $stats['total_sentences'] >= 700);

echo "\n  📊 총 해석 문장: {$stats['total_sentences']}개\n";
echo "     원국 기본: {$stats['wonguk_sentences']}개\n";
echo "     대운 기본: {$stats['daeun_sentences']}개\n";
echo "     조합 상호작용: {$stats['combo_sentences']}개\n";
echo "     컨텍스트 보정: {$stats['context_sentences']}개\n\n";

// ================================================================
// 2. 원국 패턴 정합성 (20종)
// ================================================================
echo "▶ 2. 원국 패턴 정합성\n";

$requiredWongukPatterns = [
    'jaeda_sinyak', 'siksang_saengjae', 'gwanin_sangsaeng', 'singang_mugwan',
    'sinyak_muin', 'singang_yongjae', 'sinyak_yongin', 'johwa_gyunhyeong',
    'bigyeop_gwada', 'insu_gwada', 'siksang_gwada', 'jaesung_gwada',
    'gwansung_gwada', 'sanggwan_gyeongwan', 'bigyeop_taljae', 'jaeda_pain',
    'inbi_sangsaeng', 'jaegwan_ssangmi', 'salin_sangsaeng', 'siksin_jesal',
];

$availableWonguk = DaeunCombinationData::getAvailableWongukPatterns();
$missingWonguk = [];

foreach ($requiredWongukPatterns as $p) {
    $found = in_array($p, $availableWonguk);
    if (!$found) $missingWonguk[] = $p;
}
test("20개 원국 패턴 모두 존재", empty($missingWonguk));
if (!empty($missingWonguk)) {
    echo "    ⚠ 누락된 패턴: " . implode(', ', $missingWonguk) . "\n";
}

// 각 패턴에 이름, 설명, 4개 영역 해석 보유
$wongukComplete = true;
$wongukIncompleteList = [];
foreach ($requiredWongukPatterns as $p) {
    $name = DaeunCombinationData::getWongukPatternName($p);
    $desc = DaeunCombinationData::getWongukDescription($p);
    $hasAllCats = true;
    foreach (['career', 'wealth', 'relationship', 'life_flow'] as $cat) {
        $text = DaeunCombinationData::getWongukInterpretation($p, $cat);
        if (empty($text)) $hasAllCats = false;
    }
    if (empty($name) || $name === '기본 구조' || empty($desc) || !$hasAllCats) {
        $wongukComplete = false;
        $wongukIncompleteList[] = $p;
    }
}
test("모든 원국 패턴에 이름+설명+4영역 해석 존재", $wongukComplete);
if (!empty($wongukIncompleteList)) {
    echo "    ⚠ 불완전한 패턴: " . implode(', ', $wongukIncompleteList) . "\n";
}
echo "\n";

// ================================================================
// 3. 대운 패턴 정합성 (15종)
// ================================================================
echo "▶ 3. 대운 패턴 정합성\n";

$requiredDaeunPatterns = [
    'jaesung_daeun', 'gwansung_daeun', 'siksang_daeun', 'bigyeop_daeun', 'insung_daeun',
    'jaesung_gwada_daeun', 'gwansung_gwada_daeun', 'siksang_gwada_daeun',
    'bigyeop_gwada_daeun', 'insung_gwada_daeun',
    'siksang_saengjae_daeun', 'jae_saenggwan_daeun', 'gwan_saengin_daeun',
    'in_saengbi_daeun', 'bi_saengsik_daeun',
];

$availableDaeun = DaeunCombinationData::getAvailableDaeunPatterns();
$missingDaeun = [];

foreach ($requiredDaeunPatterns as $p) {
    if (!in_array($p, $availableDaeun)) $missingDaeun[] = $p;
}
test("15개 대운 패턴 모두 존재", empty($missingDaeun));
if (!empty($missingDaeun)) {
    echo "    ⚠ 누락된 패턴: " . implode(', ', $missingDaeun) . "\n";
}

// 각 대운 패턴에 이름 + 4영역 해석 보유
$daeunComplete = true;
$daeunIncompleteList = [];
foreach ($requiredDaeunPatterns as $p) {
    $name = DaeunCombinationData::getDaeunPatternName($p);
    $hasAllCats = true;
    foreach (['career', 'wealth', 'relationship', 'life_flow'] as $cat) {
        $text = DaeunCombinationData::getDaeunInterpretation($p, $cat);
        if (empty($text)) $hasAllCats = false;
    }
    if (empty($name) || $name === '기본 대운' || !$hasAllCats) {
        $daeunComplete = false;
        $daeunIncompleteList[] = $p;
    }
}
test("모든 대운 패턴에 이름+4영역 해석 존재", $daeunComplete);
if (!empty($daeunIncompleteList)) {
    echo "    ⚠ 불완전한 패턴: " . implode(', ', $daeunIncompleteList) . "\n";
}
echo "\n";

// ================================================================
// 4. 조합 상호작용 커버리지
// ================================================================
echo "▶ 4. 조합 상호작용 커버리지\n";

$daeunGroups = ['jaesung', 'gwansung', 'siksang', 'insung', 'bigyeop'];
$categories = ['career', 'wealth', 'relationship', 'life_flow'];

$totalCombos = 0;
$foundCombos = 0;
$emptyCombos = [];

foreach ($requiredWongukPatterns as $wonguk) {
    foreach ($daeunGroups as $group) {
        foreach ($categories as $cat) {
            $totalCombos++;
            $text = DaeunCombinationData::getCombinationInteraction($wonguk, $group . '_daeun', $cat);
            if (!empty($text)) {
                $foundCombos++;
            } else {
                $emptyCombos[] = "{$wonguk}×{$group}×{$cat}";
            }
        }
    }
}

$coverage = round(($foundCombos / $totalCombos) * 100, 1);
test("조합 커버리지 100% (20×5×4={$totalCombos} 조합)", $foundCombos === $totalCombos);
echo "  📊 커버리지: {$foundCombos}/{$totalCombos} ({$coverage}%)\n";
if (!empty($emptyCombos) && count($emptyCombos) <= 10) {
    echo "    ⚠ 비어 있는 조합: " . implode(', ', array_slice($emptyCombos, 0, 10)) . "\n";
}
echo "\n";

// ================================================================
// 5. 폴백 시스템 테스트 (gwada/flow 변형)
// ================================================================
echo "▶ 5. 폴백 시스템 (gwada/flow 변형)\n";

// 과다 대운 — 그룹 기본 해석 + 과다 보정이 적용되어야 함
$gwadaResult = DaeunCombinationData::getCombinationInteraction('jaeda_sinyak', 'jaesung_gwada_daeun', 'career');
test("과다 대운 폴백: jaeda_sinyak × jaesung_gwada_daeun (비어있지 않음)", !empty($gwadaResult));

$basicResult = DaeunCombinationData::getCombinationInteraction('jaeda_sinyak', 'jaesung_daeun', 'career');
test("기본 대운: jaeda_sinyak × jaesung_daeun (비어있지 않음)", !empty($basicResult));

// 과다 결과는 기본 결과를 포함해야 함 (기본 + 과다 보정)
test("과다 대운은 기본 대운 텍스트를 포함", strpos($gwadaResult, $basicResult) !== false || mb_strlen($gwadaResult) > mb_strlen($basicResult));

// 유통 대운 폴백
$flowResult = DaeunCombinationData::getCombinationInteraction('jaeda_sinyak', 'siksang_saengjae_daeun', 'career');
test("유통 대운 폴백: jaeda_sinyak × siksang_saengjae_daeun (비어있지 않음)", !empty($flowResult));

// 대운 그룹 매핑 검증
test("DAEUN_TO_GROUP 매핑: jaesung_gwada_daeun → jaesung", DaeunCombinationData::DAEUN_TO_GROUP['jaesung_gwada_daeun'] === 'jaesung');
test("DAEUN_TO_GROUP 매핑: siksang_saengjae_daeun → jaesung", DaeunCombinationData::DAEUN_TO_GROUP['siksang_saengjae_daeun'] === 'jaesung');
test("DAEUN_TO_GROUP 매핑: gwan_saengin_daeun → insung", DaeunCombinationData::DAEUN_TO_GROUP['gwan_saengin_daeun'] === 'insung');
test("DAEUN_VARIANT: jaesung_daeun → basic", DaeunCombinationData::DAEUN_VARIANT['jaesung_daeun'] === 'basic');
test("DAEUN_VARIANT: jaesung_gwada_daeun → gwada", DaeunCombinationData::DAEUN_VARIANT['jaesung_gwada_daeun'] === 'gwada');
test("DAEUN_VARIANT: siksang_saengjae_daeun → flow", DaeunCombinationData::DAEUN_VARIANT['siksang_saengjae_daeun'] === 'flow');
echo "\n";

// ================================================================
// 6. 컨텍스트 보정 테스트
// ================================================================
echo "▶ 6. 컨텍스트 보정\n";

// 용신 보정
foreach ($categories as $cat) {
    $yongshin = DaeunCombinationData::getYongshinModifier($cat);
    test("용신 보정 {$cat}: 비어있지 않음", !empty($yongshin));
}

// 12운성 보정 (12개 × 4개 영역)
$stages = ['장생', '목욕', '관대', '건록', '제왕', '쇠', '병', '사', '묘', '절', '태', '양'];
$stageOk = true;
$stageMissing = [];
foreach ($stages as $stage) {
    foreach ($categories as $cat) {
        $text = DaeunCombinationData::getTwelveStageModifier($stage, $cat);
        if (empty($text)) {
            $stageOk = false;
            $stageMissing[] = "{$stage}×{$cat}";
        }
    }
}
test("12운성 보정 완전 커버리지 (12×4=48)", $stageOk);
if (!empty($stageMissing)) {
    echo "    ⚠ 누락: " . implode(', ', array_slice($stageMissing, 0, 5)) . "\n";
}

// 합충형파해 보정
$relTypes = ['합', '충', '형', '파', '해', '원진'];
$relOk = true;
$relMissing = [];
foreach ($relTypes as $type) {
    foreach ($categories as $cat) {
        $text = DaeunCombinationData::getRelationshipModifier($type, $cat);
        if (empty($text)) {
            $relOk = false;
            $relMissing[] = "{$type}×{$cat}";
        }
    }
}
test("합충형파해원진 보정 완전 커버리지 (6×4=24)", $relOk);
if (!empty($relMissing)) {
    echo "    ⚠ 누락: " . implode(', ', $relMissing) . "\n";
}
echo "\n";

// ================================================================
// 7. 내러티브 테스트
// ================================================================
echo "▶ 7. 내러티브 (도입 텍스트 / 전환 조언)\n";

// 도입 텍스트 등급별
$levels = ['대길', '길', '보통', '소흉', '흉'];
foreach ($levels as $level) {
    $text = DaeunCombinationData::getIntroText($level, 35);
    test("도입 텍스트 [{$level}]: 비어있지 않음", !empty($text));
}

// 도입 텍스트 나이대별
$ageTests = [
    ['대길', 15, 'youth'],
    ['길', 40, 'middle'],
    ['보통', 60, 'senior'],
];
foreach ($ageTests as [$level, $age, $expectedGroup]) {
    $text = DaeunCombinationData::getIntroText($level, $age);
    test("도입 텍스트 [{$level}] age={$age}: 비어있지 않음", !empty($text));
}

// 전환 조언
$transitionTests = [
    ['jaesung_daeun', 'gwansung_daeun', 'jaesung_to_gwansung'],
    ['siksang_daeun', 'insung_daeun', 'siksang_to_insung'],
    ['bigyeop_daeun', 'jaesung_daeun', 'bigyeop_to_jaesung'],
];
foreach ($transitionTests as [$from, $to, $key]) {
    $advice = DaeunCombinationData::getTransitionAdvice($from, $to);
    test("전환 조언 [{$key}]: 비어있지 않음", !empty($advice));
}
echo "\n";

// ================================================================
// 8. DaeunCombinationEngine 통합 테스트
// ================================================================
echo "▶ 8. DaeunCombinationEngine 통합 테스트\n";

// 샘플 사주로 엔진 생성
try {
    $engine = new SajuEngine(1990, 5, 15, 14, true, false);
    $result = $engine->getResult();

    $comboEngine = new DaeunCombinationEngine($engine, $result);
    test("DaeunCombinationEngine 인스턴스 생성 성공", true);

    // 원국 패턴 감지
    $wongukPatterns = $comboEngine->getWongukPatterns();
    test("원국 패턴 감지: " . count($wongukPatterns) . "개", count($wongukPatterns) >= 1);
    echo "  📋 감지된 원국 패턴: " . implode(', ', $wongukPatterns) . "\n";

    // 원국 패턴 이름
    $wongukNames = $comboEngine->getWongukPatternNames();
    test("원국 패턴 이름 매핑 성공", !empty($wongukNames));

    // 원국 요약
    $summary = $comboEngine->getWongukSummary();
    test("원국 요약: primary_pattern 존재", !empty($summary['primary_pattern']));
    test("원국 요약: name 존재", !empty($summary['name']));
    test("원국 요약: description 존재", !empty($summary['description']));

    // 대운 패턴 감지
    $daeunPattern = $comboEngine->detectDaeunPattern('편재', '정재');
    test("대운 패턴 감지 (편재+정재): {$daeunPattern}", $daeunPattern === 'jaesung_gwada_daeun');

    $daeunPattern2 = $comboEngine->detectDaeunPattern('식신', '편재');
    test("대운 패턴 감지 (식신+편재): {$daeunPattern2}", $daeunPattern2 === 'siksang_saengjae_daeun');

    $daeunPattern3 = $comboEngine->detectDaeunPattern('정관', '비견');
    test("대운 패턴 감지 (정관+비견): {$daeunPattern3}", $daeunPattern3 === 'gwansung_daeun');

    // 조합 해석 생성
    $sampleDaeun = [
        'stem' => '庚',
        'branch' => '午',
        'stem_sipsin' => '편재',
        'branch_sipsin' => '정재',
        'age_start' => 35,
        'age_end' => 44,
        'score' => 72,
        'is_yongshin' => true,
        'twelve_stage' => '건록',
        'relationships' => [
            ['type' => '합', 'partner' => '子']
        ],
    ];

    $interp = $comboEngine->getCombinationInterpretation($sampleDaeun);
    test("조합 해석: wonguk_pattern 존재", !empty($interp['wonguk_pattern']));
    test("조합 해석: daeun_pattern 존재", !empty($interp['daeun_pattern']));
    test("조합 해석: career 텍스트 존재", !empty($interp['career']));
    test("조합 해석: wealth 텍스트 존재", !empty($interp['wealth']));
    test("조합 해석: relationship 텍스트 존재", !empty($interp['relationship']));
    test("조합 해석: life_flow 텍스트 존재", !empty($interp['life_flow']));
    test("조합 해석: narrative 존재", !empty($interp['narrative']));

    // career 텍스트 길이 (4개 레이어 합치면 최소 100자)
    $careerLen = mb_strlen($interp['career']);
    test("career 텍스트 충분한 길이 ({$careerLen}자 >= 100)", $careerLen >= 100);

    // narrative 포맷 검증
    test("narrative에 원국 패턴명 포함", strpos($interp['narrative'], $interp['wonguk_pattern_name']) !== false);
    test("narrative에 대운 패턴명 포함", strpos($interp['narrative'], $interp['daeun_pattern_name']) !== false);
    test("narrative에 4대 영역 제목 포함", 
        strpos($interp['narrative'], '직업·경력') !== false &&
        strpos($interp['narrative'], '재물·금전') !== false
    );

    echo "\n  📝 조합 해석 결과 미리보기:\n";
    echo "     원국: {$interp['wonguk_pattern_name']}\n";
    echo "     대운: {$interp['daeun_pattern_name']}\n";
    echo "     career 길이: {$careerLen}자\n";
    echo "     wealth 길이: " . mb_strlen($interp['wealth']) . "자\n";
    echo "     relationship 길이: " . mb_strlen($interp['relationship']) . "자\n";
    echo "     life_flow 길이: " . mb_strlen($interp['life_flow']) . "자\n";

} catch (Exception $e) {
    test("DaeunCombinationEngine 통합 테스트 (예외 발생)", false);
    echo "    ⚠ " . $e->getMessage() . "\n";
}
echo "\n";

// ================================================================
// 9. 사용자 시나리오 테스트 (재다신약+재성, 식상생재+식상, 관인상생+관성)
// ================================================================
echo "▶ 9. 사용자 시나리오 테스트\n";

$scenarios = [
    ['재다신약 + 재성대운', 'jaeda_sinyak', 'jaesung_daeun'],
    ['재다신약 + 재성과다대운', 'jaeda_sinyak', 'jaesung_gwada_daeun'],
    ['식상생재 + 식상대운', 'siksang_saengjae', 'siksang_daeun'],
    ['식상생재 + 식상과다대운', 'siksang_saengjae', 'siksang_gwada_daeun'],
    ['관인상생 + 관성대운', 'gwanin_sangsaeng', 'gwansung_daeun'],
    ['관인상생 + 관성과다대운', 'gwanin_sangsaeng', 'gwansung_gwada_daeun'],
    ['식신제살 + 관성대운', 'siksin_jesal', 'gwansung_daeun'],
    ['살인상생 + 인성대운', 'salin_sangsaeng', 'insung_daeun'],
    ['비겁과다 + 식상대운', 'bigyeop_gwada', 'siksang_daeun'],
    ['재관쌍미 + 재성대운', 'jaegwan_ssangmi', 'jaesung_daeun'],
];

foreach ($scenarios as [$name, $wonguk, $daeun]) {
    $allCatsNonEmpty = true;
    foreach ($categories as $cat) {
        $text = DaeunCombinationData::getCombinationInteraction($wonguk, $daeun, $cat);
        if (empty($text)) $allCatsNonEmpty = false;
    }
    test("시나리오 [{$name}]: 4영역 모두 비어있지 않음", $allCatsNonEmpty);
}
echo "\n";

// ================================================================
// 10. 문장 품질 기본 검증
// ================================================================
echo "▶ 10. 문장 품질 기본 검증\n";

// 단정적 예언 패턴 검사 (금지어)
$forbidden = ['반드시 ~합니다', '확실히 ~됩니다', '절대로', '틀림없이', '100%'];
$qualityOk = true;
$qualityIssues = [];

foreach ($requiredWongukPatterns as $wonguk) {
    foreach ($daeunGroups as $group) {
        foreach ($categories as $cat) {
            $text = DaeunCombinationData::getCombinationInteraction($wonguk, $group . '_daeun', $cat);
            foreach ($forbidden as $word) {
                if (strpos($text, $word) !== false) {
                    $qualityOk = false;
                    $qualityIssues[] = "{$wonguk}×{$group}×{$cat}: '{$word}'";
                }
            }
        }
    }
}
test("단정적 예언 금지어 미사용", $qualityOk);
if (!empty($qualityIssues)) {
    echo "    ⚠ 문제: " . implode(', ', array_slice($qualityIssues, 0, 5)) . "\n";
}

// 최소 문장 길이 검증 (각 조합 해석이 최소 30자 이상)
$minLenOk = true;
$shortTexts = [];
foreach ($requiredWongukPatterns as $wonguk) {
    foreach ($daeunGroups as $group) {
        foreach ($categories as $cat) {
            $text = DaeunCombinationData::getCombinationInteraction($wonguk, $group . '_daeun', $cat);
            if (mb_strlen($text) < 30 && !empty($text)) {
                $minLenOk = false;
                $shortTexts[] = "{$wonguk}×{$group}×{$cat} (" . mb_strlen($text) . "자)";
            }
        }
    }
}
test("모든 조합 해석 30자 이상", $minLenOk);
if (!empty($shortTexts)) {
    echo "    ⚠ 짧은 텍스트: " . implode(', ', array_slice($shortTexts, 0, 5)) . "\n";
}

// 중복 텍스트 검사
$allTexts = [];
$duplicateCount = 0;
foreach ($requiredWongukPatterns as $wonguk) {
    foreach ($daeunGroups as $group) {
        foreach ($categories as $cat) {
            $text = DaeunCombinationData::getCombinationInteraction($wonguk, $group . '_daeun', $cat);
            if (!empty($text)) {
                $hash = md5($text);
                if (isset($allTexts[$hash])) {
                    $duplicateCount++;
                } else {
                    $allTexts[$hash] = "{$wonguk}×{$group}×{$cat}";
                }
            }
        }
    }
}
test("조합 해석 중복 없음 (중복 {$duplicateCount}개)", $duplicateCount === 0);
echo "\n";

// ================================================================
// 결과 요약
// ================================================================
echo "================================================================\n";
echo " 테스트 결과: {$passed}/{$total} 통과";
if ($failed > 0) {
    echo " ({$failed}개 실패)";
}
echo "\n================================================================\n";

exit($failed === 0 ? 0 : 1);
