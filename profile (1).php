<?php
// ============================================================
// profile.php – Haven Social Profile (Fully Functional)
// ============================================================
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
include 'includes/functions.php';
date_default_timezone_set('Asia/Dhaka');
$user_id = isLoggedIn() ? $_SESSION['user_id'] : 0;
$anon_name = $user_id ? getAnonymousName($user_id, $pdo) : 'Guest';
$unread_notifs = $user_id ? getUnreadNotifications($user_id, $pdo) : 0;

// ============================================================
// Get profile user – support both 'id' and 'user' parameter
// ============================================================
$profile_id = 0;
if (isset($_GET['id'])) {
    $profile_id = intval($_GET['id']);
} elseif (isset($_GET['user'])) {
    $profile_id = intval($_GET['user']);
} elseif (isset($_GET['slug'])) {
    $slug = $_GET['slug'];
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR anonymous_name = ?");
    $stmt->execute([$slug, $slug]);
    $row = $stmt->fetch();
    $profile_id = $row ? $row['id'] : 0;
}

// If no profile specified, default to current user (if logged in)
if (!$profile_id && $user_id) {
    $profile_id = $user_id;
}

// If still no profile, redirect to login or home
if (!$profile_id) {
    header('Location: login.php');
    exit;
}

// ============================================================
// Fetch profile user data (CORRECTED QUERY)
// ============================================================
$stmt = $pdo->prepare("
    SELECT u.*,
           p.cover_url,
           p.privacy,
           (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND status = 'published') as post_count,
           (SELECT COUNT(*) FROM reactions WHERE post_id IN (SELECT id FROM posts WHERE user_id = u.id)) as support_received,
           (SELECT COUNT(*) FROM reactions WHERE user_id = u.id) as support_given,
           (SELECT COUNT(*) FROM friends WHERE (user_id = u.id OR friend_id = u.id) AND status = 'accepted') as friend_count,
           (SELECT COUNT(*) FROM followers WHERE user_id = u.id) as follower_count,
           (SELECT COUNT(*) FROM followers WHERE follower_id = u.id) as following_count,
           (SELECT COUNT(*) FROM mood_entries WHERE user_id = u.id) as mood_count,
           (SELECT COUNT(*) FROM comments WHERE user_id = u.id) as comment_count
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

$is_owner = ($user_id && $user_id == $profile_id);

// ============================================================
// Friend/follow status
// ============================================================
$is_following = false;
$is_friend = false;
$friend_request_sent = false;

if ($user_id && !$is_owner) {
    $stmt = $pdo->prepare("SELECT id FROM followers WHERE user_id = ? AND follower_id = ?");
    $stmt->execute([$profile_id, $user_id]);
    $is_following = (bool)$stmt->fetch();

    $stmt = $pdo->prepare("SELECT status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
    $stmt->execute([$user_id, $profile_id, $profile_id, $user_id]);
    $friend = $stmt->fetch();
    if ($friend) {
        if ($friend['status'] == 'accepted') $is_friend = true;
        elseif ($friend['status'] == 'pending' && $friend['user_id'] == $user_id) $friend_request_sent = true;
    }
}

// ============================================================
// Get user's current mood (from last mood entry)
// ============================================================
$last_mood = '😊';
$last_mood_label = 'Good';
if ($profile_id) {
    $stmt = $pdo->prepare("SELECT emotion FROM mood_entries WHERE user_id = ? ORDER BY entry_date DESC LIMIT 1");
    $stmt->execute([$profile_id]);
    $mood_row = $stmt->fetch();
    if ($mood_row) {
        $mood_map = [
            'happy' => ['😊', 'Good'],
            'calm' => ['😌', 'Calm'],
            'hopeful' => ['🌱', 'Hopeful'],
            'sad' => ['😔', 'Low'],
            'stressed' => ['😰', 'Anxious'],
            'angry' => ['😡', 'Stressed'],
            'tired' => ['😴', 'Tired'],
            'grateful' => ['❤️', 'Grateful']
        ];
        $mood_data = $mood_map[$mood_row['emotion']] ?? ['😊', 'Good'];
        $last_mood = $mood_data[0];
        $last_mood_label = $mood_data[1];
    }
}

$current_chapter = 'Taking things one day at a time.';
$current_mood = $last_mood_label;
$current_mood_emoji = $last_mood;

// ============================================================
// Get posts
// ============================================================
$stmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
           (SELECT COUNT(*) FROM reactions WHERE post_id = p.id) as reaction_count
    FROM posts p
    WHERE p.user_id = ? AND p.status = 'published'
    ORDER BY p.created_at DESC LIMIT 10
");
$stmt->execute([$profile_id]);
$posts = $stmt->fetchAll();

// ============================================================
// Get photos (posts with media_url)
// ============================================================
$stmt = $pdo->prepare("
    SELECT id, title, content, created_at, media_url, media_type
    FROM posts
    WHERE user_id = ? AND status = 'published' AND media_url IS NOT NULL AND media_url != ''
    ORDER BY created_at DESC LIMIT 9
");
$stmt->execute([$profile_id]);
$photos = $stmt->fetchAll();

if (empty($photos)) {
    $stmt = $pdo->prepare("
        SELECT id, title, content, created_at, NULL as media_url, NULL as media_type
        FROM posts
        WHERE user_id = ? AND status = 'published'
        ORDER BY created_at DESC LIMIT 6
    ");
    $stmt->execute([$profile_id]);
    $photos = $stmt->fetchAll();
}

// ============================================================
// Stats
// ============================================================
$follower_count = $profile['follower_count'] ?? 0;
$following_count = $profile['following_count'] ?? 0;
$friend_count = $profile['friend_count'] ?? 0;
$checkins_count = $profile['mood_count'] ?? 0;

// Tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'posts';

// Random quote
$quotes = [
    "You are enough, just as you are.",
    "Every step forward is progress.",
    "Your journey is uniquely yours.",
    "Kindness starts with yourself.",
    "Healing is not linear, and that's okay.",
    "Small steps lead to big changes.",
    "You are stronger than you think.",
];
$quote = $quotes[array_rand($quotes)];

// Wellbeing snapshot
$wellbeing_data = ['mood' => 75, 'stress' => 45, 'sleep' => 65, 'energy' => 70];
if ($profile_id) {
    $stmt = $pdo->prepare("
        SELECT AVG(CASE emotion 
            WHEN 'happy' THEN 80 WHEN 'calm' THEN 70 WHEN 'hopeful' THEN 75 
            WHEN 'sad' THEN 40 WHEN 'stressed' THEN 35 WHEN 'angry' THEN 30 
            WHEN 'tired' THEN 50 WHEN 'grateful' THEN 85 ELSE 60 END) as mood,
            AVG(stress) as stress,
            AVG(sleep) as sleep,
            AVG(energy) as energy
        FROM mood_entries 
        WHERE user_id = ? 
        ORDER BY entry_date DESC LIMIT 7
    ");
    $stmt->execute([$profile_id]);
    $snapshot = $stmt->fetch();
    if ($snapshot) {
        $wellbeing_data['mood'] = round($snapshot['mood'] ?? 75);
        $wellbeing_data['stress'] = round($snapshot['stress'] ?? 45);
        $wellbeing_data['sleep'] = round($snapshot['sleep'] ?? 65);
        $wellbeing_data['energy'] = round($snapshot['energy'] ?? 70);
    }
}

$last_checkin = 'Never';
if ($profile_id) {
    $stmt = $pdo->prepare("SELECT entry_date FROM mood_entries WHERE user_id = ? ORDER BY entry_date DESC LIMIT 1");
    $stmt->execute([$profile_id]);
    $checkin = $stmt->fetch();
    if ($checkin) {
        $last_checkin = timeAgo($checkin['entry_date'] . ' 00:00:00');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($profile['anonymous_name'] ?? $profile['username']) ?> – Haven Profile</title>
<link rel="icon" href="logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <style>
        /* ===== CSS VARIABLES (same as before – keep) ===== */
        :root {
            --peach-1: #FDE8E0; --peach-2: #FCD5C7; --peach-3: #FBC4B0;
            --coral: #E8967A; --lavender: #D4C5E0; --mint: #B8D9C8;
            --cream: #FFF8F3;
            --glass-bg: rgba(255,255,255,0.45); --glass-border: rgba(255,255,255,0.55);
            --glass-shadow: 0 8px 32px rgba(150,110,90,0.08);
            --neumo-shadow: 8px 8px 18px rgba(150,110,90,0.12), -6px -6px 15px rgba(255,255,255,0.9);
            --neumo-shadow-sm: 4px 4px 10px rgba(150,110,90,0.08), -3px -3px 8px rgba(255,255,255,0.8);
            --text-primary: #3D2C2A; --text-secondary: #7A6258; --text-muted: #B8A89E;
            --font-serif: 'Playfair Display', serif; --font-sans: 'Inter', sans-serif;
            --radius: 20px; --radius-sm: 12px;
            --transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body.dark {
            --glass-bg: rgba(30,30,40,0.55);
            --glass-border: rgba(255,255,255,0.08);
            --glass-shadow: 0 8px 32px rgba(0,0,0,0.3);
            --neumo-shadow: 8px 8px 18px rgba(0,0,0,0.3), -6px -6px 15px rgba(30,30,40,0.9);
            --neumo-shadow-sm: 4px 4px 10px rgba(0,0,0,0.2), -3px -3px 8px rgba(30,30,40,0.8);
            --text-primary: #E8E0D8;
            --text-secondary: #B8A89E;
            --text-muted: #8A7A70;
            --cream: #1A1A20;
            --peach-1: #2A2220;
            --peach-2: #3D2C2A;
            --peach-3: #5A3E3A;
            --gradient-start: #2A2220;
            --gradient-end: #2A1A30;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: var(--font-sans);
            background: var(--cream);
            color: var(--text-primary);
            transition: background 0.6s, color 0.6s;
            overflow-x: hidden;
            min-height: 100vh;
        }
        h1,h2,h3,h4,h5,h6 { font-family: var(--font-serif); font-weight: 500; }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; }
        img { max-width: 100%; display: block; }

        /* ============================================================
           AMBIENT GRADIENT MESH BACKGROUND
           ============================================================ */
        #ambient-mesh {
            position: fixed;
            top: 0; left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }
        #ambient-mesh .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.3;
            will-change: transform;
            animation: floatBlob 25s ease-in-out infinite alternate;
        }
        .blob-1 { width: 600px; height: 600px; background: var(--peach-2); top: -10%; left: -15%; animation-delay: 0s; }
        .blob-2 { width: 500px; height: 500px; background: var(--lavender); bottom: -10%; right: -15%; animation-delay: 8s; }
        .blob-3 { width: 400px; height: 400px; background: var(--mint); top: 40%; right: -10%; animation-delay: 16s; opacity: 0.15; }
        .blob-4 { width: 350px; height: 350px; background: var(--coral); bottom: 30%; left: -10%; animation-delay: 4s; opacity: 0.15; }
        body.dark .blob-1 { background: #4A3A38; opacity: 0.15; }
        body.dark .blob-2 { background: #3A2A40; opacity: 0.15; }
        body.dark .blob-3 { background: #2A3A30; opacity: 0.10; }
        body.dark .blob-4 { background: #4A2A28; opacity: 0.10; }

        @keyframes floatBlob {
            0% { transform: translate(0, 0) scale(1) rotate(0deg); }
            33% { transform: translate(40px, -30px) scale(1.05) rotate(5deg); }
            66% { transform: translate(-30px, 20px) scale(0.95) rotate(-5deg); }
            100% { transform: translate(20px, -10px) scale(1.08) rotate(3deg); }
        }

        /* ============================================================
           NAVBAR
           ============================================================ */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1050;
            padding: 0.6rem 2rem;
            background: var(--glass-bg);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.3s;
        }
        .navbar .brand {
            font-family: var(--font-serif);
            font-weight: 600;
            font-size: 1.2rem;
            color: var(--text-primary);
        }
        .navbar .brand i { color: var(--coral); margin-right: 6px; }
        .navbar .nav-links { display: flex; align-items: center; gap: 1.2rem; }
        .navbar .nav-links a { color: var(--text-secondary); transition: 0.2s; font-size: 0.9rem; }
        .navbar .nav-links a:hover { color: var(--coral); }
        .navbar .nav-links .btn { padding: 0.3rem 1rem; border-radius: 30px; font-size: 0.85rem; }
        .navbar .nav-toggle { display: none; font-size: 1.4rem; color: var(--text-primary); }
        @media (max-width: 768px) {
            .navbar { padding: 0.6rem 1rem; }
            .navbar .nav-links { display: none; }
            .navbar .nav-links.open { display: flex; flex-direction: column; position: absolute; top: 100%; left: 0; right: 0; background: var(--glass-bg); backdrop-filter: blur(18px); padding: 1rem; border-bottom: 1px solid var(--glass-border); }
            .navbar .nav-toggle { display: block; }
        }

        /* ============================================================
           PROFILE WRAPPER
           ============================================================ */
        .profile-wrapper {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            padding: 75px 20px 20px;
        }

        /* ============================================================
           COVER
           ============================================================ */
        .cover-container {
            position: relative;
            border-radius: var(--radius);
            overflow: hidden;
            height: 300px;
            background: linear-gradient(135deg, var(--peach-2), var(--lavender));
            margin-bottom: 0;
            box-shadow: var(--glass-shadow);
        }
        body.dark .cover-container {
            background: linear-gradient(135deg, #3D2C2A, #2A1A30);
        }
        .cover-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .cover-container .cover-gradient {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60%;
            background: linear-gradient(transparent, rgba(0,0,0,0.2));
        }
        @media (max-width: 600px) {
            .cover-container { height: 180px; border-radius: 16px; }
        }

        /* ============================================================
           PROFILE HEADER
           ============================================================ */
        .profile-header {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            padding: 0 1.5rem 1rem;
            margin-top: -50px;
            position: relative;
            z-index: 2;
            align-items: flex-end;
        }
        .profile-header .avatar-wrapper {
            position: relative;
            flex-shrink: 0;
        }
        .profile-header .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 3px solid var(--glass-border);
            box-shadow: var(--neumo-shadow);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 600;
            color: #fff;
            transition: var(--transition);
            overflow: hidden;
        }
        .profile-header .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-header .avatar:hover {
            transform: scale(1.03);
        }
        .profile-header .avatar .online-dot {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #4ADE80;
            border: 2px solid var(--cream);
        }
        .profile-header .info {
            flex: 1;
            min-width: 200px;
            padding-bottom: 0.5rem;
        }
        .profile-header .info .name {
            font-size: 2.2rem;
            font-weight: 600;
            font-family: var(--font-serif);
            background: linear-gradient(135deg, var(--peach-3), var(--coral), var(--lavender), var(--mint));
            background-size: 300% 300%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmerGradient 8s ease-in-out infinite;
        }
        @keyframes shimmerGradient {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        .profile-header .info .username {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        .profile-header .info .tagline {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-top: 0.2rem;
            position: relative;
            display: inline-block;
        }
        .profile-header .info .tagline .highlight {
            position: relative;
            display: inline-block;
        }
        .profile-header .info .tagline .highlight::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 40%;
            background: rgba(232, 150, 122, 0.25);
            border-radius: 4px;
            z-index: -1;
            transition: width 1.2s ease;
        }
        .profile-header .info .tagline .highlight.animated::after {
            width: 100%;
        }
        .profile-header .info .stats {
            display: flex;
            gap: 1.5rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }
        .profile-header .info .stats .stat {
            display: flex;
            flex-direction: column;
        }
        .profile-header .info .stats .stat .number {
            font-weight: 600;
            font-size: 1.1rem;
            font-family: var(--font-serif);
        }
        .profile-header .info .stats .stat .label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .profile-header .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            padding-bottom: 0.5rem;
        }
        .profile-header .actions .btn {
            padding: 0.4rem 1.2rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--neumo-shadow-sm);
            color: var(--text-primary);
        }
        .profile-header .actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--neumo-shadow);
        }
        .profile-header .actions .btn-primary {
            background: linear-gradient(135deg, var(--coral), var(--peach-3));
            color: #fff;
            border: none;
        }
        .profile-header .actions .btn-primary:hover {
            box-shadow: 0 8px 30px rgba(232, 150, 122, 0.3);
        }
        .profile-header .actions .btn-outline {
            background: transparent;
            border: 1.5px solid var(--text-secondary);
        }
        @media (max-width: 600px) {
            .profile-header { flex-direction: column; align-items: center; text-align: center; padding: 0 1rem; }
            .profile-header .avatar { width: 90px; height: 90px; font-size: 2.2rem; margin-top: -45px; }
            .profile-header .info .name { font-size: 1.6rem; }
            .profile-header .actions { justify-content: center; }
            .profile-header .info .stats { justify-content: center; }
        }

        /* ============================================================
           CURRENT CHAPTER
           ============================================================ */
        .current-chapter {
            background: var(--glass-bg);
            backdrop-filter: blur(18px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 1.2rem 1.5rem;
            margin: 0.5rem 1.5rem 1rem;
            box-shadow: var(--glass-shadow);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
        }
        .current-chapter .icon { font-size: 1.8rem; }
        .current-chapter .content { flex: 1; }
        .current-chapter .content .label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); }
        .current-chapter .content .chapter-text { font-family: var(--font-serif); font-size: 1.1rem; font-style: italic; }
        .current-chapter .content .mood-badge {
            display: inline-block;
            padding: 0.15rem 0.8rem;
            border-radius: 20px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            font-size: 0.8rem;
            margin-top: 0.2rem;
        }
        @media (max-width: 600px) {
            .current-chapter { margin: 0.5rem 1rem; flex-direction: column; text-align: center; }
        }

        /* ============================================================
           WELLBEING SNAPSHOT
           ============================================================ */
        .wellbeing-snapshot {
            background: var(--glass-bg);
            backdrop-filter: blur(18px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 1.2rem 1.5rem;
            margin: 0 1.5rem 1rem;
            box-shadow: var(--glass-shadow);
        }
        .wellbeing-snapshot .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.6rem;
        }
        .wellbeing-snapshot .header h6 { font-family: var(--font-sans); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
        .wellbeing-snapshot .header .last-checkin { font-size: 0.7rem; color: var(--text-muted); }
        .wellbeing-snapshot .metrics {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 0.5rem;
        }
        .wellbeing-snapshot .metrics .metric {
            background: var(--glass-bg);
            border-radius: var(--radius-sm);
            padding: 0.4rem 0.8rem;
            border: 1px solid var(--glass-border);
        }
        .wellbeing-snapshot .metrics .metric .name { font-size: 0.7rem; color: var(--text-muted); }
        .wellbeing-snapshot .metrics .metric .bar {
            height: 4px;
            background: var(--peach-1);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 0.2rem;
        }
        .wellbeing-snapshot .metrics .metric .bar .fill {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, var(--coral), var(--lavender));
            transition: width 1.2s ease;
        }
        @media (max-width: 600px) {
            .wellbeing-snapshot { margin: 0 1rem; }
            .wellbeing-snapshot .metrics { grid-template-columns: 1fr 1fr; }
        }

        /* ============================================================
           PROFILE NAVIGATION (Tabs)
           ============================================================ */
        .profile-nav {
            display: flex;
            gap: 0.3rem;
            padding: 0.5rem 1.5rem;
            overflow-x: auto;
            scrollbar-width: none;
            border-bottom: 1px solid var(--glass-border);
            margin: 0 1.5rem;
        }
        .profile-nav::-webkit-scrollbar { display: none; }
        .profile-nav .tab {
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            transition: var(--transition);
            white-space: nowrap;
            font-weight: 500;
            position: relative;
            background: transparent;
        }
        .profile-nav .tab:hover { color: var(--text-primary); background: var(--glass-bg); }
        .profile-nav .tab.active {
            color: var(--text-primary);
            background: var(--glass-bg);
        }
        .profile-nav .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 3px;
            border-radius: 10px;
            background: var(--coral);
        }
        @media (max-width: 600px) {
            .profile-nav { padding: 0.5rem 1rem; margin: 0 1rem; gap: 0.2rem; }
            .profile-nav .tab { font-size: 0.75rem; padding: 0.4rem 0.8rem; }
        }

        /* ============================================================
           TAB CONTENT
           ============================================================ */
        .tab-content {
            display: none;
            padding: 1.5rem;
            animation: fadeUp 0.5s ease;
        }
        .tab-content.active { display: block; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ============================================================
           TWO-COLUMN LAYOUT (Desktop)
           ============================================================ */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2.2fr;
            gap: 1.5rem;
        }
        @media (max-width: 992px) {
            .content-grid { grid-template-columns: 1fr; }
        }

        /* ============================================================
           GLASS CARDS
           ============================================================ */
        .glass {
            background: var(--glass-bg);
            backdrop-filter: blur(18px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            box-shadow: var(--glass-shadow);
            padding: 1.2rem;
            transition: var(--transition);
        }
        .glass:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(150,110,90,0.1);
        }
        body.dark .glass:hover {
            box-shadow: 0 12px 40px rgba(0,0,0,0.3);
        }
        .glass .card-title {
            font-family: var(--font-sans);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 0.6rem;
        }

        /* ============================================================
           ABOUT SECTION
           ============================================================ */
        .about-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.3rem 0;
            font-size: 0.9rem;
            border-bottom: 1px solid var(--glass-border);
        }
        .about-item:last-child { border-bottom: none; }
        .about-item .icon { font-size: 1.1rem; width: 24px; text-align: center; color: var(--coral); }

        /* ============================================================
           PHOTOS GRID
           ============================================================ */
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4px;
        }
        .photos-grid .photo {
            aspect-ratio: 1;
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: var(--peach-1);
            cursor: pointer;
            transition: var(--transition);
        }
        .photos-grid .photo:hover { transform: scale(1.02); }
        .photos-grid .photo img { width: 100%; height: 100%; object-fit: cover; }
        .photos-grid .photo .placeholder {
            width: 100%; height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--text-muted);
            background: var(--glass-bg);
        }

        /* ============================================================
           POST CARDS (Feed-style)
           ============================================================ */
        .post-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 1.2rem;
            margin-bottom: 1.2rem;
            transition: var(--transition);
        }
        .post-card:hover { transform: translateY(-2px); box-shadow: var(--glass-shadow); }
        .post-card .post-header { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.6rem; }
        .post-card .post-header .avatar-sm {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .post-card .post-header .name { font-weight: 500; font-size: 0.95rem; }
        .post-card .post-header .time { font-size: 0.7rem; color: var(--text-muted); margin-left: auto; }
        .post-card .post-content { font-size: 0.95rem; line-height: 1.7; color: var(--text-secondary); }
        .post-card .post-content .title { font-family: var(--font-serif); font-size: 1.1rem; margin-bottom: 0.2rem; color: var(--text-primary); }
        .post-card .post-actions {
            display: flex;
            gap: 1rem;
            margin-top: 0.8rem;
            padding-top: 0.8rem;
            border-top: 1px solid var(--glass-border);
        }
        .post-card .post-actions button {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            transition: var(--transition);
            background: transparent;
            padding: 0.2rem 0.6rem;
            border-radius: 30px;
        }
        .post-card .post-actions button:hover { color: var(--coral); background: var(--glass-bg); }
        .post-card .post-actions .like-btn.liked { color: var(--coral); }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 600px) {
            .profile-wrapper { padding: 65px 10px 10px; }
            .tab-content { padding: 1rem; }
            .content-grid { gap: 1rem; }
            .glass { padding: 0.8rem; }
            .photos-grid { gap: 3px; }
            .post-card { padding: 0.8rem; }
        }

        /* ============================================================
           BOTTOM NAV (Mobile)
           ============================================================ */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0; left: 0; right: 0;
            z-index: 1050;
            background: var(--glass-bg);
            backdrop-filter: blur(18px);
            border-top: 1px solid var(--glass-border);
            padding: 0.4rem 0;
            justify-content: space-around;
            align-items: center;
        }
        .bottom-nav a {
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 0.6rem;
            color: var(--text-muted);
            transition: var(--transition);
            padding: 0.2rem 0.5rem;
        }
        .bottom-nav a i { font-size: 1.4rem; }
        .bottom-nav a.active { color: var(--coral); }
        .bottom-nav a:hover { color: var(--text-primary); }
        @media (max-width: 768px) {
            .bottom-nav { display: flex; }
            .profile-wrapper { padding-bottom: 70px; }
        }

        /* ============================================================
           SCROLLBAR
           ============================================================ */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--coral); border-radius: 10px; }
    
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
    <!-- include full CSS from previous version -->
</head>
<body>

<!-- ============================================================
   AMBIENT GRADIENT MESH
   ============================================================ -->
<div id="ambient-mesh">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
    <div class="blob blob-4"></div>
</div>

<!-- ============================================================
   NAVBAR
   ============================================================ -->
<nav class="navbar" id="navbar">
    <div>
        <button class="nav-toggle" id="navToggle"><i class="bi bi-list"></i></button>
        <a href="index.php" class="brand"><img src="logo.png" alt="Haven" style="height:22px;width:22px;object-fit:cover;border-radius:6px;vertical-align:-5px;margin-right:4px;">Haven</a>
    </div>
    <div class="nav-links" id="navLinks">
        <a href="community.php">Community</a>
        <a href="feed.php">Feed</a>
        <a href="articles.php">Articles</a>
        <a href="chatbot.php">AI Chat</a>
        <?php if ($user_id): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php" class="btn btn-outline">Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-primary">Login</a>
        <?php endif; ?>
        <button onclick="toggleDark()" style="background:none;border:none;color:var(--text-primary);font-size:1.1rem;"><i class="bi bi-moon"></i></button>
    </div>
</nav>

<!-- ============================================================
   PROFILE WRAPPER
   ============================================================ -->
<div class="profile-wrapper">

    <!-- ============================================================
       COVER
       ============================================================ -->
    <div class="cover-container" id="coverContainer">
        <?php if ($profile['cover_url']): ?>
            <img src="<?= escape($profile['cover_url']) ?>" alt="Cover">
        <?php else: ?>
            <div style="width:100%;height:100%;background:linear-gradient(135deg, <?= $profile['avatar_color'] ?? '#FCD5C7' ?>, <?= $profile['avatar_color'] ?? '#D4C5E0' ?>);"></div>
        <?php endif; ?>
        <div class="cover-gradient"></div>
    </div>

    <!-- ============================================================
       PROFILE HEADER
       ============================================================ -->
    <div class="profile-header" id="profileHeader">
        <div class="avatar-wrapper">
            <div class="avatar" style="background:<?= escape($profile['avatar_color'] ?? '#5e7564') ?>;<?= ($profile['avatar_type'] === 'icon' && $profile['avatar_icon']) ? 'font-size:2.2rem;' : '' ?>">
                <?= ($profile['avatar_type'] === 'icon' && $profile['avatar_icon']) ? escape($profile['avatar_icon']) : substr($profile['anonymous_name'] ?? $profile['username'], 0, 1) ?>
                <?php if ($profile['is_active']): ?><span class="online-dot"></span><?php endif; ?>
            </div>
        </div>
        <div class="info">
            <div class="name" id="profileName"><?= escape($profile['anonymous_name'] ?? $profile['username']) ?></div>
            <div class="username">@<?= escape($profile['username'] ?? 'user') ?></div>
            <div class="tagline" id="tagline">
                <?= nl2br(escape($profile['bio'] ?? 'Learning, creating & becoming better 🌱')) ?>
                <span class="highlight" id="highlightWord">becoming better</span>
            </div>
            <div class="stats" id="statsContainer">
                <div class="stat"><span class="number" data-count="<?= $following_count ?>">0</span><span class="label">Following</span></div>
                <div class="stat"><span class="number" data-count="<?= $friend_count ?>">0</span><span class="label">Friends</span></div>
                <div class="stat"><span class="number" data-count="<?= $checkins_count ?>">0</span><span class="label">Check-ins</span></div>
                <div class="stat"><span class="number" data-count="<?= $profile['support_received'] ?? 0 ?>">0</span><span class="label">Support Received</span></div>
            </div>
        </div>
        <div class="actions" id="actionsContainer">
            <?php if ($is_owner): ?>
                <button class="btn btn-primary" onclick="window.location.href='profile-edit.php'"><i class="bi bi-pencil"></i> Edit Profile</button>
                <button class="btn btn-outline" onclick="shareProfile()"><i class="bi bi-share"></i> Share</button>
            <?php else: ?>
                <?php if ($is_friend): ?>
                    <button class="btn btn-primary"><i class="bi bi-check-circle"></i> Friends</button>
                <?php elseif ($friend_request_sent): ?>
                    <button class="btn btn-outline" disabled><i class="bi bi-clock"></i> Request Sent</button>
                <?php else: ?>
                    <button class="btn btn-primary" onclick="sendFriendRequest(<?= $profile_id ?>)"><i class="bi bi-person-plus"></i> Add Friend</button>
                <?php endif; ?>
                <button class="btn btn-outline" onclick="sendSupport(<?= $profile_id ?>)"><i class="bi bi-heart"></i> Support</button>
                <button class="btn btn-outline" onclick="window.location.href='messages.php?user=<?= $profile_id ?>'"><i class="bi bi-chat"></i> Message</button>
                <button class="btn btn-outline" onclick="shareProfile()"><i class="bi bi-share"></i></button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================
       CURRENT CHAPTER
       ============================================================ -->
    <div class="current-chapter" id="currentChapter">
        <div class="icon">🌿</div>
        <div class="content">
            <div class="label">My current chapter</div>
            <div class="chapter-text">"<?= escape($current_chapter) ?>"</div>
            <div class="mood-badge"><?= $current_mood_emoji ?> <?= escape($current_mood) ?></div>
        </div>
    </div>

    <!-- ============================================================
       WELLBEING SNAPSHOT
       ============================================================ -->
    <div class="wellbeing-snapshot" id="wellbeingSnapshot">
        <div class="header">
            <h6><i class="bi bi-heart-pulse"></i> Wellbeing Snapshot</h6>
            <span class="last-checkin">Last checked in · <?= $last_checkin ?></span>
        </div>
        <div class="metrics">
            <div class="metric"><div class="name">Mood</div><div class="bar"><div class="fill" data-width="<?= $wellbeing_data['mood'] ?>"></div></div><span style="font-size:0.7rem;color:var(--text-muted);"><?= $wellbeing_data['mood'] ?>%</span></div>
            <div class="metric"><div class="name">Stress</div><div class="bar"><div class="fill" data-width="<?= 100 - $wellbeing_data['stress'] ?>"></div></div><span style="font-size:0.7rem;color:var(--text-muted);"><?= $wellbeing_data['stress'] ?>%</span></div>
            <div class="metric"><div class="name">Sleep</div><div class="bar"><div class="fill" data-width="<?= $wellbeing_data['sleep'] ?>"></div></div><span style="font-size:0.7rem;color:var(--text-muted);"><?= $wellbeing_data['sleep'] ?>%</span></div>
            <div class="metric"><div class="name">Energy</div><div class="bar"><div class="fill" data-width="<?= $wellbeing_data['energy'] ?>"></div></div><span style="font-size:0.7rem;color:var(--text-muted);"><?= $wellbeing_data['energy'] ?>%</span></div>
        </div>
    </div>

    <!-- ============================================================
       PROFILE NAVIGATION (Tabs)
       ============================================================ -->
    <div class="profile-nav" id="profileNav">
        <button class="tab <?= $tab == 'posts' ? 'active' : '' ?>" data-tab="posts"><i class="bi bi-grid"></i> Posts</button>
        <button class="tab <?= $tab == 'about' ? 'active' : '' ?>" data-tab="about"><i class="bi bi-person"></i> About</button>
        <button class="tab <?= $tab == 'photos' ? 'active' : '' ?>" data-tab="photos"><i class="bi bi-images"></i> Photos</button>
        <button class="tab <?= $tab == 'reactions' ? 'active' : '' ?>" data-tab="reactions"><i class="bi bi-heart"></i> Reactions</button>
        <button class="tab <?= $tab == 'saved' ? 'active' : '' ?>" data-tab="saved"><i class="bi bi-bookmark"></i> Saved</button>
    </div>

    <!-- ============================================================
       TAB CONTENTS (All Functional)
       ============================================================ -->

    <!-- POSTS TAB -->
    <div class="tab-content <?= $tab == 'posts' ? 'active' : '' ?>" id="tab-posts">
        <div class="content-grid">
            <div class="left-col">
                <!-- About mini -->
                <div class="glass">
                    <div class="card-title">About <?= escape($profile['anonymous_name'] ?? $profile['username']) ?></div>
                    <div class="about-item"><span class="icon">🌱</span> <?= escape($profile['bio'] ?? 'Learning & growing') ?></div>
                    <div class="about-item"><span class="icon">📍</span> <?= escape($profile['country'] ?? 'Not specified') ?></div>
                    <div class="about-item"><span class="icon">📅</span> Joined Haven · <?= date('M Y', strtotime($profile['created_at'])) ?></div>
                    <div class="about-item"><span class="icon">💼</span> <?= escape($profile['occupation'] ?? 'Not specified') ?></div>
                    <div class="about-item"><span class="icon">📚</span> <?= escape($profile['education'] ?? 'Not specified') ?></div>
                    <?php if ($profile['language']): ?>
                        <div class="about-item"><span class="icon">🌐</span> <?= escape($profile['language']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Photos mini -->
                <div class="glass" style="margin-top:1rem;">
                    <div class="card-title">Photos</div>
                    <div class="photos-grid">
                        <?php if (empty($photos)): ?>
                            <div style="grid-column:1/4;padding:1rem;text-align:center;color:var(--text-muted);font-size:0.85rem;">No photos yet</div>
                        <?php else: ?>
                            <?php foreach (array_slice($photos, 0, 6) as $photo): ?>
                                <div class="photo" onclick="window.location.href='post.php?id=<?= $photo['id'] ?>'">
                                    <?php if ($photo['media_url']): ?>
                                        <img src="<?= escape($photo['media_url']) ?>" alt="Photo">
                                    <?php else: ?>
                                        <div class="placeholder"><i class="bi bi-image"></i></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Wellbeing mini -->
                <div class="glass" style="margin-top:1rem;">
                    <div class="card-title">🌿 Wellbeing</div>
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                        <span style="background:var(--glass-bg);padding:0.2rem 0.8rem;border-radius:20px;font-size:0.8rem;"><?= $wellbeing_data['mood'] ?>% Mood</span>
                        <span style="background:var(--glass-bg);padding:0.2rem 0.8rem;border-radius:20px;font-size:0.8rem;"><?= $wellbeing_data['stress'] ?>% Stress</span>
                        <span style="background:var(--glass-bg);padding:0.2rem 0.8rem;border-radius:20px;font-size:0.8rem;"><?= $wellbeing_data['sleep'] ?>% Sleep</span>
                        <span style="background:var(--glass-bg);padding:0.2rem 0.8rem;border-radius:20px;font-size:0.8rem;"><?= $wellbeing_data['energy'] ?>% Energy</span>
                    </div>
                </div>
            </div>

            <div class="right-col">
                <?php if (empty($posts)): ?>
                    <div class="glass" style="text-align:center;padding:2rem;">
                        <p style="color:var(--text-muted);"><?= $is_owner ? 'You haven\'t posted yet.' : 'No posts yet.' ?></p>
                        <?php if ($is_owner): ?>
                            <a href="create-post.php" class="btn btn-primary" style="display:inline-block;margin-top:0.5rem;">Create Post</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="post-card">
                            <div class="post-header">
                                <div class="avatar-sm" style="background:<?= escape($profile['avatar_color'] ?? '#5e7564') ?>;<?= ($profile['avatar_type'] === 'icon' && $profile['avatar_icon']) ? 'font-size:1.1rem;' : '' ?>"><?= ($profile['avatar_type'] === 'icon' && $profile['avatar_icon']) ? escape($profile['avatar_icon']) : substr($profile['anonymous_name'] ?? $profile['username'], 0, 1) ?></div>
                                <span class="name"><?= escape($profile['anonymous_name'] ?? $profile['username']) ?></span>
                                <span class="time"><?= timeAgo($post['created_at']) ?></span>
                            </div>
                            <div class="post-content">
                                <?php if ($post['title']): ?><div class="title"><?= escape($post['title']) ?></div><?php endif; ?>
                                <p><?= nl2br(escape($post['content'])) ?></p>
                            </div>
                            <div class="post-actions">
                                <button class="like-btn" data-post-id="<?= $post['id'] ?>"><i class="bi bi-heart"></i> <span class="like-count"><?= $post['reaction_count'] ?? 0 ?></span></button>
                                <button onclick="window.location.href='post.php?id=<?= $post['id'] ?>'"><i class="bi bi-chat"></i> <?= $post['comment_count'] ?? 0 ?></button>
                                <button onclick="sharePost(<?= $post['id'] ?>)"><i class="bi bi-share"></i> Share</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ABOUT TAB -->
    <div class="tab-content <?= $tab == 'about' ? 'active' : '' ?>" id="tab-about">
        <div class="glass" style="max-width:700px;margin:0 auto;">
            <h4 style="font-family:var(--font-serif);margin-bottom:1rem;">About <?= escape($profile['anonymous_name'] ?? $profile['username']) ?></h4>
            <div class="about-item"><span class="icon">🌱</span> <strong>Bio</strong> — <?= nl2br(escape($profile['bio'] ?? 'Not specified')) ?></div>
            <div class="about-item"><span class="icon">📍</span> <strong>Location</strong> — <?= escape($profile['country'] ?? 'Not specified') ?><?= $profile['city'] ? ', ' . escape($profile['city']) : '' ?></div>
            <div class="about-item"><span class="icon">📅</span> <strong>Joined</strong> — <?= date('F Y', strtotime($profile['created_at'])) ?></div>
            <div class="about-item"><span class="icon">💼</span> <strong>Occupation</strong> — <?= escape($profile['occupation'] ?? 'Not specified') ?></div>
            <div class="about-item"><span class="icon">📚</span> <strong>Education</strong> — <?= escape($profile['education'] ?? 'Not specified') ?></div>
            <?php if ($profile['language']): ?>
                <div class="about-item"><span class="icon">🌐</span> <strong>Language</strong> — <?= escape($profile['language']) ?></div>
            <?php endif; ?>
            <?php if ($profile['birth_date']): ?>
                <div class="about-item"><span class="icon">🎂</span> <strong>Birth Date</strong> — <?= date('M d, Y', strtotime($profile['birth_date'])) ?></div>
            <?php endif; ?>
            <?php if ($profile['gender']): ?>
                <div class="about-item"><span class="icon">🧑</span> <strong>Gender</strong> — <?= ucfirst(escape($profile['gender'])) ?></div>
            <?php endif; ?>
            <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--glass-border);">
                <strong>🌿 Current Chapter</strong><br>
                <span style="font-family:var(--font-serif);font-style:italic;font-size:1.1rem;">"<?= escape($current_chapter) ?>"</span>
            </div>
        </div>
    </div>

    <!-- PHOTOS TAB -->
    <div class="tab-content <?= $tab == 'photos' ? 'active' : '' ?>" id="tab-photos">
        <h4 style="font-family:var(--font-serif);margin-bottom:1rem;">Photos</h4>
        <?php if (empty($photos)): ?>
            <div class="glass" style="text-align:center;padding:2rem;">
                <p style="color:var(--text-muted);">No photos yet.</p>
            </div>
        <?php else: ?>
            <div class="photos-grid" style="grid-template-columns:repeat(4,1fr);gap:6px;">
                <?php foreach ($photos as $photo): ?>
                    <div class="photo" onclick="window.location.href='post.php?id=<?= $photo['id'] ?>'">
                        <?php if ($photo['media_url']): ?>
                            <img src="<?= escape($photo['media_url']) ?>" alt="Photo">
                        <?php else: ?>
                            <div class="placeholder"><i class="bi bi-image"></i></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- REACTIONS TAB -->
    <div class="tab-content <?= $tab == 'reactions' ? 'active' : '' ?>" id="tab-reactions">
        <div class="glass" style="max-width:700px;margin:0 auto;">
            <h4 style="font-family:var(--font-serif);margin-bottom:1rem;">❤️ Reactions</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
                <div class="glass" style="padding:1rem;text-align:center;">
                    <div style="font-size:2rem;">❤️</div>
                    <div style="font-size:1.5rem;font-weight:600;"><?= number_format($profile['support_received'] ?? 0) ?></div>
                    <div style="font-size:0.8rem;color:var(--text-muted);">Support Received</div>
                </div>
                <div class="glass" style="padding:1rem;text-align:center;">
                    <div style="font-size:2rem;">🤝</div>
                    <div style="font-size:1.5rem;font-weight:600;"><?= number_format($profile['support_given'] ?? 0) ?></div>
                    <div style="font-size:0.8rem;color:var(--text-muted);">Support Given</div>
                </div>
                <div class="glass" style="padding:1rem;text-align:center;">
                    <div style="font-size:2rem;">💬</div>
                    <div style="font-size:1.5rem;font-weight:600;"><?= number_format($profile['comment_count'] ?? 0) ?></div>
                    <div style="font-size:0.8rem;color:var(--text-muted);">Comments</div>
                </div>
                <div class="glass" style="padding:1rem;text-align:center;">
                    <div style="font-size:2rem;">🌱</div>
                    <div style="font-size:1.5rem;font-weight:600;"><?= number_format($profile['mood_count'] ?? 0) ?></div>
                    <div style="font-size:0.8rem;color:var(--text-muted);">Mood Check-ins</div>
                </div>
            </div>
            <p style="margin-top:1rem;color:var(--text-muted);font-size:0.85rem;text-align:center;"><?= escape($quote) ?></p>
        </div>
    </div>

    <!-- SAVED TAB (Now functional) -->
    <div class="tab-content <?= $tab == 'saved' ? 'active' : '' ?>" id="tab-saved">
        <div class="glass" style="max-width:700px;margin:0 auto;">
            <h4 style="font-family:var(--font-serif);margin-bottom:1rem;">🔖 Saved Items</h4>
            <?php
            // If viewing own profile, show bookmarks; otherwise show "not available"
            if ($is_owner) {
                $stmt = $pdo->prepare("
                    SELECT b.*, p.title, p.id as post_id
                    FROM bookmarks b
                    JOIN posts p ON b.post_id = p.id
                    WHERE b.user_id = ?
                    ORDER BY b.created_at DESC
                ");
                $stmt->execute([$user_id]);
                $bookmarks = $stmt->fetchAll();
                if (empty($bookmarks)):
            ?>
                <p style="color:var(--text-muted);text-align:center;padding:1rem;">You haven't saved any posts yet.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($bookmarks as $bm): ?>
                        <a href="post.php?id=<?= $bm['post_id'] ?>" class="list-group-item glass" style="display:block;padding:0.8rem;margin-bottom:0.5rem;border-radius:var(--radius-sm);color:var(--text-primary);">
                            <strong><?= escape($bm['title'] ?? 'Untitled') ?></strong>
                            <span style="font-size:0.8rem;color:var(--text-muted);">saved <?= timeAgo($bm['created_at']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif;
            } else { ?>
                <p style="color:var(--text-muted);text-align:center;padding:1rem;">Saved items are private.</p>
            <?php } ?>
        </div>
    </div>

</div>

<!-- ============================================================
   BOTTOM NAV (Mobile)
   ============================================================ -->
<div class="bottom-nav">
    <a href="index.php"><i class="bi bi-house"></i><span>Home</span></a>
    <a href="community.php"><i class="bi bi-compass"></i><span>Explore</span></a>
    <a href="create-post.php"><i class="bi bi-plus-circle"></i><span>Create</span></a>
    <a href="chatbot.php"><i class="bi bi-robot"></i><span>AI</span></a>
    <a href="profile.php" class="active"><i class="bi bi-person"></i><span>You</span></a>
</div>

<!-- ============================================================
   FOOTER
   ============================================================ -->
<div style="text-align:center;padding:2rem;color:var(--text-muted);font-size:0.8rem;border-top:1px solid var(--glass-border);margin-top:1rem;position:relative;z-index:1;">
    <p>© <?= date('Y') ?> Haven — Your Wellness Community</p>
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
        document.dispatchEvent(new CustomEvent('toggleDark'));
    }
    if (localStorage.getItem('profileDark') === 'true') document.body.classList.add('dark');

    // ============================================================
    // NAVBAR TOGGLE
    // ============================================================
    document.getElementById('navToggle')?.addEventListener('click', function() {
        document.getElementById('navLinks').classList.toggle('open');
    });

    // ============================================================
    // TABS – FULLY FUNCTIONAL
    // ============================================================
    document.querySelectorAll('.profile-nav .tab').forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            // Remove active from all tabs
            document.querySelectorAll('.profile-nav .tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const target = this.dataset.tab;
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            // Show target content
            const content = document.getElementById('tab-' + target);
            if (content) {
                content.classList.add('active');
                // Update URL hash without reload
                const url = new URL(window.location);
                url.searchParams.set('tab', target);
                history.pushState({}, '', url);
            }
        });
    });

    // If URL has tab param, activate that tab on load
    (function() {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab');
        if (tab) {
            const tabBtn = document.querySelector(`.profile-nav .tab[data-tab="${tab}"]`);
            if (tabBtn) {
                tabBtn.click();
            }
        }
    })();

    // ============================================================
    // TOAST
    // ============================================================
    function showToast(message, type = 'info') {
        const colors = {
            info: 'linear-gradient(135deg, #E8967A, #D4C5E0)',
            success: 'linear-gradient(135deg, #4ADE80, #22D3EE)',
            warning: 'linear-gradient(135deg, #FBBF24, #FB923C)',
            danger: 'linear-gradient(135deg, #FB7185, #F43F5E)'
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
    // GSAP – ENTRANCE ANIMATIONS (same as before)
    // ============================================================
    gsap.from('#coverContainer', { scale: 0.98, opacity: 0, duration: 0.8, ease: 'power2.out' });
    gsap.from('#profileHeader', { y: 30, opacity: 0, duration: 0.6, delay: 0.2, ease: 'power2.out' });
    gsap.from('#currentChapter', { y: 20, opacity: 0, duration: 0.5, delay: 0.35, ease: 'power2.out' });
    gsap.from('#wellbeingSnapshot', { y: 20, opacity: 0, duration: 0.5, delay: 0.45, ease: 'power2.out' });
    gsap.from('#profileNav .tab', { y: 10, opacity: 0, duration: 0.4, stagger: 0.03, delay: 0.5, ease: 'power2.out' });

    // Marker highlight sweep
    const highlight = document.querySelector('.highlight');
    if (highlight) {
        setTimeout(() => highlight.classList.add('animated'), 1200);
    }

    // Stat counters
    document.querySelectorAll('.stat .number').forEach(counter => {
        const target = parseInt(counter.dataset.count) || 0;
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

    // Wellbeing bar animations
    document.querySelectorAll('.wellbeing-snapshot .fill').forEach(bar => {
        const width = parseInt(bar.dataset.width) || 0;
        setTimeout(() => { bar.style.width = width + '%'; }, 800);
    });

    // Card animations
    gsap.utils.toArray('.glass:not(.wellbeing-snapshot):not(.current-chapter)').forEach((card, i) => {
        gsap.from(card, {
            y: 20,
            opacity: 0,
            duration: 0.4,
            delay: 0.6 + i * 0.04,
            ease: 'power2.out',
            scrollTrigger: { trigger: card, toggleActions: 'play none none none', start: 'top 95%' }
        });
    });

    // Post cards animation
    gsap.utils.toArray('.post-card').forEach((card, i) => {
        gsap.from(card, {
            y: 15,
            opacity: 0,
            duration: 0.4,
            delay: 0.7 + i * 0.06,
            ease: 'power2.out',
            scrollTrigger: { trigger: card, toggleActions: 'play none none none', start: 'top 95%' }
        });
    });

    // ============================================================
    // FRIEND / SUPPORT ACTIONS
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
                showToast('Friend request sent! 🍑', 'success');
                location.reload();
            } else {
                showToast(data.error || 'Failed', 'danger');
            }
        });
    }

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
                const btn = document.querySelector('.btn-outline[onclick*="sendSupport"]');
                if (btn) {
                    const heart = document.createElement('div');
                    heart.textContent = '❤️';
                    heart.style.cssText = `
                        position: fixed; left: ${btn.getBoundingClientRect().left + btn.offsetWidth/2 - 15}px;
                        top: ${btn.getBoundingClientRect().top}px; font-size: 2rem; z-index: 9999;
                        pointer-events: none; transition: all 1.2s ease-out;
                    `;
                    document.body.appendChild(heart);
                    requestAnimationFrame(() => {
                        heart.style.transform = 'translateY(-100px) scale(2) rotate(20deg)';
                        heart.style.opacity = '0';
                    });
                    setTimeout(() => heart.remove(), 1200);
                }
            } else {
                showToast(data.error || 'Failed', 'danger');
            }
        });
    }

    function shareProfile() {
        const url = window.location.href;
        if (navigator.share) {
            navigator.share({ title: 'Check out this profile', url: url });
        } else {
            navigator.clipboard?.writeText(url).then(() => {
                showToast('Profile link copied! 🍑', 'success');
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

    function sharePost(postId) {
        const url = window.location.origin + '/post.php?id=' + postId;
        if (navigator.share) {
            navigator.share({ title: 'Check out this post', url: url });
        } else {
            navigator.clipboard?.writeText(url).then(() => {
                showToast('Post link copied!', 'success');
            });
        }
    }

    // ============================================================
    // LIKE (Reaction) – AJAX
    // ============================================================
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.like-btn');
        if (!btn) return;
        const postId = btn.dataset.postId;
        if (!USER_ID) { showToast('Please login to like', 'warning'); return; }

        fetch('api/reaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle&target_id=${postId}&target_type=post&reaction_type=support`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const countSpan = btn.querySelector('.like-count');
                if (data.action === 'added') {
                    btn.classList.add('liked');
                    btn.querySelector('i').className = 'bi bi-heart-fill';
                    countSpan.textContent = data.count;
                    const heart = document.createElement('div');
                    heart.textContent = '❤️';
                    heart.style.cssText = `
                        position: fixed; left: ${btn.getBoundingClientRect().left + btn.offsetWidth/2 - 15}px;
                        top: ${btn.getBoundingClientRect().top}px; font-size: 1.8rem; z-index: 9999;
                        pointer-events: none; transition: all 1s ease-out;
                    `;
                    document.body.appendChild(heart);
                    requestAnimationFrame(() => {
                        heart.style.transform = 'translateY(-80px) scale(1.8) rotate(15deg)';
                        heart.style.opacity = '0';
                    });
                    setTimeout(() => heart.remove(), 1000);
                } else {
                    btn.classList.remove('liked');
                    btn.querySelector('i').className = 'bi bi-heart';
                    countSpan.textContent = data.count || '';
                }
            }
        });
    });

    // ============================================================
    // KEYBOARD SHORTCUTS
    // ============================================================
    document.addEventListener('keydown', function(e) {
        if ((e.key === 'e' || e.key === 'E') && IS_OWNER && !e.target.closest('input,textarea,button')) {
            window.location.href = 'profile-edit.php';
        }
        if ((e.key === 's' || e.key === 'S') && !e.target.closest('input,textarea,button')) {
            shareProfile();
        }
    });

    // ============================================================
    // SCROLL – hide bottom nav on scroll (mobile)
    // ============================================================
    let lastScroll = 0;
    const bottomNav = document.querySelector('.bottom-nav');
    window.addEventListener('scroll', function() {
        if (window.innerWidth <= 768 && bottomNav) {
            const currentScroll = window.scrollY;
            if (currentScroll > lastScroll && currentScroll > 100) {
                bottomNav.style.transform = 'translateY(100%)';
                bottomNav.style.transition = '0.3s';
            } else {
                bottomNav.style.transform = 'translateY(0)';
            }
            lastScroll = currentScroll;
        }
    });

    console.log('🌿 Haven Profile loaded for: <?= escape($profile['anonymous_name'] ?? $profile['username']) ?>');
</script>

<?php if ($user_id): ?>
<nav class="mobile-bottom-nav">
    <a href="index.php"><i class="bi bi-house"></i>Home</a>
    <a href="community.php"><i class="bi bi-globe2"></i>Community</a>
    <a href="feed.php"><i class="bi bi-plus-circle-fill"></i>Feed</a>
    <a href="chatbot.php"><i class="bi bi-robot"></i>AI</a>
    <a href="dashboard.php" class="<?= $is_owner ? 'active' : '' ?>" style="position:relative;">
        <i class="bi bi-person-fill"></i>Me
        <?php if ($unread_notifs > 0): ?><span class="mbn-badge"><?= $unread_notifs > 9 ? '9+' : $unread_notifs ?></span><?php endif; ?>
    </a>
</nav>
<?php else: ?>
<nav class="mobile-bottom-nav">
    <a href="index.php"><i class="bi bi-house"></i>Home</a>
    <a href="community.php"><i class="bi bi-globe2"></i>Community</a>
    <a href="articles.php"><i class="bi bi-book"></i>Resources</a>
    <a href="login.php"><i class="bi bi-box-arrow-in-right"></i>Login</a>
</nav>
<?php endif; ?>
</body>
</html>