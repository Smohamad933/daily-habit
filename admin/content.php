<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$u = require_admin();

$fields = [
    'site_name' => ['نام سایت', 'متن کوتاه'],
    'site_tagline' => ['شعار سایت', 'یک جمله'],
    'hero_title' => ['عنوان صفحه اصلی', 'عنوان بزرگ صفحه ورود'],
    'hero_text' => ['توضیح صفحه اصلی', 'توضیح زیر عنوان صفحه اصلی'],
    'announcement' => ['اطلاعیه سراسری', 'اگر پر باشد، بالای همه صفحات کاربران نمایش داده می‌شود'],
    'login_note' => ['متن صفحه ورود', ''],
    'register_note' => ['متن صفحه ثبت‌نام', ''],
    'footer_text' => ['متن فوتر', ''],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $st = db()->prepare("INSERT INTO site_content (ckey, cvalue) VALUES (?,?)
                         ON CONFLICT(ckey) DO UPDATE SET cvalue=excluded.cvalue");
    foreach ($fields as $k => $f) $st->execute([$k, trim($_POST[$k] ?? '')]);
    flash('success', 'محتوای سایت ذخیره شد.');
    redirect('content.php');
}

$page_title = 'مدیریت محتوای سایت';
$active = 'admin';
include dirname(__DIR__) . '/includes/header.php';
?>
<h1><?= app_icon('note') ?> مدیریت محتوای کلی سایت</h1>
<div class="card" style="max-width:760px">
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <?php foreach ($fields as $k => [$label, $hint]): $val = content($k, ''); ?>
    <div class="field">
      <label><?= e($label) ?> <?php if ($hint): ?><span class="muted small">— <?= e($hint) ?></span><?php endif; ?></label>
      <?php if (in_array($k, ['hero_text', 'announcement'])): ?>
        <textarea name="<?= e($k) ?>" style="min-height:80px"><?= e($val) ?></textarea>
      <?php else: ?>
        <input type="text" name="<?= e($k) ?>" value="<?= e($val) ?>">
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <button class="btn">ذخیره محتوا</button>
  </form>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
