<?php
/** سربرگ سراسری — نیازمند: $page_title و (اختیاری) $active, $u */
$u = $u ?? current_user();
$theme = $u['theme'] ?? ($_COOKIE['theme'] ?? 'auto');
$unread = $u ? unread_count((int)$u['id']) : 0;
$siteName = content('site_name', APP_NAME);
$announcement = content('announcement', '');
$act = $active ?? '';
$moreTabs = ['more', 'tasks', 'habits', 'notes', 'goals', 'agents', 'calendar', 'notifications', 'profile', 'personality', 'admin'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= e($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($page_title ?? '') ?> | <?= e($siteName) ?></title>
<link rel="manifest" href="<?= url('manifest.php') ?>">
<meta name="theme-color" content="#12633e">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<?php if ($u): ?>
<nav class="topbar">
  <div class="container topbar-inner">
    <a class="brand" href="<?= url('dashboard.php') ?>">
      <span class="logo"><?= app_icon('calendar') ?></span><span><?= e($siteName) ?></span>
    </a>
    <div class="nav-links">
      <a href="<?= url('dashboard.php') ?>" class="<?= $act === 'dashboard' ? 'on' : '' ?>">امروز</a>
      <a href="<?= url('planner.php') ?>" class="<?= $act === 'planner' ? 'on' : '' ?>">برنامه</a>
      <a href="<?= url('reports.php') ?>" class="<?= $act === 'reports' ? 'on' : '' ?>">گزارش</a>
      <a href="<?= url('habits.php') ?>" class="<?= $act === 'habits' ? 'on' : '' ?>">عادت‌ها</a>
      <a href="<?= url('goals.php') ?>" class="<?= $act === 'goals' ? 'on' : '' ?>">اهداف</a>
      <a href="<?= url('more.php') ?>" class="<?= in_array($act, $moreTabs, true) ? 'on' : '' ?>">بیشتر</a>
      <?php if (is_admin()): ?><a href="<?= url('admin/index.php') ?>" class="admin-nav-link"><?= app_icon('settings') ?> مدیریت</a><?php endif; ?>
    </div>
    <div class="topbar-actions">
      <a class="icon-btn" href="<?= url('notifications.php') ?>" title="اعلان‌ها"><?= app_icon('bell') ?><?php if ($unread): ?><span class="badge"><?= fa_num($unread) ?></span><?php endif; ?></a>
      <button class="icon-btn" id="themeToggle" title="تغییر تم"><?= app_icon($theme === 'dark' ? 'sun' : 'moon') ?></button>
      <a class="icon-btn" href="<?= url('profile.php') ?>" title="پروفایل"><?= app_icon('user') ?></a>
    </div>
  </div>
</nav>

<div class="bottom-nav">
  <div class="bottom-nav-inner">
    <a class="bnav <?= $act === 'dashboard' ? 'on' : '' ?>" href="<?= url('dashboard.php') ?>"><?= app_icon('home') ?><span>امروز</span></a>
    <a class="bnav <?= $act === 'planner' ? 'on' : '' ?>" href="<?= url('planner.php') ?>"><?= app_icon('calendar') ?><span>برنامه</span></a>
    <div class="bnav-plus-wrap"><button class="bnav-plus" id="quickBtn" aria-label="افزودن سریع"><?= app_icon('plus') ?></button></div>
    <a class="bnav <?= $act === 'reports' ? 'on' : '' ?>" href="<?= url('reports.php') ?>"><?= app_icon('chart') ?><span>گزارش</span></a>
    <a class="bnav <?= in_array($act, $moreTabs, true) ? 'on' : '' ?>" href="<?= url('more.php') ?>"><?= app_icon('dots') ?><span>بیشتر</span></a>
  </div>
</div>

<div class="sheet-overlay" id="sheetOverlay"></div>
<div class="sheet" id="quickSheet">
  <div class="grab"></div>
  <h3>افزودن سریع</h3>
  <div class="sheet-grid">
    <a class="sheet-item" href="<?= url('tasks.php') ?>?new=1"><span class="si-ic tone-primary"><?= app_icon('check') ?></span><span>تسک جدید</span></a>
    <a class="sheet-item" href="<?= url('habits.php') ?>"><span class="si-ic tone-good"><?= app_icon('repeat') ?></span><span>ثبت عادت</span></a>
    <a class="sheet-item" href="<?= url('notes.php') ?>"><span class="si-ic tone-warn"><?= app_icon('note') ?></span><span>یادداشت</span></a>
    <a class="sheet-item" href="<?= url('goals.php') ?>"><span class="si-ic tone-info"><?= app_icon('target') ?></span><span>هدف جدید</span></a>
    <a class="sheet-item" href="<?= url('planner.php') ?>"><span class="si-ic tone-danger"><?= app_icon('clock') ?></span><span>برنامه ساعتی</span></a>
    <a class="sheet-item" href="<?= url('personality.php') ?>"><span class="si-ic tone-primary"><?= app_icon('brain') ?></span><span>تست شخصیت</span></a>
  </div>
</div>
<?php endif; ?>
<?php if ($u && $announcement): ?>
<div class="announce container"><?= app_icon('info') ?><span><?= nl2br(e($announcement)) ?></span></div>
<?php endif; ?>
<main class="container page">
<?php foreach (get_flashes() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>"><?= nl2br(e($f['msg'])) ?></div>
<?php endforeach; ?>
