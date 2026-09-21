<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$provinces = iran_provinces();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $act = $_POST['act'] ?? 'save';
    if ($act === 'save') {
        $email = trim($_POST['email'] ?? '');
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'ایمیل معتبر نیست.';
        $bd = trim($_POST['birth_date'] ?? '');
        if ($bd && !j_parse($bd)) $errors[] = 'تاریخ تولد شمسی معتبر نیست.';
        $wake = preg_match('/^\d{2}:\d{2}$/', $_POST['wake_time'] ?? '') ? $_POST['wake_time'] : $u['wake_time'];
        $sleep = preg_match('/^\d{2}:\d{2}$/', $_POST['sleep_time'] ?? '') ? $_POST['sleep_time'] : $u['sleep_time'];
        $city = trim($_POST['city'] ?? '') ?: trim($_POST['city_custom'] ?? '') ?: $u['city'];
        if (!$errors) {
            db()->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, province=?, city=?, birth_date=?,
                           skills=?, job=?, wake_time=?, sleep_time=?, goals_text=?, abilities_text=? WHERE id=?")
                ->execute([
                    trim($_POST['first_name'] ?? $u['first_name']), trim($_POST['last_name'] ?? $u['last_name']),
                    $email, trim($_POST['phone'] ?? $u['phone']), trim($_POST['province'] ?? $u['province']),
                    $city, $bd, trim($_POST['skills'] ?? $u['skills']), trim($_POST['job'] ?? $u['job']),
                    $wake, $sleep, trim($_POST['goals_text'] ?? ''), trim($_POST['abilities_text'] ?? ''), $uid,
                ]);
            flash('success', 'پروفایل ذخیره شد.');
            redirect('profile.php');
        }
    }
    if ($act === 'password') {
        if (!password_verify($_POST['curpass'] ?? '', $u['password'])) $errors[] = 'رمز فعلی اشتباه است.';
        elseif (strlen($_POST['newpass'] ?? '') < 6) $errors[] = 'رمز جدید باید حداقل ۶ کاراکتر باشد.';
        elseif (($_POST['newpass'] ?? '') !== ($_POST['newpass2'] ?? '')) $errors[] = 'تکرار رمز جدید یکسان نیست.';
        else {
            db()->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['newpass'], PASSWORD_DEFAULT), $uid]);
            flash('success', 'رمز عبور تغییر کرد.');
            redirect('profile.php');
        }
    }
}
$theme = $u['theme'];

$page_title = 'پروفایل';
$active = 'profile';
include __DIR__ . '/includes/header.php';
?>
<script>window.CITIES = <?= json_encode(array_map('array_values', $provinces), JSON_UNESCAPED_UNICODE) ?>;</script>
<h1><?= app_icon('user') ?> پروفایل و تنظیمات شخصی</h1>
<div class="grid cols-2" style="align-items:start">
  <div class="card">
    <h2><?= app_icon('user') ?> اطلاعات من</h2>
    <?php foreach ($errors as $er): ?><div class="flash flash-error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="act" value="save">
      <div class="inline-fields">
        <div class="field"><label>نام</label><input type="text" name="first_name" value="<?= e($u['first_name']) ?>"></div>
        <div class="field"><label>نام خانوادگی</label><input type="text" name="last_name" value="<?= e($u['last_name']) ?>"></div>
      </div>
      <div class="inline-fields">
        <div class="field"><label>موبایل</label><input type="text" name="phone" dir="ltr" value="<?= e($u['phone']) ?>"></div>
        <div class="field"><label>ایمیل</label><input type="email" name="email" dir="ltr" value="<?= e($u['email']) ?>"></div>
      </div>
      <div class="inline-fields">
        <div class="field"><label>استان</label>
          <select id="province" name="province">
            <option value="">—</option>
            <?php foreach ($provinces as $p => $c): ?><option value="<?= e($p) ?>" <?= $u['province'] === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>شهر</label>
          <select id="city" name="city" data-current="<?= e($u['city']) ?>"><option value="">—</option></select>
          <input type="text" name="city_custom" placeholder="یا شهر دلخواه…" class="mt" style="margin-top:6px">
        </div>
      </div>
      <div class="inline-fields">
        <div class="field"><label>تاریخ تولد (شمسی)</label><input type="text" name="birth_date" dir="ltr" value="<?= e($u['birth_date']) ?>" placeholder="1380/05/15"></div>
        <div class="field"><label>شغل / تخصص</label><input type="text" name="job" value="<?= e($u['job']) ?>" placeholder="مثلاً برنامه‌نویس"></div>
      </div>
      <div class="field"><label>مهارت‌ها</label><input type="text" name="skills" value="<?= e($u['skills']) ?>" placeholder="مثلاً: فیلمبرداری، تدوین، پایتون"></div>
      <div class="field"><label>توانمندی‌ها و داشته‌ها (خوراک تحلیل ایجنت)</label>
        <textarea name="abilities_text" style="min-height:70px" placeholder="مثلاً: دوربین حرفه‌ای دارم، با پریمیر آشنا هستم…"><?= e($u['abilities_text']) ?></textarea></div>
      <div class="field"><label>آرزوها و اهداف کلی ذهنی‌ام</label>
        <textarea name="goals_text" style="min-height:70px" placeholder="مثلاً: می‌خواهم یک فیلم کوتاه بسازم و بفروشم…"><?= e($u['goals_text']) ?></textarea></div>
      <div class="inline-fields">
        <div class="field"><label> ساعت بیدار شدن</label><input type="time" name="wake_time" value="<?= e($u['wake_time']) ?>"></div>
        <div class="field"><label> ساعت خواب</label><input type="time" name="sleep_time" value="<?= e($u['sleep_time']) ?>"></div>
      </div>
      <p class="muted small">ساعت بیدارشدن و خواب، مبنای پیشنهاد ساعت هوشمند برای تسک‌ها و پلنر شماست.</p>
      <button class="btn">ذخیره پروفایل</button>
    </form>
  </div>

  <div>
    <div class="card">
      <h2><?= app_icon('lock') ?> تغییر رمز عبور</h2>
      <form method="post" class="stack">
        <?= csrf_field() ?><input type="hidden" name="act" value="password">
        <div class="field"><label>رمز فعلی</label><input type="password" name="curpass" dir="ltr" required></div>
        <div class="field"><label>رمز جدید</label><input type="password" name="newpass" dir="ltr" required></div>
        <div class="field"><label>تکرار رمز جدید</label><input type="password" name="newpass2" dir="ltr" required></div>
        <button class="btn">تغییر رمز</button>
      </form>
    </div>
    <div class="card mt">
      <h2><?= app_icon('sun') ?> تم نمایش</h2>
      <form method="post" action="api.php">
        <div class="inline-fields">
          <select name="theme" onchange="
            document.documentElement.setAttribute('data-theme', this.value);
            fetch(window.APP.api, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=theme&theme='+this.value+'&csrf='+encodeURIComponent(window.APP.csrf||'')});">
            <option value="auto" <?= $theme === 'auto' ? 'selected' : '' ?>>خودکار (سیستم)</option>
            <option value="dark" <?= $theme === 'dark' ? 'selected' : '' ?>>دارک </option>
            <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>>لایت </option>
          </select>
        </div>
      </form>
      <p class="muted small mt">حساب: <b><?= e($u['username']) ?></b> — عضویت از <?= fa_num(date('Y/m/d', strtotime($u['created_at']))) ?></p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
