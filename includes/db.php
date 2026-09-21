<?php
/** اتصال PDO به SQLite + ساخت خودکار جدول‌ها در اولین اجرا */

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $isNew = !file_exists(DB_PATH);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');

    db_init($pdo);
    return $pdo;
}

function db_init(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE COLLATE NOCASE,
        password TEXT NOT NULL,
        first_name TEXT DEFAULT '',
        last_name TEXT DEFAULT '',
        email TEXT DEFAULT '',
        phone TEXT DEFAULT '',
        province TEXT DEFAULT '',
        city TEXT DEFAULT '',
        birth_date TEXT DEFAULT '',
        skills TEXT DEFAULT '',
        job TEXT DEFAULT '',
        wake_time TEXT DEFAULT '06:30',
        sleep_time TEXT DEFAULT '23:00',
        goals_text TEXT DEFAULT '',
        abilities_text TEXT DEFAULT '',
        personality_type TEXT DEFAULT '',
        theme TEXT DEFAULT 'auto',
        role TEXT DEFAULT 'user',
        created_at TEXT DEFAULT (datetime('now','localtime')),
        last_seen TEXT DEFAULT ''
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS site_content (
        ckey TEXT PRIMARY KEY,
        cvalue TEXT DEFAULT ''
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        skey TEXT PRIMARY KEY,
        svalue TEXT DEFAULT ''
    )");

    // عادت‌های روزانه
    $pdo->exec("CREATE TABLE IF NOT EXISTS habits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT DEFAULT '',
        color TEXT DEFAULT '#12633e',
        archived INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now','localtime'))
    )");

    // ثبت روزانه عادت (پیشرفت ۰ تا ۱۰۰)
    $pdo->exec("CREATE TABLE IF NOT EXISTS habit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        habit_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        jdate TEXT NOT NULL,
        progress INTEGER DEFAULT 0,
        note TEXT DEFAULT '',
        UNIQUE(habit_id, jdate)
    )");

    // تسک‌ها: روزانه، هفتگی، موردی
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        type TEXT DEFAULT 'daily',
        jdate TEXT DEFAULT '',
        week_start TEXT DEFAULT '',
        hour TEXT DEFAULT '',
        priority INTEGER DEFAULT 2,
        status TEXT DEFAULT 'pending',
        done_at TEXT DEFAULT '',
        source TEXT DEFAULT 'user',
        rescheduled_from INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now','localtime'))
    )");

    // برنامه ساعتی روز (پلنر)
    $pdo->exec("CREATE TABLE IF NOT EXISTS plan_slots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        jdate TEXT NOT NULL,
        hour INTEGER NOT NULL,
        title TEXT DEFAULT '',
        note TEXT DEFAULT ''
    )");

    // یادداشت‌ها
    $pdo->exec("CREATE TABLE IF NOT EXISTS notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT DEFAULT '',
        body TEXT DEFAULT '',
        jdate TEXT DEFAULT '',
        created_at TEXT DEFAULT (datetime('now','localtime')),
        updated_at TEXT DEFAULT ''
    )");

    // اهداف بازه‌ای: هفته، ماه، سه‌ماهه، شش‌ماهه اول/دوم، سال
    $pdo->exec("CREATE TABLE IF NOT EXISTS goals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        period TEXT DEFAULT 'month',
        start_jdate TEXT DEFAULT '',
        end_jdate TEXT DEFAULT '',
        target_text TEXT DEFAULT '',
        progress INTEGER DEFAULT 0,
        status TEXT DEFAULT 'active',
        created_at TEXT DEFAULT (datetime('now','localtime'))
    )");

    // گام‌های هر هدف (استپ به استپ — حالت مصاحبه‌ای)
    $pdo->exec("CREATE TABLE IF NOT EXISTS goal_steps (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        goal_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        step_order INTEGER DEFAULT 1,
        title TEXT NOT NULL,
        is_done INTEGER DEFAULT 0,
        due_jdate TEXT DEFAULT ''
    )");

    // ایجنت‌های هوشمند
    $pdo->exec("CREATE TABLE IF NOT EXISTS agents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        atype TEXT DEFAULT 'coach',
        goal_id INTEGER DEFAULT 0,
        config TEXT DEFAULT '{}',
        active INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now','localtime'))
    )");

    // نتایج تست شخصیت
    $pdo->exec("CREATE TABLE IF NOT EXISTS personality_results (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        scores TEXT DEFAULT '{}',
        ptype TEXT DEFAULT '',
        answers TEXT DEFAULT '{}',
        created_at TEXT DEFAULT (datetime('now','localtime'))
    )");

    // اعلان‌ها
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT DEFAULT '',
        body TEXT DEFAULT '',
        ntype TEXT DEFAULT 'info',
        link TEXT DEFAULT '',
        is_read INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now','localtime')),
        jdate TEXT DEFAULT ''
    )");

    // ثبت علت انجام نشدن کارها (خوراک تحلیل هوشمند)
    $pdo->exec("CREATE TABLE IF NOT EXISTS skip_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        ref_type TEXT DEFAULT 'task',
        ref_id INTEGER DEFAULT 0,
        ref_title TEXT DEFAULT '',
        jdate TEXT DEFAULT '',
        hour TEXT DEFAULT '',
        category TEXT DEFAULT 'other',
        is_negative INTEGER DEFAULT 1,
        note TEXT DEFAULT '',
        rescheduled_to TEXT DEFAULT '',
        created_at TEXT DEFAULT (datetime('now','localtime'))
    )");

    // گفتگوی کاربر با ایجنت (حالت مصاحبه‌ای استپ به استپ)
    $pdo->exec("CREATE TABLE IF NOT EXISTS chats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        agent_id INTEGER DEFAULT 0,
        goal_id INTEGER DEFAULT 0,
        sender TEXT DEFAULT 'user',
        message TEXT DEFAULT '',
        created_at TEXT DEFAULT (datetime('now','localtime'))
    )");

    // سابسکریپن پوش مرورگر
    $pdo->exec("CREATE TABLE IF NOT EXISTS push_subs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        endpoint TEXT DEFAULT '',
        p256dh TEXT DEFAULT '',
        auth TEXT DEFAULT ''
    )");

    // لاگ یادآوری‌های فرستاده‌شده (جلوگیری از تکرار)
    $pdo->exec("CREATE TABLE IF NOT EXISTS reminder_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        ref TEXT DEFAULT '',
        jdate TEXT DEFAULT '',
        kind TEXT DEFAULT 'reminder'
    )");
}

/** درج اولین: اکانت مدیر پیش‌فرض + محتوای سایت */
function db_seed(): void {
    $pdo = db();
    $c = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    if ($c == 0) {
        $pdo->prepare("INSERT INTO users (username, password, first_name, last_name, role) VALUES (?,?,?,?,?)")
            ->execute(['Mohusyn', password_hash('Smosh1387', PASSWORD_DEFAULT), 'مدیر', 'سیستم', 'admin']);
    }
    $defaults = [
        'site_name'   => 'پلنر روزانه من',
        'site_tagline'=> 'برنامه‌ریزی روزانه، عادت‌ها و تحلیل هوشمند پیشرفت',
        'hero_title'  => 'هر روز، یک قدم به جلو',
        'hero_text'   => 'با برنامه‌ریز روزانه شمسی، عادت‌هایت را بساز، پیشرفتت را ببین و با تحلیل هوشمند، عادت‌های بازدارنده را بشناس.',
        'announcement'=> '',
        'footer_text' => 'ساخته‌شده برای رشد روزانه',
        'login_note'  => 'ورود به سامانه برنامه‌ریزی روزانه',
        'register_note'=> 'عضویت رایگان است؛ پس از ثبت‌نام داشبورد شخصی شما فعال می‌شود.',
    ];
    $st = $pdo->prepare("INSERT OR IGNORE INTO site_content (ckey, cvalue) VALUES (?,?)");
    foreach ($defaults as $k => $v) $st->execute([$k, $v]);
}

function setting(string $key, string $default = ''): string {
    $st = db()->prepare("SELECT svalue FROM settings WHERE skey=?");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null || $v === '') ? $default : $v;
}

function set_setting(string $key, string $value): void {
    db()->prepare("INSERT INTO settings (skey, svalue) VALUES (?,?)
                   ON CONFLICT(skey) DO UPDATE SET svalue=excluded.svalue")->execute([$key, $value]);
}

function content(string $key, string $default = ''): string {
    $st = db()->prepare("SELECT cvalue FROM site_content WHERE ckey=?");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null) ? $default : $v;
}
