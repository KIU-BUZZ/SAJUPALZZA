<?php
/**
 * 공통 운세 서비스 헬퍼
 */

require_once SAJU_ENGINE_PATH . '/SajuEngine.php';
require_once SAJU_ENGINE_PATH . '/OhangAnalysis.php';
require_once SAJU_ENGINE_PATH . '/FortuneInterpreter.php';

function fs_clamp_score($score) {
    return max(10, min(95, (int)round($score)));
}

function fs_build_engine_from_record(array $record) {
    return new SajuEngine(
        (int)$record['birth_year'],
        (int)$record['birth_month'],
        (int)$record['birth_day'],
        (int)$record['birth_hour'],
        $record['gender'],
        $record['calendar_type']
    );
}

function fs_get_latest_analysis_record($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM saju_fortune_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function fs_decode_json($json) {
    if (!$json) return null;
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : null;
}

function fs_find_year_fortune($seunData, $year) {
    if (empty($seunData) || !is_array($seunData)) return null;

    if (isset($seunData['year']) && (int)$seunData['year'] === (int)$year) {
        return $seunData;
    }

    foreach ($seunData as $item) {
        if (is_array($item) && (int)($item['year'] ?? 0) === (int)$year) {
            return $item;
        }
    }

    return null;
}

function fs_get_year_fortune_for_record(array $record, $year) {
    $year = (int)$year;

    $storedSeun = fs_decode_json($record['seun_analysis'] ?? '');
    $yearFortune = fs_find_year_fortune($storedSeun, $year);
    if ($yearFortune) return $yearFortune;

    $storedPayload = fs_decode_json($record['fortune_result'] ?? '');
    $yearFortune = fs_find_year_fortune($storedPayload['seun'] ?? null, $year);
    if ($yearFortune) return $yearFortune;

    $engine = fs_build_engine_from_record($record);
    $interpreter = new FortuneInterpreter($engine);
    $seuns = $interpreter->analyzeSeun($year);
    return fs_find_year_fortune($seuns, $year) ?: ($seuns[0] ?? null);
}

function fs_get_monthly_fortunes_for_record(array $record, $year) {
    $engine = fs_build_engine_from_record($record);
    $interpreter = new FortuneInterpreter($engine);
    return $interpreter->analyzeMonthlyFortunes((int)$year);
}

function fs_date_score_label($score) {
    if ($score >= 85) return '기세 좋게 밀어붙이기 좋은 날';
    if ($score >= 70) return '기회가 비교적 잘 붙는 날';
    if ($score >= 55) return '무리 없이 안정적으로 가기 좋은 날';
    if ($score >= 40) return '욕심을 줄이고 페이스를 지키는 날';
    return '휴식과 정비를 우선해야 하는 날';
}

function fs_seeded_offset($seed, $range) {
    return (crc32($seed) % ($range * 2 + 1)) - $range;
}

function fs_daily_category_score($baseScore, $userId, $label, DateTimeInterface $date) {
    $seed = sprintf('%s-%s-%s', $userId, $date->format('Y-m-d'), $label);
    return fs_clamp_score($baseScore + fs_seeded_offset($seed, 9));
}

function fs_build_date_fortune(array $record, $userId, DateTimeInterface $date) {
    $year = (int)$date->format('Y');
    $month = (int)$date->format('n');
    $day = (int)$date->format('j');
    $weekday = (int)$date->format('N');

    $yearFortune = fs_get_year_fortune_for_record($record, $year);
    if (!$yearFortune) return null;

    $monthlyFortunes = fs_get_monthly_fortunes_for_record($record, $year);
    $monthFortune = null;
    foreach ($monthlyFortunes as $monthly) {
        if ((int)($monthly['month'] ?? 0) === $month) {
            $monthFortune = $monthly;
            break;
        }
    }

    $baseScore = (int)($yearFortune['score'] ?? $yearFortune['total_score'] ?? 50);
    $monthScore = (int)($monthFortune['score'] ?? $baseScore);

    $seed = sprintf('%s-%s-%s', $userId, $date->format('Y-m-d'), $yearFortune['year'] ?? $year);
    $dailyOffset = fs_seeded_offset($seed, 4);
    $weekdayOffset = [1 => 1, 2 => 0, 3 => 1, 4 => 0, 5 => 2, 6 => 3, 7 => 1][$weekday] ?? 0;
    $dayFlow = ($day % 5) - 2;
    $dailyScore = fs_clamp_score(($monthScore * 0.7) + ($baseScore * 0.3) + $dailyOffset + $weekdayOffset + $dayFlow);

    $categories = [
        ['label' => '총운', 'score' => $dailyScore],
        ['label' => '재물운', 'score' => fs_daily_category_score($dailyScore, $userId, 'wealth', $date)],
        ['label' => '연애운', 'score' => fs_daily_category_score($dailyScore, $userId, 'love', $date)],
        ['label' => '일·학업운', 'score' => fs_daily_category_score($dailyScore, $userId, 'career', $date)],
        ['label' => '건강운', 'score' => fs_daily_category_score($dailyScore, $userId, 'health', $date)],
    ];

    return [
        'date' => $date,
        'score' => $dailyScore,
        'description' => fs_date_score_label($dailyScore),
        'year_fortune' => $yearFortune,
        'month_fortune' => $monthFortune,
        'categories' => $categories,
    ];
}

function fs_first_sentences($text, $limit = 3) {
    $plain = trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', strip_tags((string)$text))));
    if ($plain === '') return [];

    $sentences = preg_split('/(?<=[.!?])\s+/u', $plain);
    $result = [];
    foreach ($sentences as $sentence) {
        $sentence = trim($sentence, " \t\n\r\0\x0B-•");
        if ($sentence === '') continue;
        $result[] = $sentence;
        if (count($result) >= $limit) break;
    }

    return $result;
}

function fs_pick_months_by_score(array $monthlyFortunes, $count = 3, $ascending = false) {
    $months = array_values(array_filter($monthlyFortunes, 'is_array'));
    usort($months, function ($left, $right) use ($ascending) {
        $leftScore = (int)($left['score'] ?? 0);
        $rightScore = (int)($right['score'] ?? 0);

        if ($leftScore === $rightScore) {
            return (int)($left['month'] ?? 0) <=> (int)($right['month'] ?? 0);
        }

        return $ascending ? ($leftScore <=> $rightScore) : ($rightScore <=> $leftScore);
    });

    return array_slice($months, 0, $count);
}

function fs_month_list_text(array $months) {
    usort($months, function ($left, $right) {
        return (int)($left['month'] ?? 0) <=> (int)($right['month'] ?? 0);
    });

    $labels = [];
    foreach ($months as $month) {
        $monthNumber = (int)($month['month'] ?? 0);
        if ($monthNumber > 0) {
            $labels[] = $monthNumber . '월';
        }
    }

    return implode(', ', $labels);
}

function fs_trim_month_focus_text($text) {
    $clean = trim((string)$text);
    if ($clean === '') return '';

    return preg_replace('/^\d+월은\s*/u', '', $clean);
}

function fs_year_score_label($score) {
    if ($score >= 80) return '상승 기운이 강한 해';
    if ($score >= 65) return '꾸준히 밀면 성과가 나는 해';
    if ($score >= 50) return '안정적으로 다져 가는 해';
    if ($score >= 35) return '속도 조절이 필요한 해';
    return '정비와 회복이 우선인 해';
}

function fs_period_score_label($score) {
    if ($score >= 80) return '강하게 밀어붙이기 좋은 흐름';
    if ($score >= 65) return '기회가 잘 붙는 흐름';
    if ($score >= 50) return '안정적으로 가는 흐름';
    if ($score >= 35) return '속도 조절이 필요한 흐름';
    return '정비가 먼저인 흐름';
}

function fs_year_element_story($element) {
    $stories = [
        '목' => [
            'tone' => '기본적으로 앞으로 뻗어 가며 배우고 넓히는 힘이 강한 사람입니다.',
            'good' => '공부, 기획, 관계 확장처럼 씨앗을 심는 일이 잘 맞고, 방향만 잡히면 성장 속도가 빨라집니다.',
            'caution' => '새로운 일에 마음이 너무 많이 나뉘면 하나도 깊게 못 가는 문제가 생길 수 있습니다.',
            'advice' => '올해는 하고 싶은 일을 모두 늘리기보다, 진짜 키울 목표만 남기고 나머지는 과감히 덜어내는 편이 좋습니다.',
        ],
        '화' => [
            'tone' => '기본적으로 표현력과 존재감이 살아 있는 사람입니다.',
            'good' => '발표, 홍보, 연애, 대외 활동처럼 밖으로 드러나는 장면에서 매력이 커질 수 있습니다.',
            'caution' => '기세가 오를수록 말이 빨라지고 체력이 먼저 닳을 수 있다는 점을 조심해야 합니다.',
            'advice' => '올해는 반응이 좋을수록 휴식과 회복 시간을 더 철저히 챙겨야 흐름이 오래 갑니다.',
        ],
        '토' => [
            'tone' => '기본적으로 생활 기반을 단단하게 다지고 버티는 힘이 좋은 사람입니다.',
            'good' => '돈 관리, 일정 정리, 장기 계획처럼 차곡차곡 쌓는 일에서 실속이 잘 붙습니다.',
            'caution' => '너무 안전한 쪽만 고르면 좋은 기회를 앞에 두고도 움직이지 못할 수 있습니다.',
            'advice' => '올해는 안정은 지키되, 준비된 기회에는 한 번쯤 과감히 손을 뻗어 보는 균형감이 필요합니다.',
        ],
        '금' => [
            'tone' => '기본적으로 판단이 빠르고 기준이 분명한 사람입니다.',
            'good' => '시험, 계약, 정리, 협상처럼 결과를 갈라야 하는 장면에서 강점이 잘 드러납니다.',
            'caution' => '정답을 빨리 찾고 싶어지는 만큼 사람과 관계에는 말이 차갑게 들릴 수 있습니다.',
            'advice' => '올해는 맞는 말보다 부드러운 말이 더 큰 실속을 만든다는 점을 기억하는 편이 좋습니다.',
        ],
        '수' => [
            'tone' => '기본적으로 흐름을 읽고 상황에 맞게 움직이는 감각이 좋은 사람입니다.',
            'good' => '공부, 정보 수집, 기획, 회복, 내면 정리 같은 주제에서 힘을 받기 쉽습니다.',
            'caution' => '생각이 너무 많아지면 실행 타이밍을 놓치고 마음만 지칠 수 있습니다.',
            'advice' => '올해는 완벽하게 이해한 뒤 움직이려 하기보다, 작은 실행으로 감각을 확인하는 방식이 더 잘 맞습니다.',
        ],
    ];

    return $stories[$element] ?? [
        'tone' => '기본적으로 자기 리듬을 중요하게 보는 사람입니다.',
        'good' => '준비된 일을 차근히 밀고 가는 장면에서 강점이 살아납니다.',
        'caution' => '흐름이 흔들릴 때 마음이 여러 방향으로 갈릴 수 있습니다.',
        'advice' => '올해는 기준을 단순하게 잡고 계속 유지하는 편이 좋습니다.',
    ];
}

function fs_year_sipsin_story($sipsin) {
    $stories = [
        '비견' => [
            'focus' => '내 기준과 자존감이 커지고, 스스로 주도권을 잡고 싶은 마음이 강해지는 흐름입니다.',
            'good' => '내가 중심이 되어 판을 끌고 가는 일',
            'caution' => '혼자 다 안고 가다 고집이 세지고 협력이 끊어지는 부분을 조심해야 합니다.',
        ],
        '겁재' => [
            'focus' => '사람, 경쟁, 넓은 관계 속에서 에너지가 많이 오가는 흐름입니다.',
            'good' => '새 사람을 만나고 판을 넓히는 일',
            'caution' => '비교심과 감정 소모 때문에 힘이 빠지는 부분을 조심해야 합니다.',
        ],
        '식신' => [
            'focus' => '생활 리듬, 생산성, 꾸준함이 살아나는 흐름입니다.',
            'good' => '실력을 차분히 쌓고 결과물을 만드는 일',
            'caution' => '편하다고 느슨해져 속도가 처지는 부분을 조심해야 합니다.',
        ],
        '상관' => [
            'focus' => '표현력, 창의성, 말의 힘이 강해지는 흐름입니다.',
            'good' => '발표, 기획, 창작, 새로운 시도',
            'caution' => '말이 앞서거나 규칙을 답답해해 충돌이 나는 부분을 조심해야 합니다.',
        ],
        '편재' => [
            'focus' => '기회, 사람, 재물, 넓은 활동 반경이 함께 움직이는 흐름입니다.',
            'good' => '영업, 사업, 네트워킹, 바깥일',
            'caution' => '여기저기 손을 뻗다가 집중력이 흐트러지는 부분을 조심해야 합니다.',
        ],
        '정재' => [
            'focus' => '생활 관리, 돈 관리, 안정적인 성과에 마음이 가는 흐름입니다.',
            'good' => '저축, 계획, 실속 챙기기',
            'caution' => '작은 손해에도 예민해지고 계산이 지나치게 앞서는 부분을 조심해야 합니다.',
        ],
        '편관' => [
            'focus' => '압박, 책임, 승부, 변화 대응 능력이 시험받는 흐름입니다.',
            'good' => '도전, 경쟁, 빠른 결단',
            'caution' => '예민함과 스트레스가 쌓여 날카로워지는 부분을 조심해야 합니다.',
        ],
        '정관' => [
            'focus' => '규칙, 시험, 직장, 평판, 공식적인 자리와 관련된 주제가 커지는 흐름입니다.',
            'good' => '자격, 평가, 승진, 신뢰',
            'caution' => '기준이 지나치게 높아져 스스로를 압박하는 부분을 조심해야 합니다.',
        ],
        '편인' => [
            'focus' => '혼자 깊게 파고들며 감각을 살리는 흐름입니다.',
            'good' => '연구, 기획, 특수 분야 탐구',
            'caution' => '생각이 많아져 실행이 늦고 사람과 거리가 벌어지는 부분을 조심해야 합니다.',
        ],
        '정인' => [
            'focus' => '배움, 문서, 보호, 안정감이 중요해지는 흐름입니다.',
            'good' => '공부, 정리, 조력 얻기',
            'caution' => '생각만 많아지고 결정이 늦어지는 부분을 조심해야 합니다.',
        ],
    ];

    return $stories[$sipsin] ?? [
        'focus' => '올해의 중심 주제가 조금씩 드러나는 흐름입니다.',
        'good' => '지금 해야 할 일에 집중하는 장면',
        'caution' => '마음이 분산되는 순간을 조심해야 합니다.',
    ];
}

function fs_year_relation_story(array $relationships) {
    $hasHap = false;
    $hasChung = false;

    foreach ($relationships as $relationship) {
        $type = $relationship['type'] ?? '';
        if (in_array($type, ['육합', '삼합', '천간합'], true)) {
            $hasHap = true;
        }
        if (in_array($type, ['충', '천간충'], true)) {
            $hasChung = true;
        }
    }

    if ($hasHap && $hasChung) {
        return [
            'summary' => '사람이 들어오고 판도 흔들리는 해라, 기회와 변화가 함께 움직일 가능성이 큽니다.',
            'good' => '좋은 사람을 만나거나 기존 관계에서 새로운 역할을 맡게 되는 흐름도 기대해 볼 수 있습니다.',
            'caution' => '다만 가까운 관계일수록 작은 오해가 크게 번질 수 있으니, 급한 말과 급한 결정을 줄이는 편이 좋습니다.',
        ];
    }

    if ($hasHap) {
        return [
            'summary' => '혼자 버티는 것보다 사람과 연결될 때 흐름이 더 부드럽게 풀리기 쉬운 해입니다.',
            'good' => '협력, 소개, 추천, 도움 요청처럼 사람을 통하는 길에서 좋은 답을 얻을 가능성이 큽니다.',
            'caution' => '좋은 인연이 들어와도 기준 없이 모두 붙잡으려 하면 오히려 일정과 감정이 복잡해질 수 있습니다.',
        ];
    }

    if ($hasChung) {
        return [
            'summary' => '자리 이동, 역할 변화, 관계 재정리처럼 흐름이 움직이는 장면이 생길 가능성이 큰 해입니다.',
            'good' => '답답했던 판을 정리하고 새로운 방향을 잡는 계기로 바뀌면 오히려 더 나은 흐름을 만들 수 있습니다.',
            'caution' => '변화가 들어올수록 감정적으로 반응하기보다, 바뀌는 이유와 순서를 먼저 보는 편이 안전합니다.',
        ];
    }

    return [
        'summary' => '크게 흔들기보다 내가 하던 흐름을 어떻게 운영하느냐가 성과를 좌우하는 해입니다.',
        'good' => '익숙한 사람들과의 신뢰를 지키고, 이미 있는 판을 안정적으로 굴리는 쪽에서 강점이 살아납니다.',
        'caution' => '겉으로 큰 사건이 없다고 느슨해지면, 작은 실수가 반복되어 뒤늦게 부담이 될 수 있습니다.',
    ];
}

function fs_build_yearly_quarter_summary(array $monthlyFortunes, $quarter) {
    $startMonth = (($quarter - 1) * 3) + 1;
    $endMonth = $startMonth + 2;
    $slice = [];

    foreach ($monthlyFortunes as $month) {
        $monthNumber = (int)($month['month'] ?? 0);
        if ($monthNumber >= $startMonth && $monthNumber <= $endMonth) {
            $slice[] = $month;
        }
    }

    if (empty($slice)) {
        return null;
    }

    $totalScore = 0;
    foreach ($slice as $month) {
        $totalScore += (int)($month['score'] ?? 0);
    }
    $averageScore = (int)round($totalScore / count($slice));

    $sorted = fs_pick_months_by_score($slice, count($slice));
    $best = $sorted[0] ?? null;
    $worst = $sorted[count($sorted) - 1] ?? null;

    $leadText = [
        1 => '한 해 초반에는',
        2 => '봄에서 초여름으로 넘어가며',
        3 => '여름 이후 중반부에는',
        4 => '연말로 갈수록',
    ][$quarter] ?? '이 구간에는';

    $bestLine = $best
        ? $best['month'] . '월에는 ' . fs_trim_month_focus_text($best['focus'] ?? '흐름이 비교적 부드럽게 풀립니다.')
        : '도움이 되는 흐름이 있습니다.';
    $worstLine = $worst
        ? $worst['month'] . '월에는 ' . fs_trim_month_focus_text($worst['focus'] ?? '조금 더 신중한 운영이 필요합니다.')
        : '주의가 필요한 시기가 있습니다.';

    return [
        'title' => $startMonth . '~' . $endMonth . '월',
        'score' => $averageScore,
        'text' => $leadText . ' ' . fs_period_score_label($averageScore) . '입니다. 특히 ' . $bestLine . ' 반대로 ' . $worstLine . ' 좋은 구간에는 시작과 공개를 맡기고, 약한 구간에는 정리와 점검을 맡기는 편이 좋습니다.',
    ];
}

function fs_build_yearly_longform(array $record, array $yearFortune, array $monthlyFortunes, $year, $mode = 'yearly') {
    $engine = fs_build_engine_from_record($record);
    $result = $engine->getResult();

    $score = (int)($yearFortune['score'] ?? 50);
    $stemSipsin = $yearFortune['stem_sipsin'] ?? '';
    $branchSipsin = $yearFortune['branch_sipsin'] ?? '';
    $bestMonths = fs_pick_months_by_score($monthlyFortunes, 3);
    $careMonths = fs_pick_months_by_score($monthlyFortunes, 3, true);
    $bestMonthsText = fs_month_list_text($bestMonths) ?: '흐름이 부드러운 구간';
    $careMonthsText = fs_month_list_text($careMonths) ?: '속도 조절이 필요한 구간';

    $dayElement = $result['day_master_element'] ?? '';
    $zodiac = $result['zodiac'] ?? '';
    $dms = $result['day_master_strength'] ?? [];
    $yongshinEl = $dms['yongshin']['element'] ?? '';
    $heeshinEl = $dms['heeshin']['element'] ?? '';
    $gishinEl = $dms['gishin']['element'] ?? '';
    $supportElementText = $heeshinEl ? $heeshinEl . ' 기운' : '보조 기운';
    $burdenElementText = $gishinEl ? $gishinEl . ' 기운' : '부담이 되는 기운';

    $elementStory = fs_year_element_story($dayElement);
    $stemStory = fs_year_sipsin_story($stemSipsin);
    $branchStory = fs_year_sipsin_story($branchSipsin);
    $relationStory = fs_year_relation_story($yearFortune['relationships'] ?? []);
    $sameSipsinFlow = ($stemSipsin !== '' && $stemSipsin === $branchSipsin);

    $helpfulMonths = 0;
    $carefulMonths = 0;
    foreach ($monthlyFortunes as $month) {
        $monthScore = (int)($month['score'] ?? 0);
        if ($monthScore >= 65) $helpfulMonths++;
        if ($monthScore <= 45) $carefulMonths++;
    }

    $heroSubtitle = $score >= 65
        ? '흐름이 열리는 달을 잘 잡으면 성과가 더 커지는 해입니다.'
        : ($score >= 45
            ? '무리한 점프보다 리듬을 지키는 운영이 훨씬 중요한 해입니다.'
            : '욕심보다 정리와 준비가 실제 차이를 만드는 해입니다.');

    if (!empty($yearFortune['is_yongshin'])) {
        $heroSubtitle .= ' 내게 필요한 기운이 직접 들어와 힘을 받기 쉽습니다.';
    }

    $headline = trim((string)(fs_first_sentences($yearFortune['interpretation'] ?? '', 1)[0] ?? ''));
    if ($headline === '') {
        $headline = $heroSubtitle;
    }

    $overallSecondParagraph = $sameSipsinFlow
        ? '올해는 겉으로도 안으로도 ' . $stemSipsin . '의 성격이 강하게 드러납니다. 즉 ' . $stemStory['focus'] . ' 그래서 하고 싶은 마음과 실제 생활 리듬을 따로 두기보다, 말과 행동의 방향을 한곳으로 모아 가는 사람이 더 안정적으로 성과를 내기 쉽습니다.'
        : '올해의 겉기운은 ' . $stemSipsin . ' 쪽으로 움직여 ' . $stemStory['good'] . ' 일이 먼저 눈에 띄기 쉽습니다. 생활 속 바닥 흐름은 ' . $branchSipsin . ' 쪽으로 흘러서 ' . $branchStory['focus'] . ' 그래서 올해는 하고 싶은 마음과 실제 생활 리듬을 따로 보지 말고, 둘을 함께 맞춰 가는 사람이 더 안정적으로 성과를 내기 쉽습니다.';

    $overallThirdParagraph = $elementStory['tone'] . ' 올해 들어오는 ' . ($yearFortune['stem_element'] ?? '') . '·' . ($yearFortune['branch_element'] ?? '') . ' 기운은 ' . $elementStory['good'] . ' 이런 장면을 더 자주 만들 수 있습니다. ' . $relationStory['summary'];

    $goodThirdParagraph = $sameSipsinFlow
        ? '올해는 ' . $stemStory['good'] . ' 쪽 흐름이 반복해서 나타나기 때문에, 실력이나 아이디어를 숨겨두기보다 적절한 타이밍에 바깥으로 꺼내는 편이 좋습니다. 같은 주제가 여러 번 반복된다는 것은 그만큼 한 방향으로 밀었을 때 누적 효과가 커진다는 뜻이기도 합니다.'
        : $stemStory['good'] . ' 쪽과 ' . $branchStory['good'] . ' 쪽을 함께 챙기면, 눈앞의 성과와 장기적인 기반을 같이 잡을 수 있습니다. 그래서 올해는 당장 눈에 띄는 일과 나를 오래 남게 하는 일을 따로 보지 말고 하나의 흐름으로 엮어 움직이는 전략이 잘 맞습니다.';

    $careSecondParagraph = $sameSipsinFlow
        ? $stemStory['caution'] . ' ' . $relationStory['caution'] . ' 특히 ' . $burdenElementText . ' 쪽 부담이 강해지는 순간에는 피로와 감정 소모가 같이 올라오기 쉬우니, 중요한 결정일수록 하루 이틀 간격을 두고 다시 보는 편이 안전합니다.'
        : $stemStory['caution'] . ' ' . $branchStory['caution'] . ' ' . $relationStory['caution'] . ' 특히 ' . $burdenElementText . ' 쪽 부담이 강해지는 순간에는 피로와 감정 소모가 같이 올라오기 쉬우니, 중요한 결정일수록 하루 이틀 간격을 두고 다시 보는 편이 안전합니다.';

    $sections = [
        [
            'title' => '총운풀이',
            'paragraphs' => [
                $year . '년은 ' . fs_year_score_label($score) . '입니다. 갑자기 인생이 완전히 뒤집히는 해라기보다, 지금까지 쌓아 둔 힘이 어느 구간에서 빛나고 어느 구간에서는 숨을 고르는지가 분명하게 드러나는 해라고 보는 편이 맞습니다. 특히 ' . $bestMonthsText . '에는 흐름이 한결 부드럽고, ' . $careMonthsText . '에는 속도를 잠시 늦추며 정리하는 운영이 더 유리합니다.',
                $overallSecondParagraph,
                $zodiac . '띠인 당신은 ' . $overallThirdParagraph,
            ],
        ],
        [
            'title' => '가장 좋은 것',
            'paragraphs' => [
                '올해 가장 좋은 점은 힘을 받을 구간이 비교적 분명하다는 것입니다. ' . $bestMonthsText . '에는 발표, 지원, 면접, 계약, 관계 확장처럼 밖으로 꺼내 보여 주는 일이 잘 붙을 가능성이 큽니다. 이미 준비한 일이 있다면 이 구간에 결과를 보여 주는 쪽으로 배치하는 것이 가장 효율적입니다.',
                (!empty($yearFortune['is_yongshin'])
                    ? '또 올해는 당신에게 도움이 되는 ' . $yongshinEl . ' 기운이 직접 닿는 해라, 평소보다 타이밍 운과 회복력이 함께 살아나기 쉽습니다. 혼자 모든 답을 만들기보다 사람, 정보, 환경의 도움을 받아 판을 키우는 편이 훨씬 빠릅니다.'
                    : '또 올해는 정면으로 모든 것이 술술 풀리는 해는 아니더라도, ' . $supportElementText . '을 잘 쓰면 전체 흐름을 충분히 부드럽게 바꿀 수 있습니다. 생활 루틴, 공부 순서, 돈 쓰는 기준처럼 기본 운영만 안정시켜도 체감 성과가 크게 달라질 수 있습니다.') . ' ' . $relationStory['good'],
                $goodThirdParagraph,
            ],
        ],
        [
            'title' => '주의가 필요한 것',
            'paragraphs' => [
                '주의할 점은 ' . $careMonthsText . '에 마음이 쉽게 흔들릴 수 있다는 것입니다. 이 시기에는 당장 분위기가 답답해 보여도 판을 크게 바꾸는 결정은 나중에 다시 검토할 일을 만들 가능성이 큽니다. 공부든 일이든 이미 쌓아 둔 틀을 보완하고 생활 리듬을 고치는 쪽이 손실을 훨씬 줄여 줍니다.',
                $careSecondParagraph,
                $elementStory['caution'] . ' 올해는 잘될 때 더 크게 벌리기보다, 잘될 때일수록 기준과 순서를 더 단순하게 유지하는 사람이 오래 갑니다. 무리해서 모든 걸 동시에 챙기려는 순간 집중력이 흐려질 수 있다는 점을 기억해 두는 편이 좋습니다.',
            ],
        ],
        [
            'title' => '올해의 조언',
            'paragraphs' => [
                '올해의 조언은 생각보다 단순합니다. ' . $elementStory['advice'] . ' 좋은 달에는 시작과 공개를 맡기고, 약한 달에는 점검과 회복을 맡기는 식으로 역할을 나누면 훨씬 편합니다.',
                $mode === 'tojung'
                    ? '토정비결처럼 월별 흐름을 보면, 한 달 안에서도 강하게 밀어도 되는 시기와 조용히 정리해야 하는 시기가 갈립니다. 달력에 중요한 일정, 돈 움직임, 공부 계획을 미리 나눠 두면 흔들릴 때도 다시 돌아올 기준이 생깁니다.'
                    : '신년운세는 한 해의 큰 방향을 보는 지도라고 생각하면 됩니다. 그래서 즉흥적으로 반응하기보다 분기마다 목표를 다시 확인하고, 좋은 구간에 힘을 쓰는 방식으로 움직이는 것이 가장 효율적입니다.',
                '결국 ' . $year . '년은 요령보다 성실함이 오래 힘을 내는 해입니다. 작게라도 매일 이어 가는 습관, 지출과 시간 사용을 기록하는 태도, 그리고 사람과 약속을 가볍게 넘기지 않는 자세가 결국 큰 보상으로 돌아올 가능성이 큽니다.',
            ],
        ],
    ];

    $quarters = [];
    for ($quarter = 1; $quarter <= 4; $quarter++) {
        $quarterSummary = fs_build_yearly_quarter_summary($monthlyFortunes, $quarter);
        if ($quarterSummary) {
            $quarters[] = $quarterSummary;
        }
    }

    return [
        'hero_subtitle' => $heroSubtitle,
        'headline' => $headline,
        'summary_cards' => [
            ['label' => '올해의 톤', 'value' => fs_year_score_label($score)],
            ['label' => '힘 받는 달', 'value' => $bestMonthsText],
            ['label' => '속도 조절 달', 'value' => $careMonthsText],
            ['label' => '도움 되는 기운', 'value' => $yongshinEl ? $yongshinEl . ' 기운' : '정보 준비 중'],
            ['label' => '기회 달 수', 'value' => $helpfulMonths . '개월'],
            ['label' => '주의 달 수', 'value' => $carefulMonths . '개월'],
        ],
        'sections' => $sections,
        'quarters' => $quarters,
    ];
}

function fs_record_cache_key(array $record) {
    if (!empty($record['id'])) {
        return 'record:' . $record['id'];
    }

    return implode(':', [
        $record['birth_year'] ?? '',
        $record['birth_month'] ?? '',
        $record['birth_day'] ?? '',
        $record['birth_hour'] ?? '',
        $record['gender'] ?? '',
        $record['calendar_type'] ?? '',
    ]);
}

function fs_get_runtime_engine_bundle(array $record) {
    static $cache = [];

    $cacheKey = fs_record_cache_key($record);
    if (!isset($cache[$cacheKey])) {
        $engine = fs_build_engine_from_record($record);
        $cache[$cacheKey] = [
            'engine' => $engine,
            'interpreter' => new FortuneInterpreter($engine),
            'result' => $engine->getResult(),
        ];
    }

    return $cache[$cacheKey];
}

function fs_get_runtime_comprehensive_fortune(array $record) {
    static $cache = [];

    $cacheKey = fs_record_cache_key($record);
    if (!isset($cache[$cacheKey])) {
        $bundle = fs_get_runtime_engine_bundle($record);
        $cache[$cacheKey] = $bundle['interpreter']->getComprehensiveFortune();
    }

    return $cache[$cacheKey];
}

function fs_get_runtime_daeun(array $record) {
    static $cache = [];

    $cacheKey = fs_record_cache_key($record);
    if (!isset($cache[$cacheKey])) {
        $bundle = fs_get_runtime_engine_bundle($record);
        $cache[$cacheKey] = $bundle['interpreter']->analyzeDaeun();
    }

    return $cache[$cacheKey];
}

function fs_get_runtime_year_context(array $record, $year) {
    static $cache = [];

    $cacheKey = fs_record_cache_key($record) . ':year:' . (int)$year;
    if (!isset($cache[$cacheKey])) {
        $bundle = fs_get_runtime_engine_bundle($record);
        $seunData = $bundle['interpreter']->analyzeSeun((int)$year);
        $cache[$cacheKey] = [
            'year_fortune' => fs_find_year_fortune($seunData, $year) ?: ($seunData[0] ?? null),
            'monthly_fortunes' => $bundle['interpreter']->analyzeMonthlyFortunes((int)$year),
        ];
    }

    return $cache[$cacheKey];
}

function fs_simple_strength_label($label) {
    $label = trim((string)$label);
    if ($label === '') return '균형을 잡아 가는 편';
    if (strpos($label, '신강') !== false) return '에너지가 강하게 밀고 나가는 편';
    if (strpos($label, '신약') !== false) return '에너지가 섬세하게 반응하는 편';
    return $label;
}

function fs_element_name($element) {
    $map = [
        '목' => '나무',
        '화' => '불',
        '토' => '흙',
        '금' => '쇠',
        '수' => '물',
    ];

    return $map[$element] ?? $element;
}

function fs_korean_pair($left, $right) {
    $left = trim((string)$left);
    $right = trim((string)$right);
    if ($left === '') return $right;
    if ($right === '') return $left;

    $particle = '와';
    if (function_exists('mb_substr') && function_exists('mb_ord')) {
        $lastChar = mb_substr($left, -1, 1, 'UTF-8');
        $code = mb_ord($lastChar, 'UTF-8');
        if ($code >= 0xAC00 && $code <= 0xD7A3) {
            $particle = (($code - 0xAC00) % 28) === 0 ? '와' : '과';
        }
    }

    return $left . $particle . ' ' . $right;
}

function fs_normalize_utf8($text) {
    $text = (string)$text;
    if ($text === '') return '';

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }

    return str_replace("�", '', $text);
}

function fs_safe_preg_replace($pattern, $replacement, $subject) {
    $subject = fs_normalize_utf8($subject);
    $result = preg_replace($pattern, $replacement, $subject);
    return $result === null ? $subject : $result;
}

function fs_safe_preg_split($pattern, $subject) {
    $subject = fs_normalize_utf8($subject);
    $result = preg_split($pattern, $subject);
    return is_array($result) ? $result : [$subject];
}

function fs_text_key($text) {
    $plain = trim(fs_safe_preg_replace('/\s+/u', ' ', strip_tags((string)$text)));
    if ($plain === '') return '';

    $plain = fs_safe_preg_replace('/[^\p{L}\p{N}]+/u', '', $plain);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($plain, 'UTF-8');
    }

    return strtolower($plain);
}

function fs_trim_repeated_sentences($text) {
    $plain = trim(fs_safe_preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', strip_tags((string)$text))));
    if ($plain === '') return '';

    $sentences = fs_safe_preg_split('/(?<=[.!?])\s+/u', $plain);
    $unique = [];
    $seen = [];

    foreach ($sentences as $sentence) {
        $sentence = trim($sentence);
        if ($sentence === '') continue;

        $key = fs_text_key($sentence);
        if ($key === '' || isset($seen[$key])) continue;

        $seen[$key] = true;
        $unique[] = $sentence;
    }

    return implode(' ', $unique);
}

function fs_append_unique_paragraph(array &$paragraphs, $text, array &$seen, $minLength = 24) {
    $text = trim((string)$text);
    if ($text === '') return;

    $text = fs_safe_preg_replace('/^━━━.*?━━━\s*/u', '', $text);
    $text = fs_safe_preg_replace('/^[【\[].*?[】\]]\s*/u', '', $text);
    $text = trim($text, " \t\n\r\0\x0B•-");
    $text = fs_trim_repeated_sentences($text);
    $text = trim($text);

    $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    if ($text === '' || $length < $minLength) return;

    $key = fs_text_key($text);
    if ($key === '' || isset($seen[$key])) return;

    $seen[$key] = true;
    $paragraphs[] = $text;
}

function fs_filter_paragraphs(array $paragraphs) {
    return array_values(array_filter($paragraphs, function ($paragraph) {
        return trim(fs_normalize_utf8($paragraph)) !== '';
    }));
}

function fs_extract_distinct_paragraphs($text, $limit = 6, array &$seen = []) {
    $clean = trim((string)$text);
    if ($clean === '') return [];

    $clean = fs_safe_preg_replace('/\r\n?|\n/u', "\n", $clean);
    $clean = fs_safe_preg_replace('/^━━━.*?━━━\s*$/mu', '', $clean);
    $clean = fs_safe_preg_replace('/^[【\[].*?[】\]]\s*$/mu', '', $clean);

    foreach (['🌟', '☀️', '☁️', '🌧️', '⛈️', '⭐', '✅', '💛', '🔹', '💬', '🔗', '📋', '🏢', '💰', '💕', '🌊', '✦'] as $marker) {
        $clean = str_replace(' ' . $marker, "\n\n" . $marker, $clean);
    }
    $clean = fs_safe_preg_replace('/\s*(【[^】]+】)\s*/u', "\n\n$1 ", $clean);

    $chunks = preg_split('/\n{2,}/u', $clean);
    $paragraphs = [];

    foreach ($chunks as $chunk) {
        $chunk = trim($chunk);
        if ($chunk === '') continue;

        $lines = preg_split('/\n+/u', $chunk);
        if (count($lines) > 1) {
            $flattened = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $flattened[] = trim($line, " \t\n\r\0\x0B•-");
            }
            $chunk = implode(' ', $flattened);
        }

        fs_append_unique_paragraph($paragraphs, $chunk, $seen);
        if (count($paragraphs) >= $limit) {
            return $paragraphs;
        }
    }

    if (!empty($paragraphs)) {
        return fs_filter_paragraphs($paragraphs);
    }

    $sentences = fs_safe_preg_split('/(?<=[.!?])\s+/u', trim(fs_safe_preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', $clean))));
    $buffer = [];
    $sentenceCount = 0;
    $groupedParagraphs = [];

    foreach ($sentences as $sentence) {
        $sentence = trim($sentence);
        if ($sentence === '') continue;

        $buffer[] = $sentence;
        $sentenceCount++;

        if ($sentenceCount >= 2) {
            fs_append_unique_paragraph($groupedParagraphs, implode(' ', $buffer), $seen);
            $buffer = [];
            $sentenceCount = 0;
            if (count($groupedParagraphs) >= $limit) {
                return fs_filter_paragraphs($groupedParagraphs);
            }
        }
    }

    if (!empty($buffer)) {
        fs_append_unique_paragraph($groupedParagraphs, implode(' ', $buffer), $seen);
    }

    return fs_filter_paragraphs(array_slice($groupedParagraphs, 0, $limit));
}

function fs_current_age(array $record, DateTimeInterface $date = null) {
    $date = $date ?: new DateTimeImmutable('today');
    $birthDate = DateTimeImmutable::createFromFormat(
        'Y-n-j',
        sprintf('%d-%d-%d', (int)$record['birth_year'], (int)$record['birth_month'], (int)$record['birth_day'])
    );

    if (!$birthDate) {
        return max(0, ((int)$date->format('Y')) - (int)$record['birth_year']);
    }

    return (int)$birthDate->diff($date)->y;
}

function fs_find_current_daeun(array $daeunData, array $record, DateTimeInterface $date = null) {
    $list = $daeunData['daeuns'] ?? [];
    if (empty($list)) return null;

    $age = fs_current_age($record, $date);
    $candidate = null;

    foreach ($list as $daeun) {
        if (!is_array($daeun)) continue;

        if ($age >= (int)($daeun['age_start'] ?? 0) && $age <= (int)($daeun['age_end'] ?? 0)) {
            return $daeun;
        }

        if ((int)($daeun['age_start'] ?? 0) <= $age) {
            $candidate = $daeun;
        }
    }

    return $candidate ?: ($list[0] ?? null);
}

function fs_next_daeuns(array $daeunData, $currentIndex, $limit = 2) {
    $list = $daeunData['daeuns'] ?? [];
    $next = [];

    foreach ($list as $daeun) {
        if (!is_array($daeun)) continue;
        if ((int)($daeun['index'] ?? -1) <= (int)$currentIndex) continue;
        $next[] = $daeun;
        if (count($next) >= $limit) break;
    }

    return $next;
}

function fs_topic_meta($type) {
    $map = [
        'wealth' => ['title' => '재물운', 'icon' => 'fa-sack-dollar', 'section' => 'wealth', 'desc' => '돈의 흐름과 관리 습관을 길게 읽습니다.'],
        'love' => ['title' => '애정운', 'icon' => 'fa-heart', 'section' => 'love', 'desc' => '연애와 관계의 패턴을 길게 읽습니다.'],
        'career' => ['title' => '직업운', 'icon' => 'fa-briefcase', 'section' => 'career', 'desc' => '일 적성과 커리어 흐름을 깊게 읽습니다.'],
        'health' => ['title' => '건강운', 'icon' => 'fa-heart-pulse', 'section' => 'health', 'desc' => '몸과 마음의 컨디션 흐름을 길게 읽습니다.'],
    ];

    return $map[$type] ?? $map['wealth'];
}

function fs_build_traditional_saju_story(array $record) {
    $bundle = fs_get_runtime_engine_bundle($record);
    $fortune = fs_get_runtime_comprehensive_fortune($record);
    $yearContext = fs_get_runtime_year_context($record, (int)date('Y'));
    $result = $bundle['result'];
    $dms = $result['day_master_strength'] ?? [];
    $bestMonthsText = fs_month_list_text(fs_pick_months_by_score($yearContext['monthly_fortunes'] ?? [], 3)) ?: '올해 힘을 받는 구간';

    $summaryCards = [
        ['label' => '띠', 'value' => ($result['zodiac'] ?? '') . '띠'],
        ['label' => '일간', 'value' => ($result['day_master'] ?? '') . '(' . ($result['day_master_element'] ?? '') . ')'],
        ['label' => '에너지 타입', 'value' => fs_simple_strength_label($dms['strength_label'] ?? '')],
        ['label' => '도움 되는 기운', 'value' => !empty($dms['yongshin']['element']) ? $dms['yongshin']['element'] . ' 기운' : '분석 중'],
    ];

    $seen = [];
    $coreParagraphs = [];
    fs_append_unique_paragraph($coreParagraphs, '이 사주의 기본 틀은 ' . ($record['year_pillar'] ?? '') . ' ' . ($record['month_pillar'] ?? '') . ' ' . ($record['day_pillar'] ?? '') . ' ' . ($record['hour_pillar'] ?? '') . '로 이루어져 있습니다. 겉으로 드러나는 기운과 안쪽에서 버티는 기운이 분리되어 있어, 처음 보이는 인상보다 실제 속내가 훨씬 깊게 느껴질 가능성이 큽니다.', $seen);
    fs_append_unique_paragraph($coreParagraphs, ($result['zodiac'] ?? '이 사주') . '띠이고 일간은 ' . ($result['day_master'] ?? '') . ' ' . fs_element_name($result['day_master_element'] ?? '') . ' 기운입니다. 그래서 성격은 한 방향으로 무조건 밀어붙이는 단순한 타입이라기보다, 상황을 읽으면서도 자기 기준을 놓치지 않는 쪽에 가깝습니다.', $seen);
    fs_append_unique_paragraph($coreParagraphs, '현재 큰 흐름으로 보면 ' . ((int)date('Y')) . '년에는 특히 ' . $bestMonthsText . ' 쪽에서 흐름이 부드럽게 열릴 가능성이 큽니다. 정통사주는 원국을 보는 페이지인 만큼, 지금 운세를 잠깐 보는 것이 아니라 왜 그런 흐름이 반복되는지를 같이 읽는 것이 중요합니다.', $seen);

    $personalityParagraphs = fs_extract_distinct_paragraphs($fortune['personality'] ?? '', 6, $seen);
    $lifeFlowParagraphs = fs_extract_distinct_paragraphs($fortune['life_flow'] ?? '', 6, $seen);

    return [
        'hero' => '사주의 구조와 인생 흐름을 길게 풀어 읽습니다.',
        'summary_cards' => $summaryCards,
        'sections' => [
            ['title' => '사주의 큰 바탕', 'paragraphs' => $coreParagraphs],
            ['title' => '기질과 타고난 힘', 'paragraphs' => $personalityParagraphs],
            ['title' => '인생 흐름과 전환점', 'paragraphs' => $lifeFlowParagraphs],
        ],
    ];
}

function fs_build_topic_fortune_story(array $record, $type) {
    $meta = fs_topic_meta($type);
    $year = (int)date('Y');
    $bundle = fs_get_runtime_engine_bundle($record);
    $fortune = fs_get_runtime_comprehensive_fortune($record);
    $yearContext = fs_get_runtime_year_context($record, $year);
    $yearFortune = $yearContext['year_fortune'] ?? [];
    $monthlyFortunes = $yearContext['monthly_fortunes'] ?? [];
    $result = $bundle['result'];
    $dms = $result['day_master_strength'] ?? [];

    $bestMonthsText = fs_month_list_text(fs_pick_months_by_score($monthlyFortunes, 3)) ?: '좋은 흐름의 달';
    $careMonthsText = fs_month_list_text(fs_pick_months_by_score($monthlyFortunes, 3, true)) ?: '속도 조절이 필요한 달';
    $stemStory = fs_year_sipsin_story($yearFortune['stem_sipsin'] ?? '');
    $branchStory = fs_year_sipsin_story($yearFortune['branch_sipsin'] ?? '');
    $relationStory = fs_year_relation_story($yearFortune['relationships'] ?? []);
    $sameTopic = !empty($yearFortune['stem_sipsin']) && ($yearFortune['stem_sipsin'] ?? '') === ($yearFortune['branch_sipsin'] ?? '');

    $summaryCards = [
        ['label' => '올해 흐름', 'value' => fs_year_score_label($yearFortune['score'] ?? 50)],
        ['label' => '힘 받는 달', 'value' => $bestMonthsText],
        ['label' => '속도 조절 달', 'value' => $careMonthsText],
        ['label' => '도움 되는 기운', 'value' => !empty($dms['yongshin']['element']) ? $dms['yongshin']['element'] . ' 기운' : '분석 중'],
    ];

    $seen = [];
    $introParagraphs = [];
    $sourceParagraphs = [];
    $adviceParagraphs = [];

    switch ($type) {
        case 'love':
            fs_append_unique_paragraph($introParagraphs, $year . '년 애정운은 사람과 마음의 거리 조절이 핵심인 흐름입니다. 특히 ' . $bestMonthsText . '에는 새로운 인연이나 기존 관계의 진전이 비교적 부드럽게 붙을 가능성이 크고, ' . $careMonthsText . '에는 감정이 앞서면서 오해가 생기지 않도록 속도를 조금 늦추는 편이 좋습니다.', $seen);
            fs_append_unique_paragraph($introParagraphs, $sameTopic
                ? '올해는 겉으로도 안으로도 ' . ($yearFortune['stem_sipsin'] ?? '관계') . ' 주제가 강하게 움직입니다. 그래서 누굴 만나느냐 못지않게, 내가 어떤 말투와 리듬으로 관계를 다루느냐가 훨씬 중요해집니다.'
                : '올해 겉으로 드러나는 관계 주제는 ' . ($yearFortune['stem_sipsin'] ?? '관계') . ' 쪽이고, 생활 바닥에서 움직이는 주제는 ' . ($yearFortune['branch_sipsin'] ?? '마음') . ' 쪽입니다. 그래서 누굴 만나느냐 못지않게, 내가 어떤 말투와 리듬으로 관계를 다루느냐가 훨씬 중요해집니다.', $seen);
            fs_append_unique_paragraph($introParagraphs, $relationStory['summary'] . ' ' . $relationStory['good'], $seen);
            $sourceParagraphs = fs_extract_distinct_paragraphs($fortune['love'] ?? '', 7, $seen);
            fs_append_unique_paragraph($adviceParagraphs, '애정운을 잘 쓰려면 상대를 빨리 판단하기보다, 마음이 잘 맞는지와 생활 리듬이 맞는지를 같이 보는 편이 좋습니다. 좋은 달에는 대화와 만남의 폭을 넓히고, 약한 달에는 결론을 서두르지 않는 것이 관계를 오래 가게 합니다.', $seen);
            fs_append_unique_paragraph($adviceParagraphs, $stemStory['caution'] . ' ' . $branchStory['caution'], $seen);
            break;

        case 'health':
            fs_append_unique_paragraph($introParagraphs, $year . '년 건강운은 무조건 아프다거나 괜찮다는 식으로 단정하기보다, 언제 체력이 올라오고 언제 쉬어야 하는지를 읽는 흐름에 가깝습니다. 특히 ' . $bestMonthsText . '에는 회복력과 생활 리듬이 비교적 안정되기 쉽고, ' . $careMonthsText . '에는 과로와 감정 소모를 줄이는 운영이 훨씬 중요해집니다.', $seen);
            fs_append_unique_paragraph($introParagraphs, $sameTopic
                ? '올해는 겉으로도 안으로도 ' . ($yearFortune['stem_sipsin'] ?? '건강') . ' 주제가 강하게 움직입니다. 그래서 몸 상태와 마음 상태를 따로 보지 말고 같이 관리하는 편이 좋습니다.'
                : '올해 몸 바깥으로 드러나는 건강 주제는 ' . ($yearFortune['stem_sipsin'] ?? '활동') . ' 쪽이고, 생활 바닥에서 쌓이는 피로 주제는 ' . ($yearFortune['branch_sipsin'] ?? '회복') . ' 쪽입니다. 그래서 체력만 챙기기보다 생활 습관과 감정 리듬까지 같이 보는 편이 좋습니다.', $seen);
            fs_append_unique_paragraph($introParagraphs, '건강운은 병의 유무만 보는 것이 아니라, 내가 얼마나 무리 없이 오래 갈 수 있는 생활 구조를 만들 수 있는지를 함께 봐야 정확합니다. 특히 ' . (($dms['yongshin']['element'] ?? '') !== '' ? $dms['yongshin']['element'] . ' 기운' : '도움 되는 기운') . '을 살리는 생활 습관이 회복력과 집중력에 직접 영향을 줄 가능성이 큽니다.', $seen);
            $sourceParagraphs = fs_extract_distinct_paragraphs($fortune['health'] ?? '', 7, $seen);
            fs_append_unique_paragraph($adviceParagraphs, '건강운을 잘 쓰려면 힘이 좋은 달에 일정을 몰아넣기보다, 잘되는 시기에도 수면, 식사, 운동, 휴식의 기준을 유지하는 편이 좋습니다. 몸이 보내는 작은 신호를 초기에 잡는 사람이 결국 흐름을 오래 지킬 수 있습니다.', $seen);
            fs_append_unique_paragraph($adviceParagraphs, $sameTopic
                ? $stemStory['caution'] . ' 그래서 올해는 무리해서 버티는 습관보다, 조금 일찍 쉬고 조금 빨리 정비하는 습관이 더 큰 차이를 만듭니다.'
                : $stemStory['caution'] . ' ' . $branchStory['caution'], $seen);
            break;

        case 'career':
            fs_append_unique_paragraph($introParagraphs, $year . '년 직업운은 하고 있는 일을 어떻게 키울지, 혹은 어디에 힘을 집중해야 할지가 비교적 분명해지는 흐름입니다. ' . $bestMonthsText . '에는 지원, 발표, 제안, 이직 준비처럼 바깥으로 보여 주는 움직임이 잘 맞고, ' . $careMonthsText . '에는 판을 무리하게 넓히기보다 기본기를 다지는 운영이 더 안전합니다.', $seen);
            fs_append_unique_paragraph($introParagraphs, $sameTopic
                ? '올해 일과 커리어 쪽에서는 ' . ($yearFortune['stem_sipsin'] ?? '성장') . ' 흐름이 겉과 속에서 동시에 작동합니다. 그래서 한 방향으로 꾸준히 밀어붙이는 사람이 더 오래 갑니다.'
                : '올해 일과 커리어 쪽 주제는 겉으로 ' . ($yearFortune['stem_sipsin'] ?? '성장') . ', 안쪽으로 ' . ($yearFortune['branch_sipsin'] ?? '정리') . ' 흐름이 같이 작동합니다. 그래서 보여 주는 성과와 실제 실력을 함께 키우는 사람이 더 오래 갑니다.', $seen);
            fs_append_unique_paragraph($introParagraphs, '당신의 사주에서 일은 단순히 직장을 뜻하는 것이 아니라, 어떤 방식으로 세상 속 역할을 잡고 인정받는지를 보여 줍니다. 올해는 특히 ' . ($dms['yongshin']['element'] ?? '핵심') . ' 기운을 살리는 방향의 일에서 체감 성과가 더 빨리 붙을 가능성이 큽니다.', $seen);
            $sourceParagraphs = fs_extract_distinct_paragraphs($fortune['career'] ?? '', 6, $seen);
            $studyParagraphs = fs_extract_distinct_paragraphs($fortune['study'] ?? '', 3, $seen);
            $sourceParagraphs = array_merge($sourceParagraphs, $studyParagraphs);
            fs_append_unique_paragraph($adviceParagraphs, '직업운을 잘 쓰려면 잘하는 일과 남들이 바로 알아보는 일을 최대한 겹치게 만드는 편이 좋습니다. 자격, 공부, 포트폴리오처럼 시간이 걸리는 자산을 같이 쌓아 두면 한 번 열린 기회가 더 길게 이어질 수 있습니다.', $seen);
            fs_append_unique_paragraph($adviceParagraphs, $sameTopic
                ? $stemStory['good'] . ' 흐름이 올해 여러 번 반복될 수 있으니, 잘 맞는 방식 하나를 정해서 꾸준히 밀어 주는 것이 커리어의 핵심입니다.'
                : $stemStory['good'] . ' 쪽과 ' . $branchStory['good'] . ' 쪽을 연결하는 일이 올해 커리어의 핵심입니다.', $seen);
            break;

        case 'wealth':
        default:
            fs_append_unique_paragraph($introParagraphs, $year . '년 재물운은 한 번에 크게 벌기보다 흐름을 읽고 타이밍을 맞추는 운영에서 차이가 나는 해입니다. 특히 ' . $bestMonthsText . '에는 돈이 붙는 장면이 생기기 쉽고, ' . $careMonthsText . '에는 지출과 충동 구매, 무리한 확장이 손실로 이어지지 않게 기준을 단단히 잡는 편이 좋습니다.', $seen);
            fs_append_unique_paragraph($introParagraphs, $sameTopic
                ? '올해는 겉으로도 안으로도 ' . ($yearFortune['stem_sipsin'] ?? '재물') . ' 주제가 강하게 움직입니다. 그래서 벌 기회가 들어와도 관리 기준이 약하면 실속이 빠지고, 반대로 생활 기준이 안정되면 작은 흐름도 재물로 쌓일 가능성이 큽니다.'
                : '올해 겉으로 보이는 돈의 주제는 ' . ($yearFortune['stem_sipsin'] ?? '재물') . ' 쪽이고, 생활 속 바닥 주제는 ' . ($yearFortune['branch_sipsin'] ?? '관리') . ' 쪽입니다. 그래서 벌 기회가 들어와도 관리 기준이 약하면 실속이 빠지고, 반대로 생활 기준이 안정되면 작은 흐름도 재물로 쌓일 가능성이 큽니다.', $seen);
            fs_append_unique_paragraph($introParagraphs, '당신에게 도움이 되는 ' . (($dms['yongshin']['element'] ?? '') !== '' ? $dms['yongshin']['element'] . ' 기운' : '핵심 기운') . '을 살리는 쪽은 돈을 불릴 때도, 지킬 때도 같이 중요합니다. 재물운은 수입만이 아니라 소비 습관, 지출 리듬, 불안할 때 돈을 쓰는 패턴까지 함께 봐야 정확합니다.', $seen);
            $sourceParagraphs = fs_extract_distinct_paragraphs($fortune['wealth'] ?? '', 7, $seen);
            fs_append_unique_paragraph($adviceParagraphs, '재물운을 잘 쓰려면 좋은 달에만 공격적으로 움직이고 나머지 시기에는 관리와 기록을 강화하는 편이 좋습니다. 지금 해도 되는 소비와 미뤄야 하는 소비를 분리해 두면, 흐름이 흔들릴 때도 돈이 새는 속도를 크게 줄일 수 있습니다.', $seen);
            fs_append_unique_paragraph($adviceParagraphs, $stemStory['caution'] . ' ' . $branchStory['caution'], $seen);
            break;
    }

    return [
        'title' => $meta['title'],
        'icon' => $meta['icon'],
        'desc' => $meta['desc'],
        'hero' => $meta['title'] . '을 원국과 올해 흐름을 함께 놓고 길게 읽습니다.',
        'summary_cards' => $summaryCards,
        'sections' => [
            ['title' => $meta['title'] . ' 총평', 'paragraphs' => $introParagraphs],
            ['title' => '타고난 ' . $meta['title'] . ' 흐름', 'paragraphs' => $sourceParagraphs],
            ['title' => $meta['title'] . '을 잘 쓰는 법', 'paragraphs' => $adviceParagraphs],
        ],
    ];
}

function fs_build_daeun_story(array $record) {
    $daeunData = fs_get_runtime_daeun($record);
    $current = fs_find_current_daeun($daeunData, $record);
    $nextDaeuns = fs_next_daeuns($daeunData, $current['index'] ?? -1, 2);
    $allDaeuns = $daeunData['daeuns'] ?? [];
    $sortedByScore = $allDaeuns;

    usort($sortedByScore, function ($left, $right) {
        return (int)($right['score'] ?? 0) <=> (int)($left['score'] ?? 0);
    });

    $topPeriods = array_slice($sortedByScore, 0, 3);
    $seen = [];
    $overviewParagraphs = [];
    $currentParagraphs = [];
    $futureParagraphs = [];

    if ($current) {
        fs_append_unique_paragraph($overviewParagraphs, '지금 당신은 ' . ($current['age_start'] ?? 0) . '~' . ($current['age_end'] ?? 0) . '세 대운의 흐름 안에 있습니다. 이 시기 점수는 ' . ($current['score'] ?? 50) . '점으로, 겉으로는 ' . ($current['stem_sipsin'] ?? '') . ' 주제가 드러나고 안에서는 ' . ($current['branch_sipsin'] ?? '') . ' 흐름이 계속 작동합니다.', $seen);
        fs_append_unique_paragraph($overviewParagraphs, !empty($current['is_yongshin'])
            ? '현재 대운은 당신에게 필요한 기운이 직접 닿는 편이라, 이전보다 일이 풀리는 감각과 회복력이 살아날 가능성이 큽니다. 같은 노력이라도 결과가 더 빨리 붙는 구간일 수 있습니다.'
            : '현재 대운은 무조건 편한 시기라기보다, 버틸 힘과 조절력이 동시에 필요한 구간에 가깝습니다. 잘되는 일과 답답한 일이 섞여 들어올 수 있으니, 무엇을 키우고 무엇을 줄일지 기준을 빨리 잡는 편이 좋습니다.', $seen);
        fs_append_unique_paragraph($overviewParagraphs, '대운은 하루 운세보다 훨씬 긴 흐름이기 때문에, 지금 1~2년의 기분만 보고 판단하면 놓치는 것이 많습니다. 현재 대운은 인생 판의 성격을 바꾸는 배경으로 보고, 세운과 월운은 그 위에서 움직이는 세부 장면으로 이해하는 것이 정확합니다.', $seen);
        $currentParagraphs = fs_extract_distinct_paragraphs($current['interpretation'] ?? '', 8, $seen);
    }

    foreach ($nextDaeuns as $next) {
        fs_append_unique_paragraph($futureParagraphs, ($next['age_start'] ?? 0) . '~' . ($next['age_end'] ?? 0) . '세에는 ' . fs_korean_pair($next['stem_sipsin'] ?? '', $next['branch_sipsin'] ?? '') . ' 흐름이 중심이 됩니다. 점수는 ' . ($next['score'] ?? 50) . '점이고, 지금과는 다르게 삶의 우선순위가 움직일 가능성이 큽니다.', $seen);
        foreach (fs_extract_distinct_paragraphs($next['interpretation'] ?? '', 5, $seen) as $paragraph) {
            $futureParagraphs[] = $paragraph;
        }
    }

    foreach ($topPeriods as $period) {
        fs_append_unique_paragraph($futureParagraphs, '전체 대운 중 비교적 힘을 받는 시기는 ' . ($period['age_start'] ?? 0) . '~' . ($period['age_end'] ?? 0) . '세 구간입니다. 이때는 ' . fs_korean_pair($period['stem_sipsin'] ?? '', $period['branch_sipsin'] ?? '') . ' 흐름이 맞물려 ' . fs_period_score_label($period['score'] ?? 50) . ' 쪽으로 움직일 가능성이 큽니다.', $seen);
    }

    return [
        'hero' => '지금 10년의 판과 다음 흐름까지 함께 읽습니다.',
        'summary_cards' => [
            ['label' => '대운 방향', 'value' => $daeunData['direction'] ?? '분석 중'],
            ['label' => '대운 시작 나이', 'value' => ($daeunData['start_age'] ?? 0) . '세'],
            ['label' => '현재 대운', 'value' => $current ? (($current['age_start'] ?? 0) . '~' . ($current['age_end'] ?? 0) . '세') : '확인 중'],
            ['label' => '현재 점수', 'value' => $current ? (($current['score'] ?? 50) . '점') : '확인 중'],
        ],
        'timeline' => $allDaeuns,
        'sections' => [
            ['title' => '현재 대운 총평', 'paragraphs' => fs_filter_paragraphs($overviewParagraphs)],
            ['title' => '지금 10년을 자세히 읽기', 'paragraphs' => fs_filter_paragraphs($currentParagraphs)],
            ['title' => '다음 흐름 미리 보기', 'paragraphs' => fs_filter_paragraphs($futureParagraphs)],
        ],
    ];
}

function fs_build_daily_longform_story(array $record, array $fortune, DateTimeInterface $date, $mode) {
    $categories = $fortune['categories'] ?? [];
    $sortedCategories = $categories;

    usort($sortedCategories, function ($left, $right) {
        return (int)($right['score'] ?? 0) <=> (int)($left['score'] ?? 0);
    });

    $bestCategory = $sortedCategories[0] ?? null;
    $careCategory = !empty($sortedCategories) ? $sortedCategories[count($sortedCategories) - 1] : null;
    $yearFortune = $fortune['year_fortune'] ?? [];
    $monthFortune = $fortune['month_fortune'] ?? [];
    $year = (int)$date->format('Y');
    $yearContext = fs_get_runtime_year_context($record, $year);
    $bestMonthsText = fs_month_list_text(fs_pick_months_by_score($yearContext['monthly_fortunes'] ?? [], 3)) ?: '도움 되는 달';
    $yearTone = fs_year_score_label($yearFortune['score'] ?? 50);
    $yearStemTopic = $yearFortune['stem_sipsin'] ?? '바깥 흐름';
    $yearBranchTopic = $yearFortune['branch_sipsin'] ?? '생활 흐름';
    $sameTopic = !empty($yearFortune['stem_sipsin']) && ($yearFortune['stem_sipsin'] ?? '') === ($yearFortune['branch_sipsin'] ?? '');
    $seen = [];

    $overviewParagraphs = [];
    fs_append_unique_paragraph($overviewParagraphs, $date->format('Y년 m월 d일') . ' ' . ($mode === 'tomorrow' ? '내일' : ($mode === 'pick' ? '선택한 날짜' : '오늘')) . '의 총운은 ' . ($fortune['score'] ?? 50) . '점입니다. ' . ($fortune['description'] ?? '') . '. 하루 한 장면만 보고 판단하기보다, 올해 흐름과 이번 달 흐름이 오늘 어디에서 만나는지 같이 보는 편이 더 정확합니다.', $seen);
    if (!empty($monthFortune['focus'])) {
        fs_append_unique_paragraph($overviewParagraphs, fs_trim_month_focus_text($monthFortune['focus']) . ' 그래서 하루 운세를 볼 때도 오늘만 따로 보지 말고, 이번 달 전체 리듬 안에서 내 위치를 확인하는 것이 중요합니다.', $seen);
    }
    fs_append_unique_paragraph($overviewParagraphs, '올해 전체로 보면 특히 ' . $bestMonthsText . ' 쪽에서 흐름이 부드럽습니다. 오늘이 완벽한 날이 아니더라도, 큰 흐름 안에서 어떤 역할을 맡길지 정하면 하루의 밀도는 충분히 달라질 수 있습니다.', $seen);

    $useParagraphs = [];
    if ($bestCategory) {
        fs_append_unique_paragraph($useParagraphs, '오늘 가장 힘을 받는 분야는 ' . ($bestCategory['label'] ?? '총운') . '입니다. 점수는 ' . ($bestCategory['score'] ?? 0) . '점으로, 이 분야는 미루기보다 오늘 바로 움직여 보는 편이 좋습니다. 작은 결과라도 직접 만들어 두면 다음 흐름으로 연결되기 쉽습니다.', $seen);
    }
    fs_append_unique_paragraph($useParagraphs, '큰 흐름으로 보면 ' . $year . '년은 ' . $yearTone . '에 가깝습니다. ' . ($sameTopic
        ? '겉으로도 안으로도 ' . $yearStemTopic . ' 주제가 강하게 움직이고 있습니다. '
        : '겉으로는 ' . $yearStemTopic . ' 주제가 움직이고, 생활 안에서는 ' . $yearBranchTopic . ' 주제가 바닥을 받치고 있습니다. ') . (!empty($yearFortune['is_yongshin'])
        ? '지금 필요한 기운이 같이 들어오는 해라, 오늘처럼 잘 붙는 순간에는 망설이기보다 바로 행동으로 옮기는 편이 훨씬 유리합니다.'
        : '그래서 오늘처럼 잘 붙는 장면이 보여도 한 번에 너무 많이 벌리기보다, 되는 일에 집중해 흐름을 길게 이어 가는 편이 더 안정적입니다.'), $seen);
    fs_append_unique_paragraph($useParagraphs, '오늘 운세를 잘 쓰는 방법은 모든 분야를 한 번에 챙기려 하지 않는 것입니다. 잘 붙는 한두 분야를 중심으로 일정과 감정을 정리하면, 나머지 흐름도 따라오는 경우가 많습니다.', $seen);

    $careParagraphs = [];
    if ($careCategory) {
        fs_append_unique_paragraph($careParagraphs, '조금 더 조심할 분야는 ' . ($careCategory['label'] ?? '주의 분야') . '입니다. 점수는 ' . ($careCategory['score'] ?? 0) . '점이라, 오늘은 결과를 서두르기보다 과정을 정리하는 쪽에 무게를 두는 편이 손실을 줄여 줍니다.', $seen);
    }
    fs_append_unique_paragraph($careParagraphs, '하루 운세는 좋고 나쁨을 단정하는 것이 아니라, 오늘 무엇을 먼저 하고 무엇을 늦출지 정하는 지도에 가깝습니다. 특히 감정이 올라오는 순간에는 중요한 말과 지출을 잠시 늦추는 것만으로도 흐름이 크게 달라질 수 있습니다.', $seen);
    fs_append_unique_paragraph($careParagraphs, '오늘이 약하게 느껴지는 분야는 완성보다 점검, 결론보다 준비, 확정보다 메모와 기록에 더 잘 맞습니다. 이런 운영이 내일 이후 흐름을 훨씬 편하게 만들어 줍니다.', $seen);

    return [
        'hero' => ($mode === 'tomorrow' ? '내일 하루의 흐름을 길게 읽습니다.' : ($mode === 'pick' ? '선택한 날짜의 흐름을 길게 읽습니다.' : '오늘 하루의 흐름을 길게 읽습니다.')),
        'summary_cards' => $categories,
        'sections' => [
            ['title' => '총운풀이', 'paragraphs' => $overviewParagraphs],
            ['title' => '좋게 쓰는 방법', 'paragraphs' => $useParagraphs],
            ['title' => '조심할 부분', 'paragraphs' => $careParagraphs],
        ],
    ];
}

function fs_element_relation($mine, $other) {
    $cycle = ['목','화','토','금','수'];
    $mineIndex = array_search($mine, $cycle, true);
    $otherIndex = array_search($other, $cycle, true);
    if ($mineIndex === false || $otherIndex === false) return 'neutral';
    if ($mineIndex === $otherIndex) return 'same';
    if ((($mineIndex + 1) % 5) === $otherIndex) return 'generate';
    if ((($otherIndex + 1) % 5) === $mineIndex) return 'support';
    if ((($mineIndex + 2) % 5) === $otherIndex) return 'control';
    if ((($otherIndex + 2) % 5) === $mineIndex) return 'challenge';
    return 'neutral';
}

function fs_zodiac_match_score($myBranch, $partnerBranch) {
    $pair = [$myBranch, $partnerBranch];
    sort($pair);

    foreach (SajuEngine::YUKHAP as $hap) {
        $temp = $hap;
        sort($temp);
        if ($temp === $pair) return 18;
    }

    foreach (SajuEngine::CHUNG as $chung) {
        $temp = $chung;
        sort($temp);
        if ($temp === $pair) return -14;
    }

    return 4;
}

function fs_build_compatibility(array $myRecord, array $partnerInput) {
    $myEngine = fs_build_engine_from_record($myRecord);
    $partnerEngine = new SajuEngine(
        (int)$partnerInput['birth_year'],
        (int)$partnerInput['birth_month'],
        (int)$partnerInput['birth_day'],
        (int)$partnerInput['birth_hour'],
        $partnerInput['gender'],
        $partnerInput['calendar_type']
    );

    $my = $myEngine->getResult();
    $partner = $partnerEngine->getResult();

    $myElement = $my['day_master_element'];
    $partnerElement = $partner['day_master_element'];
    $relation = fs_element_relation($myElement, $partnerElement);
    $score = 60;
    $relationLabel = '서로 다른 매력을 가진 관계';
    $relationText = '서로의 차이를 이해해 가는 과정이 중요합니다.';

    if ($relation === 'same') {
        $score += 14;
        $relationLabel = '결이 비슷한 관계';
        $relationText = '기본 정서와 반응 방식이 비슷해 빠르게 통할 가능성이 큽니다. 다만 둘 다 고집을 세우면 오래 부딪힐 수 있습니다.';
    } elseif ($relation === 'generate' || $relation === 'support') {
        $score += 18;
        $relationLabel = '서로를 살려 주는 관계';
        $relationText = '한쪽의 장점이 다른 쪽을 자연스럽게 도와주는 흐름입니다. 함께 있을수록 힘이 붙는 궁합에 가깝습니다.';
    } elseif ($relation === 'control') {
        $score -= 10;
        $relationLabel = '주도권이 부딪히는 관계';
        $relationText = '가까워질수록 누가 흐름을 잡느냐가 민감해질 수 있습니다. 역할을 분명히 나누는 것이 중요합니다.';
    } elseif ($relation === 'challenge') {
        $score -= 6;
        $relationLabel = '서로를 시험하는 관계';
        $relationText = '끌림은 있지만 감정 소모가 생기기 쉬운 궁합입니다. 속도를 맞추고 말투를 부드럽게 하는 노력이 필요합니다.';
    }

    $score += fs_zodiac_match_score($my['year_pillar']['branch'] ?? '', $partner['year_pillar']['branch'] ?? '');
    $score = fs_clamp_score($score);

    return [
        'score' => $score,
        'my' => $my,
        'partner' => $partner,
        'relation_label' => $relationLabel,
        'relation_text' => $relationText,
        'tips' => [
            '처음에는 비슷한 점보다 다른 점을 역할 분담으로 바꾸면 관계가 훨씬 편해집니다.',
            '갈등이 생기면 감정 자체보다 표현 방식과 타이밍을 먼저 조정하는 편이 좋습니다.',
            '서로에게 기대하는 역할을 빨리 말로 확인하면 오래 가는 궁합이 됩니다.',
        ],
    ];
}