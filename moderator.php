<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================================
// moderator.php – Complete Moderator Dashboard
// ============================================================
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// Check if user is moderator or admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$role = $stmt->fetchColumn();
if ($role !== 'moderator' && $role !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$user = getCurrentUser();
$anon_name = getAnonymousName($user_id, $pdo);
$unread_notifs = getUnreadNotifications($user_id, $pdo);

// ============================================================
// AJAX Handlers
// ============================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    ob_clean();
    
    $action = $_GET['action'] ?? '';
    $response = ['success' => false, 'error' => 'Invalid action'];

    try {
        // ---- Get queue ----
        if ($action === 'get_queue') {
            $filter = $_GET['filter'] ?? 'all';
            $sql = "SELECT 
                        r.*,
                        p.title as post_title,
                        p.content as post_content,
                        p.mood as post_mood,
                        p.created_at as post_created,
                        u.anonymous_name as user_name,
                        u.id as user_id,
                        a.emotion,
                        a.risk_score,
                        a.ai_reply,
                        a.moderation_decision,
                        (SELECT COUNT(*) FROM reports WHERE post_id = r.post_id) as report_count
                    FROM reports r
                    JOIN posts p ON r.post_id = p.id
                    JOIN users u ON p.user_id = u.id
                    LEFT JOIN ai_analysis a ON p.id = a.post_id
                    WHERE r.status = 'pending'";
            if ($filter === 'critical') $sql .= " AND r.priority = 'high'";
            else if ($filter === 'high') $sql .= " AND r.priority = 'high'";
            else if ($filter === 'medium') $sql .= " AND r.priority = 'medium'";
            else if ($filter === 'spam') $sql .= " AND r.reason = 'spam'";
            else if ($filter === 'harassment') $sql .= " AND r.reason = 'harassment'";
            else if ($filter === 'bullying') $sql .= " AND r.reason = 'bullying'";
            else if ($filter === 'self_harm') $sql .= " AND r.reason = 'self_harm'";
            $sql .= " ORDER BY FIELD(r.priority, 'high', 'medium', 'low'), r.created_at ASC LIMIT 30";
            $stmt = $pdo->query($sql);
            $items = $stmt->fetchAll();
            $response = ['success' => true, 'items' => $items];
        }

        // ---- Get AI flags ----
        elseif ($action === 'get_ai_flags') {
            $stmt = $pdo->query("
                SELECT a.*, p.title, p.content, p.user_id, u.anonymous_name,
                       p.created_at as post_created
                FROM ai_analysis a
                JOIN posts p ON a.post_id = p.id
                JOIN users u ON p.user_id = u.id
                WHERE a.risk_score >= 50 AND a.moderation_decision IN ('flag', 'escalate')
                ORDER BY a.risk_score DESC LIMIT 20
            ");
            $flags = $stmt->fetchAll();
            $response = ['success' => true, 'flags' => $flags];
        }

        // ---- Get user reports ----
        elseif ($action === 'get_reports') {
            $stmt = $pdo->query("
                SELECT r.*, u.anonymous_name as reporter_name,
                       p.title as post_title, p.content as post_content,
                       p.user_id as post_author_id,
                       u2.anonymous_name as post_author_name
                FROM reports r
                JOIN users u ON r.reporter_id = u.id
                JOIN posts p ON r.post_id = p.id
                JOIN users u2 ON p.user_id = u2.id
                WHERE r.status = 'pending'
                ORDER BY r.created_at DESC LIMIT 20
            ");
            $reports = $stmt->fetchAll();
            $response = ['success' => true, 'reports' => $reports];
        }

        // ---- Handle report ----
        elseif ($action === 'handle_report') {
            $report_id = intval($_POST['report_id']);
            $action_taken = $_POST['action_taken'];
            $note = trim($_POST['note'] ?? '');
            $post_id = intval($_POST['post_id']);

            // Get report details
            $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
            $stmt->execute([$report_id]);
            $report = $stmt->fetch();
            if (!$report) { throw new Exception('Report not found'); }

            // Update report
            $stmt = $pdo->prepare("UPDATE reports SET status = 'resolved', moderator_id = ?, action_taken = ?, resolution_notes = ?, resolved_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id, $action_taken, $note, $report_id]);

            // Update post if needed
            if ($action_taken === 'hide') {
                $stmt = $pdo->prepare("UPDATE posts SET status = 'flagged', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$post_id]);
            } elseif ($action_taken === 'delete') {
                $stmt = $pdo->prepare("UPDATE posts SET status = 'deleted', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$post_id]);
            } elseif ($action_taken === 'approve') {
                $stmt = $pdo->prepare("UPDATE posts SET status = 'published', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$post_id]);
            }

            // Create notification for post author if warning or ban
            if ($action_taken === 'warn') {
                $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
                $stmt->execute([$post_id]);
                $author_id = $stmt->fetchColumn();
                if ($author_id) {
                    createNotification($author_id, 'warning', 'Your post has been flagged and you received a warning. Please review our community guidelines.', 'dashboard.php', $pdo);
                }
            }

            // Log moderation action
            $stmt = $pdo->prepare("INSERT INTO moderation_logs (moderator_id, action, target_type, target_id, details, created_at) VALUES (?, ?, 'post', ?, ?, NOW())");
            $stmt->execute([$user_id, $action_taken, $post_id, json_encode(['report_id' => $report_id, 'note' => $note])]);

            // Assign volunteer if needed
            if ($action_taken === 'assign_volunteer' && isset($_POST['volunteer_id'])) {
                $volunteer_id = intval($_POST['volunteer_id']);
                $stmt = $pdo->prepare("INSERT INTO consultation_requests (user_id, volunteer_id, post_id, message, priority, status, created_at) VALUES (?, ?, ?, 'Moderator assigned volunteer', 'high', 'queued', NOW())");
                $stmt->execute([$report['reporter_id'] ?? 0, $volunteer_id, $post_id]);
            }

            $response = ['success' => true];
        }

        // ---- Get stats ----
        elseif ($action === 'get_stats') {
            $pending_reports = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
            $ai_flags = $pdo->query("SELECT COUNT(*) FROM ai_analysis WHERE risk_score >= 50 AND moderation_decision IN ('flag', 'escalate')")->fetchColumn();
            $critical_cases = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending' AND priority = 'high'")->fetchColumn();
            $waiting_volunteers = $pdo->query("SELECT COUNT(*) FROM consultation_requests WHERE status = 'queued'")->fetchColumn();
            $users_online = $pdo->query("SELECT COUNT(*) FROM users WHERE last_login > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
            $moderators_online = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'moderator' AND last_login > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
            $resolved_today = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'resolved' AND DATE(resolved_at) = CURDATE()")->fetchColumn();
            $total_reports = $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
            $avg_response = $pdo->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) as avg FROM reports WHERE status = 'resolved' AND resolved_at IS NOT NULL")->fetchColumn() ?: 0;
            
            // Community health (calculated from various metrics)
            $health = 85;
            if ($pending_reports > 20) $health -= 5;
            if ($ai_flags > 15) $health -= 5;
            if ($critical_cases > 5) $health -= 5;
            $health = max(40, min(98, $health));

            $response = ['success' => true, 'stats' => [
                'pending_reports' => $pending_reports,
                'ai_flags' => $ai_flags,
                'critical_cases' => $critical_cases,
                'waiting_volunteers' => $waiting_volunteers,
                'users_online' => $users_online,
                'moderators_online' => $moderators_online,
                'resolved_today' => $resolved_today,
                'total_reports' => $total_reports,
                'avg_response' => round($avg_response, 1),
                'health' => $health
            ]];
        }

        // ---- Get user history ----
        elseif ($action === 'get_user_history') {
            $user_id_lookup = intval($_GET['user_id']);
            // Get reports
            $stmt = $pdo->prepare("SELECT r.*, p.title as post_title, m.anonymous_name as moderator_name 
                                   FROM reports r 
                                   LEFT JOIN posts p ON r.post_id = p.id 
                                   LEFT JOIN users m ON r.moderator_id = m.id 
                                   WHERE r.reporter_id = ? OR r.target_id = ? 
                                   ORDER BY r.created_at DESC LIMIT 20");
            $stmt->execute([$user_id_lookup, $user_id_lookup]);
            $reports = $stmt->fetchAll();

            // Get warnings
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND type = 'warning' ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$user_id_lookup]);
            $warnings = $stmt->fetchAll();

            // Get consultations
            $stmt = $pdo->prepare("SELECT * FROM consultation_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$user_id_lookup]);
            $consultations = $stmt->fetchAll();

            // Get posts
            $stmt = $pdo->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$user_id_lookup]);
            $posts = $stmt->fetchAll();

            // Get AI analysis
            $stmt = $pdo->prepare("SELECT * FROM ai_analysis WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([$user_id_lookup]);
            $ai_history = $stmt->fetchAll();

            $response = ['success' => true, 'history' => [
                'reports' => $reports,
                'warnings' => $warnings,
                'consultations' => $consultations,
                'posts' => $posts,
                'ai_history' => $ai_history
            ]];
        }

        // ---- Get mod logs ----
        elseif ($action === 'get_logs') {
            $stmt = $pdo->query("
                SELECT l.*, u.anonymous_name as moderator_name 
                FROM moderation_logs l 
                JOIN users u ON l.moderator_id = u.id 
                ORDER BY l.created_at DESC LIMIT 50
            ");
            $logs = $stmt->fetchAll();
            $response = ['success' => true, 'logs' => $logs];
        }

        // ---- Get available volunteers ----
        elseif ($action === 'get_volunteers') {
            $stmt = $pdo->query("SELECT id, anonymous_name FROM users WHERE role = 'volunteer' AND is_active = 1 ORDER BY anonymous_name ASC");
            $volunteers = $stmt->fetchAll();
            $response = ['success' => true, 'volunteers' => $volunteers];
        }

        // ---- Get live activity ----
        elseif ($action === 'get_activity') {
            $stmt = $pdo->query("
                (SELECT 'report' as type, r.id, r.created_at, u.anonymous_name as user_name, r.reason 
                 FROM reports r JOIN users u ON r.reporter_id = u.id 
                 WHERE r.status = 'pending' ORDER BY r.created_at DESC LIMIT 5)
                UNION ALL
                (SELECT 'ai_flag' as type, a.id, a.created_at, u.anonymous_name as user_name, a.emotion 
                 FROM ai_analysis a JOIN posts p ON a.post_id = p.id JOIN users u ON p.user_id = u.id 
                 WHERE a.risk_score >= 50 ORDER BY a.created_at DESC LIMIT 5)
                UNION ALL
                (SELECT 'mod_action' as type, l.id, l.created_at, u.anonymous_name as user_name, l.action 
                 FROM moderation_logs l JOIN users u ON l.moderator_id = u.id 
                 ORDER BY l.created_at DESC LIMIT 5)
                ORDER BY created_at DESC LIMIT 15
            ");
            $activities = $stmt->fetchAll();
            $response = ['success' => true, 'activities' => $activities];
        }

        // ---- Search ----
        elseif ($action === 'search') {
            $q = '%' . trim($_GET['q']) . '%';
            $results = [];
            // Posts
            $stmt = $pdo->prepare("SELECT id, title, content, 'post' as type FROM posts WHERE title LIKE ? OR content LIKE ? AND status != 'deleted' LIMIT 10");
            $stmt->execute([$q, $q]);
            $results['posts'] = $stmt->fetchAll();
            // Users
            $stmt = $pdo->prepare("SELECT id, username, anonymous_name, 'user' as type FROM users WHERE username LIKE ? OR anonymous_name LIKE ? LIMIT 10");
            $stmt->execute([$q, $q]);
            $results['users'] = $stmt->fetchAll();
            // Reports
            $stmt = $pdo->prepare("SELECT id, reason, 'report' as type FROM reports WHERE reason LIKE ? LIMIT 10");
            $stmt->execute([$q]);
            $results['reports'] = $stmt->fetchAll();
            $response = ['success' => true, 'results' => $results];
        }

    } catch (Exception $e) {
        $response = ['success' => false, 'error' => $e->getMessage()];
        error_log("Moderator AJAX Error: " . $e->getMessage());
    }

    echo json_encode($response);
    exit;
}

// ============================================================
// Page Data
// ============================================================
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// Get stats for initial page load
$pending_reports = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
$ai_flags = $pdo->query("SELECT COUNT(*) FROM ai_analysis WHERE risk_score >= 50 AND moderation_decision IN ('flag', 'escalate')")->fetchColumn();
$critical_cases = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending' AND priority = 'high'")->fetchColumn();
$waiting_volunteers = $pdo->query("SELECT COUNT(*) FROM consultation_requests WHERE status = 'queued'")->fetchColumn();
$users_online = $pdo->query("SELECT COUNT(*) FROM users WHERE last_login > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
$moderators_online = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'moderator' AND last_login > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();

// Queue items for initial load
$queue_items = $pdo->query("
    SELECT r.*, p.title as post_title, p.content as post_content, p.mood as post_mood, 
           u.anonymous_name as user_name, u.id as user_id,
           a.emotion, a.risk_score, a.ai_reply,
           (SELECT COUNT(*) FROM reports WHERE post_id = r.post_id) as report_count
    FROM reports r
    JOIN posts p ON r.post_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN ai_analysis a ON p.id = a.post_id
    WHERE r.status = 'pending'
    ORDER BY FIELD(r.priority, 'high', 'medium', 'low'), r.created_at ASC LIMIT 20
")->fetchAll();

// Get AI flags
$ai_flags_list = $pdo->query("
    SELECT a.*, p.title, p.content, p.user_id, u.anonymous_name, p.created_at as post_created
    FROM ai_analysis a
    JOIN posts p ON a.post_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.risk_score >= 50 AND a.moderation_decision IN ('flag', 'escalate')
    ORDER BY a.risk_score DESC LIMIT 10
")->fetchAll();

// Get recent reports
$reports_list = $pdo->query("
    SELECT r.*, u.anonymous_name as reporter_name, p.title as post_title,
           u2.anonymous_name as post_author_name
    FROM reports r
    JOIN users u ON r.reporter_id = u.id
    JOIN posts p ON r.post_id = p.id
    JOIN users u2 ON p.user_id = u2.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC LIMIT 10
")->fetchAll();

// Mod logs
$mod_logs = $pdo->query("
    SELECT l.*, u.anonymous_name as moderator_name 
    FROM moderation_logs l 
    JOIN users u ON l.moderator_id = u.id 
    ORDER BY l.created_at DESC LIMIT 20
")->fetchAll();

// Community health trend (mock data for chart)
$health_data = [78, 82, 79, 85, 88, 91, 87, 92, 95, 98];

// Random tip for moderators
$tips = [
    "Always review the full context before taking action.",
    "Use warnings as educational moments when possible.",
    "Escalate to admin when patterns of abuse emerge.",
    "Document your decisions for transparency.",
    "Take breaks – moderation can be emotionally taxing.",
    "Trust your instincts but verify with evidence.",
    "Be consistent across similar cases."
];
$tip = $tips[array_rand($tips)];

// Get volunteers for assignment dropdown
$volunteers = $pdo->query("SELECT id, anonymous_name FROM users WHERE role = 'volunteer' AND is_active = 1 ORDER BY anonymous_name ASC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator Dashboard – Haven</title>
<link rel="icon" href="logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Poppins:wght@300..700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ===== GLOBAL ===== */
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f7f4ed;
            color: #26332b;
            overflow-x: hidden;
        }
        h1,h2,h3,h4,h5,h6 { font-family: 'Poppins', sans-serif; }

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

        .glass {
            background: rgba(255,255,255,0.86);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(94,117,100,0.08);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(64,77,67,0.3);
            transition: all 0.3s ease;
        }
        .glass:hover {
            background: rgba(255,255,255,0.8);
            border-color: rgba(94,117,100,0.12);
        }

        /* ===== NAV ===== */
        .mod-nav {
            position: fixed;
            top: 0; left: 0;
            width: 100%;
            z-index: 1050;
            background: rgba(255,253,248,0.92);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid rgba(94,117,100,0.1);
            padding: 0.5rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .mod-nav .brand { font-weight: 700; font-size: 1.2rem; color: #5e7564; }
        .mod-nav .brand i { margin-right: 8px; }
        .mod-nav .nav-right { display: flex; align-items: center; gap: 1rem; }
        .mod-nav .badge-online { background: #7fa383; color: #26332b; }

        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed;
            top: 70px;
            left: 0;
            width: 240px;
            height: calc(100vh - 70px);
            overflow-y: auto;
            padding: 1rem 0.5rem;
            background: rgba(255,253,248,0.95);
            border-right: 1px solid rgba(94,117,100,0.1);
            z-index: 1040;
            transition: transform 0.3s ease;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(94,117,100,0.3); border-radius: 10px; }
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.6rem 1rem;
            border-radius: 12px;
            color: rgba(38,51,43,0.65);
            transition: 0.2s;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .sidebar .nav-link i { width: 20px; text-align: center; }
        .sidebar .nav-link:hover { background: rgba(94,117,100,0.1); color: #26332b; }
        .sidebar .nav-link.active { background: rgba(94,117,100,0.15); color: #5e7564; }
        .sidebar .nav-link .badge {
            margin-left: auto;
            background: #c96a63;
            color: #fff;
            font-size: 0.7rem;
            padding: 0.1rem 0.5rem;
            border-radius: 10px;
        }
        .sidebar .divider { border-top: 1px solid rgba(94,117,100,0.06); margin: 0.5rem 0; }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 240px;
            padding: 80px 20px 20px;
            min-height: 100vh;
        }

        /* ===== STAT CARDS ===== */
        .stat-card { padding: 1.2rem; text-align: center; }
        .stat-card .number { font-size: 2.2rem; font-weight: 700; font-family: 'Poppins', sans-serif; }
        .stat-card .label { font-size: 0.8rem; color: rgba(38,51,43,0.6); margin-top: 0.2rem; }

        /* ===== QUEUE ITEMS ===== */
        .queue-item {
            padding: 1rem;
            margin-bottom: 0.8rem;
            border-left: 4px solid rgba(255,255,255,0.65);
            cursor: pointer;
            transition: 0.3s;
        }
        .queue-item:hover { transform: translateX(5px); background: rgba(255,255,255,0.65); }
        .queue-item.priority-critical { border-left-color: #c96a63; }
        .queue-item.priority-high { border-left-color: #d98a56; }
        .queue-item.priority-medium { border-left-color: #d9a441; }
        .queue-item.priority-low { border-left-color: #7fa383; }
        .queue-item .risk-badge { font-size: 0.8rem; padding: 0.1rem 0.6rem; border-radius: 12px; }
        .queue-item .risk-high { background: #c96a63; color: #26332b; }
        .queue-item .risk-medium { background: #d9a441; color: #26332b; }
        .queue-item .risk-low { background: #7fa383; color: #26332b; }

        /* ===== AI FLAG ITEM ===== */
        .ai-flag-item {
            padding: 1rem;
            margin-bottom: 0.8rem;
            background: rgba(201,106,99,0.06);
            border-left: 4px solid #c96a63;
        }
        .ai-flag-item .highlight {
            background: rgba(201,106,99,0.2);
            padding: 0.1rem 0.3rem;
            border-radius: 4px;
        }

        /* ===== MODAL CUSTOM ===== */
        .modal-content {
            background: #fffdf8;
            border: 1px solid rgba(94,117,100,0.08);
            border-radius: 20px;
        }
        .modal-header { border-bottom: 1px solid rgba(94,117,100,0.06); }
        .modal-footer { border-top: 1px solid rgba(94,117,100,0.06); }
        .form-control, .form-select {
            background: rgba(255,255,255,0.85);
            border: 1px solid rgba(94,117,100,0.08);
            color: #26332b;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(255,255,255,0.77);
            border-color: #5e7564;
            color: #26332b;
            box-shadow: 0 0 0 3px rgba(94,117,100,0.15);
        }
        .form-control::placeholder { color: rgba(38,51,43,0.45); }

        /* ===== ACTIVITY FEED ===== */
        .activity-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(94,117,100,0.04);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-item .time { font-size: 0.7rem; color: rgba(38,51,43,0.45); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
        @media (max-width: 576px) {
            .stat-card .number { font-size: 1.6rem; }
            .main-content { padding: 70px 10px 10px; }
        }
    </style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="mod-nav">
    <div>
        <button class="btn btn-link d-lg-none" id="sidebarToggle" style="color:#26332b;"><i class="bi bi-list fs-4"></i></button>
        <span class="brand"><img src="logo.png" alt="Haven" style="height:22px;width:22px;object-fit:cover;border-radius:6px;vertical-align:-4px;margin-right:6px;"><i class="bi bi-shield-check"></i> Haven ModPanel</span>
    </div>
    <div class="nav-right">
        <span class="badge bg-success">🟢 <?= $moderators_online ?> Online</span>
        <span class="badge bg-secondary d-none d-sm-block"><?= $anon_name ?></span>
        <button class="btn btn-outline-secondary btn-sm" id="darkToggle"><i class="bi bi-sun-fill"></i></button>
        <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>

<!-- ===== SIDEBAR ===== -->
<nav class="sidebar" id="sidebar">
    <div class="px-2">
        <div class="glass p-3 mb-3 text-center">
            <div style="font-size:2.5rem;">🛡️</div>
            <div class="fw-bold"><?= escape($anon_name) ?></div>
            <small class="text-muted">Moderator</small>
        </div>

        <ul class="nav flex-column">
            <li><a href="?tab=dashboard" class="nav-link <?= $tab=='dashboard'?'active':'' ?>"><i class="bi bi-house"></i> Dashboard</a></li>
            <li><a href="?tab=queue" class="nav-link <?= $tab=='queue'?'active':'' ?>"><i class="bi bi-list-ul"></i> Queue <span class="badge"><?= $pending_reports ?></span></a></li>
            <li><a href="?tab=ai-flags" class="nav-link <?= $tab=='ai-flags'?'active':'' ?>"><i class="bi bi-robot"></i> AI Flags <span class="badge"><?= $ai_flags ?></span></a></li>
            <li><a href="?tab=reports" class="nav-link <?= $tab=='reports'?'active':'' ?>"><i class="bi bi-flag"></i> User Reports</a></li>
            <li><a href="?tab=critical" class="nav-link <?= $tab=='critical'?'active':'' ?>"><i class="bi bi-exclamation-triangle text-danger"></i> Critical <span class="badge" style="background:#c96a63;"><?= $critical_cases ?></span></a></li>
            <li><a href="?tab=users" class="nav-link <?= $tab=='users'?'active':'' ?>"><i class="bi bi-people"></i> User Management</a></li>
            <li><a href="?tab=volunteers" class="nav-link <?= $tab=='volunteers'?'active':'' ?>"><i class="bi bi-person-heart"></i> Volunteers</a></li>
            <li><a href="?tab=logs" class="nav-link <?= $tab=='logs'?'active':'' ?>"><i class="bi bi-clock-history"></i> Mod Logs</a></li>
            <li><a href="?tab=analytics" class="nav-link <?= $tab=='analytics'?'active':'' ?>"><i class="bi bi-graph-up"></i> Analytics</a></li>
            <li class="divider"></li>
            <li><a href="search.php" class="nav-link"><i class="bi bi-search"></i> Search</a></li>
            <li><a href="settings.php" class="nav-link"><i class="bi bi-gear"></i> Settings</a></li>
        </ul>
    </div>
</nav>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content" id="mainContent">

    <?php if ($tab === 'dashboard'): ?>
        <!-- ===== DASHBOARD ===== -->
        <div class="row g-3 mb-3">
            <div class="col-md-3 col-6"><div class="glass stat-card"><div class="number counter" data-target="<?= $pending_reports ?>">0</div><div class="label">Pending Reports</div></div></div>
            <div class="col-md-3 col-6"><div class="glass stat-card"><div class="number counter" data-target="<?= $ai_flags ?>">0</div><div class="label">AI Flags</div></div></div>
            <div class="col-md-3 col-6"><div class="glass stat-card"><div class="number counter" data-target="<?= $critical_cases ?>">0</div><div class="label">Critical Cases</div></div></div>
            <div class="col-md-3 col-6"><div class="glass stat-card"><div class="number counter" data-target="<?= $waiting_volunteers ?>">0</div><div class="label">Waiting Volunteers</div></div></div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-4 col-6"><div class="glass stat-card"><div class="number counter" data-target="<?= $users_online ?>">0</div><div class="label">👥 Users Online</div></div></div>
            <div class="col-md-4 col-6"><div class="glass stat-card"><div class="number counter" data-target="<?= $moderators_online ?>">0</div><div class="label">🛡️ Moderators Online</div></div></div>
            <div class="col-md-4"><div class="glass stat-card"><div class="number">91%</div><div class="label">Community Health</div></div></div>
        </div>

        <!-- Tip -->
        <div class="glass p-3 mb-3" style="border-left:4px solid #5e7564;">
            <p class="mb-0"><i class="bi bi-lightbulb text-primary"></i> <strong>Tip:</strong> <?= $tip ?></p>
        </div>

        <!-- Recent Activity -->
        <div class="glass p-3 mb-3">
            <h6><i class="bi bi-clock-history"></i> Recent Activity</h6>
            <div id="activityFeed">
                <p class="text-muted small">Loading...</p>
            </div>
        </div>

        <!-- Mod Logs -->
        <div class="glass p-3">
            <h6><i class="bi bi-journal-text"></i> Recent Mod Actions</h6>
            <div class="table-responsive">
                <table class="table table-dark table-hover" style="font-size:0.85rem;">
                    <thead><tr><th>Time</th><th>Moderator</th><th>Action</th><th>Target</th></tr></thead>
                    <tbody>
                        <?php foreach ($mod_logs as $log): ?>
                            <tr>
                                <td><?= timeAgo($log['created_at']) ?></td>
                                <td><?= escape($log['moderator_name']) ?></td>
                                <td><span class="badge bg-<?= $log['action']=='delete'?'danger':($log['action']=='warn'?'warning':'secondary') ?>"><?= ucfirst($log['action']) ?></span></td>
                                <td><?= $log['target_type'] ?? 'post' ?> #<?= $log['target_id'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'queue' || $tab === 'critical'): ?>
        <!-- ===== MODERATION QUEUE ===== -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5><i class="bi bi-list-ul"></i> <?= $tab === 'critical' ? '🚨 Critical Cases' : 'Moderation Queue' ?></h5>
            <div class="btn-group">
                <button class="btn btn-sm btn-outline-secondary filter-btn active" data-filter="all">All</button>
                <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="critical">Critical</button>
                <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="high">High</button>
                <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="medium">Medium</button>
                <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="spam">Spam</button>
                <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="harassment">Harassment</button>
            </div>
        </div>

        <div id="queueContainer">
            <?php foreach ($queue_items as $item): ?>
                <div class="glass queue-item priority-<?= strtolower($item['priority']) ?>" data-id="<?= $item['id'] ?>" onclick="openReportModal(<?= $item['id'] ?>, <?= $item['post_id'] ?>)">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><strong><?= escape($item['user_name']) ?></strong> <span class="text-muted small"><?= timeAgo($item['post_created']) ?></span></span>
                        <span>
                            <span class="badge bg-<?= $item['priority']=='high'?'danger':($item['priority']=='medium'?'warning':'secondary') ?>"><?= ucfirst($item['priority']) ?></span>
                            <?php if ($item['risk_score']): ?>
                                <span class="risk-badge risk-<?= $item['risk_score'] >= 60 ? 'high' : ($item['risk_score'] >= 30 ? 'medium' : 'low') ?>"><?= $item['risk_score'] ?>%</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <p class="text-muted small mb-1"><i class="bi bi-flag"></i> <?= ucfirst($item['reason']) ?> (<?= $item['report_count'] ?? 1 ?> reports)</p>
                    <p class="small mb-0">"<?= substr(escape($item['post_content']), 0, 80) ?>..."</p>
                    <?php if ($item['emotion']): ?>
                        <span class="badge bg-secondary">Emotion: <?= ucfirst($item['emotion']) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (empty($queue_items)): ?>
                <div class="glass p-4 text-center"><p class="text-muted">🎉 No pending reports. Great job!</p></div>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'ai-flags'): ?>
        <!-- ===== AI FLAGS ===== -->
        <h5><i class="bi bi-robot"></i> AI Flagged Content</h5>
        <p class="text-muted small">Posts automatically flagged by MindShield AI for review.</p>
        <div id="aiFlagsContainer">
            <?php foreach ($ai_flags_list as $flag): ?>
                <div class="glass ai-flag-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><strong><?= escape($flag['anonymous_name']) ?></strong> <span class="text-muted small"><?= timeAgo($flag['post_created']) ?></span></span>
                        <span class="risk-badge risk-high"><?= $flag['risk_score'] ?>% Risk</span>
                    </div>
                    <p class="mt-2">"<?= substr(escape($flag['content']), 0, 150) ?>..."</p>
                    <div class="row g-2 mt-1">
                        <div class="col-md-4"><small><strong>Emotion:</strong> <?= ucfirst($flag['emotion'] ?? 'N/A') ?></small></div>
                        <div class="col-md-4"><small><strong>Decision:</strong> <span class="badge bg-warning"><?= ucfirst($flag['moderation_decision']) ?></span></small></div>
                        <div class="col-md-4"><small><strong>Category:</strong> <?= ucfirst($flag['category'] ?? 'N/A') ?></small></div>
                    </div>
                    <?php if ($flag['ai_reply']): ?>
                        <div class="mt-1 text-muted small"><i class="bi bi-robot"></i> AI: <?= substr(escape($flag['ai_reply']), 0, 80) ?>...</div>
                    <?php endif; ?>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-primary" onclick="openPostModal(<?= $flag['post_id'] ?>)">View Post</button>
                        <button class="btn btn-sm btn-outline-success" onclick="handleAIFlag(<?= $flag['id'] ?>, <?= $flag['post_id'] ?>, 'approve')">Approve</button>
                        <button class="btn btn-sm btn-outline-warning" onclick="handleAIFlag(<?= $flag['id'] ?>, <?= $flag['post_id'] ?>, 'hide')">Hide</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="handleAIFlag(<?= $flag['id'] ?>, <?= $flag['post_id'] ?>, 'escalate')">Escalate</button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($ai_flags_list)): ?>
                <div class="glass p-4 text-center"><p class="text-muted">✅ No AI flags pending.</p></div>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'reports'): ?>
        <!-- ===== USER REPORTS ===== -->
        <h5><i class="bi bi-flag"></i> User Reports</h5>
        <p class="text-muted small">Reports submitted by community members.</p>
        <div id="reportsContainer">
            <?php foreach ($reports_list as $r): ?>
                <div class="glass p-3 mb-2">
                    <div class="d-flex justify-content-between">
                        <span><strong>Post:</strong> <?= escape($r['post_title']) ?></span>
                        <span><span class="badge bg-<?= $r['priority']=='high'?'danger':'secondary' ?>"><?= ucfirst($r['priority']) ?></span></span>
                    </div>
                    <p class="small text-muted">Reported by <?= escape($r['reporter_name']) ?> for <?= escape($r['reason']) ?></p>
                    <p class="small">"<?= substr(escape($r['description'] ?? ''), 0, 80) ?>"</p>
                    <div>
                        <button class="btn btn-sm btn-primary" onclick="openReportModal(<?= $r['id'] ?>, <?= $r['post_id'] ?>)">Review</button>
                        <button class="btn btn-sm btn-outline-success" onclick="handleReport(<?= $r['id'] ?>, <?= $r['post_id'] ?>, 'approve')">Approve</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="handleReport(<?= $r['id'] ?>, <?= $r['post_id'] ?>, 'delete')">Delete</button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($reports_list)): ?>
                <div class="glass p-4 text-center"><p class="text-muted">📭 No reports.</p></div>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'volunteers'): ?>
        <!-- ===== VOLUNTEER MANAGEMENT ===== -->
        <h5><i class="bi bi-person-heart"></i> Volunteer Management</h5>
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="glass p-3 text-center"><h3 class="counter" data-target="<?= $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'volunteer'")->fetchColumn() ?>">0</h3><p class="text-muted small">Total Volunteers</p></div></div>
            <div class="col-md-3"><div class="glass p-3 text-center"><h3><?= $waiting_volunteers ?></h3><p class="text-muted small">Pending Requests</p></div></div>
            <div class="col-md-3"><div class="glass p-3 text-center"><h3><?= $pdo->query("SELECT COUNT(*) FROM consultation_requests WHERE status = 'active'")->fetchColumn() ?></h3><p class="text-muted small">Active Sessions</p></div></div>
            <div class="col-md-3"><div class="glass p-3 text-center"><h3><?= $pdo->query("SELECT COUNT(*) FROM consultation_requests WHERE status = 'closed' AND DATE(closed_at) = CURDATE()")->fetchColumn() ?></h3><p class="text-muted small">Completed Today</p></div></div>
        </div>
        <div class="glass p-3">
            <h6>Available Volunteers</h6>
            <div class="row">
                <?php foreach ($volunteers as $v): ?>
                    <div class="col-md-3 col-6">
                        <div class="glass p-2 text-center m-1">
                            <div style="font-size:1.5rem;">🧑‍⚕️</div>
                            <div><?= escape($v['anonymous_name']) ?></div>
                            <span class="badge bg-success">Online</span>
                            <button class="btn btn-sm btn-outline-primary mt-1" onclick="assignVolunteer(<?= $v['id'] ?>)">Assign</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php elseif ($tab === 'logs'): ?>
        <!-- ===== MOD LOGS ===== -->
        <h5><i class="bi bi-clock-history"></i> Moderation Logs</h5>
        <div class="glass p-3">
            <div class="table-responsive">
                <table class="table table-dark table-hover">
                    <thead><tr><th>Time</th><th>Moderator</th><th>Action</th><th>Target</th><th>Details</th></tr></thead>
                    <tbody id="logTable">
                        <?php foreach ($mod_logs as $log): ?>
                            <tr>
                                <td><?= timeAgo($log['created_at']) ?></td>
                                <td><?= escape($log['moderator_name']) ?></td>
                                <td><span class="badge bg-<?= $log['action']=='delete'?'danger':($log['action']=='warn'?'warning':'secondary') ?>"><?= ucfirst($log['action']) ?></span></td>
                                <td><?= $log['target_type'] ?? 'post' ?> #<?= $log['target_id'] ?></td>
                                <td class="text-muted small"><?= substr(escape($log['details'] ?? ''), 0, 50) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'analytics'): ?>
        <!-- ===== ANALYTICS ===== -->
        <h5><i class="bi bi-graph-up"></i> Analytics</h5>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="glass p-3">
                    <h6>Community Health Trend</h6>
                    <canvas id="healthChart" height="200"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="glass p-3">
                    <h6>Report Types</h6>
                    <canvas id="reportChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="glass p-3">
            <h6>Moderation Activity</h6>
            <canvas id="activityChart" height="150"></canvas>
        </div>

    <?php elseif ($tab === 'users'): ?>
        <!-- ===== USER MANAGEMENT ===== -->
        <h5><i class="bi bi-people"></i> User Management</h5>
        <div class="glass p-3">
            <div class="table-responsive">
                <table class="table table-dark table-hover">
                    <thead><tr><th>User</th><th>Role</th><th>Posts</th><th>Reports</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php
                        $users = $pdo->query("
                            SELECT u.*, 
                                   (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as post_count,
                                   (SELECT COUNT(*) FROM reports WHERE reporter_id = u.id OR target_id = u.id) as report_count
                            FROM users u 
                            ORDER BY u.id DESC LIMIT 30
                        ")->fetchAll();
                        foreach ($users as $u): ?>
                            <tr>
                                <td><?= escape($u['anonymous_name'] ?? $u['username']) ?></td>
                                <td><span class="badge bg-<?= $u['role']=='admin'?'danger':($u['role']=='moderator'?'warning':'secondary') ?>"><?= ucfirst($u['role']) ?></span></td>
                                <td><?= $u['post_count'] ?></td>
                                <td><?= $u['report_count'] ?></td>
                                <td><?= $u['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Banned</span>' ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewUser(<?= $u['id'] ?>)">View</button>
                                    <?php if ($u['is_active'] && $u['role'] !== 'admin'): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="banUser(<?= $u['id'] ?>)">Ban</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- ===== REPORT REVIEW MODAL ===== -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-flag text-warning"></i> Review Report</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reportModalBody">
                <div id="reportDetails">Loading...</div>
                <hr>
                <h6>AI Analysis</h6>
                <div id="aiAnalysisDisplay">Loading AI data...</div>
                <hr>
                <h6>Actions</h6>
                <div class="row g-2">
                    <div class="col-md-3"><button class="btn btn-success w-100" onclick="takeAction('approve')"><i class="bi bi-check"></i> Approve</button></div>
                    <div class="col-md-3"><button class="btn btn-warning w-100" onclick="takeAction('hide')"><i class="bi bi-eye-slash"></i> Hide</button></div>
                    <div class="col-md-3"><button class="btn btn-danger w-100" onclick="takeAction('delete')"><i class="bi bi-trash"></i> Delete</button></div>
                    <div class="col-md-3"><button class="btn btn-outline-danger w-100" onclick="takeAction('warn')"><i class="bi bi-exclamation-triangle"></i> Warn</button></div>
                </div>
                <div class="row g-2 mt-2">
                    <div class="col-md-6">
                        <select id="volunteerSelect" class="form-select">
                            <option value="">Assign Volunteer...</option>
                            <?php foreach ($volunteers as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= escape($v['anonymous_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><button class="btn btn-outline-primary w-100" onclick="takeAction('assign_volunteer')"><i class="bi bi-person-plus"></i> Assign Volunteer</button></div>
                </div>
                <div class="mt-2">
                    <label>Note (optional)</label>
                    <textarea id="modNote" class="form-control" rows="2" placeholder="Add a note about this action..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-primary" onclick="submitAction()"><i class="bi bi-check"></i> Apply Action</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== SCRIPTS ===== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script>
    // ============================================================
    // GLOBALS
    // ============================================================
    let currentReportId = 0;
    let currentPostId = 0;
    let selectedAction = '';
    let reportModal = new bootstrap.Modal(document.getElementById('reportModal'));

    // ============================================================
    // DARK MODE
    // ============================================================
    document.getElementById('darkToggle')?.addEventListener('click', function() {
        document.body.classList.toggle('light-mode');
        localStorage.setItem('modLightMode', document.body.classList.contains('light-mode'));
    });
    if (localStorage.getItem('modLightMode') === 'true') document.body.classList.add('light-mode');

    // ============================================================
    // SIDEBAR TOGGLE
    // ============================================================
    document.getElementById('sidebarToggle')?.addEventListener('click', function() {
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
    // COUNTER ANIMATION
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
    // OPEN REPORT MODAL
    // ============================================================
    function openReportModal(reportId, postId) {
        currentReportId = reportId;
        currentPostId = postId;
        selectedAction = '';
        document.getElementById('modNote').value = '';

        document.getElementById('reportDetails').innerHTML = '<p class="text-muted">Loading report...</p>';
        document.getElementById('aiAnalysisDisplay').innerHTML = '<p class="text-muted">Loading AI data...</p>';

        // Fetch report details
        fetch(`moderator.php?ajax=1&action=get_report&report_id=${reportId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.report) {
                    const r = data.report;
                    document.getElementById('reportDetails').innerHTML = `
                        <p><strong>Reported by:</strong> ${r.reporter_name} for <strong>${r.reason}</strong></p>
                        <p><strong>Priority:</strong> <span class="badge bg-${r.priority=='high'?'danger':'secondary'}">${r.priority}</span></p>
                        <p><strong>Description:</strong> ${r.description || 'No description'}</p>
                        <hr>
                        <p><strong>Post Content:</strong></p>
                        <div class="glass p-2" style="background:rgba(255,255,255,0.65);">
                            ${r.post_content}
                        </div>
                        <p class="mt-2 text-muted small">Posted by: ${r.post_author_name}</p>
                    `;
                }
            });

        // Fetch AI analysis
        fetch(`moderator.php?ajax=1&action=get_ai_analysis&post_id=${postId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.analysis) {
                    const a = data.analysis;
                    document.getElementById('aiAnalysisDisplay').innerHTML = `
                        <div class="glass p-2" style="background:rgba(201,106,99,0.06);">
                            <p><strong>Emotion:</strong> ${a.emotion || 'N/A'} (Confidence: ${a.confidence || 0}%)</p>
                            <p><strong>Risk Score:</strong> <span class="badge bg-${a.risk_score >= 60 ? 'danger' : 'warning'}">${a.risk_score}%</span></p>
                            <p><strong>Category:</strong> ${a.category || 'N/A'}</p>
                            ${a.ai_reply ? `<p><strong>AI Reply:</strong> ${a.ai_reply}</p>` : ''}
                            <p><strong>Decision:</strong> <span class="badge bg-${a.moderation_decision=='escalate'?'danger':'secondary'}">${a.moderation_decision || 'N/A'}</span></p>
                        </div>
                    `;
                } else {
                    document.getElementById('aiAnalysisDisplay').innerHTML = '<p class="text-muted">No AI analysis available.</p>';
                }
            });

        reportModal.show();
    }

    // ============================================================
    // TAKE ACTION
    // ============================================================
    function takeAction(action) {
        selectedAction = action;
        // Highlight selected button
        document.querySelectorAll('#reportModalBody .btn').forEach(b => b.style.opacity = '0.5');
        document.querySelectorAll(`#reportModalBody .btn[onclick*="${action}"]`).forEach(b => b.style.opacity = '1');
        document.querySelectorAll(`#reportModalBody .btn[onclick*="${action}"]`).forEach(b => b.style.border = '2px solid #5e7564');
    }

    // ============================================================
    // SUBMIT ACTION
    // ============================================================
    function submitAction() {
        if (!selectedAction) { showToast('Please select an action first', 'warning'); return; }

        const note = document.getElementById('modNote').value;
        const volunteerId = document.getElementById('volunteerSelect')?.value;

        const data = {
            report_id: currentReportId,
            post_id: currentPostId,
            action_taken: selectedAction,
            note: note
        };
        if (volunteerId) data.volunteer_id = volunteerId;

        fetch('moderator.php?ajax=1&action=handle_report', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: Object.entries(data).map(([k,v]) => `${k}=${encodeURIComponent(v)}`).join('&')
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(`Action "${selectedAction}" applied successfully!`, 'success');
                reportModal.hide();
                setTimeout(() => location.reload(), 500);
            } else {
                showToast(data.error || 'Failed', 'danger');
            }
        })
        .catch(() => showToast('Connection error', 'danger'));
    }

    // ============================================================
    // FILTER QUEUE
    // ============================================================
    document.querySelectorAll('.filter-btn')?.forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            fetch(`moderator.php?ajax=1&action=get_queue&filter=${filter}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('queueContainer');
                        if (data.items.length) {
                            container.innerHTML = data.items.map(item => `
                                <div class="glass queue-item priority-${item.priority.toLowerCase()}" onclick="openReportModal(${item.id}, ${item.post_id})">
                                    <div class="d-flex justify-content-between">
                                        <span><strong>${item.user_name}</strong> <span class="text-muted small">${item.post_created}</span></span>
                                        <span>
                                            <span class="badge bg-${item.priority=='high'?'danger':'secondary'}">${item.priority}</span>
                                            ${item.risk_score ? `<span class="risk-badge risk-${item.risk_score >= 60 ? 'high' : 'medium'}">${item.risk_score}%</span>` : ''}
                                        </span>
                                    </div>
                                    <p class="text-muted small"><i class="bi bi-flag"></i> ${item.reason}</p>
                                    <p class="small">"${item.post_content?.substring(0, 80)}..."</p>
                                </div>
                            `).join('');
                        } else {
                            container.innerHTML = '<div class="glass p-4 text-center"><p class="text-muted">🎉 No pending items.</p></div>';
                        }
                    }
                });
        });
    });

    // ============================================================
    // ACTIVITY FEED
    // ============================================================
    function loadActivity() {
        fetch('moderator.php?ajax=1&action=get_activity')
            .then(res => res.json())
            .then(data => {
                const feed = document.getElementById('activityFeed');
                if (data.success && data.activities.length) {
                    const icons = { report: '🚩', ai_flag: '🤖', mod_action: '🛡️' };
                    feed.innerHTML = data.activities.map(a => `
                        <div class="activity-item">
                            <span>${icons[a.type] || '📌'}</span>
                            <strong>${a.user_name || 'System'}</strong>
                            <span class="text-muted">${a.reason || a.action || a.emotion || ''}</span>
                            <span class="time float-end">${new Date(a.created_at).toLocaleTimeString()}</span>
                        </div>
                    `).join('');
                } else {
                    feed.innerHTML = '<p class="text-muted small">No recent activity.</p>';
                }
            });
    }
    loadActivity();
    setInterval(loadActivity, 10000);

    // ============================================================
    // CHARTS
    // ============================================================
    <?php if ($tab === 'analytics'): ?>
    // Health chart
    new Chart(document.getElementById('healthChart'), {
        type: 'line',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6'],
            datasets: [{
                label: 'Community Health (%)',
                data: <?= json_encode($health_data) ?>,
                borderColor: '#7fa383',
                backgroundColor: 'rgba(127,163,131,0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#7c857e' } } },
            scales: {
                x: { ticks: { color: '#7c857e' } },
                y: { ticks: { color: '#7c857e' }, min: 40, max: 100 }
            }
        }
    });

    // Report chart
    new Chart(document.getElementById('reportChart'), {
        type: 'doughnut',
        data: {
            labels: ['Harassment', 'Spam', 'Bullying', 'Self Harm', 'Other'],
            datasets: [{
                data: [12, 8, 5, 3, 4],
                backgroundColor: ['#c96a63', '#d9a441', '#d98a56', '#c96a63', '#7c857e']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#7c857e' } } }
        }
    });

    // Activity chart
    new Chart(document.getElementById('activityChart'), {
        type: 'bar',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [
                { label: 'Actions', data: [12, 18, 15, 22, 28, 14, 8], backgroundColor: '#5e7564' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#7c857e' } } },
            scales: {
                x: { ticks: { color: '#7c857e' } },
                y: { ticks: { color: '#7c857e' }, beginAtZero: true }
            }
        }
    });
    <?php endif; ?>

    // ============================================================
    // KEYBOARD SHORTCUTS
    // ============================================================
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            document.querySelector('input[type="search"]')?.focus();
        }
        if (e.key === 'Escape') {
            // Close modal if open
            if (reportModal._isShown) reportModal.hide();
        }
    });

    console.log('🛡️ Moderator Dashboard loaded.');
</script>
</body>
</html>