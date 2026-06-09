<?php
/**
 * 마이페이지
 */
require_once __DIR__ . '/../includes/auth_check.php';

$user = getCurrentUser();
$pdo = getDBConnection();

// 통계
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM saju_fortune_history WHERE user_id = ?");
$stmt->execute([$user['id']]);
$totalAnalysis = $stmt->fetch()['cnt'];

// 사주 프로필 목록
$stmt = $pdo->prepare("SELECT * FROM saju_profiles WHERE user_id = ? ORDER BY is_default DESC, created_at ASC");
$stmt->execute([$user['id']]);
$sajuProfiles = $stmt->fetchAll();

// 티켓 사용 내역 수
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM saju_ticket_logs WHERE user_id = ?");
$stmt->execute([$user['id']]);
$ticketLogs = $stmt->fetch()['cnt'];

// 프로필 수정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $nickname = trim($_POST['nickname'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (!empty($nickname)) {
            $stmt = $pdo->prepare("UPDATE saju_users SET nickname = ?, phone = ? WHERE id = ?");
            $stmt->execute([$nickname, $phone, $user['id']]);
            setFlashMessage('success', '프로필이 수정되었습니다.');
            redirect('/pages/mypage.php');
        }
    }
    
    if ($_POST['action'] === 'change_password') {
        $currentPwd = $_POST['current_password'] ?? '';
        $newPwd = $_POST['new_password'] ?? '';
        $confirmPwd = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($currentPwd, $user['password'])) {
            setFlashMessage('error', '현재 비밀번호가 올바르지 않습니다.');
        } elseif ($newPwd !== $confirmPwd) {
            setFlashMessage('error', '새 비밀번호가 일치하지 않습니다.');
        } else {
            $pwdCheck = validatePassword($newPwd);
            if (!$pwdCheck['valid']) {
                setFlashMessage('error', $pwdCheck['message']);
            } else {
                $hashedPwd = password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare("UPDATE saju_users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPwd, $user['id']]);
                setFlashMessage('success', '비밀번호가 변경되었습니다.');
            }
        }
        redirect('/pages/mypage.php');
    }
}

$pageTitle = '마이페이지 - ' . SITE_NAME;
include INCLUDES_PATH . '/header.php';
?>

<div class="animate-fade">
    <!-- 프로필 카드 -->
    <div class="profile-card">
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div>
                <div class="profile-name"><?= h($user['nickname']) ?></div>
                <div class="profile-email"><?= h($user['email']) ?></div>
            </div>
        </div>
        
        <div class="profile-stats">
            <div class="profile-stat">
                <div class="profile-stat-value"><?= (int)$user['tickets'] ?></div>
                <div class="profile-stat-label">보유 티켓</div>
            </div>
            <div class="profile-stat">
                <div class="profile-stat-value"><?= $totalAnalysis ?></div>
                <div class="profile-stat-label">분석 횟수</div>
            </div>
            <div class="profile-stat">
                <div class="profile-stat-value"><?= formatDate($user['created_at'], 'Y.m') ?></div>
                <div class="profile-stat-label">가입일</div>
            </div>
        </div>
    </div>
    
    <!-- 메뉴 목록 -->
    <div class="menu-list">
        <a href="<?= SITE_URL ?>/pages/history.php" class="menu-item">
            <i class="fas fa-clock-rotate-left"></i>
            <span>분석 기록</span>
            <span style="font-size: 0.8rem; color: var(--text-muted);"><?= $totalAnalysis ?>건</span>
            <i class="fas fa-chevron-right menu-arrow"></i>
        </a>
        <a href="<?= SITE_URL ?>/pages/premium.php" class="menu-item">
            <i class="fas fa-crown"></i>
            <span>프리미엄 분석</span>
            <i class="fas fa-chevron-right menu-arrow"></i>
        </a>
        <a href="<?= SITE_URL ?>/pages/ticket_history.php" class="menu-item">
            <i class="fas fa-ticket-alt"></i>
            <span>티켓 내역</span>
            <span style="font-size: 0.8rem; color: var(--text-muted);"><?= (int)$user['tickets'] ?>장</span>
            <i class="fas fa-chevron-right menu-arrow"></i>
        </a>
    </div>
    
    <!-- 사주 프로필 관리 -->
    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <span class="card-title">☯ 사주 프로필 관리</span>
            <span style="font-size:0.75rem;color:var(--text-muted);"><?= count($sajuProfiles) ?>/10</span>
        </div>
        
        <?php if (empty($sajuProfiles)): ?>
        <div style="text-align:center;padding:20px 0;color:var(--text-muted);">
            <i class="fas fa-user-plus" style="font-size:2rem;margin-bottom:8px;display:block;opacity:0.4;"></i>
            <p style="font-size:0.85rem;">등록된 사주 프로필이 없습니다</p>
            <p style="font-size:0.78rem;margin-top:4px;">본인이나 가족의 생년월일을 등록해 보세요</p>
        </div>
        <?php else: ?>
        <div id="profileList" style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
            <?php foreach ($sajuProfiles as $profile): ?>
            <div class="saju-profile-item" data-id="<?= $profile['id'] ?>" style="padding:12px 14px;background:<?= $profile['is_default'] ? 'linear-gradient(135deg,#FFF8E1,#FFF3E0)' : '#f9f9f9' ?>;border-radius:12px;border:1px solid <?= $profile['is_default'] ? '#FFE082' : '#eee' ?>;position:relative;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <strong style="font-size:0.9rem;"><?= h($profile['profile_name']) ?></strong>
                        <?php if ($profile['is_default']): ?>
                        <span style="font-size:0.65rem;background:#F39C12;color:#fff;padding:2px 6px;border-radius:8px;font-weight:600;">기본</span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <?php if (!$profile['is_default']): ?>
                        <button type="button" class="btn-profile-default" data-id="<?= $profile['id'] ?>" title="기본으로 설정" style="background:none;border:none;cursor:pointer;color:#F39C12;font-size:0.75rem;padding:4px 8px;border-radius:6px;border:1px solid #F39C12;">
                            <i class="fas fa-star"></i>
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn-profile-edit" data-id="<?= $profile['id'] ?>" title="수정" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:0.8rem;padding:4px 8px;">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button type="button" class="btn-profile-delete" data-id="<?= $profile['id'] ?>" data-name="<?= h($profile['profile_name']) ?>" title="삭제" style="background:none;border:none;cursor:pointer;color:#E57373;font-size:0.8rem;padding:4px 8px;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div style="font-size:0.78rem;color:var(--text-secondary);">
                    <?= $profile['birth_year'] ?>년 <?= $profile['birth_month'] ?>월 <?= $profile['birth_day'] ?>일
                    <?php if ($profile['birth_hour'] !== null): ?>
                    · <?= str_pad($profile['birth_hour'], 2, '0', STR_PAD_LEFT) ?>시
                    <?php endif; ?>
                    · <?= $profile['gender'] === 'male' ? '남' : '여' ?>
                    · <?= $profile['calendar_type'] === 'solar' ? '양력' : '음력' ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- 프로필 추가/수정 폼 -->
        <div id="profileFormWrap" style="<?= count($sajuProfiles) > 0 ? 'display:none;' : '' ?>border-top:<?= count($sajuProfiles) > 0 ? '1px solid #eee' : 'none' ?>;padding-top:<?= count($sajuProfiles) > 0 ? '16px' : '0' ?>;">
            <div id="profileFormTitle" style="font-size:0.88rem;font-weight:700;margin-bottom:12px;">새 프로필 추가</div>
            <form id="profileForm">
                <input type="hidden" name="action" value="add" id="profileAction">
                <input type="hidden" name="profile_id" value="" id="profileId">
                
                <div class="form-group">
                    <label class="form-label">프로필 이름 *</label>
                    <input type="text" name="profile_name" id="pf_name" class="form-input" placeholder="예: 본인, 아버지, 어머니" maxlength="50" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">성별 *</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="gender" value="male" checked> <i class="fas fa-mars" style="color:#42A5F5;"></i> 남성
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="gender" value="female"> <i class="fas fa-venus" style="color:#EC407A;"></i> 여성
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">역법</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="calendar_type" value="solar" checked> ☀️ 양력
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="calendar_type" value="lunar"> 🌙 음력
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">출생 연도 *</label>
                    <select name="birth_year" id="pf_year" class="form-select" required>
                        <option value="">연도 선택</option>
                        <?php for ($y = (int)date('Y'); $y >= 1940; $y--): ?>
                        <option value="<?= $y ?>"><?= $y ?>년</option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">출생 월 *</label>
                        <select name="birth_month" id="pf_month" class="form-select" required>
                            <option value="">월</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>"><?= $m ?>월</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">출생 일 *</label>
                        <select name="birth_day" id="pf_day" class="form-select" required>
                            <option value="">일</option>
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                            <option value="<?= $d ?>"><?= $d ?>일</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">출생 시간</label>
                    <select name="birth_hour" id="pf_hour" class="form-select">
                        <option value="-1">모름</option>
                        <option value="0">자시 (子時) 23:30~01:30</option>
                        <option value="1">축시 (丑時) 01:30~03:30</option>
                        <option value="3">인시 (寅時) 03:30~05:30</option>
                        <option value="5">묘시 (卯時) 05:30~07:30</option>
                        <option value="7">진시 (辰時) 07:30~09:30</option>
                        <option value="9">사시 (巳時) 09:30~11:30</option>
                        <option value="11">오시 (午時) 11:30~13:30</option>
                        <option value="13">미시 (未時) 13:30~15:30</option>
                        <option value="15">신시 (申時) 15:30~17:30</option>
                        <option value="17">유시 (酉時) 17:30~19:30</option>
                        <option value="19">술시 (戌時) 19:30~21:30</option>
                        <option value="21">해시 (亥時) 21:30~23:30</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="radio-label" style="cursor:pointer;">
                        <input type="checkbox" name="is_default" id="pf_default" value="1"> 기본 프로필로 설정
                    </label>
                </div>
                
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;" id="profileSubmitBtn">
                        <i class="fas fa-plus"></i> 추가
                    </button>
                    <button type="button" class="btn btn-outline" id="profileCancelBtn" style="display:none;" onclick="cancelProfileEdit()">
                        취소
                    </button>
                </div>
            </form>
        </div>
        
        <?php if (count($sajuProfiles) > 0 && count($sajuProfiles) < 10): ?>
        <button type="button" class="btn btn-outline btn-block" id="showProfileFormBtn" onclick="showProfileForm()" style="margin-top:8px;">
            <i class="fas fa-plus"></i> 새 프로필 추가
        </button>
        <?php endif; ?>
    </div>
    
    <!-- 프로필 수정 -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">프로필 수정</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="form-group">
                <label class="form-label">닉네임</label>
                <input type="text" name="nickname" class="form-input" value="<?= h($user['nickname']) ?>" maxlength="20" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">전화번호</label>
                <input type="tel" name="phone" class="form-input" value="<?= h($user['phone'] ?? '') ?>" placeholder="010-1234-5678">
            </div>
            
            <div class="form-group">
                <label class="form-label">이메일</label>
                <input type="email" class="form-input" value="<?= h($user['email']) ?>" disabled style="opacity: 0.5;">
                <p class="form-hint">이메일은 변경할 수 없습니다</p>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">프로필 수정</button>
        </form>
    </div>
    
    <!-- 비밀번호 변경 -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">비밀번호 변경</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="change_password">
            
            <div class="form-group">
                <label class="form-label">현재 비밀번호</label>
                <input type="password" name="current_password" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">새 비밀번호</label>
                <input type="password" name="new_password" class="form-input" placeholder="8자 이상 (영문+숫자)" required minlength="8">
            </div>
            
            <div class="form-group">
                <label class="form-label">새 비밀번호 확인</label>
                <input type="password" name="confirm_password" class="form-input" required>
            </div>
            
            <button type="submit" class="btn btn-outline btn-block">비밀번호 변경</button>
        </form>
    </div>
    
    <!-- 기타 메뉴 -->
    <div class="menu-list">
        <?php if (isAdmin()): ?>
        <a href="<?= SITE_URL ?>/admin/index.php" class="menu-item">
            <i class="fas fa-shield-halved"></i>
            <span>관리자 페이지</span>
            <i class="fas fa-chevron-right menu-arrow"></i>
        </a>
        <?php endif; ?>
        <a href="<?= SITE_URL ?>/auth/logout.php" class="menu-item danger" onclick="return confirm('로그아웃 하시겠습니까?')">
            <i class="fas fa-sign-out-alt"></i>
            <span>로그아웃</span>
        </a>
    </div>
</div>

<script>
// 사주 프로필 관리 JS
const profileApiUrl = '<?= SITE_URL ?>/pages/api_profiles.php';

function showProfileForm() {
    const wrap = document.getElementById('profileFormWrap');
    wrap.style.display = 'block';
    wrap.style.borderTop = '1px solid #eee';
    wrap.style.paddingTop = '16px';
    const btn = document.getElementById('showProfileFormBtn');
    if (btn) btn.style.display = 'none';
}

function cancelProfileEdit() {
    const wrap = document.getElementById('profileFormWrap');
    wrap.style.display = 'none';
    const btn = document.getElementById('showProfileFormBtn');
    if (btn) btn.style.display = '';
    resetProfileForm();
}

function resetProfileForm() {
    document.getElementById('profileAction').value = 'add';
    document.getElementById('profileId').value = '';
    document.getElementById('profileFormTitle').textContent = '새 프로필 추가';
    document.getElementById('profileSubmitBtn').innerHTML = '<i class="fas fa-plus"></i> 추가';
    document.getElementById('profileCancelBtn').style.display = 'none';
    document.getElementById('profileForm').reset();
}

// 프로필 추가/수정 폼 제출
document.getElementById('profileForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    // checkbox 처리
    if (!document.getElementById('pf_default').checked) {
        formData.set('is_default', '0');
    }
    
    try {
        const resp = await fetch(profileApiUrl, { method: 'POST', body: formData });
        const data = await resp.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || '오류가 발생했습니다.');
        }
    } catch(err) {
        alert('요청 처리 중 오류가 발생했습니다.');
    }
});

// 수정 버튼
document.querySelectorAll('.btn-profile-edit').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.dataset.id;
        try {
            const resp = await fetch(profileApiUrl + '?action=get&profile_id=' + id);
            const data = await resp.json();
            if (data.success) {
                const p = data.profile;
                document.getElementById('profileAction').value = 'update';
                document.getElementById('profileId').value = p.id;
                document.getElementById('pf_name').value = p.profile_name;
                document.getElementById('pf_year').value = p.birth_year;
                document.getElementById('pf_month').value = p.birth_month;
                document.getElementById('pf_day').value = p.birth_day;
                document.getElementById('pf_hour').value = p.birth_hour !== null ? p.birth_hour : '-1';
                document.getElementById('pf_default').checked = p.is_default == 1;
                
                const form = document.getElementById('profileForm');
                form.querySelector('input[name="gender"][value="' + p.gender + '"]').checked = true;
                form.querySelector('input[name="calendar_type"][value="' + p.calendar_type + '"]').checked = true;
                
                document.getElementById('profileFormTitle').textContent = '"' + p.profile_name + '" 수정';
                document.getElementById('profileSubmitBtn').innerHTML = '<i class="fas fa-check"></i> 수정 완료';
                document.getElementById('profileCancelBtn').style.display = '';
                showProfileForm();
                document.getElementById('profileFormWrap').scrollIntoView({ behavior: 'smooth' });
            }
        } catch(err) {
            alert('프로필 정보를 불러오지 못했습니다.');
        }
    });
});

// 삭제 버튼
document.querySelectorAll('.btn-profile-delete').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.dataset.id;
        const name = this.dataset.name;
        if (!confirm('"' + name + '" 프로필을 삭제하시겠습니까?')) return;
        
        const formData = new FormData();
        formData.set('action', 'delete');
        formData.set('profile_id', id);
        
        try {
            const resp = await fetch(profileApiUrl, { method: 'POST', body: formData });
            const data = await resp.json();
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || '삭제에 실패했습니다.');
            }
        } catch(err) {
            alert('요청 처리 중 오류가 발생했습니다.');
        }
    });
});

// 기본 프로필 설정
document.querySelectorAll('.btn-profile-default').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.dataset.id;
        const formData = new FormData();
        formData.set('action', 'set_default');
        formData.set('profile_id', id);
        
        try {
            const resp = await fetch(profileApiUrl, { method: 'POST', body: formData });
            const data = await resp.json();
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || '설정에 실패했습니다.');
            }
        } catch(err) {
            alert('요청 처리 중 오류가 발생했습니다.');
        }
    });
});
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
