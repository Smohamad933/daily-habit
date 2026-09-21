<?php
require __DIR__ . '/common.php';
$monitor_admin = monitor_require_admin();
$monitor_active = 'events';
$monitor_title = 'رویدادها';
$pdo = monitoring_pdo();
$q = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');
$events = [];
if ($pdo) {
    $sql = "SELECT * FROM monitor_events WHERE 1=1";
    $args = [];
    if ($q !== '') {
        $sql .= " AND (username LIKE ? OR page_path LIKE ? OR details LIKE ? OR event_type LIKE ? )";
        $like = '%' . $q . '%';
        $args = [$like, $like, $like, $like];
    }
    if ($type !== '') { $sql .= " AND event_type=?"; $args[] = $type; }
    $sql .= " ORDER BY id DESC LIMIT 150";
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $events = $st->fetchAll();
}
include __DIR__ . '/header.php';
?>
<section class="monitor-panel monitor-wide-panel">
  <div class="monitor-panel-head"><div><h2>لاگ فعالیت‌ها</h2><p>برای حفظ حریم خصوصی، IP خام ذخیره نمی‌شود و فقط هش دستگاه برای تفکیک نشست‌ها نگه‌داری می‌شود.</p></div><span class="monitor-count-pill"><?= fa_num(count($events)) ?> رویداد</span></div>
  <form class="monitor-filter" method="get">
    <label><?= app_icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="جستجو در کاربر، صفحه یا جزئیات"></label>
    <select name="type"><option value="">همه رویدادها</option><option value="page_view" <?= $type === 'page_view' ? 'selected' : '' ?>>بازدید صفحه</option><option value="user_action" <?= $type === 'user_action' ? 'selected' : '' ?>>اقدام کاربر</option><option value="login" <?= $type === 'login' ? 'selected' : '' ?>>ورود کاربر</option><option value="monitor_login" <?= $type === 'monitor_login' ? 'selected' : '' ?>>ورود مدیر</option></select>
    <button class="monitor-primary" type="submit">فیلتر <?= app_icon('filter') ?></button>
    <?php if ($q || $type): ?><a class="monitor-outline" href="<?= e(monitor_url('events.php')) ?>">حذف فیلتر</a><?php endif; ?>
  </form>
  <div class="monitor-table-wrap"><table class="monitor-table"><thead><tr><th>کاربر</th><th>نوع رویداد</th><th>صفحه / جزئیات</th><th>زمان</th></tr></thead><tbody>
  <?php foreach ($events as $event): ?><tr><td><b><?= e($event['username'] ?: 'مدیر مانیتورینگ') ?></b><small><?= $event['app_user_id'] ? 'شناسه ' . fa_num($event['app_user_id']) : 'پنل' ?></small></td><td><span class="monitor-event-tag <?= $event['event_type'] === 'user_action' ? 'is-action' : '' ?>"><?= e(monitor_event_label($event['event_type'])) ?></span></td><td><b dir="ltr"><?= e($event['page_path'] ?: '—') ?></b><small><?= e($event['details'] ?: 'بدون جزئیات') ?></small></td><td dir="ltr" class="monitor-nowrap"><?= e(monitor_time($event['created_at'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$events): ?><tr><td colspan="4"><div class="monitor-empty small-empty"><span>نتیجه‌ای برای این فیلتر پیدا نشد.</span></div></td></tr><?php endif; ?>
  </tbody></table></div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
