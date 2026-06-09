<?php
/**
 * 관리자 - 분석 기록 조회
 */
require_once __DIR__ . '/../includes/admin_check.php';

$pdo = getDBConnection();

$search = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (u.nickname LIKE ? OR u.email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($type) {
    $where .= " AND h.analysis_type = ?";
    $params[] = $type;
}

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM saju_fortune_history h JOIN saju_users u ON h.user_id = u.id WHERE $where");
$stmt->execute($params);
$total = $stmt->fetch()['cnt'];
$totalPages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("
    SELECT h.*, u.nickname, u.email 
    FROM saju_fortune_history h 
    JOIN saju_users u ON h.user_id = u.id 
    WHERE $where 
    ORDER BY h.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$records = $stmt->fetchAll();

$typeLabels = [
    'basic_saju' => '기본사주',
    'ohang' => '오행분석',
    'sipsin' => '십신분석',
    'gyeokguk' => '격국분석',
    'daeun' => '대운분석',
    'seun' => '세운분석',
    'comprehensive' => '종합분석',
];

$pageTitle = '분석 기록 - ' . SITE_NAME;
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
        .admin-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .admin-table th { background: #f8f9fa; padding: 0.75rem; text-align: left; font-weight: 500; color: #7f8c8d; border-bottom: 1px solid #eee; }
        .admin-table td { padding: 0.75rem; border-bottom: 1px solid #f0f0f0; }
        .admin-table tr:hover { background: #fafafa; }
        .badge-type { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 500; }
        .badge-free { background: #e8f5e9; color: #2e7d32; }
        .badge-premium { background: #fff3e0; color: #e65100; }
        .search-bar { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .search-bar input, .search-bar select { padding: 0.6rem 1rem; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9rem; }
        .search-bar input { flex: 1; min-width: 200px; }
        .search-bar button { padding: 0.6rem 1rem; background: #3498db; color: #fff; border: none; border-radius: 8px; cursor: pointer; }
        .pagination { display: flex; gap: 0.25rem; justify-content: center; margin-top: 1rem; }
        .pagination a { padding: 0.4rem 0.75rem; border-radius: 6px; text-decoration: none; color: #2c3e50; background: #fff; border: 1px solid #ddd; font-size: 0.85rem; }
        .pagination a.active { background: #3498db; color: #fff; border-color: #3498db; }
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
                <li><a href="<?= SITE_URL ?>/admin/index.php"><i class="fas fa-chart-pie"></i><span>대시보드</span></a></li>
                <li><a href="<?= SITE_URL ?>/admin/users.php"><i class="fas fa-users"></i><span>회원 관리</span></a></li>
                <li><a href="<?= SITE_URL ?>/admin/history.php" class="active"><i class="fas fa-scroll"></i><span>분석 기록</span></a></li>
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
            <h1><i class="fas fa-scroll"></i> 분석 기록</h1>
            <span style="color:#7f8c8d;font-size:0.85rem;">총 <?= number_format($total) ?>건</span>
        </div>
        
        <div class="admin-card">
            <form class="search-bar" method="GET">
                <input type="text" name="q" value="<?= h($search) ?>" placeholder="회원 이름/이메일 검색...">
                <select name="type">
                    <option value="">전체 유형</option>
                    <?php foreach ($typeLabels as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><i class="fas fa-search"></i> 검색</button>
            </form>
            
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>회원</th>
                        <th>유형</th>
                        <th>성별</th>
                        <th>생년월일</th>
                        <th>사주</th>
                        <th>일시</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td>#<?= $r['id'] ?></td>
                    <td>
                        <a href="<?= SITE_URL ?>/admin/users.php?id=<?= $r['user_id'] ?>" style="color:#3498db;text-decoration:none;">
                            <?= h($r['nickname']) ?>
                        </a>
                    </td>
                    <td>
                        <?php $isPremium = in_array($r['analysis_type'], ['sipsin','gyeokguk','daeun','seun','comprehensive']); ?>
                        <span class="badge-type <?= $isPremium ? 'badge-premium' : 'badge-free' ?>">
                            <?= $typeLabels[$r['analysis_type']] ?? $r['analysis_type'] ?>
                        </span>
                    </td>
                    <td><?= $r['gender'] === 'male' ? '남' : '여' ?></td>
                    <td><?= $r['birth_year'] ?>.<?= $r['birth_month'] ?>.<?= $r['birth_day'] ?></td>
                    <td style="font-family:serif;">
                        <?= h($r['year_pillar'] . ' ' . $r['month_pillar'] . ' ' . $r['day_pillar'] . ' ' . $r['hour_pillar']) ?>
                    </td>
                    <td><?= timeAgo($r['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = max(1,$page-3); $i <= min($totalPages,$page+3); $i++): ?>
                <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>" class="<?= $i===$page?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
