<?php
/**
 * ========================================================
 * 원국(原局) + 대운(大運) 조합 해석 시스템
 * ========================================================
 * 
 * 원국 사주 패턴과 대운 패턴의 조합을 기반으로
 * 직업·재물·대인관계·인생흐름 4대 영역의
 * 심층 해석을 제공하는 핵심 엔진
 * 
 * [기능]
 * - 원국 패턴 감지 (20가지)
 * - 대운 패턴 감지 (15가지)  
 * - 300+ 조합 패턴 매칭
 * - 3000+ 해석 문장 생성
 * - 스토리텔링 내러티브 생성
 * - 향후 30년 대운 흐름 분석
 */

require_once __DIR__ . '/DaeunCombinationData.php';

class DaeunCombinationEngine {

    // 십성 → 그룹 매핑
    const SIPSIN_GROUPS = [
        '비견' => '비겁', '겁재' => '비겁',
        '식신' => '식상', '상관' => '식상',
        '편재' => '재성', '정재' => '재성',
        '편관' => '관성', '정관' => '관성',
        '편인' => '인성', '정인' => '인성',
    ];

    // 그룹 한자 매핑
    const GROUP_HANJA = [
        '비겁' => '比劫', '식상' => '食傷',
        '재성' => '財星', '관성' => '官星', '인성' => '印星',
    ];

    private $engine;
    private $result;
    private $wongukPatterns = [];
    private $sipsinGroups = [];
    private $isStrong;
    private $dms;
    private $gender;

    // ============================================================
    // 생성자
    // ============================================================
    public function __construct($engine, $result) {
        $this->engine = $engine;
        $this->result = $result;
        $this->dms = $result['day_master_strength'];
        $this->isStrong = $this->dms['is_strong'];
        $this->gender = $engine->getGender();
        $this->calculateSipsinGroups();
        $this->detectWongukPatterns();
    }

    // ============================================================
    // 십성 그룹 합산
    // ============================================================
    private function calculateSipsinGroups() {
        $dist = $this->result['sipsin_full']['distribution'];
        $this->sipsinGroups = [
            'bigyeop'  => ($dist['비견'] ?? 0) + ($dist['겁재'] ?? 0),
            'siksang'  => ($dist['식신'] ?? 0) + ($dist['상관'] ?? 0),
            'jaesung'  => ($dist['편재'] ?? 0) + ($dist['정재'] ?? 0),
            'gwansung' => ($dist['편관'] ?? 0) + ($dist['정관'] ?? 0),
            'insung'   => ($dist['편인'] ?? 0) + ($dist['정인'] ?? 0),
        ];
    }

    // ============================================================
    // 원국 패턴 감지 (20가지)
    // ============================================================
    private function detectWongukPatterns() {
        $g = $this->sipsinGroups;
        $dist = $this->result['sipsin_full']['distribution'];
        $s = $this->isStrong;
        $patterns = [];

        // 1. 재다신약 — 재성 강 + 신약
        if ($g['jaesung'] >= 2.5 && !$s) $patterns[] = 'jaeda_sinyak';

        // 2. 식상생재 — 식상+재성 둘 다 강
        if ($g['siksang'] >= 2.0 && $g['jaesung'] >= 2.0) $patterns[] = 'siksang_saengjae';

        // 3. 관인상생 — 관성+인성 둘 다 강
        if ($g['gwansung'] >= 2.0 && $g['insung'] >= 1.5) $patterns[] = 'gwanin_sangsaeng';

        // 4. 상관견관 — 상관과 관성의 충돌
        if (($dist['상관'] ?? 0) >= 1.0 && $g['gwansung'] >= 1.5) $patterns[] = 'sanggwan_gyeongwan';

        // 5. 비겁과다 — 비겁 매우 강
        if ($g['bigyeop'] >= 3.0) $patterns[] = 'bigyeop_gwada';

        // 6. 인수과다 — 인성 매우 강
        if ($g['insung'] >= 3.0) $patterns[] = 'insu_gwada';

        // 7. 식상과다 — 식상 매우 강
        if ($g['siksang'] >= 3.0) $patterns[] = 'siksang_gwada';

        // 8. 재성과다 — 재성 매우 강
        if ($g['jaesung'] >= 3.0) $patterns[] = 'jaesung_gwada';

        // 9. 관성과다 — 관성 매우 강
        if ($g['gwansung'] >= 3.0) $patterns[] = 'gwansung_gwada';

        // 10. 신강무관 — 신강한데 관성 없음
        if ($s && $g['gwansung'] <= 0.5) $patterns[] = 'singang_mugwan';

        // 11. 신약무인 — 신약한데 인성 없음
        if (!$s && $g['insung'] <= 0.5) $patterns[] = 'sinyak_muin';

        // 12. 식신제살 — 식신으로 편관(칠살) 제어
        if (($dist['식신'] ?? 0) >= 1.5 && ($dist['편관'] ?? 0) >= 1.5) $patterns[] = 'siksin_jesal';

        // 13. 비겁탈재 — 비겁 강 + 재성 약
        if ($g['bigyeop'] >= 2.0 && $g['jaesung'] <= 1.0) $patterns[] = 'bigyeop_taljae';

        // 14. 재다파인 — 재성 강 + 인성 약
        if ($g['jaesung'] >= 2.0 && $g['insung'] <= 0.5) $patterns[] = 'jaeda_pain';

        // 15. 인비상생 — 인성+비겁 상생
        if ($g['insung'] >= 2.0 && $g['bigyeop'] >= 1.5) $patterns[] = 'inbi_sangsaeng';

        // 16. 재관쌍미 — 재성+관성 동시 좋음
        if ($g['jaesung'] >= 1.5 && $g['gwansung'] >= 1.5) $patterns[] = 'jaegwan_ssangmi';

        // 17. 살인상생 — 편관+인성 상생
        if (($dist['편관'] ?? 0) >= 1.0 && $g['insung'] >= 1.5) $patterns[] = 'salin_sangsaeng';

        // 18. 신강용재 — 신강해서 재성으로 설기
        if ($s && $g['jaesung'] >= 1.5) $patterns[] = 'singang_yongjae';

        // 19. 신약용인 — 신약해서 인성으로 보강
        if (!$s && $g['insung'] >= 1.5) $patterns[] = 'sinyak_yongin';

        // 20. 조화균형 — 특별한 편중 없음
        if (empty($patterns)) $patterns[] = 'johwa_gyunhyeong';

        $this->wongukPatterns = array_unique($patterns);
    }

    // ============================================================
    // 대운 패턴 감지 (15가지)
    // ============================================================
    public function detectDaeunPattern($stemSipsin, $branchSipsin) {
        $stemGroup = self::SIPSIN_GROUPS[$stemSipsin] ?? '';
        $branchGroup = self::SIPSIN_GROUPS[$branchSipsin] ?? '';

        // (1) 과다 대운: 천간·지지 모두 같은 그룹
        if ($stemGroup === $branchGroup) {
            $gwadaMap = [
                '재성' => 'jaesung_gwada_daeun',
                '관성' => 'gwansung_gwada_daeun',
                '식상' => 'siksang_gwada_daeun',
                '비겁' => 'bigyeop_gwada_daeun',
                '인성' => 'insung_gwada_daeun',
            ];
            if (isset($gwadaMap[$stemGroup])) return $gwadaMap[$stemGroup];
        }

        // (2) 상생 흐름 대운: 천간·지지가 상생 관계
        $pair = [$stemGroup, $branchGroup];
        sort($pair);
        $pairKey = implode('+', $pair);
        $flowMap = [
            '비겁+식상' => 'bi_saengsik_daeun',
            '식상+재성' => 'siksang_saengjae_daeun',
            '관성+재성' => 'jae_saenggwan_daeun',
            '관성+인성' => 'gwan_saengin_daeun',
            '비겁+인성' => 'in_saengbi_daeun',
        ];
        if (isset($flowMap[$pairKey])) return $flowMap[$pairKey];

        // (3) 기본: 천간(외적 운) 기준
        $basicMap = [
            '재성' => 'jaesung_daeun',
            '관성' => 'gwansung_daeun',
            '식상' => 'siksang_daeun',
            '비겁' => 'bigyeop_daeun',
            '인성' => 'insung_daeun',
        ];
        return $basicMap[$stemGroup] ?? 'bigyeop_daeun';
    }

    // ============================================================
    // 핵심 API: 대운 기간별 조합 해석
    // ============================================================
    public function getCombinationInterpretation($daeunData) {
        $daeunPattern = $this->detectDaeunPattern(
            $daeunData['stem_sipsin'], $daeunData['branch_sipsin']
        );
        $primaryWonguk = $this->wongukPatterns[0] ?? 'johwa_gyunhyeong';

        $interp = [
            'wonguk_pattern'      => $primaryWonguk,
            'wonguk_pattern_name' => DaeunCombinationData::getWongukPatternName($primaryWonguk),
            'daeun_pattern'       => $daeunPattern,
            'daeun_pattern_name'  => DaeunCombinationData::getDaeunPatternName($daeunPattern),
            'all_wonguk_patterns' => $this->wongukPatterns,
        ];

        // 4대 영역별 해석 조합
        foreach (['career', 'wealth', 'relationship', 'life_flow'] as $cat) {
            $interp[$cat] = $this->buildCategoryText($primaryWonguk, $daeunPattern, $cat, $daeunData);
        }

        // 풀 내러티브 생성
        $interp['narrative'] = $this->generateNarrative($interp, $daeunData);

        return $interp;
    }

    // ============================================================
    // 카테고리별 텍스트 빌드
    // ============================================================
    private function buildCategoryText($wongukPattern, $daeunPattern, $category, $daeunData) {
        // Layer 1: 원국 기본 해석
        $base = DaeunCombinationData::getWongukInterpretation($wongukPattern, $category);

        // Layer 2: 대운 보정 해석
        $modifier = DaeunCombinationData::getDaeunInterpretation($daeunPattern, $category);

        // Layer 3: 조합 상호작용
        $interaction = DaeunCombinationData::getCombinationInteraction($wongukPattern, $daeunPattern, $category);

        // Layer 4: 컨텍스트 보정 (용신·12운성·합충형파해)
        $context = $this->getContextModifier($category, $daeunData);

        // 하나의 텍스트로 결합
        $parts = array_filter([$base, $modifier, $interaction, $context]);
        return implode(' ', $parts);
    }

    // ============================================================
    // 컨텍스트 보정 (용신·12운성·합충형파해)
    // ============================================================
    private function getContextModifier($category, $daeunData) {
        $mods = [];

        // 용신 보정
        if ($daeunData['is_yongshin']) {
            $m = DaeunCombinationData::getYongshinModifier($category);
            if ($m) $mods[] = $m;
        }

        // 12운성 보정
        $stageMod = DaeunCombinationData::getTwelveStageModifier($daeunData['twelve_stage'], $category);
        if ($stageMod) $mods[] = $stageMod;

        // 합충형파해 보정
        if (!empty($daeunData['relationships'])) {
            foreach ($daeunData['relationships'] as $rel) {
                $relMod = DaeunCombinationData::getRelationshipModifier($rel['type'], $category);
                if ($relMod) $mods[] = $relMod;
            }
        }

        return implode(' ', $mods);
    }

    // ============================================================
    // 스토리텔링 내러티브 생성
    // ============================================================
    private function generateNarrative($interp, $daeunData) {
        $age = $daeunData['age_start'];
        $ageEnd = $daeunData['age_end'];
        $score = $daeunData['score'];
        $level = $score >= 75 ? '대길' : ($score >= 60 ? '길' : ($score >= 45 ? '보통' : ($score >= 30 ? '소흉' : '흉')));
        $emoji = ['대길'=>'🌟','길'=>'☀️','보통'=>'☁️','소흉'=>'🌧️','흉'=>'⛈️'][$level];
        $stemSipsin = $daeunData['stem_sipsin'];
        $branchSipsin = $daeunData['branch_sipsin'];

        $text = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $text .= "  {$emoji} {$age}~{$ageEnd}세 원국+대운 심층 해석\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        $text .= "📋 원국 패턴: {$interp['wonguk_pattern_name']}\n";
        $text .= "📋 대운 패턴: {$interp['daeun_pattern_name']}\n";
        $text .= "📋 대운 천간: {$stemSipsin} | 대운 지지: {$branchSipsin}\n";
        $text .= "📋 운세 점수: {$score}점 ({$level})\n\n";

        // 도입 멘트
        $introText = DaeunCombinationData::getIntroText($level, $age);
        $text .= $introText . "\n\n";

        // 4대 영역
        $text .= "🏢 【직업·경력 운세】\n";
        $text .= $interp['career'] . "\n\n";

        $text .= "💰 【재물·금전 운세】\n";
        $text .= $interp['wealth'] . "\n\n";

        $text .= "💕 【대인관계·연애 운세】\n";
        $text .= $interp['relationship'] . "\n\n";

        $text .= "🌊 【인생 흐름·방향】\n";
        $text .= $interp['life_flow'] . "\n";

        return $text;
    }

    // ============================================================
    // 향후 30년 대운 흐름 종합 분석
    // ============================================================
    public function generate30YearFlow($daeuns) {
        $currentAge = (int)date('Y') - (int)$this->result['input']['year'];
        $currentDaeunIdx = null;

        foreach ($daeuns as $d) {
            if ($currentAge >= $d['age_start'] && $currentAge <= $d['age_end']) {
                $currentDaeunIdx = $d['index'];
                break;
            }
        }

        // 현재·다음·그 다음 3개 대운 분석
        $targetIndices = [];
        $startIdx = $currentDaeunIdx ?? 0;
        for ($i = 0; $i < 3 && ($startIdx + $i) < count($daeuns); $i++) {
            $targetIndices[] = $startIdx + $i;
        }

        $flowText = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $flowText .= "  📊 향후 30년 대운 흐름 종합 분석\n";
        $flowText .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        $details = [];
        $prevScore = null;
        $scores = [];

        foreach ($targetIndices as $idx) {
            $d = $daeuns[$idx];
            $combo = $this->getCombinationInterpretation($d);

            $trend = '';
            if ($prevScore !== null) {
                if ($d['score'] > $prevScore + 10) $trend = '📈 상승세';
                elseif ($d['score'] < $prevScore - 10) $trend = '📉 하강세';
                else $trend = '➡️ 유지';
            } else {
                $trend = '▶ 현재';
            }

            $details[] = [
                'daeun' => $d,
                'combination' => $combo,
                'trend' => $trend,
            ];

            $level = $d['score'] >= 75 ? '대길' : ($d['score'] >= 60 ? '길' : ($d['score'] >= 45 ? '보통' : ($d['score'] >= 30 ? '소흉' : '흉')));
            $emoji = ['대길'=>'🌟','길'=>'☀️','보통'=>'☁️','소흉'=>'🌧️','흉'=>'⛈️'][$level];

            $flowText .= "{$emoji} {$d['age_start']}~{$d['age_end']}세 ({$d['stem']}{$d['branch']}) — {$d['score']}점 {$trend}\n";
            $flowText .= "   원국: {$combo['wonguk_pattern_name']} × 대운: {$combo['daeun_pattern_name']}\n";

            // 각 기간 핵심 요약
            $flowText .= "   💡 핵심: " . $this->getFlowSummary($combo) . "\n\n";

            $prevScore = $d['score'];
            $scores[] = $d['score'];
        }

        // 전환 조언
        if (count($details) >= 2) {
            $from = $details[0]['combination']['daeun_pattern'];
            $to = $details[1]['combination']['daeun_pattern'];
            $transAdv = DaeunCombinationData::getTransitionAdvice($from, $to);
            $flowText .= "━━━ 대운 전환 조언 ━━━\n";
            $flowText .= $transAdv . "\n\n";
        }

        // 전체 흐름 평가
        if (!empty($scores)) {
            $avg = array_sum($scores) / count($scores);
            $flowText .= "━━━ 전체 흐름 평가 ━━━\n";
            if ($avg >= 65) {
                $flowText .= "🌟 향후 30년의 전체적인 운세 흐름이 양호합니다. ";
                $flowText .= "기회를 적극적으로 활용하되, 겸손함을 잃지 마세요.\n";
            } elseif ($avg >= 50) {
                $flowText .= "☀️ 전체적으로 안정적인 흐름입니다. ";
                $flowText .= "꾸준한 노력이 좋은 결과를 만들 수 있는 시기입니다.\n";
            } elseif ($avg >= 40) {
                $flowText .= "☁️ 도전과 기회가 공존하는 시기입니다. ";
                $flowText .= "신중한 선택과 인내가 필요하지만, 성장의 밑거름이 되는 시간입니다.\n";
            } else {
                $flowText .= "🌧️ 인내가 필요한 시기이지만, 모든 겨울 뒤에는 반드시 봄이 옵니다. ";
                $flowText .= "용신을 적극 보강하고, 건강과 재정 관리에 집중하세요.\n";
            }

            // 점수 추세
            if (count($scores) >= 2) {
                $firstHalf = $scores[0];
                $lastHalf = end($scores);
                if ($lastHalf > $firstHalf + 5) {
                    $flowText .= "\n📈 시간이 갈수록 운세가 상승하는 추세입니다! 현재의 노력이 미래에 결실을 맺습니다.";
                } elseif ($lastHalf < $firstHalf - 5) {
                    $flowText .= "\n📉 현재가 상대적으로 좋은 시기입니다. 지금의 기회를 최대한 활용하고, 미래를 대비하세요.";
                } else {
                    $flowText .= "\n➡️ 큰 변동 없이 안정적인 흐름이 이어집니다. 꾸준함이 최고의 전략입니다.";
                }
            }
        }

        return [
            'details' => $details,
            'narrative' => $flowText,
        ];
    }

    // ============================================================
    // 흐름 요약 (한 줄)
    // ============================================================
    private function getFlowSummary($combo) {
        $career = mb_substr($combo['career'], 0, 40, 'UTF-8');
        $wealth = mb_substr($combo['wealth'], 0, 40, 'UTF-8');
        return "{$career}... / {$wealth}...";
    }

    // ============================================================
    // 공개 접근자
    // ============================================================
    public function getWongukPatterns() {
        return $this->wongukPatterns;
    }

    public function getWongukPatternNames() {
        $names = [];
        foreach ($this->wongukPatterns as $p) {
            $names[$p] = DaeunCombinationData::getWongukPatternName($p);
        }
        return $names;
    }

    public function getSipsinGroups() {
        return $this->sipsinGroups;
    }

    public function getWongukSummary() {
        $primary = $this->wongukPatterns[0] ?? 'johwa_gyunhyeong';
        $name = DaeunCombinationData::getWongukPatternName($primary);
        $desc = DaeunCombinationData::getWongukDescription($primary);
        return [
            'primary_pattern' => $primary,
            'name' => $name,
            'description' => $desc,
            'all_patterns' => $this->getWongukPatternNames(),
            'sipsin_groups' => $this->sipsinGroups,
            'is_strong' => $this->isStrong,
        ];
    }
}
