<?php
/**
 * ================================================================
 * 대운 분석 시스템 (DaeunAnalyzer) — 4단계 모듈
 * ================================================================
 * 
 * 원국(原局) 구조 + 대운(大運) 오행 변화 + 십성 변화를 조합하여
 * 각 대운 기간의 인생 흐름을 심층 해석합니다.
 * 
 * [대운 유형 분류]
 * ┌─────────────────────────────────────────┐
 * │  재성(財星) 대운  — 편재·정재            │
 * │  관성(官星) 대운  — 편관·정관            │
 * │  식상(食傷) 대운  — 식신·상관            │
 * │  인성(印星) 대운  — 편인·정인            │
 * │  비겁(比劫) 대운  — 비견·겁재            │
 * └─────────────────────────────────────────┘
 * 
 * [해석 4대 영역]
 * 1. 인생 변화 가능성    (life_change)
 * 2. 직업 변화 가능성    (career_change)
 * 3. 인간관계 변화       (relationship_change)
 * 4. 재물 흐름 변화      (wealth_change)
 * 
 * [분석 컨텍스트]
 * - 원국 신강/신약 + 대운 유형 조합
 * - 성별 맞춤 해석
 * - 인생 시기(나이대) 맞춤 해석
 * - 용신/기신 대운 여부
 * - 12운성 상태
 * - 합충형파해 관계
 * 
 * [사용법]
 *   $engine   = new SajuEngine($y, $m, $d, $h, $gender);
 *   $analyzer = new DaeunAnalyzer($engine);
 *   $result   = $analyzer->analyze();
 */

require_once __DIR__ . '/SajuEngine.php';
require_once __DIR__ . '/DaeunInterpretationData.php';

class DaeunAnalyzer {

    // ========================================================
    // 상수
    // ========================================================

    /** 십성 → 대운 그룹 매핑 */
    const SIPSIN_TO_GROUP = [
        '비견' => 'bigyeop', '겁재' => 'bigyeop',
        '식신' => 'siksang', '상관' => 'siksang',
        '편재' => 'jaesung', '정재' => 'jaesung',
        '편관' => 'gwansung', '정관' => 'gwansung',
        '편인' => 'insung',  '정인' => 'insung',
    ];

    /** 십성 → 세부 유형 매핑 */
    const SIPSIN_TO_SUBTYPE = [
        '비견' => 'bigyeon', '겁재' => 'geopjae',
        '식신' => 'siksin',  '상관' => 'sanggwan',
        '편재' => 'pyeonjae','정재' => 'jeongjae',
        '편관' => 'pyeongwan','정관' => 'jeonggwan',
        '편인' => 'pyeonin', '정인' => 'jeongin',
    ];

    /** 대운 그룹 한글명 */
    const GROUP_LABELS = [
        'bigyeop'  => '비겁(比劫) 대운',
        'siksang'  => '식상(食傷) 대운',
        'jaesung'  => '재성(財星) 대운',
        'gwansung' => '관성(官星) 대운',
        'insung'   => '인성(印星) 대운',
    ];

    /** 십성 세부 한글명 */
    const SUBTYPE_LABELS = [
        'bigyeon'   => '비견(比肩)',   'geopjae'   => '겁재(劫財)',
        'siksin'    => '식신(食神)',   'sanggwan'  => '상관(傷官)',
        'pyeonjae'  => '편재(偏財)',   'jeongjae'  => '정재(正財)',
        'pyeongwan' => '편관(偏官)',   'jeonggwan' => '정관(正官)',
        'pyeonin'   => '편인(偏印)',   'jeongin'   => '정인(正印)',
    ];

    /** 해석 4대 영역 */
    const INTERPRETATION_CATEGORIES = [
        'life_change',
        'career_change',
        'relationship_change',
        'wealth_change',
    ];

    /** 카테고리 한글 라벨 */
    const CATEGORY_LABELS = [
        'life_change'          => '인생 변화',
        'career_change'        => '직업 변화',
        'relationship_change'  => '인간관계 변화',
        'wealth_change'        => '재물 흐름',
    ];

    /** 나이대 분류 */
    const AGE_GROUPS = [
        'youth'       => [0, 19,  '유년~청소년기'],
        'young_adult' => [20, 34, '청년기'],
        'middle'      => [35, 54, '장년기'],
        'senior'      => [55, 99, '중년~노년기'],
    ];

    /** 대운 점수 등급 */
    const SCORE_LEVELS = [
        ['min' => 75, 'label' => '대길', 'emoji' => '🌟', 'color' => '#FFD700'],
        ['min' => 60, 'label' => '길',   'emoji' => '☀️', 'color' => '#4CAF50'],
        ['min' => 45, 'label' => '보통', 'emoji' => '☁️', 'color' => '#9E9E9E'],
        ['min' => 30, 'label' => '소흉', 'emoji' => '🌧️', 'color' => '#FF9800'],
        ['min' => 0,  'label' => '흉',   'emoji' => '⛈️', 'color' => '#F44336'],
    ];

    /** 12운성 점수 가감 */
    const TWELVE_STAGE_SCORES = [
        '장생' => 8,  '목욕' => -3, '관대' => 6, '건록' => 10,
        '제왕' => 7,  '쇠'   => -2, '병'   => -5,'사'   => -8,
        '묘'   => -10,'절'   => -6, '태'   => 2, '양'   => 4,
    ];

    /** 12운성 의미 */
    const TWELVE_STAGE_MEANINGS = [
        '장생' => '새로운 시작과 성장의 에너지가 솟아오르는 시기입니다.',
        '목욕' => '성장통과 시행착오를 겪으며, 실질적 경험을 쌓는 시기입니다.',
        '관대' => '사회적 활동이 활발해지고, 영향력이 확대되는 시기입니다.',
        '건록' => '일간의 힘이 가장 왕성하여, 주체적으로 삶을 개척하는 전성기입니다.',
        '제왕' => '에너지가 정점에 달해, 최고 성과를 이루지만 과욕을 경계해야 하는 시기입니다.',
        '쇠'   => '기운이 서서히 내려가지만, 경험과 지혜가 빛을 발하는 시기입니다.',
        '병'   => '에너지가 약해지니, 건강 관리와 내면 성찰에 집중해야 하는 시기입니다.',
        '사'   => '한 단계가 마무리되며, 집착을 내려놓고 새로운 준비를 해야 하는 시기입니다.',
        '묘'   => '깊은 잠복과 재충전의 시기로, 내면에서 다음 단계를 준비합니다.',
        '절'   => '가장 약한 시기이지만, 어둠이 깊을수록 새벽이 가까운 인고의 시간입니다.',
        '태'   => '새로운 가능성이 잉태되어, 아직 보이지 않지만 무언가가 자라는 시기입니다.',
        '양'   => '서서히 힘을 기르며 준비하는 시기로, 봄을 앞둔 겨울의 끝자락입니다.',
    ];

    /** 오행상생 관계 (source → generated) */
    const OHANG_SANGSAENG = [
        '목' => '화', '화' => '토', '토' => '금', '금' => '수', '수' => '목',
    ];

    /** 오행상극 관계 (source → controlled) */
    const OHANG_SANGGEUK = [
        '목' => '토', '화' => '금', '토' => '수', '금' => '목', '수' => '화',
    ];

    /** 신강/신약 × 대운 유형 조합 핵심 해석 */
    const STRENGTH_DAEUN_MATRIX = [
        'strong' => [
            'bigyeop'  => ['quality' => 'caution',  'desc' => '이미 강한 일간에 같은 힘이 더해져 에너지가 과잉됩니다. 자아가 지나치게 강해져 충돌이 잦아질 수 있으니, 겸손과 양보가 필요한 시기입니다.'],
            'siksang'  => ['quality' => 'favorable', 'desc' => '강한 에너지를 재능과 표현으로 발산하는 최적의 시기입니다. 창의적 활동과 자기 표현이 빛을 발하며, 새로운 분야 개척에 유리합니다.'],
            'jaesung'  => ['quality' => 'favorable', 'desc' => '강한 일간이 재물을 다스리는 힘이 충분하여, 경제적 성과와 사업 확장에 유리한 시기입니다. 자신감을 가지고 적극적으로 도전하세요.'],
            'gwansung' => ['quality' => 'mixed',     'desc' => '강한 일간을 관성이 제어하여, 사회적 책임과 규율이 더해집니다. 조직 내 승진이나 공적 인정을 받을 수 있지만, 자유로운 활동에 제약이 생길 수 있습니다.'],
            'insung'   => ['quality' => 'caution',   'desc' => '이미 강한 일간에 인성이 더해져 힘이 더 강해집니다. 학문적 성취는 있지만, 에너지 발산의 통로가 막히지 않도록 주의가 필요합니다.'],
        ],
        'weak' => [
            'bigyeop'  => ['quality' => 'favorable', 'desc' => '약한 일간에 같은 힘이 보태져 자신감과 실행력이 강화됩니다. 동료와 형제의 도움을 받아 어려움을 극복하는 시기입니다.'],
            'siksang'  => ['quality' => 'caution',   'desc' => '약한 일간의 에너지가 식상으로 빠져나가 기력이 소진될 수 있습니다. 무리한 표현이나 활동보다 내면의 힘을 기르는 데 집중하세요.'],
            'jaesung'  => ['quality' => 'caution',   'desc' => '약한 일간이 재물의 무게를 감당하기 어려운 시기입니다. 과도한 투자나 욕심을 자제하고, 안정적 재무 관리에 집중해야 합니다.'],
            'gwansung' => ['quality' => 'difficult',  'desc' => '약한 일간에 관성의 압박이 더해져, 스트레스와 부담이 가중됩니다. 건강 관리에 유의하고, 인성(학문·귀인)의 도움을 적극 구하세요.'],
            'insung'   => ['quality' => 'favorable', 'desc' => '약한 일간을 인성이 생해 주어, 학문적 성취와 귀인의 도움이 찾아오는 시기입니다. 공부나 자격 취득에 매우 유리합니다.'],
        ],
    ];

    // ========================================================
    // 인스턴스 변수
    // ========================================================
    private $engine;
    private $result;
    private $interpData;
    private $daeunPeriods = [];
    private $wongukContext = [];

    // ========================================================
    // 생성자
    // ========================================================
    public function __construct(SajuEngine $engine) {
        $this->engine     = $engine;
        $this->result     = $engine->getResult();
        $this->interpData = new DaeunInterpretationData();
        $this->buildWongukContext();
        $this->calculateDaeunPeriods();
    }

    // ========================================================
    // 공개 API
    // ========================================================

    /**
     * 전체 대운 분석 실행
     * 
     * @return array [
     *   'overview'    => [...],       // 대운 전체 개요
     *   'periods'     => [...],       // 10개 대운 기간별 상세 분석
     *   'timeline'    => string,      // 인생 타임라인 내러티브
     *   'statistics'  => [...],       // 통계 정보
     * ]
     */
    public function analyze(): array {
        $periods = [];
        foreach ($this->daeunPeriods as $period) {
            $periods[] = $this->analyzePeriod($period);
        }

        return [
            'overview'   => $this->buildOverview($periods),
            'periods'    => $periods,
            'timeline'   => $this->buildTimeline($periods),
            'statistics' => $this->buildStatistics($periods),
        ];
    }

    /**
     * 특정 대운 기간만 분석
     */
    public function analyzeSinglePeriod(int $index): ?array {
        if (!isset($this->daeunPeriods[$index])) return null;
        return $this->analyzePeriod($this->daeunPeriods[$index]);
    }

    /**
     * 대운 기간 목록 반환 (해석 없이)
     */
    public function getDaeunPeriods(): array {
        return $this->daeunPeriods;
    }

    /**
     * 원국 컨텍스트 반환 (디버그용)
     */
    public function getWongukContext(): array {
        return $this->wongukContext;
    }

    // ========================================================
    // 내부: 원국 컨텍스트 구축
    // ========================================================

    private function buildWongukContext(): void {
        $dms = $this->result['day_master_strength'];
        $sipsinFull = $this->result['sipsin_full'];
        $dist = $sipsinFull['distribution'] ?? [];

        // 십성 그룹 합산
        $sipsinGroups = [
            'bigyeop'  => ($dist['비견'] ?? 0) + ($dist['겁재'] ?? 0),
            'siksang'  => ($dist['식신'] ?? 0) + ($dist['상관'] ?? 0),
            'jaesung'  => ($dist['편재'] ?? 0) + ($dist['정재'] ?? 0),
            'gwansung' => ($dist['편관'] ?? 0) + ($dist['정관'] ?? 0),
            'insung'   => ($dist['편인'] ?? 0) + ($dist['정인'] ?? 0),
        ];

        // 주요 특성 판별
        $dominant = array_keys($sipsinGroups, max($sipsinGroups))[0];
        $weakest  = array_keys($sipsinGroups, min($sipsinGroups))[0];

        $this->wongukContext = [
            'dayMaster'      => $this->result['day_master'],
            'dayElement'     => $this->result['day_master_element'],
            'isStrong'       => $dms['is_strong'],
            'strength'       => $dms['strength'],
            'strengthRatio'  => $dms['ratio'],
            'gender'         => $this->engine->getGender(),
            'sipsinDist'     => $dist,
            'sipsinGroups'   => $sipsinGroups,
            'dominantGroup'  => $dominant,
            'weakestGroup'   => $weakest,
            'yongshin'       => $dms['yongshin'] ?? [],
            'heeshin'        => $dms['heeshin'] ?? [],
            'gishin'         => $dms['gishin'] ?? [],
            'gushin'         => $dms['gushin'] ?? [],
            'birthYear'      => $this->result['input']['year'],
        ];
    }

    // ========================================================
    // 내부: 대운 기간 계산
    // ========================================================

    private function calculateDaeunPeriods(): void {
        $gender     = $this->engine->getGender();
        $yearStem   = $this->engine->getYearPillar()[0];
        $monthStem  = $this->engine->getMonthPillar()[0];
        $monthBranch = $this->engine->getMonthPillar()[1];
        $dayStemIdx = $this->engine->getDayPillar()[0];

        $dayElement = $this->result['day_master_element'];
        $dayYY      = SajuEngine::CHEONGAN_YINYANG[$dayStemIdx];
        $dms        = $this->result['day_master_strength'];
        $yongshinEl = $dms['yongshin']['element'] ?? '토';
        $heeshinEl  = $dms['heeshin']['element'] ?? '';
        $gishinEl   = $dms['gishin']['element'] ?? '';
        $gushinEl   = $dms['gushin']['element'] ?? '';

        // 순행/역행 판별
        $isYangMale  = (SajuEngine::CHEONGAN_YINYANG[$yearStem] === 0 && $gender === 'male');
        $isYinFemale = (SajuEngine::CHEONGAN_YINYANG[$yearStem] === 1 && $gender === 'female');
        $forward     = ($isYangMale || $isYinFemale);

        // 대운 시작 나이 계산
        $startAge = $this->calculateStartAge($forward);

        $this->daeunPeriods = [];
        for ($i = 0; $i < 10; $i++) {
            $age = $startAge + ($i * 10);
            $stemIdx   = $forward
                ? ($monthStem + $i + 1) % 10
                : ($monthStem - $i - 1 + 100) % 10;
            $branchIdx = $forward
                ? ($monthBranch + $i + 1) % 12
                : ($monthBranch - $i - 1 + 120) % 12;

            $stem   = SajuEngine::CHEONGAN[$stemIdx];
            $branch = SajuEngine::JIJI[$branchIdx];
            $stemEl   = SajuEngine::CHEONGAN_OHANG[$stem];
            $branchEl = SajuEngine::JIJI_OHANG[$branch];

            $stemSipsin   = SajuEngine::getSipsin($dayElement, $dayYY, $stemEl, SajuEngine::CHEONGAN_YINYANG[$stemIdx]);
            $branchSipsin = SajuEngine::getSipsin($dayElement, $dayYY, $branchEl, SajuEngine::JIJI_YINYANG[$branchIdx]);

            $twelveStage  = $this->engine->getTwelveStage($dayStemIdx, $branchIdx);
            $relationships = $this->engine->analyzeRelationships(
                SajuEngine::JIJI[$branchIdx], SajuEngine::CHEONGAN[$stemIdx]
            );

            // 용신/기신 판별
            $stemIsYongshin = ($stemEl === $yongshinEl);
            $branchIsYongshin = false;
            foreach (SajuEngine::JIJANGGAN[$branch] as $item) {
                if (SajuEngine::CHEONGAN_OHANG[$item[0]] === $yongshinEl) {
                    $branchIsYongshin = true;
                    break;
                }
            }
            $isYongshinDaeun = ($stemIsYongshin || $branchIsYongshin);
            $isGishinDaeun   = ($stemEl === $gishinEl || $branchEl === $gishinEl);

            // 점수 계산
            $score = $this->calculateScore(
                $stemEl, $branchEl, $yongshinEl, $heeshinEl,
                $gishinEl, $gushinEl, $relationships, $twelveStage
            );

            // 대운 유형 분류
            $stemGroup  = self::SIPSIN_TO_GROUP[$stemSipsin] ?? 'bigyeop';
            $stemSubtype = self::SIPSIN_TO_SUBTYPE[$stemSipsin] ?? 'bigyeon';
            $branchGroup = self::SIPSIN_TO_GROUP[$branchSipsin] ?? 'bigyeop';
            $branchSubtype = self::SIPSIN_TO_SUBTYPE[$branchSipsin] ?? 'bigyeon';

            // 대표 유형 = 천간 기준 (외적 운의 주도적 흐름)
            $primaryGroup = $stemGroup;
            $primarySubtype = $stemSubtype;

            // 천간·지지가 같은 그룹이면 '순수 대운'으로 강화
            $isPure = ($stemGroup === $branchGroup);

            $this->daeunPeriods[] = [
                'index'           => $i,
                'age_start'       => $age,
                'age_end'         => $age + 9,
                'stem'            => $stem,
                'branch'          => $branch,
                'stem_hanja'      => SajuEngine::CHEONGAN_HANJA[$stemIdx],
                'branch_hanja'    => SajuEngine::JIJI_HANJA[$branchIdx],
                'stem_element'    => $stemEl,
                'branch_element'  => $branchEl,
                'stem_sipsin'     => $stemSipsin,
                'branch_sipsin'   => $branchSipsin,
                'primary_group'   => $primaryGroup,
                'primary_subtype' => $primarySubtype,
                'branch_group'    => $branchGroup,
                'branch_subtype'  => $branchSubtype,
                'is_pure'         => $isPure,
                'twelve_stage'    => $twelveStage,
                'relationships'   => $relationships,
                'is_yongshin'     => $isYongshinDaeun,
                'is_gishin'       => $isGishinDaeun,
                'score'           => $score,
                'score_level'     => $this->getScoreLevel($score),
            ];
        }
    }

    /**
     * 대운 시작 나이 계산
     */
    private function calculateStartAge(bool $forward): int {
        $solarMonth = $this->result['solar_date']['month'];
        $day = $this->result['input']['day'];
        $jeolgiDay = 5; // 평균 절입일

        if ($forward) {
            $monthToNext = (13 - $solarMonth) % 12;
            if ($monthToNext === 0) $monthToNext = 1;
            $days = ($monthToNext * 30) + ($jeolgiDay - $day);
        } else {
            $days = ($day - $jeolgiDay) + (($solarMonth - 1) % 12);
            if ($days < 0) $days = abs($days);
        }
        $days = max(1, min(120, abs($days)));
        return max(1, min(10, (int)round($days / 3)));
    }

    /**
     * 대운 점수 계산
     */
    private function calculateScore(
        string $stemEl, string $branchEl,
        string $yongshinEl, string $heeshinEl,
        string $gishinEl, string $gushinEl,
        array $relationships, string $twelveStage
    ): int {
        $score = 50;

        // 용신·희신 보너스
        if ($stemEl === $yongshinEl) $score += 15;
        if ($branchEl === $yongshinEl) $score += 10;
        if ($stemEl === $heeshinEl || $branchEl === $heeshinEl) $score += 8;

        // 기신·구신 감점
        if ($stemEl === $gishinEl) $score -= 12;
        if ($branchEl === $gishinEl) $score -= 8;
        if ($stemEl === $gushinEl || $branchEl === $gushinEl) $score -= 5;

        // 12운성 가감
        $score += (self::TWELVE_STAGE_SCORES[$twelveStage] ?? 0);

        // 합충형파해 가감
        foreach ($relationships as $rel) {
            switch ($rel['type']) {
                case '육합': case '삼합': case '방합': case '천간합':
                    $score += 5; break;
                case '충':      $score -= 8;  break;
                case '형':      $score -= 6;  break;
                case '해':      $score -= 4;  break;
                case '파':      $score -= 3;  break;
                case '천간충':  $score -= 5;  break;
            }
        }

        return max(10, min(95, $score));
    }

    /**
     * 점수 등급 반환
     */
    private function getScoreLevel(int $score): array {
        foreach (self::SCORE_LEVELS as $level) {
            if ($score >= $level['min']) return $level;
        }
        return self::SCORE_LEVELS[count(self::SCORE_LEVELS) - 1];
    }

    /**
     * 나이대 판별
     */
    private function getAgeGroup(int $age): string {
        foreach (self::AGE_GROUPS as $key => [$min, $max, $label]) {
            if ($age >= $min && $age <= $max) return $key;
        }
        return 'middle';
    }

    // ========================================================
    // 개별 대운 기간 분석
    // ========================================================

    private function analyzePeriod(array $period): array {
        $group    = $period['primary_group'];
        $subtype  = $period['primary_subtype'];
        $ageGroup = $this->getAgeGroup($period['age_start']);
        $isStrong = $this->wongukContext['isStrong'];
        $gender   = $this->wongukContext['gender'];

        // 필터링 컨텍스트
        $filterContext = [
            'strength'  => $isStrong ? 'strong' : 'weak',
            'gender'    => $gender,
            'age_group' => $ageGroup,
            'is_pure'   => $period['is_pure'],
        ];

        // ─── 4대 영역별 해석 수집 ───
        $interpretations = [];
        foreach (self::INTERPRETATION_CATEGORIES as $cat) {
            $interpretations[$cat] = $this->buildCategoryInterpretation(
                $group, $subtype, $cat, $period, $filterContext
            );
        }

        // ─── 종합 내러티브 생성 ───
        $narrative = $this->buildPeriodNarrative($period, $interpretations);

        return [
            'period'          => $period,
            'group_label'     => self::GROUP_LABELS[$group] ?? '',
            'subtype_label'   => self::SUBTYPE_LABELS[$subtype] ?? '',
            'strength_combo'  => $this->getStrengthComboDesc($group),
            'interpretations' => $interpretations,
            'narrative'       => $narrative,
            'advice'          => $this->buildPeriodAdvice($period, $group),
        ];
    }

    /**
     * 카테고리별 해석 텍스트 구성
     */
    private function buildCategoryInterpretation(
        string $group, string $subtype, string $category,
        array $period, array $filterContext
    ): array {
        $sentences = [];

        // Layer 1: 대운 유형 기본 해석
        $baseSentences = $this->interpData->getFilteredSentences(
            $group, $category, $filterContext
        );
        foreach ($baseSentences as $s) {
            $sentences[] = ['text' => $s, 'source' => 'base'];
        }

        // Layer 2: 세부 유형(편재/정재 등) 특화 해석
        $subtypeSentences = $this->interpData->getSubtypeSentences(
            $subtype, $category, $filterContext
        );
        foreach ($subtypeSentences as $s) {
            $sentences[] = ['text' => $s, 'source' => 'subtype'];
        }

        // Layer 3: 신강/신약 × 대운유형 조합 해석
        $crossSentences = $this->interpData->getCrossSentences(
            $group, $filterContext['strength'], $category, $filterContext
        );
        foreach ($crossSentences as $s) {
            $sentences[] = ['text' => $s, 'source' => 'cross'];
        }

        // Layer 4: 용신/기신 보정
        $yongshinMod = '';
        if ($period['is_yongshin']) {
            $yongshinMod = $this->getYongshinModifier($category);
        } elseif ($period['is_gishin']) {
            $yongshinMod = $this->getGishinModifier($category);
        }

        // Layer 5: 12운성 보정
        $stageMod = $this->getTwelveStageModifier($period['twelve_stage'], $category);

        // Layer 6: 합충형파해 보정
        $relMods = $this->getRelationshipModifiers($period['relationships'], $category);

        // 조립
        $mainText = $this->assembleSentences($sentences, $category);
        $modifiers = array_filter([$yongshinMod, $stageMod, implode(' ', $relMods)]);

        return [
            'main_text'  => $mainText,
            'modifiers'  => implode(' ', $modifiers),
            'full_text'  => trim($mainText . ' ' . implode(' ', $modifiers)),
            'sentence_count' => count($sentences),
        ];
    }

    /**
     * 문장 배열을 자연스러운 텍스트로 조립
     */
    private function assembleSentences(array $sentences, string $category): string {
        if (empty($sentences)) return '';

        $transitions = [
            '또한 ', '아울러 ', '특히 ', '이와 함께, ',
            '나아가 ', '한편 ', '이에 더해, ',
        ];

        $parts = [];
        $seen = [];
        foreach ($sentences as $i => $item) {
            $text = $item['text'];
            $hash = md5($text);
            if (isset($seen[$hash])) continue;
            $seen[$hash] = true;

            if ($i > 0 && $i % 3 === 0 && count($parts) > 0) {
                $tr = $transitions[array_rand($transitions)];
                $parts[] = $tr . $text;
            } else {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
    }

    // ========================================================
    // 보정 모듈 (용신/12운성/합충)
    // ========================================================

    private function getYongshinModifier(string $category): string {
        $mods = [
            'life_change'         => '이 대운은 용신(用神)의 기운이 흐르는 시기로, 인생의 중요한 전환점이 될 수 있습니다. 그동안 막혔던 일들이 풀리기 시작하며, 새로운 기회의 문이 열립니다.',
            'career_change'       => '용신 대운에서의 직업적 기회는 매우 긍정적입니다. 승진, 이직, 새 사업 모두 좋은 결과를 기대할 수 있으며, 실력이 인정받는 시기입니다.',
            'relationship_change' => '용신 기운이 들어오면서 대인관계도 활발해집니다. 좋은 인연을 만나거나, 기존 관계가 더 깊어지는 시기입니다. 귀인(貴人)의 도움을 기대할 수 있습니다.',
            'wealth_change'       => '용신 대운에서는 재물의 흐름이 순조롭습니다. 수입이 늘어나고 예상치 못한 금전적 행운이 따를 수 있습니다. 적극적인 투자와 재테크에 유리한 시기입니다.',
        ];
        return $mods[$category] ?? '';
    }

    private function getGishinModifier(string $category): string {
        $mods = [
            'life_change'         => '이 대운은 기신(忌神)의 기운이 강해지는 시기로, 예상치 못한 변수와 어려움이 나타날 수 있습니다. 신중한 판단과 인내가 특히 중요합니다.',
            'career_change'       => '기신 대운에서의 직업적 변화는 신중하게 접근해야 합니다. 성급한 이직이나 사업 확장보다는 현재 위치에서의 내실 다지기가 현명합니다.',
            'relationship_change' => '기신 기운으로 인해 대인관계에 마찰이 생길 수 있습니다. 오해나 갈등에 주의하고, 감정 조절을 통해 관계를 보호하세요.',
            'wealth_change'       => '기신 대운에서의 재물 운은 보수적으로 관리해야 합니다. 무리한 투자는 피하고, 지출 관리에 힘써야 합니다. 안전한 저축이 최선의 전략입니다.',
        ];
        return $mods[$category] ?? '';
    }

    private function getTwelveStageModifier(string $stage, string $category): string {
        $positiveStages = ['장생', '관대', '건록', '제왕', '태', '양'];
        $isPositive = in_array($stage, $positiveStages);

        if ($category === 'career_change') {
            return $isPositive
                ? "12운성 '{$stage}'의 에너지가 직업 운을 밀어주어, 이 시기의 직업적 활동에 활력을 더합니다."
                : "12운성 '{$stage}'의 영향으로 직업적 에너지가 다소 약해질 수 있으니, 무리한 도전보다 내실을 기르는 데 집중하세요.";
        }
        if ($category === 'wealth_change') {
            return $isPositive
                ? "12운성 '{$stage}'의 기운이 재물 운에도 긍정적 영향을 미쳐, 경제적 안정을 기대할 수 있습니다."
                : "12운성 '{$stage}'의 영향으로 재물 흐름이 다소 위축될 수 있으니, 보수적 재무 전략이 필요합니다.";
        }
        return '';
    }

    private function getRelationshipModifiers(array $relationships, string $category): array {
        $mods = [];
        foreach ($relationships as $rel) {
            $type = $rel['type'];

            if (in_array($type, ['육합', '삼합', '방합', '천간합'])) {
                if ($category === 'relationship_change') {
                    $mods[] = "'{$rel['chars']}' {$type}의 작용으로 인연이 결합하는 에너지가 강해져, 새로운 관계나 결합의 가능성이 높아집니다.";
                } elseif ($category === 'career_change') {
                    $mods[] = "{$type}의 에너지가 협력과 파트너십에 유리하게 작용합니다.";
                }
            } elseif ($type === '충') {
                if ($category === 'life_change') {
                    $mods[] = "'{$rel['chars']}' 충(沖)이 발생하여, 이 시기에 예상치 못한 변화나 이동이 일어날 수 있습니다. 이사, 이직, 관계의 급변 등에 대비하세요.";
                } elseif ($category === 'relationship_change') {
                    $mods[] = "충의 에너지로 인해 기존 관계에 변화가 생기거나, 갈등이 격화될 수 있습니다.";
                }
            } elseif ($type === '형') {
                if ($category === 'career_change') {
                    $mods[] = "'{$rel['chars']}' 형(刑)의 작용으로 법적 문제나 규율적 갈등이 발생할 수 있으니 주의하세요.";
                }
            }
        }
        return $mods;
    }

    // ========================================================
    // 신강/신약 조합 설명
    // ========================================================

    private function getStrengthComboDesc(string $group): string {
        $key = $this->wongukContext['isStrong'] ? 'strong' : 'weak';
        return self::STRENGTH_DAEUN_MATRIX[$key][$group]['desc'] ?? '';
    }

    // ========================================================
    // 기간별 내러티브 생성
    // ========================================================

    private function buildPeriodNarrative(array $period, array $interpretations): string {
        $ageStart = $period['age_start'];
        $ageEnd   = $period['age_end'];
        $level    = $period['score_level'];
        $group    = $period['primary_group'];
        $groupLabel = self::GROUP_LABELS[$group] ?? '';
        $stemSipsin = $period['stem_sipsin'];
        $branchSipsin = $period['branch_sipsin'];
        $stage    = $period['twelve_stage'];
        $pureLabel = $period['is_pure'] ? '(순수 대운)' : '';
        $isStrong = $this->wongukContext['isStrong'];
        $strengthLabel = $isStrong ? '신강' : '신약';

        $ageGroupKey = $this->getAgeGroup($ageStart);
        $ageGroupLabel = self::AGE_GROUPS[$ageGroupKey][2] ?? '';

        $text = "";

        // 헤더
        $text .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $text .= "  {$level['emoji']} {$ageStart}~{$ageEnd}세 대운 심층 분석 [{$ageGroupLabel}]\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        // 기본 정보
        $text .= "  대운 간지: {$period['stem']}{$period['branch']}";
        $text .= "({$period['stem_hanja']}{$period['branch_hanja']})\n";
        $text .= "  대운 유형: {$groupLabel} {$pureLabel}\n";
        $text .= "  천간 십성: {$stemSipsin} ({$period['stem_element']})\n";
        $text .= "  지지 십성: {$branchSipsin} ({$period['branch_element']})\n";
        $text .= "  12운성: {$stage}\n";
        $text .= "  운세 점수: {$period['score']}점 ({$level['label']})\n";
        if ($period['is_yongshin']) {
            $text .= "  ✅ 용신(用神) 대운\n";
        } elseif ($period['is_gishin']) {
            $text .= "  ⚠️ 기신(忌神) 대운\n";
        }
        $text .= "\n";

        // 원국 × 대운 조합 분석
        $comboDesc = $this->getStrengthComboDesc($group);
        $text .= "【{$strengthLabel} × {$groupLabel}】\n";
        $text .= $comboDesc . "\n\n";

        // 12운성 설명
        $stageMeaning = self::TWELVE_STAGE_MEANINGS[$stage] ?? '';
        if ($stageMeaning) {
            $text .= "【12운성: {$stage}】\n";
            $text .= $stageMeaning . "\n\n";
        }

        // 4대 영역 해석
        foreach (self::INTERPRETATION_CATEGORIES as $cat) {
            $catLabel = self::CATEGORY_LABELS[$cat];
            $icons = [
                'life_change'         => '🔄',
                'career_change'       => '💼',
                'relationship_change' => '❤️',
                'wealth_change'       => '💰',
            ];
            $icon = $icons[$cat] ?? '📋';
            $interp = $interpretations[$cat];

            $text .= "{$icon} 【{$catLabel}】\n";
            $text .= $interp['full_text'] . "\n\n";
        }

        // 합충형파해 정보
        if (!empty($period['relationships'])) {
            $text .= "🔗 【원국과의 합충형파해】\n";
            foreach ($period['relationships'] as $rel) {
                $text .= "  [{$rel['type']}] {$rel['chars']}\n";
            }
            $text .= "\n";
        }

        return $text;
    }

    // ========================================================
    // 기간별 조언 생성
    // ========================================================

    private function buildPeriodAdvice(array $period, string $group): string {
        $isStrong = $this->wongukContext['isStrong'];
        $quality  = self::STRENGTH_DAEUN_MATRIX[$isStrong ? 'strong' : 'weak'][$group]['quality'] ?? 'mixed';
        $stage    = $period['twelve_stage'];
        $isYong   = $period['is_yongshin'];
        $isGi     = $period['is_gishin'];

        $advices = [];

        // 대운 유형별 조언
        $typeAdvice = [
            'jaesung' => [
                'favorable' => '재성 대운의 좋은 기운을 최대한 활용하세요. 적극적인 경제활동과 투자가 유리합니다. 다만 과욕을 부리지 말고, 실력을 기반으로 한 안정적 수익을 추구하세요.',
                'caution'   => '재성의 기운이 강해지지만, 현재 사주 구조상 무리한 투자는 위험합니다. 안정적 저축과 실력 향상에 집중하며, 큰 결정은 전문가 조언을 들어보세요.',
                'mixed'     => '재물 운이 혼재된 시기입니다. 확실한 기회와 위험을 구분하여, 안전한 것부터 추진하세요.',
                'difficult' => '재물에 대한 욕심을 내려놓고, 건강과 내면의 평화에 집중하는 것이 현명합니다.',
            ],
            'gwansung' => [
                'favorable' => '사회적 인정과 승진의 기회입니다. 책임감을 가지고 맡은 바를 충실히 이행하면 큰 성과를 올릴 수 있습니다.',
                'caution'   => '관성의 압박이 스트레스로 작용할 수 있습니다. 건강 관리에 유의하고, 무리한 책임보다 자기 역량에 맞는 활동에 집중하세요.',
                'mixed'     => '사회적 기회와 압박이 공존하는 시기입니다. 냉정한 판단으로 기회를 선별하세요.',
                'difficult' => '법적 문제나 권력 갈등에 주의하세요. 인성(학문·귀인)의 도움을 적극적으로 구하는 것이 현명합니다.',
            ],
            'siksang' => [
                'favorable' => '창의력과 표현력이 빛나는 시기입니다. 새로운 기술을 배우거나, 창작 활동, 교육 관련 사업에 도전하기 좋습니다.',
                'caution'   => '에너지 소진에 주의하세요. 재능 발휘에 너무 몰두하면 건강을 해칠 수 있습니다. 적절한 휴식과 영양 보충이 필요합니다.',
                'mixed'     => '재능과 표현의 욕구가 강해지는 시기입니다. 실질적 성과로 연결되는 활동에 집중하세요.',
                'difficult' => '무리한 활동보다 내면의 안정을 먼저 찾으세요. 건강이 우선입니다.',
            ],
            'insung' => [
                'favorable' => '학문과 자기계발의 최적기입니다. 자격증 취득, 전문 교육, 대학원 진학 등이 좋은 결과를 가져옵니다. 어머니나 스승의 도움이 큽니다.',
                'caution'   => '지나친 학문 몰입이나 공상에 빠지지 않도록 주의하세요. 배운 것을 실천으로 옮기는 균형이 중요합니다.',
                'mixed'     => '배움의 기회가 있지만, 이론과 실천의 균형이 필요한 시기입니다.',
                'difficult' => '자신의 역량을 객관적으로 평가하고, 현실적인 목표를 세우세요.',
            ],
            'bigyeop' => [
                'favorable' => '동료와 형제의 도움이 큰 시기입니다. 팀워크와 협력을 통해 어려움을 극복하세요. 함께하는 사업이나 활동이 유리합니다.',
                'caution'   => '경쟁과 충돌이 심해질 수 있습니다. 재물의 손실에 주의하고, 보증이나 공동 투자는 신중하게 판단하세요.',
                'mixed'     => '협력과 경쟁이 공존하는 시기입니다. 신뢰할 수 있는 인연을 선별하세요.',
                'difficult' => '독단적 판단보다 전문가의 조언을 구하세요. 재물 관리에 특히 주의가 필요합니다.',
            ],
        ];

        $advices[] = $typeAdvice[$group][$quality] ?? $typeAdvice[$group]['mixed'] ?? '';

        // 용신/기신 추가 조언
        if ($isYong) {
            $advices[] = '💡 용신(用神)의 기운이 흐르는 대운입니다. 인생의 중요한 결정을 이 시기에 실행하면 좋은 결과를 얻을 수 있습니다.';
        } elseif ($isGi) {
            $advices[] = '⚠️ 기신(忌神)의 기운이 강한 시기입니다. 큰 변화보다 안정을 추구하고, 용신 오행을 보강하는 활동을 병행하세요.';
        }

        return implode("\n\n", array_filter($advices));
    }

    // ========================================================
    // 전체 개요 생성
    // ========================================================

    private function buildOverview(array $analyzedPeriods): array {
        $totalScore = 0;
        $bestPeriod = null;
        $worstPeriod = null;
        $bestScore = 0;
        $worstScore = 100;
        $typeDistribution = [];
        $yongshinPeriods = [];
        $gishinPeriods = [];

        foreach ($analyzedPeriods as $ap) {
            $p = $ap['period'];
            $totalScore += $p['score'];

            if ($p['score'] > $bestScore) {
                $bestScore = $p['score'];
                $bestPeriod = $p;
            }
            if ($p['score'] < $worstScore) {
                $worstScore = $p['score'];
                $worstPeriod = $p;
            }

            $group = $p['primary_group'];
            $typeDistribution[$group] = ($typeDistribution[$group] ?? 0) + 1;

            if ($p['is_yongshin']) $yongshinPeriods[] = $p;
            if ($p['is_gishin'])   $gishinPeriods[] = $p;
        }

        $avgScore = count($analyzedPeriods) > 0
            ? round($totalScore / count($analyzedPeriods), 1)
            : 0;

        // 대운 흐름 추세 계산
        $scores = array_map(fn($ap) => $ap['period']['score'], $analyzedPeriods);
        $firstHalf = array_slice($scores, 0, 5);
        $secondHalf = array_slice($scores, 5);
        $firstAvg = count($firstHalf) > 0 ? array_sum($firstHalf) / count($firstHalf) : 0;
        $secondAvg = count($secondHalf) > 0 ? array_sum($secondHalf) / count($secondHalf) : 0;

        if ($secondAvg > $firstAvg + 5) {
            $trend = 'ascending';
            $trendLabel = '후반 상승형';
            $trendDesc = '인생 후반부로 갈수록 운이 좋아지는 구조입니다. 젊은 시절의 고생이 나중에 큰 자산이 됩니다.';
        } elseif ($secondAvg < $firstAvg - 5) {
            $trend = 'descending';
            $trendLabel = '전반 호조형';
            $trendDesc = '인생 전반부에 좋은 운이 집중되어 있습니다. 젊은 시절의 기회를 최대한 활용하고, 미래를 대비하세요.';
        } else {
            $trend = 'stable';
            $trendLabel = '안정 균형형';
            $trendDesc = '전반적으로 큰 기복 없이 안정적인 운의 흐름입니다. 꾸준한 노력이 정직한 성과를 만듭니다.';
        }

        return [
            'total_periods'      => count($analyzedPeriods),
            'average_score'      => $avgScore,
            'best_period'        => $bestPeriod,
            'worst_period'       => $worstPeriod,
            'type_distribution'  => $typeDistribution,
            'yongshin_periods'   => count($yongshinPeriods),
            'gishin_periods'     => count($gishinPeriods),
            'trend'              => $trend,
            'trend_label'        => $trendLabel,
            'trend_description'  => $trendDesc,
            'overall_level'      => $this->getScoreLevel((int)round($avgScore)),
        ];
    }

    // ========================================================
    // 인생 타임라인 내러티브
    // ========================================================

    private function buildTimeline(array $analyzedPeriods): string {
        $dm = $this->wongukContext['dayMaster'];
        $el = $this->wongukContext['dayElement'];
        $isStrong = $this->wongukContext['isStrong'];
        $strengthLabel = $isStrong ? '신강(身强)' : '신약(身弱)';
        $gender = $this->wongukContext['gender'] === 'male' ? '남성' : '여성';

        $text = "═══════════════════════════════════════════\n";
        $text .= " 📜 {$dm}일간 {$gender}의 대운 인생 타임라인\n";
        $text .= "═══════════════════════════════════════════\n\n";

        $text .= "일간: {$dm}({$el}) | {$strengthLabel}\n";

        $yongshinEl = $this->wongukContext['yongshin']['element'] ?? '';
        $yongshinType = $this->wongukContext['yongshin']['type'] ?? '';
        if ($yongshinEl) {
            $text .= "용신: {$yongshinEl}({$yongshinType})\n";
        }
        $text .= "\n";

        // 각 대운 요약
        $prevScore = null;
        foreach ($analyzedPeriods as $ap) {
            $p = $ap['period'];
            $level = $p['score_level'];
            $group = self::GROUP_LABELS[$p['primary_group']] ?? '';

            $trendArrow = '';
            if ($prevScore !== null) {
                if ($p['score'] > $prevScore + 5) $trendArrow = ' 📈';
                elseif ($p['score'] < $prevScore - 5) $trendArrow = ' 📉';
                else $trendArrow = ' ➡️';
            }

            $yongMark = $p['is_yongshin'] ? ' ✅용신' : ($p['is_gishin'] ? ' ⚠️기신' : '');

            $text .= "{$level['emoji']} {$p['age_start']}~{$p['age_end']}세";
            $text .= " | {$p['stem']}{$p['branch']}({$p['stem_hanja']}{$p['branch_hanja']})";
            $text .= " | {$group}";
            $text .= " | {$p['score']}점{$trendArrow}{$yongMark}\n";

            // 핵심 한 줄 요약
            $text .= "   → " . $this->getOneLinerSummary($ap) . "\n\n";

            $prevScore = $p['score'];
        }

        // 전체 흐름 평가
        $overview = $this->buildOverview($analyzedPeriods);
        $text .= "═══════════════════════════════════════════\n";
        $text .= " 📊 전체 대운 흐름: {$overview['trend_label']}\n";
        $text .= "═══════════════════════════════════════════\n\n";
        $text .= "평균 점수: {$overview['average_score']}점 ({$overview['overall_level']['label']})\n";
        $text .= $overview['trend_description'] . "\n";

        if ($overview['best_period']) {
            $bp = $overview['best_period'];
            $text .= "\n🌟 최고 대운: {$bp['age_start']}~{$bp['age_end']}세 ({$bp['stem']}{$bp['branch']}, {$bp['score']}점)";
        }
        if ($overview['worst_period']) {
            $wp = $overview['worst_period'];
            $text .= "\n⛈️ 최저 대운: {$wp['age_start']}~{$wp['age_end']}세 ({$wp['stem']}{$wp['branch']}, {$wp['score']}점)";
        }
        $text .= "\n용신 대운: {$overview['yongshin_periods']}개 | 기신 대운: {$overview['gishin_periods']}개\n";

        return $text;
    }

    /**
     * 한 줄 요약 생성
     */
    private function getOneLinerSummary(array $analyzed): string {
        $group = $analyzed['period']['primary_group'];
        $score = $analyzed['period']['score'];
        $isYong = $analyzed['period']['is_yongshin'];
        $isGi = $analyzed['period']['is_gishin'];
        $isStrong = $this->wongukContext['isStrong'];

        $summaries = [
            'jaesung' => [
                'strong' => $score >= 60
                    ? '강한 일간이 재물을 다스려, 경제적 풍요와 사업 성공이 기대됩니다.'
                    : '재물의 압박이 생길 수 있으니, 안정적 재무 전략이 필요합니다.',
                'weak' => $score >= 60
                    ? '재물 운이 따르지만, 과욕을 자제하고 실력 기반의 수입에 집중하세요.'
                    : '재물의 부담이 크니, 무리한 투자를 피하고 안정을 추구하세요.',
            ],
            'gwansung' => [
                'strong' => $score >= 60
                    ? '사회적 인정과 직위 향상이 기대됩니다. 리더십을 발휘할 기회입니다.'
                    : '사회적 압박이 크지만, 이를 성장의 기회로 삼을 수 있습니다.',
                'weak' => $score >= 60
                    ? '외부의 도움으로 직업적 안정을 얻을 수 있는 시기입니다.'
                    : '관성의 압박이 강하니, 건강 관리와 스트레스 해소에 집중하세요.',
            ],
            'siksang' => [
                'strong' => $score >= 60
                    ? '창의력과 표현력이 빛나며, 새로운 분야 개척에 최적기입니다.'
                    : '에너지 발산이 과해질 수 있으니, 집중과 선택이 필요합니다.',
                'weak' => $score >= 60
                    ? '재능 발휘의 기회가 있지만, 에너지 관리가 중요합니다.'
                    : '무리한 활동을 자제하고, 내면의 힘을 먼저 기르세요.',
            ],
            'insung' => [
                'strong' => $score >= 60
                    ? '학문과 지혜가 깊어지며, 내면의 성장이 빛나는 시기입니다.'
                    : '지나친 이론 편향을 주의하고, 실천적 활동과 균형을 맞추세요.',
                'weak' => $score >= 60
                    ? '귀인의 도움과 학문적 성장이 일간을 강화합니다. 최고의 자기계발 시기입니다.'
                    : '배움의 기회를 놓치지 말고, 실력 향상에 매진하세요.',
            ],
            'bigyeop' => [
                'strong' => $score >= 60
                    ? '동료와 활발한 교류가 이루어지지만, 경쟁에 지혜롭게 대처하세요.'
                    : '자아가 과도하게 강해져, 충돌을 주의해야 합니다. 양보와 협력이 핵심입니다.',
                'weak' => $score >= 60
                    ? '동료·형제의 도움으로 자신감이 회복되고, 함께하는 일에서 성과를 냅니다.'
                    : '도움을 청하되, 보증이나 재물 대여에는 신중하세요.',
            ],
        ];

        $strengthKey = $isStrong ? 'strong' : 'weak';
        return $summaries[$group][$strengthKey] ?? '이 시기의 흐름을 주의 깊게 살펴보세요.';
    }

    // ========================================================
    // 통계 정보
    // ========================================================

    private function buildStatistics(array $analyzedPeriods): array {
        $totalSentences = 0;
        $totalChars = 0;
        $categoryStats = [];

        foreach ($analyzedPeriods as $ap) {
            foreach (self::INTERPRETATION_CATEGORIES as $cat) {
                $interp = $ap['interpretations'][$cat];
                $totalSentences += $interp['sentence_count'];
                $totalChars += mb_strlen($interp['full_text']);
                $categoryStats[$cat] = ($categoryStats[$cat] ?? 0) + $interp['sentence_count'];
            }
            $totalChars += mb_strlen($ap['narrative']);
        }

        return [
            'total_periods'       => count($analyzedPeriods),
            'total_sentences'     => $totalSentences,
            'total_characters'    => $totalChars,
            'sentences_by_category' => $categoryStats,
            'interpretation_data' => $this->interpData->getStatistics(),
        ];
    }
}
