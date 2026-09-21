<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $name = trim(mb_substr($_POST['name'] ?? '', 0, 80));
        $atype = $_POST['atype'] ?? 'coach';
        if (!isset(agent_types()[$atype])) $atype = 'coach';
        if ($name !== '') {
            db()->prepare("INSERT INTO agents (user_id,name,atype) VALUES (?,?,?)")->execute([$uid, $name, $atype]);
            flash('success', 'ایجنت «' . $name . '» اضافه شد و تحلیل‌هایش فعال است.');
        }
    }
    if ($act === 'toggle') {
        db()->prepare("UPDATE agents SET active=1-active WHERE id=? AND user_id=?")->execute([(int)($_POST['id'] ?? 0), $uid]);
    }
    if ($act === 'delete') {
        db()->prepare("DELETE FROM agents WHERE id=? AND user_id=?")->execute([(int)($_POST['id'] ?? 0), $uid]);
        flash('info', 'ایجنت حذف شد.');
    }
    redirect('agents.php');
}

$agents = db()->query("SELECT * FROM agents WHERE user_id=$uid ORDER BY id DESC")->fetchAll();
$analysis = ai_agent_analysis($uid);

$page_title = 'ایجنت‌های هوشمند';
$active = 'agents';
include __DIR__ . '/includes/header.php';
?>
<h1><?= app_icon('bot') ?> ایجنت‌های هوشمند</h1>
<div class="grid dash" style="align-items:start">
  <div>
    <div class="card">
      <h2><?= app_icon('pulse') ?> تحلیل لحظه‌ای مربی شخصی</h2>
      <div class="grid cols-3" style="margin-bottom:12px">
        <div class="stat card"><div class="num"><?= pct($analysis['today']['score']) ?></div><div class="lbl">امروز</div></div>
        <div class="stat card"><div class="num"><?= pct($analysis['month']['percent']) ?></div><div class="lbl">این ماه</div></div>
        <div class="stat card <?= $analysis['month']['percent'] >= $analysis['month']['prev_percent'] ? 'good' : 'bad' ?>">
          <div class="num"><?= ($analysis['month']['percent'] - $analysis['month']['prev_percent'] >= 0 ? '+' : '−') . fa_num(abs($analysis['month']['percent'] - $analysis['month']['prev_percent'])) ?></div>
          <div class="lbl">نسبت به بازه قبل</div></div>
      </div>
      <?php if ($analysis['ptest']): ?>
        <p class="small"> تیپ شخصیتی شما: <b><?= e($analysis['ptest']['ptype']) ?></b></p>
      <?php else: ?>
        <p class="small"> هنوز تست شخصیت نداده‌اید — <a href="personality.php">همین الان بدهید</a> تا تحلیل‌ها دقیق‌تر شود.</p>
      <?php endif; ?>
      <div class="item-list mt">
        <?php foreach ($analysis['suggestions'] as $s): ?>
          <div class="item"><div class="grow small"><?= e($s) ?></div></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card mt">
      <h2><?= app_icon('target') ?> وضعیت اهداف فعال</h2>
      <?php if (!$analysis['goals']): ?><p class="muted small">هدف فعالی ندارید. <a href="goals.php">ساخت هدف</a></p><?php endif; ?>
      <div class="item-list">
        <?php foreach ($analysis['goals'] as $g): ?>
        <div class="item">
          <div class="grow">
            <div class="title small"><?= e($g['title']) ?> <span class="chip"><?= goal_periods()[$g['period']] ?? '' ?></span></div>
            <div class="bar slim mt"><i style="width:<?= (int)$g['progress'] ?>%"></i></div>
          </div>
          <b class="pct-label small"><?= fa_num((int)$g['progress']) ?>٪</b>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div>
    <div class="card">
      <h2><?= app_icon('users') ?> ایجنت‌های شما</h2>
      <?php if (!$agents): ?><p class="muted small">ایجنتی اضافه نکرده‌اید؛ تحلیل‌های هوشمند به‌صورت خودکار فعال هستند، اما با افزودن ایجنت می‌توانید نقش‌ها را شخصی‌سازی کنید.</p><?php endif; ?>
      <div class="item-list">
        <?php foreach ($agents as $a): ?>
        <div class="item">
          <div class="grow">
            <div class="title small"><?= e($a['name']) ?></div>
            <div class="meta"><?= agent_types()[$a['atype']] ?? $a['atype'] ?></div>
          </div>
          <?= $a['active'] ? '<span class="chip c-good">فعال</span>' : '<span class="chip">غیرفعال</span>' ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn sm ghost" title="فعال یا غیرفعال"><?= app_icon('pulse') ?></button></form>
          <form method="post" onsubmit="return confirm('حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn sm ghost" title="حذف"><?= app_icon('trash') ?></button></form>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card mt">
      <h2><?= app_icon('plus') ?> افزودن ایجنت</h2>
      <form method="post" class="stack">
        <?= csrf_field() ?><input type="hidden" name="act" value="add">
        <div class="field"><label>نام ایجنت</label><input type="text" name="name" required placeholder="مثلاً: مربی آواز من"></div>
        <div class="field"><label>نقش</label>
          <select name="atype">
            <?php foreach (agent_types() as $k => $lbl): ?><option value="<?= $k ?>"><?= $lbl ?></option><?php endforeach; ?>
          </select>
        </div>
        <button class="btn block">افزودن</button>
      </form>
      <p class="muted small mt"> در نسخه‌های بعدی می‌توان به این ایجنت‌ها سرویس AI خارجی (مثل APIهای زبانی) وصل کرد تا تحلیل‌ها عمیق‌تر شوند — زیرساخت اتصال آماده است.</p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
