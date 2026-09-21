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
$delta = $month['percent'] - $month['prev_percent'];
$rail = [];
for ($offset = -2; $offset <= 2; $offset++) {
    [$ry, $rm, $rd] = j_add_days($jy, $jm, $jd, $offset);
    $rail[] = [
        'date' => j_str($ry, $rm, $rd),
        'day' => $rd,
        'month' => j_month_name($rm),
        'today' => $offset === 0,
    ];
}

// عادت‌های امروز
$habits = db()->query("SELECT * FROM habits WHERE user_id=$uid AND archived=0 ORDER BY id")->fetchAll();
$habitLogs = [];
$st = db()->prepare("SELECT habit_id, progress FROM habit_logs WHERE user_id=? AND jdate=?");
$st->execute([$uid, $today]);
foreach ($st->fetchAll() as $l) $habitLogs[$l['habit_id']] = $l['progress'];

// اعلان‌های اخیر (فقط ۲ تا)
$st = db()->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 2");
$st->execute([$uid]);
$recentNotifs = $st->fetchAll();

$page_title = 'امروز';
$active = 'dashboard';
include __DIR__ . '/includes/header.php';
?>
<script>window.APP.loggedIn = true;</script>

<div class="greet">
  <div class="avatar"><?= e(mb_substr($u['first_name'] ?: $u['username'], 0, 1)) ?></div>
  <div>
    <div class="g-name">سلام <?= e($u['first_name'] ?: $u['username']) ?> </div>
    <div class="g-date"><?= j_format($today) ?></div>
  </div>
  <a class="btn sm ghost" style="margin-inline-start:auto" href="<?= url('planner.php') ?>"> پلنر</a>
</div>

<div class="date-rail">
  <div class="date-rail-title"><b>برنامه امروز</b><small><?= j_format($today, false) ?></small></div>
  <div class="date-pills">
    <?php foreach ($rail as $dayItem): ?>
      <a class="date-pill <?= $dayItem['today'] ? 'is-today' : '' ?>" href="<?= url('planner.php') ?>?d=<?= urlencode($dayItem['date']) ?>">
        <span><?= $dayItem['today'] ? 'امروز' : e($dayItem['month']) ?></span>
        <b><?= fa_num($dayItem['day']) ?></b>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- کارت قهرمان: امتیاز امروز -->
<div class="card pad-lg" style="display:flex;align-items:center;gap:18px;flex-wrap:wrap">
  <div class="summary-head">
    <span class="summary-kicker">پیشرفت روزانه</span>
    <h2>کارهای امروز</h2>
    <small><?= fa_num($day['done_count']) ?> از <?= fa_num($day['total']) ?> تسک انجام شده</small>
  </div>
  <div class="ring" id="todayRing"></div>
  <div class="grid stats-row" style="flex:1;min-width:230px;grid-template-columns:repeat(3,1fr);gap:8px;margin:0">
    <div class="stat"><div class="num"><?= pct($day['percent']) ?></div><div class="lbl">امروز</div></div>
    <div class="stat"><div class="num"><?= pct($week['percent']) ?></div><div class="lbl">این هفته</div></div>
    <div class="stat"><div class="num"><?= pct($month['percent']) ?></div><div class="lbl">این ماه</div></div>
  </div>
  <div style="width:100%">
    <?php if ($month['total'] > 0): ?>
      <span class="chip <?= $delta >= 0 ? 'c-good' : 'c-danger' ?>">
        <?= $delta >= 0 ? ' رشد' : ' پسرفت' ?> <?= fa_num(abs($delta)) ?> واحد نسبت به بازه قبل
      </span>
    <?php endif; ?>
    <?php if ($day['percent'] == 100 && $day['total'] > 0): ?><span class="chip c-good"><?= app_icon('check-circle') ?> روز کامل!</span><?php endif; ?>
  </div>
</div>

<!-- کارهای امروز -->
<div class="card mt">
  <h2><?= app_icon('check-circle') ?> کارهای امروز
    <?php if ($day['total']): ?><span class="chip c-primary" style="margin-inline-start:auto"><?= fa_num($day['done_count']) ?> از <?= fa_num($day['total']) ?></span><?php endif; ?>
  </h2>
  <?php if (!$day['tasks']): ?>
    <p class="muted">برای امروز کاری نداری. با دکمه <b>+</b> پایین، اولین تسکت را بساز </p>
  <?php else: ?>
  <div class="item-list">
    <?php foreach ($day['tasks'] as $t): ?>
    <div class="item <?= $t['status'] === 'done' ? 'done' : '' ?>">
      <button class="check <?= $t['status'] === 'done' ? 'on' : ($t['status'] === 'skipped' ? 'miss' : '') ?>" data-task="<?= $t['id'] ?>"><?= app_icon('check') ?></button>
      <div class="grow">
        <div class="title"><?= e($t['title']) ?></div>
        <div class="meta">
          <?php if ($t['hour']): ?> <?= fa_num($t['hour']) ?><?php endif; ?>
          <?php if ($t['type'] === 'weekly'): ?> <span class="chip">هفتگی</span><?php endif; ?>
          <?php if ($t['source'] === 'ai'): ?> <span class="chip c-info"> بازبرنامه‌ریزی</span><?php endif; ?>
          <?php if ($t['status'] === 'skipped'): ?> <span class="chip c-danger">انجام نشد</span><?php endif; ?>
        </div>
      </div>
      <?php if ($t['status'] === 'pending'): ?>
        <button class="miss-link miss-btn" data-task="<?= $t['id'] ?>" data-title="<?= e($t['title']) ?>">انجام نشد</button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- عادت‌های امروز -->
<div class="card mt">
  <h2><?= app_icon('repeat') ?> عادت‌های امروز <a href="<?= url('habits.php') ?>" class="small" style="margin-inline-start:auto">مدیریت ←</a></h2>
  <?php if (!$habits): ?>
    <p class="muted">عادتی نداری. مثلاً «تمرین آواز» یا «مطالعه» را به‌عادت تبدیل کن تا زنجیره‌اش ساخته شود.</p>
    <a class="btn sm" href="<?= url('habits.php') ?>">ساخت اولین عادت</a>
  <?php else: ?>
  <div class="item-list">
    <?php foreach ($habits as $h): $val = (int)($habitLogs[$h['id']] ?? 0); ?>
    <div class="item" style="flex-wrap:wrap">
      <span style="width:12px;height:12px;border-radius:99px;background:<?= e($h['color']) ?>;flex-shrink:0"></span>
      <div class="grow" style="min-width:140px">
        <div class="title"><?= e($h['title']) ?></div>
        <input type="range" class="habit-range" min="0" max="100" step="5" value="<?= $val ?>"
               data-habit="<?= $h['id'] ?>" data-date="<?= e($today) ?>" data-title="<?= e($h['title']) ?>" style="width:100%">
      </div>
      <b class="habit-out pct-label" data-habit="<?= $h['id'] ?>" data-date="<?= e($today) ?>" style="color:<?= e($h['color']) ?>"><?= fa_num($val) ?>٪</b>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- اعلان‌های اخیر -->
<?php if ($recentNotifs): ?>
<div class="card mt">
  <h2><?= app_icon('bell') ?> آخرین اعلان‌ها <a href="<?= url('notifications.php') ?>" class="small" style="margin-inline-start:auto">همه ←</a></h2>
  <div class="item-list">
    <?php foreach ($recentNotifs as $n): ?>
    <div class="notif <?= $n['is_read'] ? '' : 'unread' ?>" style="border:none;padding:6px 2px">
      <div class="nbody">
        <div class="ntitle"><?= e($n['title']) ?></div>
        <div class="ntext" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= e($n['body']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script>
window.CHART_DEFS = [{ id: 'todayRing', type: 'ring', percent: <?= (int)$day['score'] ?>, label: 'امتیاز امروز' }];
</script>
<?php include __DIR__ . '/includes/skip_modal.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
