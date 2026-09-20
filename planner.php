<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];

// تاریخ مورد نظر (پیش‌فرض امروز)
$dp = j_parse($_GET['d'] ?? '') ?: today_jalali();
[$jy, $jm, $jd] = $dp;
$daysInMonth = jalali_month_days($jy, $jm);
if ($jd > $daysInMonth) $jd = $daysInMonth;
$date = j_str($jy, $jm, $jd);
$today = j_today_str();

// اسلات‌های ساعتی
$st = db()->prepare("SELECT hour, title FROM plan_slots WHERE user_id=? AND jdate=?");
$st->execute([$uid, $date]);
$slots = [];
foreach ($st->fetchAll() as $s) $slots[(int)$s['hour']] = $s['title'];

// تسک‌های این روز
$day = ai_day_stats($uid, $date);
$tasksByHour = [];
foreach ($day['tasks'] as $t) {
    $h = $t['hour'] !== '' ? (int)substr($t['hour'], 0, 2) : null;
    $tasksByHour[$h][] = $t;
}

// بازه ساعات نمایش
$wakeH = max(0, min(23, (int)substr($u['wake_time'] ?: '06:30', 0, 2)));
$sleepH = max(0, min(23, (int)substr($u['sleep_time'] ?: '23:00', 0, 2)));
$hFrom = max(5, $wakeH - 1);
$hTo = min(23, max($sleepH + 1, $wakeH + 10));

$prev = j_str(...j_add_days($jy, $jm, $jd, -1));
$next = j_str(...j_add_days($jy, $jm, $jd, +1));

$page_title = 'برنامه روز';
$active = 'planner';
include __DIR__ . '/includes/header.php';
?>
<script>window.APP.loggedIn = true;</script>
<div class="page-head">
  <h1>📅 برنامه روز</h1>
  <div class="cal-nav" style="margin:0">
    <a class="btn sm ghost" href="planner.php?d=<?= urlencode($prev) ?>">→ روز قبل</a>
    <b><?= j_format($date) ?></b>
    <a class="btn sm ghost" href="planner.php?d=<?= urlencode($next) ?>">روز بعد ←</a>
    <?php if ($date !== $today): ?><a class="btn sm" href="planner.php">امروز</a><?php endif; ?>
  </div>
</div>

<div class="grid cols-2" style="align-items:start">
  <div class="card">
    <h2>⏱ ساعت‌بندی روز <span class="muted small">(<?= fa_num($hFrom) ?> تا <?= fa_num($hTo) ?>)</span></h2>
    <p class="muted small">برای هر ساعت بنویسید چه برنامه‌ای دارید؛ با تایپ‌کردن، خودکار ذخیره می‌شود. 🤖 ایجنت از همین برنامه برای پیشنهاد ساعت تسک‌های عقب‌افتاده استفاده می‌کند.</p>
    <div class="hour-list">
      <?php for ($h = $hFrom; $h <= $hTo; $h++): $val = $slots[$h] ?? ''; ?>
      <div class="hour-row <?= $val !== '' ? 'filled' : '' ?>">
        <span class="hr"><?= fa_num(sprintf('%02d:00', $h)) ?></span>
        <input type="text" class="slot-input" data-date="<?= e($date) ?>" data-hour="<?= $h ?>"
               value="<?= e($val) ?>" placeholder="مثلاً: کار عمیق، ناهار، باشگاه…">
        <?php if (!empty($tasksByHour[$h])): ?>
          <div style="grid-column:2/-1;display:flex;gap:6px;flex-wrap:wrap">
            <?php foreach ($tasksByHour[$h] as $t): ?>
              <span class="chip <?= $t['status'] === 'done' ? 'c-good' : 'c-warn' ?>">
                <?= $t['status'] === 'done' ? '✅' : '⬜' ?> <?= e($t['title']) ?> (<?= fa_num($t['hour']) ?>)
              </span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>✅ تسک‌های این روز</h2>
      <?php if (!$day['tasks']): ?>
        <p class="muted">تسکی برای این روز نیست. <a href="tasks.php">افزودن تسک</a></p>
      <?php else: ?>
      <div class="item-list">
        <?php foreach ($day['tasks'] as $t): ?>
        <div class="item <?= $t['status'] === 'done' ? 'done' : '' ?>">
          <button class="check <?= $t['status'] === 'done' ? 'on' : ($t['status'] === 'skipped' ? 'miss' : '') ?>" data-task="<?= $t['id'] ?>">✓</button>
          <div class="grow">
            <div class="title"><?= e($t['title']) ?></div>
            <div class="meta">
              <?= $t['hour'] ? '⏰ ' . fa_num($t['hour']) : '' ?>
              <?= $t['type'] === 'weekly' ? '<span class="chip">هفتگی</span>' : '' ?>
              <?php if ($t['source'] === 'ai'): ?><span class="chip c-info">🤖</span><?php endif; ?>
            </div>
          </div>
          <?php if ($t['status'] === 'pending'): ?>
            <button class="btn sm ghost miss-btn" data-task="<?= $t['id'] ?>" data-title="<?= e($t['title']) ?>">انجام نشد</button>
          <?php elseif ($t['status'] === 'skipped'): ?>
            <span class="chip c-danger">انجام نشد</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="card mt center">
      <h2 style="justify-content:center">عملکرد این روز</h2>
      <div class="ring-wrap"><div class="ring" id="todayRing"></div></div>
      <?php if ($day['done_count']): ?><p class="small muted">انجام‌شده: <?= e(implode('، ', array_slice($day['done'], 0, 4))) ?></p><?php endif; ?>
      <?php if ($day['undone'] && $day['total']): ?><p class="small muted">انجام‌نشده: <?= e(implode('، ', array_slice($day['undone'], 0, 4))) ?></p><?php endif; ?>
    </div>

    <?php if ($date === $today): $sug = ai_suggest_hour($uid, $date); if ($sug['hour']): ?>
    <div class="card mt">
      <h2>💡 پیشنهاد هوشمند</h2>
      <p class="small">بهترین ساعت آزاد باقی‌مانده امروز برای یک کار مهم: <b><?= fa_num(sprintf('%02d:00', $sug['hour'])) ?></b><br>
      <span class="muted"><?= e($sug['why']) ?></span></p>
    </div>
    <?php endif; endif; ?>
  </div>
</div>

<script>window.CHART_DEFS = [{ id: 'todayRing', type: 'ring', percent: <?= (int)$day['score'] ?>, label: 'امتیاز روز' }];</script>
<?php include __DIR__ . '/includes/skip_modal.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
