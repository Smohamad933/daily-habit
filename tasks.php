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
            flash('success', 'تسک «' . $title . '» اضافه شد.');
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
[$ws, $we] = j_week_bounds($jy, $jm, $jd);

// تسک‌های آینده و گذشته
$st = db()->prepare("SELECT * FROM tasks WHERE user_id=? AND type IN ('daily','once') AND jdate > ? AND status<>'deleted' ORDER BY jdate, hour LIMIT 20");
$st->execute([$uid, $today]);
$upcoming = $st->fetchAll();

$st = db()->prepare("SELECT * FROM tasks WHERE user_id=? AND type IN ('daily','once') AND jdate < ? AND status='pending' AND jdate >= ? ORDER BY jdate DESC LIMIT 20");
$st->execute([$uid, $today, j_str(...j_add_days($jy, $jm, $jd, -14))]);
$overdue = $st->fetchAll();

$page_title = 'تسک‌ها';
$active = 'tasks';
include __DIR__ . '/includes/header.php';
?>
<script>window.APP.loggedIn = true;</script>
<h1>✅ تسک‌ها</h1>
<div class="grid dash" style="align-items:start">
  <div>
    <div class="card">
      <h2>امروز — <?= j_format($today, false) ?></h2>
      <?php if (!$day['tasks']): ?><p class="muted">تسکی برای امروز نیست.</p><?php endif; ?>
      <div class="item-list">
        <?php foreach ($day['tasks'] as $t): ?>
        <div class="item <?= $t['status'] === 'done' ? 'done' : '' ?>">
          <button class="check <?= $t['status'] === 'done' ? 'on' : ($t['status'] === 'skipped' ? 'miss' : '') ?>" data-task="<?= $t['id'] ?>">✓</button>
          <div class="grow">
            <div class="title"><?= e($t['title']) ?></div>
            <div class="meta">
              <?= $t['hour'] ? '⏰ ' . fa_num($t['hour']) : '' ?>
              <?php if ($t['type'] === 'weekly'): ?><span class="chip">هفتگی</span><?php endif; ?>
              <?php if ($t['source'] === 'ai'): ?><span class="chip c-info">🤖 بازبرنامه‌ریزی</span><?php endif; ?>
              <?php if ($t['priority'] == 1): ?><span class="chip c-danger">فوری</span><?php endif; ?>
              <?php if ($t['status'] === 'skipped'): ?><span class="chip c-danger">امروز انجام نشد</span><?php endif; ?>
            </div>
          </div>
          <?php if ($t['status'] === 'pending'): ?>
            <button class="btn sm ghost miss-btn" data-task="<?= $t['id'] ?>" data-title="<?= e($t['title']) ?>">انجام نشد</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($overdue): ?>
    <div class="card mt">
      <h2>⚠ عقب‌افتاده‌های ۲ هفته اخیر</h2>
      <div class="item-list">
        <?php foreach ($overdue as $t): ?>
        <div class="item">
          <div class="grow">
            <div class="title"><?= e($t['title']) ?></div>
            <div class="meta"><?= j_format($t['jdate'], false) ?></div>
          </div>
          <button class="check" data-task="<?= $t['id'] ?>">✓</button>
          <button class="btn sm ghost miss-btn" data-task="<?= $t['id'] ?>" data-title="<?= e($t['title']) ?>">بررسی</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($upcoming): ?>
    <div class="card mt">
      <h2>🔜 آینده</h2>
      <div class="item-list">
        <?php foreach ($upcoming as $t): ?>
        <div class="item">
          <div class="grow">
            <div class="title"><?= e($t['title']) ?></div>
            <div class="meta"><?= j_format($t['jdate'], false) ?> <?= $t['hour'] ? '⏰ ' . fa_num($t['hour']) : '' ?></div>
          </div>
          <form method="post" onsubmit="return confirm('حذف شود؟')"><?= csrf_field() ?>
            <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>">
            <button class="btn sm ghost">🗑</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>➕ تسک جدید</h2>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="act" value="add">
      <div class="field"><label>عنوان تسک</label><input type="text" name="title" required placeholder="مثلاً: تمرین آواز خوانی"></div>
      <div class="field"><label>نوع</label>
        <select name="type">
          <option value="daily">روزانه (یک تاریخ مشخص)</option>
          <option value="weekly">هفتگی (برای کل هفته جاری)</option>
          <option value="once">موردی / قرار ملاقات</option>
        </select>
      </div>
      <div class="field"><label>تاریخ (شمسی)</label><input type="text" name="jdate" dir="ltr" placeholder="<?= e($today) ?>" value="<?= e($today) ?>"></div>
      <div class="inline-fields">
        <div class="field"><label>ساعت (اختیاری)</label><input type="time" name="hour"></div>
        <div class="field"><label>اولویت</label>
          <select name="priority"><option value="2">عادی</option><option value="1">فوری</option><option value="3">کم‌اهمیت</option></select>
        </div>
      </div>
      <button class="btn block">افزودن تسک</button>
    </form>
    <p class="muted small mt">💡 اگر تسکی را «انجام نشد» بزنید، هوش مصنوعی دلیلش را می‌پرسد و در روزهای کم‌تراکم هفته دوباره برنامه‌ریزی‌اش می‌کند.</p>
  </div>
</div>
<?php include __DIR__ . '/includes/skip_modal.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
