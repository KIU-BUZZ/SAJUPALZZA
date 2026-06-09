<?php
/**
 * ========================================
 * 오행 분석 엔진 (OhangAnalysis)
 * ========================================
 * 
 * 사주의 오행(五行) 분포를 종합 분석합니다.
 * 
 * [분석 항목]
 * 1. 오행 카운트: 천간 + 지지 본기 기본 카운트
 * 2. 가중치 오행: 지장간 비율까지 반영한 정밀 카운트
 * 3. 십성 분포: 십성별 개수 (지장간 포함)
 * 4. 계절 분석: 왕상휴수사(旺相休囚死)
 * 5. 오행 흐름: 상생·상극 관계 해석
 * 6. 건강 분석: 오행→장부 대응
 * 7. 보완 조언: 부족/과다 오행 보충 방법
 */

class OhangAnalysis {

    // 오행 속성 사전
    const OHANG_PROPERTIES = [
        '목' => ['hanja'=>'木','color'=>'#4CAF50','direction'=>'동','season'=>'봄',
            'organ'=>'간(肝)/담(膽)','body'=>'눈, 손발톱, 근육, 인대','emotion'=>'분노(怒)',
            'taste'=>'신맛','number'=>'3,8','heavenly_virtue'=>'인(仁, 어질고 베푸는 마음)',
            'description'=>'나무는 위로 뻗어 올라가려는 성질이 있습니다. 성장, 발전, 시작의 에너지입니다.',
            'personality_strong'=>'추진력이 강하고 정의감이 넘칩니다. 새로운 것을 시작하는 데 두려움이 없으며, 성장 지향적입니다. 다만 고집이 세고 타인의 의견을 무시할 수 있습니다.',
            'personality_weak'=>'결단력이 부족하고 시작한 일을 끝까지 마무리하기 어려울 수 있습니다. 용기를 내어 도전하는 자세가 필요합니다.'],
        '화' => ['hanja'=>'火','color'=>'#F44336','direction'=>'남','season'=>'여름',
            'organ'=>'심장(心)/소장(小腸)','body'=>'혀, 혈관, 얼굴','emotion'=>'기쁨·흥분(喜)',
            'taste'=>'쓴맛','number'=>'2,7','heavenly_virtue'=>'예(禮, 예절과 밝음)',
            'description'=>'불은 위로 타오르며 밝히는 성질이 있습니다. 열정, 표현, 활동의 에너지입니다.',
            'personality_strong'=>'열정적이고 표현력이 뛰어나 사람들의 주목을 받습니다. 예술적 감각이 있고 사교적입니다. 다만 감정 기복이 크고 충동적일 수 있습니다.',
            'personality_weak'=>'소극적이고 활력이 부족할 수 있습니다. 자신감을 키우고 적극적으로 자기표현을 하는 것이 좋습니다.'],
        '토' => ['hanja'=>'土','color'=>'#FF9800','direction'=>'중앙','season'=>'환절기',
            'organ'=>'비위(脾胃)/위(胃)','body'=>'입술, 살(肌肉), 배','emotion'=>'걱정·사려(思)',
            'taste'=>'단맛','number'=>'5,10','heavenly_virtue'=>'신(信, 믿음과 신뢰)',
            'description'=>'흙은 만물을 품고 키워내는 성질이 있습니다. 안정, 조화, 포용의 에너지입니다.',
            'personality_strong'=>'신뢰감이 있고 안정적이며 포용력이 큽니다. 중재 역할을 잘 하고 꾸준합니다. 다만 변화를 싫어하고 보수적일 수 있습니다.',
            'personality_weak'=>'우유부단하고 자기 주장이 약할 수 있습니다. 중심을 잡고 확고한 소신을 갖는 것이 필요합니다.'],
        '금' => ['hanja'=>'金','color'=>'#FFD700','direction'=>'서','season'=>'가을',
            'organ'=>'폐(肺)/대장(大腸)','body'=>'코, 피부, 체모','emotion'=>'슬픔(悲)',
            'taste'=>'매운맛','number'=>'4,9','heavenly_virtue'=>'의(義, 의로움과 결단)',
            'description'=>'금속은 단단하고 날카로운 성질이 있습니다. 결단, 정리, 수확의 에너지입니다.',
            'personality_strong'=>'결단력이 있고 원칙적이며 정의감이 강합니다. 깔끔한 마무리와 정돈에 능합니다. 다만 냉정하고 비판적일 수 있습니다.',
            'personality_weak'=>'우유부단하고 결정을 잘 내리지 못합니다. 마무리가 약하고 감성적으로 흔들릴 수 있습니다.'],
        '수' => ['hanja'=>'水','color'=>'#2196F3','direction'=>'북','season'=>'겨울',
            'organ'=>'신장(腎)/방광(膀胱)','body'=>'귀, 뼈, 머리카락','emotion'=>'두려움(恐)',
            'taste'=>'짠맛','number'=>'1,6','heavenly_virtue'=>'지(智, 지혜와 슬기)',
            'description'=>'물은 아래로 흘러 스며드는 성질이 있습니다. 지혜, 적응, 소통의 에너지입니다.',
            'personality_strong'=>'지혜롭고 적응력이 뛰어나며 소통을 잘합니다. 유연한 사고방식을 가졌습니다. 다만 일관성이 없고 우유부단할 수 있습니다.',
            'personality_weak'=>'사교에 어려움이 있고 고립되기 쉽습니다. 두려움을 극복하고 유연하게 대처하는 능력을 키워야 합니다.'],
    ];

    // 왕상휴수사 매트릭스: 계절(월지)별 각 오행의 기운 크기
    // 왕(4)=가장 강, 상(3)=비교적 강, 휴(2)=보통, 수(1)=약, 사(0)=가장 약
    const WANGSANGHYUSUSA = [
        '봄'   => ['목'=>4,'화'=>3,'토'=>2,'금'=>1,'수'=>0], // 봄=목 왕
        '여름' => ['화'=>4,'토'=>3,'금'=>2,'수'=>1,'목'=>0], // 여름=화 왕
        '환절기'=> ['토'=>4,'금'=>3,'수'=>2,'목'=>1,'화'=>0], // 환절기=토 왕 (진술축미월)
        '가을' => ['금'=>4,'수'=>3,'목'=>2,'화'=>1,'토'=>0], // 가을=금 왕
        '겨울' => ['수'=>4,'목'=>3,'화'=>2,'토'=>1,'금'=>0], // 겨울=수 왕
    ];

    // 상생 관계 (A→B: A가 B를 생함)
    const SANGSAENG = ['목'=>'화','화'=>'토','토'=>'금','금'=>'수','수'=>'목'];
    // 상극 관계 (A→B: A가 B를 극함)
    const SANGGEUK = ['목'=>'토','토'=>'수','수'=>'화','화'=>'금','금'=>'목'];

    private $engine;
    private $result;

    public function __construct(SajuEngine $engine) {
        $this->engine = $engine;
        $this->result = $engine->getResult();
    }

    /**
     * 종합 분석 실행 — 모든 분석 결과를 한번에 반환
     */
    public function analyze() {
        $ohang = $this->countOhang();
        $weighted = $this->countWeightedOhang();
        $season = $this->analyzeSeason();
        $sipsinDist = $this->result['sipsin_full']['distribution'] ?? [];
        $balance = $this->analyzeBalance($weighted);
        $flow = $this->analyzeFlow($weighted);
        $health = $this->analyzeHealth($weighted);
        $supplement = $this->getSupplement($weighted, $season);
        $personality = $this->analyzePersonality($weighted, $sipsinDist);

        return [
            'ohang_count' => $ohang,
            'weighted_ohang_count' => $weighted,
            'sipsin_distribution' => $sipsinDist,
            'season_analysis' => $season,
            'balance' => $balance,
            'flow_analysis' => $flow,
            'health_analysis' => $health,
            'supplement' => $supplement,
            'personality' => $personality,
            'interpretation' => $this->buildInterpretation($weighted, $season, $sipsinDist, $balance),
        ];
    }

    // ============================================================
    // 1. 오행 카운트 (기본)
    // ============================================================
    private function countOhang() {
        $count = array_fill_keys(SajuEngine::OHANG, 0);
        $elements = $this->engine->getAllElements();
        foreach ($elements['stems'] as $stemIdx) {
            $stem = SajuEngine::CHEONGAN[$stemIdx];
            $count[SajuEngine::CHEONGAN_OHANG[$stem]]++;
        }
        foreach ($elements['branches'] as $branchIdx) {
            $branch = SajuEngine::JIJI[$branchIdx];
            $count[SajuEngine::JIJI_OHANG[$branch]]++;
        }
        return $count;
    }

    // ============================================================
    // 2. 가중치 오행 (지장간 비율 반영)
    // ============================================================
    private function countWeightedOhang() {
        $count = array_fill_keys(SajuEngine::OHANG, 0.0);
        $elements = $this->engine->getAllElements();

        // 천간: 각 1점
        foreach ($elements['stems'] as $stemIdx) {
            $stem = SajuEngine::CHEONGAN[$stemIdx];
            $count[SajuEngine::CHEONGAN_OHANG[$stem]] += 1.0;
        }

        // 지지: 지장간 비율 반영 (총합이 1이 되도록)
        foreach ($elements['branches'] as $branchIdx) {
            $branch = SajuEngine::JIJI[$branchIdx];
            foreach (SajuEngine::JIJANGGAN[$branch] as $item) {
                $el = SajuEngine::CHEONGAN_OHANG[$item[0]];
                $count[$el] += $item[1];
            }
        }

        // 소수점 2자리 반올림
        foreach ($count as $k => $v) $count[$k] = round($v, 2);
        return $count;
    }

    // ============================================================
    // 3. 계절 분석 (왕상휴수사)
    // ============================================================
    private function analyzeSeason() {
        $monthBranch = SajuEngine::JIJI[$this->engine->getMonthPillar()[1]];
        $branchToSeason = [
            '인'=>'봄','묘'=>'봄','진'=>'환절기',
            '사'=>'여름','오'=>'여름','미'=>'환절기',
            '신'=>'가을','유'=>'가을','술'=>'환절기',
            '해'=>'겨울','자'=>'겨울','축'=>'환절기',
        ];
        $season = $branchToSeason[$monthBranch] ?? '환절기';
        $wangsang = self::WANGSANGHYUSUSA[$season];

        $stateNames = [4=>'왕(旺)', 3=>'상(相)', 2=>'휴(休)', 1=>'수(囚)', 0=>'사(死)'];
        $stateDesc = [
            4=>'가장 왕성한 상태입니다. 이 계절의 주인공으로, 에너지가 가장 강합니다.',
            3=>'왕성한 기운의 도움을 받아 비교적 강한 상태입니다.',
            2=>'기운이 쉬는 상태로, 보통 수준의 활력입니다.',
            1=>'기운이 갇혀 약해진 상태로, 의식적인 노력이 필요합니다.',
            0=>'기운이 가장 약한 상태로, 특별히 보완이 필요합니다.',
        ];

        $result = [];
        foreach (SajuEngine::OHANG as $oh) {
            $val = $wangsang[$oh];
            $result[$oh] = [
                'state_value' => $val,
                'state_name' => $stateNames[$val],
                'description' => $stateDesc[$val],
            ];
        }

        $seasonDesc = [
            '봄' => '봄(春)은 만물이 싹트는 계절로, 목(木)의 기운이 왕성합니다. 새로운 시작과 성장에 유리하지만, 금(金)의 결단과 수확은 약해집니다.',
            '여름' => '여름(夏)은 만물이 꽃피우는 계절로, 화(火)의 기운이 왕성합니다. 열정과 표현에 유리하지만, 수(水)의 지혜와 냉정함은 약해집니다.',
            '환절기' => '환절기(土旺)는 계절의 전환점으로, 토(土)의 기운이 왕성합니다. 안정과 조화에 유리하지만, 화(火)의 열정이 약해집니다.',
            '가을' => '가을(秋)은 만물을 거두는 계절로, 금(金)의 기운이 왕성합니다. 결단과 정리에 유리하지만, 목(木)의 시작과 진취는 약해집니다.',
            '겨울' => '겨울(冬)은 만물이 쉬는 계절로, 수(水)의 기운이 왕성합니다. 지혜와 내면 성찰에 유리하지만, 금(金)의 추진력이 약해집니다.',
        ];

        return [
            'season' => $season,
            'season_description' => $seasonDesc[$season] ?? '',
            'month_branch' => $monthBranch,
            'wangsanghyususa' => $result,
        ];
    }

    // ============================================================
    // 4. 오행 균형 분석
    // ============================================================
    private function analyzeBalance($weighted) {
        $total = array_sum($weighted);
        $avg = $total / 5;

        $strongest = ''; $weakest = ''; $sMax = 0; $wMin = PHP_INT_MAX;
        $missing = []; $overCount = 0;
        $percent = [];

        foreach ($weighted as $oh => $val) {
            $pct = $total > 0 ? round($val / $total * 100, 1) : 0;
            $percent[$oh] = $pct;
            if ($val > $sMax) { $sMax = $val; $strongest = $oh; }
            if ($val < $wMin) { $wMin = $val; $weakest = $oh; }
            if ($val < 0.5) $missing[] = $oh;
            if ($val > $avg * 1.5) $overCount++;
        }

        // 균형도 점수 (표준편차 기반, 100점 만점)
        $variance = 0;
        foreach ($weighted as $val) $variance += pow($val - $avg, 2);
        $stddev = sqrt($variance / 5);
        $balanceScore = max(0, min(100, (int)(100 - $stddev * 25)));

        $balanceLevel = $balanceScore >= 80 ? '매우 균형적' : ($balanceScore >= 60 ? '비교적 균형적' : ($balanceScore >= 40 ? '약간 편중' : ($balanceScore >= 20 ? '상당히 편중' : '극도로 편중')));

        return [
            'percent' => $percent,
            'strongest' => ['element'=>$strongest, 'value'=>$sMax, 'pct'=>$percent[$strongest]],
            'weakest' => ['element'=>$weakest, 'value'=>$wMin, 'pct'=>$percent[$weakest]],
            'missing' => $missing,
            'balance_score' => $balanceScore,
            'balance_level' => $balanceLevel,
            'description' => $this->buildBalanceDescription($strongest, $weakest, $missing, $balanceScore, $percent),
        ];
    }

    private function buildBalanceDescription($strongest, $weakest, $missing, $score, $pct) {
        $sp = self::OHANG_PROPERTIES[$strongest]; $wp = self::OHANG_PROPERTIES[$weakest];
        $desc = "■ 가장 강한 오행: {$strongest}({$sp['hanja']}) — {$pct[$strongest]}%\n";
        $desc .= "{$sp['description']}\n";
        $desc .= "이 기운이 강하다는 것은 {$sp['heavenly_virtue']}의 덕목이 뚜렷하다는 뜻입니다.\n\n";
        $desc .= "■ 가장 약한 오행: {$weakest}({$wp['hanja']}) — {$pct[$weakest]}%\n";
        $desc .= "{$wp['description']}\n";
        $desc .= "이 기운을 보완하면 {$wp['heavenly_virtue']}의 덕목이 강화됩니다.\n\n";
        if (!empty($missing)) {
            $ml = implode(', ', array_map(fn($m)=>$m.'('.self::OHANG_PROPERTIES[$m]['hanja'].')', $missing));
            $desc .= "■ 부족 오행: {$ml}\n특히 이 오행을 의식적으로 보완하면 삶의 균형이 좋아집니다.\n\n";
        }
        $desc .= "■ 균형도: {$score}점/100점\n";
        return $desc;
    }

    // ============================================================
    // 5. 오행 흐름 (상생·상극)
    // ============================================================
    private function analyzeFlow($weighted) {
        $flows = [];

        // 상생 흐름
        foreach (self::SANGSAENG as $from => $to) {
            $fv = $weighted[$from]; $tv = $weighted[$to];
            $active = ($fv >= 1.0 && $tv >= 0.5);
            $fp = self::OHANG_PROPERTIES[$from]; $tp = self::OHANG_PROPERTIES[$to];
            $flows[] = [
                'type' => '상생',
                'from' => $from, 'to' => $to,
                'from_value' => $fv, 'to_value' => $tv,
                'active' => $active,
                'description' => "{$from}({$fp['hanja']})→{$to}({$tp['hanja']}) 상생: {$from}이(가) {$to}을(를) 만들어냅니다. ".
                    ($active ? "두 오행이 충분히 있어 이 상생 흐름이 잘 작동합니다." : "한쪽이 부족하여 이 상생 흐름이 원활하지 않습니다."),
            ];
        }

        // 상극 관계
        foreach (self::SANGGEUK as $from => $to) {
            $fv = $weighted[$from]; $tv = $weighted[$to];
            $harmful = ($fv >= 2.0 && $tv <= 1.0);
            $fp = self::OHANG_PROPERTIES[$from]; $tp = self::OHANG_PROPERTIES[$to];
            $flows[] = [
                'type' => '상극',
                'from' => $from, 'to' => $to,
                'from_value' => $fv, 'to_value' => $tv,
                'harmful' => $harmful,
                'description' => "{$from}({$fp['hanja']})→{$to}({$tp['hanja']}) 상극: {$from}이(가) {$to}을(를) 억제합니다. ".
                    ($harmful ? "⚠ {$from}이 강한데 {$to}이 약하므로, 이 억제가 지나쳐 문제가 될 수 있습니다." : "적절한 상극 관계로 균형을 이루고 있습니다."),
            ];
        }

        return $flows;
    }

    // ============================================================
    // 6. 건강 분석
    // ============================================================
    private function analyzeHealth($weighted) {
        $results = [];
        foreach (SajuEngine::OHANG as $oh) {
            $val = $weighted[$oh];
            $prop = self::OHANG_PROPERTIES[$oh];

            if ($val < 0.5) $status = '매우 약함';
            elseif ($val < 1.0) $status = '약함';
            elseif ($val < 2.0) $status = '보통';
            elseif ($val < 3.0) $status = '강함';
            else $status = '매우 강함';

            $concern = '';
            if ($val < 1.0) {
                $concern = "{$oh}({$prop['hanja']})이(가) 약하므로 {$prop['organ']} 기능에 주의하세요. ".
                    "관련 부위: {$prop['body']}. {$prop['taste']} 음식을 적절히 섭취하면 도움이 됩니다.";
            } elseif ($val >= 3.0) {
                $concern = "{$oh}({$prop['hanja']})이(가) 과다하여 {$prop['organ']}이 과부하될 수 있습니다. ".
                    "감정적으로 {$prop['emotion']}의 감정이 과도하게 나타날 수 있으니 조절이 필요합니다.";
            }

            $results[$oh] = [
                'element' => $oh,
                'value' => $val,
                'status' => $status,
                'organ' => $prop['organ'],
                'body_parts' => $prop['body'],
                'emotion' => $prop['emotion'],
                'concern' => $concern,
            ];
        }
        return $results;
    }

    // ============================================================
    // 7. 보완 조언
    // ============================================================
    private function getSupplement($weighted, $season) {
        $advices = [];

        // 부족한 오행 보승
        arsort($weighted); // 강한 순 정렬
        $sorted = $weighted;
        asort($weighted); // 약한 순 정렬
        $weakestTwo = array_slice(array_keys($weighted), 0, 2);

        foreach ($weakestTwo as $oh) {
            $prop = self::OHANG_PROPERTIES[$oh];
            $methods = [];
            switch ($oh) {
                case '목':
                    $methods = ['초록색 옷이나 소품 사용','식물 키우기나 산책','아침 활동 강화','동쪽 방향 활용','숫자 3, 8 활용'];
                    break;
                case '화':
                    $methods = ['빨간색·주황색 착용','촛불·조명 활용','남쪽 방향 활용','열정적인 활동 참여','숫자 2, 7 활용'];
                    break;
                case '토':
                    $methods = ['노란색·갈색 착용','도자기·흙 관련 취미','정원 가꾸기','중심부 활용, 안정적 루틴','숫자 5, 10 활용'];
                    break;
                case '금':
                    $methods = ['흰색·금색 착용','금속 액세서리 착용','서쪽 방향 활용','정리정돈 습관','숫자 4, 9 활용'];
                    break;
                case '수':
                    $methods = ['검정색·파란색 착용','수영·목욕 등 물 관련 활동','북쪽 방향 활용','독서·명상 등 내면 성찰','숫자 1, 6 활용'];
                    break;
            }
            $advices[] = [
                'element' => $oh,
                'type' => '보완 필요',
                'direction' => $prop['direction'],
                'season' => $prop['season'],
                'taste' => $prop['taste'],
                'color' => $prop['hanja'].'에 해당하는 색상',
                'methods' => $methods,
            ];
        }

        return $advices;
    }

    // ============================================================
    // 8. 성격 분석 (오행+십성 기반)
    // ============================================================
    private function analyzePersonality($weighted, $sipsinDist) {
        $dayElement = $this->result['day_master_element'];
        $prop = self::OHANG_PROPERTIES[$dayElement];
        $isStrong = ($this->result['day_master_strength']['is_strong'] ?? false);

        $traits = [];
        
        // 일간 오행에 따른 기본 성격
        $traits[] = $isStrong ? $prop['personality_strong'] : $prop['personality_weak'];

        // 가장 강한 오행 성격 반영
        arsort($weighted);
        $strongestEl = array_key_first($weighted);
        if ($strongestEl !== $dayElement) {
            $sp = self::OHANG_PROPERTIES[$strongestEl];
            $traits[] = "사주에서 {$strongestEl}({$sp['hanja']})의 기운이 가장 강하므로, {$sp['heavenly_virtue']}의 성향이 강하게 드러납니다.";
        }

        // 십성 분포 기반 성격
        if (!empty($sipsinDist)) {
            arsort($sipsinDist);
            $topSipsin = array_key_first($sipsinDist);
            $sipsinInfo = SajuEngine::SIPSIN_INFO[$topSipsin] ?? null;
            if ($sipsinInfo) {
                $traits[] = "십성으로는 '{$topSipsin}'의 기운이 가장 강합니다. {$sipsinInfo['meaning']}의 성향이 두드러집니다.";
            }

            // 식신/상관 강하면 표현력
            $creative = ($sipsinDist['식신'] ?? 0) + ($sipsinDist['상관'] ?? 0);
            if ($creative >= 2.5) {
                $traits[] = "식상(식신+상관)이 강하여 창의력과 표현력이 뛰어납니다. 예술·기획·강연 등에 재능이 있습니다.";
            }
            // 재성 강하면 재물복
            $wealth = ($sipsinDist['편재'] ?? 0) + ($sipsinDist['정재'] ?? 0);
            if ($wealth >= 2.5) {
                $traits[] = "재성(편재+정재)이 강하여 재물 감각이 뛰어나고 사업 수완이 좋습니다.";
            }
            // 관성 강하면 조직력
            $official = ($sipsinDist['편관'] ?? 0) + ($sipsinDist['정관'] ?? 0);
            if ($official >= 2.5) {
                $traits[] = "관성(편관+정관)이 강하여 조직 내에서 인정받기 쉬우며, 책임감과 리더십이 있습니다.";
            }
            // 인성 강하면 학구적
            $seal = ($sipsinDist['편인'] ?? 0) + ($sipsinDist['정인'] ?? 0);
            if ($seal >= 2.5) {
                $traits[] = "인성(편인+정인)이 강하여 학문적 소양이 뛰어나고 지적 호기심이 강합니다.";
            }
            // 비겁 강하면 독립적
            $peer = ($sipsinDist['비견'] ?? 0) + ($sipsinDist['겁재'] ?? 0);
            if ($peer >= 2.5) {
                $traits[] = "비겁(비견+겁재)이 강하여 독립심이 강하고 자기 주관이 뚜렷합니다. 혼자서도 잘 해내지만 협력하는 것을 배워야 합니다.";
            }
        }

        return $traits;
    }

    // ============================================================
    // 9. 종합 해석문 구성
    // ============================================================
    private function buildInterpretation($weighted, $season, $sipsinDist, $balance) {
        $dayElement = $this->result['day_master_element'];
        $dayProp = self::OHANG_PROPERTIES[$dayElement];
        $dms = $this->result['day_master_strength'];
        $yongshin = $dms['yongshin']['element'] ?? '토';
        $yp = self::OHANG_PROPERTIES[$yongshin] ?? self::OHANG_PROPERTIES['토'];
        $seasonText = $season['season_description'] ?? '';

        $text = "━━━ 오행 종합 해석 ━━━\n\n";
        $text .= "🔹 일간(日干) 성격\n";
        $text .= "당신의 일간은 {$dayElement}({$dayProp['hanja']})입니다.\n";
        $text .= "{$dayProp['description']}\n";
        $text .= "{$dayProp['heavenly_virtue']}의 덕목을 가지고 있으며, ";
        $text .= ($dms['is_strong'] ? "에너지가 풍부한 신강한 사주입니다.\n\n" : "에너지가 부족한 신약한 사주이므로 보완이 중요합니다.\n\n");

        $text .= "🔹 계절의 영향\n{$seasonText}\n\n";

        $text .= "🔹 오행 균형\n";
        $text .= "균형도: {$balance['balance_score']}점 ({$balance['balance_level']})\n";
        $text .= "가장 강한 기운: {$balance['strongest']['element']}({$balance['strongest']['pct']}%)\n";
        $text .= "가장 약한 기운: {$balance['weakest']['element']}({$balance['weakest']['pct']}%)\n\n";

        $text .= "🔹 용신(用神) 보완\n";
        $text .= "용신 = {$yongshin}({$yp['hanja']})\n";
        $text .= "{$yp['direction']}쪽 방향, {$yp['season']} 계절, {$yp['taste']} 음식이 도움이 됩니다.\n";

        return $text;
    }
}
