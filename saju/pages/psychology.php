<?php
require_once __DIR__ . '/../includes/auth_check.php';

$user = getCurrentUser();
$csrfToken = generateCSRFToken();
$errors = [];
$result = null;

$questions = [
    'q1' => [
        'label' => '갑자기 빈 시간이 생기면?',
        'options' => [
            'a' => '바로 사람을 만나거나 움직인다',
            'b' => '해야 할 일부터 정리한다',
            'c' => '혼자 쉬면서 머리를 비운다',
        ],
    ],
    'q2' => [
        'label' => '갈등이 생기면 먼저 하는 일은?',
        'options' => [
            'a' => '내 생각을 분명하게 말한다',
            'b' => '상대 반응을 보며 조절한다',
            'c' => '시간을 두고 천천히 정리한다',
        ],
    ],
    'q3' => [
        'label' => '일할 때 더 편한 방식은?',
        'options' => [
            'a' => '바로 시작하며 흐름을 탄다',
            'b' => '계획을 세우고 순서대로 간다',
            'c' => '상황을 보며 유연하게 바꾼다',
        ],
    ],
    'q4' => [
        'label' => '사람들이 나를 어떻게 기억하면 좋겠나?',
        'options' => [
            'a' => '결단력 있는 사람',
            'b' => '믿을 수 있는 사람',
            'c' => '따뜻하고 센스 있는 사람',
        ],
    ],
];

$form = [
    'q1' => $_POST['q1'] ?? '',
    'q2' => $_POST['q2'] ?? '',
    'q3' => $_POST['q3'] ?? '',
    'q4' => $_POST['q4'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = '다시 시도해 주세요.';
    }

    foreach ($questions as $key => $question) {
        if (!isset($question['options'][$form[$key]])) {
            $errors[] = '모든 질문에 답해 주세요.';
            break;
        }
    }

    if (!$errors) {
        $scores = ['action' => 50, 'stability' => 50, 'warmth' => 50, 'logic' => 50];
        $weights = [
            'q1' => ['a' => ['action' => 12], 'b' => ['logic' => 8, 'stability' => 6], 'c' => ['warmth' => 6, 'stability' => 8]],
            'q2' => ['a' => ['action' => 8, 'logic' => 5], 'b' => ['warmth' => 10], 'c' => ['stability' => 8, 'logic' => 4]],
            'q3' => ['a' => ['action' => 12], 'b' => ['logic' => 8, 'stability' => 6], 'c' => ['warmth' => 5, 'action' => 4]],
            'q4' => ['a' => ['action' => 10], 'b' => ['stability' => 10], 'c' => ['warmth' => 10]],
        ];

        foreach ($form as $key => $choice) {
            foreach ($weights[$key][$choice] as $axis => $value) {
                $scores[$axis] += $value;
            }
        }

        arsort($scores);
        $axes = array_keys($scores);
        $primary = $axes[0];
        $secondary = $axes[1];

        $profiles = [
            'action-logic' => ['title' => '직진형 개척가', 'desc' => '판단이 빠르고 움직이면서 답을 찾는 타입입니다.'],
            'action-warmth' => ['title' => '에너지형 분위기메이커', 'desc' => '사람과 상황을 동시에 살리며 흐름을 끌고 가는 타입입니다.'],
            'stability-logic' => ['title' => '분석형 설계자', 'desc' => '계획과 구조를 잘 세워 흔들리지 않게 만드는 타입입니다.'],
            'stability-warmth' => ['title' => '든든한 조율가', 'desc' => '안정감 있게 관계를 이어 가며 팀의 균형을 맞추는 타입입니다.'],
            'warmth-action' => ['title' => '따뜻한 리더형', 'desc' => '사람을 챙기면서도 필요한 순간에는 빠르게 움직이는 타입입니다.'],
            'warmth-stability' => ['title' => '공감형 보호자', 'desc' => '상대의 기분을 잘 읽고 오래 가는 신뢰를 만드는 타입입니다.'],
        ];

        $profile = $profiles[$primary . '-' . $secondary] ?? ['title' => '균형형 탐색가', 'desc' => '상황에 따라 다른 강점을 꺼내 쓰는 유연한 타입입니다.'];
        $result = [
            'title' => $profile['title'],
            'desc' => $profile['desc'],
            'scores' => $scores,
            'work' => $primary === 'logic' || $secondary === 'logic'
                ? '일에서는 구조를 만들고 흐름을 정리하는 역할을 맡을 때 강점이 커집니다.'
                : '일에서는 빠르게 움직이며 현장을 살리는 역할에서 존재감이 커집니다.',
            'relationship' => $primary === 'warmth' || $secondary === 'warmth'
                ? '관계에서는 상대 마음을 읽는 능력이 좋아서 신뢰를 쌓는 속도가 빠른 편입니다.'
                : '관계에서는 선을 분명히 하되 필요한 순간에 표현을 조금 더 늘리면 훨씬 편해집니다.',
            'stress' => $primary === 'stability'
                ? '예상치 못한 변화가 몰리면 피로가 커질 수 있으니 준비 시간을 확보하는 편이 좋습니다.'
                : '답답하게 멈춰 있는 상황이 오래 가면 스트레스가 쌓이기 쉬우니 작은 실행을 자주 만드는 편이 좋습니다.',
        ];
    }
}

$pageTitle = '심리풀이 - ' . SITE_NAME;
include INCLUDES_PATH . '/header.php';
?>

<div class="animate-fade">
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div style="font-size:0.82rem;color:var(--text-muted);">간단 성향 테스트</div>
                <h2 style="font-size:1.35rem;font-weight:900;">심리풀이</h2>
                <p style="font-size:0.84rem;color:var(--text-secondary);margin-top:6px;line-height:1.7;">몇 가지 선택만으로 내 성향, 일 스타일, 관계 습관을 빠르게 읽어 줍니다.</p>
            </div>
            <div class="service-menu-icon"><i class="fas fa-search"></i></div>
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
        <?php foreach ($questions as $key => $question): ?>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label"><?= h($question['label']) ?></label>
            <div class="radio-group">
                <?php foreach ($question['options'] as $value => $label): ?>
                <label class="radio-label" style="margin-bottom:8px;display:flex;align-items:center;gap:8px;">
                    <input type="radio" name="<?= h($key) ?>" value="<?= h($value) ?>" <?= $form[$key] === $value ? 'checked' : '' ?>>
                    <span><?= h($label) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary btn-block">심리풀이 결과 보기</button>
    </form>

    <?php if ($result): ?>
    <div class="card compat-score-card">
        <div style="font-size:0.78rem;color:var(--text-muted);">내 성향 한 줄 요약</div>
        <div style="font-size:1.45rem;font-weight:900;margin:8px 0;"><?= h($result['title']) ?></div>
        <p style="font-size:0.84rem;color:var(--text-secondary);line-height:1.7;"><?= h($result['desc']) ?></p>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">성향 점수</span></div>
        <div class="mini-card-grid">
            <?php foreach ($result['scores'] as $label => $score): ?>
            <div class="mini-stat-card">
                <div class="mini-stat-label"><?= h(['action' => '실행력', 'stability' => '안정감', 'warmth' => '공감력', 'logic' => '분석력'][$label]) ?></div>
                <div class="mini-stat-value"><?= h($score) ?>점</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">생활 속 해석</span></div>
        <div class="result-story-box">
            <div style="margin-bottom:10px;">• <?= h($result['work']) ?></div>
            <div style="margin-bottom:10px;">• <?= h($result['relationship']) ?></div>
            <div>• <?= h($result['stress']) ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>