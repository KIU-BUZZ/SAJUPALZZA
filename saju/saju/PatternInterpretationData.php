<?php
/**
 * ================================================================
 * 패턴 해석 데이터 시스템 (PatternInterpretationData) — 2단계 모듈
 * ================================================================
 * 
 * PatternDetector가 감지한 패턴에 대해
 * 6대 카테고리별 풍부한 해석 문장을 제공하는 데이터 시스템입니다.
 * 
 * [해석 카테고리]
 * 1. personality — 성격·기질·내면 특성
 * 2. talent      — 재능·적성·강점
 * 3. career      — 직업·사회생활·진로
 * 4. wealth      — 재물·금전운·경제활동
 * 5. relationship — 대인관계·연애·결혼·가족
 * 6. life_flow   — 인생흐름·운세변화·생애주기
 * 
 * [설계 원칙]
 * 1. 분리된 데이터 파일 — 패턴 그룹별 별도 파일로 관리
 * 2. 조건부 해석 — intensity/성별/신강신약에 따른 문장 필터링
 * 3. 무한 확장 — 새 패턴 해석 데이터를 파일 추가로 등록
 * 4. 다양성 보장 — 패턴당 최소 30~60개 해석 문장
 * 
 * [사용법]
 *   $interpData = new PatternInterpretationData();
 *   $data = $interpData->getInterpretation('jaeda_sinyak');
 *   // → ['personality' => [...], 'talent' => [...], ...]
 * 
 *   $filtered = $interpData->getFilteredInterpretation('jaeda_sinyak', [
 *       'intensity' => 85,
 *       'gender'    => 'male',
 *       'isStrong'  => false,
 *   ]);
 */

class PatternInterpretationData {

    // ========================================================
    // 해석 카테고리 상수
    // ========================================================
    const CAT_PERSONALITY  = 'personality';
    const CAT_TALENT       = 'talent';
    const CAT_CAREER       = 'career';
    const CAT_WEALTH       = 'wealth';
    const CAT_RELATIONSHIP = 'relationship';
    const CAT_LIFE_FLOW    = 'life_flow';

    const ALL_CATEGORIES = [
        self::CAT_PERSONALITY,
        self::CAT_TALENT,
        self::CAT_CAREER,
        self::CAT_WEALTH,
        self::CAT_RELATIONSHIP,
        self::CAT_LIFE_FLOW,
    ];

    /** 카테고리 한글명 */
    const CATEGORY_LABELS = [
        'personality'  => '성격·기질',
        'talent'       => '재능·적성',
        'career'       => '직업·진로',
        'wealth'       => '재물·금전',
        'relationship' => '대인관계·연애',
        'life_flow'    => '인생흐름',
    ];

    // ========================================================
    // 데이터 저장소
    // ========================================================
    private static $data = [];
    private static $loaded = false;

    // ========================================================
    // 생성자
    // ========================================================
    public function __construct() {
        self::loadAll();
    }

    // ========================================================
    // 공개 API
    // ========================================================

    /**
     * 특정 패턴의 전체 해석 데이터를 반환
     * 
     * @param string $patternId 패턴 ID
     * @return array|null ['personality'=>[...], 'talent'=>[...], ...] 또는 null
     */
    public function getInterpretation(string $patternId): ?array {
        return self::$data[$patternId] ?? null;
    }

    /**
     * 특정 패턴의 특정 카테고리 해석만 반환
     */
    public function getCategoryInterpretation(string $patternId, string $category): array {
        return self::$data[$patternId][$category] ?? [];
    }

    /**
     * 조건에 따라 필터링된 해석 반환
     * 
     * @param string $patternId
     * @param array $context [
     *   'intensity' => int (0~100),
     *   'gender'    => 'male'|'female',
     *   'isStrong'  => bool,
     * ]
     * @return array 필터링된 해석 데이터
     */
    public function getFilteredInterpretation(string $patternId, array $context = []): array {
        $raw = $this->getInterpretation($patternId);
        if (!$raw) return [];

        $intensity = $context['intensity'] ?? 50;
        $gender    = $context['gender'] ?? null;
        $isStrong  = $context['isStrong'] ?? null;

        $result = [];
        foreach (self::ALL_CATEGORIES as $cat) {
            $sentences = $raw[$cat] ?? [];
            $filtered = [];
            foreach ($sentences as $item) {
                // 문장이 배열인 경우 (조건부 문장)
                if (is_array($item)) {
                    // intensity 범위 체크
                    if (isset($item['min_intensity']) && $intensity < $item['min_intensity']) continue;
                    if (isset($item['max_intensity']) && $intensity > $item['max_intensity']) continue;
                    // 성별 체크
                    if (isset($item['gender']) && $gender !== null && $item['gender'] !== $gender) continue;
                    // 신강/신약 체크
                    if (isset($item['is_strong']) && $isStrong !== null && $item['is_strong'] !== $isStrong) continue;
                    $filtered[] = $item['text'];
                } else {
                    // 단순 문자열 — 항상 포함
                    $filtered[] = $item;
                }
            }
            $result[$cat] = $filtered;
        }

        return $result;
    }

    /**
     * 여러 패턴의 해석을 한 번에 가져와 카테고리별로 병합
     * 
     * @param array $patterns [['id'=>..., 'intensity'=>..., ...], ...]
     * @param array $context  ['gender'=>..., 'isStrong'=>...]
     * @return array ['personality'=>[...], 'talent'=>[...], ...]
     */
    public function getMergedInterpretations(array $patterns, array $context = []): array {
        $merged = [];
        foreach (self::ALL_CATEGORIES as $cat) {
            $merged[$cat] = [];
        }

        foreach ($patterns as $p) {
            $patternId = is_string($p) ? $p : ($p['id'] ?? '');
            $intensity = is_array($p) ? ($p['intensity'] ?? 50) : 50;

            $ctx = array_merge($context, ['intensity' => $intensity]);
            $filtered = $this->getFilteredInterpretation($patternId, $ctx);

            foreach (self::ALL_CATEGORIES as $cat) {
                if (!empty($filtered[$cat])) {
                    $merged[$cat] = array_merge($merged[$cat], $filtered[$cat]);
                }
            }
        }

        return $merged;
    }

    /**
     * 등록된 해석 데이터가 있는 패턴 ID 목록
     */
    public function getAvailablePatterns(): array {
        return array_keys(self::$data);
    }

    /**
     * 특정 패턴의 해석 문장 총 개수
     */
    public function countSentences(string $patternId): int {
        $data = self::$data[$patternId] ?? [];
        $count = 0;
        foreach ($data as $cat => $sentences) {
            $count += count($sentences);
        }
        return $count;
    }

    /**
     * 전체 패턴의 총 해석 문장 수
     */
    public function countAllSentences(): int {
        $total = 0;
        foreach (self::$data as $patternId => $cats) {
            foreach ($cats as $sentences) {
                $total += count($sentences);
            }
        }
        return $total;
    }

    /**
     * 통계 요약 반환
     */
    public function getStatistics(): array {
        $stats = [
            'total_patterns'   => count(self::$data),
            'total_sentences'  => $this->countAllSentences(),
            'by_pattern'       => [],
            'by_category'      => array_fill_keys(self::ALL_CATEGORIES, 0),
        ];
        foreach (self::$data as $patternId => $cats) {
            $patternTotal = 0;
            foreach ($cats as $cat => $sentences) {
                $c = count($sentences);
                $patternTotal += $c;
                if (isset($stats['by_category'][$cat])) {
                    $stats['by_category'][$cat] += $c;
                }
            }
            $stats['by_pattern'][$patternId] = $patternTotal;
        }
        return $stats;
    }

    // ========================================================
    // 데이터 등록 API (외부 확장용)
    // ========================================================

    /**
     * 패턴 해석 데이터를 등록 (기존 데이터는 병합)
     */
    public static function registerData(string $patternId, array $data): void {
        if (!isset(self::$data[$patternId])) {
            self::$data[$patternId] = [];
        }
        foreach (self::ALL_CATEGORIES as $cat) {
            if (isset($data[$cat])) {
                if (!isset(self::$data[$patternId][$cat])) {
                    self::$data[$patternId][$cat] = [];
                }
                self::$data[$patternId][$cat] = array_merge(
                    self::$data[$patternId][$cat],
                    $data[$cat]
                );
            }
        }
    }

    /**
     * 여러 패턴 데이터를 일괄 등록
     */
    public static function registerBulk(array $bulkData): void {
        foreach ($bulkData as $patternId => $data) {
            self::registerData($patternId, $data);
        }
    }

    // ========================================================
    // 데이터 로딩
    // ========================================================

    private static function loadAll(): void {
        if (self::$loaded) return;
        self::$loaded = true;

        $dataDir = __DIR__ . '/interpretation_data';

        // 데이터 파일 목록 — 추가 시 여기에 등록
        $dataFiles = [
            'strength_patterns.php',
            'sipsin_patterns.php',
            'flow_patterns.php',
            'structure_patterns.php',
            'special_patterns.php',
            'balance_patterns.php',
            'composite_patterns.php',
            'gender_patterns.php',
        ];

        foreach ($dataFiles as $file) {
            $path = $dataDir . '/' . $file;
            if (file_exists($path)) {
                $data = require $path;
                if (is_array($data)) {
                    self::registerBulk($data);
                }
            }
        }
    }

    /**
     * 강제 리로드 (테스트용)
     */
    public static function reload(): void {
        self::$data = [];
        self::$loaded = false;
    }
}
