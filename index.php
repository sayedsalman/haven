<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
include 'includes/functions.php';


$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_posts = $pdo->query("SELECT COUNT(*) FROM posts WHERE status='published'")->fetchColumn();
$total_support = $pdo->query("SELECT COUNT(*) FROM reactions WHERE reaction_type='support'")->fetchColumn();
$total_volunteers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='volunteer'")->fetchColumn();
$total_countries = $pdo->query("SELECT COUNT(DISTINCT country) FROM users WHERE country != ''")->fetchColumn() ?: 18;


$today_support = $pdo->query("SELECT COUNT(*) FROM reactions WHERE reaction_type='support' AND DATE(created_at)=CURDATE()")->fetchColumn();


$trending = $pdo->query("SELECT p.*, u.anonymous_name, 
                         (SELECT COUNT(*) FROM reactions WHERE post_id = p.id) as reaction_count,
                         (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
                         FROM posts p 
                         JOIN users u ON p.user_id = u.id 
                         WHERE p.status='published' 
                         ORDER BY reaction_count DESC, p.created_at DESC LIMIT 6")->fetchAll();


$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 4;
$offset = ($page - 1) * $limit;
$latest_posts = $pdo->query("SELECT p.*, u.anonymous_name,
                             (SELECT COUNT(*) FROM reactions WHERE post_id = p.id) as reaction_count,
                             (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
                             FROM posts p 
                             JOIN users u ON p.user_id = u.id 
                             WHERE p.status='published' 
                             ORDER BY p.created_at DESC 
                             LIMIT $limit OFFSET $offset")->fetchAll();
$total_posts_count = $pdo->query("SELECT COUNT(*) FROM posts WHERE status='published'")->fetchColumn();

$articles = $pdo->query("SELECT * FROM articles ORDER BY created_at DESC LIMIT 4")->fetchAll();


$mood_stats = $pdo->query("SELECT mood, COUNT(*) as count FROM posts WHERE mood != '' GROUP BY mood")->fetchAll();
$mood_distribution = [];
$mood_emoji_map = ['happy'=>'😊', 'okay'=>'🙂', 'sad'=>'😔', 'stressed'=>'😰', 'angry'=>'😡', 'tired'=>'😴'];
foreach ($mood_stats as $m) {
    $mood_distribution[$m['mood']] = $m['count'];
}
if (empty($mood_distribution)) {
    $mood_distribution = ['happy' => 42, 'okay' => 28, 'sad' => 18, 'stressed' => 12];
}
$total_moods = array_sum($mood_distribution);
$mood_percentages = [];
foreach ($mood_distribution as $mood => $count) {
    $mood_percentages[] = ['mood' => $mood, 'emoji' => $mood_emoji_map[$mood] ?? '😊', 'percent' => round(($count / $total_moods) * 100)];
}


$tips = [
    "Remember to take a break every hour while studying. Your mind needs rest.",
    "Practice deep breathing for 5 minutes to reduce anxiety. Inhale peace, exhale stress.",
    "Drink water regularly – hydration improves focus and mood.",
    "Take a short walk to refresh your mind. A change of scenery helps.",
    "Write down three things you're grateful for today. Gratitude rewires your brain.",
    "Connect with a friend. You're not alone in this journey.",
    "Listen to calming music to soothe your mind.",
    "Get 7-8 hours of sleep. Rest is essential for mental wellness."
];
$tip_index = date('z') % count($tips);
$tip = $tips[$tip_index];

// Daily quote
$quotes = [
    "You don't have to carry everything alone. We're here with you.",
    "Your feelings are valid. They matter. They deserve to be heard.",
    "One step at a time. You've got this. Progress, not perfection.",
    "Healing is a journey, not a destination. Every step counts.",
    "You are stronger than you think. You've survived everything so far.",
];
$quote = $quotes[array_rand($quotes)];


$testimonials = [];
try {
    $testimonials = $pdo->query("SELECT * FROM testimonials ORDER BY id DESC LIMIT 4")->fetchAll();
} catch (PDOException $e) {
    // Table doesn't exist; use fallback
    $testimonials = [
        ['content' => "Sharing here helped me feel less alone. I found hope again.", 'author' => "Anonymous"],
        ['content' => "The volunteers truly listen. I felt heard for the first time.", 'author' => "Anonymous"],
        ['content' => "MindGuide AI gave me the courage to seek help. Thank you.", 'author' => "Anonymous"],
        ['content' => "This community is a lifeline. I'm grateful every day.", 'author' => "Anonymous"],
    ];
}
if (empty($testimonials)) {
    $testimonials = [
        ['content' => "Sharing here helped me feel less alone.", 'author' => "Anonymous"],
    ];
}


$categories_data = $pdo->query("SELECT category, COUNT(*) as count FROM posts WHERE category != '' GROUP BY category ORDER BY count DESC LIMIT 8")->fetchAll();
$category_icons = [
    'Academic Stress' => '🎓', 'Career' => '💼', 'Relationship' => '💔', 'Sleep' => '😴',
    'Anxiety' => '😟', 'Motivation' => '🌱', 'Family' => '🏡', 'Self Care' => '🧠',
    'Stress' => '😰', 'Depression' => '🌧️', 'Work' => '💻', 'Loneliness' => '🌙'
];
$categories = [];
foreach ($categories_data as $cat) {
    $icon = $category_icons[$cat['category']] ?? '📌';
    $categories[] = ['name' => $cat['category'], 'icon' => $icon, 'count' => $cat['count']];
}
if (empty($categories)) {
    $default_cats = ['Academic Stress', 'Career', 'Relationship', 'Sleep', 'Anxiety', 'Motivation', 'Family', 'Self Care'];
    foreach ($default_cats as $cat) {
        $categories[] = ['name' => $cat, 'icon' => $category_icons[$cat] ?? '📌', 'count' => 0];
    }
}


$random_post = $pdo->query("SELECT p.*, u.anonymous_name FROM posts p JOIN users u ON p.user_id = u.id WHERE p.status='published' ORDER BY RAND() LIMIT 1")->fetch();


$reflections = [
    "What made you smile today?",
    "What's one thing you're proud of?",
    "What would you tell your younger self?",
    "What brings you peace?",
    "What's a small kindness you received recently?",
];
$reflection = $reflections[array_rand($reflections)];
$stmt = $pdo->prepare("SELECT content FROM posts WHERE status='published' AND content LIKE ? ORDER BY RAND() LIMIT 2");
$stmt->execute(['%' . $reflection . '%']);
$reflection_responses = $stmt->fetchAll();
if (empty($reflection_responses)) {
    $reflection_responses = [
        ['content' => "My friend called me today. It made my whole week."],
        ['content' => "I finally completed my assignment. Relief!"],
    ];
}


if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && isset($_GET['page'])) {
    $page = intval($_GET['page']);
    $limit = 4;
    $offset = ($page - 1) * $limit;
    $posts = $pdo->query("SELECT p.*, u.anonymous_name,
                           (SELECT COUNT(*) FROM reactions WHERE post_id = p.id) as reaction_count,
                           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
                           FROM posts p JOIN users u ON p.user_id = u.id 
                           WHERE p.status='published' 
                           ORDER BY p.created_at DESC 
                           LIMIT $limit OFFSET $offset")->fetchAll();
    foreach ($posts as $post) {
        include 'includes/_home_post_card.php';
    }
    exit;
}


if (isset($_GET['search']) && strlen($_GET['search']) >= 2) {
    $q = '%' . trim($_GET['search']) . '%';
    $results = [];
    // Posts
    $stmt = $pdo->prepare("SELECT id, title, content, 'post' as type FROM posts WHERE status='published' AND (title LIKE ? OR content LIKE ?) LIMIT 10");
    $stmt->execute([$q, $q]);
    $results = array_merge($results, $stmt->fetchAll());
    // Articles
    $stmt = $pdo->prepare("SELECT id, title, excerpt, 'article' as type FROM articles WHERE title LIKE ? OR content LIKE ? LIMIT 5");
    $stmt->execute([$q, $q]);
    $results = array_merge($results, $stmt->fetchAll());
    // Categories
    $stmt = $pdo->prepare("SELECT DISTINCT category as title, 'category' as type FROM posts WHERE category LIKE ? LIMIT 5");
    $stmt->execute([$q]);
    $results = array_merge($results, $stmt->fetchAll());
    // Users (anonymous names)
    $stmt = $pdo->prepare("SELECT anonymous_name as title, 'user' as type FROM profiles WHERE anonymous_name LIKE ? LIMIT 5");
    $stmt->execute([$q]);
    $results = array_merge($results, $stmt->fetchAll());
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}


?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Haven – Share. Heal. Support.</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Poppins:wght@300..700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Swiper JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <!-- Toastify -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <!-- GSAP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/TextPlugin.min.js"></script>
    <!-- Lenis Smooth Scroll -->
    <script src="https://unpkg.com/lenis@1.1.13/dist/lenis.min.js"></script>
    <style>
      
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #F5F8FC;
            color: #1E293B;
            transition: background 0.3s, color 0.3s;
            overflow-x: hidden;
        }
        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; }
        a { text-decoration: none; transition: 0.2s; }
        img { max-width: 100%; }

     
        body.dark-mode {
            background: #0F172A;
            color: #E2E8F0;
        }
        body.dark-mode .glass-card,
        body.dark-mode .glass-nav,
        body.dark-mode .glass-footer,
        body.dark-mode .modal-content,
        body.dark-mode .offcanvas {
            background: rgba(15, 23, 42, 0.85);
            border-color: rgba(255,255,255,0.08);
            color: #E2E8F0;
        }
        body.dark-mode .glass-card:hover {
            background: rgba(30, 41, 59, 0.9);
        }
        body.dark-mode .form-control {
            background: rgba(30, 41, 59, 0.8);
            color: #E2E8F0;
            border-color: rgba(255,255,255,0.15);
        }
        body.dark-mode .text-muted { color: #94A3B8 !important; }
        body.dark-mode .btn-outline-primary { color: #5B8DEF; border-color: #5B8DEF; }
        body.dark-mode .btn-outline-secondary { color: #94A3B8; border-color: #475569; }
        body.dark-mode .bg-light { background: rgba(30, 41, 59, 0.5) !important; }

      
        .glass-card {
            background: rgba(255, 255, 255, 0.55);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.06);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 48px rgba(0,0,0,0.10);
        }
        .glass-nav, .glass-footer {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }
        .glass-footer {
            border-bottom: none;
            border-top: 1px solid rgba(255,255,255,0.3);
        }

     
        .btn {
            border-radius: 14px;
            font-weight: 500;
            padding: 0.6rem 1.8rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #5B8DEF, #8B5CF6);
            border: none;
            color: #fff;
        }
        .btn-primary:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(91, 141, 239, 0.35);
        }
        .btn-outline-primary {
            border-color: #5B8DEF;
            color: #5B8DEF;
        }
        .btn-outline-primary:hover {
            background: #5B8DEF;
            color: #fff;
        }
        .btn-ghost {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.3);
            color: #1E293B;
        }
        body.dark-mode .btn-ghost {
            color: #E2E8F0;
            border-color: rgba(255,255,255,0.15);
        }
        .form-control {
            border-radius: 15px;
            border: 1px solid rgba(0,0,0,0.06);
            background: rgba(255,255,255,0.5);
            padding: 0.8rem 1.2rem;
            transition: 0.3s;
        }
        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(91, 141, 239, 0.15);
            border-color: #5B8DEF;
        }

        .glass-nav {
            transition: background 0.3s, box-shadow 0.3s;
            padding: 0.5rem 0;
        }
        .glass-nav.scrolled {
            background: rgba(255, 255, 255, 0.85);
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        }
        body.dark-mode .glass-nav.scrolled {
            background: rgba(15, 23, 42, 0.9);
        }
        .navbar-brand { font-weight: 700; font-size: 1.3rem; }
        .navbar-brand i { color: #5B8DEF; }

        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding: 100px 0 60px;
        }
        .blob-container {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.3;
            animation: floatBlob 20s infinite alternate ease-in-out;
        }
        .blob-1 { width: 400px; height: 400px; background: #5B8DEF; top: -100px; left: -100px; }
        .blob-2 { width: 500px; height: 500px; background: #8B5CF6; bottom: -150px; right: -150px; animation-delay: 5s; }
        .blob-3 { width: 300px; height: 300px; background: #4ADE80; top: 30%; right: 10%; animation-delay: 10s; opacity: 0.15; }
        .blob-4 { width: 250px; height: 250px; background: #FBBF24; bottom: 20%; left: 10%; animation-delay: 3s; opacity: 0.15; }
        @keyframes floatBlob {
            0% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.05); }
            66% { transform: translate(-20px, 20px) scale(0.95); }
            100% { transform: translate(20px, -10px) scale(1.08); }
        }
        .hero-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }
        .hero-title {
            font-size: 4.5rem;
            font-weight: 700;
            line-height: 1.1;
            background: linear-gradient(135deg, #5B8DEF, #8B5CF6, #4ADE80);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }
        .hero-subtitle {
            font-size: 1.2rem;
            color: #64748B;
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        body.dark-mode .hero-subtitle { color: #94A3B8; }
        .hero-cta .btn { margin: 0.3rem; }
        .hero-moods {
            margin-top: 2.5rem;
            font-size: 2.2rem;
            display: flex;
            justify-content: center;
            gap: 1.2rem;
            flex-wrap: wrap;
        }
        .hero-moods span {
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: inline-block;
        }
        .hero-moods span:hover {
            transform: scale(1.4) rotate(-8deg);
        }


        .search-section {
            position: relative;
            max-width: 600px;
            margin: -30px auto 40px;
            z-index: 2;
        }
        .search-section .form-control {
            padding: 1rem 1.5rem;
            padding-right: 3.5rem;
        }
        .search-section .search-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #94A3B8;
        }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
            border-radius: 15px;
            margin-top: 8px;
            max-height: 400px;
            overflow-y: auto;
            display: none;
            z-index: 1000;
            box-shadow: 0 12px 40px rgba(0,0,0,0.08);
        }
        body.dark-mode .search-results {
            background: rgba(15, 23, 42, 0.92);
        }
        .search-results .result-item {
            padding: 0.8rem 1.2rem;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            cursor: pointer;
            transition: 0.2s;
        }
        .search-results .result-item:hover {
            background: rgba(91, 141, 239, 0.06);
        }
        .search-results .result-item .badge {
            font-size: 0.7rem;
        }
        .search-results .result-item .type-icon {
            margin-right: 8px;
        }

 
        .stats-section .stat-card {
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s;
        }
        .stats-section .stat-card:hover {
            transform: translateY(-5px) scale(1.02);
        }
        .stats-section .stat-card .number {
            font-size: 2.8rem;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #5B8DEF, #8B5CF6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stats-section .stat-card .label {
            color: #64748B;
            font-size: 0.9rem;
            margin-top: 0.2rem;
        }
        body.dark-mode .stats-section .stat-card .label { color: #94A3B8; }

        .quote-section {
            background: linear-gradient(135deg, rgba(91,141,239,0.05), rgba(139,92,246,0.08));
            padding: 4rem 2rem;
            text-align: center;
            border-radius: 20px;
        }
        .quote-section .quote-text {
            font-size: 2.5rem;
            font-weight: 300;
            font-style: italic;
            font-family: 'Poppins', sans-serif;
            line-height: 1.4;
        }
        .quote-section .quote-author {
            color: #64748B;
            margin-top: 0.5rem;
        }


        .post-card .avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5B8DEF, #8B5CF6);
            display: inline-block;
            flex-shrink: 0;
        }
        .post-card .mood-badge { font-size: 1.2rem; }
        .post-card .post-actions .btn {
            padding: 0.2rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 20px;
            background: rgba(255,255,255,0.3);
            border: 1px solid rgba(0,0,0,0.04);
        }
        .post-card .post-actions .btn:hover {
            background: rgba(255,255,255,0.6);
            transform: scale(1.05);
        }
        body.dark-mode .post-card .post-actions .btn {
            background: rgba(255,255,255,0.05);
            border-color: rgba(255,255,255,0.08);
        }
        body.dark-mode .post-card .post-actions .btn:hover {
            background: rgba(255,255,255,0.12);
        }

        .category-card {
            padding: 1.5rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .category-card:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 16px 40px rgba(0,0,0,0.08);
        }
        .category-card .icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .category-card .name { font-weight: 600; font-size: 0.95rem; }

        .ai-card {
            padding: 1.8rem;
            text-align: center;
            height: 100%;
            transition: all 0.4s;
        }
        .ai-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 40px rgba(91,141,239,0.15);
        }
        .ai-card .icon { font-size: 3rem; margin-bottom: 1rem; }


        .faq-item {
            border: none;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            padding: 0.8rem 0;
        }
        .faq-item:last-child { border-bottom: none; }
        .faq-question {
            font-weight: 600;
            cursor: pointer;
            padding: 0.5rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.2s;
        }
        .faq-question:hover { color: #5B8DEF; }
        .faq-answer {
            padding: 0.5rem 0;
            color: #64748B;
            display: none;
        }
        .faq-answer.open { display: block; }
        body.dark-mode .faq-answer { color: #94A3B8; }

        .cta-section {
            background: linear-gradient(135deg, #5B8DEF, #8B5CF6);
            border-radius: 24px;
            padding: 4rem 2rem;
            text-align: center;
            color: #fff;
        }
        .cta-section h2 { font-size: 2.8rem; font-weight: 700; }
        .cta-section p { opacity: 0.85; font-size: 1.1rem; }
        .cta-section .btn-cta {
            background: #fff;
            color: #5B8DEF;
            font-weight: 600;
            padding: 0.8rem 2.5rem;
            border-radius: 50px;
            transition: 0.3s;
        }
        .cta-section .btn-cta:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }
        .cta-section .btn-cta-outline {
            background: transparent;
            color: #fff;
            border: 2px solid rgba(255,255,255,0.5);
            padding: 0.8rem 2.5rem;
            border-radius: 50px;
            transition: 0.3s;
        }
        .cta-section .btn-cta-outline:hover {
            background: rgba(255,255,255,0.1);
            border-color: #fff;
        }

        .testimonial-card {
            padding: 2rem;
            text-align: center;
            min-height: 200px;
        }
        .testimonial-card .quote-icon {
            font-size: 2.5rem;
            color: #5B8DEF;
            opacity: 0.3;
        }
        .testimonial-card .content {
            font-size: 1.1rem;
            font-style: italic;
            margin: 0.5rem 0;
        }
        .testimonial-card .author {
            color: #64748B;
            font-size: 0.9rem;
        }


        .footer { padding: 3rem 0 1rem; }
        .footer .footer-link {
            color: #64748B;
            transition: 0.2s;
            display: block;
            padding: 0.2rem 0;
        }
        .footer .footer-link:hover { color: #5B8DEF; }
        body.dark-mode .footer .footer-link { color: #94A3B8; }
        body.dark-mode .footer .footer-link:hover { color: #5B8DEF; }

        .floating-chat {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1050;
            width: 60px; height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5B8DEF, #8B5CF6);
            color: #fff;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 32px rgba(91,141,239,0.35);
            cursor: pointer;
            transition: 0.3s;
            border: none;
        }
        .floating-chat:hover { transform: scale(1.1) rotate(5deg); }
        .floating-chat .tooltip {
            position: absolute;
            right: 70px;
            background: rgba(0,0,0,0.8);
            color: #fff;
            padding: 0.4rem 0.8rem;
            border-radius: 10px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            transition: 0.3s;
            pointer-events: none;
        }
        .floating-chat:hover .tooltip { opacity: 1; }

        .scroll-top {
            position: fixed;
            bottom: 100px;
            right: 30px;
            z-index: 1040;
            width: 45px; height: 45px;
            border-radius: 50%;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.3s;
            opacity: 0;
            transform: translateY(20px);
            pointer-events: none;
        }
        .scroll-top.visible {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        .scroll-top:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        body.dark-mode .scroll-top {
            background: rgba(30,41,59,0.8);
            color: #E2E8F0;
        }

    
        @media (max-width: 992px) {
            .hero-title { font-size: 3rem; }
            .quote-section .quote-text { font-size: 1.8rem; }
            .cta-section h2 { font-size: 2.2rem; }
        }
        @media (max-width: 576px) {
            .hero-title { font-size: 2.2rem; }
            .hero-subtitle { font-size: 1rem; }
            .hero-moods { font-size: 1.8rem; gap: 0.8rem; }
            .stats-section .stat-card .number { font-size: 2rem; }
            .floating-chat { width: 50px; height: 50px; font-size: 1.4rem; bottom: 20px; right: 20px; }
            .scroll-top { bottom: 80px; right: 20px; width: 38px; height: 38px; }
            .cta-section { padding: 2.5rem 1.5rem; }
            .cta-section h2 { font-size: 1.8rem; }
        }
    </style>
</head>
<body>


<nav class="navbar navbar-expand-lg glass-nav fixed-top" id="mainNav">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="bi bi-heart-fill"></i> Haven</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="community.php">Community</a></li>
                <li class="nav-item"><a class="nav-link" href="articles.php">Resources</a></li>
                <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link btn btn-primary btn-sm text-white px-3" href="register.php">Join Free</a></li>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                <?php endif; ?>
                <li class="nav-item">
                    <button class="btn btn-outline-secondary btn-sm" id="darkToggle"><i class="bi bi-moon"></i></button>
                </li>
            </ul>
        </div>
    </div>
</nav>


<section class="hero-section" id="hero">
    <div class="blob-container">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
        <div class="blob blob-4"></div>
    </div>
    <div class="container hero-content">
        <h1 class="hero-title" id="heroTitle">Share. Heal. Support.</h1>
        <p class="hero-subtitle" id="heroSubtitle">AI-assisted anonymous community for mental wellness. Connect, share, and grow together.</p>
        <div class="hero-cta">
            <?php if (isLoggedIn()): ?>
                <a href="feed.php" class="btn btn-primary btn-lg rounded-pill px-5">Go to Feed</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-primary btn-lg rounded-pill px-5">Join Community</a>
                <a href="community.php" class="btn btn-ghost btn-lg rounded-pill px-5">Explore Posts</a>
            <?php endif; ?>
        </div>
        <div class="hero-moods" id="heroMoods">
            <span data-mood="happy">😊</span>
            <span data-mood="calm">😌</span>
            <span data-mood="okay">🙂</span>
            <span data-mood="sad">😔</span>
            <span data-mood="stressed">😰</span>
            <span data-mood="angry">😡</span>
        </div>
        <p class="text-muted small mt-2">Click a mood to see how Haven supports you</p>
    </div>
</section>


<div class="container search-section">
    <div class="position-relative">
        <input type="text" class="form-control" id="searchInput" placeholder="Search posts, articles, categories, people..." autocomplete="off">
        <button class="search-btn" id="searchBtn"><i class="bi bi-search"></i></button>
        <div class="search-results" id="searchResults"></div>
    </div>
</div>

<!-- ===== STATS ===== -->
<section class="container stats-section py-4">
    <div class="row g-3">
        <div class="col-md-3 col-6"><div class="glass-card stat-card"><div class="number counter" data-target="<?= $total_users ?>">0</div><div class="label">👥 Members</div></div></div>
        <div class="col-md-3 col-6"><div class="glass-card stat-card"><div class="number counter" data-target="<?= $total_posts ?>">0</div><div class="label">📝 Posts</div></div></div>
        <div class="col-md-3 col-6"><div class="glass-card stat-card"><div class="number counter" data-target="<?= $total_support ?>">0</div><div class="label">❤️ Support Given</div></div></div>
        <div class="col-md-3 col-6"><div class="glass-card stat-card"><div class="number counter" data-target="<?= $total_volunteers ?>">0</div><div class="label">🤝 Volunteers</div></div></div>
    </div>
</section>

<!-- ===== WELLNESS TIP + QUOTE ===== -->
<section class="container py-3">
    <div class="row g-3">
        <div class="col-md-6">
            <div class="glass-card p-4" style="background:rgba(91,141,239,0.06);">
                <p class="text-primary mb-1"><i class="bi bi-lightbulb"></i> Today's Wellness Tip</p>
                <p class="lead"><?= $tip ?></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="quote-section p-4">
                <p class="quote-text">"<?= $quote ?>"</p>
                <p class="quote-author">— Daily Inspiration</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== COMMUNITY PULSE ===== -->
<section class="container py-3">
    <div class="glass-card p-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5><i class="bi bi-heart-pulse text-primary"></i> Community Pulse</h5>
                <p class="text-muted small">Today's anonymous mood distribution</p>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($mood_percentages as $m): ?>
                        <div>
                            <span style="font-size:1.5rem;"><?= $m['emoji'] ?></span>
                            <span class="fw-bold"><?= $m['percent'] ?>%</span>
                            <span class="text-muted small"><?= ucfirst($m['mood']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-muted small mt-2">💚 <?= $today_support ?> people received support today</p>
            </div>
            <div class="col-md-6 text-center">
                <div id="moodPulseChart" style="height:100px;"></div>
            </div>
        </div>
    </div>
</section>

<!-- ===== TRENDING POSTS ===== -->
<section class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>🔥 Trending Discussions</h4>
        <a href="community.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="swiper trendingSwiper">
        <div class="swiper-wrapper">
            <?php foreach ($trending as $post): ?>
                <div class="swiper-slide">
                    <div class="glass-card p-3 h-100">
                        <div class="d-flex align-items-center mb-2">
                            <span class="avatar me-2" style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#5B8DEF,#8B5CF6);display:inline-block;"></span>
                            <strong><?= escape($post['anonymous_name']) ?></strong>
                            <?php if ($post['mood']): ?><span class="ms-1"><?= getMoodEmoji($post['mood']) ?></span><?php endif; ?>
                            <span class="text-muted ms-auto small"><?= timeAgo($post['created_at']) ?></span>
                        </div>
                        <h6><?= escape($post['title']) ?></h6>
                        <p class="small text-muted"><?= escape(substr($post['content'], 0, 80)) ?>...</p>
                        <div class="d-flex gap-2 mt-2">
                            <span class="badge bg-light">❤️ <?= $post['reaction_count'] ?></span>
                            <span class="badge bg-light">💬 <?= $post['comment_count'] ?></span>
                            <a href="post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline-primary ms-auto">Read</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
    </div>
</section>

<!-- ===== LATEST FEED ===== -->
<section class="container py-4" id="feedSection">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>📝 Latest from the Community</h4>
        <a href="community.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div id="publicFeed">
        <?php if (empty($latest_posts)): ?>
            <div class="glass-card p-4 text-center"><p class="text-muted">No posts yet. Be the first to share!</p></div>
        <?php else: ?>
            <?php foreach ($latest_posts as $post): ?>
                <?php include 'includes/_home_post_card.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php if ($total_posts_count > $limit): ?>
        <div class="text-center mt-3">
            <button class="btn btn-primary" id="loadMoreFeed" data-page="<?= $page+1 ?>">Load More</button>
        </div>
    <?php endif; ?>
</section>

<!-- ===== CATEGORIES ===== -->
<section class="container py-4">
    <h4 class="mb-3">📂 Mental Health Categories</h4>
    <div class="row g-3">
        <?php foreach ($categories as $cat): ?>
            <div class="col-md-3 col-6">
                <a href="community.php?category=<?= urlencode($cat['name']) ?>" class="text-decoration-none">
                    <div class="glass-card category-card">
                        <div class="icon"><?= $cat['icon'] ?></div>
                        <div class="name"><?= escape($cat['name']) ?></div>
                        <?php if ($cat['count'] > 0): ?><span class="text-muted small"><?= $cat['count'] ?> posts</span><?php endif; ?>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ===== AI FEATURES ===== -->
<section class="container py-4">
    <h4 class="mb-3">🤖 AI-Powered Support</h4>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="glass-card ai-card" style="border-top:4px solid #5B8DEF;">
                <div class="icon">🧠</div>
                <h5>MindGuide AI</h5>
                <p class="text-muted small">Reads posts and provides compassionate, supportive replies based on the user's emotional state.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card ai-card" style="border-top:4px solid #8B5CF6;">
                <div class="icon">🛡️</div>
                <h5>MindShield</h5>
                <p class="text-muted small">Detects harmful content and high-risk posts, automatically flagging them for volunteer review.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card ai-card" style="border-top:4px solid #4ADE80;">
                <div class="icon">📊</div>
                <h5>MindInsight</h5>
                <p class="text-muted small">Tracks wellness progress, mood patterns, and provides personalized recommendations.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== ARTICLES ===== -->
<section class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>📚 Knowledge Hub</h4>
        <a href="articles.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="row g-3">
        <?php foreach ($articles as $art): ?>
            <div class="col-md-3 col-6">
                <a href="article.php?id=<?= $art['id'] ?>" class="text-decoration-none">
                    <div class="glass-card p-3 h-100">
                        <h6><?= escape($art['title']) ?></h6>
                        <p class="text-muted small"><?= escape(substr($art['excerpt'] ?? $art['content'], 0, 60)) ?>...</p>
                        <span class="badge bg-light">👁️ <?= $art['views'] ?></span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ===== VIDEOS ===== -->
<section class="container py-4">
    <h4 class="mb-3">🎬 Video Learning Center</h4>
    <div class="swiper videoSwiper">
        <div class="swiper-wrapper">
            <div class="swiper-slide">
                <div class="glass-card p-3 text-center">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/1t-VD6D8_iQ?rel=0" allowfullscreen></iframe>
                    </div>
                    <p class="mt-2 fw-bold">5 Minute Meditation</p>
                </div>
            </div>
            <div class="swiper-slide">
                <div class="glass-card p-3 text-center">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/4Fi1uQa8j-Q?rel=0" allowfullscreen></iframe>
                    </div>
                    <p class="mt-2 fw-bold">Anxiety Relief</p>
                </div>
            </div>
            <div class="swiper-slide">
                <div class="glass-card p-3 text-center">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/4tV-UXL81cI?rel=0" allowfullscreen></iframe>
                    </div>
                    <p class="mt-2 fw-bold">Sleep Hypnosis</p>
                </div>
            </div>
        </div>
        <div class="swiper-pagination"></div>
    </div>
</section>

<!-- ===== DAILY REFLECTION ===== -->
<section class="container py-4">
    <div class="glass-card p-4" style="background:linear-gradient(135deg, rgba(91,141,239,0.05), rgba(139,92,246,0.05));">
        <h5><i class="bi bi-stars text-primary"></i> Daily Reflection</h5>
        <p class="lead"><?= $reflection ?></p>
        <div class="row">
            <?php foreach ($reflection_responses as $resp): ?>
                <div class="col-md-6">
                    <div class="glass-card p-2 small">"<?= escape($resp['content']) ?>"</div>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-sm btn-outline-primary mt-2" onclick="location.href='<?= isLoggedIn() ? 'create-post.php' : 'login.php' ?>'">
            Share Your Answer
        </button>
    </div>
</section>

<!-- ===== TESTIMONIALS ===== -->
<section class="container py-4">
    <h4 class="mb-3">💜 Success Stories</h4>
    <div class="swiper testimonialSwiper">
        <div class="swiper-wrapper">
            <?php foreach ($testimonials as $t): ?>
                <div class="swiper-slide">
                    <div class="glass-card testimonial-card">
                        <div class="quote-icon">💜</div>
                        <p class="content">"<?= escape($t['content']) ?>"</p>
                        <p class="author">— <?= escape($t['author']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
    </div>
</section>

<!-- ===== FAQ ===== -->
<section class="container py-4" id="faq">
    <h4 class="mb-3">❓ Frequently Asked Questions</h4>
    <div class="glass-card p-4">
        <div class="faq-item">
            <div class="faq-question" onclick="toggleFAQ(this)">Is my identity hidden? <i class="bi bi-chevron-down"></i></div>
            <div class="faq-answer">Yes! All posts and comments are completely anonymous. Your real name and email are never shown to other users. Only you and the administrators can see your account details.</div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleFAQ(this)">Is AI replacing volunteers? <i class="bi bi-chevron-down"></i></div>
            <div class="faq-answer">No. AI (MindGuide) provides initial support, but trained volunteers and moderators are always available for personal conversations and crisis support. AI assists, not replaces, human care.</div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleFAQ(this)">Is the platform free? <i class="bi bi-chevron-down"></i></div>
            <div class="faq-answer">Yes! Haven is completely free for all users. Our mission is to provide accessible mental wellness support to everyone, regardless of financial situation.</div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleFAQ(this)">How is my data protected? <i class="bi bi-chevron-down"></i></div>
            <div class="faq-answer">We use encryption, secure databases, and strict privacy policies. Your personal information is never shared with third parties. You can delete your account at any time.</div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleFAQ(this)">Can I delete my account? <i class="bi bi-chevron-down"></i></div>
            <div class="faq-answer">Absolutely. You can request account deletion from your settings page. All your data will be permanently removed within 48 hours.</div>
        </div>
    </div>
</section>

<!-- ===== CTA ===== -->
<section class="container py-4">
    <div class="cta-section">
        <h2>Ready to Join?</h2>
        <p>Create your anonymous account and become part of a supportive community.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap mt-3">
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php" class="btn btn-cta">Go to Dashboard</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-cta">Create Account</a>
                <a href="login.php" class="btn btn-cta-outline">Sign In</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="footer glass-footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <h5><i class="bi bi-heart-fill text-primary"></i> Haven</h5>
                <p class="text-muted small">AI-assisted anonymous community for mental wellness. Share. Heal. Support.</p>
            </div>
            <div class="col-md-2">
                <h6>Community</h6>
                <a href="community.php" class="footer-link">Feed</a>
                <a href="articles.php" class="footer-link">Resources</a>
                <a href="#" class="footer-link">Guidelines</a>
            </div>
            <div class="col-md-2">
                <h6>Support</h6>
                <a href="#" class="footer-link">Help Center</a>
                <a href="#" class="footer-link">Privacy</a>
                <a href="#" class="footer-link">Terms</a>
            </div>
            <div class="col-md-2">
                <h6>Connect</h6>
                <a href="#" class="footer-link"><i class="bi bi-twitter"></i> Twitter</a>
                <a href="#" class="footer-link"><i class="bi bi-instagram"></i> Instagram</a>
                <a href="#" class="footer-link"><i class="bi bi-youtube"></i> YouTube</a>
            </div>
            <div class="col-md-2">
                <h6>Account</h6>
                <?php if (isLoggedIn()): ?>
                    <a href="dashboard.php" class="footer-link">Dashboard</a>
                    <a href="logout.php" class="footer-link">Logout</a>
                <?php else: ?>
                    <a href="register.php" class="footer-link">Register</a>
                    <a href="login.php" class="footer-link">Login</a>
                <?php endif; ?>
            </div>
        </div>
        <hr>
        <p class="text-muted text-center small mb-0">© <?= date('Y') ?> Haven. All rights reserved.</p>
    </div>
</footer>

<!-- ===== FLOATING ===== -->
<button class="floating-chat" id="floatingChat" title="Chat with MindGuide">
    <i class="bi bi-robot"></i>
    <span class="tooltip">Need help? Chat with MindGuide</span>
</button>
<button class="scroll-top" id="scrollTop"><i class="bi bi-chevron-up"></i></button>

<!-- ===== LOGIN MODAL ===== -->
<div class="modal fade" id="loginPromptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card p-4">
            <div class="modal-header border-0">
                <h5 class="modal-title">💜 Join the Community</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p>To support, comment, or share your thoughts, please login or create an account.</p>
                <div class="d-flex gap-3 justify-content-center mt-3">
                    <a href="register.php" class="btn btn-primary">Create Account</a>
                    <a href="login.php" class="btn btn-outline-primary">Login</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== SCRIPTS ===== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script>
    // Lenis
    const lenis = new Lenis({ duration: 1.2 });
    function raf(time) { lenis.raf(time); requestAnimationFrame(raf); }
    requestAnimationFrame(raf);

    // GSAP
    gsap.registerPlugin(ScrollTrigger, TextPlugin);
    const tl = gsap.timeline();
    tl.from("#heroTitle", { y: 60, opacity: 0, duration: 1, ease: "power3.out" })
      .from("#heroSubtitle", { y: 30, opacity: 0, duration: 0.8, ease: "power3.out" }, "-=0.4")
      .from(".hero-cta .btn", { scale: 0.8, opacity: 0, duration: 0.6, stagger: 0.15, ease: "back.out(1.7)" }, "-=0.3")
      .from("#heroMoods span", { scale: 0, opacity: 0, duration: 0.5, stagger: 0.08, ease: "back.out(1.7)" }, "-=0.2");

    gsap.utils.toArray(".glass-card:not(.post-card)").forEach((card, i) => {
        gsap.from(card, {
            scrollTrigger: { trigger: card, toggleActions: "play none none none", start: "top 90%" },
            y: 30,
            opacity: 0,
            duration: 0.6,
            delay: i * 0.03,
            ease: "power2.out"
        });
    });
    gsap.utils.toArray(".post-card").forEach((card, i) => {
        gsap.from(card, {
            scrollTrigger: { trigger: card, toggleActions: "play none none none", start: "top 92%" },
            y: 25,
            opacity: 0,
            duration: 0.5,
            delay: i * 0.04,
            ease: "power2.out"
        });
    });

    // Navbar scroll
    const navbar = document.getElementById('mainNav');
    window.addEventListener('scroll', function() {
        if (window.scrollY > 80) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');
    });

    // Dark mode
    document.getElementById('darkToggle').addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
    });
    if (localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark-mode');

    // Counters
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

    // Mood Pulse Chart
    <?php
    $chart_labels = [];
    $chart_values = [];
    foreach ($mood_percentages as $m) {
        $chart_labels[] = $m['emoji'];
        $chart_values[] = $m['percent'];
    }
    if (empty($chart_labels)) { $chart_labels = ['😊', '🙂', '😔']; $chart_values = [42, 28, 18]; }
    ?>
    (function() {
        if (typeof ApexCharts !== 'undefined') {
            var options = {
                series: [{ data: <?= json_encode($chart_values) ?> }],
                chart: { type: 'bar', height: 100, toolbar: { show: false }, sparkline: { enabled: true } },
                colors: ['#5B8DEF', '#4ADE80', '#FBBF24', '#FB7185', '#8B5CF6'],
                plotOptions: { bar: { columnWidth: '60%', borderRadius: 6 } },
                xaxis: { categories: <?= json_encode($chart_labels) ?>, labels: { style: { fontSize: '14px' } } },
                yaxis: { show: false },
                grid: { show: false }
            };
            new ApexCharts(document.getElementById('moodPulseChart'), options).render();
        }
    })();

    // Swipers
    new Swiper('.trendingSwiper', {
        slidesPerView: 1, spaceBetween: 20,
        pagination: { el: '.swiper-pagination', clickable: true },
        breakpoints: { 576: { slidesPerView: 2 }, 992: { slidesPerView: 3 } }
    });
    new Swiper('.videoSwiper', {
        slidesPerView: 1, spaceBetween: 20,
        pagination: { el: '.swiper-pagination', clickable: true },
        breakpoints: { 576: { slidesPerView: 2 }, 992: { slidesPerView: 3 } }
    });
    new Swiper('.testimonialSwiper', {
        slidesPerView: 1, spaceBetween: 20, loop: true, autoplay: { delay: 5000 },
        pagination: { el: '.swiper-pagination', clickable: true },
        breakpoints: { 576: { slidesPerView: 2 }, 992: { slidesPerView: 3 } }
    });

    // FAQ Toggle
    function toggleFAQ(el) {
        const answer = el.nextElementSibling;
        const icon = el.querySelector('i');
        if (answer.classList.contains('open')) {
            answer.classList.remove('open');
            icon.className = 'bi bi-chevron-down';
        } else {
            answer.classList.add('open');
            icon.className = 'bi bi-chevron-up';
        }
    }

    // Load More
    document.getElementById('loadMoreFeed')?.addEventListener('click', function() {
        const page = this.dataset.page;
        const url = new URL(window.location.href);
        url.searchParams.set('page', page);
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.text())
            .then(html => {
                if (html.trim() === '') { this.style.display = 'none'; return; }
                document.getElementById('publicFeed').insertAdjacentHTML('beforeend', html);
                this.dataset.page = parseInt(page) + 1;
                gsap.utils.toArray("#publicFeed .post-card:not(.animated)").forEach(c => {
                    gsap.from(c, { y: 20, opacity: 0, duration: 0.5, ease: "power2.out" });
                    c.classList.add('animated');
                });
            });
    });

    // Search
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        const q = this.value.trim();
        clearTimeout(searchTimeout);
        if (q.length < 2) { searchResults.style.display = 'none'; return; }
        searchTimeout = setTimeout(() => {
            fetch('index.php?search=' + encodeURIComponent(q))
                .then(res => res.json())
                .then(data => {
                    if (data.length === 0) {
                        searchResults.innerHTML = '<div class="result-item text-muted">No results found.</div>';
                    } else {
                        searchResults.innerHTML = data.map(item => {
                            let icon = '📄', badge = 'Post';
                            if (item.type === 'article') { icon = '📰'; badge = 'Article'; }
                            else if (item.type === 'category') { icon = '📂'; badge = 'Category'; }
                            else if (item.type === 'user') { icon = '👤'; badge = 'User'; }
                            let link = item.type === 'post' ? `post.php?id=${item.id}` :
                                       item.type === 'article' ? `article.php?id=${item.id}` :
                                       item.type === 'category' ? `community.php?category=${encodeURIComponent(item.title)}` :
                                       '#';
                            return `<a href="${link}" class="result-item text-decoration-none text-dark d-block">
                                        <span class="type-icon">${icon}</span>
                                        ${item.title}
                                        <span class="badge bg-secondary float-end">${badge}</span>
                                    </a>`;
                        }).join('');
                    }
                    searchResults.style.display = 'block';
                });
        }, 300);
    });
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // Floating Chat
    document.getElementById('floatingChat').addEventListener('click', function() {
        <?php if (isLoggedIn()): ?>
            window.location.href = 'chatbot.php';
        <?php else: ?>
            var modal = new bootstrap.Modal(document.getElementById('loginPromptModal'));
            modal.show();
        <?php endif; ?>
    });

    // Scroll to top
    const scrollTopBtn = document.getElementById('scrollTop');
    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) scrollTopBtn.classList.add('visible');
        else scrollTopBtn.classList.remove('visible');
    });
    scrollTopBtn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Hero mood click
    document.querySelectorAll('#heroMoods span').forEach(el => {
        el.addEventListener('click', function() {
            const mood = this.dataset.mood;
            const message = {
                happy: "😊 Haven supports you with uplifting content and community encouragement.",
                calm: "😌 We offer mindfulness exercises and calming meditations.",
                okay: "🙂 It's okay to feel okay. We're here to help you maintain balance.",
                sad: "😔 We understand. Our volunteers and AI are here to listen.",
                stressed: "😰 MindGuide AI provides stress relief techniques and resources.",
                angry: "😡 We offer a safe space to vent and find support."
            };
            Toastify({
                text: message[mood] || "Haven is here for you.",
                duration: 4000,
                gravity: "bottom",
                position: "center",
                style: { background: "linear-gradient(135deg, #5B8DEF, #8B5CF6)", borderRadius: "15px" }
            }).showToast();
        });
    });

    // Guest action prompts
    <?php if (!isLoggedIn()): ?>
    document.querySelectorAll('.post-card .post-actions .btn-outline-secondary').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var modal = new bootstrap.Modal(document.getElementById('loginPromptModal'));
            modal.show();
        });
    });
    <?php endif; ?>

    console.log('🌿 Haven – Share. Heal. Support.');
</script>
</body>
</html>
