<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/fortune_services.php';

$user = getCurrentUser();
$record = fs_get_latest_analysis_record($user['id']);
$csrfToken = generateCSRFToken();
$errors = [];
$result = null;

$form = [
    'birth_year' => $_POST['birth_year'] ?? '',
    'birth_month' => $_POST['birth_month'] ?? '',
    'birth_day' => $_POST['birth_day'] ?? '',
    'birth_hour' => $_POST['birth_hour'] ?? '12',
    'gender' => $_POST['gender'] ?? 'female',
    'calendar_type' => $_POST['calendar_type'] ?? 'solar',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $record) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = '다시 시도해 주세요.';
    }

    foreach (['birth_year', 'birth_month', 'birth_day', 'birth_hour'] as $field) {
        if ($form[$field] === '' || !is_numeric($form[$field])) {
            $errors[] = '상대의 생년월일시를 정확히 입력해 주세요.';
            break;
        }
    }

    if (!$errors) {
        $result = fs_build_compatibility($record, [
            'birth_year' => (int)$form['birth_year'],
            'birth_month' => (int)$form['birth_month'],
            'birth_day' => (int)$form['birth_day'],
            'birth_hour' => (int)$form['birth_hour'],
            'gender' => $form['gender'],
            'calendar_type' => $form['calendar_type'],
        ]);
    }
}

$pageTitle = '짝궁합 - ' . SITE_NAME;
include INCLUDES_PATH . '/header.php';
?>

<div class="animate-fade">
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div style="font-size:0.82rem;color:var(--text-muted);">두 사람의 결 보기</div>
                <h2 style="font-size:1.35rem;font-weight:900;">짝궁합</h2>
                <p style="font-size:0.84rem;color:var(--text-secondary);margin-top:6px;line-height:1.7;">내 최근 사주와 상대의 생년월일시를 비교해 관계의 결을 읽어 줍니다.</p>
            </div>
            <div class="service-menu-icon"><i class="fas fa-heart"></i></div>
        </div>
    </div>

    <?php if (!$record): ?>
    <div class="card" style="text-align:center;">
        <div style="font-size:2.2rem;margin-bottom:10px;">💞</div>
        <div style="font-size:1rem;font-weight:800;margin-bottom:8px;">먼저 내 사주 분석이 필요합니다</div>
        <p style="font-size:0.84rem;color:var(--text-secondary);line-height:1.7;margin-bottom:16px;">짝궁합은 내 기본 사주를 기준으로 두 사람의 흐름을 비교합니다.</p>
        <a href="<?= SITE_URL ?>/pages/analyze.php?type=comprehensive" class="btn btn-primary">내 사주 먼저 보기</a>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header"><span class="card-title">내 기준 정보</span></div>
        <div class="mini-card-grid">
            <div class="mini-stat-card"><div class="mini-stat-label">최근 분석일</div><div class="mini-stat-value"><?= h(formatDate($record['created_at'], 'Y.m.d')) ?></div></div>
            <div class="mini-stat-card"><div class="mini-stat-label">내 일주</div><div class="mini-stat-value"><?= h($record['day_pillar']) ?></div></div>
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
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">상대 생년</label>
            <select name="birth_year" class="form-select">
                <option value="">선택해 주세요</option>
                <?php for ($year = (int)date('Y'); $year >= 1940; $year--): ?>
                <option value="<?= $year ?>" <?= (string)$year === (string)$form['birth_year'] ? 'selected' : '' ?>><?= $year ?>년</option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="mini-card-grid">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">월</label>
                <select name="birth_month" class="form-select">
                    <option value="">월</option>
                    <?php for ($month = 1; $month <= 12; $month++): ?>
                    <option value="<?= $month ?>" <?= (string)$month === (string)$form['birth_month'] ? 'selected' : '' ?>><?= $month ?>월</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">일</label>
                <select name="birth_day" class="form-select">
                    <option value="">일</option>
                    <?php for ($day = 1; $day <= 31; $day++): ?>
                    <option value="<?= $day ?>" <?= (string)$day === (string)$form['birth_day'] ? 'selected' : '' ?>><?= $day ?>일</option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="mini-card-grid">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">출생 시간</label>
                <select name="birth_hour" class="form-select">
                    <?php for ($hour = 0; $hour <= 23; $hour++): ?>
                    <option value="<?= $hour ?>" <?= (string)$hour === (string)$form['birth_hour'] ? 'selected' : '' ?>><?= str_pad((string)$hour, 2, '0', STR_PAD_LEFT) ?>시</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">성별</label>
                <select name="gender" class="form-select">
                    <option value="female" <?= $form['gender'] === 'female' ? 'selected' : '' ?>>여성</option>
                    <option value="male" <?= $form['gender'] === 'male' ? 'selected' : '' ?>>남성</option>
                </select>
            </div>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">달력 종류</label>
            <select name="calendar_type" class="form-select">
                <option value="solar" <?= $form['calendar_type'] === 'solar' ? 'selected' : '' ?>>양력</option>
                <option value="lunar" <?= $form['calendar_type'] === 'lunar' ? 'selected' : '' ?>>음력</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">짝궁합 보기</button>
    </form>

    <?php if ($result): ?>
    <div class="card compat-score-card">
        <div style="font-size:0.78rem;color:var(--text-muted);">궁합 점수</div>
        <div style="font-size:2rem;font-weight:900;margin:6px 0;"><?= h($result['score']) ?>점</div>
        <div style="font-size:1rem;font-weight:800;"><?= h($result['relation_label']) ?></div>
        <p style="font-size:0.84rem;color:var(--text-secondary);line-height:1.7;margin-top:10px;"><?= h($result['relation_text']) ?></p>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">두 사람의 기본 결</span></div>
        <div class="mini-card-grid">
            <div class="mini-stat-card"><div class="mini-stat-label">내 일간</div><div class="mini-stat-value"><?= h($result['my']['day_master_element']) ?></div></div>
            <div class="mini-stat-card"><div class="mini-stat-label">상대 일간</div><div class="mini-stat-value"><?= h($result['partner']['day_master_element']) ?></div></div>
            <div class="mini-stat-card"><div class="mini-stat-label">내 띠</div><div class="mini-stat-value"><?= h($result['my']['year_pillar']['branch'] ?? '') ?></div></div>
            <div class="mini-stat-card"><div class="mini-stat-label">상대 띠</div><div class="mini-stat-value"><?= h($result['partner']['year_pillar']['branch'] ?? '') ?></div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">관계를 편하게 만드는 팁</span></div>
        <div class="result-story-box">
            <?php foreach ($result['tips'] as $tip): ?>
            <div style="margin-bottom:10px;">• <?= h($tip) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>