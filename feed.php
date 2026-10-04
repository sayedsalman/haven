<?php
// ============================================================
// feed.php – Flagship Living Feed
// ============================================================
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
include 'includes/functions.php';

$user_id = isLoggedIn() ? $_SESSION['user_id'] : 0;
$anon_name = $user_id ? getAnonymousName($user_id, $pdo) : 'Guest';
$role = $user_id ? getUserRole($user_id, $pdo) : 'guest';
$unread_notifs = $user_id ? getUnreadNotifications($user_id, $pdo) : 0;

// ============================================================
// AJAX Handlers
// ============================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    ob_clean();
    
    $action = $_GET['action'] ?? '';
    $response = ['success' => false, 'error' => 'Invalid action'];

    try {
        // ---- Get feed (infinite scroll) ----
        if ($action === 'get_feed') {
            $limit = intval($_GET['limit'] ?? 6);
            $offset = intval($_GET['offset'] ?? 0);
            $sort = $_GET['sort'] ?? 'newest';
            $filter_mood = $_GET['mood'] ?? '';
            $filter_category = $_GET['category'] ?? '';
            
            $sql = "SELECT p.*, u.anonymous_name, u.avatar_color,
                    (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
                    (SELECT COUNT(*) FROM reactions WHERE post_id = p.id) as reaction_count,
                    (SELECT COUNT(*) FROM reactions WHERE post_id = p.id AND user_id = ?) as user_reaction
                    FROM posts p
                    JOIN users u ON p.user_id = u.id
                    WHERE p.status = 'published'";
            $params = [$user_id];
            
            if ($filter_mood) {
                $sql .= " AND p.mood = ?";
                $params[] = $filter_mood;
            }
            if ($filter_category) {
                $sql .= " AND p.category = ?";
                $params[] = $filter_category;
            }
            
            if ($sort === 'trending') {
                $sql .= " ORDER BY p.reaction_count DESC, p.created_at DESC";
            } else {
                $sql .= " ORDER BY p.created_at DESC";
            }
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $posts = $stmt->fetchAll();
            
            // Get AI replies for each post
            $postIds = array_column($posts, 'id');
            $aiReplies = [];
            if (!empty($postIds)) {
                $placeholders = implode(',', array_fill(0, count($postIds), '?'));
                $stmt = $pdo->prepare("SELECT post_id, ai_reply, emotion, is_volunteer_notified FROM ai_analysis WHERE post_id IN ($placeholders) ORDER BY created_at DESC");
                $stmt->execute($postIds);
                while ($row = $stmt->fetch()) {
                    if (!isset($aiReplies[$row['post_id']])) {
                        $aiReplies[$row['post_id']] = $row;
                    }
                }
            }
            
            // Get volunteer replies
            $volunteerReplies = [];
            if (!empty($postIds)) {
                $placeholders = implode(',', array_fill(0, count($postIds), '?'));
                $stmt = $pdo->prepare("SELECT c.*, u.anonymous_name FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id IN ($placeholders) AND u.role = 'volunteer' ORDER BY c.created_at ASC");
                $stmt->execute($postIds);
                while ($row = $stmt->fetch()) {
                    if (!isset($volunteerReplies[$row['post_id']])) {
                        $volunteerReplies[$row['post_id']] = $row;
                    }
                }
            }
            
            // Build HTML
            $html = '';
            foreach ($posts as $post) {
                $ai = $aiReplies[$post['id']] ?? null;
                $volunteer = $volunteerReplies[$post['id']] ?? null;
                $html .= renderPostCard($post, $ai, $volunteer, $user_id);
            }
            
            $hasMore = count($posts) === $limit;
            $response = ['success' => true, 'html' => $html, 'has_more' => $hasMore];
        }

        // ---- Get live stats ----
        elseif ($action === 'get_stats') {
            $online = $pdo->query("SELECT COUNT(*) FROM users WHERE last_login > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
            $today_posts = $pdo->query("SELECT COUNT(*) FROM posts WHERE DATE(created_at) = CURDATE() AND status='published'")->fetchColumn();
            $volunteer_replies = $pdo->query("SELECT COUNT(*) FROM comments WHERE user_id IN (SELECT id FROM users WHERE role = 'volunteer') AND DATE(created_at) = CURDATE()")->fetchColumn();
            $ai_responses = $pdo->query("SELECT COUNT(*) FROM ai_analysis WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            $support_given = $pdo->query("SELECT COUNT(*) FROM reactions WHERE reaction_type = 'support'")->fetchColumn();
            $response = ['success' => true, 'stats' => [
                'online' => $online,
                'today_posts' => $today_posts,
                'volunteer_replies' => $volunteer_replies,
                'ai_responses' => $ai_responses,
                'support_given' => $support_given
            ]];
        }

        // ---- Get trending ----
        elseif ($action === 'get_trending') {
            $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM posts WHERE category != '' AND status='published' GROUP BY category ORDER BY count DESC LIMIT 6");
            $categories = $stmt->fetchAll();
            $response = ['success' => true, 'categories' => $categories];
        }

        // ---- Get live activity ----
        elseif ($action === 'get_activity') {
            $stmt = $pdo->query("
                (SELECT 'post' as type, id, user_id, content as text, created_at FROM posts WHERE status='published' ORDER BY created_at DESC LIMIT 5)
                UNION ALL
                (SELECT 'comment' as type, id, user_id, content as text, created_at FROM comments ORDER BY created_at DESC LIMIT 5)
                ORDER BY created_at DESC LIMIT 8
            ");
            $activities = $stmt->fetchAll();
            foreach ($activities as &$act) {
                $stmt2 = $pdo->prepare("SELECT anonymous_name FROM users WHERE id = ?");
                $stmt2->execute([$act['user_id']]);
                $act['user_name'] = $stmt2->fetchColumn() ?: 'Anonymous';
                $act['time_ago'] = timeAgo($act['created_at']);
            }
            $response = ['success' => true, 'activities' => $activities];
        }

        // ---- Get emotion analysis (while typing) ----
        elseif ($action === 'analyze_emotion') {
            $text = $_POST['text'] ?? '';
            require_once __DIR__ . '/ai/AIManager.php';
            $manager = new AIManager();
            // Use a lightweight method to detect emotion
            require_once __DIR__ . '/ai/EmotionDetector.php';
            $emotion = EmotionDetector::detect($text);
            $response = ['success' => true, 'emotion' => $emotion];
        }

        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ============================================================
// Helper: Render Post Card
// ============================================================
function renderPostCard($post, $ai, $volunteer, $user_id) {
    $is_owner = ($user_id && $post['user_id'] == $user_id);
    $user_reaction = $post['user_reaction'] ?? null;
    $needs_support = ($post['reaction_count'] < 3 && strtotime($post['created_at']) > time() - 3600);
    $emotion_map = ['happy'=>'😊','sad'=>'😔','stressed'=>'😰','angry'=>'😡','calm'=>'😌','hopeful'=>'🌟','neutral'=>'😐'];
    $emotion_emoji = $ai ? ($emotion_map[$ai['emotion']] ?? '😐') : '😐';
    
    ob_start();
    ?>
    <div class="post-card glass" data-post-id="<?= $post['id'] ?>">
        <div class="post-header">
            <div class="post-author">
                <div class="avatar" style="background:<?= $post['avatar_color'] ?? '#5e7564' ?>;">
                    <?= substr($post['anonymous_name'], 0, 1) ?>
                </div>
                <div>
                    <span class="name"><?= escape($post['anonymous_name']) ?></span>
                    <?php if ($post['mood']): ?>
                        <span class="mood-badge"><?= getMoodEmoji($post['mood']) ?></span>
                    <?php endif; ?>
                    <span class="time"><?= timeAgo($post['created_at']) ?></span>
                </div>
            </div>
            <?php if ($ai && $ai['is_volunteer_notified']): ?>
                <span class="badge bg-info"><i class="bi bi-shield-check"></i> Volunteer Notified</span>
            <?php endif; ?>
        </div>

        <div class="post-content">
            <?php if ($post['title']): ?>
                <h3 class="post-title"><?= escape($post['title']) ?></h3>
            <?php endif; ?>
            <p class="post-text"><?= nl2br(escape($post['content'])) ?></p>
            <?php if ($needs_support): ?>
                <span class="badge bg-warning text-dark needs-support"><i class="bi bi-heart-pulse"></i> Needs Community Support</span>
            <?php endif; ?>
        </div>

        <!-- AI Reply -->
        <?php if ($ai && $ai['ai_reply']): ?>
            <div class="ai-reply glass-sm">
                <div class="ai-header">
                    <i class="bi bi-robot"></i>
                    <span class="ai-name">MindGuide AI</span>
                    <span class="ai-emotion"><?= $emotion_emoji ?> <?= ucfirst($ai['emotion'] ?? 'neutral') ?></span>
                    <span class="ai-typing-dots"><span></span><span></span><span></span></span>
                </div>
                <div class="ai-reply-text"><?= nl2br(escape($ai['ai_reply'])) ?></div>
            </div>
        <?php endif; ?>

        <!-- Volunteer Reply -->
        <?php if ($volunteer): ?>
            <div class="volunteer-reply glass-sm">
                <div class="volunteer-header">
                    <i class="bi bi-patch-check-fill text-success"></i>
                    <span class="volunteer-name">Verified Volunteer</span>
                    <span class="volunteer-time"><?= timeAgo($volunteer['created_at']) ?></span>
                </div>
                <div class="volunteer-reply-text"><?= nl2br(escape($volunteer['content'])) ?></div>
            </div>
        <?php endif; ?>

        <!-- Reactions & Actions -->
        <div class="post-actions">
            <div class="reaction-bar" data-post-id="<?= $post['id'] ?>">
                <button class="reaction-btn <?= $user_reaction ? 'active' : '' ?>" data-reaction="support">
                    <span class="reaction-icon">❤️</span>
                    <span class="reaction-count"><?= $post['reaction_count'] ?></span>
                </button>
                <button class="reaction-btn" data-reaction="hug">🫂</button>
                <button class="reaction-btn" data-reaction="prayers">🙏</button>
                <button class="reaction-btn" data-reaction="brave">👏</button>
                <button class="reaction-btn" data-reaction="inspiring">🌟</button>
                <button class="reaction-btn" data-reaction="helpful">💙</button>
            </div>
            <div class="action-buttons">
                <button class="comment-toggle" data-post-id="<?= $post['id'] ?>">
                    <i class="bi bi-chat"></i> <span><?= $post['comment_count'] ?></span>
                </button>
                <button class="bookmark-btn" data-post-id="<?= $post['id'] ?>">
                    <i class="bi bi-bookmark"></i>
                </button>
                <button class="share-btn" data-post-id="<?= $post['id'] ?>">
                    <i class="bi bi-share"></i>
                </button>
            </div>
        </div>

        <!-- Comments Section -->
        <div class="comments-section" style="display:none;">
            <div class="comment-list" data-post-id="<?= $post['id'] ?>">
                <?php
                $comments = $pdo->prepare("SELECT c.*, u.anonymous_name FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? AND c.parent_id IS NULL ORDER BY c.created_at ASC LIMIT 5");
                $comments->execute([$post['id']]);
                while ($c = $comments->fetch()):
                ?>
                    <div class="comment-item" data-comment-id="<?= $c['id'] ?>">
                        <div class="comment-header">
                            <span class="avatar small" style="background:<?= $c['avatar_color'] ?? '#5e7564' ?>;"><?= substr($c['anonymous_name'], 0, 1) ?></span>
                            <span class="name"><?= escape($c['anonymous_name']) ?></span>
                            <span class="time"><?= timeAgo($c['created_at']) ?></span>
                        </div>
                        <p class="comment-text"><?= nl2br(escape($c['content'])) ?></p>
                        <button class="reply-toggle btn btn-sm btn-link" data-comment-id="<?= $c['id'] ?>">Reply</button>
                        <div class="reply-form" style="display:none;">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control reply-input" placeholder="Write a reply..." data-comment-id="<?= $c['id'] ?>">
                                <button class="btn btn-primary reply-submit">Reply</button>
                            </div>
                        </div>
                        <!-- Nested replies -->
                        <div class="replies">
                            <?php
                            $replies = $pdo->prepare("SELECT c.*, u.anonymous_name FROM comments c JOIN users u ON c.user_id = u.id WHERE c.parent_id = ? ORDER BY c.created_at ASC");
                            $replies->execute([$c['id']]);
                            while ($r = $replies->fetch()):
                            ?>
                                <div class="comment-item nested">
                                    <div class="comment-header">
                                        <span class="avatar small" style="background:<?= $r['avatar_color'] ?? '#5e7564' ?>;"><?= substr($r['anonymous_name'], 0, 1) ?></span>
                                        <span class="name"><?= escape($r['anonymous_name']) ?></span>
                                        <span class="time"><?= timeAgo($r['created_at']) ?></span>
                                    </div>
                                    <p class="comment-text"><?= nl2br(escape($r['content'])) ?></p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <?php if ($user_id): ?>
                <div class="comment-form">
                    <div class="input-group">
                        <input type="text" class="form-control comment-input" placeholder="Write a comment..." data-post-id="<?= $post['id'] ?>">
                        <button class="btn btn-primary comment-submit">Post</button>
                    </div>
                    <div class="typing-indicator" style="display:none;">
                        <i class="bi bi-three-dots"></i> Someone is typing...
                    </div>
                </div>
            <?php else: ?>
                <p class="text-muted small"><a href="login.php">Login</a> to comment.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================================
// Page Data
// ============================================================
$feed_limit = 6;
$feed_offset = 0;

// Get initial posts
$sql = "SELECT p.*, u.anonymous_name, u.avatar_color,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
        (SELECT COUNT(*) FROM reactions WHERE post_id = p.id) as reaction_count,
        (SELECT COUNT(*) FROM reactions WHERE post_id = p.id AND user_id = ?) as user_reaction
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.status = 'published'
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $feed_limit, $feed_offset]);
$posts = $stmt->fetchAll();

// Get AI replies
$postIds = array_column($posts, 'id');
$aiReplies = [];
if (!empty($postIds)) {
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));
    $stmt = $pdo->prepare("SELECT post_id, ai_reply, emotion, is_volunteer_notified FROM ai_analysis WHERE post_id IN ($placeholders) ORDER BY created_at DESC");
    $stmt->execute($postIds);
    while ($row = $stmt->fetch()) {
        if (!isset($aiReplies[$row['post_id']])) {
            $aiReplies[$row['post_id']] = $row;
        }
    }
}

// Get volunteer replies
$volunteerReplies = [];
if (!empty($postIds)) {
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));
    $stmt = $pdo->prepare("SELECT c.*, u.anonymous_name FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id IN ($placeholders) AND u.role = 'volunteer' ORDER BY c.created_at ASC");
    $stmt->execute($postIds);
    while ($row = $stmt->fetch()) {
        if (!isset($volunteerReplies[$row['post_id']])) {
            $volunteerReplies[$row['post_id']] = $row;
        }
    }
}

// Get trending categories
$trending = $pdo->query("SELECT category, COUNT(*) as count FROM posts WHERE category != '' AND status='published' GROUP BY category ORDER BY count DESC LIMIT 6")->fetchAll();
$categories = array_column($trending, 'category');
if (empty($categories)) $categories = ['Study Stress', 'Anxiety', 'Relationships', 'Career', 'Sleep', 'Motivation'];

// Get recent activity
$activities = $pdo->query("
    (SELECT 'post' as type, id, user_id, content as text, created_at FROM posts WHERE status='published' ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'comment' as type, id, user_id, content as text, created_at FROM comments ORDER BY created_at DESC LIMIT 5)
    ORDER BY created_at DESC LIMIT 8
")->fetchAll();
foreach ($activities as &$act) {
    $stmt2 = $pdo->prepare("SELECT anonymous_name FROM users WHERE id = ?");
    $stmt2->execute([$act['user_id']]);
    $act['user_name'] = $stmt2->fetchColumn() ?: 'Anonymous';
    $act['time_ago'] = timeAgo($act['created_at']);
}

// Get community stats
$online_count = $pdo->query("SELECT COUNT(*) FROM users WHERE last_login > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
$today_posts = $pdo->query("SELECT COUNT(*) FROM posts WHERE DATE(created_at) = CURDATE() AND status='published'")->fetchColumn();
$volunteer_replies = $pdo->query("SELECT COUNT(*) FROM comments WHERE user_id IN (SELECT id FROM users WHERE role = 'volunteer') AND DATE(created_at) = CURDATE()")->fetchColumn();
$ai_responses = $pdo->query("SELECT COUNT(*) FROM ai_analysis WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$support_given = $pdo->query("SELECT COUNT(*) FROM reactions WHERE reaction_type = 'support'")->fetchColumn();

// Get random quote
$quotes = [
    "You are stronger than you think.",
    "Every day is a fresh start.",
    "Your feelings are valid.",
    "Progress, not perfection.",
    "You are not alone in this.",
    "Small steps lead to big changes.",
    "Rest is productive, too."
];
$quote = $quotes[array_rand($quotes)];

// Get daily wellness tip
$tips = [
    "Deep breathing for 5 minutes can reduce anxiety.",
    "Write down three things you're grateful for today.",
    "Take a 10-minute walk to clear your mind.",
    "Stay hydrated – it improves your mood.",
    "Connect with someone you trust.",
    "Listen to calming music to soothe your mind.",
    "Get 7-8 hours of sleep for mental wellness."
];
$tip = $tips[array_rand($tips)];

// Get live poll
$poll = $pdo->query("SELECT * FROM polls WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetch();
$poll_votes = [];
$poll_total = 0;
$poll_user_voted = false;
if ($poll) {
    $options = json_decode($poll['options'], true);
    $stmt = $pdo->prepare("SELECT option_id, COUNT(*) as votes FROM poll_votes WHERE poll_id = ? GROUP BY option_id");
    $stmt->execute([$poll['id']]);
    $votes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $poll_total = array_sum($votes);
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
        $stmt->execute([$poll['id'], $user_id]);
        $poll_user_voted = (bool)$stmt->fetch();
    }
    foreach ($options as &$opt) {
        $opt['votes'] = $votes[$opt['id']] ?? 0;
        $opt['percent'] = $poll_total > 0 ? round(($opt['votes'] / $poll_total) * 100) : 0;
    }
    $poll['options'] = $options;
}

// Get latest active volunteers
$active_volunteers = $pdo->query("SELECT anonymous_name, avatar_color FROM users WHERE role = 'volunteer' AND is_active = 1 AND last_login > DATE_SUB(NOW(), INTERVAL 5 MINUTE) LIMIT 5")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed – Haven</title>
<link rel="icon" href="logo.png" type="image/png">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- GSAP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    
    <!-- Three.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    
    <!-- Toastify -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    
    <style>
        /* ============================================================
           CSS VARIABLES
           ============================================================ */
        :root {
            --primary: #5e7564;
            --primary-dark: #4d6555;
            --secondary: #c9a76b;
            --accent: #7fa383;
            --danger: #c96a63;
            --warning: #d9a441;
            --bg: #f7f4ed;
            --bg-card: rgba(255,255,255,0.88);
            --border-card: rgba(94,117,100,0.85);
            --text-primary: #26332b;
            --text-secondary: #7c857e;
            --text-muted: #97a099;
            --shadow: 0 8px 32px rgba(64,77,67,0.1);
            --radius: 20px;
            --radius-sm: 12px;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .light-mode {
            --bg: #eee9df;
            --bg-card: rgba(255,255,255,0.92);
            --border-card: rgba(94,117,100,0.95);
            --text-primary: #26332b;
            --text-secondary: #5c655f;
            --text-muted: #7c857e;
            --shadow: 0 8px 32px rgba(64,77,67,0.08);
        }
        
        /* ============================================================
           BASE
           ============================================================ */
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            transition: background 0.5s, color 0.5s;
            overflow-x: hidden;
        }
        h1,h2,h3,h4,h5,h6 { font-family: 'Poppins', sans-serif; }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; }
        img { max-width: 100%; display: block; }
        
        /* ============================================================
           THREE.JS BACKGROUND
           ============================================================ */
        #three-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
            opacity: 0.4;
        }
        
        /* ============================================================
           SCROLLBAR
           ============================================================ */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--secondary); border-radius: 10px; }
        
        /* ============================================================
           GLASSMORPHISM
           ============================================================ */
        .glass {
            background: var(--bg-card);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid var(--border-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: all var(--transition);
        }
        .glass:hover {
            background: rgba(255,255,255,0.77);
            border-color: rgba(94,117,100,0.12);
        }
        .light-mode .glass:hover {
            background: rgba(255,255,255,0.9);
        }
        .glass-sm {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-card);
            border-radius: var(--radius-sm);
        }
        
        /* ============================================================
           LAYOUT
           ============================================================ */
        .feed-layout {
            display: grid;
            grid-template-columns: 220px 1fr 300px;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 80px 20px 20px;
            position: relative;
            z-index: 1;
            min-height: 100vh;
        }
        @media (max-width: 1200px) {
            .feed-layout { grid-template-columns: 200px 1fr 260px; }
        }
        @media (max-width: 992px) {
            .feed-layout { grid-template-columns: 1fr; }
            .left-sidebar, .right-sidebar { display: none; }
            .left-sidebar.mobile-open, .right-sidebar.mobile-open { display: block; }
        }
        @media (max-width: 576px) {
            .feed-layout { padding: 70px 10px 10px; gap: 10px; }
        }

        .mobile-bottom-nav { display: none; }
        @media (max-width: 767px) {
            body { padding-bottom: 64px; }
            .mobile-bottom-nav {
                display: flex; position: fixed; left: 0; right: 0; bottom: 0; z-index: 1200; height: 60px;
                background: rgba(255,253,248,0.96); backdrop-filter: blur(16px);
                border-top: 1px solid var(--border-card); justify-content: space-around; align-items: center;
                box-shadow: 0 -6px 24px rgba(64,77,67,.08);
            }
            .mobile-bottom-nav a {
                flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
                gap: 2px; color: var(--text-secondary); font-size: .62rem; font-weight: 600; text-decoration: none;
                padding: 6px 0; position: relative;
            }
            .mobile-bottom-nav a i { font-size: 1.25rem; }
            .mobile-bottom-nav a.active { color: var(--primary); }
            .mobile-bottom-nav a .mbn-badge {
                position: absolute; top: 2px; right: 22%; background: var(--danger); color: #fff;
                border-radius: 8px; font-size: .55rem; padding: 0 4px; line-height: 1.3;
            }
        }
        
        /* ============================================================
           SIDEBARS
           ============================================================ */
        .left-sidebar, .right-sidebar {
            position: sticky;
            top: 80px;
            height: calc(100vh - 80px);
            overflow-y: auto;
            padding: 10px;
            z-index: 1;
        }
        .left-sidebar::-webkit-scrollbar,
        .right-sidebar::-webkit-scrollbar { width: 3px; }
        
        .left-sidebar .glass,
        .right-sidebar .glass {
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .left-sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.5rem 0.8rem;
            border-radius: 12px;
            color: var(--text-secondary);
            transition: var(--transition);
            font-size: 0.9rem;
        }
        .left-sidebar .nav-link:hover { background: var(--bg-card); color: var(--text-primary); }
        .left-sidebar .nav-link.active { background: rgba(94,117,100,0.15); color: var(--primary); }
        .left-sidebar .nav-link i { width: 20px; text-align: center; }
        
        .left-sidebar .profile-card { text-align: center; padding: 1.2rem; }
        .left-sidebar .profile-card .avatar {
            width: 56px; height: 56px;
            border-radius: 50%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
            color: #fff;
        }
        
        /* ============================================================
           NAVBAR
           ============================================================ */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            padding: 0.6rem 1.5rem;
            background: rgba(255,253,248,0.85);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid rgba(94,117,100,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .light-mode .navbar { background: rgba(255,253,248,0.92); }
        .navbar .brand { font-weight: 700; font-size: 1.2rem; color: var(--primary); }
        .navbar .brand i { margin-right: 8px; }
        .navbar .nav-right { display: flex; align-items: center; gap: 0.8rem; }
        .navbar .nav-right .btn { padding: 0.3rem 0.8rem; border-radius: 30px; font-size: 0.85rem; }
        .navbar .search-input {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 30px;
            padding: 0.4rem 1rem;
            color: var(--text-primary);
            width: 200px;
            transition: var(--transition);
        }
        .navbar .search-input:focus {
            outline: none;
            border-color: var(--primary);
            width: 250px;
        }
        .navbar .search-input::placeholder { color: var(--text-muted); }
        .light-mode .navbar .search-input { background: rgba(255,255,255,0.8); }
        
        /* ============================================================
           SCROLL PROGRESS
           ============================================================ */
        #scrollProgress {
            position: fixed;
            top: 60px;
            left: 0;
            width: 0%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            z-index: 1051;
            transition: width 0.1s;
        }
        
        /* ============================================================
           CREATE POST
           ============================================================ */
        .create-post-box {
            margin-bottom: 1.2rem;
            overflow: hidden;
        }
        .create-post-box .collapsed {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.8rem 1.2rem;
            cursor: pointer;
        }
        .create-post-box .collapsed .avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            flex-shrink: 0;
        }
        .create-post-box .collapsed input {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 0.95rem;
            cursor: pointer;
            outline: none;
        }
        .create-post-box .collapsed input::placeholder { color: var(--text-muted); }
        
        .create-post-box .expanded {
            padding: 1.2rem;
            display: none;
        }
        .create-post-box .expanded .form-control {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: var(--radius-sm);
            padding: 0.8rem;
            color: var(--text-primary);
            width: 100%;
            resize: vertical;
            font-family: inherit;
        }
        .create-post-box .expanded .form-control:focus { outline: none; border-color: var(--primary); }
        .create-post-box .expanded .mood-selector {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin: 0.8rem 0;
        }
        .create-post-box .expanded .mood-selector .mood-btn {
            font-size: 1.5rem;
            padding: 0.2rem 0.6rem;
            border-radius: 30px;
            background: var(--bg-card);
            border: 2px solid transparent;
            transition: var(--transition);
        }
        .create-post-box .expanded .mood-selector .mood-btn:hover,
        .create-post-box .expanded .mood-selector .mood-btn.active {
            border-color: var(--primary);
            transform: scale(1.1);
        }
        .create-post-box .expanded .char-counter {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-align: right;
            margin-top: 0.3rem;
        }
        .create-post-box .expanded .char-counter.limit { color: var(--danger); }
        .create-post-box .expanded .emotion-indicator {
            font-size: 0.85rem;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            background: var(--bg-card);
            display: inline-block;
            margin-top: 0.5rem;
        }
        
        /* ============================================================
           POST CARDS
           ============================================================ */
        .post-card {
            padding: 1.2rem;
            margin-bottom: 1.2rem;
            transition: all var(--transition);
            position: relative;
        }
        .post-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 48px rgba(64,77,67,0.15);
        }
        .post-card .post-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.8rem;
        }
        .post-card .post-author { display: flex; align-items: center; gap: 0.8rem; }
        .post-card .post-author .avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .post-card .post-author .name { font-weight: 600; }
        .post-card .post-author .mood-badge { font-size: 1.1rem; margin-left: 0.3rem; }
        .post-card .post-author .time {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: block;
        }
        .post-card .post-title { font-size: 1.1rem; margin-bottom: 0.4rem; }
        .post-card .post-text { color: var(--text-secondary); line-height: 1.6; }
        .post-card .needs-support {
            display: inline-block;
            margin-top: 0.5rem;
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            animation: pulseGlow 2s infinite;
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 10px rgba(251,191,36,0.3); }
            50% { box-shadow: 0 0 25px rgba(251,191,36,0.6); }
        }
        
        /* AI Reply */
        .post-card .ai-reply {
            padding: 0.8rem;
            margin: 0.8rem 0;
            border-left: 3px solid var(--secondary);
            background: rgba(201,167,107,0.06);
            border-radius: var(--radius-sm);
        }
        .post-card .ai-reply .ai-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
            font-size: 0.85rem;
        }
        .post-card .ai-reply .ai-header i { color: var(--secondary); }
        .post-card .ai-reply .ai-header .ai-name { font-weight: 600; color: var(--secondary); }
        .post-card .ai-reply .ai-header .ai-emotion {
            background: var(--bg-card);
            padding: 0.1rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
        }
        .post-card .ai-reply .ai-typing-dots {
            display: inline-flex;
            gap: 3px;
            margin-left: 0.5rem;
        }
        .post-card .ai-reply .ai-typing-dots span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--secondary);
            animation: typingDot 1.2s infinite;
        }
        .post-card .ai-reply .ai-typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .post-card .ai-reply .ai-typing-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typingDot {
            0%, 60%, 100% { opacity: 0.2; transform: translateY(0); }
            30% { opacity: 1; transform: translateY(-4px); }
        }
        .post-card .ai-reply .ai-reply-text { font-size: 0.9rem; color: var(--text-secondary); }
        
        /* Volunteer Reply */
        .post-card .volunteer-reply {
            padding: 0.8rem;
            margin: 0.8rem 0;
            border-left: 3px solid var(--accent);
            background: rgba(127,163,131,0.06);
            border-radius: var(--radius-sm);
        }
        .post-card .volunteer-reply .volunteer-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
            font-size: 0.85rem;
        }
        .post-card .volunteer-reply .volunteer-header i { color: var(--accent); }
        .post-card .volunteer-reply .volunteer-header .volunteer-name { font-weight: 600; color: var(--accent); }
        .post-card .volunteer-reply .volunteer-header .volunteer-time { font-size: 0.7rem; color: var(--text-muted); }
        .post-card .volunteer-reply .volunteer-reply-text { font-size: 0.9rem; color: var(--text-secondary); }
        
        /* Actions */
        .post-card .post-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.8rem;
            padding-top: 0.8rem;
            border-top: 1px solid var(--border-card);
        }
        .post-card .reaction-bar {
            display: flex;
            gap: 0.2rem;
            flex-wrap: wrap;
        }
        .post-card .reaction-btn {
            display: flex;
            align-items: center;
            gap: 0.2rem;
            padding: 0.1rem 0.6rem;
            border-radius: 20px;
            background: var(--bg-card);
            font-size: 0.85rem;
            transition: var(--transition);
            border: 1px solid transparent;
        }
        .post-card .reaction-btn:hover { transform: scale(1.05); background: rgba(255,255,255,0.8); }
        .post-card .reaction-btn.active { border-color: var(--primary); background: rgba(94,117,100,0.15); }
        .post-card .reaction-btn .reaction-count { font-size: 0.7rem; color: var(--text-muted); }
        .post-card .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .post-card .action-buttons button {
            padding: 0.1rem 0.6rem;
            border-radius: 20px;
            background: var(--bg-card);
            color: var(--text-secondary);
            font-size: 0.85rem;
            transition: var(--transition);
        }
        .post-card .action-buttons button:hover {
            background: rgba(255,255,255,0.8);
            color: var(--text-primary);
        }
        
        /* Comments */
        .post-card .comments-section {
            margin-top: 0.8rem;
            padding-top: 0.8rem;
            border-top: 1px solid var(--border-card);
        }
        .post-card .comment-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-card);
        }
        .post-card .comment-item:last-child { border-bottom: none; }
        .post-card .comment-item .comment-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
        }
        .post-card .comment-item .comment-header .avatar {
            width: 28px; height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 0.7rem;
        }
        .post-card .comment-item .comment-header .name { font-weight: 500; }
        .post-card .comment-item .comment-header .time { font-size: 0.7rem; color: var(--text-muted); }
        .post-card .comment-item .comment-text { font-size: 0.9rem; color: var(--text-secondary); margin: 0.2rem 0; }
        .post-card .comment-item .reply-toggle { font-size: 0.75rem; color: var(--text-muted); }
        .post-card .comment-item .reply-toggle:hover { color: var(--primary); }
        .post-card .comment-item .replies { margin-left: 2rem; }
        .post-card .comment-item.nested { border-left: 2px solid var(--border-card); padding-left: 0.8rem; }
        .post-card .comment-form { margin-top: 0.5rem; }
        .post-card .comment-form .input-group { display: flex; gap: 0.5rem; }
        .post-card .comment-form .input-group input {
            flex: 1;
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 30px;
            padding: 0.4rem 1rem;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        .post-card .comment-form .input-group input:focus { outline: none; border-color: var(--primary); }
        .post-card .comment-form .input-group button { border-radius: 30px; padding: 0.4rem 1rem; }
        .post-card .comment-form .typing-indicator {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.3rem;
        }
        
        /* ============================================================
           SIDEBAR WIDGETS
           ============================================================ */
        .widget { padding: 1rem; margin-bottom: 1rem; }
        .widget-title {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.8rem;
            color: var(--text-secondary);
        }
        .widget .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 0.3rem 0;
            font-size: 0.85rem;
            border-bottom: 1px solid var(--border-card);
        }
        .widget .stat-item:last-child { border-bottom: none; }
        .widget .stat-item .value { font-weight: 600; color: var(--text-primary); }
        
        .widget .activity-item {
            padding: 0.4rem 0;
            border-bottom: 1px solid var(--border-card);
            font-size: 0.85rem;
        }
        .widget .activity-item:last-child { border-bottom: none; }
        .widget .activity-item .time { font-size: 0.65rem; color: var(--text-muted); float: right; }
        
        .widget .trending-tag {
            display: inline-block;
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            background: var(--bg-card);
            font-size: 0.75rem;
            margin: 0.2rem;
            transition: var(--transition);
        }
        .widget .trending-tag:hover {
            background: rgba(94,117,100,0.15);
            color: var(--primary);
            transform: scale(1.05);
        }
        
        .widget .poll-option {
            padding: 0.3rem 0;
            cursor: pointer;
            transition: var(--transition);
        }
        .widget .poll-option:hover { opacity: 0.8; }
        .widget .poll-option .progress {
            height: 6px;
            background: var(--bg-card);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 0.2rem;
        }
        .widget .poll-option .progress .bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 10px;
            transition: width 0.6s ease;
        }
        
        /* ============================================================
           SKELETON LOADING
           ============================================================ */
        .skeleton {
            background: var(--bg-card);
            border-radius: var(--radius-sm);
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { opacity: 0.4; }
            50% { opacity: 0.8; }
            100% { opacity: 0.4; }
        }
        .skeleton .line { height: 12px; background: var(--border-card); border-radius: 6px; margin: 6px 0; }
        .skeleton .line.short { width: 40%; }
        .skeleton .line.medium { width: 60%; }
        .skeleton .avatar-skel { width: 40px; height: 40px; border-radius: 50%; background: var(--border-card); }
        
        /* ============================================================
           NEW POSTS BANNER
           ============================================================ */
        #newPostsBanner {
            display: none;
            padding: 0.5rem 1rem;
            text-align: center;
            background: rgba(94,117,100,0.15);
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid rgba(94,117,100,0.2);
        }
        #newPostsBanner:hover { background: rgba(94,117,100,0.25); }
        
        /* ============================================================
           LOAD MORE / END
           ============================================================ */
        #feedLoader { text-align: center; padding: 2rem; display: none; }
        #feedLoader .spinner {
            width: 40px; height: 40px;
            border: 3px solid var(--border-card);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: inline-block;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        #feedEnd { text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.9rem; display: none; }
        
        /* ============================================================
           FLOATING BUTTONS
           ============================================================ */
        .float-chat {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1060;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 32px rgba(94,117,100,0.4);
            transition: var(--transition);
            animation: breathe 3s ease-in-out infinite;
        }
        .float-chat:hover { transform: scale(1.1); }
        @keyframes breathe {
            0%, 100% { box-shadow: 0 8px 32px rgba(94,117,100,0.4); }
            50% { box-shadow: 0 8px 48px rgba(94,117,100,0.7); }
        }
        
        .float-create {
            position: fixed;
            bottom: 100px;
            right: 30px;
            z-index: 1060;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #a9c2ae);
            color: #26332b;
            font-size: 1.6rem;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 32px rgba(127,163,131,0.3);
            transition: var(--transition);
        }
        .float-create:hover { transform: scale(1.1); }
        @media (max-width: 992px) { .float-create { display: flex; } }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 576px) {
            .navbar .search-input { width: 120px; }
            .navbar .search-input:focus { width: 150px; }
            .post-card { padding: 0.8rem; }
            .post-card .post-actions { flex-direction: column; align-items: stretch; }
            .float-chat { width: 50px; height: 50px; font-size: 1.4rem; bottom: 20px; right: 20px; }
            .float-create { bottom: 80px; right: 20px; width: 48px; height: 48px; font-size: 1.3rem; }
        }
    </style>
</head>
<body>

<!-- ============================================================
   THREE.JS BACKGROUND
   ============================================================ -->
<div id="three-bg"></div>

<!-- ============================================================
   SCROLL PROGRESS
   ============================================================ -->
<div id="scrollProgress"></div>

<!-- ============================================================
   NAVBAR
   ============================================================ -->
<nav class="navbar">
    <div>
        <button class="btn btn-link d-lg-none" id="sidebarToggle" style="color:var(--text-secondary);"><i class="bi bi-list fs-4"></i></button>
        <span class="brand"><img src="logo.png" alt="Haven" style="height:22px;width:22px;object-fit:cover;border-radius:6px;vertical-align:-5px;margin-right:4px;"> Haven</span>
    </div>
    <div class="nav-right">
        <input type="text" class="search-input d-none d-sm-block" placeholder="Search..." id="globalSearch">
        <button class="btn btn-outline-secondary btn-sm d-none d-sm-block" id="darkToggle"><i class="bi bi-moon"></i></button>
        <?php if ($user_id): ?>
            <a href="notifications.php" class="btn btn-outline-primary btn-sm position-relative">
                <i class="bi bi-bell"></i>
                <?php if ($unread_notifs > 0): ?><span class="badge bg-danger position-absolute top-0 start-100 translate-middle" style="font-size:0.6rem;"><?= $unread_notifs ?></span><?php endif; ?>
            </a>
            <a href="dashboard.php" class="btn btn-outline-primary btn-sm d-none d-sm-inline">Dashboard</a>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-primary btn-sm">Login</a>
        <?php endif; ?>
    </div>
</nav>

<!-- ============================================================
   FEED LAYOUT
   ============================================================ -->
<div class="feed-layout">

    <!-- ============================================================
       LEFT SIDEBAR
       ============================================================ -->
    <div class="left-sidebar" id="leftSidebar">
        <!-- Profile Card -->
        <div class="glass profile-card">
            <?php if ($user_id): ?>
                <div class="avatar" style="background:<?= $pdo->query("SELECT avatar_color FROM users WHERE id = $user_id")->fetchColumn() ?? '#5e7564' ?>;">
                    <?= substr($anon_name, 0, 1) ?>
                </div>
                <h6 class="mt-2"><?= escape($anon_name) ?></h6>
                <p class="text-muted small">Member</p>
            <?php else: ?>
                <div class="avatar" style="background:#5e7564;">?</div>
                <h6 class="mt-2">Guest</h6>
                <p class="text-muted small">Not logged in</p>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <div class="glass">
            <nav class="nav flex-column">
                <a href="index.php" class="nav-link active"><i class="bi bi-house"></i> Home</a>
                <a href="community.php" class="nav-link"><i class="bi bi-compass"></i> Explore</a>
                <a href="chatbot.php" class="nav-link"><i class="bi bi-robot"></i> AI Chat</a>
                <a href="articles.php" class="nav-link"><i class="bi bi-book"></i> Resources</a>
                <a href="dashboard.php" class="nav-link"><i class="bi bi-grid"></i> Dashboard</a>
                <a href="consultation.php?tab=new" class="nav-link"><i class="bi bi-person-heart"></i> Talk to a Volunteer</a>
            </nav>
        </div>

        <!-- Mood Filter -->
        <div class="glass">
            <div class="widget-title">Filter by Mood</div>
            <div class="d-flex flex-wrap gap-1">
                <button class="btn btn-sm btn-outline-secondary mood-filter" data-mood="">All</button>
                <button class="btn btn-sm btn-outline-secondary mood-filter" data-mood="happy">😊</button>
                <button class="btn btn-sm btn-outline-secondary mood-filter" data-mood="okay">🙂</button>
                <button class="btn btn-sm btn-outline-secondary mood-filter" data-mood="sad">😔</button>
                <button class="btn btn-sm btn-outline-secondary mood-filter" data-mood="stressed">😰</button>
                <button class="btn btn-sm btn-outline-secondary mood-filter" data-mood="angry">😡</button>
            </div>
        </div>

        <!-- Categories -->
        <div class="glass">
            <div class="widget-title">Categories</div>
            <?php foreach ($categories as $cat): ?>
                <a href="#" class="d-block text-muted small py-1 category-filter" data-category="<?= escape($cat) ?>">#<?= escape($cat) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ============================================================
       MAIN FEED
       ============================================================ -->
    <div class="feed-container">

        <!-- Create Post -->
        <div class="glass create-post-box">
            <div class="collapsed" id="createPostCollapsed">
                <?php if ($user_id): ?>
                    <div class="avatar" style="background:<?= $pdo->query("SELECT avatar_color FROM users WHERE id = $user_id")->fetchColumn() ?? '#5e7564' ?>;">
                        <?= substr($anon_name, 0, 1) ?>
                    </div>
                    <input type="text" placeholder="How are you feeling today?" readonly>
                <?php else: ?>
                    <div class="avatar" style="background:#5e7564;">?</div>
                    <input type="text" placeholder="Login to share your thoughts" readonly onclick="window.location.href='login.php'">
                <?php endif; ?>
            </div>
            <?php if ($user_id): ?>
                <div class="expanded" id="createPostExpanded">
                    <form id="createPostForm" action="create-post.php" method="POST" enctype="multipart/form-data">
                        <input type="text" name="title" class="form-control mb-2" placeholder="Title (optional)">
                        <textarea name="content" id="postContent" class="form-control" rows="4" placeholder="What's on your mind?" required data-emotion-ml data-emotion-chip="emotionDisplay"></textarea>
                        <div id="charCounter" class="char-counter">0 / 1000</div>
                        <div id="emotionDisplay" class="emotion-indicator">😐 Detecting emotion...</div>
                        <div class="mood-selector">
                            <button type="button" class="mood-btn" data-mood="happy">😊 Happy</button>
                            <button type="button" class="mood-btn" data-mood="okay">🙂 Calm</button>
                            <button type="button" class="mood-btn" data-mood="sad">😔 Sad</button>
                            <button type="button" class="mood-btn" data-mood="stressed">😰 Stressed</button>
                            <button type="button" class="mood-btn" data-mood="angry">😡 Angry</button>
                        </div>
                        <input type="hidden" name="mood" id="selectedMood" value="">
                        <div class="mt-2">
                            <label class="small text-muted mb-1 d-block"><i class="bi bi-paperclip"></i> Attach photo, video, or audio (optional)</label>
                            <input type="file" name="media" class="form-control form-control-sm" accept="image/*,video/*,audio/*">
                        </div>
                        <div class="mt-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Post</button>
                            <button type="button" class="btn btn-outline-secondary" id="cancelPost">Cancel</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- New Posts Banner -->
        <div id="newPostsBanner">
            <span id="newPostsCount">0</span> new posts — click to load
        </div>

        <!-- Feed -->
        <div id="feedContainer">
            <?php
            $postIndex = 0;
            foreach ($posts as $post):
                $ai = $aiReplies[$post['id']] ?? null;
                $volunteer = $volunteerReplies[$post['id']] ?? null;
                echo renderPostCard($post, $ai, $volunteer, $user_id);
                $postIndex++;
            endforeach;
            ?>
        </div>

        <!-- Loader & End -->
        <div id="feedLoader"><div class="spinner"></div></div>
        <div id="feedEnd">✨ You've seen everything</div>
    </div>

    <!-- ============================================================
       RIGHT SIDEBAR
       ============================================================ -->
    <div class="right-sidebar" id="rightSidebar">

        <!-- Quote -->
        <div class="glass widget text-center">
            <i class="bi bi-quote" style="font-size:1.5rem;color:var(--primary);"></i>
            <p class="small fst-italic">"<?= $quote ?>"</p>
        </div>

        <!-- Live Poll -->
        <?php if ($poll): ?>
            <div class="glass widget" id="pollWidget">
                <div class="widget-title"><i class="bi bi-bar-chart"></i> <?= escape($poll['title']) ?></div>
                <?php foreach ($poll['options'] as $opt): ?>
                    <div class="poll-option" data-poll-id="<?= $poll['id'] ?>" data-option-id="<?= $opt['id'] ?>">
                        <div class="d-flex justify-content-between">
                            <span><?= escape($opt['label']) ?></span>
                            <span class="text-muted small"><?= $opt['votes'] ?? 0 ?></span>
                        </div>
                        <div class="progress"><div class="bar" style="width:<?= $opt['percent'] ?? 0 ?>%;"></div></div>
                    </div>
                <?php endforeach; ?>
                <p class="text-muted small mt-1"><?= $poll_total ?> votes <?= $poll_user_voted ? '• You voted' : '' ?></p>
            </div>
        <?php endif; ?>

        <!-- Community Pulse -->
        <div class="glass widget" id="pulseWidget">
            <div class="widget-title"><i class="bi bi-heart-pulse"></i> Community Pulse</div>
            <div class="stat-item"><span>👥 Online</span><span class="value" id="statOnline">0</span></div>
            <div class="stat-item"><span>📝 Posts Today</span><span class="value" id="statPosts">0</span></div>
            <div class="stat-item"><span>🤖 AI Responses</span><span class="value" id="statAI">0</span></div>
            <div class="stat-item"><span>🧑‍⚕️ Volunteer Replies</span><span class="value" id="statVolunteer">0</span></div>
            <div class="stat-item"><span>❤️ Support Given</span><span class="value" id="statSupport">0</span></div>
        </div>

        <!-- Live Activity -->
        <div class="glass widget" id="activityWidget">
            <div class="widget-title"><i class="bi bi-clock-history"></i> Live Activity</div>
            <div id="activityList">
                <?php foreach ($activities as $act): ?>
                    <div class="activity-item">
                        <span><?= $act['type'] === 'post' ? '📝' : '💬' ?></span>
                        <strong><?= escape($act['user_name']) ?></strong>
                        <span class="text-muted small"><?= substr(escape($act['text']), 0, 30) ?>...</span>
                        <span class="time"><?= $act['time_ago'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Trending -->
        <div class="glass widget">
            <div class="widget-title"><i class="bi bi-fire"></i> Trending Topics</div>
            <?php foreach ($categories as $cat): ?>
                <span class="trending-tag category-filter" data-category="<?= escape($cat) ?>">#<?= escape($cat) ?></span>
            <?php endforeach; ?>
        </div>

        <!-- Active Volunteers -->
        <div class="glass widget">
            <div class="widget-title"><i class="bi bi-person-check"></i> Volunteers Online</div>
            <?php if (empty($active_volunteers)): ?>
                <p class="text-muted small">No volunteers online</p>
            <?php else: ?>
                <?php foreach ($active_volunteers as $v): ?>
                    <div class="d-flex align-items-center gap-2 py-1">
                        <div class="avatar" style="width:28px;height:28px;border-radius:50%;background:<?= $v['avatar_color'] ?? '#5e7564' ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.7rem;font-weight:600;">
                            <?= substr($v['anonymous_name'], 0, 1) ?>
                        </div>
                        <span class="small"><?= escape($v['anonymous_name']) ?></span>
                        <span class="badge bg-success" style="font-size:0.5rem;">●</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Wellness Tip -->
        <div class="glass widget" style="border-left:3px solid var(--accent);">
            <div class="widget-title"><i class="bi bi-lightbulb"></i> Wellness Tip</div>
            <p class="small text-muted">💡 <?= $tip ?></p>
        </div>

        <!-- Emergency -->
        <div class="glass widget" style="border-left:3px solid var(--danger);">
            <div class="widget-title"><i class="bi bi-exclamation-triangle text-danger"></i> Need Help?</div>
            <p class="small text-muted">If you're in crisis, reach out now.</p>
            <a href="consultation.php?tab=new" class="btn btn-danger btn-sm w-100">Contact Volunteer</a>
        </div>
    </div>
</div>

<!-- ============================================================
   FLOATING BUTTONS
   ============================================================ -->
<button class="float-chat" id="floatChat" title="Chat with MindGuide">
    <i class="bi bi-robot"></i>
</button>
<button class="float-create" id="floatCreate" title="Create Post">
    <i class="bi bi-plus-lg"></i>
</button>

<!-- ============================================================
   SCRIPTS
   ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ============================================================
    // THREE.JS BACKGROUND
    // ============================================================
    (function() {
        const container = document.getElementById('three-bg');
        if (!container) return;
        
        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0x0F172A);
        
        const camera = new THREE.PerspectiveCamera(75, container.clientWidth / container.clientHeight, 0.1, 1000);
        camera.position.z = 30;
        
        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setSize(container.clientWidth, container.clientHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        container.appendChild(renderer.domElement);
        
        // Particles
        const geometry = new THREE.BufferGeometry();
        const count = 300;
        const positions = new Float32Array(count * 3);
        const colors = new Float32Array(count * 3);
        const sizes = new Float32Array(count);
        
        const colorPalette = [
            new THREE.Color(0x5B8DEF),
            new THREE.Color(0x8B5CF6),
            new THREE.Color(0x4ADE80),
            new THREE.Color(0xFB7185),
            new THREE.Color(0xFBBF24)
        ];
        
        for (let i = 0; i < count; i++) {
            positions[i*3] = (Math.random() - 0.5) * 80;
            positions[i*3+1] = (Math.random() - 0.5) * 80;
            positions[i*3+2] = (Math.random() - 0.5) * 40 - 10;
            
            const color = colorPalette[Math.floor(Math.random() * colorPalette.length)];
            colors[i*3] = color.r;
            colors[i*3+1] = color.g;
            colors[i*3+2] = color.b;
            
            sizes[i] = 0.2 + Math.random() * 0.8;
        }
        
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
        geometry.setAttribute('size', new THREE.BufferAttribute(sizes, 1));
        
        const material = new THREE.PointsMaterial({
            size: 0.3,
            vertexColors: true,
            transparent: true,
            opacity: 0.6,
            blending: THREE.AdditiveBlending,
            sizeAttenuation: true
        });
        
        const particles = new THREE.Points(geometry, material);
        scene.add(particles);
        
        // Mouse interaction
        let mouseX = 0, mouseY = 0;
        document.addEventListener('mousemove', (e) => {
            mouseX = (e.clientX / window.innerWidth - 0.5) * 2;
            mouseY = (e.clientY / window.innerHeight - 0.5) * 2;
        });
        
        function animate() {
            requestAnimationFrame(animate);
            
            particles.rotation.x += 0.0002;
            particles.rotation.y += 0.0003;
            
            // Mouse follow
            particles.rotation.x += (mouseY * 0.02 - particles.rotation.x) * 0.01;
            particles.rotation.y += (mouseX * 0.02 - particles.rotation.y) * 0.01;
            
            renderer.render(scene, camera);
        }
        
        animate();
        
        // Resize
        window.addEventListener('resize', () => {
            const w = container.clientWidth;
            const h = container.clientHeight;
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h);
        });
        
        // Dark mode update
        const darkToggle = document.getElementById('darkToggle');
        if (darkToggle) {
            darkToggle.addEventListener('click', function() {
                const isDark = !document.body.classList.contains('light-mode');
                scene.background = new THREE.Color(isDark ? 0x0F172A : 0xF1F5F9);
                material.opacity = isDark ? 0.6 : 0.2;
            });
        }
    })();

    // ============================================================
    // GSAP - Page Entrance
    // ============================================================
    gsap.utils.toArray('.post-card, .glass:not(.create-post-box)').forEach((el, i) => {
        gsap.from(el, {
            y: 40,
            opacity: 0,
            duration: 0.6,
            delay: i * 0.04,
            ease: 'power2.out'
        });
    });

    // ============================================================
    // SCROLL PROGRESS
    // ============================================================
    document.addEventListener('scroll', () => {
        const scrollTop = window.scrollY;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const progress = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
        document.getElementById('scrollProgress').style.width = progress + '%';
    });

    // ============================================================
    // DARK MODE
    // ============================================================
    const darkToggle = document.getElementById('darkToggle');
    darkToggle?.addEventListener('click', function() {
        document.body.classList.toggle('light-mode');
        localStorage.setItem('feedLightMode', document.body.classList.contains('light-mode'));
        const icon = this.querySelector('i');
        if (icon) {
            icon.className = document.body.classList.contains('light-mode') ? 'bi bi-sun-fill' : 'bi bi-moon';
        }
    });
    if (localStorage.getItem('feedLightMode') === 'true') {
        document.body.classList.add('light-mode');
        const icon = darkToggle?.querySelector('i');
        if (icon) icon.className = 'bi bi-sun-fill';
    }

    // ============================================================
    // SIDEBAR TOGGLE (Mobile)
    // ============================================================
    document.getElementById('sidebarToggle')?.addEventListener('click', function() {
        document.getElementById('leftSidebar').classList.toggle('mobile-open');
        document.getElementById('rightSidebar').classList.toggle('mobile-open');
    });

    // ============================================================
    // CREATE POST EXPAND/COLLAPSE
    // ============================================================
    document.getElementById('createPostCollapsed')?.addEventListener('click', function() {
        const expanded = document.getElementById('createPostExpanded');
        if (expanded) {
            if (expanded.style.display === 'block') {
                expanded.style.display = 'none';
                gsap.to(expanded, { height: 0, duration: 0.3, ease: 'power2.out' });
            } else {
                expanded.style.display = 'block';
                gsap.from(expanded, { height: 0, duration: 0.4, ease: 'power2.out' });
            }
        }
    });
    document.getElementById('cancelPost')?.addEventListener('click', function() {
        const expanded = document.getElementById('createPostExpanded');
        if (expanded) {
            expanded.style.display = 'none';
            gsap.to(expanded, { height: 0, duration: 0.3, ease: 'power2.out' });
        }
    });

    // ============================================================
    // CHARACTER COUNTER & EMOTION DETECTION
    // ============================================================
    const postContent = document.getElementById('postContent');
    const charCounter = document.getElementById('charCounter');
    const emotionDisplay = document.getElementById('emotionDisplay');
    let emotionTimeout;

    postContent?.addEventListener('input', function() {
        const len = this.value.length;
        charCounter.textContent = len + ' / 1000';
        charCounter.className = 'char-counter' + (len > 900 ? ' limit' : '');
        
        clearTimeout(emotionTimeout);
        if (window.HavenEmotion && window.HavenEmotion.config() && !window.HavenEmotionFailed) return; // TFLite handles the chip (assets/js/emotion-tflite.js)
        if (len > 5) {
            emotionTimeout = setTimeout(() => {
                fetch('feed.php?ajax=1&action=analyze_emotion', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'text=' + encodeURIComponent(this.value)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.emotion) {
                        const emojis = { happy:'😊', sad:'😔', stressed:'😰', angry:'😡', calm:'😌', hopeful:'🌟' };
                        emotionDisplay.textContent = (emojis[data.emotion] || '😐') + ' ' + data.emotion;
                        emotionDisplay.style.opacity = '1';
                    }
                });
            }, 500);
        } else {
            emotionDisplay.textContent = '😐 Detecting emotion...';
        }
    });

    // ============================================================
    // MOOD SELECTOR
    // ============================================================
    document.querySelectorAll('.mood-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.mood-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('selectedMood').value = this.dataset.mood;
        });
    });

    // ============================================================
    // REACTIONS
    // ============================================================
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.reaction-btn');
        if (!btn) return;
        const postId = btn.closest('.reaction-bar').dataset.postId;
        const reaction = btn.dataset.reaction;
        if (!USER_ID) { showToast('Please login to react', 'warning'); return; }
        
        fetch('api/reaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle&target_id=${postId}&target_type=post&reaction_type=${reaction}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const countSpan = btn.querySelector('.reaction-count');
                if (data.action === 'added') {
                    btn.classList.add('active');
                    countSpan.textContent = data.count;
                    // Floating emoji animation
                    const rect = btn.getBoundingClientRect();
                    const emoji = document.createElement('div');
                    emoji.textContent = btn.querySelector('.reaction-icon')?.textContent || '❤️';
                    emoji.style.cssText = `
                        position: fixed; left: ${rect.left + rect.width/2 - 15}px; top: ${rect.top}px;
                        font-size: 2.5rem; z-index: 9999; pointer-events: none;
                        transition: all 1s ease-out;
                    `;
                    document.body.appendChild(emoji);
                    requestAnimationFrame(() => {
                        emoji.style.transform = 'translateY(-80px) scale(1.5) rotate(20deg)';
                        emoji.style.opacity = '0';
                    });
                    setTimeout(() => emoji.remove(), 1000);
                } else {
                    btn.classList.remove('active');
                    countSpan.textContent = data.count || '';
                }
            }
        });
    });

    // ============================================================
    // COMMENTS
    // ============================================================
    document.addEventListener('click', function(e) {
        // Toggle comments
        const toggle = e.target.closest('.comment-toggle');
        if (toggle) {
            const section = toggle.closest('.post-card').querySelector('.comments-section');
            if (section) {
                section.style.display = section.style.display === 'none' ? 'block' : 'none';
                gsap.from(section, { opacity: 0, y: 10, duration: 0.3 });
            }
        }
        
        // Toggle reply form
        const replyToggle = e.target.closest('.reply-toggle');
        if (replyToggle) {
            const form = replyToggle.closest('.comment-item').querySelector('.reply-form');
            if (form) {
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
                if (form.style.display === 'block') {
                    form.querySelector('.reply-input')?.focus();
                }
            }
        }
    });

    // Submit comment
    document.addEventListener('click', function(e) {
        const submit = e.target.closest('.comment-submit');
        if (!submit) return;
        const input = submit.closest('.comment-form').querySelector('.comment-input');
        if (!input) return;
        const content = input.value.trim();
        if (!content) return;
        const postId = input.dataset.postId;
        if (!USER_ID) { showToast('Please login', 'warning'); return; }
        
        fetch('api/comment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=add&post_id=${postId}&content=${encodeURIComponent(content)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                showToast('Comment added!', 'success');
                // Reload the post card to show new comment
                fetchPostCard(postId);
            }
        });
    });

    // Submit reply
    document.addEventListener('click', function(e) {
        const submit = e.target.closest('.reply-submit');
        if (!submit) return;
        const input = submit.closest('.reply-form').querySelector('.reply-input');
        if (!input) return;
        const content = input.value.trim();
        if (!content) return;
        const parentId = input.dataset.commentId;
        const postId = submit.closest('.post-card').dataset.postId;
        if (!USER_ID) { showToast('Please login', 'warning'); return; }
        
        fetch('api/comment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=add&post_id=${postId}&content=${encodeURIComponent(content)}&parent_id=${parentId}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                showToast('Reply added!', 'success');
                fetchPostCard(postId);
            }
        });
    });

    // ============================================================
    // FETCH POST CARD (for comment refresh)
    // ============================================================
    function fetchPostCard(postId) {
        fetch(`feed.php?ajax=1&action=get_post&post_id=${postId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const oldCard = document.querySelector(`.post-card[data-post-id="${postId}"]`);
                if (oldCard) {
                    const temp = document.createElement('div');
                    temp.innerHTML = data.html;
                    const newCard = temp.firstElementChild;
                    oldCard.replaceWith(newCard);
                    gsap.from(newCard, { opacity: 0, y: 20, duration: 0.5 });
                }
            }
        });
    }

    // ============================================================
    // BOOKMARK
    // ============================================================
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.bookmark-btn');
        if (!btn) return;
        const postId = btn.dataset.postId;
        if (!USER_ID) { showToast('Please login', 'warning'); return; }
        
        fetch('api/bookmark.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `post_id=${postId}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const icon = btn.querySelector('i');
                if (data.action === 'added') {
                    icon.className = 'bi bi-bookmark-fill text-warning';
                    showToast('Bookmarked!', 'success');
                } else {
                    icon.className = 'bi bi-bookmark';
                    showToast('Removed', 'info');
                }
            }
        });
    });

    // ============================================================
    // SHARE
    // ============================================================
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.share-btn');
        if (!btn) return;
        const postId = btn.dataset.postId;
        const url = window.location.origin + '/post.php?id=' + postId;
        navigator.clipboard?.writeText(url).then(() => {
            showToast('Link copied!', 'success');
        }).catch(() => {
            // Fallback
            const input = document.createElement('input');
            input.value = url;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            input.remove();
            showToast('Link copied!', 'success');
        });
    });

    // ============================================================
    // TOAST HELPER
    // ============================================================
    function showToast(message, type = 'info') {
        const colors = {
            info: 'linear-gradient(135deg, #5e7564, #c9a76b)',
            success: 'linear-gradient(135deg, #7fa383, #a9c2ae)',
            warning: 'linear-gradient(135deg, #d9a441, #e3a97a)',
            danger: 'linear-gradient(135deg, #c96a63, #a8534c)'
        };
        Toastify({
            text: message,
            duration: 3000,
            gravity: 'bottom',
            position: 'right',
            style: { background: colors[type] || colors.info, borderRadius: '15px' }
        }).showToast();
    }

    // ============================================================
    // INFINITE SCROLL
    // ============================================================
    let isLoading = false;
    let hasMore = true;
    let offset = <?= count($posts) ?>;
    const limit = 6;
    let currentMood = '';
    let currentCategory = '';

    const loader = document.getElementById('feedLoader');
    const endMsg = document.getElementById('feedEnd');
    const feed = document.getElementById('feedContainer');

    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !isLoading && hasMore) {
            loadMorePosts();
        }
    }, { rootMargin: '200px' });

    function loadMorePosts() {
        isLoading = true;
        loader.style.display = 'block';
        let url = `feed.php?ajax=1&action=get_feed&offset=${offset}&limit=${limit}`;
        if (currentMood) url += `&mood=${encodeURIComponent(currentMood)}`;
        if (currentCategory) url += `&category=${encodeURIComponent(currentCategory)}`;
        
        fetch(url)
        .then(res => res.json())
        .then(data => {
            loader.style.display = 'none';
            if (data.success && data.html) {
                feed.insertAdjacentHTML('beforeend', data.html);
                offset += limit;
                hasMore = data.has_more;
                if (!hasMore) endMsg.style.display = 'block';
                // Animate new cards
                feed.querySelectorAll('.post-card:not(.animated)').forEach((card, i) => {
                    card.classList.add('animated');
                    gsap.from(card, {
                        y: 30, opacity: 0, duration: 0.5, delay: i * 0.05, ease: 'power2.out'
                    });
                });
            } else {
                hasMore = false;
                endMsg.style.display = 'block';
            }
            isLoading = false;
            if (hasMore) observer.observe(loader);
        })
        .catch(() => {
            loader.style.display = 'none';
            isLoading = false;
        });
    }

    if (hasMore) observer.observe(loader);

    // ============================================================
    // FILTERS
    // ============================================================
    document.querySelectorAll('.mood-filter').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.mood-filter').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentMood = this.dataset.mood;
            resetFeed();
        });
    });

    document.querySelectorAll('.category-filter').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.category-filter').forEach(b => b.style.color = 'var(--text-muted)');
            this.style.color = 'var(--primary)';
            currentCategory = this.dataset.category;
            resetFeed();
        });
    });

    function resetFeed() {
        offset = 0;
        hasMore = true;
        feed.innerHTML = '';
        endMsg.style.display = 'none';
        loadMorePosts();
    }

    // ============================================================
    // LIVE POLL VOTING
    // ============================================================
    document.querySelectorAll('.poll-option').forEach(opt => {
        opt.addEventListener('click', function() {
            if (!USER_ID) { showToast('Please login to vote', 'warning'); return; }
            const pollId = this.dataset.pollId;
            const optionId = this.dataset.optionId;
            fetch('community.php?ajax=1&action=poll_vote', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `poll_id=${pollId}&option_id=${optionId}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Vote recorded!', 'success');
                    // Refresh poll
                    location.reload();
                }
            });
        });
    });

    // ============================================================
    // LIVE STATS POLLING
    // ============================================================
    function updateStats() {
        fetch('feed.php?ajax=1&action=get_stats')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const s = data.stats;
                document.getElementById('statOnline').textContent = s.online || 0;
                document.getElementById('statPosts').textContent = s.today_posts || 0;
                document.getElementById('statAI').textContent = s.ai_responses || 0;
                document.getElementById('statVolunteer').textContent = s.volunteer_replies || 0;
                document.getElementById('statSupport').textContent = s.support_given || 0;
            }
        });
    }

    function updateActivity() {
        fetch('feed.php?ajax=1&action=get_activity')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.activities) {
                const list = document.getElementById('activityList');
                list.innerHTML = data.activities.map(a => `
                    <div class="activity-item">
                        <span>${a.type === 'post' ? '📝' : '💬'}</span>
                        <strong>${a.user_name}</strong>
                        <span class="text-muted small">${a.text ? a.text.substring(0, 30) + '...' : ''}</span>
                        <span class="time">${a.time_ago}</span>
                    </div>
                `).join('');
            }
        });
    }

    setInterval(updateStats, 5000);
    setInterval(updateActivity, 8000);
    updateStats();
    updateActivity();

    // ============================================================
    // NEW POSTS POLLING
    // ============================================================
    let lastPostId = <?= !empty($posts) ? $posts[0]['id'] : 0 ?>;
    let newPostsCount = 0;
    let newPostsData = [];

    function checkNewPosts() {
        fetch(`feed.php?ajax=1&action=get_feed&offset=0&limit=1`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.html) {
                // Check if we have new posts
                const temp = document.createElement('div');
                temp.innerHTML = data.html;
                const newCard = temp.querySelector('.post-card');
                if (newCard) {
                    const newId = parseInt(newCard.dataset.postId);
                    if (newId > lastPostId) {
                        newPostsCount++;
                        newPostsData.push(newCard.outerHTML);
                        document.getElementById('newPostsCount').textContent = newPostsCount;
                        document.getElementById('newPostsBanner').style.display = 'block';
                        gsap.from('#newPostsBanner', { opacity: 0, y: -10, duration: 0.3 });
                    }
                }
            }
        });
    }

    document.getElementById('newPostsBanner')?.addEventListener('click', function() {
        if (newPostsData.length) {
            newPostsData.reverse().forEach(html => {
                const temp = document.createElement('div');
                temp.innerHTML = html;
                const card = temp.firstElementChild;
                feed.prepend(card);
                gsap.from(card, { y: -20, opacity: 0, duration: 0.5 });
                lastPostId = parseInt(card.dataset.postId);
            });
            newPostsData = [];
            newPostsCount = 0;
            this.style.display = 'none';
            showToast('New posts loaded!', 'info');
        }
    });

    setInterval(checkNewPosts, 10000);

    // ============================================================
    // FLOATING BUTTONS
    // ============================================================
    document.getElementById('floatChat')?.addEventListener('click', function() {
        <?php if ($user_id): ?>
            window.location.href = 'chatbot.php';
        <?php else: ?>
            window.location.href = 'login.php';
        <?php endif; ?>
    });

    document.getElementById('floatCreate')?.addEventListener('click', function() {
        document.getElementById('createPostCollapsed')?.click();
        document.getElementById('postContent')?.focus();
    });

    // ============================================================
    // SEARCH (Live)
    // ============================================================
    const searchInput = document.getElementById('globalSearch');
    searchInput?.addEventListener('input', function() {
        const q = this.value.trim();
        if (q.length < 2) return;
        // Simple redirect to search page
        // Or you could implement live search results
        window.location.href = `search.php?q=${encodeURIComponent(q)}`;
    });

    // ============================================================
    // KEYBOARD SHORTCUTS
    // ============================================================
    document.addEventListener('keydown', function(e) {
        // 'n' for new post
        if (e.key === 'n' && !e.ctrlKey && !e.metaKey && !e.target.closest('input,textarea')) {
            if (<?= $user_id ? 'true' : 'false' ?>) {
                document.getElementById('createPostCollapsed')?.click();
                setTimeout(() => document.getElementById('postContent')?.focus(), 400);
            }
        }
        // '/' for search
        if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.target.closest('input,textarea')) {
            e.preventDefault();
            document.getElementById('globalSearch')?.focus();
        }
        // 'Esc' to close expanded create post
        if (e.key === 'Escape') {
            const expanded = document.getElementById('createPostExpanded');
            if (expanded && expanded.style.display === 'block') {
                expanded.style.display = 'none';
                gsap.to(expanded, { height: 0, duration: 0.3 });
            }
        }
    });

    console.log('🌿 Haven Living Feed loaded.');
</script>
<nav class="mobile-bottom-nav">
    <a href="index.php"><i class="bi bi-house"></i>Home</a>
    <a href="community.php"><i class="bi bi-globe2"></i>Community</a>
    <a href="feed.php" class="active"><i class="bi bi-plus-circle-fill"></i>Feed</a>
    <a href="chatbot.php"><i class="bi bi-robot"></i>AI</a>
    <a href="dashboard.php" style="position:relative;">
        <?php if ($unread_notifs > 0): ?><span class="mbn-badge"><?= $unread_notifs > 9 ? '9+' : $unread_notifs ?></span><?php endif; ?><i class="bi bi-person"></i>Me</a>
</nav>
<script src="assets/js/haven-emotion.js?v=3"></script>
</body>
</html>