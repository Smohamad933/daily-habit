<?php
require __DIR__ . '/common.php';
$monitor_admin = monitor_require_admin();
$monitor_active = 'settings';
$monitor_title = 'اتصال و تنظیمات';
$config = monitoring_config();
$pdo = monitoring_pdo();
include __DIR__ . '/header.php';
?>
<div class="monitor-settings-grid">
  <section class="monitor-panel"><div class="monitor-panel-head"><div><h2>وضعیت اتصال</h2><p>مانیتورینگ به‌صورت جداگانه به MySQL متصل می‌شود.</p></div><span class="monitor-connection <?= $pdo ? 'is-online' : 'is-offline' ?>"><i></i><?= $pdo ? 'متصل' : 'قطع' ?></span></div><div class="monitor-settings-list"><div><span>Host</span><b dir="ltr"><?= e($config['host'] ?? '—') ?></b></div><div><span>Database</span><b dir="ltr"><?= e($config['database'] ?? '—') ?></b></div><div><span>Username</span><b dir="ltr"><?= e($config['username'] ?? '—') ?></b></div><div><span>Driver</span><b dir="ltr">PDO MySQL</b></div></div><?php if (!$pdo): ?><div class="monitor-alert is-error">اتصال برقرار نیست. <?= e(monitoring_pdo_error()) ?></div><?php endif; ?><a class="monitor-outline monitor-block" href="<?= e(monitor_url('install.php')) ?>"><?= app_icon('settings') ?> اجرای دوباره نصب / تغییر اتصال</a></section>
  <section class="monitor-panel"><div class="monitor-panel-head"><div><h2>امنیت داده‌ها</h2><p>این موارد در طراحی پنل رعایت شده‌اند.</p></div><span class="metric-icon green"><?= app_icon('shield') ?></span></div><ul class="monitor-check-list"><li><?= app_icon('check') ?><span>رمز مدیر با <b>password_hash</b> ذخیره شده و متن خام آن در دیتابیس نگه‌داری نمی‌شود.</span></li><li><?= app_icon('check') ?><span>IP خام در لاگ ذخیره نمی‌شود؛ فقط هش یک‌طرفه برای تشخیص نشست‌ها ثبت می‌شود.</span></li><li><?= app_icon('check') ?><span>اطلاعات حساس مثل رمز کاربران، محتوای یادداشت و متن فرم‌ها وارد MySQL مانیتورینگ نمی‌شود.</span></li><li><?= app_icon('check') ?><span>پوشه <b>data</b> در IIS مسدود است و فایل تنظیمات اتصال از وب قابل دانلود نیست.</span></li></ul></section>
</div>
<section class="monitor-panel monitor-danger-panel"><div><h2>راهنمای نصب روی IIS</h2><p>افزونه <b>pdo_mysql</b> را در php.ini فعال کن، به پوشه <b>data</b> دسترسی Modify بده و سپس فرم نصب را اجرا کن. بعد از نصب، دسترسی به <b>monitoring/install.php</b> را محدود یا فایل را حذف کن.</p></div><a class="monitor-outline" href="<?= e(url('requirements.php')) ?>">بررسی نیازمندی‌های PHP <?= app_icon('arrow-left') ?></a></section>
<?php include __DIR__ . '/footer.php'; ?>
