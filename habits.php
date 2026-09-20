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
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#7c5cff';
        if ($title !== '') {
            db()->prepare("INSERT INTO habits (user_id,title,description,color) VALUES (?,?,?,?)")
                ->execute([$uid, $title, trim($_POST['description'] ?? ''), $color]);
            flash('success', 'عادت «' . $title . '» ساخته شد. هر روز پیشرفتتان را از ۰ تا ۱۰۰٪ ثبت کنید.');
        }
    }
    if ($act === 'archive') {
        db()->prepare("UPDATE habits SET archived=1 WHERE id=? AND user_id=?")->execute([(int)($_POST['id'] ?? 0), $uid]);
        flash('info', 'عادت بایگانی شد.');
    }
    redirect('habits.php');
}

$habits = db()->query("SELECT * FROM habits WHERE user_id=$uid AND archived=0 ORDER BY id")->fetchAll();

// پیشرفت ۷ روز اخیر هر عادت
$last7 = [];
for ($i = 6; $i >= 0; $i--) $last7[] = j_str(...j_add_days($jy, $jm, $jd, -$i));

$logs = [];
$st = db()->prepare("SELECT habit_id, jdate, progress FROM habit_logs WHERE user_id=? AND jdate >= ?");
$st->execute([$uid, $last7[0]]);
foreach ($st->fetchAll() as $l) $logs[$l['habit_id']][$l['jdate']] = (int)$l['progress'];

// زنجیره پیوسته (روزهای پیاپی با پیشرفت ≥ ۵۰)
function habit_streak(int $uid, int $habitId): int {
    [$jy, $jm, $jd] = today_jalali();
    $streak = 0;
    for ($i = 0; $i < 120; $i++) {
        $d = j_str(...j_add_days($jy, $jm, $jd, -$i));
        $st = db()->prepare("SELECT progress FROM habit_logs WHERE habit_id=? AND user_id=? AND jdate=?");
        $st->execute([$habitId, $uid, $d]);
        $p = $st->fetchColumn();
        if ($p !== false && $p >= 50) $streak++;
        elseif ($i == 0) continue; // امروز هنوز ثبت نشده، زنجیره نمی‌شکند
        else break;
    }
    return $streak;
}

$page_title = 'عادت‌های روزانه';
$active = 'habits';
include __DIR__ . '/includes/header.php';
?>
<script>window.APP.loggedIn = true;</script>
<h1>🔁 عادت‌های روزانه</h1>
<div class="grid dash" style="align-items:start">
  <div>
    <?php if (!$habits): ?>
      <div class="card"><p class="muted">هنوز عادتی نساخته‌اید. از فرم کنار شروع کنید — مثلاً «تمرین آواز»، «مطالعه»، «باشگاه».</p></div>
    <?php endif; ?>
    <?php foreach ($habits as $h): $hl = $logs[$h['id']] ?? []; $streak = habit_streak($uid, (int)$h['id']);
      $avg = $hl ? round(array_sum($hl) / count($hl)) : 0; ?>
    <div class="card" style="margin-bottom:14px">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span style="width:14px;height:14px;border-radius:99px;background:<?= e($h['color']) ?>;display:inline-block"></span>
        <h2 style="margin:0"><?= e($h['title']) ?></h2>
        <?php if ($streak >= 3): ?><span class="chip c-good">🔥 <?= fa_num($streak) ?> روز پیاپی</span><?php endif; ?>
        <span class="chip">میانگین هفته: <?= pct($avg) ?></span>
        <form method="post" style="margin-inline-start:auto" onsubmit="return confirm('این عادت بایگانی شود؟')">
          <?= csrf_field() ?><input type="hidden" name="act" value="archive"><input type="hidden" name="id" value="<?= $h['id'] ?>">
          <button class="btn sm ghost">بایگانی</button>
        </form>
      </div>
      <?php if ($h['description']): ?><p class="muted small"><?= e($h['description']) ?></p><?php endif; ?>
      <div class="table-wrap mt">
        <table class="tbl">
          <tr><?php foreach ($last7 as $d): $p = j_parse($d); ?><th><?= j_weekday_name(j_day_of_week(...$p)) ?><br><span class="muted small"><?= fa_num($p[2]) ?></span></th><?php endforeach; ?></tr>
          <tr>
            <?php foreach ($last7 as $d): $v = $hl[$d] ?? null; ?>
            <td class="center">
              <?php if ($v === null): ?><span class="chip">—</span>
              <?php else: ?><span class="chip <?= $v >= 70 ? 'c-good' : ($v >= 40 ? 'c-warn' : 'c-danger') ?>"><?= fa_num($v) ?>٪</span><?php endif; ?>
            </td>
            <?php endforeach; ?>
          </tr>
        </table>
      </div>
      <div class="mt">
        <label class="small muted">ثبت پیشرفت امروز: </label>
        <div style="display:flex;gap:10px;align-items:center">
          <input type="range" class="habit-range" min="0" max="100" step="5" style="flex:1"
                 value="<?= (int)($hl[$today] ?? 0) ?>" data-habit="<?= $h['id'] ?>" data-date="<?= e($today) ?>" data-title="<?= e($h['title']) ?>">
          <b class="habit-out pct-label" data-habit="<?= $h['id'] ?>" data-date="<?= e($today) ?>"><?= fa_num((int)($hl[$today] ?? 0)) ?>٪</b>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>➕ عادت جدید</h2>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="act" value="add">
      <div class="field"><label>نام عادت</label><input type="text" name="title" required placeholder="مثلاً: تمرین آواز خوانی"></div>
      <div class="field"><label>توضیح (اختیاری)</label><textarea name="description" style="min-height:70px" placeholder="چرا این عادت برایتان مهم است؟"></textarea></div>
      <div class="field"><label>رنگ</label><input type="color" name="color" value="#7c5cff" style="height:44px;padding:4px"></div>
      <button class="btn block">ساخت عادت</button>
    </form>
    <p class="muted small mt">💡 هر روز پیشرفت هر عادت را از ۰ تا ۱۰۰ ثبت کنید؛ نمودارها و گزارش ماهانه از همین داده‌ها ساخته می‌شوند.</p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
