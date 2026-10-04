<?php
// ============================================================
// profile.php – Mental Wellness Social Profile
// ============================================================
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
include 'includes/functions.php';

$user_id = isLoggedIn() ? $_SESSION['user_id'] : 0;
$anon_name = $user_id ? getAnonymousName($user_id, $pdo) : 'Guest';
$unread_notifs = $user_id ? getUnreadNotifications($user_id, $pdo) : 0;

// ============================================================
// Get profile user
// ============================================================
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

// If no ID provided, check slug or use current user
if (!$profile_id && $slug) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR anonymous_name = ?");
    $stmt->execute([$slug, $slug]);
    $row = $stmt->fetch();
    $profile_id = $row ? $row['id'] : 0;
}

if (!$profile_id && $user_id) {
    $profile_id = $user_id;
}

if (!$profile_id) {
    header('Location: login.php');
    exit;
}

// Fetch profile user data
$stmt = $pdo->prepare("
    SELECT u.*, p.anonymous_name, p.avatar_color, p.bio, p.avatar_url,
           p.occupation, p.education, p.country, p.city, p.language,
           p.birth_date, p.gender, p.cover_url,
           (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND status = 'published') as post_count,
           (SELECT COUNT(*) FROM reactions WHERE user_id = u.id) as support_received,
           (SELECT COUNT(*) FROM reactions WHERE target_type = 'post' AND target_id IN (SELECT id FROM posts WHERE user_id = u.id)) as support_given,
           (SELECT COUNT(*) FROM friends WHERE user_id = u.id OR friend_id = u.id AND status = 'accepted') as friend_count,
           (SELECT COUNT(*) FROM followers WHERE user_id = u.id) as follower_count,
           (SELECT COUNT(*) FROM followers WHERE follower_id = u.id) as following_count,
           (SELECT COUNT(*) FROM mood_entries WHERE user_id = u.id) as mood_count,
           (SELECT COUNT(*) FROM article_bookmarks WHERE user_id = u.id) as bookmarks_count
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$profile_id]);
$profile = $stmt->fetch();

if (!$profile) {
    header('Location: index.php');
    exit;
}

// Check if viewing own profile
$is_owner = ($user_id && $user_id == $profile_id);

// Check friend/follow status
$is_friend = false;
$friend_request_sent = false;
$friend_request_received = false;
$is_following = false;

if ($user_id && !$is_owner) {
    // Friend status
    $stmt = $pdo->prepare("
        SELECT status FROM friends 
        WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
    ");
    $stmt->execute([$user_id, $profile_id, $profile_id, $user_id]);
    $friend = $stmt->fetch();
    if ($friend) {
        if ($friend['status'] == 'accepted') $is_friend = true;
        elseif ($friend['status'] == 'pending' && $friend['user_id'] == $user_id) $friend_request_sent = true;
        elseif ($friend['status'] == 'pending' && $friend['user_id'] == $profile_id) $friend_request_received = true;
    }
    
    // Follow status
    $stmt = $pdo->prepare("SELECT id FROM followers WHERE user_id = ? AND follower_id = ?");
    $stmt->execute([$profile_id, $user_id]);
    $is_following = (bool)$stmt->fetch();
}

// Get user's achievements
$achievements = [
    ['icon' => '🏆', 'name' => 'First Post', 'desc' => 'Made your first community post', 'earned' => ($profile['post_count'] >= 1)],
    ['icon' => '🌱', 'name' => 'Growing Together', 'desc' => 'Reached 10 posts', 'earned' => ($profile['post_count'] >= 10)],
    ['icon' => '❤️', 'name' => 'Supportive Heart', 'desc' => 'Received 50 support reactions', 'earned' => ($profile['support_received'] >= 50)],
    ['icon' => '🤝', 'name' => 'Community Builder', 'desc' => 'Gave 100 support reactions', 'earned' => ($profile['support_given'] >= 100)],
    ['icon' => '📅', 'name' => '30-Day Streak', 'desc' => 'Logged mood for 30 days', 'earned' => ($profile['mood_count'] >= 30)],
    ['icon' => '🧘', 'name' => 'Wellness Explorer', 'desc' => 'Read 10 wellness articles', 'earned' => ($profile['bookmarks_count'] >= 10)],
];

// Get user's interests
$interests = ['Mental Health', 'Self-Care', 'Meditation', 'Reading', 'Music', 'Exercise', 'Nature', 'Sleep'];
$user_interests = array_slice($interests, 0, rand(3, 6));

// Get friends list (for sidebar)
$friends = [];
$stmt = $pdo->prepare("
    SELECT u.id, u.anonymous_name, p.avatar_color 
    FROM friends f
    JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id)
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted' AND u.id != ?
    LIMIT 6
");
$stmt->execute([$profile_id, $profile_id, $profile_id]);
$friends = $stmt->fetchAll();

// Get recent posts
$stmt = $pdo->prepare("
    SELECT p.*, u.anonymous_name, u.avatar_color,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
           (SELECT COUNT(*) FROM reactions WHERE post_id = p.id) as reaction_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.user_id = ? AND p.status = 'published'
    ORDER BY p.created_at DESC LIMIT 10
");
$stmt->execute([$profile_id]);
$posts = $stmt->fetchAll();

// Get AI profile insights (for owner only)
$insights = [];
if ($is_owner) {
    try {
        require_once __DIR__ . '/ai/AIManager.php';
        $manager = new AIManager();
        $insights = $manager->getInsights($user_id);
    } catch (Exception $e) {
        $insights = ['summary' => 'Continue engaging to receive personalized insights.', 'recommendations' => ['Share your thoughts', 'Support others'], 'trend' => 'stable'];
    }
}

// Random quote for sidebar
$quotes = [
    "You are enough, just as you are.",
    "Every step forward is progress.",
    "Your journey is uniquely yours.",
    "Kindness starts with yourself.",
    "Healing is not linear, and that's okay.",
];
$quote = $quotes[array_rand($quotes)];

// Tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'timeline';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($profile['anonymous_name']) ?> – Haven Profile</title>
<link rel="icon" href="logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <style>
        /* ============================================================
           CSS VARIABLES
           ============================================================ */
        :root {
            --primary: #5e7564;
            --secondary: #c9a76b;
            --accent: #7fa383;
            --danger: #c96a63;
            --warning: #d9a441;
            --bg: #f7f4ed;
            --bg-card: rgba(255,255,255,0.88);
            --border: rgba(64,77,67,0.06);
            --text: #fffdf8;
            --text-muted: #7c857e;
            --shadow: 0 4px 20px rgba(64,77,67,0.06);
            --radius: 20px;
            --radius-sm: 12px;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body.dark {
            --bg: #26332b;
            --bg-card: rgba(38,51,43,0.8);
            --border: rgba(94,117,100,0.06);
            --text: #26332b;
            --text-muted: #7c857e;
            --shadow: 0 4px 20px rgba(64,77,67,0.3);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            transition: background 0.3s, color 0.3s;
            overflow-x: hidden;
        }
        h1,h2,h3,h4,h5,h6 { font-family: 'Poppins', sans-serif; }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; }

        /* ===== Bootstrap accent override -> Haven palette ===== */
        .btn-primary { background:#5e7564; border-color:#5e7564; }
        .btn-primary:hover, .btn-primary:focus { background:#4d6555; border-color:#4d6555; }
        .btn-outline-primary { color:#5e7564; border-color:#5e7564; }
        .btn-outline-primary:hover { background:#5e7564; border-color:#5e7564; color:#fff; }
        .text-primary { color:#5e7564 !important; }
        .bg-primary, .badge.bg-primary, .text-bg-primary { background-color:#5e7564 !important; }
        .spinner-border.text-primary { color:#5e7564 !important; }
        .form-check-input:checked { background-color:#5e7564; border-color:#5e7564; }
        ::selection { background:#dfe9df; }

        /* ============================================================
           NAVBAR
           ============================================================ */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1050;
            padding: 0.6rem 2rem;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.3s;
        }
        body.dark .navbar { background: rgba(38,51,43,0.85); }
        .navbar .brand { font-weight: 700; font-size: 1.2rem; color: var(--primary); }
        .navbar .brand i { margin-right: 6px; }
        .navbar .nav-links { display: flex; align-items: center; gap: 1.2rem; }
        .navbar .nav-links a { color: var(--text-muted); transition: 0.2s; font-size: 0.9rem; }
        .navbar .nav-links a:hover { color: var(--primary); }
        .navbar .nav-links .btn { padding: 0.3rem 1rem; border-radius: 30px; font-size: 0.85rem; }
        .navbar .nav-toggle { display: none; font-size: 1.4rem; color: var(--text); }
        @media (max-width: 768px) {
            .navbar { padding: 0.6rem 1rem; }
            .navbar .nav-links { display: none; }
            .navbar .nav-links.open { display: flex; flex-direction: column; position: absolute; top: 100%; left: 0; right: 0; background: var(--bg); padding: 1rem; border-bottom: 1px solid var(--border); }
            .navbar .nav-toggle { display: block; }
        }

        /* ============================================================
           PROFILE LAYOUT
           ============================================================ */
        .profile-wrapper {
            padding-top: 65px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* ============================================================
           COVER BANNER
           ============================================================ */
        .cover-container {
            position: relative;
            height: 280px;
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            overflow: hidden;
            border-radius: 0 0 var(--radius) var(--radius);
            margin: 0 20px;
        }
        .cover-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }
        .cover-container:hover img { transform: scale(1.03); }
        .cover-container .cover-overlay {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            padding: 2rem;
            background: linear-gradient(transparent, rgba(64,77,67,0.4));
        }
        .cover-container .cover-overlay .cover-btn {
            position: absolute;
            right: 2rem;
            bottom: 2rem;
            padding: 0.4rem 1.2rem;
            border-radius: 30px;
            background: rgba(255,255,255,0.87);
            color: #fff;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(94,117,100,0.2);
            font-size: 0.85rem;
            transition: 0.3s;
        }
        .cover-container .cover-overlay .cover-btn:hover { background: rgba(255,255,255,0.9); }
        @media (max-width: 600px) {
            .cover-container { height: 180px; margin: 0 10px; }
            .cover-container .cover-overlay .cover-btn { right: 1rem; bottom: 1rem; font-size: 0.7rem; }
        }

        /* ============================================================
           PROFILE HEADER
           ============================================================ */
        .profile-header {
            display: flex;
            align-items: flex-end;
            gap: 1.5rem;
            padding: 0 2rem 1rem;
            margin-top: -50px;
            position: relative;
            z-index: 2;
            flex-wrap: wrap;
        }
        .profile-header .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid var(--bg-card);
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 600;
            color: #fff;
            flex-shrink: 0;
            position: relative;
            box-shadow: var(--shadow);
        }
        .profile-header .avatar .online-dot {
            position: absolute;
            bottom: 4px;
            right: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #7fa383;
            border: 2px solid var(--bg-card);
        }
        .profile-header .info { flex: 1; min-width: 200px; }
        .profile-header .info h2 { font-size: 1.8rem; margin-bottom: 0.1rem; }
        .profile-header .info .username { color: var(--text-muted); font-size: 0.95rem; }
        .profile-header .info .bio { color: var(--text-muted); font-size: 0.9rem; margin-top: 0.3rem; }
        .profile-header .info .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: 0.3rem;
        }
        .profile-header .info .badges .badge {
            padding: 0.1rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            background: var(--accent);
            color: #fff;
        }
        .profile-header .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            margin-top: 0.5rem;
        }
        .profile-header .actions .btn {
            padding: 0.4rem 1.2rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
        }
        .profile-header .actions .btn-primary { background: var(--primary); color: #fff; }
        .profile-header .actions .btn-primary:hover { background: var(--secondary); }
        .profile-header .actions .btn-outline { border: 1.5px solid var(--border); color: var(--text); }
        .profile-header .actions .btn-outline:hover { background: var(--bg-card); }
        .profile-header .actions .btn-success { background: var(--accent); color: #fff; }
        .profile-header .actions .btn-success:hover { background: #a9c2ae; }
        @media (max-width: 600px) {
            .profile-header { flex-direction: column; align-items: center; text-align: center; padding: 0 1rem; }
            .profile-header .avatar { width: 90px; height: 90px; font-size: 2.2rem; margin-top: -45px; }
            .profile-header .actions { justify-content: center; }
        }

        /* ============================================================
           STATS BAR
           ============================================================ */
        .stats-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            padding: 0.8rem 2rem;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            margin: 0 2rem;
        }
        .stats-bar .stat { display: flex; flex-direction: column; align-items: center; }
        .stats-bar .stat .number { font-size: 1.2rem; font-weight: 700; }
        .stats-bar .stat .label { font-size: 0.75rem; color: var(--text-muted); }
        @media (max-width: 600px) {
            .stats-bar { gap: 1rem; padding: 0.6rem 1rem; margin: 0 1rem; justify-content: center; }
            .stats-bar .stat .number { font-size: 1rem; }
        }

        /* ============================================================
           TABS
           ============================================================ */
        .profile-tabs {
            display: flex;
            gap: 0.5rem;
            padding: 0.5rem 2rem;
            overflow-x: auto;
            border-bottom: 1px solid var(--border);
            margin: 0 2rem;
            scrollbar-width: none;
        }
        .profile-tabs::-webkit-scrollbar { display: none; }
        .profile-tabs .tab {
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
            font-size: 0.9rem;
            color: var(--text-muted);
            transition: var(--transition);
            white-space: nowrap;
            font-weight: 500;
        }
        .profile-tabs .tab:hover { color: var(--text); background: var(--bg-card); }
        .profile-tabs .tab.active { background: var(--primary); color: #fff; }
        @media (max-width: 600px) {
            .profile-tabs { padding: 0.5rem 1rem; margin: 0 1rem; gap: 0.3rem; }
            .profile-tabs .tab { font-size: 0.8rem; padding: 0.4rem 0.8rem; }
        }

        /* ============================================================
           TAB CONTENT
           ============================================================ */
        .tab-content {
            padding: 1.5rem 2rem;
            display: none;
            animation: fadeUp 0.4s ease;
        }
        .tab-content.active { display: block; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        /* ============================================================
           CONTENT GRID
           ============================================================ */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 1.5rem;
        }
        @media (max-width: 992px) {
            .content-grid { grid-template-columns: 1fr; }
            .content-grid .sidebar { display: none; }
            .content-grid .sidebar.mobile-show { display: block; }
        }

        /* ============================================================
           GLASS CARDS
           ============================================================ */
        .glass {
            background: var(--bg-card);
            backdrop-filter: blur(18px);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.2rem;
            transition: var(--transition);
        }
        .glass:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(64,77,67,0.08); }

        /* ============================================================
           POST CARD (minimal)
           ============================================================ */
        .post-card {
            padding: 1.2rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }
        .post-card .post-header { display: flex; align-items: center; gap: 0.8rem; margin-bottom: 0.8rem; }
        .post-card .post-header .avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; flex-shrink: 0; }
        .post-card .post-header .name { font-weight: 600; }
        .post-card .post-header .time { font-size: 0.75rem; color: var(--text-muted); }
        .post-card .post-content { color: var(--text); line-height: 1.6; }
        .post-card .post-actions { display: flex; gap: 1rem; margin-top: 0.8rem; padding-top: 0.8rem; border-top: 1px solid var(--border); }
        .post-card .post-actions button { color: var(--text-muted); font-size: 0.85rem; display: flex; align-items: center; gap: 0.3rem; transition: 0.2s; }
        .post-card .post-actions button:hover { color: var(--primary); }

        /* ============================================================
           FRIENDS GRID
           ============================================================ */
        .friends-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 0.8rem;
        }
        .friends-grid .friend-card {
            text-align: center;
            padding: 0.8rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            transition: var(--transition);
        }
        .friends-grid .friend-card:hover { transform: translateY(-3px); box-shadow: var(--shadow); }
        .friends-grid .friend-card .avatar { width: 50px; height: 50px; border-radius: 50%; margin: 0 auto 0.3rem; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; font-size: 1.2rem; }

        /* ============================================================
           ACHIEVEMENTS
           ============================================================ */
        .achievement-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 0.8rem;
        }
        .achievement-card {
            text-align: center;
            padding: 1rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            transition: var(--transition);
        }
        .achievement-card.locked { opacity: 0.4; }
        .achievement-card .icon { font-size: 2.2rem; }
        .achievement-card .name { font-size: 0.8rem; font-weight: 600; margin-top: 0.3rem; }
        .achievement-card .desc { font-size: 0.7rem; color: var(--text-muted); }

        /* ============================================================
           SIDEBAR WIDGETS
           ============================================================ */
        .sidebar-widget { margin-bottom: 1rem; }
        .sidebar-widget h6 { font-family: 'Inter', sans-serif; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.8rem; }
        .sidebar-widget .friend-item { display: flex; align-items: center; gap: 0.6rem; padding: 0.3rem 0; border-bottom: 1px solid var(--border); }
        .sidebar-widget .friend-item .avatar { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; font-size: 0.8rem; }
        .sidebar-widget .friend-item .name { font-size: 0.85rem; }

        /* ============================================================
           AI INSIGHTS (owner only)
           ============================================================ */
        .ai-insights {
            background: linear-gradient(135deg, rgba(94,117,100,0.05), rgba(201,167,107,0.08));
            border-radius: var(--radius);
            padding: 1.2rem;
            margin-bottom: 1.5rem;
            border-left: 3px solid var(--secondary);
        }
        .ai-insights h5 { font-family: 'Inter', sans-serif; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .ai-insights h5 i { color: var(--secondary); }
        .ai-insights p { font-size: 0.9rem; color: var(--text-muted); }
        .ai-insights ul { list-style: none; padding: 0; }
        .ai-insights ul li { font-size: 0.85rem; padding: 0.2rem 0; border-bottom: 1px solid rgba(64,77,67,0.03); }

        /* ============================================================
           MOOD JOURNEY
           ============================================================ */
        .mood-journey {
            display: flex;
            gap: 0.3rem;
            flex-wrap: wrap;
            margin: 0.5rem 0;
        }
        .mood-journey .mood-day {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            background: var(--border);
            transition: 0.2s;
        }
        .mood-journey .mood-day.happy { background: #7fa383; }
        .mood-journey .mood-day.calm { background: #5e7564; }
        .mood-journey .mood-day.okay { background: #d9a441; }
        .mood-journey .mood-day.sad { background: #c9a76b; }
        .mood-journey .mood-day.stressed { background: #c96a63; }
        .mood-journey .mood-day.angry { background: #a8534c; }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .profile-wrapper { padding-top: 60px; }
            .tab-content { padding: 1rem; }
            .content-grid { gap: 1rem; }
            .glass { padding: 0.8rem; }
        }
        .text-muted { color: var(--text-muted); }
        .mt-1 { margin-top: 0.5rem; }
        .mt-2 { margin-top: 1rem; }
        .mb-1 { margin-bottom: 0.5rem; }
        .mb-2 { margin-bottom: 1rem; }
        .d-flex { display: flex; }
        .gap-1 { gap: 0.5rem; }
        .gap-2 { gap: 1rem; }
        .align-center { align-items: center; }
        .flex-wrap { flex-wrap: wrap; }
        .justify-between { justify-content: space-between; }
    </style>
</head>
<body>

<!-- ============================================================
   NAVBAR
   ============================================================ -->
<nav class="navbar">
    <div>
        <button class="nav-toggle" id="navToggle"><i class="bi bi-list"></i></button>
        <a href="index.php" class="brand"><img src="logo.png" alt="Haven" style="height:22px;width:22px;object-fit:cover;border-radius:6px;vertical-align:-5px;margin-right:4px;">Haven</a>
    </div>
    <div class="nav-links" id="navLinks">
        <a href="feed.php">Feed</a>
        <a href="community.php">Community</a>
        <a href="articles.php">Articles</a>
        <a href="chatbot.php">AI Chat</a>
        <?php if ($user_id): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php" class="btn btn-outline">Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-primary">Login</a>
        <?php endif; ?>
        <button onclick="toggleDark()" style="background:none;border:none;color:var(--text);font-size:1.1rem;"><i class="bi bi-moon"></i></button>
    </div>
</nav>

<!-- ============================================================
   PROFILE WRAPPER
   ============================================================ -->
<div class="profile-wrapper">

    <!-- ============================================================
       COVER
       ============================================================ -->
    <div class="cover-container">
        <?php if ($profile['cover_url']): ?>
            <img src="<?= escape($profile['cover_url']) ?>" alt="Cover">
        <?php else: ?>
            <div style="width:100%;height:100%;background:linear-gradient(135deg, <?= $profile['avatar_color'] ?? '#5e7564' ?>, <?= $profile['avatar_color'] ?? '#c9a76b' ?>);"></div>
        <?php endif; ?>
        <div class="cover-overlay">
            <?php if ($is_owner): ?>
                <button class="cover-btn" onclick="document.getElementById('coverUpload').click()"><i class="bi bi-camera"></i> Change Cover</button>
                <input type="file" id="coverUpload" style="display:none;" accept="image/*" onchange="uploadCover(this)">
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================
       PROFILE HEADER
       ============================================================ -->
    <div class="profile-header">
        <div class="avatar" style="background:<?= $profile['avatar_color'] ?? '#5e7564' ?>;">
            <?= substr($profile['anonymous_name'], 0, 1) ?>
            <?php if ($profile['is_active']): ?><span class="online-dot"></span><?php endif; ?>
        </div>
        <div class="info">
            <h2><?= escape($profile['anonymous_name']) ?></h2>
            <div class="username">@<?= escape($profile['username'] ?? 'user') ?></div>
            <div class="bio"><?= nl2br(escape($profile['bio'] ?? 'Learning to grow every day.')) ?></div>
            <div class="badges">
                <?php if ($profile['role'] == 'volunteer'): ?><span class="badge" style="background:#7fa383;">🧑‍⚕️ Volunteer</span><?php endif; ?>
                <?php if ($profile['role'] == 'moderator'): ?><span class="badge" style="background:#c9a76b;">🛡️ Moderator</span><?php endif; ?>
                <?php if ($profile['support_received'] >= 100): ?><span class="badge" style="background:#d9a441;">⭐ Top Supporter</span><?php endif; ?>
                <?php if ($profile['mood_count'] >= 30): ?><span class="badge" style="background:#7fa383;">📅 30-Day Streak</span><?php endif; ?>
            </div>
        </div>
        <div class="actions">
            <?php if ($is_owner): ?>
                <a href="profile-edit.php" class="btn btn-outline"><i class="bi bi-pencil"></i> Edit Profile</a>
            <?php else: ?>
                <?php if ($is_friend): ?>
                    <button class="btn btn-success"><i class="bi bi-check-circle"></i> Friends</button>
                <?php elseif ($friend_request_sent): ?>
                    <button class="btn btn-outline" disabled><i class="bi bi-clock"></i> Request Sent</button>
                <?php elseif ($friend_request_received): ?>
                    <button class="btn btn-primary" onclick="acceptFriend(<?= $profile_id ?>)"><i class="bi bi-check"></i> Accept Request</button>
                <?php else: ?>
                    <button class="btn btn-primary" onclick="sendFriendRequest(<?= $profile_id ?>)"><i class="bi bi-person-plus"></i> Add Friend</button>
                <?php endif; ?>
                <button class="btn btn-outline" onclick="sendSupport(<?= $profile_id ?>)"><i class="bi bi-heart"></i> Support</button>
                <button class="btn btn-outline" onclick="window.location.href='message.php?user=<?= $profile_id ?>'"><i class="bi bi-chat"></i> Message</button>
                <button class="btn btn-outline" onclick="shareProfile()"><i class="bi bi-share"></i></button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================
       STATS
       ============================================================ -->
    <div class="stats-bar">
        <div class="stat"><span class="number"><?= number_format($profile['support_received']) ?></span><span class="label">Support Received</span></div>
        <div class="stat"><span class="number"><?= number_format($profile['support_given']) ?></span><span class="label">Support Given</span></div>
        <div class="stat"><span class="number"><?= number_format($profile['post_count']) ?></span><span class="label">Posts</span></div>
        <div class="stat"><span class="number"><?= number_format($profile['friend_count']) ?></span><span class="label">Friends</span></div>
        <div class="stat"><span class="number"><?= number_format($profile['follower_count']) ?></span><span class="label">Followers</span></div>
        <div class="stat"><span class="number"><?= number_format($profile['mood_count']) ?></span><span class="label">Mood Check-ins</span></div>
    </div>

    <!-- ============================================================
       TABS
       ============================================================ -->
    <div class="profile-tabs">
        <button class="tab <?= $tab == 'timeline' ? 'active' : '' ?>" data-tab="timeline"><i class="bi bi-clock"></i> Timeline</button>
        <button class="tab <?= $tab == 'about' ? 'active' : '' ?>" data-tab="about"><i class="bi bi-person"></i> About</button>
        <button class="tab <?= $tab == 'friends' ? 'active' : '' ?>" data-tab="friends"><i class="bi bi-people"></i> Friends</button>
        <button class="tab <?= $tab == 'achievements' ? 'active' : '' ?>" data-tab="achievements"><i class="bi bi-trophy"></i> Achievements</button>
        <?php if ($is_owner): ?>
            <button class="tab <?= $tab == 'insights' ? 'active' : '' ?>" data-tab="insights"><i class="bi bi-brain"></i> AI Insights</button>
        <?php endif; ?>
    </div>

    <!-- ============================================================
       TAB CONTENT
       ============================================================ -->

    <!-- ============================================================
       TIMELINE
       ============================================================ -->
    <div class="tab-content <?= $tab == 'timeline' ? 'active' : '' ?>" id="tab-timeline">
        <div class="content-grid">
            <div class="main">
                <?php if (empty($posts)): ?>
                    <div class="glass text-center" style="padding:2rem;">
                        <p class="text-muted"><?= $is_owner ? 'You haven\'t posted yet. Share your first thought!' : 'No posts yet.' ?></p>
                        <?php if ($is_owner): ?>
                            <a href="create-post.php" class="btn btn-primary" style="margin-top:0.5rem;display:inline-block;">Create Post</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="glass post-card">
                            <div class="post-header">
                                <div class="avatar" style="background:<?= $post['avatar_color'] ?? '#5e7564' ?>;"><?= substr($post['anonymous_name'], 0, 1) ?></div>
                                <div>
                                    <div class="name"><?= escape($post['anonymous_name']) ?></div>
                                    <div class="time"><?= timeAgo($post['created_at']) ?></div>
                                </div>
                            </div>
                            <div class="post-content">
                                <?php if ($post['title']): ?><h5 style="margin-bottom:0.3rem;"><?= escape($post['title']) ?></h5><?php endif; ?>
                                <p><?= nl2br(escape($post['content'])) ?></p>
                            </div>
                            <div class="post-actions">
                                <button><i class="bi bi-heart"></i> <?= $post['reaction_count'] ?></button>
                                <button><i class="bi bi-chat"></i> <?= $post['comment_count'] ?></button>
                                <a href="post.php?id=<?= $post['id'] ?>" style="color:var(--text-muted);font-size:0.85rem;">View Post</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="sidebar">
                <!-- Mood Journey -->
                <div class="glass sidebar-widget">
                    <h6><i class="bi bi-emoji-smile"></i> Mood Journey</h6>
                    <div class="mood-journey">
                        <?php
                        $moods = ['happy','calm','okay','sad','stressed','happy','calm','okay','happy','calm','sad','stressed','okay','happy','calm','happy'];
                        foreach ($moods as $m):
                        ?>
                            <div class="mood-day <?= $m ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-muted small mt-1">Last 30 days</p>
                </div>

                <!-- Friends -->
                <?php if (!empty($friends)): ?>
                    <div class="glass sidebar-widget">
                        <h6><i class="bi bi-people"></i> Friends (<?= count($friends) ?>)</h6>
                        <?php foreach ($friends as $f): ?>
                            <div class="friend-item">
                                <div class="avatar" style="background:<?= $f['avatar_color'] ?? '#5e7564' ?>;"><?= substr($f['anonymous_name'], 0, 1) ?></div>
                                <span class="name"><a href="profile.php?id=<?= $f['id'] ?>"><?= escape($f['anonymous_name']) ?></a></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($profile['friend_count'] > 6): ?>
                            <a href="?tab=friends" class="text-muted small">View all <?= $profile['friend_count'] ?> friends</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Wellness Quote -->
                <div class="glass sidebar-widget">
                    <h6><i class="bi bi-quote"></i> Quote</h6>
                    <p style="font-style:italic;color:var(--text-muted);font-size:0.9rem;">"<?= $quote ?>"</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
       ABOUT
       ============================================================ -->
    <div class="tab-content <?= $tab == 'about' ? 'active' : '' ?>" id="tab-about">
        <div class="content-grid">
            <div class="main">
                <div class="glass">
                    <h4>About <?= escape($profile['anonymous_name']) ?></h4>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;margin-top:1rem;">
                        <div><strong>Joined</strong><br><span class="text-muted"><?= date('M Y', strtotime($profile['created_at'])) ?></span></div>
                        <div><strong>Location</strong><br><span class="text-muted"><?= escape($profile['country'] ?? 'Not specified') ?></span></div>
                        <div><strong>Occupation</strong><br><span class="text-muted"><?= escape($profile['occupation'] ?? 'Not specified') ?></span></div>
                        <div><strong>Education</strong><br><span class="text-muted"><?= escape($profile['education'] ?? 'Not specified') ?></span></div>
                        <div><strong>Language</strong><br><span class="text-muted"><?= escape($profile['language'] ?? 'English') ?></span></div>
                        <div><strong>Birth Date</strong><br><span class="text-muted"><?= $profile['birth_date'] ? date('M d, Y', strtotime($profile['birth_date'])) : 'Not specified' ?></span></div>
                    </div>
                    <?php if (!empty($user_interests)): ?>
                        <div style="margin-top:1.5rem;">
                            <strong>Interests</strong>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <?php foreach ($user_interests as $interest): ?>
                                    <span style="padding:0.2rem 0.8rem;border-radius:20px;background:var(--bg-card);border:1px solid var(--border);font-size:0.8rem;"><?= escape($interest) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sidebar">
                <div class="glass sidebar-widget">
                    <h6><i class="bi bi-info-circle"></i> Profile Privacy</h6>
                    <p class="text-muted small">This profile is <?= $profile['privacy'] ?? 'public' ?></p>
                    <?php if ($is_owner): ?>
                        <button class="btn btn-outline btn-sm mt-1" onclick="alert('Privacy settings coming soon')">Manage Privacy</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
       FRIENDS
       ============================================================ -->
    <div class="tab-content <?= $tab == 'friends' ? 'active' : '' ?>" id="tab-friends">
        <h4 style="margin-bottom:1rem;">Friends (<?= $profile['friend_count'] ?>)</h4>
        <?php
        $all_friends = [];
        $stmt = $pdo->prepare("
            SELECT u.id, u.anonymous_name, p.avatar_color 
            FROM friends f
            JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id)
            LEFT JOIN profiles p ON u.id = p.user_id
            WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted' AND u.id != ?
            ORDER BY u.anonymous_name ASC
        ");
        $stmt->execute([$profile_id, $profile_id, $profile_id]);
        $all_friends = $stmt->fetchAll();
        ?>
        <?php if (empty($all_friends)): ?>
            <div class="glass text-center" style="padding:2rem;">
                <p class="text-muted">No friends yet.</p>
            </div>
        <?php else: ?>
            <div class="friends-grid">
                <?php foreach ($all_friends as $f): ?>
                    <div class="friend-card">
                        <div class="avatar" style="background:<?= $f['avatar_color'] ?? '#5e7564' ?>;"><?= substr($f['anonymous_name'], 0, 1) ?></div>
                        <div style="font-size:0.85rem;font-weight:500;"><a href="profile.php?id=<?= $f['id'] ?>"><?= escape($f['anonymous_name']) ?></a></div>
                        <?php if ($user_id && !$is_owner): ?>
                            <button class="btn btn-sm btn-outline" style="font-size:0.7rem;margin-top:0.3rem;" onclick="sendFriendRequest(<?= $f['id'] ?>)">Add Friend</button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================
       ACHIEVEMENTS
       ============================================================ -->
    <div class="tab-content <?= $tab == 'achievements' ? 'active' : '' ?>" id="tab-achievements">
        <h4 style="margin-bottom:1rem;">Achievements</h4>
        <div class="achievement-grid">
            <?php foreach ($achievements as $ach): ?>
                <div class="achievement-card <?= $ach['earned'] ? '' : 'locked' ?>">
                    <div class="icon"><?= $ach['icon'] ?></div>
                    <div class="name"><?= escape($ach['name']) ?></div>
                    <div class="desc"><?= escape($ach['desc']) ?></div>
                    <?= $ach['earned'] ? '<span style="font-size:0.6rem;color:#7fa383;">✓ Earned</span>' : '<span style="font-size:0.6rem;color:var(--text-muted);">🔒 Locked</span>' ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ============================================================
       AI INSIGHTS (OWNER ONLY)
       ============================================================ -->
    <?php if ($is_owner): ?>
        <div class="tab-content <?= $tab == 'insights' ? 'active' : '' ?>" id="tab-insights">
            <div class="ai-insights">
                <h5><i class="bi bi-brain"></i> Your Personal Insights</h5>
                <p><?= nl2br(escape($insights['summary'] ?? 'Continue engaging to receive personalized insights.')) ?></p>
                <?php if (!empty($insights['recommendations'])): ?>
                    <ul style="margin-top:0.5rem;">
                        <?php foreach ($insights['recommendations'] as $rec): ?>
                            <li>🌱 <?= escape($rec) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="text-muted small mt-1">Trend: <?= escape($insights['trend'] ?? 'stable') ?></p>
            </div>
            <div class="glass" style="margin-top:1rem;">
                <h6>Community Impact</h6>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:0.8rem;margin-top:0.5rem;">
                    <div><strong>🤝 Support Given</strong><br><span class="text-muted"><?= number_format($profile['support_given']) ?></span></div>
                    <div><strong>❤️ Support Received</strong><br><span class="text-muted"><?= number_format($profile['support_received']) ?></span></div>
                    <div><strong>📝 Posts</strong><br><span class="text-muted"><?= number_format($profile['post_count']) ?></span></div>
                    <div><strong>📅 Mood Check-ins</strong><br><span class="text-muted"><?= number_format($profile['mood_count']) ?></span></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- ============================================================
   FOOTER
   ============================================================ -->
<div style="text-align:center;padding:2rem;color:var(--text-muted);font-size:0.85rem;border-top:1px solid var(--border);margin-top:2rem;">
    <p>© <?= date('Y') ?> Haven – Your Wellness Community</p>
</div>

<!-- ============================================================
   SCRIPTS
   ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ============================================================
    // GLOBALS
    // ============================================================
    const USER_ID = <?= $user_id ?: 0 ?>;
    const PROFILE_ID = <?= $profile_id ?>;
    const IS_OWNER = <?= $is_owner ? 'true' : 'false' ?>;

    // ============================================================
    // DARK MODE
    // ============================================================
    function toggleDark() {
        document.body.classList.toggle('dark');
        localStorage.setItem('profileDark', document.body.classList.contains('dark'));
    }
    if (localStorage.getItem('profileDark') === 'true') document.body.classList.add('dark');

    // ============================================================
    // NAVBAR TOGGLE
    // ============================================================
    document.getElementById('navToggle')?.addEventListener('click', function() {
        document.getElementById('navLinks').classList.toggle('open');
    });

    // ============================================================
    // TABS
    // ============================================================
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const target = this.dataset.tab;
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById('tab-' + target)?.classList.add('active');
            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('tab', target);
            history.pushState({}, '', url);
        });
    });

    // ============================================================
    // TOAST
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
    // FRIEND REQUESTS
    // ============================================================
    function sendFriendRequest(userId) {
        if (!USER_ID) { showToast('Please login', 'warning'); return; }
        fetch('api/friend.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=send&user_id=${userId}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Friend request sent!', 'success');
                location.reload();
            } else {
                showToast(data.error || 'Failed', 'danger');
            }
        });
    }

    function acceptFriend(userId) {
        if (!USER_ID) { showToast('Please login', 'warning'); return; }
        fetch('api/friend.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=accept&user_id=${userId}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Friend request accepted! 🎉', 'success');
                location.reload();
            } else {
                showToast(data.error || 'Failed', 'danger');
            }
        });
    }

    // ============================================================
    // SUPPORT
    // ============================================================
    function sendSupport(userId) {
        if (!USER_ID) { showToast('Please login', 'warning'); return; }
        fetch('api/reaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=send_support&user_id=${userId}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('❤️ Support sent!', 'success');
            } else {
                showToast(data.error || 'Failed', 'danger');
            }
        });
    }

    // ============================================================
    // SHARE
    // ============================================================
    function shareProfile() {
        const url = window.location.href;
        if (navigator.share) {
            navigator.share({
                title: 'Check out this profile',
                url: url
            });
        } else {
            navigator.clipboard?.writeText(url).then(() => {
                showToast('Profile link copied!', 'success');
            }).catch(() => {
                const input = document.createElement('input');
                input.value = url;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                input.remove();
                showToast('Profile link copied!', 'success');
            });
        }
    }

    // ============================================================
    // COVER UPLOAD
    // ============================================================
    function uploadCover(input) {
        const file = input.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('cover', file);
        fetch('api/upload-cover.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Cover updated!', 'success');
                location.reload();
            } else {
                showToast(data.error || 'Failed', 'danger');
            }
        });
    }

    // ============================================================
    // GSAP ANIMATIONS
    // ============================================================
    gsap.from('.cover-container', { scale: 0.98, opacity: 0, duration: 0.8, ease: 'power2.out' });
    gsap.from('.profile-header', { y: 30, opacity: 0, duration: 0.6, delay: 0.2, ease: 'power2.out' });
    gsap.from('.stats-bar .stat', { y: 20, opacity: 0, duration: 0.5, stagger: 0.05, delay: 0.3, ease: 'power2.out' });
    gsap.from('.profile-tabs .tab', { y: 10, opacity: 0, duration: 0.4, stagger: 0.03, delay: 0.4, ease: 'power2.out' });
    gsap.utils.toArray('.glass:not(.sidebar-widget)').forEach((el, i) => {
        gsap.from(el, {
            y: 20,
            opacity: 0,
            duration: 0.5,
            delay: 0.5 + i * 0.04,
            ease: 'power2.out',
            scrollTrigger: { trigger: el, toggleActions: 'play none none none', start: 'top 95%' }
        });
    });

    console.log('🌿 Profile loaded: <?= escape($profile['anonymous_name']) ?>');
</script>
</body>
</html>