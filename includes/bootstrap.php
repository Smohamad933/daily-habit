<?php
/** راه‌اندازی سراسری: سشن، امنیت، اتصال‌ها */

error_reporting(E_ALL);
ini_set('display_errors', '0'); // در استقرار واقعی خطاها لاگ می‌شوند
ini_set('log_errors', '1');

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/jalali.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/monitoring.php';
require_once APP_ROOT . '/includes/notify.php';
require_once APP_ROOT . '/includes/ai_engine.php';

date_default_timezone_set(APP_TZ);
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_name('habitplan');
    session_start();
}

db();
db_seed();

// اجرای زمان‌بند تنبل (تولید خلاصه‌ها و یادآوری‌ها هنگام بازدید)
scheduler_tick();

// اگر مانیتورینگ MySQL نصب شده باشد، آخرین بازدید و رویداد صفحه ثبت می‌شود.
if (function_exists('monitor_record_page_view')) monitor_record_page_view();
