<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
[$jy, $jm, $jd] = today_jalali();
$today = j_today_str();

$day = ai_day_stats($uid, $today);
$monthStart = j_str($jy, $jm, 1);
$month = ai_range_stats($uid, $monthStart, $today);
[$ws, $we] = j_week_bounds($jy, $jm, $jd);
$week = ai_range_stats($uid, $ws, $we);

// عادت‌های امروز
$habits = db()->query("SELECT * FROM habits WHERE user_id=$uid AND archived=0")->fetchAll();
$habitLogs = [];
$st = db()->prepare("SELECT habit_id, progress FROM habit_logs WHERE user_id=? AND jdate=?");
$st->execute([$uid, $today]);
foreach ($st->fetchAll() as $l) $habitLogs[$l['habit_id']] = $l['progress'];

// سری ۷ روز گذشته برای نمودار داشبورد
$chartLabels = $chartValues = [];
for ($i = 6; $i >= 0; $i--) {
    [$ay, $am, $ad] = j_add_days($jy, $jm, $jd, -$i);
    $ds = ai_day_stats($uid, j_str($ay, $am, $ad));
    $chartLabels[] = fa_num($ad) . ' ' . mb_substr(j_month_name($am), 0, 3);
    $chartValues[] = $ds['score'];
}

// اعلان‌های اخیر
$st = db()->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 4");
$st->execute([$uid]);
$recentNotifs = $st->fetchAll();

$page_title = 'داشبورد';
$active = 'dashboard';
include __DIR__ . '/includes/header.php';
?>
<script>window.APP.loggedIn = true;</script>
<div class="page-head">
  <h1>سلام <?= e($u['first_name'] ?: $u['username']) ?> 👋</h1>
  <span class="chip c-primary"><?= j_format($today) ?></span>
</div>

<div class="grid cols-4" style="margin-bottom:16px">
  <div class="card stat"><div class="num"><?= pct($day['percent']) ?></div><div class="lbl">پیشرفت امروز</div></div>
  <div class="card stat <?= $week['percent'] >= 60 ? 'good' : '' ?>"><div class="num"><?= pct($week['percent']) ?></div><div class="lbl">این هفته</div></div>
  <div class="card stat <?= $month['percent'] >= 60 ? 'good' : '' ?>"><div class="num"><?= pct($month['percent']) ?></div><div class="lbl">این ماه</div></div>
  <div class="card stat <?= $month['percent'] - $month['prev_percent'] >= 0 ? 'good' : 'bad' ?>">
    <div class="num"><?= ($month['percent'] - $month['prev_percent'] >= 0 ? '+' : '−') . fa_num(abs($month['percent'] - $month['prev_percent'])) ?></div>
    <div class="lbl">رشد/پسرفت نسبت به بازه قبل</div>
  </div>
</div>

<div class="grid dash">
  <div>
    <div class="card">
      <h2>✅ کارهای امروز</h2>
      <?php if (!$day['tasks']): ?>
        <p class="muted">هنوز برای امروز تسکی ندارید. از <a href="tasks.php">بخش تسک‌ها</a> اضافه کنید.</p>
      <?php else: ?>
      <div class="item-list">
        <?php foreach ($day['tasks'] as $t): ?>
        <div class="item <?= $t['status'] === 'done' ? 'done' : '' ?>">
          <button class="check <?= $t['status'] === 'done' ? 'on' : ($t['status'] === 'skipped' ? 'miss' : '') ?>" data-task="<?= $t['id'] ?>">✓</button>
          <div class="grow">
            <div class="title"><?= e($t['title']) ?></div>
            <div class="meta">
              <?php if ($t['hour']): ?>⏰ <?= fa_num($t['hour']) ?> &nbsp;<?php endif; ?>
              <?= $t['type'] === 'weekly' ? '<span class="chip">هفتگی</span>' : '' ?>
              <?php if ($t['source'] === 'ai'): ?><span class="chip c-info">🤖 زمان‌بندی مجدد</span><?php endif; ?>
            </div>
          </div>
          <?php if ($t['status'] !== 'done'): ?>
            <button class="btn sm ghost miss-btn" data-task="<?= $t['id'] ?>" data-title="<?= e($t['title']) ?>">انجام نشد</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="mt"><a class="btn sm" href="planner.php">📅 رفتن به برنامه امروز</a></div>
    </div>

    <div class="card mt">
      <h2>🔁 عادت‌های امروز</h2>
      <?php if (!$habits): ?>
        <p class="muted">عادتی تعریف نشده. از <a href="habits.php">بخش عادت‌ها</a> شروع کنید.</p>
      <?php else: ?>
      <div class="item-list">
        <?php foreach ($habits as $h): $val = (int)($habitLogs[$h['id']] ?? 0); ?>
        <div class="item">
          <span style="color:<?= e($h['color']) ?>">●</span>
          <div class="grow">
            <div class="title"><?= e($h['title']) ?></div>
            <input type="range" class="habit-range" min="0" max="100" step="5" value="<?= $val ?>"
                   data-habit="<?= $h['id'] ?>" data-date="<?= e($today) ?>" data-title="<?= e($h['title']) ?>" style="width:100%">
          </div>
          <b class="habit-out pct-label" data-habit="<?= $h['id'] ?>" data-date="<?= e($today) ?>"><?= fa_num($val) ?>٪</b>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="card mt">
      <h2>📈 روند ۷ روز اخیر</h2>
      <div class="chart-box"><canvas id="dashLine"></canvas></div>
    </div>
  </div>

  <div>
    <div class="card center">
      <h2 style="justify-content:center">امروز</h2>
      <div class="ring-wrap"><div class="ring" id="todayRing"></div></div>
      <?php if ($day['undone']): ?>
        <p class="muted small mt">انجام‌نشده‌ها: <?= e(implode('، ', array_slice($day['undone'], 0, 3))) ?><?= count($day['undone']) > 3 ? '…' : '' ?></p>
      <?php endif; ?>
    </div>

    <div class="card mt">
      <h2>🔔 اعلان‌های اخیر</h2>
      <?php if (!$recentNotifs): ?><p class="muted small">اعلانی ندارید.</p><?php endif; ?>
      <div class="item-list">
        <?php foreach ($recentNotifs as $n): ?>
        <div class="notif <?= $n['is_read'] ? '' : 'unread' ?>" style="border:none;padding:8px 4px">
          <div class="nbody">
            <div class="ntitle small"><?= e($n['title']) ?></div>
            <div class="ntime"><?= fa_num(date('H:i', strtotime($n['created_at']))) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <a href="notifications.php" class="btn sm ghost mt">همه اعلان‌ها</a>
    </div>

    <div class="card mt">
      <h2>⚡ دسترسی سریع</h2>
      <div class="grid cols-2" style="gap:8px">
        <a class="btn sm ghost" href="notes.php">📝 یادداشت</a>
        <a class="btn sm ghost" href="goals.php">🎯 اهداف</a>
        <a class="btn sm ghost" href="agents.php">🤖 ایجنت‌ها</a>
        <a class="btn sm ghost" href="reports.php">📊 گزارش ماهانه</a>
      </div>
    </div>
  </div>
</div>

<script>
window.CHART_DEFS = [
  { id: 'dashLine', type: 'line', labels: <?= json_encode($chartLabels) ?>, values: <?= json_encode($chartValues) ?>, opts: { yMax: 100 } },
  { id: 'todayRing', type: 'ring', percent: <?= (int)$day['score'] ?>, label: 'امتیاز امروز' }
];
</script>

<?php /* مودال دلیل انجام نشدن */ include __DIR__ . '/includes/skip_modal.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
