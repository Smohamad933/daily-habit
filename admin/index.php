<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$u = require_admin();

$totalUsers = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalTasks = (int)db()->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$doneTasks = (int)db()->query("SELECT COUNT(*) FROM tasks WHERE status='done'")->fetchColumn();
$totalHabits = (int)db()->query("SELECT COUNT(*) FROM habits")->fetchColumn();
$totalGoals = (int)db()->query("SELECT COUNT(*) FROM goals WHERE status<>'deleted'")->fetchColumn();
$totalNotifs = (int)db()->query("SELECT COUNT(*) FROM notifications")->fetchColumn();

$recent = db()->query("SELECT * FROM users ORDER BY id DESC LIMIT 6")->fetchAll();
$activeToday = (int)db()->query("SELECT COUNT(DISTINCT user_id) FROM tasks WHERE done_at >= date('now','localtime')")->fetchColumn();

$page_title = 'پنل مدیریت';
$active = 'admin';
include dirname(__DIR__) . '/includes/header.php';
?>
<h1><?= app_icon('settings') ?> پنل مدیریت</h1>
<div class="grid cols-4" style="margin-bottom:16px">
  <div class="card stat"><div class="num"><?= fa_num($totalUsers) ?></div><div class="lbl">کاربر ثبت‌نامی</div></div>
  <div class="card stat"><div class="num"><?= fa_num($doneTasks) ?> / <?= fa_num($totalTasks) ?></div><div class="lbl">تسک انجام‌شده / کل</div></div>
  <div class="card stat"><div class="num"><?= fa_num($totalHabits) ?></div><div class="lbl">عادت فعال</div></div>
  <div class="card stat"><div class="num"><?= fa_num($totalGoals) ?></div><div class="lbl">هدف فعال</div></div>
</div>

<div class="grid cols-2" style="align-items:start">
  <div class="card">
    <h2><?= app_icon('users') ?> آخرین کاربران</h2>
    <div class="table-wrap">
      <table class="tbl">
        <tr><th>نام</th><th>نام کاربری</th><th>موبایل</th><th>شهر</th><th>عضویت</th></tr>
        <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?> <?= $r['role'] === 'admin' ? '<span class="chip c-warn">مدیر</span>' : '' ?></td>
          <td dir="ltr"><?= e($r['username']) ?></td>
          <td dir="ltr"><?= e($r['phone'] ?: '—') ?></td>
          <td><?= e($r['city'] ?: '—') ?></td>
          <td class="small"><?= fa_num(date('Y/m/d', strtotime($r['created_at']))) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <div class="mt" style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn sm" href="users.php"><?= app_icon('users') ?> مدیریت کاربران</a>
      <a class="btn sm success" href="export.php"><?= app_icon('download') ?> خروجی اکسل</a>
    </div>
  </div>

  <div>
    <div class="card">
      <h2><?= app_icon('settings') ?> مدیریت</h2>
      <div class="grid cols-2" style="gap:10px">
        <a class="btn ghost" href="users.php"><?= app_icon('users') ?> کاربران و جزئیات</a>
        <a class="btn ghost" href="content.php"><?= app_icon('note') ?> محتوای سایت</a>
        <a class="btn ghost" href="settings.php"><?= app_icon('settings') ?> تنظیمات</a>
        <a class="btn ghost" href="export.php"><?= app_icon('download') ?> خروجی اکسل</a>
        <a class="btn ghost" href="../monitoring/login.php"><?= app_icon('activity') ?> پنل مانیتورینگ افراد</a>
      </div>
      <p class="muted small mt">مجموع اعلان‌های ارسال‌شده: <?= fa_num($totalNotifs) ?> — کاربران فعال امروز: <?= fa_num($activeToday) ?></p>
    </div>
    <div class="card mt">
      <h2><?= app_icon('info') ?> وضعیت سیستم</h2>
      <p class="small">نسخه برنامه: <?= e(APP_VERSION) ?><br>
      دیتابیس: SQLite (فایل <?= e(basename(DB_PATH)) ?>)<br>
      موتور AI: داخلی قانون‌محور <?= setting('ai_api_key') ? '+ اتصال خارجی پیکربندی‌شده' : '(اتصال خارجی هنوز تنظیم نشده)' ?><br>
      پوش نوتیفیکیشن: <?= setting('vapid_public') ? ' کلیدهای VAPID تولید شده' : ' با اولین بازدید کاربر تولید می‌شود' ?></p>
    </div>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
