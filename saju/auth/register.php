<?php
/**
 * 회원가입 페이지
 */
require_once __DIR__ . '/../config/config.php';

// 이미 로그인했으면 홈으로
if (isLoggedIn()) {
    redirect('/pages/home.php');
}

$errors = [];
$formData = [];

// POST 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
        'nickname' => trim($_POST['nickname'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'terms' => isset($_POST['terms']),
        'marketing' => isset($_POST['marketing']),
        // 사주 정보
        'birth_year' => (int)($_POST['birth_year'] ?? 0),
        'birth_month' => (int)($_POST['birth_month'] ?? 0),
        'birth_day' => (int)($_POST['birth_day'] ?? 0),
        'birth_hour' => $_POST['birth_hour'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'calendar_type' => $_POST['calendar_type'] ?? 'solar',
    ];
    
    // CSRF 검증
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = '잘못된 요청입니다. 다시 시도해 주세요.';
    }
    
    // 이메일 검증
    if (empty($formData['email'])) {
        $errors[] = '이메일을 입력해 주세요.';
    } elseif (!isValidEmail($formData['email'])) {
        $errors[] = '올바른 이메일 형식이 아닙니다.';
    }
    
    // 비밀번호 검증
    $pwdCheck = validatePassword($formData['password']);
    if (!$pwdCheck['valid']) {
        $errors[] = $pwdCheck['message'];
    }
    
    if ($formData['password'] !== $formData['password_confirm']) {
        $errors[] = '비밀번호가 일치하지 않습니다.';
    }
    
    // 이용약관 동의 확인
    if (!$formData['terms']) {
        $errors[] = '이용약관에 동의해 주세요.';
    }
    
    // 닉네임 없으면 자동 생성
    if (empty($formData['nickname'])) {
        $formData['nickname'] = generateNickname();
    }
    
    // 중복 이메일 확인
    if (empty($errors)) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM saju_users WHERE email = ?");
        $stmt->execute([$formData['email']]);
        if ($stmt->fetch()) {
            $errors[] = '이미 사용 중인 이메일입니다.';
        }
    }
    
    // 회원가입 처리
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($formData['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            
            $stmt = $pdo->prepare("
                INSERT INTO saju_users (email, password, nickname, phone, tickets, terms_agreed, marketing_agreed, role, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'user', NOW())
            ");
            
            $stmt->execute([
                $formData['email'],
                $hashedPassword,
                $formData['nickname'],
                $formData['phone'],
                DEFAULT_TICKETS,
                1,
                $formData['marketing'] ? 1 : 0,
            ]);
            
            $userId = $pdo->lastInsertId();
            
            // 사주 프로필 저장 (입력된 경우)
            if ($formData['birth_year'] > 0 && $formData['birth_month'] > 0 && $formData['birth_day'] > 0 && !empty($formData['gender'])) {
                $stmtProfile = $pdo->prepare("
                    INSERT INTO saju_profiles (user_id, profile_name, birth_year, birth_month, birth_day, birth_hour, gender, calendar_type, is_default, created_at)
                    VALUES (?, '본인', ?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $birthHourVal = ($formData['birth_hour'] !== '' && $formData['birth_hour'] !== '-1') ? (int)$formData['birth_hour'] : null;
                $stmtProfile->execute([
                    $userId,
                    $formData['birth_year'],
                    $formData['birth_month'],
                    $formData['birth_day'],
                    $birthHourVal,
                    $formData['gender'],
                    $formData['calendar_type'],
                ]);
            }
            
            // 초기 티켓 로그 기록
            $stmt = $pdo->prepare("
                INSERT INTO saju_ticket_logs (user_id, action, amount, reason, created_at)
                VALUES (?, 'add', ?, '회원가입 축하 티켓', NOW())
            ");
            $stmt->execute([$userId, DEFAULT_TICKETS]);
            
            // 자동 로그인
            $_SESSION['user_id'] = $userId;
            setFlashMessage('success', '환영합니다! 🎊 ' . DEFAULT_TICKETS . '개의 무료 티켓이 지급되었습니다.');
            redirect('/pages/home.php');
            
        } catch (PDOException $e) {
            $errors[] = '회원가입 중 오류가 발생했습니다. 다시 시도해 주세요.';
        }
    }
}

$pageTitle = '회원가입 - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= h($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body style="padding-top: 0; padding-bottom: 0;">
    <div class="auth-container animate-fade">
        <div class="auth-logo">☯</div>
        <div class="auth-card">
            <h1 class="auth-title">회원가입</h1>
            <p class="auth-subtitle"><?= SITE_NAME ?>에서 운명을 알아보세요</p>
            
            <?php if (!empty($errors)): ?>
            <div style="background: #FFEBEE; color: #C62828; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 0.85rem;">
                <?php foreach ($errors as $error): ?>
                    <p>• <?= h($error) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <div class="form-group">
                    <label class="form-label">이메일 *</label>
                    <input type="email" name="email" class="form-input" placeholder="example@email.com" 
                           value="<?= h($formData['email'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">비밀번호 *</label>
                    <input type="password" name="password" class="form-input" placeholder="8자 이상 (영문+숫자)" 
                           required minlength="8" id="password">
                    <div class="form-hint" id="pwdStrength"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">비밀번호 확인 *</label>
                    <input type="password" name="password_confirm" class="form-input" placeholder="비밀번호를 다시 입력해 주세요" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">닉네임 <span style="color: var(--text-muted); font-weight: 400;">(비워두면 자동 생성)</span></label>
                    <input type="text" name="nickname" class="form-input" placeholder="닉네임" 
                           value="<?= h($formData['nickname'] ?? '') ?>" maxlength="20">
                </div>
                
                <div class="form-group">
                    <label class="form-label">전화번호</label>
                    <input type="tel" name="phone" class="form-input" placeholder="010-1234-5678" 
                           value="<?= h($formData['phone'] ?? '') ?>">
                </div>
                
                <!-- 사주 정보 (선택) -->
                <div style="margin: 24px 0 16px; padding: 16px; background: linear-gradient(135deg, #fdf6e3, #fef9ef); border-radius: 12px; border: 1px solid #FFE082;">
                    <div style="font-size: 0.95rem; font-weight: 700; margin-bottom: 4px; color: #E65100;">
                        ☯ 사주 정보 <span style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted);">(선택)</span>
                    </div>
                    <p style="font-size: 0.78rem; color: #8D6E63; margin-bottom: 14px;">입력하면 사주 분석 시 자동으로 입력됩니다</p>
                    
                    <div class="form-group" style="margin-bottom:10px;">
                        <label class="form-label" style="font-size:0.82rem;">성별</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="gender" value="male" <?= ($formData['gender'] ?? '') === 'male' ? 'checked' : '' ?>>
                                <i class="fas fa-mars" style="color:#42A5F5;"></i> 남성
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="gender" value="female" <?= ($formData['gender'] ?? '') === 'female' ? 'checked' : '' ?>>
                                <i class="fas fa-venus" style="color:#EC407A;"></i> 여성
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:10px;">
                        <label class="form-label" style="font-size:0.82rem;">역법</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="calendar_type" value="solar" <?= ($formData['calendar_type'] ?? 'solar') === 'solar' ? 'checked' : '' ?>>
                                ☀️ 양력
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="calendar_type" value="lunar" <?= ($formData['calendar_type'] ?? 'solar') === 'lunar' ? 'checked' : '' ?>>
                                🌙 음력
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:10px;">
                        <label class="form-label" style="font-size:0.82rem;">출생 연도</label>
                        <select name="birth_year" class="form-select">
                            <option value="0">연도 선택</option>
                            <?php for ($y = (int)date('Y'); $y >= 1940; $y--): ?>
                            <option value="<?= $y ?>" <?= ($formData['birth_year'] ?? 0) == $y ? 'selected' : '' ?>><?= $y ?>년</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" style="margin-bottom:10px;">
                            <label class="form-label" style="font-size:0.82rem;">출생 월</label>
                            <select name="birth_month" class="form-select">
                                <option value="0">월</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= ($formData['birth_month'] ?? 0) == $m ? 'selected' : '' ?>><?= $m ?>월</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label class="form-label" style="font-size:0.82rem;">출생 일</label>
                            <select name="birth_day" class="form-select">
                                <option value="0">일</option>
                                <?php for ($d = 1; $d <= 31; $d++): ?>
                                <option value="<?= $d ?>" <?= ($formData['birth_day'] ?? 0) == $d ? 'selected' : '' ?>><?= $d ?>일</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:4px;">
                        <label class="form-label" style="font-size:0.82rem;">출생 시간</label>
                        <select name="birth_hour" class="form-select">
                            <option value="">모름</option>
                            <option value="0" <?= ($formData['birth_hour'] ?? '') === '0' ? 'selected' : '' ?>>자시 (23:30~01:30)</option>
                            <option value="1" <?= ($formData['birth_hour'] ?? '') === '1' ? 'selected' : '' ?>>축시 (01:30~03:30)</option>
                            <option value="3" <?= ($formData['birth_hour'] ?? '') === '3' ? 'selected' : '' ?>>인시 (03:30~05:30)</option>
                            <option value="5" <?= ($formData['birth_hour'] ?? '') === '5' ? 'selected' : '' ?>>묘시 (05:30~07:30)</option>
                            <option value="7" <?= ($formData['birth_hour'] ?? '') === '7' ? 'selected' : '' ?>>진시 (07:30~09:30)</option>
                            <option value="9" <?= ($formData['birth_hour'] ?? '') === '9' ? 'selected' : '' ?>>사시 (09:30~11:30)</option>
                            <option value="11" <?= ($formData['birth_hour'] ?? '') === '11' ? 'selected' : '' ?>>오시 (11:30~13:30)</option>
                            <option value="13" <?= ($formData['birth_hour'] ?? '') === '13' ? 'selected' : '' ?>>미시 (13:30~15:30)</option>
                            <option value="15" <?= ($formData['birth_hour'] ?? '') === '15' ? 'selected' : '' ?>>신시 (15:30~17:30)</option>
                            <option value="17" <?= ($formData['birth_hour'] ?? '') === '17' ? 'selected' : '' ?>>유시 (17:30~19:30)</option>
                            <option value="19" <?= ($formData['birth_hour'] ?? '') === '19' ? 'selected' : '' ?>>술시 (19:30~21:30)</option>
                            <option value="21" <?= ($formData['birth_hour'] ?? '') === '21' ? 'selected' : '' ?>>해시 (21:30~23:30)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" required <?= ($formData['terms'] ?? false) ? 'checked' : '' ?>>
                        <span><strong>[필수]</strong> 이용약관에 동의합니다</span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="marketing" <?= ($formData['marketing'] ?? false) ? 'checked' : '' ?>>
                        <span>[선택] 마케팅 정보 수신에 동의합니다</span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg">회원가입</button>
            </form>
            
            <div class="auth-links">
                <a href="<?= SITE_URL ?>/auth/login.php">이미 계정이 있으신가요? <strong>로그인</strong></a>
            </div>
        </div>
    </div>
    
    <script src="<?= SITE_URL ?>/assets/js/app.js"></script>
    <script>
        document.getElementById('password').addEventListener('input', function() {
            checkPasswordStrength(this, document.getElementById('pwdStrength'));
        });
    </script>
</body>
</html>
