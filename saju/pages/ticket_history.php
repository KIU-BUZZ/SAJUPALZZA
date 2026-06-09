<?php
/**
 * 티켓 내역
 */
require_once __DIR__ . '/../includes/auth_check.php';

$user = getCurrentUser();
$pdo = getDBConnection();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 전체 수
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM saju_ticket_logs WHERE user_id = ?");
$stmt->execute([$user['id']]);
$total = $stmt->fetch()['cnt'];
$totalPages = max(1, ceil($total / $perPage));

// 내역 목록
$stmt = $pdo->prepare("
    SELECT * FROM saju_ticket_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$user['id'], $perPage, $offset]);
$logs = $stmt->fetchAll();

$pageTitle = '티켓 내역 - ' . SITE_NAME;
include INCLUDES_PATH . '/header.php';
?>

<div class="animate-fade">
    <div class="page-header">
        <h2 class="page-title">티켓 내역</h2>
        <p class="page-desc">현재 보유: <strong><?= (int)$user['tickets'] ?></strong>장</p>
    </div>
    
    <?php if (empty($logs)): ?>
    <div class="empty-state">
        <i class="fas fa-ticket-alt"></i>
        <p>티켓 내역이 없습니다</p>
    </div>
    <?php else: ?>
    <div class="ticket-log-list">
        <?php foreach ($logs as $log): ?>
        <div class="ticket-log-item">
            <div class="ticket-log-icon <?= $log['action'] === 'use' ? 'use' : 'add' ?>">
                <i class="fas <?= $log['action'] === 'use' ? 'fa-minus' : 'fa-plus' ?>"></i>
            </div>
            <div class="ticket-log-info">
                <div class="ticket-log-reason"><?= h($log['reason']) ?></div>
                <div class="ticket-log-date"><?= formatDate($log['created_at']) ?></div>
            </div>
            <div class="ticket-log-amount <?= $log['action'] === 'use' ? 'use' : 'add' ?>">
                <?= $log['action'] === 'use' ? '-' : '+' ?><?= abs((int)$log['amount']) ?>장
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>" class="pagination-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
        <a href="?page=<?= $i ?>" class="pagination-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>" class="pagination-btn">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.ticket-log-list { display: flex; flex-direction: column; gap: 0.5rem; }
.ticket-log-item {
    display: flex; align-items: center; gap: 0.75rem;
    background: var(--bg-card); border-radius: 12px; padding: 1rem;
}
.ticket-log-icon {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem;
}
.ticket-log-icon.add { background: rgba(76,175,80,0.1); color: #4CAF50; }
.ticket-log-icon.use { background: rgba(244,67,54,0.1); color: #f44336; }
.ticket-log-info { flex: 1; }
.ticket-log-reason { font-size: 0.95rem; font-weight: 500; margin-bottom: 0.2rem; }
.ticket-log-date { font-size: 0.8rem; color: var(--text-muted); }
.ticket-log-amount { font-weight: 700; font-size: 1rem; }
.ticket-log-amount.add { color: #4CAF50; }
.ticket-log-amount.use { color: #f44336; }
</style>

<?php include INCLUDES_PATH . '/footer.php'; ?>
