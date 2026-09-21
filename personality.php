<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$qs = personality_questions();
$opts = personality_options();
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $answers = [];
    foreach ($qs as $i => $q) $answers[$i] = (int)($_POST['q' . $i] ?? 0);
    $result = ai_personality_result($answers);
    db()->prepare("INSERT INTO personality_results (user_id,scores,ptype,answers) VALUES (?,?,?,?)")
        ->execute([$uid, json_encode($result['scores'], JSON_UNESCAPED_UNICODE), $result['label'], json_encode($answers)]);
    db()->prepare("UPDATE users SET personality_type=? WHERE id=?")->execute([$result['label'], $uid]);
    flash('success', 'نتیجه تست ذخیره شد و در تحلیل‌های ایجنت استفاده می‌شود.');
}

$st = db()->prepare("SELECT * FROM personality_results WHERE user_id=? ORDER BY id DESC LIMIT 1");
$st->execute([$uid]);
$last = $st->fetch();

$page_title = 'تست شخصیت';
$active = 'personality';
include __DIR__ . '/includes/header.php';
?>
<h1><?= app_icon('brain') ?> تست شخصیت و سبک برنامه‌ریزی</h1>
<?php if ($result): ?>
<div class="card" style="border:2px solid var(--accent)">
  <h2><?= app_icon('brain') ?> نتیجه تست شما: <?= e($result['label']) ?></h2>
  <p><?= e($result['desc']) ?></p>
  <p class="small">بُعد دوم شخصیت شما: <b><?= e($result['secondary']) ?></b> — ریسک وقفه در برنامه: <b><?= e($result['interrupt_risk']) ?></b></p>
  <div class="chart-box"><canvas id="pchart" style="max-width:420px"></canvas></div>
  <h3 class="mt"><?= app_icon('info') ?> توصیه‌های اختصاصی</h3>
  <div class="item-list">
    <?php foreach ($result['tips'] as $tip): ?><div class="item"><div class="grow small"> <?= e($tip) ?></div></div><?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card mt">
  <h2><?= app_icon('brain') ?> <?= $result || $last ? 'شرکت دوباره در تست' : 'به ۸ سوال کوتاه پاسخ دهید' ?></h2>
  <?php if ($last && !$result): ?>
    <p class="small muted">آخرین نتیجه شما: <b><?= e($last['ptype']) ?></b> — <?= fa_num(date('Y/m/d', strtotime($last['created_at']))) ?></p>
  <?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <?php foreach ($qs as $i => $q): ?>
    <div class="card" style="box-shadow:none">
      <h3><?= fa_num($i + 1) ?>. <?= e($q) ?></h3>
      <div class="opt-grid">
        <?php foreach ($opts[$i] as $oi => $o): ?>
        <label><input type="radio" name="q<?= $i ?>" value="<?= $oi ?>" required> <?= e($o['t']) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <button class="btn block">مشاهده نتیجه</button>
  </form>
</div>
<script>
document.querySelectorAll('.opt-grid label').forEach(l => l.addEventListener('click', () => {
  const grp = l.closest('.opt-grid');
  grp.querySelectorAll('label').forEach(x => x.classList.remove('sel'));
  l.classList.add('sel');
}));
<?php if ($result): ?>
window.CHART_DEFS = [{ id: 'pchart', type: 'donut', items: [
  { label: 'منظم', value: <?= (int)$result['scores']['planner'] ?>, color: '#12633e' },
  { label: 'منعطف', value: <?= (int)$result['scores']['flexible'] ?>, color: '#b57926' },
  { label: 'اجتماعی', value: <?= (int)$result['scores']['social'] ?>, color: '#258a57' },
  { label: 'متمرکز', value: <?= (int)$result['scores']['focused'] ?>, color: '#3e7eaa' }
], opts: { center: 'شما' } }];
<?php endif; ?>
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
