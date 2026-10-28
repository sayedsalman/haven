<?php
/**
 * Instagram-style "instant post": one photo + caption (+ optional Google Maps location).
 * Goes through the same moderation queue (AnalyzePostJob / MindShield) as every other post.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
$me = hx_post_guard(); $pdo = hx_pdo(); $d = hx_input();
if (!hx_rate($me, 'instant_post', 20, 3600)) hx_out(['ok' => false, 'error' => 'You are posting very quickly — take a breath and try again soon.'], 429);
$caption = hx_clean((string)($_POST['caption'] ?? ''), 2000);
if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) hx_out(['ok' => false, 'error' => 'Choose or take a photo first.'], 422);
if ($caption !== '' && ($why = hx_toxic($caption))) hx_out(['ok' => false, 'error' => "Your caption looks like $why. Please rephrase kindly."], 422);
$r = hx_save_image($_FILES['image'], 'public:posts', 1600);
if (!$r['ok']) hx_out(['ok' => false, 'error' => $r['error']], 422);
$anon = (($_POST['identity'] ?? 'anonymous') === 'public') ? 0 : 1;
[$lat, $lng] = hx_coords($_POST['lat'] ?? null, $_POST['lng'] ?? null, !empty($_POST['blur']));
$place = hx_clean((string)($_POST['place'] ?? ''), 200);
$content = $caption !== '' ? $caption : '📷';
try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO posts (user_id, category, mood, title, content, is_anonymous, location, tags, status, created_at, updated_at, risk_score, comment_count, reaction_count, media_url, media_type, share_count, lat, lng, place_name)
                   VALUES (?, 'General', NULL, NULL, ?, ?, ?, NULL, 'pending_ai', NOW(), NOW(), 0, 0, 0, ?, 'photo', 0, ?, ?, ?)")
        ->execute([$me, $content, $anon, $place ?: null, $r['path'], $lat, $lng, $place ?: null]);
    $pid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO jobs (job_class, payload, available_at, attempts, failed, created_at) VALUES ('AnalyzePostJob', ?, NOW(), 0, 0, NOW())")
        ->execute([json_encode(['post_id' => $pid, 'user_id' => $me, 'content' => $content], JSON_UNESCAPED_UNICODE)]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    @unlink(hx_root() . '/' . $r['path']);
    error_log('[Haven instant] ' . $e->getMessage());
    hx_out(['ok' => false, 'error' => 'Could not post right now.'], 500);
}
hx_out(['ok' => true, 'post_id' => $pid, 'message' => 'Posted! Our safety check is looking at it now.']);
