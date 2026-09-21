<?php
/** API داخلی برای درخواست‌های ایجکس (تسک‌ها، عادت‌ها، نظرسنجی، پوش) */
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$u = current_user();
$action = $_POST['action'] ?? '';

if (!$u) { echo json_encode(['ok' => false, 'error' => 'نشست منقضی شده'], JSON_UNESCAPED_UNICODE); exit; }
$uid = (int)$u['id'];
$monitorAction = (string)$action;
if (function_exists('monitor_note_action')) monitor_note_action($monitorAction, $uid);

// محافظت CSRF روی همه عملیات‌های ایجکس
if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
    echo json_encode(['ok' => false, 'error' => 'توکن امنیتی معتبر نیست؛ صفحه را رفرش کنید'], JSON_UNESCAPED_UNICODE);
    exit;
}

function out(array $a): void { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

function own_task(int $uid, int $id): ?array {
    $st = db()->prepare("SELECT * FROM tasks WHERE id=? AND user_id=?");
    $st->execute([$id, $uid]);
    $t = $st->fetch();
    return $t ?: null;
}

switch ($action) {
    case 'theme':
        $theme = in_array($_POST['theme'] ?? '', ['dark', 'light', 'auto']) ? $_POST['theme'] : 'auto';
        db()->prepare("UPDATE users SET theme=? WHERE id=?")->execute([$theme, $uid]);
        out(['ok' => true]);

    case 'task_toggle':
        $t = own_task($uid, (int)($_POST['id'] ?? 0));
        if (!$t) out(['ok' => false, 'error' => 'تسک پیدا نشد']);
        $status = ($_POST['status'] ?? '') === 'done' ? 'done' : 'pending';
        db()->prepare("UPDATE tasks SET status=?, done_at=? WHERE id=?")
            ->execute([$status, $status === 'done' ? date('Y-m-d H:i:s') : '', $t['id']]);
        $today = j_today_str();
        $s = ai_day_stats($uid, $today);
        // تشویق تکمیل کامل روز
        if ($status === 'done' && $s['total'] > 0 && $s['percent'] == 100 && !already_done_notify($uid, 'full', $today, 'full')) {
            mark_done_notify($uid, 'full', $today, 'full');
            notify_create($uid, 'روز کامل!', 'همه کارهای امروز را انجام دادید. فوق‌العاده بود!', 'success', 'reports.php');
        }
        out(['ok' => true, 'percent' => $s['percent']]);

    case 'task_skip':
        $t = own_task($uid, (int)($_POST['id'] ?? 0));
        if (!$t) out(['ok' => false, 'error' => 'تسک پیدا نشد']);
        $r = ai_record_skip($uid, $t, $_POST['category'] ?? 'other', trim($_POST['note'] ?? ''));
        out(['ok' => true, 'rescheduled_to' => $r['rescheduled_to'],
             'message' => 'برای ' . j_format($r['rescheduled_to'], false) . ' دوباره برنامه‌ریزی شد' . ($r['repeat'] >= 2 ? '  الگوی تکراری!' : '')]);

    case 'slot_save':
        $date = $_POST['date'] ?? j_today_str();
        if (!j_parse($date)) out(['ok' => false]);
        $hour = max(0, min(23, (int)($_POST['hour'] ?? 0)));
        $title = trim(mb_substr($_POST['title'] ?? '', 0, 120));
        db()->prepare("DELETE FROM plan_slots WHERE user_id=? AND jdate=? AND hour=?")->execute([$uid, $date, $hour]);
        if ($title !== '') {
            db()->prepare("INSERT INTO plan_slots (user_id,jdate,hour,title) VALUES (?,?,?,?)")
                ->execute([$uid, $date, $hour, $title]);
        }
        out(['ok' => true]);

    case 'habit_log':
        $habitId = (int)($_POST['habit'] ?? 0);
        $date = $_POST['date'] ?? j_today_str();
        $progress = max(0, min(100, (int)($_POST['progress'] ?? 0)));
        $st = db()->prepare("SELECT id FROM habits WHERE id=? AND user_id=?");
        $st->execute([$habitId, $uid]);
        if (!$st->fetch()) out(['ok' => false, 'error' => 'عادت معتبر نیست']);
        db()->prepare("INSERT INTO habit_logs (habit_id,user_id,jdate,progress) VALUES (?,?,?,?)
                       ON CONFLICT(habit_id,jdate) DO UPDATE SET progress=excluded.progress")
            ->execute([$habitId, $uid, $date, $progress]);
        out(['ok' => true]);

    case 'step_toggle':
        $sid = (int)($_POST['id'] ?? 0);
        $st = db()->prepare("SELECT * FROM goal_steps WHERE id=? AND user_id=?");
        $st->execute([$sid, $uid]);
        $step = $st->fetch();
        if (!$step) out(['ok' => false]);
        db()->prepare("UPDATE goal_steps SET is_done=? WHERE id=?")->execute([(int)!empty($_POST['done']), $sid]);
        $st = db()->prepare("SELECT COUNT(*) total, SUM(is_done) done FROM goal_steps WHERE goal_id=?");
        $st->execute([$step['goal_id']]);
        $agg = $st->fetch();
        $percent = $agg['total'] ? round($agg['done'] / $agg['total'] * 100) : 0;
        db()->prepare("UPDATE goals SET progress=?, status=CASE WHEN ? >= 100 THEN 'done' ELSE status END WHERE id=?")
            ->execute([$percent, $percent, $step['goal_id']]);
        if ($percent >= 100 && !already_done_notify($uid, 'goal_done' . $step['goal_id'], j_today_str(), 'gdone')) {
            mark_done_notify($uid, 'goal_done' . $step['goal_id'], j_today_str(), 'gdone');
            $g = db()->query("SELECT title FROM goals WHERE id=" . (int)$step['goal_id'])->fetch();
            notify_create($uid, 'هدف محقق شد!', 'هدف «' . ($g['title'] ?? '') . '» کامل شد. به خودتان افتخار کنید!', 'success', 'goals.php');
        }
        out(['ok' => true, 'percent' => $percent]);

    case 'poll':
        $last = (int)($_SESSION['last_poll'] ?? 0);
        $st = db()->prepare("SELECT title, body FROM notifications WHERE user_id=? AND id > ? ORDER BY id DESC LIMIT 5");
        $st->execute([$uid, $last]);
        $items = $st->fetchAll();
        if ($items) {
            $maxId = db()->query("SELECT MAX(id) FROM notifications WHERE user_id=$uid")->fetchColumn();
            $_SESSION['last_poll'] = (int)$maxId;
        }
        out(['ok' => true, 'items' => $items, 'unread' => unread_count($uid)]);

    case 'push_sub':
        $endpoint = trim($_POST['endpoint'] ?? '');
        $p256dh = trim($_POST['p256dh'] ?? '');
        $auth = trim($_POST['auth'] ?? '');
        if ($endpoint && $p256dh) {
            $st = db()->prepare("SELECT id FROM push_subs WHERE user_id=? AND endpoint=?");
            $st->execute([$uid, $endpoint]);
            if (!$st->fetch()) {
                db()->prepare("INSERT INTO push_subs (user_id,endpoint,p256dh,auth) VALUES (?,?,?,?)")
                    ->execute([$uid, $endpoint, $p256dh, $auth]);
            }
        }
        out(['ok' => true]);

    case 'mark_read_all':
        db()->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
        out(['ok' => true]);

    case 'goal_progress':
        $gid = (int)($_POST['id'] ?? 0);
        $p = max(0, min(100, (int)($_POST['progress'] ?? 0)));
        $st = db()->prepare("UPDATE goals SET progress=? WHERE id=? AND user_id=?");
        $st->execute([$p, $gid, $uid]);
        out(['ok' => true]);

    default:
        out(['ok' => false, 'error' => 'عملیات نامعتبر']);
}
