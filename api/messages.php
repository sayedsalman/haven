<?php
/**
 * Haven secure messaging.
 *  - Members need the other member's PERMISSION first (message request) — except
 *    friends, and volunteers / moderators / doctors / admins, who anyone may message.
 *  - Bodies are encrypted at rest (AES-256-GCM). Images are re-encoded and served privately.
 *  - Bullying/threat filter, rate limits, strikes → temporary mute, reports, blocks.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
$pdo = hx_pdo();
$a = (string)($_GET['a'] ?? hx_input()['a'] ?? '');

/* ------------------------------------------------------------ shared helpers */
function dm_pair(int $x, int $y): array { return $x < $y ? [$x, $y] : [$y, $x]; }

function dm_thread(int $x, int $y): ?array {
    [$p, $q] = dm_pair($x, $y);
    $s = hx_pdo()->prepare('SELECT * FROM dm_threads WHERE user_a = ? AND user_b = ?');
    $s->execute([$p, $q]);
    return $s->fetch() ?: null;
}
function dm_thread_for_member(int $tid, int $uid): ?array {
    $s = hx_pdo()->prepare('SELECT * FROM dm_threads WHERE id = ? AND (user_a = ? OR user_b = ?)');
    $s->execute([$tid, $uid, $uid]);
    return $s->fetch() ?: null;
}
function dm_peer(array $t, int $me): int { return (int)($t['user_a'] == $me ? $t['user_b'] : $t['user_a']); }

function dm_muted(int $uid): ?string {
    $s = hx_pdo()->prepare('SELECT muted_until FROM dm_user_state WHERE user_id = ? AND muted_until > NOW()');
    $s->execute([$uid]);
    return $s->fetchColumn() ?: null;
}
function dm_strike(int $uid): int {
    $pdo = hx_pdo();
    $pdo->prepare('INSERT INTO dm_user_state (user_id, strikes, updated_at) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE strikes = strikes + 1, updated_at = NOW()')->execute([$uid]);
    $s = $pdo->prepare('SELECT strikes FROM dm_user_state WHERE user_id = ?');
    $s->execute([$uid]);
    $n = (int)$s->fetchColumn();
    if ($n >= 3) {                       // 3 strikes → 1h mute, doubling each time
        $hrs = min(72, 1 << min(6, $n - 3));
        $pdo->prepare('UPDATE dm_user_state SET muted_until = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE user_id = ?')->execute([$hrs, $uid]);
    }
    return $n;
}

/** Decide how $from may contact $to. Returns [mode, thread]; mode: allowed|request|pending|respond|denied */
function dm_mode(int $from, int $to): array {
    if ($from === $to) return ['denied', null];
    $b = hx_user_brief($to);
    if (!$b['active'] || $b['role'] === 'ai') return ['denied', null];
    if (hx_blocked($from, $to)) return ['denied', null];
    $t = dm_thread($from, $to);
    if ($t) {
        if ($t['status'] === 'active') return ['allowed', $t];
        if ($t['status'] === 'request') return [((int)$t['requested_by'] === $from) ? 'pending' : 'respond', $t];
        if ($t['status'] === 'declined') {
            if ((int)$t['requested_by'] !== $from) return ['allowed', $t];       // the decliner may reach out themselves
            $fresh = $t['declined_at'] && strtotime($t['declined_at']) > time() - 7 * 86400;
            return [$fresh ? 'denied' : 'request', $t];
        }
    }
    if (in_array($b['role'], HX_STAFF_ROLES, true) && !hx_is_staff($from)) return ['allowed', $t];  // members → staff: no permission needed
    if (hx_friends($from, $to)) return ['allowed', $t];
    return ['request', $t];
}

function dm_fmt(array $m, int $me): array {
    $out = [
        'id' => (int)$m['id'], 'mine' => (int)$m['sender_id'] === $me, 'kind' => $m['kind'],
        't' => date('c', strtotime($m['created_at'])), 'read' => $m['read_at'] !== null,
        'text' => ((int)$m['is_hidden'] === 1 || $m['deleted_at']) ? '' : hx_dec($m['body']),
        'hidden' => (int)$m['is_hidden'] === 1, 'deleted' => (bool)$m['deleted_at'],
    ];
    if ($m['kind'] === 'image' && !$m['deleted_at'] && !(int)$m['is_hidden']) $out['img'] = 'api/file.php?k=dm&id=' . (int)$m['id'];
    if ($m['kind'] === 'location' && $m['lat'] !== null) { $out['lat'] = (float)$m['lat']; $out['lng'] = (float)$m['lng']; }
    return $out;
}

function dm_add(int $tid, int $from, string $kind, ?string $body, ?string $media = null, $lat = null, $lng = null): int {
    $pdo = hx_pdo();
    $pdo->prepare('INSERT INTO dm_messages (thread_id, sender_id, kind, body, media_path, lat, lng, created_at) VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([$tid, $from, $kind, hx_enc($body), $media, $lat, $lng]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE dm_threads SET last_message_at = NOW() WHERE id = ?')->execute([$tid]);
    return $id;
}

/** Common "prepare to send" step for text/image/location. Returns [thread, mode, peerId]. */
function dm_prepare_send(int $me, array $d): array {
    $pdo = hx_pdo();
    if ($m = dm_muted($me)) hx_out(['ok' => false, 'error' => 'Messaging is paused on your account until ' . date('H:i', strtotime($m)) . ' because of recent warnings. Please be kind to others.'], 403);
    if (!hx_rate($me, 'dm_send', 30, 60)) hx_out(['ok' => false, 'error' => 'You are sending messages very fast. Please slow down.'], 429);

    $tid = (int)($d['thread'] ?? 0);
    if ($tid) {
        $t = dm_thread_for_member($tid, $me);
        if (!$t) hx_out(['ok' => false, 'error' => 'Conversation not found.'], 404);
        $peer = dm_peer($t, $me);
    } else {
        $peer = (int)($d['user'] ?? 0);
        if ($peer < 1) hx_out(['ok' => false, 'error' => 'Choose who to message.'], 422);
    }
    [$mode, $t] = dm_mode($me, $peer);
    if ($mode === 'denied') hx_out(['ok' => false, 'error' => 'You can’t message this person.', 'mode' => 'denied'], 403);
    if ($mode === 'pending') hx_out(['ok' => false, 'error' => 'Your request is waiting for their answer. You can send more once they accept.', 'mode' => 'pending'], 403);
    if ($mode === 'respond') hx_out(['ok' => false, 'error' => 'They sent you a request. Accept it first to reply.', 'mode' => 'respond'], 403);
    return [$t, $mode, $peer];
}

function dm_ensure_thread(?array $t, string $mode, int $me, int $peer): int {
    $pdo = hx_pdo();
    if ($mode === 'request' && !hx_rate($me, 'dm_request', 8, 86400)) hx_out(['ok' => false, 'error' => 'You have reached today’s limit for message requests.'], 429);
    [$p, $q] = dm_pair($me, $peer);
    $status = $mode === 'request' ? 'request' : 'active';
    if ($t) {
        $pdo->prepare('UPDATE dm_threads SET status = ?, requested_by = ?, declined_at = NULL WHERE id = ?')
            ->execute([$status, $mode === 'request' ? $me : $t['requested_by'], $t['id']]);
        return (int)$t['id'];
    }
    $pdo->prepare('INSERT INTO dm_threads (user_a, user_b, status, requested_by, created_at) VALUES (?,?,?,?,NOW())')
        ->execute([$p, $q, $status, $mode === 'request' ? $me : null]);
    return (int)$pdo->lastInsertId();
}

/* ================================================================== GET ==== */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $me = hx_need_login();

    if ($a === 'pulse') {
        $u = $pdo->prepare("SELECT COUNT(*) FROM dm_messages m JOIN dm_threads t ON t.id = m.thread_id
                            WHERE (t.user_a = ? OR t.user_b = ?) AND m.sender_id <> ? AND m.read_at IS NULL AND m.is_hidden = 0 AND t.status = 'active'");
        $u->execute([$me, $me, $me]);
        $r = $pdo->prepare("SELECT COUNT(*) FROM dm_threads WHERE status = 'request' AND requested_by <> ? AND (user_a = ? OR user_b = ?)");
        $r->execute([$me, $me, $me]);
        $n = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $n->execute([$me]);
        hx_out(['ok' => true, 'msgs' => (int)$u->fetchColumn(), 'requests' => (int)$r->fetchColumn(), 'notifs' => (int)$n->fetchColumn()]);
    }

    if ($a === 'threads') {
        $box = ($_GET['box'] ?? 'chats') === 'requests' ? 'requests' : 'chats';
        if ($box === 'requests') {
            $where = "t.status = 'request' AND t.requested_by <> :me";
        } else {
            $where = "(t.status = 'active' OR (t.status = 'request' AND t.requested_by = :me))";
        }
        $s = $pdo->prepare("SELECT t.*, (SELECT COUNT(*) FROM dm_messages m WHERE m.thread_id = t.id AND m.sender_id <> :me2 AND m.read_at IS NULL AND m.is_hidden = 0) AS unread,
                                   (SELECT m2.id FROM dm_messages m2 WHERE m2.thread_id = t.id AND m2.is_hidden = 0 AND m2.deleted_at IS NULL ORDER BY m2.id DESC LIMIT 1) AS last_id
                            FROM dm_threads t WHERE (t.user_a = :me3 OR t.user_b = :me4) AND $where
                            ORDER BY COALESCE(t.last_message_at, t.created_at) DESC LIMIT 40");
        $s->execute([':me' => $me, ':me2' => $me, ':me3' => $me, ':me4' => $me]);
        $out = [];
        foreach ($s->fetchAll() as $t) {
            $peer = hx_user_brief(dm_peer($t, $me));
            $prev = '';
            if ($t['last_id']) {
                $m = $pdo->prepare('SELECT * FROM dm_messages WHERE id = ?'); $m->execute([$t['last_id']]); $m = $m->fetch();
                $prev = $m['kind'] === 'image' ? '📷 Photo' : ($m['kind'] === 'location' ? '📍 Location' : mb_substr(hx_dec($m['body']), 0, 70));
                if ((int)$m['sender_id'] === $me) $prev = 'You: ' . $prev;
            }
            $out[] = ['id' => (int)$t['id'], 'peer' => $peer, 'preview' => $prev, 'at' => $t['last_message_at'] ? date('c', strtotime($t['last_message_at'])) : null,
                      'unread' => (int)$t['unread'], 'status' => $t['status'], 'mine_request' => (int)$t['requested_by'] === $me];
        }
        hx_out(['ok' => true, 'threads' => $out]);
    }

    if ($a === 'blocked') {
        $s = $pdo->prepare('SELECT blocked_id FROM blocked_users WHERE user_id = ? ORDER BY id DESC LIMIT 100'); $s->execute([$me]);
        hx_out(['ok' => true, 'blocked' => array_map('hx_user_brief', $s->fetchAll(PDO::FETCH_COLUMN))]);
    }

    if ($a === 'staff') {
        $s = $pdo->prepare("SELECT u.id FROM users u LEFT JOIN doctor_profiles d ON d.user_id = u.id
                            WHERE u.is_active = 1 AND u.id <> ? AND (u.role IN ('volunteer','moderator') OR (u.role = 'doctor' AND d.status = 'approved'))
                              AND NOT EXISTS (SELECT 1 FROM blocked_users b WHERE (b.user_id = u.id AND b.blocked_id = ?) OR (b.user_id = ? AND b.blocked_id = u.id))
                            ORDER BY FIELD(u.role,'volunteer','doctor','moderator'), u.id LIMIT 12");
        $s->execute([$me, $me, $me]);
        hx_out(['ok' => true, 'staff' => array_map('hx_user_brief', $s->fetchAll(PDO::FETCH_COLUMN))]);
    }

    if ($a === 'search') {
        $q = hx_clean((string)($_GET['q'] ?? ''), 40);
        if (mb_strlen($q) < 2) hx_out(['ok' => true, 'people' => []]);
        $s = $pdo->prepare("SELECT u.id FROM users u WHERE u.is_active = 1 AND u.role <> 'ai' AND u.id <> ? AND (u.anonymous_name LIKE ? OR u.username LIKE ?)
                            AND NOT EXISTS (SELECT 1 FROM blocked_users b WHERE (b.user_id = u.id AND b.blocked_id = ?) OR (b.user_id = ? AND b.blocked_id = u.id)) LIMIT 8");
        $like = '%' . addcslashes($q, '%_') . '%';
        $s->execute([$me, $like, $like, $me, $me]);
        hx_out(['ok' => true, 'people' => array_map('hx_user_brief', $s->fetchAll(PDO::FETCH_COLUMN))]);
    }

    if ($a === 'open') {                                  // who is this, and what may I do?
        $peerId = (int)($_GET['user'] ?? 0);
        $tid = (int)($_GET['thread'] ?? 0);
        if ($tid) { $t0 = dm_thread_for_member($tid, $me); if (!$t0) hx_out(['ok' => false, 'error' => 'Conversation not found.'], 404); $peerId = dm_peer($t0, $me); }
        if ($peerId < 1) hx_out(['ok' => false, 'error' => 'Unknown user.'], 422);
        [$mode, $t] = dm_mode($me, $peerId);
        hx_out(['ok' => true, 'peer' => hx_user_brief($peerId), 'mode' => $mode, 'thread' => $t ? (int)$t['id'] : null,
                'staff' => in_array(hx_user_brief($peerId)['role'], HX_STAFF_ROLES, true)]);
    }

    if ($a === 'thread') {
        $tid = (int)($_GET['id'] ?? 0);
        $t = dm_thread_for_member($tid, $me);
        if (!$t) hx_out(['ok' => false, 'error' => 'Conversation not found.'], 404);
        $after = (int)($_GET['after'] ?? 0);
        $s = $pdo->prepare('SELECT * FROM (SELECT * FROM dm_messages WHERE thread_id = ? AND id > ? ORDER BY id DESC LIMIT 60) x ORDER BY id ASC');
        $s->execute([$tid, $after]);
        $rows = $s->fetchAll();
        if ($t['status'] === 'active' || (int)$t['requested_by'] !== $me) {
            $pdo->prepare('UPDATE dm_messages SET read_at = NOW() WHERE thread_id = ? AND sender_id <> ? AND read_at IS NULL')->execute([$tid, $me]);
        }
        $peerId = dm_peer($t, $me);
        [$mode] = dm_mode($me, $peerId);
        hx_out(['ok' => true, 'messages' => array_map(fn($m) => dm_fmt($m, $me), $rows), 'peer' => hx_user_brief($peerId), 'mode' => $mode, 'status' => $t['status'], 'mine_request' => (int)$t['requested_by'] === $me]);
    }
    hx_out(['ok' => false, 'error' => 'Unknown action.'], 400);
}

/* ================================================================= POST ==== */
$me = hx_post_guard();
$d = hx_input();
$a = (string)($d['a'] ?? $a);

if ($a === 'send' || $a === 'send_image') {
    [$t, $mode, $peer] = dm_prepare_send($me, $d);
    $kind = 'text'; $body = ''; $media = null; $lat = $lng = null;

    if ($a === 'send_image') {
        if ($mode === 'request') hx_out(['ok' => false, 'error' => 'Photos can be sent once they accept your request.'], 403);
        if (empty($_FILES['image'])) hx_out(['ok' => false, 'error' => 'Choose a photo.'], 422);
        $r = hx_save_image($_FILES['image'], 'private:dm', 1600);
        if (!$r['ok']) hx_out(['ok' => false, 'error' => $r['error']], 422);
        $kind = 'image'; $media = $r['path'];
    } else {
        $body = hx_clean((string)($d['text'] ?? ''), $mode === 'request' ? 240 : 2000);
        [$la, $lo] = hx_coords($d['lat'] ?? null, $d['lng'] ?? null, false);
        if ($la !== null) {
            if ($mode === 'request') hx_out(['ok' => false, 'error' => 'Locations can be shared once they accept your request.'], 403);
            $kind = 'location'; $lat = $la; $lng = $lo;
        }
        if ($body === '' && $kind === 'text') hx_out(['ok' => false, 'error' => 'Write a message first.'], 422);
        if ($mode === 'request' && hx_has_link_or_phone($body)) hx_out(['ok' => false, 'error' => 'Message requests can’t include links or phone numbers.'], 422);
        if ($why = hx_toxic($body)) {
            $n = dm_strike($me);
            hx_out(['ok' => false, 'blocked' => true, 'error' => 'This message wasn’t sent — it looks like ' . $why . '. Haven is a safe space; please rephrase kindly.' . ($n >= 3 ? ' Your messaging is temporarily paused.' : '')], 422);
        }
    }
    $pdo->beginTransaction();
    try {
        $tid = dm_ensure_thread($t, $mode, $me, $peer);
        $id = dm_add($tid, $me, $kind, $body, $media, $lat, $lng);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); error_log('[Haven dm] ' . $e->getMessage()); hx_out(['ok' => false, 'error' => 'Could not send right now.'], 500); }

    $who = hx_user_brief($me)['name'];
    if ($mode === 'request') hx_notify($peer, 'message_request', "$who would like to message you. You can accept or decline.", 'messages.php?box=requests');
    elseif (hx_is_staff($peer) && (int)$pdo->query("SELECT COUNT(*) FROM dm_messages WHERE thread_id = $tid")->fetchColumn() === 1) hx_notify($peer, 'message', "$who sent you a message.", 'messages.php?t=' . $tid);
    $m = $pdo->prepare('SELECT * FROM dm_messages WHERE id = ?'); $m->execute([$id]);
    hx_out(['ok' => true, 'thread' => $tid, 'mode' => $mode, 'message' => dm_fmt($m->fetch(), $me), 'crisis' => $kind === 'text' && hx_crisis($body)]);
}

if ($a === 'accept' || $a === 'decline') {
    $t = dm_thread_for_member((int)($d['thread'] ?? 0), $me);
    if (!$t || $t['status'] !== 'request' || (int)$t['requested_by'] === $me) hx_out(['ok' => false, 'error' => 'No pending request.'], 404);
    if ($a === 'accept') {
        $pdo->prepare("UPDATE dm_threads SET status = 'active' WHERE id = ?")->execute([$t['id']]);
        hx_notify((int)$t['requested_by'], 'message_accept', hx_user_brief($me)['name'] . ' accepted your message request. You can chat now.', 'messages.php?t=' . $t['id']);
    } else {
        $pdo->prepare("UPDATE dm_threads SET status = 'declined', declined_at = NOW() WHERE id = ?")->execute([$t['id']]);
    }
    hx_out(['ok' => true]);
}

if ($a === 'block' || $a === 'unblock') {
    $u = (int)($d['user'] ?? 0);
    if ($u < 1 || $u === $me) hx_out(['ok' => false, 'error' => 'Invalid user.'], 422);
    if ($a === 'block') {
        $pdo->prepare('INSERT IGNORE INTO blocked_users (user_id, blocked_id, created_at) VALUES (?,?,NOW())')->execute([$me, $u]);
        $pdo->prepare('DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)')->execute([$me, $u, $u, $me]);
        $pdo->prepare('DELETE FROM followers WHERE (user_id = ? AND follower_id = ?) OR (user_id = ? AND follower_id = ?)')->execute([$me, $u, $u, $me]);
    } else {
        $pdo->prepare('DELETE FROM blocked_users WHERE user_id = ? AND blocked_id = ?')->execute([$me, $u]);
    }
    hx_out(['ok' => true]);
}

if ($a === 'delete') {
    $pdo->prepare('UPDATE dm_messages SET deleted_at = NOW() WHERE id = ? AND sender_id = ? AND deleted_at IS NULL')->execute([(int)($d['id'] ?? 0), $me]);
    hx_out(['ok' => true]);
}

if ($a === 'report') {
    if (!hx_rate($me, 'dm_report', 20, 3600)) hx_out(['ok' => false, 'error' => 'Too many reports in a short time.'], 429);
    $mid = (int)($d['message'] ?? 0);
    $tid = (int)($d['thread'] ?? 0);
    $reasons = ['bullying', 'harassment', 'threat', 'sexual', 'spam', 'self_harm', 'other'];
    $reason = in_array($d['reason'] ?? '', $reasons, true) ? $d['reason'] : 'other';
    $details = hx_clean((string)($d['details'] ?? ''), 500);
    $evidence = '';
    if ($mid) {
        $s = $pdo->prepare('SELECT m.*, t.user_a, t.user_b FROM dm_messages m JOIN dm_threads t ON t.id = m.thread_id WHERE m.id = ? AND (t.user_a = ? OR t.user_b = ?)');
        $s->execute([$mid, $me, $me]);
        $m = $s->fetch();
        if (!$m || (int)$m['sender_id'] === $me) hx_out(['ok' => false, 'error' => 'Message not found.'], 404);
        $offender = (int)$m['sender_id']; $tid = (int)$m['thread_id'];
        $evidence = '[Reported DM #' . $mid . ' · thread ' . $tid . ' · sender #' . $offender . '] ' . ($m['kind'] === 'text' ? mb_substr(hx_dec($m['body']), 0, 600) : '(' . $m['kind'] . ')');
    } else {
        $t = dm_thread_for_member($tid, $me);
        if (!$t) hx_out(['ok' => false, 'error' => 'Conversation not found.'], 404);
        $offender = dm_peer($t, $me);
        $s = $pdo->prepare('SELECT * FROM dm_messages WHERE thread_id = ? AND sender_id = ? AND is_hidden = 0 AND deleted_at IS NULL ORDER BY id DESC LIMIT 3');
        $s->execute([$tid, $offender]);
        $evidence = '[Reported DM thread ' . $tid . ' · user #' . $offender . '] ' . implode(' | ', array_map(fn($x) => $x['kind'] === 'text' ? mb_substr(hx_dec($x['body']), 0, 200) : '(' . $x['kind'] . ')', array_reverse($s->fetchAll())));
        $mid = 0;
    }
    $prio = in_array($reason, ['threat', 'self_harm', 'sexual'], true) ? 'high' : 'medium';
    $pdo->prepare("INSERT INTO reports (reporter_id, target_type, target_id, post_id, reason, description, priority, status, created_at) VALUES (?, 'message', ?, NULL, ?, ?, ?, 'pending', NOW())")
        ->execute([$me, $mid ?: $offender, $reason, trim($evidence . ($details ? "\nReporter note: $details" : '')), $prio]);
    if ($mid) $pdo->prepare('UPDATE dm_messages SET is_hidden = 1 WHERE id = ? AND ? = 1')->execute([$mid, $prio === 'high' ? 1 : 0]);   // serious → hidden pending review

    // Escalation: several different people reporting the same member → automatic pause + moderator alert.
    $c = $pdo->prepare("SELECT COUNT(DISTINCT reporter_id) FROM reports WHERE target_type = 'message' AND status = 'pending' AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) AND description LIKE ?");
    $c->execute(['%sender #' . $offender . ']%']);
    $c2 = $pdo->prepare("SELECT COUNT(DISTINCT reporter_id) FROM reports WHERE target_type = 'message' AND status = 'pending' AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) AND description LIKE ?");
    $c2->execute(['%user #' . $offender . ']%']);
    $distinct = max((int)$c->fetchColumn(), (int)$c2->fetchColumn());
    if ($distinct >= 3) $pdo->prepare('INSERT INTO dm_user_state (user_id, strikes, muted_until, updated_at) VALUES (?, 3, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW()) ON DUPLICATE KEY UPDATE muted_until = DATE_ADD(NOW(), INTERVAL 24 HOUR), updated_at = NOW()')->execute([$offender]);
    if ($prio === 'high' || $distinct >= 3) {
        foreach ($pdo->query("SELECT id FROM users WHERE role IN ('moderator','admin') AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN) as $sid)
            hx_notify((int)$sid, 'urgent_report', '⚠️ Urgent message report (' . $reason . ') — please review.', 'safety_reports.php');
    }
    hx_out(['ok' => true, 'message' => 'Thank you. Our moderators will review this. You can also block this person.']);
}

hx_out(['ok' => false, 'error' => 'Unknown action.'], 400);
