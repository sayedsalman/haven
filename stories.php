<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// Self-healing: Stories are intentionally lightweight (text + optional
// prompt type) and expire after 24 hours, kept separate from posts so
// they never appear in the main feed or count toward post totals.
$pdo->exec("CREATE TABLE IF NOT EXISTS stories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content VARCHAR(280) NOT NULL,
    story_type ENUM('motivational','wellness_tip','prompt','question') DEFAULT 'prompt',
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS story_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    story_id INT NOT NULL,
    user_id INT NOT NULL,
    viewed_at DATETIME NOT NULL,
    UNIQUE KEY uniq_view (story_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_POST['action'] === 'create') {
        $content = trim($_POST['content'] ?? '');
        $type = $_POST['type'] ?? 'prompt';
        if (!in_array($type, ['motivational','wellness_tip','prompt','question'], true)) $type = 'prompt';
        if ($content === '') { echo json_encode(['ok' => false, 'error' => 'Please write something.']); exit; }
        if (mb_strlen($content) > 280) { echo json_encode(['ok' => false, 'error' => 'Keep it under 280 characters.']); exit; }
        $pdo->prepare("INSERT INTO stories (user_id, content, story_type, created_at, expires_at) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))")
            ->execute([$user_id, $content, $type]);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($_POST['action'] === 'view') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("INSERT IGNORE INTO story_views (story_id, user_id, viewed_at) VALUES (?, ?, NOW())")->execute([$id, $user_id]);
        echo json_encode(['ok' => true]);
        exit;
    }
    echo json_encode(['ok' => false], 400);
    exit;
}

$stories = $pdo->prepare("SELECT s.*, u.anonymous_name, u.avatar_color, u.avatar_icon, u.avatar_type,
                           EXISTS(SELECT 1 FROM story_views v WHERE v.story_id = s.id AND v.user_id = ?) AS viewed
                           FROM stories s JOIN users u ON s.user_id = u.id
                           WHERE s.expires_at > NOW() ORDER BY s.created_at DESC LIMIT 30");
$stories->execute([$user_id]);
$stories = $stories->fetchAll();

$typeLabels = ['motivational' => '✨ Motivation', 'wellness_tip' => '🌿 Wellness Tip', 'prompt' => '💭 Prompt', 'question' => '❓ Question'];

$pageTitle = 'Stories';
include 'includes/header.php';
?>
<style>
.story-strip { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 20px; }
.story-circle { flex-shrink: 0; width: 68px; text-align: center; cursor: pointer; }
.story-circle .ring { width: 60px; height: 60px; border-radius: 50%; padding: 3px; background: linear-gradient(135deg, #e8b99f, #879d8b); }
.story-circle.viewed .ring { background: var(--line); }
.story-circle .inner { width: 100%; height: 100%; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; }
.story-circle .name { font-size: .65rem; margin-top: 4px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.story-viewer { position: fixed; inset: 0; background: rgba(38,51,43,.85); display: none; align-items: center; justify-content: center; z-index: 3000; }
.story-viewer.open { display: flex; }
.story-viewer-card { background: linear-gradient(160deg, var(--cream), var(--cream-2)); border-radius: 24px; padding: 40px 30px; max-width: 380px; width: 90%; text-align: center; position: relative; }
.story-viewer-card .type-tag { font-size: .7rem; font-weight: 700; color: var(--sage-dark); text-transform: uppercase; margin-bottom: 14px; }
.story-viewer-card .content { font-family: 'Playfair Display', serif; font-size: 1.3rem; line-height: 1.5; color: var(--ink); }
.story-viewer-card .author { margin-top: 20px; color: var(--muted); font-size: .8rem; }
.story-viewer-close { position: absolute; top: 12px; right: 16px; font-size: 1.3rem; background: none; border: none; color: var(--muted); }
</style>

<div style="max-width:720px;margin:0 auto;">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h3 class="mb-0"><i class="bi bi-stars"></i> Haven Stories</h3>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newStoryModal">+ Share a Story</button>
    </div>
    <p class="text-muted mb-3">Short, disappearing reflections — motivational thoughts, wellness tips, or a question for the community. Stories vanish after 24 hours.</p>

    <div class="story-strip" id="storyStrip">
        <?php if (empty($stories)): ?>
            <p class="text-muted">No stories right now. Be the first to share one.</p>
        <?php endif; ?>
        <?php foreach ($stories as $s):
            $useIcon = ($s['avatar_type'] ?? '') === 'icon' && !empty($s['avatar_icon']);
        ?>
            <div class="story-circle <?= $s['viewed'] ? 'viewed' : '' ?>" data-id="<?= $s['id'] ?>"
                 data-content="<?= escape($s['content']) ?>" data-type="<?= escape($typeLabels[$s['story_type']] ?? '') ?>" data-author="<?= escape($s['anonymous_name'] ?: 'Member') ?>">
                <div class="ring"><div class="inner" style="background:<?= escape($s['avatar_color'] ?: '#5e7564') ?>;<?= $useIcon ? 'font-size:22px;' : '' ?>">
                    <?= $useIcon ? escape($s['avatar_icon']) : mb_substr(escape($s['anonymous_name'] ?: 'H'), 0, 1) ?>
                </div></div>
                <div class="name"><?= escape($s['anonymous_name'] ?: 'Member') ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="story-viewer" id="storyViewer">
    <div class="story-viewer-card">
        <button class="story-viewer-close" onclick="document.getElementById('storyViewer').classList.remove('open')">✕</button>
        <div class="type-tag" id="svType"></div>
        <div class="content" id="svContent"></div>
        <div class="author" id="svAuthor"></div>
    </div>
</div>

<div class="modal fade" id="newStoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="newStoryForm">
            <div class="modal-header"><h5 class="modal-title">Share a Story</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <select name="type" class="form-select mb-2">
                    <?php foreach ($typeLabels as $val => $label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
                </select>
                <textarea name="content" class="form-control" rows="3" maxlength="280" placeholder="Share a thought, tip, or question..." required></textarea>
                <small class="text-muted">Visible for 24 hours only.</small>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary w-100">Post Story</button></div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.story-circle').forEach(c => {
    c.addEventListener('click', async () => {
        document.getElementById('svType').textContent = c.dataset.type;
        document.getElementById('svContent').textContent = c.dataset.content;
        document.getElementById('svAuthor').textContent = '— ' + c.dataset.author;
        document.getElementById('storyViewer').classList.add('open');
        c.classList.add('viewed');
        const fd = new FormData(); fd.append('action', 'view'); fd.append('id', c.dataset.id);
        try { await fetch('stories.php', { method: 'POST', body: fd }); } catch (e) {}
    });
});
document.getElementById('newStoryForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target); fd.append('action', 'create');
    const r = await fetch('stories.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) location.reload(); else alert(d.error || 'Could not post story.');
});
</script>
<?php include 'includes/footer.php'; ?>
