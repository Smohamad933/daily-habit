<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
[$ty, $tm, $td] = today_jalali();

$vy = (int)($_GET['y'] ?? $ty);
$vm = (int)($_GET['m'] ?? $tm);
if ($vm < 1) { $vm = 12; $vy--; }
if ($vm > 12) { $vm = 1; $vy++; }
$days = jalali_month_days($vy, $vm);
$firstDow = j_day_of_week($vy, $vm, 1);
$todayStr = j_today_str();

// آمار هر روز ماه (فقط روزهای گذشته و امروز محاسبه می‌شود تا صفحه سریع بماند)
$cellStats = [];
for ($d = 1; $d <= $days; $d++) {
    $ds = j_str($vy, $vm, $d);
    if (j_num($vy, $vm, $d) > j_num($ty, $tm, $td)) break;
    $s = ai_day_stats($uid, $ds);
    if ($s['total']) $cellStats[$d] = $s;
}

$py = $vm == 1 ? $vy - 1 : $vy;  $pm = $vm == 1 ? 12 : $vm - 1;
$ny = $vm == 12 ? $vy + 1 : $vy; $nm = $vm == 12 ? 1 : $vm + 1;

$page_title = 'تقویم ' . j_month_name($vm);
$active = 'calendar';
include __DIR__ . '/includes/header.php';
?>
<h1>🗓 تقویم شمسی</h1>
<div class="card">
  <div class="cal-nav">
    <a class="btn sm ghost" href="calendar.php?y=<?= $py ?>&m=<?= $pm ?>">→ ماه قبل</a>
    <b><?= j_month_name($vm) ?> <?= fa_num($vy) ?> <span class="muted small">(<?= fa_num($days) ?> روزه)</span></b>
    <a class="btn sm ghost" href="calendar.php?y=<?= $ny ?>&m=<?= $nm ?>">ماه بعد ←</a>
    <?php if ($vy != $ty || $vm != $tm): ?><a class="btn sm" href="calendar.php">ماه جاری</a><?php endif; ?>
  </div>
  <div class="cal-head">
    <div>شنبه</div><div>یکشنبه</div><div>دوشنبه</div><div>سه‌شنبه</div><div>چهارشنبه</div><div>پنجشنبه</div><div>جمعه</div>
  </div>
  <div class="cal-grid">
    <?php for ($i = 0; $i < $firstDow; $i++): ?><div class="cal-cell blank"></div><?php endfor; ?>
    <?php for ($d = 1; $d <= $days; $d++): $ds = j_str($vy, $vm, $d); $s = $cellStats[$d] ?? null; ?>
    <div class="cal-cell <?= $ds === $todayStr ? 'today' : '' ?>">
      <a class="stretch" href="planner.php?d=<?= urlencode($ds) ?>" title="برنامه این روز"></a>
      <div class="dnum"><?= fa_num($d) ?></div>
      <?php if ($s): ?>
        <div class="dots">
          <?php if ($s['done_count']): ?><span class="dot g" title="انجام‌شده: <?= fa_num($s['done_count']) ?>"></span><?php endif; ?>
          <?php if ($s['total'] - $s['done_count'] > 0): ?><span class="dot r" title="انجام‌نشده"></span><?php endif; ?>
        </div>
        <div class="pct"><?= pct($s['percent']) ?> از <?= fa_num($s['total']) ?></div>
      <?php endif; ?>
    </div>
    <?php endfor; ?>
  </div>
  <p class="muted small mt">📌 طول این ماه: <?= fa_num($days) ?> روز — ماه‌های شمسی بسته به سال، ۲۹، ۳۰ یا ۳۱ روزه هستند. روی هر روز کلیک کنید تا برنامه‌اش را ببینید.</p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
