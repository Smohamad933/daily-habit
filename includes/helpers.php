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
