<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];

// علامت‌گذاری همه به‌عنوان خوانده‌شده بعد از نمایش
db()->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0")->execute([$uid]);

$items = db()->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY id DESC LIMIT 80")->fetchAll();

$page_title = 'اعلان‌ها';
$active = 'notifications';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1>🔔 مرکز اعلان‌ها</h1>
  <button class="btn sm ghost" id="askNotif">فعال‌سازی نوتیفیکیشن مرورگر</button>
</div>
<div class="item-list">
  <?php if (!$items): ?><div class="card"><p class="muted">اعلانی ندارید. گزارش‌های روزانه، هفتگی و ماهانه به‌صورت خودکار همین‌جا می‌آیند.</p></div><?php endif; ?>
  <?php foreach ($items as $n): ?>
  <div class="notif <?= $n['is_read'] ? '' : 'unread' ?>">
    <div class="nbody">
      <div class="ntitle"><?= e($n['title']) ?></div>
      <div class="ntext"><?= e($n['body']) ?></div>
      <?php if ($n['link']): ?><a class="small" href="<?= e($n['link']) ?>">مشاهده ←</a><?php endif; ?>
    </div>
    <div class="ntime"><?= fa_num(date('Y/m/d H:i', strtotime($n['created_at']))) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<script>
document.getElementById('askNotif').addEventListener('click', () => {
  if (!('Notification' in window)) { alert('مرورگر شما از نوتیفیکیشن پشتیبانی نمی‌کند.'); return; }
  Notification.requestPermission().then(p => {
    alert(p === 'granted' ? 'نوتیفیکیشن مرورگر فعال شد ✅' : 'اجازه داده نشد.');
    if (p === 'granted') location.reload();
  });
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
