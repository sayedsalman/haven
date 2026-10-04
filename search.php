<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
include 'includes/functions.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$tab = $_GET['type'] ?? 'all';
$posts = $articles = $users = [];

if (mb_strlen($q) >= 2) {
    $like = "%$q%";

    $stmt = $pdo->prepare("SELECT p.id, p.title, p.content, p.category, u.anonymous_name, u.avatar_color, u.avatar_icon, u.avatar_type
                            FROM posts p JOIN users u ON p.user_id = u.id
                            WHERE p.status='published' AND (p.title LIKE ? OR p.content LIKE ? OR p.tags LIKE ?)
                            ORDER BY p.created_at DESC LIMIT 20");
    $stmt->execute([$like, $like, $like]);
    $posts = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT id, title, excerpt, category, reading_time FROM articles WHERE is_published=1 AND (title LIKE ? OR excerpt LIKE ? OR content LIKE ? OR category LIKE ?) ORDER BY views DESC LIMIT 15");
    $stmt->execute([$like, $like, $like, $like]);
    $articles = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT u.id, u.anonymous_name, u.avatar_color, u.avatar_icon, u.avatar_type, u.bio
                            FROM users u WHERE u.is_active=1 AND u.anonymous_name LIKE ? LIMIT 15");
    $stmt->execute([$like]);
    $users = $stmt->fetchAll();
}

$totalResults = count($posts) + count($articles) + count($users);

$pageTitle = 'Search';
include 'includes/header.php';
?>
<style>
.search-wrap { max-width: 720px; margin: 0 auto; }
.search-box { display: flex; gap: 8px; margin-bottom: 20px; }
.search-box input { flex: 1; border-radius: 16px; border: 1px solid var(--line); padding: 12px 18px; font-size: 1rem; background: rgba(255,255,255,.6); }
.search-tabs { display: flex; gap: 6px; margin-bottom: 20px; background: rgba(255,255,255,.5); padding: 6px; border-radius: 16px; width: fit-content; }
.search-tabs a { padding: 8px 18px; border-radius: 12px; font-weight: 600; font-size: .85rem; color: var(--muted); text-decoration: none; }
.search-tabs a.active { background: var(--sage-dark); color: #fff; }
.result-card { background: var(--card); border-radius: 16px; padding: 16px; margin-bottom: 10px; display: flex; gap: 12px; text-decoration: none; color: inherit; }
.result-card:hover { background: rgba(135,157,139,.1); }
.result-body h6 { margin-bottom: 4px; }
.result-avatar { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0; }
</style>

<div class="search-wrap">
    <form class="search-box" method="GET">
        <input type="hidden" name="type" value="<?= escape($tab) ?>">
        <input type="text" name="q" value="<?= escape($q) ?>" placeholder="Search Haven — people, posts, articles..." autofocus>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
    </form>

    <?php if (mb_strlen($q) >= 2): ?>
    <div class="search-tabs">
        <a href="?q=<?= urlencode($q) ?>&type=all" class="<?= $tab=='all'?'active':'' ?>">All (<?= $totalResults ?>)</a>
        <a href="?q=<?= urlencode($q) ?>&type=people" class="<?= $tab=='people'?'active':'' ?>">People (<?= count($users) ?>)</a>
        <a href="?q=<?= urlencode($q) ?>&type=posts" class="<?= $tab=='posts'?'active':'' ?>">Posts (<?= count($posts) ?>)</a>
        <a href="?q=<?= urlencode($q) ?>&type=articles" class="<?= $tab=='articles'?'active':'' ?>">Articles (<?= count($articles) ?>)</a>
    </div>

    <?php if ($totalResults === 0): ?>
        <div class="glass-card p-4 text-center text-muted">No results for "<?= escape($q) ?>". Try a different search term.</div>
    <?php endif; ?>

    <?php if (($tab=='all' || $tab=='people') && !empty($users)): ?>
        <?php if ($tab=='all'): ?><h6 class="text-muted">People</h6><?php endif; ?>
        <?php foreach ($users as $u): ?>
            <a href="profile.php?id=<?= $u['id'] ?>" class="result-card">
                <span class="result-avatar" style="background:<?= escape($u['avatar_color'] ?: '#5e7564') ?>;<?= ($u['avatar_type']==='icon' && $u['avatar_icon']) ? 'font-size:20px;' : '' ?>">
                    <?= ($u['avatar_type']==='icon' && $u['avatar_icon']) ? escape($u['avatar_icon']) : mb_substr(escape($u['anonymous_name'] ?: 'H'),0,1) ?>
                </span>
                <div class="result-body"><h6><?= escape($u['anonymous_name'] ?: 'Haven Member') ?></h6><small class="text-muted"><?= escape(mb_substr($u['bio'] ?: 'Haven community member', 0, 70)) ?></small></div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (($tab=='all' || $tab=='posts') && !empty($posts)): ?>
        <?php if ($tab=='all'): ?><h6 class="text-muted mt-3">Posts</h6><?php endif; ?>
        <?php foreach ($posts as $p): ?>
            <a href="post.php?id=<?= $p['id'] ?>" class="result-card">
                <span class="result-avatar" style="background:<?= escape($p['avatar_color'] ?: '#5e7564') ?>;<?= ($p['avatar_type']==='icon' && $p['avatar_icon']) ? 'font-size:20px;' : '' ?>">
                    <?= ($p['avatar_type']==='icon' && $p['avatar_icon']) ? escape($p['avatar_icon']) : mb_substr(escape($p['anonymous_name'] ?: 'H'),0,1) ?>
                </span>
                <div class="result-body"><h6><?= escape($p['title'] ?: 'Untitled') ?></h6><small class="text-muted"><?= escape(mb_substr($p['content'], 0, 90)) ?>…</small></div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (($tab=='all' || $tab=='articles') && !empty($articles)): ?>
        <?php if ($tab=='all'): ?><h6 class="text-muted mt-3">Articles</h6><?php endif; ?>
        <?php foreach ($articles as $a): ?>
            <a href="article.php?id=<?= $a['id'] ?>" class="result-card">
                <span class="result-avatar" style="background:var(--sage-dark);"><i class="bi bi-book"></i></span>
                <div class="result-body"><h6><?= escape($a['title']) ?></h6><small class="text-muted"><?= escape(mb_substr($a['excerpt'] ?: '', 0, 90)) ?> · <?= (int)$a['reading_time'] ?> min read</small></div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php else: ?>
        <p class="text-muted text-center">Type at least 2 characters to search across people, posts, and articles.</p>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
