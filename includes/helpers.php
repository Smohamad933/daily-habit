<?php
/** توابع کمکی عمومی */

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** پیشوند نسبی از اسکریپت جاری تا ریشه برنامه — برای استقرار در هر پوشه‌ای روی IIS */
function app_url_prefix(): string {
    static $prefix = null;
    if ($prefix !== null) return $prefix;
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? realpath($_SERVER['SCRIPT_NAME'] ?? '') ?: '');
    $root = str_replace('\\', '/', APP_ROOT);
    // در ویندوز/IIS ممکن است بزرگی و کوچکی حروف مسیرها یکسان نباشد
    if ($script && stripos($script, $root) === 0) {
        $rel = substr(dirname($script), strlen($root));
        $depth = $rel ? count(array_filter(explode('/', $rel))) : 0;
        $prefix = str_repeat('../', $depth);
    } else {
        $prefix = '';
    }
    return $prefix;
}

function url(string $path): string {
    if (preg_match('#^https?://#', $path)) return $path;
    return app_url_prefix() . ltrim($path, '/');
}

function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): bool {
    return hash_equals(csrf_token(), $_POST['csrf'] ?? ($_GET['csrf'] ?? ''));
}

function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg]; }

function get_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** درصد فارسی: ۷۵٪ */
function pct($n): string { return fa_num((int)round((float)$n)) . '٪'; }

function fa_time(string $datetime): string {
    if (!$datetime) return '';
    $ts = strtotime($datetime);
    return fa_num(date('H:i', $ts));
}

/** دسته‌بندی دلایل انجام نشدن کارها — خوراک تحلیل هوشمند عادت‌ها */
function skip_categories(): array {
    return [
        'friends'   => ['label' => 'دوستان / مهمانی / بیرون رفتن', 'negative' => 1],
        'family'    => ['label' => 'خانواده / دیدار نزدیکان',      'negative' => 0],
        'fatigue'   => ['label' => 'خستگی / بی‌حالی',              'negative' => 1],
        'lazy'      => ['label' => 'بی‌انگیزگی / حوصله نداشتم',     'negative' => 1],
        'procrast'  => ['label' => 'به تعویق انداختم',             'negative' => 1],
        'urgent'    => ['label' => 'کار فوری پیش آمد',             'negative' => 0],
        'work'      => ['label' => 'کار / مطالعه اجباری',          'negative' => 0],
        'sick'      => ['label' => 'بیماری / کسالت',               'negative' => 0],
        'forget'    => ['label' => 'فراموش کردم',                  'negative' => 1],
        'other'     => ['label' => 'سایر',                          'negative' => 1],
    ];
}

/** بازه‌های زمانی اهداف */
function goal_periods(): array {
    return [
        'week'  => 'هفتگی',
        'month' => 'ماهانه',
        'q1'    => 'سه ماه اول',
        'h1'    => 'شش ماه اول',
        'h2'    => 'شش ماه دوم',
        'year'  => 'یک سال کامل',
    ];
}

function goal_period_days(string $p): int {
    return ['week' => 7, 'month' => 30, 'q1' => 90, 'h1' => 180, 'h2' => 365, 'year' => 365][$p] ?? 30;
}

function agent_types(): array {
    return [
        'coach'    => 'مربی شخصی (تحلیل و پیشنهاد)',
        'planner'  => 'برنامه‌ریز (ساخت برنامه استپ‌به‌استپ)',
        'reminder' => 'یادآور هوشمند',
    ];
}

/** فهرست استان‌های ایران به تفکیک شهرهای اصلی */
function iran_provinces(): array {
    return [
        'آذربایجان شرقی' => ['تبریز','مراغه','مرند','اهر','میانه','بناب'],
        'آذربایجان غربی' => ['ارومیه','خوی','بوکان','مهاباد','میاندوآب','سلماس'],
        'اردبیل' => ['اردبیل','پارس‌آباد','مشگین‌شهر','خلخال'],
        'اصفهان' => ['اصفهان','کاشان','خمینی‌شهر','نجف‌آباد','شاهین‌شهر','فولادشهر'],
        'البرز' => ['کرج','فردیس','نظرآباد','هشتگرد','محمدشهر'],
        'ایلام' => ['ایلام','دهلران','آبدانان','مهران'],
        'بوشهر' => ['بوشهر','برازجان','بندر گناوه','بندر کنگان'],
        'تهران' => ['تهران','اسلامشهر','شهریار','قدس','ملارد','ورامین','ری','پاکدشت','پردیس','دماوند'],
        'چهارمحال و بختیاری' => ['شهرکرد','بروجن','فارسان','لردگان'],
        'خراسان جنوبی' => ['بیرجند','قائن','طبس','فردوس'],
        'خراسان رضوی' => ['مشهد','نیشابور','سبزوار','تربت حیدریه','قوچان','کاشمر'],
        'خراسان شمالی' => ['بجنورد','شیروان','اسفراین','آشخانه'],
        'خوزستان' => ['اهواز','دزفول','آبادان','خرمشهر','بهبهان','ماهشهر','اندیمشک'],
        'زنجان' => ['زنجان','ابهر','خرمدره','قیدار'],
        'سمنان' => ['سمنان','شاهرود','دامغان','گرمسار'],
        'سیستان و بلوچستان' => ['زاهدان','زابل','چابهار','ایرانشهر','سراوان'],
        'فارس' => ['شیراز','مرودشت','جهرم','فسا','کازرون','داراب','لار'],
        'قزوین' => ['قزوین','تاکستان','آبیک','الوند'],
        'قم' => ['قم','قنوات','جعفریه','کهک'],
        'کردستان' => ['سنندج','سقز','مریوان','بانه','قروه'],
        'کرمان' => ['کرمان','سیرجان','رفسنجان','جیرفت','بم','زرند'],
        'کرمانشاه' => ['کرمانشاه','اسلام‌آباد غرب','کنگاور','سنقر','هرسین'],
        'کهگیلویه و بویراحمد' => ['یاسوج','دوگنبدان','دهدشت','لیکک'],
        'گلستان' => ['گرگان','گنبد کاووس','علی‌آباد کتول','بندر ترکمن','آق‌قلا'],
        'گیلان' => ['رشت','بندر انزلی','لاهیجان','لنگرود','آستارا','تالش','صومعه‌سرا'],
        'لرستان' => ['خرم‌آباد','بروجرد','دورود','الیگودرز','کوهدشت'],
        'مازندران' => ['ساری','بابل','آمل','قائم‌شهر','بهشهر','چالوس','نوشهر','تنکابن'],
        'مرکزی' => ['اراک','ساوه','خمین','محلات','دلیجان'],
        'هرمزگان' => ['بندرعباس','میناب','بندر لنگه','قشم','کیش','حاجی‌آباد'],
        'همدان' => ['همدان','ملایر','نهاوند','اسدآباد','تویسرکان'],
        'یزد' => ['یزد','میبد','اردکان','بافق','مهریز','تفت'],
    ];
}

/** تولید جفت‌کلید VAPID (ES256/P-256) برای پوش نوتیفیکیشن */
function generate_vapid_keys(): array {
    // اگر افزونه openssl فعال نباشد، پوش غیرفعال می‌شود ولی سایت به کار خود ادامه می‌دهد
    if (!function_exists('openssl_pkey_new') || !function_exists('openssl_pkey_get_details')
        || !function_exists('openssl_pkey_export') || !defined('OPENSSL_KEYTYPE_EC')) {
        return [];
    }
    try {
        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if (!$key) return [];
        $details = openssl_pkey_get_details($key);
        if (empty($details['ec']['x']) || empty($details['ec']['y'])) return [];
        $pem = '';
        openssl_pkey_export($key, $pem);
        return [
            'private' => $pem,
            'public'  => base64url_encode("\x04" . $details['ec']['x'] . $details['ec']['y']),
        ];
    } catch (Throwable $e) {
        return [];
    }
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

/** آیکون‌های خطی یکدست برای تمام رابط کاربری؛ بدون وابستگی به کتابخانه یا ایموجی. */
function app_icon(string $name, string $class = ''): string {
    $paths = [
        'activity' => '<path d="M3 12h4l2.2-6 4.1 12 2.4-6H21"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'home' => '<path d="m3 10 9-7 9 7"/><path d="M5 9v11h14V9M9 20v-6h6v6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4M8 14h.01M12 14h.01M16 14h.01M8 17h.01M12 17h.01"/>',
        'chart' => '<path d="M4 19V9M10 19V5M16 19v-7M3 21h18"/>',
        'pulse' => '<path d="M3 12h4l2-5 4 10 2-5h6"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/>',
        'repeat' => '<path d="M17 2l4 4-4 4"/><path d="M3 6h18M7 22l-4-4 4-4"/><path d="M21 18H3"/>',
        'note' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'bot' => '<rect x="4" y="7" width="16" height="13" rx="3"/><path d="M12 3v4M8 13h.01M16 13h.01M9 17h6"/><path d="M2 12h2M20 12h2"/>',
        'brain' => '<path d="M9 4.5A3 3 0 0 0 5.5 8 3 3 0 0 0 6 13.8 3.5 3.5 0 0 0 9 19v-3M15 4.5A3 3 0 0 1 18.5 8 3 3 0 0 1 18 13.8 3.5 3.5 0 0 1 15 19v-3M9 8a3 3 0 0 1 3 3v8M15 8a3 3 0 0 0-3 3M6 12h3M15 12h3"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 5.5a3 3 0 0 1 0 5.8M17 14a5.5 5.5 0 0 1 4.5 6"/>',
        'bell' => '<path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 1.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-2.5V20a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1-1.8-1.8.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H6v-2.5h.2a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 1.8-1.8.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6V5h2.5v.2a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.8 1.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v2.5H21a1.7 1.7 0 0 0-1.6 1z"/>',
        'moon' => '<path d="M20 15.5A8.5 8.5 0 0 1 8.5 4 8.5 8.5 0 1 0 20 15.5z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'arrow-left' => '<path d="m9 5 7 7-7 7M16 12H3"/>',
        'arrow-right' => '<path d="m15 5-7 7 7 7M8 12h13"/>',
        'chevron-left' => '<path d="m14 5-7 7 7 7"/>',
        'chevron-right' => '<path d="m10 5 7 7-7 7"/>',
        'edit' => '<path d="m4 20 4.5-1 10-10a2.1 2.1 0 0 0-3-3l-10 10zM13.5 7.5l3 3"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 14h10l1-14M9 7V4h6v3"/>',
        'archive' => '<path d="M4 7h16v13H4zM3 4h18v3H3zM9 12h6"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3M21 19V5a2 2 0 0 0-2-2h-6"/>',
        'search' => '<circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/>',
        'filter' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
        'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v7c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12v7c0 1.7 3.6 3 8 3s8-1.3 8-3v-7"/>',
        'server' => '<rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01M11 7h6M11 17h6"/>',
        'shield' => '<path d="M12 3 20 6v5c0 5-3.3 8.3-8 10-4.7-1.7-8-5-8-10V6z"/><path d="m8 12 2.5 2.5L16 9"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
        'phone' => '<path d="M6 3h3l1.5 4-2 1.5a14 14 0 0 0 7 7l1.5-2 4 1.5v3a2 2 0 0 1-2 2C10.7 20 4 13.3 4 5a2 2 0 0 1 2-2z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7h.01"/>',
        'flag' => '<path d="M5 21V4M5 5c4-3 7 3 14 0v9c-7 3-10-3-14 0"/>',
        'leaf' => '<path d="M20 4C10 4 4 9 4 16c0 2 1 4 1 4s2-1 4-1c7 0 11-5 11-15zM4 20c2-4 5-7 10-10"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'lock' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'dots' => '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
    ];
    $body = $paths[$name] ?? $paths['info'];
    $cls = trim('app-icon ' . $class);
    return '<svg class="' . e($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}
