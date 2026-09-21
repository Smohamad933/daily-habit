<?php
/** نصب: ساخت اکانت مدیر + (اختیاری) داده دمو برای تست سریع — بعد از نصب حذفش کنید */
require __DIR__ . '/includes/bootstrap.php';
db(); db_seed();

$withDemo = isset($_GET['demo']);
$demoMade = false;

if ($withDemo) {
    $exists = db()->query("SELECT COUNT(*) FROM users WHERE username='reza'")->fetchColumn();
    if (!$exists) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO users (username,password,first_name,last_name,email,phone,province,city,birth_date,skills,job,wake_time,sleep_time,abilities_text,goals_text)
                VALUES ('reza', ?, 'رضا', 'محمدی', 'reza@example.com', '09121234567', 'تهران', 'تهران', '1380/03/12', 'آواز خوانی، تدوین ویدیو', 'دانشجو / تولید محتوا', '06:30', '23:30',
                'صدای خوبی دارم، با پریمیر کار کرده‌ام', 'ساخت و انتشار یک فیلم کوتاه و رشد کانال یوتیوب')")
                ->execute([password_hash('Reza1387!', PASSWORD_DEFAULT)]);
            $uid = (int)$pdo->lastInsertId();

            // عادت‌ها
            $habits = [['تمرین آواز خوانی', '#ef4767'], ['مطالعه برنامه‌نویسی', '#3e7eaa'], ['ورزش و باشگاه', '#258a57']];
            $hids = [];
            foreach ($habits as [$t, $c]) {
                $pdo->prepare("INSERT INTO habits (user_id,title,color) VALUES (?,?,?)")->execute([$uid, $t, $c]);
                $hids[] = (int)$pdo->lastInsertId();
            }

            [$ty, $tm, $td] = today_jalali();
            $todayNum = j_num($ty, $tm, $td);
            $reasons = [['friends', 1], ['lazy', 1], ['procrast', 1], ['urgent', 0], ['fatigue', 1], ['friends', 1], ['work', 0], ['forget', 1]];
            $titles = ['تمرین آواز خوانی', 'مرور درس زبان انگلیسی', 'تدوین ویدیوی جدید', 'تماس با یک مشتری جدید'];
            $noteSamples = ['دوستم زنگ زد برای بیرون رفتن و قبول کردم', 'حوصله نداشتم و به فردا انداختم', 'یه‌دفعه یادم رفت', 'کار فوری پیش آمد'];

            $insTask = $pdo->prepare("INSERT INTO tasks (user_id,title,type,jdate,hour,priority,status,done_at,created_at) VALUES (?,?,?,?,?,?,?,?,?)");
            $insSkip = $pdo->prepare("INSERT INTO skip_reports (user_id,ref_type,ref_id,ref_title,jdate,hour,category,is_negative,note) VALUES (?,'task',?,?,?,?,?,?,?)");
            $insLog = $pdo->prepare("INSERT OR IGNORE INTO habit_logs (habit_id,user_id,jdate,progress) VALUES (?,?,?,?)");

            for ($i = 34; $i >= 1; $i--) {
                [$dy, $dm, $dd] = d2j($todayNum - $i);
                $date = j_str($dy, $dm, $dd);
                // عادت‌ها
                foreach ($hids as $hi => $hid) {
                    if (mt_rand(0, 10) < 8) $insLog->execute([$hid, $uid, $date, mt_rand(2, 20) * 5]);
                }
                // تسک‌ها
                $n = mt_rand(2, 3);
                for ($k = 0; $k < $n; $k++) {
                    $title = $titles[mt_rand(0, count($titles) - 1)];
                    $hour = sprintf('%02d:00', mt_rand(8, 21));
                    $r = mt_rand(1, 100);
                    $status = $r <= 62 ? 'done' : ($r <= 84 ? 'skipped' : 'pending');
                    $insTask->execute([$uid, $title, 'daily', $date, $hour, mt_rand(1, 3) === 1 ? 1 : 2, $status,
                        $status === 'done' ? $date . ' 12:00:00' : '', $date . ' 08:00:00']);
                    if ($status === 'skipped') {
                        [$cat, $neg] = $reasons[mt_rand(0, count($reasons) - 1)];
                        $insSkip->execute([$uid, (int)$pdo->lastInsertId(), $title, $date, $hour, $cat, $neg,
                            $noteSamples[mt_rand(0, count($noteSamples) - 1)]]);
                    }
                }
            }

            // تسک هفتگی برای هفته جاری
            [$ws] = j_week_bounds($ty, $tm, $td);
            $pdo->prepare("INSERT INTO tasks (user_id,title,type,week_start,hour,priority,status) VALUES (?,?,?,?,?,?,'pending')")
                ->execute([$uid, 'دیدن یک جلسه دوره آموزشی', 'weekly', $ws, '18:00', 2]);

            // برنامه ساعتی امروز
            $slots = [[8, 'کار عمیق روی پروژه'], [10, 'تمرین آواز'], [13, 'ناهار و استراحت'], [17, 'باشگاه'], [21, 'مرور زبان']];
            foreach ($slots as [$h, $t]) {
                $pdo->prepare("INSERT INTO plan_slots (user_id,jdate,hour,title) VALUES (?,?,?,?)")->execute([$uid, j_today_str(), $h, $t]);
            }

            // هدف با گام‌ها
            $pdo->prepare("INSERT INTO goals (user_id,title,period,start_jdate,end_jdate,target_text,progress,status)
                VALUES (?,?,?,?,?,?,34,'planned')")
                ->execute([$uid, 'ساخت فیلم کوتاه ۱۰۰ ثانیه‌ای', 'q1', j_str(...j_add_days($ty, $tm, $td, -20)), j_str(...j_add_days($ty, $tm, $td, 70)),
                           'یک فیلم کوتاه صد ثانیه‌ای با موضوع آزاد، تدوین و منتشرشده']);
            $gid = (int)$pdo->lastInsertId();
            $steps = ['نوشتن ایده و فیلم‌نامه اولیه', 'استوری‌بورد صحنه‌ها', 'تهیه لوکیشن و تجهیزات', 'فیلمبرداری بخش اول', 'فیلمبرداری بخش دوم', 'تدوین و خروجی نهایی'];
            $insStep = $pdo->prepare("INSERT INTO goal_steps (goal_id,user_id,step_order,title,is_done,due_jdate) VALUES (?,?,?,?,?,?)");
            foreach ($steps as $i => $s) {
                $insStep->execute([$gid, $uid, $i + 1, $s, $i < 2 ? 1 : 0, j_str(...j_add_days($ty, $tm, $td, ($i + 1) * 15))]);
            }

            // نتیجه تست شخصیت
            $pdo->prepare("INSERT INTO personality_results (user_id,scores,ptype) VALUES (?,?,?)")
                ->execute([$uid, json_encode(['planner' => 2, 'flexible' => 2, 'social' => 3, 'focused' => 1], JSON_UNESCAPED_UNICODE), 'اجتماعی و ارتباطی']);
            $pdo->prepare("UPDATE users SET personality_type='اجتماعی و ارتباطی' WHERE id=?")->execute([$uid]);

            // یادداشت و اعلان
            $pdo->prepare("INSERT INTO notes (user_id,title,body,jdate) VALUES (?,?,?,?)")
                ->execute([$uid, 'ایده‌های ویدیوی بعدی', "۱) ولاگ یک روز با من\n۲) آموزش کوک صدا برای مبتدی‌ها", j_today_str()]);
            $pdo->prepare("INSERT INTO notifications (user_id,title,body,ntype,link,jdate) VALUES (?,?,?,?,?,?)")
                ->execute([$uid, 'خوش آمدید ', 'حساب دمو ساخته شد. عادت‌ها، تسک‌ها و گزارش‌ها را مرور کنید.', 'success', 'dashboard.php', j_today_str()]);

            $pdo->commit();
            $demoMade = true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo '<p style="font-family:Tahoma;direction:rtl">خطا در ساخت داده دمو: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>نصب</title><style>
body{font-family:Tahoma,'Segoe UI';background:#0f1220;color:#eef0fa;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.card{background:#181c30;border:1px solid #2a2f4a;border-radius:16px;padding:34px;max-width:520px;text-align:center}
a{display:inline-block;background:#12633e;color:#fff;padding:11px 26px;border-radius:10px;text-decoration:none;margin:6px}
a.ghost{background:#232842}code{background:#232842;padding:2px 8px;border-radius:6px;direction:ltr;display:inline-block}
</style></head><body><div class="card">
<h1><?= app_icon('check-circle') ?> نصب انجام شد</h1>
<p>دیتابیس ساخته شد و اکانت مدیر پیش‌فرض آماده است:</p>
<p><code>Mohusyn</code> / <code>Smosh1387</code></p>
<?php if ($demoMade): ?>
<p> حساب دمو هم ساخته شد: <code>reza</code> / <code>Reza1387!</code> — با ۳۵ روز داده نمونه برای دیدن نمودارها و گزارش ماهانه.</p>
<?php endif; ?>
<p style="font-size:13px;color:#98a0bd"> بعد از نصب، فایل install.php را حذف کنید.</p>
<a href="login.php">ورود به سامانه</a>
<?php if (!$demoMade): ?><a class="ghost" href="install.php?demo=1">+ ساخت داده دمو</a><?php endif; ?>
</div></body></html>
