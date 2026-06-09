<?php
/**
 * 사주 분석 입력 페이지
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once SAJU_ENGINE_PATH . '/SajuEngine.php';
require_once SAJU_ENGINE_PATH . '/OhangAnalysis.php';
require_once SAJU_ENGINE_PATH . '/FortuneInterpreter.php';

$user = getCurrentUser();
$errors = [];
$userTickets = getUserTickets($user['id']);
$analysisTypeOptions = array_merge(array_fill_keys(FREE_FEATURES, 0), array_map(function ($feature) {
    return (int)($feature['tickets'] ?? 0);
}, PREMIUM_FEATURES));
$fortuneOnlyTypes = ['love', 'career', 'wealth'];
$selectedType = $_GET['type'] ?? 'basic_saju';
if (!array_key_exists($selectedType, $analysisTypeOptions)) {
    $selectedType = 'basic_saju';
}

function analyzeFeatureMeta($type) {
    $freeMeta = [
        'basic_saju' => ['name' => '사주팔자 계산', 'icon' => 'fa-yin-yang', 'summary' => '사주명식 · 지장간 · 관계 흐름'],
        'ohang' => ['name' => '오행 분석', 'icon' => 'fa-circle-nodes', 'summary' => '에너지 균형 · 생활 팁'],
    ];

    if (isset($freeMeta[$type])) {
        return $freeMeta[$type];
    }

    $premium = PREMIUM_FEATURES[$type] ?? ['name' => $type, 'icon' => 'fa-star'];
    $summaryMap = [
        'sipsin' => '성향 분포 · 해석 포인트',
        'gyeokguk' => '삶의 중심 테마 · 완성도',
        'daeun' => '10년 흐름 · 전환 시기',
        'seun' => '해마다 달라지는 흐름',
        'love' => '관계 스타일 · 연애 흐름',
        'career' => '일 적성 · 커리어 방향',
        'wealth' => '돈 흐름 · 관리 습관',
        'comprehensive' => '핵심 성향부터 인생 흐름까지 한 번에',
    ];

    return [
        'name' => $premium['name'],
        'icon' => $premium['icon'],
        'summary' => $summaryMap[$type] ?? '프리미엄 분석',
    ];
}

function slimRelationshipsForStorage($relationships) {
    if (empty($relationships) || !is_array($relationships)) {
        return [];
    }

    $lean = [];
    foreach ($relationships as $rel) {
        if (!is_array($rel)) continue;
        $item = [
            'type' => $rel['type'] ?? '',
            'from' => $rel['from'] ?? null,
            'to' => $rel['to'] ?? null,
            'meaning' => $rel['meaning'] ?? null,
        ];
        $lean[] = array_filter($item, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    return $lean;
}

function trimStorageText($text, $limit = 1200) {
    $plain = trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', strip_tags((string)$text))));
    if ($plain === '') {
        return '';
    }

    $length = function_exists('mb_strlen') ? mb_strlen($plain) : strlen($plain);
    if ($length <= $limit) {
        return $plain;
    }

    $sentences = preg_split('/(?<=[.!?])\s+/u', $plain);
    $snippet = '';
    foreach ($sentences as $sentence) {
        $candidate = trim($snippet === '' ? $sentence : $snippet . ' ' . $sentence);
        $candidateLength = function_exists('mb_strlen') ? mb_strlen($candidate) : strlen($candidate);
        if ($candidateLength > $limit) {
            break;
        }
        $snippet = $candidate;
    }

    if ($snippet !== '') {
        return $snippet;
    }

    return function_exists('mb_substr') ? mb_substr($plain, 0, $limit) : substr($plain, 0, $limit);
}

function slimDaeunPayload($daeunData) {
    if (empty($daeunData) || !is_array($daeunData)) {
        return null;
    }

    $lean = [
        'direction' => $daeunData['direction'] ?? '',
        'start_age' => $daeunData['start_age'] ?? null,
        'wonguk_patterns' => array_slice($daeunData['wonguk_patterns'] ?? [], 0, 10),
        'daeuns' => [],
    ];

    foreach (($daeunData['daeuns'] ?? []) as $d) {
        if (!is_array($d)) continue;
        $lean['daeuns'][] = [
            'index' => $d['index'] ?? null,
            'age_start' => $d['age_start'] ?? null,
            'age_end' => $d['age_end'] ?? null,
            'stem' => $d['stem'] ?? '',
            'branch' => $d['branch'] ?? '',
            'stem_hanja' => $d['stem_hanja'] ?? '',
            'branch_hanja' => $d['branch_hanja'] ?? '',
            'stem_element' => $d['stem_element'] ?? '',
            'branch_element' => $d['branch_element'] ?? '',
            'stem_sipsin' => $d['stem_sipsin'] ?? '',
            'branch_sipsin' => $d['branch_sipsin'] ?? '',
            'twelve_stage' => $d['twelve_stage'] ?? '',
            'is_yongshin' => !empty($d['is_yongshin']),
            'score' => $d['score'] ?? 50,
            'relationships' => slimRelationshipsForStorage($d['relationships'] ?? []),
            'interpretation' => trimStorageText($d['interpretation'] ?? '', 1200),
        ];
    }

    return $lean;
}

function slimSeunPayload($seunData) {
    if (empty($seunData) || !is_array($seunData)) {
        return null;
    }

    $items = isset($seunData['year']) ? [$seunData] : $seunData;
    $lean = [];

    foreach ($items as $s) {
        if (!is_array($s)) continue;
        $lean[] = [
            'year' => $s['year'] ?? null,
            'stem' => $s['stem'] ?? '',
            'branch' => $s['branch'] ?? '',
            'stem_hanja' => $s['stem_hanja'] ?? '',
            'branch_hanja' => $s['branch_hanja'] ?? '',
            'stem_element' => $s['stem_element'] ?? '',
            'branch_element' => $s['branch_element'] ?? '',
            'stem_sipsin' => $s['stem_sipsin'] ?? '',
            'branch_sipsin' => $s['branch_sipsin'] ?? '',
            'zodiac' => $s['zodiac'] ?? '',
            'is_yongshin' => !empty($s['is_yongshin']),
            'score' => $s['score'] ?? ($s['total_score'] ?? 50),
            'monthly_highlight' => $s['monthly_highlight'] ?? null,
            'relationships' => slimRelationshipsForStorage($s['relationships'] ?? []),
            'interpretation' => trimStorageText($s['interpretation'] ?? '', 800),
        ];
    }

    return isset($seunData['year']) ? ($lean[0] ?? null) : $lean;
}

function buildStoredFortunePayload($analysisType, $isPremium, $ticketCost, $fortuneData = null) {
    $payload = [
        'analysis_type' => $analysisType,
        'is_premium' => $isPremium,
        'ticket_cost' => $ticketCost,
    ];

    if (!empty($fortuneData)) {
        $payload['fortune'] = $fortuneData;
    }

    return $payload;
}

// 저장된 사주 프로필 불러오기
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT id, profile_name, birth_year, birth_month, birth_day, birth_hour, gender, calendar_type, is_default FROM saju_profiles WHERE user_id = ? ORDER BY is_default DESC, created_at ASC");
$stmt->execute([$user['id']]);
$savedProfiles = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $birthYear = (int)($_POST['birth_year'] ?? 0);
    $birthMonth = (int)($_POST['birth_month'] ?? 0);
    $birthDay = (int)($_POST['birth_day'] ?? 0);
    $birthHour = (int)($_POST['birth_hour'] ?? -1);
    $gender = $_POST['gender'] ?? '';
    $calendarType = $_POST['calendar_type'] ?? 'solar';
    $analysisType = $_POST['analysis_type'] ?? 'basic_saju';
    if (!array_key_exists($analysisType, $analysisTypeOptions)) {
        $analysisType = 'basic_saju';
    }
    
    // 검증
    if ($birthYear < 1900 || $birthYear > (int)date('Y')) {
        $errors[] = '올바른 출생 연도를 입력해 주세요.';
    }
    if ($birthMonth < 1 || $birthMonth > 12) {
        $errors[] = '올바른 출생 월을 선택해 주세요.';
    }
    if ($birthDay < 1 || $birthDay > 31) {
        $errors[] = '올바른 출생 일을 입력해 주세요.';
    }
    if ($birthHour < 0 || $birthHour > 23) {
        $errors[] = '올바른 출생 시간을 선택해 주세요.';
    }
    if (!in_array($gender, ['male', 'female'])) {
        $errors[] = '성별을 선택해 주세요.';
    }
    
    // 티켓 비용 계산
    $ticketCost = $analysisTypeOptions[$analysisType] ?? 0;
    
    // 프리미엄 분석이면 티켓 확인
    if ($ticketCost > 0 && $userTickets < $ticketCost) {
        $errors[] = "티켓이 부족합니다. 이 분석에는 {$ticketCost}장이 필요합니다. (보유: {$userTickets}장)";
    }
    
    if (empty($errors)) {
        try {
            // 사주 계산
            $engine = new SajuEngine($birthYear, $birthMonth, $birthDay, $birthHour, $gender, $calendarType);
            $sajuResult = $engine->getResult();
            
            // 오행 분석 (무료 — 항상 실행)
            $ohangAnalysis = new OhangAnalysis($engine);
            $ohangData = $ohangAnalysis->analyze();
            
            // 운세 해석
            $interpreter = new FortuneInterpreter($engine);
            
            // 프리미엄 분석 데이터
            $sipsinData = null;
            $gyeokgukData = null;
            $daeunData = null;
            $seunData = null;
            $fortuneData = null;
            
            // 분석 유형별 프리미엄 실행
            $isPremium = !in_array($analysisType, FREE_FEATURES, true);
            
            if ($analysisType === 'sipsin' || $analysisType === 'comprehensive') {
                $sipsinData = $interpreter->analyzeSipsin();
            }
            if ($analysisType === 'gyeokguk' || $analysisType === 'comprehensive') {
                $gyeokgukData = $interpreter->analyzeGyeokguk();
            }
            if ($analysisType === 'daeun' || $analysisType === 'comprehensive') {
                $daeunData = $interpreter->analyzeDaeun();
            }
            if ($analysisType === 'seun' || $analysisType === 'comprehensive') {
                $seunData = $interpreter->analyzeSeun();
            }
            if ($analysisType === 'comprehensive') {
                $fortuneData = $interpreter->getComprehensiveFortune();
            }
            if (in_array($analysisType, $fortuneOnlyTypes, true)) {
                $fullFortune = $interpreter->getComprehensiveFortune();
                if (!empty($fullFortune[$analysisType])) {
                    $fortuneData = [
                        $analysisType => $fullFortune[$analysisType],
                        '_meta' => $fullFortune['_meta'] ?? [],
                    ];
                }
            }
            
            $storedFortuneResult = buildStoredFortunePayload($analysisType, $isPremium, $ticketCost, $fortuneData);
            $storedDaeunData = slimDaeunPayload($daeunData);
            $storedSeunData = slimSeunPayload($seunData);
            
            // 프리미엄이면 티켓 차감
            if ($ticketCost > 0) {
                $featureMeta = analyzeFeatureMeta($analysisType);
                $ticketResult = useTickets($user['id'], $ticketCost, ($featureMeta['name'] ?? '분석') . ' 이용');
                if (!$ticketResult) {
                    throw new Exception('티켓 차감에 실패했습니다.');
                }
                $userTickets -= $ticketCost;
            }
            
            // DB 저장
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("
                INSERT INTO saju_fortune_history 
                (user_id, birth_year, birth_month, birth_day, birth_hour, gender, calendar_type,
                 year_pillar, month_pillar, day_pillar, hour_pillar,
                 ohang_analysis, sipsin_analysis, gyeokguk_analysis, daeun_analysis, seun_analysis,
                 fortune_result, analysis_type, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $user['id'],
                $birthYear, $birthMonth, $birthDay, $birthHour,
                $gender, $calendarType,
                $sajuResult['year_pillar']['text'],
                $sajuResult['month_pillar']['text'],
                $sajuResult['day_pillar']['text'],
                $sajuResult['hour_pillar']['text'],
                json_encode($ohangData, JSON_UNESCAPED_UNICODE),
                $sipsinData ? json_encode($sipsinData, JSON_UNESCAPED_UNICODE) : null,
                $gyeokgukData ? json_encode($gyeokgukData, JSON_UNESCAPED_UNICODE) : null,
                $storedDaeunData ? json_encode($storedDaeunData, JSON_UNESCAPED_UNICODE) : null,
                $storedSeunData ? json_encode($storedSeunData, JSON_UNESCAPED_UNICODE) : null,
                json_encode($storedFortuneResult, JSON_UNESCAPED_UNICODE),
                $analysisType,
            ]);
            
            $recordId = $pdo->lastInsertId();
            
            // 결과 페이지로 이동
            header('Location: ' . SITE_URL . '/pages/result.php?id=' . $recordId);
            exit;
            
        } catch (Exception $e) {
            $errors[] = '분석 중 오류가 발생했습니다: ' . $e->getMessage();
        }
    }
}

$serviceMenu = [
    ['label' => '신년운세', 'icon' => 'fa-star', 'url' => SITE_URL . '/pages/yearly_fortune.php?mode=yearly', 'badge' => 'N'],
    ['label' => '토정비결', 'icon' => 'fa-book-open', 'url' => SITE_URL . '/pages/yearly_fortune.php?mode=tojung', 'badge' => 'N'],
    ['label' => '정통사주', 'icon' => 'fa-pen-nib', 'url' => SITE_URL . '/pages/traditional_saju.php', 'badge' => 'N'],
    ['label' => '재물운', 'icon' => 'fa-sack-dollar', 'url' => SITE_URL . '/pages/focus_fortune.php?type=wealth', 'badge' => ''],
    ['label' => '건강운', 'icon' => 'fa-heart-pulse', 'url' => SITE_URL . '/pages/focus_fortune.php?type=health', 'badge' => ''],
    ['label' => '직업운', 'icon' => 'fa-briefcase', 'url' => SITE_URL . '/pages/focus_fortune.php?type=career', 'badge' => ''],
];

$pageTitle = '사주 분석 - ' . SITE_NAME;
include INCLUDES_PATH . '/header.php';
?>

<div class="animate-fade">
    <div class="card service-menu-hero">
        <div class="service-menu-kicker">자주 보는 운세 바로가기</div>
        <div class="service-menu-title">원하는 운세로 바로 이동</div>
        <div class="service-menu-grid">
            <?php foreach ($serviceMenu as $menu): ?>
            <a href="<?= h($menu['url']) ?>" class="service-menu-card">
                <?php if (!empty($menu['badge'])): ?>
                <span class="service-menu-badge"><?= h($menu['badge']) ?></span>
                <?php endif; ?>
                <span class="service-menu-icon"><i class="fas <?= h($menu['icon']) ?>"></i></span>
                <span class="service-menu-name"><?= h($menu['label']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size: 1.2rem; font-weight: 800; margin-bottom: 4px;">사주팔자 분석</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted);">생년월일시를 입력하여 운명을 알아보세요</p>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div style="background: #FFEBEE; color: #C62828; padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 0.85rem;">
        <?php foreach ($errors as $error): ?>
            <p>• <?= h($error) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="" class="card" id="analyzeForm">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        
        <!-- 프로필 선택 -->
        <?php if (!empty($savedProfiles)): ?>
        <div class="form-group" style="margin-bottom:18px;">
            <label class="form-label"><i class="fas fa-user-circle" style="color:#F39C12;"></i> 저장된 프로필</label>
            <select id="profileSelector" class="form-select" style="border:2px solid #FFE082;background:linear-gradient(135deg,#FFFDE7,#FFF8E1);">
                <option value="">직접 입력</option>
                <?php foreach ($savedProfiles as $sp): ?>
                <option value="<?= $sp['id'] ?>" 
                        data-year="<?= $sp['birth_year'] ?>" 
                        data-month="<?= $sp['birth_month'] ?>" 
                        data-day="<?= $sp['birth_day'] ?>" 
                        data-hour="<?= $sp['birth_hour'] !== null ? $sp['birth_hour'] : '' ?>" 
                        data-gender="<?= $sp['gender'] ?>" 
                        data-calendar="<?= $sp['calendar_type'] ?>"
                        <?= $sp['is_default'] ? 'selected' : '' ?>>
                    <?= h($sp['profile_name']) ?> (<?= $sp['birth_year'] ?>.<?= $sp['birth_month'] ?>.<?= $sp['birth_day'] ?> <?= $sp['gender'] === 'male' ? '남' : '여' ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <p class="form-hint">등록된 프로필을 선택하면 자동으로 입력됩니다. <a href="<?= SITE_URL ?>/pages/mypage.php" style="color:#F39C12;text-decoration:underline;">프로필 관리</a></p>
        </div>
        <hr style="border:0;border-top:1px solid #eee;margin:0 0 16px 0;">
        <?php endif; ?>
        
        <!-- 성별 선택 -->
        <div class="form-group">
            <label class="form-label">성별 *</label>
            <div class="radio-group">
                <label class="radio-label">
                    <input type="radio" name="gender" value="male" required checked>
                    <i class="fas fa-mars" style="color: #42A5F5;"></i> 남성
                </label>
                <label class="radio-label">
                    <input type="radio" name="gender" value="female" required>
                    <i class="fas fa-venus" style="color: #EC407A;"></i> 여성
                </label>
            </div>
        </div>
        
        <!-- 양력/음력 -->
        <div class="form-group">
            <label class="form-label">역법 *</label>
            <div class="radio-group">
                <label class="radio-label">
                    <input type="radio" name="calendar_type" value="solar" required checked>
                    ☀️ 양력
                </label>
                <label class="radio-label">
                    <input type="radio" name="calendar_type" value="lunar" required>
                    🌙 음력
                </label>
            </div>
        </div>
        
        <!-- 생년월일 -->
        <div class="form-group">
            <label class="form-label">출생 연도 *</label>
            <select name="birth_year" class="form-select" required>
                <option value="">연도 선택</option>
                <?php for ($y = (int)date('Y'); $y >= 1940; $y--): ?>
                <option value="<?= $y ?>"><?= $y ?>년</option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">출생 월 *</label>
                <select name="birth_month" class="form-select" required>
                    <option value="">월 선택</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>"><?= $m ?>월</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">출생 일 *</label>
                <select name="birth_day" class="form-select" required>
                    <option value="">일 선택</option>
                    <?php for ($d = 1; $d <= 31; $d++): ?>
                    <option value="<?= $d ?>"><?= $d ?>일</option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        
        <!-- 출생 시간 -->
        <div class="form-group">
            <label class="form-label">출생 시간 *</label>
            <select name="birth_hour" class="form-select" required>
                <option value="">시간 선택</option>
                <option value="0">자시 (子時) 23:30~01:30</option>
                <option value="1">축시 (丑時) 01:30~03:30</option>
                <option value="3">인시 (寅時) 03:30~05:30</option>
                <option value="5">묘시 (卯時) 05:30~07:30</option>
                <option value="7">진시 (辰時) 07:30~09:30</option>
                <option value="9">사시 (巳時) 09:30~11:30</option>
                <option value="11">오시 (午時) 11:30~13:30</option>
                <option value="13">미시 (未時) 13:30~15:30</option>
                <option value="15">신시 (申時) 15:30~17:30</option>
                <option value="17">유시 (酉時) 17:30~19:30</option>
                <option value="19">술시 (戌時) 19:30~21:30</option>
                <option value="21">해시 (亥時) 21:30~23:30</option>
            </select>
            <p class="form-hint">정확한 시간을 모르면 가장 가까운 시간대를 선택하세요</p>
        </div>
        
        <!-- 분석 유형: 무료 / 프리미엄 -->
        <div class="form-group">
            <label class="form-label">분석 유형</label>
            
            <!-- 보유 티켓 표시 -->
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;padding:10px 14px;background:linear-gradient(135deg,#FFF8E1,#FFF3E0);border-radius:10px;border:1px solid #FFE082;">
                <i class="fas fa-ticket-alt" style="color:#F57C00;font-size:1.1rem;"></i>
                <span style="font-size:0.85rem;font-weight:600;color:#E65100;">보유 티켓: <strong style="font-size:1.05rem;"><?= $userTickets ?></strong>장</span>
                <a href="<?= SITE_URL ?>/pages/mypage.php" style="margin-left:auto;font-size:0.75rem;color:#F57C00;text-decoration:underline;">충전하기</a>
            </div>

            <!-- 무료 분석 -->
            <div style="margin-bottom:6px;">
                <div style="font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;padding-left:2px;">
                    <i class="fas fa-gift" style="color:#4CAF50;"></i> 무료 분석
                </div>
                <div class="analysis-type-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <?php foreach (FREE_FEATURES as $featureKey): $featureMeta = analyzeFeatureMeta($featureKey); ?>
                    <label class="analysis-type-btn <?= $selectedType === $featureKey ? 'active' : '' ?>" data-cost="0">
                        <input type="radio" name="analysis_type" value="<?= h($featureKey) ?>" <?= $selectedType === $featureKey ? 'checked' : '' ?> style="display:none;">
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span><i class="fas <?= h($featureMeta['icon']) ?>" style="margin-right:4px;"></i> <?= h($featureMeta['name']) ?></span>
                            <span class="card-badge free" style="margin-left:auto;font-size:0.68rem;">무료</span>
                        </div>
                        <span style="font-size:0.7rem;color:var(--text-muted);display:block;margin-top:2px;"><?= h($featureMeta['summary']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 프리미엄 분석 -->
            <div style="margin-top:14px;">
                <div style="font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;padding-left:2px;">
                    <i class="fas fa-crown" style="color:#F39C12;"></i> 프리미엄 분석
                </div>
                <div class="analysis-type-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <?php foreach (PREMIUM_FEATURES as $featureKey => $feature): $featureMeta = analyzeFeatureMeta($featureKey); $isComprehensive = $featureKey === 'comprehensive'; ?>
                    <label class="analysis-type-btn premium-type <?= $selectedType === $featureKey ? 'active' : '' ?>" data-cost="<?= (int)$feature['tickets'] ?>" style="<?= $isComprehensive ? 'grid-column:1/-1;background:linear-gradient(135deg,#fdf6e3,#fef9ef);border:2px solid #F39C12;' : '' ?>">
                        <input type="radio" name="analysis_type" value="<?= h($featureKey) ?>" <?= $selectedType === $featureKey ? 'checked' : '' ?> style="display:none;">
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span><i class="fas <?= h($featureMeta['icon']) ?>" style="margin-right:4px;"></i><?= $isComprehensive ? '<strong>' . h($featureMeta['name']) . '</strong>' : h($featureMeta['name']) ?></span>
                            <span class="card-badge premium" style="margin-left:auto;font-size:0.68rem;background:<?= $isComprehensive ? 'linear-gradient(135deg,#E91E63,#9C27B0)' : 'linear-gradient(135deg,#F39C12,#E67E22)' ?>;color:#fff;">🎫 <?= (int)$feature['tickets'] ?>장</span>
                        </div>
                        <span style="font-size:0.7rem;color:<?= $isComprehensive ? '#E65100' : 'var(--text-muted)' ?>;display:block;margin-top:2px;"><?= $isComprehensive ? '핵심 성향부터 흐름, 종합 운세까지 한 번에 확인합니다.' : h($featureMeta['summary']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="ticketInfo" style="margin-top:10px;padding:8px 12px;border-radius:8px;font-size:0.82rem;display:none;">
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary btn-block btn-lg" id="analyzeBtn">
            <i class="fas fa-yin-yang"></i> <span id="btnText">사주 분석하기</span>
        </button>
    </form>
</div>

<script>
// 분석 유형 버튼 토글 + 티켓 비용 표시
const userTickets = <?= $userTickets ?>;
const ticketInfo = document.getElementById('ticketInfo');
const btnText = document.getElementById('btnText');
const analyzeBtn = document.getElementById('analyzeBtn');

function updateAnalyzeSelection(btn) {
    document.querySelectorAll('.analysis-type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const cost = parseInt(btn.dataset.cost) || 0;

    if (cost > 0) {
        ticketInfo.style.display = 'block';
        if (userTickets >= cost) {
            ticketInfo.style.background = '#E8F5E9';
            ticketInfo.style.color = '#2E7D32';
            ticketInfo.innerHTML = '<i class="fas fa-check-circle"></i> 이 분석에 <strong>' + cost + '장</strong> 사용됩니다. (분석 후 잔여: ' + (userTickets - cost) + '장)';
            analyzeBtn.disabled = false;
            btnText.textContent = '🎫 ' + cost + '장으로 프리미엄 분석하기';
        } else {
            ticketInfo.style.display = 'block';
            ticketInfo.style.background = '#FFEBEE';
            ticketInfo.style.color = '#C62828';
            ticketInfo.innerHTML = '<i class="fas fa-exclamation-triangle"></i> 티켓이 부족합니다. <strong>' + cost + '장</strong> 필요 (보유: ' + userTickets + '장) <a href="<?= SITE_URL ?>/pages/mypage.php" style="color:#C62828;text-decoration:underline;margin-left:6px;">충전하기</a>';
            analyzeBtn.disabled = true;
            btnText.textContent = '티켓 부족';
        }
    } else {
        ticketInfo.style.display = 'none';
        analyzeBtn.disabled = false;
        btnText.textContent = '사주 분석하기';
    }
}

document.querySelectorAll('.analysis-type-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        updateAnalyzeSelection(this);
    });
});

const initiallyCheckedBtn = document.querySelector('.analysis-type-btn input[name="analysis_type"]:checked');
if (initiallyCheckedBtn) {
    updateAnalyzeSelection(initiallyCheckedBtn.closest('.analysis-type-btn'));
}

// 폼 중복 제출 방지
document.getElementById('analyzeForm').addEventListener('submit', function() {
    const btn = document.getElementById('analyzeBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="loader"></span> 분석 중...';
});

// 프로필 선택 시 자동 입력
const profileSelector = document.getElementById('profileSelector');
if (profileSelector) {
    function applyProfile() {
        const opt = profileSelector.options[profileSelector.selectedIndex];
        if (!opt.value) return; // "직접 입력" 선택 시 변경하지 않음
        
        const form = document.getElementById('analyzeForm');
        
        // 성별
        const genderVal = opt.dataset.gender;
        const genderRadio = form.querySelector('input[name="gender"][value="' + genderVal + '"]');
        if (genderRadio) genderRadio.checked = true;
        
        // 역법
        const calVal = opt.dataset.calendar;
        const calRadio = form.querySelector('input[name="calendar_type"][value="' + calVal + '"]');
        if (calRadio) calRadio.checked = true;
        
        // 연도
        const yearSel = form.querySelector('select[name="birth_year"]');
        if (yearSel) yearSel.value = opt.dataset.year;
        
        // 월
        const monthSel = form.querySelector('select[name="birth_month"]');
        if (monthSel) monthSel.value = opt.dataset.month;
        
        // 일
        const daySel = form.querySelector('select[name="birth_day"]');
        if (daySel) daySel.value = opt.dataset.day;
        
        // 시간
        const hourSel = form.querySelector('select[name="birth_hour"]');
        if (hourSel) {
            const hourVal = opt.dataset.hour;
            if (hourVal !== '' && hourVal !== undefined) {
                hourSel.value = hourVal;
            } else {
                hourSel.value = '';
            }
        }
    }
    
    profileSelector.addEventListener('change', applyProfile);
    
    // 기본 프로필이 선택되어 있으면 초기 자동 입력
    if (profileSelector.value) {
        applyProfile();
    }
}
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
