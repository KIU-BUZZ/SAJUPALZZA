<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/fortune_services.php';

$user = getCurrentUser();
$record = fs_get_latest_analysis_record($user['id']);
$story = $record ? fs_build_traditional_saju_story($record) : null;

$pageTitle = '정통사주 - ' . SITE_NAME;
include INCLUDES_PATH . '/header.php';
?>

<div class="animate-fade">
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div style="font-size:0.82rem;color:var(--text-muted);">깊이 읽는 사주</div>
                <h2 style="font-size:1.35rem;font-weight:900;">정통사주</h2>
                <p style="font-size:0.84rem;color:var(--text-secondary);margin-top:6px;line-height:1.7;">최근 분석을 기준으로 사주의 구조와 인생 흐름을 장문으로 풀어 읽습니다.</p>
            </div>
            <div class="service-menu-icon"><i class="fas fa-pen-nib"></i></div>
        </div>
    </div>

    <?php if (!$record): ?>
    <div class="card" style="text-align:center;">
        <div style="font-size:2.1rem;margin-bottom:10px;">☯</div>
        <div style="font-size:1rem;font-weight:800;margin-bottom:8px;">최근 분석 기록이 없습니다</div>
        <p style="font-size:0.84rem;color:var(--text-secondary);line-height:1.7;margin-bottom:16px;">정통사주는 먼저 생년월일시를 분석한 뒤 깊게 읽는 기능입니다.</p>
        <a href="<?= SITE_URL ?>/pages/analyze.php?type=comprehensive" class="btn btn-primary">정통사주 분석하기</a>
    </div>
    <?php else: ?>
    <div class="card yearly-hero-banner">
        <div class="yearly-hero-inner">
            <div class="yearly-hero-eyebrow">최근 분석일 <?= h(formatDate($record['created_at'], 'Y.m.d')) ?></div>
            <div class="yearly-hero-title">정통사주</div>
            <div class="yearly-hero-subtitle"><?= h($story['hero'] ?? '') ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">최근 분석 기준</span>
            <span style="font-size:0.78rem;color:var(--text-muted);"><?= formatDate($record['created_at'], 'Y.m.d') ?></span>
        </div>
        <div class="mini-card-grid">
            <div class="mini-stat-card"><div class="mini-stat-label">생년월일</div><div class="mini-stat-value"><?= h(sprintf('%04d.%02d.%02d', (int)$record['birth_year'], (int)$record['birth_month'], (int)$record['birth_day'])) ?></div></div>
            <div class="mini-stat-card"><div class="mini-stat-label">출생시</div><div class="mini-stat-value"><?= h(sprintf('%02d시', (int)$record['birth_hour'])) ?></div></div>
            <div class="mini-stat-card"><div class="mini-stat-label">년주</div><div class="mini-stat-value"><?= h($record['year_pillar']) ?></div></div>
            <div class="mini-stat-card"><div class="mini-stat-label">월주</div><div class="mini-stat-value"><?= h($record['month_pillar']) ?></div></div>
            <div class="mini-stat-card"><div class="mini-stat-label">일주</div><div class="mini-stat-value"><?= h($record['day_pillar']) ?></div></div>
            <div class="mini-stat-card"><div class="mini-stat-label">시주</div><div class="mini-stat-value"><?= h($record['hour_pillar']) ?></div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">정통사주 핵심 요약</span>
        </div>
        <div class="yearly-summary-grid">
            <?php foreach (($story['summary_cards'] ?? []) as $card): ?>
            <div class="mini-stat-card yearly-summary-card">
                <div class="mini-stat-label"><?= h($card['label']) ?></div>
                <div class="mini-stat-value"><?= h($card['value']) ?></div>
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

    <a href="<?= SITE_URL ?>/pages/result.php?id=<?= $record['id'] ?>" class="btn btn-primary btn-block">전체 정통사주 리포트 보기</a>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>