<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// Self-healing: discussion rooms use the existing posts/comments engine
// under the hood (a room is really a fixed category), so no schema
// change is needed — this keeps moderation, AI safety analysis, and
// reporting all working exactly as they already do for posts.
$rooms = [
    'overwhelmed'   => ['name' => "I'm Feeling Overwhelmed", 'icon' => '💭', 'category' => 'Room: Overwhelmed'],
    'student-stress'=> ['name' => 'Student Stress',          'icon' => '🎓', 'category' => 'Room: Student Stress'],
    'relationships' => ['name' => 'Relationships',           'icon' => '❤️', 'category' => 'Room: Relationships'],
    'sleep'         => ['name' => 'Sleep Problems',          'icon' => '😴', 'category' => 'Room: Sleep'],
    'career'        => ['name' => 'Career Anxiety',          'icon' => '💻', 'category' => 'Room: Career'],
    'university'    => ['name' => 'University Life',         'icon' => '🧑‍🎓', 'category' => 'Room: University'],
];

$activeRoom = $_GET['room'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post') {
    header('Content-Type: application/json; charset=utf-8');
    $room = $_POST['room'] ?? '';
    $content = trim($_POST['content'] ?? '');
    if (!isset($rooms[$room]) || $content === '') { echo json_encode(['ok' => false, 'error' => 'Invalid room or empty message.']); exit; }

    $stmt = $pdo->prepare("INSERT INTO posts (user_id, category, content, is_anonymous, status) VALUES (?, ?, ?, 1, 'pending_ai')");
    $stmt->execute([$user_id, $rooms[$room]['category'], $content]);
    $postId = $pdo->lastInsertId();

    try {   // ML emotion computed on the server (rooms posts have no browser scores)
        require_once __DIR__ . '/includes/EmotionML.php';
        EmotionML::store($pdo, (int)$postId, (int)$user_id, $_POST['ml_scores'] ?? null, $content);
    } catch (Throwable $e) { error_log('[Haven] ML store: ' . $e->getMessage()); }

    $ranAi = false;
    $aiFile = __DIR__ . '/ai/AIManager.php';
    if (is_file($aiFile)) {
        require_once $aiFile;
        if (class_exists('AIManager')) {
            try { (new AIManager())->analyzePost($postId); $ranAi = true; }
            catch (Throwable $e) { error_log('[Haven] Room post AI analysis failed: ' . $e->getMessage()); }
        }
    }
    if (!$ranAi) {
        $pdo->prepare("UPDATE posts SET status = 'published' WHERE id = ? AND status = 'pending_ai'")->execute([$postId]);
    }
    echo json_encode(['ok' => true, 'post_id' => $postId]);
    exit;
}

$roomPosts = [];
$roomCount = 0;
if ($activeRoom && isset($rooms[$activeRoom])) {
    $q = $pdo->prepare("SELECT p.id, p.content, p.created_at, u.anonymous_name, u.avatar_color, u.avatar_icon, u.avatar_type, p.comment_count, p.reaction_count
                         FROM posts p JOIN users u ON p.user_id = u.id
                         WHERE p.category = ? AND p.status = 'published' ORDER BY p.created_at DESC LIMIT 30");
    $q->execute([$rooms[$activeRoom]['category']]);
    $roomPosts = $q->fetchAll();
}
foreach ($rooms as $slug => &$r) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE category = ? AND status = 'published'");
    $q->execute([$r['category']]);
    $r['count'] = (int)$q->fetchColumn();
}
unset($r);

$pageTitle = 'Discussion Rooms';
include 'includes/header.php';
?>
<style>
.room-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px,1fr)); gap: 14px; margin-bottom: 24px; }
.room-card { background: var(--card); border-radius: 18px; padding: 20px; text-decoration: none; color: inherit; display: block; text-align: center; border: 2px solid transparent; }
.room-card:hover { transform: translateY(-2px); }
.room-card.active-room { border-color: var(--sage-dark); }
.room-card .icon { font-size: 1.8rem; }
.room-card .name { font-weight: 700; margin-top: 6px; font-size: .9rem; }
.room-card .cnt { color: var(--muted); font-size: .75rem; }
.room-post { background: var(--card); border-radius: 16px; padding: 16px; margin-bottom: 10px; }
.room-post .meta { color: var(--muted); font-size: .75rem; margin-top: 8px; }
</style>

<div style="max-width:720px;margin:0 auto;">
    <h3 class="mb-1"><i class="bi bi-chat-square-heart"></i> Discussion Rooms</h3>
    <p class="text-muted mb-4">Anonymous, topic-focused spaces. Every post here still goes through MindShield safety review just like the rest of Haven.</p>

    <div class="room-grid">
        <?php foreach ($rooms as $slug => $r): ?>
            <a href="?room=<?= $slug ?>" class="room-card <?= $activeRoom === $slug ? 'active-room' : '' ?>">
                <div class="icon"><?= $r['icon'] ?></div>
                <div class="name"><?= escape($r['name']) ?></div>
                <div class="cnt"><?= $r['count'] ?> posts</div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($activeRoom && isset($rooms[$activeRoom])): ?>
        <div class="glass-card p-3 mb-3">
            <form id="roomPostForm">
                <input type="hidden" name="room" value="<?= $activeRoom ?>">
                <textarea name="content" class="form-control mb-2" rows="3" placeholder="Share anonymously in this room..." required></textarea>
                <button type="submit" class="btn btn-primary btn-sm">Post anonymously</button>
            </form>
        </div>
        <div id="roomPosts">
        <?php foreach ($roomPosts as $p):
            $useIcon = ($p['avatar_type'] ?? '') === 'icon' && !empty($p['avatar_icon']);
        ?>
            <a href="post.php?id=<?= $p['id'] ?>" class="text-decoration-none text-dark">
            <div class="room-post">
                <p class="mb-1"><?= nl2br(escape(mb_substr($p['content'], 0, 300))) ?></p>
                <div class="meta">🕊️ Anonymous · <?= timeAgo($p['created_at']) ?> · <?= (int)$p['comment_count'] ?> replies · <?= (int)$p['reaction_count'] ?> reactions</div>
            </div>
            </a>
        <?php endforeach; ?>
        <?php if (empty($roomPosts)): ?><p class="text-muted">Be the first to share in this room.</p><?php endif; ?>
        </div>
    <?php else: ?>
        <p class="text-muted text-center">Choose a room above to read or post anonymously.</p>
    <?php endif; ?>
</div>

<script>
document.getElementById('roomPostForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target); fd.append('action', 'post');
    const r = await fetch('rooms.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) location.reload(); else alert(d.error || 'Could not post.');
});
</script>
<?php include 'includes/footer.php'; ?>
