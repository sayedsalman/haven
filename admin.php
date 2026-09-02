<?php
// ========== Includes & Auth ==========
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireRole('admin');
include 'includes/functions.php';

$admin_id = $_SESSION['user_id'];
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ========== Handle Actions ==========
if ($action == 'delete_user' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id != $admin_id) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['admin_msg'] = 'User deleted successfully.';
    }
    header('Location: admin.php?tab=users');
    exit;
}

if ($action == 'ban_user' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['admin_msg'] = 'User banned successfully.';
    header('Location: admin.php?tab=users');
    exit;
}

if ($action == 'unban_user' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['admin_msg'] = 'User unbanned successfully.';
    header('Location: admin.php?tab=users');
    exit;
}

if ($action == 'delete_post' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE posts SET status = 'deleted' WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['admin_msg'] = 'Post deleted.';
    header('Location: admin.php?tab=posts');
    exit;
}

if ($action == 'hide_post' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE posts SET status = 'flagged' WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['admin_msg'] = 'Post hidden.';
    header('Location: admin.php?tab=posts');
    exit;
}

if ($action == 'resolve_report' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['admin_msg'] = 'Report resolved.';
    header('Location: admin.php?tab=moderation');
    exit;
}

if ($action == 'dismiss_report' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE reports SET status = 'dismissed' WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['admin_msg'] = 'Report dismissed.';
    header('Location: admin.php?tab=moderation');
    exit;
}

if ($action == 'update_role' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['user_id']);
    $new_role = $_POST['role'];
    $allowed_roles = ['user', 'volunteer', 'moderator', 'admin'];
    if (in_array($new_role, $allowed_roles) && $id != $admin_id) {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $id]);
        $_SESSION['admin_msg'] = 'User role updated to ' . ucfirst($new_role) . '.';
    }
    header('Location: admin.php?tab=users');
    exit;
}

if ($action == 'add_article' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $reading_time = intval($_POST['reading_time'] ?? 5);
    if ($title !== '' && $content !== '') {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-')) . '-' . substr(md5(uniqid()), 0, 6);
        $stmt = $pdo->prepare("INSERT INTO articles (title, slug, excerpt, content, category, author, author_id, image_url, reading_time, is_published, published_at, created_at, updated_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW(), NOW())");
        $stmt->execute([$title, $slug, $excerpt, $content, $category, $_SESSION['username'] ?? 'Admin', $admin_id, $image_url, $reading_time]);
        $_SESSION['admin_msg'] = 'Article published successfully.';
    } else {
        $_SESSION['admin_msg'] = 'Title and content are required.';
    }
    header('Location: admin.php?tab=resources');
    exit;
}

if ($action == 'delete_article' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("DELETE FROM articles WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['admin_msg'] = 'Article deleted.';
    header('Location: admin.php?tab=resources');
    exit;
}

if ($action == 'add_category' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? 'post');
    $icon = trim($_POST['icon'] ?? 'bi-tag');
    if ($name !== '') {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, type, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $slug, $icon, $type]);
        $_SESSION['admin_msg'] = 'Category added.';
    }
    header('Location: admin.php?tab=resources');
    exit;
}

if ($action == 'delete_category' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['admin_msg'] = 'Category deleted.';
    header('Location: admin.php?tab=resources');
    exit;
}

if ($action == 'save_settings' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $settings_keys = ['site_name', 'site_tagline', 'contact_email', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'gemini_api_key', 'openrouter_api_key', 'rate_limit_requests', 'maintenance_mode'];
    foreach ($settings_keys as $key) {
        if (isset($_POST[$key])) {
            $value = trim($_POST[$key]);
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())
                                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
            $stmt->execute([$key, $value]);
        }
    }
    $_SESSION['admin_msg'] = 'Settings saved successfully.';
    header('Location: admin.php?tab=settings');
    exit;
}

if ($action == 'export_csv' && isset($_GET['type'])) {
    $type = $_GET['type'];
    $map = [
        'users' => ['sql' => "SELECT id, username, email, role, is_active, created_at FROM users ORDER BY id DESC", 'file' => 'users_export'],
        'posts' => ['sql' => "SELECT id, user_id, category, mood, status, comment_count, reaction_count, created_at FROM posts ORDER BY id DESC", 'file' => 'posts_export'],
        'reports' => ['sql' => "SELECT id, reporter_id, post_id, reason, status, priority, created_at FROM reports ORDER BY id DESC", 'file' => 'reports_export'],
        'ai' => ['sql' => "SELECT id, post_id, emotion, sentiment, risk_score, category, created_at FROM ai_analysis ORDER BY id DESC", 'file' => 'ai_analysis_export'],
    ];
    if (isset($map[$type])) {
        $rows = $pdo->query($map[$type]['sql'])->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $map[$type]['file'] . '_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
    header('Location: admin.php?tab=reports');
    exit;
}

// ========== Fetch Real Data ==========

// User stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$active_users = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$banned_users = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 0")->fetchColumn();
$volunteers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'volunteer'")->fetchColumn();
$moderators = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'moderator'")->fetchColumn();
$admins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

// Post stats
$total_posts = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$published_posts = $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'published'")->fetchColumn();
$flagged_posts = $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'flagged'")->fetchColumn();
$deleted_posts = $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'deleted'")->fetchColumn();
$today_posts = $pdo->query("SELECT COUNT(*) FROM posts WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// Comments
$total_comments = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();

// Reactions
$total_reactions = $pdo->query("SELECT COUNT(*) FROM reactions")->fetchColumn();
$support_count = $pdo->query("SELECT COUNT(*) FROM reactions WHERE reaction_type = 'support'")->fetchColumn();

// AI stats
$ai_replies = $pdo->query("SELECT COUNT(*) FROM ai_analysis")->fetchColumn();
$high_risk_posts = $pdo->query("SELECT COUNT(*) FROM ai_analysis WHERE risk_score >= 40")->fetchColumn();
$pending_ai_reviews = $pdo->query("SELECT COUNT(*) FROM ai_analysis WHERE risk_score BETWEEN 20 AND 39")->fetchColumn();

// Reports
$pending_reports = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
$resolved_reports = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'resolved'")->fetchColumn();
$dismissed_reports = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'dismissed'")->fetchColumn();

// Volunteer stats
$volunteer_requests = $pdo->query("SELECT COUNT(*) FROM consultation_requests WHERE status = 'pending'")->fetchColumn();
$active_consultations = $pdo->query("SELECT COUNT(*) FROM consultation_requests WHERE status = 'active'")->fetchColumn();
$completed_consultations = $pdo->query("SELECT COUNT(*) FROM consultation_requests WHERE status = 'completed'")->fetchColumn();

// Get all users for management
$user_search = trim($_GET['q'] ?? '');
if ($user_search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ? ORDER BY id DESC LIMIT 100");
    $like = '%' . $user_search . '%';
    $stmt->execute([$like, $like, $like]);
    $users = $stmt->fetchAll();
} else {
    $users = $pdo->query("SELECT * FROM users ORDER BY id DESC LIMIT 50")->fetchAll();
}

// Get posts for management
$posts = $pdo->query("SELECT p.*, u.username, u.anonymous_name FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.id DESC LIMIT 50")->fetchAll();

// Get reports for moderation
$reports = $pdo->query("SELECT r.*, p.title as post_title, u.username as reporter, p.user_id as post_author_id 
                        FROM reports r 
                        JOIN posts p ON r.post_id = p.id 
                        JOIN users u ON r.reporter_id = u.id 
                        WHERE r.status = 'pending' 
                        ORDER BY r.created_at DESC LIMIT 20")->fetchAll();

// Get AI alerts
$ai_alerts = $pdo->query("SELECT a.*, p.title, u.username FROM ai_analysis a 
                          JOIN posts p ON a.post_id = p.id 
                          JOIN users u ON p.user_id = u.id 
                          WHERE a.risk_score >= 30 
                          ORDER BY a.created_at DESC LIMIT 15")->fetchAll();

// Get mood distribution for chart
$mood_distribution = $pdo->query("SELECT mood, COUNT(*) as count FROM posts WHERE mood != '' GROUP BY mood")->fetchAll();
$mood_labels = [];
$mood_data = [];
$mood_colors = ['#7fa383', '#5e7564', '#c96a63', '#d9a441', '#c9a76b'];
foreach ($mood_distribution as $m) {
    $mood_labels[] = ucfirst($m['mood']);
    $mood_data[] = $m['count'];
}

// Get daily activity for chart (last 7 days)
$daily_activity = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $count = $pdo->query("SELECT COUNT(*) FROM posts WHERE DATE(created_at) = '$date'")->fetchColumn();
    $daily_activity[] = $count;
}
$daily_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $daily_labels[] = date('M d', strtotime("-$i days"));
}

// Real user growth (last 7 days)
$user_growth = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $user_growth[] = (int)$stmt->fetchColumn();
}

// Real emotion distribution (from ai_analysis)
$emotion_rows = $pdo->query("SELECT emotion, COUNT(*) as count FROM ai_analysis WHERE emotion IS NOT NULL AND emotion != '' GROUP BY emotion ORDER BY count DESC LIMIT 6")->fetchAll();
$emotion_labels = [];
$emotion_data = [];
foreach ($emotion_rows as $e) {
    $emotion_labels[] = ucfirst($e['emotion']);
    $emotion_data[] = (int)$e['count'];
}
if (empty($emotion_labels)) { $emotion_labels = ['No data yet']; $emotion_data = [0]; }

// Site settings (key => value)
$settings_rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
$site_settings = [];
foreach ($settings_rows as $s) { $site_settings[$s['setting_key']] = $s['setting_value']; }
$get_setting = function($key, $default = '') use ($site_settings) {
    return isset($site_settings[$key]) ? $site_settings[$key] : $default;
};

// Categories for resource management
$all_categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Message handling
$admin_msg = isset($_SESSION['admin_msg']) ? $_SESSION['admin_msg'] : '';
unset($_SESSION['admin_msg']);

// ========== Random Admin Tip ==========
$tips = [
    'Review flagged content daily to maintain community safety.',
    'AI detected high-risk posts; consider assigning volunteers.',
    'Community health score is based on engagement and safety metrics.',
    'Enable dark mode for late-night moderation sessions.',
    'Backup the database weekly to prevent data loss.'
];
$tip = $tips[array_rand($tips)];

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Haven</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Poppins:wght@300..700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <!-- GSAP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Toastify -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <!-- Lenis Smooth Scroll -->
    <script src="https://unpkg.com/lenis@1.1.13/dist/lenis.min.js"></script>
    <style>
        /* ===== GLOBAL ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f7f4ed;
            transition: background 0.3s, color 0.3s;
            overflow-x: hidden;
        }
        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; }

        /* ===== Bootstrap accent override -> Haven palette ===== */
        a { color: #5e7564; }
        .btn-primary { background:#5e7564; border-color:#5e7564; }
        .btn-primary:hover, .btn-primary:focus { background:#4d6555; border-color:#4d6555; }
        .btn-outline-primary { color:#5e7564; border-color:#5e7564; }
        .btn-outline-primary:hover { background:#5e7564; border-color:#5e7564; color:#fff; }
        .text-primary { color:#5e7564 !important; }
        .bg-primary, .badge.bg-primary, .text-bg-primary { background-color:#5e7564 !important; }
        .spinner-border.text-primary { color:#5e7564 !important; }
        .form-check-input:checked { background-color:#5e7564; border-color:#5e7564; }
        .form-control:focus, .form-select:focus { border-color:#879d8b; box-shadow:0 0 0 .2rem rgba(135,157,139,.2); }
        ::selection { background:#dfe9df; }

        /* ===== DARK MODE ===== */
        body.dark-mode {
            background: #f7f4ed;
            color: #26332b;
        }
        body.dark-mode .glass-card,
        body.dark-mode .glass-nav,
        body.dark-mode .glass-footer,
        body.dark-mode .modal-content {
            background: rgba(38,51,43,0.85);
            border-color: rgba(94,117,100,0.1);
            color: #26332b;
        }
        body.dark-mode .glass-card:hover {
            background: rgba(38,51,43,0.9);
        }
        body.dark-mode .form-control {
            background: rgba(38,51,43,0.8);
            color: #26332b;
            border-color: rgba(94,117,100,0.2);
        }
        body.dark-mode .text-muted { color: #7c857e !important; }
        body.dark-mode .table { color: #26332b; }
        body.dark-mode .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: rgba(38,51,43,0.18);
        }
        body.dark-mode .table-hover > tbody > tr:hover > * {
            background-color: rgba(38,51,43,0.21);
        }
        body.dark-mode .dropdown-menu {
            background: #fffdf8;
            border-color: rgba(94,117,100,0.1);
        }
        body.dark-mode .dropdown-item {
            color: #26332b;
        }
        body.dark-mode .dropdown-item:hover {
            background: rgba(255,255,255,0.85);
        }

        /* ===== SIDEBAR (Dark Glass) ===== */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            height: 100vh;
            width: 250px;
            background: rgba(255,253,248,0.94);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border-right: 1px solid rgba(94,117,100,0.1);
            padding-top: 70px;
            z-index: 1030;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(94,117,100,0.3); border-radius: 10px; }
        .sidebar .brand {
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            padding: 15px 20px;
            color: #26332b;
            font-weight: 700;
            font-size: 1.2rem;
            border-bottom: 1px solid rgba(94,117,100,0.08);
        }
        .sidebar .brand i { color: #5e7564; margin-right: 8px; }
        .sidebar .nav-link {
            color: rgba(38,51,43,0.65);
            border-radius: 12px;
            padding: 0.6rem 1rem;
            margin: 2px 8px;
            transition: all 0.2s;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .sidebar .nav-link i { margin-right: 12px; width: 20px; text-align: center; }
        .sidebar .nav-link:hover {
            background: rgba(94,117,100,0.1);
            color: #26332b;
        }
        .sidebar .nav-link.active {
            background: rgba(94,117,100,0.2);
            color: #5e7564;
            border-left: 3px solid #5e7564;
        }
        .sidebar .nav-link.logout {
            color: #c96a63;
            margin-top: 20px;
            border-top: 1px solid rgba(94,117,100,0.05);
            padding-top: 15px;
        }
        .sidebar .nav-link.logout:hover { background: rgba(201,106,99,0.1); color: #c96a63; }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 250px;
            padding: 80px 20px 20px;
            transition: margin-left 0.3s;
        }

        /* ===== GLASS ===== */
        .glass-card {
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(94,117,100,0.25);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(64,77,67,0.06);
            transition: all 0.3s ease;
        }
        .glass-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(64,77,67,0.10);
        }
        .glass-nav, .glass-footer {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(94,117,100,0.3);
        }
        .glass-footer {
            border-bottom: none;
            border-top: 1px solid rgba(94,117,100,0.3);
        }

        /* ===== STAT CARDS ===== */
        .stat-card {
            padding: 1.2rem;
            text-align: center;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px) scale(1.02);
        }
        .stat-card .icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .stat-card .number {
            font-size: 2.2rem;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
        }

        /* ===== TABLES ===== */
        .table {
            background: rgba(255,255,255,0.9);
            border-radius: 15px;
            overflow: hidden;
        }
        .table th { background: rgba(94,117,100,0.08); font-weight: 600; border-bottom: none; }
        .table td { vertical-align: middle; }

        /* ===== BADGES ===== */
        .badge-risk-high { background: #c96a63; color: #fff; }
        .badge-risk-medium { background: #d9a441; color: #fffdf8; }
        .badge-risk-low { background: #7fa383; color: #fffdf8; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            #sidebarToggle { display: inline-block !important; }
        }
        @media (min-width: 993px) {
            #sidebarToggle { display: none !important; }
        }
        @media (max-width: 576px) {
            .stat-card .number { font-size: 1.6rem; }
            .main-content { padding: 70px 10px 10px; }
        }
    </style>
</head>
<body>

<!-- ========== SIDEBAR ========== -->
<nav class="sidebar" id="sidebar">
    <div class="brand"><i class="bi bi-heart-fill"></i> Haven</div>
    <ul class="nav flex-column mt-2">
        <li class="nav-item"><a href="?tab=dashboard" class="nav-link <?= $tab=='dashboard'?'active':'' ?>"><i class="bi bi-grid"></i> Dashboard</a></li>
        <li class="nav-item"><a href="?tab=users" class="nav-link <?= $tab=='users'?'active':'' ?>"><i class="bi bi-people"></i> Users</a></li>
        <li class="nav-item"><a href="?tab=posts" class="nav-link <?= $tab=='posts'?'active':'' ?>"><i class="bi bi-file-text"></i> Posts</a></li>
        <li class="nav-item"><a href="?tab=comments" class="nav-link <?= $tab=='comments'?'active':'' ?>"><i class="bi bi-chat"></i> Comments</a></li>
        <li class="nav-item"><a href="?tab=ai" class="nav-link <?= $tab=='ai'?'active':'' ?>"><i class="bi bi-robot"></i> AI Center</a></li>
        <li class="nav-item"><a href="?tab=moderation" class="nav-link <?= $tab=='moderation'?'active':'' ?>"><i class="bi bi-shield-check"></i> Moderation</a></li>
        <li class="nav-item"><a href="?tab=volunteers" class="nav-link <?= $tab=='volunteers'?'active':'' ?>"><i class="bi bi-person-heart"></i> Volunteers</a></li>
        <li class="nav-item"><a href="?tab=resources" class="nav-link <?= $tab=='resources'?'active':'' ?>"><i class="bi bi-book"></i> Resources</a></li>
        <li class="nav-item"><a href="?tab=analytics" class="nav-link <?= $tab=='analytics'?'active':'' ?>"><i class="bi bi-graph-up"></i> Analytics</a></li>
        <li class="nav-item"><a href="?tab=reports" class="nav-link <?= $tab=='reports'?'active':'' ?>"><i class="bi bi-file-earmark-text"></i> Reports</a></li>
        <li class="nav-item"><a href="?tab=settings" class="nav-link <?= $tab=='settings'?'active':'' ?>"><i class="bi bi-gear"></i> Settings</a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link logout"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
    </ul>
</nav>

<!-- ========== TOP NAVBAR ========== -->
<nav class="navbar navbar-expand glass-nav fixed-top">
    <div class="container-fluid px-3">
        <button class="btn btn-link d-lg-none" id="sidebarToggle" style="color:inherit;"><i class="bi bi-list fs-4"></i></button>
        <span class="navbar-brand mb-0 h6">Admin Panel</span>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="text-muted small d-none d-sm-block"><?= date('l, M d, Y') ?></span>
            <button class="btn btn-outline-secondary btn-sm" id="darkToggle"><i class="bi bi-moon"></i></button>
            <span class="avatar rounded-circle bg-primary" style="width:32px;height:32px;display:inline-block;"></span>
        </div>
    </div>
</nav>

<!-- ========== MAIN CONTENT ========== -->
<div class="main-content" id="mainContent">

    <?php if ($admin_msg): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= $admin_msg ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if ($tab == 'dashboard'): ?>
        <!-- ===== DASHBOARD TAB ===== -->
        <div class="glass-card p-3 mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4>Good <?= date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening') ?>, Admin 👋</h4>
                    <p class="text-muted">Welcome back to Haven Mission Control Center</p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-primary p-2"><?= $tip ?></span>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="glass-card stat-card"><div class="icon">👥</div><div class="number counter" data-target="<?= $total_users ?>">0</div><p class="text-muted small">Total Users</p></div></div>
            <div class="col-md-3 col-6"><div class="glass-card stat-card"><div class="icon">📝</div><div class="number counter" data-target="<?= $total_posts ?>">0</div><p class="text-muted small">Total Posts</p></div></div>
            <div class="col-md-3 col-6"><div class="glass-card stat-card"><div class="icon">💬</div><div class="number counter" data-target="<?= $total_comments ?>">0</div><p class="text-muted small">Comments</p></div></div>
            <div class="col-md-3 col-6"><div class="glass-card stat-card"><div class="icon">🤖</div><div class="number counter" data-target="<?= $ai_replies ?>">0</div><p class="text-muted small">AI Replies</p></div></div>
        </div>

        <!-- Community Health & AI Center Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="glass-card p-3 text-center">
                    <h6>Community Health</h6>
                    <div id="healthGauge" style="height:180px;"></div>
                    <p class="text-muted small">Based on engagement, safety, and volunteer response</p>
                </div>
            </div>
            <div class="col-md-8">
                <div class="glass-card p-3">
                    <h6><i class="bi bi-robot text-purple"></i> AI Center</h6>
                    <div class="row g-2 mt-2">
                        <div class="col-md-3 col-6"><div class="glass-card p-2 text-center"><strong><?= $ai_replies ?></strong><br><span class="text-muted small">Total AI Responses</span></div></div>
                        <div class="col-md-3 col-6"><div class="glass-card p-2 text-center"><strong><?= $high_risk_posts ?></strong><br><span class="text-muted small">High Risk Flagged</span></div></div>
                        <div class="col-md-3 col-6"><div class="glass-card p-2 text-center"><strong>95%</strong><br><span class="text-muted small">AI Confidence</span></div></div>
                        <div class="col-md-3 col-6"><div class="glass-card p-2 text-center"><strong><?= $pending_ai_reviews ?></strong><br><span class="text-muted small">Pending Review</span></div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Moderation & Volunteers Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="glass-card p-3">
                    <h6><i class="bi bi-shield-check"></i> Moderation Center</h6>
                    <div class="row g-2 mt-2">
                        <div class="col-6"><div class="glass-card p-2 text-center"><strong><?= $pending_reports ?></strong><br><span class="text-muted small">Pending Reports</span></div></div>
                        <div class="col-6"><div class="glass-card p-2 text-center"><strong><?= $flagged_posts ?></strong><br><span class="text-muted small">Flagged Posts</span></div></div>
                        <div class="col-6"><div class="glass-card p-2 text-center"><strong><?= $resolved_reports ?></strong><br><span class="text-muted small">Resolved</span></div></div>
                        <div class="col-6"><div class="glass-card p-2 text-center"><strong><?= $dismissed_reports ?></strong><br><span class="text-muted small">Dismissed</span></div></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="glass-card p-3">
                    <h6><i class="bi bi-person-heart text-green"></i> Volunteer Center</h6>
                    <div class="row g-2 mt-2">
                        <div class="col-6"><div class="glass-card p-2 text-center"><strong><?= $volunteers ?></strong><br><span class="text-muted small">Total Volunteers</span></div></div>
                        <div class="col-6"><div class="glass-card p-2 text-center"><strong><?= $volunteer_requests ?></strong><br><span class="text-muted small">Pending Requests</span></div></div>
                        <div class="col-6"><div class="glass-card p-2 text-center"><strong><?= $active_consultations ?></strong><br><span class="text-muted small">Active Sessions</span></div></div>
                        <div class="col-6"><div class="glass-card p-2 text-center"><strong><?= $completed_consultations ?></strong><br><span class="text-muted small">Completed</span></div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Activity Chart -->
        <div class="glass-card p-3 mb-4">
            <h6>📊 Daily Activity (Last 7 Days)</h6>
            <div id="activityChart" style="height:250px;"></div>
        </div>

        <!-- Quick Actions -->
        <div class="glass-card p-3">
            <h6>⚡ Quick Actions</h6>
            <div class="d-flex flex-wrap gap-2">
                <a href="?tab=users" class="btn btn-outline-primary btn-sm"><i class="bi bi-people"></i> Manage Users</a>
                <a href="?tab=moderation" class="btn btn-outline-warning btn-sm"><i class="bi bi-shield-check"></i> Review Reports</a>
                <a href="?tab=ai" class="btn btn-outline-purple btn-sm"><i class="bi bi-robot"></i> AI Center</a>
                <a href="?tab=volunteers" class="btn btn-outline-success btn-sm"><i class="bi bi-person-heart"></i> Volunteers</a>
                <a href="?tab=settings" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear"></i> Settings</a>
            </div>
        </div>

    <?php elseif ($tab == 'users'): ?>
        <!-- ===== USER MANAGEMENT ===== -->
        <div class="glass-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5><i class="bi bi-people"></i> User Management</h5>
                <span class="text-muted small">Total: <?= $total_users ?> users</span>
            </div>
            <form method="GET" class="mb-3 d-flex gap-2">
                <input type="hidden" name="tab" value="users">
                <input type="text" name="q" class="form-control" placeholder="Search by username, email or name..." value="<?= escape($user_search) ?>">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i> Search</button>
                <?php if ($user_search !== ''): ?><a href="?tab=users" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
            </form>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= escape($u['username']) ?></td>
                                <td><?= escape($u['email']) ?></td>
                                <td><span class="badge bg-<?= $u['role']=='admin'?'danger':($u['role']=='volunteer'?'success':($u['role']=='moderator'?'warning':'secondary')) ?>"><?= ucfirst($u['role']) ?></span></td>
                                <td><?= $u['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Banned</span>' ?></td>
                                <td>
                                    <?php if ($u['id'] != $admin_id): ?>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <form method="POST" action="?tab=users&action=update_role" class="d-flex gap-1">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <select name="role" class="form-select form-select-sm" style="width:auto;">
                                                    <?php foreach (['user','volunteer','moderator','admin'] as $r): ?>
                                                        <option value="<?= $r ?>" <?= $u['role']==$r?'selected':'' ?>><?= ucfirst($r) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-outline-primary" onclick="return confirm('Change this user\'s role?')">Save</button>
                                            </form>
                                            <?php if ($u['is_active']): ?>
                                                <a href="?tab=users&action=ban_user&id=<?= $u['id'] ?>" class="btn btn-sm btn-warning" onclick="return confirm('Ban this user?')">Ban</a>
                                            <?php else: ?>
                                                <a href="?tab=users&action=unban_user&id=<?= $u['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Unban this user?')">Unban</a>
                                            <?php endif; ?>
                                            <a href="?tab=users&action=delete_user&id=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user? This cannot be undone.')">Delete</a>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">You</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab == 'posts'): ?>
        <!-- ===== POST MANAGEMENT ===== -->
        <div class="glass-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5><i class="bi bi-file-text"></i> Post Management</h5>
                <span class="text-muted small">Total: <?= $total_posts ?> posts</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr><th>Title</th><th>Author</th><th>Category</th><th>Mood</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $p): ?>
                            <tr>
                                <td><?= escape($p['title']) ?></td>
                                <td><?= escape($p['username']) ?></td>
                                <td><?= escape($p['category']) ?: '—' ?></td>
                                <td><?= $p['mood'] ? getMoodEmoji($p['mood']) : '—' ?></td>
                                <td><span class="badge bg-<?= $p['status']=='published'?'success':($p['status']=='flagged'?'warning':'danger') ?>"><?= ucfirst($p['status']) ?></span></td>
                                <td>
                                    <a href="post.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">View</a>
                                    <?php if ($p['status'] != 'flagged'): ?>
                                        <a href="?tab=posts&action=hide_post&id=<?= $p['id'] ?>" class="btn btn-sm btn-warning" onclick="return confirm('Hide this post?')">Hide</a>
                                    <?php endif; ?>
                                    <a href="?tab=posts&action=delete_post&id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this post?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab == 'comments'): ?>
        <!-- ===== COMMENT MANAGEMENT ===== -->
        <div class="glass-card p-3">
            <h5><i class="bi bi-chat"></i> Comment Management</h5>
            <p class="text-muted">Total comments: <?= $total_comments ?></p>
            <?php
            $comments = $pdo->query("SELECT c.*, u.username, p.title as post_title FROM comments c 
                                     JOIN users u ON c.user_id = u.id 
                                     JOIN posts p ON c.post_id = p.id 
                                     ORDER BY c.id DESC LIMIT 30")->fetchAll();
            ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Comment</th><th>User</th><th>Post</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($comments as $c): ?>
                            <tr>
                                <td><?= escape(substr($c['content'], 0, 50)) ?>...</td>
                                <td><?= escape($c['username']) ?></td>
                                <td><?= escape($c['post_title']) ?></td>
                                <td><a href="post.php?id=<?= $c['post_id'] ?>#comments" class="btn btn-sm btn-primary">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab == 'ai'): ?>
        <!-- ===== AI CENTER ===== -->
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="glass-card p-3 text-center"><h3><?= $ai_replies ?></h3><p class="text-muted small">Total AI Responses</p></div></div>
            <div class="col-md-3"><div class="glass-card p-3 text-center"><h3><?= $high_risk_posts ?></h3><p class="text-muted small">High Risk Flagged</p></div></div>
            <div class="col-md-3"><div class="glass-card p-3 text-center"><h3><?= $pending_ai_reviews ?></h3><p class="text-muted small">Pending Review</p></div></div>
            <div class="col-md-3"><div class="glass-card p-3 text-center"><h3>95%</h3><p class="text-muted small">AI Confidence</p></div></div>
        </div>
        <div class="glass-card p-3">
            <h5><i class="bi bi-robot text-purple"></i> AI Alerts & Analysis</h5>
            <?php if (empty($ai_alerts)): ?>
                <p class="text-muted">No AI alerts.</p>
            <?php else: ?>
                <?php foreach ($ai_alerts as $a): ?>
                    <div class="glass-card p-2 mb-2">
                        <p><strong>Post:</strong> <?= escape($a['title']) ?> (by <?= escape($a['username']) ?>)</p>
                        <p><strong>Risk:</strong> 
                            <span class="badge <?= $a['risk_score'] >= 40 ? 'badge-risk-high' : 'badge-risk-medium' ?>"><?= $a['risk_score'] ?>%</span>
                            <strong>Emotion:</strong> <?= $a['emotion'] ?: 'N/A' ?>
                        </p>
                        <p class="small"><strong>AI Reply:</strong> <?= nl2br(escape($a['ai_reply'])) ?></p>
                        <a href="post.php?id=<?= $a['post_id'] ?>" class="btn btn-sm btn-primary">View Post</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php elseif ($tab == 'moderation'): ?>
        <!-- ===== MODERATION CENTER ===== -->
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="glass-card p-3 text-center"><h3><?= $pending_reports ?></h3><p class="text-muted small">Pending Reports</p></div></div>
            <div class="col-md-3"><div class="glass-card p-3 text-center"><h3><?= $flagged_posts ?></h3><p class="text-muted small">Flagged Posts</p></div></div>
            <div class="col-md-3"><div class="glass-card p-3 text-center"><h3><?= $resolved_reports ?></h3><p class="text-muted small">Resolved</p></div></div>
            <div class="col-md-3"><div class="glass-card p-3 text-center"><h3><?= $dismissed_reports ?></h3><p class="text-muted small">Dismissed</p></div></div>
        </div>
        <div class="glass-card p-3">
            <h5><i class="bi bi-shield-check"></i> Pending Reports</h5>
            <?php if (empty($reports)): ?>
                <p class="text-muted">No pending reports. Great job!</p>
            <?php else: ?>
                <?php foreach ($reports as $r): ?>
                    <div class="glass-card p-2 mb-2">
                        <p><strong>Post:</strong> <?= escape($r['post_title']) ?></p>
                        <p><strong>Reason:</strong> <?= escape($r['reason']) ?></p>
                        <p><strong>Reported by:</strong> <?= escape($r['reporter']) ?></p>
                        <p><strong>Description:</strong> <?= escape($r['description']) ?></p>
                        <div>
                            <a href="?tab=moderation&action=resolve_report&id=<?= $r['id'] ?>" class="btn btn-sm btn-success">Resolve</a>
                            <a href="?tab=moderation&action=dismiss_report&id=<?= $r['id'] ?>" class="btn btn-sm btn-secondary">Dismiss</a>
                            <a href="post.php?id=<?= $r['post_id'] ?>" class="btn btn-sm btn-primary">View Post</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php elseif ($tab == 'volunteers'): ?>
        <!-- ===== VOLUNTEER MANAGEMENT ===== -->
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="glass-card p-3 text-center"><h3><?= $volunteers ?></h3><p class="text-muted small">Total Volunteers</p></div></div>
            <div class="col-md-3"><div class="glass-card p-3 text-center"><h3><?= $volunteer_requests ?></h3><p class="text-muted small">Pending Requests</p></div></div>
            <div class="col-md-3"><div class="glass-card p-3 text-center"><h3><?= $active_consultations ?></h3><p class="text-muted small">Active Sessions</p></div></div>
            <div class="col-md-3"><div class="glass-card p-3 text-center"><h3><?= $completed_consultations ?></h3><p class="text-muted small">Completed</p></div></div>
        </div>
        <div class="glass-card p-3">
            <h5><i class="bi bi-person-heart text-green"></i> Volunteer Requests</h5>
            <?php
            $vol_requests = $pdo->query("SELECT r.*, u.username, u.anonymous_name FROM consultation_requests r JOIN users u ON r.user_id = u.id WHERE r.status IN ('pending','active') ORDER BY r.created_at DESC LIMIT 20")->fetchAll();
            if (empty($vol_requests)): ?>
                <p class="text-muted">No volunteer requests.</p>
            <?php else: ?>
                <?php foreach ($vol_requests as $vr): ?>
                    <div class="glass-card p-2 mb-2">
                        <p><strong>User:</strong> <?= escape($vr['username']) ?> (<?= escape($vr['anonymous_name']) ?>)</p>
                        <p><strong>Message:</strong> <?= escape($vr['message']) ?></p>
                        <p><strong>Status:</strong> <span class="badge bg-<?= $vr['status']=='pending'?'warning':'success' ?>"><?= ucfirst($vr['status']) ?></span></p>
                        <?php if ($vr['volunteer_id']): ?>
                            <p class="text-muted small">Assigned to volunteer #<?= $vr['volunteer_id'] ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php elseif ($tab == 'resources'): ?>
        <!-- ===== RESOURCES ===== -->
        <div class="glass-card p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-book"></i> Resource Management</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addArticleModal"><i class="bi bi-plus-lg"></i> Add New Article</button>
            </div>
            <p class="text-muted mb-0">Publish and manage wellness articles shown on the Resources page.</p>
            <hr>
            <?php
            $resources = $pdo->query("SELECT * FROM articles ORDER BY created_at DESC LIMIT 20")->fetchAll();
            ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Title</th><th>Category</th><th>Views</th><th>Published</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($resources)): ?>
                            <tr><td colspan="5" class="text-muted text-center">No articles yet. Add your first one above.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($resources as $res): ?>
                            <tr>
                                <td><?= escape($res['title']) ?></td>
                                <td><?= escape($res['category']) ?></td>
                                <td><?= (int)$res['views'] ?></td>
                                <td><?= $res['is_published'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">Draft</span>' ?></td>
                                <td>
                                    <a href="article.php?id=<?= $res['id'] ?>" class="btn btn-sm btn-primary" target="_blank">View</a>
                                    <a href="?tab=resources&action=delete_article&id=<?= $res['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this article?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass-card p-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-tags"></i> Categories</h5>
                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal"><i class="bi bi-plus-lg"></i> Add Category</button>
            </div>
            <div class="table-responsive mt-2">
                <table class="table table-hover">
                    <thead><tr><th>Name</th><th>Type</th><th>Icon</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($all_categories)): ?>
                            <tr><td colspan="4" class="text-muted text-center">No categories yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($all_categories as $cat): ?>
                            <tr>
                                <td><?= escape($cat['name']) ?></td>
                                <td><span class="badge bg-secondary"><?= escape($cat['type']) ?></span></td>
                                <td><i class="bi <?= escape($cat['icon']) ?>"></i></td>
                                <td><a href="?tab=resources&action=delete_category&id=<?= $cat['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this category?')">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Article Modal -->
        <div class="modal fade" id="addArticleModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="?tab=resources&action=add_article" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Article</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
                        <div class="mb-2"><label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <?php foreach ($all_categories as $cat): ?>
                                    <option value="<?= escape($cat['name']) ?>"><?= escape($cat['name']) ?></option>
                                <?php endforeach; ?>
                                <option value="General">General</option>
                            </select>
                        </div>
                        <div class="mb-2"><label class="form-label">Excerpt</label><input type="text" name="excerpt" class="form-control"></div>
                        <div class="mb-2"><label class="form-label">Content</label><textarea name="content" rows="6" class="form-control" required></textarea></div>
                        <div class="mb-2"><label class="form-label">Image URL</label><input type="url" name="image_url" class="form-control" placeholder="https://..."></div>
                        <div class="mb-2"><label class="form-label">Reading Time (minutes)</label><input type="number" name="reading_time" class="form-control" value="5" min="1"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Publish Article</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Category Modal -->
        <div class="modal fade" id="addCategoryModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="?tab=resources&action=add_category" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
                        <div class="mb-2"><label class="form-label">Type</label>
                            <select name="type" class="form-select">
                                <option value="post">Post</option>
                                <option value="article">Article</option>
                            </select>
                        </div>
                        <div class="mb-2"><label class="form-label">Bootstrap Icon class</label><input type="text" name="icon" class="form-control" placeholder="bi-heart" value="bi-tag"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Category</button>
                    </div>
                </form>
            </div>
        </div>

    <?php elseif ($tab == 'analytics'): ?>
        <!-- ===== ANALYTICS ===== -->
        <div class="glass-card p-3 mb-3">
            <h5><i class="bi bi-graph-up"></i> Analytics Dashboard</h5>
            <p class="text-muted">Real-time community analytics</p>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="glass-card p-3">
                    <h6>Mood Distribution</h6>
                    <div id="moodPie" style="height:280px;"></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="glass-card p-3">
                    <h6>User Growth (Last 7 Days)</h6>
                    <div id="userGrowth" style="height:280px;"></div>
                </div>
            </div>
        </div>
        <div class="glass-card p-3">
            <h6>Emotion Distribution</h6>
            <div id="emotionBar" style="height:250px;"></div>
        </div>

    <?php elseif ($tab == 'reports'): ?>
        <!-- ===== REPORTS ===== -->
        <div class="glass-card p-3">
            <h5><i class="bi bi-file-earmark-text"></i> Data Export</h5>
            <p class="text-muted">Download a CSV snapshot of any dataset below.</p>
            <div class="row g-3">
                <div class="col-md-3"><div class="glass-card p-2 text-center"><h6>Users</h6><p><?= $total_users ?></p><a href="?tab=reports&action=export_csv&type=users" class="btn btn-sm btn-outline-primary">Export CSV</a></div></div>
                <div class="col-md-3"><div class="glass-card p-2 text-center"><h6>Posts</h6><p><?= $total_posts ?></p><a href="?tab=reports&action=export_csv&type=posts" class="btn btn-sm btn-outline-primary">Export CSV</a></div></div>
                <div class="col-md-3"><div class="glass-card p-2 text-center"><h6>Reports</h6><p><?= $pending_reports + $resolved_reports + $dismissed_reports ?></p><a href="?tab=reports&action=export_csv&type=reports" class="btn btn-sm btn-outline-primary">Export CSV</a></div></div>
                <div class="col-md-3"><div class="glass-card p-2 text-center"><h6>AI Analysis</h6><p><?= $ai_replies ?></p><a href="?tab=reports&action=export_csv&type=ai" class="btn btn-sm btn-outline-primary">Export CSV</a></div></div>
            </div>
        </div>

    <?php elseif ($tab == 'settings'): ?>
        <!-- ===== SETTINGS ===== -->
        <form method="POST" action="?tab=settings&action=save_settings">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="glass-card p-3 h-100">
                    <h6><i class="bi bi-gear"></i> General</h6>
                    <div class="mb-2"><label class="form-label small">Site Name</label><input type="text" name="site_name" class="form-control" value="<?= escape($get_setting('site_name', 'Haven')) ?>"></div>
                    <div class="mb-2"><label class="form-label small">Tagline</label><input type="text" name="site_tagline" class="form-control" value="<?= escape($get_setting('site_tagline', 'A place to breathe, connect & belong.')) ?>"></div>
                    <div class="mb-0"><label class="form-label small">Contact Email</label><input type="email" name="contact_email" class="form-control" value="<?= escape($get_setting('contact_email')) ?>"></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="glass-card p-3 h-100">
                    <h6><i class="bi bi-envelope"></i> Email (SMTP)</h6>
                    <div class="mb-2"><label class="form-label small">SMTP Host</label><input type="text" name="smtp_host" class="form-control" value="<?= escape($get_setting('smtp_host')) ?>"></div>
                    <div class="row">
                        <div class="col-6 mb-2"><label class="form-label small">Port</label><input type="text" name="smtp_port" class="form-control" value="<?= escape($get_setting('smtp_port', '587')) ?>"></div>
                        <div class="col-6 mb-2"><label class="form-label small">Username</label><input type="text" name="smtp_user" class="form-control" value="<?= escape($get_setting('smtp_user')) ?>"></div>
                    </div>
                    <div class="mb-0"><label class="form-label small">Password</label><input type="password" name="smtp_pass" class="form-control" placeholder="Leave blank to keep current" value=""></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="glass-card p-3 h-100">
                    <h6><i class="bi bi-robot"></i> AI Configuration</h6>
                    <div class="mb-2"><label class="form-label small">Gemini API Key</label><input type="password" name="gemini_api_key" class="form-control" placeholder="<?= $get_setting('gemini_api_key') ? '••••••••••••' : 'Not set' ?>"></div>
                    <div class="mb-0"><label class="form-label small">OpenRouter API Key</label><input type="password" name="openrouter_api_key" class="form-control" placeholder="<?= $get_setting('openrouter_api_key') ? '••••••••••••' : 'Not set' ?>"></div>
                    <p class="small text-muted mt-2 mb-0">Keys are stored server-side only and never sent to the browser.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="glass-card p-3 h-100">
                    <h6><i class="bi bi-shield-lock"></i> Security</h6>
                    <div class="mb-2"><label class="form-label small">Rate limit (requests / minute)</label><input type="number" name="rate_limit_requests" class="form-control" value="<?= escape($get_setting('rate_limit_requests', '60')) ?>"></div>
                    <div class="form-check mt-2">
                        <input type="hidden" name="maintenance_mode" value="0">
                        <input class="form-check-input" type="checkbox" name="maintenance_mode" value="1" id="maintCheck" <?= $get_setting('maintenance_mode') == '1' ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="maintCheck">Enable maintenance mode</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3 text-end">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Settings</button>
        </div>
        </form>
    <?php endif; ?>

</div>

<!-- ========== FOOTER ========== -->
<footer class="glass-footer mt-4 py-3 text-center">
    <p class="mb-0 small text-muted">© 2026 Haven Admin Panel v2.0</p>
</footer>

<!-- ========== SCRIPTS ========== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ============================================================
    // Lenis Smooth Scroll
    // ============================================================
    const lenis = new Lenis({ duration: 1.2 });
    function raf(time) { lenis.raf(time); requestAnimationFrame(raf); }
    requestAnimationFrame(raf);

    // ============================================================
    // GSAP Entrance
    // ============================================================
    gsap.utils.toArray(".glass-card:not(.glass-nav):not(.glass-footer)").forEach((card, i) => {
        gsap.from(card, {
            y: 20,
            opacity: 0,
            duration: 0.5,
            delay: i * 0.03,
            ease: "power2.out"
        });
    });

    // ============================================================
    // Counter Animation
    // ============================================================
    document.querySelectorAll('.counter').forEach(counter => {
        const target = parseInt(counter.dataset.target);
        if (target === 0) { counter.textContent = '0'; return; }
        const duration = 1500;
        const startTime = performance.now();
        function updateCounter(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const current = Math.floor(progress * target);
            counter.textContent = current.toLocaleString();
            if (progress < 1) requestAnimationFrame(updateCounter);
            else counter.textContent = target.toLocaleString();
        }
        requestAnimationFrame(updateCounter);
    });

    // ============================================================
    // Dark Mode
    // ============================================================
    document.getElementById('darkToggle').addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('adminDarkMode', document.body.classList.contains('dark-mode'));
    });
    if (localStorage.getItem('adminDarkMode') === 'true') document.body.classList.add('dark-mode');

    // ============================================================
    // Sidebar Toggle (mobile)
    // ============================================================
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('open');
    });
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebarToggle');
        if (window.innerWidth <= 992 && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ============================================================
    // Community Health Gauge
    // ============================================================
    <?php
    // Calculate health score based on real data
    $health = 85;
    if ($pending_reports > 10) $health -= 5;
    if ($flagged_posts > 20) $health -= 5;
    if ($high_risk_posts > 10) $health -= 5;
    if ($volunteers < 3) $health -= 5;
    $health = max(40, min(98, $health));
    ?>
    var healthOptions = {
        series: [<?= $health ?>],
        chart: { type: 'radialBar', height: 180, sparkline: { enabled: true } },
        colors: ['#5e7564'],
        plotOptions: {
            radialBar: {
                hollow: { size: '55%' },
                track: { background: 'rgba(64,77,67,0.04)' },
                dataLabels: { name: { show: true, fontSize: '14px' }, value: { fontSize: '24px', fontWeight: 700 } }
            }
        },
        labels: ['Health']
    };
    new ApexCharts(document.getElementById('healthGauge'), healthOptions).render();

    // ============================================================
    // Activity Chart (Last 7 Days)
    // ============================================================
    var activityOptions = {
        series: [{ name: 'Posts', data: <?= json_encode($daily_activity) ?> }],
        chart: { type: 'area', height: 250, toolbar: { show: false }, background: 'transparent' },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
        xaxis: { categories: <?= json_encode($daily_labels) ?>, labels: { style: { colors: '#7c857e' } } },
        yaxis: { labels: { style: { colors: '#7c857e' } } },
        colors: ['#5e7564'],
        grid: { borderColor: 'rgba(64,77,67,0.04)' }
    };
    new ApexCharts(document.getElementById('activityChart'), activityOptions).render();

    // ============================================================
    // Mood Pie Chart
    // ============================================================
    <?php if (!empty($mood_data)): ?>
    var moodPieOptions = {
        series: <?= json_encode($mood_data) ?>,
        chart: { type: 'pie', height: 280, toolbar: { show: false }, background: 'transparent' },
        colors: ['#7fa383', '#5e7564', '#c96a63', '#d9a441', '#c9a76b'],
        labels: <?= json_encode($mood_labels) ?>,
        legend: { position: 'bottom', labels: { colors: '#7c857e' } },
        dataLabels: { style: { colors: ['#fffdf8'] } }
    };
    new ApexCharts(document.getElementById('moodPie'), moodPieOptions).render();
    <?php endif; ?>

    // ============================================================
    // User Growth Chart (mock data, can be extended)
    // ============================================================
    var growthOptions = {
        series: [{ name: 'New Users', data: <?= json_encode($user_growth) ?> }],
        chart: { type: 'bar', height: 280, toolbar: { show: false }, background: 'transparent' },
        xaxis: { categories: <?= json_encode($daily_labels) ?>, labels: { style: { colors: '#7c857e' } } },
        yaxis: { labels: { style: { colors: '#7c857e' } } },
        colors: ['#c9a76b'],
        grid: { borderColor: 'rgba(64,77,67,0.04)' }
    };
    new ApexCharts(document.getElementById('userGrowth'), growthOptions).render();

    // ============================================================
    // Emotion Bar Chart (real data from ai_analysis)
    // ============================================================
    var emotionOptions = {
        series: [{ name: 'Posts', data: <?= json_encode($emotion_data) ?> }],
        chart: { type: 'bar', height: 250, toolbar: { show: false }, background: 'transparent' },
        xaxis: { categories: <?= json_encode($emotion_labels) ?>, labels: { style: { colors: '#7c857e' } } },
        yaxis: { labels: { style: { colors: '#7c857e' } } },
        colors: ['#5e7564'],
        grid: { borderColor: 'rgba(64,77,67,0.04)' }
    };
    new ApexCharts(document.getElementById('emotionBar'), emotionOptions).render();

    // ============================================================
    // Toast notifications for any messages
    // ============================================================
    <?php if ($admin_msg): ?>
    Toastify({
        text: "<?= addslashes($admin_msg) ?>",
        duration: 3000,
        gravity: "top",
        position: "right",
        style: { background: "linear-gradient(135deg, #7fa383, #5e7564)" }
    }).showToast();
    <?php endif; ?>
</script>
</body>
</html>
