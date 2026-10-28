<?php
/**
 * Interactive star rating (#7) for a post or for the whole site.
 *   GET  api/rating.php?type=post&id=12   (type=site&id=0 for the site)
 *   POST api/rating.php {type, id, stars}  stars = 1..5 (re-rating updates your old vote)
 */
require_once __DIR__ . '/_lib.php';
$pdo = hv_pdo();
$d = hv_input();
$type = ($d['type'] ?? 'post') === 'site' ? 'site' : 'post';
$id   = $type === 'site' ? 0 : (int)($d['id'] ?? 0);
if ($type === 'post' && $id < 1) hv_json(['success' => false, 'message' => 'id required'], 422);

function hv_rating_summary(PDO $pdo, string $type, int $id, int $uid): array {
    $s = $pdo->prepare("SELECT COUNT(*) n, COALESCE(AVG(stars),0) a FROM ratings WHERE target_type = ? AND target_id = ?");
    $s->execute([$type, $id]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    $mine = 0;
    if ($uid) {
        $m = $pdo->prepare("SELECT stars FROM ratings WHERE user_id = ? AND target_type = ? AND target_id = ?");
        $m->execute([$uid, $type, $id]);
        $mine = (int)$m->fetchColumn();
    }
    return ['average' => round((float)$r['a'], 2), 'count' => (int)$r['n'], 'my_rating' => $mine];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    hv_json(['success' => true] + hv_rating_summary($pdo, $type, $id, hv_uid()));
}

$uid = hv_need_login();
$stars = (int)($d['stars'] ?? 0);
if ($stars < 1 || $stars > 5) hv_json(['success' => false, 'message' => 'stars must be 1 to 5'], 422);
if ($type === 'post') {
    $e = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND status <> 'deleted'");
    $e->execute([$id]);
    if (!$e->fetch()) hv_json(['success' => false, 'message' => 'Post not found'], 404);
}
$pdo->prepare("INSERT INTO ratings (user_id, target_type, target_id, stars) VALUES (?,?,?,?)
               ON DUPLICATE KEY UPDATE stars = VALUES(stars)")->execute([$uid, $type, $id, $stars]);
hv_json(['success' => true] + hv_rating_summary($pdo, $type, $id, $uid));
