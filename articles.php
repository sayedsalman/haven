<?php
// ============================================================
// articles.php – Digital Wellness Magazine
// ============================================================
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
include 'includes/functions.php';

$user_id = isLoggedIn() ? $_SESSION['user_id'] : 0;
$anon_name = $user_id ? getAnonymousName($user_id, $pdo) : 'Guest';
$unread_notifs = $user_id ? getUnreadNotifications($user_id, $pdo) : 0;

// Turns plain-text article content (with blank-line paragraphs and
// "## Heading" style subheadings) into readable HTML. Article content
// is authored as plain text, not pre-built HTML, so it must be run
// through this before being echoed — echoing it raw collapses all
// paragraph breaks and headings into one unreadable block, which is
// especially noticeable on longer articles.
function render_article_body_html($raw) {
    if ($raw === null || $raw === '') return '';
    // If the content already looks like real HTML (contains block tags),
    // trust it as-is rather than double-processing.
    if (preg_match('/<(p|div|h[1-6]|ul|ol|br)\b/i', $raw)) {
        return $raw;
    }
    $blocks = preg_split('/\n\s*\n/', trim($raw));
    $html = '';
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') continue;
        if (strpos($block, '## ') === 0) {
            $html .= '<h3>' . escape(substr($block, 3)) . '</h3>';
        } else {
            $html .= '<p>' . nl2br(escape($block)) . '</p>';
        }
    }
    return $html;
}

// ============================================================
// AJAX Handlers
// ============================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    ob_clean();
    
    $action = $_GET['action'] ?? '';
    $response = ['success' => false, 'error' => 'Invalid action'];

    try {
        // ---- Search articles ----
        if ($action === 'search') {
            $q = '%' . trim($_GET['q']) . '%';
            $stmt = $pdo->prepare("
                SELECT id, title, slug, excerpt, thumbnail, category, reading_time, 
                       published_at, views, likes, bookmarks_count
                FROM articles 
                WHERE (title LIKE ? OR content LIKE ? OR excerpt LIKE ? OR category LIKE ?)
                  AND is_published = 1
                ORDER BY published_at DESC LIMIT 10
            ");
            $stmt->execute([$q, $q, $q, $q]);
            $articles = $stmt->fetchAll();
            $response = ['success' => true, 'articles' => $articles];
        }

        // ---- Get articles (filtered/paginated) ----
        if ($action === 'get_articles') {
            $limit = intval($_GET['limit'] ?? 6);
            $offset = intval($_GET['offset'] ?? 0);
            $category = $_GET['category'] ?? '';
            $sort = $_GET['sort'] ?? 'latest';
            
            $sql = "SELECT * FROM articles WHERE is_published = 1";
            $params = [];
            
            if ($category) {
                $sql .= " AND category = ?";
                $params[] = $category;
            }
            
            if ($sort === 'trending') {
                $sql .= " ORDER BY views DESC, likes DESC";
            } else {
                $sql .= " ORDER BY published_at DESC";
            }
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $articles = $stmt->fetchAll();
            
            $hasMore = count($articles) === $limit;
            $response = ['success' => true, 'articles' => $articles, 'has_more' => $hasMore];
        }

        // ---- Get article detail ----
        if ($action === 'get_article') {
            $slug = $_GET['slug'] ?? '';
            $stmt = $pdo->prepare("SELECT * FROM articles WHERE slug = ? AND is_published = 1");
            $stmt->execute([$slug]);
            $article = $stmt->fetch();
            
            if (!$article) {
                $response = ['success' => false, 'error' => 'Article not found'];
            } else {
                // Increment views
                $stmt = $pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?");
                $stmt->execute([$article['id']]);
                $response = ['success' => true, 'article' => $article];
            }
        }

        // ---- Bookmark article ----
        if ($action === 'bookmark') {
            if (!$user_id) { throw new Exception('Login required'); }
            $article_id = intval($_POST['article_id']);
            
            // Check if already bookmarked
            $stmt = $pdo->prepare("SELECT id FROM article_bookmarks WHERE user_id = ? AND article_id = ?");
            $stmt->execute([$user_id, $article_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $pdo->prepare("DELETE FROM article_bookmarks WHERE id = ?");
                $stmt->execute([$existing['id']]);
                $stmt = $pdo->prepare("UPDATE articles SET bookmarks_count = bookmarks_count - 1 WHERE id = ?");
                $stmt->execute([$article_id]);
                $response = ['success' => true, 'action' => 'removed'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO article_bookmarks (user_id, article_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $article_id]);
                $stmt = $pdo->prepare("UPDATE articles SET bookmarks_count = bookmarks_count + 1 WHERE id = ?");
                $stmt->execute([$article_id]);
                $response = ['success' => true, 'action' => 'added'];
            }
        }

        // ---- Get reading progress ----
        if ($action === 'save_progress') {
            if (!$user_id) { throw new Exception('Login required'); }
            $article_id = intval($_POST['article_id']);
            $progress = intval($_POST['progress']);
            
            $stmt = $pdo->prepare("INSERT INTO article_progress (user_id, article_id, progress, updated_at) 
                                   VALUES (?, ?, ?, NOW()) 
                                   ON DUPLICATE KEY UPDATE progress = ?, updated_at = NOW()");
            $stmt->execute([$user_id, $article_id, $progress, $progress]);
            $response = ['success' => true];
        }

        // ---- Get reading progress ----
        if ($action === 'get_progress') {
            if (!$user_id) { throw new Exception('Login required'); }
            $article_id = intval($_GET['article_id']);
            $stmt = $pdo->prepare("SELECT progress FROM article_progress WHERE user_id = ? AND article_id = ?");
            $stmt->execute([$user_id, $article_id]);
            $progress = $stmt->fetch();
            $response = ['success' => true, 'progress' => $progress['progress'] ?? 0];
        }

        // ---- Get related articles ----
        if ($action === 'get_related') {
            $article_id = intval($_GET['article_id']);
            $category = $_GET['category'] ?? '';
            
            $stmt = $pdo->prepare("
                SELECT * FROM articles 
                WHERE is_published = 1 AND id != ? AND category = ?
                ORDER BY views DESC, published_at DESC LIMIT 3
            ");
            $stmt->execute([$article_id, $category]);
            $related = $stmt->fetchAll();
            $response = ['success' => true, 'related' => $related];
        }

        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ============================================================
// Page Data
// ============================================================
$page = isset($_GET['page']) ? $_GET['page'] : 'list';
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

// --- For article list page ---
$categories = $pdo->query("
    SELECT category, COUNT(*) as count 
    FROM articles 
    WHERE is_published = 1 AND category != '' 
    GROUP BY category 
    ORDER BY count DESC
")->fetchAll();

// Get featured article (most viewed in last 30 days or highest rated)
$featured = $pdo->query("
    SELECT * FROM articles 
    WHERE is_published = 1 
    ORDER BY views DESC, published_at DESC LIMIT 1
")->fetch();

// Get trending articles (last 7 days, most views)
$trending = $pdo->query("
    SELECT * FROM articles 
    WHERE is_published = 1 
      AND published_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY views DESC LIMIT 4
")->fetchAll();

// Get latest articles
$latest = $pdo->query("
    SELECT * FROM articles 
    WHERE is_published = 1 
    ORDER BY published_at DESC LIMIT 6
")->fetchAll();

// Get user's bookmarks
$bookmarked_ids = [];
if ($user_id) {
    $stmt = $pdo->prepare("SELECT article_id FROM article_bookmarks WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $bookmarked_ids = array_column($stmt->fetchAll(), 'article_id');
}

// Get daily wellness pick
$daily_pick = $pdo->query("
    SELECT * FROM articles 
    WHERE is_published = 1 
    ORDER BY RAND() LIMIT 1
")->fetch();

// Get reading progress
$reading_progress = [];
if ($user_id) {
    $stmt = $pdo->prepare("SELECT article_id, progress FROM article_progress WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $reading_progress = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Get article for single view
$article = null;
$related = [];
if ($page === 'read' && $slug) {
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE slug = ? AND is_published = 1");
    $stmt->execute([$slug]);
    $article = $stmt->fetch();
    
    if ($article) {
        // Increment views
        $stmt = $pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?");
        $stmt->execute([$article['id']]);
        
        // Get related articles
        if ($article['category']) {
            $stmt = $pdo->prepare("
                SELECT * FROM articles 
                WHERE is_published = 1 AND id != ? AND category = ?
                ORDER BY views DESC, published_at DESC LIMIT 3
            ");
            $stmt->execute([$article['id'], $article['category']]);
            $related = $stmt->fetchAll();
        }
        
        // Get user progress
        $user_progress = 0;
        if ($user_id) {
            $stmt = $pdo->prepare("SELECT progress FROM article_progress WHERE user_id = ? AND article_id = ?");
            $stmt->execute([$user_id, $article['id']]);
            $row = $stmt->fetch();
            $user_progress = $row['progress'] ?? 0;
        }
    }
}

// Random wellness quote for sidebar
$quotes = [
    "Your mental health is a priority. Your happiness is essential. Your self-care is a necessity.",
    "Healing takes time. Be patient with yourself.",
    "You are not your thoughts. You are not your feelings. You are the observer of them.",
    "Small steps lead to big changes. Keep going.",
    "Rest is not a reward for productivity; it's a requirement for sustainability.",
    "You are enough, just as you are.",
];
$quote = $quotes[array_rand($quotes)];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page === 'read' && $article ? escape($article['title']) . ' – Haven Articles' : 'Wellness Library – Haven' ?></title>
<link rel="icon" href="logo.png" type="image/png">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&family=Merriweather:wght@300;400;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- GSAP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/SplitText.min.js"></script>
    
    <!-- Toastify -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    
    <style>
        /* ============================================================
           CSS VARIABLES – CALM, MINIMAL THEME
           ============================================================ */
        :root {
            --bg: #FFFDF8;
            --bg-card: #FFFFFF;
            --bg-hover: #F5F2ED;
            --border: #ECE6DC;
            --border-light: #F0EDE7;
            --primary: #6B7A5C;
            --primary-light: #8A9B7A;
            --secondary: #A88F6D;
            --text: #2F2F2F;
            --text-muted: #757575;
            --text-light: #B8B8B8;
            --accent: #D7C8B0;
            --accent-light: #E8DDD0;
            --shadow: 0 2px 12px rgba(0,0,0,0.04);
            --shadow-hover: 0 8px 30px rgba(0,0,0,0.08);
            --radius: 12px;
            --radius-sm: 8px;
            --font-serif: 'Merriweather', serif;
            --font-display: 'Playfair Display', serif;
            --font-sans: 'Inter', sans-serif;
            --max-width: 850px;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* ============================================================
           BASE
           ============================================================ */
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: var(--font-sans);
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            transition: background 0.3s;
        }
        h1,h2,h3,h4,h5,h6 { font-family: var(--font-display); font-weight: 500; color: var(--text); }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; }
        img { max-width: 100%; display: block; }
        
        /* ============================================================
           SCROLLBAR
           ============================================================ */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--secondary); border-radius: 10px; }
        
        /* ============================================================
           NAVBAR
           ============================================================ */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            padding: 0.8rem 2rem;
            background: rgba(255, 253, 248, 0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all var(--transition);
        }
        .navbar.scrolled {
            box-shadow: var(--shadow);
        }
        .navbar .brand {
            font-family: var(--font-display);
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary);
        }
        .navbar .brand i { margin-right: 6px; color: var(--secondary); }
        .navbar .nav-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .navbar .nav-links a {
            font-size: 0.9rem;
            color: var(--text-muted);
            transition: var(--transition);
        }
        .navbar .nav-links a:hover { color: var(--primary); }
        .navbar .nav-links .btn {
            padding: 0.4rem 1.2rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
        }
        .navbar .nav-links .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        .navbar .nav-links .btn-primary:hover { background: var(--primary-light); }
        .navbar .nav-links .btn-outline {
            border: 1.5px solid var(--border);
            color: var(--text);
        }
        .navbar .nav-links .btn-outline:hover { background: var(--bg-hover); }
        .navbar .nav-toggle {
            display: none;
            font-size: 1.4rem;
            color: var(--text);
        }
        @media (max-width: 768px) {
            .navbar { padding: 0.6rem 1rem; }
            .navbar .nav-links { display: none; }
            .navbar .nav-links.open { display: flex; flex-direction: column; position: absolute; top: 100%; left: 0; right: 0; background: var(--bg); padding: 1rem; border-bottom: 1px solid var(--border); }
            .navbar .nav-toggle { display: block; }
        }
        
        /* ============================================================
           READING PROGRESS BAR
           ============================================================ */
        #readingProgress {
            position: fixed;
            top: 65px;
            left: 0;
            width: 0%;
            height: 3px;
            background: var(--primary);
            z-index: 1049;
            transition: width 0.1s;
        }
        
        /* ============================================================
           MAIN LAYOUT
           ============================================================ */
        .main-wrapper {
            padding-top: 75px;
            min-height: 100vh;
        }
        
        /* ============================================================
           HERO (List Page)
           ============================================================ */
        .hero {
            padding: 4rem 2rem 3rem;
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }
        .hero h1 {
            font-size: 3.2rem;
            font-weight: 600;
            line-height: 1.2;
            margin-bottom: 0.5rem;
        }
        .hero h1 span { color: var(--secondary); }
        .hero p {
            font-size: 1.15rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
        }
        .hero .search-box {
            max-width: 500px;
            margin: 0 auto;
            position: relative;
        }
        .hero .search-box input {
            width: 100%;
            padding: 0.8rem 1.2rem 0.8rem 3rem;
            border: 2px solid var(--border);
            border-radius: 50px;
            font-size: 1rem;
            background: var(--bg-card);
            color: var(--text);
            transition: var(--transition);
            font-family: var(--font-sans);
        }
        .hero .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(107,122,92,0.1);
        }
        .hero .search-box .icon {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        .hero .search-box .suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-card);
            border: 2px solid var(--border);
            border-top: none;
            border-radius: 0 0 20px 20px;
            display: none;
            max-height: 300px;
            overflow-y: auto;
            z-index: 10;
            box-shadow: var(--shadow);
        }
        .hero .search-box .suggestions .item {
            padding: 0.6rem 1.2rem;
            border-bottom: 1px solid var(--border-light);
            cursor: pointer;
            transition: var(--transition);
        }
        .hero .search-box .suggestions .item:hover { background: var(--bg-hover); }
        .hero .search-box .suggestions .item .category { font-size: 0.7rem; color: var(--text-muted); }
        
        /* ============================================================
           CATEGORIES PILLS
           ============================================================ */
        .categories {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.6rem;
            padding: 1rem 2rem 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        .categories .pill {
            padding: 0.4rem 1.2rem;
            border-radius: 30px;
            border: 1.5px solid var(--border);
            font-size: 0.85rem;
            color: var(--text-muted);
            transition: var(--transition);
            cursor: pointer;
            font-family: var(--font-sans);
        }
        .categories .pill:hover,
        .categories .pill.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        /* ============================================================
           SECTION CONTAINER
           ============================================================ */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem 3rem;
        }
        .section-title {
            font-size: 1.6rem;
            font-weight: 500;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .section-title .view-all {
            margin-left: auto;
            font-size: 0.85rem;
            font-family: var(--font-sans);
            color: var(--text-muted);
            transition: var(--transition);
        }
        .section-title .view-all:hover { color: var(--primary); }
        
        /* ============================================================
           FEATURED ARTICLE
           ============================================================ */
        .featured-card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2.5rem;
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border-light);
            overflow: hidden;
            margin-bottom: 3rem;
            transition: var(--transition);
        }
        .featured-card:hover { box-shadow: var(--shadow-hover); }
        .featured-card .image {
            height: 100%;
            min-height: 280px;
            background: var(--accent-light);
            background-size: cover;
            background-position: center;
            transition: transform 0.6s ease;
            overflow: hidden;
        }
        .featured-card .image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }
        .featured-card:hover .image img { transform: scale(1.03); }
        .featured-card .content {
            padding: 2rem 2rem 2rem 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .featured-card .content .badge {
            display: inline-block;
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            background: var(--accent-light);
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            align-self: flex-start;
        }
        .featured-card .content h2 {
            font-size: 1.8rem;
            line-height: 1.3;
            margin-bottom: 0.5rem;
        }
        .featured-card .content p {
            color: var(--text-muted);
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 1rem;
        }
        .featured-card .content .meta {
            display: flex;
            gap: 1.2rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .featured-card .content .meta i { margin-right: 0.2rem; }
        .featured-card .content .read-btn {
            align-self: flex-start;
            padding: 0.5rem 1.8rem;
            border-radius: 30px;
            background: var(--primary);
            color: #fff;
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition);
            margin-top: 0.5rem;
        }
        .featured-card .content .read-btn:hover {
            background: var(--primary-light);
            transform: translateX(4px);
        }
        @media (max-width: 768px) {
            .featured-card { grid-template-columns: 1fr; }
            .featured-card .content { padding: 1.5rem; }
            .featured-card .image { min-height: 200px; }
        }
        
        /* ============================================================
           ARTICLE GRID
           ============================================================ */
        .article-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.8rem;
            margin-bottom: 2rem;
        }
        
        .article-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border-light);
            overflow: hidden;
            transition: var(--transition);
            cursor: default;
        }
        .article-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-4px);
        }
        .article-card .thumb {
            height: 180px;
            background: var(--accent-light);
            overflow: hidden;
        }
        .article-card .thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .article-card:hover .thumb img { transform: scale(1.05); }
        .article-card .body {
            padding: 1.2rem 1.4rem 1.4rem;
        }
        .article-card .body .category {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--secondary);
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 0.3rem;
        }
        .article-card .body h3 {
            font-size: 1.1rem;
            line-height: 1.3;
            margin-bottom: 0.4rem;
        }
        .article-card .body h3 a:hover { color: var(--primary); }
        .article-card .body .excerpt {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .article-card .body .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 0.8rem;
            padding-top: 0.8rem;
            border-top: 1px solid var(--border-light);
        }
        .article-card .body .meta i { margin-right: 0.2rem; }
        .article-card .body .meta .bookmark-btn {
            margin-left: auto;
            transition: var(--transition);
        }
        .article-card .body .meta .bookmark-btn:hover { color: var(--secondary); }
        .article-card .body .meta .bookmark-btn.active { color: var(--primary); }
        .article-card .body .progress-bar {
            height: 3px;
            background: var(--border-light);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        .article-card .body .progress-bar .fill {
            height: 100%;
            background: var(--primary);
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        
        /* ============================================================
           AI RECOMMENDATION BANNER
           ============================================================ */
        .ai-recommend {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 1.8rem 2rem;
            margin: 2rem 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .ai-recommend .left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .ai-recommend .left .icon {
            font-size: 2.2rem;
            color: var(--secondary);
        }
        .ai-recommend .left .text h4 { font-size: 1.1rem; }
        .ai-recommend .left .text p { font-size: 0.9rem; color: var(--text-muted); }
        .ai-recommend .btn {
            padding: 0.4rem 1.6rem;
            border-radius: 30px;
            background: var(--primary);
            color: #fff;
            font-weight: 500;
            transition: var(--transition);
        }
        .ai-recommend .btn:hover { background: var(--primary-light); transform: scale(1.02); }
        
        /* ============================================================
           DAILY WELLNESS PICK
           ============================================================ */
        .daily-pick {
            background: var(--accent-light);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .daily-pick .icon { font-size: 2.5rem; }
        .daily-pick .content h4 { font-size: 0.9rem; color: var(--secondary); text-transform: uppercase; letter-spacing: 1px; }
        .daily-pick .content h2 { font-size: 1.4rem; }
        .daily-pick .content p { color: var(--text-muted); font-size: 0.95rem; }
        .daily-pick .btn {
            padding: 0.4rem 1.6rem;
            border-radius: 30px;
            background: var(--primary);
            color: #fff;
            font-weight: 500;
            transition: var(--transition);
            margin-left: auto;
        }
        .daily-pick .btn:hover { background: var(--primary-light); }
        @media (max-width: 600px) { .daily-pick { flex-direction: column; text-align: center; } .daily-pick .btn { margin: 0; } }
        
        /* ============================================================
           NEWSLETTER
           ============================================================ */
        .newsletter {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 2.5rem 2rem;
            text-align: center;
            margin: 3rem 0;
        }
        .newsletter h3 { font-size: 1.5rem; margin-bottom: 0.3rem; }
        .newsletter p { color: var(--text-muted); margin-bottom: 1.5rem; }
        .newsletter .form { display: flex; gap: 0.8rem; max-width: 450px; margin: 0 auto; flex-wrap: wrap; justify-content: center; }
        .newsletter .form input {
            flex: 1;
            min-width: 200px;
            padding: 0.6rem 1.2rem;
            border: 2px solid var(--border);
            border-radius: 30px;
            font-size: 0.95rem;
            background: var(--bg);
            font-family: var(--font-sans);
        }
        .newsletter .form input:focus { outline: none; border-color: var(--primary); }
        .newsletter .form button {
            padding: 0.6rem 1.8rem;
            border-radius: 30px;
            background: var(--primary);
            color: #fff;
            font-weight: 500;
            transition: var(--transition);
        }
        .newsletter .form button:hover { background: var(--primary-light); }
        
        /* ============================================================
           FOOTER
           ============================================================ */
        .footer {
            border-top: 1px solid var(--border);
            padding: 2rem 2rem 1.5rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .footer .links { display: flex; justify-content: center; gap: 1.5rem; margin-bottom: 0.5rem; flex-wrap: wrap; }
        .footer .links a { transition: var(--transition); }
        .footer .links a:hover { color: var(--primary); }
        
        /* ============================================================
           SIDEBAR (for single article)
           ============================================================ */
        .article-sidebar {
            position: sticky;
            top: 90px;
        }
        .article-sidebar .widget {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.2rem;
        }
        .article-sidebar .widget h6 { font-family: var(--font-sans); font-weight: 600; font-size: 0.9rem; margin-bottom: 0.8rem; }
        .article-sidebar .widget .quote { font-style: italic; color: var(--text-muted); font-size: 0.95rem; }
        .article-sidebar .widget .quote .author { font-style: normal; font-size: 0.8rem; color: var(--text-light); margin-top: 0.3rem; }
        
        /* ============================================================
           SINGLE ARTICLE PAGE
           ============================================================ */
        .article-single {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 1.5rem 2rem 3rem;
        }
        .article-single .breadcrumb {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .article-single .breadcrumb a:hover { color: var(--primary); }
        .article-single .breadcrumb .sep { margin: 0 0.5rem; }
        
        .article-single .header {
            margin-bottom: 2rem;
        }
        .article-single .header .badge {
            display: inline-block;
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            background: var(--accent-light);
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        .article-single .header h1 {
            font-size: 2.6rem;
            line-height: 1.2;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .article-single .header .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.2rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .article-single .header .meta i { margin-right: 0.3rem; }
        .article-single .header .actions {
            display: flex;
            gap: 0.8rem;
            margin-top: 0.8rem;
        }
        .article-single .header .actions button {
            padding: 0.4rem 1.2rem;
            border-radius: 30px;
            border: 1.5px solid var(--border);
            font-size: 0.85rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .article-single .header .actions button:hover { background: var(--bg-hover); }
        .article-single .header .actions button.bookmarked { border-color: var(--primary); color: var(--primary); }
        .article-single .header .featured-image {
            margin: 1.5rem 0;
            border-radius: var(--radius);
            overflow: hidden;
        }
        .article-single .header .featured-image img {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
        }
        
        .article-single .body {
            font-family: var(--font-serif);
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--text);
        }
        .article-single .body h2, .article-single .body h3 {
            font-family: var(--font-display);
            margin-top: 1.8rem;
            margin-bottom: 0.5rem;
        }
        .article-single .body p { margin-bottom: 1.2rem; }
        .article-single .body blockquote {
            border-left: 4px solid var(--secondary);
            padding: 0.8rem 1.5rem;
            margin: 1.5rem 0;
            background: var(--bg-hover);
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
            font-style: italic;
            color: var(--text-muted);
        }
        .article-single .body .tip-box {
            background: var(--accent-light);
            border-radius: var(--radius-sm);
            padding: 1rem 1.5rem;
            margin: 1.5rem 0;
            display: flex;
            gap: 0.8rem;
            align-items: flex-start;
        }
        .article-single .body .tip-box .icon { font-size: 1.4rem; flex-shrink: 0; }
        .article-single .body .tip-box p { margin: 0; }
        .article-single .body .warning-box {
            background: #FFF8E1;
            border-radius: var(--radius-sm);
            padding: 1rem 1.5rem;
            margin: 1.5rem 0;
            border-left: 4px solid #FBBF24;
        }
        .article-single .body .warning-box p { margin: 0; }
        .article-single .body img {
            border-radius: var(--radius-sm);
            margin: 1.5rem 0;
            width: 100%;
        }
        
        .article-single .author-box {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            padding: 1.5rem;
            background: var(--bg-hover);
            border-radius: var(--radius);
            margin-top: 2rem;
        }
        .article-single .author-box .avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.4rem;
            font-weight: 600;
            flex-shrink: 0;
        }
        .article-single .author-box .info h5 { font-family: var(--font-sans); margin-bottom: 0.1rem; }
        .article-single .author-box .info p { font-size: 0.9rem; color: var(--text-muted); margin: 0; }
        
        .article-single .related {
            margin-top: 3rem;
            border-top: 1px solid var(--border);
            padding-top: 2rem;
        }
        .article-single .related h4 { font-size: 1.3rem; margin-bottom: 1.2rem; }
        
        .article-single .comments-section {
            margin-top: 2.5rem;
            border-top: 1px solid var(--border);
            padding-top: 2rem;
        }
        .article-single .comments-section .comment-form { display: flex; gap: 0.8rem; margin-bottom: 1.5rem; }
        .article-single .comments-section .comment-form input { flex: 1; padding: 0.6rem 1.2rem; border: 2px solid var(--border); border-radius: 30px; font-size: 0.95rem; background: var(--bg); font-family: var(--font-sans); }
        .article-single .comments-section .comment-form input:focus { outline: none; border-color: var(--primary); }
        .article-single .comments-section .comment-form button { padding: 0.6rem 1.8rem; border-radius: 30px; background: var(--primary); color: #fff; font-weight: 500; }
        .article-single .comments-section .comment-item { padding: 0.8rem 0; border-bottom: 1px solid var(--border-light); }
        .article-single .comments-section .comment-item .meta { font-size: 0.85rem; color: var(--text-muted); }
        .article-single .comments-section .comment-item .meta strong { color: var(--text); }
        .article-single .comments-section .comment-item p { margin-top: 0.2rem; font-size: 0.95rem; }
        
        /* ============================================================
           LOAD MORE
           ============================================================ */
        .load-more {
            text-align: center;
            padding: 1.5rem 0;
        }
        .load-more button {
            padding: 0.5rem 2rem;
            border-radius: 30px;
            border: 1.5px solid var(--border);
            background: var(--bg-card);
            font-size: 0.95rem;
            transition: var(--transition);
            color: var(--text);
        }
        .load-more button:hover { background: var(--bg-hover); border-color: var(--primary); }
        .load-more .loader { display: none; }
        .load-more .loader.show { display: inline-block; }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 992px) {
            .article-single { padding: 1rem; }
        }
        @media (max-width: 600px) {
            .hero h1 { font-size: 2.2rem; }
            .hero { padding: 2rem 1rem; }
            .container { padding: 0 1rem 2rem; }
            .article-grid { grid-template-columns: 1fr; }
            .article-single .header h1 { font-size: 1.8rem; }
            .article-single .header .actions { flex-wrap: wrap; }
            .navbar .brand { font-size: 1rem; }
            .daily-pick .content h2 { font-size: 1.1rem; }
        }
    
.mobile-bottom-nav { display: none; }
@media (max-width: 767px) {
    body { padding-bottom: 64px; }
    .mobile-bottom-nav {
        display: flex; position: fixed; left: 0; right: 0; bottom: 0; z-index: 1200; height: 60px;
        background: rgba(255,253,248,0.96); backdrop-filter: blur(16px);
        border-top: 1px solid rgba(94,117,100,.14); justify-content: space-around; align-items: center;
        box-shadow: 0 -6px 24px rgba(64,77,67,.08);
    }
    .mobile-bottom-nav a {
        flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 2px; color: #7c857e; font-size: .62rem; font-weight: 600; text-decoration: none;
        padding: 6px 0; position: relative;
    }
    .mobile-bottom-nav a i { font-size: 1.25rem; }
    .mobile-bottom-nav a.active { color: #5e7564; }
    .mobile-bottom-nav a .mbn-badge {
        position: absolute; top: 2px; right: 22%; background: #c96a63; color: #fff;
        border-radius: 8px; font-size: .55rem; padding: 0 4px; line-height: 1.3;
    }
}
</style>
</head>
<body>

<!-- ============================================================
   READING PROGRESS (single article)
   ============================================================ -->
<div id="readingProgress" style="display:<?= $page === 'read' ? 'block' : 'none' ?>;"></div>

<!-- ============================================================
   NAVBAR
   ============================================================ -->
<nav class="navbar" id="navbar">
    <div>
        <a href="index.php" class="brand"><img src="logo.png" alt="Haven" style="height:22px;width:22px;object-fit:cover;border-radius:6px;vertical-align:-5px;margin-right:4px;">Haven</a>
    </div>
    <div class="nav-links" id="navLinks">
        <a href="index.php"><i class="bi bi-house"></i> Home</a>
        <a href="community.php"><i class="bi bi-chat"></i> Community</a>
        <a href="articles.php" style="color:var(--primary);"><i class="bi bi-book"></i> Articles</a>
        <a href="chatbot.php"><i class="bi bi-robot"></i> AI Chat</a>
        <?php if ($user_id): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php" class="btn btn-outline">Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-primary">Login</a>
        <?php endif; ?>
    </div>
    <button class="nav-toggle" id="navToggle"><i class="bi bi-list"></i></button>
</nav>

<!-- ============================================================
   MAIN CONTENT
   ============================================================ -->
<div class="main-wrapper">

<?php if ($page === 'read' && $article): ?>
    
    <!-- ============================================================
       SINGLE ARTICLE VIEW
       ============================================================ -->
    <div class="article-single" data-article-id="<?= $article['id'] ?>">
        
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="articles.php">Articles</a>
            <span class="sep">/</span>
            <a href="?category=<?= urlencode($article['category']) ?>"><?= escape($article['category']) ?></a>
            <span class="sep">/</span>
            <span><?= escape($article['title']) ?></span>
        </div>
        
        <!-- Header -->
        <div class="header">
            <span class="badge"><?= escape($article['category']) ?></span>
            <h1><?= escape($article['title']) ?></h1>
            <div class="meta">
                <span><i class="bi bi-person"></i> <?= escape($article['author'] ?? 'Wellness Team') ?></span>
                <span><i class="bi bi-calendar3"></i> <?= date('M d, Y', strtotime($article['published_at'])) ?></span>
                <span><i class="bi bi-clock"></i> <?= $article['reading_time'] ?? 5 ?> min read</span>
                <span><i class="bi bi-eye"></i> <?= number_format($article['views']) ?></span>
                <span><i class="bi bi-heart"></i> <?= $article['likes'] ?? 0 ?></span>
                <span><i class="bi bi-bookmark"></i> <?= $article['bookmarks_count'] ?? 0 ?></span>
            </div>
            <div class="actions">
                <button class="bookmark-action <?= in_array($article['id'], $bookmarked_ids) ? 'bookmarked' : '' ?>" data-id="<?= $article['id'] ?>">
                    <i class="bi <?= in_array($article['id'], $bookmarked_ids) ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                    <?= in_array($article['id'], $bookmarked_ids) ? 'Saved' : 'Save' ?>
                </button>
                <button onclick="shareArticle()"><i class="bi bi-share"></i> Share</button>
                <button onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            </div>
            <?php if ($article['thumbnail']): ?>
                <div class="featured-image">
                    <img src="<?= escape($article['thumbnail']) ?>" alt="<?= escape($article['title']) ?>">
                </div>
            <?php endif; ?>
        </div>
        
        <!-- AI Summary (Top) -->
        <div class="ai-recommend" style="margin: 0 0 2rem;">
            <div class="left">
                <div class="icon"><i class="bi bi-robot"></i></div>
                <div class="text">
                    <h4>AI Quick Summary</h4>
                    <p><?= escape($article['excerpt'] ?? 'A summary of key insights from this article.') ?></p>
                </div>
            </div>
        </div>
        
        <!-- Reading Progress for user -->
        <?php if ($user_id && isset($user_progress) && $user_progress > 0 && $user_progress < 95): ?>
            <div style="background:var(--accent-light);border-radius:var(--radius-sm);padding:0.8rem 1.2rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.8rem;flex-wrap:wrap;">
                <i class="bi bi-clock-history"></i>
                <span>You left off at <strong><?= $user_progress ?>%</strong> of this article.</span>
                <button onclick="scrollToSavedPosition()" class="btn" style="padding:0.2rem 1.2rem;border-radius:30px;background:var(--primary);color:#fff;font-size:0.85rem;margin-left:auto;">Continue Reading</button>
            </div>
        <?php endif; ?>
        
        <!-- Article Body -->
        <div class="body" id="articleBody">
            <?= render_article_body_html($article['content']) ?>
        </div>
        
        <!-- Author Box -->
        <div class="author-box">
            <div class="avatar"><?= substr($article['author'] ?? 'W', 0, 1) ?></div>
            <div class="info">
                <h5><?= escape($article['author'] ?? 'Wellness Team') ?></h5>
                <p>Passionate about mental wellness and holistic health. Writing to help you find calm and clarity.</p>
            </div>
        </div>
        
        <!-- Comments -->
        <div class="comments-section">
            <h4>Discussion (<?= $pdo->query("SELECT COUNT(*) FROM article_comments WHERE article_id = {$article['id']}")->fetchColumn() ?? 0 ?>)</h4>
            <?php if ($user_id): ?>
                <div class="comment-form">
                    <input type="text" id="commentInput" placeholder="Share your thoughts...">
                    <button id="submitComment" data-id="<?= $article['id'] ?>">Post</button>
                </div>
            <?php else: ?>
                <p class="text-muted"><a href="login.php">Login</a> to join the discussion.</p>
            <?php endif; ?>
            <div id="commentList">
                <?php
                $comments = $pdo->prepare("
                    SELECT c.*, u.anonymous_name 
                    FROM article_comments c 
                    JOIN users u ON c.user_id = u.id 
                    WHERE c.article_id = ? 
                    ORDER BY c.created_at DESC LIMIT 20
                ");
                $comments->execute([$article['id']]);
                while ($c = $comments->fetch()):
                ?>
                    <div class="comment-item">
                        <div class="meta"><strong><?= escape($c['anonymous_name']) ?></strong> <span class="text-muted">• <?= timeAgo($c['created_at']) ?></span></div>
                        <p><?= nl2br(escape($c['comment'])) ?></p>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        
        <!-- Related Articles -->
        <?php if (!empty($related)): ?>
            <div class="related">
                <h4>You Might Also Like</h4>
                <div class="article-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr));">
                    <?php foreach ($related as $r): ?>
                        <div class="article-card">
                            <div class="thumb">
                                <?php if ($r['thumbnail']): ?>
                                    <img src="<?= escape($r['thumbnail']) ?>" alt="<?= escape($r['title']) ?>">
                                <?php else: ?>
                                    <div style="height:100%;background:var(--accent-light);display:flex;align-items:center;justify-content:center;color:var(--text-light);font-size:2rem;"><i class="bi bi-book"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="body">
                                <span class="category"><?= escape($r['category']) ?></span>
                                <h3><a href="articles.php?page=read&slug=<?= escape($r['slug']) ?>"><?= escape($r['title']) ?></a></h3>
                                <div class="meta">
                                    <span><i class="bi bi-clock"></i> <?= $r['reading_time'] ?? 5 ?> min</span>
                                    <span><i class="bi bi-eye"></i> <?= number_format($r['views']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>

    <!-- ============================================================
       ARTICLE LIST / MAGAZINE VIEW
       ============================================================ -->
    
    <!-- Hero -->
    <section class="hero">
        <h1>Wellness <span>Library</span></h1>
        <p>Small daily habits can make a big difference. Explore articles on mental health, sleep, stress, and more.</p>
        <div class="search-box">
            <span class="icon"><i class="bi bi-search"></i></span>
            <input type="text" id="searchInput" placeholder="Search articles..." autocomplete="off">
            <div class="suggestions" id="searchSuggestions"></div>
        </div>
    </section>

    <!-- Categories -->
    <div class="categories" id="categoryPills">
        <button class="pill active" data-category="">All</button>
        <?php foreach ($categories as $cat): ?>
            <button class="pill" data-category="<?= escape($cat['category']) ?>"><?= escape($cat['category']) ?> (<?= $cat['count'] ?>)</button>
        <?php endforeach; ?>
    </div>

    <!-- Daily Wellness Pick -->
    <?php if ($daily_pick): ?>
        <div class="container">
            <div class="daily-pick">
                <div class="icon">🌿</div>
                <div class="content">
                    <h4>Daily Wellness Pick</h4>
                    <h2><?= escape($daily_pick['title']) ?></h2>
                    <p><?= escape($daily_pick['excerpt'] ?? '') ?></p>
                </div>
                <a href="articles.php?page=read&slug=<?= escape($daily_pick['slug']) ?>" class="btn">Read Now</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="container">

        <!-- Featured Article -->
        <?php if ($featured): ?>
            <div class="featured-card">
                <div class="image">
                    <?php if ($featured['thumbnail']): ?>
                        <img src="<?= escape($featured['thumbnail']) ?>" alt="<?= escape($featured['title']) ?>">
                    <?php else: ?>
                        <div style="height:100%;background:var(--accent-light);display:flex;align-items:center;justify-content:center;color:var(--text-light);font-size:3rem;"><i class="bi bi-book"></i></div>
                    <?php endif; ?>
                </div>
                <div class="content">
                    <span class="badge">Featured</span>
                    <h2><?= escape($featured['title']) ?></h2>
                    <p><?= escape($featured['excerpt'] ?? substr(strip_tags($featured['content']), 0, 150)) ?>...</p>
                    <div class="meta">
                        <span><i class="bi bi-person"></i> <?= escape($featured['author'] ?? 'Wellness Team') ?></span>
                        <span><i class="bi bi-clock"></i> <?= $featured['reading_time'] ?? 5 ?> min read</span>
                        <span><i class="bi bi-eye"></i> <?= number_format($featured['views']) ?></span>
                    </div>
                    <a href="articles.php?page=read&slug=<?= escape($featured['slug']) ?>" class="read-btn">Read Article →</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Trending -->
        <?php if (!empty($trending)): ?>
            <h2 class="section-title">🔥 Trending Now <span class="view-all"><a href="#">View All</a></span></h2>
            <div class="article-grid">
                <?php foreach ($trending as $t): ?>
                    <div class="article-card">
                        <div class="thumb">
                            <?php if ($t['thumbnail']): ?>
                                <img src="<?= escape($t['thumbnail']) ?>" alt="<?= escape($t['title']) ?>">
                            <?php else: ?>
                                <div style="height:100%;background:var(--accent-light);display:flex;align-items:center;justify-content:center;color:var(--text-light);font-size:2rem;"><i class="bi bi-book"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="body">
                            <span class="category"><?= escape($t['category']) ?></span>
                            <h3><a href="articles.php?page=read&slug=<?= escape($t['slug']) ?>"><?= escape($t['title']) ?></a></h3>
                            <p class="excerpt"><?= escape($t['excerpt'] ?? substr(strip_tags($t['content']), 0, 80)) ?>...</p>
                            <div class="meta">
                                <span><i class="bi bi-clock"></i> <?= $t['reading_time'] ?? 5 ?> min</span>
                                <span><i class="bi bi-eye"></i> <?= number_format($t['views']) ?></span>
                                <button class="bookmark-btn <?= in_array($t['id'], $bookmarked_ids) ? 'active' : '' ?>" data-id="<?= $t['id'] ?>">
                                    <i class="bi <?= in_array($t['id'], $bookmarked_ids) ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                                </button>
                            </div>
                            <?php if (isset($reading_progress[$t['id']]) && $reading_progress[$t['id']] > 0 && $reading_progress[$t['id']] < 95): ?>
                                <div class="progress-bar">
                                    <div class="fill" style="width:<?= $reading_progress[$t['id']] ?>%;"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Latest Articles -->
        <h2 class="section-title">📖 Latest Articles <span class="view-all"><a href="#" id="viewAllLatest">View All</a></span></h2>
        <div class="article-grid" id="articleGrid">
            <?php foreach ($latest as $a): ?>
                <div class="article-card" data-category="<?= escape($a['category']) ?>">
                    <div class="thumb">
                        <?php if ($a['thumbnail']): ?>
                            <img src="<?= escape($a['thumbnail']) ?>" alt="<?= escape($a['title']) ?>" loading="lazy">
                        <?php else: ?>
                            <div style="height:100%;background:var(--accent-light);display:flex;align-items:center;justify-content:center;color:var(--text-light);font-size:2rem;"><i class="bi bi-book"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="body">
                        <span class="category"><?= escape($a['category']) ?></span>
                        <h3><a href="articles.php?page=read&slug=<?= escape($a['slug']) ?>"><?= escape($a['title']) ?></a></h3>
                        <p class="excerpt"><?= escape($a['excerpt'] ?? substr(strip_tags($a['content']), 0, 80)) ?>...</p>
                        <div class="meta">
                            <span><i class="bi bi-clock"></i> <?= $a['reading_time'] ?? 5 ?> min</span>
                            <span><i class="bi bi-eye"></i> <?= number_format($a['views']) ?></span>
                            <button class="bookmark-btn <?= in_array($a['id'], $bookmarked_ids) ? 'active' : '' ?>" data-id="<?= $a['id'] ?>">
                                <i class="bi <?= in_array($a['id'], $bookmarked_ids) ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                            </button>
                        </div>
                        <?php if (isset($reading_progress[$a['id']]) && $reading_progress[$a['id']] > 0 && $reading_progress[$a['id']] < 95): ?>
                            <div class="progress-bar">
                                <div class="fill" style="width:<?= $reading_progress[$a['id']] ?>%;"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- AI Recommendation (after 6 articles) -->
        <div class="ai-recommend">
            <div class="left">
                <div class="icon"><i class="bi bi-robot"></i></div>
                <div class="text">
                    <h4>Recommended For You</h4>
                    <p>Based on your mood and reading history, we think you'll enjoy:</p>
                </div>
            </div>
            <a href="articles.php?page=read&slug=<?= $daily_pick['slug'] ?? '' ?>" class="btn">View Recommendations</a>
        </div>

        <!-- Newsletter -->
        <div class="newsletter">
            <h3>📬 Stay Updated</h3>
            <p>Get the latest wellness articles delivered to your inbox.</p>
            <div class="form">
                <input type="email" placeholder="Your email address">
                <button>Subscribe</button>
            </div>
        </div>

    </div>

<?php endif; ?>

<!-- ============================================================
   FOOTER
   ============================================================ -->
<footer class="footer">
    <div class="links">
        <a href="articles.php">Articles</a>
        <a href="community.php">Community</a>
        <a href="about.php">About</a>
        <a href="privacy.php">Privacy</a>
        <a href="terms.php">Terms</a>
        <a href="contact.php">Contact</a>
    </div>
    <p>© <?= date('Y') ?> Haven – Your Digital Wellness Companion</p>
</footer>

</div><!-- end main-wrapper -->

<!-- ============================================================
   SCRIPTS
   ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ============================================================
    // GLOBALS
    // ============================================================
    const USER_ID = <?= $user_id ?: 0 ?>;
    const PAGE = '<?= $page ?>';
    const ARTICLE_ID = <?= $page === 'read' && $article ? $article['id'] : 0 ?>;
    const MAX_PROGRESS = <?= $page === 'read' && $article && isset($user_progress) ? $user_progress : 0 ?>;

    // ============================================================
    // NAVBAR TOGGLE
    // ============================================================
    document.getElementById('navToggle')?.addEventListener('click', function() {
        document.getElementById('navLinks').classList.toggle('open');
    });

    // ============================================================
    // NAVBAR SCROLL
    // ============================================================
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');
    });

    // ============================================================
    // TOAST
    // ============================================================
    function showToast(message, type = 'info') {
        const colors = {
            info: 'linear-gradient(135deg, #6B7A5C, #8A9B7A)',
            success: 'linear-gradient(135deg, #4ADE80, #22D3EE)',
            warning: 'linear-gradient(135deg, #FBBF24, #FB923C)',
            danger: 'linear-gradient(135deg, #FB7185, #F43F5E)'
        };
        Toastify({
            text: message,
            duration: 3000,
            gravity: 'bottom',
            position: 'right',
            style: { background: colors[type] || colors.info, borderRadius: '15px', fontFamily: 'Inter, sans-serif' }
        }).showToast();
    }

    // ============================================================
    // SEARCH (Live)
    // ============================================================
    const searchInput = document.getElementById('searchInput');
    const suggestions = document.getElementById('searchSuggestions');
    let searchTimeout;

    searchInput?.addEventListener('input', function() {
        const q = this.value.trim();
        clearTimeout(searchTimeout);
        if (q.length < 2) { suggestions.style.display = 'none'; return; }
        
        searchTimeout = setTimeout(() => {
            fetch('articles.php?ajax=1&action=search&q=' + encodeURIComponent(q))
            .then(res => res.json())
            .then(data => {
                if (data.success && data.articles.length) {
                    suggestions.innerHTML = data.articles.map(a => `
                        <a href="articles.php?page=read&slug=${a.slug}" class="item">
                            <strong>${a.title}</strong>
                            <span class="category">${a.category} • ${a.reading_time || 5} min</span>
                        </a>
                    `).join('');
                    suggestions.style.display = 'block';
                } else {
                    suggestions.innerHTML = '<div class="item" style="color:var(--text-muted);">No articles found</div>';
                    suggestions.style.display = 'block';
                }
            });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!searchInput?.contains(e.target) && !suggestions?.contains(e.target)) {
            suggestions.style.display = 'none';
        }
    });

    // ============================================================
    // CATEGORY FILTER
    // ============================================================
    document.querySelectorAll('.pill').forEach(pill => {
        pill.addEventListener('click', function() {
            document.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            const category = this.dataset.category;
            if (category) {
                window.location.href = 'articles.php?category=' + encodeURIComponent(category);
            } else {
                window.location.href = 'articles.php';
            }
        });
    });

    // ============================================================
    // BOOKMARK
    // ============================================================
    document.querySelectorAll('.bookmark-btn, .bookmark-action').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!USER_ID) { showToast('Please login to save articles', 'warning'); return; }
            const articleId = this.dataset.id;
            const isAction = this.classList.contains('bookmark-action');
            
            fetch('articles.php?ajax=1&action=bookmark', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'article_id=' + articleId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.action === 'added') {
                        if (isAction) {
                            this.innerHTML = '<i class="bi bi-bookmark-fill"></i> Saved';
                            this.classList.add('bookmarked');
                        } else {
                            this.classList.add('active');
                            this.querySelector('i').className = 'bi bi-bookmark-fill';
                        }
                        showToast('Article saved!', 'success');
                    } else {
                        if (isAction) {
                            this.innerHTML = '<i class="bi bi-bookmark"></i> Save';
                            this.classList.remove('bookmarked');
                        } else {
                            this.classList.remove('active');
                            this.querySelector('i').className = 'bi bi-bookmark';
                        }
                        showToast('Removed', 'info');
                    }
                }
            });
        });
    });

    // ============================================================
    // SHARE
    // ============================================================
    function shareArticle() {
        if (navigator.share) {
            navigator.share({
                title: '<?= addslashes($article['title'] ?? '') ?>',
                url: window.location.href
            });
        } else {
            navigator.clipboard?.writeText(window.location.href).then(() => {
                showToast('Link copied!', 'success');
            }).catch(() => {
                // Fallback
                const input = document.createElement('input');
                input.value = window.location.href;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                input.remove();
                showToast('Link copied!', 'success');
            });
        }
    }

    // ============================================================
    // READING PROGRESS (Single Article)
    // ============================================================
    <?php if ($page === 'read' && $article): ?>
    const progressBar = document.getElementById('readingProgress');
    const articleBody = document.getElementById('articleBody');
    
    function updateReadingProgress() {
        if (!articleBody) return;
        const rect = articleBody.getBoundingClientRect();
        const totalHeight = articleBody.scrollHeight;
        const visibleHeight = window.innerHeight;
        const scrolled = window.scrollY - rect.top;
        const progress = Math.min(100, Math.max(0, (scrolled / (totalHeight - visibleHeight)) * 100));
        progressBar.style.width = progress + '%';
        
        // Save progress every 5%
        if (USER_ID && ARTICLE_ID && Math.floor(progress / 5) !== Math.floor((progress - 1) / 5)) {
            fetch('articles.php?ajax=1&action=save_progress', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'article_id=' + ARTICLE_ID + '&progress=' + Math.round(progress)
            });
        }
    }
    
    window.addEventListener('scroll', updateReadingProgress);
    window.addEventListener('resize', updateReadingProgress);
    setTimeout(updateReadingProgress, 100);
    
    function scrollToSavedPosition() {
        if (MAX_PROGRESS > 0 && MAX_PROGRESS < 95) {
            const totalHeight = articleBody.scrollHeight;
            const targetScroll = (MAX_PROGRESS / 100) * totalHeight;
            window.scrollTo({ top: targetScroll + (articleBody.getBoundingClientRect().top + window.scrollY), behavior: 'smooth' });
        }
    }
    <?php endif; ?>

    // ============================================================
    // COMMENTS
    // ============================================================
    <?php if ($page === 'read' && $article): ?>
    document.getElementById('submitComment')?.addEventListener('click', function() {
        if (!USER_ID) { showToast('Please login to comment', 'warning'); return; }
        const input = document.getElementById('commentInput');
        const content = input.value.trim();
        if (!content) return;
        
        fetch('api/comment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=add_article_comment&article_id=' + this.dataset.id + '&content=' + encodeURIComponent(content)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                showToast('Comment posted!', 'success');
                location.reload();
            }
        });
    });
    
    document.getElementById('commentInput')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') document.getElementById('submitComment')?.click();
    });
    <?php endif; ?>

    // ============================================================
    // GSAP – Entrance Animations
    // ============================================================
    <?php if ($page !== 'read'): ?>
    gsap.utils.toArray('.article-card, .featured-card, .daily-pick, .ai-recommend, .newsletter').forEach((el, i) => {
        gsap.from(el, {
            y: 30,
            opacity: 0,
            duration: 0.6,
            delay: i * 0.05,
            ease: 'power2.out',
            scrollTrigger: {
                trigger: el,
                toggleActions: 'play none none none',
                start: 'top 95%'
            }
        });
    });
    
    gsap.from('.hero h1, .hero p, .hero .search-box', {
        y: 40,
        opacity: 0,
        duration: 0.8,
        stagger: 0.15,
        ease: 'power2.out'
    });
    <?php else: ?>
    // Single article animations
    gsap.from('.article-single .header .badge, .article-single .header h1, .article-single .header .meta, .article-single .header .actions', {
        y: 20,
        opacity: 0,
        duration: 0.6,
        stagger: 0.1,
        ease: 'power2.out'
    });
    gsap.from('.article-single .header .featured-image', {
        scale: 0.98,
        opacity: 0,
        duration: 0.8,
        ease: 'power2.out'
    });
    <?php endif; ?>

    // ============================================================
    // KEYBOARD SHORTCUTS
    // ============================================================
    document.addEventListener('keydown', function(e) {
        // '/' for search
        if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.target.closest('input,textarea')) {
            e.preventDefault();
            document.getElementById('searchInput')?.focus();
        }
    });

    console.log('📚 Haven Wellness Library loaded.');
</script>

<?php if ($user_id): ?>
<nav class="mobile-bottom-nav">
    <a href="index.php"><i class="bi bi-house"></i>Home</a>
    <a href="community.php"><i class="bi bi-globe2"></i>Community</a>
    <a href="feed.php"><i class="bi bi-plus-circle-fill"></i>Feed</a>
    <a href="articles.php" class="active"><i class="bi bi-book"></i>Library</a>
    <a href="dashboard.php" style="position:relative;">
        <i class="bi bi-person-fill"></i>Me
        <?php if ($unread_notifs > 0): ?><span class="mbn-badge"><?= $unread_notifs > 9 ? '9+' : $unread_notifs ?></span><?php endif; ?>
    </a>
</nav>
<?php else: ?>
<nav class="mobile-bottom-nav">
    <a href="index.php"><i class="bi bi-house"></i>Home</a>
    <a href="community.php"><i class="bi bi-globe2"></i>Community</a>
    <a href="articles.php" class="active"><i class="bi bi-book"></i>Library</a>
    <a href="login.php"><i class="bi bi-box-arrow-in-right"></i>Login</a>
</nav>
<?php endif; ?>
</body>
</html>