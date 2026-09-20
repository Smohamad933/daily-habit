<?php
/** احراز هویت: ثبت‌نام، ورود، سشن */

function current_user(): ?array {
    static $user = null;
    static $loaded = false;
    if ($loaded) return $user;
    $loaded = true;
    $uid = $_SESSION['uid'] ?? 0;
    if ($uid) {
        $st = db()->prepare("SELECT * FROM users WHERE id=?");
        $st->execute([$uid]);
        $u = $st->fetch();
        if ($u) { $user = $u; return $user; }
    }
    $user = null;
    return $user;
}

function is_admin(): bool {
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    // نقطه شروع نظرسنجی اعلان‌ها: فقط اعلان‌های بعد از ورود «جدید» محسوب شوند
    $maxId = db()->query("SELECT MAX(id) FROM notifications WHERE user_id=" . (int)$u['id'])->fetchColumn();
    $_SESSION['last_poll'] = (int)($maxId ?: 0);
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function attempt_register(array $d): array {
    $errors = [];
    $username = trim($d['username'] ?? '');
    $password = $d['password'] ?? '';
    if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) $errors[] = 'نام کاربری باید ۳ تا ۳۰ کاراکتر انگلیسی باشد.';
    if (strlen($password) < 6) $errors[] = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
    if (($d['password2'] ?? '') !== $password) $errors[] = 'تکرار رمز عبور یکسان نیست.';
    if (!trim($d['first_name'] ?? '')) $errors[] = 'نام الزامی است.';
    if (!trim($d['last_name'] ?? '')) $errors[] = 'نام خانوادگی الزامی است.';
    $phone = trim($d['phone'] ?? '');
    if (!preg_match('/^09\d{9}$/', $phone)) $errors[] = 'شماره موبایل معتبر نیست (مثل 09121234567).';
    $email = trim($d['email'] ?? '');
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'ایمیل معتبر نیست.';
    if (!trim($d['province'] ?? '')) $errors[] = 'استان محل زندگی را انتخاب کنید.';
    $city = trim($d['city'] ?? '') ?: trim($d['city_custom'] ?? '');
    if (!$city) $errors[] = 'شهر محل زندگی را وارد کنید.';
    $bd = trim($d['birth_date'] ?? '');
    $bdOk = false;
    if ($bdp = j_parse($bd)) {
        [$by, $bm, $bdd] = $bdp;
        if ($by >= 1300 && $by <= today_jalali()[0] && $bm >= 1 && $bm <= 12
            && $bdd >= 1 && $bdd <= jalali_month_days($by, $bm)) $bdOk = true;
    }
    if (!$bdOk) $errors[] = 'تاریخ تولد شمسی معتبر نیست (مثل 1380/05/15).';

    if (!$errors) {
        $st = db()->prepare("SELECT id FROM users WHERE username=? COLLATE NOCASE");
        $st->execute([$username]);
        if ($st->fetch()) $errors[] = 'این نام کاربری قبلاً ثبت شده است.';
    }
    if (!$errors) {
        $st = db()->prepare("INSERT INTO users (username,password,first_name,last_name,email,phone,province,city,birth_date,skills,role)
                             VALUES (?,?,?,?,?,?,?,?,?,?, 'user')");
        $st->execute([
            $username, password_hash($password, PASSWORD_DEFAULT),
            trim($d['first_name']), trim($d['last_name']), $email, $phone,
            trim($d['province']), $city, $bd, trim($d['skills'] ?? ''),
        ]);
        $u = db()->query("SELECT * FROM users WHERE id=" . db()->lastInsertId())->fetch();
        login_user($u);
        notify_create((int)$u['id'], 'خوش آمدید 👋', 'حساب شما فعال شد. از بخش «برنامه امروز» شروع کنید و عادت‌هایتان را بسازید.', 'success', 'planner.php');
        return ['ok' => true, 'user' => $u];
    }
    return ['ok' => false, 'errors' => $errors];
}

function attempt_login(string $username, string $password): array {
    $st = db()->prepare("SELECT * FROM users WHERE username=? COLLATE NOCASE");
    $st->execute([trim($username)]);
    $u = $st->fetch();
    if ($u && password_verify($password, $u['password'])) {
        login_user($u);
        return ['ok' => true, 'user' => $u];
    }
    return ['ok' => false, 'errors' => ['نام کاربری یا رمز عبور اشتباه است.']];
}

function require_login(): array {
    $u = current_user();
    if (!$u) redirect('login.php');
    return $u;
}

function require_admin(): array {
    $u = require_login();
    if (!is_admin()) { flash('error', 'دسترسی فقط برای مدیر سیستم مجاز است.'); redirect('dashboard.php'); }
    return $u;
}
