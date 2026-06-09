<?php
/**
 * 관리자 - 사주 보고서 생성기 (v2 인쇄 최적화판)
 * 
 * ✅ A4 인쇄 최적화  ✅ 드롭다운 없이 전체 펼침
 * ✅ 가시성 대폭 개선  ✅ 쉬운 한국어 설명
 */
require_once __DIR__ . '/../includes/admin_check.php';
require_once SAJU_ENGINE_PATH . '/SajuEngine.php';
require_once SAJU_ENGINE_PATH . '/OhangAnalysis.php';
require_once SAJU_ENGINE_PATH . '/FortuneInterpreter.php';
require_once __DIR__ . '/../includes/fortune_services.php';
require_once __DIR__ . '/../includes/report_share_functions.php';

function admin_report_build_result(array $input) {
    $engine = new SajuEngine(
        (int)$input['birth_year'],
        (int)$input['birth_month'],
        (int)$input['birth_day'],
        (int)$input['birth_hour'],
        $input['gender'],
        $input['calendar_type']
    );

    $sajuResult = $engine->getResult();
    $ohangAnalysis = new OhangAnalysis($engine);
    $interpreter = new FortuneInterpreter($engine);

    return [
        'client_name' => trim((string)($input['client_name'] ?? '')) ?: '미입력',
        'saju' => $sajuResult,
        'ohang' => $ohangAnalysis->analyze(),
        'sipsin' => $interpreter->analyzeSipsin(),
        'gyeokguk' => $interpreter->analyzeGyeokguk(),
        'daeun' => $interpreter->analyzeDaeun(),
        'seun' => $interpreter->analyzeSeun(),
        'fortune' => $interpreter->getComprehensiveFortune(),
    ];
}

function admin_report_build_runtime_record(array $result) {
    $saju = $result['saju'];

    return [
        'birth_year' => (int)($saju['input']['year'] ?? 0),
        'birth_month' => (int)($saju['input']['month'] ?? 0),
        'birth_day' => (int)($saju['input']['day'] ?? 0),
        'birth_hour' => (int)($saju['input']['hour'] ?? 0),
        'gender' => $saju['input']['gender'] ?? 'male',
        'calendar_type' => $saju['input']['calendar_type'] ?? 'solar',
        'year_pillar' => ($saju['year_pillar']['stem'] ?? '') . ($saju['year_pillar']['branch'] ?? ''),
        'month_pillar' => ($saju['month_pillar']['stem'] ?? '') . ($saju['month_pillar']['branch'] ?? ''),
        'day_pillar' => ($saju['day_pillar']['stem'] ?? '') . ($saju['day_pillar']['branch'] ?? ''),
        'hour_pillar' => ($saju['hour_pillar']['stem'] ?? '') . ($saju['hour_pillar']['branch'] ?? ''),
    ];
}

function admin_report_build_user_stories(array $result) {
    $record = admin_report_build_runtime_record($result);
    $today = new DateTimeImmutable('today');
    $year = (int)$today->format('Y');
    $yearContext = fs_get_runtime_year_context($record, $year);
    $dailyFortune = fs_build_date_fortune($record, fs_record_cache_key($record), $today);

    $stories = [
        [
            'title' => '정통사주',
            'icon' => 'fa-pen-nib',
            'color' => '#4A3D8F',
            'story' => fs_build_traditional_saju_story($record),
        ],
        [
            'title' => $year . '년 신년운세',
            'icon' => 'fa-star',
            'color' => '#2471A3',
            'story' => fs_build_yearly_longform($record, $yearContext['year_fortune'] ?? [], $yearContext['monthly_fortunes'] ?? [], $year, 'yearly'),
        ],
        [
            'title' => $year . '년 토정비결',
            'icon' => 'fa-book-open',
            'color' => '#C8A45A',
            'story' => fs_build_yearly_longform($record, $yearContext['year_fortune'] ?? [], $yearContext['monthly_fortunes'] ?? [], $year, 'tojung'),
        ],
        [
            'title' => '대운풀이',
            'icon' => 'fa-arrows-rotate',
            'color' => '#2D7D46',
            'story' => fs_build_daeun_story($record),
        ],
        [
            'title' => '재물운',
            'icon' => 'fa-sack-dollar',
            'color' => '#C8A45A',
            'story' => fs_build_topic_fortune_story($record, 'wealth'),
        ],
        [
            'title' => '애정운',
            'icon' => 'fa-heart',
            'color' => '#C0392B',
            'story' => fs_build_topic_fortune_story($record, 'love'),
        ],
        [
            'title' => '직업운',
            'icon' => 'fa-briefcase',
            'color' => '#2471A3',
            'story' => fs_build_topic_fortune_story($record, 'career'),
        ],
        [
            'title' => '건강운',
            'icon' => 'fa-heart-pulse',
            'color' => '#E65100',
            'story' => fs_build_topic_fortune_story($record, 'health'),
        ],
    ];

    if ($dailyFortune) {
        $stories[] = [
            'title' => $today->format('n월 j일 오늘의 운세'),
            'icon' => 'fa-calendar-day',
            'color' => '#8E44AD',
            'story' => fs_build_daily_longform_story($record, $dailyFortune, $today, 'today'),
        ];
    }

    return $stories;
}

function admin_report_clean_text($text) {
    $text = str_replace(["\r\n", "\r"], "\n", trim((string)$text));
    $text = preg_replace('/[ \t]+/u', ' ', $text);
    $text = preg_replace('/\n{3,}/u', "\n\n", $text);
    return trim($text);
}

function admin_report_unique_relationships(array $relationships) {
    $unique = [];
    $seen = [];

    foreach ($relationships as $rel) {
        if (!is_array($rel)) {
            continue;
        }

        $key = trim((string)($rel['type'] ?? '')) . '|' . trim((string)($rel['chars'] ?? ''));
        if ($key === '|' || isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $unique[] = $rel;
    }

    return $unique;
}

function admin_report_plain_relation_label($type) {
    $map = [
        '육합' => '사람과 일이 잘 맞물리는 흐름',
        '삼합' => '도움과 협력이 크게 붙는 흐름',
        '방합' => '한 방향으로 힘이 모이는 흐름',
        '충' => '변화와 이동이 생기기 쉬운 흐름',
        '형' => '마찰을 조심해야 하는 흐름',
        '해' => '보이지 않는 피로를 조심할 흐름',
        '파' => '계획이 흔들리기 쉬운 흐름',
        '천간합' => '겉으로 보이는 관계가 부드러워지는 흐름',
        '천간충' => '겉으로 드러나는 충돌을 조심할 흐름',
    ];

    return $map[$type] ?? '주변 흐름을 살펴볼 포인트';
}

function admin_report_plain_relation_text(array $rel) {
    $type = trim((string)($rel['type'] ?? ''));
    $meaning = admin_report_clean_text($rel['meaning'] ?? '');
    $meaning = preg_replace('/→\s*[^\n]+/u', '', $meaning);
    $meaning = preg_replace('/\([^\)]*(沖|害|合|形|破)[^\)]*\)/u', '', $meaning);
    $meaning = trim($meaning);

    $leadMap = [
        '육합' => '주변과 손발이 맞기 쉬운 때라 협업이나 관계 정리가 조금 수월해질 수 있습니다.',
        '삼합' => '여러 흐름이 한쪽으로 모여서 도움을 받거나 성과를 묶어 내기 쉬운 때입니다.',
        '방합' => '에너지가 한 방향으로 모이기 쉬워 목표를 분명히 잡으면 힘을 쓰기 좋습니다.',
        '충' => '자리 변화, 이동, 관계의 부딪힘처럼 예상 밖 변화가 생기기 쉬운 때입니다.',
        '형' => '감정 상함이나 사소한 마찰이 커질 수 있어 말과 행동의 강도를 낮추는 편이 좋습니다.',
        '해' => '겉으로는 조용해 보여도 속으로 피로와 오해가 쌓일 수 있어 사람 문제를 가볍게 넘기지 않는 편이 좋습니다.',
        '파' => '세워 둔 계획이나 믿고 있던 흐름이 흔들릴 수 있어 중간 점검이 중요합니다.',
        '천간합' => '겉으로 드러나는 분위기는 비교적 부드럽게 흘러가서 대화와 조율이 먹히기 쉬운 때입니다.',
        '천간충' => '말, 일정, 대외 관계에서 충돌이 겉으로 드러날 수 있어 서두르기보다 조율이 먼저입니다.',
    ];

    $text = trim(($leadMap[$type] ?? '') . ' ' . $meaning);
    return fs_trim_repeated_sentences($text);
}

function admin_report_summarize_energy_block(array $lines) {
    $joined = admin_report_clean_text(implode("\n", $lines));
    if ($joined === '') {
        return '';
    }

    $sentences = [];

    if (preg_match('/좋은 변화|조화|협력|합/u', $joined)) {
        $sentences[] = '이 시기에는 사람의 도움을 받거나 막혀 있던 흐름이 조금씩 풀릴 가능성이 있습니다.';
    }

    if (preg_match('/변화|이동/u', $joined)) {
        $sentences[] = '다만 일정, 자리, 관계가 예상보다 빨리 바뀔 수 있으니 큰 결정은 한 번 더 확인하는 편이 좋습니다.';
    }

    if (preg_match('/주의|충돌|갈등|방해|손해|형|해|파|충/u', $joined)) {
        $sentences[] = '특히 말실수, 오해, 무리한 확장은 손해로 이어질 수 있어 속도를 조절하는 것이 안전합니다.';
    }

    if (empty($sentences)) {
        $sentences[] = '이 시기에는 주변 흐름이 평소보다 민감하게 바뀔 수 있으니 서두르기보다 차분하게 살피며 움직이는 편이 좋습니다.';
    }

    return fs_trim_repeated_sentences(implode(' ', $sentences));
}

function admin_report_simplify_interpretation($text, $maxParagraphs = 2) {
    $text = admin_report_clean_text($text);
    if ($text === '') {
        return '';
    }

    $lines = preg_split('/\n+/u', $text);
    $plainLines = [];
    $energyLines = [];
    $captureEnergy = false;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if (mb_strpos($line, '이 시기에 발생하는 에너지 변화') !== false) {
            $captureEnergy = true;
            continue;
        }

        if ($captureEnergy) {
            $energyLines[] = $line;
            continue;
        }

        if (preg_match('/^→/u', $line)) {
            continue;
        }

        if (preg_match('/^(삼합|육합|방합|충|형|해|파|천간합|천간충)\s/u', $line)) {
            continue;
        }

        $plainLines[] = $line;
    }

    $paragraphs = array_slice($plainLines, 0, max(1, (int)$maxParagraphs));
    $energySummary = admin_report_summarize_energy_block($energyLines);
    if ($energySummary !== '') {
        $paragraphs[] = $energySummary;
    }

    if (empty($paragraphs)) {
        $paragraphs[] = '이 시기에는 흐름을 무리하게 밀어붙이기보다, 상황을 보고 속도를 조절하는 편이 좋습니다.';
    }

    return fs_trim_repeated_sentences(implode("\n\n", $paragraphs));
}

function admin_report_short_sipsin_label($label) {
    $label = trim((string)$label);

    if ($label === '') {
        return '흐름';
    }

    if (mb_strpos($label, '재') !== false) {
        return '돈';
    }
    if (mb_strpos($label, '관') !== false) {
        return '책임';
    }
    if (mb_strpos($label, '인') !== false) {
        return '배움';
    }
    if (mb_strpos($label, '식') !== false || mb_strpos($label, '상') !== false) {
        return '표현';
    }
    if (mb_strpos($label, '비') !== false || mb_strpos($label, '겁') !== false) {
        return '자기힘';
    }

    return $label;
}

function admin_report_plain_sipsin_focus($label, $prefix) {
    $label = trim((string)$label);

    if ($label === '') {
        return $prefix . ' 방향을 다시 정리하는 일이 중요해집니다.';
    }

    if (mb_strpos($label, '재') !== false) {
        return $prefix . ' 돈 관리, 현실 감각, 생활 기반을 챙기는 일이 중요해집니다.';
    }
    if (mb_strpos($label, '관') !== false) {
        return $prefix . ' 책임, 규칙, 맡은 역할을 안정적으로 지키는 일이 중요해집니다.';
    }
    if (mb_strpos($label, '인') !== false) {
        return $prefix . ' 배우고 회복하는 힘, 몸과 마음을 정비하는 일이 중요해집니다.';
    }
    if (mb_strpos($label, '식') !== false || mb_strpos($label, '상') !== false) {
        return $prefix . ' 실력, 표현, 말과 아이디어를 밖으로 보여 주는 일이 중요해집니다.';
    }
    if (mb_strpos($label, '비') !== false || mb_strpos($label, '겁') !== false) {
        return $prefix . ' 자기 기준을 세우고 주도권을 잃지 않는 일이 중요해집니다.';
    }

    return $prefix . ' 생활 리듬과 우선순위를 차분히 맞추는 일이 중요해집니다.';
}

function admin_report_build_daeun_summary(array $daeun) {
    $score = (int)($daeun['score'] ?? 50);
    $scoreSentence = $score >= 70
        ? '이 시기는 큰 판을 벌리기보다도, 이미 준비해 둔 일을 자연스럽게 키우기 좋은 편입니다.'
        : ($score >= 50
            ? '이 시기는 무리만 하지 않으면 안정적으로 흐름을 이어 가기 좋은 편입니다.'
            : ($score >= 35
                ? '이 시기는 성급하게 넓히기보다 생활과 일의 기본을 다시 다지는 편이 더 유리합니다.'
                : '이 시기는 욕심을 줄이고 건강, 돈, 관계의 기본을 먼저 지키는 쪽이 훨씬 안전합니다.'));

    $focusSentence = admin_report_plain_sipsin_focus(trim((string)($daeun['stem_sipsin'] ?? '')), '겉으로는')
        . ' '
        . admin_report_plain_sipsin_focus(trim((string)($daeun['branch_sipsin'] ?? '')), '생활 안에서는');

    if (!empty($daeun['is_yongshin'])) {
        $adviceSentence = '나와 잘 맞는 기운이 들어오는 시기라, 회복력과 기회가 함께 살아날 가능성이 있습니다. 중요한 일은 너무 늦추지 말고 차근차근 실행하는 편이 좋습니다.';
    } elseif ($score >= 55) {
        $adviceSentence = '좋은 흐름이 완전히 저절로 굴러가는 시기는 아니더라도, 기준만 흔들리지 않으면 결과를 꽤 안정적으로 만들 수 있습니다.';
    } elseif ($score >= 35) {
        $adviceSentence = '성과를 급하게 확인하려 하기보다, 꾸준히 버티면서 정리할 것을 정리하는 태도가 실제 차이를 만듭니다.';
    } else {
        $adviceSentence = '특히 말실수, 무리한 투자, 감정적으로 내리는 결정은 손해로 이어질 수 있어 한 박자 늦추는 습관이 중요합니다.';
    }

    return fs_trim_repeated_sentences($scoreSentence . ' ' . $focusSentence . ' ' . $adviceSentence);
}

function admin_report_story_paragraphs(array $story, array $sectionIndexes, $limit = 2) {
    $paragraphs = [];
    $seen = [];

    foreach ($sectionIndexes as $index) {
        $section = $story['sections'][$index] ?? null;
        if (!is_array($section)) {
            continue;
        }

        foreach (($section['paragraphs'] ?? []) as $paragraph) {
            $paragraph = admin_report_clean_text($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $key = mb_strtolower(preg_replace('/\s+/u', ' ', $paragraph), 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $paragraphs[] = $paragraph;

            if (count($paragraphs) >= $limit) {
                return $paragraphs;
            }
        }
    }

    return $paragraphs;
}

function admin_report_build_easy_fortune_sections(array $userStories, array $fortune) {
    $storyMap = [];
    foreach ($userStories as $entry) {
        $storyMap[$entry['title'] ?? ''] = $entry['story'] ?? [];
    }

    $traditional = $storyMap['정통사주'] ?? [];
    $wealth = $storyMap['재물운'] ?? [];
    $love = $storyMap['애정운'] ?? [];
    $career = $storyMap['직업운'] ?? [];
    $health = $storyMap['건강운'] ?? [];

    $sections = [
        [
            'icon' => 'fa-user-circle',
            'title' => '성격과 기질 — 나는 어떤 사람인가',
            'color' => '#4A3D8F',
            'paragraphs' => admin_report_story_paragraphs($traditional, [0, 1], 2),
        ],
        [
            'icon' => 'fa-heart',
            'title' => '연애와 결혼 — 사랑의 패턴',
            'color' => '#C0392B',
            'paragraphs' => admin_report_story_paragraphs($love, [0, 1], 2),
        ],
        [
            'icon' => 'fa-briefcase',
            'title' => '직업과 적성 — 나에게 맞는 일',
            'color' => '#2471A3',
            'paragraphs' => admin_report_story_paragraphs($career, [0, 1], 2),
        ],
        [
            'icon' => 'fa-coins',
            'title' => '재물운 — 돈과의 관계',
            'color' => '#C8A45A',
            'paragraphs' => admin_report_story_paragraphs($wealth, [0, 1], 2),
        ],
        [
            'icon' => 'fa-graduation-cap',
            'title' => '학업과 자기계발',
            'color' => '#2D7D46',
            'paragraphs' => array_filter([
                admin_report_simplify_interpretation(is_string($fortune['study'] ?? '') ? ($fortune['study'] ?? '') : (($fortune['study']['content'] ?? '')), 2),
            ]),
        ],
        [
            'icon' => 'fa-heartbeat',
            'title' => '건강 — 몸과 마음 관리',
            'color' => '#E65100',
            'paragraphs' => admin_report_story_paragraphs($health, [0, 1], 2),
        ],
        [
            'icon' => 'fa-road',
            'title' => '인생 흐름 — 삶의 큰 그림',
            'color' => '#1A1A1A',
            'paragraphs' => admin_report_story_paragraphs($traditional, [2], 2),
        ],
    ];

    return array_values(array_filter($sections, function ($section) {
        return !empty($section['paragraphs']);
    }));
}

$result = null;
$errors = [];
$shareFeedback = null;
$shareRequest = null;
$action = $_POST['action'] ?? 'generate';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $birthYear   = (int)($_POST['birth_year'] ?? 0);
    $birthMonth  = (int)($_POST['birth_month'] ?? 0);
    $birthDay    = (int)($_POST['birth_day'] ?? 0);
    $birthHour   = (int)($_POST['birth_hour'] ?? -1);
    $gender      = $_POST['gender'] ?? '';
    $calendarType = $_POST['calendar_type'] ?? 'solar';
    $clientName  = trim($_POST['client_name'] ?? '');
    $recipientEmail = trim($_POST['recipient_email'] ?? '');

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = '유효하지 않은 요청입니다. 새로고침 후 다시 시도해 주세요.';
    }

    if ($birthYear < 1900 || $birthYear > (int)date('Y')) $errors[] = '올바른 출생 연도를 입력해 주세요.';
    if ($birthMonth < 1 || $birthMonth > 12) $errors[] = '올바른 출생 월을 선택해 주세요.';
    if ($birthDay < 1 || $birthDay > 31) $errors[] = '올바른 출생 일을 입력해 주세요.';
    if ($birthHour < 0 || $birthHour > 23) $errors[] = '올바른 출생 시간을 선택해 주세요.';
    if (!in_array($gender, ['male','female'], true)) $errors[] = '성별을 선택해 주세요.';

    if (empty($errors)) {
        try {
            $result = admin_report_build_result([
                'birth_year' => $birthYear,
                'birth_month' => $birthMonth,
                'birth_day' => $birthDay,
                'birth_hour' => $birthHour,
                'gender' => $gender,
                'calendar_type' => $calendarType,
                'client_name' => $clientName,
            ]);

            if ($action === 'send_mail') {
                if ($recipientEmail === '' || !isValidEmail($recipientEmail)) {
                    $shareRequest = ['error' => '메일을 보낼 주소를 정확히 입력해 주세요.'];
                } else {
                    $shareRequest = ['recipient_email' => $recipientEmail];
                }
            }
        } catch (Exception $e) {
            $errors[] = '분석 오류: ' . $e->getMessage();
        }
    }
}

$pageTitle = '사주 보고서 생성 - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700;900&family=Noto+Serif+KR:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           BASE VARIABLES & RESET
           ============================================================ */
        :root {
            --sidebar-w: 260px;
            --accent: #4A3D8F;
            --accent-light: #7C6EC4;
            --accent-soft: #EDE8FF;
            --gold: #C8A45A;
            --gold-light: #F5ECD7;
            --bg: #F4F3F0;
            --card-bg: #FFFFFF;
            --text-primary: #1A1A1A;
            --text-body: #333333;
            --text-secondary: #555555;
            --text-muted: #999999;
            --border: #E0DDD8;
            --border-light: #F0EEEA;
            --success: #2D7D46;
            --danger: #C0392B;
            --info: #2471A3;
            --warm-bg: #FDFBF7;
            --radius: 12px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg);
            font-family: 'Noto Sans KR', -apple-system, sans-serif;
            color: var(--text-body);
            line-height: 1.85;
            font-size: 15px;
            -webkit-font-smoothing: antialiased;
        }

        /* ============================================================
           A4 PAGE RULES
           ============================================================ */
        @page {
            size: A4;
            margin: 14mm 12mm 16mm 12mm;
        }

        /* ============================================================
           SIDEBAR (화면용, 인쇄시 숨김)
           ============================================================ */
        .sidebar {
            width: var(--sidebar-w); position: fixed; top: 0; left: 0; bottom: 0;
            background: linear-gradient(180deg, #2D3436 0%, #1E272E 100%);
            z-index: 100; display: flex; flex-direction: column;
        }
        .sidebar-brand { padding: 28px 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .sidebar-brand h2 {
            color: #fff; font-size: 1.15rem; font-weight: 700;
            display: flex; align-items: center; gap: 10px;
        }
        .sidebar-brand h2 .brand-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
        }
        .sidebar-brand small { color: rgba(255,255,255,0.35); font-size: 0.72rem; display: block; margin-top: 4px; margin-left: 46px; }
        .sidebar-nav { list-style: none; padding: 12px 12px; flex: 1; }
        .sidebar-nav li { margin-bottom: 2px; }
        .sidebar-nav a {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 16px; color: rgba(255,255,255,0.55);
            text-decoration: none; border-radius: 10px; transition: all 0.2s; font-size: 0.88rem;
        }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.85); }
        .sidebar-nav a.active {
            background: linear-gradient(135deg, var(--accent), #5A4BD1);
            color: #fff; font-weight: 600; box-shadow: 0 4px 12px rgba(74,61,143,0.3);
        }
        .sidebar-nav a i { width: 20px; text-align: center; font-size: 0.9rem; }
        .sidebar-divider { height: 1px; background: rgba(255,255,255,0.06); margin: 12px 16px; }
        .sidebar-footer { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.06); }
        .sidebar-footer a { color: rgba(255,255,255,0.45); font-size: 0.8rem; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .sidebar-footer a:hover { color: rgba(255,255,255,0.75); }

        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main { margin-left: var(--sidebar-w); min-height: 100vh; }
        .topbar {
            position: sticky; top: 0; z-index: 50;
            background: rgba(244,243,240,0.9); backdrop-filter: blur(12px);
            padding: 16px 32px; display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid var(--border);
        }
        .topbar h1 { font-size: 1.25rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }
        .topbar h1 i { color: var(--accent); }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .topbar-avatar {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, var(--accent-light), var(--accent));
            color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700;
        }
        .container { padding: 24px 32px; max-width: 1100px; }

        /* ============================================================
           FORM
           ============================================================ */
        .form-card {
            background: var(--card-bg); border-radius: var(--radius); padding: 28px;
            box-shadow: var(--shadow-md); border: 1px solid var(--border);
        }
        .form-card-title {
            font-size: 1.05rem; font-weight: 700; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px; color: var(--text-primary);
        }
        .form-card-title i {
            width: 32px; height: 32px; border-radius: 8px;
            background: linear-gradient(135deg, var(--accent-light), var(--accent));
            color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;
        }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap: 16px; }
        .fg { display: flex; flex-direction: column; }
        .fg label { font-size: 0.78rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .fg input, .fg select {
            padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 10px;
            font-size: 0.9rem; font-family: inherit; background: var(--warm-bg); transition: all 0.2s;
        }
        .fg input:focus, .fg select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(74,61,143,0.1); background: #fff; }
        .btn-generate {
            background: linear-gradient(135deg, var(--accent), #5A4BD1);
            color: #fff; border: none; padding: 12px 32px; font-size: 0.95rem;
            border-radius: 12px; cursor: pointer; font-weight: 700;
            font-family: inherit; display: inline-flex; align-items: center; gap: 8px;
            box-shadow: 0 4px 15px rgba(74,61,143,0.3); transition: all 0.2s;
        }
        .btn-generate:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(74,61,143,0.4); }
        .btn-print {
            background: var(--card-bg); color: var(--text-secondary); border: 1.5px solid var(--border);
            padding: 10px 20px; font-size: 0.85rem; border-radius: 10px; cursor: pointer;
            font-weight: 500; font-family: inherit; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;
        }
        .btn-print:hover { border-color: var(--accent); color: var(--accent); }
        .error-toast {
            background: #FFF3E0; color: #E65100;
            padding: 12px 18px; border-radius: 12px; margin-bottom: 16px;
            font-size: 0.88rem; display: flex; align-items: center; gap: 10px;
            border: 1px solid #FFE0B2;
        }

        /* ============================================================
           REPORT — GLOBAL
           ============================================================ */
        .report-wrap { margin-top: 24px; }
        .report {
            background: var(--card-bg); overflow: hidden;
            box-shadow: var(--shadow-md); border: 1px solid var(--border);
        }

        /* Report Hero (Cover Page) */
        .report-hero {
            background: linear-gradient(160deg, #1A1A2E 0%, #2D2B55 40%, #4A3D8F 100%);
            color: #fff; padding: 56px 48px 48px; position: relative; overflow: hidden;
            page-break-after: always;
        }
        .report-hero::before {
            content: ''; position: absolute; top: -30%; right: -10%; width: 350px; height: 350px;
            background: radial-gradient(circle, rgba(200,164,90,0.15) 0%, transparent 70%); border-radius: 50%;
        }
        .report-hero-inner { position: relative; z-index: 1; }
        .report-hero .title-line { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
        .report-hero .title-icon {
            width: 56px; height: 56px; border-radius: 14px;
            background: linear-gradient(135deg, var(--gold), #D4A843);
            display: flex; align-items: center; justify-content: center; font-size: 1.6rem;
            box-shadow: 0 4px 16px rgba(200,164,90,0.3);
        }
        .report-hero h2 {
            font-family: 'Noto Serif KR', serif;
            font-size: 1.8rem; font-weight: 900; letter-spacing: -0.5px;
        }
        .report-hero .subtitle { color: rgba(255,255,255,0.5); font-size: 0.85rem; margin-top: 4px; }
        .report-meta-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 12px; margin-top: 28px;
        }
        .report-meta-item {
            background: rgba(255,255,255,0.07); border-radius: 10px;
            padding: 14px 18px; border: 1px solid rgba(255,255,255,0.08);
        }
        .report-meta-item .meta-label { font-size: 0.72rem; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.5px; }
        .report-meta-item .meta-value { font-size: 0.95rem; font-weight: 600; margin-top: 4px; }

        /* Report Body */
        .report-body { padding: 0; }

        /* ============================================================
           SECTION STYLES
           ============================================================ */
        .rpt-section {
            padding: 36px 44px;
            border-bottom: 2px solid var(--border-light);
            page-break-inside: avoid;
        }
        .rpt-section:last-child { border-bottom: none; }

        /* Section page break hints — large sections start on new page */
        .rpt-section.page-start { page-break-before: always; }

        .rpt-section-head {
            display: flex; align-items: flex-start; gap: 14px; margin-bottom: 24px;
        }
        .rpt-section-num {
            width: 40px; height: 40px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; font-weight: 800; color: #fff; flex-shrink: 0;
            margin-top: 2px;
        }
        .rpt-section-title {
            font-family: 'Noto Serif KR', serif;
            font-size: 1.2rem; font-weight: 900; color: var(--text-primary);
            letter-spacing: -0.3px;
        }
        .rpt-section-subtitle {
            font-size: 0.88rem; color: var(--text-secondary); margin-top: 4px;
            line-height: 1.6;
        }
        .rpt-desc {
            font-size: 0.92rem; color: var(--text-body); line-height: 1.85;
            margin-bottom: 20px; background: var(--warm-bg); padding: 16px 20px;
            border-radius: 10px; border-left: 3px solid var(--gold);
        }

        /* ============================================================
           PILLAR TABLE
           ============================================================ */
        .pillar-grid {
            display: grid; grid-template-columns: 70px repeat(4,1fr);
            border: 2px solid var(--border); border-radius: 12px; overflow: hidden;
        }
        .pillar-grid .pg-head {
            background: var(--accent); color: #fff; font-size: 0.78rem; font-weight: 700;
            padding: 12px 8px; text-align: center; letter-spacing: 0.3px;
        }
        .pillar-grid .pg-label {
            background: #F8F7F4; font-size: 0.82rem; font-weight: 700;
            color: var(--text-secondary); padding: 12px 8px; display: flex;
            align-items: center; justify-content: center;
        }
        .pillar-grid .pg-cell {
            padding: 16px 10px; text-align: center;
            border-top: 1px solid var(--border); border-left: 1px solid var(--border);
            background: #fff;
        }
        .pg-cell .hanja { font-size: 1.8rem; font-weight: 900; line-height: 1.1; }
        .pg-cell .Korean { font-size: 0.88rem; color: var(--text-secondary); margin-top: 6px; }
        .pg-cell .element-tag {
            display: inline-block; padding: 3px 10px; border-radius: 6px;
            font-size: 0.75rem; font-weight: 700; margin-top: 6px;
        }
        .el-목 { background: #E8F5E9; color: #1B5E20; } .el-화 { background: #FFEBEE; color: #B71C1C; }
        .el-토 { background: #FFF8E1; color: #E65100; } .el-금 { background: #F3E5F5; color: #4A148C; }
        .el-수 { background: #E3F2FD; color: #0D47A1; }

        .tc-목{color:#1B5E20} .tc-화{color:#B71C1C} .tc-토{color:#E65100} .tc-금{color:#4A148C} .tc-수{color:#0D47A1}

        /* ============================================================
           GOD CARDS (용신/희신/기신/구신)
           ============================================================ */
        .god-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; }
        .god-card {
            border-radius: 12px; padding: 20px 16px; text-align: center;
            border: 2px solid var(--border); position: relative; overflow: hidden;
            background: #fff;
        }
        .god-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
        }
        .god-card .god-label { font-size: 0.78rem; font-weight: 700; margin-bottom: 4px; }
        .god-card .god-desc { font-size: 0.72rem; color: var(--text-muted); margin-bottom: 6px; }
        .god-card .god-element { font-size: 1.6rem; font-weight: 900; margin: 8px 0; }
        .god-card .god-type { font-size: 0.72rem; color: var(--text-muted); }

        /* ============================================================
           BAR CHART
           ============================================================ */
        .bar-row { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
        .bar-label { min-width: 50px; text-align: right; }
        .bar-track { flex: 1; height: 28px; background: #F0EEEA; border-radius: 8px; overflow: hidden; position: relative; }
        .bar-fill {
            height: 100%; border-radius: 8px; display: flex; align-items: center;
            padding-left: 12px; font-size: 0.78rem; font-weight: 700; color: #fff;
            transition: width 0.6s ease;
        }
        .bar-value { min-width: 45px; font-size: 0.82rem; font-weight: 600; text-align: left; color: var(--text-secondary); }

        /* ============================================================
           DAEUN TIMELINE (가로 타임라인)
           ============================================================ */
        .daeun-flow { display: flex; gap: 0; overflow-x: auto; padding-bottom: 10px; margin-bottom: 24px; }
        .daeun-node {
            min-width: 100px; text-align: center; padding: 14px 10px; position: relative;
            border-radius: 10px; border: 2px solid var(--border); margin-right: 6px; flex-shrink: 0;
            background: #fff;
        }
        .daeun-node.yongshin {
            border-color: var(--gold); background: var(--gold-light);
        }
        .daeun-node .dn-age { font-size: 0.72rem; color: var(--text-muted); font-weight: 700; }
        .daeun-node .dn-pillar { font-size: 1.15rem; font-weight: 900; margin: 4px 0 2px; }
        .daeun-node .dn-sipsin { font-size: 0.72rem; color: var(--text-secondary); margin-top: 4px; }
        .daeun-node .dn-score {
            display: inline-block; margin-top: 6px; padding: 2px 10px; border-radius: 20px;
            font-size: 0.72rem; font-weight: 700; color: #fff;
        }

        /* ============================================================
           DAEUN DETAIL CARDS (펼침형, 드롭다운 없음)
           ============================================================ */
        .daeun-detail-card {
            margin-bottom: 16px; border: 2px solid var(--border);
            border-radius: 12px; overflow: hidden;
            page-break-inside: avoid;
        }
        .daeun-detail-card.is-yongshin { border-color: var(--gold); }
        .daeun-detail-header {
            padding: 16px 20px;
            background: #F8F7F4;
            display: flex; align-items: center; gap: 12px;
            border-bottom: 1px solid var(--border-light);
        }
        .daeun-detail-header .ddh-age {
            font-family: 'Noto Serif KR', serif;
            font-size: 1.05rem; font-weight: 900; color: var(--text-primary);
        }
        .daeun-detail-header .ddh-pillar {
            font-size: 0.92rem; color: var(--text-secondary); font-weight: 500;
        }
        .daeun-detail-header .ddh-score { margin-left: auto; }
        .daeun-detail-body {
            padding: 20px 24px;
            font-size: 0.92rem; color: var(--text-body); line-height: 1.9;
            white-space: pre-line;
        }

        /* ============================================================
           SEUN ROW
           ============================================================ */
        .seun-card {
            display: grid; grid-template-columns: 90px 1fr 60px; gap: 16px;
            align-items: start; padding: 20px 0; border-bottom: 1px solid var(--border-light);
            page-break-inside: avoid;
        }
        .seun-card:last-child { border-bottom: none; }
        .seun-year {
            font-family: 'Noto Serif KR', serif;
            font-size: 1.25rem; font-weight: 900; text-align: center;
        }
        .seun-year small { display: block; font-size: 0.75rem; font-weight: 400; color: var(--text-muted); }
        .seun-body { font-size: 0.9rem; line-height: 1.85; color: var(--text-body); }
        .seun-score { font-size: 1.15rem; font-weight: 900; text-align: center; padding-top: 4px; }

        /* ============================================================
           FORTUNE SECTION (종합 운세)
           ============================================================ */
        .fortune-item {
            padding: 28px 0; border-bottom: 1px solid var(--border-light);
            page-break-inside: avoid;
        }
        .fortune-item:last-child { border-bottom: none; }
        .fortune-item-head {
            display: flex; align-items: center; gap: 12px; margin-bottom: 14px;
        }
        .fortune-item-icon {
            width: 36px; height: 36px; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; font-size: 0.9rem; color: #fff; flex-shrink: 0;
        }
        .fortune-item-title {
            font-family: 'Noto Serif KR', serif;
            font-size: 1.05rem; font-weight: 900; color: var(--text-primary);
        }
        .fortune-item-text {
            font-size: 0.93rem; color: var(--text-body); line-height: 2;
            white-space: pre-line; letter-spacing: -0.1px;
        }

        /* ============================================================
           MISC COMPONENTS
           ============================================================ */
        .score-pill { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; }
        .sp-high { background: #E8F5E9; color: #1B5E20; }
        .sp-mid { background: #E3F2FD; color: #0D47A1; }
        .sp-low { background: #FFF3E0; color: #E65100; }
        .sp-danger { background: #FFEBEE; color: #B71C1C; }

        .rel-tag {
            display: inline-flex; align-items: center; padding: 4px 12px;
            border-radius: 6px; font-size: 0.78rem; font-weight: 700; margin-right: 4px;
        }

        .sipsin-card {
            border-radius: 12px; padding: 18px 20px; margin-bottom: 12px;
            border-left: 4px solid var(--accent); background: var(--warm-bg);
        }
        .sipsin-card .sc-head { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .sipsin-card .sc-level {
            font-size: 0.72rem; font-weight: 700; padding: 3px 10px; border-radius: 4px; color: #fff;
        }
        .sipsin-card .sc-title { font-weight: 700; font-size: 0.92rem; }
        .sipsin-card .sc-text { font-size: 0.88rem; color: var(--text-body); line-height: 1.85; white-space: pre-line; }

        .info-block {
            background: var(--warm-bg); border-radius: 12px; padding: 18px 22px;
            font-size: 0.92rem; line-height: 1.85; color: var(--text-body); white-space: pre-line;
            margin-top: 14px;
        }

        .sup-card {
            border: 1px solid var(--border); border-radius: 10px; padding: 16px 18px;
            margin-bottom: 10px; background: var(--warm-bg);
        }
        .sup-card .sup-head { font-weight: 700; font-size: 0.9rem; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .sup-card ul { list-style: none; padding: 0; margin: 0; }
        .sup-card li { font-size: 0.85rem; color: var(--text-body); padding: 4px 0; padding-left: 18px; position: relative; }
        .sup-card li::before { content: '✦'; position: absolute; left: 0; color: var(--accent); }

        .jjg-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; }
        .jjg-item { background: var(--warm-bg); border-radius: 10px; padding: 14px; text-align: center; border: 1px solid var(--border); }
        .jjg-item .jjg-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 700; margin-bottom: 6px; }

        .report-footer {
            text-align: center; padding: 28px 44px; background: #F8F7F4;
            border-top: 2px solid var(--border-light);
        }
        .report-footer p { font-size: 0.82rem; color: var(--text-muted); line-height: 1.6; }

        /* Wonguk Patterns */
        .wonguk-pattern-tag {
            display: inline-block; padding: 5px 14px; border-radius: 20px;
            font-size: 0.82rem; font-weight: 600;
            background: var(--accent); color: #fff;
            margin: 3px 4px;
        }

        /* 30yr flow */
        .thirty-year-block {
            margin-top: 20px; padding: 20px 24px;
            background: var(--gold-light); border-radius: 12px;
            border: 1px solid #E8D9B0;
        }
        .thirty-year-block h4 {
            font-family: 'Noto Serif KR', serif;
            font-size: 1rem; font-weight: 900; margin-bottom: 12px; color: var(--text-primary);
        }
        .thirty-year-block p {
            font-size: 0.9rem; color: var(--text-body); line-height: 1.85;
        }

        .expert-detail-section { display: none; }
        body.show-expert-report .expert-detail-section { display: block; }

        .print-summary-guide {
            margin: 16px 0 0;
            padding: 14px 16px;
            background: #FFF9EC;
            border: 1px solid #F5E4B8;
            border-radius: 12px;
            font-size: 0.84rem;
            color: var(--text-secondary);
            line-height: 1.7;
        }

        /* ============================================================
           PRINT STYLES
           ============================================================ */
        @media print {
            body {
                background: #fff !important;
                font-size: 10.5pt !important;
                line-height: 1.7 !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .sidebar, .topbar, .no-print { display: none !important; }
            .main { margin-left: 0 !important; }
            .container { padding: 0 !important; max-width: 100% !important; }
            .report { box-shadow: none !important; border: none !important; }
            .expert-detail-section { display: none !important; }
            .print-summary-guide { display: none !important; }

            .report-hero {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                padding: 40px 36px 36px !important;
            }
            .rpt-section {
                padding: 28px 32px !important;
                page-break-inside: avoid;
            }
            .rpt-section.page-start { page-break-before: always; }

            .daeun-detail-card { page-break-inside: avoid; }
            .fortune-item { page-break-inside: avoid; }
            .seun-card { page-break-inside: avoid; }
            .sipsin-card { page-break-inside: avoid; }

            .daeun-flow { flex-wrap: wrap; overflow: visible !important; }
            .daeun-node { margin-bottom: 6px; }

            .pillar-grid .pg-head {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .element-tag, .score-pill, .rel-tag, .sc-level, .dn-score, .wonguk-pattern-tag {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .god-card, .god-card::before {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .rpt-section-num {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .fortune-item-icon {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .bar-fill {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* 헤더 반복 방지 */
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar-brand h2 span, .sidebar-brand small, .sidebar-nav a span { display: none; }
            .sidebar-nav a { justify-content: center; padding: 12px; }
            .sidebar-footer { display: none; }
            .main { margin-left: 60px; }
            .container { padding: 16px; }
            .rpt-section { padding: 20px; }
            .report-hero { padding: 24px 20px; }
            .god-grid { grid-template-columns: 1fr 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .pillar-grid .pg-cell .hanja { font-size: 1.2rem; }
            .report-meta-grid { grid-template-columns: 1fr 1fr; }
            .jjg-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<!-- ========== Sidebar (인쇄 시 숨김) ========== -->
<aside class="sidebar no-print">
    <div class="sidebar-brand">
        <h2>
            <span class="brand-icon">☯</span>
            <span><?= SITE_NAME ?></span>
        </h2>
        <small>Admin Console</small>
    </div>
    <nav>
        <ul class="sidebar-nav">
            <li><a href="<?= SITE_URL ?>/admin/index.php"><i class="fas fa-chart-pie"></i><span>대시보드</span></a></li>
            <li><a href="<?= SITE_URL ?>/admin/users.php"><i class="fas fa-users"></i><span>회원 관리</span></a></li>
            <li><a href="<?= SITE_URL ?>/admin/history.php"><i class="fas fa-scroll"></i><span>분석 기록</span></a></li>
            <li><a href="<?= SITE_URL ?>/admin/tickets.php"><i class="fas fa-ticket-alt"></i><span>티켓 관리</span></a></li>
            <li><a href="<?= SITE_URL ?>/admin/report.php" class="active"><i class="fas fa-file-alt"></i><span>보고서 생성</span></a></li>
        </ul>
        <div class="sidebar-divider"></div>
    </nav>
    <div class="sidebar-footer">
        <a href="<?= SITE_URL ?>/pages/home.php"><i class="fas fa-arrow-left"></i> 사이트로 돌아가기</a>
    </div>
</aside>

<!-- ========== Main ========== -->
<div class="main">
    <div class="topbar no-print">
        <h1><i class="fas fa-file-alt"></i> 보고서 생성</h1>
        <div class="topbar-right">
            <span style="font-size:0.82rem;color:var(--text-muted);"><?= h(getCurrentUser()['nickname']) ?></span>
            <div class="topbar-avatar"><?= mb_substr(getCurrentUser()['nickname'], 0, 1) ?></div>
        </div>
    </div>

    <div class="container">

        <!-- ====== 입력 폼 ====== -->
        <div class="form-card no-print">
            <div class="form-card-title">
                <i class="fas fa-user-edit"></i>
                생년월일시 입력
            </div>

            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $e): ?>
                    <div class="error-toast"><i class="fas fa-exclamation-circle"></i> <?= h($e) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= h(generateCSRFToken()) ?>">
                <div class="form-grid">
                    <div class="fg">
                        <label>의뢰인 이름</label>
                        <input type="text" name="client_name" placeholder="홍길동" value="<?= h($_POST['client_name'] ?? '') ?>">
                    </div>
                    <div class="fg">
                        <label>출생 연도 *</label>
                        <input type="number" name="birth_year" min="1900" max="<?= date('Y') ?>" placeholder="1990" value="<?= h($_POST['birth_year'] ?? '') ?>" required>
                    </div>
                    <div class="fg">
                        <label>출생 월 *</label>
                        <select name="birth_month" required>
                            <option value="">선택</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= (($_POST['birth_month'] ?? '') == $m) ? 'selected' : '' ?>><?= $m ?>월</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label>출생 일 *</label>
                        <input type="number" name="birth_day" min="1" max="31" placeholder="15" value="<?= h($_POST['birth_day'] ?? '') ?>" required>
                    </div>
                    <div class="fg">
                        <label>출생 시 *</label>
                        <select name="birth_hour" required>
                            <option value="">선택</option>
                            <?php
                            $times = ['자시 23:30~','','축시 01:30~','','인시 03:30~','','묘시 05:30~','','진시 07:30~','','사시 09:30~','','오시 11:30~','','미시 13:30~','','신시 15:30~','','유시 17:30~','','술시 19:30~','','해시 21:30~',''];
                            for ($h = 0; $h <= 23; $h++):
                            ?>
                            <option value="<?= $h ?>" <?= (($_POST['birth_hour'] ?? '') === (string)$h) ? 'selected' : '' ?>><?= $h ?>시<?= $times[$h] ? " ({$times[$h]})" : '' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label>성별 *</label>
                        <select name="gender" required>
                            <option value="">선택</option>
                            <option value="male" <?= (($_POST['gender'] ?? '') === 'male') ? 'selected' : '' ?>>남성</option>
                            <option value="female" <?= (($_POST['gender'] ?? '') === 'female') ? 'selected' : '' ?>>여성</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>달력 유형</label>
                        <select name="calendar_type">
                            <option value="solar" <?= (($_POST['calendar_type'] ?? 'solar') === 'solar') ? 'selected' : '' ?>>양력</option>
                            <option value="lunar" <?= (($_POST['calendar_type'] ?? '') === 'lunar') ? 'selected' : '' ?>>음력</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>수신 이메일</label>
                        <input type="email" name="recipient_email" placeholder="report@example.com" value="<?= h($_POST['recipient_email'] ?? '') ?>">
                    </div>
                </div>
                <div style="text-align:center;margin-top:20px;display:flex;justify-content:center;gap:10px;flex-wrap:wrap;">
                    <button type="submit" name="action" value="generate" class="btn-generate"><i class="fas fa-scroll"></i> 보고서 생성</button>
                    <button type="submit" name="action" value="send_mail" class="btn-print" style="background:#F7F1FF;border-color:#D8CBFF;color:#4A3D8F;"><i class="fas fa-paper-plane"></i> 링크 생성 후 메일 발송</button>
                </div>
                <div style="margin-top:12px;text-align:center;font-size:0.8rem;color:var(--text-muted);">메일 발송은 서버의 <strong>mail()</strong> 설정이 되어 있어야 실제 전송됩니다.</div>
            </form>
        </div>

        <?php if ($result): ?>
        <?php
            $saju = $result['saju'];
            $ohang = $result['ohang'];
            $sipsin = $result['sipsin'];
            $gyeokguk = $result['gyeokguk'];
            $daeun = $result['daeun'];
            $seun = $result['seun'];
            $fortune = $result['fortune'];
            $pillars = [
                'year' => $saju['year_pillar'],
                'month' => $saju['month_pillar'],
                'day' => $saju['day_pillar'],
                'hour' => $saju['hour_pillar'],
            ];
            $genderStr = $saju['input']['gender'] === 'male' ? '남' : '여';
            $dms = $saju['day_master_strength'];
            $ohangColors = ['목'=>'#1B5E20','화'=>'#B71C1C','토'=>'#E65100','금'=>'#4A148C','수'=>'#0D47A1'];
            $sectionColors = ['#4A3D8F','#2D7D46','#2471A3','#C0392B','#C8A45A','#7C6EC4','#8E6A3A','#2980B9','#27AE60','#8E44AD','#D35400'];

            $normGod = function($g) {
                if (is_array($g) && isset($g['element'])) return $g;
                if (is_array($g) && isset($g['primary'])) return ['element'=>$g['primary'],'type'=>''];
                return ['element'=>(string)$g,'type'=>''];
            };

            // 오행 한글→설명 맵
            $ohangDesc = [
                '목' => '나무의 기운 — 성장·창의성',
                '화' => '불의 기운 — 열정·표현력',
                '토' => '흙의 기운 — 안정·중재력',
                '금' => '쇠의 기운 — 결단·정의감',
                '수' => '물의 기운 — 지혜·유연성',
            ];
            $userStories = admin_report_build_user_stories($result);
            $reportTitle = $result['client_name'] . '님의 사주 종합 보고서';
        ?>

        <!-- 액션 바 -->
        <div style="display:flex;justify-content:flex-end;gap:10px;margin:20px 0;flex-wrap:wrap;" class="no-print">
            <button type="button" class="btn-print" id="toggleExpertReportButton"><i class="fas fa-layer-group"></i> 전문가용 상세 보기</button>
            <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> 인쇄 / PDF 저장</button>
        </div>
        <div class="print-summary-guide no-print">
            인쇄하거나 PDF로 저장할 때는 전문 용어가 많은 상세 분석은 제외하고, 사용자 페이지처럼 이해하기 쉬운 해설 중심으로 출력됩니다.
        </div>

        <?php ob_start(); ?>
        <div class="report" id="reportArea">

            <!-- ===== 표지 ===== -->
            <div class="report-hero">
                <div class="report-hero-inner">
                    <div class="title-line">
                        <div class="title-icon">📜</div>
                        <div>
                            <h2>사주팔자 종합 분석 보고서</h2>
                            <div class="subtitle"><?= SITE_NAME ?> · <?= date('Y년 n월 j일') ?> 생성</div>
                        </div>
                    </div>
                    <div style="margin-top:16px;font-size:0.9rem;color:rgba(255,255,255,0.65);line-height:1.8;">
                        이 보고서는 동양 전통 명리학을 바탕으로 태어난 날과 시간의 에너지를 분석합니다.<br>
                        각 섹션을 통해 당신만의 타고난 성향, 재능, 그리고 인생의 흐름을 알아보세요.
                    </div>
                    <div class="report-meta-grid">
                        <div class="report-meta-item">
                            <div class="meta-label">의뢰인</div>
                            <div class="meta-value"><?= h($result['client_name']) ?></div>
                        </div>
                        <div class="report-meta-item">
                            <div class="meta-label">생년월일시</div>
                            <div class="meta-value"><?= $saju['input']['year'] ?>.<?= $saju['input']['month'] ?>.<?= $saju['input']['day'] ?> <?= $saju['input']['hour'] ?>시</div>
                        </div>
                        <div class="report-meta-item">
                            <div class="meta-label">성별</div>
                            <div class="meta-value"><?= $genderStr ?>성</div>
                        </div>
                        <div class="report-meta-item">
                            <div class="meta-label">달력</div>
                            <div class="meta-value"><?= $saju['input']['calendar_type'] === 'solar' ? '양력' : '음력' ?></div>
                        </div>
                        <div class="report-meta-item">
                            <div class="meta-label">일간 (나를 대표하는 에너지)</div>
                            <div class="meta-value" style="font-size:1.15rem;"><?= $saju['day_master'] ?> (<?= $saju['day_master_element'] ?> — <?= $ohangDesc[$saju['day_master_element']] ?? '' ?>)</div>
                        </div>
                        <div class="report-meta-item">
                            <div class="meta-label">띠</div>
                            <div class="meta-value"><?= $saju['zodiac'] ?? '' ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="report-body">

                <!-- ====================================================
                     1. 사주명식 — 에너지 설계도
                     ==================================================== -->
                <div class="rpt-section expert-detail-section">
                    <div class="rpt-section-head">
                        <div class="rpt-section-num" style="background:<?= $sectionColors[0] ?>;">1</div>
                        <div>
                            <div class="rpt-section-title">사주명식 — 나의 에너지 설계도</div>
                            <div class="rpt-section-subtitle">
                                태어난 순간, 하늘(천간)과 땅(지지)의 에너지가 8글자로 기록됩니다.
                                이 8글자가 바로 당신만의 타고난 에너지 배치도입니다.
                            </div>
                        </div>
                    </div>
                    <div class="rpt-desc">
                        💡 <strong>쉽게 이해하기:</strong> 사주명식은 마치 DNA처럼, 태어난 순간의 자연 에너지가 당신에게 부여한 고유한 설계도입니다.
                        위쪽 줄(천간)은 겉으로 드러나는 성격, 아래쪽 줄(지지)은 내면에 숨겨진 잠재력을 나타냅니다.
                    </div>
                    <div class="pillar-grid">
                        <div class="pg-head"></div>
                        <div class="pg-head">시주 · 말년</div>
                        <div class="pg-head">일주 · 나 자신</div>
                        <div class="pg-head">월주 · 사회활동</div>
                        <div class="pg-head">년주 · 조상·환경</div>
                        <div class="pg-label">천간<br><small style="font-size:0.65rem;color:var(--text-muted);">겉</small></div>
                        <?php foreach (['hour','day','month','year'] as $p): ?>
                        <div class="pg-cell">
                            <div class="hanja tc-<?= $pillars[$p]['stem_element'] ?>"><?= $pillars[$p]['stem_hanja'] ?></div>
                            <div class="Korean"><?= $pillars[$p]['stem'] ?></div>
                            <span class="element-tag el-<?= $pillars[$p]['stem_element'] ?>"><?= $pillars[$p]['stem_element'] ?></span>
                        </div>
                        <?php endforeach; ?>
                        <div class="pg-label">지지<br><small style="font-size:0.65rem;color:var(--text-muted);">속</small></div>
                        <?php foreach (['hour','day','month','year'] as $p): ?>
                        <div class="pg-cell">
                            <div class="hanja tc-<?= $pillars[$p]['branch_element'] ?>"><?= $pillars[$p]['branch_hanja'] ?></div>
                            <div class="Korean"><?= $pillars[$p]['branch'] ?></div>
                            <span class="element-tag el-<?= $pillars[$p]['branch_element'] ?>"><?= $pillars[$p]['branch_element'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ====================================================
                     2. 지장간 — 숨겨진 에너지
                     ==================================================== -->
                <?php if (!empty($saju['jijanggan'])): ?>
                <div class="rpt-section expert-detail-section">
                    <div class="rpt-section-head">
                        <div class="rpt-section-num" style="background:<?= $sectionColors[1] ?>;">2</div>
                        <div>
                            <div class="rpt-section-title">지장간 — 숨겨진 내면의 에너지</div>
                            <div class="rpt-section-subtitle">
                                지지(아래쪽 글자) 안에는 눈에 보이지 않는 에너지가 숨어 있습니다.
                                이것이 당신의 잠재된 재능과 내면의 힘입니다.
                            </div>
                        </div>
                    </div>
                    <div class="jjg-grid">
                    <?php
                    $pillarNames = ['year'=>'년주','month'=>'월주','day'=>'일주','hour'=>'시주'];
                    foreach (['hour','day','month','year'] as $p):
                        $jjg = $saju['jijanggan'][$p] ?? [];
                    ?>
                        <div class="jjg-item">
                            <div class="jjg-label"><?= $pillarNames[$p] ?> · <?= $pillars[$p]['branch'] ?>(<?= $pillars[$p]['branch_hanja'] ?>)</div>
                            <?php foreach ($jjg as $item): ?>
                            <div style="margin:4px 0;">
                                <span class="element-tag el-<?= $item['element'] ?>"><?= $item['element'] ?></span>
                                <span style="font-size:0.85rem;font-weight:600;"><?= $item['gan'] ?? '' ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ====================================================
                     3. 에너지 체질 — 신강/신약 + 용신
                     ==================================================== -->
                <div class="rpt-section page-start">
                    <div class="rpt-section-head">
                        <div class="rpt-section-num" style="background:<?= $sectionColors[2] ?>;">3</div>
                        <div>
                            <div class="rpt-section-title">나의 에너지 체질 분석</div>
                            <div class="rpt-section-subtitle">
                                당신의 타고난 에너지가 강한 편인지, 부드러운 편인지 파악합니다.
                                그리고 삶에서 가장 필요한 에너지(용신)와 피해야 할 에너지(기신)를 알려드립니다.
                            </div>
                        </div>
                    </div>
                    <?php
                    $strengthLabel = $dms['is_strong'] ? '에너지가 강한 타입 (신강)' : '에너지가 부드러운 타입 (신약)';
                    $strengthEmoji = $dms['is_strong'] ? '💪' : '🍃';
                    $strengthDesc = $dms['is_strong']
                        ? '당신은 타고난 에너지가 넘치는 사람입니다. 추진력과 자기 주장이 강하고, 스스로 길을 개척하는 힘이 있습니다.'
                        : '당신은 섬세하고 유연한 에너지를 가진 사람입니다. 주변과 조화를 이루며, 협력을 통해 큰 성과를 낼 수 있습니다.';
                    $yong = $normGod($dms['yongshin'] ?? []);
                    $hee = $normGod($dms['heeshin'] ?? []);
                    $gi = $normGod($dms['gishin'] ?? []);
                    $gu = $normGod($dms['gushin'] ?? []);
                    ?>
                    <div style="text-align:center;margin-bottom:24px;padding:20px;background:var(--warm-bg);border-radius:12px;">
                        <span style="font-size:2.2rem;"><?= $strengthEmoji ?></span>
                        <div style="font-size:1.2rem;font-weight:900;margin-top:6px;color:var(--text-primary);"><?= $strengthLabel ?></div>
                        <div style="font-size:0.9rem;color:var(--text-secondary);margin-top:8px;max-width:500px;margin-left:auto;margin-right:auto;line-height:1.7;">
                            <?= $strengthDesc ?>
                        </div>
                    </div>

                    <div class="rpt-desc">
                        💡 <strong>꼭 기억하세요:</strong> 아래 네 가지 에너지 중 <strong style="color:var(--success);">용신</strong>은 당신에게 가장 도움이 되는 에너지이고,
                        <strong style="color:var(--danger);">기신</strong>은 주의해야 할 에너지입니다.
                        용신의 색상·방향·계절을 생활 속에서 가까이하면 좋습니다.
                    </div>

                    <div class="god-grid">
                        <?php
                        $gods = [
                            ['data'=>$yong,'label'=>'용신','desc'=>'가장 필요한 에너지','color'=>'#2D7D46','bg'=>'#F0F7F1','border'=>'#2D7D46'],
                            ['data'=>$hee,'label'=>'희신','desc'=>'용신을 도와주는 에너지','color'=>'#2471A3','bg'=>'#EEF4F9','border'=>'#2471A3'],
                            ['data'=>$gi,'label'=>'기신','desc'=>'주의해야 할 에너지','color'=>'#C0392B','bg'=>'#FDF2F0','border'=>'#C0392B'],
                            ['data'=>$gu,'label'=>'구신','desc'=>'기신을 돕는 에너지','color'=>'#8E44AD','bg'=>'#F6F0FA','border'=>'#8E44AD'],
                        ];
                        foreach ($gods as $g): ?>
                        <div class="god-card" style="background:<?= $g['bg'] ?>;border-color:<?= $g['border'] ?>;">
                            <div style="position:absolute;top:0;left:0;right:0;height:4px;background:<?= $g['color'] ?>;"></div>
                            <div class="god-label" style="color:<?= $g['color'] ?>;"><?= $g['label'] ?></div>
                            <div class="god-desc"><?= $g['desc'] ?></div>
                            <div class="god-element" style="color:<?= $g['color'] ?>;"><?= $g['data']['element'] ?></div>
                            <div class="god-type"><?= $ohangDesc[$g['data']['element']] ?? '' ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($dms['four_gods_explanation'])): ?>
                    <div class="info-block" style="margin-top:18px;">
                        <?= nl2br(h($dms['four_gods_explanation'])) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ====================================================
                     4. 에너지 관계도 — 합충형파해
                     ==================================================== -->
                <?php if (!empty($saju['relationships'])): ?>
                <div class="rpt-section expert-detail-section">
                    <div class="rpt-section-head">
                        <div class="rpt-section-num" style="background:<?= $sectionColors[3] ?>;">4</div>
                        <div>
                            <div class="rpt-section-title">관계와 흐름에서 살펴볼 변화 포인트</div>
                            <div class="rpt-section-subtitle">
                                어려운 한자 용어 대신, 실제 생활에서 어떻게 느껴질 수 있는지를 중심으로 정리한 참고용 설명입니다.
                            </div>
                        </div>
                    </div>
                    <?php
                    $displayRelationships = admin_report_unique_relationships($saju['relationships']);
                    $relStyles = [
                        '육합'=>['bg'=>'#F0F7F1','color'=>'#1B5E20','label'=>'조화'],
                        '삼합'=>['bg'=>'#E8F5E9','color'=>'#1B5E20','label'=>'큰 조화'],
                        '방합'=>['bg'=>'#E8F5E9','color'=>'#1B5E20','label'=>'방향 조화'],
                        '충'=>['bg'=>'#FDF2F0','color'=>'#B71C1C','label'=>'충돌'],
                        '형'=>['bg'=>'#FFF3E0','color'=>'#E65100','label'=>'마찰'],
                        '해'=>['bg'=>'#FCE4EC','color'=>'#AD1457','label'=>'방해'],
                        '파'=>['bg'=>'#FFF8E1','color'=>'#E65100','label'=>'깨짐'],
                        '천간합'=>['bg'=>'#EEF4F9','color'=>'#0D47A1','label'=>'하늘의 조화'],
                        '천간충'=>['bg'=>'#F6F0FA','color'=>'#4A148C','label'=>'하늘의 충돌'],
                    ];
                    foreach ($displayRelationships as $rel):
                        $rs = $relStyles[$rel['type']] ?? ['bg'=>'#F5F5F5','color'=>'#333','label'=>''];
                    ?>
                    <div style="padding:14px 0;border-bottom:1px solid var(--border-light);">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                            <span class="rel-tag" style="background:<?= $rs['bg'] ?>;color:<?= $rs['color'] ?>;"><?= h(admin_report_plain_relation_label($rel['type'] ?? '')) ?></span>
                        </div>
                        <div style="font-size:0.9rem;color:var(--text-body);line-height:1.8;padding-left:4px;"><?= nl2br(h(admin_report_plain_relation_text($rel))) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- ====================================================
                     5. 오행 에너지 분석
                     ==================================================== -->
                <div class="rpt-section page-start expert-detail-section">
                    <div class="rpt-section-head">
                        <div class="rpt-section-num" style="background:<?= $sectionColors[4] ?>;">5</div>
                        <div>
                            <div class="rpt-section-title">오행 에너지 — 다섯 가지 자연의 힘</div>
                            <div class="rpt-section-subtitle">
                                자연의 다섯 가지 에너지(나무·불·흙·쇠·물)가 당신에게 얼마나 분포되어 있는지 보여줍니다.
                                균형 잡힌 에너지는 건강하고 원활한 삶을, 편향된 에너지는 특별한 재능이나 주의가 필요한 영역을 나타냅니다.
                            </div>
                        </div>
                    </div>
                    <?php
                    $weighted = $ohang['weighted_ohang_count'] ?? $ohang['ohang_count'] ?? [];
                    $maxEl = max(array_values($weighted) ?: [1]);
                    foreach (['목','화','토','금','수'] as $el):
                        $val = $weighted[$el] ?? 0;
                        $pct = $maxEl > 0 ? round($val / $maxEl * 100) : 0;
                    ?>
                    <div class="bar-row">
                        <div class="bar-label"><span class="element-tag el-<?= $el ?>" style="font-size:0.82rem;"><?= $el ?></span></div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $ohangColors[$el] ?>;"><?= number_format($val, 1) ?></div>
                        </div>
                        <div class="bar-value"><?= $pct ?>%</div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (!empty($ohang['balance'])): ?>
                    <div class="info-block" style="margin-top:18px;">
                        <strong>⚖️ 오행 균형 평가</strong><br>
                        <?= nl2br(h($ohang['balance']['description'] ?? '')) ?>
                        <?php if (!empty($ohang['balance']['missing'])): ?><br><br>🔸 <strong>부족한 에너지:</strong> <?= implode(', ', array_map(function($e) use ($ohangDesc) { return $e . ' (' . ($ohangDesc[$e] ?? '') . ')'; }, $ohang['balance']['missing'])) ?><?php endif; ?>
                        <?php if (!empty($ohang['balance']['balance_level'])): ?><br>🔹 <strong>균형 점수:</strong> <?= h($ohang['balance']['balance_level']) ?> (<?= $ohang['balance']['balance_score'] ?? '' ?>점)<?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($ohang['supplement']) && is_array($ohang['supplement'])): ?>
                    <div style="margin-top:20px;">
                        <div style="font-weight:700;font-size:0.95rem;margin-bottom:12px;">💡 생활 속 에너지 보완 가이드</div>
                        <?php foreach ($ohang['supplement'] as $sup): ?>
                        <div class="sup-card">
                            <div class="sup-head">
                                <span class="element-tag el-<?= h($sup['element'] ?? '') ?>" style="font-size:0.82rem;"><?= h($sup['element'] ?? '') ?></span>
                                <?= h($sup['type'] ?? '') ?> — 방위: <?= h($sup['direction'] ?? '') ?>, 계절: <?= h($sup['season'] ?? '') ?>
                            </div>
                            <?php if (!empty($sup['methods'])): ?>
                            <ul>
                                <?php foreach ($sup['methods'] as $m): ?><li><?= h($m) ?></li><?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ====================================================
                     6. 십성 — 나와 세상의 관계
                     ==================================================== -->
                <div class="rpt-section page-start expert-detail-section">
                    <div class="rpt-section-head">
                        <div class="rpt-section-num" style="background:<?= $sectionColors[5] ?>;">6</div>
                        <div>
                            <div class="rpt-section-title">십성 분석 — 나와 세상이 맺는 관계의 방식</div>
                            <div class="rpt-section-subtitle">
                                나(일간)를 중심으로 다른 에너지들이 어떤 역할을 하는지 분석합니다.
                                이를 통해 재능, 직업 적성, 대인관계, 재물운의 패턴을 알 수 있습니다.
                            </div>
                        </div>
                    </div>
                    <?php
                    $sipsinDist = $sipsin['distribution'] ?? [];
                    arsort($sipsinDist);
                    $maxSipsin = max(array_values($sipsinDist) ?: [1]);
                    ?>

                    <div style="margin-bottom:24px;">
                        <div style="font-weight:700;font-size:0.92rem;margin-bottom:12px;">📊 십성 에너지 분포</div>
                        <?php foreach ($sipsinDist as $sName => $sVal):
                            if ($sVal <= 0) continue;
                            $sPct = round(($sVal / $maxSipsin) * 100);
                        ?>
                        <div class="bar-row">
                            <div class="bar-label" style="min-width:55px;font-size:0.85rem;font-weight:600;"><?= $sName ?></div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width:<?= $sPct ?>%;background:var(--accent);"><?= number_format($sVal, 1) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($sipsin['group_totals'])): ?>
                    <div class="rpt-desc" style="border-left-color:var(--accent);">
                        💡 <strong>5가지 대분류 설명:</strong>
                        <strong>비겁</strong>은 자기 자신의 힘,
                        <strong>식상</strong>은 표현력·창의성,
                        <strong>재성</strong>은 재물·현실 능력,
                        <strong>관성</strong>은 사회적 지위·책임,
                        <strong>인성</strong>은 학문·지혜를 뜻합니다.
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:24px;">
                        <?php
                        $gColors = ['비겁(比劫)'=>'#4A3D8F','식상(食傷)'=>'#C8A45A','재성(財星)'=>'#2D7D46','관성(官星)'=>'#2471A3','인성(印星)'=>'#C0392B'];
                        $gDescs = ['비겁(比劫)'=>'나의 힘','식상(食傷)'=>'표현·창의','재성(財星)'=>'재물능력','관성(官星)'=>'지위·책임','인성(印星)'=>'학문·지혜'];
                        foreach ($sipsin['group_totals'] as $gn => $gv): $gc = $gColors[$gn] ?? '#666'; ?>
                        <div style="text-align:center;padding:14px 8px;background:var(--warm-bg);border-radius:10px;border-top:4px solid <?= $gc ?>;">
                            <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;"><?= $gDescs[$gn] ?? $gn ?></div>
                            <div style="font-size:1.2rem;font-weight:900;color:<?= $gc ?>;margin-top:6px;"><?= number_format($gv, 1) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($sipsin['interpretations'])): ?>
                    <div style="font-weight:700;font-size:0.95rem;margin-bottom:14px;">📖 당신의 사주에서 발견된 특별한 패턴</div>
                    <?php
                    $levelColorMap = ['길'=>'#2D7D46','대길'=>'#2D7D46','주의'=>'#C0392B','경고'=>'#B71C1C','갈등'=>'#4A3D8F','양면'=>'#2471A3','참고'=>'#555555','종합'=>'#1A1A1A'];
                    foreach ($sipsin['interpretations'] as $rule): ?>
                    <div class="sipsin-card" style="border-left-color:<?= $levelColorMap[$rule['level']] ?? '#555' ?>;">
                        <div class="sc-head">
                            <span class="sc-level" style="background:<?= $levelColorMap[$rule['level']] ?? '#555' ?>;"><?= $rule['level'] ?></span>
                            <span style="font-size:0.75rem;color:var(--text-muted);"><?= $rule['category'] ?></span>
                        </div>
                        <div class="sc-title"><?= h($rule['title']) ?></div>
                        <div class="sc-text"><?= nl2br(h($rule['text'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- ====================================================
                     7. 격국 — 인생의 핵심 테마
                     ==================================================== -->
                <div class="rpt-section page-start expert-detail-section">
                    <div class="rpt-section-head">
                        <div class="rpt-section-num" style="background:<?= $sectionColors[6] ?>;">7</div>
                        <div>
                            <div class="rpt-section-title">인생의 핵심 테마</div>
                            <div class="rpt-section-subtitle">
                                격국(格局)은 당신의 사주를 관통하는 하나의 큰 주제입니다.
                                마치 영화의 장르처럼, 당신의 인생 스토리가 어떤 방향으로 흘러가는지를 보여줍니다.
                            </div>
                        </div>
                    </div>
                    <div style="text-align:center;padding:28px 0;">
                        <div style="font-family:'Noto Serif KR',serif;font-size:1.8rem;font-weight:900;color:var(--accent);"><?= h($gyeokguk['name']) ?></div>
                        <div style="font-size:0.95rem;color:var(--text-secondary);margin-top:8px;"><?= h($gyeokguk['description']) ?></div>
                        <?php if (!empty($gyeokguk['quality'])): ?>
                        <span class="score-pill sp-high" style="margin-top:12px;display:inline-block;font-size:0.85rem;"><?= h($gyeokguk['quality']['level']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="info-block" style="border-left:4px solid var(--accent);font-size:0.93rem;">
                        <?= nl2br(h($gyeokguk['detail'])) ?>
                    </div>
                    <?php if (!empty($gyeokguk['quality']['description'])): ?>
                    <div style="margin-top:14px;font-size:0.9rem;color:var(--text-secondary);padding:14px 18px;background:var(--warm-bg);border-radius:10px;">
                        <strong>📋 격국 품질 평가:</strong> <?= h($gyeokguk['quality']['description']) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ====================================================
                     8. 대운 — 10년 주기 인생 흐름
                     ==================================================== -->
                <div class="rpt-section page-start">
                    <div class="rpt-section-head">
                        <div class="rpt-section-num" style="background:<?= $sectionColors[7] ?>;">8</div>
                        <div>
                            <div class="rpt-section-title">10년 주기 인생 흐름 — 삶의 계절</div>
                            <div class="rpt-section-subtitle">
                                대운(大運)은 10년마다 바뀌는 큰 흐름입니다. 마치 봄·여름·가을·겨울처럼 인생에도 계절이 있습니다.
                                각 시기마다 어떤 에너지가 찾아오는지, 그것이 당신에게 어떤 의미인지 알아봅니다.
                                <br>방향: <?= $daeun['direction'] ?> · 시작 나이: <?= $daeun['start_age'] ?>세
                            </div>
                        </div>
                    </div>

                    <!-- 타임라인 한눈에 보기 -->
                    <div class="rpt-desc">
                        💡 <strong>보는 방법:</strong> 점수가 높을수록(초록색) 순풍을 만나는 시기, 낮을수록(빨간색) 역풍을 이겨내야 하는 시기입니다.
                        ⭐ 표시는 당신에게 가장 필요한 에너지(용신)가 들어오는 특별히 좋은 시기를 뜻합니다.
                    </div>
                    <div class="daeun-flow">
                    <?php foreach ($daeun['daeuns'] as $d):
                        $sc = $d['score'];
                        $scBg = $sc >= 70 ? '#2D7D46' : ($sc >= 50 ? '#2471A3' : ($sc >= 35 ? '#E65100' : '#B71C1C'));
                    ?>
                        <div class="daeun-node<?= $d['is_yongshin'] ? ' yongshin' : '' ?>">
                            <div class="dn-age"><?= $d['age_start'] ?>~<?= $d['age_end'] ?>세</div>
                            <div class="dn-pillar"><?= $d['stem_hanja'] ?><?= $d['branch_hanja'] ?></div>
                            <div style="font-size:0.78rem;color:var(--text-muted);"><?= $d['stem'] ?><?= $d['branch'] ?></div>
                            <div class="dn-sipsin">
                                <span class="element-tag el-<?= $d['stem_element'] ?>" style="font-size:0.68rem;"><?= h(admin_report_short_sipsin_label($d['stem_sipsin'] ?? '')) ?></span>
                                <span class="element-tag el-<?= $d['branch_element'] ?>" style="font-size:0.68rem;"><?= h(admin_report_short_sipsin_label($d['branch_sipsin'] ?? '')) ?></span>
                            </div>
                            <span class="dn-score" style="background:<?= $scBg ?>;"><?= $sc ?>점</span>
                            <?php if ($d['is_yongshin']): ?><div style="font-size:0.7rem;color:#C8A45A;margin-top:4px;font-weight:700;">⭐ 용신</div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>

                    <!-- 대운 상세 — 드롭다운 없이 모두 펼침 -->
                    <?php foreach ($daeun['daeuns'] as $d):
                        $scCls = $d['score'] >= 70 ? 'sp-high' : ($d['score'] >= 50 ? 'sp-mid' : ($d['score'] >= 35 ? 'sp-low' : 'sp-danger'));
                    ?>
                    <div class="daeun-detail-card<?= $d['is_yongshin'] ? ' is-yongshin' : '' ?>">
                        <div class="daeun-detail-header">
                            <span class="ddh-age"><?= $d['age_start'] ?>~<?= $d['age_end'] ?>세</span>
                            <span class="ddh-pillar"><?= $d['stem'] ?><?= $d['branch'] ?> (<?= $d['stem_hanja'] ?><?= $d['branch_hanja'] ?>)</span>
                            <span class="ddh-score"><span class="score-pill <?= $scCls ?>"><?= $d['score'] ?>점</span></span>
                            <?php if ($d['is_yongshin']): ?><span style="color:var(--gold);font-size:0.85rem;font-weight:700;">⭐ 용신 대운</span><?php endif; ?>
                        </div>
                        <div class="daeun-detail-body"><?= nl2br(h(admin_report_build_daeun_summary($d))) ?></div>
                    </div>
                    <?php endforeach; ?>

                    <!-- 원국 패턴 -->
                    <?php if (!empty($daeun['wonguk_patterns'])): ?>
                    <div style="margin-top:20px;padding:20px 24px;background:var(--accent-soft);border-radius:12px;border:1px solid #D5D0EA;">
                        <div style="font-weight:700;font-size:0.95rem;margin-bottom:12px;color:var(--accent);">🔮 당신의 사주에서 발견된 핵심 패턴</div>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        <?php foreach ($daeun['wonguk_patterns'] as $pat): ?>
                            <span class="wonguk-pattern-tag"><?= h($pat) ?></span>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 30년 대운 흐름 -->
                    <?php if (!empty($daeun['thirty_year_flow'])): ?>
                    <?php $flow = $daeun['thirty_year_flow']; ?>
                    <div class="thirty-year-block">
                        <h4>📊 30년 대운 흐름 종합 분석</h4>
                        <?php if (!empty($flow['period_label'])): ?>
                        <p><strong>분석 기간:</strong> <?= h($flow['period_label']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($flow['overall_trend'])): ?>
                        <p><strong>전체 흐름:</strong> <?= h($flow['overall_trend']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($flow['narrative'])): ?>
                        <div style="margin-top:10px;white-space:pre-line;font-size:0.9rem;line-height:1.85;color:var(--text-body);">
                            <?= h($flow['narrative']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ====================================================
                     9. 세운 — 다가오는 5년의 운세
                     ==================================================== -->
                <div class="rpt-section page-start expert-detail-section">
                    <div class="rpt-section-head">
                        <div class="rpt-section-num" style="background:<?= $sectionColors[8] ?>;">9</div>
                        <div>
                            <div class="rpt-section-title">다가오는 5년의 운세</div>
                            <div class="rpt-section-subtitle">
                                세운(歲運)은 해마다 바뀌는 에너지입니다. 올해부터 5년간 어떤 기운이 찾아오는지 살펴보세요.
                                매년 알맞은 전략을 세우면 좋은 해는 더 좋게, 어려운 해는 무난하게 보낼 수 있습니다.
                            </div>
                        </div>
                    </div>
                    <?php foreach ($seun as $s): ?>
                    <div class="seun-card">
                        <div class="seun-year">
                            <?= $s['year'] ?>
                            <small><?= $s['stem'] ?><?= $s['branch'] ?></small>
                        </div>
                        <div class="seun-body">
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                                <span class="element-tag el-<?= $s['stem_element'] ?>"><?= $s['stem_sipsin'] ?></span>
                                <span class="element-tag el-<?= $s['branch_element'] ?>"><?= $s['branch_sipsin'] ?></span>
                                <span style="font-size:0.78rem;color:var(--text-muted);"><?= $s['zodiac'] ?>띠</span>
                                <?php if ($s['is_yongshin']): ?><span style="font-size:0.78rem;color:var(--gold);font-weight:700;">⭐ 용신</span><?php endif; ?>
                            </div>
                            <div style="font-size:0.9rem;line-height:1.85;white-space:pre-line;"><?= nl2br(h(admin_report_simplify_interpretation($s['interpretation'] ?? '', 2))) ?></div>
                            <?php if (!empty($s['monthly_highlight'])): ?>
                            <div style="margin-top:8px;font-size:0.82rem;color:var(--text-muted);padding:8px 12px;background:var(--warm-bg);border-radius:8px;">
                                📅 가장 좋은 달: <strong><?= $s['monthly_highlight']['best_month'] ?>월</strong> · 주의할 달: <strong><?= $s['monthly_highlight']['worst_month'] ?>월</strong>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="seun-score" style="color:<?= $s['score'] >= 70 ? '#2D7D46' : ($s['score'] >= 50 ? '#2471A3' : ($s['score'] >= 35 ? '#E65100' : '#B71C1C')) ?>;">
                            <?= $s['score'] ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- ====================================================
                     10. 종합 운세 — 나의 인생 이야기
                     ==================================================== -->
                <div class="rpt-section page-start">
                    <div class="rpt-section-head">
                        <div class="rpt-section-num" style="background:<?= $sectionColors[9] ?>;">10</div>
                        <div>
                            <div class="rpt-section-title">나의 인생 이야기 — 7가지 삶의 영역</div>
                            <div class="rpt-section-subtitle">
                                사주의 모든 요소를 종합하여, 당신의 성격·연애·직업·재물·학업·건강·인생흐름을
                                하나의 이야기로 풀어드립니다. 가장 핵심적인 분석 결과를 담았습니다.
                            </div>
                        </div>
                    </div>
                    <div class="fortune-grid">
                    <?php
                    $easyFortuneSections = admin_report_build_easy_fortune_sections($userStories, $fortune);
                    foreach ($easyFortuneSections as $sec):
                    ?>
                    <div class="fortune-item">
                        <div class="fortune-item-head">
                            <div class="fortune-item-icon" style="background:<?= $sec['color'] ?>;"><i class="fas <?= $sec['icon'] ?>"></i></div>
                            <div class="fortune-item-title"><?= $sec['title'] ?></div>
                        </div>
                        <div class="fortune-item-text">
                            <?php foreach ($sec['paragraphs'] as $paragraph): ?>
                            <p style="margin:0 0 12px;line-height:1.9;"><?= nl2br(h($paragraph)) ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>

                <div class="rpt-section page-start">
                    <div class="rpt-section-head">
                        <div class="rpt-section-num" style="background:<?= $sectionColors[10] ?>;">11</div>
                        <div>
                            <div class="rpt-section-title">일반 사용자용 쉬운 해설 모음</div>
                            <div class="rpt-section-subtitle">
                                신년운세, 토정비결, 정통사주, 대운풀이, 재물운, 건강운, 직업운, 애정운, 오늘의 운세까지
                                앱에서 보는 장문 해설을 한 번에 모아 읽을 수 있도록 정리한 섹션입니다.
                            </div>
                        </div>
                    </div>
                    <div class="rpt-desc">
                        💡 <strong>읽는 방법:</strong> 아래 내용은 일반 사용자가 바로 이해할 수 있도록 전문 용어를 줄이고,
                        지금 생활에 바로 적용할 수 있는 설명 중심으로 다시 정리한 버전입니다.
                    </div>
                    <?php foreach ($userStories as $entry): ?>
                    <?php $story = $entry['story'] ?? []; ?>
                    <div class="fortune-item" style="margin-bottom:18px;">
                        <div class="fortune-item-head">
                            <div class="fortune-item-icon" style="background:<?= h($entry['color']) ?>;"><i class="fas <?= h($entry['icon']) ?>"></i></div>
                            <div>
                                <div class="fortune-item-title"><?= h($entry['title']) ?></div>
                                <?php if (!empty($story['hero'])): ?>
                                <div style="font-size:0.82rem;color:var(--text-muted);margin-top:4px;line-height:1.7;"><?= h($story['hero']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($story['summary_cards'])): ?>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin:14px 0 4px;">
                            <?php foreach (array_slice($story['summary_cards'], 0, 6) as $card): ?>
                            <div style="padding:12px 14px;background:var(--warm-bg);border:1px solid var(--border-light);border-radius:10px;">
                                <div style="font-size:0.72rem;color:var(--text-muted);font-weight:700;"><?= h($card['label'] ?? '') ?></div>
                                <div style="font-size:0.88rem;color:var(--text-primary);font-weight:700;margin-top:4px;line-height:1.6;"><?= h($card['value'] ?? '') ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php foreach (($story['sections'] ?? []) as $storySection): ?>
                        <?php
                            $paragraphs = [];
                            foreach (($storySection['paragraphs'] ?? []) as $paragraph) {
                                if (trim((string)$paragraph) !== '') {
                                    $paragraphs[] = $paragraph;
                                }
                            }
                            if (empty($paragraphs)) {
                                continue;
                            }
                        ?>
                        <div style="margin-top:16px;">
                            <div style="font-size:0.95rem;font-weight:800;color:var(--text-primary);"><?= h($storySection['title'] ?? '') ?></div>
                            <?php foreach ($paragraphs as $paragraph): ?>
                            <p style="margin-top:8px;font-size:0.9rem;line-height:1.9;color:var(--text-body);"><?= nl2br(h($paragraph)) ?></p>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div><!-- /report-body -->

            <div class="report-footer">
                <p style="font-size:0.88rem;color:var(--text-secondary);font-weight:500;"><?= SITE_NAME ?> · 사주팔자 종합 분석 보고서</p>
                <p style="margin-top:4px;"><?= date('Y년 n월 j일 H:i') ?> 생성</p>
                <p style="margin-top:10px;font-size:0.82rem;color:var(--text-muted);line-height:1.7;">
                    본 보고서는 동양 전통 명리학을 기반으로 태어난 날과 시간의 에너지를 분석한 것입니다.<br>
                    사주는 '정해진 운명'이 아니라 '타고난 에너지의 지도'입니다.<br>
                    이 지도를 참고로 활용하여, 더 현명한 선택을 하시길 바랍니다.
                </p>
            </div>
        </div><!-- /report -->

        <?php
            $reportHtml = ob_get_clean();

            if ($action === 'send_mail') {
                if (!empty($shareRequest['error'])) {
                    $shareFeedback = [
                        'type' => 'error',
                        'message' => $shareRequest['error'],
                    ];
                } else {
                    try {
                        $share = createReportShare([
                            'client_name' => $result['client_name'],
                            'recipient_email' => $shareRequest['recipient_email'],
                            'report_title' => $reportTitle,
                            'report_html' => $reportHtml,
                            'created_by' => (int)(getCurrentUser()['id'] ?? 0),
                        ]);

                        $mailSent = sendSharedReportMail($shareRequest['recipient_email'], $result['client_name'], $share['url'], $reportTitle);
                        if ($mailSent) {
                            markReportShareSent($share['token']);
                        }

                        $shareFeedback = [
                            'type' => $mailSent ? 'success' : 'warning',
                            'message' => $mailSent
                                ? '공유 링크를 생성하고 메일 발송까지 완료했습니다.'
                                : '공유 링크는 생성했지만 메일 발송은 실패했습니다. mail() 또는 sendmail 설정을 확인해 주세요.',
                            'url' => $share['url'],
                            'recipient_email' => $shareRequest['recipient_email'],
                            'expires_at' => $share['expires_at'],
                        ];
                    } catch (Exception $e) {
                        $shareFeedback = [
                            'type' => 'error',
                            'message' => '공유 링크 생성 중 오류가 발생했습니다: ' . $e->getMessage(),
                        ];
                    }
                }
            }

            echo $reportHtml;
        ?>

        <?php if (!empty($shareFeedback)): ?>
        <?php
            $shareToneMap = [
                'success' => ['bg' => '#F0F7F1', 'border' => '#CFE7D6', 'text' => '#2D7D46'],
                'warning' => ['bg' => '#FFF8E1', 'border' => '#F5DE9C', 'text' => '#A66A00'],
                'error' => ['bg' => '#FDF2F0', 'border' => '#F2C9C3', 'text' => '#B71C1C'],
            ];
            $shareTone = $shareToneMap[$shareFeedback['type']] ?? $shareToneMap['success'];
        ?>
        <div class="form-card no-print" style="margin-top:20px;background:<?= $shareTone['bg'] ?>;border-color:<?= $shareTone['border'] ?>;">
            <div class="form-card-title" style="margin-bottom:12px;color:<?= $shareTone['text'] ?>;">
                <i class="fas fa-link" style="background:<?= $shareTone['text'] ?>;"></i>
                공유 링크 발송 결과
            </div>
            <div style="font-size:0.92rem;color:<?= $shareTone['text'] ?>;line-height:1.8;"><?= h($shareFeedback['message']) ?></div>

            <?php if (!empty($shareFeedback['url'])): ?>
            <div style="margin-top:14px;">
                <div style="font-size:0.78rem;font-weight:700;color:var(--text-secondary);margin-bottom:6px;">공유 링크</div>
                <input type="text" readonly value="<?= h($shareFeedback['url']) ?>" onclick="this.select();" style="width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:#fff;font-size:0.84rem;">
                <div style="font-size:0.8rem;color:var(--text-muted);margin-top:8px;line-height:1.7;">
                    수신자: <?= h($shareFeedback['recipient_email'] ?? '-') ?><br>
                    링크 만료일: <?= h($shareFeedback['expires_at'] ?? '-') ?>
                </div>
                <a href="<?= h($shareFeedback['url']) ?>" target="_blank" rel="noopener" class="btn-print" style="margin-top:12px;background:#fff;">공유 보고서 열기</a>
            </div>
            <?php endif; ?>

            <div style="font-size:0.78rem;color:var(--text-muted);margin-top:12px;line-height:1.7;">서버에서 <strong>mail()</strong> 전송이 실패하더라도 공유 링크는 유지됩니다.</div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div><!-- /container -->
</div><!-- /main -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggleButton = document.getElementById('toggleExpertReportButton');
    if (!toggleButton) {
        return;
    }

    toggleButton.addEventListener('click', function () {
        document.body.classList.toggle('show-expert-report');
        toggleButton.innerHTML = document.body.classList.contains('show-expert-report')
            ? '<i class="fas fa-layer-group"></i> 쉬운 보고서만 보기'
            : '<i class="fas fa-layer-group"></i> 전문가용 상세 보기';
    });
});
</script>

</body>
</html>
