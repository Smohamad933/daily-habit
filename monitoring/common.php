<?php
/** پایه‌ی صفحات پنل مانیتورینگ مستقل از پنل کاربری */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

function monitor_url(string $path = 'index.php'): string {
    return url('monitoring/' . ltrim($path, '/'));
}

function monitor_require_admin(): array {
    $admin = monitoring_admin();
    if (!$admin) {
        header('Location: ' . monitor_url('login.php'));
        exit;
    }
    return $admin;
}

function monitor_csrf_token(): string {
    if (empty($_SESSION['monitor_csrf'])) $_SESSION['monitor_csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['monitor_csrf'];
}

function monitor_csrf_field(): string {
    return '<input type="hidden" name="monitor_csrf" value="' . e(monitor_csrf_token()) . '">';
}

function monitor_csrf_check(): bool {
    return hash_equals(monitor_csrf_token(), (string)($_POST['monitor_csrf'] ?? ''));
}

function monitor_initial(string $value): string {
    return function_exists('mb_substr') ? (string)mb_substr($value, 0, 1) : substr($value, 0, 1);
}

function monitor_time(string $value): string {
    if (!$value) return '—';
    $ts = strtotime($value);
    if (!$ts) return $value;
    [$jy, $jm, $jd] = g2jalali((int)date('Y', $ts), (int)date('m', $ts), (int)date('d', $ts));
    return j_format(j_str($jy, $jm, $jd), false) . ' ' . fa_num(date('H:i', $ts));
}

function monitor_percent(int $done, int $total): int {
    return $total > 0 ? (int)round($done / $total * 100) : 0;
}

function monitor_event_label(string $type): string {
    return [
        'page_view' => 'صفحه را دیده است',
        'api_request' => 'درخواست سامانه',
        'user_action' => 'یک اقدام انجام داده',
        'login' => 'وارد سامانه شده',
        'monitor_login' => 'مدیر وارد پنل شده',
    ][$type] ?? 'رویداد جدید';
}
