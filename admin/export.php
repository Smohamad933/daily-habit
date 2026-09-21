<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$u = require_admin();
require dirname(__DIR__) . '/includes/xlsx.php';

/** ستون‌های قابل انتخاب خروجی — خودتان تعیین می‌کنید کدام‌ها در اکسل باشند */
$allCols = [
    'id' => 'شناسه', 'username' => 'نام کاربری', 'first_name' => 'نام', 'last_name' => 'نام خانوادگی',
    'phone' => 'موبایل', 'email' => 'ایمیل', 'province' => 'استان', 'city' => 'شهر',
    'birth_date' => 'تاریخ تولد', 'skills' => 'مهارت‌ها', 'job' => 'شغل',
    'personality_type' => 'تیپ شخصیتی', 'wake_time' => 'ساعت بیدارشدن', 'sleep_time' => 'ساعت خواب',
    'role' => 'نقش', 'created_at' => 'تاریخ عضویت',
    'stats_tasks' => 'تعداد تسک‌ها', 'stats_done' => 'تسک‌های انجام‌شده', 'stats_habits' => 'تعداد عادت‌ها',
    'stats_goals' => 'تعداد اهداف', 'stats_skips' => 'موارد انجام‌نشده', 'stats_month' => 'درصد انجام این ماه',
];
$defaultCols = array_keys($allCols);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $cols = array_intersect($_POST['cols'] ?? $defaultCols, array_keys($allCols));
    if (!$cols) $cols = $defaultCols;
    $q = trim($_POST['q'] ?? '');

    $sql = "SELECT * FROM users";
    $args = [];
    if ($q !== '') {
        $sql .= " WHERE username LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR city LIKE ? OR province LIKE ?";
        $like = "%$q%";
        $args = array_fill(0, 6, $like);
    }
    $sql .= " ORDER BY id";
    $st = db()->prepare($sql);
    $st->execute($args);
    $users = $st->fetchAll();

    // آمار هر کاربر
    $counters = [];
    foreach (db()->query("SELECT user_id, COUNT(*) c, SUM(status='done') d FROM tasks WHERE status<>'deleted' GROUP BY user_id") as $r) {
        $counters[$r['user_id']]['tasks'] = $r['c'];
        $counters[$r['user_id']]['done'] = (int)$r['d'];
    }
    foreach (db()->query("SELECT user_id, COUNT(*) c FROM habits GROUP BY user_id") as $r) $counters[$r['user_id']]['habits'] = $r['c'];
    foreach (db()->query("SELECT user_id, COUNT(*) c FROM goals WHERE status<>'deleted' GROUP BY user_id") as $r) $counters[$r['user_id']]['goals'] = $r['c'];
    foreach (db()->query("SELECT user_id, COUNT(*) c FROM skip_reports GROUP BY user_id") as $r) $counters[$r['user_id']]['skips'] = $r['c'];

    [$jy, $jm] = today_jalali();
    $monthPercent = [];
    foreach ($users as $usr) {
        $s = ai_range_stats((int)$usr['id'], j_str($jy, $jm, 1), j_today_str());
        $monthPercent[$usr['id']] = $s['total'] ? $s['percent'] : '';
    }

    $xlsx = new XlsxWriter();
    $header = [];
    foreach ($cols as $c) $header[] = $allCols[$c];
    $xlsx->addRow($header);

    foreach ($users as $r) {
        $cnt = $counters[$r['id']] ?? [];
        $row = [];
        foreach ($cols as $c) {
            switch ($c) {
                case 'stats_tasks': $row[] = (int)($cnt['tasks'] ?? 0); break;
                case 'stats_done': $row[] = (int)($cnt['done'] ?? 0); break;
                case 'stats_habits': $row[] = (int)($cnt['habits'] ?? 0); break;
                case 'stats_goals': $row[] = (int)($cnt['goals'] ?? 0); break;
                case 'stats_skips': $row[] = (int)($cnt['skips'] ?? 0); break;
                case 'stats_month': $row[] = $monthPercent[$r['id']] !== '' ? $monthPercent[$r['id']] . '%' : ''; break;
                case 'created_at': $row[] = date('Y/m/d H:i', strtotime($r['created_at'])); break;
                default: $row[] = $r[$c] ?? '';
            }
        }
        $xlsx->addRow($row);
    }
    $xlsx->output('users-' . str_replace('/', '-', j_today_str()) . '.xlsx');
}

$page_title = 'خروجی اکسل';
$active = 'admin';
include dirname(__DIR__) . '/includes/header.php';
?>
<h1><?= app_icon('download') ?> خروجی اکسل کاربران</h1>
<div class="card" style="max-width:760px">
  <p class="muted">ستون‌هایی که می‌خواهید در فایل اکسل باشد را انتخاب کنید؛ فایل نهایی (XLSX) فقط همان ستون‌ها را خواهد داشت.</p>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label>فیلتر (اختیاری)</label>
      <input type="text" name="q" placeholder="جستجو در نام، موبایل، شهر…"></div>
    <div class="grid cols-3 mt" style="gap:8px">
      <?php foreach ($allCols as $k => $lbl): ?>
      <label style="display:flex;gap:6px;align-items:center;font-size:13px;cursor:pointer">
        <input type="checkbox" name="cols[]" value="<?= e($k) ?>" checked style="accent-color:var(--accent)"> <?= e($lbl) ?>
      </label>
      <?php endforeach; ?>
    </div>
    <button class="btn success mt">دانلود فایل اکسل (XLSX)</button>
  </form>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
