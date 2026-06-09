<?php
/**
 * 프리미엄 분석 기능 목록 페이지
 */
require_once __DIR__ . '/../includes/auth_check.php';

$user = getCurrentUser();

$pageTitle = '프리미엄 분석 - ' . SITE_NAME;
include INCLUDES_PATH . '/header.php';
?>

<div class="animate-fade">
    <!-- 보유 티켓 정보 -->
    <div class="card" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-align: center;">
        <div style="font-size: 0.85rem; opacity: 0.8; margin-bottom: 8px;">보유 분석 티켓</div>
        <div style="font-size: 2.5rem; font-weight: 900;"><?= (int)$user['tickets'] ?></div>
        <div style="font-size: 0.8rem; margin-top: 8px; opacity: 0.7;">
            분석마다 필요한 티켓 수가 다릅니다
        </div>
    </div>
    
    <!-- 무료 기능 -->
    <div class="card-header" style="padding: 0; margin-top: 20px; margin-bottom: 12px;">
        <span style="font-size: 1rem; font-weight: 700;">🆓 무료 분석</span>
    </div>
    
    <div class="features-grid stagger-children">
        <a href="<?= SITE_URL ?>/pages/analyze.php?type=basic_saju" class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-yin-yang"></i>
            </div>
            <div class="feature-name">사주팔자 계산</div>
            <div class="feature-cost" style="color: #4CAF50;"><i class="fas fa-check"></i> 무료</div>
        </a>
        <a href="<?= SITE_URL ?>/pages/analyze.php?type=ohang" class="feature-card">
            <div class="feature-icon" style="background: #E8F5E9; color: #4CAF50;">
                <i class="fas fa-circle-nodes"></i>
            </div>
            <div class="feature-name">오행 분석</div>
            <div class="feature-cost" style="color: #4CAF50;"><i class="fas fa-check"></i> 무료</div>
        </a>
    </div>
    
    <!-- 프리미엄 기능 -->
    <div class="card-header" style="padding: 0; margin-top: 24px; margin-bottom: 12px;">
        <span style="font-size: 1rem; font-weight: 700;">👑 프리미엄 분석</span>
    </div>
    
    <div class="features-grid stagger-children">
        <?php foreach (PREMIUM_FEATURES as $key => $feature): ?>
        <a href="<?= SITE_URL ?>/pages/analyze.php?type=<?= h($key) ?>" class="feature-card <?= $user['tickets'] < $feature['tickets'] ? 'locked' : '' ?>">
            <div class="feature-icon">
                <i class="fas <?= $feature['icon'] ?>"></i>
            </div>
            <div class="feature-name"><?= $feature['name'] ?></div>
            <div class="feature-cost">
                <i class="fas fa-ticket-alt"></i> 티켓 <?= $feature['tickets'] ?>장
                <?php if ($user['tickets'] < $feature['tickets']): ?>
                    <br><span style="color: #F44336; font-size: 0.7rem;">티켓 부족</span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    
    <!-- 안내 -->
    <div class="card" style="margin-top: 16px;">
        <div class="card-title" style="margin-bottom: 12px;">
            <i class="fas fa-info-circle" style="color: var(--primary-dark);"></i> 안내
        </div>
        <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.8;">
            <p>• 사주팔자 계산과 오행 분석은 <strong>무료</strong>로 이용할 수 있습니다.</p>
            <p>• 프리미엄 분석은 <strong>티켓</strong>을 사용하여 이용합니다.</p>
            <p>• 회원가입 시 <?= DEFAULT_TICKETS ?>장의 무료 티켓이 지급됩니다.</p>
            <p>• 분석 결과는 모두 저장되어 언제든 다시 확인할 수 있습니다.</p>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
