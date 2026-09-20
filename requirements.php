<?php
/** صفحه تشخیص نیازمندی‌ها — مستقل از برنامه؛ حتی اگر نصب خراب باشد کار می‌کند */
error_reporting(E_ALL);
ini_set('display_errors', '1');

function chk(bool $ok, string $label, string $help = ''): string {
    $icon = $ok ? '✅' : '❌';
    return '<tr><td>' . $icon . '</td><td><b>' . htmlspecialchars($label) . '</b></td><td class="h">'
         . htmlspecialchars($help) . '</td></tr>';
}

$pdoSqlite = extension_loaded('pdo_sqlite');
$sqlite3 = extension_loaded('sqlite3');
$openssl = extension_loaded('openssl');
$curl = extension_loaded('curl');
$mb = extension_loaded('mbstring');
$zip = extension_loaded('zip');

$dataDir = __DIR__ . '/data';
$dataOk = is_dir($dataDir) && is_writable($dataDir);
if (!is_dir($dataDir)) @mkdir($dataDir, 0775, true);

$phpIni = php_ini_loaded_file() ?: 'php.ini';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>تشخیص نیازمندی‌ها</title>
<style>
body{font-family:Tahoma,'Segoe UI';background:#0f1220;color:#eef0fa;margin:0;padding:24px}
.card{background:#181c30;border:1px solid #2a2f4a;border-radius:16px;padding:26px;max-width:820px;margin:20px auto}
h1{font-size:22px} table{width:100%;border-collapse:collapse;font-size:14px}
td{padding:9px 8px;border-bottom:1px solid #2a2f4a;vertical-align:top}
.h{color:#98a0bd;font-size:12.5px} code{background:#232842;padding:2px 8px;border-radius:6px;direction:ltr;display:inline-block;font-size:12px}
a{color:#9d8cff} .ok-all{background:#123527;border-color:#22c793}
</style>
</head>
<body>
<div class="card <?= ($pdoSqlite && $dataOk) ? 'ok-all' : '' ?>">
<h1>🩺 تشخیص نیازمندی‌های سرور</h1>
<p>نسخه PHP: <code><?= htmlspecialchars(PHP_VERSION) ?></code> — مسیر php.ini: <code><?= htmlspecialchars($phpIni) ?></code></p>
<table>
<?= chk(version_compare(PHP_VERSION, '8.0.0', '>='), 'PHP نسخه ۸ یا بالاتر', 'نسخه فعلی: ' . PHP_VERSION) ?>
<?= chk($pdoSqlite, 'افزونه pdo_sqlite (ضروری)', 'در php.ini خط extension=pdo_sqlite را فعال کنید') ?>
<?= chk($sqlite3, 'افزونه sqlite3 (ضروری)', 'در php.ini خط extension=sqlite3 را فعال کنید') ?>
<?= chk($mb, 'افزونه mbstring (برای متن فارسی توصیه می‌شود)', 'در php.ini خط extension=mbstring را فعال کنید') ?>
<?= chk($openssl, 'افزونه openssl (برای پوش نوتیفیکیشن)', 'در php.ini خط extension=openssl را فعال کنید — بدون آن سایت کار می‌کند ولی پوش غیرفعال است') ?>
<?= chk($curl, 'افزونه curl (برای پوش و AI خارجی)', 'در php.ini خط extension=curl را فعال کنید') ?>
<?= chk($zip, 'افزونه zip (برای خروجی اکسل واقعی)', 'در php.ini خط extension=zip را فعال کنید — بدون آن خروجی با فرمت جایگزین ارائه می‌شود') ?>
<?= chk($dataOk, 'پوشه data قابل نوشتن است', 'به کاربر IIS (مثل IIS_IUSRS) دسترسی Modify روی پوشه data بدهید') ?>
</table>
<?php if (!$pdoSqlite || !$sqlite3): ?>
<p style="color:#ef4767"><b>اقدام لازم:</b> فایل <code><?= htmlspecialchars($phpIni) ?></code> را باز کنید، خطوط زیر را پیدا و از حالت کامنت (;) خارج کنید، سپس اپ‌پول IIS را ری‌استارت کنید:</p>
<pre dir="ltr" style="background:#232842;padding:12px;border-radius:10px">extension=pdo_sqlite
extension=sqlite3
extension=mbstring
extension=openssl
extension=curl
extension=zip</pre>
<p>همچنین مطمئن شوید <code>extension_dir</code> به پوشه <code>ext</code> در محل نصب PHP اشاره می‌کند و فایل‌های dll در آن وجود دارند.</p>
<?php else: ?>
<p style="color:#22c793">✅ نیازمندی‌های اصلی برقرار است. <?php if (!$openssl || !$curl): ?>برای پوش نوتیفیکیشن کامل، افزونه‌های علامت‌خورده را هم فعال کنید.<?php endif; ?></p>
<?php endif; ?>
<p><a href="index.php">→ بازگشت به سایت</a></p>
</div>
</body>
</html>
