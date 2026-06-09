<?php
/**
 * 관리자 - 대시보드
 */
require_once __DIR__ . '/../includes/admin_check.php';

$pdo = getDBConnection();

// 통계
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM saju_users");
$stats['total_users'] = $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM saju_users WHERE created_at >= CURDATE()");
$stats['today_users'] = $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM saju_fortune_history");
$stats['total_analysis'] = $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM saju_fortune_history WHERE created_at >= CURDATE()");
$stats['today_analysis'] = $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT SUM(tickets) as total FROM saju_users");
$stats['total_tickets'] = (int)($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM saju_ticket_logs WHERE action = 'use'");
$stats['used_tickets'] = $stmt->fetch()['cnt'];

// 최근 가입자
$stmt = $pdo->query("SELECT * FROM saju_users ORDER BY created_at DESC LIMIT 5");
$recentUsers = $stmt->fetchAll();

// 최근 분석
$stmt = $pdo->query("
    SELECT h.*, u.nickname, u.email 
    FROM saju_fortune_history h 
    JOIN saju_users u ON h.user_id = u.id 
    ORDER BY h.created_at DESC 
    LIMIT 5
");
$recentHistory = $stmt->fetchAll();

// 분석 유형 분포
$stmt = $pdo->query("
    SELECT analysis_type, COUNT(*) as cnt 
    FROM saju_fortune_history 
    GROUP BY analysis_type 
    ORDER BY cnt DESC
");
$typeStats = $stmt->fetchAll();

$pageTitle = '관리자 대시보드 - ' . SITE_NAME;
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
        .admin-sidebar {
            width: 250px; background: #2c3e50; color: #ecf0f1;
            position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; z-index: 100;
        }
        .admin-sidebar .logo {
            padding: 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .admin-sidebar .logo h2 { margin: 0; color: #fff; font-size: 1.2rem; }
        .admin-sidebar .logo small { color: rgba(255,255,255,0.5); font-size: 0.75rem; }
        .admin-nav { list-style: none; padding: 0.5rem 0; margin: 0; }
        .admin-nav a {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.85rem 1.5rem; color: rgba(255,255,255,0.7);
            text-decoration: none; transition: 0.2s;
        }
        .admin-nav a:hover, .admin-nav a.active {
            background: rgba(255,255,255,0.1); color: #fff;
        }
        .admin-nav a.active { border-left: 3px solid #e74c3c; }
        .admin-nav a i { width: 20px; text-align: center; }
        .admin-main { flex: 1; margin-left: 250px; padding: 1.5rem; }
        .admin-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1.5rem;
        }
        .admin-header h1 { font-size: 1.4rem; color: #2c3e50; margin: 0; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card {
            background: #fff; border-radius: 12px; padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .stat-card .label { font-size: 0.85rem; color: #7f8c8d; margin-bottom: 0.5rem; }
        .stat-card .value { font-size: 1.8rem; font-weight: 700; color: #2c3e50; }
        .stat-card .sub { font-size: 0.75rem; color: #95a5a6; margin-top: 0.25rem; }
        .stat-card.purple .value { color: #9b59b6; }
        .stat-card.blue .value { color: #3498db; }
        .stat-card.red .value { color: #e74c3c; }
        .stat-card.green .value { color: #27ae60; }
        .admin-card {
            background: #fff; border-radius: 12px; padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 1.5rem;
        }
        .admin-card h3 { margin: 0 0 1rem; font-size: 1rem; color: #2c3e50; }
        .admin-table {
            width: 100%; border-collapse: collapse; font-size: 0.85rem;
        }
        .admin-table th {
            background: #f8f9fa; padding: 0.75rem; text-align: left;
            font-weight: 500; color: #7f8c8d; border-bottom: 1px solid #eee;
        }
        .admin-table td {
            padding: 0.75rem; border-bottom: 1px solid #f0f0f0;
        }
        .admin-table tr:hover { background: #fafafa; }
        .badge-type { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 500; }
        .badge-free { background: #e8f5e9; color: #2e7d32; }
        .badge-premium { background: #fff3e0; color: #e65100; }
        .badge-admin { background: #fce4ec; color: #c62828; }
        .badge-user { background: #e3f2fd; color: #1565c0; }
        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 8px; text-decoration: none; display: inline-block; }
        .btn-info { background: #3498db; color: #fff; }
        .back-link { color: #e74c3c; text-decoration: none; font-size: 0.85rem; }
        @media (max-width: 768px) {
            .admin-sidebar { width: 60px; }
            .admin-sidebar .logo h2, .admin-sidebar .logo small, .admin-nav span { display: none; }
            .admin-nav a { padding: 0.85rem; justify-content: center; }
            .admin-main { margin-left: 60px; padding: 1rem; }
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
                <li><a href="<?= SITE_URL ?>/admin/index.php" class="active"><i class="fas fa-chart-pie"></i><span>대시보드</span></a></li>
                <li><a href="<?= SITE_URL ?>/admin/users.php"><i class="fas fa-users"></i><span>회원 관리</span></a></li>
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
        <div class="admin-header">
            <h1><i class="fas fa-chart-pie"></i> 대시보드</h1>
            <span style="color:#7f8c8d; font-size:0.85rem;">관리자: <?= h(getCurrentUser()['nickname']) ?></span>
        </div>
        
        <div class="stat-grid">
            <div class="stat-card blue">
                <div class="label">전체 회원</div>
                <div class="value"><?= number_format($stats['total_users']) ?></div>
                <div class="sub">오늘 +<?= $stats['today_users'] ?></div>
            </div>
            <div class="stat-card purple">
                <div class="label">전체 분석</div>
                <div class="value"><?= number_format($stats['total_analysis']) ?></div>
                <div class="sub">오늘 +<?= $stats['today_analysis'] ?></div>
            </div>
            <div class="stat-card green">
                <div class="label">보유 티켓(전체)</div>
                <div class="value"><?= number_format($stats['total_tickets']) ?></div>
            </div>
            <div class="stat-card red">
                <div class="label">사용된 티켓</div>
                <div class="value"><?= number_format($stats['used_tickets']) ?></div>
            </div>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
            <div class="admin-card">
                <h3><i class="fas fa-user-plus"></i> 최근 가입</h3>
                <table class="admin-table">
                    <thead><tr><th>닉네임</th><th>이메일</th><th>가입일</th><th>역할</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentUsers as $u): ?>
                    <tr>
                        <td><?= h($u['nickname']) ?></td>
                        <td><?= h($u['email']) ?></td>
                        <td><?= formatDate($u['created_at'], 'Y-m-d') ?></td>
                        <td><span class="badge-type <?= $u['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>"><?= $u['role'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="admin-card">
                <h3><i class="fas fa-scroll"></i> 최근 분석</h3>
                <table class="admin-table">
                    <thead><tr><th>회원</th><th>유형</th><th>일시</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentHistory as $r): ?>
                    <tr>
                        <td><?= h($r['nickname']) ?></td>
                        <td>
                            <?php
                            $typeLabels = [
                                'basic_saju' => '기본사주',
                                'ohang' => '오행분석',
                                'sipsin' => '십신분석',
                                'gyeokguk' => '격국분석',
                                'daeun' => '대운분석',
                                'seun' => '세운분석',
                                'comprehensive' => '종합분석',
                            ];
                            $tLabel = $typeLabels[$r['analysis_type']] ?? $r['analysis_type'];
                            $isPremium = in_array($r['analysis_type'], ['sipsin','gyeokguk','daeun','seun','comprehensive']);
                            ?>
                            <span class="badge-type <?= $isPremium ? 'badge-premium' : 'badge-free' ?>"><?= $tLabel ?></span>
                        </td>
                        <td><?= timeAgo($r['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if (!empty($typeStats)): ?>
        <div class="admin-card">
            <h3><i class="fas fa-chart-bar"></i> 분석 유형 분포</h3>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                <?php foreach ($typeStats as $ts): 
                    $tl = $typeLabels[$ts['analysis_type']] ?? $ts['analysis_type'];
                ?>
                <div style="background:#f8f9fa;padding:0.75rem 1rem;border-radius:8px;text-align:center;min-width:100px;">
                    <div style="font-size:1.5rem;font-weight:700;color:#2c3e50;"><?= $ts['cnt'] ?></div>
                    <div style="font-size:0.8rem;color:#7f8c8d;"><?= $tl ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
