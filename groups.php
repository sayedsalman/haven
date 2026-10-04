<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
include 'includes/functions.php';

$user_id = isLoggedIn() ? $_SESSION['user_id'] : 0;

$pdo->exec("CREATE TABLE IF NOT EXISTS user_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    group_slug VARCHAR(50) NOT NULL,
    joined_at DATETIME NOT NULL,
    UNIQUE KEY uniq_membership (user_id, group_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$groups = [
    'students'      => ['name' => 'Students',        'icon' => '🎓', 'category' => 'Students'],
    'programming'   => ['name' => 'Programming',      'icon' => '💻', 'category' => 'Programming'],
    'ai-ml'         => ['name' => 'AI / ML',           'icon' => '🤖', 'category' => 'AI/ML'],
    'study'         => ['name' => 'Study',            'icon' => '📚', 'category' => 'Study'],
    'creativity'    => ['name' => 'Creativity',       'icon' => '🎨', 'category' => 'Creativity'],
    'fitness'       => ['name' => 'Fitness',          'icon' => '🏃', 'category' => 'Fitness'],
    'personal-growth' => ['name' => 'Personal Growth', 'icon' => '🌱', 'category' => 'Personal Growth'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $user_id) {
    header('Content-Type: application/json; charset=utf-8');
    $slug = $_POST['slug'] ?? '';
    if (!isset($groups[$slug])) { echo json_encode(['ok' => false]); exit; }
    if ($_POST['action'] === 'join') {
        $pdo->prepare("INSERT IGNORE INTO user_groups (user_id, group_slug, joined_at) VALUES (?, ?, NOW())")->execute([$user_id, $slug]);
        echo json_encode(['ok' => true, 'joined' => true]);
    } elseif ($_POST['action'] === 'leave') {
        $pdo->prepare("DELETE FROM user_groups WHERE user_id = ? AND group_slug = ?")->execute([$user_id, $slug]);
        echo json_encode(['ok' => true, 'joined' => false]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

$myGroups = [];
if ($user_id) {
    $q = $pdo->prepare("SELECT group_slug FROM user_groups WHERE user_id = ?");
    $q->execute([$user_id]);
    $myGroups = array_flip($q->fetchAll(PDO::FETCH_COLUMN));
}

foreach ($groups as $slug => &$g) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM user_groups WHERE group_slug = ?");
    $q->execute([$slug]);
    $g['members'] = (int)$q->fetchColumn();
    $q = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE category = ? AND status = 'published'");
    $q->execute([$g['category']]);
    $g['posts'] = (int)$q->fetchColumn();
}
unset($g);

$activeGroup = $_GET['g'] ?? null;
$groupPosts = [];
if ($activeGroup && isset($groups[$activeGroup])) {
    $q = $pdo->prepare("SELECT p.id, p.title, p.content, p.created_at, u.anonymous_name, u.avatar_color, u.avatar_icon, u.avatar_type
                         FROM posts p JOIN users u ON p.user_id = u.id
                         WHERE p.category = ? AND p.status = 'published' ORDER BY p.created_at DESC LIMIT 15");
    $q->execute([$groups[$activeGroup]['category']]);
    $groupPosts = $q->fetchAll();
}

$pageTitle = 'Groups';
include 'includes/header.php';
?>
<style>
.grp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px,1fr)); gap: 14px; margin-bottom: 24px; }
.grp-card { background: var(--card); border-radius: 18px; padding: 18px; text-align: center; }
.grp-card .icon { font-size: 2rem; }
.grp-card .name { font-weight: 700; margin: 6px 0 2px; }
.grp-card .meta { color: var(--muted); font-size: .78rem; margin-bottom: 10px; }
.grp-card.active-group { border: 2px solid var(--sage-dark); }
.grp-post { background: var(--card); border-radius: 16px; padding: 14px; margin-bottom: 10px; }
</style>

<div style="max-width:900px;margin:0 auto;">
    <h3 class="mb-1"><i class="bi bi-people"></i> Communities</h3>
    <p class="text-muted mb-4">Join groups around what matters to you — posts tagged with a group's category show up here.</p>

    <div class="grp-grid">
        <?php foreach ($groups as $slug => $g): $joined = isset($myGroups[$slug]); ?>
            <div class="grp-card <?= $activeGroup === $slug ? 'active-group' : '' ?>">
                <div class="icon"><?= $g['icon'] ?></div>
                <div class="name"><?= escape($g['name']) ?></div>
                <div class="meta"><?= $g['members'] ?> members · <?= $g['posts'] ?> posts</div>
                <a href="?g=<?= $slug ?>" class="btn btn-sm btn-outline-primary mb-1">View</a>
                <?php if ($user_id): ?>
                    <button class="btn btn-sm <?= $joined ? 'btn-secondary' : 'btn-primary' ?> join-btn" data-slug="<?= $slug ?>" data-joined="<?= $joined ? '1' : '0' ?>">
                        <?= $joined ? 'Joined ✓' : 'Join' ?>
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($activeGroup && isset($groups[$activeGroup])): ?>
        <h5><?= $groups[$activeGroup]['icon'] ?> <?= escape($groups[$activeGroup]['name']) ?> — recent posts</h5>
        <?php if (empty($groupPosts)): ?>
            <p class="text-muted">No posts in this group yet.</p>
        <?php endif; ?>
        <?php foreach ($groupPosts as $p): ?>
            <a href="post.php?id=<?= $p['id'] ?>" class="text-decoration-none text-dark">
            <div class="grp-post">
                <strong><?= escape($p['title'] ?: 'Untitled') ?></strong>
                <p class="mb-1 small text-muted"><?= escape(mb_substr($p['content'], 0, 120)) ?>…</p>
                <small class="text-muted">by <?= escape($p['anonymous_name'] ?: 'Member') ?> · <?= timeAgo($p['created_at']) ?></small>
            </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.join-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const joined = btn.dataset.joined === '1';
        const fd = new FormData();
        fd.append('action', joined ? 'leave' : 'join');
        fd.append('slug', btn.dataset.slug);
        const r = await fetch('groups.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) location.reload();
    });
});
</script>
<?php include 'includes/footer.php'; ?>
