<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$unread = unread_count($uid);
$counts = [
    'tasks' => db()->query("SELECT COUNT(*) FROM tasks WHERE user_id=$uid AND status<>'deleted'")->fetchColumn(),
    'habits' => db()->query("SELECT COUNT(*) FROM habits WHERE user_id=$uid AND archived=0")->fetchColumn(),
    'notes' => db()->query("SELECT COUNT(*) FROM notes WHERE user_id=$uid")->fetchColumn(),
    'goals' => db()->query("SELECT COUNT(*) FROM goals WHERE user_id=$uid AND status<>'deleted'")->fetchColumn(),
];

$page_title = 'بیشتر';
$active = 'more';
include __DIR__ . '/includes/header.php';
?>
<h1>منو</h1>
<div class="card" style="display:flex;align-items:center;gap:14px;margin-bottom:14px">
  <div class="avatar" style="width:52px;height:52px;border-radius:17px;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:21px">
    <?= e(mb_substr($u['first_name'] ?: $u['username'], 0, 1)) ?>
  </div>
  <div class="grow">
    <b><?= e($u['first_name'] . ' ' . $u['last_name']) ?></b>
    <div class="muted small" dir="ltr">@<?= e($u['username']) ?> · <?= e($u['city'] ?: '') ?></div>
  </div>
  <a class="btn sm ghost" href="<?= url('profile.php') ?>">ویرایش</a>
</div>

<div class="menu-list">
  <a class="menu-item" href="<?= url('notifications.php') ?>"><span class="mi-ic">🔔</span> اعلان‌ها
    <?php if ($unread): ?><span class="chip c-danger"><?= fa_num($unread) ?> جدید</span><?php endif; ?>
    <span class="mi-arrow">‹</span></a>
  <a class="menu-item" href="<?= url('tasks.php') ?>"><span class="mi-ic">✅</span> تسک‌ها
    <span class="chip"><?= fa_num((int)$counts['tasks']) ?></span><span class="mi-arrow">‹</span></a>
  <a class="menu-item" href="<?= url('habits.php') ?>"><span class="mi-ic">🔁</span> عادت‌های روزانه
    <span class="chip"><?= fa_num((int)$counts['habits']) ?></span><span class="mi-arrow">‹</span></a>
  <a class="menu-item" href="<?= url('calendar.php') ?>"><span class="mi-ic">🗓</span> تقویم شمسی <span class="mi-arrow">‹</span></a>
  <a class="menu-item" href="<?= url('notes.php') ?>"><span class="mi-ic">📝</span> یادداشت‌ها
    <span class="chip"><?= fa_num((int)$counts['notes']) ?></span><span class="mi-arrow">‹</span></a>
  <a class="menu-item" href="<?= url('goals.php') ?>"><span class="mi-ic">🎯</span> اهداف و برنامه‌ها
    <span class="chip"><?= fa_num((int)$counts['goals']) ?></span><span class="mi-arrow">‹</span></a>
  <a class="menu-item" href="<?= url('agents.php') ?>"><span class="mi-ic">🤖</span> ایجنت‌های هوشمند <span class="mi-arrow">‹</span></a>
  <a class="menu-item" href="<?= url('personality.php') ?>"><span class="mi-ic">🧠</span> تست شخصیت <span class="mi-arrow">‹</span></a>
  <a class="menu-item" href="<?= url('profile.php') ?>"><span class="mi-ic">👤</span> پروفایل و تنظیمات <span class="mi-arrow">‹</span></a>
  <?php if (is_admin()): ?>
  <a class="menu-item" href="<?= url('admin/index.php') ?>" style="border-color:color-mix(in srgb, var(--warn) 40%, var(--line))"><span class="mi-ic" style="background:color-mix(in srgb, var(--warn) 18%, var(--card))">⚙</span> پنل مدیریت <span class="mi-arrow">‹</span></a>
  <?php endif; ?>
  <a class="menu-item danger" href="<?= url('logout.php') ?>"><span class="mi-ic">🚪</span> خروج از حساب</a>
</div>
<p class="muted small center mt">نسخه <?= e(APP_VERSION) ?> · <?= nl2br(e(content('footer_text', ''))) ?></p>
<?php include __DIR__ . '/includes/footer.php'; ?>
