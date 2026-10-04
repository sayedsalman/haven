<?php
/**
 * ============================================================================
 *  post.php — The Haven Post View Page
 *  A real-time social interaction hub for a single post.
 *  Requires: includes/config.php to define a PDO connection in $pdo
 *            (charset utf8mb4, PDO::ERRMODE_EXCEPTION) and start the session.
 * ============================================================================
 */

require_once __DIR__ . '/includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Dhaka');

/* ---------------------------------------------------------------------------
 *  If config.php exposes a mysqli $conn instead of PDO $pdo, bail out loudly
 *  so it's obvious what needs adjusting rather than failing silently.
 * ------------------------------------------------------------------------ */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('post.php expects includes/config.php to provide a PDO connection as $pdo.');
}

/* ---------------------------------------------------------------------------
 *  Auth guard — the whole Haven experience is for logged-in members only.
 * ------------------------------------------------------------------------ */
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$currentRole   = $_SESSION['role'] ?? 'user';
$isStaff       = in_array($currentRole, ['moderator', 'admin'], true);

/* ---------------------------------------------------------------------------
 *  CSRF token
 * ------------------------------------------------------------------------ */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function csrf_ok(): bool
{
    $sent = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $sent);
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------------------------------------------------------------------
 *  Small helpers
 * ------------------------------------------------------------------------ */
function esc(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function avatar_html(?string $name, ?string $avatarType, ?string $avatarIcon, ?string $avatarColor, int $size = 40): string
{
    $color = (preg_match('/^#[0-9a-f]{6}$/i', (string) $avatarColor)) ? $avatarColor : '#5e7564';
    $initial = mb_strtoupper(mb_substr((string) ($name ?: 'M'), 0, 1));
    $content = ($avatarType === 'icon' && !empty($avatarIcon)) ? $avatarIcon : $initial;
    $fontSize = ($avatarType === 'icon' && !empty($avatarIcon)) ? round($size * 0.55) : round($size * 0.42);
    return '<span style="display:inline-flex;align-items:center;justify-content:center;width:' . $size . 'px;height:' . $size . 'px;'
        . 'border-radius:' . round($size * 0.32) . 'px;background:' . esc($color) . ';color:#fff;font-weight:700;'
        . 'font-size:' . $fontSize . 'px;flex-shrink:0;line-height:1;overflow:hidden;">' . esc($content) . '</span>';
}

function time_ago(string $datetime): string
{
    $now  = new DateTime('now', new DateTimeZone('Asia/Dhaka'));
    $then = new DateTime($datetime, new DateTimeZone('Asia/Dhaka'));
    $diff = $now->getTimestamp() - $then->getTimestamp();

    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return $then->format('d M Y, g:i A');
}

function ai_reply_text(?string $raw): array
{
    // ai_reply may be stored as plain text or as a (sometimes malformed) JSON blob.
    if ($raw === null || $raw === '') {
        return ['reply' => '', 'emotion' => null];
    }
    $trimmed = trim($raw);
    if ($trimmed !== '' && $trimmed[0] === '{') {
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded) && !empty($decoded['reply'])) {
            return ['reply' => $decoded['reply'], 'emotion' => $decoded['emotion'] ?? null];
        }
        // Malformed / truncated JSON — strip the wrapper heuristically.
        if (preg_match('/"reply"\s*:\s*"(.+)/s', $trimmed, $m)) {
            $text = preg_replace('/"\s*,?\s*"emotion.*$/s', '', $m[1]);
            $text = stripslashes(rtrim($text, "\" \n\r\t"));
            return ['reply' => $text, 'emotion' => null];
        }
    }
    return ['reply' => $raw, 'emotion' => null];
}

function is_blocked_pair(PDO $pdo, int $a, int $b): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM blocked_users
         WHERE (user_id = :a1 AND blocked_id = :b1) OR (user_id = :b2 AND blocked_id = :a2)
         LIMIT 1'
    );
    $stmt->execute([':a1' => $a, ':b1' => $b, ':b2' => $a, ':a2' => $b]);
    return (bool) $stmt->fetchColumn();
}

function notify(PDO $pdo, int $userId, ?string $title, string $type, string $message, ?string $link): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, title, type, message, link, is_read, created_at)
         VALUES (:user_id, :title, :type, :message, :link, 0, NOW())'
    );
    $stmt->execute([
        ':user_id' => $userId, ':title' => $title, ':type' => $type,
        ':message' => $message, ':link' => $link,
    ]);
}

const REACTIONS = [
    'like'     => ['emoji' => '❤️', 'label' => 'Care'],
    'support'  => ['emoji' => '🤗', 'label' => 'Support'],
    'empathy'  => ['emoji' => '💙', 'label' => 'Feel you'],
    'helpful'  => ['emoji' => '👍', 'label' => 'Helpful'],
];

$postId = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);

/* =============================================================================
 *  AJAX ACTION DISPATCH — everything below runs before any HTML is emitted.
 * ========================================================================== */
$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action !== null) {

    /* ---- get_comments : poll for new comments (and updated reaction counts) --- */
    if ($action === 'get_comments') {
        $pid    = (int) ($_GET['post_id'] ?? $_POST['post_id'] ?? 0);
        $sinceId = (int) ($_GET['since_id'] ?? $_POST['since_id'] ?? 0);

        $stmt = $pdo->prepare(
            'SELECT c.*, u.anonymous_name, u.avatar_color, u.avatar_icon, u.avatar_type, u.role
             FROM comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.post_id = :pid AND c.id > :sid
             ORDER BY c.id ASC'
        );
        $stmt->execute([':pid' => $pid, ':sid' => $sinceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $blockedIds = [];
        $bStmt = $pdo->prepare('SELECT blocked_id FROM blocked_users WHERE user_id = :u1
                                 UNION SELECT user_id FROM blocked_users WHERE blocked_id = :u2');
        $bStmt->execute([':u1' => $currentUserId, ':u2' => $currentUserId]);
        $blockedIds = array_map('intval', $bStmt->fetchAll(PDO::FETCH_COLUMN));

        $out = [];
        foreach ($rows as $r) {
            if (in_array((int) $r['user_id'], $blockedIds, true)) continue;
            $out[] = render_comment_array($pdo, $r, $currentUserId);
        }
        json_out(['success' => true, 'comments' => $out]);
    }

    /* ---- typing : send a typing signal --------------------------------- */
    if ($action === 'typing') {
        if (!csrf_ok()) json_out(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        $pid = (int) ($_POST['post_id'] ?? 0);
        $stmt = $pdo->prepare(
            'INSERT INTO typing_status (user_id, post_id, `timestamp`) VALUES (:u, :p, NOW())
             ON DUPLICATE KEY UPDATE `timestamp` = NOW()'
        );
        try {
            $stmt->execute([':u' => $currentUserId, ':p' => $pid]);
        } catch (PDOException $e) {
            // no unique key on (user_id, post_id) in this schema — fall back to delete+insert
            $pdo->prepare('DELETE FROM typing_status WHERE user_id = :u AND post_id = :p')
                ->execute([':u' => $currentUserId, ':p' => $pid]);
            $pdo->prepare('INSERT INTO typing_status (user_id, post_id, `timestamp`) VALUES (:u, :p, NOW())')
                ->execute([':u' => $currentUserId, ':p' => $pid]);
        }
        json_out(['success' => true]);
    }

    /* ---- get_typing : who's typing right now (5s TTL) -------------------- */
    if ($action === 'get_typing') {
        $pid = (int) ($_GET['post_id'] ?? 0);
        $stmt = $pdo->prepare(
            'SELECT u.anonymous_name FROM typing_status t
             JOIN users u ON u.id = t.user_id
             WHERE t.post_id = :p AND t.user_id != :me
               AND t.timestamp >= (NOW() - INTERVAL 5 SECOND)'
        );
        $stmt->execute([':p' => $pid, ':me' => $currentUserId]);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        json_out(['success' => true, 'typing' => $names]);
    }

    /* ---- add_comment : new comment or reply ------------------------------ */
    if ($action === 'add_comment') {
        if (!csrf_ok()) json_out(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        $pid       = (int) ($_POST['post_id'] ?? 0);
        $content   = trim($_POST['content'] ?? '');
        $parentId  = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;

        if ($content === '') json_out(['success' => false, 'error' => 'Comment cannot be empty'], 422);
        if (mb_strlen($content) > 2000) json_out(['success' => false, 'error' => 'Comment is too long'], 422);

        $postStmt = $pdo->prepare('SELECT id, user_id, title, status FROM posts WHERE id = :id');
        $postStmt->execute([':id' => $pid]);
        $post = $postStmt->fetch(PDO::FETCH_ASSOC);
        if (!$post || $post['status'] === 'deleted') {
            json_out(['success' => false, 'error' => 'Post not found'], 404);
        }
        if (is_blocked_pair($pdo, $currentUserId, (int) $post['user_id'])) {
            json_out(['success' => false, 'error' => 'Unable to comment on this post'], 403);
        }

        $ins = $pdo->prepare(
            'INSERT INTO comments (post_id, user_id, parent_id, content, is_anonymous, created_at)
             VALUES (:pid, :uid, :parent, :content, 1, NOW())'
        );
        $ins->execute([
            ':pid' => $pid, ':uid' => $currentUserId, ':parent' => $parentId, ':content' => $content,
        ]);
        $newId = (int) $pdo->lastInsertId();

        $pdo->prepare('UPDATE posts SET comment_count = comment_count + 1 WHERE id = :id')
            ->execute([':id' => $pid]);

        // Clear the typing flag for this user on this post.
        $pdo->prepare('DELETE FROM typing_status WHERE user_id = :u AND post_id = :p')
            ->execute([':u' => $currentUserId, ':p' => $pid]);

        // Notify the post owner (unless they're commenting on their own post).
        if ((int) $post['user_id'] !== $currentUserId) {
            notify(
                $pdo, (int) $post['user_id'], null, 'new_comment',
                'Someone replied to your post: "' . $post['title'] . '"',
                'post.php?id=' . $pid
            );
        }

        // If this is a reply to another comment, also notify that
        // comment's author specifically (distinct from the post owner).
        if ($parentId) {
            $parentStmt = $pdo->prepare('SELECT user_id FROM comments WHERE id = :id');
            $parentStmt->execute([':id' => $parentId]);
            $parentAuthorId = (int) $parentStmt->fetchColumn();
            if ($parentAuthorId && $parentAuthorId !== $currentUserId && $parentAuthorId !== (int) $post['user_id']) {
                notify(
                    $pdo, $parentAuthorId, null, 'comment_reply',
                    'Someone replied to your comment.',
                    'post.php?id=' . $pid
                );
            }
        }

        $row = $pdo->prepare(
            'SELECT c.*, u.anonymous_name, u.avatar_color, u.avatar_icon, u.avatar_type, u.role
             FROM comments c JOIN users u ON u.id = c.user_id WHERE c.id = :id'
        );
        $row->execute([':id' => $newId]);
        $comment = $row->fetch(PDO::FETCH_ASSOC);

        json_out(['success' => true, 'comment' => render_comment_array($pdo, $comment, $currentUserId)]);
    }

    /* ---- delete_comment : owner or staff --------------------------------- */
    if ($action === 'delete_comment') {
        if (!csrf_ok()) json_out(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        $cid = (int) ($_POST['comment_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT * FROM comments WHERE id = :id');
        $stmt->execute([':id' => $cid]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$comment) json_out(['success' => false, 'error' => 'Comment not found'], 404);

        if ((int) $comment['user_id'] !== $currentUserId && !$isStaff) {
            json_out(['success' => false, 'error' => 'Not allowed'], 403);
        }

        $pdo->prepare('DELETE FROM comments WHERE id = :id1 OR parent_id = :id2')
            ->execute([':id1' => $cid, ':id2' => $cid]);

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE post_id = :pid');
        $countStmt->execute([':pid' => $comment['post_id']]);
        $newCount = (int) $countStmt->fetchColumn();
        $pdo->prepare('UPDATE posts SET comment_count = :c WHERE id = :pid')
            ->execute([':c' => $newCount, ':pid' => $comment['post_id']]);

        json_out(['success' => true]);
    }

    /* ---- react : toggle a reaction on the post ---------------------------- */
    if ($action === 'react') {
        if (!csrf_ok()) json_out(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        $pid  = (int) ($_POST['post_id'] ?? 0);
        $type = $_POST['reaction_type'] ?? '';
        if (!isset(REACTIONS[$type])) json_out(['success' => false, 'error' => 'Invalid reaction'], 422);

        $existing = $pdo->prepare('SELECT * FROM reactions WHERE post_id = :p AND user_id = :u');
        $existing->execute([':p' => $pid, ':u' => $currentUserId]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['reaction_type'] === $type) {
            // toggle off
            $pdo->prepare('DELETE FROM reactions WHERE id = :id')->execute([':id' => $row['id']]);
            $active = null;
        } elseif ($row) {
            // switch type
            $pdo->prepare('UPDATE reactions SET reaction_type = :t, emoji = :e, created_at = NOW() WHERE id = :id')
                ->execute([':t' => $type, ':e' => REACTIONS[$type]['emoji'], ':id' => $row['id']]);
            $active = $type;
        } else {
            $pdo->prepare(
                'INSERT INTO reactions (post_id, user_id, reaction_type, emoji, created_at)
                 VALUES (:p, :u, :t, :e, NOW())'
            )->execute([':p' => $pid, ':u' => $currentUserId, ':t' => $type, ':e' => REACTIONS[$type]['emoji']]);
            $active = $type;
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM reactions WHERE post_id = :p');
        $countStmt->execute([':p' => $pid]);
        $total = (int) $countStmt->fetchColumn();
        $pdo->prepare('UPDATE posts SET reaction_count = :c WHERE id = :p')->execute([':c' => $total, ':p' => $pid]);

        $breakdownStmt = $pdo->prepare(
            'SELECT reaction_type, COUNT(*) c FROM reactions WHERE post_id = :p GROUP BY reaction_type'
        );
        $breakdownStmt->execute([':p' => $pid]);
        $breakdown = array_fill_keys(array_keys(REACTIONS), 0);
        foreach ($breakdownStmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $breakdown[$b['reaction_type']] = (int) $b['c'];
        }

        json_out([
            'success' => true, 'active' => $active, 'total' => $total, 'breakdown' => $breakdown,
            'energy' => min(100, (int) round($total / 20 * 100)),
        ]);
    }

    /* ---- comment_react : toggle ❤️ on a comment --------------------------- */
    if ($action === 'comment_react') {
        if (!csrf_ok()) json_out(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        $cid = (int) ($_POST['comment_id'] ?? 0);

        $existing = $pdo->prepare('SELECT * FROM comment_reactions WHERE comment_id = :c AND user_id = :u');
        $existing->execute([':c' => $cid, ':u' => $currentUserId]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $pdo->prepare('DELETE FROM comment_reactions WHERE id = :id')->execute([':id' => $row['id']]);
            $active = false;
        } else {
            $pdo->prepare(
                'INSERT INTO comment_reactions (comment_id, user_id, emoji, created_at) VALUES (:c, :u, :e, NOW())'
            )->execute([':c' => $cid, ':u' => $currentUserId, ':e' => '❤️']);
            $active = true;
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM comment_reactions WHERE comment_id = :c');
        $countStmt->execute([':c' => $cid]);
        json_out(['success' => true, 'active' => $active, 'count' => (int) $countStmt->fetchColumn()]);
    }

    /* ---- block_user : toggle block/unblock -------------------------------- */
    if ($action === 'block_user') {
        if (!csrf_ok()) json_out(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($targetId === $currentUserId || $targetId <= 0) {
            json_out(['success' => false, 'error' => 'Invalid user'], 422);
        }

        $existing = $pdo->prepare('SELECT id FROM blocked_users WHERE user_id = :u AND blocked_id = :b');
        $existing->execute([':u' => $currentUserId, ':b' => $targetId]);
        if ($existing->fetchColumn()) {
            $pdo->prepare('DELETE FROM blocked_users WHERE user_id = :u AND blocked_id = :b')
                ->execute([':u' => $currentUserId, ':b' => $targetId]);
            json_out(['success' => true, 'blocked' => false]);
        }
        $pdo->prepare('INSERT INTO blocked_users (user_id, blocked_id, created_at) VALUES (:u, :b, NOW())')
            ->execute([':u' => $currentUserId, ':b' => $targetId]);
        json_out(['success' => true, 'blocked' => true]);
    }

    /* ---- hide_post : soft delete, owner only ------------------------------ */
    if ($action === 'hide_post') {
        if (!csrf_ok()) json_out(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        $pid = (int) ($_POST['post_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT user_id FROM posts WHERE id = :id');
        $stmt->execute([':id' => $pid]);
        $ownerId = $stmt->fetchColumn();
        if ($ownerId === false) json_out(['success' => false, 'error' => 'Post not found'], 404);
        if ((int) $ownerId !== $currentUserId) json_out(['success' => false, 'error' => 'Not allowed'], 403);

        $pdo->prepare("UPDATE posts SET status = 'deleted' WHERE id = :id")->execute([':id' => $pid]);
        json_out(['success' => true]);
    }

    /* ---- report : submit a report + notify moderators ---------------------- */
    if ($action === 'report') {
        if (!csrf_ok()) json_out(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        $pid    = (int) ($_POST['post_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        if ($reason === '') json_out(['success' => false, 'error' => 'Please choose a reason'], 422);

        $stmt = $pdo->prepare('SELECT id FROM posts WHERE id = :id');
        $stmt->execute([':id' => $pid]);
        if (!$stmt->fetchColumn()) json_out(['success' => false, 'error' => 'Post not found'], 404);

        $pdo->prepare(
            'INSERT INTO reports (reporter_id, post_id, reason, description, status, created_at, priority, target_id, target_type)
             VALUES (:r, :p, :reason, :desc, "pending", NOW(), "medium", :tid, "post")'
        )->execute([':r' => $currentUserId, ':p' => $pid, ':reason' => $reason, ':desc' => $desc, ':tid' => $pid]);

        // Notify moderators / admins — triggers a MindShield review.
        $staff = $pdo->query("SELECT id FROM users WHERE role IN ('moderator','admin') AND is_active = 1")
                      ->fetchAll(PDO::FETCH_COLUMN);
        foreach ($staff as $staffId) {
            notify($pdo, (int) $staffId, null, 'report', "Post #{$pid} has been reported.", 'moderator.php?view=reports');
        }

        json_out(['success' => true]);
    }

    json_out(['success' => false, 'error' => 'Unknown action'], 400);
}

/* ---------------------------------------------------------------------------
 *  Comment renderer (shared by the AJAX endpoints above and the page render
 *  below) — returns a plain array ready to be JSON-encoded or looped over.
 * ------------------------------------------------------------------------ */
function render_comment_array(PDO $pdo, array $c, int $viewerId): array
{
    $rc = $pdo->prepare('SELECT COUNT(*) FROM comment_reactions WHERE comment_id = :id');
    $rc->execute([':id' => $c['id']]);

    $mine = $pdo->prepare('SELECT 1 FROM comment_reactions WHERE comment_id = :id AND user_id = :u');
    $mine->execute([':id' => $c['id'], ':u' => $viewerId]);

    return [
        'id'            => (int) $c['id'],
        'post_id'       => (int) $c['post_id'],
        'user_id'       => (int) $c['user_id'],
        'parent_id'     => $c['parent_id'] !== null ? (int) $c['parent_id'] : null,
        'content'       => $c['content'],
        'name'          => $c['anonymous_name'] ?: 'Member',
        'avatar_color'  => $c['avatar_color'] ?: '#5e7564',
        'avatar_icon'   => $c['avatar_icon'] ?: '',
        'avatar_type'   => $c['avatar_type'] ?? 'color',
        'role'          => $c['role'],
        'created_at'    => $c['created_at'],
        'time_ago'      => time_ago($c['created_at']),
        'reaction_count'=> (int) $rc->fetchColumn(),
        'reacted'       => (bool) $mine->fetchColumn(),
        'can_delete'    => (int) $c['user_id'] === $viewerId,
    ];
}

/* =============================================================================
 *  PAGE RENDER — load everything the page needs.
 * ========================================================================== */
if ($postId <= 0) {
    http_response_code(404);
    die('Post not found.');
}

$stmt = $pdo->prepare(
    'SELECT p.*, u.id AS author_id, u.anonymous_name, u.avatar_color, u.avatar_icon, u.avatar_type, u.role AS author_role
     FROM posts p JOIN users u ON u.id = p.user_id
     WHERE p.id = :id'
);
$stmt->execute([':id' => $postId]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post || ($post['status'] === 'deleted' && (int) $post['author_id'] !== $currentUserId && !$isStaff)) {
    http_response_code(404);
    die('This post is unavailable.');
}

$isOwner = (int) $post['author_id'] === $currentUserId;

if (!$isOwner && !$isStaff && is_blocked_pair($pdo, $currentUserId, (int) $post['author_id'])) {
    http_response_code(403);
    die('This post is unavailable.');
}

$aiStmt = $pdo->prepare('SELECT * FROM ai_analysis WHERE post_id = :id ORDER BY id DESC LIMIT 1');
$aiStmt->execute([':id' => $postId]);
$ai = $aiStmt->fetch(PDO::FETCH_ASSOC) ?: null;
$aiReply = $ai ? ai_reply_text($ai['ai_reply']) : ['reply' => '', 'emotion' => null];

$riskScore = (int) ($post['risk_score'] ?? 0);
$showCrisisBanner = $isOwner && $riskScore >= 70;

/* Reactions breakdown + current viewer's reaction */
$breakdownStmt = $pdo->prepare('SELECT reaction_type, COUNT(*) c FROM reactions WHERE post_id = :p GROUP BY reaction_type');
$breakdownStmt->execute([':p' => $postId]);
$breakdown = array_fill_keys(array_keys(REACTIONS), 0);
foreach ($breakdownStmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
    $breakdown[$b['reaction_type']] = (int) $b['c'];
}
$totalReactions = array_sum($breakdown);
$energy = min(100, (int) round($totalReactions / 20 * 100));

$myReactStmt = $pdo->prepare('SELECT reaction_type FROM reactions WHERE post_id = :p AND user_id = :u');
$myReactStmt->execute([':p' => $postId, ':u' => $currentUserId]);
$myReaction = $myReactStmt->fetchColumn() ?: null;

/* Comments — top level + one level of replies, blocked users filtered out */
$blockedStmt = $pdo->prepare(
    'SELECT blocked_id FROM blocked_users WHERE user_id = :u1
     UNION SELECT user_id FROM blocked_users WHERE blocked_id = :u2'
);
$blockedStmt->execute([':u1' => $currentUserId, ':u2' => $currentUserId]);
$blockedIds = array_map('intval', $blockedStmt->fetchAll(PDO::FETCH_COLUMN));

$cStmt = $pdo->prepare(
    'SELECT c.*, u.anonymous_name, u.avatar_color, u.avatar_icon, u.avatar_type, u.role
     FROM comments c JOIN users u ON u.id = c.user_id
     WHERE c.post_id = :p ORDER BY c.id ASC'
);
$cStmt->execute([':p' => $postId]);
$allComments = array_filter(
    $cStmt->fetchAll(PDO::FETCH_ASSOC),
    fn($c) => !in_array((int) $c['user_id'], $blockedIds, true)
);

$topLevel = [];
$repliesByParent = [];
foreach ($allComments as $c) {
    if (empty($c['parent_id'])) {
        $topLevel[] = $c;
    } else {
        $repliesByParent[$c['parent_id']][] = $c;
    }
}
$lastCommentId = 0;
foreach ($allComments as $c) {
    $lastCommentId = max($lastCommentId, (int) $c['id']);
}

$tags = array_filter(array_map('trim', explode(',', (string) ($post['tags'] ?? ''))));
$moodEmojiMap = [
    'happy' => '😊', 'sad' => '😢', 'stressed' => '😣', 'angry' => '😠',
    'anxious' => '😰', 'calm' => '😌', 'tired' => '😴', 'hopeful' => '🌱',
];
$moodEmoji = $moodEmojiMap[$post['mood'] ?? ''] ?? '🌱';

function role_badge(string $role): string
{
    $map = [
        'ai'        => ['🤖 MindGuide AI', 'badge-ai'],
        'volunteer' => ['🤝 Volunteer', 'badge-volunteer'],
        'moderator' => ['🛡️ Moderator', 'badge-mod'],
        'admin'     => ['👑 Admin', 'badge-admin'],
    ];
    if (!isset($map[$role])) return '';
    [$label, $class] = $map[$role];
    return '<span class="role-badge ' . $class . '">' . esc($label) . '</span>';
}

function render_comment_html(array $c, array $repliesByParent, int $viewerId, bool $isStaff): string
{
    $reactCount = 0; // populated client-side on first poll for freshness; server render uses 0 baseline avoided below
    $name  = esc($c['anonymous_name'] ?: 'Member');
    $color = esc($c['avatar_color'] ?: '#5e7564');
    $iconType = $c['avatar_type'] ?? 'color';
    $iconVal = $c['avatar_icon'] ?? '';
    $avatarInner = ($iconType === 'icon' && !empty($iconVal)) ? esc($iconVal) : mb_substr($name, 0, 1);
    $avatarFontStyle = ($iconType === 'icon' && !empty($iconVal)) ? ' font-size:18px;' : '';
    $canDelete = ((int) $c['user_id'] === $viewerId) || $isStaff;
    $badge = role_badge($c['role']);

    $html = '<div class="comment" data-comment-id="' . (int) $c['id'] . '">';
    $html .= '<a href="profile.php?id=' . (int) $c['user_id'] . '" class="c-avatar" style="background:' . $color . ';' . $avatarFontStyle . '"><span>' . $avatarInner . '</span></a>';
    $html .= '<div class="c-body">';
    $html .= '<div class="c-head"><a href="profile.php?id=' . (int) $c['user_id'] . '" class="c-name">' . $name . '</a>' . $badge . '<span class="c-time">' . time_ago($c['created_at']) . '</span></div>';
    $html .= '<div class="c-content">' . nl2br(esc($c['content'])) . '</div>';
    $html .= '<div class="c-actions">';
    $html .= '<button class="c-react" data-comment-id="' . (int) $c['id'] . '">❤️ <span class="c-react-count">0</span></button>';
    $html .= '<button class="c-reply-btn" data-comment-id="' . (int) $c['id'] . '">Reply</button>';
    if ($canDelete) {
        $html .= '<button class="c-delete" data-comment-id="' . (int) $c['id'] . '">Delete</button>';
    }
    $html .= '</div>';

    if (!empty($repliesByParent[$c['id']])) {
        $html .= '<div class="c-replies">';
        foreach ($repliesByParent[$c['id']] as $r) {
            $rname  = esc($r['anonymous_name'] ?: 'Member');
            $rcolor = esc($r['avatar_color'] ?: '#5e7564');
            $rIconType = $r['avatar_type'] ?? 'color';
            $rIconVal = $r['avatar_icon'] ?? '';
            $rAvatarInner = ($rIconType === 'icon' && !empty($rIconVal)) ? esc($rIconVal) : mb_substr($rname, 0, 1);
            $rAvatarFontStyle = ($rIconType === 'icon' && !empty($rIconVal)) ? ' font-size:15px;' : '';
            $rCanDelete = ((int) $r['user_id'] === $viewerId) || $isStaff;
            $rBadge = role_badge($r['role']);
            $html .= '<div class="comment reply" data-comment-id="' . (int) $r['id'] . '">';
            $html .= '<a href="profile.php?id=' . (int) $r['user_id'] . '" class="c-avatar small" style="background:' . $rcolor . ';' . $rAvatarFontStyle . '"><span>' . $rAvatarInner . '</span></a>';
            $html .= '<div class="c-body">';
            $html .= '<div class="c-head"><a href="profile.php?id=' . (int) $r['user_id'] . '" class="c-name">' . $rname . '</a>' . $rBadge . '<span class="c-time">' . time_ago($r['created_at']) . '</span></div>';
            $html .= '<div class="c-content">' . nl2br(esc($r['content'])) . '</div>';
            $html .= '<div class="c-actions">';
            $html .= '<button class="c-react" data-comment-id="' . (int) $r['id'] . '">❤️ <span class="c-react-count">0</span></button>';
            if ($rCanDelete) {
                $html .= '<button class="c-delete" data-comment-id="' . (int) $r['id'] . '">Delete</button>';
            }
            $html .= '</div></div></div>';
        }
        $html .= '</div>';
    }

    $html .= '</div></div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($post['title'] ?: 'Post') ?> – The Haven</title>
<link rel="icon" href="logo.png" type="image/png">
<script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.2/anime.min.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  :root{
    --bg-1:#f7f4ed; --bg-2:#eee9df; --glass:rgba(255,255,255,0.6); --glass-border:rgba(255,255,255,0.8);
    --ink:#26332b; --ink-soft:#7c857e; --accent:#5e7564; --accent-soft:#dfe9df;
    --danger:#c96a63; --shadow:0 8px 30px rgba(94,117,100,0.12);
    --radius:20px;
  }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; font-family:'DM Sans','Segoe UI', system-ui, -apple-system, sans-serif;
    background:linear-gradient(135deg,var(--bg-1),var(--bg-2)); color:var(--ink);
    padding-bottom:70px;
  }
  .wrap{max-width:760px;margin:0 auto;padding:24px 16px;}
  .mobile-bottom-nav{display:none;}
  @media (max-width:767px){
    .mobile-bottom-nav{display:flex;position:fixed;left:0;right:0;bottom:0;z-index:1200;height:60px;background:rgba(255,253,248,0.96);backdrop-filter:blur(16px);border-top:1px solid var(--glass-border);justify-content:space-around;align-items:center;box-shadow:0 -6px 24px rgba(94,117,100,.12);}
    .mobile-bottom-nav a{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:var(--ink-soft);font-size:.62rem;font-weight:600;text-decoration:none;padding:6px 0;}
    .mobile-bottom-nav a i{font-size:1.25rem;}
    .mobile-bottom-nav a.active{color:var(--accent);}
  }
  .glass{
    background:var(--glass); backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
    border:1px solid var(--glass-border); border-radius:var(--radius); box-shadow:var(--shadow);
  }
  .top-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
  .top-bar a{color:var(--ink-soft);text-decoration:none;font-weight:600;}
  .menu-btn{background:none;border:none;font-size:20px;cursor:pointer;color:var(--ink-soft);}

  .post-card{padding:22px 22px 14px;margin-bottom:18px;}
  .post-head{display:flex;align-items:center;gap:12px;}
  .avatar{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:18px;flex-shrink:0;box-shadow:0 4px 10px rgba(0,0,0,0.08);}
  .post-head-meta{flex:1;min-width:0;}
  .post-head-meta .name-row{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
  .post-head-meta a.name{font-weight:700;color:var(--ink);text-decoration:none;}
  .post-head-meta .sub{font-size:12.5px;color:var(--ink-soft);}
  .role-badge{font-size:11px;padding:2px 8px;border-radius:999px;font-weight:600;}
  .badge-ai{background:#e4ddff;color:#4d6555;}
  .badge-volunteer{background:#d7f5e3;color:#1e9e5c;}
  .badge-mod{background:#ffe8cf;color:#c07a1e;}
  .badge-admin{background:#ffe0e0;color:#c0392b;}

  .post-title{font-size:22px;font-weight:800;margin:14px 0 6px;color:var(--ink);}
  .post-content{font-size:15.5px;line-height:1.6;color:var(--ink);white-space:pre-wrap;}
  .post-tags{margin-top:12px;display:flex;flex-wrap:wrap;gap:6px;}
  .tag-chip{background:var(--accent-soft);color:#4d6555;font-size:12px;padding:4px 10px;border-radius:999px;font-weight:600;}
  .mood-chip{font-size:13px;background:#fff;border-radius:999px;padding:4px 10px;box-shadow:0 2px 6px rgba(0,0,0,0.05);}

  .risk-tag{font-size:11px;color:#c0392b;background:#ffe0e0;padding:3px 8px;border-radius:999px;font-weight:700;margin-left:6px;}

  .crisis-banner{
    background:linear-gradient(135deg,#ffe3e3,#fff0e0); border:1px solid #ffc2c2; border-radius:18px;
    padding:16px 18px; margin-bottom:18px; display:flex; gap:12px; align-items:flex-start;
  }
  .crisis-banner .icon{font-size:26px;}
  .crisis-banner h3{margin:0 0 4px;color:#b23838;font-size:16px;}
  .crisis-banner p{margin:0 0 10px;font-size:13.5px;color:#7a3a3a;}
  .crisis-links{display:flex;gap:8px;flex-wrap:wrap;}
  .crisis-links a{background:#fff;color:#b23838;text-decoration:none;font-size:12.5px;font-weight:700;padding:7px 12px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,0.06);}

  .ai-box{
    margin:16px 0; padding:16px 18px; border-radius:16px;
    background:linear-gradient(135deg,#eef1ff,#f5eeff); border:1px solid #dcd4ff;
  }
  .ai-box .ai-head{display:flex;align-items:center;gap:8px;font-weight:700;color:#4d6555;font-size:13.5px;margin-bottom:6px;}
  .ai-box .ai-text{font-size:14.5px;line-height:1.6;color:#26332b;white-space:pre-wrap;}
  .ai-box .ai-flag{font-size:11.5px;color:#7c857e;margin-top:8px;}

  .energy-wrap{margin:16px 0 6px;}
  .energy-label{font-size:11.5px;color:var(--ink-soft);display:flex;justify-content:space-between;margin-bottom:4px;}
  .energy-track{height:8px;border-radius:99px;background:#e2e8f8;overflow:hidden;}
  .energy-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,#5e7564,#8fd0ff);width:0%;transition:width .6s ease;}

  .reactions-bar{display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;}
  .react-btn{
    display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:14px;border:1px solid rgba(0,0,0,0.06);
    background:#fff; cursor:pointer; font-size:13.5px; font-weight:600; color:var(--ink-soft); transition:.15s;
  }
  .react-btn:hover{transform:translateY(-2px);}
  .react-btn.active{background:var(--accent);color:#fff;border-color:var(--accent);}
  .react-count{font-size:12px;opacity:.85;}

  .post-actions{display:flex;gap:10px;margin-top:14px;padding-top:12px;border-top:1px solid rgba(0,0,0,0.06);}
  .post-actions button{
    background:none;border:none;color:var(--ink-soft);font-size:13px;font-weight:600;cursor:pointer;
    display:flex;align-items:center;gap:4px;padding:6px 8px;border-radius:8px;
  }
  .post-actions button:hover{background:rgba(0,0,0,0.04);}
  .post-actions .danger{color:var(--danger);}

  .comments-card{padding:20px;}
  .comments-title{font-weight:700;font-size:15px;margin-bottom:12px;}
  .typing-indicator{font-size:12.5px;color:var(--ink-soft);font-style:italic;min-height:16px;margin-bottom:8px;}

  .comment{display:flex;gap:10px;margin-bottom:16px;}
  .comment.reply{margin-bottom:10px;}
  .c-avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;flex-shrink:0;text-decoration:none;}
  .c-avatar.small{width:28px;height:28px;font-size:12px;}
  .c-body{flex:1;min-width:0;}
  .c-head{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
  .c-name{font-weight:700;font-size:13.5px;color:var(--ink);text-decoration:none;}
  .c-time{font-size:11.5px;color:var(--ink-soft);}
  .c-content{font-size:14px;line-height:1.5;margin-top:2px;color:var(--ink);}
  .c-actions{display:flex;gap:14px;margin-top:5px;}
  .c-actions button{background:none;border:none;font-size:12px;color:var(--ink-soft);cursor:pointer;font-weight:600;padding:0;}
  .c-actions .c-react.reacted{color:var(--danger);}
  .c-actions .c-delete{color:var(--danger);}
  .c-replies{margin-top:10px;padding-left:14px;border-left:2px solid rgba(0,0,0,0.06);}

  .comment-form{display:flex;gap:10px;margin-top:16px;align-items:flex-end;}
  .comment-form textarea{
    flex:1;resize:none;border:1px solid rgba(0,0,0,0.08);border-radius:14px;padding:10px 14px;font-size:14px;
    font-family:inherit;background:#fff;min-height:42px;max-height:120px;
  }
  .comment-form button{
    background:var(--accent);color:#fff;border:none;border-radius:12px;padding:10px 18px;font-weight:700;cursor:pointer;
  }
  .reply-context{font-size:12px;color:var(--accent);margin-bottom:6px;display:none;align-items:center;gap:6px;}
  .reply-context button{background:none;border:none;color:var(--ink-soft);cursor:pointer;font-size:12px;}

  .modal-overlay{position:fixed;inset:0;background:rgba(30,40,60,0.4);display:none;align-items:center;justify-content:center;z-index:50;padding:20px;}
  .modal-overlay.open{display:flex;}
  .modal{background:#fff;border-radius:18px;padding:22px;max-width:380px;width:100%;}
  .modal h3{margin:0 0 10px;font-size:16px;}
  .modal select, .modal textarea{width:100%;border-radius:10px;border:1px solid #ddd;padding:9px;font-size:13.5px;margin-bottom:10px;font-family:inherit;}
  .modal-actions{display:flex;gap:8px;justify-content:flex-end;}
  .modal-actions button{border:none;border-radius:10px;padding:8px 16px;font-weight:700;cursor:pointer;}
  .btn-cancel{background:#f1f1f1;color:#555;}
  .btn-confirm{background:var(--danger);color:#fff;}

  .toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:10px 18px;border-radius:12px;font-size:13px;opacity:0;pointer-events:none;transition:.25s;z-index:80;}
  .toast.show{opacity:1;}

  .bottom-nav{display:none;}
  @media(max-width:640px){
    .bottom-nav{
      display:flex;position:fixed;bottom:0;left:0;right:0;background:rgba(255,255,255,0.85);backdrop-filter:blur(14px);
      justify-content:space-around;padding:10px 0;box-shadow:0 -4px 14px rgba(0,0,0,0.06);z-index:40;
    }
    .bottom-nav a{color:var(--ink-soft);text-decoration:none;font-size:11px;text-align:center;}
    .wrap{padding-bottom:20px;}
  }
</style>
</head>
<body>

<div class="wrap">
  <div class="top-bar">
    <a href="community.php">&larr; Back to Haven</a>
    <button class="menu-btn" id="postMenuBtn">⋮</button>
  </div>

  <?php if ($showCrisisBanner): ?>
  <div class="crisis-banner">
    <div class="icon">💚</div>
    <div>
      <h3>You matter, and support is here</h3>
      <p>We noticed this post may reflect a difficult moment. You don't have to go through this alone.</p>
      <div class="crisis-links">
        <a href="chatbot.php">💬 Talk to MindGuide</a>
        <a href="consultation.php?tab=new&post_id=<?= (int)$postId ?>">🤝 Reach a volunteer</a>
        <a href="emergency.php">📞 Emergency contacts</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="post-card glass" id="postCard" data-post-id="<?= (int)$postId ?>">
    <div class="post-head">
      <a href="profile.php?id=<?= (int)$post['author_id'] ?>" class="avatar" style="background:<?= esc($post['avatar_color'] ?: '#5e7564') ?>;<?= (($post['avatar_type'] ?? '') === 'icon' && !empty($post['avatar_icon'])) ? 'font-size:22px;' : '' ?>">
        <?= (($post['avatar_type'] ?? '') === 'icon' && !empty($post['avatar_icon'])) ? esc($post['avatar_icon']) : mb_substr(esc($post['anonymous_name'] ?: 'M'), 0, 1) ?>
      </a>
      <div class="post-head-meta">
        <div class="name-row">
          <a href="profile.php?id=<?= (int)$post['author_id'] ?>" class="name"><?= esc($post['anonymous_name'] ?: 'Member') ?></a>
          <?= role_badge($post['author_role']) ?>
          <?php if ($isStaff && $riskScore > 0): ?>
            <span class="risk-tag">Risk: <?= $riskScore ?></span>
          <?php endif; ?>
        </div>
        <div class="sub"><?= esc(time_ago($post['created_at'])) ?> — <span class="mood-chip"><?= $moodEmoji ?> <?= esc(ucfirst($post['mood'] ?? '')) ?></span></div>
      </div>
    </div>

    <?php if (!empty($post['title'])): ?><div class="post-title"><?= esc($post['title']) ?></div><?php endif; ?>
    <div class="post-content"><?= nl2br(esc($post['content'])) ?></div>

    <?php if (!empty($post['media_url'])): ?>
    <div class="post-media" style="margin-top:14px;border-radius:16px;overflow:hidden;">
      <?php if (($post['media_type'] ?? '') === 'image'): ?>
        <img src="<?= esc($post['media_url']) ?>" alt="" style="width:100%;max-height:520px;object-fit:cover;border-radius:16px;display:block;" loading="lazy">
      <?php elseif (($post['media_type'] ?? '') === 'video'): ?>
        <video src="<?= esc($post['media_url']) ?>" controls preload="metadata" style="width:100%;max-height:520px;border-radius:16px;background:#000;"></video>
      <?php elseif (($post['media_type'] ?? '') === 'audio'): ?>
        <audio src="<?= esc($post['media_url']) ?>" controls style="width:100%;"></audio>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($tags)): ?>
    <div class="post-tags">
      <?php foreach ($tags as $t): ?><span class="tag-chip">#<?= esc($t) ?></span><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($isOwner && $ai && !empty($aiReply['reply'])): ?>
    <div class="ai-box">
      <div class="ai-head">🤖 MindGuide reply<?= !empty($ai['emotion']) ? ' — sensed ' . esc($ai['emotion']) : '' ?></div>
      <div class="ai-text"><?= nl2br(esc($aiReply['reply'])) ?></div>
      <?php if ((int) $ai['is_volunteer_notified'] === 1): ?>
        <div class="ai-flag">🔔 A volunteer has been quietly notified and may reach out.</div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="energy-wrap">
      <div class="energy-label"><span>Community energy</span><span id="energyPct"><?= $energy ?>%</span></div>
      <div class="energy-track"><div class="energy-fill" id="energyFill" style="width:<?= $energy ?>%"></div></div>
    </div>

    <div class="reactions-bar" id="reactionsBar">
      <?php foreach (REACTIONS as $type => $r): ?>
        <button class="react-btn <?= $myReaction === $type ? 'active' : '' ?>" data-type="<?= esc($type) ?>">
          <?= $r['emoji'] ?> <?= esc($r['label']) ?>
          <span class="react-count" data-type-count="<?= esc($type) ?>"><?= $breakdown[$type] ?></span>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="post-actions">
      <?php if (!$isOwner): ?>
        <button id="blockBtn" data-user-id="<?= (int)$post['author_id'] ?>">🚫 Block</button>
        <button id="reportBtn">🚩 Report</button>
      <?php endif; ?>
      <?php if ($isOwner || $isStaff): ?>
        <button class="danger" id="deletePostBtn">🗑️ Delete</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="comments-card glass">
    <div class="comments-title">💬 Comments (<span id="commentCount"><?= count($allComments) ?></span>)</div>
    <div class="typing-indicator" id="typingIndicator"></div>

    <div id="commentsList">
      <?php foreach ($topLevel as $c): ?>
        <?= render_comment_html($c, $repliesByParent, $currentUserId, $isStaff) ?>
      <?php endforeach; ?>
    </div>

    <div class="reply-context" id="replyContext">
      Replying to a comment <button id="cancelReply">cancel</button>
    </div>
    <div class="comment-form">
      <textarea id="commentInput" placeholder="Share something kind..." rows="1"></textarea>
      <button id="submitComment">Send</button>
    </div>
  </div>
</div>

<div class="bottom-nav">
  <a href="feed.php">🏠<br>Home</a>
  <a href="chat.php">💬<br>Chat</a>
  <a href="create_post.php">➕<br>Post</a>
  <a href="notifications.php">🔔<br>Alerts</a>
  <a href="profile.php">👤<br>Me</a>
</div>

<!-- Report modal -->
<div class="modal-overlay" id="reportModal">
  <div class="modal">
    <h3>🚩 Report this post</h3>
    <select id="reportReason">
      <option value="">Choose a reason...</option>
      <option value="harassment">Harassment or bullying</option>
      <option value="self_harm">Self-harm concern</option>
      <option value="sexual_content">Sexual content</option>
      <option value="dangerous_content">Dangerous content</option>
      <option value="spam">Spam</option>
      <option value="hate_speech">Hate speech</option>
      <option value="misinformation">Misinformation</option>
      <option value="other">Other</option>
    </select>
    <textarea id="reportDescription" rows="3" placeholder="Add any details (optional)"></textarea>
    <div class="modal-actions">
      <button class="btn-cancel" id="reportCancel">Cancel</button>
      <button class="btn-confirm" id="reportConfirm">Submit report</button>
    </div>
  </div>
</div>

<!-- Delete confirmation modal -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <h3>🗑️ Delete this post?</h3>
    <p style="font-size:13.5px;color:#666;margin:0 0 14px;">This will remove the post from The Haven. This can't be undone.</p>
    <div class="modal-actions">
      <button class="btn-cancel" id="deleteCancel">Cancel</button>
      <button class="btn-confirm" id="deleteConfirm">Delete</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const CSRF_TOKEN   = <?= json_encode($csrfToken) ?>;
const POST_ID      = <?= (int)$postId ?>;
const CURRENT_UID  = <?= (int)$currentUserId ?>;
let lastCommentId  = <?= (int)$lastCommentId ?>;
let replyToId      = null;

function toast(msg){
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'), 2200);
}

async function api(action, data = {}, method = 'POST'){
  const opts = { method, headers: {} };
  if (method === 'POST') {
    const body = new URLSearchParams({ action, csrf_token: CSRF_TOKEN, ...data });
    opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
    opts.body = body;
    const res = await fetch('post.php', opts);
    return res.json();
  } else {
    const qs = new URLSearchParams({ action, ...data });
    const res = await fetch('post.php?' + qs.toString());
    return res.json();
  }
}

/* ---------- entrance animation ---------- */
if (window.anime) {
  anime({ targets: '.post-card', opacity: [0,1], translateY: [16,0], duration: 500, easing: 'easeOutQuad' });
  anime({ targets: '.comment', opacity: [0,1], translateY: [10,0], delay: anime.stagger(60), duration: 400, easing: 'easeOutQuad' });
}

/* ---------- reactions on the post ---------- */
document.querySelectorAll('.react-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const type = btn.dataset.type;
    const res = await api('react', { post_id: POST_ID, reaction_type: type });
    if (!res.success) { toast(res.error || 'Something went wrong'); return; }

    document.querySelectorAll('.react-btn').forEach(b => b.classList.remove('active'));
    if (res.active) {
      document.querySelector(`.react-btn[data-type="${res.active}"]`).classList.add('active');
      if (window.anime) anime({ targets: `.react-btn[data-type="${res.active}"]`, scale: [1,1.15,1], duration: 350 });
    }
    Object.entries(res.breakdown).forEach(([t, c]) => {
      const el = document.querySelector(`[data-type-count="${t}"]`);
      if (el) el.textContent = c;
    });
    document.getElementById('energyPct').textContent = res.energy + '%';
    document.getElementById('energyFill').style.width = res.energy + '%';
  });
});

/* ---------- comment rendering helper (client-side, mirrors server) ---------- */
function commentHtml(c){
  const initial = (c.name || 'M').charAt(0).toUpperCase();
  const useIcon = c.avatar_type === 'icon' && c.avatar_icon;
  const avatarInner = useIcon ? c.avatar_icon : initial;
  const avatarStyle = `background:${c.avatar_color};${useIcon ? 'font-size:18px;' : ''}`;
  const badgeMap = {
    ai: ['🤖 MindGuide AI','badge-ai'], volunteer: ['🤝 Volunteer','badge-volunteer'],
    moderator: ['🛡️ Moderator','badge-mod'], admin: ['👑 Admin','badge-admin']
  };
  let badge = '';
  if (badgeMap[c.role]) badge = `<span class="role-badge ${badgeMap[c.role][1]}">${badgeMap[c.role][1] && badgeMap[c.role][0]}</span>`;
  const del = c.can_delete ? `<button class="c-delete" data-comment-id="${c.id}">Delete</button>` : '';
  const replyBtn = c.parent_id ? '' : `<button class="c-reply-btn" data-comment-id="${c.id}">Reply</button>`;
  return `<div class="comment ${c.parent_id ? 'reply' : ''}" data-comment-id="${c.id}">
    <a href="profile.php?id=${c.user_id}" class="c-avatar ${c.parent_id ? 'small' : ''}" style="${avatarStyle}"><span>${avatarInner}</span></a>
    <div class="c-body">
      <div class="c-head"><a href="profile.php?id=${c.user_id}" class="c-name">${c.name}</a>${badge}<span class="c-time">${c.time_ago}</span></div>
      <div class="c-content">${c.content.replace(/</g,'&lt;').replace(/\n/g,'<br>')}</div>
      <div class="c-actions">
        <button class="c-react ${c.reacted ? 'reacted' : ''}" data-comment-id="${c.id}">❤️ <span class="c-react-count">${c.reaction_count}</span></button>
        ${replyBtn}
        ${del}
      </div>
      <div class="c-replies" id="replies-${c.id}"></div>
    </div>
  </div>`;
}

function bindCommentEvents(container){
  container.querySelectorAll('.c-react').forEach(btn => {
    btn.onclick = async () => {
      const id = btn.dataset.commentId;
      const res = await api('comment_react', { comment_id: id });
      if (!res.success) return;
      btn.classList.toggle('reacted', res.active);
      btn.querySelector('.c-react-count').textContent = res.count;
      if (window.anime) anime({ targets: btn, scale: [1,1.2,1], duration: 300 });
    };
  });
  container.querySelectorAll('.c-delete').forEach(btn => {
    btn.onclick = async () => {
      if (!confirm('Delete this comment?')) return;
      const id = btn.dataset.commentId;
      const res = await api('delete_comment', { comment_id: id });
      if (res.success) {
        document.querySelector(`.comment[data-comment-id="${id}"]`)?.remove();
        toast('Comment deleted');
      } else toast(res.error || 'Could not delete');
    };
  });
  container.querySelectorAll('.c-reply-btn').forEach(btn => {
    btn.onclick = () => {
      replyToId = btn.dataset.commentId;
      const ctx = document.getElementById('replyContext');
      ctx.style.display = 'flex';
      document.getElementById('commentInput').focus();
    };
  });
}
bindCommentEvents(document);

document.getElementById('cancelReply').addEventListener('click', () => {
  replyToId = null;
  document.getElementById('replyContext').style.display = 'none';
});

/* ---------- submit comment ---------- */
document.getElementById('submitComment').addEventListener('click', submitComment);
document.getElementById('commentInput').addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitComment(); }
});

async function submitComment(){
  const input = document.getElementById('commentInput');
  const content = input.value.trim();
  if (!content) return;

  const res = await api('add_comment', { post_id: POST_ID, content, parent_id: replyToId || '' });
  if (!res.success) { toast(res.error || 'Could not post comment'); return; }

  input.value = '';
  const wasReply = replyToId;
  replyToId = null;
  document.getElementById('replyContext').style.display = 'none';

  if (wasReply) {
    let box = document.getElementById(`replies-${wasReply}`);
    if (!box) {
      const parent = document.querySelector(`.comment[data-comment-id="${wasReply}"] .c-body`);
      box = document.createElement('div');
      box.className = 'c-replies';
      box.id = `replies-${wasReply}`;
      parent.appendChild(box);
    }
    box.insertAdjacentHTML('beforeend', commentHtml(res.comment));
  } else {
    document.getElementById('commentsList').insertAdjacentHTML('beforeend', commentHtml(res.comment));
  }
  bindCommentEvents(document.getElementById('commentsList'));
  lastCommentId = Math.max(lastCommentId, res.comment.id);
  document.getElementById('commentCount').textContent = parseInt(document.getElementById('commentCount').textContent) + 1;

  if (window.anime) {
    anime({ targets: `.comment[data-comment-id="${res.comment.id}"]`, opacity: [0,1], translateY: [10,0], duration: 400 });
  }
}

/* ---------- real-time polling: new comments every 3s ---------- */
setInterval(async () => {
  const res = await api('get_comments', { post_id: POST_ID, since_id: lastCommentId }, 'GET');
  if (!res.success || !res.comments.length) return;
  res.comments.forEach(c => {
    if (document.querySelector(`.comment[data-comment-id="${c.id}"]`)) return;
    if (c.parent_id) {
      let box = document.getElementById(`replies-${c.parent_id}`);
      if (!box) {
        const parent = document.querySelector(`.comment[data-comment-id="${c.parent_id}"] .c-body`);
        if (!parent) return;
        box = document.createElement('div');
        box.className = 'c-replies';
        box.id = `replies-${c.parent_id}`;
        parent.appendChild(box);
      }
      box.insertAdjacentHTML('beforeend', commentHtml(c));
    } else {
      document.getElementById('commentsList').insertAdjacentHTML('beforeend', commentHtml(c));
    }
    document.getElementById('commentCount').textContent = parseInt(document.getElementById('commentCount').textContent) + 1;
    lastCommentId = Math.max(lastCommentId, c.id);
  });
  bindCommentEvents(document.getElementById('commentsList'));
}, 3000);

/* ---------- typing indicator ---------- */
let typingTimer = null;
document.getElementById('commentInput').addEventListener('input', () => {
  clearTimeout(typingTimer);
  api('typing', { post_id: POST_ID });
  typingTimer = setTimeout(() => {}, 1500);
});

setInterval(async () => {
  const res = await api('get_typing', { post_id: POST_ID }, 'GET');
  const el = document.getElementById('typingIndicator');
  if (res.success && res.typing.length) {
    el.textContent = res.typing.slice(0,3).join(', ') + (res.typing.length > 1 ? ' are typing…' : ' is typing…');
  } else {
    el.textContent = '';
  }
}, 3000);

/* ---------- block user ---------- */
const blockBtn = document.getElementById('blockBtn');
if (blockBtn) {
  blockBtn.addEventListener('click', async () => {
    const res = await api('block_user', { user_id: blockBtn.dataset.userId });
    if (res.success) {
      toast(res.blocked ? 'User blocked' : 'User unblocked');
      blockBtn.textContent = res.blocked ? '✅ Unblock' : '🚫 Block';
      if (res.blocked) setTimeout(() => window.location.href = 'community.php', 900);
    } else toast(res.error || 'Something went wrong');
  });
}

/* ---------- report modal ---------- */
const reportModal = document.getElementById('reportModal');
document.getElementById('reportBtn')?.addEventListener('click', () => reportModal.classList.add('open'));
document.getElementById('reportCancel').addEventListener('click', () => reportModal.classList.remove('open'));
document.getElementById('reportConfirm').addEventListener('click', async () => {
  const reason = document.getElementById('reportReason').value;
  const description = document.getElementById('reportDescription').value.trim();
  if (!reason) { toast('Please choose a reason'); return; }
  const res = await api('report', { post_id: POST_ID, reason, description });
  reportModal.classList.remove('open');
  toast(res.success ? 'Report submitted. Thank you for keeping Haven safe.' : (res.error || 'Could not submit report'));
});

/* ---------- delete post ---------- */
const deleteModal = document.getElementById('deleteModal');
document.getElementById('deletePostBtn')?.addEventListener('click', () => deleteModal.classList.add('open'));
document.getElementById('deleteCancel').addEventListener('click', () => deleteModal.classList.remove('open'));
document.getElementById('deleteConfirm').addEventListener('click', async () => {
  const res = await api('hide_post', { post_id: POST_ID });
  if (res.success) {
    toast('Post deleted');
    setTimeout(() => window.location.href = 'community.php', 800);
  } else {
    toast(res.error || 'Could not delete post');
    deleteModal.classList.remove('open');
  }
});

/* close modals on backdrop click */
[reportModal, deleteModal].forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
</script>
<?php $__uid_logged = isLoggedIn() ? $_SESSION['user_id'] : 0; if ($__uid_logged): $__unread_p = function_exists('getUnreadNotifications') ? getUnreadNotifications($__uid_logged, $pdo) : 0; ?>
<nav class="mobile-bottom-nav">
    <a href="index.php"><i class="bi bi-house"></i>Home</a>
    <a href="community.php"><i class="bi bi-globe2"></i>Community</a>
    <a href="feed.php" class="active"><i class="bi bi-chat-dots-fill"></i>Feed</a>
    <a href="chatbot.php"><i class="bi bi-robot"></i>AI</a>
    <a href="dashboard.php" style="position:relative;">
        <?php if ($__unread_p > 0): ?><span style="position:absolute;top:2px;right:22%;background:var(--danger);color:#fff;border-radius:8px;font-size:.55rem;padding:0 4px;"><?= $__unread_p > 9 ? '9+' : $__unread_p ?></span><?php endif; ?><i class="bi bi-person"></i>Me</a>
</nav>
<?php endif; ?>
</body>
</html>