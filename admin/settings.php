<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$u = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    set_setting('ai_api_url', trim($_POST['ai_api_url'] ?? ''));
    set_setting('ai_api_key', trim($_POST['ai_api_key'] ?? ''));
    flash('success', 'تنظیمات ذخیره شد.');
    redirect('settings.php');
}

$page_title = 'تنظیمات';
$active = 'admin';
include dirname(__DIR__) . '/includes/header.php';
?>
<h1><?= app_icon('settings') ?> تنظیمات سیستم</h1>
<div class="grid cols-2" style="align-items:start">
  <div class="card">
    <h2><?= app_icon('bot') ?> اتصال AI خارجی (اختیاری — آینده)</h2>
    <p class="muted small">موتور اصلی، هوشمندِ داخلی و قانون‌محور است و بدون اینترنت کار می‌کند. برای غنی‌ترشدن تحلیل‌ها می‌توانید یک سرویس AI خارجی (سازگار با دریافت prompt) وصل کنید.</p>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <div class="field"><label>آدرس API</label>
        <input type="text" name="ai_api_url" dir="ltr" placeholder="https://api.example.com/v1/analyze" value="<?= e(setting('ai_api_url')) ?>"></div>
      <div class="field"><label>کلید API</label>
        <input type="text" name="ai_api_key" dir="ltr" placeholder="sk-…" value="<?= e(setting('ai_api_key')) ?>"></div>
      <button class="btn">ذخیره</button>
    </form>
  </div>
  <div>
    <div class="card">
      <h2><?= app_icon('bell') ?> پوش نوتیفیکیشن (VAPID)</h2>
      <?php $vp = setting('vapid_public'); ?>
      <?php if ($vp): ?>
        <p class="small">کلید عمومی (برای دیباگ):</p>
        <input type="text" dir="ltr" value="<?= e($vp) ?>" readonly>
        <p class="muted small mt">کلیدها خودکار تولید شده‌اند و پوش فعال است (نیاز به HTTPS روی دامنه نهایی دارد).</p>
      <?php else: ?>
        <p class="muted small">کلیدها با اولین بازدید یک کاربر لاگین‌شده به‌صورت خودکار تولید می‌شوند.</p>
      <?php endif; ?>
    </div>
    <div class="card mt">
      <h2><?= app_icon('clock') ?> زمان‌بند خلاصه‌ها</h2>
      <p class="small">خلاصه‌های روزانه (ساعت ۲۱)، هفتگی (پنجشنبه ۲۰) و ماهانه (آخرین روز ماه ۲۰) با بازدید کاربران فعال می‌شوند (زمان‌بند تنبل).
      برای اجرای دقیق‌تر، یک Task Scheduler ویندوز بسازید که هر ۱۰ دقیقه این آدرس را صدا بزند:</p>
      <input type="text" dir="ltr" value="<?= e(url('cron.php')) ?>" readonly>
    </div>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
