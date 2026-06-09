<?php
/**
 * 홈 페이지 - 오늘의 운세 & 대시보드
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once SAJU_ENGINE_PATH . '/SajuEngine.php';
require_once SAJU_ENGINE_PATH . '/OhangAnalysis.php';
require_once SAJU_ENGINE_PATH . '/FortuneInterpreter.php';

$user = getCurrentUser();
if (!$user) {
    redirect('/auth/login.php');
}
$pdo = getDBConnection();

function clampScore($score) {
    return max(10, min(95, (int)round($score)));
}

function extractCurrentYearFortune($fortuneResult, $currentYear) {
    $seun = $fortuneResult['seun'] ?? null;
    if (!is_array($seun)) return null;

    if (isset($seun['year']) && (int)$seun['year'] === $currentYear) {
        return $seun;
    }

    foreach ($seun as $item) {
        if (is_array($item) && (int)($item['year'] ?? 0) === $currentYear) {
            return $item;
        }
    }

    return null;
}

function buildDailyFortune($yearFortune, $userId, $date = null) {
    $date = $date ?: new DateTimeImmutable('now');
    $baseScore = (int)($yearFortune['total_score'] ?? $yearFortune['score'] ?? 50);
    $month = (int)$date->format('n');
    $day = (int)$date->format('j');
    $weekday = (int)$date->format('N');
    $monthScore = null;

    foreach (($yearFortune['monthly'] ?? []) as $monthly) {
        if ((int)($monthly['month'] ?? 0) === $month) {
            $monthScore = (int)($monthly['score'] ?? $baseScore);
            break;
        }
    }

    if ($monthScore === null) {
        $monthScore = $baseScore;
        $highlight = $yearFortune['monthly_highlight'] ?? [];
        if (!empty($highlight['best_month']) && (int)$highlight['best_month'] === $month) {
            $monthScore += 6;
        }
        if (!empty($highlight['worst_month']) && (int)$highlight['worst_month'] === $month) {
            $monthScore -= 6;
        }
    }

    $seed = sprintf('%s-%s-%s', $userId, $date->format('Y-m-d'), $yearFortune['year'] ?? '');
    $dailyOffset = (crc32($seed) % 9) - 4;
    $weekdayOffset = [1 => 1, 2 => 0, 3 => 1, 4 => 0, 5 => 2, 6 => 3, 7 => 1][$weekday] ?? 0;
    $dayFlow = ($day % 5) - 2;

    $dailyScore = clampScore(($monthScore * 0.65) + ($baseScore * 0.35) + $dailyOffset + $weekdayOffset + $dayFlow);

    $dailyLabel = '잔잔한 흐름의 하루입니다.';
    if ($dailyScore >= 85) {
        $dailyLabel = '기세 좋게 밀어붙이기 좋은 하루입니다.';
    } elseif ($dailyScore >= 70) {
        $dailyLabel = '기회가 비교적 잘 붙는 하루입니다.';
    } elseif ($dailyScore >= 55) {
        $dailyLabel = '무리 없이 안정적으로 가기 좋은 하루입니다.';
    } elseif ($dailyScore >= 40) {
        $dailyLabel = '욕심을 줄이고 페이스를 지키는 것이 좋은 하루입니다.';
    } else {
        $dailyLabel = '컨디션 관리와 휴식을 먼저 챙기는 편이 좋은 하루입니다.';
    }

    return [
        'score' => $dailyScore,
        'description' => $dailyLabel,
        'year_fortune' => $yearFortune,
    ];
}

function dailyCategoryScore($baseScore, $userId, $label, $date = null) {
    $date = $date ?: new DateTimeImmutable('now');
    $seed = sprintf('%s-%s-%s', $userId, $date->format('Y-m-d'), $label);
    $offset = (crc32($seed) % 19) - 9;
    return clampScore($baseScore + $offset);
}

// 최근 사주 분석 기록 조회
$stmt = $pdo->prepare("SELECT * FROM saju_fortune_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user['id']]);
$lastAnalysis = $stmt->fetch();

$serviceMenu = [
    ['label' => '신년운세', 'icon' => 'fa-star', 'url' => SITE_URL . '/pages/yearly_fortune.php?mode=yearly', 'badge' => 'N'],
    ['label' => '토정비결', 'icon' => 'fa-book-open', 'url' => SITE_URL . '/pages/yearly_fortune.php?mode=tojung', 'badge' => 'N'],
    ['label' => '정통사주', 'icon' => 'fa-pen-nib', 'url' => SITE_URL . '/pages/traditional_saju.php', 'badge' => 'N'],
    ['label' => '재물운', 'icon' => 'fa-sack-dollar', 'url' => SITE_URL . '/pages/focus_fortune.php?type=wealth', 'badge' => ''],
    ['label' => '건강운', 'icon' => 'fa-heart-pulse', 'url' => SITE_URL . '/pages/focus_fortune.php?type=health', 'badge' => ''],
    ['label' => '직업운', 'icon' => 'fa-briefcase', 'url' => SITE_URL . '/pages/focus_fortune.php?type=career', 'badge' => ''],
];

// 오늘의 운세 (마지막 분석 기반)
$todayFortune = null;
if ($lastAnalysis) {
    $currentYear = (int)date('Y');
    $fortuneResult = json_decode($lastAnalysis['fortune_result'] ?? '', true) ?: [];
    $storedSeun = json_decode($lastAnalysis['seun_analysis'] ?? '', true) ?: [];
    $yearFortune = extractCurrentYearFortune($storedSeun, $currentYear);

    if (!$yearFortune) {
        $yearFortune = extractCurrentYearFortune($fortuneResult, $currentYear);
    }

    if (!$yearFortune) {
        $engine = new SajuEngine(
            $lastAnalysis['birth_year'],
            $lastAnalysis['birth_month'],
            $lastAnalysis['birth_day'],
            $lastAnalysis['birth_hour'],
            $lastAnalysis['gender'],
            $lastAnalysis['calendar_type']
        );

        $interpreter = new FortuneInterpreter($engine);
        $seunRaw = $interpreter->analyzeSeun($currentYear);

        if (is_array($seunRaw)) {
            if (isset($seunRaw['year']) && (int)$seunRaw['year'] === $currentYear) {
                $yearFortune = $seunRaw;
            } else {
                foreach ($seunRaw as $su) {
                    if (is_array($su) && (int)($su['year'] ?? 0) === $currentYear) {
                        $yearFortune = $su;
                        break;
                    }
                }
            }
        }
    }

    if ($yearFortune) {
        $todayFortune = buildDailyFortune($yearFortune, (int)$user['id']);
    }
}

// 전체 분석 횟수
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM saju_fortune_history WHERE user_id = ?");
$stmt->execute([$user['id']]);
$totalAnalysis = $stmt->fetch()['cnt'];

$pageTitle = '홈 - ' . SITE_NAME;
include INCLUDES_PATH . '/header.php';
?>

<div class="animate-fade">
    <div class="card service-menu-hero">
        <div class="service-menu-kicker">소름 돋는 미래 예측</div>
        <div class="service-menu-title">가장 정확한 사주 풀이</div>
        <div class="service-menu-grid">
            <?php foreach ($serviceMenu as $menu): ?>
            <a href="<?= h($menu['url']) ?>" class="service-menu-card">
                <?php if (!empty($menu['badge'])): ?>
                <span class="service-menu-badge"><?= h($menu['badge']) ?></span>
                <?php endif; ?>
                <span class="service-menu-icon"><i class="fas <?= h($menu['icon']) ?>"></i></span>
                <span class="service-menu-name"><?= h($menu['label']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 환영 섹션 -->
    <div class="card" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: var(--secondary);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-size: 0.85rem; opacity: 0.8;">오늘의 운세</div>
                <div style="font-size: 1.2rem; font-weight: 800; margin-top: 4px;"><?= h($user['nickname']) ?>님의 하루 요약</div>
            </div>
            <div style="font-size: 2rem;">☯</div>
        </div>
    </div>
    
    <?php if ($todayFortune): ?>
    <!-- 오늘의 운세 점수 -->
    <div class="card">
        <div class="score-container">
            <div class="score-circle">
                <span class="score-number" id="todayScore"><?= $todayFortune['score'] ?? 50 ?></span>
                <span class="score-label">오늘의 운세</span>
            </div>
            <p class="score-description">
                <?= h($todayFortune['description'] ?? '평온하고 안정적인 하루입니다.') ?>
            </p>
        </div>
        
        <a href="<?= SITE_URL ?>/pages/date_fortune.php?mode=today" class="btn btn-outline btn-block" style="margin-top: 8px;">
            오늘 하루 자세히 보기 <i class="fas fa-chevron-right" style="font-size: 0.75rem;"></i>
        </a>
    </div>
    
    <!-- 레이더 차트 -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">카테고리별 운세</span>
            <span class="text-sm text-muted"><?= date('Y') ?>년</span>
        </div>
        <div class="radar-chart-container">
            <canvas id="fortuneRadar"></canvas>
        </div>
    </div>
    
    <?php else: ?>
    <!-- 처음 사용자 -->
    <div class="card" style="text-align: center; padding: 40px 20px;">
        <div style="font-size: 3rem; margin-bottom: 16px;">🔮</div>
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 8px;">사주 분석을 시작해 보세요</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 20px;">
            생년월일과 시간을 입력하면<br>정통 명리학 기반의 사주팔자 분석을 받으실 수 있습니다.
        </p>
        <a href="<?= SITE_URL ?>/pages/analyze.php" class="btn btn-primary btn-lg">
            <i class="fas fa-yin-yang"></i> 무료 사주 분석하기
        </a>
    </div>
    <?php endif; ?>
    
    <!-- 간단 통계 -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
        <div class="card" style="text-align: center; margin-bottom: 0;">
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--primary-dark);"><?= $totalAnalysis ?></div>
            <div style="font-size: 0.78rem; color: var(--text-muted);">총 분석 횟수</div>
        </div>
        <div class="card" style="text-align: center; margin-bottom: 0;">
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--primary-dark);"><?= (int)$user['tickets'] ?></div>
            <div style="font-size: 0.78rem; color: var(--text-muted);">보유 티켓</div>
        </div>
    </div>
</div>

<?php if ($todayFortune): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 점수 애니메이션
    animateNumber(document.getElementById('todayScore'), <?= $todayFortune['score'] ?? 50 ?>);
    
    <?php if (!empty($todayFortune['categories'])): ?>
    // 레이더 차트 생성 (old format with categories)
    createRadarChart('fortuneRadar', {
        labels: [
            <?php foreach ($todayFortune['categories'] as $cat): ?>
            '<?= $cat['name'] ?>',
            <?php endforeach; ?>
        ],
        values: [
            <?php foreach ($todayFortune['categories'] as $cat): ?>
            <?= $cat['score'] ?>,
            <?php endforeach; ?>
        ]
    });
    <?php else: ?>
    // 레이더 차트 생성 (new format: derive from score)
    createRadarChart('fortuneRadar', {
        labels: ['총운','재물운','연애운','직장운','건강운','학업운'],
        values: [
            <?= $todayFortune['score'] ?? 50 ?>,
            <?= dailyCategoryScore((int)($todayFortune['score'] ?? 50), (int)$user['id'], 'wealth') ?>,
            <?= dailyCategoryScore((int)($todayFortune['score'] ?? 50), (int)$user['id'], 'love') ?>,
            <?= dailyCategoryScore((int)($todayFortune['score'] ?? 50), (int)$user['id'], 'career') ?>,
            <?= dailyCategoryScore((int)($todayFortune['score'] ?? 50), (int)$user['id'], 'health') ?>,
            <?= dailyCategoryScore((int)($todayFortune['score'] ?? 50), (int)$user['id'], 'study') ?>
        ]
    });
    <?php endif; ?>
});
</script>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
