<?php
require_once __DIR__ . '/../includes/auth_check.php';

$user = getCurrentUser();
$csrfToken = generateCSRFToken();
$errors = [];
$result = null;

$traits = [
    'forehead' => [
        'label' => '이마 느낌',
        'options' => [
            'wide' => '넓고 시원한 편',
            'balanced' => '적당히 균형 잡힌 편',
            'narrow' => '조금 좁고 단단한 편',
        ],
    ],
    'eyes' => [
        'label' => '눈매 느낌',
        'options' => [
            'soft' => '부드럽고 둥근 눈매',
            'clear' => '또렷하고 맑은 눈매',
            'sharp' => '날카롭고 집중된 눈매',
        ],
    ],
    'nose' => [
        'label' => '코의 인상',
        'options' => [
            'stable' => '곧고 안정적인 편',
            'gentle' => '부드럽고 매끈한 편',
            'strong' => '도드라지고 존재감 있는 편',
        ],
    ],
    'mouth' => [
        'label' => '입매 느낌',
        'options' => [
            'warm' => '웃는 인상이 잘 보임',
            'calm' => '차분하고 단정한 편',
            'firm' => '입술선이 또렷하고 단단함',
        ],
    ],
    'jaw' => [
        'label' => '턱선 느낌',
        'options' => [
            'round' => '둥글고 편안한 편',
            'balanced' => '적당히 안정된 편',
            'defined' => '각이 있고 분명한 편',
        ],
    ],
];

$form = [
    'forehead' => $_POST['forehead'] ?? '',
    'eyes' => $_POST['eyes'] ?? '',
    'nose' => $_POST['nose'] ?? '',
    'mouth' => $_POST['mouth'] ?? '',
    'jaw' => $_POST['jaw'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = '요청을 다시 시도해 주세요.';
    }

    foreach ($traits as $key => $trait) {
        if (!isset($trait['options'][$form[$key]])) {
            $errors[] = $trait['label'] . '을(를) 선택해 주세요.';
        }
    }

    if (!$errors) {
        $themeScores = ['leadership' => 50, 'warmth' => 50, 'focus' => 50, 'stability' => 50];
        $messages = [];

        $ruleBook = [
            'forehead' => [
                'wide' => ['leadership' => 10, 'focus' => 4, 'msg' => '넓은 이마 인상은 생각의 폭이 넓고 앞을 내다보는 힘이 드러나는 편입니다.'],
                'balanced' => ['stability' => 8, 'warmth' => 4, 'msg' => '균형 잡힌 이마는 현실 감각과 조절력이 비교적 안정적인 편으로 읽힙니다.'],
                'narrow' => ['focus' => 8, 'leadership' => 4, 'msg' => '단단한 이마 인상은 순간 집중력과 빠른 판단이 먼저 드러나는 편입니다.'],
            ],
            'eyes' => [
                'soft' => ['warmth' => 12, 'stability' => 4, 'msg' => '부드러운 눈매는 상대를 편하게 하고 공감 능력이 살아 있는 인상으로 보입니다.'],
                'clear' => ['focus' => 10, 'warmth' => 4, 'msg' => '맑고 또렷한 눈매는 생각이 분명하고 감정선이 깨끗한 사람처럼 보이게 합니다.'],
                'sharp' => ['focus' => 12, 'leadership' => 4, 'msg' => '날카로운 눈매는 목표를 놓치지 않는 집중형 이미지가 강합니다.'],
            ],
            'nose' => [
                'stable' => ['stability' => 12, 'leadership' => 3, 'msg' => '곧고 안정적인 코 인상은 책임감과 생활 기반을 지키는 힘이 있다는 느낌을 줍니다.'],
                'gentle' => ['warmth' => 8, 'stability' => 5, 'msg' => '부드러운 코 인상은 융통성과 대인 관계의 완충력이 좋게 보이는 편입니다.'],
                'strong' => ['leadership' => 12, 'focus' => 3, 'msg' => '존재감 있는 코 인상은 추진력과 자기 기준이 강한 사람처럼 읽힙니다.'],
            ],
            'mouth' => [
                'warm' => ['warmth' => 12, 'leadership' => 2, 'msg' => '웃는 인상이 잘 보이는 입매는 말 한마디의 힘이 좋고 사람을 끌어당기는 편입니다.'],
                'calm' => ['stability' => 8, 'focus' => 4, 'msg' => '단정한 입매는 말을 아끼고 신중하게 관계를 다루는 느낌을 줍니다.'],
                'firm' => ['focus' => 10, 'leadership' => 5, 'msg' => '입술선이 또렷한 편은 약속과 기준을 중요하게 여기는 이미지가 강합니다.'],
            ],
            'jaw' => [
                'round' => ['warmth' => 8, 'stability' => 6, 'msg' => '둥근 턱선은 부드럽고 관계 지향적인 인상이 잘 드러납니다.'],
                'balanced' => ['stability' => 10, 'warmth' => 3, 'msg' => '균형 있는 턱선은 버티는 힘과 현실 감각이 고르게 깔린 편으로 읽힙니다.'],
                'defined' => ['leadership' => 8, 'focus' => 6, 'msg' => '분명한 턱선은 끝까지 밀고 가는 힘과 결단력을 보여 주는 편입니다.'],
            ],
        ];

        foreach ($form as $traitKey => $choice) {
            $rule = $ruleBook[$traitKey][$choice];
            foreach (['leadership', 'warmth', 'focus', 'stability'] as $theme) {
                $themeScores[$theme] += $rule[$theme] ?? 0;
            }
            $messages[] = $rule['msg'];
        }

        arsort($themeScores);
        $topThemes = array_keys($themeScores);
        $primary = $topThemes[0];
        $secondary = $topThemes[1];

        $titleMap = [
            'leadership-focus' => '직진형 추진가',
            'leadership-stability' => '든든한 리더형',
            'warmth-stability' => '따뜻한 신뢰형',
            'warmth-focus' => '센스 있는 조율형',
            'focus-stability' => '차분한 분석형',
            'stability-warmth' => '믿음직한 보호형',
        ];
        $title = $titleMap[$primary . '-' . $secondary] ?? '균형 잡힌 관상형';

        $result = [
            'title' => $title,
            'scores' => $themeScores,
            'headline' => $messages[0] . ' ' . $messages[1],
            'career' => $primary === 'leadership' || $primary === 'focus'
                ? '일에서는 주도권을 잡거나 기준을 세우는 역할에서 강점이 잘 보입니다.'
                : '일에서는 사람을 안정시키고 흐름을 부드럽게 만드는 역할에서 힘이 살아납니다.',
            'relationship' => $primary === 'warmth'
                ? '관계에서는 먼저 분위기를 풀고 상대의 마음을 읽어 주는 장점이 큽니다.'
                : '관계에서는 속도보다 신뢰를 쌓는 방식이 더 잘 맞는 편입니다.',
            'money' => $primary === 'stability'
                ? '돈 흐름은 크게 흔들기보다 차곡차곡 관리할수록 강점이 드러나는 타입입니다.'
                : '돈 문제는 한 번에 크게 벌리기보다 집중할 한두 가지 목표를 정하는 편이 좋습니다.',
            'tips' => array_slice($messages, 0, 3),
        ];
    }
}

$pageTitle = '관상 - ' . SITE_NAME;
include INCLUDES_PATH . '/header.php';
?>

<div class="animate-fade">
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div style="font-size:0.82rem;color:var(--text-muted);">얼굴 인상 풀이</div>
                <h2 style="font-size:1.35rem;font-weight:900;">관상</h2>
                <p style="font-size:0.84rem;color:var(--text-secondary);margin-top:6px;line-height:1.7;">얼굴의 분위기를 바탕으로 성향과 대인 인상을 쉽게 풀어줍니다.</p>
            </div>
            <div class="service-menu-icon"><i class="fas fa-smile"></i></div>
        </div>
    </div>

    <?php if ($errors): ?>
    <div class="flash-message flash-error" style="display:block;">
        <div class="flash-inner" style="display:block;">
            <?= h($errors[0]) ?>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" class="card trait-form-grid">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <?php foreach ($traits as $key => $trait): ?>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label"><?= h($trait['label']) ?></label>
            <select name="<?= h($key) ?>" class="form-select">
                <option value="">선택해 주세요</option>
                <?php foreach ($trait['options'] as $value => $label): ?>
                <option value="<?= h($value) ?>" <?= $form[$key] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary btn-block">관상 풀이 보기</button>
    </form>

    <?php if ($result): ?>
    <div class="card compat-score-card">
        <div style="font-size:0.78rem;color:var(--text-muted);">가장 강하게 보이는 인상</div>
        <div style="font-size:1.45rem;font-weight:900;margin:8px 0;"><?= h($result['title']) ?></div>
        <p style="font-size:0.84rem;color:var(--text-secondary);line-height:1.7;"><?= h($result['headline']) ?></p>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">인상 점수</span></div>
        <div class="mini-card-grid">
            <?php foreach ($result['scores'] as $label => $score): ?>
            <div class="mini-stat-card">
                <div class="mini-stat-label"><?= h(['leadership' => '주도성', 'warmth' => '친화력', 'focus' => '집중력', 'stability' => '안정감'][$label]) ?></div>
                <div class="mini-stat-value"><?= h($score) ?>점</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">분야별 해석</span></div>
        <div class="result-story-box">
            <div style="margin-bottom:10px;">• <?= h($result['career']) ?></div>
            <div style="margin-bottom:10px;">• <?= h($result['relationship']) ?></div>
            <div>• <?= h($result['money']) ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>