<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $act = $_POST['act'] ?? '';
    if ($act === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim(mb_substr($_POST['title'] ?? '', 0, 160));
        $body = trim(mb_substr($_POST['body'] ?? '', 0, 8000));
        if ($id) {
            db()->prepare("UPDATE notes SET title=?, body=?, updated_at=datetime('now','localtime') WHERE id=? AND user_id=?")
                ->execute([$title, $body, $id, $uid]);
            flash('success', 'یادداشت به‌روز شد.');
        } else {
            db()->prepare("INSERT INTO notes (user_id,title,body,jdate) VALUES (?,?,?,?)")
                ->execute([$uid, $title, $body, j_today_str()]);
            flash('success', 'یادداشت ذخیره شد.');
        }
    }
    if ($act === 'delete') {
        db()->prepare("DELETE FROM notes WHERE id=? AND user_id=?")->execute([(int)($_POST['id'] ?? 0), $uid]);
        flash('info', 'یادداشت حذف شد.');
    }
    redirect('notes.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    $st = db()->prepare("SELECT * FROM notes WHERE id=? AND user_id=?");
    $st->execute([$editId, $uid]);
    $edit = $st->fetch();
}
$notes = db()->query("SELECT * FROM notes WHERE user_id=$uid ORDER BY id DESC")->fetchAll();

$page_title = 'یادداشت‌ها';
$active = 'notes';
include __DIR__ . '/includes/header.php';
?>
<h1><?= app_icon('note') ?> یادداشت‌ها</h1>
<div class="grid dash" style="align-items:start">
  <div class="card">
    <h2><?= $edit ? app_icon('edit') . ' ویرایش یادداشت' : app_icon('plus') . ' یادداشت جدید' ?></h2>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
      <div class="field"><input type="text" name="title" placeholder="عنوان یادداشت" value="<?= e($edit['title'] ?? '') ?>"></div>
      <div class="field"><textarea name="body" style="min-height:180px" placeholder="بنویسید…"><?= e($edit['body'] ?? '') ?></textarea></div>
      <div class="inline-fields">
        <button class="btn">ذخیره</button>
        <?php if ($edit): ?><a class="btn ghost" href="notes.php">انصراف</a><?php endif; ?>
      </div>
    </form>
  </div>
  <div>
    <?php if (!$notes): ?><div class="card"><p class="muted">یادداشتی ندارید.</p></div><?php endif; ?>
    <?php foreach ($notes as $n): ?>
    <div class="card" style="margin-bottom:12px">
      <div style="display:flex;gap:8px;align-items:center">
        <b><?= e($n['title'] ?: 'بدون عنوان') ?></b>
        <span class="muted small" style="margin-inline-start:auto"><?= $n['jdate'] ? j_format($n['jdate'], false) : '' ?></span>
      </div>
      <p class="small" style="white-space:pre-line"><?= e(mb_substr($n['body'], 0, 400)) ?><?= mb_strlen($n['body']) > 400 ? '…' : '' ?></p>
      <div style="display:flex;gap:8px">
        <a class="btn sm ghost" href="notes.php?edit=<?= $n['id'] ?>"><?= app_icon('edit') ?> ویرایش</a>
        <form method="post" onsubmit="return confirm('حذف شود؟')"><?= csrf_field() ?>
          <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $n['id'] ?>">
          <button class="btn sm danger"><?= app_icon('trash') ?> حذف</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
