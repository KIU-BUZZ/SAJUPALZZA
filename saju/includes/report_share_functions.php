<?php

function ensureReportShareTable() {
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $pdo = getDBConnection();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS saju_shared_reports (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(64) NOT NULL UNIQUE,
            client_name VARCHAR(100) NOT NULL,
            recipient_email VARCHAR(255) DEFAULT NULL,
            report_title VARCHAR(255) NOT NULL,
            report_html MEDIUMTEXT NOT NULL,
            created_by INT DEFAULT NULL,
            sent_at DATETIME DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_shared_reports_expires_at (expires_at),
            INDEX idx_shared_reports_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $initialized = true;
}

function createReportShare(array $data) {
    ensureReportShareTable();

    $pdo = getDBConnection();
    $token = bin2hex(random_bytes(24));
    $expiresAt = $data['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+30 days'));

    $stmt = $pdo->prepare(
        "INSERT INTO saju_shared_reports
            (token, client_name, recipient_email, report_title, report_html, created_by, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $token,
        $data['client_name'] ?? '미입력',
        $data['recipient_email'] ?? null,
        $data['report_title'] ?? '사주 보고서',
        $data['report_html'] ?? '',
        $data['created_by'] ?? null,
        $expiresAt,
    ]);

    return [
        'token' => $token,
        'url' => getAbsoluteSiteUrl('/pages/shared_report.php?token=' . $token),
        'expires_at' => $expiresAt,
    ];
}

function markReportShareSent($token) {
    ensureReportShareTable();

    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE saju_shared_reports SET sent_at = NOW() WHERE token = ?");
    $stmt->execute([$token]);
}

function getSharedReportByToken($token) {
    ensureReportShareTable();

    $token = trim((string)$token);
    if ($token === '') {
        return null;
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT *
         FROM saju_shared_reports
         WHERE token = ?
           AND (expires_at IS NULL OR expires_at >= NOW())
         LIMIT 1"
    );
    $stmt->execute([$token]);

    return $stmt->fetch() ?: null;
}

function getAdminReportStyles() {
    static $styles = null;

    if ($styles !== null) {
        return $styles;
    }

    $reportSource = @file_get_contents(__DIR__ . '/../admin/report.php');
    if ($reportSource && preg_match('/<style>(.*?)<\/style>/si', $reportSource, $matches)) {
        $styles = trim($matches[1]);
        return $styles;
    }

    $styles = "body{margin:0;background:#F4F3F0;color:#333;font-family:'Noto Sans KR',sans-serif;} .container{max-width:1100px;margin:0 auto;padding:24px;} .report{background:#fff;border:1px solid #E0DDD8;box-shadow:0 4px 16px rgba(0,0,0,0.08);} p{line-height:1.8;}";
    return $styles;
}

function buildSharedReportDocument($title, $reportHtml) {
    $styles = getAdminReportStyles();
    $title = htmlspecialchars($title ?: '사주 보고서', ENT_QUOTES, 'UTF-8');

    return "<!DOCTYPE html>
<html lang=\"ko\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>{$title}</title>
    <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css\">
    <link href=\"https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700;900&family=Noto+Serif+KR:wght@400;700;900&display=swap\" rel=\"stylesheet\">
    <style>{$styles}
        .main { margin-left: 0 !important; }
        .container { max-width: 1100px; margin: 0 auto; padding: 24px 16px; }
        .report-wrap { margin-top: 0; }
        .no-print, .sidebar, .topbar { display: none !important; }
        .expert-detail-section { display: none !important; }
        @media (max-width: 768px) {
            .container { padding: 12px; }
            .report-meta-grid { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>
    <div class=\"main\">
        <div class=\"container\">
            <div class=\"report-wrap\">{$reportHtml}</div>
        </div>
    </div>
</body>
</html>";
}

function sendSharedReportMail($recipientEmail, $clientName, $shareUrl, $reportTitle) {
    $recipientEmail = trim((string)$recipientEmail);
    if ($recipientEmail === '' || !isValidEmail($recipientEmail)) {
        return false;
    }

    $clientLabel = trim((string)$clientName) !== '' ? trim((string)$clientName) : '고객';
    $subject = '[' . SITE_NAME . '] ' . $clientLabel . '님의 사주 보고서 링크';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $host = parse_url(getAbsoluteSiteUrl(), PHP_URL_HOST) ?: 'localhost';
    $from = 'no-reply@' . preg_replace('/[^a-z0-9.\-]/i', '', $host);
    if (strpos($from, '@') === false || substr($from, -1) === '@') {
        $from = 'no-reply@localhost';
    }

    $message = '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8"></head><body style="margin:0;padding:24px;background:#F4F3F0;font-family:\'Noto Sans KR\',sans-serif;color:#333;">'
        . '<div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #E0DDD8;border-radius:16px;padding:28px;">'
        . '<div style="font-size:1.2rem;font-weight:800;color:#1A1A1A;margin-bottom:12px;">' . h($reportTitle) . '</div>'
        . '<p style="font-size:0.95rem;line-height:1.8;margin:0 0 12px;">안녕하세요. ' . h($clientLabel) . '님의 사주 보고서가 준비되었습니다.</p>'
        . '<p style="font-size:0.95rem;line-height:1.8;margin:0 0 18px;">아래 버튼을 눌러 일반 사용자도 바로 이해할 수 있는 쉬운 설명 중심 보고서를 확인하실 수 있습니다.</p>'
        . '<p style="margin:0 0 20px;"><a href="' . h($shareUrl) . '" style="display:inline-block;background:#4A3D8F;color:#fff;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:700;">보고서 보기</a></p>'
        . '<p style="font-size:0.82rem;line-height:1.7;color:#666;margin:0;">버튼이 열리지 않으면 아래 주소를 복사해서 사용해 주세요.<br>' . h($shareUrl) . '</p>'
        . '</div></body></html>';

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . SITE_NAME . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'X-Mailer: PHP/' . phpversion(),
    ];

    return @mail($recipientEmail, $encodedSubject, $message, implode("\r\n", $headers));
}