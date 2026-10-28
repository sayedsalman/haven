<?php
/**
 * Facebook-style Like / Dislike with live counts (#3).
 *   GET  api/vote.php?post_id=12            -> counts + my vote
 *   POST api/vote.php {post_id, vote}       -> vote = like | dislike (same vote again = remove)
 * One reaction per user per post, so like and dislike exclude each other.
 */
require_once __DIR__ . '/_lib.php';
$pdo = hv_pdo();

function hv_vote_counts(PDO $pdo, int $post, int $uid): array {
    $s = $pdo->prepare("SELECT reaction_type, COUNT(*) c FROM reactions WHERE post_id = ? AND reaction_type IN ('like','dislike') GROUP BY reaction_type");
    $s->execute([$post]);
    $out = ['likes' => 0, 'dislikes' => 0];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['reaction_type'] . 's'] = (int)$r['c'];
    $mine = null;
    if ($uid) {
        $m = $pdo->prepare("SELECT reaction_type FROM reactions WHERE post_id = ? AND user_id = ?");
        $m->execute([$post, $uid]);
        $v = $m->fetchColumn();
        $mine = in_array($v, ['like', 'dislike'], true) ? $v : null;
    }
    return $out + ['my_vote' => $mine];
}

$d = hv_input();
$post = (int)($d['post_id'] ?? 0);
if ($post < 1) hv_json(['success' => false, 'message' => 'post_id required'], 422);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    hv_json(['success' => true] + hv_vote_counts($pdo, $post, hv_uid()));
}

$uid = hv_need_login();
$vote = (string)($d['vote'] ?? '');
if (!in_array($vote, ['like', 'dislike'], true)) hv_json(['success' => false, 'message' => 'vote must be like or dislike'], 422);

$exists = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND status <> 'deleted'");
$exists->execute([$post]);
if (!$exists->fetch()) hv_json(['success' => false, 'message' => 'Post not found'], 404);

$hasEmoji = hv_has_column($pdo, 'reactions', 'emoji');
$emoji = ['like' => '👍', 'dislike' => '👎'][$vote];

$cur = $pdo->prepare("SELECT id, reaction_type FROM reactions WHERE post_id = ? AND user_id = ?");
$cur->execute([$post, $uid]);
$row = $cur->fetch(PDO::FETCH_ASSOC);

if ($row && $row['reaction_type'] === $vote) {
    $pdo->prepare("DELETE FROM reactions WHERE id = ?")->execute([$row['id']]);
} elseif ($row) {
    if ($hasEmoji) $pdo->prepare("UPDATE reactions SET reaction_type = ?, emoji = ? WHERE id = ?")->execute([$vote, $emoji, $row['id']]);
    else           $pdo->prepare("UPDATE reactions SET reaction_type = ? WHERE id = ?")->execute([$vote, $row['id']]);
} else {
    if ($hasEmoji) $pdo->prepare("INSERT INTO reactions (post_id, user_id, reaction_type, emoji) VALUES (?,?,?,?)")->execute([$post, $uid, $vote, $emoji]);
    else           $pdo->prepare("INSERT INTO reactions (post_id, user_id, reaction_type) VALUES (?,?,?)")->execute([$post, $uid, $vote]);
}

// keep the cached total on posts in sync (only if the column exists on this install)
if (hv_has_column($pdo, 'posts', 'reaction_count')) {
    $pdo->prepare("UPDATE posts SET reaction_count = (SELECT COUNT(*) FROM reactions WHERE post_id = ?) WHERE id = ?")->execute([$post, $post]);
}
hv_json(['success' => true] + hv_vote_counts($pdo, $post, $uid));
