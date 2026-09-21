<?php
require __DIR__ . '/includes/bootstrap.php';
$u = current_user();
if ($u) redirect('dashboard.php');
$page_title = content('site_name', APP_NAME);
include __DIR__ . '/includes/header.php';
?>
<div class="hero">
  <div class="hero-mark"><?= app_icon('calendar') ?></div>
  <h1><?= e(content('hero_title', 'هر روز، یک قدم به جلو')) ?></h1>
  <p><?= e(content('hero_text', '')) ?></p>
  <div style="display:flex;gap:10px;justify-content:center;margin-top:22px;flex-wrap:wrap">
    <a class="btn" style="padding:13px 30px;font-size:15px" href="register.php">شروع رایگان</a>
    <a class="btn ghost" style="padding:13px 30px;font-size:15px" href="login.php">ورود</a>
  </div>
</div>
<div class="feature-grid">
  <div class="feature"><div class="ic"><?= app_icon('calendar') ?></div><div><h3>تقویم شمسی واقعی</h3>
    <p class="muted small">پلنر ساعتی و تقویم ماهانه با ماه‌های ۲۹، ۳۰ و ۳۱ روزه.</p></div></div>
  <div class="feature"><div class="ic"><?= app_icon('chart') ?></div><div><h3>درصد پیشرفت</h3>
    <p class="muted small">نمودار روزانه، هفتگی و ماهانه + درصد رشد و پسرفت.</p></div></div>
  <div class="feature"><div class="ic"><?= app_icon('bot') ?></div><div><h3>ایجنت هوشمند</h3>
    <p class="muted small">اگر کاری را انجام ندهی دلیلش را می‌پرسد، دوباره برنامه‌ریزی‌اش می‌کند و آخر ماه عادت‌های بازدارنده‌ات را افشا می‌کند.</p></div></div>
  <div class="feature"><div class="ic"><?= app_icon('target') ?></div><div><h3>اهداف استپ‌به‌استپ</h3>
    <p class="muted small">از «فیلم کوتاه بسازم» تا «اپم را بفروشم» — با مصاحبه کوتاه، برنامه گام‌به‌گام می‌گیری.</p></div></div>
  <div class="feature"><div class="ic"><?= app_icon('bell') ?></div><div><h3>یادآوری خودکار</h3>
    <p class="muted small">گزارش پایان روز، خلاصه هفتگی و گزارش ماهانه به‌صورت نوتیفیکیشن.</p></div></div>
  <div class="feature"><div class="ic"><?= app_icon('brain') ?></div><div><h3>تست شخصیت</h3>
    <p class="muted small">تیپ شخصیتی و نقاط ریسک برنامه‌ات را بشناس.</p></div></div>
</div>
<div class="center" style="margin-top:30px">
  <a class="btn" href="register.php">همین الان شروع کن </a>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
