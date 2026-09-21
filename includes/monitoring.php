<?php
/**
 * لایه‌ی اختیاری مانیتورینگ MySQL.
 *
 * دیتابیس اصلی برنامه همچنان SQLite است تا نصب ساده و سازگار با IIS بماند؛
 * این لایه یک کپی امن از شاخص‌ها و رویدادهای کاری را در MySQL نگه می‌دارد.
 * رمزهای عبور هرگز در رویدادها یا snapshotها ذخیره نمی‌شوند.
 */

function monitoring_config_path(): string {
    return APP_ROOT . '/data/monitoring_mysql.php';
}

function monitoring_config(): array {
    static $config = null;
    if ($config !== null) return $config;
    $config = [];
    $file = monitoring_config_path();
    if (is_file($file)) {
        $loaded = require $file;
        if (is_array($loaded)) $config = $loaded;
    }
    return $config;
}

function monitoring_configured(): bool {
    $c = monitoring_config();
    return !empty($c['host']) && !empty($c['database']) && !empty($c['username']);
}

function monitoring_mysql_available(): bool {
    return class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers(), true);
}

function monitoring_pdo(): ?PDO {
    static $pdo = null;
    static $attempted = false;
    global $monitoring_pdo_error;
    if ($pdo instanceof PDO) return $pdo;
    if ($attempted) return null;
    $attempted = true;
    $monitoring_pdo_error = '';

    if (!monitoring_configured()) {
        $monitoring_pdo_error = 'اتصال MySQL هنوز نصب نشده است.';
        return null;
    }
    if (!monitoring_mysql_available()) {
        $monitoring_pdo_error = 'درایور pdo_mysql روی PHP فعال نیست.';
        return null;
    }

    $c = monitoring_config();
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($c['host'] ?? '127.0.0.1'));
    $port = (int)($c['port'] ?? 3306);
    $port = $port > 0 && $port < 65536 ? $port : 3306;
    $database = (string)($c['database'] ?? '');
    try {
        $pdo = new PDO(
            'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=utf8mb4',
            (string)($c['username'] ?? ''),
            (string)($c['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (Throwable $e) {
        $monitoring_pdo_error = $e->getMessage();
        return null;
    }
}

function monitoring_pdo_error(): string {
    global $monitoring_pdo_error;
    return (string)($monitoring_pdo_error ?? '');
}

/** ساخت جداول MySQL مانیتورینگ. */
function monitoring_mysql_init(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS monitor_admins (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        username VARCHAR(64) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        display_name VARCHAR(120) NOT NULL DEFAULT '',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_login_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_monitor_admin_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS monitor_users (
        app_user_id INT UNSIGNED NOT NULL,
        username VARCHAR(64) NOT NULL,
        full_name VARCHAR(180) NOT NULL DEFAULT '',
        email VARCHAR(190) NOT NULL DEFAULT '',
        phone VARCHAR(40) NOT NULL DEFAULT '',
        province VARCHAR(100) NOT NULL DEFAULT '',
        city VARCHAR(100) NOT NULL DEFAULT '',
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        app_created_at VARCHAR(32) NOT NULL DEFAULT '',
        last_seen VARCHAR(32) NOT NULL DEFAULT '',
        tasks_total INT UNSIGNED NOT NULL DEFAULT 0,
        tasks_done INT UNSIGNED NOT NULL DEFAULT 0,
        habits_total INT UNSIGNED NOT NULL DEFAULT 0,
        goals_total INT UNSIGNED NOT NULL DEFAULT 0,
        synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (app_user_id),
        KEY idx_monitor_users_seen (last_seen),
        KEY idx_monitor_users_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS monitor_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        app_user_id INT UNSIGNED NULL,
        username VARCHAR(64) NOT NULL DEFAULT '',
        event_type VARCHAR(40) NOT NULL,
        page_path VARCHAR(180) NOT NULL DEFAULT '',
        details TEXT NULL,
        ip_hash CHAR(64) NOT NULL DEFAULT '',
        user_agent VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_monitor_events_user (app_user_id),
        KEY idx_monitor_events_type (event_type),
        KEY idx_monitor_events_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function monitoring_upsert_admin(PDO $pdo, string $username, string $password, string $displayName = 'مدیر مانیتورینگ'): void {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $st = $pdo->prepare("INSERT INTO monitor_admins (username,password_hash,display_name,is_active)
        VALUES (?,?,?,1)
        ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), display_name=VALUES(display_name), is_active=1");
    $st->execute([$username, $hash, $displayName]);
}

function monitoring_find_admin(string $username): ?array {
    $pdo = monitoring_pdo();
    if (!$pdo) return null;
    $st = $pdo->prepare("SELECT * FROM monitor_admins WHERE username=? AND is_active=1 LIMIT 1");
    $st->execute([trim($username)]);
    $admin = $st->fetch();
    return $admin ?: null;
}

function monitoring_admin_login(array $admin): void {
    session_regenerate_id(true);
    $_SESSION['monitor_admin_id'] = (int)$admin['id'];
    $_SESSION['monitor_admin_username'] = (string)$admin['username'];
    $pdo = monitoring_pdo();
    if ($pdo) {
        $pdo->prepare("UPDATE monitor_admins SET last_login_at=NOW() WHERE id=?")
            ->execute([(int)$admin['id']]);
    }
    monitor_log_event('monitor_login', 'monitoring/login.php', 0, 'ورود مدیر مانیتورینگ');
}

function monitoring_admin(): ?array {
    static $admin = null;
    static $loaded = false;
    if ($loaded) return $admin;
    $loaded = true;
    $id = (int)($_SESSION['monitor_admin_id'] ?? 0);
    if (!$id) return null;
    $pdo = monitoring_pdo();
    if (!$pdo) return null;
    $st = $pdo->prepare("SELECT * FROM monitor_admins WHERE id=? AND is_active=1 LIMIT 1");
    $st->execute([$id]);
    $admin = $st->fetch() ?: null;
    return $admin;
}

function monitoring_logout(): void {
    unset($_SESSION['monitor_admin_id'], $_SESSION['monitor_admin_username']);
}

function monitoring_record_ip_hash(): string {
    $c = monitoring_config();
    $secret = (string)($c['ip_secret'] ?? APP_NAME);
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', $secret . '|' . $ip);
}

/** ثبت رویداد بدون ذخیره‌ی IP خام یا رمز عبور. */
function monitor_log_event(string $eventType, string $page = '', ?int $uid = null, string $details = ''): void {
    $pdo = monitoring_pdo();
    if (!$pdo) return;
    if ($uid === null && function_exists('current_user')) {
        $cu = current_user();
        $uid = $cu ? (int)$cu['id'] : null;
    }
    $username = '';
    if ($uid && function_exists('current_user')) {
        $cu = current_user();
        if ($cu && (int)$cu['id'] === $uid) $username = (string)$cu['username'];
    }
    if ($uid && $username === '') {
        try {
            $ust = db()->prepare("SELECT username FROM users WHERE id=? LIMIT 1");
            $ust->execute([$uid]);
            $username = (string)($ust->fetchColumn() ?: '');
        } catch (Throwable $e) {
            $username = '';
        }
    }
    try {
        $st = $pdo->prepare("INSERT INTO monitor_events
            (app_user_id,username,event_type,page_path,details,ip_hash,user_agent)
            VALUES (?,?,?,?,?,?,?)");
        $st->execute([
            $uid ?: null,
            $username,
            substr($eventType, 0, 40),
            substr($page, 0, 180),
            $details !== '' ? substr($details, 0, 1000) : null,
            monitoring_record_ip_hash(),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable $e) {
        // قطعی MySQL نباید قابلیت‌های اصلی برنامه را از کار بیندازد.
    }
}

function monitoring_user_snapshot(int $uid): ?array {
    if ($uid <= 0) return null;
    $st = db()->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
    $st->execute([$uid]);
    $u = $st->fetch();
    if (!$u) return null;

    $st = db()->prepare("SELECT COUNT(*) total, SUM(status='done') done FROM tasks WHERE user_id=? AND status<>'deleted'");
    $st->execute([$uid]);
    $task = $st->fetch() ?: ['total' => 0, 'done' => 0];
    $st = db()->prepare("SELECT COUNT(*) FROM habits WHERE user_id=? AND archived=0");
    $st->execute([$uid]);
    $habits = (int)$st->fetchColumn();
    $st = db()->prepare("SELECT COUNT(*) FROM goals WHERE user_id=? AND status<>'deleted'");
    $st->execute([$uid]);
    $goals = (int)$st->fetchColumn();

    return [
        'app_user_id' => (int)$u['id'],
        'username' => (string)$u['username'],
        'full_name' => trim((string)$u['first_name'] . ' ' . (string)$u['last_name']),
        'email' => (string)($u['email'] ?? ''),
        'phone' => (string)($u['phone'] ?? ''),
        'province' => (string)($u['province'] ?? ''),
        'city' => (string)($u['city'] ?? ''),
        'role' => (string)$u['role'],
        'app_created_at' => (string)($u['created_at'] ?? ''),
        'last_seen' => (string)($u['last_seen'] ?? ''),
        'tasks_total' => (int)($task['total'] ?? 0),
        'tasks_done' => (int)($task['done'] ?? 0),
        'habits_total' => $habits,
        'goals_total' => $goals,
    ];
}

function monitoring_sync_user(int $uid): bool {
    $pdo = monitoring_pdo();
    $snapshot = monitoring_user_snapshot($uid);
    if (!$pdo || !$snapshot) return false;
    try {
        $st = $pdo->prepare("INSERT INTO monitor_users
            (app_user_id,username,full_name,email,phone,province,city,role,app_created_at,last_seen,
             tasks_total,tasks_done,habits_total,goals_total,synced_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE username=VALUES(username), full_name=VALUES(full_name), email=VALUES(email),
             phone=VALUES(phone), province=VALUES(province), city=VALUES(city), role=VALUES(role),
             app_created_at=VALUES(app_created_at), last_seen=VALUES(last_seen), tasks_total=VALUES(tasks_total),
             tasks_done=VALUES(tasks_done), habits_total=VALUES(habits_total), goals_total=VALUES(goals_total), synced_at=NOW()");
        $st->execute([
            $snapshot['app_user_id'], $snapshot['username'], $snapshot['full_name'], $snapshot['email'],
            $snapshot['phone'], $snapshot['province'], $snapshot['city'], $snapshot['role'],
            $snapshot['app_created_at'], $snapshot['last_seen'], $snapshot['tasks_total'], $snapshot['tasks_done'],
            $snapshot['habits_total'], $snapshot['goals_total'],
        ]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function monitoring_sync_all(): int {
    $pdo = monitoring_pdo();
    if (!$pdo) return 0;
    $ids = db()->query("SELECT id FROM users ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $n = 0;
    foreach ($ids as $id) if (monitoring_sync_user((int)$id)) $n++;
    return $n;
}

/** نقطه‌ی ثبت بازدید کاربران عادی؛ در bootstrap بعد از آغاز session صدا زده می‌شود. */
function monitor_record_page_view(): void {
    $uid = 0;
    if (function_exists('current_user')) {
        $cu = current_user();
        if ($cu) $uid = (int)$cu['id'];
    }
    if (!$uid) return;
    try {
        db()->prepare("UPDATE users SET last_seen=? WHERE id=?")
            ->execute([date('Y-m-d H:i:s'), $uid]);
    } catch (Throwable $e) {
        return;
    }
    monitoring_sync_user($uid);
    $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $eventType = $page === 'api.php' ? 'api_request' : 'page_view';
    if ($eventType === 'page_view') monitor_log_event($eventType, $page, $uid);
}

function monitor_note_action(string $action, int $uid): void {
    if (in_array($action, ['poll', 'theme'], true)) return;
    monitor_log_event('user_action', 'api.php', $uid, $action);
}
