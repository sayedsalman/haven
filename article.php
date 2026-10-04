<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
include 'includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

$user_id = isLoggedIn() ? $_SESSION['user_id'] : 0;

// ------------------------------------------------------------
// AJAX: discussion comments + bookmark toggle (handled before the
// view-count increment below, so replying/bookmarking never double-counts a view)
// ------------------------------------------------------------
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$user_id) { echo json_encode(['success' => false, 'error' => 'Please log in first.']); exit; }

    if ($_POST['action'] === 'comment') {
        $text = trim($_POST['comment'] ?? '');
        if ($text === '') { echo json_encode(['success' => false, 'error' => 'Write something first.']); exit; }
        $stmt = $pdo->prepare("INSERT INTO article_comments (article_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$id, $user_id, $text]);
        $u = $pdo->prepare("SELECT anonymous_name, avatar_color, avatar_icon, avatar_type FROM users WHERE id = ?");
        $u->execute([$user_id]);
        $urow = $u->fetch();
        echo json_encode(['success' => true, 'comment' => [
            'id' => $pdo->lastInsertId(), 'text' => $text, 'name' => $urow['anonymous_name'] ?: 'Member',
            'avatar_color' => $urow['avatar_color'] ?: '#5e7564', 'avatar_icon' => $urow['avatar_icon'] ?: '',
            'avatar_type' => $urow['avatar_type'] ?: 'color', 'created_at' => date('c'),
        ]]);
        exit;
    }

    if ($_POST['action'] === 'toggle_bookmark') {
        $chk = $pdo->prepare("SELECT id FROM article_bookmarks WHERE article_id = ? AND user_id = ?");
        $chk->execute([$id, $user_id]);
        if ($row = $chk->fetch()) {
            $pdo->prepare("DELETE FROM article_bookmarks WHERE id = ?")->execute([$row['id']]);
            $pdo->prepare("UPDATE articles SET bookmarks_count = GREATEST(0, bookmarks_count - 1) WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'bookmarked' => false]);
        } else {
            $pdo->prepare("INSERT INTO article_bookmarks (article_id, user_id, created_at) VALUES (?, ?, NOW())")->execute([$id, $user_id]);
            $pdo->prepare("UPDATE articles SET bookmarks_count = bookmarks_count + 1 WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'bookmarked' => true]);
        }
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ? AND is_published = 1");
$stmt->execute([$id]);
$article = $stmt->fetch();
if (!$article) {
    header('Location: articles.php');
    exit;
}

// Increment views
$pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$id]);

$isBookmarked = false;
if ($user_id) {
    $chk = $pdo->prepare("SELECT id FROM article_bookmarks WHERE article_id = ? AND user_id = ?");
    $chk->execute([$id, $user_id]);
    $isBookmarked = (bool)$chk->fetch();
}

$comments = $pdo->prepare("SELECT ac.*, u.anonymous_name, u.avatar_color, u.avatar_icon, u.avatar_type
                            FROM article_comments ac JOIN users u ON ac.user_id = u.id
                            WHERE ac.article_id = ? ORDER BY ac.created_at ASC");
$comments->execute([$id]);
$comments = $comments->fetchAll();

// Related articles (same category)
$related = $pdo->prepare("SELECT id, title, reading_time, image_url FROM articles WHERE category = ? AND id != ? AND is_published = 1 ORDER BY created_at DESC LIMIT 3");
$related->execute([$article['category'], $id]);
$related = $related->fetchAll();

// Turn plain-text content into readable paragraphs / simple headings.
// Lines starting with "## " become subheadings; blank-line-separated
// blocks become paragraphs.
function render_article_body(string $raw): string {
    $blocks = preg_split('/\n\s*\n/', trim($raw));
    $html = '';
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') continue;
        if (str_starts_with($block, '## ')) {
            $html .= '<h3>' . escape(substr($block, 3)) . '</h3>';
        } else {
            $html .= '<p>' . nl2br(escape($block)) . '</p>';
        }
    }
    return $html;
}

$pageTitle = $article['title'];
include 'includes/header.php';
?>
<style>
.art-wrap { max-width: 720px; margin: 0 auto; }
.art-cat { display: inline-block; background: var(--sage-soft); color: var(--sage-dark); font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: 4px 12px; border-radius: 999px; margin-bottom: 12px; }
.art-title { font-family: 'Playfair Display', serif; font-size: 2.1rem; line-height: 1.25; color: var(--ink); margin-bottom: 10px; }
.art-meta { color: var(--muted); font-size: .85rem; margin-bottom: 20px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.art-hero { width: 100%; border-radius: 22px; margin-bottom: 24px; max-height: 380px; object-fit: cover; }
.art-body { font-size: 1.05rem; line-height: 1.85; color: var(--ink); }
.art-body h3 { font-family: 'Playfair Display', serif; font-size: 1.35rem; margin: 30px 0 12px; }
.art-body p { margin-bottom: 18px; }
.art-actions { display: flex; gap: 10px; margin: 26px 0; }
.bookmark-btn { border: 1px solid var(--line); background: rgba(255,255,255,.6); border-radius: 14px; padding: 8px 16px; font-weight: 600; font-size: .85rem; cursor: pointer; }
.bookmark-btn.active { background: var(--sage-dark); color: #fff; border-color: var(--sage-dark); }
.related-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-top: 14px; }
.related-card { background: var(--card); border-radius: 16px; padding: 14px; font-size: .85rem; font-weight: 600; text-decoration: none; color: var(--ink); display: block; }
.discussion-item { display: flex; gap: 12px; margin-bottom: 16px; }
.discussion-item .dc-body { flex: 1; }
.discussion-item .dc-name { font-weight: 700; font-size: .85rem; }
.discussion-item .dc-time { color: var(--muted); font-size: .72rem; margin-left: 6px; }
.discussion-item .dc-text { font-size: .88rem; margin-top: 2px; }
</style>

<div class="art-wrap">
    <a href="articles.php" class="text-decoration-none small text-muted">&larr; Back to Resources</a>

    <div class="mt-3">
        <span class="art-cat"><?= escape($article['category'] ?: 'Wellness') ?></span>
        <h1 class="art-title"><?= escape($article['title']) ?></h1>
        <div class="art-meta">
            <span><i class="bi bi-person-circle"></i> <?= escape($article['author'] ?: 'Haven Team') ?></span>
            <span><i class="bi bi-calendar3"></i> <?= date('F j, Y', strtotime($article['published_at'] ?: $article['created_at'])) ?></span>
            <span><i class="bi bi-clock"></i> <?= (int)($article['reading_time'] ?: 5) ?> min read</span>
            <span><i class="bi bi-eye"></i> <?= (int)$article['views'] ?> views</span>
        </div>
    </div>

    <?php if (!empty($article['image_url'])): ?>
        <img src="<?= escape($article['image_url']) ?>" alt="" class="art-hero">
    <?php endif; ?>

    <?php if (!empty($article['video_url'])): ?>
        <div class="mb-4"><video src="<?= escape($article['video_url']) ?>" controls style="width:100%;border-radius:18px;"></video></div>
    <?php endif; ?>

    <div class="art-body"><?= render_article_body($article['content']) ?></div>

    <div class="art-actions">
        <?php if ($user_id): ?>
            <button class="bookmark-btn <?= $isBookmarked ? 'active' : '' ?>" id="bookmarkBtn" data-on="<?= $isBookmarked ? '1' : '0' ?>">
                <i class="bi bi-bookmark<?= $isBookmarked ? '-fill' : '' ?>"></i> <?= $isBookmarked ? 'Saved' : 'Save for later' ?>
            </button>
        <?php else: ?>
            <a href="login.php" class="bookmark-btn"><i class="bi bi-bookmark"></i> Log in to save</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($related)): ?>
    <hr class="my-4">
    <h6>More on <?= escape($article['category']) ?></h6>
    <div class="related-grid">
        <?php foreach ($related as $r): ?>
            <a href="article.php?id=<?= $r['id'] ?>" class="related-card"><?= escape($r['title']) ?><br><span class="text-muted fw-normal"><?= (int)$r['reading_time'] ?> min</span></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <hr class="my-4">

    <h5 class="mb-3"><i class="bi bi-chat-square-text"></i> Discussion (<span id="discussionCount"><?= count($comments) ?></span>)</h5>

    <?php if ($user_id): ?>
    <form id="discussionForm" class="mb-4">
        <textarea id="discussionInput" class="form-control mb-2" rows="3" placeholder="What did you think? Share your thoughts or experience…" required></textarea>
        <button type="submit" class="btn btn-primary btn-sm">Post comment</button>
    </form>
    <?php else: ?>
        <p class="text-muted small mb-4"><a href="login.php">Log in</a> to join the discussion.</p>
    <?php endif; ?>

    <div id="discussionList">
        <?php foreach ($comments as $c):
            $useIcon = ($c['avatar_type'] ?? '') === 'icon' && !empty($c['avatar_icon']);
        ?>
            <div class="discussion-item">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:12px;background:<?= escape($c['avatar_color'] ?: '#5e7564') ?>;color:#fff;font-weight:700;flex-shrink:0;<?= $useIcon ? 'font-size:16px;' : '' ?>">
                    <?= $useIcon ? escape($c['avatar_icon']) : mb_substr(escape($c['anonymous_name'] ?: 'M'), 0, 1) ?>
                </span>
                <div class="dc-body">
                    <span class="dc-name"><?= escape($c['anonymous_name'] ?: 'Member') ?></span><span class="dc-time"><?= timeAgo($c['created_at']) ?></span>
                    <div class="dc-text"><?= nl2br(escape($c['comment'])) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($comments)): ?>
            <p class="text-muted small" id="noCommentsMsg">No comments yet — be the first to share your thoughts.</p>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('bookmarkBtn')?.addEventListener('click', async function() {
    const fd = new FormData(); fd.append('action', 'toggle_bookmark');
    const r = await fetch('article.php?id=<?= $id ?>', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.success) {
        this.classList.toggle('active', d.bookmarked);
        this.innerHTML = d.bookmarked ? '<i class="bi bi-bookmark-fill"></i> Saved' : '<i class="bi bi-bookmark"></i> Save for later';
    }
});

document.getElementById('discussionForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const input = document.getElementById('discussionInput');
    const text = input.value.trim();
    if (!text) return;
    const fd = new FormData(); fd.append('action', 'comment'); fd.append('comment', text);
    const r = await fetch('article.php?id=<?= $id ?>', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.success) {
        document.getElementById('noCommentsMsg')?.remove();
        const useIcon = d.comment.avatar_type === 'icon' && d.comment.avatar_icon;
        const avatarInner = useIcon ? d.comment.avatar_icon : (d.comment.name || 'M').charAt(0).toUpperCase();
        const div = document.createElement('div');
        div.className = 'discussion-item';
        div.innerHTML = `<span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:12px;background:${d.comment.avatar_color};color:#fff;font-weight:700;flex-shrink:0;${useIcon ? 'font-size:16px;' : ''}">${avatarInner}</span>
            <div class="dc-body"><span class="dc-name">${d.comment.name}</span><span class="dc-time">just now</span>
            <div class="dc-text"></div></div>`;
        div.querySelector('.dc-text').textContent = text;
        document.getElementById('discussionList').appendChild(div);
        document.getElementById('discussionCount').textContent = parseInt(document.getElementById('discussionCount').textContent) + 1;
        input.value = '';
    } else {
        alert(d.error || 'Could not post comment.');
    }
});
</script>
<?php include 'includes/footer.php'; ?>
