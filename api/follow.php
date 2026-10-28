<?php
/** Follow / unfollow (one-way). Friends stay in api/friend.php. */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $me = hx_need_login(); $t = (int)($_GET['user'] ?? 0); $pdo = hx_pdo();
    $f = $pdo->prepare('SELECT 1 FROM followers WHERE user_id = ? AND follower_id = ?'); $f->execute([$t, $me]);
    $c = $pdo->prepare('SELECT COUNT(*) FROM followers WHERE user_id = ?'); $c->execute([$t]);
    hx_out(['ok' => true, 'following' => (bool)$f->fetchColumn(), 'followers' => (int)$c->fetchColumn()]);
}
$me = hx_post_guard(); $d = hx_input(); $pdo = hx_pdo();
$t = (int)($d['user'] ?? 0);
if ($t < 1 || $t === $me) hx_out(['ok' => false, 'error' => 'Invalid user.'], 422);
$b = hx_user_brief($t);
if (!$b['active'] || $b['role'] === 'ai' || hx_blocked($me, $t)) hx_out(['ok' => false, 'error' => 'Not available.'], 403);
if (!hx_rate($me, 'follow', 60, 3600)) hx_out(['ok' => false, 'error' => 'Slow down a little.'], 429);
$f = $pdo->prepare('SELECT id FROM followers WHERE user_id = ? AND follower_id = ?'); $f->execute([$t, $me]);
if ($f->fetchColumn()) { $pdo->prepare('DELETE FROM followers WHERE user_id = ? AND follower_id = ?')->execute([$t, $me]); $on = false; }
else { $pdo->prepare('INSERT IGNORE INTO followers (user_id, follower_id) VALUES (?,?)')->execute([$t, $me]); $on = true;
       hx_notify($t, 'follow', hx_user_brief($me)['name'] . ' started following you.', 'user.php?id=' . $me); }
$c = $pdo->prepare('SELECT COUNT(*) FROM followers WHERE user_id = ?'); $c->execute([$t]);
hx_out(['ok' => true, 'following' => $on, 'followers' => (int)$c->fetchColumn()]);
