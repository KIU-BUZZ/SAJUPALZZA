<?php
/**
 * ================================================================
 * 원국+대운 조합 해석 데이터 허브 (DaeunCombinationData) — 5단계 데이터
 * ================================================================
 * 
 * DaeunCombinationEngine이 사용하는 모든 해석 데이터를 관리합니다.
 * 
 * [데이터 계층]
 * ┌─────────────────────────────────────────────────────┐
 * │ Layer 1: 원국 패턴 기본 해석 (20종 × 4영역)        │
 * │ Layer 2: 대운 패턴 기본 해석 (15종 × 4영역)        │
 * │ Layer 3: 조합 상호작용 해석 (20×15 × 4영역 = 핵심) │
 * │ Layer 4: 컨텍스트 보정 (12운성/합충/용신)          │
 * │ Layer 5: 내러티브 템플릿 (도입/전환/마무리)        │
 * └─────────────────────────────────────────────────────┘
 * 
 * [데이터 파일 구조]
 * combination_data/
 *   ├── wonguk_patterns.php         — 20 원국 패턴 정의 + 해석
 *   ├── daeun_patterns.php          — 15 대운 패턴 정의 + 해석
 *   ├── combo_interactions_1.php    — 조합 상호작용 1부 (10 원국)
 *   ├── combo_interactions_2.php    — 조합 상호작용 2부 (10 원국)
 *   └── context_modifiers.php       — 12운성/합충/용신/내러티브
 * 
 * [총 해석 데이터]
 * - 원국 기본 해석: 20 × 4 = 80 문장
 * - 대운 기본 해석: 15 × 4 = 60 문장
 * - 조합 상호작용: 20 × 5그룹 × 4 = 400+ 핵심 문장
 *                  + 과다/흐름 변형 200+ 문장
 * - 컨텍스트 보정: 150+ 문장
 * - 내러티브 템플릿: 50+ 문장
 * ─────────────────────────────────
 * 총계: 940+ 고유 해석 문장
 */

class DaeunCombinationData {

    // ========================================================
    // 상수
    // ========================================================

    /** 해석 4대 영역 */
    const CATEGORIES = ['career', 'wealth', 'relationship', 'life_flow'];

    /** 대운 패턴 → 그룹 매핑 */
    const DAEUN_TO_GROUP = [
        'jaesung_daeun'           => 'jaesung',
        'jaesung_gwada_daeun'     => 'jaesung',
        'siksang_saengjae_daeun'  => 'jaesung',  // 식상→재성 흐름
        'gwansung_daeun'          => 'gwansung',
        'gwansung_gwada_daeun'    => 'gwansung',
        'jae_saenggwan_daeun'     => 'gwansung',  // 재성→관성 흐름
        'siksang_daeun'           => 'siksang',
        'siksang_gwada_daeun'     => 'siksang',
        'bi_saengsik_daeun'       => 'siksang',   // 비겁→식상 흐름
        'insung_daeun'            => 'insung',
        'insung_gwada_daeun'      => 'insung',
        'gwan_saengin_daeun'      => 'insung',    // 관성→인성 흐름
        'bigyeop_daeun'           => 'bigyeop',
        'bigyeop_gwada_daeun'     => 'bigyeop',
        'in_saengbi_daeun'        => 'bigyeop',   // 인성→비겁 흐름
    ];

    /** 대운 패턴 유형 분류 */
    const DAEUN_VARIANT = [
        'jaesung_daeun'           => 'basic',
        'jaesung_gwada_daeun'     => 'gwada',
        'siksang_saengjae_daeun'  => 'flow',
        'gwansung_daeun'          => 'basic',
        'gwansung_gwada_daeun'    => 'gwada',
        'jae_saenggwan_daeun'     => 'flow',
        'siksang_daeun'           => 'basic',
        'siksang_gwada_daeun'     => 'gwada',
        'bi_saengsik_daeun'       => 'flow',
        'insung_daeun'            => 'basic',
        'insung_gwada_daeun'      => 'gwada',
        'gwan_saengin_daeun'      => 'flow',
        'bigyeop_daeun'           => 'basic',
        'bigyeop_gwada_daeun'     => 'gwada',
        'in_saengbi_daeun'        => 'flow',
    ];

    // ========================================================
    // 데이터 저장소
    // ========================================================
    private static $wongukData = [];
    private static $daeunData = [];
    private static $comboData = [];
    private static $contextData = [];
    private static $loaded = false;

    // ========================================================
    // 데이터 로딩
    // ========================================================

    private static function ensureLoaded(): void {
        if (self::$loaded) return;

        $dir = __DIR__ . '/combination_data';

        // 원국 패턴 데이터
        $path = $dir . '/wonguk_patterns.php';
        if (file_exists($path)) self::$wongukData = require $path;

        // 대운 패턴 데이터
        $path = $dir . '/daeun_patterns.php';
        if (file_exists($path)) self::$daeunData = require $path;

        // 조합 상호작용 데이터 (분할 로드)
        foreach (['combo_interactions_1', 'combo_interactions_2'] as $file) {
            $path = $dir . '/' . $file . '.php';
            if (file_exists($path)) {
                $data = require $path;
                if (is_array($data)) {
                    self::$comboData = array_merge(self::$comboData, $data);
                }
            }
        }

        // 컨텍스트 보정 데이터
        $path = $dir . '/context_modifiers.php';
        if (file_exists($path)) self::$contextData = require $path;

        self::$loaded = true;
    }

    // ========================================================
    // 원국 패턴 API
    // ========================================================

    /**
     * 원국 패턴 한글명 반환
     */
    public static function getWongukPatternName(string $patternId): string {
        self::ensureLoaded();
        return self::$wongukData[$patternId]['name'] ?? '기본 구조';
    }

    /**
     * 원국 패턴 설명 반환
     */
    public static function getWongukDescription(string $patternId): string {
        self::ensureLoaded();
        return self::$wongukData[$patternId]['description'] ?? '';
    }

    /**
     * 원국 패턴 기본 해석 (Layer 1)
     */
    public static function getWongukInterpretation(string $patternId, string $category): string {
        self::ensureLoaded();
        return self::$wongukData[$patternId]['interpretation'][$category] ?? '';
    }

    // ========================================================
    // 대운 패턴 API
    // ========================================================

    /**
     * 대운 패턴 한글명 반환
     */
    public static function getDaeunPatternName(string $patternId): string {
        self::ensureLoaded();
        return self::$daeunData[$patternId]['name'] ?? '기본 대운';
    }

    /**
     * 대운 패턴 기본 해석 (Layer 2)
     */
    public static function getDaeunInterpretation(string $patternId, string $category): string {
        self::ensureLoaded();
        return self::$daeunData[$patternId]['interpretation'][$category] ?? '';
    }

    // ========================================================
    // 조합 상호작용 API (핵심!)
    // ========================================================

    /**
     * 원국 × 대운 조합 상호작용 해석 (Layer 3)
     * 
     * 검색 순서:
     * 1. 특정 대운 패턴 ID로 검색 (가장 정밀)
     * 2. 대운 그룹으로 폴백 (범용)
     * 3. 제네릭 폴백
     */
    public static function getCombinationInteraction(string $wongukPattern, string $daeunPattern, string $category): string {
        self::ensureLoaded();

        $wongukCombo = self::$comboData[$wongukPattern] ?? [];

        // 1차: 특정 대운 패턴 ID 검색
        if (isset($wongukCombo[$daeunPattern][$category])) {
            return $wongukCombo[$daeunPattern][$category];
        }

        // 2차: 대운 그룹 폴백
        $group = self::DAEUN_TO_GROUP[$daeunPattern] ?? '';
        if ($group && isset($wongukCombo[$group][$category])) {
            $baseText = $wongukCombo[$group][$category];
            // 변형 유형에 따른 접미 보정
            $variant = self::DAEUN_VARIANT[$daeunPattern] ?? 'basic';
            $suffix = self::getVariantSuffix($variant, $group, $category);
            return trim($baseText . ' ' . $suffix);
        }

        // 3차: 제네릭 폴백
        return self::getGenericInteraction($daeunPattern, $category);
    }

    /**
     * 변형 유형(gwada/flow)에 따른 보정 접미사
     */
    private static function getVariantSuffix(string $variant, string $group, string $category): string {
        if ($variant === 'basic') return '';

        $suffixes = self::$contextData['variant_suffixes'] ?? [];
        return $suffixes[$variant][$group][$category] ?? '';
    }

    /**
     * 제네릭 폴백 — 조합 데이터가 없을 때
     */
    private static function getGenericInteraction(string $daeunPattern, string $category): string {
        $group = self::DAEUN_TO_GROUP[$daeunPattern] ?? 'bigyeop';
        $generic = self::$contextData['generic_interactions'] ?? [];
        return $generic[$group][$category] ?? '';
    }

    // ========================================================
    // 컨텍스트 보정 API (Layer 4)
    // ========================================================

    /**
     * 용신 대운 보정 텍스트
     */
    public static function getYongshinModifier(string $category): string {
        self::ensureLoaded();
        $mods = self::$contextData['yongshin'] ?? [];
        return $mods[$category] ?? '';
    }

    /**
     * 12운성 보정 텍스트
     */
    public static function getTwelveStageModifier(string $stage, string $category): string {
        self::ensureLoaded();
        $stages = self::$contextData['twelve_stages'] ?? [];
        return $stages[$stage][$category] ?? '';
    }

    /**
     * 합충형파해 보정 텍스트
     */
    public static function getRelationshipModifier(string $type, string $category): string {
        self::ensureLoaded();
        $rels = self::$contextData['relationships'] ?? [];
        return $rels[$type][$category] ?? '';
    }

    // ========================================================
    // 내러티브 API (Layer 5)
    // ========================================================

    /**
     * 도입 텍스트
     */
    public static function getIntroText(string $level, int $age): string {
        self::ensureLoaded();
        $intros = self::$contextData['intro_texts'] ?? [];

        // 나이대별 그룹
        $ageGroup = 'middle';
        if ($age < 30) $ageGroup = 'youth';
        elseif ($age < 55) $ageGroup = 'middle';
        else $ageGroup = 'senior';

        // 레벨 × 나이대 조합 검색
        $key = $level . '_' . $ageGroup;
        if (isset($intros[$key])) return $intros[$key];

        // 레벨만으로 폴백
        return $intros[$level] ?? '이 시기의 대운 흐름을 살펴보겠습니다.';
    }

    /**
     * 대운 전환 조언
     */
    public static function getTransitionAdvice(string $fromPattern, string $toPattern): string {
        self::ensureLoaded();
        $advice = self::$contextData['transition_advice'] ?? [];

        // 대운 그룹으로 매핑
        $fromGroup = self::DAEUN_TO_GROUP[$fromPattern] ?? 'bigyeop';
        $toGroup = self::DAEUN_TO_GROUP[$toPattern] ?? 'bigyeop';
        $key = $fromGroup . '_to_' . $toGroup;

        if (isset($advice[$key])) return $advice[$key];

        // 동일 그룹 전환
        if ($fromGroup === $toGroup) {
            return $advice['same_group'] ?? '같은 기운의 대운이 이어지므로, 이전 시기의 흐름을 이어받아 안정적으로 대응하시기 바랍니다.';
        }

        return $advice['default'] ?? '대운의 전환기이므로, 새로운 환경에 적응하면서 유연하게 대처하는 자세가 필요합니다.';
    }

    // ========================================================
    // 통계 및 디버그 API
    // ========================================================

    /**
     * 전체 통계 정보
     */
    public static function getStatistics(): array {
        self::ensureLoaded();

        $stats = [
            'wonguk_patterns' => count(self::$wongukData),
            'daeun_patterns'  => count(self::$daeunData),
            'combo_wonguk_keys' => count(self::$comboData),
            'combo_total_entries' => 0,
            'context_keys' => count(self::$contextData),
            'total_sentences' => 0,
        ];

        // 원국 해석 문장 수
        $wongukSentences = 0;
        foreach (self::$wongukData as $p) {
            $wongukSentences += count($p['interpretation'] ?? []);
        }

        // 대운 해석 문장 수
        $daeunSentences = 0;
        foreach (self::$daeunData as $d) {
            $daeunSentences += count($d['interpretation'] ?? []);
        }

        // 조합 문장 수
        $comboSentences = 0;
        foreach (self::$comboData as $wonguk => $daeunMap) {
            foreach ($daeunMap as $daeun => $cats) {
                $comboSentences += count($cats);
                $stats['combo_total_entries']++;
            }
        }

        // 컨텍스트 문장 수
        $contextSentences = 0;
        foreach (self::$contextData as $key => $section) {
            if (is_array($section)) {
                $contextSentences += self::countDeep($section);
            }
        }

        $stats['wonguk_sentences'] = $wongukSentences;
        $stats['daeun_sentences'] = $daeunSentences;
        $stats['combo_sentences'] = $comboSentences;
        $stats['context_sentences'] = $contextSentences;
        $stats['total_sentences'] = $wongukSentences + $daeunSentences + $comboSentences + $contextSentences;

        return $stats;
    }

    /**
     * 사용 가능한 원국 패턴 ID 목록
     */
    public static function getAvailableWongukPatterns(): array {
        self::ensureLoaded();
        return array_keys(self::$wongukData);
    }

    /**
     * 사용 가능한 대운 패턴 ID 목록
     */
    public static function getAvailableDaeunPatterns(): array {
        self::ensureLoaded();
        return array_keys(self::$daeunData);
    }

    /**
     * 특정 원국 패턴의 조합 데이터 유무 확인
     */
    public static function hasComboData(string $wongukPattern): bool {
        self::ensureLoaded();
        return isset(self::$comboData[$wongukPattern]);
    }

    /**
     * 데이터 캐시 초기화 (테스트용)
     */
    public static function resetCache(): void {
        self::$wongukData = [];
        self::$daeunData = [];
        self::$comboData = [];
        self::$contextData = [];
        self::$loaded = false;
    }

    // ========================================================
    // 내부 유틸
    // ========================================================

    /**
     * 중첩 배열의 리프(string) 개수를 재귀적으로 카운트
     */
    private static function countDeep(array $arr): int {
        $count = 0;
        foreach ($arr as $val) {
            if (is_array($val)) {
                $count += self::countDeep($val);
            } elseif (is_string($val) && mb_strlen($val) > 0) {
                $count++;
            }
        }
        return $count;
    }
}
