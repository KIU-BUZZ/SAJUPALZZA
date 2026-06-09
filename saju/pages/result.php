<?php
/**
 * 사주 분석 결과 페이지 — 사용자 친화형 결과 표시
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once SAJU_ENGINE_PATH . '/SajuEngine.php';
require_once SAJU_ENGINE_PATH . '/OhangAnalysis.php';
require_once SAJU_ENGINE_PATH . '/FortuneInterpreter.php';

$user = getCurrentUser();
$recordId = (int)($_GET['id'] ?? 0);

if ($recordId <= 0) {
    setFlashMessage('error', '잘못된 요청입니다.');
    redirect('/pages/history.php');
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM saju_fortune_history WHERE id = ? AND user_id = ?");
$stmt->execute([$recordId, $user['id']]);
$record = $stmt->fetch();

if (!$record) {
    setFlashMessage('error', '분석 기록을 찾을 수 없습니다.');
    redirect('/pages/history.php');
}

$storedPayload = json_decode($record['fortune_result'] ?? '', true) ?: [];
$analysisType  = $storedPayload['analysis_type'] ?? $record['analysis_type'] ?? 'comprehensive';
$isPremium     = $storedPayload['is_premium'] ?? ($analysisType !== 'basic_saju' && $analysisType !== 'ohang');
$ticketCost    = $storedPayload['ticket_cost'] ?? 0;

$engineForRecord = null;
$interpreterForRecord = null;
$getEngineForRecord = function () use (&$engineForRecord, $record) {
    if ($engineForRecord === null) {
        $engineForRecord = new SajuEngine(
            (int)$record['birth_year'],
            (int)$record['birth_month'],
            (int)$record['birth_day'],
            (int)$record['birth_hour'],
            $record['gender'],
            $record['calendar_type']
        );
    }

    return $engineForRecord;
};
$getInterpreterForRecord = function () use (&$interpreterForRecord, $getEngineForRecord) {
    if ($interpreterForRecord === null) {
        $interpreterForRecord = new FortuneInterpreter($getEngineForRecord());
    }

    return $interpreterForRecord;
};

$sajuResult = $storedPayload['saju'] ?? null;
if (!$sajuResult) {
    $sajuResult = $getEngineForRecord()->getResult();
}

$ohangData = $storedPayload['ohang'] ?? (json_decode($record['ohang_analysis'] ?? '', true) ?: null);
if (!$ohangData && $sajuResult) {
    $ohangData = (new OhangAnalysis($getEngineForRecord()))->analyze();
}

$sipsinData = $storedPayload['sipsin'] ?? (json_decode($record['sipsin_analysis'] ?? '', true) ?: null);
if (!$sipsinData && in_array($analysisType, ['sipsin', 'comprehensive'], true)) {
    $sipsinData = $getInterpreterForRecord()->analyzeSipsin();
}

$gyeokgukData = $storedPayload['gyeokguk'] ?? (json_decode($record['gyeokguk_analysis'] ?? '', true) ?: null);
if (!$gyeokgukData && in_array($analysisType, ['gyeokguk', 'comprehensive'], true)) {
    $gyeokgukData = $getInterpreterForRecord()->analyzeGyeokguk();
}

$daeunData = $storedPayload['daeun'] ?? (json_decode($record['daeun_analysis'] ?? '', true) ?: null);
if (!$daeunData && in_array($analysisType, ['daeun', 'comprehensive'], true)) {
    $daeunData = $getInterpreterForRecord()->analyzeDaeun();
}

$seunData = $storedPayload['seun'] ?? (json_decode($record['seun_analysis'] ?? '', true) ?: null);
if (!$seunData && in_array($analysisType, ['seun', 'comprehensive'], true)) {
    $seunData = $getInterpreterForRecord()->analyzeSeun();
}

$fortuneData = $storedPayload['fortune'] ?? null;

function elClass($el) {
    return ['목'=>'wood','화'=>'fire','토'=>'earth','금'=>'metal','수'=>'water'][$el] ?? '';
}

function elementFriendlyName($element) {
    $map = [
        '목' => '나무 에너지',
        '화' => '불 에너지',
        '토' => '흙 에너지',
        '금' => '쇠 에너지',
        '수' => '물 에너지',
    ];
    return $map[$element] ?? $element;
}

function elementKeyword($element) {
    $map = [
        '목' => '성장하고 확장하는 힘',
        '화' => '표현하고 빛을 내는 힘',
        '토' => '중심을 잡고 버티는 힘',
        '금' => '판단하고 정리하는 힘',
        '수' => '흐름을 읽고 유연하게 움직이는 힘',
    ];
    return $map[$element] ?? $element;
}

function simplifyStrengthLabel($label) {
    $label = trim((string)$label);
    if ($label === '') return '';
    if (strpos($label, '신강') !== false) return '에너지가 강하게 밀고 나가는 편';
    if (strpos($label, '신약') !== false) return '에너지가 섬세하게 반응하는 편';
    return $label;
}

function trimSnippet($text, $length = 96) {
    $plain = trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', strip_tags((string)$text))));
    if ($plain === '') return '';
    $textLength = function_exists('mb_strlen') ? mb_strlen($plain) : strlen($plain);
    if ($textLength <= $length) return $plain;

    $sentences = preg_split('/(?<=[.!?])\s+/u', $plain);
    $snippet = '';
    foreach ($sentences as $sentence) {
        $candidate = trim($snippet === '' ? $sentence : $snippet . ' ' . $sentence);
        $candidateLength = function_exists('mb_strlen') ? mb_strlen($candidate) : strlen($candidate);
        if ($candidateLength > $length) break;
        $snippet = $candidate;
    }

    if ($snippet !== '') {
        return $snippet;
    }

    $snippet = function_exists('mb_substr') ? mb_substr($plain, 0, $length) : substr($plain, 0, $length);
    $snippet = preg_replace('/\s+\S*$/u', '', $snippet);
    return rtrim($snippet) . '...';
}

function fortuneTextLength($content) {
    $text = is_string($content) ? $content : ($content['content'] ?? '');
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$text)));
    return function_exists('mb_strlen') ? mb_strlen($plain) : strlen($plain);
}

function fortuneDataStats($fortuneData) {
    $total = 0;
    $count = 0;

    if (!is_array($fortuneData)) {
        return ['total' => 0, 'count' => 0, 'average' => 0];
    }

    foreach ($fortuneData as $key => $content) {
        if ($key !== '' && $key[0] === '_') continue;
        $length = fortuneTextLength($content);
        if ($length <= 0) continue;
        $total += $length;
        $count++;
    }

    return [
        'total' => $total,
        'count' => $count,
        'average' => $count > 0 ? ($total / $count) : 0,
    ];
}

function isLegacyFortuneData($fortuneData) {
    if (empty($fortuneData) || !is_array($fortuneData)) return true;

    $stats = fortuneDataStats($fortuneData);
    $engineVersion = $fortuneData['_meta']['engine_version'] ?? '';

    if ($stats['count'] === 0) return true;
    if (strpos($engineVersion, 'v4_') === 0 && $stats['total'] >= 1200) return false;

    return $stats['average'] < 380;
}

function fortuneTextBlocks($text) {
    $text = trim(replaceTechnicalTerms((string)$text));
    if ($text === '') return [];

    $text = preg_replace('/\r\n?|\n/u', "\n", $text);
    $text = preg_replace('/\n{3,}/u', "\n\n", $text);
    $blocks = preg_split('/\n{2,}/u', $text);
    $result = [];

    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') continue;

        if (preg_match('/^━━━\s*(.+?)\s*━━━\s*(.*)$/us', $block, $matches)) {
            $result[] = ['type' => 'heading', 'text' => trim($matches[1])];
            $rest = trim($matches[2]);
            if ($rest !== '') {
                $result[] = ['type' => 'paragraph', 'text' => $rest];
            }
            continue;
        }

        if (preg_match('/^[【\[](.+?)[】\]]\s*(.*)$/us', $block, $matches)) {
            $result[] = ['type' => 'heading', 'text' => trim($matches[1])];
            $rest = trim($matches[2]);
            if ($rest !== '') {
                $result[] = ['type' => 'paragraph', 'text' => $rest];
            }
            continue;
        }

        $result[] = ['type' => 'paragraph', 'text' => $block];
    }

    return $result;
}

function fortunePreviewLimit($sectionKey, $textLength) {
    if ($textLength <= 1200) return 99;

    $map = [
        'personality' => 6,
        'career' => 6,
        'life_flow' => 5,
        'love' => 4,
        'wealth' => 4,
        'health' => 4,
        'study' => 4,
    ];

    return $map[$sectionKey] ?? 4;
}

function simplifyRelationType($type) {
    $map = [
        '육합' => '서로 잘 맞는 흐름',
        '삼합' => '함께 힘이 커지는 흐름',
        '방합' => '같은 방향으로 모이는 흐름',
        '충' => '변화가 크게 일어나는 흐름',
        '형' => '긴장과 압박이 생기는 흐름',
        '해' => '오해나 흔들림이 생기기 쉬운 흐름',
        '파' => '예상 밖 균열이 생기는 흐름',
        '천간합' => '겉으로 잘 맞아 보이는 흐름',
        '천간충' => '겉으로 부딪히기 쉬운 흐름',
    ];
    return $map[$type] ?? $type;
}

function groupIntensityLabel($value) {
    if ($value >= 2.8) return '강하게 드러남';
    if ($value >= 1.5) return '균형 있게 보임';
    return '은은하게 깔림';
}

function elementPercent($weighted, $element) {
    $total = array_sum($weighted) ?: 1;
    return round((($weighted[$element] ?? 0) / $total) * 100, 1);
}

function elementLevelFromPercent($percent) {
    if ($percent >= 35) return '아주 강한 편';
    if ($percent >= 23) return '꽤 있는 편';
    if ($percent >= 14) return '적당한 편';
    return '조금 약한 편';
}

function formatPercentLabel($percent) {
    if ((float)$percent === 0.0) return '0%';
    return rtrim(rtrim(number_format((float)$percent, 1, '.', ''), '0'), '.') . '%';
}

function simpleSeasonFlow($text) {
    $text = replaceTechnicalTerms($text);
    $text = preg_replace('/환절기는 계절의 전환점으로, 토의 기운이 왕성합니다\./u', '지금은 계절이 바뀌는 흐름이라 중심을 잡는 힘이 비교적 강합니다.', $text);
    $text = preg_replace('/안정과 조화에 유리하지만, 화의 열정이 약해집니다\./u', '생활 리듬을 차분히 가져가면 좋고, 의욕이 처질 때는 몸을 조금 더 움직여 주는 편이 좋습니다.', $text);
    return trimSnippet($text, 110);
}

function simpleHealthLine($element, $text) {
    $map = [
        '목' => '눈의 피로, 몸의 뻣뻣함처럼 긴장감이 쌓이지 않게 스트레칭을 자주 해주세요.',
        '화' => '감정이 쉽게 달아오를 수 있으니 잠깐 쉬는 시간과 수면 리듬을 먼저 챙기는 편이 좋습니다.',
        '토' => '생활 패턴이 흔들리면 쉽게 지칠 수 있어 식사와 수면 시간을 일정하게 잡는 편이 좋습니다.',
        '금' => '예민함이 쌓이기 쉬우니 정리와 호흡을 통해 긴장을 풀어주는 것이 도움이 됩니다.',
        '수' => '기운이 쉽게 빠질 수 있어 무리한 일정 대신 회복 시간을 꼭 남겨 두는 편이 좋습니다.',
    ];
    return $map[$element] ?? trimSnippet(simplifyHealthConcernText($text), 90);
}

function periodMoodLabel($score, $isYongshin = false) {
    if ($isYongshin) return '내 편이 강해지는 시기';
    if ($score >= 70) return '기회가 크게 열리는 시기';
    if ($score >= 50) return '안정적으로 흐르는 시기';
    if ($score >= 35) return '조정이 필요한 시기';
    return '무리하지 말고 힘을 아껴야 할 시기';
}

function simplifyRuleLevel($level) {
    $map = [
        '대길' => '매우 좋음',
        '길' => '좋음',
        '주의' => '주의',
        '경고' => '경고',
        '갈등' => '균형 필요',
        '양면' => '장점과 주의점 함께',
        '참고' => '참고',
        '종합' => '종합',
    ];
    return $map[$level] ?? $level;
}

function simplifyRuleTitle($title) {
    $title = trim((string)$title);
    if ($title === '') return '';
    if (strpos($title, '—') !== false) {
        $parts = explode('—', $title, 2);
        return trim($parts[1]);
    }
    $title = preg_replace('/\([^)]*\)/u', '', $title);
    return trim(preg_replace('/\s+/u', ' ', $title));
}

function buildHiddenEnergySummary($jijanggan, $sipsinPillars = []) {
    if (empty($jijanggan) || !is_array($jijanggan)) return [];

    $elementTraits = [
        '목' => ['label' => '성장과 확장', 'desc' => '새로운 것을 키우고 앞으로 나아가게 하는 힘'],
        '화' => ['label' => '표현과 열정', 'desc' => '감정과 에너지를 바깥으로 드러내는 힘'],
        '토' => ['label' => '안정과 현실감', 'desc' => '중심을 잡고 현실을 챙기게 하는 힘'],
        '금' => ['label' => '판단과 정리', 'desc' => '기준을 세우고 정리하게 하는 힘'],
        '수' => ['label' => '유연함과 통찰', 'desc' => '흐름을 읽고 유연하게 움직이게 하는 힘'],
    ];
    $roleTraits = [
        '비견' => '자기 주도성',
        '겁재' => '승부욕',
        '식신' => '재능 표현력',
        '상관' => '독창성',
        '편재' => '기회 포착력',
        '정재' => '현실 감각',
        '편관' => '도전 정신',
        '정관' => '책임감',
        '편인' => '직감',
        '정인' => '배움과 이해력',
    ];
    $pillarAreas = [
        'year' => '처음 만나는 환경과 가족 분위기',
        'month' => '사회생활과 일하는 방식',
        'day' => '내면과 가까운 관계',
        'hour' => '미래 계획과 바라는 삶',
    ];

    $elementWeights = [];
    $roleWeights = [];
    $pillarHighlights = [];

    foreach ($pillarAreas as $key => $area) {
        $items = $jijanggan[$key] ?? [];
        if (empty($items) || !is_array($items)) continue;

        usort($items, function ($left, $right) {
            return ($right['ratio'] ?? 0) <=> ($left['ratio'] ?? 0);
        });

        $top = $items[0] ?? null;
        if (!empty($top['element']) && isset($elementTraits[$top['element']])) {
            $pillarHighlights[] = [
                'label' => $area,
                'text' => $elementTraits[$top['element']]['desc'] . '이 비교적 강하게 숨어 있습니다.',
            ];
        }

        foreach ($items as $item) {
            $element = $item['element'] ?? null;
            if (!$element) continue;
            $elementWeights[$element] = ($elementWeights[$element] ?? 0) + (float)($item['ratio'] ?? 0);
        }
    }

    foreach ($sipsinPillars as $pillar) {
        foreach (($pillar['jijanggan_sipsin'] ?? []) as $item) {
            $role = $item['sipsin'] ?? null;
            if (!$role) continue;
            $roleWeights[$role] = ($roleWeights[$role] ?? 0) + (float)($item['ratio'] ?? 0);
        }
    }

    arsort($elementWeights);
    arsort($roleWeights);

    $topElements = array_slice(array_keys($elementWeights), 0, 2);
    $topRoles = array_slice(array_keys($roleWeights), 0, 2);
    $summaryParts = [];

    if ($topElements) {
        $elementLabels = [];
        foreach ($topElements as $element) {
            $elementLabels[] = $elementTraits[$element]['label'];
        }
        $summaryParts[] = '겉으로 보이는 모습 뒤에는 ' . implode(', ', $elementLabels) . ' 흐름이 숨어 있어 상황에 따라 예상보다 다른 매력이 드러납니다.';
    }

    if ($topRoles) {
        $roleLabels = [];
        foreach ($topRoles as $role) {
            $roleLabels[] = $roleTraits[$role] ?? $role;
        }
        $summaryParts[] = '내면에서는 특히 ' . implode(', ', $roleLabels) . '이 함께 작동해 한 가지 성격만으로 설명되지 않는 깊이가 있습니다.';
    }

    return [
        'summary' => implode("\n\n", $summaryParts),
        'pillars' => $pillarHighlights,
    ];
}

function normalizeGod($god) {
    if (!is_array($god)) return null;
    if (isset($god['element'])) return $god;
    if (isset($god['primary'])) return ['element'=>$god['primary'],'type'=>$god['reason'] ?? ''];
    return null;
}

function replaceTechnicalTerms($text) {
    $text = strtr((string)$text, [
        '사주팔자(四柱八字)' => '사주 네 기둥',
        '격국(格局)' => '사주의 중심 구조',
        '관인상생(官印相生)' => '관인상생 구조(책임과 배움이 함께 자라는 흐름)',
        '살인상생(殺印相生)' => '살인상생 구조(압박을 실력과 공부로 바꾸는 흐름)',
        '식신생재(食神生財)' => '식신생재 구조(재능이 돈으로 이어지는 흐름)',
        '상관생재(傷官生財)' => '상관생재 구조(표현력과 아이디어가 수익으로 이어지는 흐름)',
        '재생관(財生官)' => '재생관 구조(현실 감각이 지위와 성취를 밀어주는 흐름)',
        '관살혼잡(官殺混雜)' => '관살혼잡 구조(책임과 압박이 함께 몰리는 흐름)',
        '쇠약운성(衰弱運星)' => '쇠약운성(힘을 아끼며 안을 다져야 하는 흐름)',
        '큰 나무처럼 강인한 의지와 추진력을 가졌습니다. 주변을 이끄는 리더십이 있으나, 고집이 세다는 평을 들을 수 있습니다. 기운을 식상(화)으로 발산하면 균형이 잡힙니다.' => '기본 추진력과 버티는 힘이 강한 편입니다. 앞에서 방향을 잡고 이끄는 힘이 있지만, 너무 밀어붙이면 고집 세게 보일 수 있습니다. 생각만 쌓아두기보다 말하기, 표현하기, 활동으로 힘을 풀면 균형이 좋아집니다.',
        '새싹처럼 보살핌이 필요합니다. 수(水)의 도움으로 자양분을 얻고, 같은 목(木)의 지원으로 함께 성장하는 것이 중요합니다.' => '기본 힘이 섬세한 편이라 좋은 환경과 주변의 도움을 받을 때 더 잘 자랍니다. 혼자 버티기보다 배우고 기대며 천천히 힘을 키우는 방식이 잘 맞습니다.',
        '태양처럼 뜨거운 열정과 에너지를 발산합니다. 표현력이 뛰어나지만 감정 조절이 과제입니다. 토(土)로 설기하면 안정됩니다.' => '열정과 표현력이 강한 편입니다. 다만 감정이 빨리 올라올 수 있어, 생활 리듬을 안정적으로 잡으면 훨씬 편안해집니다.',
        '촛불처럼 바람에 흔들리기 쉽습니다. 목(木)이 연료가 되어주고, 같은 화(火)의 온기를 모아야 합니다.' => '기분과 컨디션이 환경의 영향을 받기 쉬운 편입니다. 혼자 버티기보다 응원해 주는 사람과 따뜻한 분위기 속에서 힘을 모으면 좋습니다.',
        '큰 산처럼 묵직하고 안정적인 중심을 잡습니다. 포용력이 크지만 변화에 둔할 수 있습니다. 금(金)으로 설기하면 활력을 되찾습니다.' => '중심을 잘 잡고 쉽게 흔들리지 않는 편입니다. 다만 변화가 느리게 느껴질 수 있어, 정리와 결단을 자주 해주면 활력이 살아납니다.',
        '흙이 흩어지기 쉬운 상태입니다. 화(火)가 토를 굳혀주고, 같은 토의 뭉침으로 안정을 찾아야 합니다.' => '기본 체력과 중심이 쉽게 흔들릴 수 있습니다. 생활 패턴을 일정하게 잡고, 몸과 마음을 따뜻하게 유지하면 안정감을 찾기 쉽습니다.',
        '강철처럼 날카롭고 정확한 판단력을 지녔습니다. 결단력이 뛰어나지만 유연함을 길러야 합니다. 수(水)로 설기하면 부드러워집니다.' => '판단이 빠르고 분명한 편입니다. 다만 너무 단호하게 보일 수 있어, 한 템포 부드럽게 표현하면 관계가 훨씬 편해집니다.',
        '금박처럼 외부 충격에 약합니다. 토(土)가 금을 보호해주고, 같은 금의 단단함이 필요합니다.' => '예민하게 상처를 받기 쉬운 편입니다. 기본 생활을 단단히 만들고, 믿을 만한 기준을 세우면 흔들림이 줄어듭니다.',
        '바다처럼 깊은 지혜와 포용력을 가졌습니다. 적응력이 뛰어나지만 방향을 잡는 것이 과제입니다. 목(木)으로 설기하면 목표가 생깁니다.' => '이해력과 적응력이 좋은 편입니다. 다만 생각이 많아 방향이 흐려질 수 있어, 목표를 하나씩 분명히 잡아 가는 것이 중요합니다.',
        '이슬처럼 쉽게 증발합니다. 금(金)이 수를 생해주고, 같은 수의 흐름으로 힘을 모아야 합니다.' => '기운이 쉽게 빠질 수 있어 혼자 오래 버티기 힘들 수 있습니다. 쉬는 시간과 회복 습관을 분명히 두면 컨디션 관리에 도움이 됩니다.',
        '월령(月令)의 기운을 얻어 득령(得令) 상태입니다.' => '태어난 계절의 도움을 받는 편입니다.',
        '월령의 기운을 얻지 못해 실령(失令) 상태입니다.' => '태어난 계절의 도움은 크지 않은 편입니다.',
        '용신(用神)' => '가장 필요한 에너지',
        '희신(喜神)' => '도움을 더해주는 에너지',
        '기신(忌神)' => '주의해야 할 에너지',
        '구신(仇神)' => '함께 부담을 키우는 에너지',
        '일간(日干)' => '나를 대표하는 기운',
        '신강(身强)' => '에너지가 강한 편',
        '신약(身弱)' => '에너지가 섬세한 편',
        '월령(月令)' => '태어난 계절',
        '득령(得令)' => '계절의 도움',
        '천간' => '겉으로 보이는 흐름',
        '지지' => '바탕 흐름',
        '식상(화)' => '표현하고 움직이는 활동',
        '식상' => '표현과 활동',
        '12운성' => '에너지 단계',
        '합충형파해' => '관계 변화',
        '비겁(比劫)' => '자기 주도성',
        '식상(食傷)' => '표현력',
        '재성(財星)' => '현실 감각',
        '관성(官星)' => '책임감',
        '인성(印星)' => '배움과 통찰',
    ]);
    $text = str_replace('당신의 사주 네 기둥를 분석합니다.', '당신의 사주 네 기둥을 분석합니다.', $text);
    $text = str_replace('당신의 나를 대표하는 기운은', '당신을 대표하는 기운은', $text);
    $text = str_replace('사주의 중심 구조의 관점에서', '사주의 중심 구조를 보면', $text);
    $text = preg_replace('/지지에 (\d+)개의 뿌리\(根\)를 두고 있어 기반이 튼튼합니다\./u', '기초 체력을 받쳐 주는 기반이 $1곳에서 단단하게 잡혀 있습니다.', $text);
    $text = preg_replace('/지지에 (\d+)개의 뿌리\(根\)를 두고 있어 어느 정도 지지를 받고 있습니다\./u', '기초 체력을 받쳐 주는 기반이 $1곳 있어 어느 정도 버틸 힘이 있습니다.', $text);
    $text = str_replace('지지에 뿌리가 없어 기반이 약합니다.', '기초 체력을 받쳐 주는 기반이 약한 편이라 주변 도움과 환경의 영향이 중요합니다.', $text);
    $text = preg_replace('/[📌🔗💡✨⚡💛🏆🎯📚💬🌟☀️☁️🌧️⛈️📝💊]+/u', '', $text);
    $text = preg_replace('/\n{3,}/u', "\n\n", $text);
    return trim($text);
}

function simpleSipsinText($sipsin) {
    $map = [
        '비견' => '내 힘을 믿고 밀고 나가는 마음이 커집니다.',
        '겁재' => '경쟁심과 승부욕이 강해지기 쉽습니다.',
        '식신' => '재능과 표현이 자연스럽게 나오는 흐름입니다.',
        '상관' => '틀을 깨고 자유롭게 말하고 싶은 흐름입니다.',
        '편재' => '사람과 기회를 넓히는 흐름입니다.',
        '정재' => '생활을 안정시키고 차곡차곡 챙기는 흐름입니다.',
        '편관' => '압박 속에서도 도전하며 단단해지는 흐름입니다.',
        '정관' => '책임과 역할이 커지는 흐름입니다.',
        '편인' => '혼자 깊게 파고들며 생각이 많아지는 흐름입니다.',
        '정인' => '배우고 정리하며 마음을 다지는 흐름입니다.',
    ];
    return $map[$sipsin] ?? ($sipsin ? $sipsin . '의 흐름입니다.' : '');
}

function topGroupKeys($groupTotals, $limit = 2) {
    if (empty($groupTotals) || !is_array($groupTotals)) return [];
    $sorted = $groupTotals;
    arsort($sorted);
    return array_slice(array_keys($sorted), 0, $limit);
}

function groupSummarySentence($groupKey) {
    $map = [
        '비겁(比劫)' => '자기 생각이 뚜렷해서 주도권을 잡고 움직이려는 편입니다.',
        '식상(食傷)' => '생각을 말이나 결과물로 보여주는 힘이 좋은 편입니다.',
        '재성(財星)' => '돈과 기회, 현실 문제를 빨리 챙기는 감각이 있습니다.',
        '관성(官星)' => '맡은 역할과 책임을 쉽게 놓지 않는 편입니다.',
        '인성(印星)' => '배우고 이해하고 정리하는 힘이 좋은 편입니다.',
    ];
    return $map[$groupKey] ?? '';
}

function buildStrengthSummary($dms) {
    if (empty($dms) || !is_array($dms)) return [];

    $lines = [];
    $isStrong = !empty($dms['is_strong']);
    $yongshin = normalizeGod($dms['yongshin'] ?? null);
    $roots = (int)($dms['roots'] ?? 0);

    $lines[] = $isStrong
        ? '기본 힘이 충분해서 혼자 결정을 내리고 밀고 나가는 편입니다.'
        : '기본 힘이 섬세해서 좋은 환경과 사람을 만나면 더 잘 풀리는 편입니다.';

    if (!empty($dms['deukryeong']) || $roots > 0) {
        $parts = [];
        if (!empty($dms['deukryeong'])) $parts[] = '태어난 계절의 도움을 받는 편입니다';
        if ($roots > 0) $parts[] = '버틸 힘이 되는 뿌리가 ' . $roots . '개 있습니다';
        $lines[] = implode(', ', $parts) . '.';
    }

    if ($yongshin && !empty($yongshin['element'])) {
        $lines[] = elementFriendlyName($yongshin['element']) . ' 쪽 습관을 더하면 마음과 생활의 균형을 잡기 쉽습니다.';
    }

    return array_values(array_unique(array_filter($lines)));
}

function buildGodsGuide($dms) {
    if (empty($dms) || !is_array($dms)) return [];

    $isStrong = !empty($dms['is_strong']);
    $yongshin = normalizeGod($dms['yongshin'] ?? null);
    $gishin = normalizeGod($dms['gishin'] ?? null);
    $lines = [];

    $lines[] = $isStrong
        ? '핵심은 넘치는 힘을 쌓아두기보다 바깥으로 건강하게 풀어내는 것입니다.'
        : '핵심은 부족한 힘을 혼자 버티기보다 주변 도움으로 채우는 것입니다.';

    if ($yongshin && !empty($yongshin['element'])) {
        $lines[] = '특히 ' . elementFriendlyName($yongshin['element']) . ' 쪽 활동과 환경이 컨디션을 안정시키는 데 도움이 됩니다.';
    }

    if ($gishin && !empty($gishin['element'])) {
        $lines[] = elementFriendlyName($gishin['element']) . '가 너무 강해질 때는 피로감이나 무리가 쌓이기 쉬우니 속도를 조금 늦추는 것이 좋습니다.';
    }

    return array_values(array_unique(array_filter($lines)));
}

function dedupeRelationships($relationships) {
    if (empty($relationships) || !is_array($relationships)) return [];

    $seen = [];
    $result = [];
    foreach ($relationships as $rel) {
        $key = simplifyRelationType($rel['type'] ?? '');
        if ($key === '' || isset($seen[$key])) continue;
        $seen[$key] = true;
        $result[] = $rel;
        if (count($result) >= 4) break;
    }

    return $result;
}

function buildRelationSummary($rel) {
    $map = [
        '육합' => '사람이나 기회가 자연스럽게 붙기 쉬운 흐름입니다. 혼자보다 함께할 때 힘이 커집니다.',
        '삼합' => '서로 힘을 보태며 큰 흐름을 만드는 관계입니다. 팀플레이가 잘 맞을 수 있습니다.',
        '방합' => '비슷한 방향으로 힘이 모이는 흐름입니다. 한쪽으로 마음이 쏠릴 수 있으니 중심만 잡으면 됩니다.',
        '충' => '갑자기 방향이 바뀌거나 부딪히는 일이 생길 수 있습니다. 갈등보다 변화의 신호로 보는 편이 좋습니다.',
        '형' => '예민해지기 쉬운 흐름입니다. 서두르기보다 한 번 더 확인하는 태도가 도움이 됩니다.',
        '해' => '겉으로 티가 안 나도 속으로 서운함이 쌓일 수 있습니다. 마음에 걸리는 관계는 빨리 풀어두는 편이 좋습니다.',
        '파' => '계획이 어긋날 수 있으니 예비안을 준비해 두면 훨씬 편합니다.',
        '천간합' => '첫인상이나 겉모습에서는 잘 맞아 보이는 흐름입니다. 금방 친해지기 쉽습니다.',
        '천간충' => '말이나 태도에서 부딪힘이 생기기 쉬운 흐름입니다. 표현 방식을 부드럽게 하면 갈등을 줄일 수 있습니다.',
    ];
    return $map[$rel['type'] ?? ''] ?? trimSnippet(replaceTechnicalTerms($rel['meaning'] ?? ''), 100);
}

function simplifyHealthConcernText($text) {
    $text = replaceTechnicalTerms($text);
    $text = preg_replace('/([목화토금수])\s+\1\([^)]+\)이\(가\)/u', '$1 에너지가', $text);
    $text = preg_replace('/([목화토금수])\([^)]+\)/u', '$1', $text);
    return trimSnippet($text, 110);
}

function buildBalanceSummary($ohangData) {
    if (empty($ohangData) || !is_array($ohangData)) return [];

    $weighted = $ohangData['weighted_ohang_count'] ?? ($ohangData['ohang_count'] ?? []);
    if (empty($weighted)) return [];

    $total = array_sum($weighted) ?: 1;
    $sorted = $weighted;
    arsort($sorted);
    $keys = array_keys($sorted);
    $topEl = $keys[0] ?? null;
    $lowEl = $keys ? $keys[count($keys) - 1] : null;

    $adviceMap = [
        '목' => '새로운 일을 한꺼번에 벌이기보다 하나씩 키우는 습관이 도움이 됩니다.',
        '화' => '감정이 올라올 때 말의 속도를 조금 늦추면 균형이 좋아집니다.',
        '토' => '생활 리듬을 일정하게 잡고 기본 체력을 챙기면 균형이 좋아집니다.',
        '금' => '정리 정돈과 규칙적인 습관이 컨디션을 받쳐 줍니다.',
        '수' => '생각만 오래 돌리지 말고 쉬는 시간을 분명히 나누면 좋습니다.',
    ];

    $summary = ['lines' => [], 'season' => '', 'health' => [], 'supplements' => [], 'top' => null, 'low' => null, 'weighted' => $weighted];

    if ($topEl) {
        $summary['top'] = ['element' => $topEl, 'percent' => elementPercent($weighted, $topEl)];
        $summary['lines'][] = '가장 강한 쪽은 ' . elementFriendlyName($topEl) . '입니다. ' . elementKeyword($topEl) . '이 자연스럽게 드러납니다. (' . round($weighted[$topEl] / $total * 100, 1) . '%)';
    }

    if ($lowEl) {
        $summary['low'] = ['element' => $lowEl, 'percent' => elementPercent($weighted, $lowEl)];
        $summary['lines'][] = '조금 더 챙기면 좋은 쪽은 ' . elementFriendlyName($lowEl) . '입니다. ' . ($adviceMap[$lowEl] ?? '기본 생활 리듬을 일정하게 잡아 보세요.');
    }

    if (!empty($ohangData['season_analysis']['season_description'])) {
        $summary['season'] = simpleSeasonFlow(preg_replace('/\([^)]+\)/u', '', $ohangData['season_analysis']['season_description']));
    }

    if (!empty($ohangData['health_analysis']) && is_array($ohangData['health_analysis'])) {
        foreach ($ohangData['health_analysis'] as $element => $health) {
            if (empty($health['concern'])) continue;
            $summary['health'][] = [
                'element' => $element,
                'text' => simpleHealthLine($element, $health['concern']),
            ];
            if (count($summary['health']) >= 1) break;
        }
    }

    if (!empty($ohangData['supplement']) && is_array($ohangData['supplement'])) {
        foreach ($ohangData['supplement'] as $advice) {
            if (empty($advice['methods']) || !is_array($advice['methods'])) continue;
            $summary['supplements'][] = [
                'element' => $advice['element'] ?? '',
                'text' => implode(' · ', array_slice($advice['methods'], 0, 2)),
            ];
            if (count($summary['supplements']) >= 1) break;
        }
    }

    return $summary;
}

function gyeokgukFriendlyName($gyeokgukData) {
    $nameMap = [
        'seal'          => '인성격',
        'indirect_seal' => '편인격',
        'officer'       => '정관격',
        'kill'          => '편관격',
        'output'        => '식신격',
        'hurt'          => '상관격',
        'wealth'        => '정재격',
        'power'         => '편재격',
        'rival'         => '비견격',
        'rob'           => '겁재격',
    ];
    $key = $gyeokgukData['detail_gyeokguk'] ?? '';
    return $nameMap[$key] ?? ($gyeokgukData['name'] ?? preg_replace('/\([^)]*\)/u', '', $gyeokgukData['type_name'] ?? ''));
}

function gyeokgukFriendlyDesc($gyeokgukData) {
    $descMap = [
        'seal'          => '배움과 이해·통찰이 삶의 중심에 있는 흐름입니다.',
        'indirect_seal' => '직감과 깊은 사색이 삶의 방향을 이끕니다.',
        'officer'       => '책임감과 바른 역할이 삶의 중심에 있는 흐름입니다.',
        'kill'          => '도전과 성취를 통해 단단해지는 흐름입니다.',
        'output'        => '재능을 표현하고 풀어내는 것이 삶의 중심입니다.',
        'hurt'          => '창의성과 자유로운 표현이 삶의 원동력입니다.',
        'wealth'        => '현실 감각과 안정을 중시하는 흐름입니다.',
        'power'         => '기회를 넓히고 확장하는 것이 삶의 중심입니다.',
        'rival'         => '독립심과 주도력이 강하게 드러나는 흐름입니다.',
        'rob'           => '경쟁과 추진력으로 나아가는 흐름입니다.',
    ];
    $key = $gyeokgukData['detail_gyeokguk'] ?? '';
    return $descMap[$key] ?? ($gyeokgukData['description'] ?? '');
}

function dominantSipsinFriendlyLabel($sipsin) {
    $map = [
        '비견' => '독립심이 강하고 주도적인 성향',
        '겁재' => '경쟁심이 강하고 추진력이 있는 성향',
        '식신' => '재능과 표현이 풍부한 성향',
        '상관' => '창의적이고 독창적인 성향',
        '편재' => '기회 포착력이 뛰어난 성향',
        '정재' => '현실적이고 안정을 추구하는 성향',
        '편관' => '도전과 성취를 통해 성장하는 성향',
        '정관' => '규칙과 책임을 중히 여기는 성향',
        '편인' => '직감이 뛰어나고 깊이 생각하는 성향',
        '정인' => '배움을 좋아하고 이해력이 깊은 성향',
    ];
    return $map[$sipsin] ?? $sipsin;
}

function balanceScoreLabel($score) {
    $score = (int)$score;
    if ($score >= 80) return '매우 균형적';
    if ($score >= 60) return '균형 잡힌 편';
    if ($score >= 40) return '약간 치우친 편';
    if ($score >= 20) return '한쪽이 강함';
    return '불균형이 큰 편';
}

function buildLifeThemeSummary($gyeokgukData) {
    if (empty($gyeokgukData) || !is_array($gyeokgukData)) return [];

    $lines = [];
    $desc = gyeokgukFriendlyDesc($gyeokgukData);
    if ($desc) $lines[] = $desc;

    if (!empty($gyeokgukData['interpretation'])) {
        $text = replaceTechnicalTerms($gyeokgukData['interpretation']);
        foreach (preg_split('/(?<=[.!?。])\s+/u', $text) as $sentence) {
            $sentence = trim($sentence, " \t\n\r\0\x0B-•「」");
            if ($sentence === '' || mb_strlen($sentence) < 15) continue;
            if (preg_match('/격국|신강|신약|사주|용신|기신|인성격|편관격|식신격|당신의/u', $sentence)) continue;
            $lines[] = trimSnippet($sentence, 110);
            if (count($lines) >= 3) break;
        }
    }

    if (count($lines) < 2 && !empty($gyeokgukData['yongsin']['description'])) {
        $lines[] = trimSnippet(replaceTechnicalTerms($gyeokgukData['yongsin']['description']), 100);
    }

    return array_values(array_unique(array_filter($lines)));
}

function buildDaeunSummary($du) {
    $lines = [];
    $score = $du['score'] ?? 50;
    $lines[] = !empty($du['is_yongshin'])
        ? '도움이 들어오는 10년이라 새로운 시도를 해볼 만합니다.'
        : ($score >= 50 ? '무리하지 않으면 비교적 안정적으로 갈 수 있는 10년입니다.' : '속도를 줄이고 기초를 챙기는 편이 좋은 10년입니다.');

    if (!empty($du['stem_sipsin'])) $lines[] = '겉으로는 ' . simpleSipsinText($du['stem_sipsin']);
    if (!empty($du['branch_sipsin'])) $lines[] = '속으로는 ' . simpleSipsinText($du['branch_sipsin']);

    return array_slice(array_values(array_unique(array_filter($lines))), 0, 3);
}

function buildSeunSummary($su) {
    $lines = [];
    $score = $su['score'] ?? ($su['total_score'] ?? 50);
    $lines[] = !empty($su['is_yongshin'])
        ? '도움이 들어오는 해라 평소 미뤄 둔 일을 시작해 보기 좋습니다.'
        : ($score >= 50 ? '큰 무리 없이 현재 것을 지켜 가기 좋은 해입니다.' : '욕심을 줄이고 몸과 마음을 정리하는 편이 좋은 해입니다.');

    if (!empty($su['stem_sipsin'])) $lines[] = '겉으로는 ' . simpleSipsinText($su['stem_sipsin']);
    if (!empty($su['branch_sipsin'])) $lines[] = '속으로는 ' . simpleSipsinText($su['branch_sipsin']);

    return array_slice(array_values(array_unique(array_filter($lines))), 0, 3);
}

function buildFortuneSummary($sectionKey, $context = []) {
    $groupTotals = $context['groups'] ?? [];
    $dms = $context['dms'] ?? [];
    $dayElement = $context['dayElement'] ?? '';
    $ohangData = $context['ohang'] ?? [];
    $daeunData = $context['daeun'] ?? [];
    $yongshinEl = $context['yongshinEl'] ?? '';

    switch ($sectionKey) {
        case 'personality':
            $lines = [
                ($dayElement === '화' ? '기본적으로 밝고 표현이 빠르며 분위기를 살리는 힘이 큽니다.' : '기본 성향은 타고난 기운의 색이 분명한 편입니다.'),
                !empty($dms['is_strong']) ? '힘이 충분해서 스스로 밀고 나가는 편이지만, 너무 빨라질 때는 속도 조절이 필요합니다.' : '힘이 섬세해서 혼자 버티기보다 좋은 환경을 만날 때 더 잘 풀립니다.',
            ];
            foreach (topGroupKeys($groupTotals, 1) as $groupKey) {
                $summary = groupSummarySentence($groupKey);
                if ($summary) $lines[] = $summary;
            }
            return array_slice(array_values(array_unique(array_filter($lines))), 0, 3);
        case 'love':
            $lines = ['관계에서는 누가 이기느냐보다 서로의 속도와 말투를 맞추는 것이 중요합니다.'];
            if (($groupTotals['식상(食傷)'] ?? 0) >= 2.0) $lines[] = '마음을 표현하는 힘은 좋지만, 말이 급해질 때는 오해가 생기기 쉬우니 한 번 더 부드럽게 말하면 좋습니다.';
            if (($groupTotals['인성(印星)'] ?? 0) >= 2.0) $lines[] = '말이 잘 통하고 배울 점이 있는 사람에게 끌리기 쉽습니다.';
            return array_slice(array_values(array_unique(array_filter($lines))), 0, 3);
        case 'career':
            $lines = ['잘 맞는 일은 오래 해도 지치지 않는 일, 그리고 내 강점이 바로 보이는 일입니다.'];
            foreach (topGroupKeys($groupTotals, 1) as $groupKey) {
                $map = [
                    '비겁(比劫)' => '독립적으로 움직일 수 있는 일',
                    '식상(食傷)' => '기획·표현·창작이 들어가는 일',
                    '재성(財星)' => '영업·사업·돈의 흐름을 다루는 일',
                    '관성(官星)' => '조직·관리·책임이 분명한 일',
                    '인성(印星)' => '교육·상담·연구처럼 배움이 필요한 일',
                ];
                if (!empty($map[$groupKey])) $lines[] = '특히 ' . $map[$groupKey] . ' 쪽이 잘 맞습니다.';
            }
            if ($yongshinEl) $lines[] = elementFriendlyName($yongshinEl) . '와 연결된 환경이 컨디션을 살리는 데 도움이 됩니다.';
            return array_slice(array_values(array_unique(array_filter($lines))), 0, 3);
        case 'wealth':
            $lines = [
                (($groupTotals['재성(財星)'] ?? 0) >= 2.0) ? '돈과 기회를 보는 감각이 괜찮은 편입니다.' : '한 번에 크게 벌기보다 꾸준히 쌓아 가는 방식이 더 잘 맞습니다.',
                (($groupTotals['비겁(比劫)'] ?? 0) >= 2.0) ? '버는 힘만큼 쓰는 속도도 빨라질 수 있으니 자동저축 같은 관리 장치가 도움이 됩니다.' : '생활 리듬을 안정적으로 유지하면 재물 흐름도 함께 안정되기 쉽습니다.',
            ];
            return array_slice(array_values(array_unique(array_filter($lines))), 0, 3);
        case 'study':
            $lines = [
                (($groupTotals['인성(印星)'] ?? 0) >= 2.0) ? '이해하고 정리하는 힘이 좋아서 차근차근 배우면 실력이 잘 쌓입니다.' : '이론만 오래 보기보다 직접 해보면서 배우는 방식이 더 잘 맞습니다.',
                (($groupTotals['식상(食傷)'] ?? 0) >= 2.0) ? '배운 내용을 말하거나 써보면 훨씬 빨리 내 것이 됩니다.' : '목표를 작게 나눠서 하나씩 끝내는 방식이 더 잘 맞습니다.',
            ];
            return array_slice(array_values(array_unique(array_filter($lines))), 0, 3);
        case 'health':
            $summary = buildBalanceSummary($ohangData);
            $lines = [];
            if (!empty($summary['health'][0]['text'])) $lines[] = '몸에서는 특히 ' . $summary['health'][0]['text'];
            if (!empty($summary['supplements'][0])) $lines[] = elementFriendlyName($summary['supplements'][0]['element']) . '를 보완하는 생활 습관을 챙기면 도움이 됩니다.';
            if ($yongshinEl) $lines[] = '컨디션이 떨어질 때는 ' . elementFriendlyName($yongshinEl) . '를 살리는 생활 습관을 먼저 챙겨 보세요.';
            return array_slice(array_values(array_unique(array_filter($lines))), 0, 3);
        case 'life_flow':
            $daeuns = $daeunData['daeuns'] ?? [];
            if (!$daeuns) return [];
            $best = $daeuns[0];
            $lowest = $daeuns[0];
            foreach ($daeuns as $du) {
                if (($du['score'] ?? 50) > ($best['score'] ?? 50)) $best = $du;
                if (($du['score'] ?? 50) < ($lowest['score'] ?? 50)) $lowest = $du;
            }
            return [
                $best['age_start'] . '~' . $best['age_end'] . '세에는 ' . periodMoodLabel($best['score'] ?? 50, !empty($best['is_yongshin'])) . ' 흐름이 비교적 강합니다.',
                $lowest['age_start'] . '~' . $lowest['age_end'] . '세에는 무리하지 않고 기본을 챙기는 편이 더 좋습니다.',
                '전체적으로는 빠르게 달릴 때와 쉬어갈 때의 차이를 잘 보는 것이 중요합니다.',
            ];
    }

    return [];
}

function upgradeFortuneDataIfNeeded($fortuneData, $analysisType, $record) {
    $fortuneTypes = ['comprehensive', 'love', 'career', 'wealth'];
    if (!in_array($analysisType, $fortuneTypes, true) && empty($fortuneData)) {
        return [$fortuneData, false];
    }

    if (!isLegacyFortuneData($fortuneData) && !empty($fortuneData)) {
        return [$fortuneData, false];
    }

    try {
        $engine = new SajuEngine(
            (int)$record['birth_year'],
            (int)$record['birth_month'],
            (int)$record['birth_day'],
            (int)$record['birth_hour'],
            $record['gender'],
            $record['calendar_type']
        );
        $interpreter = new FortuneInterpreter($engine);
        $generatedFortune = $interpreter->getComprehensiveFortune();

        if ($analysisType === 'comprehensive') {
            return [$generatedFortune, true];
        }

        if (isset($generatedFortune[$analysisType])) {
            return [[
                $analysisType => $generatedFortune[$analysisType],
                '_meta' => $generatedFortune['_meta'] ?? [],
            ], true];
        }
    } catch (Throwable $e) {
        return [$fortuneData, false];
    }

    return [$fortuneData, false];
}

if ($seunData && is_array($seunData) && isset($seunData['year'])) {
    $seunData = [$seunData];
}
if ($daeunData && is_array($daeunData) && !isset($daeunData['daeuns']) && isset($daeunData['daeun_list'])) {
    $daeunData['daeuns'] = $daeunData['daeun_list'];
}

$sipsinPillars = $sipsinData['pillars_detail'] ?? ($sipsinData['pillars'] ?? []);
$dominantSipsin = $sipsinData['dominant'] ?? $sipsinData['dominant_sipsin'] ?? '';
$dominantSipsinInfo = $sipsinData['dominant_info'] ?? $sipsinData['dominant_sipsin_info'] ?? [];
$hiddenEnergySummary = buildHiddenEnergySummary($sajuResult['jijanggan'] ?? [], $sipsinPillars);
$resultHighlights = [];

if (!empty($sajuResult['day_master_strength'])) {
    $resultHighlights[] = [
        'label' => '에너지 타입',
        'value' => simplifyStrengthLabel($sajuResult['day_master_strength']['strength'] ?? ''),
        'text' => trimSnippet($sajuResult['day_master_strength']['description'] ?? ''),
    ];
}
if (!empty($hiddenEnergySummary['summary'])) {
    $resultHighlights[] = [
        'label' => '숨은 성향',
        'value' => '겉보다 더 깊은 내면 흐름',
        'text' => trimSnippet($hiddenEnergySummary['summary']),
    ];
}
if ($dominantSipsin || !empty($dominantSipsinInfo)) {
    $resultHighlights[] = [
        'label' => '가장 두드러진 성향',
        'value' => dominantSipsinFriendlyLabel($dominantSipsin),
        'text' => simpleSipsinText($dominantSipsin),
    ];
}
if (!empty($gyeokgukData)) {
    $resultHighlights[] = [
        'label' => '삶의 중심 테마',
        'value' => gyeokgukFriendlyName($gyeokgukData),
        'text' => gyeokgukFriendlyDesc($gyeokgukData),
    ];
}

$bodySummaryLines = buildStrengthSummary($sajuResult['day_master_strength'] ?? []);
$bodyGuideLines = buildGodsGuide($sajuResult['day_master_strength'] ?? []);
$visibleRelationships = dedupeRelationships($sajuResult['relationships'] ?? []);
$balanceSummary = buildBalanceSummary($ohangData);
$lifeThemeSummary = buildLifeThemeSummary($gyeokgukData);
[$fortuneData, $fortuneAutoEnhanced] = upgradeFortuneDataIfNeeded($fortuneData, $analysisType, $record);
$fortuneStats = fortuneDataStats($fortuneData);

$pageTitle = '분석 결과 - ' . SITE_NAME;
include INCLUDES_PATH . '/header.php';
?>

<div class="animate-fade">

<!-- ========================================= -->
<!-- 기본 정보 카드 -->
<!-- ========================================= -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <div>
            <div style="font-size:0.78rem;color:var(--text-muted);">분석 날짜: <?= formatDate($record['created_at'],'Y.m.d H:i') ?></div>
            <h2 style="font-size:1.15rem;font-weight:800;margin-top:4px;">🔮 나를 이해하는 사주 해석 리포트</h2>
        </div>
        <span class="card-badge <?= $isPremium ? '' : 'free' ?>" style="<?= $isPremium ? 'background:linear-gradient(135deg,#F39C12,#E67E22);color:#fff;' : '' ?>"><?= $isPremium ? '👑 프리미엄' : '무료 분석' ?></span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:0.82rem;color:var(--text-secondary);">
        <div><i class="fas fa-calendar" style="width:18px;color:var(--text-muted);"></i> <?= $record['birth_year'] ?>년 <?= $record['birth_month'] ?>월 <?= $record['birth_day'] ?>일</div>
        <div><i class="fas fa-clock" style="width:18px;color:var(--text-muted);"></i> <?= $sajuResult['siji_name'] ?? '' ?></div>
        <div><i class="fas fa-<?= $record['gender']==='male'?'mars':'venus' ?>" style="width:18px;color:var(--text-muted);"></i> <?= $record['gender']==='male'?'남성':'여성' ?></div>
        <div><i class="fas fa-<?= $record['calendar_type']==='solar'?'sun':'moon' ?>" style="width:18px;color:var(--text-muted);"></i> <?= $record['calendar_type']==='solar'?'양력':'음력' ?></div>
    </div>
</div>

<?php if ($resultHighlights): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">한눈에 보는 핵심 해석</span>
        <span style="font-size:0.78rem;color:var(--text-muted);">기술 용어보다 의미 중심으로 요약</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
        <?php foreach ($resultHighlights as $highlight): ?>
        <div style="padding:12px;background:var(--bg-secondary);border-radius:10px;">
            <div style="font-size:0.74rem;color:var(--text-muted);margin-bottom:4px;"><?= h($highlight['label']) ?></div>
            <div style="font-size:0.95rem;font-weight:800;color:var(--text-primary);line-height:1.4;"><?= h($highlight['value']) ?></div>
            <?php if (!empty($highlight['text'])): ?>
            <div style="font-size:0.8rem;color:var(--text-secondary);line-height:1.6;margin-top:6px;"><?= h($highlight['text']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($sajuResult): ?>
<!-- ========================================= -->
<!-- 1. 사주명식 (四柱命式) -->
<!-- ========================================= -->
<div class="card">
    <div class="card-header">
        <span class="card-title">태어난 순간의 기본 에너지</span>
        <span style="font-size:0.8rem;color:var(--text-muted);">나를 대표하는 기운: <strong style="color:var(--text-primary);"><?= $sajuResult['day_master'] ?>(<?= $sajuResult['day_master_element'] ?>)</strong></span>
    </div>

    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:10px;line-height:1.7;">복잡한 사주 기호 대신, 삶의 각 영역에서 어떤 기운이 겉으로 드러나고 어떤 기운이 바탕에 깔려 있는지만 읽기 쉽게 정리했습니다.</p>

    <div style="text-align:center;margin-bottom:8px;font-size:0.78rem;color:var(--text-muted);">
        <?= $sajuResult['zodiac'] ?? '' ?>띠
        <?php if (!empty($sajuResult['day_master_strength']['strength'])): ?>
         · <?= h(simplifyStrengthLabel($sajuResult['day_master_strength']['strength'])) ?>
        <?php endif; ?>
    </div>

    <div class="saju-chart">
        <?php
        $pillarOrder = [
            ['label'=>'시주','key'=>'hour_pillar'],
            ['label'=>'일주','key'=>'day_pillar'],
            ['label'=>'월주','key'=>'month_pillar'],
            ['label'=>'년주','key'=>'year_pillar'],
        ];
        $pillarAreaMap = [
            '시주' => '미래와 바라는 삶',
            '일주' => '나 자신과 가까운 관계',
            '월주' => '사회와 일하는 방식',
            '년주' => '가족과 처음 만나는 환경',
        ];
        $sipsinMap = [];
        foreach ($sipsinPillars as $sp) {
            if (isset($sp['name'])) $sipsinMap[$sp['name']] = $sp;
        }
        foreach ($pillarOrder as $pi):
            $p = $sajuResult[$pi['key']];
            $sc = elClass($p['stem_element']);
            $bc = elClass($p['branch_element']);
            $surfaceElement = elementFriendlyName($p['stem_element']);
            $baseElement = elementFriendlyName($p['branch_element']);
            $surfaceKeyword = elementKeyword($p['stem_element']);
            $baseKeyword = elementKeyword($p['branch_element']);
        ?>
        <div class="pillar-column">
            <div class="pillar-label"><?= $pi['label'] ?></div>
            <div class="pillar-sipsin"><?= h($pillarAreaMap[$pi['label']] ?? '') ?></div>
            <div class="pillar-cell <?= $sc ?>">
                <span class="element-tag <?= $sc ?>">겉 에너지</span>
                <span class="pillar-korean"><?= h($surfaceElement) ?></span>
            </div>
            <div class="pillar-sipsin" style="font-size:0.66rem;color:var(--text-muted);line-height:1.4;"><?= h($surfaceKeyword) ?></div>
            <div class="pillar-sipsin" style="font-size:0.68rem;"><?= h($surfaceElement) ?> 겉 · <?= h($baseElement) ?> 속</div>
            <div class="pillar-cell <?= $bc ?>">
                <span class="element-tag <?= $bc ?>">속 에너지</span>
                <span class="pillar-korean"><?= h($baseElement) ?></span>
            </div>
            <div class="pillar-sipsin" style="font-size:0.66rem;color:var(--text-muted);line-height:1.4;"><?= h($baseKeyword) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ========================================= -->
<!-- 2. 지장간 (地藏干) -->
<!-- ========================================= -->
<?php if (!empty($sajuResult['jijanggan'])): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">겉으로 보이지 않는 내면의 에너지</span>
        <span style="font-size:0.78rem;color:var(--text-muted);">숨은 성향 요약</span>
    </div>
    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:10px;line-height:1.7;">사람에게 겉모습만으로 설명되지 않는 면이 있듯, 사주에도 밖으로 바로 드러나지 않는 내면의 흐름이 있습니다. 어려운 원문 대신 핵심만 풀어서 보여드릴게요.</p>

    <?php if (!empty($hiddenEnergySummary['summary'])): ?>
    <div style="padding:12px 14px;background:var(--bg-secondary);border-radius:10px;font-size:0.84rem;color:var(--text-secondary);line-height:1.8;">
        <?= nl2br(h($hiddenEnergySummary['summary'])) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($hiddenEnergySummary['pillars'])): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;margin-top:12px;">
        <?php foreach ($hiddenEnergySummary['pillars'] as $insight): ?>
        <div style="padding:10px;background:var(--bg-secondary);border-radius:8px;">
            <div style="font-size:0.74rem;color:var(--text-muted);margin-bottom:4px;"><?= h($insight['label']) ?></div>
            <div style="font-size:0.8rem;color:var(--text-secondary);line-height:1.6;"><?= h($insight['text']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ========================================= -->
<!-- 3. 신강/신약 + 4신 -->
<!-- ========================================= -->
<?php if (!empty($sajuResult['day_master_strength'])): $dms = $sajuResult['day_master_strength']; ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">나의 에너지 체질 분석</span>
        <span style="font-size:0.85rem;font-weight:700;"><?= h(simplifyStrengthLabel($dms['strength'] ?? '')) ?></span>
    </div>

    <div style="display:flex;gap:10px;margin-bottom:12px;">
        <div style="flex:1;background:#E8F5E9;padding:10px;border-radius:8px;text-align:center;">
            <div style="font-size:0.72rem;color:#2E7D32;">도와주는 힘</div>
            <div style="font-size:1.1rem;font-weight:800;color:#2E7D32;"><?= $dms['support'] ?></div>
        </div>
        <div style="flex:1;background:#FFEBEE;padding:10px;border-radius:8px;text-align:center;">
            <div style="font-size:0.72rem;color:#C62828;">에너지를 빼는 힘</div>
            <div style="font-size:1.1rem;font-weight:800;color:#C62828;"><?= $dms['oppose'] ?></div>
        </div>
        <div style="flex:1;background:#E3F2FD;padding:10px;border-radius:8px;text-align:center;">
            <div style="font-size:0.72rem;color:#1565C0;">전체 균형</div>
            <div style="font-size:1.1rem;font-weight:800;color:#1565C0;"><?= round($dms['ratio']*100) ?>%</div>
        </div>
    </div>

    <div style="font-size:0.82rem;margin-bottom:8px;line-height:1.6;">
        <?php if ($dms['deukryeong']): ?>
        <span style="color:#2E7D32;">✅ 태어난 계절이 나를 도와주는 편입니다.</span>
        <?php else: ?>
        <span style="color:#F57C00;">⚠️ 태어난 계절이 나를 강하게 밀어주지는 않는 편입니다.</span>
        <?php endif; ?>
        · 기초 체력을 받쳐주는 뿌리: <?= $dms['roots'] ?? 0 ?>개
    </div>

    <?php if ($bodySummaryLines): ?>
    <div style="margin-bottom:14px;padding:12px 14px;background:var(--bg-secondary);border-radius:8px;font-size:0.83rem;color:var(--text-secondary);line-height:1.7;">
        <?php foreach ($bodySummaryLines as $line): ?>
        <div style="margin-bottom:6px;">• <?= h($line) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <?php
        $godsConfig = [
            ['key'=>'yongshin','label'=>'가장 필요한 에너지','desc'=>'지금 삶에 가장 도움이 되는 방향','bg'=>'#E8F5E9','color'=>'#2E7D32'],
            ['key'=>'heeshin','label'=>'도움을 더해주는 에너지','desc'=>'컨디션을 끌어올려 주는 보조 흐름','bg'=>'#E3F2FD','color'=>'#1565C0'],
            ['key'=>'gishin','label'=>'주의해야 할 에너지','desc'=>'과하면 부담이 되는 흐름','bg'=>'#FFEBEE','color'=>'#C62828'],
            ['key'=>'gushin','label'=>'함께 따라오는 부담','desc'=>'기신을 더 강하게 만드는 흐름','bg'=>'#FFF3E0','color'=>'#E65100'],
        ];
        foreach ($godsConfig as $g):
            $godRaw = $dms[$g['key']] ?? null;
            $god = normalizeGod($godRaw);
            if (!$god || empty($god['element'])) continue;
        ?>
        <div style="background:<?= $g['bg'] ?>;padding:10px 12px;border-radius:8px;">
            <div style="font-size:0.72rem;color:<?= $g['color'] ?>;font-weight:600;"><?= $g['label'] ?></div>
            <div style="font-size:1rem;font-weight:800;color:<?= $g['color'] ?>;margin:2px 0;"><?= h(elementFriendlyName($god['element'])) ?></div>
            <div style="font-size:0.72rem;color:<?= $g['color'] ?>;opacity:0.8;"><?= h($g['desc']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($bodyGuideLines): ?>
    <div style="margin-top:12px;padding:12px 14px;background:var(--bg-secondary);border-radius:8px;font-size:0.82rem;color:var(--text-secondary);line-height:1.7;">
        <div style="font-size:0.78rem;font-weight:700;color:var(--text-primary);margin-bottom:6px;">쉽게 읽는 핵심</div>
        <?php foreach ($bodyGuideLines as $line): ?>
        <div style="margin-bottom:6px;">• <?= h($line) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php
    $godsExplanation = $dms['four_gods_explanation'] ?? ($dms['yongshin']['reason'] ?? '');
    if ($godsExplanation): ?>
    <details style="margin-top:10px;">
        <summary style="cursor:pointer;font-size:0.78rem;color:var(--text-muted);">전통 설명 보기</summary>
        <div style="margin-top:8px;padding:12px 14px;background:var(--bg-secondary);border-radius:8px;font-size:0.8rem;color:var(--text-secondary);line-height:1.7;white-space:pre-wrap;"><?= h(replaceTechnicalTerms($godsExplanation)) ?></div>
    </details>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ========================================= -->
<!-- 4. 합충형파해 관계 -->
<!-- ========================================= -->
<?php if (!empty($sajuResult['relationships'])): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">관계에서 눈에 띄는 흐름</span>
        <span style="font-size:0.78rem;color:var(--text-muted);"><?= count($visibleRelationships) ?>가지 핵심</span>
    </div>
    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:10px;line-height:1.7;">사주 안에는 서로 잘 맞는 흐름도 있고, 부딪히면서 변화를 만드는 흐름도 있습니다. 복잡한 용어 대신 지금 삶에 어떤 느낌으로 나타나는지 중심으로 정리했습니다.</p>
    <?php
    $relColors = ['육합'=>'background:#E8F5E9;color:#2E7D32;','삼합'=>'background:#C8E6C9;color:#1B5E20;','방합'=>'background:#A5D6A7;color:#1B5E20;',
        '충'=>'background:#FFEBEE;color:#C62828;','형'=>'background:#FFF3E0;color:#E65100;','해'=>'background:#FCE4EC;color:#AD1457;',
        '파'=>'background:#FFF8E1;color:#F57F17;','천간합'=>'background:#E3F2FD;color:#1565C0;','천간충'=>'background:#F3E5F5;color:#7B1FA2;'];
    foreach ($visibleRelationships as $rel): ?>
    <div style="padding:8px 0;border-bottom:1px solid var(--border-light);font-size:0.83rem;">
        <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:0.75rem;font-weight:700;<?= $relColors[$rel['type']] ?? 'background:#F5F5F5;color:#333;' ?>"><?= h(simplifyRelationType($rel['type'])) ?></span>
        <div style="margin-top:4px;color:var(--text-secondary);font-size:0.8rem;line-height:1.6;"><?= h(buildRelationSummary($rel)) ?></div>
    </div>
    <?php endforeach; ?>

    <?php if (count($sajuResult['relationships']) > count($visibleRelationships)): ?>
    <details style="margin-top:10px;">
        <summary style="cursor:pointer;font-size:0.78rem;color:var(--text-muted);">참고용 세부 흐름 보기</summary>
        <div style="margin-top:8px;padding:12px;background:var(--bg-secondary);border-radius:8px;">
            <?php foreach ($sajuResult['relationships'] as $rel): ?>
            <div style="font-size:0.78rem;color:var(--text-secondary);line-height:1.6;margin-bottom:8px;">
                <strong><?= h(simplifyRelationType($rel['type'])) ?></strong><br>
                <?= h(trimSnippet(replaceTechnicalTerms($rel['meaning'] ?? ''), 120)) ?>
            </div>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ========================================= -->
<!-- 5. 오행 분석 (五行) -->
<!-- ========================================= -->
<?php if ($ohangData): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">다섯 가지 에너지 균형</span>
        <?php $balScore = is_array($ohangData['balance'] ?? null) ? ($ohangData['balance']['balance_score'] ?? 0) : (int)($ohangData['balance'] ?? 0); ?>
        <span style="font-size:0.82rem;font-weight:600;"><?= balanceScoreLabel($balScore) ?></span>
    </div>

    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:10px;line-height:1.7;">목·화·토·금·수 중 어떤 에너지가 강하고 약한지 보여줍니다. 숫자 자체보다, 어느 쪽에 힘이 실리는지 보시면 됩니다.</p>

    <?php $weighted = $balanceSummary['weighted'] ?? ($ohangData['weighted_ohang_count'] ?? ($ohangData['ohang_count'] ?? [])); ?>

    <?php if (!empty($balanceSummary['top']) || !empty($balanceSummary['low'])): ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px;">
        <?php if (!empty($balanceSummary['top'])): ?>
        <div style="padding:14px;border-radius:12px;background:#FFF8E1;">
            <div style="font-size:0.74rem;color:var(--text-muted);margin-bottom:4px;">지금 가장 강한 힘</div>
            <div style="font-size:0.95rem;font-weight:800;color:var(--text-primary);"><?= h(elementFriendlyName($balanceSummary['top']['element'])) ?></div>
            <div style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px;line-height:1.6;"><?= h(elementKeyword($balanceSummary['top']['element'])) ?></div>
            <div style="font-size:0.76rem;color:#8D6E63;margin-top:6px;"><?= h(elementLevelFromPercent($balanceSummary['top']['percent'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($balanceSummary['low'])): ?>
        <div style="padding:14px;border-radius:12px;background:#F1F8E9;">
            <div style="font-size:0.74rem;color:var(--text-muted);margin-bottom:4px;">조금 더 보완하면 좋은 힘</div>
            <div style="font-size:0.95rem;font-weight:800;color:var(--text-primary);"><?= h(elementFriendlyName($balanceSummary['low']['element'])) ?></div>
            <div style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px;line-height:1.6;"><?= h(elementKeyword($balanceSummary['low']['element'])) ?></div>
            <div style="font-size:0.76rem;color:#558B2F;margin-top:6px;"><?= h(elementLevelFromPercent($balanceSummary['low']['percent'])) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($weighted): ?>
    <div style="margin-top:14px;padding:12px 14px;background:var(--bg-secondary);border-radius:10px;">
        <div style="font-size:0.78rem;font-weight:700;color:var(--text-primary);margin-bottom:8px;">한눈에 보는 다섯 기운</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:8px;">
            <?php foreach ($weighted as $el => $val): $pct = elementPercent($weighted, $el); ?>
            <div style="padding:10px;border-radius:8px;background:#fff;border:1px solid var(--border-light);">
                <div style="font-size:0.74rem;color:var(--text-muted);"><?= h(elementFriendlyName($el)) ?></div>
                <div style="font-size:0.84rem;font-weight:700;color:var(--text-primary);margin-top:3px;"><?= h(elementLevelFromPercent($pct)) ?></div>
                <div style="font-size:0.72rem;color:var(--text-secondary);margin-top:4px;"><?= h(formatPercentLabel($pct)) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($balanceSummary['lines'])): ?>
    <div style="margin-top:12px;padding:12px 14px;background:var(--bg-secondary);border-radius:8px;font-size:0.83rem;color:var(--text-secondary);line-height:1.7;">
        <?php foreach ($balanceSummary['lines'] as $line): ?>
        <div style="margin-bottom:6px;">• <?= h($line) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($balanceSummary['season'])): ?>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-light);">
        <div style="font-size:0.85rem;font-weight:700;margin-bottom:6px;">📅 지금 생활에서 볼 포인트</div>
        <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:8px;line-height:1.6;"><?= h($balanceSummary['season']) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($balanceSummary['health'])): ?>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-light);">
        <div style="font-size:0.85rem;font-weight:700;margin-bottom:6px;">💊 몸 컨디션에서 기억할 점</div>
        <?php foreach ($balanceSummary['health'] as $item): ?>
        <div style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:4px;line-height:1.5;">
            <span class="element-tag <?= elClass($item['element']) ?>" style="font-size:0.7rem;padding:1px 6px;"><?= h(elementFriendlyName($item['element'])) ?></span>
            <?= h($item['text']) ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($balanceSummary['supplements'])): ?>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-light);">
        <div style="font-size:0.85rem;font-weight:700;margin-bottom:6px;">💡 일상에서 바로 해볼 것</div>
        <?php foreach ($balanceSummary['supplements'] as $adv): ?>
        <div style="margin-bottom:10px;">
            <div style="font-size:0.82rem;font-weight:600;margin-bottom:4px;">
                <span class="element-tag <?= elClass($adv['element']) ?>" style="font-size:0.7rem;padding:1px 6px;"><?= h(elementFriendlyName($adv['element'])) ?></span>
                <?= h(elementFriendlyName($adv['element'])) ?> 보완
            </div>
            <div style="font-size:0.8rem;color:var(--text-secondary);line-height:1.6;"><?= h($adv['text']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($ohangData['interpretation']) && is_string($ohangData['interpretation'])): ?>
    <details style="margin-top:10px;">
        <summary style="cursor:pointer;font-size:0.78rem;color:var(--text-muted);">전통 오행 해석 보기</summary>
        <div style="margin-top:8px;padding:12px;background:var(--bg-secondary);border-radius:8px;font-size:0.8rem;color:var(--text-secondary);line-height:1.7;white-space:pre-wrap;"><?= h(replaceTechnicalTerms($ohangData['interpretation'])) ?></div>
    </details>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ========================================= -->
<!-- 6. 십성 분석 (十星) -->
<!-- ========================================= -->
<?php if ($sipsinData): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">성향 키워드 분석</span>
        <span style="font-size:0.82rem;color:var(--primary-dark);font-weight:600;">가장 두드러진 성향</span>
    </div>

    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:10px;line-height:1.7;">전문 용어 대신, 당신이 사람과 세상을 어떤 방식으로 대하는지 드러나는 성향 키워드로 풀어서 설명합니다.</p>

    <?php if ($dominantSipsinInfo): ?>
    <div style="background:var(--primary-light);padding:12px;border-radius:8px;margin-bottom:12px;">
        <div style="font-size:0.9rem;font-weight:700;margin-bottom:4px;">🏆 지금 가장 강하게 보이는 성향</div>
        <div style="font-size:0.82rem;color:var(--text-secondary);line-height:1.7;">
            <strong><?= h(dominantSipsinFriendlyLabel($dominantSipsin)) ?></strong><br>
            <?= h(simpleSipsinText($dominantSipsin)) ?>
        </div>
    </div>
    <?php endif; ?>

    <?php $groupTotals = $sipsinData['group_totals'] ?? []; ?>
    <?php if ($groupTotals): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-bottom:14px;">
        <?php
        $groupMeta = [
            '비겁(比劫)' => ['title'=>'자기 주도성', 'desc'=>'내 방향을 밀고 나가는 힘', 'color'=>'#9C27B0'],
            '식상(食傷)' => ['title'=>'표현력', 'desc'=>'재능을 밖으로 드러내는 힘', 'color'=>'#FF9800'],
            '재성(財星)' => ['title'=>'현실 감각', 'desc'=>'돈과 기회를 다루는 힘', 'color'=>'#4CAF50'],
            '관성(官星)' => ['title'=>'책임감', 'desc'=>'규칙과 역할을 감당하는 힘', 'color'=>'#2196F3'],
            '인성(印星)' => ['title'=>'배움과 통찰', 'desc'=>'이해하고 흡수하는 힘', 'color'=>'#F44336'],
        ];
        foreach ($groupMeta as $groupKey => $meta):
            if (!array_key_exists($groupKey, $groupTotals)) continue;
            $groupValue = $groupTotals[$groupKey];
        ?>
        <div style="padding:10px;background:var(--bg-secondary);border-radius:8px;border-top:3px solid <?= $meta['color'] ?>;">
            <div style="font-size:0.72rem;color:var(--text-muted);"><?= $meta['title'] ?></div>
            <div style="font-size:0.9rem;font-weight:800;color:<?= $meta['color'] ?>;margin:4px 0;"><?= groupIntensityLabel($groupValue) ?></div>
            <div style="font-size:0.76rem;color:var(--text-secondary);line-height:1.5;"><?= $meta['desc'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php $interps = array_slice($sipsinData['interpretations'] ?? [], 0, 2); ?>
    <?php if ($interps): ?>
    <div style="border-top:1px solid var(--border-light);padding-top:12px;">
        <div style="font-size:0.85rem;font-weight:700;margin-bottom:8px;">📋 상황별 해석 포인트</div>
        <?php
        $levelColors = ['길'=>'#2E7D32','대길'=>'#1B5E20','주의'=>'#F57C00','경고'=>'#C62828','갈등'=>'#7B1FA2','양면'=>'#1565C0','참고'=>'#455A64','종합'=>'#37474F'];
        $levelBg = ['길'=>'#E8F5E9','대길'=>'#C8E6C9','주의'=>'#FFF3E0','경고'=>'#FFEBEE','갈등'=>'#F3E5F5','양면'=>'#E3F2FD','참고'=>'#ECEFF1','종합'=>'#ECEFF1'];
        foreach ($interps as $rule): ?>
        <div style="margin-bottom:10px;padding:10px;background:<?= $levelBg[$rule['level']] ?? '#F5F5F5' ?>;border-radius:8px;">
            <div style="font-size:0.8rem;font-weight:700;color:<?= $levelColors[$rule['level']] ?? '#333' ?>;margin-bottom:4px;">
                [<?= h($rule['category']) ?>] <?= h(simplifyRuleTitle($rule['title'])) ?>
                <span style="font-size:0.7rem;font-weight:400;margin-left:6px;padding:1px 6px;border-radius:3px;background:<?= $levelColors[$rule['level']] ?? '#666' ?>;color:#fff;"><?= h(simplifyRuleLevel($rule['level'])) ?></span>
            </div>
            <div style="font-size:0.82rem;color:var(--text-secondary);line-height:1.6;"><?= h(trimSnippet(replaceTechnicalTerms($rule['text']), 110)) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card" style="position:relative;overflow:hidden;">
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,0.85),rgba(255,255,255,0.95));backdrop-filter:blur(4px);z-index:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:20px;">
        <i class="fas fa-lock" style="font-size:2rem;color:#F39C12;margin-bottom:8px;"></i>
        <div style="font-size:0.95rem;font-weight:700;color:#333;">⭐ 성향 키워드 분석</div>
        <div style="font-size:0.8rem;color:#666;margin:6px 0;">성향 요약 · 상황별 해석 포인트</div>
        <a href="<?= SITE_URL ?>/pages/analyze.php" style="display:inline-block;margin-top:8px;padding:8px 20px;background:linear-gradient(135deg,#F39C12,#E67E22);color:#fff;border-radius:20px;font-size:0.82rem;font-weight:600;text-decoration:none;">🎫 1장으로 잠금 해제</a>
    </div>
    <div style="filter:blur(3px);opacity:0.4;pointer-events:none;">
        <div class="card-header"><span class="card-title">성향 키워드 분석</span></div>
        <p style="font-size:0.8rem;color:var(--text-secondary);">이 분석은 프리미엄 기능입니다.</p>
        <div style="height:120px;"></div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================= -->
<!-- 7. 격국 분석 (格局) -->
<!-- ========================================= -->
<?php if ($gyeokgukData): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">삶의 중심 테마</span>
        <span style="font-size:0.78rem;color:var(--text-muted);">사주 전체를 관통하는 흐름</span>
    </div>
    
    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:10px;line-height:1.7;">어려운 이름보다 중요한 것은, 이 사주가 전체적으로 어떤 방향의 삶을 향하는지입니다. 중심 테마를 쉽게 풀어 보여드립니다.</p>

    <div style="display:flex;gap:10px;margin-bottom:14px;">
        <div style="flex:1;background:var(--primary-light);padding:12px;border-radius:10px;text-align:center;">
            <div style="font-size:0.72rem;color:var(--text-muted);">핵심 테마</div>
            <div style="font-size:1rem;font-weight:800;"><?= h(gyeokgukFriendlyName($gyeokgukData)) ?></div>
            <div style="font-size:0.78rem;color:var(--text-secondary);margin-top:4px;line-height:1.5;"><?= h(gyeokgukFriendlyDesc($gyeokgukData)) ?></div>
        </div>
        <?php if (!empty($gyeokgukData['quality'])): $q = $gyeokgukData['quality']; ?>
        <div style="flex:1;background:#E3F2FD;padding:12px;border-radius:10px;text-align:center;">
            <div style="font-size:0.72rem;color:var(--text-muted);">현재 완성도</div>
            <div style="font-size:1rem;font-weight:800;"><?= h($q['level']) ?></div>
            <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px;"><?= h($q['description'] ?? '') ?></div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($lifeThemeSummary): ?>
    <div style="font-size:0.84rem;color:var(--text-secondary);line-height:1.7;">
        <?php foreach ($lifeThemeSummary as $line): ?>
        <div style="margin-bottom:8px;">• <?= h($line) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($gyeokgukData['detail'])): ?>
    <details style="margin-top:10px;">
        <summary style="cursor:pointer;font-size:0.78rem;color:var(--text-muted);">전통 테마 설명 보기</summary>
        <div style="margin-top:8px;font-size:0.8rem;color:var(--text-secondary);line-height:1.7;white-space:pre-wrap;"><?= h(replaceTechnicalTerms($gyeokgukData['detail'])) ?></div>
    </details>
    <?php endif; ?>
</div>
<?php else: ?>
<!-- 격국 분석 잠금 카드 -->
<div class="card" style="position:relative;overflow:hidden;">
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,0.85),rgba(255,255,255,0.95));backdrop-filter:blur(4px);z-index:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:20px;">
        <i class="fas fa-lock" style="font-size:2rem;color:#F39C12;margin-bottom:8px;"></i>
        <div style="font-size:0.95rem;font-weight:700;color:#333;">👑 삶의 중심 테마</div>
        <div style="font-size:0.8rem;color:#666;margin:6px 0;">삶을 관통하는 큰 주제 · 현재 완성도</div>
        <a href="<?= SITE_URL ?>/pages/analyze.php" style="display:inline-block;margin-top:8px;padding:8px 20px;background:linear-gradient(135deg,#F39C12,#E67E22);color:#fff;border-radius:20px;font-size:0.82rem;font-weight:600;text-decoration:none;">🎫 1장으로 잠금 해제</a>
    </div>
    <div style="filter:blur(3px);opacity:0.4;pointer-events:none;">
        <div class="card-header"><span class="card-title">삶의 중심 테마</span></div>
        <p style="font-size:0.8rem;color:var(--text-secondary);">이 분석은 프리미엄 기능입니다.</p>
        <div style="height:100px;"></div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================= -->
<!-- 8. 대운 분석 (大運) -->
<!-- ========================================= -->
<?php if ($daeunData): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">10년 주기 인생 흐름</span>
        <span style="font-size:0.78rem;color:var(--text-muted);">인생의 큰 계절</span>
    </div>
    
    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:8px;line-height:1.7;">인생은 10년 단위로 분위기가 크게 바뀌곤 합니다. 복잡한 기호는 줄이고, 각 시기에 어떤 느낌으로 살아가게 되는지 중심으로 보여드립니다.</p>
    
    <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:12px;">
        큰 흐름이 본격적으로 움직이기 시작하는 시점: <strong><?= $daeunData['start_age'] ?>세 전후</strong>
    </div>

    <div class="daeun-timeline">
    <?php foreach (($daeunData['daeuns'] ?? []) as $du): 
        $duScore = $du['score'] ?? 50;
        $hl = $duScore >= 70 ? 'highlight':'';
        $scoreColor = $duScore >= 70 ? '#2E7D32' : ($duScore >= 50 ? '#1565C0' : ($duScore >= 35 ? '#F57C00' : '#C62828'));
    ?>
        <div class="daeun-item <?= $hl ?>">
            <div class="daeun-age"><?= $du['age_start'] ?>~<?= $du['age_end'] ?>세</div>
            <div class="daeun-pillar" style="font-size:0.8rem;"><?= h(periodMoodLabel($duScore, !empty($du['is_yongshin']))) ?></div>
            <div class="daeun-score" style="color:<?= $scoreColor ?>;"><?= !empty($du['is_yongshin']) ? '기회 확장' : '흐름 체크' ?></div>
        </div>
    <?php endforeach; ?>
    </div>

    <?php foreach (($daeunData['daeuns'] ?? []) as $du): ?>
    <div style="margin-top:8px;border:1px solid var(--border-light);border-radius:8px;overflow:hidden;">
        <div style="padding:10px 12px;font-size:0.82rem;font-weight:600;background:var(--bg-secondary);display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
            <span><?= $du['age_start'] ?>~<?= $du['age_end'] ?>세</span>
            <span style="color:<?= !empty($du['is_yongshin']) ? '#2E7D32' : 'var(--text-muted)' ?>;"><?= h(periodMoodLabel($du['score'] ?? 50, !empty($du['is_yongshin']))) ?></span>
        </div>
        <div style="padding:10px 12px 12px;font-size:0.82rem;color:var(--text-secondary);line-height:1.7;">
            <?php foreach (buildDaeunSummary($du) as $line): ?>
            <div style="margin-bottom:6px;">• <?= h($line) ?></div>
            <?php endforeach; ?>
            <?php if (!empty($du['interpretation'])): ?>
            <details style="margin-top:8px;">
                <summary style="cursor:pointer;font-size:0.78rem;color:var(--text-muted);">전문 해석 보기</summary>
                <div style="margin-top:8px;white-space:pre-wrap;"><?= h(replaceTechnicalTerms($du['interpretation'])) ?></div>
            </details>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<!-- 대운 분석 잠금 카드 -->
<div class="card" style="position:relative;overflow:hidden;">
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,0.85),rgba(255,255,255,0.95));backdrop-filter:blur(4px);z-index:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:20px;">
        <i class="fas fa-lock" style="font-size:2rem;color:#F39C12;margin-bottom:8px;"></i>
        <div style="font-size:0.95rem;font-weight:700;color:#333;">🛤 10년 주기 인생 흐름</div>
        <div style="font-size:0.8rem;color:#666;margin:6px 0;">인생의 큰 계절 · 중요한 전환점</div>
        <a href="<?= SITE_URL ?>/pages/analyze.php" style="display:inline-block;margin-top:8px;padding:8px 20px;background:linear-gradient(135deg,#F39C12,#E67E22);color:#fff;border-radius:20px;font-size:0.82rem;font-weight:600;text-decoration:none;">🎫 2장으로 잠금 해제</a>
    </div>
    <div style="filter:blur(3px);opacity:0.4;pointer-events:none;">
        <div class="card-header"><span class="card-title">10년 주기 인생 흐름</span></div>
        <p style="font-size:0.8rem;color:var(--text-secondary);">이 분석은 프리미엄 기능입니다.</p>
        <div style="height:140px;"></div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================= -->
<!-- 9. 세운 분석 (歲運) -->
<!-- ========================================= -->
<?php if ($seunData && is_array($seunData)): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">다가오는 5년 흐름</span>
        <span style="font-size:0.78rem;color:var(--text-muted);">해마다 달라지는 분위기</span>
    </div>
    
    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:10px;line-height:1.7;">각 해마다 에너지가 조금씩 달라집니다. 복잡한 기호는 빼고, 실제 생활에서 어떤 분위기로 느껴질지 위주로 정리했습니다.</p>

    <?php foreach ($seunData as $su): 
        if (!is_array($su)) continue;
        $suScore = $su['score'] ?? ($su['total_score'] ?? 50);
        $scoreColor = $suScore >= 70 ? '#2E7D32' : ($suScore >= 50 ? '#1565C0' : ($suScore >= 35 ? '#F57C00' : '#C62828'));
    ?>
    <div style="margin-bottom:8px;border:1px solid var(--border-light);border-radius:8px;overflow:hidden;">
        <div style="padding:10px 12px;font-size:0.85rem;font-weight:600;background:var(--bg-secondary);display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span><?= $su['year'] ?? '' ?>년</span>
            <span style="font-size:0.78rem;color:var(--text-muted);"><?= h(periodMoodLabel($suScore, !empty($su['is_yongshin']))) ?></span>
            <span style="margin-left:auto;font-weight:700;color:<?= $scoreColor ?>;"><?= !empty($su['is_yongshin']) ? '기회 확장' : '흐름 체크' ?></span>
        </div>
        <div style="padding:10px 12px 12px;font-size:0.82rem;color:var(--text-secondary);line-height:1.7;">
            <?php foreach (buildSeunSummary($su) as $line): ?>
            <div style="margin-bottom:6px;">• <?= h($line) ?></div>
            <?php endforeach; ?>
            <?php if (!empty($su['monthly_highlight'])): $mh = $su['monthly_highlight']; ?>
            <div style="margin-top:8px;padding:8px;background:var(--bg-secondary);border-radius:6px;">
                📅 최고의 달: <strong><?= $mh['best_month'] ?>월</strong> · 주의할 달: <strong><?= $mh['worst_month'] ?>월</strong>
            </div>
            <?php elseif (!empty($su['monthly'])): ?>
            <?php
                $months = $su['monthly'];
                $bestM = $months[0]; $worstM = $months[0];
                foreach ($months as $m) { if ($m['score'] > $bestM['score']) $bestM = $m; if ($m['score'] < $worstM['score']) $worstM = $m; }
            ?>
            <div style="margin-top:8px;padding:8px;background:var(--bg-secondary);border-radius:6px;">
                📅 최고의 달: <strong><?= $bestM['month'] ?>월</strong> · 주의할 달: <strong><?= $worstM['month'] ?>월</strong>
            </div>
            <?php endif; ?>
            <?php if (!empty($su['interpretation'])): ?>
            <details style="margin-top:8px;">
                <summary style="cursor:pointer;font-size:0.78rem;color:var(--text-muted);">전문 해석 보기</summary>
                <div style="margin-top:8px;white-space:pre-wrap;"><?= h(replaceTechnicalTerms($su['interpretation'])) ?></div>
            </details>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<!-- 세운 분석 잠금 카드 -->
<div class="card" style="position:relative;overflow:hidden;">
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,0.85),rgba(255,255,255,0.95));backdrop-filter:blur(4px);z-index:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:20px;">
        <i class="fas fa-lock" style="font-size:2rem;color:#F39C12;margin-bottom:8px;"></i>
        <div style="font-size:0.95rem;font-weight:700;color:#333;">📅 다가오는 5년 흐름</div>
        <div style="font-size:0.8rem;color:#666;margin:6px 0;">해마다 달라지는 분위기 · 월별 포인트</div>
        <a href="<?= SITE_URL ?>/pages/analyze.php" style="display:inline-block;margin-top:8px;padding:8px 20px;background:linear-gradient(135deg,#F39C12,#E67E22);color:#fff;border-radius:20px;font-size:0.82rem;font-weight:600;text-decoration:none;">🎫 2장으로 잠금 해제</a>
    </div>
    <div style="filter:blur(3px);opacity:0.4;pointer-events:none;">
        <div class="card-header"><span class="card-title">다가오는 5년 흐름</span></div>
        <p style="font-size:0.8rem;color:var(--text-secondary);">이 분석은 프리미엄 기능입니다.</p>
        <div style="height:140px;"></div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================= -->
<!-- 10. 종합 운세 -->
<!-- ========================================= -->
<?php if ($fortuneData): ?>
<?php
$fortuneMeta = $fortuneData['_meta'] ?? [];
$fortuneSections = [
    'personality' => ['icon'=>'fa-brain','title'=>'성격·기질 분석','color'=>'#9C27B0'],
    'love'        => ['icon'=>'fa-heart','title'=>'연애·결혼 분석','color'=>'#E91E63'],
    'career'      => ['icon'=>'fa-briefcase','title'=>'직업·적성 분석','color'=>'#2196F3'],
    'wealth'      => ['icon'=>'fa-coins','title'=>'재물 분석','color'=>'#FF9800'],
    'study'       => ['icon'=>'fa-graduation-cap','title'=>'학업·시험 분석','color'=>'#4CAF50'],
    'health'      => ['icon'=>'fa-heartbeat','title'=>'건강 분석','color'=>'#F44336'],
    'life_flow'   => ['icon'=>'fa-river','title'=>'인생 흐름 개요','color'=>'#607D8B'],
];
?>
<div class="card" style="background:linear-gradient(135deg,#FFF9F1,#FFFFFF);border:1px solid #F5D4A4;">
    <div class="card-header">
        <span class="card-title">심층 사주 해석</span>
        <span style="font-size:0.78rem;color:#9C6A17;">
            <?= number_format((int)($fortuneMeta['total_chars'] ?? $fortuneStats['total'])) ?>자
        </span>
    </div>
    <div style="font-size:0.84rem;color:var(--text-secondary);line-height:1.8;">
        한두 줄 요약이 아니라, 사주의 구조와 반복 패턴을 바탕으로 성격, 관계, 일, 재물, 건강, 인생 흐름을 길게 풀어낸 개인화 해석입니다.
    </div>
    <?php if ($fortuneAutoEnhanced): ?>
    <div style="margin-top:10px;padding:10px 12px;background:#FFF3E0;border-radius:10px;font-size:0.8rem;color:#8D5A00;line-height:1.7;">
        예전 기록이라 짧게 저장돼 있던 해석은 현재 엔진으로 다시 풀어 보여드리고 있습니다.
    </div>
    <?php endif; ?>
</div>

<?php
foreach ($fortuneSections as $key => $meta):
    $content = $fortuneData[$key] ?? null;
    if (!$content) continue;
    $text = is_string($content) ? $content : ($content['content'] ?? '');
    $textLength = fortuneTextLength($text);
    $blocks = fortuneTextBlocks($text);
    $previewLimit = fortunePreviewLimit($key, $textLength);
    $previewBlocks = array_slice($blocks, 0, $previewLimit);
    $hiddenBlocks = array_slice($blocks, $previewLimit);
    $simpleLines = buildFortuneSummary($key, [
        'dayElement' => $sajuResult['day_master_element'] ?? '',
        'dms' => $sajuResult['day_master_strength'] ?? [],
        'groups' => $sipsinData['group_totals'] ?? [],
        'ohang' => $ohangData,
        'daeun' => $daeunData,
        'yongshinEl' => $sajuResult['day_master_strength']['yongshin']['element'] ?? '',
    ]);
?>
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas <?= $meta['icon'] ?>" style="color:<?= $meta['color'] ?>;margin-right:6px;"></i><?= $meta['title'] ?></span>
        <span style="font-size:0.76rem;color:var(--text-muted);">
            <?= $textLength >= 900 ? '심층 ' : '상세 ' ?><?= number_format($textLength) ?>자
        </span>
    </div>
    <?php if ($simpleLines): ?>
    <div style="margin-bottom:12px;padding:12px 14px;background:var(--bg-secondary);border-radius:10px;font-size:0.82rem;color:var(--text-secondary);line-height:1.7;">
        <div style="font-size:0.74rem;font-weight:700;color:var(--text-primary);margin-bottom:6px;">핵심 먼저</div>
        <?php foreach ($simpleLines as $line): ?>
        <div style="margin-bottom:6px;">• <?= h($line) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($previewBlocks): ?>
    <div style="font-size:0.84rem;color:var(--text-secondary);line-height:1.9;">
        <?php foreach ($previewBlocks as $block): ?>
            <?php if ($block['type'] === 'heading'): ?>
            <div style="margin:14px 0 8px;font-size:0.78rem;font-weight:800;color:var(--text-primary);letter-spacing:-0.01em;"><?= h($block['text']) ?></div>
            <?php else: ?>
            <div style="margin-bottom:10px;white-space:pre-line;"><?= h($block['text']) ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($hiddenBlocks): ?>
    <details style="margin-top:10px;">
        <summary style="cursor:pointer;font-size:0.78rem;color:var(--text-muted);">이어서 심층 해석 보기</summary>
        <div style="margin-top:10px;font-size:0.82rem;color:var(--text-secondary);line-height:1.85;">
            <?php foreach ($hiddenBlocks as $block): ?>
                <?php if ($block['type'] === 'heading'): ?>
                <div style="margin:14px 0 8px;font-size:0.78rem;font-weight:800;color:var(--text-primary);"><?= h($block['text']) ?></div>
                <?php else: ?>
                <div style="margin-bottom:10px;white-space:pre-line;"><?= h($block['text']) ?></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php else: ?>
<!-- 종합 운세 잠금 카드 -->
<div class="card" style="position:relative;overflow:hidden;">
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,0.85),rgba(255,255,255,0.95));backdrop-filter:blur(4px);z-index:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:20px;">
        <i class="fas fa-lock" style="font-size:2rem;color:#E91E63;margin-bottom:8px;"></i>
        <div style="font-size:0.95rem;font-weight:700;color:#333;">🔮 종합 운세 분석</div>
        <div style="font-size:0.8rem;color:#666;margin:6px 0;">성격 · 연애 · 직업 · 재물 · 학업 · 건강 · 인생흐름</div>
        <a href="<?= SITE_URL ?>/pages/analyze.php" style="display:inline-block;margin-top:8px;padding:8px 20px;background:linear-gradient(135deg,#E91E63,#9C27B0);color:#fff;border-radius:20px;font-size:0.82rem;font-weight:600;text-decoration:none;">🔮 종합 프리미엄으로 잠금 해제</a>
    </div>
    <div style="filter:blur(3px);opacity:0.4;pointer-events:none;">
        <div class="card-header"><span class="card-title">종합 운세 분석</span></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;padding:10px;">
            <div style="padding:12px;background:#f0f0f0;border-radius:8px;text-align:center;font-size:0.8rem;">성격·기질</div>
            <div style="padding:12px;background:#f0f0f0;border-radius:8px;text-align:center;font-size:0.8rem;">연애·결혼</div>
            <div style="padding:12px;background:#f0f0f0;border-radius:8px;text-align:center;font-size:0.8rem;">직업·적성</div>
            <div style="padding:12px;background:#f0f0f0;border-radius:8px;text-align:center;font-size:0.8rem;">재물·건강</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================= -->
<!-- 하단 액션 -->
<!-- ========================================= -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px;">
    <a href="<?= SITE_URL ?>/pages/analyze.php" class="btn btn-primary btn-block"><i class="fas fa-redo"></i> 새로 분석</a>
    <a href="<?= SITE_URL ?>/pages/history.php" class="btn btn-outline btn-block"><i class="fas fa-clock-rotate-left"></i> 기록 보기</a>
</div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() { if (typeof animateOhangBars === 'function') animateOhangBars(); });
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
