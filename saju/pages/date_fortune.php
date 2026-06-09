<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/fortune_services.php';

$user = getCurrentUser();
$mode = $_GET['mode'] ?? 'today';
$configMap = [
    'today' => ['title' => '오늘의 운세', 'icon' => 'fa-calendar-day', 'date' => 'today', 'desc' => '오늘 하루의 흐름을 바로 확인합니다.'],
    'tomorrow' => ['title' => '내일의 운세', 'icon' => 'fa-clock', 'date' => '+1 day', 'desc' => '내일의 분위기와 주의 포인트를 미리 봅니다.'],
    'pick' => ['title' => '지정일 운세', 'icon' => 'fa-calendar-check', 'date' => 'today', 'desc' => '원하는 날짜를 골라 흐름을 살펴봅니다.'],
];
$mode = array_key_exists($mode, $configMap) ? $mode : 'today';
$config = $configMap[$mode];

$selectedDate = $mode === 'pick'
    ? DateTimeImmutable::createFromFormat('Y-m-d', $_GET['date'] ?? date('Y-m-d'))
    : new DateTimeImmutable($config['date']);
if (!$selectedDate) {
    $selectedDate = new DateTimeImmutable('today');
}

$record = fs_get_latest_analysis_record($user['id']);
$fortune = $record ? fs_build_date_fortune($record, (int)$user['id'], $selectedDate) : null;
$story = ($record && $fortune) ? fs_build_daily_longform_story($record, $fortune, $selectedDate, $mode) : null;

$pageTitle = $config['title'] . ' - ' . SITE_NAME;
include INCLUDES_PATH . '/header.php';
?>

<div class="animate-fade">
    <div class="card">
        <div style="display:flex;align-items:center;gap:12px;justify-content:space-between;">
            <div>
                <div style="font-size:0.82rem;color:var(--text-muted);">날짜 운세</div>
                <h2 style="font-size:1.35rem;font-weight:900;"><?= h($config['title']) ?></h2>
                <p style="font-size:0.84rem;color:var(--text-secondary);margin-top:6px;line-height:1.7;"><?= h($config['desc']) ?></p>
            </div>
            <div class="service-menu-icon"><i class="fas <?= h($config['icon']) ?>"></i></div>
        </div>
    </div>

    <div class="mini-card-grid" style="margin-bottom:16px;">
        <a href="<?= SITE_URL ?>/pages/date_fortune.php?mode=today" class="mini-stat-card" style="text-align:center;<?= $mode === 'today' ? 'border-color:#F2D35A;background:var(--primary-light);' : '' ?>">오늘</a>
        <a href="<?= SITE_URL ?>/pages/date_fortune.php?mode=tomorrow" class="mini-stat-card" style="text-align:center;<?= $mode === 'tomorrow' ? 'border-color:#F2D35A;background:var(--primary-light);' : '' ?>">내일</a>
        <a href="<?= SITE_URL ?>/pages/date_fortune.php?mode=pick" class="mini-stat-card" style="text-align:center;<?= $mode === 'pick' ? 'border-color:#F2D35A;background:var(--primary-light);' : '' ?>">지정일</a>
    </div>

    <?php if ($mode === 'pick'): ?>
    <form method="GET" class="card">
        <input type="hidden" name="mode" value="pick">
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">확인할 날짜</label>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="date" name="date" class="form-select" value="<?= h($selectedDate->format('Y-m-d')) ?>">
                <button type="submit" class="btn btn-primary">보기</button>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <?php if (!$record): ?>
    <div class="card" style="text-align:center;">
        <div style="font-size:2.2rem;margin-bottom:10px;">🔮</div>
        <div style="font-size:1rem;font-weight:800;margin-bottom:8px;">먼저 사주 분석이 필요합니다</div>
        <p style="font-size:0.84rem;color:var(--text-secondary);line-height:1.7;margin-bottom:16px;">생년월일시를 한 번 분석해 두면 오늘, 내일, 지정일 운세를 모두 이어서 볼 수 있습니다.</p>
        <a href="<?= SITE_URL ?>/pages/analyze.php?type=comprehensive" class="btn btn-primary">정통사주 분석하기</a>
    </div>
    <?php elseif ($fortune): ?>
    <div class="card yearly-hero-banner">
        <div class="yearly-hero-inner">
            <div class="yearly-hero-eyebrow"><?= h($selectedDate->format('Y년 m월 d일')) ?> 기준</div>
            <div class="yearly-hero-title"><?= h($config['title']) ?></div>
            <div class="yearly-hero-subtitle"><?= h($story['hero'] ?? '') ?></div>
        </div>
    </div>

    <div class="card">
        <div class="score-container" style="padding-top:12px;">
            <div class="score-circle">
                <span class="score-number"><?= h($fortune['score']) ?></span>
                <span class="score-label"><?= h($selectedDate->format('m월 d일')) ?></span>
            </div>
            <p class="score-description"><?= h($fortune['description']) ?></p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">세부 점수 요약</span>
            <span style="font-size:0.78rem;color:var(--text-muted);"><?= h($selectedDate->format('Y년 m월 d일')) ?></span>
        </div>
        <div class="mini-card-grid">
            <?php foreach (($story['summary_cards'] ?? []) as $category): ?>
            <div class="mini-stat-card">
                <div class="mini-stat-label"><?= h($category['label']) ?></div>
                <div class="mini-stat-value"><?= h($category['score']) ?>점</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php foreach (($story['sections'] ?? []) as $section): ?>
    <div class="card yearly-longform-section">
        <div class="yearly-section-title"><?= h($section['title']) ?></div>
        <?php foreach (($section['paragraphs'] ?? []) as $paragraph): ?>
        <?php if (trim((string)$paragraph) !== ''): ?>
        <p class="yearly-body-paragraph"><?= h($paragraph) ?></p>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>