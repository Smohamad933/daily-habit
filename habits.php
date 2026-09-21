<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
[$jy, $jm, $jd] = today_jalali();
$today = j_today_str();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $title = trim(mb_substr($_POST['title'] ?? '', 0, 120));
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#7652d6';
        if ($title !== '') {
            db()->prepare("INSERT INTO habits (user_id,title,description,color) VALUES (?,?,?,?)")
                ->execute([$uid, $title, trim($_POST['description'] ?? ''), $color]);
            flash('success', 'عادت «' . $title . '» ساخته شد  هر روز پیشرفتت را ثبت کن.');
        }
    }
    if ($act === 'archive') {
        db()->prepare("UPDATE habits SET archived=1 WHERE id=? AND user_id=?")->execute([(int)($_POST['id'] ?? 0), $uid]);
        flash('info', 'عادت بایگانی شد.');
    }
    redirect('habits.php');
}

$habits = db()->query("SELECT * FROM habits WHERE user_id=$uid AND archived=0 ORDER BY id")->fetchAll();

$last7 = [];
for ($i = 6; $i >= 0; $i--) $last7[] = j_str(...j_add_days($jy, $jm, $jd, -$i));

$logs = [];
$st = db()->prepare("SELECT habit_id, jdate, progress FROM habit_logs WHERE user_id=? AND jdate >= ?");
$st->execute([$uid, $last7[0]]);
foreach ($st->fetchAll() as $l) $logs[$l['habit_id']][$l['jdate']] = (int)$l['progress'];

function habit_streak(int $uid, int $habitId): int {
    [$jy, $jm, $jd] = today_jalali();
    $streak = 0;
    for ($i = 0; $i < 120; $i++) {
        $d = j_str(...j_add_days($jy, $jm, $jd, -$i));
        $st = db()->prepare("SELECT progress FROM habit_logs WHERE habit_id=? AND user_id=? AND jdate=?");
        $st->execute([$habitId, $uid, $d]);
        $p = $st->fetchColumn();
        if ($p !== false && $p >= 50) $streak++;
        elseif ($i == 0) continue;
        else break;
    }
    return $streak;
}

$page_title = 'عادت‌ها';
$active = 'habits';
include __DIR__ . '/includes/header.php';
?>
<script>window.APP.loggedIn = true;</script>
<div class="page-head">
  <h1 style="margin:0"><?= app_icon('repeat') ?> عادت‌های روزانه</h1>
</div>

<div class="card" style="margin-bottom:14px">
  <h2><?= app_icon('plus') ?> عادت جدید</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="act" value="add">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <input type="text" name="title" required placeholder="مثلاً: تمرین آواز خوانی" style="flex:2;min-width:170px">
      <input type="color" name="color" value="#7652d6" style="width:52px;height:44px;padding:4px;flex:none">
      <button class="btn">ساخت</button>
    </div>
    <input type="text" name="description" placeholder="چرا این عادت مهمه؟ (اختیاری)" class="mt" style="margin-top:8px">
  </form>
</div>

<?php if (!$habits): ?>
  <div class="card center pad-lg">
    <div class="empty-mark"><?= app_icon('leaf') ?></div>
    <h2><?= app_icon('leaf') ?> هنوز عادتی نداری</h2>
    <p class="muted">اولین عادتت را بساز؛ هر روز پیشرفتت را ۰ تا ۱۰۰ ثبت کن و زنجیره‌ات را نگه دار.</p>
  </div>
<?php endif; ?>

<?php foreach ($habits as $h): $hl = $logs[$h['id']] ?? []; $streak = habit_streak($uid, (int)$h['id']);
  $vals = array_filter(array_map(fn($d) => $hl[$d] ?? null, $last7), fn($v) => $v !== null);
  $avg = $vals ? round(array_sum($vals) / count($vals)) : 0; ?>
<div class="card" style="margin-bottom:12px">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span style="width:14px;height:14px;border-radius:99px;background:<?= e($h['color']) ?>;flex-shrink:0"></span>
    <h2 style="margin:0"><?= e($h['title']) ?></h2>
    <?php if ($streak >= 2): ?><span class="chip c-good"> <?= fa_num($streak) ?> روز پیاپی</span><?php endif; ?>
    <span class="chip">هفته: <?= pct($avg) ?></span>
    <form method="post" style="margin-inline-start:auto" onsubmit="return confirm('این عادت بایگانی شود؟')">
      <?= csrf_field() ?><input type="hidden" name="act" value="archive"><input type="hidden" name="id" value="<?= $h['id'] ?>">
      <button class="icon-btn" title="بایگانی"><?= app_icon('archive') ?></button>
    </form>
  </div>

  <!-- نوار ۷ روز گذشته -->
  <div style="display:flex;gap:6px;margin:14px 0 4px;align-items:flex-end;justify-content:center">
    <?php foreach ($last7 as $d): $v = $hl[$d] ?? null; $p = j_parse($d); ?>
    <div style="text-align:center;flex:1;max-width:64px">
      <div style="height:52px;display:flex;align-items:flex-end;justify-content:center">
        <div style="width:70%;max-width:26px;border-radius:8px;height:<?= $v === null ? 6 : max(10, $v * 0.52) ?>px;
             background:<?= $v === null ? 'var(--chip)' : e($h['color']) ?>;opacity:<?= $v === null ? 1 : (.45 + $v / 200) ?>"></div>
      </div>
      <div class="muted small" style="font-size:10px"><?= fa_num($p[2]) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="display:flex;gap:10px;align-items:center;margin-top:10px">
    <span class="small muted" style="white-space:nowrap">امروز:</span>
    <input type="range" class="habit-range" min="0" max="100" step="5" style="flex:1"
           value="<?= (int)($hl[$today] ?? 0) ?>" data-habit="<?= $h['id'] ?>" data-date="<?= e($today) ?>" data-title="<?= e($h['title']) ?>">
    <b class="habit-out pct-label" data-habit="<?= $h['id'] ?>" data-date="<?= e($today) ?>" style="color:<?= e($h['color']) ?>"><?= fa_num((int)($hl[$today] ?? 0)) ?>٪</b>
  </div>
</div>
<?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
