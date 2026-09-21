<?php
/**
 * موتور هوشمند داخلی (قانون‌محور)
 * - تحلیل درصد پیشرفت/پسرفت روزانه، هفتگی و ماهانه
 * - دریافت دلیل انجام نشدن کارها و تشخیص عادت‌های منفی
 * - زمان‌بندی مجدد هوشمند تسک‌های عقب‌افتاده
 * - گزارش ماهانه شخصیت‌شناسی رفتاری
 * - مصاحبه استپ‌به‌استپ برای ساخت برنامه هدف‌ها (ایجنت برنامه‌ریز)
 * ساختار برای اتصال API خارجی در آینده آماده است (ai_external_call)
 */

/** نقطه اتصال API خارجی (آینده): اگر تنظیم شد، تحلیل‌ها با AI بیرونی غنی می‌شوند */
function ai_external_call(string $prompt): ?string {
    $apiUrl = setting('ai_api_url');
    $apiKey = setting('ai_api_key');
    if (!$apiUrl || !$apiKey || !function_exists('curl_init')) return null;
    try {
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
            CURLOPT_POSTFIELDS => json_encode(['prompt' => $prompt], JSON_UNESCAPED_UNICODE),
        ]);
        $r = curl_exec($ch);
        curl_close($ch);
        return $r ?: null;
    } catch (Throwable $e) { return null; }
}

/* ---------------- آمار روزانه ---------------- */

function ai_tasks_for_day(int $uid, string $jdate): array {
    [$ws, $we] = j_week_bounds(...j_parse($jdate));
    $st = db()->prepare("SELECT * FROM tasks WHERE user_id=? AND status<>'deleted' AND (
        (type IN ('daily','once') AND jdate=?) OR (type='weekly' AND week_start=?) )");
    $st->execute([$uid, $jdate, $ws]);
    return $st->fetchAll();
}

function ai_day_stats(int $uid, string $jdate): array {
    $tasks = ai_tasks_for_day($uid, $jdate);
    $done = $undone = [];
    foreach ($tasks as $t) {
        if ($t['status'] == 'done') $done[] = $t['title'];
        elseif ($t['status'] != 'deleted') $undone[] = $t['title'];
    }
    $total = count($tasks);
    $percent = $total ? round(count($done) / $total * 100) : 0;

    // میانگین پیشرفت عادت‌های ثبت‌شده امروز
    $st = db()->prepare("SELECT AVG(progress) FROM habit_logs WHERE user_id=? AND jdate=?");
    $st->execute([$uid, $jdate]);
    $habitPct = (float)($st->fetchColumn() ?: 0);

    $score = $percent;
    if ($total && $habitPct) $score = round($percent * 0.7 + $habitPct * 0.3);
    elseif (!$total) $score = round($habitPct);

    return ['total' => $total, 'done_count' => count($done), 'done' => $done, 'undone' => $undone,
            'percent' => $percent, 'habit_pct' => round($habitPct), 'score' => $score, 'tasks' => $tasks];
}

/* ---------------- آمار یک بازه + مقایسه با بازه قبل ---------------- */

function ai_range_stats(int $uid, string $start, string $end): array {
    $days = [];
    [$jy, $jm, $jd] = j_parse($start);
    $n = j_num(...j_parse($end)) - j_num(...j_parse($start)) + 1;
    $total = $done = 0;
    for ($i = 0; $i < $n; $i++) {
        $d = j_str(...j_add_days($jy, $jm, $jd, $i));
        $s = ai_day_stats($uid, $d);
        $total += $s['total']; $done += $s['done_count'];
        $days[$d] = $s;
    }
    $percent = $total ? round($done / $total * 100) : 0;

    // بازه قبل برای محاسبه رشد/پسرفت
    [$sy, $sm, $sd] = j_parse($start);
    $prevEnd = j_str(...j_add_days($sy, $sm, $sd, -1));
    $prevStart = j_str(...j_add_days($sy, $sm, $sd, -$n));
    [$psy, $psm, $psd] = j_parse($prevStart);
    $pt = $pd = 0;
    for ($i = 0; $i < $n; $i++) {
        $d = j_str(...j_add_days($psy, $psm, $psd, $i));
        $s = ai_day_stats($uid, $d);
        $pt += $s['total']; $pd += $s['done_count'];
    }
    $prevPercent = $pt ? round($pd / $pt * 100) : 0;

    // پرتکرارترین دلیل منفی در بازه
    $st = db()->prepare("SELECT category, COUNT(*) c FROM skip_reports WHERE user_id=? AND jdate BETWEEN ? AND ? GROUP BY category ORDER BY c DESC LIMIT 1");
    $st->execute([$uid, $start, $end]);
    $top = $st->fetch();
    $cats = skip_categories();
    $topNegative = $top ? ($cats[$top['category']]['label'] ?? '') : '';

    return ['total' => $total, 'done_count' => $done, 'percent' => $percent,
            'prev_percent' => $prevPercent, 'days' => $days, 'top_negative' => $topNegative];
}

/* ---------------- ثبت دلیل انجام نشدن + زمان‌بندی مجدد هوشمند ---------------- */

function ai_record_skip(int $uid, array $task, string $category, string $note): array {
    $cats = skip_categories();
    $cat = $cats[$category] ?? $cats['other'];
    [$jy, $jm, $jd] = today_jalali();
    $today = j_today_str();
    $now = new DateTime('now', new DateTimeZone(APP_TZ));

    // علامت‌گذاری تسک به‌عنوان انجام‌نشده
    db()->prepare("UPDATE tasks SET status='skipped' WHERE id=?")->execute([$task['id']]);

    // پیدا کردن بهترین روز جایگزین در هفته جاری/بعدی (کم‌تراکم‌ترین روز آینده)
    [$ws, $we] = j_week_bounds($jy, $jm, $jd);
    $candidates = [];
    for ($i = 1; $i <= 10; $i++) {
        $d = j_str(...j_add_days($jy, $jm, $jd, $i));
        $st = db()->prepare("SELECT COUNT(*) FROM tasks WHERE user_id=? AND type IN ('daily','once') AND jdate=? AND status<>'deleted'");
        $st->execute([$uid, $d]);
        $candidates[$d] = (int)$st->fetchColumn();
        $p = j_parse($d);
        if (j_day_of_week(...$p) == 6 && count($candidates) >= 4) break; // تا جمعه این هفته + چند روز
    }
    asort($candidates);
    $best = array_key_first($candidates);

    $hour = $task['hour'];
    if (!$hour) {
        $sug = ai_suggest_hour($uid, $best, $task['title']);
        $hour = $sug['hour'] ? sprintf('%02d:00', $sug['hour']) : '';
    }

    db()->prepare("INSERT INTO tasks (user_id,title,type,jdate,hour,priority,source,rescheduled_from)
                   VALUES (?,?,?,?,?,?,'ai',?)")
        ->execute([$uid, $task['title'], 'once', $best, $hour, $task['priority'], $task['id']]);
    $newId = (int)db()->lastInsertId();

    db()->prepare("INSERT INTO skip_reports (user_id,ref_type,ref_id,ref_title,jdate,hour,category,is_negative,note,rescheduled_to)
                   VALUES (?,'task',?,?,?,?,?,?,?,?)")
        ->execute([$uid, $task['id'], $task['title'], $today, $task['hour'] ?: $now->format('H:i'),
                   $category, $cat['negative'], $note, $best]);

    // تشخیص الگوی تکراری: این عنوان در همین هفته چند بار عقب افتاده؟
    $st = db()->prepare("SELECT COUNT(*) FROM skip_reports WHERE user_id=? AND ref_title=? AND jdate BETWEEN ? AND ?");
    $st->execute([$uid, $task['title'], $ws, $we]);
    $repeat = (int)$st->fetchColumn();

    $msg = "هوش مصنوعی تسک «" . $task['title'] . "» را برای " . j_format($best, false) . " دوباره برنامه‌ریزی کرد.";
    if ($repeat >= 2) {
        $msg .= "\n این " . fa_num($repeat) . "مین بار است که این کار عقب می‌افتد — یک الگوی رفتاری در حال شکل‌گیری است. در گزارش ماهانه تحلیلش می‌کنیم.";
    }

    notify_create($uid, 'زمان‌بندی مجدد هوشمند', $msg, 'info', 'planner.php?d=' . urlencode($best));
    return ['rescheduled_to' => $best, 'repeat' => $repeat, 'negative' => $cat['negative']];
}

/* ---------------- پیشنهاد ساعت مناسب ---------------- */

function ai_suggest_hour(int $uid, string $jdate, string $title = ''): array {
    $st = db()->prepare("SELECT wake_time, sleep_time FROM users WHERE id=?");
    $st->execute([$uid]);
    $u = $st->fetch();
    $wake = $u['wake_time'] ?: '07:00';
    $sleep = $u['sleep_time'] ?: '23:00';
    $wakeH = (int)substr($wake, 0, 2);
    $sleepH = (int)substr($sleep, 0, 2);
    if ($sleepH <= $wakeH) $sleepH = 24;

    $busy = array_fill($wakeH + 1, max(1, $sleepH - $wakeH - 1), 0);
    $st = db()->prepare("SELECT hour FROM plan_slots WHERE user_id=? AND jdate=? AND title<>''");
    $st->execute([$uid, $jdate]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $h) if (isset($busy[$h])) $busy[$h]++;
    $st = db()->prepare("SELECT hour FROM tasks WHERE user_id=? AND jdate=? AND hour<>'' AND status<>'deleted'");
    $st->execute([$uid, $jdate]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $hh) {
        $h = (int)substr($hh, 0, 2);
        if (isset($busy[$h])) $busy[$h]++;
    }

    $best = 0; $bestLoad = 99;
    foreach ($busy as $h => $load) {
        if ($load < $bestLoad) { $bestLoad = $load; $best = $h; }
    }
    $why = $bestLoad == 0 ? 'این ساعت در برنامه شما کاملاً آزاد است.' : 'این ساعت کمترین تداخل را با برنامه فعلی شما دارد.';
    return ['hour' => $best ?: 0, 'why' => $why, 'load' => $bestLoad];
}

/* ---------------- گزارش تحلیلی ماهانه ---------------- */

function ai_monthly_report(int $uid, int $jy, int $jm): array {
    $monthDays = jalali_month_days($jy, $jm);
    $start = j_str($jy, $jm, 1);
    $end = j_str($jy, $jm, $monthDays);

    // جمع‌آوری یک‌جای تسک‌های ماه
    $st = db()->prepare("SELECT * FROM tasks WHERE user_id=? AND status<>'deleted'");
    $st->execute([$uid]);
    $all = $st->fetchAll();

    $total = $done = $skipped = 0;
    $byWeek = []; $byHourDone = []; $byHourMissed = []; $dailySeries = [];
    $weeks = j_month_weeks($jy, $jm);
    foreach ($weeks as $wi => $wdays) { $byWeek[$wi] = ['total' => 0, 'done' => 0]; }

    $dayOfWeekMissed = array_fill(0, 7, 0);

    foreach ($all as $t) {
        $match = false;
        if (in_array($t['type'], ['daily', 'once']) && $t['jdate'] >= $start && $t['jdate'] <= $end) $match = true;
        if ($t['type'] == 'weekly' && $t['week_start'] >= $start && $t['week_start'] <= $end) $match = true;
        if (!$match) continue;
        $total++;
        if ($t['status'] == 'done') {
            $done++;
            if ($t['hour'] !== '') { $h = (int)substr($t['hour'], 0, 2); $byHourDone[$h] = ($byHourDone[$h] ?? 0) + 1; }
        } elseif ($t['status'] == 'skipped') {
            $skipped++;
            if ($t['hour'] !== '') { $h = (int)substr($t['hour'], 0, 2); $byHourMissed[$h] = ($byHourMissed[$h] ?? 0) + 1; }
            $p = j_parse($t['jdate'] ?: $t['week_start']);
            if ($p) $dayOfWeekMissed[j_day_of_week(...$p)]++;
        }
        // هفته متعلق
        $ref = $t['type'] == 'weekly' ? $t['week_start'] : $t['jdate'];
        foreach ($weeks as $wi => $wdays) {
            if (in_array($ref, $wdays)) {
                $byWeek[$wi]['total']++;
                if ($t['status'] == 'done') $byWeek[$wi]['done']++;
                break;
            }
        }
    }

    // سری روزانه برای نمودار
    for ($d = 1; $d <= $monthDays; $d++) {
        $ds = ai_day_stats($uid, j_str($jy, $jm, $d));
        $dailySeries[] = ['day' => $d, 'percent' => $ds['total'] ? $ds['percent'] : 0, 'total' => $ds['total']];
    }

    $percent = $total ? round($done / $total * 100) : 0;

    // تحلیل دلایل
    $st = db()->prepare("SELECT category, is_negative, COUNT(*) c FROM skip_reports
                         WHERE user_id=? AND jdate BETWEEN ? AND ? GROUP BY category ORDER BY c DESC");
    $st->execute([$uid, $start, $end]);
    $reasons = $st->fetchAll();
    $cats = skip_categories();
    $skipTotal = array_sum(array_column($reasons, 'c')) ?: 1;
    $negCount = 0; $posCount = 0;
    $reasonRows = [];
    foreach ($reasons as $r) {
        if ($r['is_negative']) $negCount += $r['c']; else $posCount += $r['c'];
        $reasonRows[] = ['category' => $r['category'], 'label' => $cats[$r['category']]['label'] ?? $r['category'],
                         'count' => $r['c'], 'negative' => (bool)$r['is_negative'],
                         'percent' => round($r['c'] / $skipTotal * 100)];
    }
    $negPercent = round($negCount / $skipTotal * 100);

    // بدترین ساعت روز
    $worstHour = null; $worstRate = -1;
    foreach ($byHourMissed as $h => $m) {
        $d = $byHourDone[$h] ?? 0;
        $rate = $m / ($m + $d);
        if ($m >= 1 && $rate > $worstRate) { $worstRate = $rate; $worstHour = $h; }
    }

    // بدترین روز هفته
    $worstDow = null; $maxMiss = 0;
    foreach ($dayOfWeekMissed as $w => $m) if ($m > $maxMiss) { $maxMiss = $m; $worstDow = $w; }

    // عادت‌ها در این ماه
    $st = db()->prepare("SELECT h.id, h.title, AVG(l.progress) avg_p, COUNT(l.id) cnt
                         FROM habits h LEFT JOIN habit_logs l ON l.habit_id=h.id AND l.jdate BETWEEN ? AND ?
                         WHERE h.user_id=? AND h.archived=0 GROUP BY h.id");
    $st->execute([$start, $end, $uid]);
    $habitRows = [];
    foreach ($st->fetchAll() as $r) {
        $habitRows[] = ['title' => $r['title'], 'avg' => round((float)($r['avg_p'] ?? 0)), 'days' => (int)$r['cnt']];
    }
    usort($habitRows, fn($a, $b) => $b['avg'] <=> $a['avg']);
    $bestHabit = $habitRows[0] ?? null;
    $worstHabit = $habitRows ? end($habitRows) : null;

    // رشد/پسرفت نسبت به ماه قبل
    [$py, $pm] = $jm == 1 ? [$jy - 1, 12] : [$jy, $jm - 1];
    $pdays = jalali_month_days($py, $pm);
    $prev = ai_range_stats($uid, j_str($py, $pm, 1), j_str($py, $pm, $pdays));
    $delta = $prev['total'] ? $percent - $prev['percent'] : 0;

    // شخصیت رفتاری
    $st = db()->prepare("SELECT * FROM personality_results WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $st->execute([$uid]);
    $ptest = $st->fetch();
    $behavior = ai_behavior_type($reasonRows, $percent);
    $ptypeLabel = $ptest ? ($ptest['ptype'] ?: $behavior['label']) : $behavior['label'];

    // عادت‌های منفی غالب (الگوهای رفتاری)
    $patterns = [];
    foreach ($reasonRows as $r) {
        if (!$r['negative'] || $r['percent'] < 15) continue;
        $patterns[] = ai_pattern_text($r['category'], $r['percent'], $r['count']);
    }

    // پیشنهادها
    $advice = [];
    if ($worstHour !== null) $advice[] = ' بیشترین ریزش کارهای شما حدود ساعت ' . fa_num($worstHour) . ' است؛ تسک‌های مهم را در ساعات دیگر بگذارید.';
    if ($worstDow !== null && $maxMiss >= 2) $advice[] = 'روزهای ' . j_weekday_name($worstDow) . ' بیشترین کارهای انجام‌نشده را دارید؛ بار آن روز را سبک‌تر کنید.';
    foreach ($reasonRows as $r) {
        if ($r['negative'] && $r['percent'] >= 25) {
            $advice[] = ai_advice_for($r['category']);
        }
    }
    if ($percent >= 80) $advice[] = ' ثبات عالی! سطح فعلی را حفظ کنید و یک هدف بلندپروازانه‌تر اضافه کنید.';
    if ($worstHabit && $worstHabit['avg'] < 40 && $worstHabit['avg'] > 0) $advice[] = ' عادت «' . $worstHabit['title'] . '» ضعیف‌ترین عادت شماست؛ هدف روزانه‌اش را کوچک‌تر کنید تا زنجیره قطع نشود.';

    $summary = "در " . j_month_name($jm) . ' ' . fa_num($jy) . ": " . fa_num($done) . " از " . fa_num($total)
             . " کار انجام شد (" . pct($percent) . ").\n"
             . ($delta >= 0 ? ' رشد نسبت به ماه قبل: ' : ' پسرفت نسبت به ماه قبل: ') . fa_num(abs($delta)) . " واحد.\n"
             . ' سهم دلایل منفی در عقب‌ماندن‌ها: ' . pct($negPercent) . ".\n"
             . ' تیپ رفتاری این ماه: ' . $ptypeLabel;

    return [
        'total' => $total, 'done' => $done, 'skipped' => $skipped, 'percent' => $percent,
        'delta' => $delta, 'by_week' => $byWeek, 'daily_series' => $dailySeries,
        'reasons' => $reasonRows, 'neg_percent' => $negPercent, 'pos_percent' => 100 - $negPercent,
        'worst_hour' => $worstHour, 'worst_dow' => $worstDow, 'hour_done' => $byHourDone, 'hour_missed' => $byHourMissed,
        'habits' => $habitRows, 'best_habit' => $bestHabit, 'worst_habit' => $worstHabit,
        'personality_test' => $ptest, 'behavior' => $behavior, 'ptype_label' => $ptypeLabel,
        'patterns' => $patterns, 'advice' => $advice, 'summary' => $summary,
        'prev_percent' => $prev['percent'],
    ];
}

function ai_pattern_text(string $cat, int $percent, int $count): string {
    $map = [
        'friends' => "الگوی «محدود کردن برنامه‌های خود برای دیگران»: $count بار برنامه‌تان را به‌خاطر دوستان/بیرون رفتن لغو کردید ($percent٪ دلایل).",
        'family'  => "تداخل برنامه با خانواده: $count بار ($percent٪).",
        'fatigue' => "الگوی خستگی: $count بار به‌خاطر بی‌حالی کار نکردید ($percent٪) — احتمالاً بار روزانه زیاد است.",
        'lazy'    => "الگوی بی‌انگیزگی: $count بار ($percent٪) — هدف‌ها را کوچک‌تر و پاداش‌دار کنید.",
        'procrast'=> "الگوی به‌تعویق‌اندازی: $count بار ($percent٪) — قانون ۵ دقیقه را امتحان کنید.",
        'forget'  => "فراموشی: $count بار ($percent٪) — یادآورها را جدی بگیرید.",
        'urgent'  => "کارهای فوری: $count بار ($percent٪) — زمان واکنش به فوریت‌ها را محدود کنید.",
        'work'    => "فشار کار/مطالعه: $count بار ($percent٪).",
        'sick'    => "بیماری: $count بار ($percent٪).",
        'other'   => "دلایل دیگر: $count بار ($percent٪).",
    ];
    return $map[$cat] ?? '';
}

function ai_advice_for(string $cat): string {
    $map = [
        'friends'  => ' پیشنهاد: قبل از قبول پیشنهاد بیرون‌رفتن، برنامه امروزتان را چک کنید؛ جواب را ۱۰ دقیقه به تأخیر بیندازید.',
        'fatigue'  => ' پیشنهاد: تسک‌های سنگین را به ساعات پرانرژی صبح منتقل کنید و بعدازظهر کار سبک بگذارید.',
        'lazy'     => ' پیشنهاد: هر تسک را به یک گام ۵ دقیقه‌ای تبدیل کنید؛ شروع‌کردن سخت‌ترین بخش است.',
        'procrast' => ' پیشنهاد: برای تسک‌های عقب‌افتاده «مهلت مصنوعی» زودتر از موعد واقعی بگذارید.',
        'forget'   => ' پیشنهاد: برای تسک‌های مهم حتماً ساعت تعیین کنید تا یادآور فعال شود.',
        'urgent'   => ' پیشنهاد: روزی یک «بلوک بدون وقفه» ۹۰ دقیقه‌ای در برنامه قفل کنید.',
        'work'     => ' پیشنهاد: مرز بین کار اجباری و اهداف شخصی را مشخص کنید؛ حداقل یک بلوک روزانه فقط برای خودتان.',
        'sick'     => ' پیشنهاد: روزهای کسالت فقط یک عادت سبک را حفظ کنید تا زنجیره قطع نشود.',
        'other'    => ' پیشنهاد: دلیل را با جزئیات بنویسید تا الگوی پنهانش در گزارش‌های بعدی پیدا شود.',
    ];
    return $map[$cat] ?? '';
}

function ai_behavior_type(array $reasonRows, int $percent): array {
    $get = function ($c) use ($reasonRows) {
        foreach ($reasonRows as $r) if ($r['category'] === $c) return $r['percent'];
        return 0;
    };
    $social = $get('friends') + $get('family');
    $avoid = $get('lazy') + $get('procrast') + $get('fatigue');
    $busy = $get('urgent') + $get('work');

    if ($percent >= 80) return ['label' => 'منظم و پایدار', 'key' => 'steady',
        'desc' => 'شما ثبات بالایی در اجرای برنامه دارید. تمرکز بعدی: اهداف بزرگ‌تر.'];
    if ($social >= 40) return ['label' => 'اجتماعیِ در دسترس دیگران', 'key' => 'social',
        'desc' => 'انرژی شما از ارتباط با دیگران می‌آید، اما برنامه‌های شخصی‌تان قربانی درخواسته‌های دیگران می‌شود. مهارت «نه گفتن محترمانه» را تمرین کنید.'];
    if ($avoid >= 40) return ['label' => 'شروعگرِ ناتمام', 'key' => 'avoidant',
        'desc' => 'شروع‌کردن برایتان سخت است و انگیزه زود افت می‌کند. گام‌های کوچک و پاداش فوری معجزه می‌کند.'];
    if ($busy >= 40) return ['label' => 'پرمشغله بدون اولویت', 'key' => 'busy',
        'desc' => 'وقت شما پر است اما نه لزوماً با اهداف خودتان. اولویت‌بندی بی‌رحمانه لازم است.'];
    if ($percent >= 50) return ['label' => 'در مسیر رشد', 'key' => 'growing',
        'desc' => 'روند شما مثبت است؛ با حذف یک عادت منفی غالب، جهش بزرگی خواهید کرد.'];
    return ['label' => 'نیازمند ساختار تازه', 'key' => 'rebuild',
        'desc' => 'برنامه فعلی با سبک زندگی شما هماهنگ نیست؛ با ایجنت برنامه‌ریز یک ساختار ساده‌تر بسازید.'];
}

/* ---------------- مصاحبه استپ‌به‌استپ ایجنت برنامه‌ریز ---------------- */

function ai_interview_questions(): array {
    return [
        'این هدف دقیقاً چه خروجی نهایی دارد؟ «تمام شدن» را با یک جمله مشخص تعریف کنید.',
        'الان کجای مسیر هستید؟ چه چیزهایی از قبل آماده یا انجام شده است؟',
        'چه مهارت، ابزار یا منبعی نیاز دارید که هنوز ندارید؟',
        'در هفته چند ساعت واقع‌بینانه می‌توانید برای این هدف وقت بگذارید؟ (فقط عدد)',
        'بزرگ‌ترین مانعی که تا امروز جلویتان را گرفته چه بوده؟',
    ];
}

/** وضعیت مصاحبه برای یک هدف: سوال جاری بر اساس تعداد پاسخ‌های کاربر */
function ai_interview_state(int $uid, int $goalId): array {
    $st = db()->prepare("SELECT * FROM chats WHERE user_id=? AND goal_id=? ORDER BY id");
    $st->execute([$uid, $goalId]);
    $msgs = $st->fetchAll();
    $answers = [];
    $qs = ai_interview_questions();
    $qi = 0;
    foreach ($msgs as $m) {
        if ($m['sender'] === 'user' && $qi < count($qs)) { $answers[$qi] = $m['message']; $qi++; }
    }
    return ['messages' => $msgs, 'answers' => $answers, 'next_q' => $qi < count($qs) ? $qs[$qi] : null,
            'question_index' => $qi, 'questions' => $qs];
}

/** تولید برنامه گام‌به‌گام از پاسخ‌های مصاحبه */
function ai_build_goal_plan(int $uid, int $goalId): array {
    $st = db()->prepare("SELECT * FROM goals WHERE id=? AND user_id=?");
    $st->execute([$goalId, $uid]);
    $goal = $st->fetch();
    if (!$goal) return [];

    $state = ai_interview_state($uid, $goalId);
    $a = $state['answers'];
    $title = $goal['title'];
    $periodDays = goal_period_days($goal['period']);
    $hoursPerWeek = max(1, min(60, (int)preg_replace('/\D/', '', $a[3] ?? '5') ?: 5));

    // انتخاب قالب گام‌ها بر اساس کلیدواژه‌های هدف
    $t = mb_strtolower($title . ' ' . ($a[0] ?? ''));
    if (preg_match('/برنامه|اپ|سایت|وب|نرم|اپلیکیشن|php|app|site|code|کد/u', $t)) {
        $tmpl = ['تحلیل نیازها و نوشتن لیست امکانات (MVP)', 'طراحی ساختار دیتابیس و معماری',
                 'پیاده‌سازی هسته و امکانات اصلی', 'طراحی رابط کاربری و تجربه کاربری',
                 'تست، رفع باگ و بهینه‌سازی', 'انتشار نسخه اول و بازاریابی'];
    } elseif (preg_match('/فیلم|ویدیو|کلیپ|مستند|عکس|تدوین|فیلمبرداری/u', $t)) {
        $tmpl = ['نوشتن ایده و فیلم‌نامه اولیه', 'استوری‌بورد و طراحی صحنه‌ها',
                 'تهیه تجهیزات و لوکیشن', 'فیلمبرداری بخش اول', 'فیلمبرداری بخش دوم',
                 'تدوین و اصلاح رنگ', 'خروجی نهایی و انتشار'];
    } elseif (preg_match('/ورزش|بدنسازی|لاغر|تناسب|دویدن|فوتبال|باشگاه|وزن/u', $t)) {
        $tmpl = ['اندازه‌گیری وضعیت فعلی و تعیین اعداد هدف', 'ساخت برنامه تمرینی هفته‌های اول (سبک)',
                 'افزایش تدریجی شدت تمرین', 'تثبیت روتین تغذیه و خواب', 'ارزیابی میانه مسیر و اصلاح برنامه',
                 'فاز نهایی و رسیدن به عدد هدف'];
    } elseif (preg_match('/زبان|مطالعه|یادگیری|دوره|آزمون|کنکور|مدرک|انگلیسی/u', $t)) {
        $tmpl = ['تعیین سطح و انتخاب منابع', 'برنامه مطالعاتی روزانه سبک (شروع زنجیره)',
                 'عمیق‌شدن در سرفصل‌های اصلی', 'تمرین فعال و تست‌زنی', 'مرور فشرده و رفع اشکال', 'ارزیابی نهایی'];
    } else {
        $tmpl = ['شفاف‌سازی خروجی و معیار موفقیت', 'شکستن هدف به زیربخش‌ها',
                 'اجرای فاز اول', 'اجرای فاز میانی', 'اجرای فاز نهایی', 'بازبینی و جمع‌بندی'];
    }

    $n = count($tmpl);
    $stepDays = max(1, intdiv($periodDays, $n));
    [$jy, $jm, $jd] = j_parse($goal['start_jdate'] ?: j_today_str());

    db()->prepare("DELETE FROM goal_steps WHERE goal_id=? AND user_id=?")->execute([$goalId, $uid]);
    $ins = db()->prepare("INSERT INTO goal_steps (goal_id,user_id,step_order,title,due_jdate) VALUES (?,?,?,?,?)");
    for ($i = 0; $i < $n; $i++) {
        $due = j_add_days($jy, $jm, $jd, $stepDays * ($i + 1));
        $ins->execute([$goalId, $uid, $i + 1, $tmpl[$i], j_str(...$due)]);
    }

    // بروزرسانی هدف
    $end = j_add_days($jy, $jm, $jd, $periodDays);
    db()->prepare("UPDATE goals SET end_jdate=?, status='planned' WHERE id=?")
        ->execute([j_str(...$end), $goalId]);

    // پیام تحلیلی ایجنت
    $obstacle = $a[4] ?? '';
    $msg = " برنامه «$title» در " . ($n) . " گام ساخته شد و مهلت آن تا " . j_format(j_str(...$end), false) . " است.\n"
         . " با هفته‌ای " . fa_num($hoursPerWeek) . " ساعت، هر گام حدود " . fa_num($stepDays) . " روز فرصت دارد.\n";
    if ($obstacle) $msg .= " مانع اصلی که گفتید («$obstacle») را در نظر گرفتم: برای هر گام، اولین حرکت را آنقدر کوچک کردم که شروعش بدون بهانه ممکن باشد.\n";
    $msg .= " هر گام را که تمام کردید تیک بزنید؛ درصد هدف به‌صورت خودکار به‌روز می‌شود و در بازه‌های مشخص یادآوری می‌گیرید.";

    db()->prepare("INSERT INTO chats (user_id,agent_id,goal_id,sender,message) VALUES (?,0,?,'agent',?)")
        ->execute([$uid, $goalId, $msg]);

    notify_create($uid, 'برنامه هدف آماده شد', 'برنامه «' . $title . '» در ' . fa_num($n) . ' گام ساخته شد. از بخش اهداف ببینید.', 'success', 'goals.php');
    return $tmpl;
}

/* ---------------- تست شخصیت ---------------- */

function personality_questions(): array {
    return [
        'وقتی یک کار مهم دارم، ترجیح می‌دهم…',
        'آخر هفته ایده‌آل من…',
        'وقتی برنامه‌ام با پیشنهاد دوستان تداخل می‌کند…',
        'برای شروع یک پروژه بزرگ…',
        'اگر یک روز را از دست بدهم…',
        'محیط کار ایده‌آل من…',
        'وقتی خسته‌ام…',
        'موفقیت برای من یعنی…',
    ];
}

function personality_options(): array {
    // هر گزینه یک بُعد را تقویت می‌کند: planner منظم، flexible خلاق، social اجتماعی، focused متمرکز
    return [
        [['t' => 'از چند روز قبل برنامه‌ریزی کنم', 'd' => 'planner'], ['t' => 'لحظه آخر، با انرژی شروع کنم', 'd' => 'flexible'],
         ['t' => 'با یک دوست انجامش بدهم', 'd' => 'social'], ['t' => 'تنها و بدون وقفه کار کنم', 'd' => 'focused']],
        [['t' => 'طبق برنامه شخصی‌ام پیش بروم', 'd' => 'planner'], ['t' => 'هرچه پیش آمد، خوش بگذرد', 'd' => 'flexible'],
         ['t' => 'با دوستان و خانواده بیرون بروم', 'd' => 'social'], ['t' => 'روی پروژه شخصی‌ام کار کنم', 'd' => 'focused']],
        [['t' => 'برنامه‌ام را حفظ می‌کنم و محترمانه نه می‌گویم', 'd' => 'planner'], ['t' => 'بستگی به حالم دارد', 'd' => 'flexible'],
         ['t' => 'معمولاً برنامه‌ام را تغییر می‌دهم و می‌روم', 'd' => 'social'], ['t' => 'اگر کار مهمی نداشته باشم، می‌روم', 'd' => 'focused']],
        [['t' => 'اول نقشه کامل می‌کشم', 'd' => 'planner'], ['t' => 'وسط کار مسیر را پیدا می‌کنم', 'd' => 'flexible'],
         ['t' => 'از دیگران ایده و کمک می‌گیرم', 'd' => 'social'], ['t' => 'بی‌سر و صدا شروع می‌کنم و ادامه می‌دهم', 'd' => 'focused']],
        [['t' => 'جبران می‌کنم و برنامه فردا را اصلاح می‌کنم', 'd' => 'planner'], ['t' => 'به خودم سخت نمی‌گیرم', 'd' => 'flexible'],
         ['t' => 'با کسی درددل می‌کنم و انرژی می‌گیرم', 'd' => 'social'], ['t' => 'تمرکزم به هم می‌ریزد و سریع برمی‌گردم', 'd' => 'focused']],
        [['t' => 'منظم، ساکت و قابل پیش‌بینی', 'd' => 'planner'], ['t' => 'متنوع و هر جا که ایده بیاید', 'd' => 'flexible'],
         ['t' => 'شلوغ و پر از آدم', 'd' => 'social'], ['t' => 'کاملاً خصوصی و عمیق', 'd' => 'focused']],
        [['t' => 'استراحت کوتاه و برگشت به برنامه', 'd' => 'planner'], ['t' => 'کار را ول می‌کنم تا حالش بیاید', 'd' => 'flexible'],
         ['t' => 'با یک دوست صحبت می‌کنم', 'd' => 'social'], ['t' => 'تنها شارژ می‌شوم', 'd' => 'focused']],
        [['t' => 'رسیدن طبق برنامه به اهدافم', 'd' => 'planner'], ['t' => 'تجربه‌های تازه و متنوع', 'd' => 'flexible'],
         ['t' => 'رابطه‌های قوی و اثرگذار بودن', 'd' => 'social'], ['t' => 'تسلط عمیق بر کارم', 'd' => 'focused']],
    ];
}

function ai_personality_result(array $answers): array {
    $scores = ['planner' => 0, 'flexible' => 0, 'social' => 0, 'focused' => 0];
    $opts = personality_options();
    foreach ($answers as $qi => $oi) {
        if (isset($opts[$qi][$oi])) $scores[$opts[$qi][$oi]['d']]++;
    }
    arsort($scores);
    $keys = array_keys($scores);
    $primary = $keys[0]; $secondary = $keys[1];

    $labels = [
        'planner' => ['منظم و برنامه‌ریز', 'شما با ساختار پیشرفت می‌کنید. بزرگ‌ترین ریسک شما کمال‌گرایی و شکنندگی برنامه با یک وقفه کوچک است.',
                      ['بعد از هر وقفه، برنامه را «بازنویسی» کنید نه «رها».', 'یک بلوک آزاد روزانه برای کارهای پیش‌بینی‌نشده نگه دارید.']],
        'flexible' => ['خلاق و منعطف', 'انرژی شما نوسانی است؛ در اوج، عالی کار می‌کنید و در افت، چیزی نه. ثبات از گام‌های کوچک می‌آید.',
                       ['قانون زنجیره: هر روز فقط یک تیک کوچک، حتی در روزهای بد.', 'کارهای مهم را به ساعات اوج انرژی‌تان بچسبانید.']],
        'social' => ['اجتماعی و ارتباطی', 'شما با آدم‌ها زنده‌اید — این قدرت شماست، اما بزرگ‌ترین تهدید برنامه‌تان هم درخواست‌های دیگران است.',
                     ['قبل از هر «بله» به دیگران، تقویم امروزتان را نگاه کنید.', 'برای کارهای شخصی‌تان هم مثل قرار ملاقات، وقت قفل کنید.']],
        'focused' => ['متمرکز و عمیق', 'وقتی غرق کار می‌شوید بی‌نظیرید، اما شروع‌کردن برایتان سنگین است و انزوای زیاد انرژی‌تان را می‌گیرد.',
                      ['شروع هر جلسه کاری را با یک گام ۵ دقیقه‌ای تعریف کنید.', 'هفته‌ای یک جلسه کاری مشترک با دیگران داشته باشید.']],
    ];
    [$label, $desc, $tips] = $labels[$primary];
    $label2 = $labels[$secondary][0];

    return [
        'type' => $primary, 'label' => $label, 'secondary' => $label2, 'desc' => $desc,
        'tips' => $tips, 'scores' => $scores,
        'interrupt_risk' => $scores['social'] >= 5 ? 'بالا' : ($scores['planner'] <= 2 ? 'متوسط' : 'کم'),
    ];
}

/* ---------------- تحلیل ایجنت (داشبورد مربی) ---------------- */

function ai_agent_analysis(int $uid): array {
    [$jy, $jm, $jd] = today_jalali();
    $monthDays = jalali_month_days($jy, $jm);
    $start = j_str($jy, $jm, 1);
    $today = j_today_str();
    $todayStats = ai_day_stats($uid, $today);
    $month = ai_range_stats($uid, $start, $today);

    $st = db()->prepare("SELECT * FROM personality_results WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $st->execute([$uid]);
    $ptest = $st->fetch();

    $st = db()->prepare("SELECT * FROM goals WHERE user_id=? AND status IN ('active','planned')");
    $st->execute([$uid]);
    $goals = $st->fetchAll();

    $suggestions = [];
    if ($month['percent'] < 50 && $month['total'] > 3) {
        $suggestions[] = 'درصد انجام ماه زیر ۵۰٪ است؛ پیشنهاد می‌کنم تعداد تسک‌های روزانه را به ۳ مورد کلیدی کاهش دهید.';
    }
    if ($month['top_negative']) {
        $suggestions[] = 'عامل اصلی عقب‌ماندن شما «' . $month['top_negative'] . '» بوده است. یک راهکار اختصاصی برایش در گزارش ماهانه هست.';
    }
    foreach ($goals as $g) {
        if (!$g['end_jdate']) continue;
        $ep = j_parse($g['end_jdate']);
        $sp = j_parse($g['start_jdate'] ?: $today);
        if (!$ep || !$sp) continue;
        $span = max(1, j_num(...$ep) - j_num(...$sp));
        $elapsed = j_num($jy, $jm, $jd) - j_num(...$sp);
        $expected = round(min(1, $elapsed / $span) * 100);
        if ($g['progress'] < $expected - 15) {
            $suggestions[] = 'هدف «' . $g['title'] . '» از خط انتظار (' . pct($expected) . ') عقب است (الان ' . pct($g['progress']) . '). یک گام کوچک امروزش را همین الان تیک بزنید.';
        }
    }
    if ($ptest) {
        $suggestions[] = 'بر اساس تست شخصیت («' . $ptest['ptype'] . '»)، ' . ai_advice_for_type($ptest['ptype']);
    }
    if (!$suggestions) $suggestions[] = 'اوضاع خوب است! سطح فعلی را حفظ کنید و برای چالش بعدی آماده شوید.';

    return ['today' => $todayStats, 'month' => $month, 'ptest' => $ptest, 'goals' => $goals, 'suggestions' => $suggestions];
}

function ai_advice_for_type(string $ptype): string {
    $map = [
        'منظم و برنامه‌ریز' => 'بعد از هر وقفه برنامه را بازنویسی کنید، رها نکنید.',
        'خلاق و منعطف' => 'زنجیره تیک‌های کوچک روزانه را حتی در روزهای بد حفظ کنید.',
        'اجتماعی و ارتباطی' => 'قبل از هر «بله» به دیگران، تقویم‌تان را چک کنید.',
        'متمرکز و عمیق' => 'شروع جلسات کاری را با گام ۵ دقیقه‌ای آسان کنید.',
    ];
    return $map[$ptype] ?? 'الگوهای گزارش ماهانه را دنبال کنید.';
}
