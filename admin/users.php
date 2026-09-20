<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$u = require_admin();

$viewId = (int)($_GET['id'] ?? 0);
$view = null;
if ($viewId) {
    $st = db()->prepare("SELECT * FROM users WHERE id=?");
    $st->execute([$viewId]);
    $view = $st->fetch();
}

if ($view) {
    // نمای جزئیات یک کاربر
    $tasks = db()->query("SELECT * FROM tasks WHERE user_id=$viewId AND status<>'deleted' ORDER BY id DESC LIMIT 60")->fetchAll();
    $habits = db()->query("SELECT * FROM habits WHERE user_id=$viewId")->fetchAll();
    $goals = db()->query("SELECT * FROM goals WHERE user_id=$viewId AND status<>'deleted'")->fetchAll();
    $skips = db()->query("SELECT * FROM skip_reports WHERE user_id=$viewId ORDER BY id DESC LIMIT 40")->fetchAll();
    $notes = db()->query("SELECT COUNT(*) FROM notes WHERE user_id=$viewId")->fetchColumn();
    $notifs = db()->query("SELECT COUNT(*) FROM notifications WHERE user_id=$viewId")->fetchColumn();

    $page_title = 'کاربر: ' . $view['username'];
    $active = 'admin';
    include dirname(__DIR__) . '/includes/header.php';
    ?>
    <div class="page-head">
      <h1>👤 <?= e($view['first_name'] . ' ' . $view['last_name']) ?> <span class="muted" dir="ltr">@<?= e($view['username']) ?></span></h1>
      <a class="btn sm ghost" href="users.php">→ بازگشت به فهرست</a>
    </div>
    <div class="grid cols-4" style="margin-bottom:16px">
      <div class="card stat"><div class="num"><?= fa_num(count($tasks)) ?></div><div class="lbl">تسک</div></div>
      <div class="card stat"><div class="num"><?= fa_num(count($habits)) ?></div><div class="lbl">عادت</div></div>
      <div class="card stat"><div class="num"><?= fa_num(count($goals)) ?></div><div class="lbl">هدف</div></div>
      <div class="card stat"><div class="num"><?= fa_num(count($skips)) ?></div><div class="lbl">گزارش عقب‌ماندن</div></div>
    </div>
    <div class="grid cols-2" style="align-items:start">
      <div class="card">
        <h2>مشخصات</h2>
        <table class="tbl" style="min-width:0">
          <tr><td class="muted">موبایل</td><td dir="ltr"><?= e($view['phone'] ?: '—') ?></td></tr>
          <tr><td class="muted">ایمیل</td><td dir="ltr"><?= e($view['email'] ?: '—') ?></td></tr>
          <tr><td class="muted">محل زندگی</td><td><?= e(trim($view['province'] . '، ' . $view['city'], '، ')) ?></td></tr>
          <tr><td class="muted">تاریخ تولد</td><td><?= e($view['birth_date'] ?: '—') ?></td></tr>
          <tr><td class="muted">مهارت‌ها</td><td><?= e($view['skills'] ?: '—') ?></td></tr>
          <tr><td class="muted">شغل</td><td><?= e($view['job'] ?: '—') ?></td></tr>
          <tr><td class="muted">تیپ شخصیتی</td><td><?= e($view['personality_type'] ?: 'تست نداده') ?></td></tr>
          <tr><td class="muted">عضویت</td><td><?= fa_num(date('Y/m/d H:i', strtotime($view['created_at']))) ?></td></tr>
          <tr><td class="muted">یادداشت‌ها / اعلان‌ها</td><td><?= fa_num((int)$notes) ?> / <?= fa_num((int)$notifs) ?></td></tr>
        </table>
      </div>
      <div class="card">
        <h2>دلایل انجام نشدن کارها</h2>
        <?php if (!$skips): ?><p class="muted small">موردی ثبت نشده.</p><?php endif; ?>
        <div class="item-list">
          <?php foreach ($skips as $s): $cats = skip_categories(); ?>
          <div class="item" style="padding:8px 12px">
            <span class="chip <?= $s['is_negative'] ? 'c-danger' : 'c-good' ?>"><?= e($cats[$s['category']]['label'] ?? $s['category']) ?></span>
            <div class="grow small"><?= e($s['ref_title']) ?><?php if ($s['note']): ?><div class="meta">«<?= e($s['note']) ?>»</div><?php endif; ?></div>
            <span class="meta"><?= e($s['jdate']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="card mt">
      <h2>آخرین تسک‌ها</h2>
      <div class="table-wrap"><table class="tbl">
        <tr><th>عنوان</th><th>نوع</th><th>تاریخ</th><th>ساعت</th><th>وضعیت</th></tr>
        <?php foreach ($tasks as $t): ?>
        <tr>
          <td><?= e($t['title']) ?></td>
          <td><?= ['daily' => 'روزانه', 'weekly' => 'هفتگی', 'once' => 'موردی'][$t['type']] ?? $t['type'] ?></td>
          <td><?= e($t['jdate'] ?: $t['week_start']) ?></td>
          <td><?= e($t['hour'] ?: '—') ?></td>
          <td><span class="chip <?= $t['status'] === 'done' ? 'c-good' : ($t['status'] === 'skipped' ? 'c-danger' : '') ?>"><?= ['done' => 'انجام شد', 'pending' => 'باز', 'skipped' => 'انجام نشد'][$t['status']] ?? $t['status'] ?></span></td>
        </tr>
        <?php endforeach; ?>
      </table></div>
    </div>
    <?php
    include dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// فهرست همه کاربران
$q = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM users";
$args = [];
if ($q !== '') {
    $sql .= " WHERE username LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR city LIKE ? OR province LIKE ?";
    $like = "%$q%";
    $args = [$like, $like, $like, $like, $like, $like];
}
$sql .= " ORDER BY id DESC";
$st = db()->prepare($sql);
$st->execute($args);
$users = $st->fetchAll();

$page_title = 'مدیریت کاربران';
$active = 'admin';
include dirname(__DIR__) . '/includes/header.php';
?>
<h1>👥 مدیریت کاربران</h1>
<div class="card">
  <form class="searchbar" method="get">
    <input type="text" name="q" placeholder="جستجو در نام، نام کاربری، موبایل، شهر…" value="<?= e($q) ?>">
    <button class="btn">جستجو</button>
    <a class="btn success" href="export.php<?= $q ? '?q=' . urlencode($q) : '' ?>">📥 خروجی اکسل</a>
  </form>
  <div class="table-wrap">
    <table class="tbl">
      <tr><th>#</th><th>نام و نام خانوادگی</th><th>نام کاربری</th><th>موبایل</th><th>ایمیل</th><th>استان/شهر</th><th>تولد</th><th>مهارت</th><th>عضویت</th><th></th></tr>
      <?php foreach ($users as $r): ?>
      <tr>
        <td><?= fa_num($r['id']) ?></td>
        <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?> <?= $r['role'] === 'admin' ? '<span class="chip c-warn">مدیر</span>' : '' ?></td>
        <td dir="ltr"><?= e($r['username']) ?></td>
        <td dir="ltr"><?= e($r['phone'] ?: '—') ?></td>
        <td dir="ltr" class="small"><?= e($r['email'] ?: '—') ?></td>
        <td class="small"><?= e(trim($r['province'] . ' / ' . $r['city'], ' / ')) ?></td>
        <td class="small"><?= e($r['birth_date'] ?: '—') ?></td>
        <td class="small"><?= e($r['skills'] ?: '—') ?></td>
        <td class="small"><?= fa_num(date('Y/m/d', strtotime($r['created_at']))) ?></td>
        <td><a class="btn sm ghost" href="users.php?id=<?= $r['id'] ?>">جزئیات</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
