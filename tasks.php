<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
[$jy, $jm, $jd] = today_jalali();
$today = j_today_str();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $title = trim(mb_substr($_POST['title'] ?? '', 0, 160));
        $type = in_array($_POST['type'] ?? '', ['daily', 'weekly', 'once']) ? $_POST['type'] : 'daily';
        $date = j_parse($_POST['jdate'] ?? '') ? trim($_POST['jdate']) : $today;
        $hour = preg_match('/^\d{1,2}:\d{2}$/', $_POST['hour'] ?? '') ? $_POST['hour'] : '';
        $priority = max(1, min(3, (int)($_POST['priority'] ?? 2)));
        if ($title !== '') {
            $weekStart = $type === 'weekly' ? j_week_bounds(...j_parse($date))[0] : '';
            db()->prepare("INSERT INTO tasks (user_id,title,type,jdate,week_start,hour,priority) VALUES (?,?,?,?,?,?,?)")
                ->execute([$uid, $title, $type, $type === 'weekly' ? '' : $date, $weekStart, $hour, $priority]);
            flash('success', '«' . $title . '» اضافه شد ✅');
        }
    }
    if ($act === 'delete') {
        db()->prepare("UPDATE tasks SET status='deleted' WHERE id=? AND user_id=?")
            ->execute([(int)($_POST['id'] ?? 0), $uid]);
        flash('info', 'تسک حذف شد.');
    }
    redirect('tasks.php');
}

$day = ai_day_stats($uid, $today);

$st = db()->prepare("SELECT * FROM tasks WHERE user_id=? AND type IN ('daily','once') AND jdate > ? AND status<>'deleted' ORDER BY jdate, hour LIMIT 15");
$st->execute([$uid, $today]);
$upcoming = $st->fetchAll();

$st = db()->prepare("SELECT * FROM tasks WHERE user_id=? AND type IN ('daily','once') AND jdate < ? AND status='pending' AND jdate >= ? ORDER BY jdate DESC LIMIT 10");
$st->execute([$uid, $today, j_str(...j_add_days($jy, $jm, $jd, -14))]);
$overdue = $st->fetchAll();

$page_title = 'تسک‌ها';
$active = 'tasks';
include __DIR__ . '/includes/header.php';
?>
<script>window.APP.loggedIn = true;</script>
<h1>✅ تسک‌ها</h1>

<!-- تسک جدید -->
<div class="card" id="addCard" style="margin-bottom:14px">
  <h2>➕ تسک جدید</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="act" value="add">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <input type="text" name="title" id="newTitle" required placeholder="مثلاً: تمرین آواز خوانی" style="flex:2;min-width:180px">
      <input type="text" name="jdate" dir="ltr" placeholder="تاریخ <?= e($today) ?>" value="<?= e($today) ?>" style="flex:1;min-width:110px">
      <input type="time" name="hour" style="flex:1;min-width:100px">
      <select name="type" style="flex:1;min-width:110px">
        <option value="daily">روزانه</option>
        <option value="weekly">هفتگی</option>
        <option value="once">موردی/قرار</option>
      </select>
      <button class="btn">افزودن</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>امروز <?php if ($day['total']): ?><span class="chip c-primary" style="margin-inline-start:auto"><?= fa_num($day['done_count']) ?> از <?= fa_num($day['total']) ?></span><?php endif; ?></h2>
  <?php if (!$day['tasks']): ?><p class="muted">تسکی برای امروز نیست ☀</p><?php endif; ?>
  <div class="item-list">
    <?php foreach ($day['tasks'] as $t): ?>
    <div class="item <?= $t['status'] === 'done' ? 'done' : '' ?>">
      <button class="check <?= $t['status'] === 'done' ? 'on' : ($t['status'] === 'skipped' ? 'miss' : '') ?>" data-task="<?= $t['id'] ?>">✓</button>
      <div class="grow">
        <div class="title"><?= e($t['title']) ?></div>
        <div class="meta">
          <?= $t['hour'] ? '⏰ ' . fa_num($t['hour']) : '' ?>
          <?php if ($t['type'] === 'weekly'): ?> <span class="chip">هفتگی</span><?php endif; ?>
          <?php if ($t['source'] === 'ai'): ?> <span class="chip c-info">🤖 بازبرنامه‌ریزی</span><?php endif; ?>
          <?php if ($t['priority'] == 1): ?> <span class="chip c-danger">فوری</span><?php endif; ?>
        </div>
      </div>
      <?php if ($t['status'] === 'pending'): ?>
        <button class="miss-link miss-btn" data-task="<?= $t['id'] ?>" data-title="<?= e($t['title']) ?>">انجام نشد</button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($overdue): ?>
<div class="card mt">
  <h2>⚠ عقب‌افتاده‌ها</h2>
  <div class="item-list">
    <?php foreach ($overdue as $t): ?>
    <div class="item">
      <button class="check" data-task="<?= $t['id'] ?>">✓</button>
      <div class="grow">
        <div class="title"><?= e($t['title']) ?></div>
        <div class="meta"><?= j_format($t['jdate'], false) ?></div>
      </div>
      <button class="miss-link miss-btn" data-task="<?= $t['id'] ?>" data-title="<?= e($t['title']) ?>">بررسی دلیل</button>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($upcoming): ?>
<div class="card mt">
  <h2>🔜 روزهای آینده</h2>
  <div class="item-list">
    <?php foreach ($upcoming as $t): ?>
    <div class="item">
      <div class="grow">
        <div class="title"><?= e($t['title']) ?></div>
        <div class="meta"><?= j_format($t['jdate'], false) ?> <?= $t['hour'] ? '⏰ ' . fa_num($t['hour']) : '' ?></div>
      </div>
      <form method="post" onsubmit="return confirm('حذف شود؟')"><?= csrf_field() ?>
        <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>">
        <button class="icon-btn" title="حذف">🗑</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['new'])): ?>
<script>document.getElementById('newTitle').focus();</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/skip_modal.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
