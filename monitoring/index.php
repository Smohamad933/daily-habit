<?php
require __DIR__ . '/common.php';
$monitor_admin = monitor_require_admin();
$monitor_active = 'dashboard';
$monitor_title = 'نمای کلی';

$syncCount = monitoring_sync_all();
$pdo = monitoring_pdo();
$stats = ['users' => 0, 'active' => 0, 'tasks' => 0, 'done' => 0, 'events' => 0];
$users = [];
$events = [];
if ($pdo) {
    $stats['users'] = (int)$pdo->query("SELECT COUNT(*) FROM monitor_users")->fetchColumn();
    $stats['active'] = (int)$pdo->query("SELECT COUNT(*) FROM monitor_users WHERE last_seen >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 24 HOUR),'%Y-%m-%d %H:%i:%s')")->fetchColumn();
    $stats['tasks'] = (int)$pdo->query("SELECT COALESCE(SUM(tasks_total),0) FROM monitor_users")->fetchColumn();
    $stats['done'] = (int)$pdo->query("SELECT COALESCE(SUM(tasks_done),0) FROM monitor_users")->fetchColumn();
    $stats['events'] = (int)$pdo->query("SELECT COUNT(*) FROM monitor_events WHERE created_at >= CURDATE()")->fetchColumn();
    $users = $pdo->query("SELECT * FROM monitor_users ORDER BY (last_seen='') ASC, last_seen DESC, app_user_id DESC LIMIT 12")->fetchAll();
    $events = $pdo->query("SELECT * FROM monitor_events ORDER BY id DESC LIMIT 10")->fetchAll();
}
$taskPercent = monitor_percent($stats['done'], $stats['tasks']);
$monitorToday = j_today_str();
include __DIR__ . '/header.php';
?>
<section class="monitor-welcome">
  <div><span class="monitor-eyebrow">آخرین همگام‌سازی: <?= fa_num($syncCount) ?> کاربر</span><h2>تصویر روشن‌تری از روند سامانه داشته باش</h2><p>افراد فعال، پیشرفت تسک‌ها و آخرین رویدادها را بدون شلوغی دنبال کن.</p></div>
  <div class="monitor-date-badge"><?= app_icon('calendar') ?><span><?= j_format($monitorToday, false) ?></span></div>
</section>

<div class="monitor-metric-grid">
  <div class="monitor-metric"><span class="metric-icon green"><?= app_icon('users') ?></span><div><b><?= fa_num($stats['users']) ?></b><small>کل افراد</small></div><span class="metric-caption">ثبت‌شده</span></div>
  <div class="monitor-metric"><span class="metric-icon blue"><?= app_icon('pulse') ?></span><div><b><?= fa_num($stats['active']) ?></b><small>فعال در ۲۴ ساعت اخیر</small></div><span class="metric-caption">آنلاین‌نما</span></div>
  <div class="monitor-metric"><span class="metric-icon amber"><?= app_icon('check') ?></span><div><b><?= fa_num($taskPercent) ?>٪</b><small>نرخ انجام تسک‌ها</small></div><span class="metric-caption"><?= fa_num($stats['done']) ?> از <?= fa_num($stats['tasks']) ?></span></div>
  <div class="monitor-metric"><span class="metric-icon violet"><?= app_icon('activity') ?></span><div><b><?= fa_num($stats['events']) ?></b><small>رویداد امروز</small></div><span class="metric-caption">ثبت‌شده</span></div>
</div>

<div class="monitor-content-grid">
  <section class="monitor-panel" id="users">
    <div class="monitor-panel-head"><div><h2>افراد و وضعیت پیشرفت</h2><p>نمای فشرده‌ای از کاربران سامانه</p></div><a class="monitor-link" href="<?= e(monitor_url('events.php')) ?>">همه رویدادها <?= app_icon('arrow-left') ?></a></div>
    <?php if (!$users): ?><div class="monitor-empty"><?= app_icon('users') ?><b>هنوز داده‌ای همگام نشده</b><span>بعد از ورود کاربران، اینجا وضعیت آن‌ها دیده می‌شود.</span></div><?php endif; ?>
    <div class="monitor-user-list">
      <?php foreach ($users as $person): $p = monitor_percent((int)$person['tasks_done'], (int)$person['tasks_total']); ?>
      <a class="monitor-user-row" href="<?= e(monitor_url('user.php?id=' . (int)$person['app_user_id'])) ?>">
        <span class="monitor-avatar <?= $person['role'] === 'admin' ? 'is-admin' : '' ?>"><?= e(monitor_initial($person['full_name'] ?: $person['username'])) ?></span>
        <span class="monitor-user-main"><b><?= e($person['full_name'] ?: $person['username']) ?></b><small dir="ltr">@<?= e($person['username']) ?> · <?= e($person['city'] ?: 'بدون شهر') ?></small></span>
        <span class="monitor-progress"><span><i style="width:<?= $p ?>%"></i></span><small><?= fa_num($p) ?>٪ پیشرفت تسک</small></span>
        <span class="monitor-user-meta"><b><?= fa_num((int)$person['tasks_total']) ?></b><small>تسک</small></span>
        <span class="monitor-chevron"><?= app_icon('chevron-left') ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="monitor-panel">
    <div class="monitor-panel-head"><div><h2>آخرین رویدادها</h2><p>بازدید و اقدام‌های ثبت‌شده</p></div><a class="monitor-icon-btn" href="<?= e(monitor_url('events.php')) ?>" title="مشاهده همه"><?= app_icon('external') ?></a></div>
    <div class="monitor-event-list">
      <?php if (!$events): ?><div class="monitor-empty small-empty"><span>رویدادی ثبت نشده.</span></div><?php endif; ?>
      <?php foreach ($events as $event): ?>
      <div class="monitor-event-row"><span class="event-dot <?= $event['event_type'] === 'user_action' ? 'is-action' : '' ?>"></span><div><b><?= e($event['username'] ?: 'مدیر مانیتورینگ') ?></b><span><?= e(monitor_event_label($event['event_type'])) ?><?php if ($event['page_path']): ?> · <?= e($event['page_path']) ?><?php endif; ?></span></div><time><?= e(monitor_time($event['created_at'])) ?></time></div>
      <?php endforeach; ?>
    </div>
  </section>
</div>
<?php include __DIR__ . '/footer.php'; ?>
