<?php
/**
 * 공통 푸터 (사용자 페이지)
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
    </main>

    <!-- 하단 네비게이션 -->
    <?php if (isLoggedIn()): ?>
    <nav class="bottom-nav">
        <a href="<?= SITE_URL ?>/pages/home.php" class="nav-item <?= $currentPage === 'home' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>홈</span>
        </a>
        <a href="<?= SITE_URL ?>/pages/analyze.php" class="nav-item <?= $currentPage === 'analyze' ? 'active' : '' ?>">
            <i class="fas fa-yin-yang"></i>
            <span>사주분석</span>
        </a>
        <a href="<?= SITE_URL ?>/pages/yearly_fortune.php?mode=yearly" class="nav-item <?= in_array($currentPage, ['yearly_fortune', 'date_fortune', 'focus_fortune', 'daeun_fortune'], true) ? 'active' : '' ?>">
            <i class="fas fa-star"></i>
            <span>운세</span>
        </a>
        <a href="<?= SITE_URL ?>/pages/traditional_saju.php" class="nav-item <?= $currentPage === 'traditional_saju' ? 'active' : '' ?>">
            <i class="fas fa-pen-nib"></i>
            <span>정통사주</span>
        </a>
        <a href="<?= SITE_URL ?>/pages/mypage.php" class="nav-item <?= $currentPage === 'mypage' ? 'active' : '' ?>">
            <i class="fas fa-user"></i>
            <span>마이</span>
        </a>
    </nav>
    <?php endif; ?>

    <!-- Footer Info -->
    <footer class="app-footer">
        <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
    </footer>

    <!-- Custom JS -->
    <script src="<?= SITE_URL ?>/assets/js/app.js"></script>
    
    <script>
        // 플래시 메시지 자동 닫기
        setTimeout(() => {
            const flash = document.getElementById('flashMessage');
            if (flash) {
                flash.style.opacity = '0';
                flash.style.transform = 'translateY(-100%)';
                setTimeout(() => flash.remove(), 300);
            }
        }, 4000);
    </script>
</body>
</html>
