<?php
require __DIR__ . '/common.php';
$monitor_admin = monitor_require_admin();
$monitor_active = 'users';
$pdo = monitoring_pdo();
$id = (int)($_GET['id'] ?? 0);
$person = null;
$events = [];
if ($pdo && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM monitor_users WHERE app_user_id=? LIMIT 1");
    $st->execute([$id]);
    $person = $st->fetch() ?: null;
    $st = $pdo->prepare("SELECT * FROM monitor_events WHERE app_user_id=? ORDER BY id DESC LIMIT 60");
    $st->execute([$id]);
    $events = $st->fetchAll();
}
if (!$person) {
    flash('error', 'اطلاعات این کاربر هنوز همگام نشده یا وجود ندارد.');
    header('Location: ' . monitor_url());
    exit;
}
$monitor_title = 'جزئیات ' . ($person['full_name'] ?: $person['username']);
include __DIR__ . '/header.php';
$progress = monitor_percent((int)$person['tasks_done'], (int)$person['tasks_total']);
?>
<div class="monitor-back-row"><a class="monitor-outline" href="<?= e(monitor_url('index.php#users')) ?>"><?= app_icon('arrow-right') ?> بازگشت به افراد</a><span class="monitor-status-dot"></span><small>داده‌ی همگام‌شده</small></div>
<section class="monitor-profile-hero">
  <span class="monitor-avatar monitor-avatar-xl <?= $person['role'] === 'admin' ? 'is-admin' : '' ?>"><?= e(monitor_initial($person['full_name'] ?: $person['username'])) ?></span>
  <div><div class="monitor-eyebrow">پروفایل کاربر</div><h2><?= e($person['full_name'] ?: $person['username']) ?></h2><p dir="ltr">@<?= e($person['username']) ?> · <?= e($person['city'] ?: 'شهر ثبت نشده') ?></p></div>
  <div class="monitor-profile-contact"><span><?= app_icon('phone') ?> <?= e($person['phone'] ?: 'شماره ثبت نشده') ?></span><span><?= app_icon('mail') ?> <?= e($person['email'] ?: 'ایمیل ثبت نشده') ?></span></div>
</section>
<div class="monitor-detail-metrics"><div><b><?= fa_num((int)$person['tasks_total']) ?></b><span>کل تسک</span></div><div><b><?= fa_num((int)$person['tasks_done']) ?></b><span>تسک انجام‌شده</span></div><div><b><?= fa_num((int)$person['habits_total']) ?></b><span>عادت فعال</span></div><div><b><?= fa_num((int)$person['goals_total']) ?></b><span>هدف فعال</span></div><div><b><?= fa_num($progress) ?>٪</b><span>نرخ انجام</span></div></div>
<div class="monitor-content-grid">
  <section class="monitor-panel"><div class="monitor-panel-head"><div><h2>خلاصه فعالیت</h2><p>آخرین بازدید: <?= e(monitor_time($person['last_seen'])) ?></p></div><span class="monitor-big-progress"><?= fa_num($progress) ?>٪</span></div><div class="monitor-profile-bar"><i style="width:<?= $progress ?>%"></i></div><div class="monitor-info-grid"><div><small>استان</small><b><?= e($person['province'] ?: '—') ?></b></div><div><small>تاریخ عضویت</small><b><?= e(monitor_time($person['app_created_at'])) ?></b></div><div><small>آخرین همگام‌سازی</small><b><?= e(monitor_time($person['synced_at'])) ?></b></div><div><small>نقش</small><b><?= $person['role'] === 'admin' ? 'مدیر سامانه' : 'کاربر' ?></b></div></div></section>
  <section class="monitor-panel"><div class="monitor-panel-head"><div><h2>تایم‌لاین فعالیت</h2><p>آخرین ۶۰ رویداد این کاربر</p></div></div><div class="monitor-event-list"><?php foreach ($events as $event): ?><div class="monitor-event-row"><span class="event-dot <?= $event['event_type'] === 'user_action' ? 'is-action' : '' ?>"></span><div><b><?= e(monitor_event_label($event['event_type'])) ?></b><span><?= e($event['page_path']) ?><?= $event['details'] ? ' · ' . e($event['details']) : '' ?></span></div><time><?= e(monitor_time($event['created_at'])) ?></time></div><?php endforeach; ?><?php if (!$events): ?><div class="monitor-empty small-empty"><span>هنوز رویدادی برای این کاربر ثبت نشده.</span></div><?php endif; ?></div></section>
</div>
<?php include __DIR__ . '/footer.php'; ?>
