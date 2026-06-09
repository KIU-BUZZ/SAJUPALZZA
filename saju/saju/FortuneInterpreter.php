<?php
/**
 * ========================================
 * 운세 해석 엔진 (FortuneInterpreter) v4
 * ========================================
 * 
 * 스토리텔링 중심의 풍부한 명리학 해석
 * 
 * [핵심 기능]
 * 1. 격국(格局) 분석 — 사주의 중심 구조 + 스토리텔링
 * 2. 대운(大運) — 10년 단위 운세 + 인생 시나리오
 * 3. 세운(歲運) — 연 단위 운세 + 월별 상세
 * 4. 조건 기반 규칙 엔진 — 십성 조합 맞춤 해석 (풍부)
 * 5. 종합 운세 — 성격/연애/직업/재물/학업/건강/인생흐름 (대서사)
 * 6. [v4] 패턴 기반 해석 — SajuStoryGenerator + DaeunCombinationEngine 통합
 */

require_once __DIR__ . '/SajuStoryGenerator.php';
require_once __DIR__ . '/DaeunCombinationEngine.php';

class FortuneInterpreter {

    private $engine;
    private $result;
    private $ohangAnalysis;
    private $ohangData;

    public function __construct(SajuEngine $engine) {
        $this->engine = $engine;
        $this->result = $engine->getResult();
        $this->ohangAnalysis = new OhangAnalysis($engine);
        $this->ohangData = $this->ohangAnalysis->analyze();
    }

    public function getOhangData() { return $this->ohangData; }

    // ================================================================
    //  십성 분석 (지장간 포함 상세 분석)
    // ================================================================
    public function analyzeSipsin() {
        $sipsinFull = $this->result['sipsin_full'];
        $dist = $sipsinFull['distribution'];
        $dominant = $sipsinFull['dominant_sipsin'];
        $dominantInfo = $sipsinFull['dominant_sipsin_info'];

        $groups = [
            '비겁(比劫)' => ['비견','겁재'],
            '식상(食傷)' => ['식신','상관'],
            '재성(財星)' => ['편재','정재'],
            '관성(官星)' => ['편관','정관'],
            '인성(印星)' => ['편인','정인'],
        ];
        $groupTotals = [];
        foreach ($groups as $gName => $members) {
            $total = 0;
            foreach ($members as $m) $total += ($dist[$m] ?? 0);
            $groupTotals[$gName] = round($total, 2);
        }
        arsort($groupTotals);

        $interpretations = $this->interpretSipsinCombination($dist, $groupTotals);

        return [
            'pillars_detail' => $sipsinFull['pillars'],
            'distribution' => $dist,
            'group_totals' => $groupTotals,
            'dominant' => $dominant,
            'dominant_info' => $dominantInfo,
            'interpretations' => $interpretations,
        ];
    }

    // ================================================================
    //  조건 기반 규칙 엔진 — 풍부한 스토리텔링 해석
    // ================================================================
    private function interpretSipsinCombination($dist, $groups) {
        $rules = [];
        $bigyeop  = $groups['비겁(比劫)'] ?? 0;
        $siksang  = $groups['식상(食傷)'] ?? 0;
        $jaesung  = $groups['재성(財星)'] ?? 0;
        $gwansung = $groups['관성(官星)'] ?? 0;
        $insung   = $groups['인성(印星)'] ?? 0;
        $dms = $this->result['day_master_strength'];
        $isStrong = $dms['is_strong'];
        $dayElement = $this->result['day_master_element'];
        $gender = $this->engine->getGender();
        $genderLabel = $gender === 'male' ? '남성' : '여성';

        // --- 비겁 관련 ---
        if ($bigyeop >= 3.0) {
            $rules[] = ['category'=>'성격','title'=>'비겁(比劫) 과다 — "나 홀로 천하"의 기운','level'=>'주의',
                'text'=>"당신의 사주에는 비겁(比劫)의 기운이 매우 강하게 흐르고 있습니다.\n\n".
                    "비겁은 곧 '나와 같은 기운'입니다. 마치 세상에 나 혼자 우뚝 선 큰 나무와 같아서, ".
                    "자존심이 하늘을 찌르고 독립심이 강합니다. 누구에게도 굽히지 않으려 하고, ".
                    "\"내 일은 내가 한다\"는 신조로 살아갑니다.\n\n".
                    "🔥 이런 기운의 장점은 어떤 역경에서도 꺾이지 않는 강한 정신력입니다. ".
                    "경쟁 상황에서 절대 포기하지 않으며, 스스로 길을 개척하는 능력이 탁월합니다.\n\n".
                    "⚡ 하지만 주의할 점이 있습니다. 고집이 너무 세서 주변 사람들과 마찰이 잦을 수 있고, ".
                    "\"내가 맞다\"는 생각에 다른 사람의 좋은 의견도 무시할 수 있습니다. ".
                    "특히 동업이나 팀 프로젝트에서 갈등이 생기기 쉽습니다.\n\n".
                    "💡 인생 조언: 가끔은 한 발 물러나서 상대방의 이야기를 들어보세요. ".
                    "\"양보하는 것이 지는 것이 아니라, 더 큰 것을 얻기 위한 지혜\"라는 것을 기억하면, ".
                    "당신의 강한 에너지가 더욱 빛을 발할 것입니다."];
        }
        if ($bigyeop >= 2.0 && $jaesung <= 1.0) {
            $rules[] = ['category'=>'재물','title'=>'비겁탈재(比劫奪財) — "들어오는 돈이 빠져나가는" 구조','level'=>'경고',
                'text'=>"비겁(나의 기운)이 강한데 재성(돈)이 약합니다. 명리학에서 이를 '비겁탈재(比劫奪財)'라 부릅니다.\n\n".
                    "쉽게 말하면, 돈이 들어와도 다시 나가는 구조입니다. 마치 바닥에 구멍 뚫린 항아리에 물을 붓는 것과 같습니다. ".
                    "넉넉하게 벌어도 왠지 모르게 통장 잔고가 늘지 않는 현상을 겪을 수 있습니다.\n\n".
                    "이는 당신이 의리가 있고 인정이 많아, 주변 사람들에게 잘 베풀기 때문이기도 합니다. ".
                    "친구의 부탁을 거절하지 못하고, 후배에게 밥을 사주며, ".
                    "\"돈보다 사람이 중요하다\"는 철학을 가지고 있지 않나요?\n\n".
                    "💡 재물 관리 전략:\n".
                    "① 자동이체로 월급의 일정 비율을 저축하세요 (돈이 손에 오기 전에 빼놓는 전략)\n".
                    "② 동업은 절대 피하세요 — 독자 경영이 유리합니다\n".
                    "③ 보증이나 투자 권유에 쉽게 응하지 마세요\n".
                    "④ 용신({$dms['yongshin']['element']}의 기운)을 보강하면 재물운이 개선됩니다"];
        }
        if ($bigyeop <= 0.5 && $gwansung >= 2.0) {
            $rules[] = ['category'=>'성격','title'=>'비겁 부족 + 관성 강 — "무거운 짐을 진 나그네"','level'=>'주의',
                'text'=>"당신의 사주에서 나를 대표하는 비겁의 기운이 약한 반면, ".
                    "나를 억누르는 관성의 기운이 매우 강합니다.\n\n".
                    "이것은 마치 어린 나무에 무거운 눈이 쌓인 것과 같습니다. ".
                    "직장에서 과도한 업무를 맡거나, 사회적 기대와 역할에 눌려 스트레스를 많이 받을 수 있습니다. ".
                    "\"해야 할 것\"은 많은데 \"할 수 있는 것\"이 부족하다고 느끼는 순간이 잦습니다.\n\n".
                    "하지만 이런 사주의 소유자들은 대개 매우 성실하고 책임감이 강합니다. ".
                    "남들이 안 하는 일도 묵묵히 해내는 인내심의 소유자입니다.\n\n".
                    "💡 인생 조언:\n".
                    "• 모든 것을 혼자 짊어지려 하지 마세요 — 도움을 요청하는 것도 능력입니다\n".
                    "• 운동이나 취미 활동으로 스트레스를 반드시 해소하세요\n".
                    "• 인성(공부·자기단련)을 통해 내면의 힘을 기르면 관성의 부담이 줄어듭니다"];
        }

        // --- 식상 관련 ---
        if ($siksang >= 3.0) {
            $rules[] = ['category'=>'성격','title'=>'식상(食傷) 과다 — "세상을 향해 자신을 표현하는" 예술가 기질','level'=>'양면',
                'text'=>"당신에게는 식상(食傷)의 기운이 넘쳐흐릅니다! 식상은 '나로부터 나오는 에너지'입니다.\n\n".
                    "마치 꽃이 만발한 봄 정원처럼, 당신은 생각과 감정을 풍부하게 표현합니다. ".
                    "말을 잘하고, 글재주가 있으며, 예술적 감각이 남다릅니다. ".
                    "유머 감각도 뛰어나서 주변 사람들을 즐겁게 만드는 재주가 있습니다.\n\n".
                    "✨ 이런 기운의 소유자들은 창작·예술·미디어·기획 분야에서 빛을 발합니다. ".
                    "유튜버, 작가, 디자이너, 강사, 연예인 등 '자기 표현'이 중심인 일이 천직입니다.\n\n".
                    "⚡ 다만 주의할 점: 참을성이 부족하고, 규칙이나 제약을 답답해합니다. ".
                    "직장에서 상사의 지시에 반발심이 생기기 쉽고, 한 가지 일에 오래 집중하기 어려울 수 있습니다. ".
                    "또한 말이 너무 많아서 실수할 수 있으니, 때때로 침묵의 미덕을 기억하세요.\n\n".
                    "💡 핵심: 당신의 표현력은 엄청난 재산입니다. ".
                    "이것을 '재성(돈)'으로 연결하면 식상생재(食傷生財)가 되어 큰 부를 이룰 수 있습니다."];
        }
        if ($siksang >= 2.0 && $jaesung >= 2.0) {
            $rules[] = ['category'=>'재물','title'=>'식상생재(食傷生財) — "재능이 곧 돈이 되는" 최고의 재물 구조','level'=>'대길',
                'text'=>"🎯 축하합니다! 당신의 사주에는 '식상생재(食傷生財)'라는 매우 좋은 구조가 있습니다!\n\n".
                    "이것은 무엇일까요? 식상(나의 재능·기술·표현력)이 재성(돈·재물)을 생산해내는 구조입니다. ".
                    "쉽게 말하면, \"내가 잘하는 것을 하면 돈이 된다\"는 뜻입니다.\n\n".
                    "🌟 이런 구조의 사람들은:\n".
                    "• 전문직으로 성공하는 경우가 많습니다 (의사, 변호사, 전문 기술자 등)\n".
                    "• 창작 활동으로 수입을 올립니다 (작가, 유튜버, 디자이너 등)\n".
                    "• 사업을 하면 자기 기술력을 기반으로 성장합니다\n\n".
                    "💎 당신의 전략: 능력 개발에 투자하세요! 자격증, 기술 학습, 포트폴리오 강화 등 ".
                    "실력을 키우는 모든 활동이 곧 재물로 연결됩니다. ".
                    "현재 하고 있는 일에서 전문성을 더 깊이 파고들수록, 수입은 자연스럽게 늘어납니다.\n\n".
                    "🔑 핵심 키워드: \"실력 = 돈\". 꾸준한 자기계발이 최고의 재테크입니다."];
        }
        if ($siksang >= 2.0 && $gwansung >= 2.0) {
            $rules[] = ['category'=>'직업','title'=>'식상·관성 갈등 — "자유와 안정 사이에서"','level'=>'갈등',
                'text'=>"당신의 내면에는 두 가지 상반된 에너지가 공존합니다.\n\n".
                    "한쪽에서는 식상(食傷)이 \"자유롭게 살고 싶어! 나만의 방식으로!\"라고 외치고, ".
                    "다른 쪽에서는 관성(官星)이 \"안정적으로! 규칙을 따라서!\"라고 말합니다.\n\n".
                    "이것은 예술가의 영혼이 공무원의 몸에 들어간 것과 같습니다. ".
                    "창의적인 아이디어가 샘솟지만 조직의 틀 안에서 답답함을 느끼고, ".
                    "안정이 필요하지만 자유를 갈망합니다.\n\n".
                    "✨ 하지만 이것은 단점이 아닙니다! 오히려 두 가지를 모두 가진 당신만의 장점입니다.\n\n".
                    "💡 최적의 직업 환경:\n".
                    "• 자유로우면서도 체계적인 조직 (IT기업, 스타트업, 외국계 회사)\n".
                    "• 창의적이면서도 사회적 권위가 있는 직종 (건축가, 판사, 프로듀서)\n".
                    "• 조직 내에서 기획·혁신 담당 부서\n".
                    "• 프리랜서이면서 장기 계약 형태의 일"];
        }

        // --- 재성 관련 ---
        if ($jaesung >= 3.0) {
            $rules[] = ['category'=>'재물','title'=>'재성(財星) 과다 — "돈의 흐름이 강한" 사주','level'=>'양면',
                'text'=>"당신의 사주에는 재성(財星)의 기운이 매우 강합니다.\n\n".
                    "재성은 '내가 다스리는 것' — 즉, 돈·재물·현실적 가치를 의미합니다. ".
                    "돈에 대한 감각이 예리하고, 사업 수완이 뛰어나며, ".
                    "\"있는 돈을 어떻게 불릴까\"를 본능적으로 파악합니다.\n\n".
                    "🌟 장점: 현실 감각이 좋고, 남들이 놓치는 기회를 잘 포착합니다. ".
                    "재테크·투자·사업에서 성과를 내기 쉽습니다.\n\n".
                    "⚡ 주의할 점:\n".
                    "• 과로 주의! 돈을 위해 건강을 해칠 수 있습니다\n".
                    "• 인성(학문·정신적 가치)을 소홀히 하면 '부자이지만 내면은 공허한' 상태가 될 수 있습니다\n".
                    "• {$genderLabel}의 경우 ".($gender==='male' ? "여성 관계가 복잡해질 수 있으니 가정에 충실하세요" : "남편(관성)에 대한 기대가 높아 갈등이 생길 수 있습니다")."\n\n".
                    "💡 핵심: 물질적 풍요와 정신적 풍요의 균형을 잡으세요. ".
                    "돈도 중요하지만, 건강·가족·자기 성장도 함께 챙겨야 진정한 부자입니다."];
        }
        if ($jaesung >= 2.0 && $gwansung <= 0.5) {
            $rules[] = ['category'=>'직업','title'=>'재성 강 + 관성 약 — "사장 팔자"의 사주','level'=>'참고',
                'text'=>"돈을 버는 능력(재성)은 뛰어나지만, 직장·조직(관성)과의 인연은 약합니다.\n\n".
                    "이것은 명리학에서 전형적인 '사장 팔자'로 봅니다. ".
                    "남 밑에서 일하기보다 자기 사업을 하는 것이 훨씬 유리한 구조입니다.\n\n".
                    "🏠 직장 생활을 한다면:\n".
                    "• 오래 다니기 어렵고, 이직이 잦을 수 있습니다\n".
                    "• 가능하면 영업·세일즈 등 성과 위주의 직종이 좋습니다\n\n".
                    "🚀 사업을 한다면:\n".
                    "• 자기가 직접 운영하는 소규모 사업이 유리합니다\n".
                    "• 초기에는 어렵더라도 꾸준히 하면 성과가 나옵니다\n".
                    "• 법률·세무 전문가의 도움을 반드시 받으세요 (관성이 약하므로)"];
        }
        if ($jaesung >= 2.0 && $insung <= 0.5) {
            $rules[] = ['category'=>'학업','title'=>'재다인약(財多印弱) — "실전형 인재"','level'=>'주의',
                'text'=>"실용적 능력은 뛰어나지만, 이론적·학문적 깊이가 부족할 수 있습니다.\n\n".
                    "명리학에서 '재다인약(財多印弱)'은 돈에 정신 팔린 생각이 지혜를 가린다는 뜻입니다. ".
                    "당장의 수익에만 집중하면, 장기적으로 성장할 수 있는 지혜와 학식을 놓치게 됩니다.\n\n".
                    "💡 보완 방법:\n".
                    "• 매일 30분이라도 독서하는 습관을 들이세요\n".
                    "• 전문 자격증이나 학위가 장기적으로 더 큰 재물을 가져다줍니다\n".
                    "• 멘토를 찾아 정기적으로 조언을 구하세요"];
        }

        // --- 관성 관련 ---
        if ($gwansung >= 3.0) {
            $rules[] = ['category'=>'직업','title'=>'관성(官星) 과다 — "무겁지만 보람 있는 책임의 무게"','level'=>'주의',
                'text'=>"당신은 타고난 책임감의 소유자입니다. 관성이 매우 강하기 때문입니다.\n\n".
                    "관성은 '나를 다스리는 기운' — 직장·조직·사회적 역할·법·규율을 의미합니다. ".
                    "이 기운이 강하면 명예욕이 크고, 높은 자리에 오르고 싶은 욕구가 강합니다.\n\n".
                    "🏆 좋은 면: 조직에서 빠르게 인정받고 승진합니다. 리더십이 있고, ".
                    "사회적으로 존경받는 위치에 오를 수 있습니다.\n\n".
                    "⚡ 주의할 면: 스트레스가 매우 심합니다. ".
                    "\"해야 한다\"는 의무감에 얽매여 자기 자신을 돌보지 못할 수 있습니다. ".
                    "특히 편관(偏官=칠살)이 강하면 갑작스러운 인사이동·사건이 생길 수 있습니다.\n\n".
                    "💡 생존 전략:\n".
                    "• 인성(印星)을 강화하세요 — 학문·자격증·자기 수양이 관성의 압력을 완화합니다\n".
                    "• \"관인상생\"의 흐름을 만들면: 직장의 성과 → 자기 성장 → 더 큰 성과의 선순환\n".
                    "• 주말에는 반드시 쉬세요. 번아웃은 당신의 가장 큰 적입니다"];
        }
        if ($gwansung >= 2.0 && $insung >= 1.5) {
            $rules[] = ['category'=>'직업','title'=>'관인상생(官印相生) — "출세와 학문이 함께 가는" 귀한 구조','level'=>'대길',
                'text'=>"🎯 당신의 사주에는 '관인상생(官印相生)'이라는 매우 귀한 구조가 있습니다!\n\n".
                    "이것이 무엇일까요?\n".
                    "• 관성(官星) = 직장·사회적 지위·권력·명예\n".
                    "• 인성(印星) = 학문·지혜·자격증·정신적 힘\n\n".
                    "관성이 인성을 생(生)하는 구조, 즉 '조직에서의 경험과 성과가 나의 지혜와 학식으로 쌓인다'는 뜻입니다.\n\n".
                    "🌟 이런 사주의 사람들은 역사적으로:\n".
                    "• 과거 시험에 합격하여 높은 벼슬에 오른 학자 관료\n".
                    "• 현대에는 고위 공무원, 교수, 판사, 검사, 대기업 임원\n".
                    "• 전문 자격(의사, 변호사, 회계사)을 취득하여 사회적 지위를 얻는 사람\n\n".
                    "📚 당신에게 가장 중요한 것은 '꾸준한 학습'입니다.\n".
                    "공부하면 할수록 직장에서의 성과가 올라가고, 성과가 올라갈수록 더 깊은 공부를 하게 되는 선순환이 일어납니다.\n\n".
                    "💡 실천 전략:\n".
                    "① 업무와 관련된 자격증을 하나 더 따세요\n".
                    "② 승진 시험이나 사내 교육 프로그램에 적극 참여하세요\n".
                    "③ 학위 과정(석사/박사)을 고려해보세요 — 당신에게는 학문이 출세의 지름길입니다"];
        }

        // --- 인성 관련 ---
        if ($insung >= 3.0) {
            $rules[] = ['category'=>'성격','title'=>'인성(印星) 과다 — "생각이 깊은 지식인의 고민"','level'=>'양면',
                'text'=>"당신의 사주에는 인성(印星)의 기운이 매우 강합니다.\n\n".
                    "인성은 '나를 키워주는 기운' — 학문, 지식, 어머니, 보호, 정신세계를 의미합니다. ".
                    "이 기운이 강한 사람은 머리가 좋고, 배우는 것을 좋아하며, 사고가 깊습니다.\n\n".
                    "📚 장점: 지적 수준이 높고, 다른 사람들이 보지 못하는 것을 봅니다. ".
                    "학문적 성취가 높고, 현명한 판단을 내릴 수 있습니다.\n\n".
                    "⚡ 하지만 '생각의 늪'에 빠지기 쉽습니다:\n".
                    "• 결정을 내리지 못하고 계속 고민만 하는 경우\n".
                    "• 완벽주의에 빠져 시작하지 못하는 경우\n".
                    "• 현실 감각이 떨어져 이상과 현실의 괴리가 생기는 경우\n\n".
                    "💡 인생 조언: \"알고 있는 것\"을 \"행동으로 옮기는 것\"이 관건입니다. ".
                    "\"Done is better than perfect\" — 완벽하지 않아도 일단 시작하세요!"];
        }
        if ($insung >= 2.0 && $siksang <= 0.5) {
            $rules[] = ['category'=>'성격','title'=>'인성 강 + 식상 약 — "머릿속의 보물을 꺼내지 못하는"','level'=>'참고',
                'text'=>"당신은 많이 알고 깊이 생각하지만, 그것을 밖으로 표현하는 데 어려움이 있습니다.\n\n".
                    "인성(배움)은 풍부한데 식상(표현)이 약한 것입니다. ".
                    "머릿속에는 훌륭한 아이디어가 가득하지만, 막상 말이나 글로 표현하면 생각만큼 나오지 않습니다.\n\n".
                    "이것은 마치 보물 상자를 가지고 있으면서 열쇠를 찾지 못한 것과 같습니다.\n\n".
                    "🔑 표현력 키우기:\n".
                    "• 블로그나 SNS에 매일 짧은 글을 써보세요\n".
                    "• 발표 연습이나 토론 모임에 참여하세요\n".
                    "• 말보다 글이 편하다면 작문부터 시작하세요\n".
                    "• 식상을 보완하는 대운이 올 때 크게 성장할 수 있습니다"];
        }

        // --- 신강/신약 + 용신 ---
        if ($isStrong && $siksang <= 0.5) {
            $rules[] = ['category'=>'운세','title'=>'신강 + 식상 부족 — "막힌 에너지를 풀어야 할 때"','level'=>'참고',
                'text'=>"당신의 사주는 에너지가 넘치는 '신강(身强)' 사주입니다.\n\n".
                    "체력이 좋고 의지가 강하지만, 이 에너지를 발산할 식상(표현·출구)이 부족합니다. ".
                    "마치 꽉 막힌 댐에 물이 가득 찬 것과 같습니다.\n\n".
                    "이 에너지가 건강한 방식으로 발산되지 않으면:\n".
                    "• 답답함과 짜증이 쌓입니다\n".
                    "• 갑작스럽게 폭발할 수 있습니다\n".
                    "• 건강 문제(고혈압, 두통 등)가 생길 수 있습니다\n\n".
                    "🏃 에너지 방출 전략:\n".
                    "• 격렬한 운동 (달리기, 수영, 복싱 등) — 가장 효과적\n".
                    "• 창작 활동 (그림, 음악, 글쓰기)\n".
                    "• 봉사 활동이나 멘토링\n".
                    "• 대운에서 식상이 올 때 인생의 전환점이 될 수 있습니다!"];
        }
        if (!$isStrong && $gwansung >= 2.0) {
            $rules[] = ['category'=>'운세','title'=>'신약 + 관성 과다 — "작은 배에 큰 파도가 치는" 구조','level'=>'주의',
                'text'=>"⚠ 당신의 사주는 주의가 필요한 구조입니다.\n\n".
                    "일간(나)의 힘이 약한데(신약), 관성(외부 압력)이 매우 강합니다. ".
                    "이것은 작은 배가 거센 파도를 만난 것과 같습니다.\n\n".
                    "실생활에서 나타나는 현상:\n".
                    "• 직장에서 업무 과중으로 스트레스가 극심합니다\n".
                    "• \"나보다 능력 있는 사람들 사이에서 버텨야 한다\"는 압박감\n".
                    "• 겉으로는 잘 해내는 것 같지만, 내면은 지쳐 있을 수 있습니다\n\n".
                    "🛡 자기 보호 전략:\n".
                    "• 인성(印星) 보강이 핵심! → 학습·자기계발로 실력을 키우면 관성의 압력을 감당할 수 있습니다\n".
                    "• 무리한 야근과 과로를 피하세요\n".
                    "• 내 편이 되어줄 사람(비겁=동료)을 곁에 두세요\n".
                    "• 인성과 비겁이 오는 대운이 당신의 기회입니다"];
        }

        // --- 특수 조합 ---
        if ($siksang >= 1.5 && $insung >= 1.5 && ($dist['상관'] ?? 0) >= 1.0 && ($dist['편인'] ?? 0) >= 1.0) {
            $rules[] = ['category'=>'주의','title'=>'도식(倒食) — 상관+편인의 갈등','level'=>'경고',
                'text'=>"사주에 상관과 편인이 함께 존재합니다. 이를 '도식(倒食)'이라 합니다.\n\n".
                    "나의 재능(식상)을 편인이 빼앗는 구조로, ".
                    "아이디어가 떠올라도 실행에 옮기지 못하거나, ".
                    "시작한 일을 중간에 그만두는 패턴이 반복될 수 있습니다.\n\n".
                    "💡 극복법: 재성(財星)을 강화하면 이 갈등을 해소할 수 있습니다. ".
                    "현실적인 목표를 세우고, 돈이나 구체적 성과로 연결하는 훈련을 하세요."];
        }
        if ($gwansung >= 1.5 && $bigyeop >= 1.5 && ($dist['편관'] ?? 0) >= 1.0) {
            $rules[] = ['category'=>'변화','title'=>'칠살혼잡(七殺混雜) — 변동과 도전의 인생','level'=>'양면',
                'text'=>"편관(칠살)과 비겁이 함께 강합니다. 인생에 변동이 많고 역동적인 삶을 살게 됩니다.\n\n".
                    "평탄한 삶보다는 파란만장한 드라마 같은 인생이 전개될 가능성이 높습니다. ".
                    "하지만 이런 사주의 소유자들이 오히려 역사에 이름을 남긴 경우가 많습니다.\n\n".
                    "💡 식신(食神)으로 칠살을 제어하면(식신제살) 영웅의 사주가 됩니다."];
        }

        if (empty($rules)) {
            $rules[] = ['category'=>'종합','title'=>'조화로운 사주 — "고르게 발달한 만능형"','level'=>'길',
                'text'=>"당신의 사주는 십성이 비교적 고르게 분포되어 있습니다.\n\n".
                    "이것은 어느 한 방향으로 극단적이지 않다는 뜻으로, ".
                    "다양한 분야에서 균형 있게 능력을 발휘할 수 있는 만능형 사주입니다.\n\n".
                    "장점: 어떤 환경에서든 잘 적응하고, 여러 분야에 두루 재능이 있습니다.\n".
                    "보완점: 뚜렷한 강점이 없을 수 있으니, 하나의 전문 분야를 정해 깊이 파고드는 것이 좋습니다.\n\n".
                    "💡 핵심: \"여러 가지를 잘하는 것\"에서 \"한 가지를 탁월하게 잘하는 것\"으로 진화하세요!"];
        }

        return $rules;
    }

    // ================================================================
    //  격국(格局) 분석
    // ================================================================
    public function analyzeGyeokguk() {
        $dist = $this->result['sipsin_full']['distribution'];
        $dms = $this->result['day_master_strength'];
        $isStrong = $dms['is_strong'];
        $dayElement = $this->result['day_master_element'];

        $monthBranch = SajuEngine::JIJI[$this->engine->getMonthPillar()[1]];
        $jijangganList = SajuEngine::JIJANGGAN[$monthBranch];
        $lastItem = end($jijangganList);
        $bongGan = $lastItem[0];
        $bongEl = SajuEngine::CHEONGAN_OHANG[$bongGan];
        $bongYY = SajuEngine::CHEONGAN_YINYANG[array_search($bongGan, SajuEngine::CHEONGAN)];
        $dayYY = SajuEngine::CHEONGAN_YINYANG[$this->engine->getDayPillar()[0]];
        $mainSipsin = SajuEngine::getSipsin($dayElement, $dayYY, $bongEl, $bongYY);

        $gyeokgukMap = [
            '비견' => ['name'=>'건록격(建祿格)','desc'=>'자립심이 강하고 독립적인 기질',
                'detail'=>"건록격(建祿格)은 일간과 같은 오행이 월지에 자리한 격국으로, 마치 깊이 뿌리내린 고목(古木)과 같습니다.\n\n".
                    "🌳 당신의 인생 이야기:\n".
                    "태어날 때부터 \"나는 내 힘으로 살겠다\"는 DNA가 새겨져 있습니다. 어린 시절부터 남에게 의지하기보다 스스로 해결하려 했고, ".
                    "그 독립심은 나이가 들수록 더욱 강해집니다. 주변에서 \"고집이 세다\"는 말을 듣기도 하지만, ".
                    "사실 그것은 자신만의 원칙이 확고하기 때문입니다.\n\n".
                    "💼 적합한 삶의 형태: 자영업, 프리랜서, 전문직, 운동선수, CEO\n".
                    "▸ 남 밑에서 일하기보다 자기 사업을 하는 것이 훨씬 행복합니다.\n".
                    "▸ 동업은 피하세요 — 의견 충돌이 불가피합니다.\n\n".
                    "💡 성공의 열쇠: 당신의 강한 독립심에 '협력의 지혜'를 더하면 황금 조합입니다. ".
                    "혼자서 10을 이루기보다, 함께해서 100을 이루는 길을 찾아보세요."],
            '겁재' => ['name'=>'양인격(陽刃格)','desc'=>'승부욕이 강하고 도전적인 격국',
                'detail'=>"양인격(陽刃格)은 명리학에서 가장 강렬한 에너지를 가진 격국입니다. ".
                    "마치 날카롭게 벼린 칼날과 같아서, 잘 쓰면 천하를 얻고 잘못 쓰면 자신을 해칩니다.\n\n".
                    "⚔️ 당신의 인생 이야기:\n".
                    "어릴 때부터 지는 것을 몹시 싫어했습니다. 어떤 경쟁에서든 최고가 되려 하고, ".
                    "위기 상황에서 오히려 빛을 발하는 사람입니다. 남들이 포기할 때 당신은 \"한 번 더!\"를 외칩니다.\n\n".
                    "💼 적합 직업: 군인, 경찰, 검찰, 외과의사, 프로 운동선수, 투자가, 위기관리 전문가\n\n".
                    "💡 성공의 열쇠: 정관(正官)이 양인을 적절히 제어하면 대장군의 사주가 됩니다. ".
                    "규율과 질서를 스스로에게 부여하는 것이 핵심입니다."],
            '식신' => ['name'=>'식신격(食神格)','desc'=>'여유롭고 복이 많은 격국',
                'detail'=>"식신격(食神格)은 '복록격'이라고도 불리며, 타고난 복이 있는 격국입니다.\n\n".
                    "🎁 당신의 인생 이야기:\n".
                    "당신은 인생을 즐길 줄 아는 사람입니다. 맛있는 음식, 좋은 사람들과의 대화, ".
                    "아름다운 것들에 대한 감상... 삶의 소소한 즐거움을 누리는 데 천부적 재능이 있습니다.\n\n".
                    "풍요와 여유의 기운이 당신을 감싸고 있어, 먹을 복이 있고 사교성이 좋으며, ".
                    "어디를 가든 분위기를 밝게 만듭니다.\n\n".
                    "💼 적합 직업: 요리사, 카페/레스토랑 경영, 작가, 강사, 연예인, 콘텐츠 크리에이터\n\n".
                    "⚡ 주의: 너무 편안함에 안주하면 게을러질 수 있습니다. 적절한 긴장감을 유지하세요.\n\n".
                    "💡 성공의 열쇠: 식상생재(食傷生財)의 흐름을 만들면 됩니다. ".
                    "즉, 당신이 즐기는 것을 직업으로 만들면 돈도 자연스럽게 따라옵니다."],
            '상관' => ['name'=>'상관격(傷官格)','desc'=>'천재적 재능을 지닌 파격의 격국',
                'detail'=>"상관격(傷官格)은 기존의 틀을 깨부수는 혁명가의 격국입니다!\n\n".
                    "🔥 당신의 인생 이야기:\n".
                    "\"왜 꼭 그래야 해?\" — 이것이 당신의 평생 화두입니다. ".
                    "기존의 규칙이나 관습에 의문을 품고, 남들과 다른 방식으로 세상을 봅니다. ".
                    "어린 시절에는 '반항기 있는 아이'로 불렸지만, 사실 그것은 독창적 사고의 발현입니다.\n\n".
                    "언변이 날카롭고, 비판적 시각이 예리하며, 예술적 감각이 남다릅니다. ".
                    "하지만 말이 너무 날카로워 의도치 않게 상대방에게 상처를 줄 때가 있습니다.\n\n".
                    "💼 적합 직업: 예술가, 변호사, 비평가, 혁신 기업가, 유튜버, 발명가\n\n".
                    "💡 성공의 열쇠: 상관의 날카로운 에너지를 재성(財星)으로 연결하면(상관생재) ".
                    "독창적인 재능이 엄청난 부로 전환됩니다."],
            '편재' => ['name'=>'편재격(偏財格)','desc'=>'사업 수완이 뛰어난 큰손의 격국',
                'detail'=>"편재격(偏財格)은 큰 그릇의 사업가 기질을 가진 격국입니다.\n\n".
                    "💰 당신의 인생 이야기:\n".
                    "당신은 돈이 어디에 있는지 알고, 어떻게 벌어야 하는지 아는 사람입니다. ".
                    "편재는 '흘러다니는 큰 돈'을 상징하며, 스케일이 크고 대범한 결정을 내립니다.\n\n".
                    "인맥이 넓고 사교성이 좋으며, 사람을 통해 기회를 만들어냅니다. ".
                    "투기적 성향이 있어 큰 돈을 벌기도 하지만, 큰 돈을 잃기도 합니다.\n\n".
                    "💼 적합 직업: 사업가, 트레이더, 부동산 개발, 세일즈, 무역, 투자\n\n".
                    "💡 성공의 열쇠: \"큰 그릇에는 큰 물이 담긴다.\" 하지만 관성(官星)이 뒷받침되어야 ".
                    "재물과 함께 명예(사회적 신용)도 얻을 수 있습니다."],
            '정재' => ['name'=>'정재격(正財格)','desc'=>'근면성실한 안정의 격국',
                'detail'=>"정재격(正財格)은 '정당한 노력으로 얻는 재물'의 격국으로, 가장 안정적이고 꾸준한 격입니다.\n\n".
                    "🏛 당신의 인생 이야기:\n".
                    "당신은 화려하진 않지만 확실합니다. 묵묵히 한 걸음씩, 성실하게 쌓아가는 사람입니다. ".
                    "티끌 모아 태산이라는 말이 당신의 인생 철학입니다.\n\n".
                    "신용이 좋고, 맡은 일은 반드시 해내며, 경제적 안정을 중시합니다. ".
                    "사치보다 실용을, 모험보다 안전을 선택합니다.\n\n".
                    "💼 적합 직업: 회계사, 금융인, 공무원, 경영관리, 안정적 자영업\n\n".
                    "💡 성공의 열쇠: 당신의 장점인 '꾸준함'을 유지하되, ".
                    "때로는 계산된 리스크를 감수하는 용기도 필요합니다. ".
                    "너무 보수적이면 큰 기회를 놓칠 수 있습니다."],
            '편관' => ['name'=>'편관격(七殺格)','desc'=>'카리스마 넘치는 영웅의 격국',
                'detail'=>"편관격(七殺格)은 영웅과 장군의 격국입니다!\n\n".
                    "⚔️ 당신의 인생 이야기:\n".
                    "평화로운 시대보다 위기의 시대에 빛을 발하는 것이 당신입니다. ".
                    "편관(칠살)의 기운은 압도적인 카리스마와 결단력을 부여합니다. ".
                    "위기 상황에서 남들이 멈출 때 당신은 앞으로 나아갑니다.\n\n".
                    "인생이 파란만장할 수 있지만, 그 과정에서 단련되어 보통 사람과는 다른 깊이를 가지게 됩니다.\n\n".
                    "💼 적합 직업: 군인, 경찰, 정치인, CEO, 외과의사, 위기관리 전문가, 소방관\n\n".
                    "💡 성공의 열쇠: 식신(食神)이 칠살을 제어하면(식신제살) 천하를 다스리는 큰 인물이 됩니다. ".
                    "자기 절제와 인내가 핵심입니다."],
            '정관' => ['name'=>'정관격(正官格)','desc'=>'품위와 명예의 격국',
                'detail'=>"정관격(正官格)은 전통적으로 가장 '귀(貴)'한 격국으로 여겨집니다.\n\n".
                    "🏆 당신의 인생 이야기:\n".
                    "당신은 타고난 '어른'입니다. 바르고 정직하며, 남을 이끌 줄 알고, ".
                    "사회적 질서를 존중합니다. 어릴 때부터 반장을 하거나, ".
                    "주변에서 \"네가 좀 책임져\"라는 말을 자주 들었을 것입니다.\n\n".
                    "조직에서 빠르게 인정받아 높은 자리에 오르며, ".
                    "사회적 명예와 지위를 얻는 것이 다른 격국보다 쉽습니다.\n\n".
                    "💼 적합 직업: 고위 공무원, 판사, 교수, 대기업 임원, 외교관, 정치인\n\n".
                    "💡 성공의 열쇠: 인성(印星)이 뒷받침되면 '관인상생'으로 최고의 성과를 거둡니다. ".
                    "꾸준한 자기 수양과 학습이 핵심입니다."],
            '편인' => ['name'=>'편인격(梟神格)','desc'=>'특수한 재능과 독특한 세계관',
                'detail'=>"편인격(梟神格)은 남들과 다른 특수한 재능을 가진 격국입니다.\n\n".
                    "🔮 당신의 인생 이야기:\n".
                    "당신은 직감이 비범하고, 남들이 보지 못하는 것을 봅니다. ".
                    "때로는 \"난 왜 남들과 다르지?\"라는 외로움을 느낄 수 있지만, ".
                    "그것이 바로 당신의 독보적 재능입니다.\n\n".
                    "관습적이지 않은 분야, 특수 기술 영역에서 두각을 나타냅니다. ".
                    "한 가지에 깊이 빠져드는 집중력이 있지만, 변덕이 심한 면도 있습니다.\n\n".
                    "💼 적합 직업: 연구원, 의사, 점술가, IT전문가, 심리상담사, 특수기술직, 발명가\n\n".
                    "💡 성공의 열쇠: 자기만의 전문 영역을 찾아 깊이 파고드세요. ".
                    "남들과 같은 길을 갈 필요가 없습니다. 당신만의 길이 곧 정답입니다."],
            '정인' => ['name'=>'정인격(正印格)','desc'=>'학자와 교육자의 격국',
                'detail'=>"정인격(正印格)은 학문과 지혜의 격국으로, '현명한 스승'의 사주입니다.\n\n".
                    "📚 당신의 인생 이야기:\n".
                    "당신은 배우는 것을 사랑하고, 배운 것을 가르치는 것에서 기쁨을 느끼는 사람입니다. ".
                    "어머니의 사랑처럼 따뜻하고 포용력이 있으며, 주변 사람들에게 지혜와 조언을 아끼지 않습니다.\n\n".
                    "어린 시절부터 책을 좋아했고, 공부에 두각을 나타냈을 가능성이 높습니다. ".
                    "지적 호기심이 강하며, 평생 배움을 멈추지 않습니다.\n\n".
                    "💼 적합 직업: 교수, 교사, 의사, 종교인, 작가, 상담사, 학자, 연구원\n\n".
                    "💡 성공의 열쇠: 관성(官星)과 만나면 관인상생의 귀한 구조가 됩니다. ".
                    "학문을 넘어 사회적으로도 인정받는 길이 열립니다."],
        ];

        $gInfo = $gyeokgukMap[$mainSipsin] ?? $gyeokgukMap['비견'];
        $quality = $this->assessGyeokgukQuality($mainSipsin, $dist, $isStrong);

        return [
            'main_sipsin' => $mainSipsin,
            'month_branch' => $monthBranch,
            'bong_gan' => $bongGan,
            'name' => $gInfo['name'],
            'description' => $gInfo['desc'],
            'detail' => $gInfo['detail'],
            'quality' => $quality,
        ];
    }

    private function assessGyeokgukQuality($mainSipsin, $dist, $isStrong) {
        $mainVal = $dist[$mainSipsin] ?? 0;
        if ($mainVal >= 2.0) {
            if (($mainSipsin === '정관' || $mainSipsin === '정인') && !$isStrong) {
                return ['level'=>'길격(吉格)', 'description'=>"격국의 기운이 충분하고 일간이 겸손하여, 좋은 환경에서 성장하는 구조입니다. 꾸준히 노력하면 큰 성과를 이룹니다."];
            } elseif (($mainSipsin === '식신' || $mainSipsin === '정재') && $isStrong) {
                return ['level'=>'길격(吉格)', 'description'=>"신강한 일간이 식신/정재를 활용하는 좋은 구조입니다. 재능과 노력이 결과로 이어집니다."];
            } elseif ($mainSipsin === '편관') {
                if (($dist['식신'] ?? 0) >= 1.0) {
                    return ['level'=>'대길격(大吉格)', 'description'=>"편관(칠살)을 식신이 적절히 제어하는 '식신제살' 구조로, 영웅의 사주입니다!"];
                } else {
                    return ['level'=>'흉격(凶格)', 'description'=>"칠살이 제어되지 않아 압력이 강하지만, 이를 극복하면 큰 인물이 됩니다. 위기가 곧 기회입니다."];
                }
            } else {
                return ['level'=>'성격(成格)', 'description'=>"격국이 성립하여 인생의 방향이 명확합니다. 대운에 따라 성과가 크게 달라집니다."];
            }
        }
        return ['level'=>'파격(破格)', 'description'=>"격국의 기운이 약하지만, 대운에서 도움이 올 때 역전의 기회가 있습니다. 포기하지 마세요!"];
    }

    // ================================================================
    //  대운(大運) 분석
    // ================================================================
    public function analyzeDaeun() {
        $gender = $this->engine->getGender();
        $yearStem = $this->engine->getYearPillar()[0];
        $monthStem = $this->engine->getMonthPillar()[0];
        $monthBranch = $this->engine->getMonthPillar()[1];

        $isYangMale = (SajuEngine::CHEONGAN_YINYANG[$yearStem] === 0 && $gender === 'male');
        $isYinFemale = (SajuEngine::CHEONGAN_YINYANG[$yearStem] === 1 && $gender === 'female');
        $forward = ($isYangMale || $isYinFemale);
        $startAge = $this->calculateDaeunStartAge();
        
        $dayElement = $this->result['day_master_element'];
        $dayYY = SajuEngine::CHEONGAN_YINYANG[$this->engine->getDayPillar()[0]];
        $dms = $this->result['day_master_strength'];
        $yongshinEl = $dms['yongshin']['element'] ?? '토';

        $daeunsDetailed = [];
        for ($i = 0; $i < 10; $i++) {
            $age = $startAge + ($i * 10);
            $stemIdx = $forward ? ($monthStem + $i + 1) % 10 : ($monthStem - $i - 1 + 100) % 10;
            $branchIdx = $forward ? ($monthBranch + $i + 1) % 12 : ($monthBranch - $i - 1 + 120) % 12;

            $stem = SajuEngine::CHEONGAN[$stemIdx];
            $branch = SajuEngine::JIJI[$branchIdx];
            $stemEl = SajuEngine::CHEONGAN_OHANG[$stem];
            $branchEl = SajuEngine::JIJI_OHANG[$branch];
            $stemSipsin = SajuEngine::getSipsin($dayElement, $dayYY, $stemEl, SajuEngine::CHEONGAN_YINYANG[$stemIdx]);
            $branchSipsin = SajuEngine::getSipsin($dayElement, $dayYY, $branchEl, SajuEngine::JIJI_YINYANG[$branchIdx]);
            $twelveStage = $this->engine->getTwelveStage($this->engine->getDayPillar()[0], $branchIdx);

            $relationships = $this->engine->analyzeRelationships(
                SajuEngine::JIJI[$branchIdx], SajuEngine::CHEONGAN[$stemIdx]
            );

            $stemIsYongshin = ($stemEl === $yongshinEl);
            $branchIsYongshin = false;
            foreach (SajuEngine::JIJANGGAN[$branch] as $item) {
                if (SajuEngine::CHEONGAN_OHANG[$item[0]] === $yongshinEl) { $branchIsYongshin = true; break; }
            }
            $isYongshinDaeun = ($stemIsYongshin || $branchIsYongshin);

            $score = $this->calculateDaeunScore($stemEl, $branchEl, $dayElement, $yongshinEl, $relationships, $twelveStage, $dms);

            $daeunsDetailed[] = [
                'index' => $i,
                'age_start' => $age,
                'age_end' => $age + 9,
                'stem' => $stem, 'branch' => $branch,
                'stem_hanja' => SajuEngine::CHEONGAN_HANJA[$stemIdx],
                'branch_hanja' => SajuEngine::JIJI_HANJA[$branchIdx],
                'stem_element' => $stemEl, 'branch_element' => $branchEl,
                'stem_sipsin' => $stemSipsin, 'branch_sipsin' => $branchSipsin,
                'twelve_stage' => $twelveStage,
                'is_yongshin' => $isYongshinDaeun,
                'score' => $score,
                'relationships' => $relationships,
                'interpretation' => $this->interpretDaeun($stemSipsin, $branchSipsin, $twelveStage, $isYongshinDaeun, $score, $relationships, $age),
            ];
        }

        // [v4] DaeunCombinationEngine 통합 — 원국×대운 조합 해석 추가
        $baseResult = [
            'direction' => $forward ? '순행' : '역행',
            'start_age' => $startAge,
            'daeuns' => $daeunsDetailed,
        ];

        try {
            $comboEngine = new DaeunCombinationEngine($this->engine, $this->result);
            $wongukSummary = $comboEngine->getWongukSummary();
            $baseResult['wonguk_patterns'] = $comboEngine->getWongukPatternNames();

            foreach ($baseResult['daeuns'] as &$d) {
                $comboInterp = $comboEngine->getCombinationInterpretation($d);
                $d['combination'] = $comboInterp;
                // 기존 interpretation 뒤에 조합 해석 추가
                if (!empty($comboInterp['narrative'])) {
                    $d['interpretation'] .= "\n\n" . $comboInterp['narrative'];
                }
            }
            unset($d);

            // 30년 흐름 분석 추가
            $baseResult['thirty_year_flow'] = $comboEngine->generate30YearFlow($baseResult['daeuns']);
        } catch (Exception $e) {
            // 조합 엔진 실패 시 기본 해석 유지
        }

        return $baseResult;
    }

    private function calculateDaeunStartAge() {
        $solarMonth = $this->result['solar_date']['month'];
        $day = $this->result['input']['day'];
        $gender = $this->engine->getGender();
        $yearStem = $this->engine->getYearPillar()[0];
        $isYangMale = (SajuEngine::CHEONGAN_YINYANG[$yearStem] === 0 && $gender === 'male');
        $isYinFemale = (SajuEngine::CHEONGAN_YINYANG[$yearStem] === 1 && $gender === 'female');
        $forward = ($isYangMale || $isYinFemale);
        $jeolgiDay = 5;
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

    private function calculateDaeunScore($stemEl, $branchEl, $dayElement, $yongshinEl, $relationships, $twelveStage, $dms) {
        $score = 50;
        if ($stemEl === $yongshinEl) $score += 15;
        if ($branchEl === $yongshinEl) $score += 10;
        $heeshinEl = $dms['heeshin']['element'] ?? '';
        if ($stemEl === $heeshinEl || $branchEl === $heeshinEl) $score += 8;
        $gishinEl = $dms['gishin']['element'] ?? '';
        if ($stemEl === $gishinEl) $score -= 12;
        if ($branchEl === $gishinEl) $score -= 8;
        $gushinEl = $dms['gushin']['element'] ?? '';
        if ($stemEl === $gushinEl || $branchEl === $gushinEl) $score -= 5;
        $stageScore = ['장생'=>8,'관대'=>6,'건록'=>10,'제왕'=>7,'목욕'=>-3,'쇠'=>-2,'병'=>-5,'사'=>-8,'묘'=>-10,'절'=>-6,'태'=>2,'양'=>4];
        $score += ($stageScore[$twelveStage] ?? 0);
        foreach ($relationships as $rel) {
            switch ($rel['type']) {
                case '육합': case '삼합': case '방합': case '천간합': $score += 5; break;
                case '충': $score -= 8; break;
                case '형': $score -= 6; break;
                case '해': $score -= 4; break;
                case '파': $score -= 3; break;
                case '천간충': $score -= 5; break;
            }
        }
        return max(10, min(95, $score));
    }

    private function interpretDaeun($stemSipsin, $branchSipsin, $twelveStage, $isYongshin, $score, $relationships, $age) {
        $level = $score >= 75 ? '매우 좋음' : ($score >= 60 ? '좋음' : ($score >= 45 ? '보통' : ($score >= 30 ? '주의' : '어려움')));
        $emoji = ['매우 좋음'=>'🌟','좋음'=>'☀️','보통'=>'☁️','주의'=>'🌧️','어려움'=>'⛈️'][$level];

        // 십성을 일상적 언어로 설명
        $sipsinFriendly = [
            '비견'=>['이름'=>'나 자신의 힘','비유'=>'마치 같은 길을 걷는 동료를 만난 것처럼, 자기 자신의 에너지가 강해지는 시기입니다. 자존감이 높아지고 독립적으로 움직이게 됩니다.','키워드'=>'자신감·독립심·동료와의 경쟁'],
            '겁재'=>['이름'=>'도전과 승부','비유'=>'마치 결승선을 눈앞에 둔 주자처럼, 승부욕이 불타오르는 시기입니다. 대담한 행동력이 생기지만, 무리하면 손해를 볼 수 있습니다.','키워드'=>'대담함·경쟁심·파트너십'],
            '식신'=>['이름'=>'재능과 풍요','비유'=>'마치 따뜻한 봄날에 꽃이 피어나듯, 당신의 재능이 자연스럽게 표현되는 풍요로운 시기입니다. 먹을 복이 있고, 마음이 평안합니다.','키워드'=>'표현력·창의성·여유·먹복'],
            '상관'=>['이름'=>'자유와 변화','비유'=>'마치 새장을 벗어난 새처럼, 기존의 틀을 깨고 자유를 향해 날아가고 싶은 시기입니다. 창의력이 폭발하지만, 말조심이 필요합니다.','키워드'=>'창의력·언변·자유·반항기'],
            '편재'=>['이름'=>'기회와 재물','비유'=>'마치 넓은 바다에서 물고기가 몰려오듯, 크고 작은 재물의 기회가 찾아오는 시기입니다. 사람을 통해 돈이 들어오며, 사업 수완이 발휘됩니다.','키워드'=>'투자기회·사업번창·사교활동'],
            '정재'=>['이름'=>'안정적 수입','비유'=>'마치 잘 가꾼 텃밭에서 열매를 수확하듯, 꾸준한 노력이 안정적인 수입으로 돌아오는 시기입니다. 저축과 재산 관리에 좋습니다.','키워드'=>'안정·근면·저축·가정운'],
            '편관'=>['이름'=>'시련과 성장','비유'=>'마치 거센 바람 속에서 뿌리가 더 깊이 내리듯, 외부의 압력과 도전이 오지만 그만큼 크게 성장하는 시기입니다. 위기가 곧 기회입니다.','키워드'=>'변화·도전·권력·스트레스'],
            '정관'=>['이름'=>'인정과 명예','비유'=>'마치 열심히 준비한 무대에 조명이 비추는 것처럼, 노력이 사회적으로 인정받는 시기입니다. 직장에서의 성과와 승진이 기대됩니다.','키워드'=>'직장운·명예·질서·책임감'],
            '편인'=>['이름'=>'깊은 배움','비유'=>'마치 깊은 숲 속에서 보석을 발견하듯, 남들과 다른 특별한 재능이나 지식을 얻게 되는 시기입니다. 직감이 예리해지고 영감이 따릅니다.','키워드'=>'특수재능·직감·연구·고독'],
            '정인'=>['이름'=>'지혜와 보호','비유'=>'마치 따뜻한 어머니의 품에 안긴 것처럼, 누군가의 도움과 보살핌을 받으며 내면이 성장하는 시기입니다. 공부하면 좋은 성과를 얻습니다.','키워드'=>'학업성취·지원·안정·어머니'],
        ];

        $stemFr = $sipsinFriendly[$stemSipsin] ?? ['이름'=>$stemSipsin,'비유'=>'','키워드'=>''];
        $branchFr = $sipsinFriendly[$branchSipsin] ?? ['이름'=>$branchSipsin,'비유'=>'','키워드'=>''];

        $ageLabel = '';
        if ($age < 10) $ageLabel = '어린 시절';
        elseif ($age < 20) $ageLabel = '10대 성장기';
        elseif ($age < 30) $ageLabel = '20대 도약기';
        elseif ($age < 40) $ageLabel = '30대 안정기';
        elseif ($age < 50) $ageLabel = '40대 결실기';
        elseif ($age < 60) $ageLabel = '50대 원숙기';
        elseif ($age < 70) $ageLabel = '60대 지혜기';
        else $ageLabel = '인생 완성기';

        $text = "{$emoji} {$age}~".($age+9)."세 [{$ageLabel}] — {$level} ({$score}점)\n\n";

        // 용신 대운 스토리텔링
        if ($isYongshin) {
            $text .= "⭐ 이 시기는 당신의 인생에서 가장 중요한 전환점이 될 수 있습니다!\n";
            $text .= "당신에게 가장 필요한 에너지가 찾아오는 시기입니다. ";
            $text .= "마치 오랫동안 기다리던 비가 메마른 땅을 적시듯, ";
            $text .= "그동안 정체되었던 일들이 술술 풀리기 시작합니다. ";
            $text .= "이 시기에 적극적으로 행동하면 인생을 바꿀 수 있는 큰 기회를 잡을 수 있습니다.\n\n";
        }

        // 겉으로 드러나는 흐름
        $text .= "🔹 이 시기에 겉으로 느끼게 되는 변화: \"{$stemFr['이름']}\"\n";
        $text .= "{$stemFr['비유']}\n";
        $text .= "✦ 핵심 키워드: {$stemFr['키워드']}\n\n";

        // 내면의 변화
        $text .= "🔹 이 시기에 내면에서 일어나는 변화: \"{$branchFr['이름']}\"\n";
        $text .= "{$branchFr['비유']}\n";
        $text .= "✦ 핵심 키워드: {$branchFr['키워드']}\n\n";

        // 12운성 (에너지 단계)
        $text .= "🔹 이 시기의 에너지 단계: \"{$twelveStage}\"\n";
        $text .= $this->getTwelveStageMeaning($twelveStage)."\n\n";

        // 대운 시기별 조언 — 개인적으로 와닿는 표현
        if ($age < 20) {
            $text .= "💬 이 시기에 당신에게 하고 싶은 말:\n";
            if ($score >= 60) {
                $text .= "학교생활이나 공부에서 좋은 환경이 만들어집니다. 이때 배운 것이 당신의 평생 자산이 될 수 있습니다. ";
                $text .= "호기심을 따라가되, 기본기를 탄탄히 다져놓으세요. 지금 심는 씨앗이 나중에 큰 나무가 됩니다.\n";
            } else {
                $text .= "당장은 힘들 수 있지만, 이 시기를 겪으면서 당신은 다른 사람들보다 훨씬 단단해집니다. ";
                $text .= "어린 시절의 어려움은 나중에 큰 힘이 됩니다. 포기하지 마세요.\n";
            }
        } elseif ($age < 40) {
            $text .= "💬 이 시기에 당신에게 하고 싶은 말:\n";
            if ($score >= 60) {
                $text .= "사회에서 자리를 잡고, 경력을 쌓아가기에 좋은 시기입니다. ";
                $text .= "기회가 올 때 망설이지 말고 잡으세요. 열정과 체력이 함께하는 이 시기는 다시 오지 않습니다. ";
                $text .= "인생의 기초 자산(경력, 인맥, 재산)을 이때 최대한 쌓아두세요.\n";
            } else {
                $text .= "시행착오가 많을 수 있지만, 이것은 성장통입니다. ";
                $text .= "실패를 두려워하지 말되, 같은 실수를 반복하지 않는 것이 핵심입니다. ";
                $text .= "이 시기를 잘 버티면 40대 이후에 빛을 발하게 됩니다.\n";
            }
        } elseif ($age < 60) {
            $text .= "💬 이 시기에 당신에게 하고 싶은 말:\n";
            if ($score >= 60) {
                $text .= "그동안 쌓아온 것들이 결실을 맺는 보람찬 시기입니다. ";
                $text .= "경험에서 우러나오는 판단력이 빛을 발합니다. ";
                $text .= "욕심부리지 않으면서도 자신감 있게 나아가세요. 당신의 가치를 아는 사람들이 모여듭니다.\n";
            } else {
                $text .= "건강과 재정 관리에 특별히 신경 써야 합니다. ";
                $text .= "무리한 투자나 과로는 피하고, 이미 가지고 있는 것을 잘 지키는 것이 지혜입니다. ";
                $text .= "지금은 공격보다 수비가 중요한 시기입니다.\n";
            }
        } else {
            $text .= "💬 이 시기에 당신에게 하고 싶은 말:\n";
            if ($score >= 60) {
                $text .= "풍요로운 경험과 지혜가 빛나는 시기입니다. ";
                $text .= "삶의 의미를 되돌아보며, 주변 사람들에게 당신의 지혜를 나눠주세요. ";
                $text .= "존경받는 어른이 되어 평안한 나날을 보낼 수 있습니다.\n";
            } else {
                $text .= "무엇보다 건강이 가장 소중합니다. ";
                $text .= "무리하지 말고, 가족과 함께하는 소소한 일상에서 행복을 찾으세요. ";
                $text .= "지금까지 살아온 것만으로도 충분히 대단한 일입니다.\n";
            }
        }

        if (!empty($relationships)) {
            $text .= "\n🔗 이 시기에 발생하는 에너지 변화:\n";
            foreach ($relationships as $rel) {
                $relDesc = '';
                switch ($rel['type']) {
                    case '육합': case '삼합': case '방합': case '천간합':
                        $relDesc = '(좋은 변화 — 조화와 협력의 기운)';
                        break;
                    case '충': case '천간충':
                        $relDesc = '(주의 — 변화·이동·충돌의 기운)';
                        break;
                    case '형':
                        $relDesc = '(주의 — 마찰·갈등의 기운)';
                        break;
                    default:
                        $relDesc = '(변화의 기운)';
                }
                $text .= "  {$rel['type']} {$rel['chars']} {$relDesc}\n  → {$rel['meaning']}\n";
            }
        }

        return $text;
    }

    private function getTwelveStageMeaning($stage) {
        $meanings = [
            '장생'=>'새로운 시작과 희망의 에너지가 솟아납니다. 새로운 일을 시작하기에 최적의 시기입니다.',
            '목욕'=>'성장통과 시행착오의 시기입니다. 배우는 과정에서 유혹과 실수가 있을 수 있으나, 이것이 성장의 밑거름이 됩니다.',
            '관대'=>'성장과 확장의 시기입니다. 사회적 활동이 활발해지고, 인간관계가 넓어집니다.',
            '건록'=>'가장 활발하고 생산적인 시기! 수입이 안정되고 직업에서 성과를 냅니다. 인생의 전성기와 같습니다.',
            '제왕'=>'에너지가 정점에 달합니다. 최고조의 성과를 이루지만, 정상에서는 내려갈 준비도 해야 합니다.',
            '쇠'=>'기운이 서서히 약해지지만, 경험과 지혜는 깊어집니다. 무리하지 않으면 안정적인 시기입니다.',
            '병'=>'건강과 기력에 주의가 필요합니다. 쉬어가는 것이 지혜입니다. 몸의 신호에 귀 기울이세요.',
            '사'=>'하나의 시기가 마무리됩니다. 집착을 내려놓고 새로운 준비를 시작하세요.',
            '묘'=>'잠시 멈추어 서는 시기입니다. 내면의 성찰과 재충전이 필요합니다. 겨울이 있어야 봄이 오듯이.',
            '절'=>'가장 약한 시기이지만, 어둠이 깊을수록 새벽은 가깝습니다. 씨앗이 땅 속에서 발아를 준비하는 인고의 시간입니다.',
            '태'=>'새로운 가능성이 잉태됩니다. 아직은 눈에 보이지 않지만, 내면에서 새로운 무언가가 자라고 있습니다.',
            '양'=>'준비와 성장의 시기. 서두르지 말고 실력을 기르며 때를 기다리세요. 봄은 반드시 옵니다.',
        ];
        return $meanings[$stage] ?? '';
    }

    // ================================================================
    //  세운(歲運) 분석
    // ================================================================
    public function analyzeSeun($targetYear = null) {
        if ($targetYear === null) $targetYear = (int)date('Y');
        $dayElement = $this->result['day_master_element'];
        $dayYY = SajuEngine::CHEONGAN_YINYANG[$this->engine->getDayPillar()[0]];
        $dms = $this->result['day_master_strength'];
        $yongshinEl = $dms['yongshin']['element'] ?? '토';

        $seuns = [];
        for ($y = $targetYear; $y < $targetYear + 5; $y++) {
            $stemIdx = (($y - 4) % 10 + 10) % 10;
            $branchIdx = (($y - 4) % 12 + 12) % 12;
            $stem = SajuEngine::CHEONGAN[$stemIdx];
            $branch = SajuEngine::JIJI[$branchIdx];
            $stemEl = SajuEngine::CHEONGAN_OHANG[$stem];
            $branchEl = SajuEngine::JIJI_OHANG[$branch];
            $stemSipsin = SajuEngine::getSipsin($dayElement, $dayYY, $stemEl, SajuEngine::CHEONGAN_YINYANG[$stemIdx]);
            $branchSipsin = SajuEngine::getSipsin($dayElement, $dayYY, $branchEl, SajuEngine::JIJI_YINYANG[$branchIdx]);
            $zodiac = SajuEngine::ZODIAC_ANIMALS[$branch];

            $relationships = $this->engine->analyzeRelationships(
                SajuEngine::JIJI[$branchIdx], SajuEngine::CHEONGAN[$stemIdx]
            );

            $isYongshin = ($stemEl === $yongshinEl || $branchEl === $yongshinEl);
            $score = $this->calculateSeunScore($stemEl, $branchEl, $yongshinEl, $dms, $relationships);
            $monthlyHighlight = $this->getMonthlyHighlight($y, $dayElement, $dayYY, $yongshinEl);

            $seuns[] = [
                'year' => $y,
                'stem' => $stem, 'branch' => $branch,
                'stem_hanja' => SajuEngine::CHEONGAN_HANJA[$stemIdx],
                'branch_hanja' => SajuEngine::JIJI_HANJA[$branchIdx],
                'stem_element' => $stemEl, 'branch_element' => $branchEl,
                'stem_sipsin' => $stemSipsin, 'branch_sipsin' => $branchSipsin,
                'zodiac' => $zodiac,
                'is_yongshin' => $isYongshin,
                'score' => $score,
                'relationships' => $relationships,
                'monthly_highlight' => $monthlyHighlight,
                'interpretation' => $this->interpretSeun($y,$stem,$branch,$stemSipsin,$branchSipsin,$isYongshin,$score,$relationships,$zodiac),
            ];
        }
        return $seuns;
    }

    public function analyzeMonthlyFortunes($targetYear = null) {
        if ($targetYear === null) $targetYear = (int)date('Y');

        $dayElement = $this->result['day_master_element'];
        $dayYY = SajuEngine::CHEONGAN_YINYANG[$this->engine->getDayPillar()[0]];
        $dms = $this->result['day_master_strength'];
        $yongshinEl = $dms['yongshin']['element'] ?? '토';

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $branchIdx = ($month + 1) % 12;
            $branch = SajuEngine::JIJI[$branchIdx];
            $branchEl = SajuEngine::JIJI_OHANG[$branch];
            $branchSipsin = SajuEngine::getSipsin($dayElement, $dayYY, $branchEl, SajuEngine::JIJI_YINYANG[$branchIdx]);
            $score = $this->calculateMonthlyFortuneScore($branchEl, $yongshinEl, $dms, $month);

            $months[] = [
                'year' => $targetYear,
                'month' => $month,
                'branch' => $branch,
                'branch_hanja' => SajuEngine::JIJI_HANJA[$branchIdx],
                'branch_element' => $branchEl,
                'branch_sipsin' => $branchSipsin,
                'score' => $score,
                'label' => $this->monthlyMoodLabel($score),
                'focus' => $this->describeMonthlyFortune($month, $branchEl, $branchSipsin, $score, $yongshinEl),
            ];
        }

        return $months;
    }

    private function calculateSeunScore($stemEl, $branchEl, $yongshinEl, $dms, $relationships) {
        $score = 50;
        if ($stemEl === $yongshinEl) $score += 15;
        if ($branchEl === $yongshinEl) $score += 10;
        $heeshinEl = $dms['heeshin']['element'] ?? '';
        if ($stemEl === $heeshinEl || $branchEl === $heeshinEl) $score += 8;
        $gishinEl = $dms['gishin']['element'] ?? '';
        if ($stemEl === $gishinEl) $score -= 12;
        if ($branchEl === $gishinEl) $score -= 8;
        foreach ($relationships as $rel) {
            switch ($rel['type']) {
                case '육합': case '삼합': case '천간합': $score += 5; break;
                case '충': $score -= 8; break;
                case '형': $score -= 6; break;
                case '해': $score -= 4; break;
                case '파': case '천간충': $score -= 3; break;
            }
        }
        return max(10, min(95, $score));
    }

    private function calculateMonthlyFortuneScore($branchEl, $yongshinEl, $dms, $month) {
        $score = 50;
        if ($branchEl === $yongshinEl) $score += 15;

        $heeshinEl = $dms['heeshin']['element'] ?? '';
        if ($branchEl === $heeshinEl) $score += 8;

        $gishinEl = $dms['gishin']['element'] ?? '';
        if ($branchEl === $gishinEl) $score -= 10;

        $gushinEl = $dms['gushin']['element'] ?? '';
        if ($branchEl === $gushinEl) $score -= 5;

        if (in_array($month, [3, 6, 9, 12], true)) $score += 2;
        if (in_array($month, [1, 7], true)) $score += 1;

        return max(10, min(95, $score));
    }

    private function monthlyMoodLabel($score) {
        if ($score >= 80) return '강하게 밀어붙이기 좋은 달';
        if ($score >= 65) return '기회가 잘 붙는 달';
        if ($score >= 50) return '안정적으로 가기 좋은 달';
        if ($score >= 35) return '속도 조절이 필요한 달';
        return '몸과 마음을 아껴야 하는 달';
    }

    private function describeMonthlyFortune($month, $branchEl, $branchSipsin, $score, $yongshinEl) {
        $elementFocus = [
            '목' => '새로운 시작과 관계 확장이 눈에 들어오는 흐름입니다.',
            '화' => '표현, 발표, 대외 활동을 통해 존재감이 커지는 흐름입니다.',
            '토' => '생활 기반과 일정 정리가 중요한 흐름입니다.',
            '금' => '판단, 계약, 정리, 시험처럼 결과를 가르는 일이 강조됩니다.',
            '수' => '휴식, 회복, 공부, 내면 정리에 힘이 실리는 흐름입니다.',
        ];
        $sipsinMeaning = SajuEngine::SIPSIN_INFO[$branchSipsin]['meaning'] ?? $branchSipsin;

        $prefix = $score >= 65
            ? "{$month}월은 비교적 흐름이 부드럽습니다."
            : ($score >= 45 ? "{$month}월은 무난하지만 페이스 조절이 중요합니다." : "{$month}월은 욕심보다 정비가 먼저인 시기입니다.");

        $yongshinLine = $branchEl === $yongshinEl
            ? '지금 필요한 에너지가 들어와 힘을 받기 쉽습니다.'
            : '내게 꼭 맞는 기운은 아니니 무리하게 밀어붙이기보다 준비와 정리에 비중을 두는 편이 좋습니다.';

        return $prefix . ' ' . ($elementFocus[$branchEl] ?? '') . ' 특히 ' . $sipsinMeaning . ' 주제가 드러나기 쉽고, ' . $yongshinLine;
    }

    private function getMonthlyHighlight($year, $dayElement, $dayYY, $yongshinEl) {
        $bestMonth = 1; $bestScore = 0; $worstMonth = 1; $worstScore = 100;
        for ($m = 1; $m <= 12; $m++) {
            $monthBranch = ($m + 1) % 12;
            $branchEl = SajuEngine::JIJI_OHANG[SajuEngine::JIJI[$monthBranch]];
            $s = 50;
            if ($branchEl === $yongshinEl) $s += 15;
            $gishinEl = SajuEngine::OHANG[((array_search($yongshinEl, SajuEngine::OHANG) + 2) % 5)];
            if ($branchEl === $gishinEl) $s -= 10;
            if ($s > $bestScore) { $bestScore = $s; $bestMonth = $m; }
            if ($s < $worstScore) { $worstScore = $s; $worstMonth = $m; }
        }
        return ['best_month'=>$bestMonth, 'worst_month'=>$worstMonth];
    }

    private function interpretSeun($year,$stem,$branch,$stemSipsin,$branchSipsin,$isYongshin,$score,$rels,$zodiac) {
        $level = $score >= 75 ? '대길' : ($score >= 60 ? '길' : ($score >= 45 ? '보통' : ($score >= 30 ? '소흉' : '흉')));
        $emoji = ['대길'=>'🌟','길'=>'☀️','보통'=>'☁️','소흉'=>'🌧️','흉'=>'⛈️'][$level];
        $sipsinInfo = SajuEngine::SIPSIN_INFO[$stemSipsin] ?? ['meaning'=>''];
        $sipsinInfo2 = SajuEngine::SIPSIN_INFO[$branchSipsin] ?? ['meaning'=>''];

        $text = "{$emoji} {$year}년 ({$stem}{$branch}, {$zodiac}띠 해) — {$level} ({$score}점)\n\n";

        // 한 해 요약 스토리텔링
        if ($score >= 75) {
            $text .= "🌟 올해는 당신에게 행운이 가득한 해입니다!\n";
            $text .= "하늘이 돕는 기운이 강하게 흐르고 있어, 새로운 시작이나 큰 결정을 해도 좋은 결과를 얻을 수 있습니다.\n\n";
        } elseif ($score >= 60) {
            $text .= "☀️ 전반적으로 순조로운 한 해가 예상됩니다.\n";
            $text .= "큰 행운은 아니지만 꾸준히 노력하면 좋은 성과를 거둘 수 있는 한 해입니다.\n\n";
        } elseif ($score >= 45) {
            $text .= "☁️ 평범하지만 안정적인 한 해입니다.\n";
            $text .= "큰 변화보다는 현재의 것을 유지하고 다지는 데 집중하는 것이 좋습니다.\n\n";
        } elseif ($score >= 30) {
            $text .= "🌧️ 조금 주의가 필요한 한 해입니다.\n";
            $text .= "큰 모험보다는 안정을 추구하고, 건강과 재정 관리에 신경 쓰세요.\n\n";
        } else {
            $text .= "⛈️ 인내가 필요한 한 해입니다.\n";
            $text .= "어려움이 있을 수 있지만, 이것은 성장을 위한 시련입니다. 이 시기를 잘 버티면 다음 해에 반드시 좋아집니다.\n\n";
        }

        $text .= "📌 올해의 천간: {$stemSipsin} — {$sipsinInfo['meaning']}\n";
        $text .= "📌 올해의 지지: {$branchSipsin} — {$sipsinInfo2['meaning']}\n\n";

        if ($isYongshin) {
            $text .= "✅ 올해는 용신(用神)의 해! 당신에게 가장 필요한 기운이 하늘에서 내려오는 해입니다.\n";
            $text .= "이 기회를 놓치지 마세요. 평소 하고 싶었던 일을 시작하기에 최적의 타이밍입니다.\n\n";
        }

        // 합충형파해
        $hasChung = false; $hasHap = false;
        foreach ($rels as $r) {
            if ($r['type'] === '충') $hasChung = true;
            if (in_array($r['type'], ['육합','삼합','천간합'])) $hasHap = true;
        }
        if ($hasChung) {
            $text .= "⚡ 충(沖)이 있습니다! 올해는 변동과 움직임이 많은 해입니다.\n";
            $text .= "이사, 이직, 여행 등 움직임이 생길 수 있습니다. 갑작스러운 변화에 대비하세요.\n";
            $text .= "하지만 충은 '변화'이지 반드시 '나쁜 것'은 아닙니다. 때로는 변화가 도약의 계기가 됩니다.\n\n";
        }
        if ($hasHap) {
            $text .= "💛 합(合)이 있습니다! 새로운 인연이나 협력의 기회가 생깁니다.\n";
            $text .= "좋은 사람을 만나거나, 의미 있는 파트너십을 형성할 수 있는 해입니다.\n\n";
        }

        return $text;
    }

    // ================================================================
    //  종합 운세 (getComprehensiveFortune) — 풍부한 스토리텔링
    // ================================================================
    public function getComprehensiveFortune() {
        $sipsin = $this->analyzeSipsin();
        $dist = $sipsin['distribution'];
        $groups = $sipsin['group_totals'];
        $dms = $this->result['day_master_strength'];
        $dayElement = $this->result['day_master_element'];
        $dayMaster = $this->result['day_master'];
        $isStrong = $dms['is_strong'];
        $dayProp = OhangAnalysis::OHANG_PROPERTIES[$dayElement];
        $yongshinEl = $dms['yongshin']['element'] ?? '토';
        $yongshinProp = OhangAnalysis::OHANG_PROPERTIES[$yongshinEl];

        $bigyeop  = $groups['비겁(比劫)'] ?? 0;
        $siksang  = $groups['식상(食傷)'] ?? 0;
        $jaesung  = $groups['재성(財星)'] ?? 0;
        $gwansung = $groups['관성(官星)'] ?? 0;
        $insung   = $groups['인성(印星)'] ?? 0;
        $gender = $this->engine->getGender();
        $genderLabel = $gender === 'male' ? '남성' : '여성';
        $dmHanja = SajuEngine::CHEONGAN_HANJA[array_search($dayMaster, SajuEngine::CHEONGAN)];

        // ===================== 성격 분석 =====================
        $personality = "당신의 일간(日干)은 {$dayMaster}({$dmHanja}, {$dayElement})입니다.\n\n";
        $personality .= "일간은 사주팔자에서 '나 자신'을 나타내는 가장 핵심적인 글자입니다. ";
        $personality .= "당신이라는 사람의 본질, 타고난 기질, 세상을 대하는 방식이 모두 여기에 담겨 있습니다.\n\n";

        // 오행별 성격 스토리텔링
        $elementStories = [
            '목' => "나무(木)는 봄의 기운입니다. 당신은 끊임없이 성장하고 올라가려는 에너지를 타고났습니다. ".
                    "나무가 하늘을 향해 쭉쭉 뻗어가듯, 목표를 향한 추진력이 강하고, 시작하는 힘이 뛰어납니다.\n\n".
                    "인자하고 따뜻한 마음을 가졌으며, 남을 돕는 것을 좋아합니다. ".
                    "하지만 나무가 굽히면 부러지듯, 타협이 서투르고 올곧은 성격이 때로는 고집으로 보일 수 있습니다.",
            '화' => "불(火)은 여름의 기운입니다. 당신은 뜨거운 열정과 밝은 에너지를 타고났습니다. ".
                    "마치 태양처럼 주변을 환하게 비추며, 사람들에게 희망과 활기를 줍니다.\n\n".
                    "예의 바르고 밝으며 사교성이 좋습니다. 어디를 가든 분위기를 이끄는 중심 인물이 됩니다. ".
                    "하지만 불처럼 쉽게 타오르고 쉽게 꺼질 수 있어, 끈기와 인내심을 기르는 것이 과제입니다.",
            '토' => "흙(土)은 사계절 모두에 걸친 기운입니다. 당신은 중심을 잡아주는 안정적이고 믿음직한 사람입니다. ".
                    "대지가 모든 것을 품듯이, 당신은 포용력이 크고 주변 사람들의 중재자 역할을 합니다.\n\n".
                    "신뢰가 가는 성격으로, 사람들이 자연스럽게 당신에게 의지합니다. ".
                    "하지만 변화에 다소 둔감하고 보수적일 수 있으며, 결정이 느린 편입니다.",
            '금' => "쇠(金)는 가을의 기운입니다. 당신은 단호하고 결단력 있으며, 정의로운 성격을 타고났습니다. ".
                    "칼처럼 날카로운 판단력으로 옳고 그름을 명확히 가릅니다.\n\n".
                    "의리가 있고 약속을 중시하며, 한번 결심하면 끝까지 밀어붙이는 추진력이 있습니다. ".
                    "하지만 너무 날카로워서 말로 상처를 줄 수 있고, 유연성이 부족할 때가 있습니다.",
            '수' => "물(水)은 겨울의 기운입니다. 당신은 지혜롭고 유연하며, 상황 적응 능력이 탁월합니다. ".
                    "물이 어떤 그릇에도 담기듯, 당신은 어떤 환경에서든 적응하고 자신의 길을 찾아갑니다.\n\n".
                    "통찰력이 깊고 눈치가 빠르며, 사람의 마음을 잘 읽습니다. ".
                    "하지만 물이 한곳에 머물지 못하듯, 변덕스럽거나 한 가지에 오래 집중하지 못할 수 있습니다.",
        ];
        $personality .= ($elementStories[$dayElement] ?? '')."\n\n";
        $personality .= "━━━ 당신의 에너지 타입 ━━━\n\n";
        $personality .= $isStrong
            ? "{$dayProp['personality_strong']}\n\n당신은 에너지가 넘치는 타입입니다. 남에게 의존하지 않고 스스로 길을 개척하는 힘이 있습니다. 마치 자기 힘으로 산을 오르는 등산가처럼, 어떤 어려움도 직접 돌파하려 합니다."
            : "{$dayProp['personality_weak']}\n\n당신은 섬세하고 전략적인 타입입니다. 혼자 힘으로 밀어붙이기보다, 좋은 사람들과 협력하며 시너지를 만드는 것이 당신에게 유리합니다. 마치 물이 바위를 돌아가듯, 유연한 접근이 당신의 강점입니다.";
        $personality .= "\n\n";

        // 십성 추가 성격
        $traits = [];
        if ($bigyeop >= 2.5) $traits[] = "비겁이 강하여 자존심과 독립심이 매우 높습니다. 리더가 되려는 본능이 강하며, 자신만의 방식을 고수합니다.";
        if ($siksang >= 2.5) $traits[] = "식상이 강하여 표현 욕구가 넘칩니다. 말을 잘하고, 글을 잘 쓰며, 예술적 감각이 뛰어납니다. 창의적인 아이디어가 끊이지 않습니다.";
        if ($jaesung >= 2.5) $traits[] = "재성이 강하여 현실 감각이 뛰어납니다. 돈과 가치에 대한 감각이 예리하며, 실질적 이익을 추구합니다.";
        if ($gwansung >= 2.5) $traits[] = "관성이 강하여 책임감과 명예욕이 큽니다. 사회적 인정을 받고 싶은 욕구가 강하며, 규율을 중시합니다.";
        if ($insung >= 2.5) $traits[] = "인성이 강하여 학자적 기질이 있습니다. 깊이 생각하고 배우는 것을 좋아하지만, 행동보다 생각이 앞설 때가 있습니다.";
        if (!empty($traits)) {
            $personality .= "━━━ 당신만의 특별한 성격 포인트 ━━━\n\n";
            foreach ($traits as $t) $personality .= "• {$t}\n\n";
        }

        // ===================== 연애·결혼 분석 =====================
        $love = "사주에서 연애와 결혼은 단순한 운이 아니라, 당신의 내면에 새겨진 '관계의 패턴'입니다.\n\n";

        if ($gender === 'male') {
            $love .= "━━━ 당신의 연애·결혼 이야기 ━━━\n\n";
            $love .= "당신의 사주에서 '돈과 재물을 관리하는 에너지'가 곧 이성 인연의 크기를 보여줍니다.\n\n";
            if ($jaesung >= 3.0) {
                $love .= "💕 당신의 사주에는 재성이 매우 풍부합니다!\n\n";
                $love .= "이것은 여성과의 인연이 많다는 뜻입니다. 이성에게 매력적으로 보이며, ";
                $love .= "연애 기회가 자주 찾아옵니다. 하지만 너무 많은 인연은 오히려 혼란을 줄 수 있습니다.\n\n";
                $love .= "정재(正財)가 강하면 → 현모양처형, 가정적이고 성실한 배우자를 만납니다.\n";
                $love .= "편재(偏財)가 강하면 → 활동적이고 사교적인, 자기 일이 있는 배우자를 만납니다.\n\n";
                $love .= "💡 조언: 여러 인연 중 진정한 인연을 알아보는 지혜가 필요합니다. 설레임에만 끌리지 말고, 함께 성장할 수 있는 사람을 선택하세요.";
            } elseif ($jaesung >= 1.5) {
                $love .= "재성이 적절하여 안정적이고 좋은 연애·결혼운입니다.\n\n";
                $love .= "극적인 로맨스보다는 편안하고 따뜻한 사랑을 합니다. ";
                $love .= "서로를 존중하며 함께 성장하는 관계가 당신에게 맞습니다.\n";
                $love .= "배우자와 함께 가정을 꾸리면 안정적이고 화목한 가정을 이룰 수 있습니다.";
            } else {
                $love .= "재성이 약하여 연애의 시작이 쉽지 않을 수 있습니다.\n\n";
                $love .= "이것은 매력이 없다는 뜻이 아닙니다! 단지 인연의 시기가 정해져 있다는 것입니다.\n";
                $love .= "대운이나 세운에서 재성(편재·정재)이 들어오는 시기에 좋은 만남이 있습니다.\n\n";
                $love .= "💡 그때를 위해 자기 자신을 가꾸고 사교 범위를 넓혀두세요.";
            }
        } else {
            $love .= "━━━ 당신의 연애·결혼 이야기 ━━━\n\n";
            $love .= "당신의 사주에서 '사회적 지위와 책임의 에너지'가 곧 이성 인연의 크기를 보여줍니다.\n\n";
            if ($gwansung >= 3.0) {
                $love .= "💕 당신의 사주에는 관성이 매우 풍부합니다!\n\n";
                $love .= "남성과의 인연이 많고, 매력적인 분위기를 풍깁니다. ";
                $love .= "하지만 관성이 혼잡하면 연애에서 복잡한 상황이 생길 수 있습니다.\n\n";
                $love .= "정관(正官)이 강하면 → 안정적이고 믿음직한, 사회적으로 존경받는 배우자를 만납니다.\n";
                $love .= "편관(偏官)이 강하면 → 카리스마 있고 능력 있지만, 파란만장한 관계가 될 수 있습니다.\n\n";
                $love .= "💡 조언: 정관 하나만 깨끗이 있는 것이 가장 이상적입니다. 감정에 휩쓸리지 말고 현명하게 선택하세요.";
            } elseif ($gwansung >= 1.5) {
                $love .= "관성이 적절하여 안정적이고 좋은 연애·결혼운입니다.\n\n";
                $love .= "배려심 있고 성실한 배우자를 만날 가능성이 높습니다. ";
                $love .= "결혼 후에도 서로를 존중하는 좋은 관계를 유지할 수 있습니다.";
            } else {
                $love .= "관성이 약하여 결혼이 다소 늦어지거나, 인연이 더디 올 수 있습니다.\n\n";
                $love .= "하지만 대운에서 관성이 올 때 인생의 반려자를 만나게 됩니다.\n\n";
                $love .= "💡 조언: 너무 조급해하지 마세요. 학업이나 커리어에 집중하면 자연스럽게 좋은 인연이 따라옵니다.";
            }
        }
        $love .= "\n\n";

        // 공통 조합
        if ($siksang >= 2.0 && $gwansung >= 1.5) {
            $love .= "⚡ 상관견관(傷官見官)의 기운이 있습니다.\n";
            $love .= "연애에서 매우 주도적이고 자기 주관이 강합니다. 상대방에게 직설적으로 말하는 편이어서 ";
            $love .= "의도치 않게 갈등을 만들 수 있습니다. 때로는 한 발 물러나 상대의 이야기를 경청하는 것이 관계의 열쇠입니다.\n\n";
        }
        if ($bigyeop >= 2.5) {
            $love .= "💡 비겁이 강하여 연애에서 경쟁자가 나타날 수 있습니다. ".
                "하지만 진심을 다하면 결국 승리합니다. 외모보다 진정성으로 승부하세요.\n\n";
        }
        if ($insung >= 2.5) {
            $love .= "📚 인성이 강하여 지적인 대화가 통하는 사람에게 끌립니다. ".
                "서로의 성장을 돕는 '소울메이트'형 관계가 이상적입니다.\n\n";
        }

        // ===================== 직업·적성 분석 =====================
        $career = "사주에서 직업은 '먹고사는 문제'를 넘어 '내 인생의 소명'을 찾는 것과 같습니다.\n\n";
        $career .= "당신의 일간 {$dayMaster}({$dayElement})와 십성 분포를 분석한 결과, ";
        $career .= "다음과 같은 직업 적성이 나타납니다.\n\n";

        // 주요 추천
        $careerSections = [];
        if ($siksang >= 2.0) {
            $careerSections[] = "🎨 창작·표현 분야 — 당신의 아이디어가 곧 경쟁력\n".
                "당신에게는 마치 샘물처럼 끊임없이 솟아나는 표현력과 창의력이 있습니다. ".
                "머릿속에 떠오르는 아이디어를 현실로 바꾸는 능력이 남다릅니다.\n".
                "▸ 잘 맞는 직업: 작가, 강사, 유튜버, 디자이너, 마케터, 기획자, 연예인, PD\n".
                "▸ 특히 재능을 돈으로 연결하는 능력이 함께 있다면, 프리랜서나 전문직으로 높은 수입도 가능합니다";
        }
        if ($jaesung >= 2.0) {
            $careerSections[] = "💰 사업·재물 분야 — 돈의 흐름을 읽는 직감\n".
                "마치 물고기가 물살을 타듯, 돈이 어디로 흘러가는지 본능적으로 느끼는 감각이 있습니다. ".
                "투자, 사업, 세일즈 등에서 남들보다 한 발 빠르게 기회를 잡습니다.\n".
                "▸ 잘 맞는 직업: 사업가, 투자자, 부동산, 금융인, 무역, 유통, 세일즈\n".
                "▸ 큰 스케일의 도전을 좋아하는 타입이라면 사업을, 안정을 중시한다면 금융 분야를 추천합니다";
        }
        if ($gwansung >= 2.0) {
            $careerSections[] = "🏛 조직·공직 분야 — 사회 속에서 빛나는 리더십\n".
                "마치 오케스트라의 지휘자처럼, 조직 안에서 사람들을 이끌고 성과를 만들어내는 능력이 뛰어납니다. ".
                "사회적 인정과 지위를 얻기에 유리한 구조입니다.\n".
                "▸ 잘 맞는 직업: 공무원, 대기업, 군인, 경찰, 법조인, 행정가, 정치인\n".
                "▸ 자격증이나 시험을 통해 실력을 인정받으면 출세가 더 빨라집니다";
        }
        if ($insung >= 2.0) {
            $careerSections[] = "📚 학문·교육 분야 — 지식을 나누며 성장하는 사람\n".
                "마치 깊은 우물처럼 한 분야를 파고드는 집중력이 있으며, 그것을 사람들에게 전달하는 재능이 있습니다.\n".
                "▸ 잘 맞는 직업: 교수, 교사, 연구원, 의사, 약사, 상담사, 학자, 작가\n".
                "▸ 평생 배우고 성장하는 것 자체가 당신의 가장 큰 무기입니다";
        }
        if ($bigyeop >= 2.0) {
            $careerSections[] = "⚡ 독립·경쟁 분야 — 자기 길을 개척하는 사람\n".
                "마치 야생마처럼, 남의 지시를 받기보다는 자기만의 길을 걸어가려는 기질이 강합니다.\n".
                "▸ 잘 맞는 직업: 자영업, 프리랜서, 전문직, 운동선수, CEO, 1인 기업가\n".
                "▸ 동업보다 독자적으로 운영하는 것이 더 잘 맞습니다";
        }

        if (empty($careerSections)) {
            $careerSections[] = "🌈 십성이 고르게 분포되어 다방면의 재능을 가지고 있습니다.\n".
                "하나의 전문 분야를 정해 깊이 파고드는 것이 성공의 열쇠입니다.";
        }

        foreach ($careerSections as $sec) $career .= $sec."\n\n";

        $career .= "━━━ 당신에게 특히 유리한 방향 ━━━\n\n";
        $career .= "당신에게 가장 필요한 에너지는 '{$yongshinEl}'입니다. ";
        $career .= "이 에너지와 맞닿아 있는 {$yongshinProp['direction']}쪽 방향의 직장이나 사업이 유리합니다.\n\n";
        $elementJobs = [
            '목'=>'교육, 출판, 의류, 가구, 인테리어, 건축, 조경, 패션, 농업, 한의학',
            '화'=>'IT, 미디어, 에너지, 조명, 음식점, 미용, 패션, 엔터테인먼트, 광고, 마케팅',
            '토'=>'부동산, 건설, 농업, 유통, 중개, 보험, 재건축, 관광, 호텔, 물류',
            '금'=>'금융, 기계, 자동차, 전자, 의료기기, 보석, 법률, 군수, 반도체, 철강',
            '수'=>'무역, 수산업, 음료, 운송, 관광, 호텔, 서비스업, 수입, 해운, 유학',
        ];
        $career .= "🏢 구체적 추천 업종: ".$elementJobs[$yongshinEl]."\n";

        // ===================== 재물 분석 =====================
        $wealth = "재물운은 단순히 '돈이 많다/적다'가 아니라, '어떻게 벌고 어떻게 써야 하는가'에 대한 안내서입니다.\n\n";

        if ($jaesung >= 3.0) {
            $wealth .= "━━━ 재물 에너지: ★★★★★ 매우 풍부 ━━━\n\n";
            $wealth .= "당신은 마치 비옥한 땅과 같습니다 — 씨앗을 뿌리면 풍성하게 열매가 맺히는 타입이에요. ";
            $wealth .= "돈을 벌고 관리하는 감각이 타고났으며, 물질적 풍요를 누릴 가능성이 높습니다.\n\n";
            $wealth .= "다만, 돈에만 집중하면 삶의 다른 영역이 메마를 수 있어요. ";
            $wealth .= "가족·건강·자기 성장과의 균형을 잊지 마세요.";
        } elseif ($jaesung >= 2.0) {
            $wealth .= "━━━ 재물 에너지: ★★★★☆ 안정적 ━━━\n\n";
            $wealth .= "재물 감각이 좋고, 마치 벽돌을 쌓듯 꾸준한 노력으로 단단한 부를 만들어갈 수 있습니다. ";
            $wealth .= "한방에 대박보다는 차곡차곡 쌓아가는 방식이 당신에게 잘 맞아요.";
        } elseif ($jaesung >= 1.0) {
            $wealth .= "━━━ 재물 에너지: ★★★☆☆ 균형형 ━━━\n\n";
            $wealth .= "마치 조용한 강물처럼, 화려하지는 않지만 꾸준히 흐르는 재물운입니다. ";
            $wealth .= "무리한 투자보다는 성실한 노력과 꾸준한 저축이 당신의 부를 만들어줍니다.";
        } else {
            $wealth .= "━━━ 재물 에너지: ★★☆☆☆ 성장 가능형 ━━━\n\n";
            $wealth .= "지금 재물 에너지가 약하다고 걱정하지 마세요! 마치 봄을 기다리는 나무처럼, ";
            $wealth .= "자기 능력과 실력을 키우는 데 집중하면, 때가 왔을 때 큰 열매를 거둘 수 있습니다.";
        }
        $wealth .= "\n\n";

        // 특수 구조
        if ($siksang >= 2.0 && $jaesung >= 1.5) {
            $wealth .= "🎯 당신의 재능이 곧 돈이 됩니다!\n";
            $wealth .= "마치 자석이 쇠를 끌어당기듯, 당신이 실력을 키울수록 자연스럽게 수입이 따라옵니다. ";
            $wealth .= "자기계발이 곧 최고의 재테크예요.\n\n";
        }
        if ($bigyeop >= 2.0 && $jaesung >= 1.0) {
            $wealth .= "⚠ 돈이 들어오지만 빠져나가기도 쉬운 구조입니다\n";
            $wealth .= "마치 물이 새는 항아리처럼, 의식적으로 관리하지 않으면 돈이 빠져나갑니다. ";
            $wealth .= "자동 저축, 적금, 보험 등으로 '강제 저축'하는 시스템을 만드세요. 그리고 보증은 절대 서지 마세요!\n\n";
        }
        if ($gwansung >= 1.5 && $jaesung >= 1.5) {
            $wealth .= "🏆 재물과 명예, 두 마리 토끼를 잡을 수 있는 구조입니다!\n";
            $wealth .= "사회적 지위가 올라갈수록 재물도 함께 늘어나는 좋은 흐름을 타고 있어요.\n\n";
        }

        $wealth .= "💡 당신에게 맞는 재테크 방향:\n";
        $wealth .= "• {$yongshinProp['direction']}쪽 방향의 투자가 유리합니다\n";
        $wealth .= "• {$yongshinProp['color']} 계열의 지갑이나 수첩이 재물운을 도와줍니다\n";
        $wealth .= "• {$yongshinEl} 에너지와 관련된 업종에 투자를 고려해 보세요\n";

        // ===================== 학업·시험 분석 =====================
        $study = "학업운은 '머리가 좋다/나쁘다'의 문제가 아니라, '어떤 방식으로 배우는 것이 나에게 맞는가'에 대한 답입니다.\n\n";

        $studyScore = $insung + ($gwansung >= 1.0 ? 1.0 : 0);
        if ($studyScore >= 3.5) {
            $study .= "━━━ 학습 잠재력: ★★★★★ 매우 높음 ━━━\n\n";
            $study .= "당신은 마치 스펀지처럼 지식을 빠르게 흡수하는 능력이 있습니다! ";
            $study .= "시험운도 좋아서 자격증, 공무원 시험, 대학원 진학 등에서 빛을 발할 수 있어요. ";
            $study .= "타고난 지적 호기심이 당신의 가장 큰 자산입니다.";
        } elseif ($studyScore >= 2.5) {
            $study .= "━━━ 학습 잠재력: ★★★★☆ 높음 ━━━\n\n";
            $study .= "마치 잘 다듬어진 도구처럼, 공부의 기본기가 탄탄합니다. ";
            $study .= "여기에 꾸준한 노력이 더해지면 기대 이상의 성과를 거둘 수 있어요.";
        } elseif ($studyScore >= 1.5) {
            $study .= "━━━ 학습 잠재력: ★★★☆☆ 보통 ━━━\n\n";
            $study .= "호기심은 있지만 집중력이 관건인 타입이에요. ";
            $study .= "흥미를 느끼는 분야에서는 놀라울 정도로 몰입하는 힘이 있습니다.";
        } else {
            $study .= "━━━ 학습 잠재력: ★★☆☆☆ 실전형 ━━━\n\n";
            $study .= "교과서보다 현장! 이론보다 경험! ";
            $study .= "직접 부딪혀가며 배우는 것이 당신에게 훨씬 효과적인 학습법입니다.";
        }
        $study .= "\n\n";

        // 학습 스타일 추천
        $study .= "📝 당신에게 맞는 학습 스타일:\n\n";
        if ($insung >= 2.0) $study .= "• 깊이 있는 독서와 이론 학습이 효과적입니다. 도서관형 공부법이 잘 맞습니다.\n";
        if ($siksang >= 2.0) $study .= "• 토론, 발표, 강의하기 등 '아웃풋 중심' 학습이 효과적입니다. 가르치면서 배우는 타입입니다.\n";
        if ($jaesung >= 2.0) $study .= "• 실용적인 학습이 잘 맞습니다. \"이것을 배우면 어디에 쓰지?\"가 명확해야 동기부여가 됩니다.\n";
        if ($gwansung >= 2.0) $study .= "• 목표가 명확할 때 집중력이 극대화됩니다. 시험일, 목표 점수 등을 정해두세요.\n";
        if ($bigyeop >= 2.0) $study .= "• 경쟁 환경에서 실력이 발휘됩니다. 스터디 그룹이나 라이벌이 있으면 더 열심히 합니다.\n";

        if ($gwansung >= 1.5 && $insung >= 1.5) {
            $study .= "\n🎯 시험운이 특히 좋은 구조입니다! ";
            $study .= "공무원 시험, 자격증, 면접, 승진 등 '시험'이라는 이름이 붙는 것에서 강한 힘을 발휘합니다.\n";
        }

        // ===================== 건강 분석 =====================
        $healthText = "건강은 몸 안의 다섯 가지 에너지(목·화·토·금·수)가 얼마나 균형 잡혀 있느냐에 달려 있습니다. ";
        $healthText .= "부족하거나 너무 많은 에너지가 어떤 신체 부위에 영향을 주는지 살펴보겠습니다.\n\n";

        $ohangFriendlyHealth = [
            '목' => '🌳 나무 에너지 — 간·담·눈·근육',
            '화' => '🔥 불 에너지 — 심장·소장·혈액순환',
            '토' => '🏔 흙 에너지 — 위장·비장·소화기관',
            '금' => '⚙ 쇠 에너지 — 폐·대장·피부·호흡기',
            '수' => '💧 물 에너지 — 신장·방광·뼈·생식기',
        ];
        $healthData = $this->ohangData['health_analysis'] ?? [];
        foreach ($healthData as $oh => $hd) {
            if (!is_array($hd)) continue;
            $friendlyLabel = $ohangFriendlyHealth[$oh] ?? "{$oh} 에너지";
            $healthText .= "━━━ {$friendlyLabel} ━━━\n";
            $healthText .= "현재 상태: ".($hd['status'] ?? '보통')."\n";
            $healthText .= "관련 장기: ".($hd['organ'] ?? '')."\n";
            $healthText .= "관련 신체 부위: ".($hd['body_parts'] ?? '')."\n";
            if (!empty($hd['concern'])) {
                $healthText .= "💊 주의사항: {$hd['concern']}\n";
            }
            $healthText .= "\n";
        }

        $healthText .= "💡 건강 관리 핵심 포인트:\n";
        $healthText .= "• 당신에게 필요한 에너지({$yongshinEl})를 보충하는 음식과 활동을 늘려보세요\n";
        $healthText .= "• 과한 에너지를 자극하는 것은 줄이는 것이 좋습니다\n";
        $healthText .= "• 태어난 계절에 따라 약한 장기가 다릅니다 — 계절별 건강관리를 의식해 보세요\n";

        // ===================== 인생 흐름 =====================
        $lifeFlow = "당신의 인생은 마치 사계절이 있는 한 편의 영화와 같습니다. ";
        $lifeFlow .= "봄·여름·가을·겨울 — 각 시기마다 고유의 아름다움과 과제가 있어요.\n\n";

        $lifeFlow .= "━━━ 🌱 어린 시절 ~ 10대 (1~20세) ━━━\n";
        if ($isStrong) {
            $lifeFlow .= "마치 새싹이 힘차게 올라오듯, 에너지 넘치는 어린 시절이었을 거예요. ";
            $lifeFlow .= "또래보다 활발하고, '나는 이렇게 할 거야!'라는 자기주장이 강했을 가능성이 높습니다. ";
            $lifeFlow .= "운동이나 활동적인 취미에서 빛을 발했을 것입니다.\n\n";
        } else {
            $lifeFlow .= "마치 깊은 뿌리를 내리는 나무처럼, 겉으로는 조용했지만 내면에 풍부한 감성을 키워온 시기입니다. ";
            $lifeFlow .= "혼자만의 시간을 통해 깊은 생각과 감수성을 발전시켰을 것입니다.\n\n";
        }

        $lifeFlow .= "━━━ 🌿 20~30대, 사회로 나가는 시기 (21~40세) ━━━\n";
        if ($siksang + $jaesung >= 3.0) {
            $lifeFlow .= "마치 여름의 태양처럼 에너지가 폭발하는 시기! ";
            $lifeFlow .= "사회에서 자신의 재능을 마음껏 펼치며 경제적 기반을 다져갈 수 있습니다. ";
            $lifeFlow .= "이 시기에 얼마나 열심히 뛰느냐가 인생 전체의 기반을 결정합니다.\n\n";
        } elseif ($gwansung >= 2.0) {
            $lifeFlow .= "마치 계단을 한 칸씩 올라가듯, 조직과 사회 속에서 착실하게 위치를 잡아가는 시기입니다. ";
            $lifeFlow .= "노력한 만큼 인정받고, 한 단계씩 성장해 나갑니다.\n\n";
        } else {
            $lifeFlow .= "다양한 경험을 통해 '진짜 내가 원하는 것'을 찾아가는 여행과 같은 시기입니다. ";
            $lifeFlow .= "시행착오도 모두 소중한 자산이 될 거예요.\n\n";
        }

        $lifeFlow .= "━━━ 🍂 40~50대, 인생의 수확기 (41~60세) ━━━\n";
        $lifeFlow .= "마치 가을에 열매를 거두듯, 지금까지 쌓아온 것들의 결실을 맛보는 시기입니다. ";
        $lifeFlow .= "인생의 큰 전환점이 올 수 있으며, 이 시기에는 건강 관리와 재정 안정이 가장 중요한 두 기둥이에요.\n\n";

        $lifeFlow .= "━━━ ❄ 60대 이후, 인생의 완성 ━━━\n";
        if ($insung >= 1.5) {
            $lifeFlow .= "마치 눈 덮인 산처럼 고요하고 깊은 지혜가 빛나는 시기입니다. ";
            $lifeFlow .= "쌓아온 지식과 경험이 주변 사람들에게 큰 영감이 되며, 존경받는 삶을 살 수 있어요.\n\n";
        } elseif ($jaesung >= 2.0) {
            $lifeFlow .= "그동안 차곡차곡 모아온 것들로 여유롭고 풍요로운 시간을 보낼 수 있습니다. ";
            $lifeFlow .= "취미와 가족, 그리고 자신을 위한 시간을 충분히 누려보세요.\n\n";
        } else {
            $lifeFlow .= "마치 잔잔한 호수처럼, 평온하고 감사로 가득한 시간이 펼쳐집니다. ";
            $lifeFlow .= "건강을 가장 소중히 여기고, 마음의 평화를 추구하세요.\n\n";
        }

        $lifeFlow .= "✨ 기억하세요: 사주는 '정해진 운명'이 아니라 '인생의 지도'입니다. ";
        $lifeFlow .= "지도를 알면 더 현명하게 길을 선택할 수 있어요. ";
        $lifeFlow .= "결국, 당신의 선택이 당신의 운명을 만듭니다!";

        // ======================================================
        // [v4] 패턴 기반 해석 통합 — 개인별 고유 해석 생성
        // ======================================================
        $fortuneResult = [
            'personality' => $personality,
            'love' => $love,
            'career' => $career,
            'wealth' => $wealth,
            'study' => $study,
            'health' => $healthText,
            'life_flow' => $lifeFlow,
        ];

        try {
            $storyGen = new SajuStoryGenerator($this->engine);
            $story = $storyGen->generate();

            // 섹션 ID → content 맵 구성
            $storySections = [];
            foreach ($story['sections'] as $sec) {
                $storySections[$sec['id']] = $sec['content'] ?? '';
            }

            // 스토리 섹션 → 종합 운세 키 매핑
            $mapping = [
                'personality'  => 'personality',
                'career'       => 'career',
                'relationship' => 'love',
                'wealth'       => 'wealth',
                'life_flow'    => 'life_flow',
            ];

            foreach ($mapping as $storyId => $fortuneKey) {
                if (!empty($storySections[$storyId])) {
                    // 패턴 기반 해석을 앞에, 기존 해석을 뒤에
                    $fortuneResult[$fortuneKey] = $storySections[$storyId]
                        . "\n\n━━━ 전통 명리학 관점의 상세 해석 ━━━\n\n"
                        . $fortuneResult[$fortuneKey];
                }
            }

            // 사주 구조 분석 → 성격 앞에 추가
            if (!empty($storySections['saju_structure'])) {
                $fortuneResult['personality'] = $storySections['saju_structure']
                    . "\n\n" . $fortuneResult['personality'];
            }

            // 재능 분석 → 직업 앞에 추가
            if (!empty($storySections['talent'])) {
                $fortuneResult['career'] = $storySections['talent']
                    . "\n\n" . $fortuneResult['career'];
            }

            // 감지된 패턴 수 메타 정보 추가
            $fortuneResult['_meta'] = [
                'patterns_detected' => $story['meta']['patterns_detected'] ?? 0,
                'patterns_used'     => $story['meta']['patterns_used'] ?? 0,
                'total_chars'       => $story['char_count'] ?? 0,
                'engine_version'    => 'v4_pattern_enhanced',
            ];
        } catch (Exception $e) {
            // 패턴 엔진 실패 시 기존 해석 유지 (무음 실패)
            $fortuneResult['_meta'] = [
                'engine_version' => 'v3_fallback',
                'error' => $e->getMessage(),
            ];
        }

        return $fortuneResult;
    }
}
