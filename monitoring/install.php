<?php
require __DIR__ . '/common.php';

$defaults = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'daily_habit_monitor',
    'username' => 'root',
    'password' => '',
];
$old = monitoring_config();
foreach ($defaults as $key => $value) if ($key !== 'password' && isset($old[$key])) $defaults[$key] = (string)$old[$key];
$form = [
    'host' => $_POST['host'] ?? $defaults['host'],
    'port' => $_POST['port'] ?? $defaults['port'],
    'database' => $_POST['database'] ?? $defaults['database'],
    'username' => $_POST['username'] ?? $defaults['username'],
    'password' => $_POST['password'] ?? '',
    'admin_username' => $_POST['admin_username'] ?? 'Mohusyn',
    'admin_password' => $_POST['admin_password'] ?? 'Smosh1387',
];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!monitor_csrf_check()) $errors[] = 'درخواست امنیتی معتبر نیست؛ صفحه را دوباره باز کنید.';
    if (!monitoring_mysql_available()) $errors[] = 'درایور pdo_mysql روی PHP فعال نیست. در php.ini مقدار extension=pdo_mysql را فعال کنید.';
    if (!preg_match('/^[A-Za-z0-9_.:-]{1,120}$/', $form['host'])) $errors[] = 'آدرس MySQL معتبر نیست.';
    if (!preg_match('/^\d{2,5}$/', (string)$form['port']) || (int)$form['port'] < 1 || (int)$form['port'] > 65535) $errors[] = 'پورت MySQL معتبر نیست.';
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $form['database'])) $errors[] = 'نام دیتابیس فقط می‌تواند شامل حروف انگلیسی، عدد و _ باشد.';
    if (!preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $form['username'])) $errors[] = 'نام کاربری MySQL معتبر نیست.';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $form['admin_username'])) $errors[] = 'نام کاربری مدیر مانیتورینگ معتبر نیست.';
    if (strlen($form['admin_password']) < 8) $errors[] = 'رمز مدیر مانیتورینگ باید حداقل ۸ کاراکتر باشد.';

    if (!$errors) {
        try {
            $host = $form['host'];
            $port = (int)$form['port'];
            $dbName = $form['database'];
            $server = new PDO(
                'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4',
                $form['username'],
                $form['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            // نام دیتابیس از regex عبور کرده و قابل تزریق نیست.
            $server->exec('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo = new PDO(
                'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbName . ';charset=utf8mb4',
                $form['username'],
                $form['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            monitoring_mysql_init($pdo);
            monitoring_upsert_admin($pdo, $form['admin_username'], $form['admin_password'], 'مدیر مانیتورینگ');

            $config = [
                'host' => $host,
                'port' => $port,
                'database' => $dbName,
                'username' => $form['username'],
                'password' => $form['password'],
                'ip_secret' => bin2hex(random_bytes(24)),
            ];
            $configFile = monitoring_config_path();
            $configText = "<?php\n// این فایل توسط monitoring/install.php ساخته شده و در data/ قرار دارد.\nreturn " . var_export($config, true) . ";\n";
            if (!is_dir(dirname($configFile))) @mkdir(dirname($configFile), 0775, true);
            if (@file_put_contents($configFile, $configText, LOCK_EX) === false) {
                throw new RuntimeException('پوشه data برای ذخیره تنظیمات قابل نوشتن نیست. دسترسی Modify را به IIS_IUSRS بدهید.');
            }
            $success = true;
        } catch (Throwable $e) {
            $errors[] = 'اتصال یا ساخت دیتابیس انجام نشد: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>نصب مانیتورینگ MySQL</title>
<link rel="stylesheet" href="<?= e(monitor_url('assets/monitor.css')) ?>">
</head>
<body class="monitor-auth-body">
<div class="monitor-installer">
  <div class="monitor-installer-head"><span class="monitor-auth-mark"><?= app_icon('database') ?></span><div><div class="monitor-eyebrow">نصب یک‌باره</div><h1>اتصال مانیتورینگ به MySQL</h1></div></div>
  <?php foreach ($errors as $error): ?><div class="monitor-alert is-error"><?= e($error) ?></div><?php endforeach; ?>
  <?php if ($success): ?>
    <div class="monitor-success-panel"><div class="monitor-success-icon"><?= app_icon('check') ?></div><h2>نصب با موفقیت انجام شد</h2><p>جدول‌های مانیتورینگ و مدیر پنل داخل MySQL ساخته شدند. رمز به‌صورت هش‌شده ذخیره شده است.</p><div class="monitor-credential"><span>نام کاربری پنل</span><b dir="ltr"><?= e($form['admin_username']) ?></b><span>رمز اولیه</span><b dir="ltr"><?= e($form['admin_password']) ?></b></div><a class="monitor-primary monitor-block" href="<?= e(monitor_url('login.php')) ?>">ورود به پنل <?= app_icon('arrow-left') ?></a></div>
  <?php else: ?>
    <p class="monitor-installer-copy">مشخصات سرور MySQL را وارد کنید. نصب‌کننده دیتابیس را در صورت داشتن دسترسی ایجاد می‌کند و سپس فقط شاخص‌ها، رویدادها و وضعیت‌های غیرحساس سامانه را در آن ثبت می‌کند.</p>
    <form method="post" class="monitor-form monitor-install-form">
      <?= monitor_csrf_field() ?>
      <div class="monitor-form-section"><h2><?= app_icon('server') ?> اتصال MySQL</h2><div class="monitor-form-grid">
        <label>Host<input type="text" name="host" value="<?= e($form['host']) ?>" dir="ltr" required></label>
        <label>Port<input type="text" name="port" value="<?= e($form['port']) ?>" dir="ltr" required></label>
        <label>نام دیتابیس<input type="text" name="database" value="<?= e($form['database']) ?>" dir="ltr" required></label>
        <label>نام کاربری MySQL<input type="text" name="username" value="<?= e($form['username']) ?>" dir="ltr" required></label>
        <label class="monitor-full">رمز MySQL<input type="password" name="password" value="<?= e($form['password']) ?>" dir="ltr" autocomplete="new-password"></label>
      </div></div>
      <div class="monitor-form-section"><h2><?= app_icon('shield') ?> مدیر پنل</h2><div class="monitor-form-grid">
        <label>نام کاربری پنل<input type="text" name="admin_username" value="<?= e($form['admin_username']) ?>" dir="ltr" required></label>
        <label>رمز پنل<input type="password" name="admin_password" value="<?= e($form['admin_password']) ?>" dir="ltr" autocomplete="new-password" required></label>
      </div><p class="monitor-help">مقدار پیش‌فرض همین است: <b dir="ltr">Mohusyn</b> / <b dir="ltr">Smosh1387</b>. در صورت تغییر، آن را امن نگه دارید.</p></div>
      <button class="monitor-primary monitor-block" type="submit">ساخت اتصال و جدول‌ها <?= app_icon('arrow-left') ?></button>
    </form>
  <?php endif; ?>
  <div class="monitor-auth-foot"><a href="<?= e(monitor_url('login.php')) ?>">صفحه ورود مانیتورینگ</a><span>پس از نصب، دسترسی به install.php را محدود یا حذف کنید.</span></div>
</div>
</body>
</html>
