<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) redirect('dashboard.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) $errors[] = 'توکن امنیتی معتبر نیست؛ دوباره تلاش کنید.';
    else {
        $r = attempt_register($_POST);
        if ($r['ok']) redirect('dashboard.php');
        $errors = $r['errors'];
    }
}
$provinces = iran_provinces();
$page_title = 'ثبت‌نام';
include __DIR__ . '/includes/header.php';
?>
<script>window.CITIES = <?= json_encode(array_map('array_values', $provinces), JSON_UNESCAPED_UNICODE) ?>;</script>
<div class="auth-wrap" style="max-width:640px">
  <div class="card auth-card">
    <div class="auth-logo"><?= app_icon('leaf') ?></div>
    <h1 class="center">ساخت حساب کاربری</h1>
    <p class="muted center"><?= e(content('register_note', '')) ?></p>
    <?php foreach ($errors as $er): ?><div class="flash flash-error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" class="stack mt">
      <?= csrf_field() ?>
      <div class="inline-fields">
        <div class="field"><label>نام *</label><input type="text" name="first_name" required value="<?= e($_POST['first_name'] ?? '') ?>"></div>
        <div class="field"><label>نام خانوادگی *</label><input type="text" name="last_name" required value="<?= e($_POST['last_name'] ?? '') ?>"></div>
      </div>
      <div class="inline-fields">
        <div class="field"><label>نام کاربری * (انگلیسی)</label><input type="text" name="username" dir="ltr" required value="<?= e($_POST['username'] ?? '') ?>"></div>
        <div class="field"><label>شماره موبایل *</label><input type="text" name="phone" dir="ltr" placeholder="09xxxxxxxxx" required value="<?= e($_POST['phone'] ?? '') ?>"></div>
      </div>
      <div class="inline-fields">
        <div class="field"><label>رمز عبور *</label><input type="password" name="password" dir="ltr" required></div>
        <div class="field"><label>تکرار رمز عبور *</label><input type="password" name="password2" dir="ltr" required></div>
      </div>
      <div class="field"><label>ایمیل</label><input type="email" name="email" dir="ltr" value="<?= e($_POST['email'] ?? '') ?>"></div>
      <div class="inline-fields">
        <div class="field"><label>استان *</label>
          <select id="province" name="province" required>
            <option value="">— انتخاب استان —</option>
            <?php foreach ($provinces as $p => $cities): ?>
              <option value="<?= e($p) ?>" <?= ($_POST['province'] ?? '') === $p ? 'selected' : '' ?>><?= e($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>شهر *</label>
          <select id="city" name="city" data-current="<?= e($_POST['city'] ?? '') ?>">
            <option value="">— شهر را انتخاب کنید —</option>
          </select>
          <input type="text" name="city_custom" placeholder="یا شهر خود را بنویسید (اختیاری)" value="" class="mt" style="margin-top:6px">
        </div>
      </div>
      <div class="inline-fields">
        <div class="field"><label>تاریخ تولد (شمسی) *</label><input type="text" name="birth_date" dir="ltr" placeholder="1380/05/15" required value="<?= e($_POST['birth_date'] ?? '') ?>"></div>
        <div class="field"><label>شغل / تخصص</label><input type="text" name="skills" placeholder="مثلاً: فیلمبردار، برنامه‌نویس…" value="<?= e($_POST['skills'] ?? '') ?>"></div>
      </div>
      <button class="btn block">ساخت حساب و ورود</button>
    </form>
    <p class="center muted mt">قبلاً ثبت‌نام کرده‌اید؟ <a href="login.php">وارد شوید</a></p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
