<?php
/**
 * 관리자 - 회원 관리
 */
require_once __DIR__ . '/../includes/admin_check.php';

$pdo = getDBConnection();

// 티켓 지급/차감 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'grant_tickets') {
        $userId = (int)$_POST['user_id'];
        $amount = (int)$_POST['amount'];
        $reason = trim($_POST['reason'] ?? '관리자 지급');
        
        if ($userId > 0 && $amount != 0) {
            if ($amount > 0) {
                addTickets($userId, $amount, $reason, getCurrentUser()['id']);
            } else {
                useTickets($userId, abs($amount), $reason);
            }
            setFlashMessage('success', "티켓 {$amount}장 처리 완료");
        }
        redirect('/admin/users.php?id=' . $userId);
    }
    
    if ($_POST['action'] === 'change_role') {
        $userId = (int)$_POST['user_id'];
        $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
        $stmt = $pdo->prepare("UPDATE saju_users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $userId]);
        setFlashMessage('success', '역할이 변경되었습니다.');
        redirect('/admin/users.php?id=' . $userId);
    }
}

// 개별 회원 상세
if (isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM saju_users WHERE id = ?");
    $stmt->execute([$userId]);
    $viewUser = $stmt->fetch();
    
    if (!$viewUser) {
        setFlashMessage('error', '회원을 찾을 수 없습니다.');
        redirect('/admin/users.php');
    }
    
    // 해당 회원의 분석 수
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM saju_fortune_history WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userAnalysis = $stmt->fetch()['cnt'];
    
    // 티켓 로그
    $stmt = $pdo->prepare("SELECT * FROM saju_ticket_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$userId]);
    $ticketLogs = $stmt->fetchAll();
}

// 회원 목록
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if ($search) {
    $where = "WHERE nickname LIKE ? OR email LIKE ?";
    $params = ["%{$search}%", "%{$search}%"];
}

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM saju_users $where");
$stmt->execute($params);
$total = $stmt->fetch()['cnt'];
$totalPages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM saju_users $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = '회원 관리 - ' . SITE_NAME;
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
        .badge-type { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 500; }
        .badge-admin { background: #fce4ec; color: #c62828; }
        .badge-user { background: #e3f2fd; color: #1565c0; }
        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 8px; text-decoration: none; display: inline-block; border: none; cursor: pointer; }
        .btn-info { background: #3498db; color: #fff; }
        .btn-success { background: #27ae60; color: #fff; }
        .btn-danger { background: #e74c3c; color: #fff; }
        .search-bar { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .search-bar input { flex: 1; padding: 0.6rem 1rem; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9rem; }
        .search-bar button { padding: 0.6rem 1rem; background: #3498db; color: #fff; border: none; border-radius: 8px; cursor: pointer; }
        .pagination { display: flex; gap: 0.25rem; justify-content: center; margin-top: 1rem; }
        .pagination a { padding: 0.4rem 0.75rem; border-radius: 6px; text-decoration: none; color: #2c3e50; background: #fff; border: 1px solid #ddd; font-size: 0.85rem; }
        .pagination a.active { background: #3498db; color: #fff; border-color: #3498db; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .detail-item .label { font-size: 0.8rem; color: #7f8c8d; }
        .detail-item .value { font-size: 1rem; font-weight: 500; color: #2c3e50; }
        .back-link { color: #3498db; text-decoration: none; font-size: 0.9rem; }
        @media (max-width: 768px) {
            .admin-sidebar { width: 60px; }
            .admin-sidebar .logo h2, .admin-sidebar .logo small, .admin-nav span { display: none; }
            .admin-nav a { padding: 0.85rem; justify-content: center; }
            .admin-main { margin-left: 60px; padding: 1rem; }
            .detail-grid { grid-template-columns: 1fr; }
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
                <li><a href="<?= SITE_URL ?>/admin/users.php" class="active"><i class="fas fa-users"></i><span>회원 관리</span></a></li>
                <li><a href="<?= SITE_URL ?>/admin/history.php"><i class="fas fa-scroll"></i><span>분석 기록</span></a></li>
                <li><a href="<?= SITE_URL ?>/admin/tickets.php"><i class="fas fa-ticket-alt"></i><span>티켓 관리</span></a></li>
                <li><a href="<?= SITE_URL ?>/admin/report.php"><i class="fas fa-file-alt"></i><span>보고서 생성</span></a></li>
                <li style="border-top:1px solid rgba(255,255,255,0.1);margin-top:1rem;padding-top:0.5rem;">
                    <a href="<?= SITE_URL ?>/pages/home.php"><i class="fas fa-home"></i><span>사이트로</span></a>
                </li>
            </ul>
        </nav>
    </aside>
    
    <main class="admin-main">
        <?php if (isset($viewUser)): ?>
        <!-- 회원 상세 -->
        <div class="admin-header">
            <h1><i class="fas fa-user"></i> 회원 상세</h1>
            <a href="<?= SITE_URL ?>/admin/users.php" class="back-link"><i class="fas fa-arrow-left"></i> 목록으로</a>
        </div>
        
        <?php if ($msg = getFlashMessage()): ?>
        <div style="padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;background:<?= $msg['type']==='success'?'#e8f5e9':'#ffebee' ?>;color:<?= $msg['type']==='success'?'#2e7d32':'#c62828' ?>;">
            <?= h($msg['message']) ?>
        </div>
        <?php endif; ?>
        
        <div class="admin-card">
            <h3>기본 정보</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="label">ID</div>
                    <div class="value">#<?= $viewUser['id'] ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">이메일</div>
                    <div class="value"><?= h($viewUser['email']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">닉네임</div>
                    <div class="value"><?= h($viewUser['nickname']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">전화번호</div>
                    <div class="value"><?= h($viewUser['phone'] ?? '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">보유 티켓</div>
                    <div class="value" style="color:#e74c3c;font-weight:700;"><?= (int)$viewUser['tickets'] ?>장</div>
                </div>
                <div class="detail-item">
                    <div class="label">분석 횟수</div>
                    <div class="value"><?= $userAnalysis ?>회</div>
                </div>
                <div class="detail-item">
                    <div class="label">역할</div>
                    <div class="value">
                        <span class="badge-type <?= $viewUser['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                            <?= $viewUser['role'] ?>
                        </span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="label">가입일</div>
                    <div class="value"><?= formatDate($viewUser['created_at']) ?></div>
                </div>
            </div>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <!-- 티켓 지급/차감 -->
            <div class="admin-card">
                <h3><i class="fas fa-ticket-alt"></i> 티켓 지급/차감</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="grant_tickets">
                    <input type="hidden" name="user_id" value="<?= $viewUser['id'] ?>">
                    <div style="margin-bottom:0.75rem;">
                        <label style="display:block;font-size:0.8rem;color:#7f8c8d;margin-bottom:0.25rem;">수량 (음수=차감)</label>
                        <input type="number" name="amount" required style="width:100%;padding:0.5rem;border:1px solid #ddd;border-radius:6px;" placeholder="예: 5 또는 -3">
                    </div>
                    <div style="margin-bottom:0.75rem;">
                        <label style="display:block;font-size:0.8rem;color:#7f8c8d;margin-bottom:0.25rem;">사유</label>
                        <input type="text" name="reason" style="width:100%;padding:0.5rem;border:1px solid #ddd;border-radius:6px;" placeholder="관리자 지급" value="관리자 지급">
                    </div>
                    <button type="submit" class="btn-sm btn-success">처리</button>
                </form>
            </div>
            
            <!-- 역할 변경 -->
            <div class="admin-card">
                <h3><i class="fas fa-shield-halved"></i> 역할 변경</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_role">
                    <input type="hidden" name="user_id" value="<?= $viewUser['id'] ?>">
                    <div style="margin-bottom:0.75rem;">
                        <select name="role" style="width:100%;padding:0.5rem;border:1px solid #ddd;border-radius:6px;">
                            <option value="user" <?= $viewUser['role'] === 'user' ? 'selected' : '' ?>>일반 회원</option>
                            <option value="admin" <?= $viewUser['role'] === 'admin' ? 'selected' : '' ?>>관리자</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-sm btn-info" onclick="return confirm('역할을 변경하시겠습니까?')">변경</button>
                </form>
            </div>
        </div>
        
        <?php if (!empty($ticketLogs)): ?>
        <div class="admin-card">
            <h3>티켓 로그 (최근 20건)</h3>
            <table class="admin-table">
                <thead><tr><th>일시</th><th>유형</th><th>수량</th><th>사유</th></tr></thead>
                <tbody>
                <?php foreach ($ticketLogs as $log): ?>
                <tr>
                    <td><?= formatDate($log['created_at']) ?></td>
                    <td><?= $log['action'] === 'use' ? '사용' : ($log['action'] === 'add' ? '지급' : $log['action']) ?></td>
                    <td style="color:<?= $log['action']==='use'?'#e74c3c':'#27ae60' ?>;font-weight:600;">
                        <?= $log['action']==='use'?'-':'+' ?><?= abs((int)$log['amount']) ?>
                    </td>
                    <td><?= h($log['reason']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- 회원 목록 -->
        <div class="admin-header">
            <h1><i class="fas fa-users"></i> 회원 관리</h1>
            <span style="color:#7f8c8d;font-size:0.85rem;">총 <?= number_format($total) ?>명</span>
        </div>
        
        <div class="admin-card">
            <form class="search-bar" method="GET">
                <input type="text" name="q" value="<?= h($search) ?>" placeholder="닉네임 또는 이메일 검색...">
                <button type="submit"><i class="fas fa-search"></i> 검색</button>
            </form>
            
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>닉네임</th>
                        <th>이메일</th>
                        <th>티켓</th>
                        <th>역할</th>
                        <th>가입일</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>#<?= $u['id'] ?></td>
                    <td><?= h($u['nickname']) ?></td>
                    <td><?= h($u['email']) ?></td>
                    <td><strong><?= (int)$u['tickets'] ?></strong></td>
                    <td><span class="badge-type <?= $u['role']==='admin'?'badge-admin':'badge-user' ?>"><?= $u['role'] ?></span></td>
                    <td><?= formatDate($u['created_at'], 'Y-m-d') ?></td>
                    <td><a href="?id=<?= $u['id'] ?>" class="btn-sm btn-info">상세</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = max(1,$page-3); $i <= min($totalPages,$page+3); $i++): ?>
                <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>" class="<?= $i===$page?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
