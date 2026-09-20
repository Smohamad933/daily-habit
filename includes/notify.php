<?php
/** سیستم اعلان: داخلی + مرورگر + پوش کامل (Web Push / VAPID) + زمان‌بند خلاصه‌ها */

function notify_create(int $uid, string $title, string $body, string $type = 'info', string $link = ''): int {
    $st = db()->prepare("INSERT INTO notifications (user_id,title,body,ntype,link,jdate) VALUES (?,?,?,?,?,?)");
    $st->execute([$uid, $title, $body, $type, $link, j_today_str()]);
    $id = (int)db()->lastInsertId();
    push_send($uid, $title, $body);
    return $id;
}

function unread_count(int $uid): int {
    $st = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $st->execute([$uid]);
    return (int)$st->fetchColumn();
}

function already_done_notify(int $uid, string $ref, string $jdate, string $kind): bool {
    $st = db()->prepare("SELECT COUNT(*) FROM reminder_log WHERE user_id=? AND ref=? AND jdate=? AND kind=?");
    $st->execute([$uid, $ref, $jdate, $kind]);
    return $st->fetchColumn() > 0;
}

function mark_done_notify(int $uid, string $ref, string $jdate, string $kind): void {
    db()->prepare("INSERT INTO reminder_log (user_id,ref,jdate,kind) VALUES (?,?,?,?)")
        ->execute([$uid, $ref, $jdate, $kind]);
}

/* ---------- پوش نوتیفیکیشن (VAPID) ---------- */

function vapid_keys(): array {
    $pub = setting('vapid_public');
    $priv = setting('vapid_private');
    if (!$pub || !$priv) {
        $k = generate_vapid_keys();
        if ($k) {
            set_setting('vapid_public', $k['public']);
            set_setting('vapid_private', $k['private']);
            return ['public' => $k['public'], 'private' => $k['private']];
        }
        return [];
    }
    return ['public' => $pub, 'private' => $priv];
}

function push_send(int $uid, string $title, string $body): void {
    try {
        if (!function_exists('openssl_pkey_new') || !function_exists('curl_init') || !function_exists('hash_hkdf')) return;
        $subs = db()->prepare("SELECT * FROM push_subs WHERE user_id=?");
        $subs->execute([$uid]);
        $subs = $subs->fetchAll();
        if (!$subs) return;
        $keys = vapid_keys();
        if (!$keys) return;

        foreach ($subs as $sub) {
            $payload = json_encode(['title' => $title, 'body' => $body, 'url' => url('notifications.php')], JSON_UNESCAPED_UNICODE);
            $encrypted = webpush_encrypt($payload, base64url_decode($sub['p256dh']), base64url_decode($sub['auth']), $keys);
            if ($encrypted === null) continue;
            $jwt = vapid_jwt($sub['endpoint'], $keys);

            $ch = curl_init($sub['endpoint']);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_POSTFIELDS => $encrypted,
                CURLOPT_HTTPHEADER => [
                    'Authorization: WebPush ' . $jwt,
                    'Content-Type: application/octet-stream',
                    'Content-Encoding: aes128gcm',
                    'TTL: 300',
                    'Crypto-Key: p256ecdsa=' . $keys['public'],
                ],
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            // سابسکریپن‌های منقضی پاک می‌شوند
            if ($code === 404 || $code === 410) {
                db()->prepare("DELETE FROM push_subs WHERE id=?")->execute([$sub['id']]);
            }
        }
    } catch (Throwable $e) { /* پوش اختیاری است؛ خطا نادیده گرفته می‌شود */ }
}

function vapid_jwt(string $endpoint, array $keys): string {
    $origin = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
    $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $claims = base64url_encode(json_encode(['aud' => $origin, 'exp' => time() + 43200, 'sub' => 'mailto:admin@habitplan.local']));
    $data = $header . '.' . $claims;
    $pkey = openssl_pkey_get_private($keys['private']);
    openssl_sign($data, $der, $pkey, OPENSSL_ALGO_SHA256);
    return $data . '.' . base64url_encode(der_to_raw_sig($der));
}

/** تبدیل امضای DER به فرمت خام r||s (۶۴ بایت) */
function der_to_raw_sig(string $der): string {
    $pos = 2;
    if (ord($der[1]) > 128) $pos += (ord($der[1]) - 128);
    $rlen = ord($der[$pos + 1]);
    $r = substr($der, $pos + 2, $rlen);
    $pos += 2 + $rlen;
    $slen = ord($der[$pos + 1]);
    $s = substr($der, $pos + 2, $slen);
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
}

/** ساخت کلید عمومی EC از نقطه خام برای محاسبه ECDH */
function ec_key_from_raw(string $rawPoint) {
    $spki = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $rawPoint;
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    return openssl_pkey_get_public($pem);
}

/** رمزگذاری پیام طبق RFC 8291 (aes128gcm) */
function webpush_encrypt(string $plaintext, string $uaPublic, string $authSecret, array $keys) {
    $privKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$privKey) return null;
    $details = openssl_pkey_get_details($privKey);
    $ephPublic = "\x04" . $details['ec']['x'] . $details['ec']['y'];

    $peerKey = ec_key_from_raw($uaPublic);
    if (!$peerKey) return null;
    $ikm = openssl_pkey_derive($peerKey, $privKey, 32);
    if (!$ikm) return null;

    $infoKey = "WebPush: info\x00" . $uaPublic . $ephPublic;
    $prkKey = hash_hkdf('sha256', $ikm, 32, $infoKey, $authSecret);

    $salt = random_bytes(16);
    $nonce = hash_hkdf('sha256', $salt, 12, "Content-Encoding: nonce\x00", $prkKey);
    $secret = hash_hkdf('sha256', $salt, 16, "Content-Encoding: aes128gcm\x00", $prkKey);

    $record = $plaintext . "\x02"; // پدینگ رکورد نهایی
    $cipher = openssl_encrypt($record, 'aes-128-gcm', $secret, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($cipher === false) return null;

    $header = $salt . pack('N', 4096) . chr(65) . $ephPublic;
    return $header . $cipher . $tag;
}

/* ---------- زمان‌بند تنبل: یادآوری‌ها و خلاصه‌های روزانه/هفتگی/ماهانه ---------- */

function scheduler_tick(): void {
    try {
        $now = time();
        $last = (int)setting('sched_last', '0');
        if ($now - $last < 300) return; // حداکثر هر ۵ دقیقه
        set_setting('sched_last', (string)$now);

        $tz = new DateTimeZone(APP_TZ);
        $dt = new DateTime('now', $tz);
        $hour = (int)$dt->format('G');
        $minute = (int)$dt->format('i');
        [$jy, $jm, $jd] = today_jalali();
        $today = j_today_str();
        $monthDays = jalali_month_days($jy, $jm);
        $dow = j_day_of_week($jy, $jm, $jd);

        $users = db()->query("SELECT id, username, wake_time, sleep_time FROM users")->fetchAll();
        foreach ($users as $u) {
            $uid = (int)$u['id'];

            // ۱) یادآور هوشمند تسک‌های ساعت‌دارِ امروز (از یک ساعت مانده تا زمان تسک)
            $st = db()->prepare("SELECT * FROM tasks WHERE user_id=? AND jdate=? AND status='pending' AND hour<>''");
            $st->execute([$uid, $today]);
            $nowMin = $hour * 60 + $minute;
            foreach ($st->fetchAll() as $t) {
                [$th, $tm] = array_map('intval', explode(':', $t['hour']));
                $tMin = $th * 60 + $tm;
                if ($tMin >= $nowMin && $tMin - $nowMin <= 75 && !already_done_notify($uid, 'task' . $t['id'], $today, 'remind')) {
                    mark_done_notify($uid, 'task' . $t['id'], $today, 'remind');
                    notify_create($uid, '⏰ یادآوری تا ' . fa_num($t['hour']), 'تسک «' . $t['title'] . '» در برنامه امروز شماست.', 'reminder', 'planner.php?d=' . urlencode($today));
                }
            }

            // ۲) خلاصه پایان روز (ساعت ۲۱)
            if ($hour >= 21 && !already_done_notify($uid, 'sum', $today, 'daily')) {
                $stats = ai_day_stats($uid, $today);
                if ($stats['total'] > 0) {
                    mark_done_notify($uid, 'sum', $today, 'daily');
                    $doneTxt = $stats['done'] ? '✅ انجام‌شده: ' . implode('، ', array_slice($stats['done'], 0, 5)) : '';
                    $undoneTxt = $stats['undone'] ? '⛔ انجام‌نشده: ' . implode('، ', array_slice($stats['undone'], 0, 5)) : '';
                    $body = "امروز " . fa_num($stats['done_count']) . " از " . fa_num($stats['total']) . " کار انجام شد (" . pct($stats['percent']) . ").\n" . $doneTxt . ($doneTxt && $undoneTxt ? "\n" : '') . $undoneTxt;
                    if ($stats['percent'] >= 80) $body .= "\n🌟 عملکرد فوق‌العاده‌ای داشتید!";
                    notify_create($uid, '📋 گزارش کارهای امروز', $body, $stats['percent'] >= 50 ? 'success' : 'warning', 'reports.php');
                }
            }

            // ۳) خلاصه هفتگی: پنجشنبه‌ها ساعت ۲۰ به بعد (پایان هفته شمسی)
            if ($dow == 5 && $hour >= 20 && !already_done_notify($uid, 'wsum', $today, 'weekly')) {
                [$ws, $we] = j_week_bounds($jy, $jm, $jd);
                $w = ai_range_stats($uid, $ws, $we);
                if ($w['total'] > 0) {
                    mark_done_notify($uid, 'wsum', $today, 'weekly');
                    $body = "در این هفته " . fa_num($w['done_count']) . " از " . fa_num($w['total']) . " کار انجام شد (" . pct($w['percent']) . ").\n";
                    $body .= $w['percent'] >= $w['prev_percent']
                        ? '📈 نسبت به هفته قبل ' . fa_num(round($w['percent'] - $w['prev_percent'])) . ' واحد رشد داشتید.'
                        : '📉 نسبت به هفته قبل ' . fa_num(round($w['prev_percent'] - $w['percent'])) . ' واحد پسرفت داشتید.';
                    if ($w['top_negative']) $body .= "\n⚠ پرتکرارترین دلیل عقب‌ماندن: " . $w['top_negative'];
                    notify_create($uid, '🗓 گزارش هفتگی', $body, 'info', 'reports.php');
                }
            }

            // ۴) گزارش تحلیلی پایان ماه: آخرین روز ماه ساعت ۲۰ به بعد
            if ($jd == $monthDays && $hour >= 20 && !already_done_notify($uid, 'msum', $today, 'monthly')) {
                $rep = ai_monthly_report($uid, $jy, $jm);
                if ($rep['total'] > 0) {
                    mark_done_notify($uid, 'msum', $today, 'monthly');
                    notify_create($uid, '🏆 گزارش ماهانه ' . j_month_name($jm), $rep['summary'], 'success', 'reports.php?month=1');
                }
            }

            // ۵) اهداف: سررسید نزدیک است
            $st = db()->prepare("SELECT * FROM goals WHERE user_id=? AND status='active'");
            $st->execute([$uid]);
            foreach ($st->fetchAll() as $g) {
                if (!$g['end_jdate']) continue;
                $ep = j_parse($g['end_jdate']);
                if (!$ep) continue;
                $diff = j_num(...$ep) - j_num($jy, $jm, $jd);
                if ($diff == 2 && !already_done_notify($uid, 'goal' . $g['id'], $today, 'goal')) {
                    mark_done_notify($uid, 'goal' . $g['id'], $today, 'goal');
                    notify_create($uid, '🎯 سررسید هدف نزدیک است', 'فقط ۲ روز به پایان مهلت هدف «' . $g['title'] . '» مانده و پیشرفت فعلی ' . pct($g['progress']) . ' است.', 'warning', 'goals.php');
                }
            }
        }
    } catch (Throwable $e) { /* زمان‌بند نباید صفحه را خراب کند */ }
}
