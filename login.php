<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) redirect('dashboard.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) $errors[] = 'توکن امنیتی معتبر نیست؛ دوباره تلاش کنید.';
    else {
        $r = attempt_login($_POST['username'] ?? '', $_POST['password'] ?? '');
        if ($r['ok']) {
            if ($r['user']['role'] === 'admin') redirect('admin/index.php');
            redirect('dashboard.php');
        }
        $errors = $r['errors'];
    }
}
$page_title = 'ورود';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="card auth-card">
    <div class="auth-logo">📅</div>
    <h1 class="center"><?= e(content('site_name', APP_NAME)) ?></h1>
    <p class="muted center"><?= e(content('login_note', '')) ?></p>
    <?php foreach ($errors as $er): ?><div class="flash flash-error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" class="stack mt">
      <?= csrf_field() ?>
      <div class="field"><label>نام کاربری</label>
        <input type="text" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>" dir="ltr"></div>
      <div class="field"><label>رمز عبور</label>
        <input type="password" name="password" required dir="ltr"></div>
      <button class="btn block">ورود</button>
    </form>
    <p class="center muted mt">حساب ندارید؟ <a href="register.php">ثبت‌نام کنید</a></p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
