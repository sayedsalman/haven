<?php
// Common response helper is intentionally duplicated in API files so each endpoint
// remains independently deployable on cPanel.
function haven_json(array $data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function haven_user_id(): int {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    return (int)($_SESSION['user_id'] ?? 0);
}
function haven_input(): array {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    return is_array($json) ? $json : $_POST;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$uid = haven_user_id();
if (!$uid) haven_json(['success' => false, 'error' => 'Login required.'], 401);

$d = haven_input();
$action = $d['action'] ?? '';
$targetId = (int)($d['user_id'] ?? $d['target_id'] ?? 0);

if (!$targetId || $targetId === $uid) {
    haven_json(['success' => false, 'error' => 'Invalid user.'], 422);
}

$pdo = db()->getConnection();

// Refuse if either side has blocked the other.
$blocked = $pdo->prepare("SELECT id FROM blocked_users WHERE (user_id = :a1 AND blocked_id = :b1) OR (user_id = :b2 AND blocked_id = :a2)");
$blocked->execute([':a1' => $uid, ':b1' => $targetId, ':b2' => $uid, ':a2' => $targetId]);
if ($blocked->fetch()) {
    haven_json(['success' => false, 'error' => 'This action is not available for this user.'], 403);
}

if ($action === 'send') {
    // Already friends or already pending in either direction?
    $chk = $pdo->prepare("SELECT id, user_id, status FROM friends WHERE (user_id = :a1 AND friend_id = :b1) OR (user_id = :b2 AND friend_id = :a2)");
    $chk->execute([':a1' => $uid, ':b1' => $targetId, ':b2' => $uid, ':a2' => $targetId]);
    $existing = $chk->fetch();

    if ($existing) {
        if ($existing['status'] === 'accepted') {
            haven_json(['success' => false, 'error' => 'You are already friends.'], 409);
        }
        if ($existing['status'] === 'pending' && (int)$existing['user_id'] === $targetId) {
            // They already sent you a request — accept it instead of duplicating.
            $pdo->prepare("UPDATE friends SET status = 'accepted', updated_at = NOW() WHERE id = ?")->execute([$existing['id']]);
            createNotification($targetId, 'friend_accept', getAnonymousName($uid, $pdo) . ' accepted your friend request.', 'profile.php?id=' . $uid, $pdo);
            haven_json(['success' => true, 'status' => 'accepted']);
        }
        haven_json(['success' => false, 'error' => 'A friend request is already pending.'], 409);
    }

    $ins = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status, created_at, updated_at) VALUES (?, ?, 'pending', NOW(), NOW())");
    $ins->execute([$uid, $targetId]);
    createNotification($targetId, 'friend_request', getAnonymousName($uid, $pdo) . ' sent you a friend request.', 'profile.php?id=' . $uid, $pdo);
    haven_json(['success' => true, 'status' => 'pending']);
}

if ($action === 'accept') {
    $stmt = $pdo->prepare("UPDATE friends SET status = 'accepted', updated_at = NOW() WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
    $stmt->execute([$targetId, $uid]);
    if ($stmt->rowCount() > 0) {
        createNotification($targetId, 'friend_accept', getAnonymousName($uid, $pdo) . ' accepted your friend request.', 'profile.php?id=' . $uid, $pdo);
        haven_json(['success' => true, 'status' => 'accepted']);
    }
    haven_json(['success' => false, 'error' => 'No pending request found.'], 404);
}

if ($action === 'reject' || $action === 'cancel') {
    $stmt = $pdo->prepare("DELETE FROM friends WHERE ((user_id = :a1 AND friend_id = :b1) OR (user_id = :b2 AND friend_id = :a2)) AND status = 'pending'");
    $stmt->execute([':a1' => $uid, ':b1' => $targetId, ':b2' => $uid, ':a2' => $targetId]);
    haven_json(['success' => true, 'status' => 'removed']);
}

if ($action === 'unfriend') {
    $stmt = $pdo->prepare("DELETE FROM friends WHERE (user_id = :a1 AND friend_id = :b1) OR (user_id = :b2 AND friend_id = :a2)");
    $stmt->execute([':a1' => $uid, ':b1' => $targetId, ':b2' => $uid, ':a2' => $targetId]);
    haven_json(['success' => true, 'status' => 'removed']);
}

haven_json(['success' => false, 'error' => 'Unknown action.'], 400);
