<?php
/** سربرگ سراسری — نیازمند: $page_title و (اختیاری) $active, $u */
$u = $u ?? current_user();
$theme = $u['theme'] ?? ($_COOKIE['theme'] ?? 'auto');
$unread = $u ? unread_count((int)$u['id']) : 0;
$siteName = content('site_name', APP_NAME);
$announcement = content('announcement', '');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= e($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? '') ?> | <?= e($siteName) ?></title>
<link rel="manifest" href="<?= url('manifest.php') ?>">
<meta name="theme-color" content="#7c5cff">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<?php if ($u): ?>
<nav class="topbar">
  <div class="container topbar-inner">
    <a class="brand" href="<?= url('dashboard.php') ?>">📅 <?= e($siteName) ?></a>
    <button class="nav-toggle" id="navToggle" aria-label="منو">☰</button>
    <div class="nav-links" id="navLinks">
      <a href="<?= url('dashboard.php') ?>" class="<?= ($active ?? '') === 'dashboard' ? 'on' : '' ?>">داشبورد</a>
      <a href="<?= url('planner.php') ?>" class="<?= ($active ?? '') === 'planner' ? 'on' : '' ?>">برنامه امروز</a>
      <a href="<?= url('habits.php') ?>" class="<?= ($active ?? '') === 'habits' ? 'on' : '' ?>">عادت‌ها</a>
      <a href="<?= url('tasks.php') ?>" class="<?= ($active ?? '') === 'tasks' ? 'on' : '' ?>">تسک‌ها</a>
      <a href="<?= url('calendar.php') ?>" class="<?= ($active ?? '') === 'calendar' ? 'on' : '' ?>">تقویم</a>
      <a href="<?= url('notes.php') ?>" class="<?= ($active ?? '') === 'notes' ? 'on' : '' ?>">یادداشت‌ها</a>
      <a href="<?= url('goals.php') ?>" class="<?= ($active ?? '') === 'goals' ? 'on' : '' ?>">اهداف</a>
      <a href="<?= url('agents.php') ?>" class="<?= ($active ?? '') === 'agents' ? 'on' : '' ?>">ایجنت‌ها</a>
      <a href="<?= url('personality.php') ?>" class="<?= ($active ?? '') === 'personality' ? 'on' : '' ?>">تست شخصیت</a>
      <a href="<?= url('reports.php') ?>" class="<?= ($active ?? '') === 'reports' ? 'on' : '' ?>">گزارش‌ها</a>
      <?php if (is_admin()): ?><a href="<?= url('admin/index.php') ?>" class="admin-link">⚙ پنل مدیریت</a><?php endif; ?>
    </div>
    <div class="topbar-actions">
      <a class="icon-btn" href="<?= url('notifications.php') ?>" title="اعلان‌ها">🔔<?php if ($unread): ?><span class="badge"><?= fa_num($unread) ?></span><?php endif; ?></a>
      <button class="icon-btn" id="themeToggle" title="تغییر تم">🌓</button>
      <a class="icon-btn" href="<?= url('profile.php') ?>" title="پروفایل">👤</a>
      <a class="icon-btn" href="<?= url('logout.php') ?>" title="خروج">🚪</a>
    </div>
  </div>
</nav>
<?php endif; ?>
<?php if ($u && $announcement): ?>
<div class="announce container">📣 <?= nl2br(e($announcement)) ?></div>
<?php endif; ?>
<main class="container page">
<?php foreach (get_flashes() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>"><?= nl2br(e($f['msg'])) ?></div>
<?php endforeach; ?>
