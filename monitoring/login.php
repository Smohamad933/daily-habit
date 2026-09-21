<?php
require __DIR__ . '/common.php';
if (monitoring_admin()) {
    header('Location: ' . monitor_url());
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!monitoring_configured()) {
        $errors[] = 'اتصال MySQL هنوز نصب نشده است.';
    } elseif (!monitor_csrf_check()) {
        $errors[] = 'درخواست امنیتی معتبر نیست؛ دوباره تلاش کنید.';
    } else {
        $admin = monitoring_find_admin($_POST['username'] ?? '');
        if ($admin && password_verify((string)($_POST['password'] ?? ''), $admin['password_hash'])) {
            monitoring_admin_login($admin);
            header('Location: ' . monitor_url());
            exit;
        }
        $errors[] = 'نام کاربری یا رمز عبور پنل اشتباه است.';
    }
}
$mysqlReady = monitoring_configured() && monitoring_mysql_available() && monitoring_pdo();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود به مانیتورینگ</title>
<link rel="stylesheet" href="<?= e(monitor_url('assets/monitor.css')) ?>">
</head>
<body class="monitor-auth-body">
<div class="monitor-auth-card">
  <div class="monitor-auth-mark"><?= app_icon('activity') ?></div>
  <div class="monitor-eyebrow">پنل خصوصی مدیر</div>
  <h1>ورود به مانیتورینگ</h1>
  <p class="monitor-auth-copy">فعالیت کاربران، روند پیشرفت و رویدادهای سامانه را در یک نمای آرام و خوانا ببینید.</p>
  <?php foreach ($errors as $error): ?><div class="monitor-alert is-error"><?= e($error) ?></div><?php endforeach; ?>
  <?php if (!$mysqlReady): ?>
    <div class="monitor-setup-note">
      <?= app_icon('database') ?>
      <div><b>اتصال MySQL آماده نیست</b><small>ابتدا نصب‌کننده را اجرا کنید تا جدول‌ها و مدیر پنل در MySQL ساخته شوند.</small></div>
    </div>
    <a class="monitor-primary monitor-block" href="<?= e(monitor_url('install.php')) ?>">راه‌اندازی اتصال MySQL <?= app_icon('arrow-left') ?></a>
  <?php else: ?>
    <form method="post" class="monitor-form">
      <?= monitor_csrf_field() ?>
      <label>نام کاربری<input type="text" name="username" value="<?= e($_POST['username'] ?? 'Mohusyn') ?>" dir="ltr" autocomplete="username" required autofocus></label>
      <label>رمز عبور<input type="password" name="password" dir="ltr" autocomplete="current-password" required></label>
      <button class="monitor-primary monitor-block" type="submit">ورود به پنل <?= app_icon('arrow-left') ?></button>
    </form>
  <?php endif; ?>
  <div class="monitor-auth-foot"><a href="<?= e(url('login.php')) ?>">بازگشت به سامانه</a><span>دسترسی فقط برای مدیر</span></div>
</div>
</body>
</html>
