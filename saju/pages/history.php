<?php
/**
 * 사주 분석 기록 목록 페이지
 */
require_once __DIR__ . '/../includes/auth_check.php';

$user = getCurrentUser();
$pdo = getDBConnection();

// 페이지네이션
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// 전체 수
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM saju_fortune_history WHERE user_id = ?");
$stmt->execute([$user['id']]);
$total = $stmt->fetch()['cnt'];
$totalPages = max(1, ceil($total / $perPage));

// 기록 목록
$stmt = $pdo->prepare("
    SELECT * FROM saju_fortune_history 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$user['id'], $perPage, $offset]);
$records = $stmt->fetchAll();

// 분석 유형 이름
$analysisNames = [
    'basic' => '사주팔자',
    'ohang' => '오행 분석',
];
foreach (PREMIUM_FEATURES as $key => $f) {
    $analysisNames[$key] = $f['name'];
}

$pageTitle = '분석 기록 - ' . SITE_NAME;
include INCLUDES_PATH . '/header.php';
?>

<div class="animate-fade">
    <div class="card">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.15rem; font-weight: 800;">분석 기록</h2>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;">총 <?= $total ?>개의 분석 기록</p>
            </div>
            <a href="<?= SITE_URL ?>/pages/analyze.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> 새 분석
            </a>
        </div>
    </div>
    
    <?php if (empty($records)): ?>
    <div class="empty-state">
        <i class="fas fa-clock-rotate-left"></i>
        <p>아직 분석 기록이 없습니다</p>
        <a href="<?= SITE_URL ?>/pages/analyze.php" class="btn btn-primary">
            <i class="fas fa-yin-yang"></i> 사주 분석하기
        </a>
    </div>
    <?php else: ?>
    <div class="history-list stagger-children">
        <?php foreach ($records as $record): ?>
        <a href="<?= SITE_URL ?>/pages/result.php?id=<?= $record['id'] ?>" class="history-item">
            <div class="history-icon">
                <?php if (in_array($record['analysis_type'], ['basic', 'ohang'])): ?>
                    <i class="fas fa-yin-yang"></i>
                <?php else: ?>
                    <i class="fas fa-crown" style="color: #FF9800;"></i>
                <?php endif; ?>
            </div>
            <div class="history-info">
                <div class="history-title">
                    <?= h($record['year_pillar'] . ' ' . $record['month_pillar'] . ' ' . $record['day_pillar'] . ' ' . $record['hour_pillar']) ?>
                </div>
                <div class="history-date">
                    <span class="card-badge <?= in_array($record['analysis_type'], ['basic', 'ohang']) ? 'free' : 'premium' ?>" style="font-size: 0.65rem; padding: 1px 6px;">
                        <?= $analysisNames[$record['analysis_type']] ?? $record['analysis_type'] ?>
                    </span>
                    <?= $record['birth_year'] ?>년 <?= $record['birth_month'] ?>월 <?= $record['birth_day'] ?>일 ·
                    <?= $record['gender'] === 'male' ? '남' : '여' ?> · 
                    <?= formatDate($record['created_at'], 'Y.m.d') ?>
                </div>
            </div>
            <div class="history-arrow"><i class="fas fa-chevron-right"></i></div>
        </a>
        <?php endforeach; ?>
    </div>
    
    <!-- 페이지네이션 -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        
        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <?php if ($p == $page): ?>
                <span class="current"><?= $p ?></span>
            <?php else: ?>
                <a href="?page=<?= $p ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
