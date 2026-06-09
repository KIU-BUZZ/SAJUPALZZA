<?php
/**
 * ================================================================
 * 대운 해석 데이터 시스템 (DaeunInterpretationData) — 4단계 데이터 허브
 * ================================================================
 * 
 * 5개 대운 유형(재성·관성·식상·인성·비겁) × 4개 해석 영역의
 * 방대한 해석 문장을 관리하고, 컨텍스트에 따라 필터링합니다.
 * 
 * [데이터 구조]
 * daeun_data/
 *   ├── jaesung_daeun.php   — 재성(財星) 대운
 *   ├── gwansung_daeun.php  — 관성(官星) 대운
 *   ├── siksang_daeun.php   — 식상(食傷) 대운
 *   ├── insung_daeun.php    — 인성(印星) 대운
 *   └── bigyeop_daeun.php   — 비겁(比劫) 대운
 * 
 * [사용법]
 *   $data = new DaeunInterpretationData();
 *   $sentences = $data->getFilteredSentences('jaesung', 'wealth_change', $context);
 */

class DaeunInterpretationData {

    /** 해석 영역 */
    const CATEGORIES = ['life_change', 'career_change', 'relationship_change', 'wealth_change'];

    /** 대운 유형 */
    const DAEUN_TYPES = ['jaesung', 'gwansung', 'siksang', 'insung', 'bigyeop'];

    /** 데이터 저장소 */
    private static $data = [];
    private static $loaded = false;

    // ========================================================
    // 생성자
    // ========================================================

    public function __construct() {
        if (!self::$loaded) {
            self::loadAll();
        }
    }

    // ========================================================
    // 데이터 로딩
    // ========================================================

    private static function loadAll(): void {
        $dataDir = __DIR__ . '/daeun_data';
        $files = [
            'jaesung_daeun',
            'gwansung_daeun',
            'siksang_daeun',
            'insung_daeun',
            'bigyeop_daeun',
        ];

        foreach ($files as $file) {
            $path = $dataDir . '/' . $file . '.php';
            if (file_exists($path)) {
                $fileData = require $path;
                if (is_array($fileData)) {
                    self::$data = array_merge(self::$data, $fileData);
                }
            }
        }

        self::$loaded = true;
    }

    // ========================================================
    // 공개 API
    // ========================================================

    /**
     * 대운 유형(그룹)별 필터링된 해석 문장 가져오기
     * 
     * @param string $daeunType   대운 유형 (jaesung, gwansung, siksang, insung, bigyeop)
     * @param string $category    해석 영역 (life_change, career_change, etc.)
     * @param array  $context     필터 컨텍스트 [strength, gender, age_group, is_pure]
     * @return array 문장 배열
     */
    public function getFilteredSentences(string $daeunType, string $category, array $context = []): array {
        $typeData = self::$data[$daeunType] ?? [];
        $sentences = $typeData[$category] ?? [];
        if (empty($sentences)) return [];

        return $this->filterSentences($sentences, $context);
    }

    /**
     * 세부 유형(편재/정재 등)별 해석 문장 가져오기
     */
    public function getSubtypeSentences(string $subtype, string $category, array $context = []): array {
        $subtypeData = self::$data['subtype_' . $subtype] ?? [];
        $sentences = $subtypeData[$category] ?? [];
        if (empty($sentences)) return [];

        return $this->filterSentences($sentences, $context);
    }

    /**
     * 신강/신약 × 대운유형 교차 해석 가져오기
     */
    public function getCrossSentences(string $daeunType, string $strength, string $category, array $context = []): array {
        $crossKey = 'cross_' . $daeunType . '_' . $strength;
        $crossData = self::$data[$crossKey] ?? [];
        $sentences = $crossData[$category] ?? [];
        if (empty($sentences)) return [];

        return $this->filterSentences($sentences, $context);
    }

    /**
     * 전체 원시 데이터 반환
     */
    public function getRawData(): array {
        return self::$data;
    }

    /**
     * 사용 가능한 데이터 키 목록
     */
    public function getAvailableKeys(): array {
        return array_keys(self::$data);
    }

    /**
     * 통계 정보
     */
    public function getStatistics(): array {
        $totalSentences = 0;
        $byType = [];
        $byCategory = [];

        foreach (self::$data as $key => $typeData) {
            $typeSentences = 0;
            foreach (self::CATEGORIES as $cat) {
                $count = count($typeData[$cat] ?? []);
                $typeSentences += $count;
                $byCategory[$cat] = ($byCategory[$cat] ?? 0) + $count;
            }
            $byType[$key] = $typeSentences;
            $totalSentences += $typeSentences;
        }

        return [
            'total_sentences' => $totalSentences,
            'total_keys'      => count(self::$data),
            'by_type'         => $byType,
            'by_category'     => $byCategory,
        ];
    }

    /**
     * 전체 문장 수 카운팅
     */
    public function countAllSentences(): int {
        $total = 0;
        foreach (self::$data as $typeData) {
            foreach (self::CATEGORIES as $cat) {
                $total += count($typeData[$cat] ?? []);
            }
        }
        return $total;
    }

    // ========================================================
    // 내부: 문장 필터링
    // ========================================================

    /**
     * 컨텍스트에 따라 문장 필터링
     */
    private function filterSentences(array $sentences, array $context): array {
        if (empty($context)) {
            // 컨텍스트 없으면 text만 추출
            return array_map(fn($s) => is_array($s) ? ($s['text'] ?? '') : $s, $sentences);
        }

        $strength = $context['strength'] ?? null;
        $gender   = $context['gender'] ?? null;
        $ageGroup = $context['age_group'] ?? null;

        $result = [];
        foreach ($sentences as $s) {
            if (is_string($s)) {
                $result[] = $s;
                continue;
            }
            if (!is_array($s) || empty($s['text'])) continue;

            // 신강/신약 필터
            if ($strength && !empty($s['strength']) && $s['strength'] !== 'all') {
                if ($s['strength'] !== $strength) continue;
            }

            // 성별 필터
            if ($gender && !empty($s['gender']) && $s['gender'] !== 'all') {
                if ($s['gender'] !== $gender) continue;
            }

            // 나이대 필터
            if ($ageGroup && !empty($s['age']) && $s['age'] !== 'all') {
                if ($s['age'] !== $ageGroup) continue;
            }

            $result[] = $s['text'];
        }

        return $result;
    }
}
