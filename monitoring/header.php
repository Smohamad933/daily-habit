<?php
$monitor_admin = $monitor_admin ?? monitoring_admin();
$monitor_title = $monitor_title ?? 'مانیتورینگ';
$monitor_active = $monitor_active ?? 'dashboard';
$monitor_name = $monitor_admin['display_name'] ?? 'مدیر مانیتورینگ';
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($monitor_title) ?> | مانیتورینگ</title>
<link rel="stylesheet" href="<?= e(monitor_url('assets/monitor.css')) ?>">
</head>
<body class="monitor-body">
<div class="monitor-shell">
  <aside class="monitor-sidebar">
    <a class="monitor-brand" href="<?= e(monitor_url()) ?>">
      <span class="monitor-brand-mark"><?= app_icon('activity') ?></span>
      <span><b>داشبورد</b><small>مانیتورینگ روزانه</small></span>
    </a>
    <nav class="monitor-nav" aria-label="منوی مانیتورینگ">
      <a class="<?= $monitor_active === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(monitor_url()) ?>"><?= app_icon('grid') ?><span>نمای کلی</span></a>
      <a class="<?= $monitor_active === 'users' ? 'is-active' : '' ?>" href="<?= e(monitor_url('index.php#users')) ?>"><?= app_icon('users') ?><span>افراد</span></a>
      <a class="<?= $monitor_active === 'events' ? 'is-active' : '' ?>" href="<?= e(monitor_url('events.php')) ?>"><?= app_icon('pulse') ?><span>رویدادها</span></a>
      <a class="<?= $monitor_active === 'settings' ? 'is-active' : '' ?>" href="<?= e(monitor_url('settings.php')) ?>"><?= app_icon('settings') ?><span>اتصال و تنظیمات</span></a>
    </nav>
    <div class="monitor-sidebar-bottom">
      <div class="monitor-admin-mini"><span class="monitor-avatar"><?= e(monitor_initial($monitor_name)) ?></span><span><b><?= e($monitor_name) ?></b><small dir="ltr">@<?= e($monitor_admin['username'] ?? '') ?></small></span></div>
      <a class="monitor-logout" href="<?= e(monitor_url('logout.php')) ?>"><?= app_icon('logout') ?> خروج</a>
    </div>
  </aside>
  <main class="monitor-main">
    <header class="monitor-mobile-head">
      <a class="monitor-brand" href="<?= e(monitor_url()) ?>"><span class="monitor-brand-mark"><?= app_icon('activity') ?></span><b>مانیتورینگ</b></a>
      <a class="monitor-icon-btn" href="<?= e(monitor_url('settings.php')) ?>" aria-label="تنظیمات"><?= app_icon('settings') ?></a>
    </header>
    <div class="monitor-topline">
      <div>
        <div class="monitor-eyebrow">کنترل و مشاهده‌ی فعالیت‌ها</div>
        <h1><?= e($monitor_title) ?></h1>
      </div>
      <div class="monitor-top-actions">
        <a class="monitor-outline" href="<?= e(url('dashboard.php')) ?>"><?= app_icon('external') ?> سامانه کاربری</a>
        <a class="monitor-icon-btn" href="<?= e(monitor_url('logout.php')) ?>" title="خروج"><?= app_icon('logout') ?></a>
      </div>
    </div>
    <?php foreach (get_flashes() as $flash): ?>
      <div class="monitor-alert <?= $flash['type'] === 'error' ? 'is-error' : 'is-success' ?>"><?= nl2br(e($flash['msg'])) ?></div>
    <?php endforeach; ?>
