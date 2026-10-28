<?php
/** Stories (24h): photo or text, optional Google Maps location, audience, views, report. */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
$pdo = hx_pdo();
$BG = ['g1', 'g2', 'g3', 'g4', 'g5', 'g6'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $me = hx_need_login();
    $a = (string)($_GET['a'] ?? 'tray');
    // Visible = not expired, not hidden, not blocked, and (everyone OR I'm a friend of the author OR it's mine)
    $vis = "s.expires_at > NOW() AND s.is_hidden = 0
            AND NOT EXISTS (SELECT 1 FROM blocked_users b WHERE (b.user_id = s.user_id AND b.blocked_id = :me1) OR (b.user_id = :me2 AND b.blocked_id = s.user_id))
            AND (s.user_id = :me3 OR s.audience = 'everyone' OR EXISTS (SELECT 1 FROM friends f WHERE f.status = 'accepted' AND ((f.user_id = s.user_id AND f.friend_id = :me4) OR (f.user_id = :me5 AND f.friend_id = s.user_id))))";
    $p = [':me1' => $me, ':me2' => $me, ':me3' => $me, ':me4' => $me, ':me5' => $me];
    if ($a === 'tray') {
        $s = $pdo->prepare("SELECT s.id, s.user_id, s.content, s.media_url, s.bg, s.lat, s.lng, s.place_name, s.audience, s.created_at,
                                   EXISTS(SELECT 1 FROM story_views v WHERE v.story_id = s.id AND v.user_id = :me6) AS seen,
                                   (SELECT COUNT(*) FROM story_views v2 WHERE v2.story_id = s.id) AS views,
                                   (EXISTS(SELECT 1 FROM friends f2 WHERE f2.status='accepted' AND ((f2.user_id=s.user_id AND f2.friend_id=:me7) OR (f2.user_id=:me8 AND f2.friend_id=s.user_id)))
                                    OR EXISTS(SELECT 1 FROM followers fo WHERE fo.user_id = s.user_id AND fo.follower_id = :me9)) AS close_tie
                            FROM stories s WHERE $vis ORDER BY s.created_at ASC LIMIT 200");
        $s->execute($p + [':me6' => $me, ':me7' => $me, ':me8' => $me, ':me9' => $me]);
        $groups = [];
        foreach ($s->fetchAll() as $r) {
            $u = (int)$r['user_id'];
            if (!isset($groups[$u])) $groups[$u] = ['user' => hx_user_brief($u), 'mine' => $u === $me, 'close' => (bool)$r['close_tie'], 'all_seen' => true, 'items' => []];
            if (!$r['seen']) $groups[$u]['all_seen'] = false;
            $groups[$u]['items'][] = ['id' => (int)$r['id'], 'text' => $r['content'], 'img' => $r['media_url'], 'bg' => $r['bg'] ?: 'g1',
                'lat' => $r['lat'] !== null ? (float)$r['lat'] : null, 'lng' => $r['lng'] !== null ? (float)$r['lng'] : null, 'place' => $r['place_name'],
                'at' => date('c', strtotime($r['created_at'])), 'seen' => (bool)$r['seen'], 'views' => $u === $me ? (int)$r['views'] : null, 'audience' => $r['audience']];
        }
        $list = array_values($groups);
        usort($list, function ($x, $y) {
            if ($x['mine'] !== $y['mine']) return $x['mine'] ? -1 : 1;
            if ($x['all_seen'] !== $y['all_seen']) return $x['all_seen'] ? 1 : -1;
            if ($x['close'] !== $y['close']) return $x['close'] ? -1 : 1;
            return 0;
        });
        hx_out(['ok' => true, 'groups' => $list]);
    }
    if ($a === 'viewers') {                                    // only the owner
        $sid = (int)($_GET['id'] ?? 0);
        $o = $pdo->prepare('SELECT 1 FROM stories WHERE id = ? AND user_id = ?'); $o->execute([$sid, $me]);
        if (!$o->fetchColumn()) hx_out(['ok' => false, 'error' => 'Not yours.'], 403);
        $v = $pdo->prepare('SELECT user_id FROM story_views WHERE story_id = ? ORDER BY viewed_at DESC LIMIT 50'); $v->execute([$sid]);
        hx_out(['ok' => true, 'viewers' => array_map('hx_user_brief', $v->fetchAll(PDO::FETCH_COLUMN))]);
    }
    hx_out(['ok' => false, 'error' => 'Unknown action.'], 400);
}

$me = hx_post_guard(); $d = hx_input(); $a = (string)($d['a'] ?? '');
if ($a === 'create') {
    if (!hx_rate($me, 'story', 15, 86400)) hx_out(['ok' => false, 'error' => 'You have reached today’s story limit.'], 429);
    $text = hx_clean((string)($d['text'] ?? ''), 280);
    $aud = ($d['audience'] ?? '') === 'friends' ? 'friends' : 'everyone';
    $bg = in_array($d['bg'] ?? '', $BG, true) ? $d['bg'] : 'g1';
    $media = null;
    if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $r = hx_save_image($_FILES['image'], 'public:stories', 1440);
        if (!$r['ok']) hx_out(['ok' => false, 'error' => $r['error']], 422);
        $media = $r['path'];
    }
    if ($text === '' && !$media) hx_out(['ok' => false, 'error' => 'Add a photo or write something.'], 422);
    if ($text !== '' && ($why = hx_toxic($text))) { if ($media) @unlink(hx_root() . '/' . $media); hx_out(['ok' => false, 'error' => "That text looks like $why. Please rephrase kindly."], 422); }
    [$lat, $lng] = hx_coords($d['lat'] ?? null, $d['lng'] ?? null, !empty($d['blur']));
    $place = $lat !== null || !empty($d['place']) ? hx_clean((string)($d['place'] ?? ''), 200) : '';
    $pdo->prepare("INSERT INTO stories (user_id, content, story_type, created_at, expires_at, media_url, media_type, bg, lat, lng, place_name, audience)
                   VALUES (?, ?, 'prompt', NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR), ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$me, $text, $media, $media ? 'image' : 'none', $bg, $lat, $lng, $place ?: null, $aud]);
    hx_out(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
}
if ($a === 'view') {
    $sid = (int)($d['id'] ?? 0);
    $pdo->prepare('INSERT IGNORE INTO story_views (story_id, user_id, viewed_at) SELECT id, ?, NOW() FROM stories WHERE id = ? AND user_id <> ? AND expires_at > NOW() AND is_hidden = 0')->execute([$me, $sid, $me]);
    hx_out(['ok' => true]);
}
if ($a === 'delete') {
    $sid = (int)($d['id'] ?? 0);
    $s = $pdo->prepare('SELECT media_url FROM stories WHERE id = ? AND user_id = ?'); $s->execute([$sid, $me]);
    if ($row = $s->fetch()) {
        $pdo->prepare('DELETE FROM stories WHERE id = ?')->execute([$sid]);
        if ($row['media_url'] && strncmp($row['media_url'], 'uploads/stories/', 16) === 0) @unlink(hx_root() . '/' . $row['media_url']);
    }
    hx_out(['ok' => true]);
}
if ($a === 'report') {
    if (!hx_rate($me, 'story_report', 20, 3600)) hx_out(['ok' => false, 'error' => 'Too many reports.'], 429);
    $sid = (int)($d['id'] ?? 0);
    $reason = in_array($d['reason'] ?? '', ['bullying', 'harassment', 'sexual', 'self_harm', 'spam', 'other'], true) ? $d['reason'] : 'other';
    $s = $pdo->prepare('SELECT user_id, content FROM stories WHERE id = ?'); $s->execute([$sid]);
    if (!($r = $s->fetch()) || (int)$r['user_id'] === $me) hx_out(['ok' => false, 'error' => 'Story not found.'], 404);
    $pdo->prepare("INSERT INTO reports (reporter_id, target_type, target_id, post_id, reason, description, priority, status, created_at) VALUES (?, 'story', ?, NULL, ?, ?, ?, 'pending', NOW())")
        ->execute([$me, $sid, $reason, '[Story #' . $sid . ' by user #' . $r['user_id'] . '] ' . mb_substr($r['content'], 0, 280), in_array($reason, ['sexual', 'self_harm'], true) ? 'high' : 'medium']);
    $n = $pdo->prepare("SELECT COUNT(DISTINCT reporter_id) FROM reports WHERE target_type='story' AND target_id=? AND status='pending'"); $n->execute([$sid]);
    if ((int)$n->fetchColumn() >= 3) {      // community auto-protection: hide until a moderator looks
        $pdo->prepare('UPDATE stories SET is_hidden = 1 WHERE id = ?')->execute([$sid]);
        foreach ($pdo->query("SELECT id FROM users WHERE role IN ('moderator','admin') AND is_active=1")->fetchAll(PDO::FETCH_COLUMN) as $m)
            hx_notify((int)$m, 'urgent_report', '⚠️ A story was auto-hidden after 3 reports — please review.', 'safety_reports.php');
    }
    hx_out(['ok' => true, 'message' => 'Thanks — a moderator will take a look.']);
}
hx_out(['ok' => false, 'error' => 'Unknown action.'], 400);
