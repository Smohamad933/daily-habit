<?php
/** سربرگ سراسری — نیازمند: $page_title و (اختیاری) $active, $u */
$u = $u ?? current_user();
$theme = $u['theme'] ?? ($_COOKIE['theme'] ?? 'auto');
$unread = $u ? unread_count((int)$u['id']) : 0;
$siteName = content('site_name', APP_NAME);
$announcement = content('announcement', '');
$act = $active ?? '';
// تب «بیشتر» برای همه صفحات فرعی فعال می‌ماند
$moreTabs = ['more', 'tasks', 'habits', 'notes', 'goals', 'agents', 'calendar', 'notifications', 'profile', 'personality', 'admin'];
function bnav_icon(string $name): string {
    $icons = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h5v-6h4v6h5V9.5"/>',
        'cal' => '<rect x="3" y="5" width="18" height="17" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/><path d="m9 15 2 2 4-4"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-8M21 20H3"/>',
        'dots' => '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
    ];
    return '<svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">' . $icons[$name] . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= e($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($page_title ?? '') ?> | <?= e($siteName) ?></title>
<link rel="manifest" href="<?= url('manifest.php') ?>">
<meta name="theme-color" content="#5b5bd6">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<?php if ($u): ?>
<nav class="topbar">
  <div class="container topbar-inner">
    <a class="brand" href="<?= url('dashboard.php') ?>">
      <span class="logo">📅</span><span><?= e($siteName) ?></span>
    </a>
    <div class="nav-links">
      <a href="<?= url('dashboard.php') ?>" class="<?= $act === 'dashboard' ? 'on' : '' ?>">امروز</a>
      <a href="<?= url('planner.php') ?>" class="<?= $act === 'planner' ? 'on' : '' ?>">پلنر</a>
      <a href="<?= url('reports.php') ?>" class="<?= $act === 'reports' ? 'on' : '' ?>">گزارش‌ها</a>
      <a href="<?= url('habits.php') ?>" class="<?= $act === 'habits' ? 'on' : '' ?>">عادت‌ها</a>
      <a href="<?= url('goals.php') ?>" class="<?= $act === 'goals' ? 'on' : '' ?>">اهداف</a>
      <a href="<?= url('more.php') ?>" class="<?= in_array($act, $moreTabs) ? 'on' : '' ?>">بیشتر</a>
      <?php if (is_admin()): ?><a href="<?= url('admin/index.php') ?>" style="color:var(--warn)">⚙ مدیریت</a><?php endif; ?>
    </div>
    <div class="topbar-actions">
      <a class="icon-btn" href="<?= url('notifications.php') ?>" title="اعلان‌ها">🔔<?php if ($unread): ?><span class="badge"><?= fa_num($unread) ?></span><?php endif; ?></a>
      <button class="icon-btn" id="themeToggle" title="تم">🌓</button>
      <a class="icon-btn" href="<?= url('profile.php') ?>" title="پروفایل">👤</a>
    </div>
  </div>
</nav>

<!-- ناوبری پایین موبایل -->
<div class="bottom-nav">
  <div class="bottom-nav-inner">
    <a class="bnav <?= $act === 'dashboard' ? 'on' : '' ?>" href="<?= url('dashboard.php') ?>"><?= bnav_icon('home') ?><span>امروز</span></a>
    <a class="bnav <?= $act === 'planner' ? 'on' : '' ?>" href="<?= url('planner.php') ?>"><?= bnav_icon('cal') ?><span>پلنر</span></a>
    <div class="bnav-plus-wrap"><button class="bnav-plus" id="quickBtn" aria-label="افزودن سریع">+</button></div>
    <a class="bnav <?= $act === 'reports' ? 'on' : '' ?>" href="<?= url('reports.php') ?>"><?= bnav_icon('chart') ?><span>گزارش</span></a>
    <a class="bnav <?= in_array($act, $moreTabs) ? 'on' : '' ?>" href="<?= url('more.php') ?>"><?= bnav_icon('dots') ?><span>بیشتر</span></a>
  </div>
</div>

<!-- شیت اقدام سریع -->
<div class="sheet-overlay" id="sheetOverlay"></div>
<div class="sheet" id="quickSheet">
  <div class="grab"></div>
  <h3>دوست داری چی کار کنی؟</h3>
  <div class="sheet-grid">
    <a class="sheet-item" href="<?= url('tasks.php') ?>?new=1"><span class="si-ic" style="background:var(--accent-soft)">✅</span> تسک جدید</a>
    <a class="sheet-item" href="<?= url('habits.php') ?>"><span class="si-ic" style="background:color-mix(in srgb, var(--good) 15%, var(--card))">🔁</span> ثبت عادت</a>
    <a class="sheet-item" href="<?= url('notes.php') ?>"><span class="si-ic" style="background:color-mix(in srgb, var(--warn) 18%, var(--card))">📝</span> یادداشت</a>
    <a class="sheet-item" href="<?= url('goals.php') ?>"><span class="si-ic" style="background:color-mix(in srgb, var(--info) 15%, var(--card))">🎯</span> هدف جدید</a>
    <a class="sheet-item" href="<?= url('planner.php') ?>"><span class="si-ic" style="background:color-mix(in srgb, var(--bad) 13%, var(--card))">⏱</span> برنامه ساعتی</a>
    <a class="sheet-item" href="<?= url('personality.php') ?>"><span class="si-ic" style="background:var(--accent-soft)">🧠</span> تست شخصیت</a>
  </div>
</div>
<?php endif; ?>
<?php if ($u && $announcement): ?>
<div class="announce container">📣 <?= nl2br(e($announcement)) ?></div>
<?php endif; ?>
<main class="container page">
<?php foreach (get_flashes() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>"><?= nl2br(e($f['msg'])) ?></div>
<?php endforeach; ?>
