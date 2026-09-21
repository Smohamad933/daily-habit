<?php
/**
 * تبدیل تقویم جلالی (شمسی) ↔ میلادی — الگوریتم استاندارد jalaali
 * پشتیبانی از ماه‌های ۲۹، ۳۰ و ۳۱ روزه و سال‌های کبیسه
 */

if (!function_exists('jalali_div')) {
    function jalali_div($a, $b) { return (int)($a / $b); }
    function jalali_mod($a, $b) { return $a - (int)($a / $b) * $b; }
}

/** محاسبه مشخصات سال جلالی: کبیسه و روز اول فروردین */
function jal_cal($jy) {
    $breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181,
               1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    $bl = count($breaks);
    $gy = $jy + 621;
    $leapJ = -14;
    $jp = $breaks[0];
    $jump = 0;
    for ($i = 1; $i < $bl; $i++) {
        $jm = $breaks[$i];
        $jump = $jm - $jp;
        if ($jy < $jm) break;
        $leapJ = $leapJ + jalali_div($jump, 33) * 8 + jalali_div(jalali_mod($jump, 33), 4);
        $jp = $jm;
    }
    $n = $jy - $jp;
    $leapJ = $leapJ + jalali_div($n, 33) * 8 + jalali_div(jalali_mod($n, 33) + 3, 4);
    if (jalali_mod($jump, 33) == 4 && $jump - $n == 4) $leapJ += 1;
    $leapG = jalali_div($gy, 4) - jalali_div((jalali_div($gy, 100) + 1) * 3, 4) - 150;
    $march = 20 + $leapJ - $leapG;
    if ($jump - $n < 6) $n = $n - $jump + jalali_div($jump + 4, 33) * 33;
    $leap = jalali_mod(jalali_mod($n + 1, 33) - 1, 4);
    if ($leap == -1) $leap = 4;
    return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
}

/** تعداد روزهای هر ماه شمسی: ۶ ماه اول ۳۱، پنج ماه بعدی ۳۰، اسفند ۲۹ یا ۳۰ */
function jalali_month_days($jy, $jm) {
    if ($jm <= 6) return 31;
    if ($jm <= 11) return 30;
    $r = jal_cal($jy);
    return ($r['leap'] == 0) ? 30 : 29;
}

/** سال کبیسه شمسی: شاخص کبیسه صفر باشد (مثل ۱۳۹۹، ۱۴۰۳، ۱۴۰۸) */
function jalali_is_leap($jy) { return jal_cal($jy)['leap'] == 0; }

/** تبدیل جلالی به عدد روز ژولینی */
function j2d($jy, $jm, $jd) {
    $r = jal_cal($jy);
    return g2d($r['gy'], 3, $r['march']) + ($jm - 1) * 31 - jalali_div($jm, 7) * ($jm - 7) + $jd - 1;
}

/** تبدیل میلادی به عدد روز */
function g2d($gy, $gm, $gd) {
    $d = jalali_div(($gy + jalali_div($gm - 8, 6) + 100100) * 1461, 4)
       + jalali_div(153 * jalali_mod($gm + 9, 12) + 2, 5)
       + $gd - 34840408;
    $d = $d - jalali_div(jalali_div($gy + 100100 + jalali_div($gm - 8, 6), 100) * 3, 4) + 752;
    return $d;
}

/** تبدیل عدد روز به میلادی */
function d2g($jdn) {
    $j = 4 * $jdn + 139361631;
    $j = $j + jalali_div(jalali_div(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    $i = jalali_div(jalali_mod($j, 1461), 4) * 5 + 308;
    $gd = jalali_div(jalali_mod($i, 153), 5) + 1;
    $gm = jalali_mod(jalali_div($i, 153), 12) + 1;
    $gy = jalali_div($j, 1461) - 100100 + jalali_div(8 - $gm, 6);
    return [$gy, $gm, $gd];
}

/** تبدیل عدد روز به جلالی */
function d2j($jdn) {
    $gy = d2g($jdn)[0];
    $jy = $gy - 621;
    $r = jal_cal($jy);
    $jdn1f = g2d($gy, 3, $r['march']);
    $k = $jdn - $jdn1f;
    if ($k >= 0) {
        if ($k <= 185) {
            $jm = 1 + jalali_div($k, 31);
            $jd = jalali_mod($k, 31) + 1;
            return [$jy, $jm, $jd];
        }
        $k -= 186;
    } else {
        $jy -= 1;
        $k += 179;
        if ($r['leap'] == 1) $k += 1;
    }
    $jm = 7 + jalali_div($k, 30);
    $jd = jalali_mod($k, 30) + 1;
    return [$jy, $jm, $jd];
}

/** میلادی به جلالی */
function g2jalali($gy, $gm, $gd) { return d2j(g2d($gy, $gm, $gd)); }

/** جلالی به میلادی */
function jalali2g($jy, $jm, $jd) { return d2g(j2d($jy, $jm, $jd)); }

/** امروز به شمسی بر اساس منطقه زمانی تهران: [سال، ماه، روز] */
function today_jalali() {
    $tz = new DateTimeZone(APP_TZ);
    $now = new DateTime('now', $tz);
    return g2jalali((int)$now->format('Y'), (int)$now->format('n'), (int)$now->format('j'));
}

function j_today_str() {
    [$jy, $jm, $jd] = today_jalali();
    return j_str($jy, $jm, $jd);
}

function j_str($jy, $jm, $jd) {
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}

function j_parse($str) {
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', trim((string)$str), $m)) {
        return [(int)$m[1], (int)$m[2], (int)$m[3]];
    }
    return null;
}

/** عدد روزِ یک تاریخ شمسی (برای مقایسه و فاصله‌گیری) */
function j_num($jy, $jm, $jd) { return j2d($jy, $jm, $jd); }

function j_add_days($jy, $jm, $jd, $days) {
    return d2j(j2d($jy, $jm, $jd) + $days);
}

/** شماره روز هفته: 0=شنبه ... 6=جمعه */
function j_day_of_week($jy, $jm, $jd) {
    // میلادیِ متناظر را پیدا کرده و از dayOfWeek میلادی (0=دوشنبه) تبدیل می‌کنیم
    [$gy, $gm, $gd] = jalali2g($jy, $jm, $jd);
    $ts = mktime(12, 0, 0, $gm, $gd, $gy);
    $w = (int)date('N', $ts); // 1=Mon..7=Sun
    // شنبه=0: شنبه(6 میلادی)، یکشنبه(7)، دوشنبه(1)...
    $map = [6 => 0, 7 => 1, 1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6];
    return $map[$w];
}

function j_month_name($jm) {
    $names = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    return $names[$jm - 1] ?? '';
}

function j_weekday_name($w) {
    $names = ['شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه'];
    return $names[$w] ?? '';
}

function j_month_name_full($jy, $jm) { return j_month_name($jm) . ' ' . fa_num($jy); }

/** تبدیل اعداد به فارسی */
function fa_num($num) {
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return strtr((string)$num, ['0'=>$fa[0],'1'=>$fa[1],'2'=>$fa[2],'3'=>$fa[3],'4'=>$fa[4],'5'=>$fa[5],'6'=>$fa[6],'7'=>$fa[7],'8'=>$fa[8],'9'=>$fa[9]]);
}

/** نمایش خوانای تاریخ شمسی مثل: شنبه ۲۰ شهریور ۱۴۰۵ */
function j_format($jdate, $with_weekday = true) {
    $p = j_parse($jdate);
    if (!$p) return $jdate;
    [$jy, $jm, $jd] = $p;
    $txt = fa_num($jd) . ' ' . j_month_name($jm) . ' ' . fa_num($jy);
    if ($with_weekday) $txt = j_weekday_name(j_day_of_week($jy, $jm, $jd)) . ' ' . $txt;
    return $txt;
}

/** شروع و پایان هفته (شنبه تا جمعه) برای یک تاریخ */
function j_week_bounds($jy, $jm, $jd) {
    $w = j_day_of_week($jy, $jm, $jd);
    $start = j_add_days($jy, $jm, $jd, -$w);
    $end = j_add_days($jy, $jm, $jd, 6 - $w);
    return [j_str(...$start), j_str(...$end)];
}

/** هفته‌های یک ماه شمسی (شنبه‌محور) — برای نمودار هفتگی */
function j_month_weeks($jy, $jm) {
    $days = jalali_month_days($jy, $jm);
    $weeks = [];
    $week = [];
    for ($d = 1; $d <= $days; $d++) {
        $w = j_day_of_week($jy, $jm, $d);
        if ($w == 0 && $d > 1) { $weeks[] = $week; $week = []; }
        $week[] = j_str($jy, $jm, $d);
    }
    if ($week) $weeks[] = $week;
    return $weeks;
}
