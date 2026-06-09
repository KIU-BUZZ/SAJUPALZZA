<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/fortune_services.php';

$user = getCurrentUser();
$record = fs_get_latest_analysis_record($user['id']);
$story = $record ? fs_build_daeun_story($record) : null;
$currentDaeun = $record ? fs_find_current_daeun(fs_get_runtime_daeun($record), $record) : null;

$pageTitle = '대운풀이 - ' . SITE_NAME;
include INCLUDES_PATH . '/header.php';
?>

<div class="animate-fade">
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div style="font-size:0.82rem;color:var(--text-muted);">10년 흐름 읽기</div>
                <h2 style="font-size:1.35rem;font-weight:900;">대운풀이</h2>
                <p style="font-size:0.84rem;color:var(--text-secondary);margin-top:6px;line-height:1.7;">현재 10년 흐름과 다음 대운까지 길게 풀어 읽습니다.</p>
            </div>
            <div class="service-menu-icon"><i class="fas fa-arrows-rotate"></i></div>
        </div>
    </div>

    <?php if (!$record): ?>
    <div class="card" style="text-align:center;">
        <div style="font-size:2.2rem;margin-bottom:10px;">🌊</div>
        <div style="font-size:1rem;font-weight:800;margin-bottom:8px;">먼저 사주 분석이 필요합니다</div>
        <p style="font-size:0.84rem;color:var(--text-secondary);line-height:1.7;margin-bottom:16px;">대운풀이는 최근 사주 분석을 기준으로 10년 단위 흐름을 깊게 읽는 기능입니다.</p>
        <a href="<?= SITE_URL ?>/pages/analyze.php?type=comprehensive" class="btn btn-primary">정통사주 분석하기</a>
    </div>
    <?php else: ?>
    <div class="card yearly-hero-banner">
        <div class="yearly-hero-inner">
            <div class="yearly-hero-eyebrow">최근 분석일 <?= h(formatDate($record['created_at'], 'Y.m.d')) ?></div>
            <div class="yearly-hero-title">대운풀이</div>
            <div class="yearly-hero-subtitle"><?= h($story['hero'] ?? '') ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">대운 핵심 요약</span>
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

    <div class="card">
        <div class="card-header">
            <span class="card-title">대운 타임라인</span>
            <span style="font-size:0.78rem;color:var(--text-muted);"><?= $currentDaeun ? h(($currentDaeun['age_start'] ?? 0) . '~' . ($currentDaeun['age_end'] ?? 0) . '세 진행 중') : '현재 구간 확인 중' ?></span>
        </div>
        <div class="daeun-timeline">
            <?php foreach (($story['timeline'] ?? []) as $daeun): ?>
            <div class="daeun-item <?= ((int)($daeun['index'] ?? -1) === (int)($currentDaeun['index'] ?? -2)) ? 'highlight' : '' ?>">
                <div class="daeun-age"><?= h(($daeun['age_start'] ?? 0) . '~' . ($daeun['age_end'] ?? 0) . '세') ?></div>
                <div class="daeun-pillar"><?= h(($daeun['stem'] ?? '') . ($daeun['branch'] ?? '')) ?></div>
                <div class="daeun-score"><?= h($daeun['score'] ?? 0) ?>점</div>
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

    <a href="<?= SITE_URL ?>/pages/result.php?id=<?= $record['id'] ?>" class="btn btn-primary btn-block">전체 분석 리포트 보기</a>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>