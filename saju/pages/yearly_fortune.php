<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/fortune_services.php';

$user = getCurrentUser();
$mode = $_GET['mode'] ?? 'yearly';
$mode = in_array($mode, ['yearly', 'tojung'], true) ? $mode : 'yearly';
$pageLabel = $mode === 'tojung' ? '토정비결' : '신년운세';
$year = (int)($_GET['year'] ?? date('Y'));
$year = max((int)date('Y') - 1, min((int)date('Y') + 5, $year));

$record = fs_get_latest_analysis_record($user['id']);
$yearFortune = $record ? fs_get_year_fortune_for_record($record, $year) : null;
$monthlyFortunes = $record ? fs_get_monthly_fortunes_for_record($record, $year) : [];
$yearlyStory = ($record && $yearFortune)
    ? fs_build_yearly_longform($record, $yearFortune, $monthlyFortunes, $year, $mode)
    : null;

$relatedFortunes = [
    ['label' => '정통사주', 'icon' => 'fa-pen-nib', 'url' => SITE_URL . '/pages/traditional_saju.php'],
    ['label' => '대운풀이', 'icon' => 'fa-arrows-rotate', 'url' => SITE_URL . '/pages/daeun_fortune.php'],
    ['label' => '재물운', 'icon' => 'fa-sack-dollar', 'url' => SITE_URL . '/pages/focus_fortune.php?type=wealth'],
    ['label' => '애정운', 'icon' => 'fa-heart', 'url' => SITE_URL . '/pages/focus_fortune.php?type=love'],
    ['label' => '직업운', 'icon' => 'fa-briefcase', 'url' => SITE_URL . '/pages/focus_fortune.php?type=career'],
    ['label' => '오늘의 운세', 'icon' => 'fa-calendar-day', 'url' => SITE_URL . '/pages/date_fortune.php?mode=today'],
];

$pageTitle = $pageLabel . ' - ' . SITE_NAME;
include INCLUDES_PATH . '/header.php';
?>

<div class="animate-fade">
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div style="font-size:0.82rem;color:var(--text-muted);">연간 흐름 읽기</div>
                <h2 style="font-size:1.35rem;font-weight:900;"><?= h($year) ?>년 <?= h($pageLabel) ?></h2>
                <p style="font-size:0.84rem;color:var(--text-secondary);margin-top:6px;line-height:1.7;">
                    <?= $mode === 'tojung' ? '월별 흐름을 길게 읽고 토정 포인트를 자세히 정리합니다.' : '한 해 전체의 기회, 주의 구간, 흐름의 방향을 정리합니다.' ?>
                </p>
            </div>
            <div class="service-menu-icon"><i class="fas <?= $mode === 'tojung' ? 'fa-book-open' : 'fa-star' ?>"></i></div>
        </div>
    </div>

    <form method="GET" class="card">
        <input type="hidden" name="mode" value="<?= h($mode) ?>">
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">연도 선택</label>
            <div style="display:flex;gap:8px;align-items:center;">
                <select name="year" class="form-select">
                    <?php for ($y = (int)date('Y') - 1; $y <= (int)date('Y') + 5; $y++): ?>
                    <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?>년</option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-primary">보기</button>
            </div>
        </div>
    </form>

    <?php if (!$record): ?>
    <div class="card" style="text-align:center;">
        <div style="font-size:2.2rem;margin-bottom:10px;">🌟</div>
        <div style="font-size:1rem;font-weight:800;margin-bottom:8px;">먼저 정통사주 분석이 필요합니다</div>
        <p style="font-size:0.84rem;color:var(--text-secondary);line-height:1.7;margin-bottom:16px;">신년운세와 토정비결은 기본 사주를 바탕으로 연도를 해석합니다.</p>
        <a href="<?= SITE_URL ?>/pages/analyze.php?type=comprehensive" class="btn btn-primary">분석 시작하기</a>
    </div>
    <?php elseif ($yearFortune): ?>
    <div class="card yearly-hero-banner">
        <div class="yearly-hero-inner">
            <div class="yearly-hero-eyebrow">
                <?= $mode === 'tojung' ? '월별 흐름까지 길게 읽는 올해의 운세 지도' : '새해와 함께 펼쳐질 당신의 흐름' ?>
            </div>
            <div class="yearly-hero-title"><?= h(substr((string)$year, 2)) ?>년 <?= h($pageLabel) ?></div>
            <div class="yearly-hero-subtitle"><?= h($yearlyStory['hero_subtitle'] ?? '') ?></div>
        </div>
    </div>

    <div class="card">
        <div class="score-container" style="padding-top:10px;">
            <div class="score-circle">
                <span class="score-number"><?= h($yearFortune['score'] ?? 50) ?></span>
                <span class="score-label"><?= h($year) ?>년</span>
            </div>
            <p class="score-description"><?= h($yearlyStory['hero_subtitle'] ?? ($pageLabel . ' 흐름 요약')) ?></p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">한눈에 보는 올해 요약</span>
        </div>
        <div class="yearly-summary-grid">
            <?php foreach (($yearlyStory['summary_cards'] ?? []) as $card): ?>
            <div class="mini-stat-card yearly-summary-card">
                <div class="mini-stat-label"><?= h($card['label']) ?></div>
                <div class="mini-stat-value"><?= h($card['value']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php foreach (($yearlyStory['sections'] ?? []) as $section): ?>
    <div class="card yearly-longform-section">
        <div class="yearly-section-title"><?= h($section['title']) ?></div>
        <?php foreach (($section['paragraphs'] ?? []) as $paragraph): ?>
        <?php if (trim((string)$paragraph) !== ''): ?>
        <p class="yearly-body-paragraph"><?= h($paragraph) ?></p>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($yearlyStory['quarters']) && $mode !== 'tojung'): ?>
    <div class="card">
        <div class="card-header">
            <span class="card-title">분기별 핵심 포인트</span>
        </div>
        <div class="yearly-quarter-grid">
            <?php foreach ($yearlyStory['quarters'] as $quarter): ?>
            <div class="yearly-quarter-card">
                <div class="forecast-month-title"><?= h($quarter['title']) ?></div>
                <div class="forecast-month-score"><?= h($quarter['score']) ?>점</div>
                <div class="forecast-month-text"><?= h($quarter['text']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><?= $mode === 'tojung' ? '월별 토정비결' : '한눈에 보는 12개월' ?></span>
        </div>
        <div class="forecast-month-grid">
            <?php foreach ($monthlyFortunes as $month): ?>
            <div class="forecast-month-card">
                <div class="forecast-month-title"><?= h($month['month']) ?>월 · <?= h($month['label']) ?></div>
                <div class="forecast-month-score"><?= h($month['score']) ?>점 · <?= h($month['branch_element']) ?> 기운</div>
                <div class="forecast-month-text"><?= h($month['focus']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card yearly-related-shell">
        <div class="card-header" style="justify-content:center;">
            <span class="card-title">그외 다양한 운들도 확인해보세요!</span>
        </div>
        <div class="yearly-related-grid">
            <?php foreach ($relatedFortunes as $fortune): ?>
            <a href="<?= h($fortune['url']) ?>" class="yearly-related-card">
                <span class="yearly-related-icon"><i class="fas <?= h($fortune['icon']) ?>"></i></span>
                <span class="yearly-related-name"><?= h($fortune['label']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>