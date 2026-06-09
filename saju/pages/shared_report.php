<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/report_share_functions.php';

$token = trim((string)($_GET['token'] ?? ''));
$report = $token !== '' ? getSharedReportByToken($token) : null;

if (!$report) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>보고서를 찾을 수 없습니다 - <?= h(SITE_NAME) ?></title>
        <style>
            body { margin: 0; background: #F4F3F0; color: #333; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
            .wrap { max-width: 640px; margin: 80px auto; padding: 24px; }
            .card { background: #fff; border: 1px solid #E0DDD8; border-radius: 16px; padding: 28px; box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
            h1 { margin: 0 0 12px; font-size: 1.5rem; }
            p { margin: 0; line-height: 1.8; color: #555; }
        </style>
    </head>
    <body>
        <div class="wrap">
            <div class="card">
                <h1>보고서를 찾을 수 없습니다.</h1>
                <p>링크가 잘못되었거나 유효 기간이 지난 보고서입니다.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

echo buildSharedReportDocument($report['report_title'] ?? '사주 보고서', $report['report_html'] ?? '');