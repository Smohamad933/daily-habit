<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
[$jy, $jm, $jd] = today_jalali();
$today = j_today_str();

// انتخاب ماه گزارش (پیش‌فرض: ماه جاری)
$ry = (int)($_GET['y'] ?? $jy);
$rm = (int)($_GET['m'] ?? $jm);
if ($rm < 1) { $rm = 12; $ry--; }
if ($rm > 12) { $rm = 1; $ry++; }

$rep = ai_monthly_report($uid, $ry, $rm);
$day = ai_day_stats($uid, $today);
[$ws, $we] = j_week_bounds($jy, $jm, $jd);
$week = ai_range_stats($uid, $ws, $we);

// داده‌های نمودارها
$weeks = j_month_weeks($ry, $rm);
$weekLabels = $weekValues = [];
foreach ($rep['by_week'] as $wi => $w) {
    $weekLabels[] = 'هفته ' . fa_num($wi + 1);
    $weekValues[] = $w['total'] ? round($w['done'] / $w['total'] * 100) : 0;
}
$dailyLabels = array_map(fn($d) => fa_num($d['day']), $rep['daily_series']);
$dailyValues = array_column($rep['daily_series'], 'percent');

$reasonItems = [];
$palette = ['#ef4767', '#f5a623', '#7c5cff', '#2f9df4', '#22c793', '#e0519a', '#8a94a6', '#c94f2e', '#4fb286', '#6d7390'];
foreach ($rep['reasons'] as $i => $r) {
    $reasonItems[] = ['label' => $r['label'], 'value' => $r['count'], 'color' => $palette[$i % count($palette)]];
}
$hourItems = [];
if ($rep['hour_missed']) {
    foreach ($rep['hour_missed'] as $h => $m) {
        $d = $rep['hour_done'][$h] ?? 0;
        $rate = round($m / ($m + $d) * 100);
        $hourItems[] = ['label' => 'ساعت ' . fa_num($h), 'value' => $rate, 'suffix' => fa_num($rate) . '٪', 'color' => $rate >= 60 ? '#ef4767' : '#f5a623'];
    }
    usort($hourItems, fn($a, $b) => $b['value'] <=> $a['value']);
}

$page_title = 'گزارش‌ها و تحلیل';
$active = 'reports';
include __DIR__ . '/includes/header.php';
?>
<h1>📊 گزارش‌ها و تحلیل هوشمند</h1>
<div class="cal-nav">
  <?php $pm = $rm == 1 ? [$ry - 1, 12] : [$ry, $rm - 1]; $nm = $rm == 12 ? [$ry + 1, 1] : [$ry, $rm + 1]; ?>
  <a class="btn sm ghost" href="reports.php?y=<?= $pm[0] ?>&m=<?= $pm[1] ?>">→ ماه قبل</a>
  <b><?= j_month_name($rm) ?> <?= fa_num($ry) ?></b>
  <a class="btn sm ghost" href="reports.php?y=<?= $nm[0] ?>&m=<?= $nm[1] ?>">ماه بعد ←</a>
</div>

<div class="grid cols-4" style="margin-bottom:16px">
  <div class="card stat"><div class="num"><?= pct($rep['percent']) ?></div><div class="lbl">انجام‌شد کل ماه</div></div>
  <div class="card stat <?= $rep['delta'] >= 0 ? 'good' : 'bad' ?>">
    <div class="num"><?= ($rep['delta'] >= 0 ? '+' : '−') . fa_num(abs($rep['delta'])) ?></div>
    <div class="lbl"><?= $rep['delta'] >= 0 ? 'رشد نسبت به ماه قبل 📈' : 'پسرفت نسبت به ماه قبل 📉' ?></div></div>
  <div class="card stat good"><div class="num"><?= fa_num($rep['done']) ?></div><div class="lbl">کار انجام‌شده</div></div>
  <div class="card stat bad"><div class="num"><?= fa_num($rep['skipped']) ?></div><div class="lbl">کار انجام‌نشده</div></div>
</div>

<div class="grid cols-2" style="align-items:start">
  <div class="card">
    <h2>📅 درصد هفته‌های ماه</h2>
    <div class="chart-box"><canvas id="weekBar"></canvas></div>
  </div>
  <div class="card">
    <h2>📈 روند روزانه ماه</h2>
    <div class="chart-box"><canvas id="dailyLine"></canvas></div>
  </div>
</div>

<div class="grid cols-2 mt" style="align-items:start">
  <div class="card">
    <h2>🧩 چرا کارها انجام نشد؟</h2>
    <?php if (!$rep['reasons']): ?><p class="muted small">در این ماه گزارشی از دلایل ثبت نشده است.</p><?php endif; ?>
    <div class="grid cols-2" style="align-items:center">
      <div class="chart-box"><canvas id="reasonDonut"></canvas></div>
      <div class="item-list">
        <?php foreach ($rep['reasons'] as $r): ?>
        <div class="item" style="padding:8px 12px">
          <span class="chip <?= $r['negative'] ? 'c-danger' : 'c-good' ?>"><?= $r['negative'] ? 'منفی' : 'مثبت' ?></span>
          <div class="grow small"><?= e($r['label']) ?></div>
          <b class="small"><?= fa_num($r['count']) ?>× (<?= pct($r['percent']) ?>)</b>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($rep['reasons']): ?>
    <div class="grid cols-2 mt">
      <div class="stat card"><div class="num" style="color:var(--danger)"><?= pct($rep['neg_percent']) ?></div><div class="lbl">سهم دلایل منفی</div></div>
      <div class="stat card"><div class="num" style="color:var(--accent)"><?= pct($rep['pos_percent']) ?></div><div class="lbl">سهم دلایل مثبت/موجه</div></div>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>⏰ تحلیل ساعت‌های ریزش</h2>
    <?php if ($hourItems): ?>
      <p class="muted small">درصد انجام نشدن تسک‌ها به تفکیک ساعت روز:</p>
      <div class="chart-box"><canvas id="hourBar"></canvas></div>
      <?php if ($rep['worst_hour'] !== null): ?>
        <p class="small mt">⚠ بیشترین ریزش شما حدود <b>ساعت <?= fa_num($rep['worst_hour']) ?></b> است.<?php if ($rep['worst_dow'] !== null): ?> و پرتکرارترین روز: <b><?= j_weekday_name($rep['worst_dow']) ?></b>.<?php endif; ?></p>
      <?php endif; ?>
    <?php else: ?><p class="muted small">داده کافی برای تحلیل ساعتی نیست (تسک‌ها بدون ساعت بودند یا انجام‌نشده‌ای نبود).</p><?php endif; ?>
  </div>
</div>

<div class="card mt">
  <h2>🧭 تیپ رفتاری این ماه</h2>
  <div class="item" style="border:none;background:var(--chip)">
    <div class="grow">
      <div class="title"><?= e($rep['ptype_label']) ?></div>
      <div class="small muted"><?= e($rep['behavior']['desc']) ?></div>
    </div>
  </div>
  <?php if ($rep['patterns']): ?>
    <h3 class="mt">عادت‌های منفی شناسایی‌شده</h3>
    <div class="item-list">
      <?php foreach ($rep['patterns'] as $p): ?><div class="item"><div class="grow small">⚠ <?= e($p) ?></div></div><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($rep['advice']): ?>
    <h3 class="mt">پیشنهاد هوش مصنوعی برای ماه بعد</h3>
    <div class="item-list">
      <?php foreach ($rep['advice'] as $a): ?><div class="item"><div class="grow small"><?= e($a) ?></div></div><?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card mt">
  <h2>🔁 عملکرد عادت‌ها در این ماه</h2>
  <?php if (!$rep['habits']): ?><p class="muted small">عادتی برای این ماه ثبت نشده است.</p><?php endif; ?>
  <div class="item-list">
    <?php foreach ($rep['habits'] as $h): ?>
    <div class="item">
      <div class="grow">
        <div class="title small"><?= e($h['title']) ?></div>
        <div class="bar slim mt"><i style="width:<?= (int)$h['avg'] ?>%"></i></div>
      </div>
      <b class="pct-label small"><?= pct($h['avg']) ?></b>
      <span class="chip"><?= fa_num($h['days']) ?> روز ثبت</span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card mt">
  <h2>🕒 خلاصه سریع امروز</h2>
  <div class="grid cols-3">
    <div class="stat card"><div class="num"><?= pct($day['percent']) ?></div><div class="lbl">پیشرفت امروز</div></div>
    <div class="stat card good"><div class="num"><?= fa_num($day['done_count']) ?></div><div class="lbl">انجام‌شده امروز</div></div>
    <div class="stat card bad"><div class="num"><?= fa_num(count($day['undone'])) ?></div><div class="lbl">انجام‌نشده امروز</div></div>
  </div>
</div>

<script>
window.CHART_DEFS = [
  { id: 'weekBar', type: 'bar', labels: <?= json_encode($weekLabels) ?>, values: <?= json_encode($weekValues) ?>, opts: { yMax: 100 } },
  { id: 'dailyLine', type: 'line', labels: <?= json_encode($dailyLabels) ?>, values: <?= json_encode($dailyValues) ?>, opts: { yMax: 100 } },
  { id: 'reasonDonut', type: 'donut', items: <?= json_encode($reasonItems, JSON_UNESCAPED_UNICODE) ?>, opts: { height: 200, center: <?= json_encode(fa_num(array_sum(array_column($reasonItems, 'value')))) ?> } },
  { id: 'hourBar', type: 'hbar', items: <?= json_encode($hourItems, JSON_UNESCAPED_UNICODE) ?> }
];
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
