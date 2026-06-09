<?php
/**
 * 관리자 - 티켓 관리
 */
require_once __DIR__ . '/../includes/admin_check.php';

$pdo = getDBConnection();

// 일괄 지급 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'bulk_grant') {
        $amount = (int)$_POST['amount'];
        $reason = trim($_POST['reason'] ?? '관리자 일괄 지급');
        
        if ($amount > 0) {
            $stmt = $pdo->query("SELECT id FROM saju_users WHERE role = 'user'");
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($userIds as $uid) {
                addTickets($uid, $amount, $reason, getCurrentUser()['id']);
            }
            setFlashMessage('success', count($userIds) . '명에게 ' . $amount . '장 지급 완료');
        }
        redirect('/admin/tickets.php');
    }
    
    if ($_POST['action'] === 'single_grant') {
        $email = trim($_POST['email'] ?? '');
        $amount = (int)$_POST['amount'];
        $reason = trim($_POST['reason'] ?? '관리자 지급');
        
        $stmt = $pdo->prepare("SELECT id FROM saju_users WHERE email = ?");
        $stmt->execute([$email]);
        $targetUser = $stmt->fetch();
        
        if ($targetUser && $amount != 0) {
            if ($amount > 0) {
                addTickets($targetUser['id'], $amount, $reason, getCurrentUser()['id']);
            } else {
                useTickets($targetUser['id'], abs($amount), $reason);
            }
            setFlashMessage('success', $email . '에게 ' . $amount . '장 처리 완료');
        } else {
            setFlashMessage('error', '회원을 찾을 수 없거나 수량이 올바르지 않습니다.');
        }
        redirect('/admin/tickets.php');
    }
}

// 최근 티켓 로그
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM saju_ticket_logs");
$total = $stmt->fetch()['cnt'];
$totalPages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("
    SELECT l.*, u.nickname, u.email
    FROM saju_ticket_logs l 
    JOIN saju_users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$logs = $stmt->fetchAll();

// 통계
$stmt = $pdo->query("SELECT SUM(amount) as total FROM saju_ticket_logs WHERE action = 'add'");
$totalGranted = (int)($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->query("SELECT SUM(amount) as total FROM saju_ticket_logs WHERE action = 'use'");
$totalUsed = (int)($stmt->fetch()['total'] ?? 0);

$pageTitle = '티켓 관리 - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f5f6fa; font-family: 'Noto Sans KR', sans-serif; }
        .admin-wrap { display: flex; min-height: 100vh; }
        .admin-sidebar { width: 250px; background: #2c3e50; color: #ecf0f1; position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; z-index: 100; }
        .admin-sidebar .logo { padding: 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .admin-sidebar .logo h2 { margin: 0; color: #fff; font-size: 1.2rem; }
        .admin-sidebar .logo small { color: rgba(255,255,255,0.5); font-size: 0.75rem; }
        .admin-nav { list-style: none; padding: 0.5rem 0; margin: 0; }
        .admin-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1.5rem; color: rgba(255,255,255,0.7); text-decoration: none; transition: 0.2s; }
        .admin-nav a:hover, .admin-nav a.active { background: rgba(255,255,255,0.1); color: #fff; }
        .admin-nav a.active { border-left: 3px solid #e74c3c; }
        .admin-nav a i { width: 20px; text-align: center; }
        .admin-main { flex: 1; margin-left: 250px; padding: 1.5rem; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .admin-header h1 { font-size: 1.4rem; color: #2c3e50; margin: 0; }
        .admin-card { background: #fff; border-radius: 12px; padding: 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 1.5rem; }
        .admin-card h3 { margin: 0 0 1rem; font-size: 1rem; color: #2c3e50; }
        .admin-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .admin-table th { background: #f8f9fa; padding: 0.75rem; text-align: left; font-weight: 500; color: #7f8c8d; border-bottom: 1px solid #eee; }
        .admin-table td { padding: 0.75rem; border-bottom: 1px solid #f0f0f0; }
        .admin-table tr:hover { background: #fafafa; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: #fff; border-radius: 12px; padding: 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat-card .label { font-size: 0.85rem; color: #7f8c8d; margin-bottom: 0.5rem; }
        .stat-card .value { font-size: 1.8rem; font-weight: 700; }
        .stat-card.green .value { color: #27ae60; }
        .stat-card.red .value { color: #e74c3c; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group { margin-bottom: 0.75rem; }
        .form-group label { display: block; font-size: 0.8rem; color: #7f8c8d; margin-bottom: 0.25rem; }
        .form-group input, .form-group select { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.85rem; border-radius: 8px; border: none; cursor: pointer; display: inline-block; }
        .btn-success { background: #27ae60; color: #fff; }
        .btn-warning { background: #f39c12; color: #fff; }
        .pagination { display: flex; gap: 0.25rem; justify-content: center; margin-top: 1rem; }
        .pagination a { padding: 0.4rem 0.75rem; border-radius: 6px; text-decoration: none; color: #2c3e50; background: #fff; border: 1px solid #ddd; font-size: 0.85rem; }
        .pagination a.active { background: #3498db; color: #fff; border-color: #3498db; }
        @media (max-width: 768px) {
            .admin-sidebar { width: 60px; }
            .admin-sidebar .logo h2, .admin-sidebar .logo small, .admin-nav span { display: none; }
            .admin-nav a { padding: 0.85rem; justify-content: center; }
            .admin-main { margin-left: 60px; padding: 1rem; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="admin-wrap">
    <aside class="admin-sidebar">
        <div class="logo">
            <h2>🔮 <?= SITE_NAME ?></h2>
            <small>관리자 패널</small>
        </div>
        <nav>
            <ul class="admin-nav">
                <li><a href="<?= SITE_URL ?>/admin/index.php"><i class="fas fa-chart-pie"></i><span>대시보드</span></a></li>
                <li><a href="<?= SITE_URL ?>/admin/users.php"><i class="fas fa-users"></i><span>회원 관리</span></a></li>
                <li><a href="<?= SITE_URL ?>/admin/history.php"><i class="fas fa-scroll"></i><span>분석 기록</span></a></li>
                <li><a href="<?= SITE_URL ?>/admin/tickets.php" class="active"><i class="fas fa-ticket-alt"></i><span>티켓 관리</span></a></li>
                <li><a href="<?= SITE_URL ?>/admin/report.php"><i class="fas fa-file-alt"></i><span>보고서 생성</span></a></li>
                <li style="border-top:1px solid rgba(255,255,255,0.1);margin-top:1rem;padding-top:0.5rem;">
                    <a href="<?= SITE_URL ?>/pages/home.php"><i class="fas fa-home"></i><span>사이트로</span></a>
                </li>
            </ul>
        </nav>
    </aside>
    
    <main class="admin-main">
        <div class="admin-header">
            <h1><i class="fas fa-ticket-alt"></i> 티켓 관리</h1>
        </div>
        
        <?php if ($msg = getFlashMessage()): ?>
        <div style="padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;background:<?= $msg['type']==='success'?'#e8f5e9':'#ffebee' ?>;color:<?= $msg['type']==='success'?'#2e7d32':'#c62828' ?>;">
            <?= h($msg['message']) ?>
        </div>
        <?php endif; ?>
        
        <div class="stat-grid">
            <div class="stat-card green">
                <div class="label">총 지급 티켓</div>
                <div class="value"><?= number_format($totalGranted) ?></div>
            </div>
            <div class="stat-card red">
                <div class="label">총 사용 티켓</div>
                <div class="value"><?= number_format($totalUsed) ?></div>
            </div>
        </div>
        
        <div class="form-row">
            <!-- 개별 지급 -->
            <div class="admin-card">
                <h3><i class="fas fa-user"></i> 개별 티켓 지급/차감</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="single_grant">
                    <div class="form-group">
                        <label>회원 이메일</label>
                        <input type="email" name="email" required placeholder="회원 이메일 입력">
                    </div>
                    <div class="form-group">
                        <label>수량 (음수=차감)</label>
                        <input type="number" name="amount" required placeholder="예: 5 또는 -3">
                    </div>
                    <div class="form-group">
                        <label>사유</label>
                        <input type="text" name="reason" value="관리자 지급" placeholder="지급 사유">
                    </div>
                    <button type="submit" class="btn-sm btn-success">처리</button>
                </form>
            </div>
            
            <!-- 일괄 지급 -->
            <div class="admin-card">
                <h3><i class="fas fa-users"></i> 전체 회원 일괄 지급</h3>
                <form method="POST" onsubmit="return confirm('전체 회원에게 티켓을 지급하시겠습니까?')">
                    <input type="hidden" name="action" value="bulk_grant">
                    <div class="form-group">
                        <label>지급 수량</label>
                        <input type="number" name="amount" min="1" required placeholder="지급할 티켓 수">
                    </div>
                    <div class="form-group">
                        <label>사유</label>
                        <input type="text" name="reason" value="이벤트 보상" placeholder="지급 사유">
                    </div>
                    <button type="submit" class="btn-sm btn-warning">전체 지급</button>
                </form>
            </div>
        </div>
        
        <div class="admin-card">
            <h3>최근 티켓 로그</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>일시</th>
                        <th>회원</th>
                        <th>유형</th>
                        <th>수량</th>
                        <th>사유</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= formatDate($log['created_at']) ?></td>
                    <td>
                        <a href="<?= SITE_URL ?>/admin/users.php?id=<?= $log['user_id'] ?>" style="color:#3498db;text-decoration:none;">
                            <?= h($log['nickname']) ?>
                        </a>
                    </td>
                    <td><?= $log['action'] === 'use' ? '사용' : '지급' ?></td>
                    <td style="color:<?= $log['action']==='use'?'#e74c3c':'#27ae60' ?>;font-weight:600;">
                        <?= $log['action']==='use'?'-':'+' ?><?= abs((int)$log['amount']) ?>
                    </td>
                    <td><?= h($log['reason']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = max(1,$page-3); $i <= min($totalPages,$page+3); $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= $i===$page?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
