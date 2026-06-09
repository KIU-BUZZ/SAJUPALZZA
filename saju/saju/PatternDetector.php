<?php
/**
 * ================================================================
 * 사주 패턴 감지 엔진 (PatternDetector) — 1단계 모듈
 * ================================================================
 * 
 * 사주 데이터(일간, 오행 분포, 십성 분포, 신강/신약)를 분석하여
 * 명리학 패턴을 자동 감지하는 확장 가능한 모듈입니다.
 * 
 * [설계 원칙]
 * 1. 데이터 기반 레지스트리 — 새 패턴은 배열 하나 추가로 등록
 * 2. 조건 함수(Callable) — 각 패턴은 detect 콜백으로 판정
 * 3. 강도(Intensity) 계산 — 패턴이 얼마나 뚜렷한지 0~100 점수
 * 4. 카테고리/심각도/메타 — 풍부한 해석 메타데이터 제공
 * 5. 조합 패턴 — 기본 패턴 조합으로 상위 패턴 자동 감지
 * 
 * [사용법]
 *   $detector = new PatternDetector($sajuEngine);
 *   $patterns = $detector->detect();
 *   // → 감지된 모든 패턴 배열 반환
 * 
 * [확장법]
 *   PatternDetector::register([
 *       'id'          => 'my_custom_pattern',
 *       'name'        => '내 커스텀 패턴',
 *       'category'    => 'custom',
 *       'description' => '...',
 *       'detect'      => function($ctx) { return $ctx['isStrong'] && ...; },
 *       'intensity'   => function($ctx) { return min(100, $ctx['sipsinGroups']['bigyeop'] * 25); },
 *   ]);
 */

class PatternDetector {

    // ========================================================
    // 상수
    // ========================================================

    /** 패턴 카테고리 */
    const CATEGORY_STRENGTH   = 'strength';    // 신강/신약 관련
    const CATEGORY_SIPSIN     = 'sipsin';      // 십성 과다/부족
    const CATEGORY_FLOW       = 'flow';        // 오행 흐름(상생/상극 체인)
    const CATEGORY_STRUCTURE  = 'structure';   // 격국 구조
    const CATEGORY_SPECIAL    = 'special';     // 특수 패턴(종격 등)
    const CATEGORY_BALANCE    = 'balance';     // 균형/불균형

    /** 심각도 레벨 */
    const SEVERITY_INFO     = 'info';       // 참고 수준
    const SEVERITY_MODERATE = 'moderate';   // 보통 영향
    const SEVERITY_STRONG   = 'strong';     // 강한 영향
    const SEVERITY_CRITICAL = 'critical';   // 매우 강한 영향

    /** 십성 → 그룹 매핑 */
    const SIPSIN_GROUPS = [
        '비견' => 'bigyeop', '겁재' => 'bigyeop',
        '식신' => 'siksang', '상관' => 'siksang',
        '편재' => 'jaesung', '정재' => 'jaesung',
        '편관' => 'gwansung', '정관' => 'gwansung',
        '편인' => 'insung', '정인' => 'insung',
    ];

    /** 그룹 한글명 */
    const GROUP_NAMES = [
        'bigyeop'  => '비겁(比劫)',
        'siksang'  => '식상(食傷)',
        'jaesung'  => '재성(財星)',
        'gwansung' => '관성(官星)',
        'insung'   => '인성(印星)',
    ];

    /** 오행 상생 순서 */
    const OHANG_CYCLE = ['목', '화', '토', '금', '수'];

    // ========================================================
    // 패턴 레지스트리 (static으로 런타임 확장 가능)
    // ========================================================
    private static $registry = [];
    private static $initialized = false;

    // ========================================================
    // 인스턴스
    // ========================================================
    private $engine;
    private $result;
    private $context;          // detect 함수에 전달되는 분석 컨텍스트
    private $detectedPatterns = [];

    // ========================================================
    // 생성자
    // ========================================================
    public function __construct(SajuEngine $engine) {
        $this->engine = $engine;
        $this->result = $engine->getResult();
        self::initDefaultPatterns();
        $this->buildContext();
    }

    // ========================================================
    // 공개 API
    // ========================================================

    /**
     * 모든 등록된 패턴을 검사하고, 감지된 패턴 목록을 반환합니다.
     * 
     * @return array [
     *     [
     *         'id'           => string,   // 패턴 고유 ID
     *         'name'         => string,   // 한글 패턴명
     *         'category'     => string,   // 카테고리
     *         'severity'     => string,   // 심각도
     *         'intensity'    => int,       // 강도 (0~100)
     *         'description'  => string,   // 패턴 설명
     *         'effect'       => string,   // 영향/효과
     *         'advice'       => string,   // 조언
     *         'related'      => array,    // 관련 패턴 ID 목록
     *         'tags'         => array,    // 태그 목록
     *     ], ...
     * ]
     */
    public function detect(): array {
        $this->detectedPatterns = [];

        // 1차: 기본 패턴 감지
        foreach (self::$registry as $pattern) {
            if ($this->evaluatePattern($pattern)) {
                $detected = $this->buildDetectedResult($pattern);
                $this->detectedPatterns[$pattern['id']] = $detected;
            }
        }

        // 2차: 조합 패턴 감지 (기본 패턴 결과를 기반으로)
        $this->detectCompositePatterns();

        // 강도순 정렬
        uasort($this->detectedPatterns, function ($a, $b) {
            return $b['intensity'] - $a['intensity'];
        });

        return array_values($this->detectedPatterns);
    }

    /**
     * 특정 카테고리의 감지된 패턴만 반환
     */
    public function detectByCategory(string $category): array {
        $all = $this->detect();
        return array_values(array_filter($all, fn($p) => $p['category'] === $category));
    }

    /**
     * 감지된 패턴 ID 목록만 반환 (간단 조회용)
     */
    public function detectIds(): array {
        return array_column($this->detect(), 'id');
    }

    /**
     * 특정 패턴이 감지되었는지 확인
     */
    public function hasPattern(string $patternId): bool {
        return in_array($patternId, $this->detectIds());
    }

    /**
     * 분석 컨텍스트 반환 (디버그/외부 활용)
     */
    public function getContext(): array {
        return $this->context;
    }

    /**
     * 감지 결과 요약 — 카테고리별 그룹핑
     */
    public function getSummary(): array {
        $patterns = $this->detect();
        $summary = [
            'total_detected'  => count($patterns),
            'by_category'     => [],
            'by_severity'     => [],
            'top_patterns'    => [],
            'pattern_ids'     => [],
        ];

        foreach ($patterns as $p) {
            $summary['by_category'][$p['category']][] = $p['name'];
            $summary['by_severity'][$p['severity']][] = $p['name'];
            $summary['pattern_ids'][] = $p['id'];
        }

        // 상위 5개 패턴
        $summary['top_patterns'] = array_slice($patterns, 0, 5);

        return $summary;
    }

    // ========================================================
    // 패턴 등록 (외부 확장 지원)
    // ========================================================

    /**
     * 새로운 패턴을 레지스트리에 등록합니다.
     * 
     * @param array $pattern [
     *   'id'          => (필수) 고유 식별자,
     *   'name'        => (필수) 한글 이름,
     *   'category'    => (필수) 카테고리 상수,
     *   'detect'      => (필수) function($ctx): bool,
     *   'description' => (선택) 설명,
     *   'effect'      => (선택) 영향 설명,
     *   'advice'      => (선택) 조언,
     *   'severity'    => (선택) 심각도 (기본: moderate),
     *   'intensity'   => (선택) function($ctx): int (0~100),
     *   'related'     => (선택) 관련 패턴 ID 배열,
     *   'tags'        => (선택) 태그 배열,
     * ]
     */
    public static function register(array $pattern): void {
        if (empty($pattern['id']) || empty($pattern['name']) || 
            empty($pattern['category']) || empty($pattern['detect'])) {
            throw new \InvalidArgumentException(
                'Pattern must have id, name, category, and detect callback.'
            );
        }
        $pattern = array_merge([
            'severity'    => self::SEVERITY_MODERATE,
            'description' => '',
            'effect'      => '',
            'advice'      => '',
            'intensity'   => null,
            'related'     => [],
            'tags'        => [],
        ], $pattern);
        self::$registry[$pattern['id']] = $pattern;
    }

    /**
     * 여러 패턴을 한 번에 등록
     */
    public static function registerAll(array $patterns): void {
        foreach ($patterns as $p) {
            self::register($p);
        }
    }

    /**
     * 등록된 패턴 수 반환
     */
    public static function getRegisteredCount(): int {
        self::initDefaultPatterns();
        return count(self::$registry);
    }

    /**
     * 레지스트리 초기화 (테스트용)
     */
    public static function resetRegistry(): void {
        self::$registry = [];
        self::$initialized = false;
    }

    // ========================================================
    // 내부: 컨텍스트 구성
    // ========================================================

    /**
     * 감지 함수에 전달할 분석 컨텍스트를 구성합니다.
     * 모든 패턴 감지에 필요한 데이터를 하나의 배열로 정리합니다.
     */
    private function buildContext(): void {
        $dms = $this->result['day_master_strength'];
        $sipsinDist = $this->result['sipsin_full']['distribution'];
        $dominant = $this->result['sipsin_full']['dominant_sipsin'] ?? '';

        // 십성 그룹 합산
        $groups = [
            'bigyeop'  => ($sipsinDist['비견'] ?? 0) + ($sipsinDist['겁재'] ?? 0),
            'siksang'  => ($sipsinDist['식신'] ?? 0) + ($sipsinDist['상관'] ?? 0),
            'jaesung'  => ($sipsinDist['편재'] ?? 0) + ($sipsinDist['정재'] ?? 0),
            'gwansung' => ($sipsinDist['편관'] ?? 0) + ($sipsinDist['정관'] ?? 0),
            'insung'   => ($sipsinDist['편인'] ?? 0) + ($sipsinDist['정인'] ?? 0),
        ];

        // 오행 분포 (가중치 반영)
        $ohangAnalysis = new OhangAnalysis($this->engine);
        $ohangData = $ohangAnalysis->analyze();
        $weightedOhang = $ohangData['weighted_ohang_count'] ?? [];
        $ohangTotal = array_sum($weightedOhang) ?: 1;

        // 오행 비율 (%)
        $ohangRatio = [];
        foreach ($weightedOhang as $el => $val) {
            $ohangRatio[$el] = round(($val / $ohangTotal) * 100, 1);
        }

        // 일간 정보
        $dayMaster = $this->result['day_master'];
        $dayElement = $this->result['day_master_element'];

        // 사주 기둥 정보
        $pillars = $this->result['sipsin_full']['pillars'] ?? [];

        // 합충 관계
        $relationships = $this->result['relationships'] ?? [];
        $relTypes = [];
        foreach ($relationships as $rel) {
            $type = $rel['type'] ?? '';
            if (!isset($relTypes[$type])) $relTypes[$type] = [];
            $relTypes[$type][] = $rel;
        }

        // 12운성 수집
        $twelveStages = [];
        foreach ($pillars as $p) {
            if (isset($p['twelve_stage']) && $p['twelve_stage'] !== '-') {
                $twelveStages[] = $p['twelve_stage'];
            }
        }

        // 공망
        $gongmang = $this->result['gongmang'] ?? [];

        // 용신 정보
        $yongshin = $dms['yongshin'] ?? [];

        $this->context = [
            // 신강/신약
            'isStrong'         => $dms['is_strong'],
            'strength'         => $dms['strength'],
            'strengthRatio'    => $dms['ratio'],
            'deukryeong'       => $dms['deukryeong'],
            'roots'            => $dms['roots'],

            // 일간
            'dayMaster'        => $dayMaster,
            'dayElement'       => $dayElement,

            // 십성 분포 (개별)
            'sipsinDist'       => $sipsinDist,
            'dominantSipsin'   => $dominant,

            // 십성 그룹별 합산
            'sipsinGroups'     => $groups,

            // 오행
            'weightedOhang'    => $weightedOhang,
            'ohangRatio'       => $ohangRatio,
            'ohangTotal'       => $ohangTotal,

            // 관계
            'relationships'    => $relationships,
            'relTypes'         => $relTypes,

            // 12운성
            'twelveStages'     => $twelveStages,

            // 공망
            'gongmang'         => $gongmang,

            // 용신
            'yongshin'         => $yongshin,

            // 사주 기둥 상세
            'pillars'          => $pillars,

            // 성별
            'gender'           => $this->engine->getGender(),
        ];
    }

    // ========================================================
    // 내부: 패턴 평가 & 결과 생성
    // ========================================================

    private function evaluatePattern(array $pattern): bool {
        $detectFn = $pattern['detect'];
        if (is_callable($detectFn)) {
            return (bool) $detectFn($this->context);
        }
        return false;
    }

    private function buildDetectedResult(array $pattern): array {
        // 강도 계산
        $intensity = 50; // 기본값
        if (is_callable($pattern['intensity'] ?? null)) {
            $intensity = (int) ($pattern['intensity'])($this->context);
            $intensity = max(0, min(100, $intensity));
        }

        // 심각도 자동 조정 (강도에 따라)
        $severity = $pattern['severity'];
        if ($severity === self::SEVERITY_MODERATE) {
            if ($intensity >= 80) $severity = self::SEVERITY_STRONG;
            elseif ($intensity >= 95) $severity = self::SEVERITY_CRITICAL;
        }

        return [
            'id'          => $pattern['id'],
            'name'        => $pattern['name'],
            'category'    => $pattern['category'],
            'severity'    => $severity,
            'intensity'   => $intensity,
            'description' => $pattern['description'],
            'effect'      => $pattern['effect'],
            'advice'      => $pattern['advice'],
            'related'     => $pattern['related'],
            'tags'        => $pattern['tags'],
        ];
    }

    // ========================================================
    // 내부: 조합(Composite) 패턴 감지
    // ========================================================

    private function detectCompositePatterns(): void {
        $detectedIds = array_keys($this->detectedPatterns);

        $composites = $this->getCompositeDefinitions();

        foreach ($composites as $comp) {
            // 필요한 패턴이 모두 감지되었는지 확인
            $required = $comp['requires'] ?? [];
            $allFound = true;
            foreach ($required as $reqId) {
                if (!in_array($reqId, $detectedIds)) {
                    $allFound = false;
                    break;
                }
            }
            if (!$allFound) continue;

            // 추가 조건 확인
            if (isset($comp['extraCondition']) && is_callable($comp['extraCondition'])) {
                if (!$comp['extraCondition']($this->context)) continue;
            }

            // 강도 = 구성 패턴들 중 최대값
            $maxIntensity = 0;
            foreach ($required as $reqId) {
                if (isset($this->detectedPatterns[$reqId])) {
                    $maxIntensity = max($maxIntensity, $this->detectedPatterns[$reqId]['intensity']);
                }
            }
            if (is_callable($comp['intensity'] ?? null)) {
                $maxIntensity = (int) ($comp['intensity'])($this->context);
            }

            $this->detectedPatterns[$comp['id']] = [
                'id'          => $comp['id'],
                'name'        => $comp['name'],
                'category'    => $comp['category'] ?? self::CATEGORY_STRUCTURE,
                'severity'    => $comp['severity'] ?? self::SEVERITY_STRONG,
                'intensity'   => max(0, min(100, $maxIntensity)),
                'description' => $comp['description'] ?? '',
                'effect'      => $comp['effect'] ?? '',
                'advice'      => $comp['advice'] ?? '',
                'related'     => $required,
                'tags'        => $comp['tags'] ?? ['조합패턴'],
            ];
        }
    }

    // ========================================================
    // 조합 패턴 정의
    // ========================================================

    private function getCompositeDefinitions(): array {
        return [
            // ─── 재다신약 + 인성부족 = 극심한 재다파인 ───
            [
                'id'          => 'jaeda_sinyak_pain',
                'name'        => '재다파인(財多破印)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_CRITICAL,
                'requires'    => ['jaeda_sinyak', 'insung_bujouk'],
                'description' => '재성이 과다하여 신약한데, 보호막인 인성마저 부족합니다. 재성이 인성을 극하여 학문·정신력의 도움을 받기 어렵고, 재물에 휘둘리는 삶이 되기 쉽습니다.',
                'effect'      => "• 재물에 대한 욕심은 크지만 실속을 챙기기 어렵습니다\n• 학업·자격증 취득이 중도 포기되기 쉽습니다\n• 정신적 스트레스가 높고 판단력이 흐려질 수 있습니다\n• 어머니(인성)와의 관계에 어려움이 있을 수 있습니다",
                'advice'      => "인성(학문, 자격, 정신 수양)을 의식적으로 강화하세요. 무리한 투자보다 안정적인 지식 축적이 먼저입니다. 명상이나 독서 습관이 큰 도움이 됩니다.",
                'tags'        => ['조합패턴', '재물', '학업', '인성부족'],
            ],

            // ─── 관다신약 + 비겁부족 = 고립무원 ───
            [
                'id'          => 'gwanda_goripmuwon',
                'name'        => '고립무원(孤立無援)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_CRITICAL,
                'requires'    => ['gwanda_sinyak', 'bigyeop_bujouk'],
                'description' => '관성의 압박이 극심한데 비겁(동료·형제)의 도움마저 없어, 홀로 거센 파도를 맞서는 형국입니다.',
                'effect'      => "• 직장·사회에서 과도한 압박과 스트레스를 받습니다\n• 도움을 구할 동료나 협력자가 부족합니다\n• 책임감에 짓눌려 건강이 약해지기 쉽습니다\n• 남성은 관재(官災), 여성은 남편과의 갈등이 심할 수 있습니다",
                'advice'      => "혼자 해결하려 하지 말고 인맥을 넓히세요. 인성(학문, 자격)으로 관성을 설기시키는 것이 핵심 전략입니다. 과도한 책임은 분담하는 지혜가 필요합니다.",
                'tags'        => ['조합패턴', '직업', '스트레스', '대인관계'],
            ],

            // ─── 식상생재 + 신강 = 부귀쌍전 ───
            [
                'id'          => 'siksang_saengjae_singang',
                'name'        => '식상생재·신강(食傷生財·身強)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_INFO,
                'requires'    => ['siksang_saengjae'],
                'extraCondition' => fn($ctx) => $ctx['isStrong'],
                'description' => '신강한 사주에서 식상이 재성을 생하는 최고의 재물 구조입니다. 넘치는 에너지를 재능으로 발산하고 그것이 곧 돈이 됩니다.',
                'effect'      => "• 창의적 아이디어가 실질적 수입으로 연결됩니다\n• 사업 수완이 뛰어나고 돈을 버는 감각이 탁월합니다\n• 프리랜서·창업·예술 분야에서 큰 성공 가능성이 있습니다\n• 재물이 안정적이고 지속적으로 들어옵니다",
                'advice'      => "이 좋은 구조를 최대한 활용하려면, 자신의 재능을 사업화하세요. 남을 위해 일하기보다 자기 브랜드를 만드는 것이 유리합니다.",
                'tags'        => ['조합패턴', '재물', '사업', '길패턴'],
                'intensity'   => fn($ctx) => min(100, (int)(($ctx['sipsinGroups']['siksang'] + $ctx['sipsinGroups']['jaesung']) * 18)),
            ],

            // ─── 관인상생 + 신약 = 귀인도움 ───
            [
                'id'          => 'gwanin_sinyak_guiin',
                'name'        => '관인상생·귀인도움(官印相生·貴人)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_INFO,
                'requires'    => ['gwanin_sangsaeng'],
                'extraCondition' => fn($ctx) => !$ctx['isStrong'],
                'description' => '신약하지만 관성이 인성을 생하여 나를 도와주는 귀한 구조입니다. 조직·제도의 틀 안에서 학문과 지혜로 출세하는 패턴입니다.',
                'effect'      => "• 공무원·대기업·학계 등 안정적 조직에서 승진이 빠릅니다\n• 윗사람의 도움과 인정을 잘 받습니다\n• 학위·자격증이 출세의 디딤돌이 됩니다\n• 명예와 지위가 자연스럽게 따릅니다",
                'advice'      => "안정적인 조직에 소속되어 실력을 쌓는 것이 최선입니다. 자격증·학위가 운을 크게 열어줍니다. 윗사람과의 관계를 잘 유지하세요.",
                'tags'        => ['조합패턴', '직업', '명예', '길패턴'],
                'intensity'   => fn($ctx) => min(100, (int)(($ctx['sipsinGroups']['gwansung'] + $ctx['sipsinGroups']['insung']) * 20)),
            ],

            // ─── 비겁과다 + 재성부족 = 비겁탈재 ───
            [
                'id'          => 'bigyeop_taljae_composite',
                'name'        => '비겁탈재(比劫奪財)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_STRONG,
                'requires'    => ['bigyeop_gwada', 'jaesung_bujouk'],
                'description' => '비겁이 과다한데 재성이 부족하여, 돈을 벌기보다 쓰는 데 능한 구조입니다. 경쟁자(비겁)가 많아 재물을 뺏기기 쉽습니다.',
                'effect'      => "• 재물이 모이지 않고 들어오는 대로 빠져나갑니다\n• 동업·투자에서 손해를 보기 쉽습니다\n• 형제·친구·동료와 금전 문제로 갈등이 생깁니다\n• 자존심 때문에 현실적인 판단이 어렵습니다",
                'advice'      => "식상(재능 발휘)을 통해 비겁의 기운을 설기시키고 그 힘이 재성으로 흐르게 하세요. 동업보다 혼자 하는 사업, 기술직·전문직이 유리합니다.",
                'tags'        => ['조합패턴', '재물', '대인관계'],
            ],

            // ─── 식상과다 + 관성부족 = 무관방종 ───
            [
                'id'          => 'siksang_gwada_mugwan',
                'name'        => '식상무관(食傷無官)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_MODERATE,
                'requires'    => ['siksang_gwada', 'gwansung_bujouk'],
                'description' => '식상이 과다하고 관성(규율·통제)이 없어 자유분방하지만 방향 없이 흩어집니다.',
                'effect'      => "• 아이디어는 넘치지만 실행·마무리가 약합니다\n• 규칙에 순응하기 어렵고 이직이 잦을 수 있습니다\n• 관(직장·조직)과 잘 맞지 않습니다\n• 자유로운 표현은 뛰어나나 사회적 체계에 적응이 힘듭니다",
                'advice'      => "프리랜서·예술·창업 등 자유로운 분야가 적합합니다. 스스로 루틴과 규율을 만드는 것이 성공의 열쇠입니다.",
                'tags'        => ['조합패턴', '직업', '성격'],
            ],

            // ─── 인성과다 + 식상부족 = 인다극식 ───
            [
                'id'          => 'inda_geuksik',
                'name'        => '인다극식(印多剋食)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_STRONG,
                'requires'    => ['insung_gwada', 'siksang_bujouk'],
                'description' => '인성이 과다하여 식상(표현력·창의력)을 극합니다. 아는 것은 많으나 표현하거나 실행에 옮기지 못하는 패턴입니다.',
                'effect'      => "• 머릿속은 가득하지만 입 밖으로 나오지 않습니다\n• 과도한 생각·걱정으로 행동이 늦어집니다\n• 의존적 성향이 강하고 독립심이 약합니다\n• 자녀운(식상)에 영향을 줄 수 있습니다",
                'advice'      => "행동을 먼저 하고 생각은 나중에 하는 연습이 필요합니다. 글쓰기·발표·운동 등 식상을 자극하는 활동을 의식적으로 하세요.",
                'tags'        => ['조합패턴', '성격', '학업', '행동력'],
            ],

            // ─── 재관혼잡 + 신약 = 극심한 혼란 ───
            [
                'id'          => 'jaegwan_honjap_sinyak',
                'name'        => '재관혼잡·신약(財官混雜·身弱)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_CRITICAL,
                'requires'    => ['jaegwan_honjap'],
                'extraCondition' => fn($ctx) => !$ctx['isStrong'],
                'description' => '편관과 정관이 혼잡한데 신약하여, 사방에서 압박이 쏟아지지만 감당할 힘이 없는 위험한 구조입니다.',
                'effect'      => "• 일은 많고 체력·정신력은 부족한 만성 과로 상태\n• 여러 사람의 기대와 요구에 시달립니다\n• 직장·사업에서 여러 일이 동시에 꼬일 수 있습니다\n• 건강 악화와 정신적 소진이 우려됩니다",
                'advice'      => "인성(학문, 수양)으로 관성의 압박을 설기시키고, 비겁(동료)의 도움을 적극 구하세요. 무리한 확장보다 현재 위치를 굳히는 것이 먼저입니다.",
                'tags'        => ['조합패턴', '직업', '건강', '위험패턴'],
            ],
        ];
    }

    // ========================================================
    // 기본 패턴 초기화 (최초 1회만 실행)
    // ========================================================

    private static function initDefaultPatterns(): void {
        if (self::$initialized) return;
        self::$initialized = true;

        // ────────────────────────────────────────
        // ▸ 신강/신약 관련 패턴
        // ────────────────────────────────────────

        self::registerAll([
            // ── 재다신약 ──
            [
                'id'          => 'jaeda_sinyak',
                'name'        => '재다신약(財多身弱)',
                'category'    => self::CATEGORY_STRENGTH,
                'severity'    => self::SEVERITY_STRONG,
                'tags'        => ['신약', '재성과다', '재물'],
                'description' => '재성(財星)이 과다하여 일간(나)을 극하고, 일간의 힘이 약한 상태입니다. 돈을 벌고 싶은 욕구는 크지만 이를 감당할 체력과 역량이 부족합니다.',
                'effect'      => "• 재물에 대한 욕심이 크지만 몸이 따라주지 않습니다\n• 과로·과욕으로 건강을 해치기 쉽습니다\n• 돈은 들어오지만 지키기 어렵습니다\n• 남성의 경우 여성 문제로 고생할 수 있습니다\n• 아버지(편재)의 영향이 과도하게 강할 수 있습니다",
                'advice'      => "욕심을 줄이고 능력 범위 안에서 활동하세요. 인성(학문, 자격) 강화로 기반을 다지는 것이 우선입니다. 무리한 사업 확장은 금물입니다.",
                'related'     => ['gwanda_sinyak', 'insung_bujouk'],
                'detect'      => function ($ctx) {
                    return $ctx['sipsinGroups']['jaesung'] >= 2.5 && !$ctx['isStrong'];
                },
                'intensity'   => function ($ctx) {
                    $jae = $ctx['sipsinGroups']['jaesung'];
                    $ratio = 1 - $ctx['strengthRatio'];
                    return min(100, (int)($jae * 15 + $ratio * 60));
                },
            ],

            // ── 관다신약 ──
            [
                'id'          => 'gwanda_sinyak',
                'name'        => '관다신약(官多身弱)',
                'category'    => self::CATEGORY_STRENGTH,
                'severity'    => self::SEVERITY_STRONG,
                'tags'        => ['신약', '관성과다', '직업', '스트레스'],
                'description' => '관성(官星)이 과다하여 일간을 억압하고, 일간의 힘이 약한 상태입니다. 사회적 압박·책임이 능력을 초과합니다.',
                'effect'      => "• 직장에서 과도한 업무·책임에 시달립니다\n• 상사·조직의 압박이 매우 강합니다\n• 법적 문제나 관재(官災)에 주의해야 합니다\n• 스트레스로 인한 건강 악화가 우려됩니다\n• 여성의 경우 남편 문제로 고생할 수 있습니다",
                'advice'      => "인성(학문, 자격증)으로 관성을 설기시키는 것이 최선입니다. 식신으로 편관을 제어하는 것도 좋은 방법입니다. 무리한 승진 경쟁은 피하세요.",
                'related'     => ['jaeda_sinyak', 'bigyeop_bujouk'],
                'detect'      => function ($ctx) {
                    return $ctx['sipsinGroups']['gwansung'] >= 2.5 && !$ctx['isStrong'];
                },
                'intensity'   => function ($ctx) {
                    $gwan = $ctx['sipsinGroups']['gwansung'];
                    $ratio = 1 - $ctx['strengthRatio'];
                    return min(100, (int)($gwan * 15 + $ratio * 60));
                },
            ],

            // ── 신강무제 (신강한데 설기 부족) ──
            [
                'id'          => 'singang_muje',
                'name'        => '신강무제(身強無制)',
                'category'    => self::CATEGORY_STRENGTH,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['신강', '설기부족', '성격'],
                'description' => '일간이 매우 강하지만 이 강한 에너지를 빼줄(설기) 식상·재성·관성이 부족하여, 힘이 넘치되 쓸 곳이 없는 상태입니다.',
                'effect'      => "• 에너지가 넘쳐 가만히 있지 못합니다\n• 고집이 매우 세고 타인의 말을 듣지 않습니다\n• 자만심이 강하고 통제를 싫어합니다\n• 실력은 있으나 사회 적응이 어려울 수 있습니다\n• 대인관계에서 마찰이 잦습니다",
                'advice'      => "넘치는 에너지를 발산할 출구를 만드세요. 운동·창작·봉사 등 식상의 활동이 매우 중요합니다. 겸손함을 연습하면 큰 인물이 됩니다.",
                'related'     => ['bigyeop_gwada'],
                'detect'      => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return $ctx['isStrong'] && $ctx['strengthRatio'] >= 0.55 &&
                        ($g['siksang'] + $g['jaesung'] + $g['gwansung']) <= 3.0;
                },
                'intensity'   => function ($ctx) {
                    $deficit = 3.0 - ($ctx['sipsinGroups']['siksang'] + $ctx['sipsinGroups']['jaesung'] + $ctx['sipsinGroups']['gwansung']);
                    return min(100, (int)($ctx['strengthRatio'] * 80 + max(0, $deficit) * 15));
                },
            ],

            // ── 신약무조 (신약한데 도움 부족) ──
            [
                'id'          => 'sinyak_mujo',
                'name'        => '신약무조(身弱無助)',
                'category'    => self::CATEGORY_STRENGTH,
                'severity'    => self::SEVERITY_STRONG,
                'tags'        => ['신약', '도움부족', '건강'],
                'description' => '일간이 신약한데 인성(도움)과 비겁(동료)이 모두 부족하여, 아무런 지원 없이 어려움을 감당해야 하는 상태입니다.',
                'effect'      => "• 체력이 약하고 피로를 쉽게 느낍니다\n• 주변에 의지할 사람이 적습니다\n• 자신감이 부족하고 소극적입니다\n• 남에게 이용당하기 쉽습니다\n• 건강 문제가 만성적으로 나타날 수 있습니다",
                'advice'      => "인성(학문, 수양, 어머니의 도움)을 적극적으로 구하세요. 무리하지 말고 체력 관리를 최우선으로 하며, 신뢰할 수 있는 소수의 인맥을 소중히 하세요.",
                'related'     => ['gwanda_sinyak', 'jaeda_sinyak'],
                'detect'      => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return !$ctx['isStrong'] && ($g['bigyeop'] + $g['insung']) <= 2.0;
                },
                'intensity'   => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    $help = $g['bigyeop'] + $g['insung'];
                    return min(100, (int)((1 - $ctx['strengthRatio']) * 70 + (3.0 - $help) * 15));
                },
            ],
        ]);

        // ────────────────────────────────────────
        // ▸ 십성 과다 패턴
        // ────────────────────────────────────────

        self::registerAll([
            // ── 비겁과다 ──
            [
                'id'          => 'bigyeop_gwada',
                'name'        => '비겁과다(比劫過多)',
                'category'    => self::CATEGORY_SIPSIN,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['비겁', '과다', '성격', '대인관계'],
                'description' => '비겁(比劫)이 과다합니다. 나와 같은 오행의 기운이 넘쳐, 자아가 매우 강하고 경쟁심이 불타는 상태입니다.',
                'effect'      => "• 자존심이 하늘을 찌르고 양보를 모릅니다\n• 독립심이 강하고 남에게 의존하지 않습니다\n• 경쟁에서 절대 지지 않으려 합니다\n• 동업·팀워크에서 갈등이 빈번합니다\n• 재물(재성)을 극하여 돈이 모이기 어렵습니다\n• 형제·친구·동료와 마찰이 잦습니다",
                'advice'      => "가끔은 양보가 더 큰 것을 얻는 지혜임을 기억하세요. 식상(재능 발휘)으로 비겁의 에너지를 발산하면 재물과 성취가 따릅니다.",
                'related'     => ['siksang_bujouk', 'jaesung_bujouk'],
                'detect'      => fn($ctx) => $ctx['sipsinGroups']['bigyeop'] >= 3.0,
                'intensity'   => fn($ctx) => min(100, (int)($ctx['sipsinGroups']['bigyeop'] * 25)),
            ],

            // ── 식상과다 ──
            [
                'id'          => 'siksang_gwada',
                'name'        => '식상과다(食傷過多)',
                'category'    => self::CATEGORY_SIPSIN,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['식상', '과다', '표현', '자유'],
                'description' => '식상(食傷)이 과다합니다. 표현력과 창의력이 넘치지만, 에너지가 과도하게 발산되어 기운이 새어나갑니다.',
                'effect'      => "• 말이 많고 표현 욕구가 매우 강합니다\n• 창의력·예술성이 뛰어나지만 산만합니다\n• 조직·규율에 적응하기 어렵습니다\n• 일간의 기운을 빼앗아 체력이 약해질 수 있습니다\n• 여성은 자녀 문제가 복잡해질 수 있습니다\n• 자유로운 영혼이지만 방향성이 없을 수 있습니다",
                'advice'      => "넘치는 창의력을 체계적으로 관리하세요. 하나의 분야에 집중하면 대가(大家)가 될 수 있습니다. 재성으로 연결하면 창작이 곧 수입이 됩니다.",
                'related'     => ['gwansung_bujouk'],
                'detect'      => fn($ctx) => $ctx['sipsinGroups']['siksang'] >= 3.0,
                'intensity'   => fn($ctx) => min(100, (int)($ctx['sipsinGroups']['siksang'] * 25)),
            ],

            // ── 인성과다 ──
            [
                'id'          => 'insung_gwada',
                'name'        => '인성과다(印星過多)',
                'category'    => self::CATEGORY_SIPSIN,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['인성', '과다', '학업', '의존'],
                'description' => '인성(印星)이 과다합니다. 나를 생해주는 기운이 넘쳐, 과보호·과잉학습·의존성의 문제가 나타납니다.',
                'effect'      => "• 생각이 많고 행동이 느립니다\n• 학문·이론에는 강하나 실전에 약합니다\n• 어머니(인성)의 영향이 과도하게 강합니다\n• 게으름·안주·의존적 성향이 나타납니다\n• 식상(식신·상관)을 극하여 표현력이 위축됩니다\n• 문서·계약 관련 어려움이 있을 수 있습니다",
                'advice'      => "아는 것을 실행에 옮기는 연습이 핵심입니다. 식상(표현 활동)을 의식적으로 강화하세요. 독립적인 생활을 통해 자립심을 기르는 것이 중요합니다.",
                'related'     => ['siksang_bujouk'],
                'detect'      => fn($ctx) => $ctx['sipsinGroups']['insung'] >= 3.0,
                'intensity'   => fn($ctx) => min(100, (int)($ctx['sipsinGroups']['insung'] * 25)),
            ],

            // ── 재성과다 ──
            [
                'id'          => 'jaesung_gwada',
                'name'        => '재성과다(財星過多)',
                'category'    => self::CATEGORY_SIPSIN,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['재성', '과다', '재물', '탐욕'],
                'description' => '재성(財星)이 과다합니다. 재물에 대한 관심과 욕구가 지나치게 강해, 오히려 인성을 극하고 일간을 소모시킵니다.',
                'effect'      => "• 재물·물질에 대한 집착이 매우 강합니다\n• 인성(학문·명예)을 극하여 학업이 어렵습니다\n• 돈을 쫓다가 정작 중요한 것을 놓칩니다\n• 남성은 여성 문제로 고생할 수 있습니다\n• 아버지와의 관계가 복잡합니다\n• 신약하면 과로·과욕으로 건강이 악화됩니다",
                'advice'      => "물질적 욕심을 다스리고 정신적 가치(인성)를 함께 추구하세요. 학문이나 자기계발로 내면을 채우면, 오히려 재물운도 안정됩니다.",
                'related'     => ['insung_bujouk', 'jaeda_sinyak'],
                'detect'      => fn($ctx) => $ctx['sipsinGroups']['jaesung'] >= 3.0,
                'intensity'   => fn($ctx) => min(100, (int)($ctx['sipsinGroups']['jaesung'] * 25)),
            ],

            // ── 관성과다 ──
            [
                'id'          => 'gwansung_gwada',
                'name'        => '관성과다(官星過多)',
                'category'    => self::CATEGORY_SIPSIN,
                'severity'    => self::SEVERITY_STRONG,
                'tags'        => ['관성', '과다', '직업', '압박'],
                'description' => '관성(官星)이 과다합니다. 사회적 제약·규율·압박이 과도하여 일간을 극심하게 억압합니다.',
                'effect'      => "• 직장·사회에서 스트레스가 극심합니다\n• 법·규칙·타인의 시선에 지나치게 신경 씁니다\n• 소심하고 두려움이 많아질 수 있습니다\n• 관재(官災)·법적 문제에 주의가 필요합니다\n• 여성은 남편·남자 문제가 복잡합니다\n• 건강 중 위장·소화기 계통이 약해지기 쉽습니다",
                'advice'      => "인성(학문·자격)으로 관성을 설기시키는 것이 핵심입니다. 과도한 책임은 나눠지고, 정신적 수양(명상, 종교)으로 내면의 평안을 찾으세요.",
                'related'     => ['gwanda_sinyak', 'bigyeop_bujouk'],
                'detect'      => fn($ctx) => $ctx['sipsinGroups']['gwansung'] >= 3.0,
                'intensity'   => fn($ctx) => min(100, (int)($ctx['sipsinGroups']['gwansung'] * 25)),
            ],
        ]);

        // ────────────────────────────────────────
        // ▸ 십성 부족 패턴
        // ────────────────────────────────────────

        self::registerAll([
            // ── 비겁부족 ──
            [
                'id'          => 'bigyeop_bujouk',
                'name'        => '비겁부족(比劫不足)',
                'category'    => self::CATEGORY_SIPSIN,
                'severity'    => self::SEVERITY_INFO,
                'tags'        => ['비겁', '부족', '대인관계', '독립'],
                'description' => '비겁(比劫)이 매우 부족합니다. 나를 도와줄 동료·형제의 힘이 없어 홀로 서야 합니다.',
                'effect'      => "• 외롭고 고독한 시간이 많습니다\n• 남의 도움 없이 혼자 해결해야 합니다\n• 경쟁 상황에서 불리합니다\n• 자아 정체성에 대한 고민이 있을 수 있습니다",
                'advice'      => "고독을 강점으로 만드세요. 혼자 하는 일(연구, 창작, 전문직)에서 빛나며, 소수 인맥을 깊이 관리하는 것이 좋습니다.",
                'related'     => ['gwanda_sinyak'],
                'detect'      => fn($ctx) => $ctx['sipsinGroups']['bigyeop'] <= 0.5,
                'intensity'   => fn($ctx) => min(100, (int)((1.0 - $ctx['sipsinGroups']['bigyeop']) * 80)),
            ],

            // ── 식상부족 ──
            [
                'id'          => 'siksang_bujouk',
                'name'        => '식상부족(食傷不足)',
                'category'    => self::CATEGORY_SIPSIN,
                'severity'    => self::SEVERITY_INFO,
                'tags'        => ['식상', '부족', '표현', '창의력'],
                'description' => '식상(食傷)이 매우 부족합니다. 자기표현과 창의적 발산의 통로가 막혀 있는 상태입니다.',
                'effect'      => "• 자기표현이 서툴고 속마음을 잘 드러내지 않습니다\n• 창의적 아이디어가 부족하거나 표현을 못합니다\n• 감정 해소가 어렵고 답답함을 느낍니다\n• 여성은 자녀 문제에 고민이 있을 수 있습니다",
                'advice'      => "글쓰기, 그림, 음악, 운동 등 표현 활동을 의식적으로 하세요. 속마음을 표현하는 연습이 스트레스 해소의 핵심입니다.",
                'related'     => ['insung_gwada'],
                'detect'      => fn($ctx) => $ctx['sipsinGroups']['siksang'] <= 0.5,
                'intensity'   => fn($ctx) => min(100, (int)((1.0 - $ctx['sipsinGroups']['siksang']) * 80)),
            ],

            // ── 재성부족 ──
            [
                'id'          => 'jaesung_bujouk',
                'name'        => '재성부족(財星不足)',
                'category'    => self::CATEGORY_SIPSIN,
                'severity'    => self::SEVERITY_INFO,
                'tags'        => ['재성', '부족', '재물'],
                'description' => '재성(財星)이 매우 부족합니다. 현실적 재물감각이 약하고, 돈보다 정신적 가치를 우선합니다.',
                'effect'      => "• 재물운이 약하고 돈에 대한 집착이 적습니다\n• 경제적 현실감이 부족할 수 있습니다\n• 이상주의적 성향이 강합니다\n• 남성은 이성과의 인연이 약할 수 있습니다",
                'advice'      => "재테크 교육이나 현실적 금전 감각을 기르세요. 식상(표현·기술)을 통해 재성(수입)으로 연결하는 경로를 만드는 것이 좋습니다.",
                'related'     => ['bigyeop_gwada'],
                'detect'      => fn($ctx) => $ctx['sipsinGroups']['jaesung'] <= 0.5,
                'intensity'   => fn($ctx) => min(100, (int)((1.0 - $ctx['sipsinGroups']['jaesung']) * 80)),
            ],

            // ── 관성부족 ──
            [
                'id'          => 'gwansung_bujouk',
                'name'        => '관성부족(官星不足)',
                'category'    => self::CATEGORY_SIPSIN,
                'severity'    => self::SEVERITY_INFO,
                'tags'        => ['관성', '부족', '직업', '규율'],
                'description' => '관성(官星)이 매우 부족합니다. 사회적 규율·통제력이 약하여 자유롭지만 방향성이 부족합니다.',
                'effect'      => "• 규칙에 얽매이지 않는 자유로운 영혼입니다\n• 조직 적응이 어렵고 이직이 잦을 수 있습니다\n• 명예·지위에 대한 욕심이 적습니다\n• 여성은 남편·남자와의 인연이 약할 수 있습니다",
                'advice'      => "자율적인 직업(프리랜서, 사업, 예술)이 적합합니다. 스스로 규율을 만들어 지키는 습관이 성공의 열쇠입니다.",
                'related'     => ['siksang_gwada'],
                'detect'      => fn($ctx) => $ctx['sipsinGroups']['gwansung'] <= 0.5,
                'intensity'   => fn($ctx) => min(100, (int)((1.0 - $ctx['sipsinGroups']['gwansung']) * 80)),
            ],

            // ── 인성부족 ──
            [
                'id'          => 'insung_bujouk',
                'name'        => '인성부족(印星不足)',
                'category'    => self::CATEGORY_SIPSIN,
                'severity'    => self::SEVERITY_INFO,
                'tags'        => ['인성', '부족', '학업', '모친'],
                'description' => '인성(印星)이 매우 부족합니다. 나를 보호하고 가르쳐줄 기운이 없어 스스로 모든 것을 터득해야 합니다.',
                'effect'      => "• 학업·학위가 순탄치 않을 수 있습니다\n• 어머니(인성)와의 인연이 약할 수 있습니다\n• 문서·계약 관련 실수에 주의해야 합니다\n• 보호막이 없어 관성의 극을 직접 받습니다",
                'advice'      => "독학 능력을 키우고, 실전 경험으로 지식을 채우세요. 멘토를 찾아 인성의 역할을 대신하게 하면 큰 도움이 됩니다.",
                'related'     => ['jaeda_sinyak', 'jaesung_gwada'],
                'detect'      => fn($ctx) => $ctx['sipsinGroups']['insung'] <= 0.5,
                'intensity'   => fn($ctx) => min(100, (int)((1.0 - $ctx['sipsinGroups']['insung']) * 80)),
            ],
        ]);

        // ────────────────────────────────────────
        // ▸ 오행 흐름(상생 체인) 패턴
        // ────────────────────────────────────────

        self::registerAll([
            // ── 식상생재 ──
            [
                'id'          => 'siksang_saengjae',
                'name'        => '식상생재(食傷生財)',
                'category'    => self::CATEGORY_FLOW,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['상생', '식상', '재성', '재물', '사업'],
                'description' => '식상(食傷)이 재성(財星)을 생하는 흐름입니다. 재능과 창의력이 실질적 재물로 연결되는 길(吉) 패턴입니다.',
                'effect'      => "• 창의적 활동이 수입으로 직결됩니다\n• 사업·투자 감각이 뛰어납니다\n• 아이디어를 돈으로 만드는 능력이 있습니다\n• 프리랜서·창업가·예술가적 잠재력이 큽니다",
                'advice'      => "이 흐름을 최대한 활용하세요. 자신의 재능을 상품화·서비스화하면 큰 부를 이룰 수 있습니다.",
                'related'     => ['siksang_gwada', 'jaesung_gwada'],
                'detect'      => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return $g['siksang'] >= 2.0 && $g['jaesung'] >= 2.0;
                },
                'intensity'   => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return min(100, (int)(($g['siksang'] + $g['jaesung']) * 18));
                },
            ],

            // ── 관인상생 ──
            [
                'id'          => 'gwanin_sangsaeng',
                'name'        => '관인상생(官印相生)',
                'category'    => self::CATEGORY_FLOW,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['상생', '관성', '인성', '명예', '직업'],
                'description' => '관성(官星)이 인성(印星)을 생하는 흐름입니다. 사회적 지위와 학문이 서로 강화하는 길(吉) 패턴입니다.',
                'effect'      => "• 공직·학계·대기업에서 승진이 빠릅니다\n• 명예와 학문이 선순환합니다\n• 윗사람의 도움과 귀인을 만나기 쉽습니다\n• 사회적 안정과 지위 상승이 기대됩니다",
                'advice'      => "안정적 조직에서 학위·자격증을 무기로 활용하세요. 공부와 경력을 병행하면 시너지가 극대화됩니다.",
                'related'     => ['gwansung_gwada', 'insung_gwada'],
                'detect'      => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return $g['gwansung'] >= 1.5 && $g['insung'] >= 1.5;
                },
                'intensity'   => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return min(100, (int)(($g['gwansung'] + $g['insung']) * 20));
                },
            ],

            // ── 재생관(재성→관성 상생) ──
            [
                'id'          => 'jae_saenggwan',
                'name'        => '재생관(財生官)',
                'category'    => self::CATEGORY_FLOW,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['상생', '재성', '관성', '승진', '지위'],
                'description' => '재성(財星)이 관성(官星)을 생하는 흐름입니다. 재물이 사회적 지위와 명예로 연결됩니다.',
                'effect'      => "• 경제력이 사회적 입지를 강화합니다\n• 돈으로 인맥·지위를 얻을 수 있습니다\n• 사업의 성공이 명예·존경으로 이어집니다\n• 승진·진급에 유리한 흐름입니다",
                'advice'      => "재물을 쌓으면 자연스럽게 지위가 따릅니다. 투자·사업의 성과가 사회적 인정으로 이어지는 구조를 만드세요.",
                'related'     => ['jaesung_gwada', 'gwansung_gwada'],
                'detect'      => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return $g['jaesung'] >= 2.0 && $g['gwansung'] >= 1.5;
                },
                'intensity'   => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return min(100, (int)(($g['jaesung'] + $g['gwansung']) * 18));
                },
            ],

            // ── 인비상생(인성→비겁 상생) ──
            [
                'id'          => 'inbi_sangsaeng',
                'name'        => '인비상생(印比相生)',
                'category'    => self::CATEGORY_FLOW,
                'severity'    => self::SEVERITY_INFO,
                'tags'        => ['상생', '인성', '비겁', '지원'],
                'description' => '인성(印星)이 일간(비겁)을 생하는 흐름이 강합니다. 학문·어른의 도움이 나를 강하게 키워주는 패턴입니다.',
                'effect'      => "• 어머니·스승·귀인의 도움을 잘 받습니다\n• 학문·자격으로 자신이 강해집니다\n• 보호받는 환경에서 안정적으로 성장합니다\n• 다만 과보호 시 의존적이 될 수 있습니다",
                'advice'      => "배움을 통해 자기 강화를 하되, 적절한 시기에 독립하여 자립심을 기르세요.",
                'related'     => ['insung_gwada', 'bigyeop_gwada'],
                'detect'      => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return $g['insung'] >= 2.0 && $g['bigyeop'] >= 1.5;
                },
                'intensity'   => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return min(100, (int)(($g['insung'] + $g['bigyeop']) * 18));
                },
            ],

            // ── 비겁생식상(비겁→식상 상생) ──
            [
                'id'          => 'bigyeop_saengsiksang',
                'name'        => '비겁생식(比劫生食)',
                'category'    => self::CATEGORY_FLOW,
                'severity'    => self::SEVERITY_INFO,
                'tags'        => ['상생', '비겁', '식상', '발산'],
                'description' => '비겁(比劫)이 식상(食傷)을 생하는 흐름입니다. 강한 자아가 창의적 표현으로 발산되는 건강한 패턴입니다.',
                'effect'      => "• 넘치는 에너지가 창작·표현으로 잘 발산됩니다\n• 자신감 있는 표현력을 가집니다\n• 행동력과 창의력이 결합되어 실행력이 뛰어납니다\n• 대인관계에서 활발하고 인기가 많습니다",
                'advice'      => "이 에너지 흐름을 재성(수입)으로 연결하면 이상적인 식상생재 체인이 완성됩니다. 자기 재능을 수익화하세요.",
                'related'     => ['bigyeop_gwada', 'siksang_gwada'],
                'detect'      => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return $g['bigyeop'] >= 2.0 && $g['siksang'] >= 1.5;
                },
                'intensity'   => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return min(100, (int)(($g['bigyeop'] + $g['siksang']) * 18));
                },
            ],
        ]);

        // ────────────────────────────────────────
        // ▸ 구조 패턴
        // ────────────────────────────────────────

        self::registerAll([
            // ── 재관혼잡 ──
            [
                'id'          => 'jaegwan_honjap',
                'name'        => '관살혼잡(官殺混雜)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_STRONG,
                'tags'        => ['관성', '혼잡', '직업', '갈등'],
                'description' => '정관과 편관(칠살)이 동시에 강하게 나타나, 관성의 성격이 일관되지 않고 혼란스럽습니다. 명리학에서 가장 기피하는 패턴 중 하나입니다.',
                'effect'      => "• 직장에서 두 상사 위에 서는 것처럼 갈등이 생깁니다\n• 이직·전직이 잦고 직업이 안정되지 않습니다\n• 사회적 평가가 일관되지 않습니다\n• 여성은 남자 문제가 복잡해질 수 있습니다\n• 남성은 직업과 사회생활에 파란이 많습니다\n• 법적 문제에 연루되기 쉽습니다",
                'advice'      => "편관을 식신으로 제어(식신제살)하거나, 인성으로 설기시키는 것이 핵심입니다. 하나의 전문 분야에 집중하여 관성의 에너지를 통합하세요.",
                'related'     => ['gwansung_gwada', 'siksin_jesal'],
                'detect'      => function ($ctx) {
                    $dist = $ctx['sipsinDist'];
                    return ($dist['편관'] ?? 0) >= 1.0 && ($dist['정관'] ?? 0) >= 1.0;
                },
                'intensity'   => function ($ctx) {
                    $dist = $ctx['sipsinDist'];
                    $mixed = ($dist['편관'] ?? 0) + ($dist['정관'] ?? 0);
                    return min(100, (int)($mixed * 25));
                },
            ],

            // ── 재관혼잡(재성+관성 동시 혼잡) ──
            [
                'id'          => 'jaegwan_honjap_wide',
                'name'        => '재관혼잡(財官混雜)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['재성', '관성', '혼잡', '복잡'],
                'description' => '편재·정재와 편관·정관이 모두 강하게 나타나, 재물과 직업 양측에서 복잡한 상황이 만들어집니다.',
                'effect'      => "• 돈과 직업 모두 불안정한 시기가 있습니다\n• 여러 가지 일을 동시에 벌이게 됩니다\n• 재물과 명예 사이에서 갈등합니다\n• 복잡한 인간관계로 에너지가 소모됩니다",
                'advice'      => "모든 것을 한 번에 잡으려 하지 마세요. 하나의 목표에 집중하고, 나머지는 차례로 정리하는 것이 지혜입니다.",
                'related'     => ['jaegwan_honjap', 'jaesung_gwada'],
                'detect'      => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    $dist = $ctx['sipsinDist'];
                    return $g['jaesung'] >= 2.0 && $g['gwansung'] >= 2.0 &&
                        ($dist['편재'] ?? 0) >= 0.5 && ($dist['정재'] ?? 0) >= 0.5 &&
                        ($dist['편관'] ?? 0) >= 0.5 && ($dist['정관'] ?? 0) >= 0.5;
                },
                'intensity'   => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return min(100, (int)(($g['jaesung'] + $g['gwansung']) * 15));
                },
            ],

            // ── 상관견관 ──
            [
                'id'          => 'sanggwan_gyeongwan',
                'name'        => '상관견관(傷官見官)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_STRONG,
                'tags'        => ['식상', '관성', '갈등', '반항'],
                'description' => '상관(傷官)과 관성(官星)이 동시에 나타나 정면 충돌합니다. 자유로운 영혼이 사회적 규율과 부딪히는 날카로운 패턴입니다.',
                'effect'      => "• 윗사람·조직과 마찰이 매우 심합니다\n• 반항심이 강하고 권위에 도전합니다\n• 직장 생활이 순탄하지 않습니다\n• 재능은 뛰어나지만 인정받기 어렵습니다\n• 구설·시비에 휘말리기 쉽습니다\n• 여성은 남편과의 갈등이 심할 수 있습니다",
                'advice'      => "자유로운 환경에서 일하세요. 조직보다 프리랜서·사업이 적합합니다. 상관의 예리함을 건설적으로 활용하면(평론, 법조, 의술) 오히려 대성합니다.",
                'related'     => ['siksang_gwada', 'gwansung_gwada'],
                'detect'      => function ($ctx) {
                    $dist = $ctx['sipsinDist'];
                    return ($dist['상관'] ?? 0) >= 1.0 && $ctx['sipsinGroups']['gwansung'] >= 1.5;
                },
                'intensity'   => function ($ctx) {
                    $dist = $ctx['sipsinDist'];
                    return min(100, (int)((($dist['상관'] ?? 0) + $ctx['sipsinGroups']['gwansung']) * 22));
                },
            ],

            // ── 식신제살 ──
            [
                'id'          => 'siksin_jesal',
                'name'        => '식신제살(食神制殺)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_INFO,
                'tags'        => ['식상', '편관', '제어', '길패턴'],
                'description' => '식신(食神)이 편관(七殺)을 적절히 제어하는 길(吉) 패턴입니다. 위험한 편관의 에너지를 지혜롭게 다스립니다.',
                'effect'      => "• 위기 상황에서 냉정하고 지혜로운 대처 능력이 있습니다\n• 강한 압박을 부드럽게 제어합니다\n• 군인·경찰·의사·법조인 등 분야에 적합합니다\n• 리더십이 뛰어나고 부하를 잘 다스립니다\n• 위험을 기회로 바꾸는 능력이 탁월합니다",
                'advice'      => "이 패턴은 매우 좋은 구조입니다. 도전적인 환경에서 빛을 발합니다. 편관의 에너지를 두려워하지 말고 최대한 활용하세요.",
                'related'     => ['siksang_gwada', 'gwansung_gwada'],
                'detect'      => function ($ctx) {
                    $dist = $ctx['sipsinDist'];
                    return ($dist['식신'] ?? 0) >= 1.5 && ($dist['편관'] ?? 0) >= 1.5;
                },
                'intensity'   => function ($ctx) {
                    $dist = $ctx['sipsinDist'];
                    return min(100, (int)((($dist['식신'] ?? 0) + ($dist['편관'] ?? 0)) * 22));
                },
            ],

            // ── 살인상생 ──
            [
                'id'          => 'salin_sangsaeng',
                'name'        => '살인상생(殺印相生)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_INFO,
                'tags'        => ['편관', '인성', '상생', '길패턴'],
                'description' => '편관(七殺)이 인성(印星)을 생하여, 위험한 관살의 에너지가 학문·지혜로 변환되는 길(吉) 패턴입니다.',
                'effect'      => "• 어려운 환경에서 오히려 실력이 늡니다\n• 위기를 학습과 성장의 기회로 삼습니다\n• 학문·연구 분야에서 큰 성과를 냅니다\n• 권력과 지혜를 겸비한 인물이 됩니다\n• 군사·법학·의학 등 분야에 적합합니다",
                'advice'      => "편관의 압박을 성장 동력으로 활용하세요. 학문과 경력을 병행하면 시너지가 극대화됩니다.",
                'related'     => ['gwanin_sangsaeng'],
                'detect'      => function ($ctx) {
                    $dist = $ctx['sipsinDist'];
                    $g = $ctx['sipsinGroups'];
                    return ($dist['편관'] ?? 0) >= 1.0 && $g['insung'] >= 1.5;
                },
                'intensity'   => function ($ctx) {
                    $dist = $ctx['sipsinDist'];
                    $g = $ctx['sipsinGroups'];
                    return min(100, (int)((($dist['편관'] ?? 0) + $g['insung']) * 22));
                },
            ],

            // ── 재관쌍미 ──
            [
                'id'          => 'jaegwan_ssangmi',
                'name'        => '재관쌍미(財官雙美)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_INFO,
                'tags'        => ['재성', '관성', '길패턴', '부귀'],
                'description' => '재성과 관성이 적절히 균형을 이루어, 재물과 명예를 동시에 누리는 이상적인 패턴입니다.',
                'effect'      => "• 재물과 사회적 지위를 동시에 얻습니다\n• 경제적 안정과 사회적 인정이 함께 옵니다\n• 사업과 직장 모두에서 성공 가능성이 높습니다\n• 부와 명예가 서로를 강화합니다",
                'advice'      => "이 좋은 균형을 유지하세요. 재물과 명예 중 하나에 치우치지 않는 것이 핵심입니다.",
                'related'     => ['jae_saenggwan'],
                'detect'      => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return $g['jaesung'] >= 1.5 && $g['jaesung'] <= 3.0 &&
                        $g['gwansung'] >= 1.5 && $g['gwansung'] <= 3.0;
                },
                'intensity'   => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    // 재/관이 비슷할수록 높은 점수
                    $diff = abs($g['jaesung'] - $g['gwansung']);
                    $balance = max(0, 100 - $diff * 30);
                    return min(100, (int)($balance * 0.7 + ($g['jaesung'] + $g['gwansung']) * 5));
                },
            ],
        ]);

        // ────────────────────────────────────────
        // ▸ 특수 패턴
        // ────────────────────────────────────────

        self::registerAll([
            // ── 종재격 경향 ──
            [
                'id'          => 'jongjae_tendency',
                'name'        => '종재격 경향(從財格)',
                'category'    => self::CATEGORY_SPECIAL,
                'severity'    => self::SEVERITY_STRONG,
                'tags'        => ['종격', '재성', '특수'],
                'description' => '일간이 극도로 약하고 재성이 사주를 지배하여, 재성을 따라가는(종하는) 종재격의 경향이 보입니다.',
                'effect'      => "• 재물의 흐름에 몸을 맡기면 성공합니다\n• 사업가·투자자로서 큰 재물을 다룰 수 있습니다\n• 자아를 내세우기보다 현실에 순응하는 것이 유리합니다\n• 인성·비겁이 오면 오히려 불리합니다",
                'advice'      => "재성의 흐름을 거스르지 마세요. 사업·투자·재테크 분야에서 능력을 발휘하되, 인성(학문)이 강해지는 시기에는 주의하세요.",
                'related'     => ['jaeda_sinyak', 'jaesung_gwada'],
                'detect'      => function ($ctx) {
                    return $ctx['strengthRatio'] <= 0.25 &&
                        $ctx['sipsinGroups']['jaesung'] >= 3.5 &&
                        $ctx['sipsinGroups']['bigyeop'] <= 1.0 &&
                        $ctx['sipsinGroups']['insung'] <= 0.5;
                },
                'intensity'   => function ($ctx) {
                    return min(100, (int)($ctx['sipsinGroups']['jaesung'] * 20 + (0.3 - $ctx['strengthRatio']) * 100));
                },
            ],

            // ── 종관격 경향 ──
            [
                'id'          => 'jonggwan_tendency',
                'name'        => '종관격 경향(從官格)',
                'category'    => self::CATEGORY_SPECIAL,
                'severity'    => self::SEVERITY_STRONG,
                'tags'        => ['종격', '관성', '특수'],
                'description' => '일간이 극도로 약하고 관성이 사주를 지배하여, 관성을 따르는 종관격의 경향이 보입니다.',
                'effect'      => "• 조직·권력의 흐름에 순응하면 출세합니다\n• 공직·대기업 등 체계적 조직에서 성공합니다\n• 자아를 낮추고 조직에 헌신하는 것이 유리합니다\n• 비겁이 오면 오히려 조직과 충돌합니다",
                'advice'      => "조직의 질서를 존중하고 순응하세요. 공무원·대기업·군인 등 체계적 조직에서 큰 성과를 거둘 수 있습니다.",
                'related'     => ['gwanda_sinyak', 'gwansung_gwada'],
                'detect'      => function ($ctx) {
                    return $ctx['strengthRatio'] <= 0.25 &&
                        $ctx['sipsinGroups']['gwansung'] >= 3.5 &&
                        $ctx['sipsinGroups']['bigyeop'] <= 1.0 &&
                        $ctx['sipsinGroups']['insung'] <= 0.5;
                },
                'intensity'   => function ($ctx) {
                    return min(100, (int)($ctx['sipsinGroups']['gwansung'] * 20 + (0.3 - $ctx['strengthRatio']) * 100));
                },
            ],

            // ── 종아격 경향 ──
            [
                'id'          => 'jonga_tendency',
                'name'        => '종아격 경향(從兒格)',
                'category'    => self::CATEGORY_SPECIAL,
                'severity'    => self::SEVERITY_STRONG,
                'tags'        => ['종격', '식상', '특수', '예술'],
                'description' => '일간이 극도로 약하고 식상이 사주를 지배하여, 식상을 따르는 종아격의 경향이 보입니다.',
                'effect'      => "• 예술·창작·표현 분야에서 큰 재능을 발휘합니다\n• 자유로운 영혼으로 조직에 맞지 않습니다\n• 창의적 분야에서 대가(大家)가 될 수 있습니다\n• 인성이 오면 오히려 창의력이 죽습니다",
                'advice'      => "예술·창작·연예·디자인 등 표현 분야가 천직입니다. 자유롭게 표현하고 그것을 수입으로 연결하세요.",
                'related'     => ['siksang_gwada'],
                'detect'      => function ($ctx) {
                    return $ctx['strengthRatio'] <= 0.25 &&
                        $ctx['sipsinGroups']['siksang'] >= 3.5 &&
                        $ctx['sipsinGroups']['bigyeop'] <= 1.0 &&
                        $ctx['sipsinGroups']['insung'] <= 0.5;
                },
                'intensity'   => function ($ctx) {
                    return min(100, (int)($ctx['sipsinGroups']['siksang'] * 20 + (0.3 - $ctx['strengthRatio']) * 100));
                },
            ],

            // ── 비겁격 경향 (건록격/양인격) ──
            [
                'id'          => 'bigyeop_geuk',
                'name'        => '건록/양인격 경향(建祿/羊刃格)',
                'category'    => self::CATEGORY_SPECIAL,
                'severity'    => self::SEVERITY_STRONG,
                'tags'        => ['비겁', '격국', '신강', '독립'],
                'description' => '비겁이 극도로 왕성하여 일간이 매우 강합니다. 건록격·양인격의 경향으로, 강한 자아와 독립심이 특징입니다.',
                'effect'      => "• 매우 강인한 독립심과 자존심\n• 남에게 절대 굽히지 않습니다\n• 사업가·리더·운동선수 기질이 강합니다\n• 재성을 극하여 재물 관리에 주의 필요\n• 배우자와 갈등이 생길 수 있습니다",
                'advice'      => "식상으로 설기하여 에너지를 발산하세요. 관성(사회적 규울)도 적절히 받아들이면 리더로서 크게 성공합니다.",
                'related'     => ['bigyeop_gwada', 'singang_muje'],
                'detect'      => function ($ctx) {
                    return $ctx['strengthRatio'] >= 0.60 &&
                        $ctx['sipsinGroups']['bigyeop'] >= 3.5;
                },
                'intensity'   => function ($ctx) {
                    return min(100, (int)($ctx['sipsinGroups']['bigyeop'] * 18 + $ctx['strengthRatio'] * 40));
                },
            ],

            // ── 인수격 경향 ──
            [
                'id'          => 'insu_geuk',
                'name'        => '인수격 경향(印綬格)',
                'category'    => self::CATEGORY_SPECIAL,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['인성', '격국', '학문'],
                'description' => '인성이 사주의 핵심 구조를 이루고 있습니다. 학문·명예·정신적 가치를 중시하는 인수격의 경향입니다.',
                'effect'      => "• 학문·연구 분야에서 뛰어난 재능\n• 명예와 학위가 인생의 핵심가치\n• 교육·학술·출판 분야에 적합\n• 물질보다 정신을 중시합니다",
                'advice'      => "학문을 통한 성취가 당신의 길입니다. 교수·연구원·교사·작가 등 인성 관련 직업에서 빛을 발합니다.",
                'related'     => ['insung_gwada', 'gwanin_sangsaeng'],
                'detect'      => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    return $g['insung'] >= 2.5 && 
                        $g['insung'] > $g['bigyeop'] && 
                        $g['insung'] > $g['jaesung'];
                },
                'intensity'   => function ($ctx) {
                    return min(100, (int)($ctx['sipsinGroups']['insung'] * 25));
                },
            ],
        ]);

        // ────────────────────────────────────────
        // ▸ 균형/오행 밸런스 패턴
        // ────────────────────────────────────────

        self::registerAll([
            // ── 오행 완전 편중 ──
            [
                'id'          => 'ohang_pyeonjung',
                'name'        => '오행편중(五行偏重)',
                'category'    => self::CATEGORY_BALANCE,
                'severity'    => self::SEVERITY_STRONG,
                'tags'        => ['오행', '편중', '불균형'],
                'description' => '하나의 오행이 전체의 40% 이상을 차지하여, 오행의 균형이 심하게 무너진 상태입니다.',
                'effect'      => "• 해당 오행의 성질이 극단적으로 나타납니다\n• 부족한 오행과 관련된 건강 문제 주의\n• 성격이 한쪽으로 치우쳐 유연성이 부족합니다\n• 편중된 오행의 장기(臟器)가 과부하 될 수 있습니다",
                'advice'      => "부족한 오행을 보충하세요. 색상·방위·음식·직업 등으로 부족한 기운을 채우는 것이 건강과 운세에 도움이 됩니다.",
                'related'     => [],
                'detect'      => function ($ctx) {
                    foreach ($ctx['ohangRatio'] as $ratio) {
                        if ($ratio >= 40.0) return true;
                    }
                    return false;
                },
                'intensity'   => function ($ctx) {
                    $max = max($ctx['ohangRatio']);
                    return min(100, (int)(($max - 20) * 2.5));
                },
            ],

            // ── 오행 결핍 ──
            [
                'id'          => 'ohang_gyeolhip',
                'name'        => '오행결핍(五行缺乏)',
                'category'    => self::CATEGORY_BALANCE,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['오행', '결핍', '불균형', '건강'],
                'description' => '하나 이상의 오행이 5% 미만으로 거의 없는 상태입니다. 해당 오행과 관련된 기능이 매우 약합니다.',
                'effect'      => "• 결핍된 오행의 장기(臟器)가 약합니다\n• 해당 오행의 성격 특성이 부족합니다\n• 관련 인간관계(십성)에 어려움이 있을 수 있습니다\n• 해당 방위·계절에 약해지기 쉽습니다",
                'advice'      => "결핍된 오행을 적극적으로 보충하세요. 해당 오행의 색상 착용, 방위 활용, 관련 음식 섭취가 도움됩니다.",
                'related'     => [],
                'detect'      => function ($ctx) {
                    foreach ($ctx['ohangRatio'] as $ratio) {
                        if ($ratio < 5.0) return true;
                    }
                    return false;
                },
                'intensity'   => function ($ctx) {
                    $minCount = 0;
                    foreach ($ctx['ohangRatio'] as $ratio) {
                        if ($ratio < 5.0) $minCount++;
                    }
                    return min(100, $minCount * 40);
                },
            ],

            // ── 오행 조화 (길 패턴) ──
            [
                'id'          => 'ohang_johwa',
                'name'        => '오행조화(五行調和)',
                'category'    => self::CATEGORY_BALANCE,
                'severity'    => self::SEVERITY_INFO,
                'tags'        => ['오행', '균형', '길패턴', '조화'],
                'description' => '오행이 비교적 고르게 분포되어 있어, 전반적으로 안정적이고 조화로운 사주입니다.',
                'effect'      => "• 성격이 균형 잡혀 있고 유연합니다\n• 건강이 비교적 안정적입니다\n• 어떤 환경에도 잘 적응합니다\n• 극단적인 성공보다는 안정적인 삶을 살기 쉽습니다",
                'advice'      => "이 조화로움을 유지하세요. 극단적인 변화보다 꾸준한 노력이 성공의 열쇠입니다.",
                'related'     => [],
                'detect'      => function ($ctx) {
                    $ratios = array_values($ctx['ohangRatio']);
                    $max = max($ratios);
                    $min = min($ratios);
                    return ($max - $min) <= 20.0 && $min >= 8.0;
                },
                'intensity'   => function ($ctx) {
                    $ratios = array_values($ctx['ohangRatio']);
                    $diff = max($ratios) - min($ratios);
                    return max(30, min(100, (int)(100 - $diff * 3)));
                },
            ],

            // ── 중화 (신강신약 중간) ──
            [
                'id'          => 'junghwa',
                'name'        => '중화(中和)',
                'category'    => self::CATEGORY_BALANCE,
                'severity'    => self::SEVERITY_INFO,
                'tags'        => ['중화', '균형', '길패턴'],
                'description' => '일간의 신강/신약이 중화(中和) 상태로, 가장 이상적인 밸런스입니다. 명리학에서 가장 귀한 상태로 여깁니다.',
                'effect'      => "• 상황에 따라 유연하게 대처합니다\n• 극단에 빠지지 않는 안정성이 있습니다\n• 어떤 직업·환경에도 적응 가능합니다\n• 대운의 영향을 크게 타지 않습니다",
                'advice'      => "중화는 가장 좋은 상태입니다. 이 균형을 유지하면서 자신의 강점을 살리는 방향으로 나아가세요.",
                'related'     => ['ohang_johwa'],
                'detect'      => fn($ctx) => $ctx['strength'] === '중화',
                'intensity'   => function ($ctx) {
                    $dist = abs($ctx['strengthRatio'] - 0.44);
                    return max(40, min(100, (int)(100 - $dist * 400)));
                },
            ],

            // ── 십성 편중 (하나의 십성 그룹이 지배) ──
            [
                'id'          => 'sipsin_pyeonjung',
                'name'        => '십성편중(十星偏重)',
                'category'    => self::CATEGORY_BALANCE,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['십성', '편중', '불균형'],
                'description' => '하나의 십성 그룹이 전체 십성의 40% 이상을 차지하여, 해당 십성의 영향이 사주를 지배합니다.',
                'effect'      => "• 해당 십성의 성격과 운세가 극대화됩니다\n• 다른 십성의 영향이 약해져 편향된 삶이 될 수 있습니다\n• 해당 십성이 나타내는 인간관계가 복잡합니다",
                'advice'      => "편중된 십성의 에너지를 건설적으로 활용하되, 부족한 십성의 역할을 의식적으로 보충하세요.",
                'related'     => [],
                'detect'      => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    $total = array_sum($g) ?: 1;
                    foreach ($g as $val) {
                        if (($val / $total) >= 0.40) return true;
                    }
                    return false;
                },
                'intensity'   => function ($ctx) {
                    $g = $ctx['sipsinGroups'];
                    $total = array_sum($g) ?: 1;
                    $maxRatio = 0;
                    foreach ($g as $val) {
                        $maxRatio = max($maxRatio, $val / $total);
                    }
                    return min(100, (int)($maxRatio * 150));
                },
            ],
        ]);

        // ────────────────────────────────────────
        // ▸ 합충 관련 패턴
        // ────────────────────────────────────────

        self::registerAll([
            // ── 충 다수 ──
            [
                'id'          => 'chung_dasu',
                'name'        => '충다(沖多)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['충', '변동', '이동'],
                'description' => '사주 원국에 충(沖)이 2개 이상 있어, 삶에 변동과 이동이 많은 구조입니다.',
                'effect'      => "• 이사·이직·변화가 잦습니다\n• 안정보다 변동의 삶을 삽니다\n• 갈등과 파격을 통해 성장합니다\n• 한 곳에 머물기 어렵습니다",
                'advice'      => "변화를 두려워하지 마세요. 충은 정체를 깨뜨리는 힘이기도 합니다. 적응력을 키우면 매번 더 나은 곳으로 이동할 수 있습니다.",
                'related'     => [],
                'detect'      => function ($ctx) {
                    $count = count($ctx['relTypes']['충'] ?? []);
                    return $count >= 2;
                },
                'intensity'   => function ($ctx) {
                    $count = count($ctx['relTypes']['충'] ?? []);
                    return min(100, $count * 35);
                },
            ],

            // ── 합 다수 ──
            [
                'id'          => 'hap_dasu',
                'name'        => '합다(合多)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_INFO,
                'tags'        => ['합', '대인관계', '인연'],
                'description' => '사주 원국에 합(六合·三合·天干合)이 2개 이상 있어, 인연과 관계가 풍부한 구조입니다.',
                'effect'      => "• 대인관계가 풍부하고 인연이 많습니다\n• 사교적이고 남들과 잘 어울립니다\n• 결합·모임·협력에 유리합니다\n• 너무 많으면 우유부단해질 수 있습니다",
                'advice'      => "풍부한 인연을 잘 관리하세요. 좋은 합은 귀인을 만나게 하고, 나쁜 합은 이끌려 다니게 만듭니다. 주체성을 유지하는 것이 중요합니다.",
                'related'     => [],
                'detect'      => function ($ctx) {
                    $count = count($ctx['relTypes']['육합'] ?? []) +
                        count($ctx['relTypes']['삼합'] ?? []) +
                        count($ctx['relTypes']['천간합'] ?? []);
                    return $count >= 2;
                },
                'intensity'   => function ($ctx) {
                    $count = count($ctx['relTypes']['육합'] ?? []) +
                        count($ctx['relTypes']['삼합'] ?? []) +
                        count($ctx['relTypes']['천간합'] ?? []);
                    return min(100, $count * 30);
                },
            ],

            // ── 형 존재 ──
            [
                'id'          => 'hyung_exist',
                'name'        => '형존재(刑存在)',
                'category'    => self::CATEGORY_STRUCTURE,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['형', '시련', '법', '건강'],
                'description' => '사주 원국에 형(刑)이 존재하여, 시련과 마찰을 통한 성장의 과정이 있는 구조입니다.',
                'effect'      => "• 법적 문제·소송에 주의가 필요합니다\n• 인간관계에서 갈등과 마찰이 있습니다\n• 건강 문제(수술, 사고)에 주의합니다\n• 시련을 통해 단단해지는 힘이 있습니다",
                'advice'      => "법·계약 관련 일에 신중하세요. 건강 검진을 정기적으로 받고, 대인관계에서 한 발 물러서는 지혜가 필요합니다.",
                'related'     => ['chung_dasu'],
                'detect'      => function ($ctx) {
                    return count($ctx['relTypes']['형'] ?? []) >= 1;
                },
                'intensity'   => function ($ctx) {
                    $count = count($ctx['relTypes']['형'] ?? []);
                    return min(100, $count * 45);
                },
            ],
        ]);

        // ────────────────────────────────────────
        // ▸ 12운성 기반 패턴
        // ────────────────────────────────────────

        self::registerAll([
            // ── 제왕/건록 다수 (왕성한 기운) ──
            [
                'id'          => 'wangseong_unyeong',
                'name'        => '왕성운성(旺盛運星)',
                'category'    => self::CATEGORY_STRENGTH,
                'severity'    => self::SEVERITY_INFO,
                'tags'        => ['12운성', '제왕', '건록', '왕성'],
                'description' => '12운성에 제왕(帝旺)·건록(建祿)이 많아, 기운이 왕성한 시기에 태어났습니다.',
                'effect'      => "• 왕성한 활력과 추진력\n• 리더십과 주도적 성격\n• 에너지가 넘치고 적극적\n• 과신·오만에 주의 필요",
                'advice'      => "넘치는 에너지를 건설적으로 사용하세요. 과신하지 않도록 자기 성찰을 병행하면 큰 성취를 이룰 수 있습니다.",
                'related'     => ['bigyeop_gwada', 'singang_muje'],
                'detect'      => function ($ctx) {
                    $strong = ['제왕', '건록'];
                    $count = 0;
                    foreach ($ctx['twelveStages'] as $st) {
                        if (in_array($st, $strong)) $count++;
                    }
                    return $count >= 2;
                },
                'intensity'   => function ($ctx) {
                    $strong = ['제왕', '건록'];
                    $count = 0;
                    foreach ($ctx['twelveStages'] as $st) {
                        if (in_array($st, $strong)) $count++;
                    }
                    return min(100, $count * 35);
                },
            ],

            // ── 사묘절 다수 (쇠약한 기운) ──
            [
                'id'          => 'soeyak_unyeong',
                'name'        => '쇠약운성(衰弱運星)',
                'category'    => self::CATEGORY_STRENGTH,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['12운성', '사', '묘', '절', '쇠약'],
                'description' => '12운성에 사(死)·묘(墓)·절(絶)이 많아, 기운이 약한 시기에 태어났습니다.',
                'effect'      => "• 체력이 약하고 무기력함을 느끼기 쉽습니다\n• 내적 성찰과 정신적 깊이가 특징\n• 새로운 시작에 어려움이 있으나 내면이 깊습니다\n• 건강 관리에 특별한 주의 필요",
                'advice'      => "체력 관리를 최우선으로 하세요. 대운에서 장생·관대가 오면 새로운 시작이 열립니다. 정신적 깊이를 학문·예술로 승화하세요.",
                'related'     => ['sinyak_mujo'],
                'detect'      => function ($ctx) {
                    $weak = ['사', '묘', '절'];
                    $count = 0;
                    foreach ($ctx['twelveStages'] as $st) {
                        if (in_array($st, $weak)) $count++;
                    }
                    return $count >= 2;
                },
                'intensity'   => function ($ctx) {
                    $weak = ['사', '묘', '절'];
                    $count = 0;
                    foreach ($ctx['twelveStages'] as $st) {
                        if (in_array($st, $weak)) $count++;
                    }
                    return min(100, $count * 35);
                },
            ],
        ]);

        // ────────────────────────────────────────
        // ▸ 성별 특화 패턴
        // ────────────────────────────────────────

        self::registerAll([
            // ── 여성: 관살혼잡 (남편 문제) ──
            [
                'id'          => 'female_gwansal_honjap',
                'name'        => '여명 관살혼잡(女命官殺混雜)',
                'category'    => self::CATEGORY_SPECIAL,
                'severity'    => self::SEVERITY_STRONG,
                'tags'        => ['여성', '관성', '혼잡', '배우자'],
                'description' => '여성 사주에서 정관과 편관이 동시에 강하면, 남편(관)의 역할이 분산되어 결혼·연애에 복잡한 상황이 생길 수 있습니다.',
                'effect'      => "• 연애·결혼 관계가 단순하지 않습니다\n• 이성 문제로 고민이 많을 수 있습니다\n• 직장에서도 갈등 요소가 있습니다\n• 결혼 시기가 늦어지거나 재혼 가능성",
                'advice'      => "관성의 에너지를 직업·사회활동으로 승화하세요. 식신으로 편관을 제어하면 관계가 안정됩니다.",
                'related'     => ['jaegwan_honjap'],
                'detect'      => function ($ctx) {
                    if ($ctx['gender'] !== 'female') return false;
                    $dist = $ctx['sipsinDist'];
                    return ($dist['편관'] ?? 0) >= 1.0 && ($dist['정관'] ?? 0) >= 1.0;
                },
                'intensity'   => function ($ctx) {
                    $dist = $ctx['sipsinDist'];
                    return min(100, (int)((($dist['편관'] ?? 0) + ($dist['정관'] ?? 0)) * 25));
                },
            ],

            // ── 남성: 재성혼잡 (여성/재물 문제) ──
            [
                'id'          => 'male_jaesung_honjap',
                'name'        => '남명 재성혼잡(男命財星混雜)',
                'category'    => self::CATEGORY_SPECIAL,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['남성', '재성', '혼잡', '배우자'],
                'description' => '남성 사주에서 편재와 정재가 동시에 강하면, 처(정재)와 첩(편재)의 상이 혼재되어 이성·재물 관계가 복잡해집니다.',
                'effect'      => "• 이성 관계가 다양하고 복잡합니다\n• 재물의 출처가 여러 곳에서 옵니다\n• 투자·사업에서 정재와 편재가 섞입니다\n• 바람·외도의 유혹에 주의 필요",
                'advice'      => "하나의 관계에 집중하고, 재물도 정도(正道)를 걸으세요. 편재의 유혹을 경계하면 안정적인 삶을 유지할 수 있습니다.",
                'related'     => ['jaesung_gwada'],
                'detect'      => function ($ctx) {
                    if ($ctx['gender'] !== 'male') return false;
                    $dist = $ctx['sipsinDist'];
                    return ($dist['편재'] ?? 0) >= 1.0 && ($dist['정재'] ?? 0) >= 1.0;
                },
                'intensity'   => function ($ctx) {
                    $dist = $ctx['sipsinDist'];
                    return min(100, (int)((($dist['편재'] ?? 0) + ($dist['정재'] ?? 0)) * 25));
                },
            ],

            // ── 여성: 식상과다 (자녀·남편 갈등) ──
            [
                'id'          => 'female_siksang_gwada',
                'name'        => '여명 식상과다(女命食傷過多)',
                'category'    => self::CATEGORY_SPECIAL,
                'severity'    => self::SEVERITY_MODERATE,
                'tags'        => ['여성', '식상', '과다', '배우자', '자녀'],
                'description' => '여성 사주에서 식상이 과다하면, 남편(관성)을 극하여 결혼생활에 마찰이 생기기 쉽고, 자녀 문제가 복잡해질 수 있습니다.',
                'effect'      => "• 남편과의 갈등이 잦을 수 있습니다\n• 자녀에 대한 걱정·기대가 과합니다\n• 표현이 직설적이라 오해를 살 수 있습니다\n• 결혼보다 자유로운 삶을 선호합니다",
                'advice'      => "상대방의 입장에서 한 번 더 생각하세요. 표현의 강도를 조절하면 관계가 크게 개선됩니다.",
                'related'     => ['siksang_gwada', 'sanggwan_gyeongwan'],
                'detect'      => function ($ctx) {
                    if ($ctx['gender'] !== 'female') return false;
                    return $ctx['sipsinGroups']['siksang'] >= 3.0;
                },
                'intensity'   => fn($ctx) => min(100, (int)($ctx['sipsinGroups']['siksang'] * 25)),
            ],
        ]);
    }
}
