<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $title = trim(mb_substr($_POST['title'] ?? '', 0, 160));
        $period = $_POST['period'] ?? 'month';
        if (!isset(goal_periods()[$period])) $period = 'month';
        if ($title !== '') {
            db()->prepare("INSERT INTO goals (user_id,title,period,start_jdate,target_text) VALUES (?,?,?,?,?)")
                ->execute([$uid, $title, $period, j_today_str(), trim($_POST['target_text'] ?? '')]);
            $gid = (int)db()->lastInsertId();
            $qs = ai_interview_questions();
            $hello = '🤖 سلام! برای ساخت برنامه «' . $title . '»، ' . fa_num(count($qs)) . " سوال کوتاه می‌پرسم.\nسوال ۱ از " . fa_num(count($qs)) . ': ' . $qs[0];
            db()->prepare("INSERT INTO chats (user_id,agent_id,goal_id,sender,message) VALUES (?,0,?,'agent',?)")
                ->execute([$uid, $gid, $hello]);
            flash('success', 'هدف ساخته شد. به سوال‌های ایجنت پاسخ دهید تا برنامه استپ‌به‌استپ بسازد.');
        }
    }
    if ($act === 'answer') {
        $gid = (int)($_POST['goal_id'] ?? 0);
        $msg = trim(mb_substr($_POST['message'] ?? '', 0, 600));
        $st = db()->prepare("SELECT id FROM goals WHERE id=? AND user_id=?");
        $st->execute([$gid, $uid]);
        if ($msg !== '' && $st->fetch()) {
            db()->prepare("INSERT INTO chats (user_id,agent_id,goal_id,sender,message) VALUES (?,0,?,'user',?)")->execute([$uid, $gid, $msg]);
            $state = ai_interview_state($uid, $gid);
            if ($state['next_q'] !== null) {
                db()->prepare("INSERT INTO chats (user_id,agent_id,goal_id,sender,message) VALUES (?,0,?,'agent',?)")
                    ->execute([$uid, $gid, 'سوال ' . fa_num($state['question_index'] + 1) . ' از ' . fa_num(count($state['questions'])) . ': ' . $state['next_q']]);
            } else {
                db()->prepare("INSERT INTO chats (user_id,agent_id,goal_id,sender,message) VALUES (?,0,?,'agent',?)")
                    ->execute([$uid, $gid, '✅ عالی! پاسخ‌ها کامل شد. حالا دکمه «ساخت برنامه گام‌به‌گام» را بزنید تا برنامه را بر اساس جواب‌هایتان بچینم.']);
            }
        }
    }
    if ($act === 'build') {
        $gid = (int)($_POST['goal_id'] ?? 0);
        ai_build_goal_plan($uid, $gid);
        flash('success', 'برنامه گام‌به‌گام ساخته شد! 🎉');
    }
    if ($act === 'close') {
        db()->prepare("UPDATE goals SET status='done' WHERE id=? AND user_id=?")->execute([(int)($_POST['id'] ?? 0), $uid]);
    }
    if ($act === 'delete') {
        db()->prepare("UPDATE goals SET status='deleted' WHERE id=? AND user_id=?")->execute([(int)($_POST['id'] ?? 0), $uid]);
    }
    redirect('goals.php');
}

$goals = db()->query("SELECT * FROM goals WHERE user_id=$uid AND status<>'deleted' ORDER BY id DESC")->fetchAll();
$stepsByGoal = [];
$st = db()->prepare("SELECT * FROM goal_steps WHERE user_id=? ORDER BY step_order");
$st->execute([$uid]);
foreach ($st->fetchAll() as $s) $stepsByGoal[$s['goal_id']][] = $s;

$page_title = 'اهداف';
$active = 'goals';
include __DIR__ . '/includes/header.php';
?>
<script>window.APP.loggedIn = true;</script>
<h1>🎯 اهداف بازه‌ای</h1>
<div class="grid dash" style="align-items:start">
  <div>
    <?php if (!$goals): ?><div class="card"><p class="muted">هدفی تعریف نشده. از فرم کنار شروع کنید — مثلاً «ساخت فیلم کوتاه ۱۰۰ ثانیه‌ای» یا «فروش اپلیکیشنم».</p></div><?php endif; ?>
    <?php foreach ($goals as $g): $steps = $stepsByGoal[$g['id']] ?? []; $chats = null; ?>
    <div class="card" style="margin-bottom:14px">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <h2 style="margin:0"><?= e($g['title']) ?></h2>
        <span class="chip c-primary"><?= goal_periods()[$g['period']] ?? $g['period'] ?></span>
        <?php if ($g['end_jdate']): ?><span class="chip">مهلت: <?= j_format($g['end_jdate'], false) ?></span><?php endif; ?>
        <?php if ($g['status'] === 'done'): ?><span class="chip c-good">✔ محقق شد</span><?php endif; ?>
      </div>
      <?php if ($g['target_text']): ?><p class="muted small">🎯 <?= e($g['target_text']) ?></p><?php endif; ?>

      <div class="mt" style="display:flex;align-items:center;gap:10px">
        <div class="bar slim" style="flex:1"><i id="goalBar<?= $g['id'] ?>" style="width:<?= (int)$g['progress'] ?>%"></i></div>
        <b class="pct-label" id="goalPct<?= $g['id'] ?>"><?= fa_num((int)$g['progress']) ?>٪</b>
      </div>

      <?php if ($steps): ?>
        <div class="item-list mt">
          <?php foreach ($steps as $s): ?>
          <div class="item" style="padding:8px 12px">
            <input type="checkbox" class="step-check" data-step="<?= $s['id'] ?>" data-goal="<?= $g['id'] ?>"
                   style="width:20px;height:20px;accent-color:var(--accent)" <?= $s['is_done'] ? 'checked' : '' ?>>
            <div class="grow">
              <div class="title small" <?= $s['is_done'] ? 'style="text-decoration:line-through;opacity:.6"' : '' ?>><?= fa_num($s['step_order']) ?>. <?= e($s['title']) ?></div>
              <?php if ($s['due_jdate']): ?><div class="meta">مهلت: <?= j_format($s['due_jdate'], false) ?></div><?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <?php
        $state = ai_interview_state($uid, (int)$g['id']);
        $stc = db()->prepare("SELECT * FROM chats WHERE user_id=? AND goal_id=? ORDER BY id");
        $stc->execute([$uid, $g['id']]);
        $msgs = $stc->fetchAll();
        ?>
        <div class="chat mt">
          <?php foreach ($msgs as $m): ?>
            <div class="bubble <?= e($m['sender']) ?>"><?= e($m['message']) ?></div>
          <?php endforeach; ?>
        </div>
        <?php if ($state['question_index'] >= count($state['questions'])): ?>
          <?php if ($g['status'] !== 'done'): ?>
          <form method="post" class="mt"><?= csrf_field() ?>
            <input type="hidden" name="act" value="build"><input type="hidden" name="goal_id" value="<?= $g['id'] ?>">
            <button class="btn success block">🤖 ساخت برنامه گام‌به‌گام</button>
          </form>
          <?php endif; ?>
        <?php elseif ($state['next_q'] !== null): ?>
          <form method="post" class="chat-input"><?= csrf_field() ?>
            <input type="hidden" name="act" value="answer"><input type="hidden" name="goal_id" value="<?= $g['id'] ?>">
            <input type="text" name="message" placeholder="پاسخ شما…" required>
            <button class="btn">ارسال</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($g['status'] !== 'done'): ?>
      <div style="display:flex;gap:8px;margin-top:12px">
        <form method="post" onsubmit="return confirm('هدف به‌عنوان محقق‌شده بسته شود؟')"><?= csrf_field() ?>
          <input type="hidden" name="act" value="close"><input type="hidden" name="id" value="<?= $g['id'] ?>">
          <button class="btn sm success">✔ محقق شد</button>
        </form>
        <form method="post" onsubmit="return confirm('حذف شود؟')"><?= csrf_field() ?>
          <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $g['id'] ?>">
          <button class="btn sm danger">🗑</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>➕ هدف جدید</h2>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="act" value="add">
      <div class="field"><label>عنوان هدف</label>
        <input type="text" name="title" required placeholder="مثلاً: ساخت فیلم کوتاه ۱۰۰ ثانیه‌ای"></div>
      <div class="field"><label>بازه زمانی</label>
        <select name="period">
          <?php foreach (goal_periods() as $k => $lbl): ?><option value="<?= $k ?>"><?= $lbl ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>توصیف نتیجه دلخواه (اختیاری)</label>
        <textarea name="target_text" style="min-height:70px" placeholder="دقیقاً چه چیزی می‌خواهید به دست بیاورید؟"></textarea></div>
      <button class="btn block">ساخت هدف و شروع مصاحبه با ایجنت</button>
    </form>
    <p class="muted small mt">💡 بعد از ساخت هدف، ایجنت ۵ سوال کوتاه می‌پرسد و بر اساس پاسخ‌ها برنامه گام‌به‌گام با مهلت هر گام می‌سازد. در بازه‌های مشخص یادآوری می‌گیرید.</p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
