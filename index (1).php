<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Haven database connection is unavailable.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$isLoggedIn = !empty($_SESSION['user_id']);
$currentUserId = $isLoggedIn ? (int)$_SESSION['user_id'] : null;


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| RATE HAVEN (App-store style rating + review, saved to `testimonials`)
|--------------------------------------------------------------------------
*/
if (isset($_POST['action']) && $_POST['action'] === 'rate_haven') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$isLoggedIn) {
        echo json_encode(['success' => false, 'error' => 'Please log in to leave a review.']);
        exit;
    }
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 0)));
    $review = trim($_POST['review'] ?? '');
    $author = getAnonymousName($currentUserId, $pdo) ?: 'A Haven member';
    if ($rating < 1 || $review === '') {
        echo json_encode(['success' => false, 'error' => 'Please select a star rating and write a short review.']);
        exit;
    }
    try {
        // Self-healing: add `rating` and `user_id` columns the first time
        // this runs (base schema's testimonials table has neither).
        // user_id lets a logged-in visitor update their own review
        // instead of piling up duplicates, like Play Store/App Store.
        $col = $pdo->query("SHOW COLUMNS FROM testimonials LIKE 'rating'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE testimonials ADD COLUMN rating TINYINT UNSIGNED DEFAULT 5 AFTER content");
        }
        $col2 = $pdo->query("SHOW COLUMNS FROM testimonials LIKE 'user_id'")->fetch();
        if (!$col2) {
            $pdo->exec("ALTER TABLE testimonials ADD COLUMN user_id INT DEFAULT NULL AFTER id");
        }
        $existing = $pdo->prepare("SELECT id FROM testimonials WHERE user_id = ?");
        $existing->execute([$currentUserId]);
        if ($row = $existing->fetch()) {
            $pdo->prepare("UPDATE testimonials SET author = ?, content = ?, rating = ?, created_at = NOW() WHERE id = ?")
                ->execute([$author, $review, $rating, $row['id']]);
            echo json_encode(['success' => true]);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO testimonials (author, content, rating, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$author, $review, $rating, $currentUserId]);
        echo json_encode(['success' => true]);
    } catch (Throwable $ex) {
        echo json_encode(['success' => false, 'error' => 'Could not save your review right now.']);
    }
    exit;
}


/*
|--------------------------------------------------------------------------
| SITE STATISTICS
|--------------------------------------------------------------------------
*/

$stats = [
    'members' => 0,
    'posts' => 0,
    'support' => 0,
    'volunteers' => 0,
];

try {

    $stats['members'] = (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM users
            WHERE is_active = 1
        ")
        ->fetchColumn();

} catch (Throwable $e) {
    $stats['members'] = 0;
}

try {

    $stats['posts'] = (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM posts
            WHERE status = 'published'
        ")
        ->fetchColumn();

} catch (Throwable $e) {
    $stats['posts'] = 0;
}

try {

    $stats['support'] = (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM reactions
            WHERE reaction_type IN (
                'support',
                'empathy',
                'helpful'
            )
        ")
        ->fetchColumn();

} catch (Throwable $e) {
    $stats['support'] = 0;
}

try {

    $stats['volunteers'] = (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM users
            WHERE role = 'volunteer'
            AND is_active = 1
        ")
        ->fetchColumn();

} catch (Throwable $e) {
    $stats['volunteers'] = 0;
}

/*
|--------------------------------------------------------------------------
| TESTIMONIALS (ratings + reviews shown app-store style)
|--------------------------------------------------------------------------
*/
$havenReviews = [];
$havenAvgRating = null;
$havenRatingCount = 0;
try {
    $col = $pdo->query("SHOW COLUMNS FROM testimonials LIKE 'rating'")->fetch();
    if ($col) {
        $havenReviews = $pdo->query("SELECT author, content, rating, created_at FROM testimonials ORDER BY created_at DESC LIMIT 12")->fetchAll();
        $agg = $pdo->query("SELECT AVG(rating) avg_r, COUNT(*) c FROM testimonials WHERE rating IS NOT NULL")->fetch();
        if ($agg && $agg['c'] > 0) { $havenAvgRating = round((float)$agg['avg_r'], 1); $havenRatingCount = (int)$agg['c']; }
    } else {
        $havenReviews = $pdo->query("SELECT author, content, NULL as rating, created_at FROM testimonials ORDER BY created_at DESC LIMIT 12")->fetchAll();
    }
} catch (Throwable $e) { /* testimonials table optional */ }


/*
|--------------------------------------------------------------------------
| CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

try {

    $categoryStmt = $pdo->query("
        SELECT
            id,
            name,
            slug,
            description
        FROM categories
        ORDER BY name ASC
        LIMIT 12
    ");

    $categories = $categoryStmt->fetchAll();

} catch (Throwable $e) {
    $categories = [];
}


/*
|--------------------------------------------------------------------------
| PUBLIC COMMUNITY POSTS
|--------------------------------------------------------------------------
*/

$posts = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.user_id,
            p.content,
            p.media_type,
            p.media_url,
            p.visibility,
            p.status,
            p.created_at,

            pr.display_name,
            pr.anonymous_name,
            pr.avatar_url,
            pr.avatar_color,

            (
                SELECT COUNT(*)
                FROM comments c
                WHERE c.post_id = p.id
                AND c.status = 'visible'
            ) AS comment_count,

            (
                SELECT COUNT(*)
                FROM reactions r
                WHERE r.post_id = p.id
            ) AS reaction_count

        FROM posts p

        LEFT JOIN profiles pr
            ON pr.user_id = p.user_id

        WHERE p.status = 'published'
        AND p.visibility = 'public'

        ORDER BY p.created_at DESC

        LIMIT 8
    ");

    $stmt->execute();

    $posts = $stmt->fetchAll();

} catch (Throwable $e) {
    $posts = [];
}


/*
|--------------------------------------------------------------------------
| WELLNESS ARTICLES
|--------------------------------------------------------------------------
*/

$articles = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            slug,
            excerpt,
            cover_image,
            category,
            published_at

        FROM articles

        WHERE status = 'published'

        ORDER BY published_at DESC

        LIMIT 4
    ");

    $stmt->execute();

    $articles = $stmt->fetchAll();

} catch (Throwable $e) {
    $articles = [];
}


/*
|--------------------------------------------------------------------------
| TODAY'S GUIDANCE
|--------------------------------------------------------------------------
*/

$guidance = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            content,
            category,
            age_group

        FROM wellness_tips

        WHERE status = 'published'

        ORDER BY RAND()

        LIMIT 5
    ");

    $stmt->execute();

    $guidance = $stmt->fetchAll();

} catch (Throwable $e) {

    /*
     * If wellness_tips doesn't exist yet,
     * the homepage simply doesn't show this section.
     */

    $guidance = [];
}

?>
<!DOCTYPE html>

<html
    lang="en"
    dir="ltr"
>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Haven — a calm, supportive community where you can share, connect and find a little room to breathe."
    >

    <meta
        name="theme-color"
        content="#f7f4ed"
    >

    <title>Haven — A place to breathe, connect & belong</title>

    <link
        rel="icon"
        href="logo.png"
        type="image/png"
    >

    <!-- Fonts -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <!-- GSAP -->

    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"
    ></script>

    <style>

        :root {

            --cream: #f7f4ed;
            --cream-2: #eee9df;

            --white: #ffffff;

            --sage: #879d8b;
            --sage-dark: #5e7564;
            --sage-soft: #dfe9df;

            --peach: #e8b99f;
            --peach-soft: #f5ded2;

            --ink: #26332b;
            --muted: #7c857e;

            --line: rgba(94,117,100,.12);

            --card:
                rgba(255,255,255,.67);

            --shadow:
                0 20px 60px rgba(64,77,67,.08);

            --radius-xl: 30px;
            --radius-lg: 22px;
            --radius-md: 16px;
        }


        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        html {
            scroll-behavior: smooth;
        }


        body {

            min-height: 100vh;

            background:
                radial-gradient(
                    circle at 10% 5%,
                    rgba(232,185,159,.18),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 90% 10%,
                    rgba(135,157,139,.17),
                    transparent 30%
                ),
                var(--cream);

            color: var(--ink);

            font-family:
                "DM Sans",
                sans-serif;
        }


        a {
            color: inherit;
        }


        img {
            max-width: 100%;
            display: block;
        }


        .container {

            width:
                min(1180px, calc(100% - 40px));

            margin: auto;
        }


        /* =========================================================
           NAVBAR
        ========================================================= */

        .navbar {

            position: sticky;

            top: 15px;

            z-index: 100;

            width:
                min(1180px, calc(100% - 40px));

            margin: 15px auto 0;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 10px 12px 10px 18px;

            border:
                1px solid rgba(255,255,255,.8);

            background:
                rgba(255,255,255,.65);

            backdrop-filter: blur(18px);

            -webkit-backdrop-filter: blur(18px);

            border-radius: 20px;

            box-shadow:
                0 10px 35px rgba(65,76,67,.06);
        }


        .brand {

            display: flex;

            align-items: center;

            gap: 10px;

            text-decoration: none;
        }


        .brand-mark {

            width: 42px;
            height: 42px;

            display: grid;

            place-items: center;

            border-radius: 14px;
            overflow: hidden;

            background:
                linear-gradient(
                    145deg,
                    var(--peach-soft),
                    var(--sage-soft)
                );
        }

        .brand-logo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }


        .brand-mark svg {

            width: 24px;
            height: 24px;

            stroke: var(--sage-dark);
        }


        .brand-name {

            font-family:
                "Playfair Display",
                serif;

            font-size: 24px;

            font-weight: 600;
        }


        .nav-links {

            display: flex;

            align-items: center;

            gap: 6px;
        }


        .nav-links a {

            padding: 10px 13px;

            border-radius: 12px;

            text-decoration: none;

            font-size: 13px;

            color: var(--muted);

            transition: .25s ease;
        }


        .nav-links a:hover {

            color: var(--sage-dark);

            background:
                rgba(135,157,139,.08);
        }


        .nav-actions {

            display: flex;

            align-items: center;

            gap: 8px;
        }


        .login-link {

            padding: 10px 14px;

            text-decoration: none;

            color: var(--sage-dark);

            font-size: 13px;

            font-weight: 600;
        }


        .join-btn {

            padding: 11px 17px;

            background:
                var(--sage-dark);

            color: white;

            border-radius: 13px;

            text-decoration: none;

            font-size: 13px;

            font-weight: 700;

            box-shadow:
                0 8px 22px rgba(94,117,100,.18);
        }


        .menu-btn {

            display: none;

            border: 0;

            background: transparent;

            font-size: 23px;

            cursor: pointer;
        }


        /* =========================================================
           HERO
        ========================================================= */

        .hero {

            min-height: 730px;

            display: grid;

            grid-template-columns:
                1.05fr .95fr;

            align-items: center;

            gap: 70px;

            padding:
                85px 0 100px;
        }


        .hero-copy {

            max-width: 650px;
        }


        .eyebrow {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding: 8px 13px;

            border-radius: 999px;

            background:
                rgba(255,255,255,.58);

            border:
                1px solid rgba(255,255,255,.8);

            color: var(--sage-dark);

            font-size: 12px;

            font-weight: 700;

            letter-spacing: .6px;

            text-transform: uppercase;
        }


        .eyebrow span {

            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: var(--sage);
        }


        .hero h1 {

            margin-top: 25px;

            font-family:
                "Playfair Display",
                serif;

            font-size:
                clamp(54px, 7vw, 82px);

            line-height: 1.02;

            letter-spacing: -3px;
        }


        .hero h1 em {

            font-style: normal;

            color: var(--sage-dark);
        }


        .hero-description {

            margin-top: 25px;

            max-width: 570px;

            color: var(--muted);

            font-size: 17px;

            line-height: 1.8;
        }


        .hero-actions {

            display: flex;

            gap: 12px;

            flex-wrap: wrap;

            margin-top: 32px;
        }


        .btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            min-height: 49px;

            padding: 0 20px;

            border-radius: 15px;

            text-decoration: none;

            font-size: 13px;

            font-weight: 700;

            transition:
                transform .25s ease,
                box-shadow .25s ease;
        }


        .btn:hover {

            transform: translateY(-3px);
        }


        .btn-primary {

            color: white;

            background:
                linear-gradient(
                    135deg,
                    var(--sage-dark),
                    #819787
                );

            box-shadow:
                0 13px 28px rgba(94,117,100,.18);
        }


        .btn-secondary {

            background:
                rgba(255,255,255,.7);

            border:
                1px solid rgba(255,255,255,.85);

            color: var(--sage-dark);
        }


        /* =========================================================
           HERO VISUAL
        ========================================================= */

        .hero-art {

            position: relative;

            min-height: 500px;

            display: grid;

            place-items: center;
        }


        .art-halo {

            position: absolute;

            width: 430px;
            height: 430px;

            border-radius: 50%;

            background:
                radial-gradient(
                    circle,
                    rgba(220,231,221,.75),
                    rgba(220,231,221,.18) 55%,
                    transparent 70%
                );
        }


        .haven-card {

            position: relative;

            width: min(390px, 100%);

            padding: 30px;

            border-radius: 30px;

            background:
                rgba(255,255,255,.68);

            border:
                1px solid rgba(255,255,255,.9);

            backdrop-filter:
                blur(20px);

            -webkit-backdrop-filter:
                blur(20px);

            box-shadow:
                0 35px 80px rgba(65,76,67,.12);

            transform:
                rotate(2deg);
        }


        .card-top {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 25px;
        }


        .mini-label {

            color: var(--sage-dark);

            font-size: 11px;

            font-weight: 700;

            letter-spacing: .6px;

            text-transform: uppercase;
        }


        .breathing-dot {

            width: 13px;
            height: 13px;

            border-radius: 50%;

            background: var(--sage);

            box-shadow:
                0 0 0 7px
                rgba(135,157,139,.12);
        }


        .card-message {

            font-family:
                "Playfair Display",
                serif;

            font-size: 28px;

            line-height: 1.35;
        }


        .card-message span {

            color: var(--sage-dark);
        }


        .card-line {

            width: 100%;

            height: 1px;

            background: var(--line);

            margin: 25px 0;
        }


        .card-bottom {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .avatar-stack {

            display: flex;
        }


        .avatar {

            width: 32px;
            height: 32px;

            margin-left: -7px;

            border-radius: 50%;

            border: 2px solid white;

            display: grid;

            place-items: center;

            font-size: 11px;

            font-weight: 700;

            color: var(--sage-dark);

            background: var(--sage-soft);
        }


        .avatar:first-child {
            margin-left: 0;
        }


        .card-small-text {

            font-size: 11px;

            color: var(--muted);

            line-height: 1.5;
        }


        .floating-note {

            position: absolute;

            right: -10px;

            bottom: 55px;

            width: 190px;

            padding: 15px;

            border-radius: 18px;

            background:
                rgba(255,255,255,.78);

            border:
                1px solid rgba(255,255,255,.9);

            box-shadow:
                0 18px 40px rgba(65,76,67,.10);

            font-size: 11px;

            color: var(--muted);
        }


        .floating-note strong {

            display: block;

            margin-bottom: 5px;

            color: var(--ink);
        }


        /* =========================================================
           STATS
        ========================================================= */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 14px;

            margin-bottom: 100px;
        }


        .stat {

            padding: 25px;

            border-radius: 20px;

            background:
                rgba(255,255,255,.55);

            border:
                1px solid rgba(255,255,255,.75);

            box-shadow:
                0 15px 35px rgba(65,76,67,.04);
        }


        .stat-number {

            font-family:
                "Playfair Display",
                serif;

            font-size: 34px;

            color: var(--sage-dark);
        }


        .stat-label {

            margin-top: 5px;

            font-size: 12px;

            color: var(--muted);
        }


        /* =========================================================
           SECTION
        ========================================================= */

        .section {

            padding:
                80px 0;
        }


        .section-heading {

            display: flex;

            justify-content: space-between;

            align-items: end;

            gap: 20px;

            margin-bottom: 30px;
        }


        .section-heading h2 {

            font-family:
                "Playfair Display",
                serif;

            font-size:
                clamp(32px, 4vw, 45px);

            letter-spacing: -1px;
        }


        .section-heading p {

            max-width: 500px;

            color: var(--muted);

            font-size: 14px;

            line-height: 1.7;
        }


        .text-link {

            color: var(--sage-dark);

            text-decoration: none;

            font-size: 13px;

            font-weight: 700;

            white-space: nowrap;
        }


        /* =========================================================
           CATEGORIES
        ========================================================= */

        .categories {

            display: flex;

            gap: 9px;

            flex-wrap: wrap;

            margin-bottom: 25px;
        }


        .category {

            padding: 9px 14px;

            border-radius: 999px;

            background:
                rgba(255,255,255,.65);

            border:
                1px solid rgba(255,255,255,.8);

            color: var(--muted);

            text-decoration: none;

            font-size: 12px;

            transition: .25s ease;
        }


        .category:hover {

            color: var(--sage-dark);

            transform: translateY(-2px);
        }


        /* =========================================================
           POSTS
        ========================================================= */

        .post-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 17px;
        }


        .post {

            padding: 22px;

            border-radius: 22px;

            background:
                rgba(255,255,255,.68);

            border:
                1px solid rgba(255,255,255,.82);

            box-shadow:
                0 16px 35px rgba(65,76,67,.05);

            transition:
                transform .3s ease,
                box-shadow .3s ease;
        }


        .post:hover {

            transform: translateY(-5px);

            box-shadow:
                0 25px 50px rgba(65,76,67,.09);
        }


        .post-author {

            display: flex;

            align-items: center;

            gap: 10px;

            margin-bottom: 16px;
        }


        .post-avatar {

            width: 38px;
            height: 38px;

            border-radius: 50%;

            display: grid;

            place-items: center;

            font-size: 12px;

            font-weight: 700;

            color: var(--sage-dark);

            background:
                var(--sage-soft);

            overflow: hidden;
        }


        .post-avatar img {

            width: 100%;
            height: 100%;

            object-fit: cover;
        }


        .post-author-name {

            font-size: 12px;

            font-weight: 700;
        }


        .post-time {

            font-size: 10px;

            color: var(--muted);

            margin-top: 2px;
        }


        .post-content {

            color: #556059;

            font-size: 13px;

            line-height: 1.75;

            display: -webkit-box;

            -webkit-line-clamp: 5;

            -webkit-box-orient: vertical;

            overflow: hidden;
        }


        .post-footer {

            display: flex;

            align-items: center;

            gap: 15px;

            margin-top: 20px;

            padding-top: 15px;

            border-top:
                1px solid var(--line);

            font-size: 11px;

            color: var(--muted);
        }


        /* =========================================================
           GUIDANCE
        ========================================================= */

        .guidance-grid {

            display: grid;

            grid-template-columns:
                repeat(5, 1fr);

            gap: 12px;
        }


        .guidance {

            min-height: 180px;

            padding: 21px;

            border-radius: 21px;

            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.76),
                    rgba(255,255,255,.42)
                );

            border:
                1px solid rgba(255,255,255,.82);
        }


        .guidance-category {

            color: var(--sage-dark);

            font-size: 10px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .6px;
        }


        .guidance h3 {

            margin-top: 12px;

            font-family:
                "Playfair Display",
                serif;

            font-size: 20px;

            line-height: 1.3;
        }


        .guidance p {

            margin-top: 10px;

            color: var(--muted);

            font-size: 11px;

            line-height: 1.65;
        }


        /* =========================================================
           ARTICLES
        ========================================================= */

        .article-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;
        }


        .article {

            overflow: hidden;

            border-radius: 21px;

            background:
                rgba(255,255,255,.65);

            border:
                1px solid rgba(255,255,255,.8);
        }


        .article-image {

            height: 170px;

            background:
                linear-gradient(
                    135deg,
                    var(--sage-soft),
                    var(--peach-soft)
                );

            display: grid;

            place-items: center;

            color: var(--sage-dark);

            font-size: 12px;
        }


        .article-body {

            padding: 18px;
        }


        .article-category {

            font-size: 10px;

            color: var(--sage-dark);

            font-weight: 700;

            text-transform: uppercase;
        }


        .article h3 {

            margin-top: 9px;

            font-family:
                "Playfair Display",
                serif;

            font-size: 19px;

            line-height: 1.35;
        }


        /* =========================================================
           AI
        ========================================================= */

        .ai-section {

            padding:
                80px 0;
        }


        .ai-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 16px;
        }


        .ai-card {

            padding: 28px;

            border-radius: 25px;

            background:
                rgba(255,255,255,.65);

            border:
                1px solid rgba(255,255,255,.82);

            box-shadow:
                0 18px 40px rgba(65,76,67,.05);
        }


        .ai-icon {

            width: 48px;
            height: 48px;

            display: grid;

            place-items: center;

            border-radius: 15px;

            background:
                linear-gradient(
                    135deg,
                    var(--sage-soft),
                    var(--peach-soft)
                );

            color: var(--sage-dark);

            font-weight: 700;
        }


        .ai-card h3 {

            margin-top: 20px;

            font-family:
                "Playfair Display",
                serif;

            font-size: 25px;
        }


        .ai-card p {

            margin-top: 10px;

            color: var(--muted);

            font-size: 13px;

            line-height: 1.75;
        }


        /* =========================================================
           CTA
        ========================================================= */

        .cta {

            margin:
                50px 0 90px;

            padding:
                65px 40px;

            text-align: center;

            border-radius: 32px;

            background:
                linear-gradient(
                    135deg,
                    rgba(223,233,223,.85),
                    rgba(245,222,210,.72)
                );

            border:
                1px solid rgba(255,255,255,.8);
        }


        .cta h2 {

            font-family:
                "Playfair Display",
                serif;

            font-size:
                clamp(32px, 5vw, 48px);
        }


        .cta p {

            max-width: 560px;

            margin: 15px auto 25px;

            color: var(--muted);

            font-size: 14px;

            line-height: 1.7;
        }


        /* =========================================================
           FOOTER
        ========================================================= */

        footer {

            border-top:
                1px solid var(--line);

            padding:
                45px 0 35px;

            color: var(--muted);

            font-size: 12px;
        }


        .footer-inner {

            display: flex;

            justify-content: space-between;

            gap: 20px;

            flex-wrap: wrap;
        }


        .footer-brand {

            font-family:
                "Playfair Display",
                serif;

            color: var(--ink);

            font-size: 22px;
        }


        .footer-links {

            display: flex;

            gap: 18px;

            flex-wrap: wrap;
        }


        .footer-links a {

            text-decoration: none;
        }


        /* =========================================================
           CHATBOT FLOATING BUTTON
        ========================================================= */

        .chatbot-launcher {

            position: fixed;

            left: 24px;

            bottom: 24px;

            z-index: 500;

            width: 62px;
            height: 62px;

            border-radius: 21px;

            border:
                1px solid rgba(255,255,255,.85);

            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.86),
                    rgba(223,233,223,.82)
                );

            box-shadow:
                0 15px 35px rgba(65,76,67,.15);

            display: grid;

            place-items: center;

            cursor: pointer;

            transition:
                transform .25s ease;
        }


        .chatbot-launcher:hover {

            transform:
                translateY(-5px)
                scale(1.03);
        }


        .chatbot-launcher svg {

            width: 28px;
            height: 28px;

            stroke: var(--sage-dark);
        }


        .chatbot-pulse {

            position: absolute;

            inset: -5px;

            border-radius: 25px;

            border:
                1px solid
                rgba(135,157,139,.25);

            animation:
                chatbotPulse 2.8s infinite;
        }


        @keyframes chatbotPulse {

            0% {
                transform: scale(.9);
                opacity: .8;
            }

            70% {
                transform: scale(1.18);
                opacity: 0;
            }

            100% {
                opacity: 0;
            }
        }


        /* =========================================================
           LOGIN PROMPT
        ========================================================= */

        .login-overlay {

            position: fixed;

            inset: 0;

            z-index: 1000;

            display: none;

            align-items: center;

            justify-content: center;

            padding: 20px;

            background:
                rgba(38,51,43,.20);

            backdrop-filter:
                blur(8px);

            -webkit-backdrop-filter:
                blur(8px);
        }


        .login-overlay.active {

            display: flex;
        }


        .login-modal {

            width:
                min(430px, 100%);

            padding: 32px;

            border-radius: 28px;

            background:
                rgba(255,255,255,.88);

            border:
                1px solid rgba(255,255,255,.95);

            box-shadow:
                0 35px 90px rgba(40,50,43,.18);

            text-align: center;
        }


        .modal-icon {

            width: 58px;
            height: 58px;

            margin: 0 auto 18px;

            border-radius: 18px;

            display: grid;

            place-items: center;

            background:
                linear-gradient(
                    135deg,
                    var(--sage-soft),
                    var(--peach-soft)
                );

            color: var(--sage-dark);
        }


        .modal-icon svg {

            width: 28px;
            height: 28px;
        }


        .login-modal h3 {

            font-family:
                "Playfair Display",
                serif;

            font-size: 28px;
        }


        .login-modal p {

            margin-top: 10px;

            color: var(--muted);

            font-size: 13px;

            line-height: 1.7;
        }


        .modal-actions {

            display: grid;

            gap: 9px;

            margin-top: 23px;
        }


        .modal-close {

            margin-top: 13px;

            border: 0;

            background: transparent;

            color: var(--muted);

            cursor: pointer;

            font-size: 12px;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 900px) {

            .nav-links {
                display: none;
            }

            .menu-btn {
                display: block;
            }

            .hero {

                grid-template-columns: 1fr;

                text-align: center;

                padding-top: 65px;
            }

            .hero-description {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-actions {
                justify-content: center;
            }

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .post-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .guidance-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .article-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

        }


        @media (max-width: 600px) {

            .container,
            .navbar {

                width:
                    calc(100% - 26px);
            }

            .navbar {
                top: 8px;
                margin-top: 8px;
            }

            .nav-actions .login-link {
                display: none;
            }

            .hero {

                min-height: auto;

                padding:
                    55px 0 70px;
            }

            .hero h1 {

                font-size: 52px;

                letter-spacing: -2px;
            }

            .hero-description {
                font-size: 15px;
            }

            .hero-art {
                min-height: 390px;
            }

            .art-halo {
                width: 320px;
                height: 320px;
            }

            .haven-card {
                width: 330px;
                max-width: 100%;
            }

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);

                margin-bottom: 50px;
            }

            .stat {
                padding: 18px;
            }

            .stat-number {
                font-size: 28px;
            }

            .post-grid,
            .guidance-grid,
            .article-grid,
            .ai-grid {

                grid-template-columns: 1fr;
            }

            .section-heading {

                align-items: start;

                flex-direction: column;
            }

            .chatbot-launcher {

                width: 57px;
                height: 57px;

                left: 17px;
                bottom: 17px;
            }

            .cta {
                padding: 45px 22px;
            }
        }

        .mobile-bottom-nav { display: none; }
        @media (max-width: 767px) {
            body { padding-bottom: 64px; }
            .mobile-bottom-nav {
                display: flex;
                position: fixed;
                left: 0; right: 0; bottom: 0;
                z-index: 1040;
                height: 60px;
                background: rgba(255,253,248,0.96);
                backdrop-filter: blur(16px);
                border-top: 1px solid var(--line);
                justify-content: space-around;
                align-items: center;
                box-shadow: 0 -6px 24px rgba(64,77,67,.08);
            }
            .mobile-bottom-nav a {
                flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
                gap: 2px; color: var(--muted); font-size: 0.62rem; font-weight: 600; text-decoration: none;
                padding: 6px 0; position: relative; font-family: 'DM Sans', sans-serif;
            }
            .mobile-bottom-nav a i { font-size: 1.25rem; }
            .mobile-bottom-nav a.active { color: var(--sage-dark); }
            .mobile-bottom-nav a .mbn-badge {
                position: absolute; top: 2px; right: 22%; background: var(--peach);
                color: var(--ink); border-radius: 8px; font-size: 0.55rem; padding: 0 4px; line-height: 1.3;
            }
        }

        /* Ratings & reviews */
        .review-summary { display: flex; align-items: center; gap: 18px; margin-bottom: 28px; }
        .review-score { font-family: 'Playfair Display', serif; font-size: 3rem; color: var(--ink); line-height: 1; }
        .review-stars { color: #d9a441; font-size: 1.1rem; letter-spacing: 2px; }
        .review-stars.small { font-size: 0.85rem; margin-bottom: 8px; }
        .review-count { color: var(--muted); font-size: 0.85rem; margin-top: 4px; }
        .review-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
        .review-card { background: var(--card); backdrop-filter: blur(18px); border: 1px solid rgba(255,255,255,.8); border-radius: var(--radius-lg); padding: 22px; box-shadow: var(--shadow); }
        .review-text { color: var(--ink); font-size: 0.92rem; line-height: 1.6; margin-bottom: 12px; }
        .review-author { color: var(--muted); font-size: 0.8rem; font-weight: 600; }

        /* Rate Haven modal */
        .rate-modal-backdrop { position: fixed; inset: 0; background: rgba(38,51,43,.45); display: none; align-items: center; justify-content: center; z-index: 3000; padding: 20px; }
        .rate-modal-backdrop.open { display: flex; }
        .rate-modal-box { background: var(--white); border-radius: 26px; padding: 30px; max-width: 420px; width: 100%; box-shadow: 0 30px 80px rgba(38,51,43,.25); }
        .rate-modal-box h3 { margin-bottom: 4px; }
        .rate-modal-stars { font-size: 2.2rem; letter-spacing: 8px; text-align: center; margin: 16px 0; cursor: pointer; }
        .rate-modal-stars span { color: #ddd; transition: .15s; }
        .rate-modal-stars span.on { color: #d9a441; }
        .rate-modal-box textarea, .rate-modal-box input[type=text] {
            width: 100%; border-radius: 14px; border: 1px solid var(--line); padding: 10px 14px;
            background: rgba(255,255,255,.6); font-family: 'DM Sans', sans-serif; margin-bottom: 10px;
        }
        .rate-modal-actions { display: flex; gap: 10px; margin-top: 6px; }
        .rate-modal-actions button { flex: 1; border-radius: 14px; padding: 10px; border: none; font-weight: 600; cursor: pointer; }
        .rate-modal-cancel { background: var(--cream-2); color: var(--ink); }
    </style>

</head>


<body>


<!-- =========================================================
     NAVIGATION
========================================================= -->

<nav class="navbar">

    <a
        href="index.php"
        class="brand"
    >

        <div class="brand-mark">

            <img src="logo.png" alt="Haven" class="brand-logo-img">

        </div>

        <span class="brand-name">
            Haven
        </span>

    </a>


    <div class="nav-links">

        <a href="index.php">
            Home
        </a>

        <a href="community.php">
            Community
        </a>

        <a href="articles.php">
            Articles
        </a>

        <a href="consultation.php?tab=new">
            Support
        </a>

    </div>


    <div class="nav-actions">

        <?php if ($isLoggedIn): ?>

            <a
                href="dashboard.php"
                class="join-btn"
            >
                My Haven
            </a>

        <?php else: ?>

            <a
                href="login.php"
                class="login-link"
            >
                Sign in
            </a>

            <a
                href="register.php"
                class="join-btn"
            >
                Join Haven
            </a>

        <?php endif; ?>

    </div>

</nav>


<!-- =========================================================
     HERO
========================================================= -->

<main>

<section class="container hero">


    <div class="hero-copy">

        <div class="eyebrow">

            <span></span>

            A calmer corner of the internet

        </div>


        <h1>

            You don't have to
            <em>carry everything</em>
            alone.

        </h1>


        <p class="hero-description">

            Haven is a gentle community where you can
            share how you're feeling, find understanding,
            learn about your wellbeing, and connect with
            people who care.

        </p>


        <div class="hero-actions">

            <a
                href="community.php"
                class="btn btn-primary"
            >
                Explore the community
                →
            </a>


            <?php if (!$isLoggedIn): ?>

                <a
                    href="register.php"
                    class="btn btn-secondary"
                >
                    Create your Haven
                </a>

            <?php else: ?>

                <a
                    href="dashboard.php"
                    class="btn btn-secondary"
                >
                    Open my dashboard
                </a>

            <?php endif; ?>

        </div>

    </div>


    <div class="hero-art">

        <div class="art-halo"></div>


        <div class="haven-card">

            <div class="card-top">

                <span class="mini-label">
                    A moment at Haven
                </span>

                <span class="breathing-dot"></span>

            </div>


            <div class="card-message">

                “Some days don't need
                <span>answers.</span>
                They just need
                <span>understanding.</span>”

            </div>


            <div class="card-line"></div>


            <div class="card-bottom">

                <div class="avatar-stack">

                    <div class="avatar">A</div>
                    <div class="avatar">N</div>
                    <div class="avatar">S</div>
                    <div class="avatar">+</div>

                </div>


                <div class="card-small-text">

                    People are here,
                    listening without judgment.

                </div>

            </div>

        </div>


        <div class="floating-note">

            <strong>
                Take a breath.
            </strong>

            You can explore Haven at
            your own pace. There is no
            pressure to share anything.

        </div>

    </div>

</section>


<!-- =========================================================
     STATISTICS
========================================================= -->

<section class="container stats">

    <div class="stat">

        <div
            class="stat-number"
            data-count="<?= $stats['members'] ?>"
        >
            0
        </div>

        <div class="stat-label">
            people in the community
        </div>

    </div>


    <div class="stat">

        <div
            class="stat-number"
            data-count="<?= $stats['posts'] ?>"
        >
            0
        </div>

        <div class="stat-label">
            stories shared
        </div>

    </div>


    <div class="stat">

        <div
            class="stat-number"
            data-count="<?= $stats['support'] ?>"
        >
            0
        </div>

        <div class="stat-label">
            moments of support
        </div>

    </div>


    <div class="stat">

        <div
            class="stat-number"
            data-count="<?= $stats['volunteers'] ?>"
        >
            0
        </div>

        <div class="stat-label">
            people offering support
        </div>

    </div>

</section>


<!-- =========================================================
     RATINGS & REVIEWS (app-store style)
========================================================= -->

<section class="container section" id="reviews">

    <div class="section-heading">
        <div>
            <h2>Loved by the people who use it.</h2>
            <p>Real words from the Haven community.</p>
        </div>
        <button type="button" class="btn-primary" style="border:none;cursor:pointer;" onclick="document.getElementById('rateHavenModal').classList.add('open')">
            <i class="bi bi-star-fill"></i> Rate Haven
        </button>
    </div>

    <?php if ($havenAvgRating !== null): ?>
    <div class="review-summary">
        <div class="review-score"><?= number_format($havenAvgRating, 1) ?></div>
        <div>
            <div class="review-stars">
                <?php for ($i = 1; $i <= 5; $i++): ?><i class="bi bi-star<?= $i <= round($havenAvgRating) ? '-fill' : '' ?>"></i><?php endfor; ?>
            </div>
            <div class="review-count"><?= $havenRatingCount ?> rating<?= $havenRatingCount == 1 ? '' : 's' ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="review-grid">
        <?php if (empty($havenReviews)): ?>
            <p>Be the first to share what Haven means to you.</p>
        <?php endif; ?>
        <?php foreach ($havenReviews as $r): ?>
            <div class="review-card">
                <?php if ($r['rating']): ?>
                <div class="review-stars small">
                    <?php for ($i = 1; $i <= 5; $i++): ?><i class="bi bi-star<?= $i <= (int)$r['rating'] ? '-fill' : '' ?>"></i><?php endfor; ?>
                </div>
                <?php endif; ?>
                <p class="review-text">&ldquo;<?= nl2br(e(mb_substr($r['content'], 0, 240))) ?>&rdquo;</p>
                <div class="review-author"><?= e($r['author'] ?: 'A Haven member') ?></div>
            </div>
        <?php endforeach; ?>
    </div>

</section>


<!-- =========================================================
     COMMUNITY
========================================================= -->

<section class="container section">

    <div class="section-heading">

        <div>

            <h2>
                A living community.
            </h2>

            <p>
                Read what people are sharing today.
                You don't need an account to listen.
            </p>

        </div>


        <a
            href="community.php"
            class="text-link"
        >
            Explore everything →
        </a>

    </div>


    <?php if ($categories): ?>

        <div class="categories">

            <?php foreach ($categories as $category): ?>

                <a
                    href="community.php?category=<?= urlencode((string)$category['slug']) ?>"
                    class="category"
                >
                    <?= e($category['name']) ?>
                </a>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <div class="post-grid">

        <?php if ($posts): ?>

            <?php foreach ($posts as $post): ?>

                <?php

                $author =
                    $post['anonymous_name']
                    ?: $post['display_name']
                    ?: 'Haven member';

                ?>

                <article class="post">

                    <div class="post-author">

                        <div
                            class="post-avatar"
                            style="
                                background:
                                <?= e(
                                    $post['avatar_color']
                                    ?: '#dfe9df'
                                ) ?>
                            "
                        >

                            <?php if (!empty($post['avatar_url'])): ?>

                                <img
                                    src="<?= e($post['avatar_url']) ?>"
                                    alt=""
                                >

                            <?php else: ?>

                                <?= e(
                                    strtoupper(
                                        mb_substr(
                                            $author,
                                            0,
                                            1
                                        )
                                    )
                                ) ?>

                            <?php endif; ?>

                        </div>


                        <div>

                            <div class="post-author-name">
                                <?= e($author) ?>
                            </div>

                            <div class="post-time">
                                <?= e(
                                    timeAgo(
                                        (string)$post['created_at']
                                    )
                                ) ?>
                            </div>

                        </div>

                    </div>


                    <div class="post-content">

                        <?= nl2br(
                            e(
                                (string)$post['content']
                            )
                        ) ?>

                    </div>


                    <div class="post-footer">

                        <span>
                            ♡
                            <?= (int)$post['reaction_count'] ?>
                        </span>

                        <span>
                            ◌
                            <?= (int)$post['comment_count'] ?>
                        </span>

                        <span>
                            Public
                        </span>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php else: ?>

            <div
                style="
                    grid-column:1/-1;
                    text-align:center;
                    padding:60px 20px;
                    color:#7c857e;
                "
            >

                The community is quietly waiting
                for its first stories.

            </div>

        <?php endif; ?>

    </div>

</section>


<!-- =========================================================
     WELLNESS GUIDANCE
========================================================= -->

<?php if ($guidance): ?>

<section class="container section">

    <div class="section-heading">

        <div>

            <h2>
                A little something for today.
            </h2>

            <p>
                Small, practical ideas that may help
                you care for yourself today.
            </p>

        </div>

    </div>


    <div class="guidance-grid">

        <?php foreach ($guidance as $tip): ?>

            <article class="guidance">

                <div class="guidance-category">
                    <?= e(
                        $tip['category']
                        ?: 'Wellbeing'
                    ) ?>
                </div>


                <h3>
                    <?= e(
                        $tip['title']
                    ) ?>
                </h3>


                <p>
                    <?= e(
                        $tip['content']
                    ) ?>
                </p>

            </article>

        <?php endforeach; ?>

    </div>

</section>

<?php endif; ?>


<!-- =========================================================
     ARTICLES
========================================================= -->

<?php if ($articles): ?>

<section class="container section">

    <div class="section-heading">

        <div>

            <h2>
                Read at your own pace.
            </h2>

            <p>
                Thoughtful articles about emotional
                wellbeing, relationships, stress,
                growth and everyday life.
            </p>

        </div>


        <a
            href="articles.php"
            class="text-link"
        >
            View all articles →
        </a>

    </div>


    <div class="article-grid">

        <?php foreach ($articles as $article): ?>

            <a
                href="article.php?slug=<?= urlencode(
                    (string)$article['slug']
                ) ?>"
                class="article"
                style="text-decoration:none;"
            >

                <div class="article-image">

                    <?php if (!empty($article['cover_image'])): ?>

                        <img
                            src="<?= e(
                                $article['cover_image']
                            ) ?>"
                            alt=""
                            style="
                                width:100%;
                                height:100%;
                                object-fit:cover;
                            "
                        >

                    <?php else: ?>

                        Haven Reading

                    <?php endif; ?>

                </div>


                <div class="article-body">

                    <div class="article-category">

                        <?= e(
                            $article['category']
                            ?: 'Wellbeing'
                        ) ?>

                    </div>


                    <h3>
                        <?= e(
                            $article['title']
                        ) ?>
                    </h3>

                </div>

            </a>

        <?php endforeach; ?>

    </div>

</section>

<?php endif; ?>


<!-- =========================================================
     HOW HAVEN WORKS
========================================================= -->

<section class="container section">

    <div class="section-heading">

        <div>

            <h2>
                Haven works at your pace.
            </h2>

            <p>
                There is no requirement to share.
                You can simply read, learn and
                take what you need.
            </p>

        </div>

    </div>


    <div class="guidance-grid">

        <article class="guidance">

            <div class="guidance-category">
                01 · Share
            </div>

            <h3>
                Say what you're feeling.
            </h3>

            <p>
                Share a thought, a difficult day,
                a small victory or simply how
                you're doing.
            </p>

        </article>


        <article class="guidance">

            <div class="guidance-category">
                02 · Connect
            </div>

            <h3>
                Find people who understand.
            </h3>

            <p>
                Community members can respond
                with supportive reactions,
                comments and encouragement.
            </p>

        </article>


        <article class="guidance">

            <div class="guidance-category">
                03 · Understand
            </div>

            <h3>
                Learn more about yourself.
            </h3>

            <p>
                Haven's AI tools can help identify
                patterns, emotions and useful
                wellbeing resources.
            </p>

        </article>


        <article class="guidance">

            <div class="guidance-category">
                04 · Grow
            </div>

            <h3>
                Take the next small step.
            </h3>

            <p>
                Sometimes progress isn't a giant
                change. Sometimes it's simply
                making it through today.
            </p>

        </article>


        <article class="guidance">

            <div class="guidance-category">
                05 · Support
            </div>

            <h3>
                Reach a real person.
            </h3>

            <p>
                When appropriate, Haven can connect
                users with trained volunteers for
                human support.
            </p>

        </article>

    </div>

</section>


<!-- =========================================================
     AI
========================================================= -->

<section class="container ai-section">

    <div class="section-heading">

        <div>

            <h2>
                Technology with a human purpose.
            </h2>

            <p>
                Haven's three AI systems have different
                responsibilities. They complement human
                support rather than replacing it.
            </p>

        </div>

    </div>


    <div class="ai-grid">

        <article class="ai-card">

            <div class="ai-icon">
                MG
            </div>

            <h3>
                MindGuide
            </h3>

            <p>
                Your gentle AI companion for everyday
                conversations, reflection, coping ideas
                and wellbeing guidance.
            </p>

        </article>


        <article class="ai-card">

            <div class="ai-icon">
                MS
            </div>

            <h3>
                MindShield
            </h3>

            <p>
                Helps Haven identify potentially harmful
                or high-risk content and helps moderators
                respond appropriately.
            </p>

        </article>


        <article class="ai-card">

            <div class="ai-icon">
                MI
            </div>

            <h3>
                MindInsight
            </h3>

            <p>
                Helps users understand longer-term mood
                and wellbeing patterns without turning
                those patterns into a diagnosis.
            </p>

        </article>

    </div>

</section>


<!-- =========================================================
     CTA
========================================================= -->

<section class="container">

    <div class="cta">

        <h2>
            There is room for you here.
        </h2>

        <p>
            You can start by simply looking around.
            When you're ready, create your own quiet
            corner inside Haven.
        </p>


        <?php if ($isLoggedIn): ?>

            <a
                href="community.php"
                class="btn btn-primary"
            >
                Enter the community
                →
            </a>

        <?php else: ?>

            <a
                href="register.php"
                class="btn btn-primary"
            >
                Join Haven
                →
            </a>

        <?php endif; ?>

    </div>

</section>

</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    <div class="container footer-inner">

        <div>

            <div class="footer-brand">
                Haven
            </div>

            <div style="margin-top:7px;">
                A calmer corner of the internet.
            </div>

        </div>


        <div class="footer-links">

            <a href="community.php">
                Community
            </a>

            <a href="articles.php">
                Articles
            </a>

            <a href="consultation.php?tab=new">
                Support
            </a>

            <a href="404.php">
                Help
            </a>

        </div>

    </div>

</footer>


<!-- =========================================================
     MINDGUIDE FLOATING BUTTON
========================================================= -->

<button
    type="button"
    class="chatbot-launcher"
    id="chatbotLauncher"
    aria-label="Open MindGuide"
>

    <span class="chatbot-pulse"></span>


    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
    >

        <path
            d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5
               H8l-4 2 1.2-4A7.5 7.5 0 1 1
               20 11.5Z"
        />

        <path d="M8 11.5h.01"/>
        <path d="M12 11.5h.01"/>
        <path d="M16 11.5h.01"/>

    </svg>

</button>


<!-- =========================================================
     LOGIN PROMPT
========================================================= -->

<div
    class="login-overlay"
    id="loginOverlay"
>

    <div
        class="login-modal"
        role="dialog"
        aria-modal="true"
    >

        <div class="modal-icon">

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.6"
                stroke-linecap="round"
                stroke-linejoin="round"
            >

                <path
                    d="M12 21s-7-4.35-9.5-9.1
                       C.8 8.55 2.55 5
                       6.2 5c2.05 0 3.55 1.12
                       4.55 2.5
                       C11.75 6.12 13.25 5
                       15.3 5
                       c3.65 0 5.4 3.55
                       3.7 6.9
                       C19 16.65 12 21 12 21Z"
                />

            </svg>

        </div>


        <h3>
            MindGuide is here.
        </h3>


        <p>
            We'd love to let you have a private conversation
            with MindGuide. You'll just need a Haven account
            first — it helps us keep your conversation secure
            and connected to your experience.
        </p>


        <div class="modal-actions">

            <a
                href="login.php?redirect=chatbot.php"
                class="btn btn-primary"
            >
                Sign in to MindGuide
            </a>


            <a
                href="register.php"
                class="btn btn-secondary"
            >
                Create a Haven account
            </a>

        </div>


        <button
            type="button"
            class="modal-close"
            id="closeLoginModal"
        >
            Maybe later
        </button>

    </div>

</div>


<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {

        /*
        |--------------------------------------------------------------------------
        | GSAP INTRO
        |--------------------------------------------------------------------------
        */

        if (
            typeof gsap !== "undefined"
        ) {

            const intro =
                gsap.timeline({
                    defaults: {
                        ease: "power3.out"
                    }
                });


            intro

                .from(
                    ".navbar",
                    {
                        y: -25,
                        opacity: 0,
                        duration: .7
                    }
                )

                .from(
                    ".hero-copy > *",
                    {
                        y: 25,
                        opacity: 0,
                        duration: .65,
                        stagger: .08
                    },
                    "-=.3"
                )

                .from(
                    ".haven-card",
                    {
                        scale: .88,
                        opacity: 0,
                        rotation: -3,
                        duration: 1
                    },
                    "-=.6"
                )

                .from(
                    ".floating-note",
                    {
                        x: 25,
                        opacity: 0,
                        duration: .5
                    },
                    "-=.5"
                );


            /*
            |--------------------------------------------------------------------------
            | Breathing animation
            |--------------------------------------------------------------------------
            */

            gsap.to(
                ".breathing-dot",
                {
                    scale: 1.35,
                    opacity: .55,
                    duration: 2.4,
                    repeat: -1,
                    yoyo: true,
                    ease: "sine.inOut"
                }
            );


            gsap.to(
                ".haven-card",
                {
                    y: -8,
                    duration: 4,
                    repeat: -1,
                    yoyo: true,
                    ease: "sine.inOut"
                }
            );


            /*
            |--------------------------------------------------------------------------
            | Scroll reveal
            |--------------------------------------------------------------------------
            */

            const revealItems =
                document.querySelectorAll(
                    ".stat, .post, .guidance, .article, .ai-card"
                );


            if (
                "IntersectionObserver"
                in window
            ) {

                const observer =
                    new IntersectionObserver(
                        entries => {

                            entries.forEach(
                                entry => {

                                    if (
                                        entry.isIntersecting
                                    ) {

                                        gsap.fromTo(
                                            entry.target,
                                            {
                                                y: 25,
                                                opacity: 0
                                            },
                                            {
                                                y: 0,
                                                opacity: 1,
                                                duration: .65,
                                                ease:
                                                    "power3.out"
                                            }
                                        );

                                        observer.unobserve(
                                            entry.target
                                        );
                                    }

                                }
                            );

                        },
                        {
                            threshold: .12
                        }
                    );


                revealItems.forEach(
                    item => observer.observe(item)
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Statistics count
            |--------------------------------------------------------------------------
            */

            document
                .querySelectorAll(
                    "[data-count]"
                )
                .forEach(
                    counter => {

                        const target =
                            Number(
                                counter.dataset.count
                            );


                        if (
                            !Number.isFinite(target)
                            ||
                            target <= 0
                        ) {

                            counter.textContent = "0";

                            return;
                        }


                        const state = {
                            value: 0
                        };


                        gsap.to(
                            state,
                            {
                                value: target,
                                duration: 1.6,
                                ease: "power2.out",

                                onUpdate: () => {

                                    counter.textContent =
                                        Math.floor(
                                            state.value
                                        ).toLocaleString();

                                }
                            }
                        );

                    }
                );

        }


        /*
        |--------------------------------------------------------------------------
        | CHATBOT
        |--------------------------------------------------------------------------
        */

        const chatbotLauncher =
            document.getElementById(
                "chatbotLauncher"
            );


        const loginOverlay =
            document.getElementById(
                "loginOverlay"
            );


        const closeLoginModal =
            document.getElementById(
                "closeLoginModal"
            );


        chatbotLauncher.addEventListener(
            "click",
            function () {

                const loggedIn =
                    <?= $isLoggedIn ? 'true' : 'false' ?>;


                if (loggedIn) {

                    window.location.href =
                        "chatbot.php";

                    return;
                }


                loginOverlay.classList.add(
                    "active"
                );


                if (
                    typeof gsap !== "undefined"
                ) {

                    gsap.fromTo(
                        ".login-modal",
                        {
                            scale: .92,
                            y: 20,
                            opacity: 0
                        },
                        {
                            scale: 1,
                            y: 0,
                            opacity: 1,
                            duration: .45,
                            ease: "power3.out"
                        }
                    );

                }

            }
        );


        closeLoginModal.addEventListener(
            "click",
            function () {

                loginOverlay.classList.remove(
                    "active"
                );

            }
        );


        loginOverlay.addEventListener(
            "click",
            function (event) {

                if (
                    event.target ===
                    loginOverlay
                ) {

                    loginOverlay.classList.remove(
                        "active"
                    );

                }

            }
        );


        document.addEventListener(
            "keydown",
            function (event) {

                if (
                    event.key === "Escape"
                ) {

                    loginOverlay.classList.remove(
                        "active"
                    );

                }

            }
        );

    }
);

</script>

<?php if (isset($_SESSION['user_id'])): $__uid = $_SESSION['user_id']; ?>
<nav class="mobile-bottom-nav">
    <a href="index.php" class="active"><i class="bi bi-house-fill"></i>Home</a>
    <a href="community.php"><i class="bi bi-globe2"></i>Community</a>
    <a href="create-post.php"><i class="bi bi-plus-circle-fill"></i>Post</a>
    <a href="chatbot.php"><i class="bi bi-robot"></i>AI</a>
    <a href="dashboard.php" style="position:relative;">
        <?php $__unread = function_exists('getUnreadNotifications') ? getUnreadNotifications($__uid, $pdo) : 0; if ($__unread > 0): ?><span class="mbn-badge"><?= $__unread > 9 ? '9+' : $__unread ?></span><?php endif; ?><i class="bi bi-person"></i>Me</a>
</nav>
<?php else: ?>
<nav class="mobile-bottom-nav">
    <a href="index.php" class="active"><i class="bi bi-house-fill"></i>Home</a>
    <a href="community.php"><i class="bi bi-globe2"></i>Community</a>
    <a href="articles.php"><i class="bi bi-book"></i>Resources</a>
    <a href="login.php"><i class="bi bi-box-arrow-in-right"></i>Login</a>
</nav>
<?php endif; ?>
<!-- Rate Haven modal -->
<div class="rate-modal-backdrop" id="rateHavenModal">
    <div class="rate-modal-box">
        <h3>Rate Haven</h3>
        <?php if ($isLoggedIn): ?>
        <p style="color:var(--muted);font-size:.85rem;margin-bottom:0;">Tell others what being here has been like for you.</p>
        <div class="rate-modal-stars" id="rateHavenStars">
            <span data-v="1">★</span><span data-v="2">★</span><span data-v="3">★</span><span data-v="4">★</span><span data-v="5">★</span>
        </div>
        <textarea id="rateHavenReview" rows="4" placeholder="What has Haven meant to you?" maxlength="500"></textarea>
        <div id="rateHavenMsg"></div>
        <div class="rate-modal-actions">
            <button class="rate-modal-cancel" onclick="document.getElementById('rateHavenModal').classList.remove('open')">Cancel</button>
            <button class="btn-primary" style="border:none;" onclick="submitHavenRating()">Submit</button>
        </div>
        <?php else: ?>
        <p style="color:var(--muted);font-size:.9rem;margin:10px 0 18px;">Please log in to leave a review — this keeps ratings genuine and tied to real Haven members.</p>
        <div class="rate-modal-actions">
            <button class="rate-modal-cancel" onclick="document.getElementById('rateHavenModal').classList.remove('open')">Cancel</button>
            <a href="login.php" class="btn-primary" style="border:none;text-align:center;text-decoration:none;">Log in</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
let havenRatingValue = 0;
document.querySelectorAll('#rateHavenStars span').forEach(s => {
    s.addEventListener('click', () => {
        havenRatingValue = parseInt(s.dataset.v);
        document.querySelectorAll('#rateHavenStars span').forEach(x => x.classList.toggle('on', parseInt(x.dataset.v) <= havenRatingValue));
    });
});
async function submitHavenRating() {
    const box = document.getElementById('rateHavenMsg');
    const review = document.getElementById('rateHavenReview').value.trim();
    if (!havenRatingValue) { box.innerHTML = '<p style="color:#c96a63;font-size:.8rem;">Please select a star rating.</p>'; return; }
    if (!review) { box.innerHTML = '<p style="color:#c96a63;font-size:.8rem;">Please write a short review.</p>'; return; }
    const fd = new FormData();
    fd.append('action', 'rate_haven');
    fd.append('rating', havenRatingValue);
    fd.append('review', review);
    try {
        const r = await fetch('index.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            box.innerHTML = '<p style="color:#5e7564;font-size:.8rem;">Thank you for sharing! ✅</p>';
            setTimeout(() => location.reload(), 900);
        } else {
            box.innerHTML = '<p style="color:#c96a63;font-size:.8rem;">' + (d.error || 'Something went wrong.') + '</p>';
        }
    } catch (e) {
        box.innerHTML = '<p style="color:#c96a63;font-size:.8rem;">Network error. Please try again.</p>';
    }
}
</script>
</body>
</html>