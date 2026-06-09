<?php
/**
 * ================================================================
 * 사주 스토리 생성기 (SajuStoryGenerator) — 3단계 모듈
 * ================================================================
 * 
 * PatternDetector(1단계)가 감지한 패턴과
 * PatternInterpretationData(2단계)의 해석 문장을 조합하여
 * 자연스러운 사주 해석 스토리를 생성합니다.
 * 
 * [스토리 구조 (7섹션)]
 * 1. 사주 구조 설명    — 사주 기본 구성, 일간, 오행 분포, 신강/신약 개요
 * 2. 성격 분석         — personality 카테고리 기반
 * 3. 재능 분석         — talent 카테고리 기반
 * 4. 직업 방향         — career 카테고리 기반
 * 5. 인간관계 특징     — relationship 카테고리 기반
 * 6. 재물 흐름         — wealth 카테고리 기반
 * 7. 인생 흐름         — life_flow 카테고리 기반
 * 
 * [설계 원칙]
 * 1. 최소 3000자 이상 생성
 * 2. 패턴 간 우선순위·가중치 반영
 * 3. 자연스러운 연결어·전환문 삽입
 * 4. 성별·신강신약 맞춤 해석
 * 5. 반복 문장 제거
 * 
 * [사용법]
 *   $engine   = new SajuEngine($y, $m, $d, $h, $gender);
 *   $story    = new SajuStoryGenerator($engine);
 *   $result   = $story->generate();
 *   // → ['title'=>..., 'sections'=>[...], 'full_text'=>..., 'char_count'=>..., 'meta'=>...]
 */

require_once __DIR__ . '/SajuEngine.php';
require_once __DIR__ . '/OhangAnalysis.php';
require_once __DIR__ . '/PatternDetector.php';
require_once __DIR__ . '/PatternInterpretationData.php';

class SajuStoryGenerator {

    // ========================================================
    // 상수
    // ========================================================

    /** 스토리 섹션 ID */
    const SECTION_STRUCTURE    = 'saju_structure';
    const SECTION_PERSONALITY  = 'personality';
    const SECTION_TALENT       = 'talent';
    const SECTION_CAREER       = 'career';
    const SECTION_RELATIONSHIP = 'relationship';
    const SECTION_WEALTH       = 'wealth';
    const SECTION_LIFE_FLOW    = 'life_flow';

    /** 섹션 순서 및 메타 */
    const SECTION_META = [
        self::SECTION_STRUCTURE => [
            'order'   => 1,
            'title'   => '사주 구조 분석',
            'icon'    => '🏛️',
            'intro'   => '먼저 당신의 사주 기본 구조를 살펴보겠습니다.',
        ],
        self::SECTION_PERSONALITY => [
            'order'   => 2,
            'title'   => '성격과 기질',
            'icon'    => '🧠',
            'intro'   => '사주에 나타난 당신의 성격과 내면 세계를 분석합니다.',
        ],
        self::SECTION_TALENT => [
            'order'   => 3,
            'title'   => '재능과 적성',
            'icon'    => '✨',
            'intro'   => '당신이 타고난 재능과 잠재력을 살펴봅니다.',
        ],
        self::SECTION_CAREER => [
            'order'   => 4,
            'title'   => '직업과 사회생활',
            'icon'    => '💼',
            'intro'   => '사회적 활동과 직업적 방향성을 분석합니다.',
        ],
        self::SECTION_RELATIONSHIP => [
            'order'   => 5,
            'title'   => '인간관계와 연애·결혼',
            'icon'    => '❤️',
            'intro'   => '대인관계, 연애, 결혼, 가족 관계의 특징을 살펴봅니다.',
        ],
        self::SECTION_WEALTH => [
            'order'   => 6,
            'title'   => '재물과 금전운',
            'icon'    => '💰',
            'intro'   => '재물의 흐름과 경제 활동의 패턴을 분석합니다.',
        ],
        self::SECTION_LIFE_FLOW => [
            'order'   => 7,
            'title'   => '인생의 흐름',
            'icon'    => '🌊',
            'intro'   => '생애 전체를 관통하는 운세의 흐름을 조망합니다.',
        ],
    ];

    /** 최소 생성 글자 수 */
    const MIN_CHAR_COUNT = 3000;

    /** 카테고리 → 섹션 매핑 */
    const CATEGORY_TO_SECTION = [
        'personality'  => self::SECTION_PERSONALITY,
        'talent'       => self::SECTION_TALENT,
        'career'       => self::SECTION_CAREER,
        'wealth'       => self::SECTION_WEALTH,
        'relationship' => self::SECTION_RELATIONSHIP,
        'life_flow'    => self::SECTION_LIFE_FLOW,
    ];

    // ========================================================
    // 일간 오행 설명 템플릿
    // ========================================================
    const DAYMASTER_DESCRIPTIONS = [
        '갑' => [
            'element' => '목(木)',
            'nature'  => '양목(陽木) — 큰 나무, 대들보',
            'traits'  => '곧은 성품, 성장 지향적, 리더십, 진취적 기상',
            'image'   => '하늘을 향해 곧게 뻗는 큰 소나무처럼, 한 번 정한 방향을 단호하게 추구합니다.',
        ],
        '을' => [
            'element' => '목(木)',
            'nature'  => '음목(陰木) — 풀, 꽃, 덩굴',
            'traits'  => '유연함, 적응력, 부드러운 강인함, 섬세한 감수성',
            'image'   => '바위틈을 비집고 피어나는 꽃처럼, 부드러우면서도 끈질긴 생명력을 가지고 있습니다.',
        ],
        '병' => [
            'element' => '화(火)',
            'nature'  => '양화(陽火) — 태양, 큰 불',
            'traits'  => '밝고 따뜻함, 열정, 정의로움, 존재감',
            'image'   => '세상을 환하게 비추는 태양과 같아서, 어디에서든 그 존재가 빛을 발합니다.',
        ],
        '정' => [
            'element' => '화(火)',
            'nature'  => '음화(陰火) — 촛불, 별빛',
            'traits'  => '섬세한 열정, 예술적 감성, 따뜻한 배려, 직관력',
            'image'   => '어둠 속의 촛불처럼, 은은하지만 결코 꺼지지 않는 따뜻한 빛을 내면에 품고 있습니다.',
        ],
        '무' => [
            'element' => '토(土)',
            'nature'  => '양토(陽土) — 산, 큰 언덕',
            'traits'  => '신의와 중후함, 포용력, 안정감, 의지력',
            'image'   => '흔들리지 않는 큰 산처럼, 묵직한 존재감과 믿음직한 신뢰감을 줍니다.',
        ],
        '기' => [
            'element' => '토(土)',
            'nature'  => '음토(陰土) — 정원, 논밭',
            'traits'  => '양육과 돌봄, 실용적, 꼼꼼함, 겸손',
            'image'   => '모든 생명을 키워내는 비옥한 대지처럼, 주변 사람들을 돌보고 키우는 힘이 있습니다.',
        ],
        '경' => [
            'element' => '금(金)',
            'nature'  => '양금(陽金) — 큰 칼, 바위, 원석',
            'traits'  => '결단력, 의리, 강직함, 정의감',
            'image'   => '날카롭게 벼려진 명검처럼, 한 번의 결단으로 상황을 정리하는 과감함이 있습니다.',
        ],
        '신' => [
            'element' => '금(金)',
            'nature'  => '음금(陰金) — 보석, 바늘, 정밀한 도구',
            'traits'  => '정밀함, 세련미, 예리한 관찰력, 완벽주의',
            'image'   => '빛에 따라 색이 달라지는 보석처럼, 섬세하고 다면적인 매력을 가지고 있습니다.',
        ],
        '임' => [
            'element' => '수(水)',
            'nature'  => '양수(陽水) — 큰 강, 바다',
            'traits'  => '지혜, 유연한 사고, 포용력, 깊은 내면',
            'image'   => '모든 것을 품는 넓은 바다처럼, 어떤 상황에서도 자신만의 흐름을 유지합니다.',
        ],
        '계' => [
            'element' => '수(水)',
            'nature'  => '음수(陰水) — 이슬, 안개, 시냇물',
            'traits'  => '섬세한 지성, 적응력, 감성적 깊이, 직관',
            'image'   => '깊은 산속의 맑은 시냇물처럼, 조용하지만 끊임없이 흐르는 지혜와 감수성을 품고 있습니다.',
        ],
    ];

    // ========================================================
    // 오행 한글명
    // ========================================================
    const OHANG_MEANING = [
        '목' => '목(木·나무)',
        '화' => '화(火·불)',
        '토' => '토(土·흙)',
        '금' => '금(金·쇠)',
        '수' => '수(水·물)',
    ];

    // ========================================================
    // 신강/신약 설명
    // ========================================================
    const STRENGTH_DESCRIPTIONS = [
        'strong' => [
            'label'  => '신강(身强)',
            'meaning' => '일간(日干)의 힘이 강하여, 자아 의식이 뚜렷하고 주체적인 삶을 살아갑니다.',
            'detail' => '당신의 사주에서 일간을 돕는 힘(인성·비겁)이 충분히 갖추어져 있어, 맡은 바를 힘 있게 추진할 수 있는 기반이 탄탄합니다. 신강한 사주는 식상(재능)과 재성(재물), 관성(명예·직업) 등 일간이 제어하고 활용해야 할 오행이 용신(用神)으로 작용하여, "에너지를 밖으로 발산하고 세상과 교류하는" 방향으로 운을 열어가게 됩니다.',
        ],
        'weak' => [
            'label'  => '신약(身弱)',
            'meaning' => '일간(日干)의 힘이 약하여, 유연하고 적응력이 뛰어나며, 조력자의 도움이 큰 영향을 미칩니다.',
            'detail' => '당신의 사주에서 일간을 돕는 힘(인성·비겁)이 부족하여, 외부 환경과 타인의 에너지에 민감하게 반응합니다. 신약한 사주는 인성(학문·보호)과 비겁(동료·조력)이 용신(用神)이 되어, "배움과 협력을 통해 자아를 강화하는" 방향으로 운을 풀어가게 됩니다. 약함이 곧 나쁜 것이 아니라, 겸손하고 유연한 방식으로 세상과 조화를 이루어가는 지혜입니다.',
        ],
    ];

    // ========================================================
    // 연결어·전환문 뱅크
    // ========================================================
    const TRANSITION_PHRASES = [
        'addition' => [
            '또한, ',
            '아울러 ',
            '이와 더불어, ',
            '나아가 ',
            '이에 더해, ',
            '한편으로는, ',
        ],
        'contrast' => [
            '다만, ',
            '반면에, ',
            '한편, ',
            '그러나 ',
            '한 가지 주의할 점은, ',
            '이면(裏面)을 보면, ',
        ],
        'emphasis' => [
            '특히 ',
            '무엇보다 중요한 것은, ',
            '결정적으로, ',
            '핵심적인 특징은, ',
            '주목할 점은, ',
        ],
        'consequence' => [
            '이로 인해, ',
            '따라서 ',
            '그 결과, ',
            '이러한 흐름은 ',
            '이것은 곧 ',
        ],
        'summary' => [
            '종합하면, ',
            '전체적으로 보면, ',
            '정리하자면, ',
            '총괄하면, ',
            '결국, ',
        ],
    ];

    // ========================================================
    // 패턴 카테고리별 해석 도입문
    // ========================================================
    const PATTERN_INTRO_TEMPLATES = [
        'strength' => [
            '사주의 에너지 강약(身强·身弱) 관점에서 보면, {pattern_name} 패턴이 뚜렷하게 나타납니다.',
            '{pattern_name}의 기운이 감지되며, 이는 당신의 사주 에너지 구조에 중요한 영향을 줍니다.',
        ],
        'sipsin' => [
            '십성(十星) 분포를 분석하면, {pattern_name} 패턴이 두드러집니다.',
            '사주의 십성 배분에서 {pattern_name}의 특성이 나타납니다.',
        ],
        'flow' => [
            '오행(五行)의 흐름을 따라가면, {pattern_name}의 아름다운 순환이 발견됩니다.',
            '{pattern_name} 구조가 사주에 존재하여, 에너지가 자연스럽게 흘러가는 통로가 마련되어 있습니다.',
        ],
        'structure' => [
            '사주의 구조적 특성을 보면, {pattern_name} 패턴이 핵심적 역할을 합니다.',
            '격국(格局)의 관점에서, {pattern_name}이(가) 사주 전체의 성격을 규정합니다.',
        ],
        'special' => [
            '특수한 구조인 {pattern_name}이(가) 감지됩니다. 이는 일반적인 해석과는 다른 관점이 필요합니다.',
            '{pattern_name}의 특별한 에너지가 사주에 존재하여, 독특한 삶의 방향을 제시합니다.',
        ],
        'balance' => [
            '오행의 균형 상태를 살펴보면, {pattern_name} 특성이 나타납니다.',
            '사주의 전체적 균형 분석에서 {pattern_name}의 경향이 두드러집니다.',
        ],
    ];

    // ========================================================
    // 인스턴스 변수
    // ========================================================
    private $engine;
    private $result;
    private $detector;
    private $interpData;
    private $ohangAnalysis;
    private $patterns      = [];
    private $context       = [];
    private $sections      = [];
    private $usedSentences = []; // 중복 문장 제거용

    // ========================================================
    // 생성자
    // ========================================================
    public function __construct(SajuEngine $engine) {
        $this->engine       = $engine;
        $this->result       = $engine->getResult();
        $this->detector     = new PatternDetector($engine);
        $this->interpData   = new PatternInterpretationData();
        $this->ohangAnalysis = new OhangAnalysis($engine);
        $this->patterns     = $this->detector->detect();
        $this->buildStoryContext();
    }

    // ========================================================
    // 공개 API
    // ========================================================

    /**
     * 전체 스토리를 생성하여 반환합니다.
     * 
     * @return array [
     *   'title'      => string,          // 스토리 제목
     *   'subtitle'   => string,          // 부제목 (사주 요약)
     *   'sections'   => [                // 7개 섹션
     *       [
     *           'id'      => string,
     *           'order'   => int,
     *           'title'   => string,
     *           'icon'    => string,
     *           'content' => string,     // 본문 텍스트
     *           'patterns_used' => array, // 사용된 패턴 ID 목록
     *       ], ...
     *   ],
     *   'full_text'   => string,          // 전체 텍스트 (섹션 병합)
     *   'char_count'  => int,             // 총 글자 수
     *   'meta'        => [
     *       'patterns_detected' => int,
     *       'patterns_used'     => int,
     *       'total_sentences'   => int,
     *       'generation_info'   => string,
     *   ],
     * ]
     */
    public function generate(): array {
        $this->usedSentences = [];
        $this->sections = [];

        // 7개 섹션 생성
        $this->sections[] = $this->generateStructureSection();
        $this->sections[] = $this->generateInterpretationSection(self::SECTION_PERSONALITY);
        $this->sections[] = $this->generateInterpretationSection(self::SECTION_TALENT);
        $this->sections[] = $this->generateInterpretationSection(self::SECTION_CAREER);
        $this->sections[] = $this->generateInterpretationSection(self::SECTION_RELATIONSHIP);
        $this->sections[] = $this->generateInterpretationSection(self::SECTION_WEALTH);
        $this->sections[] = $this->generateInterpretationSection(self::SECTION_LIFE_FLOW);

        // 글자 수 검증 → 부족하면 보충
        $this->ensureMinimumLength();

        // 최종 조립
        return $this->assembleStory();
    }

    /**
     * 특정 섹션만 생성
     */
    public function generateSection(string $sectionId): ?array {
        $this->usedSentences = [];
        if ($sectionId === self::SECTION_STRUCTURE) {
            return $this->generateStructureSection();
        }
        if (isset(self::SECTION_META[$sectionId])) {
            return $this->generateInterpretationSection($sectionId);
        }
        return null;
    }

    /**
     * 감지된 패턴 목록 반환
     */
    public function getDetectedPatterns(): array {
        return $this->patterns;
    }

    /**
     * 스토리 컨텍스트 반환 (디버그용)
     */
    public function getStoryContext(): array {
        return $this->context;
    }

    // ========================================================
    // 내부: 스토리 컨텍스트 구축
    // ========================================================

    private function buildStoryContext(): void {
        $dms = $this->result['day_master_strength'];
        $dayMaster = $this->result['day_master'];
        $dayElement = $this->result['day_master_element'];
        $sipsinFull = $this->result['sipsin_full'];
        $ohangData = $this->ohangAnalysis->analyze();

        // 패턴을 카테고리별·강도순 정리
        $patternsByCategory = [];
        $patternsByIntensity = [];
        foreach ($this->patterns as $p) {
            $patternsByCategory[$p['category']][] = $p;
            $patternsByIntensity[] = $p;
        }

        // 패턴을 섹션에 할당 (각 해석 카테고리에 관련된 패턴 선별)
        $patternsBySection = [];
        foreach ($this->patterns as $p) {
            // 모든 패턴은 모든 섹션에 해석 문장을 기여할 수 있음
            foreach (self::CATEGORY_TO_SECTION as $cat => $section) {
                $interpSentences = $this->interpData->getCategoryInterpretation($p['id'], $cat);
                if (!empty($interpSentences)) {
                    $patternsBySection[$section][] = $p;
                }
            }
        }

        // 중복 제거 (같은 섹션에 같은 패턴이 여러 번 배정되지 않도록)
        foreach ($patternsBySection as $sec => &$pats) {
            $seen = [];
            $unique = [];
            foreach ($pats as $p) {
                if (!in_array($p['id'], $seen)) {
                    $seen[] = $p['id'];
                    $unique[] = $p;
                }
            }
            $pats = $unique;
        }
        unset($pats);

        $this->context = [
            'dayMaster'          => $dayMaster,
            'dayElement'         => $dayElement,
            'dayMasterInfo'      => self::DAYMASTER_DESCRIPTIONS[$dayMaster] ?? [],
            'isStrong'           => $dms['is_strong'],
            'strength'           => $dms['strength'],
            'strengthRatio'      => $dms['ratio'],
            'gender'             => $this->engine->getGender(),
            'genderLabel'        => $this->engine->getGender() === 'male' ? '남성' : '여성',
            'sipsinDist'         => $sipsinFull['distribution'] ?? [],
            'dominantSipsin'     => $sipsinFull['dominant_sipsin'] ?? '',
            'ohangData'          => $ohangData,
            'pillars'            => [
                'year'  => $this->result['year_pillar'],
                'month' => $this->result['month_pillar'],
                'day'   => $this->result['day_pillar'],
                'hour'  => $this->result['hour_pillar'],
            ],
            'fourGods'           => [
                'yongshin' => $dms['yongshin'] ?? [],
                'heeshin'  => $dms['heeshin'] ?? [],
                'gishin'   => $dms['gishin'] ?? [],
                'gushin'   => $dms['gushin'] ?? [],
            ],
            'allPatterns'        => $this->patterns,
            'patternsByCategory' => $patternsByCategory,
            'patternsBySection'  => $patternsBySection,
            'input'              => $this->result['input'],
        ];
    }

    // ========================================================
    // 섹션 1: 사주 구조 설명
    // ========================================================

    private function generateStructureSection(): array {
        $meta = self::SECTION_META[self::SECTION_STRUCTURE];
        $paragraphs = [];

        // 0) 도입부
        $paragraphs[] = $meta['intro'];

        // 1) 사주 기본 구성
        $paragraphs[] = $this->buildPillarDescription();

        // 2) 일간(일주의 천간) 설명
        $paragraphs[] = $this->buildDayMasterDescription();

        // 3) 오행 분포
        $paragraphs[] = $this->buildOhangDistribution();

        // 4) 신강/신약 판별
        $paragraphs[] = $this->buildStrengthDescription();

        // 5) 용신 설명
        $paragraphs[] = $this->buildYongshinDescription();

        // 6) 감지된 패턴 개요
        $paragraphs[] = $this->buildPatternOverview();

        $content = implode("\n\n", array_filter($paragraphs));

        return [
            'id'            => self::SECTION_STRUCTURE,
            'order'         => $meta['order'],
            'title'         => $meta['title'],
            'icon'          => $meta['icon'],
            'content'       => $content,
            'patterns_used' => array_column($this->patterns, 'id'),
        ];
    }

    /**
     * 사주 기둥(년·월·일·시주) 설명 생성
     */
    private function buildPillarDescription(): string {
        $p = $this->context['pillars'];
        $input = $this->context['input'];
        $genderLabel = $this->context['genderLabel'];

        $yearText  = $p['year']['text'] ?? '??';
        $monthText = $p['month']['text'] ?? '??';
        $dayText   = $p['day']['text'] ?? '??';
        $hourText  = $p['hour']['text'] ?? '??';

        $yearHanja  = $p['year']['hanja'] ?? '';
        $monthHanja = $p['month']['hanja'] ?? '';
        $dayHanja   = $p['day']['hanja'] ?? '';
        $hourHanja  = $p['hour']['hanja'] ?? '';

        $birthInfo = '';
        if (isset($input['year'])) {
            $birthInfo = "{$input['year']}년 {$input['month']}월 {$input['day']}일";
            if ($input['hour'] !== null) {
                $birthInfo .= " {$input['hour']}시";
            }
            $birthInfo .= " 출생의 {$genderLabel}";
        }

        $text = "당신의 사주팔자(四柱八字)를 분석합니다.";
        if ($birthInfo) {
            $text .= " {$birthInfo}으로서, ";
        } else {
            $text .= " ";
        }

        $text .= "사주 네 기둥은 다음과 같습니다.\n\n";
        $text .= "  • 년주(年柱): {$yearText}({$yearHanja}) — 조상과 유년기의 기운\n";
        $text .= "  • 월주(月柱): {$monthText}({$monthHanja}) — 부모와 청년기의 기운\n";
        $text .= "  • 일주(日柱): {$dayText}({$dayHanja}) — 자신과 중년기의 기운\n";
        $text .= "  • 시주(時柱): {$hourText}({$hourHanja}) — 자녀와 말년의 기운";

        return $text;
    }

    /**
     * 일간(日干) 설명 생성
     */
    private function buildDayMasterDescription(): string {
        $dm = $this->context['dayMaster'];
        $info = $this->context['dayMasterInfo'];

        if (empty($info)) {
            return "일간(日干)은 '{$dm}'입니다.";
        }

        $text = "당신의 일간(日干)은 '{$dm}'이며, 이는 {$info['nature']}에 해당합니다. "
              . "오행으로는 {$info['element']}에 속하며, "
              . "{$info['traits']}의 성질을 타고났습니다.\n\n"
              . "{$info['image']}";

        return $text;
    }

    /**
     * 오행 분포 설명 생성
     */
    private function buildOhangDistribution(): string {
        $ohangData = $this->context['ohangData'];
        $weighted = $ohangData['weighted_ohang_count'] ?? [];
        $total = array_sum($weighted) ?: 1;

        // 내림차순 정렬
        arsort($weighted);

        $dominant = array_key_first($weighted);
        $dominantPct = round(($weighted[$dominant] / $total) * 100, 1);

        // 결핍 오행 찾기
        $missing = [];
        foreach (['목', '화', '토', '금', '수'] as $el) {
            if (($weighted[$el] ?? 0) < 0.3) {
                $missing[] = self::OHANG_MEANING[$el];
            }
        }

        $text = "사주 전체의 오행(五行) 분포를 분석하면, ";

        // 분포 나열
        $parts = [];
        foreach ($weighted as $el => $val) {
            $pct = round(($val / $total) * 100, 1);
            $parts[] = self::OHANG_MEANING[$el] . " {$pct}%";
        }
        $text .= implode(', ', $parts) . "의 비율을 보입니다.";

        $text .= " 가장 강한 오행은 " . self::OHANG_MEANING[$dominant] . "({$dominantPct}%)이며, "
               . "이 기운이 사주 전반에 큰 영향을 미칩니다.";

        if (!empty($missing)) {
            $text .= " 반면, " . implode(', ', $missing) . " 기운이 매우 약하거나 결핍되어 있어, "
                   . "이 오행의 보충이 운세 개선에 중요합니다.";
        }

        // 균형 상태
        $balance = $ohangData['balance'] ?? [];
        if (!empty($balance['description'])) {
            $text .= "\n\n오행의 균형 상태: " . $balance['description'];
        }

        return $text;
    }

    /**
     * 신강/신약 설명 생성
     */
    private function buildStrengthDescription(): string {
        $isStrong = $this->context['isStrong'];
        $strength = $this->context['strength'];
        $ratio = $this->context['strengthRatio'];
        $key = $isStrong ? 'strong' : 'weak';
        $desc = self::STRENGTH_DESCRIPTIONS[$key];

        $text = "신강·신약 판별 결과, 당신은 **{$desc['label']}**에 해당합니다 "
              . "(일간 힘: {$strength}, 비율: {$ratio}%).\n\n"
              . $desc['meaning'] . "\n\n"
              . $desc['detail'];

        return $text;
    }

    /**
     * 용신(用神) 설명 생성
     */
    private function buildYongshinDescription(): string {
        $fourGods = $this->context['fourGods'];
        if (empty($fourGods['yongshin'])) return '';

        $yong    = $fourGods['yongshin'];
        $heeshin = $fourGods['heeshin'];
        $gishin  = $fourGods['gishin'];
        $gushin  = $fourGods['gushin'];

        $text = "사주 균형의 핵심인 사신(四神)을 분석하면:\n\n";
        if (!empty($yong))    $text .= "  • 용신(用神): {$yong['element']}({$yong['type']}) — 가장 필요한 오행으로, 이 기운이 강해지는 시기에 운이 열립니다.\n";
        if (!empty($heeshin)) $text .= "  • 희신(喜神): {$heeshin['element']}({$heeshin['type']}) — 용신을 돕는 오행으로, 용신과 함께 좋은 영향을 미칩니다.\n";
        if (!empty($gishin))  $text .= "  • 기신(忌神): {$gishin['element']}({$gishin['type']}) — 사주에 해가 되는 오행으로, 이 기운이 강한 시기에 주의가 필요합니다.\n";
        if (!empty($gushin))  $text .= "  • 구신(仇神): {$gushin['element']}({$gushin['type']}) — 기신을 돕는 오행으로, 기신과 함께 나쁜 영향을 줍니다.";

        $yongEl = $yong['element'] ?? '';
        $text .= "\n\n용신인 '{$yongEl}' 기운을 강화하는 것이 개운(開運)의 핵심 전략입니다. "
               . "이 오행과 관련된 직업, 방위, 색상, 음식 등을 활용하면 운의 흐름이 좋아집니다.";

        return $text;
    }

    /**
     * 감지된 패턴 개요
     */
    private function buildPatternOverview(): string {
        $count = count($this->patterns);
        if ($count === 0) {
            return "기본적인 오행 분포와 십성 배분이 비교적 균형을 이루고 있으며, 특별히 두드러지는 극단적 패턴은 감지되지 않았습니다.";
        }

        $text = "당신의 사주에서 총 {$count}개의 명리학적 패턴이 감지되었습니다. ";

        // 상위 3개 패턴 언급
        $top = array_slice($this->patterns, 0, min(3, $count));
        $topNames = array_map(fn($p) => "'{$p['name']}'(강도 {$p['intensity']})", $top);
        $text .= "그 중 가장 뚜렷한 패턴은 " . implode(', ', $topNames) . "입니다.";

        // 카테고리별 요약
        $catCounts = [];
        foreach ($this->patterns as $p) {
            $catLabel = $this->getCategoryLabel($p['category']);
            $catCounts[$catLabel] = ($catCounts[$catLabel] ?? 0) + 1;
        }
        $catParts = [];
        foreach ($catCounts as $label => $cnt) {
            $catParts[] = "{$label} {$cnt}개";
        }
        $text .= " 카테고리별로는 " . implode(', ', $catParts) . "가 감지되었습니다.";

        $text .= "\n\n이제 이 패턴들이 당신의 성격, 재능, 직업, 인간관계, 재물, 인생 흐름에 어떤 구체적 영향을 미치는지 깊이 분석하겠습니다.";

        return $text;
    }

    // ========================================================
    // 섹션 2~7: 해석 카테고리별 스토리 생성
    // ========================================================

    /**
     * 해석 카테고리 섹션 생성 (personality, talent, career, etc.)
     */
    private function generateInterpretationSection(string $sectionId): array {
        $meta = self::SECTION_META[$sectionId];
        $paragraphs = [];
        $patternsUsed = [];

        // 도입부
        $paragraphs[] = $meta['intro'];

        // 이 섹션에 해석을 기여할 패턴 목록 (강도순)
        $sectionPatterns = $this->context['patternsBySection'][$sectionId] ?? [];
        usort($sectionPatterns, fn($a, $b) => $b['intensity'] - $a['intensity']);

        if (empty($sectionPatterns)) {
            // 패턴이 없을 경우 기본 해석 생성
            $paragraphs[] = $this->buildDefaultSectionContent($sectionId);
        } else {
            // 각 패턴의 해석 문장을 가공하여 문단 생성
            $isFirst = true;
            $sentenceCount = 0;
            
            foreach ($sectionPatterns as $idx => $pattern) {
                $paragraph = $this->buildPatternParagraph($pattern, $sectionId, $isFirst, $idx);
                if (!empty($paragraph)) {
                    $paragraphs[] = $paragraph;
                    $patternsUsed[] = $pattern['id'];
                    $isFirst = false;
                    $sentenceCount++;
                }
            }

            // 섹션별 종합 마무리 문구
            $closing = $this->buildSectionClosing($sectionId, $patternsUsed);
            if ($closing) {
                $paragraphs[] = $closing;
            }
        }

        $content = implode("\n\n", array_filter($paragraphs));

        return [
            'id'            => $sectionId,
            'order'         => $meta['order'],
            'title'         => $meta['title'],
            'icon'          => $meta['icon'],
            'content'       => $content,
            'patterns_used' => array_unique($patternsUsed),
        ];
    }

    /**
     * 개별 패턴에 대한 해석 문단 생성
     */
    private function buildPatternParagraph(array $pattern, string $sectionId, bool $isFirst, int $index): string {
        $patternId = $pattern['id'];
        $category  = $sectionId; // sectionId와 해석 카테고리가 동일 (structure 제외)

        // 필터링된 해석 문장 가져오기
        $filtered = $this->interpData->getFilteredInterpretation($patternId, [
            'intensity' => $pattern['intensity'],
            'gender'    => $this->context['gender'],
            'isStrong'  => $this->context['isStrong'],
        ]);

        $sentences = $filtered[$category] ?? [];
        if (empty($sentences)) return '';

        // 중복 문장 제거
        $sentences = $this->filterDuplicateSentences($sentences);
        if (empty($sentences)) return '';

        $parts = [];

        // 패턴 도입 (첫 번째 또는 새로운 패턴)
        $intro = $this->buildPatternIntro($pattern, $isFirst, $index);
        if ($intro) {
            $parts[] = $intro;
        }

        // 문장 선택 — 최대 문장 수는 패턴 강도에 비례
        $maxSentences = $this->calculateMaxSentences($pattern['intensity'], count($sentences));
        $selected = array_slice($sentences, 0, $maxSentences);

        // 문장들을 자연스럽게 연결
        foreach ($selected as $i => $sentence) {
            if ($i === 0 && !empty($parts)) {
                // 첫 문장은 도입문에 자연스럽게 이어짐
                $parts[] = $sentence;
            } elseif ($i > 0) {
                // 2번째 이후 문장에는 간헐적으로 연결어 삽입
                if ($i % 3 === 0) {
                    $transition = $this->pickTransition($i, count($selected));
                    $parts[] = $transition . $sentence;
                } else {
                    $parts[] = $sentence;
                }
            } else {
                $parts[] = $sentence;
            }
        }

        // 사용된 문장 기록
        foreach ($selected as $s) {
            $this->usedSentences[md5($s)] = true;
        }

        return implode(' ', $parts);
    }

    /**
     * 패턴 도입문 생성
     */
    private function buildPatternIntro(array $pattern, bool $isFirst, int $index): string {
        $category = $pattern['category'];
        $templates = self::PATTERN_INTRO_TEMPLATES[$category] ?? self::PATTERN_INTRO_TEMPLATES['structure'];

        if ($isFirst) {
            $template = $templates[0] ?? '{pattern_name} 패턴이 감지됩니다.';
        } elseif ($index < 3) {
            // 추가 도입
            $transitions = self::TRANSITION_PHRASES['addition'];
            $prefix = $transitions[array_rand($transitions)];
            $template = $prefix . ($templates[1] ?? '{pattern_name} 패턴도 감지됩니다.');
        } else {
            // 후속 패턴
            $transitions = self::TRANSITION_PHRASES['emphasis'];
            $prefix = $transitions[array_rand($transitions)];
            $template = $prefix . '{pattern_name}의 영향도 주목해야 합니다.';
        }

        return str_replace('{pattern_name}', "'{$pattern['name']}'", $template);
    }

    /**
     * 패턴이 없는 섹션의 기본 해석 생성
     */
    private function buildDefaultSectionContent(string $sectionId): string {
        $dm = $this->context['dayMaster'];
        $element = $this->context['dayElement'];
        $isStrong = $this->context['isStrong'];
        $gender = $this->context['genderLabel'];
        $strengthLabel = $isStrong ? '신강' : '신약';

        $defaults = [
            self::SECTION_PERSONALITY => "일간 {$dm}({$element})의 기본 성질에 {$strengthLabel}의 에너지가 더해져, "
                . ($isStrong
                    ? "주체적이고 자기 확신이 강한 성격의 기반을 형성합니다. 자신의 의견과 방향에 대한 신념이 뚜렷하여, 한 번 결정한 것은 쉽게 바꾸지 않는 일관성이 있습니다."
                    : "유연하고 적응력이 뛰어난 성격의 기반을 형성합니다. 타인의 의견을 잘 수용하며, 부드러운 방식으로 주변과 조화를 이루어갑니다."),

            self::SECTION_TALENT => "일간 {$dm}의 본질적 재능은 {$element}의 성향에 뿌리를 두고 있습니다. "
                . "사주 전체의 흐름을 따라 재능을 개발하면, 독자적인 전문성을 갖출 수 있습니다.",

            self::SECTION_CAREER => "{$gender}으로서 {$strengthLabel}의 사주를 가진 당신에게는, "
                . ($isStrong
                    ? "자율적이고 주체적으로 일할 수 있는 직업 환경이 적합합니다."
                    : "안정적이고 체계적인 직업 환경에서 멘토의 도움을 받으며 성장하는 것이 좋습니다."),

            self::SECTION_RELATIONSHIP => "대인관계에서 일간 {$dm}의 성질이 기초적인 관계 패턴을 형성합니다. "
                . "{$element}의 기운이 관계의 모든 영역에 영향을 미쳐, 특유의 교류 방식을 만들어냅니다.",

            self::SECTION_WEALTH => "재물운은 사주 전체의 흐름에 영향을 받으며, {$strengthLabel}에 따른 재물 전략이 필요합니다. "
                . ($isStrong
                    ? "강한 에너지를 식상(재능)으로 발산하여 재성(재물)으로 흐르게 하는 것이 최적의 전략입니다."
                    : "인성(학문)과 비겁(조력)의 도움을 받아 안정적인 재물 기반을 만드는 것이 중요합니다."),

            self::SECTION_LIFE_FLOW => "인생의 큰 흐름은 대운(大運)의 변화에 따라 달라집니다. "
                . "용신(用神)이 강해지는 대운에서는 순풍을 만나고, 기신(忌神)이 강해지는 대운에서는 도전이 찾아옵니다.",
        ];

        return $defaults[$sectionId] ?? '';
    }

    /**
     * 섹션 마무리 문구 생성
     */
    private function buildSectionClosing(string $sectionId, array $patternsUsed): string {
        $count = count($patternsUsed);
        if ($count === 0) return '';

        $closings = [
            self::SECTION_PERSONALITY => [
                "이러한 성격적 특성들은 고정된 것이 아니라, 대운(大運)의 흐름과 환경에 따라 강약이 조절됩니다. 자신의 특성을 이해하고 활용하는 것이 성격의 장점을 극대화하는 핵심입니다.",
                "성격은 타고난 것이지만, 인식하고 조절하는 것은 선택입니다. 위에 언급된 특성들을 자각하는 것만으로도 인생의 질이 한 단계 높아질 수 있습니다.",
            ],
            self::SECTION_TALENT => [
                "재능이 있다는 것과 그것을 발현하는 것은 다른 문제입니다. 위에 언급된 재능들을 의식적으로 개발하고 연습하면, 타인과 차별화되는 독보적 능력으로 성장합니다.",
                "당신의 잠재력은 이미 사주 안에 설계되어 있습니다. 이것을 어떤 방향으로 개발할 것인지는 당신의 선택에 달려 있습니다.",
            ],
            self::SECTION_CAREER => [
                "사주에 나타난 직업적 방향은 하나의 지표이며, 실제 진로는 현시대의 환경과 본인의 노력이 결합되어 결정됩니다. 사주가 제시하는 방향을 참고하되, 실전에서의 경험을 통해 자신만의 길을 만들어가세요.",
                "직업은 단순한 돈벌이가 아니라 자아를 실현하는 공간입니다. 사주가 가리키는 방향에서 일할 때, 성취감과 경제적 보상이 모두 따라옵니다.",
            ],
            self::SECTION_RELATIONSHIP => [
                "모든 관계는 양방향입니다. 사주가 보여주는 관계 패턴을 이해하면, 상대와의 갈등을 예방하고 더 깊은 유대를 쌓을 수 있습니다. 특히 가장 가까운 사람에게 더 많은 배려를 기울이세요.",
                "인간관계에서 가장 중요한 것은 '이해'입니다. 자신의 관계 패턴을 아는 것이 타인을 이해하는 첫 걸음이 됩니다.",
            ],
            self::SECTION_WEALTH => [
                "수입의 양보다 중요한 것은 흐름의 안정성입니다. 사주에 맞는 재물 전략을 세우고 꾸준히 실천하면, 시간이 지남에 따라 자산이 축적됩니다. 용신(用神)의 방향을 따르는 경제활동이 최선입니다.",
                "재물은 에너지의 흐름입니다. 막히지 않고 원활하게 순환할 때 풍요가 지속됩니다. 사주의 재물 경로를 이해하고 이에 맞는 전략을 세우세요.",
            ],
            self::SECTION_LIFE_FLOW => [
                "인생은 사계절처럼 순환합니다. 지금이 봄이든 겨울이든, 그 안에서 최선을 다하는 것이 결국 더 나은 다음 계절을 준비하는 것입니다. 사주는 운명의 확정이 아니라, 삶의 나침반입니다.",
                "사주가 보여주는 인생의 큰 그림을 이해하면, 순경(順境)에서 교만하지 않고 역경(逆境)에서 절망하지 않는 지혜를 얻을 수 있습니다. 당신의 인생이라는 작품을 더 아름답게 그려가시길 바랍니다.",
            ],
        ];

        $options = $closings[$sectionId] ?? [];
        if (empty($options)) return '';

        return $options[array_rand($options)];
    }

    // ========================================================
    // 문장 처리 유틸리티
    // ========================================================

    /**
     * 패턴 강도에 따른 최대 문장 수 결정
     */
    private function calculateMaxSentences(int $intensity, int $available): int {
        // 강도가 높을수록 더 많은 문장을 사용
        if ($intensity >= 80) {
            $max = min($available, 8);
        } elseif ($intensity >= 60) {
            $max = min($available, 6);
        } elseif ($intensity >= 40) {
            $max = min($available, 4);
        } else {
            $max = min($available, 3);
        }

        return max(2, $max); // 최소 2문장
    }

    /**
     * 중복 문장 필터링
     */
    private function filterDuplicateSentences(array $sentences): array {
        $filtered = [];
        foreach ($sentences as $s) {
            $hash = md5($s);
            if (!isset($this->usedSentences[$hash])) {
                $filtered[] = $s;
            }
        }
        return $filtered;
    }

    /**
     * 연결어/전환문 선택
     */
    private function pickTransition(int $sentenceIndex, int $totalSentences): string {
        // 문단 위치에 따라 적절한 전환어 유형 선택
        $ratio = $sentenceIndex / max(1, $totalSentences);

        if ($ratio < 0.3) {
            $type = 'addition';
        } elseif ($ratio < 0.6) {
            $type = 'emphasis';
        } elseif ($ratio < 0.8) {
            $type = 'contrast';
        } else {
            $type = 'consequence';
        }

        $phrases = self::TRANSITION_PHRASES[$type];
        return $phrases[array_rand($phrases)];
    }

    /**
     * 카테고리 한글 라벨 반환
     */
    private function getCategoryLabel(string $category): string {
        $labels = [
            'strength'  => '신강/신약',
            'sipsin'    => '십성',
            'flow'      => '오행 흐름',
            'structure' => '구조',
            'special'   => '특수',
            'balance'   => '균형',
        ];
        return $labels[$category] ?? $category;
    }

    // ========================================================
    // 글자 수 보장: 최소 3000자
    // ========================================================

    private function ensureMinimumLength(): void {
        $totalChars = $this->countTotalChars();

        if ($totalChars >= self::MIN_CHAR_COUNT) {
            return;
        }

        $deficit = self::MIN_CHAR_COUNT - $totalChars;

        // 보충 전략 1: 각 섹션에 추가 해석 문장 삽입
        $this->supplementSections($deficit);

        // 보충 전략 2: 여전히 부족하면 종합 보충 문단 추가
        $totalChars = $this->countTotalChars();
        if ($totalChars < self::MIN_CHAR_COUNT) {
            $this->addSupplementaryContent(self::MIN_CHAR_COUNT - $totalChars);
        }
    }

    /**
     * 각 섹션에 추가 문장 보충
     */
    private function supplementSections(int $deficit): void {
        // 문장이 적은 섹션부터 보충
        $sectionLengths = [];
        foreach ($this->sections as $idx => $section) {
            if ($section['id'] === self::SECTION_STRUCTURE) continue; // 구조 섹션은 건너뜀
            $sectionLengths[$idx] = mb_strlen($section['content']);
        }
        asort($sectionLengths);

        $supplementPerSection = max(1, intval($deficit / count($sectionLengths) / 80)); // 문장당 약 80자 가정

        foreach ($sectionLengths as $idx => $len) {
            $section = &$this->sections[$idx];
            $sectionId = $section['id'];

            // 사용되지 않은 추가 문장 수집
            $additionalSentences = $this->collectUnusedSentences($sectionId, $supplementPerSection);

            if (!empty($additionalSentences)) {
                $supplement = "\n\n" . implode(' ', $additionalSentences);
                $section['content'] .= $supplement;

                // 사용 기록
                foreach ($additionalSentences as $s) {
                    $this->usedSentences[md5($s)] = true;
                }
            }
        }
        unset($section);
    }

    /**
     * 특정 섹션에 대한 미사용 해석 문장 수집
     */
    private function collectUnusedSentences(string $sectionId, int $count): array {
        $collected = [];
        $category = $sectionId;

        foreach ($this->patterns as $pattern) {
            if (count($collected) >= $count) break;

            $filtered = $this->interpData->getFilteredInterpretation($pattern['id'], [
                'intensity' => $pattern['intensity'],
                'gender'    => $this->context['gender'],
                'isStrong'  => $this->context['isStrong'],
            ]);

            $sentences = $filtered[$category] ?? [];
            foreach ($sentences as $s) {
                if (count($collected) >= $count) break;
                $hash = md5($s);
                if (!isset($this->usedSentences[$hash])) {
                    $collected[] = $s;
                }
            }
        }

        return $collected;
    }

    /**
     * 종합 보충 컨텐츠 추가 (마지막 수단)
     */
    private function addSupplementaryContent(int $deficit): void {
        $dm = $this->context['dayMaster'];
        $element = $this->context['dayElement'];
        $isStrong = $this->context['isStrong'];
        $strengthLabel = $isStrong ? '신강(身强)' : '신약(身弱)';
        $gender = $this->context['genderLabel'];
        $fourGods = $this->context['fourGods'];
        $yongEl = $fourGods['yongshin']['element'] ?? '';

        $supplements = [];

        // 일간별 상세 보충
        $dmInfo = self::DAYMASTER_DESCRIPTIONS[$dm] ?? [];
        if (!empty($dmInfo)) {
            $supplements[] = "일간 '{$dm}'의 본질을 더 깊이 들여다보겠습니다. "
                . "{$dm}은(는) {$dmInfo['nature']}로서, {$dmInfo['traits']}을 근본적 성향으로 가집니다. "
                . "이 에너지가 {$strengthLabel}의 조건과 결합하여, "
                . ($isStrong
                    ? "자기 확신과 추진력이 강화된 모습으로 발현됩니다. 에너지를 밖으로 발산하는 방향에서 삶의 활력을 찾을 수 있습니다."
                    : "유연하고 수용적인 모습으로 발현됩니다. 내면의 힘을 키우고 좋은 인연을 만나는 것이 인생의 핵심 전략입니다.");
        }

        // 사계절 인생론
        $supplements[] = "명리학에서는 인생을 사계절에 비유합니다. 봄(인·묘·진)에 태어나면 성장과 시작의 에너지가 강하고, "
            . "여름(사·오·미)에 태어나면 열정과 표현의 에너지가 넘칩니다. "
            . "가을(신·유·술)에 태어나면 결실과 판단의 에너지가 작용하며, "
            . "겨울(해·자·축)에 태어나면 사유와 저장의 에너지가 깊어집니다. "
            . "당신의 사주가 어떤 계절의 에너지를 품고 있는지에 따라, 인생의 리듬이 달라집니다.";

        // 용신 활용법
        if ($yongEl) {
            $supplements[] = "개운(開運)의 구체적 방법을 제시하겠습니다. 용신인 '{$yongEl}' 기운을 일상에서 강화하려면, "
                . "이 오행과 관련된 색상의 옷을 입고, 해당 방위에서 활동하며, 관련 음식을 자주 섭취하는 것이 도움이 됩니다. "
                . "또한 용신의 기운이 강해지는 대운(大運)과 세운(歲運)의 시기를 미리 파악하여, 중요한 결정을 그 시기에 집중하면 성공 확률이 높아집니다.";
        }

        // {$gender} 특성
        $supplements[] = "{$gender}으로서의 사주 특성을 살펴보면, "
            . ($this->context['gender'] === 'male'
                ? "재성(財星)이 아내와 재물을, 관성(官星)이 자녀와 직업을 의미합니다. 이 십성들의 상태가 가정의 화목과 사회적 성취에 직접적 영향을 미칩니다."
                : "관성(官星)이 남편과 직업을, 식상(食傷)이 자녀와 표현력을 의미합니다. 이 십성들의 상태가 결혼생활의 만족도와 자녀와의 관계에 깊은 영향을 줍니다.")
            . " 당신의 사주에 나타난 이 십성들의 배치와 강약을 이해하면, 인생의 주요 관계에서 더 현명한 선택을 할 수 있습니다.";

        // 보충 텍스트를 인생 흐름 섹션에 추가
        $targetIdx = null;
        foreach ($this->sections as $idx => $section) {
            if ($section['id'] === self::SECTION_LIFE_FLOW) {
                $targetIdx = $idx;
                break;
            }
        }

        if ($targetIdx !== null) {
            $needed = intval(ceil($deficit / 200)); // 보충문단당 약 200자 가정
            $toAdd = array_slice($supplements, 0, min(count($supplements), max(1, $needed)));
            $this->sections[$targetIdx]['content'] .= "\n\n" . implode("\n\n", $toAdd);
        }
    }

    /**
     * 현재 전체 글자 수 계산
     */
    private function countTotalChars(): int {
        $total = 0;
        foreach ($this->sections as $section) {
            $total += mb_strlen($section['content']);
        }
        return $total;
    }

    // ========================================================
    // 최종 조립
    // ========================================================

    private function assembleStory(): array {
        $dm = $this->context['dayMaster'];
        $element = $this->context['dayElement'];
        $isStrong = $this->context['isStrong'];
        $strengthLabel = $isStrong ? '신강' : '신약';
        $gender = $this->context['genderLabel'];

        // 제목 생성
        $title = "사주팔자 심층 분석 — {$dm}일간 {$gender}의 명리학적 해석";
        $subtitle = "{$element} {$strengthLabel} | 감지 패턴 " . count($this->patterns) . "개";

        // 전체 텍스트 병합
        $fullTextParts = [];
        $allPatternsUsed = [];
        $totalSentences = 0;

        foreach ($this->sections as $section) {
            $header = "【{$section['icon']} {$section['title']}】";
            $fullTextParts[] = $header . "\n\n" . $section['content'];
            $allPatternsUsed = array_merge($allPatternsUsed, $section['patterns_used']);
        }

        $fullText = implode("\n\n" . str_repeat('—', 40) . "\n\n", $fullTextParts);
        $charCount = mb_strlen($fullText);

        return [
            'title'      => $title,
            'subtitle'   => $subtitle,
            'sections'   => $this->sections,
            'full_text'  => $fullText,
            'char_count' => $charCount,
            'meta'       => [
                'patterns_detected'  => count($this->patterns),
                'patterns_used'      => count(array_unique($allPatternsUsed)),
                'pattern_ids'        => array_unique($allPatternsUsed),
                'day_master'         => $dm,
                'day_element'        => $element,
                'is_strong'          => $isStrong,
                'gender'             => $this->context['gender'],
                'generation_info'    => 'SajuStoryGenerator v1.0 | PatternDetector + PatternInterpretationData',
                'min_char_target'    => self::MIN_CHAR_COUNT,
                'actual_char_count'  => $charCount,
                'meets_minimum'      => $charCount >= self::MIN_CHAR_COUNT,
            ],
        ];
    }
}
