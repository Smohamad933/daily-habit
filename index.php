<?php
require __DIR__ . '/includes/bootstrap.php';
$u = current_user();
if ($u) redirect('dashboard.php');
$page_title = content('site_name', APP_NAME);
include __DIR__ . '/includes/header.php';
?>
<div class="hero">
  <h1><?= e(content('hero_title', 'هر روز، یک قدم به جلو')) ?></h1>
  <p><?= e(content('hero_text', '')) ?></p>
  <div class="mt">
    <a class="btn" href="register.php">🚀 ساخت حساب رایگان</a>
    <a class="btn ghost" href="login.php">ورود</a>
  </div>
</div>
<div class="feature-grid">
  <div class="feature"><div class="ic">🗓</div><h3>تقویم شمسی واقعی</h3>
    <p class="muted">پلنر روزانه با ساعت‌بندی و تقویم ماهانه — با پشتیبانی کامل از ماه‌های ۲۹، ۳۰ و ۳۱ روزه.</p></div>
  <div class="feature"><div class="ic">📊</div><h3>درصد پیشرفت و نمودار</h3>
    <p class="muted">پیشرفت روزانه، هفتگی و ماهانه با نمودار — همراه با درصد رشد و پسرفت.</p></div>
  <div class="feature"><div class="ic">🤖</div><h3>ایجنت هوشمند</h3>
    <p class="muted">اگر کاری را انجام ندهید، دلیلش را می‌پرسد، هوشمندانه دوباره برنامه‌ریزی‌اش می‌کند و آخر ماه عادت‌های بازدارنده‌تان را افشا می‌کند.</p></div>
  <div class="feature"><div class="ic">🎯</div><h3>اهداف استپ‌به‌استپ</h3>
    <p class="muted">از «می‌خواهم یک فیلم کوتاه بسازم» تا «اپلیکیشن خودم را بفروشم» — ایجنت با مصاحبه کوتاه، برنامه گام‌به‌گام می‌سازد.</p></div>
  <div class="feature"><div class="ic">🔔</div><h3>یادآوری و نوتیفیکیشن</h3>
    <p class="muted">گزارش پایان روز، خلاصه هفتگی و گزارش ماهانه به‌صورت خودکار اطلاع‌رسانی می‌شود.</p></div>
  <div class="feature"><div class="ic">🧠</div><h3>تست شخصیت</h3>
    <p class="muted">تیپ شخصیتی، مهارت‌های ارتباطی و نقاط ریسک برنامه‌تان را بشناسید.</p></div>
</div>
<div class="center mt" style="margin-top:36px">
  <a class="btn" href="register.php">همین الان شروع کنید</a>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
