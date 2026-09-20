<?php
/** نقطه اجرای زمان‌بند — برای دقت بالاتر با Task Scheduler ویندوز هر ۱۰ دقیقه صدا زده شود */
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
set_setting('sched_last', '0'); // اجرای فوری در این درخواست
scheduler_tick();
echo 'OK — scheduler run at ' . date('Y-m-d H:i:s') . "\n";
