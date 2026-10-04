<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ============================================================
// volunteer.php – Complete Volunteer Portal
// ============================================================
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// Check if user is a volunteer or admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$role = $stmt->fetchColumn();
if ($role !== 'volunteer' && $role !== 'admin') {
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
    $action = $_GET['action'] ?? '';

    // ---- Get queue (cases waiting for volunteer) ----
    if ($action === 'get_queue') {
        $filter = $_GET['filter'] ?? 'all';
        $sql = "SELECT cr.*, u.anonymous_name, u.id as user_id,
                (SELECT COUNT(*) FROM consultation_messages WHERE request_id = cr.id) as message_count,
                (SELECT ai_reply FROM ai_analysis WHERE post_id = cr.post_id ORDER BY created_at DESC LIMIT 1) as ai_summary,
                (SELECT emotion FROM ai_analysis WHERE post_id = cr.post_id ORDER BY created_at DESC LIMIT 1) as emotion,
                (SELECT risk_score FROM ai_analysis WHERE post_id = cr.post_id ORDER BY created_at DESC LIMIT 1) as risk_score
                FROM consultation_requests cr
                JOIN users u ON cr.user_id = u.id
                WHERE cr.status = 'queued'";
        if ($filter === 'high') $sql .= " AND cr.priority = 'high'";
        else if ($filter === 'medium') $sql .= " AND cr.priority = 'medium'";
        else if ($filter === 'low') $sql .= " AND cr.priority = 'low'";
        $sql .= " ORDER BY FIELD(cr.priority, 'high', 'medium', 'low'), cr.created_at ASC LIMIT 20";
        $stmt = $pdo->query($sql);
        $cases = $stmt->fetchAll();
        echo json_encode(['success' => true, 'cases' => $cases]);
        exit;
    }

    // ---- Get assigned cases ----
    if ($action === 'get_assigned') {
        $sql = "SELECT cr.*, u.anonymous_name, u.id as user_id,
                (SELECT COUNT(*) FROM consultation_messages WHERE request_id = cr.id) as message_count,
                (SELECT ai_reply FROM ai_analysis WHERE post_id = cr.post_id ORDER BY created_at DESC LIMIT 1) as ai_summary,
                (SELECT emotion FROM ai_analysis WHERE post_id = cr.post_id ORDER BY created_at DESC LIMIT 1) as emotion
                FROM consultation_requests cr
                JOIN users u ON cr.user_id = u.id
                WHERE cr.volunteer_id = ? AND cr.status != 'closed'
                ORDER BY cr.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $cases = $stmt->fetchAll();
        echo json_encode(['success' => true, 'cases' => $cases]);
        exit;
    }

    // ---- Accept a case ----
    if ($action === 'accept_case') {
        $case_id = intval($_POST['case_id']);
        $stmt = $pdo->prepare("UPDATE consultation_requests SET volunteer_id = ?, status = 'active', assigned_at = NOW() WHERE id = ? AND status = 'queued'");
        $stmt->execute([$user_id, $case_id]);
        if ($stmt->rowCount() > 0) {
            // Notify user
            $stmt = $pdo->prepare("SELECT user_id FROM consultation_requests WHERE id = ?");
            $stmt->execute([$case_id]);
            $userId = $stmt->fetchColumn();
            createNotification($userId, 'volunteer_assigned', 'A volunteer has been assigned to your case. They will reach out shortly.', 'consultation.php?id='.$case_id, $pdo);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Case already taken']);
        }
        exit;
    }

    // ---- Get case details ----
    if ($action === 'case_details') {
        $case_id = intval($_GET['case_id']);
        $stmt = $pdo->prepare("SELECT cr.*, u.anonymous_name, u.id as user_id,
                               u.birth_date, u.occupation, u.country, u.language,
                               p.content as post_content, p.title as post_title, p.mood as post_mood, p.created_at as post_created
                               FROM consultation_requests cr
                               JOIN users u ON cr.user_id = u.id
                               LEFT JOIN posts p ON cr.post_id = p.id
                               WHERE cr.id = ?");
        $stmt->execute([$case_id]);
        $case = $stmt->fetch();
        if (!$case) { echo json_encode(['success' => false]); exit; }

        // Get AI analysis
        $ai = [];
        if ($case['post_id']) {
            $stmt = $pdo->prepare("SELECT * FROM ai_analysis WHERE post_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$case['post_id']]);
            $ai = $stmt->fetch();
        }

        // Get mood history
        $stmt = $pdo->prepare("SELECT emotion, entry_date FROM mood_entries WHERE user_id = ? ORDER BY entry_date DESC LIMIT 7");
        $stmt->execute([$case['user_id']]);
        $moods = $stmt->fetchAll();

        // Get conversation messages
        $stmt = $pdo->prepare("SELECT cm.*, u.anonymous_name, u.role
                               FROM consultation_messages cm
                               JOIN users u ON cm.sender_id = u.id
                               WHERE cm.request_id = ?
                               ORDER BY cm.created_at ASC");
        $stmt->execute([$case_id]);
        $messages = $stmt->fetchAll();

        // Get volunteer notes
        $stmt = $pdo->prepare("SELECT * FROM volunteer_notes WHERE case_id = ? ORDER BY created_at DESC");
        $stmt->execute([$case_id]);
        $notes = $stmt->fetchAll();

        // Get follow-ups
        $stmt = $pdo->prepare("SELECT * FROM volunteer_followups WHERE case_id = ? AND status = 'pending' ORDER BY followup_date ASC");
        $stmt->execute([$case_id]);
        $followups = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'case' => $case,
            'ai' => $ai,
            'moods' => $moods,
            'messages' => $messages,
            'notes' => $notes,
            'followups' => $followups
        ]);
        exit;
    }

    // ---- Send message ----
    if ($action === 'send_message') {
        $case_id = intval($_POST['case_id']);
        $message = trim($_POST['message']);
        if (empty($message)) { echo json_encode(['success' => false, 'error' => 'Message required']); exit; }

        // Check if volunteer is assigned to this case
        $stmt = $pdo->prepare("SELECT volunteer_id, user_id FROM consultation_requests WHERE id = ?");
        $stmt->execute([$case_id]);
        $case = $stmt->fetch();
        if (!$case || $case['volunteer_id'] != $user_id) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO consultation_messages (request_id, sender_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$case_id, $user_id, $message]);

        // Mark the case as actively in-progress once a volunteer has replied.
        $pdo->prepare("UPDATE consultation_requests SET status = 'active', started_at = COALESCE(started_at, NOW()) WHERE id = ? AND status != 'closed'")->execute([$case_id]);

        // Notify user
        createNotification($case['user_id'], 'volunteer_message', 'Volunteer replied to your consultation.', 'consultation.php?id='.$case_id, $pdo);

        echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
        exit;
    }

    // ---- Get new messages (polling) ----
    if ($action === 'get_messages') {
        $case_id = intval($_GET['case_id']);
        $last_id = intval($_GET['last_id']);
        $stmt = $pdo->prepare("SELECT cm.*, u.anonymous_name, u.role
                               FROM consultation_messages cm
                               JOIN users u ON cm.sender_id = u.id
                               WHERE cm.request_id = ? AND cm.id > ?
                               ORDER BY cm.created_at ASC");
        $stmt->execute([$case_id, $last_id]);
        $messages = $stmt->fetchAll();
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    }

    // ---- Close case ----
    if ($action === 'close_case') {
        $case_id = intval($_POST['case_id']);
        $feedback = trim($_POST['feedback'] ?? '');
        $rating = intval($_POST['rating'] ?? 0);

        $stmt = $pdo->prepare("UPDATE consultation_requests SET status = 'closed', closed_at = NOW(), feedback_rating = ?, feedback_comment = ? WHERE id = ? AND volunteer_id = ?");
        $stmt->execute([$rating, $feedback, $case_id, $user_id]);

        // Notify user
        $stmt = $pdo->prepare("SELECT user_id FROM consultation_requests WHERE id = ?");
        $stmt->execute([$case_id]);
        $userId = $stmt->fetchColumn();
        createNotification($userId, 'case_closed', 'Your consultation has been closed. We hope you found it helpful.', 'consultation.php?id='.$case_id, $pdo);

        echo json_encode(['success' => true]);
        exit;
    }

    // ---- Add note ----
    if ($action === 'add_note') {
        $case_id = intval($_POST['case_id']);
        $note = trim($_POST['note']);
        if (empty($note)) { echo json_encode(['success' => false]); exit; }
        $stmt = $pdo->prepare("INSERT INTO volunteer_notes (case_id, volunteer_id, note) VALUES (?, ?, ?)");
        $stmt->execute([$case_id, $user_id, $note]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ---- Add follow-up ----
    if ($action === 'add_followup') {
        $case_id = intval($_POST['case_id']);
        $followup_date = $_POST['followup_date'];
        $note = trim($_POST['note']);
        $stmt = $pdo->prepare("INSERT INTO volunteer_followups (case_id, volunteer_id, followup_date, note) VALUES (?, ?, ?, ?)");
        $stmt->execute([$case_id, $user_id, $followup_date, $note]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ---- Get stats ----
    if ($action === 'get_stats') {
        $today = date('Y-m-d');
        // Assigned cases (active)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM consultation_requests WHERE volunteer_id = ? AND status = 'active'");
        $stmt->execute([$user_id]);
        $assigned = $stmt->fetchColumn();

        // Resolved today
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM consultation_requests WHERE volunteer_id = ? AND status = 'closed' AND DATE(closed_at) = ?");
        $stmt->execute([$user_id, $today]);
        $resolved = $stmt->fetchColumn();

        // Average response time
        $stmt = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, assigned_at)) as avg_response FROM consultation_requests WHERE volunteer_id = ? AND assigned_at IS NOT NULL");
        $stmt->execute([$user_id]);
        $avg_response = round($stmt->fetchColumn() ?: 0);

        // Rating
        $stmt = $pdo->prepare("SELECT AVG(feedback_rating) as avg_rating FROM consultation_requests WHERE volunteer_id = ? AND feedback_rating > 0");
        $stmt->execute([$user_id]);
        $avg_rating = round($stmt->fetchColumn() ?: 0, 1);

        // Queue size
        $stmt = $pdo->query("SELECT COUNT(*) FROM consultation_requests WHERE status = 'queued'");
        $queue_size = $stmt->fetchColumn();

        // High priority
        $stmt = $pdo->query("SELECT COUNT(*) FROM consultation_requests WHERE status = 'queued' AND priority = 'high'");
        $high_priority = $stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'assigned' => $assigned,
            'resolved' => $resolved,
            'avg_response' => $avg_response,
            'avg_rating' => $avg_rating,
            'queue_size' => $queue_size,
            'high_priority' => $high_priority
        ]);
        exit;
    }

    // ---- Get AI Copilot suggestion ----
    if ($action === 'ai_suggest') {
        $case_id = intval($_GET['case_id']);
        $stmt = $pdo->prepare("SELECT cr.*, p.content FROM consultation_requests cr LEFT JOIN posts p ON cr.post_id = p.id WHERE cr.id = ?");
        $stmt->execute([$case_id]);
        $case = $stmt->fetch();
        if (!$case) { echo json_encode(['success' => false]); exit; }

        $content = $case['content'] ?? $case['reason'] ?? '';
        // Use MindGuide to generate suggestion
        require_once __DIR__ . '/ai/AIManager.php';
        $manager = new AIManager();
        $suggestion = $manager->chat('Generate a compassionate response to this user: "' . substr($content, 0, 500) . '"', $case['user_id']);
        echo json_encode(['success' => true, 'suggestion' => $suggestion['reply'] ?? '']);
        exit;
    }

    // ---- Emergency alert (get active emergencies) ----
    if ($action === 'emergency_alerts') {
        $stmt = $pdo->query("SELECT cr.*, u.anonymous_name, a.risk_score
                             FROM consultation_requests cr
                             JOIN users u ON cr.user_id = u.id
                             LEFT JOIN ai_analysis a ON cr.post_id = a.post_id
                             WHERE cr.priority = 'high' AND cr.status = 'queued'
                             ORDER BY cr.created_at DESC LIMIT 5");
        $emergencies = $stmt->fetchAll();
        echo json_encode(['success' => true, 'emergencies' => $emergencies]);
        exit;
    }

    // ---- Performance history ----
    if ($action === 'performance_history') {
        $stmt = $pdo->prepare("SELECT DATE(closed_at) as date, COUNT(*) as count FROM consultation_requests WHERE volunteer_id = ? AND status = 'closed' AND closed_at > DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(closed_at) ORDER BY date ASC");
        $stmt->execute([$user_id]);
        $data = $stmt->fetchAll();
        $labels = [];
        $values = [];
        foreach ($data as $d) {
            $labels[] = date('M d', strtotime($d['date']));
            $values[] = $d['count'];
        }
        echo json_encode(['success' => true, 'labels' => $labels, 'values' => $values]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// ============================================================
// Page Data
// ============================================================
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$case_id = isset($_GET['case_id']) ? intval($_GET['case_id']) : 0;

// Stats
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM consultation_requests WHERE volunteer_id = ? AND status = 'active'");
$stmt->execute([$user_id]);
$assigned_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM consultation_requests WHERE volunteer_id = ? AND status = 'closed' AND DATE(closed_at) = ?");
$stmt->execute([$user_id, $today]);
$resolved_today = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, assigned_at)) as avg_response FROM consultation_requests WHERE volunteer_id = ? AND assigned_at IS NOT NULL");
$stmt->execute([$user_id]);
$avg_response = round($stmt->fetchColumn() ?: 0);

$stmt = $pdo->prepare("SELECT AVG(feedback_rating) as avg_rating FROM consultation_requests WHERE volunteer_id = ? AND feedback_rating > 0");
$stmt->execute([$user_id]);
$avg_rating = round($stmt->fetchColumn() ?: 0, 1);

$stmt = $pdo->query("SELECT COUNT(*) FROM consultation_requests WHERE status = 'queued'");
$queue_size = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM consultation_requests WHERE status = 'queued' AND priority = 'high'");
$high_priority = $stmt->fetchColumn();

// Recent cases
$stmt = $pdo->prepare("SELECT cr.*, u.anonymous_name FROM consultation_requests cr JOIN users u ON cr.user_id = u.id WHERE cr.volunteer_id = ? ORDER BY cr.created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$recent_cases = $stmt->fetchAll();

// Daily goal (10 cases)
$goal_percent = min(100, round(($resolved_today / 10) * 100));

// Random tip
$tips = [
    "Listen without judgment. Sometimes people just need to feel heard.",
    "Acknowledge their feelings before offering solutions.",
    "You don't have to have all the answers. Being present is enough.",
    "Use open-ended questions to encourage them to share more.",
    "Validate their experience – it builds trust.",
    "Remember to take breaks. You can't pour from an empty cup."
];
$tip = $tips[array_rand($tips)];

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Portal – Haven</title>
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
            transition: background 0.3s, color 0.3s;
            overflow-x: hidden;
        }
        h1,h2,h3,h4,h5 { font-family: 'Poppins', sans-serif; }

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

        body.dark-mode {
            background: #f7f4ed;
            color: #26332b;
        }
        body.dark-mode .glass-card,
        body.dark-mode .glass-nav,
        body.dark-mode .glass-sidebar,
        body.dark-mode .glass-footer {
            background: rgba(38,51,43,0.85);
            border-color: rgba(94,117,100,0.08);
            color: #26332b;
        }
        body.dark-mode .form-control {
            background: rgba(38,51,43,0.8);
            color: #26332b;
            border-color: rgba(94,117,100,0.15);
        }
        body.dark-mode .text-muted { color: #7c857e !important; }
        body.dark-mode .btn-outline-secondary { color: #7c857e; border-color: #5c655f; }

        .glass-card {
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(94,117,100,0.25);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(64,77,67,0.06);
            transition: all 0.3s ease;
        }
        .glass-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(64,77,67,0.10);
        }
        .glass-nav, .glass-sidebar, .glass-footer {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(94,117,100,0.3);
        }
        .glass-sidebar { border-right: 1px solid rgba(94,117,100,0.3); border-bottom: none; }
        .glass-footer { border-bottom: none; border-top: 1px solid rgba(94,117,100,0.3); }

        /* ===== LAYOUT ===== */
        .volunteer-layout {
            display: grid;
            grid-template-columns: 220px 1fr 280px;
            gap: 20px;
            padding: 80px 20px 20px;
            max-width: 1440px;
            margin: 0 auto;
        }
        @media (max-width: 992px) {
            .volunteer-layout { grid-template-columns: 1fr; }
            .left-sidebar, .right-sidebar { display: none; }
            .left-sidebar.mobile-open, .right-sidebar.mobile-open { display: block; }
        }

        /* ===== SIDEBARS ===== */
        .left-sidebar, .right-sidebar {
            position: sticky;
            top: 80px;
            height: calc(100vh - 80px);
            overflow-y: auto;
            padding: 10px;
        }
        .left-sidebar .nav-link {
            padding: 0.5rem 0.8rem;
            border-radius: 10px;
            color: inherit;
            transition: 0.2s;
        }
        .left-sidebar .nav-link:hover { background: rgba(94,117,100,0.08); }
        .left-sidebar .nav-link.active { background: rgba(94,117,100,0.12); color: #5e7564; }
        .left-sidebar .nav-link i { margin-right: 10px; }

        /* ===== STATS CARDS ===== */
        .stat-card { padding: 1rem; text-align: center; }
        .stat-card .number { font-size: 2rem; font-weight: 700; font-family: 'Poppins', sans-serif; }
        .stat-card .label { font-size: 0.8rem; color: #7c857e; }

        /* ===== QUEUE ITEMS ===== */
        .queue-item {
            padding: 1rem;
            margin-bottom: 0.8rem;
            border-left: 4px solid #7c857e;
            cursor: pointer;
            transition: 0.3s;
        }
        .queue-item:hover { transform: translateX(5px); }
        .queue-item.priority-high { border-left-color: #c96a63; }
        .queue-item.priority-medium { border-left-color: #d9a441; }
        .queue-item.priority-low { border-left-color: #7fa383; }
        .queue-item .waiting-time { font-size: 0.8rem; color: #7c857e; }

        /* ===== CHAT ===== */
        .chat-container {
            max-height: 400px;
            overflow-y: auto;
            padding: 0.5rem;
        }
        .chat-message {
            padding: 0.5rem 1rem;
            border-radius: 16px;
            margin-bottom: 0.5rem;
            max-width: 80%;
            word-wrap: break-word;
        }
        .chat-message.user { background: rgba(94,117,100,0.15); align-self: flex-start; }
        .chat-message.volunteer { background: rgba(127,163,131,0.15); align-self: flex-end; margin-left: auto; }
        .chat-message .sender { font-size: 0.7rem; font-weight: 600; margin-bottom: 0.2rem; }
        .chat-message .time { font-size: 0.6rem; color: #7c857e; margin-left: 0.5rem; }

        /* ===== AI SUGGESTION ===== */
        .ai-suggestion {
            background: rgba(201,167,107,0.08);
            border-left: 3px solid #c9a76b;
            padding: 0.8rem;
            border-radius: 12px;
        }
        .ai-suggestion .suggestion-text { font-style: italic; color: #7c857e; }

        /* ===== TABS ===== */
        .tab-btn {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            border: none;
            background: transparent;
            color: #7c857e;
            transition: 0.2s;
        }
        .tab-btn.active { background: rgba(94,117,100,0.12); color: #5e7564; }
        .tab-btn:hover { background: rgba(94,117,100,0.06); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .volunteer-layout { grid-template-columns: 1fr; padding: 70px 10px 10px; }
        }
        @media (max-width: 576px) {
            .stat-card .number { font-size: 1.5rem; }
            .queue-item { padding: 0.8rem; }
        }
    </style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar navbar-expand glass-nav fixed-top">
    <div class="container-fluid px-3">
        <button class="btn btn-link d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>
        <a class="navbar-brand" href="index.php"><img src="logo.png" alt="Haven" style="height:24px;width:24px;object-fit:cover;border-radius:6px;vertical-align:-6px;margin-right:4px;"> Haven</a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="badge bg-success">🟢 Volunteer</span>
            <button class="btn btn-outline-secondary btn-sm" id="darkToggle"><i class="bi bi-moon"></i></button>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
    </div>
</nav>

<!-- ===== VOLUNTEER LAYOUT ===== -->
<div class="volunteer-layout">

    <!-- LEFT SIDEBAR -->
    <div class="left-sidebar" id="leftSidebar">
        <div class="profile-card glass-card p-3 text-center">
            <div class="avatar" style="width:60px;height:60px;border-radius:50%;background:#5e7564;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.5rem;margin:0 auto;">
                <?= substr($anon_name, 0, 1) ?>
            </div>
            <h6 class="mt-2"><?= escape($anon_name) ?></h6>
            <p class="text-muted small">Volunteer</p>
        </div>

        <nav class="nav flex-column mt-2">
            <a href="?tab=dashboard" class="nav-link <?= $tab=='dashboard'?'active':'' ?>"><i class="bi bi-house"></i> Dashboard</a>
            <a href="?tab=assigned" class="nav-link <?= $tab=='assigned'?'active':'' ?>"><i class="bi bi-inbox"></i> Assigned Cases <?php if ($assigned_count > 0): ?><span class="badge bg-primary"><?= $assigned_count ?></span><?php endif; ?></a>
            <a href="?tab=queue" class="nav-link <?= $tab=='queue'?'active':'' ?>"><i class="bi bi-clock"></i> Waiting Queue <?php if ($queue_size > 0): ?><span class="badge bg-warning"><?= $queue_size ?></span><?php endif; ?></a>
            <a href="?tab=emergency" class="nav-link <?= $tab=='emergency'?'active':'' ?>"><i class="bi bi-exclamation-triangle text-danger"></i> Emergency <?php if ($high_priority > 0): ?><span class="badge bg-danger"><?= $high_priority ?></span><?php endif; ?></a>
            <a href="?tab=assigned" class="nav-link"><i class="bi bi-chat-dots"></i> Live Conversations</a>
            <a href="articles.php" class="nav-link"><i class="bi bi-book"></i> Resource Library</a>
            <a href="?tab=dashboard#performanceChart" class="nav-link"><i class="bi bi-graph-up"></i> Performance</a>
            <a href="notifications.php" class="nav-link"><i class="bi bi-bell"></i> Notifications <?php if ($unread_notifs > 0): ?><span class="badge bg-danger"><?= $unread_notifs ?></span><?php endif; ?></a>
            <a href="profile.php?id=<?= $user_id ?>" class="nav-link"><i class="bi bi-person"></i> Profile</a>
            <a href="settings.php" class="nav-link"><i class="bi bi-gear"></i> Settings</a>
        </nav>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <?php if ($tab == 'dashboard'): ?>
            <!-- ===== DASHBOARD TAB ===== -->

            <!-- Greeting -->
            <div class="glass-card p-4 mb-3">
                <h2>Good <?= date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening') ?>, <?= escape($anon_name) ?> 👋</h2>
                <p class="text-muted">Thank you for supporting our community ❤️</p>
                <p class="small">💡 <?= $tip ?></p>
            </div>

            <!-- Stats -->
            <div class="row g-3 mb-3">
                <div class="col-md-3 col-6"><div class="glass-card stat-card"><div class="number counter" data-target="<?= $assigned_count ?>">0</div><div class="label">Assigned Cases</div></div></div>
                <div class="col-md-3 col-6"><div class="glass-card stat-card"><div class="number counter" data-target="<?= $resolved_today ?>">0</div><div class="label">Resolved Today</div></div></div>
                <div class="col-md-3 col-6"><div class="glass-card stat-card"><div class="number counter" data-target="<?= $avg_response ?>">0</div><div class="label">Avg Response (min)</div></div></div>
                <div class="col-md-3 col-6"><div class="glass-card stat-card"><div class="number counter" data-target="<?= $avg_rating ?>">0</div><div class="label">Community Rating</div></div></div>
            </div>

            <!-- Daily Goal -->
            <div class="glass-card p-3 mb-3">
                <h6>Today's Goal – Support 10 People</h6>
                <div class="d-flex align-items-center gap-3">
                    <div class="progress flex-grow-1" style="height:10px;">
                        <div class="progress-bar" style="width:<?= $goal_percent ?>%;"></div>
                    </div>
                    <span class="fw-bold"><?= $resolved_today ?> / 10</span>
                </div>
            </div>

            <!-- Recent Cases -->
            <div class="glass-card p-3 mb-3">
                <h6>Recent Cases</h6>
                <?php if (empty($recent_cases)): ?>
                    <p class="text-muted small">No recent cases.</p>
                <?php else: ?>
                    <?php foreach ($recent_cases as $case): ?>
                        <div class="glass-card p-2 mb-2">
                            <div class="d-flex justify-content-between">
                                <span><strong><?= escape($case['anonymous_name']) ?></strong> <span class="badge bg-<?= $case['priority']=='high'?'danger':($case['priority']=='medium'?'warning':'secondary') ?>"><?= ucfirst($case['priority']) ?></span></span>
                                <span class="text-muted small"><?= timeAgo($case['created_at']) ?></span>
                            </div>
                            <p class="small text-muted"><?= substr(escape($case['message'] ?? $case['reason'] ?? ''), 0, 80) ?>...</p>
                            <a href="?tab=case&case_id=<?= $case['id'] ?>" class="btn btn-sm btn-primary">View</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($tab == 'queue'): ?>
            <!-- ===== QUEUE TAB ===== -->
            <div class="glass-card p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="bi bi-clock"></i> Waiting Queue</h5>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-secondary filter-btn active" data-filter="all">All</button>
                        <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="high">High</button>
                        <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="medium">Medium</button>
                        <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="low">Low</button>
                    </div>
                </div>
                <div id="queueContainer">
                    <p class="text-muted">Loading...</p>
                </div>
            </div>

        <?php elseif ($tab == 'assigned'): ?>
            <!-- ===== ASSIGNED CASES TAB ===== -->
            <div class="glass-card p-3">
                <h5><i class="bi bi-inbox"></i> My Assigned Cases</h5>
                <div id="assignedContainer">
                    <p class="text-muted">Loading...</p>
                </div>
            </div>

        <?php elseif ($tab == 'emergency'): ?>
            <!-- ===== EMERGENCY TAB ===== -->
            <div class="glass-card p-3" style="border:2px solid #c96a63;">
                <h5><i class="bi bi-exclamation-triangle text-danger"></i> Emergency Cases</h5>
                <p class="text-muted small">These cases require immediate attention</p>
                <div id="emergencyContainer">
                    <p class="text-muted">Loading...</p>
                </div>
            </div>

        <?php elseif ($tab == 'case' && $case_id > 0): ?>
            <!-- ===== CASE DETAILS TAB ===== -->
            <div id="caseWorkspace">
                <div id="caseLoader" class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p>Loading case...</p>
                </div>
                <div id="caseContent" style="display:none;">
                    <!-- Case Details -->
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <!-- User Info & AI Summary -->
                            <div class="glass-card p-3 mb-3" id="caseHeader">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 id="caseUserName">Loading...</h5>
                                        <p class="text-muted small" id="caseUserInfo"></p>
                                    </div>
                                    <div>
                                        <span class="badge" id="casePriorityBadge">Loading...</span>
                                        <span class="badge bg-secondary" id="caseStatusBadge">Loading...</span>
                                    </div>
                                </div>
                            </div>

                            <!-- AI Summary -->
                            <div class="glass-card p-3 mb-3" style="background:rgba(201,167,107,0.05); border-left:4px solid #c9a76b;">
                                <h6><i class="bi bi-robot"></i> MindGuide Summary</h6>
                                <div id="aiSummary">
                                    <p class="text-muted">Loading AI analysis...</p>
                                </div>
                            </div>

                            <!-- Conversation -->
                            <div class="glass-card p-3 mb-3">
                                <h6><i class="bi bi-chat-dots"></i> Conversation</h6>
                                <div class="chat-container" id="chatContainer">
                                    <p class="text-muted">No messages yet.</p>
                                </div>
                                <div class="input-group mt-2">
                                    <input type="text" class="form-control" id="chatInput" placeholder="Type a message...">
                                    <button class="btn btn-primary" id="sendBtn">Send</button>
                                </div>
                                <div class="mt-1 small text-muted" id="typingIndicator" style="display:none;">
                                    <i class="bi bi-three-dots"></i> User is typing...
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- AI Copilot -->
                            <div class="glass-card p-3 mb-3" style="background:rgba(201,167,107,0.05);">
                                <h6><i class="bi bi-magic"></i> AI Copilot</h6>
                                <button class="btn btn-sm btn-outline-primary w-100 mb-2" id="suggestReplyBtn">
                                    <i class="bi bi-lightbulb"></i> Suggest Reply
                                </button>
                                <div id="suggestionContainer" class="ai-suggestion" style="display:none;">
                                    <p class="suggestion-text" id="suggestionText">Click suggest for AI response</p>
                                    <button class="btn btn-sm btn-primary mt-1" id="useSuggestionBtn">Use Suggestion</button>
                                </div>
                                <hr>
                                <h6>Resources</h6>
                                <div class="d-flex flex-wrap gap-1">
                                    <button class="btn btn-sm btn-outline-secondary resource-btn" data-resource="articles">📚 Articles</button>
                                    <button class="btn btn-sm btn-outline-secondary resource-btn" data-resource="videos">🎬 Videos</button>
                                    <button class="btn btn-sm btn-outline-secondary resource-btn" data-resource="breathing">🌬️ Breathing</button>
                                    <button class="btn btn-sm btn-outline-secondary resource-btn" data-resource="meditation">🧘 Meditation</button>
                                    <button class="btn btn-sm btn-outline-secondary resource-btn" data-resource="emergency">🚨 Emergency</button>
                                </div>
                                <div id="resourceContent" class="mt-2"></div>
                            </div>

                            <!-- Mood History -->
                            <div class="glass-card p-3 mb-3">
                                <h6>Mood History (7 days)</h6>
                                <div id="moodHistory" class="d-flex gap-1 flex-wrap">
                                    <span class="text-muted">Loading...</span>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="glass-card p-3 mb-3">
                                <h6>Private Notes</h6>
                                <div id="notesContainer">
                                    <p class="text-muted small">No notes.</p>
                                </div>
                                <div class="input-group input-group-sm mt-2">
                                    <input type="text" class="form-control" id="noteInput" placeholder="Add a note...">
                                    <button class="btn btn-outline-secondary" id="addNoteBtn">Add</button>
                                </div>
                            </div>

                            <!-- Follow-ups -->
                            <div class="glass-card p-3 mb-3">
                                <h6>Follow-up</h6>
                                <div id="followupContainer">
                                    <p class="text-muted small">No follow-ups.</p>
                                </div>
                                <div class="input-group input-group-sm mt-2">
                                    <input type="date" class="form-control" id="followupDate">
                                    <input type="text" class="form-control" id="followupNote" placeholder="Note">
                                    <button class="btn btn-outline-secondary" id="addFollowupBtn">Add</button>
                                </div>
                            </div>

                            <!-- Case Actions -->
                            <div class="glass-card p-3">
                                <h6>Actions</h6>
                                <div class="d-flex flex-wrap gap-1">
                                    <button class="btn btn-sm btn-success" id="closeCaseBtn"><i class="bi bi-check"></i> Close</button>
                                    <button class="btn btn-sm btn-warning" id="escalateBtn"><i class="bi bi-arrow-up"></i> Escalate</button>
                                    <button class="btn btn-sm btn-danger" id="emergencyBtn"><i class="bi bi-exclamation-triangle"></i> Emergency</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT SIDEBAR -->
    <div class="right-sidebar" id="rightSidebar">
        <div class="widget glass-card p-3 mb-3">
            <h6><i class="bi bi-robot"></i> MindGuide</h6>
            <p class="small text-muted">AI assistant for volunteers</p>
            <button class="btn btn-primary btn-sm w-100" onclick="window.location.href='chatbot.php'">Open Assistant</button>
        </div>

        <div class="widget glass-card p-3 mb-3">
            <h6><i class="bi bi-people"></i> Community Pulse</h6>
            <div class="small">
                <div>👥 <span id="onlineCount">0</span> online</div>
                <div>📝 <span id="todayPosts">0</span> posts today</div>
                <div>⏳ <span id="queueSize"><?= $queue_size ?></span> waiting</div>
                <div>🔴 <span id="highPriorityCount"><?= $high_priority ?></span> high priority</div>
            </div>
        </div>

        <div class="widget glass-card p-3 mb-3">
            <h6><i class="bi bi-lightbulb"></i> Tip</h6>
            <p class="small text-muted">"<?= $tip ?>"</p>
        </div>

        <div class="widget glass-card p-3 mb-3">
            <h6><i class="bi bi-person-check"></i> Online Volunteers</h6>
            <?php
            $online_volunteers = $pdo->query("SELECT anonymous_name FROM users WHERE role = 'volunteer' AND last_login > DATE_SUB(NOW(), INTERVAL 5 MINUTE) LIMIT 5")->fetchAll();
            ?>
            <?php foreach ($online_volunteers as $v): ?>
                <div class="small"><i class="bi bi-circle-fill text-success" style="font-size:0.5rem;"></i> <?= escape($v['anonymous_name']) ?></div>
            <?php endforeach; ?>
        </div>

        <div class="widget glass-card p-3 mb-3">
            <h6>📊 Performance</h6>
            <canvas id="performanceChart" height="120"></canvas>
        </div>
    </div>
</div>

<!-- ===== FOOTER ===== -->
<footer class="glass-footer py-3 text-center">
    <p class="mb-0 small text-muted">© <?= date('Y') ?> Haven Volunteer Portal</p>
</footer>

<!-- ===== SCRIPTS ===== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script>
    // ============================================================
    // GLOBALS
    // ============================================================
    const USER_ID = <?= $user_id ?>;
    const CASE_ID = <?= $case_id ?: 0 ?>;
    const TAB = '<?= $tab ?>';
    let lastMessageId = 0;
    let pollInterval = null;

    // ============================================================
    // DARK MODE
    // ============================================================
    document.getElementById('darkToggle').addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
    });
    if (localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark-mode');

    // ============================================================
    // SIDEBAR TOGGLE
    // ============================================================
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('leftSidebar').classList.toggle('mobile-open');
        document.getElementById('rightSidebar').classList.toggle('mobile-open');
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
    // QUEUE / ASSIGNED / EMERGENCY LOADING
    // ============================================================
    function loadQueue(filter = 'all') {
        const container = document.getElementById('queueContainer');
        if (!container) return;
        container.innerHTML = '<p class="text-muted">Loading...</p>';
        fetch(`volunteer.php?ajax=1&action=get_queue&filter=${filter}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.cases.length) {
                container.innerHTML = data.cases.map(c => `
                    <div class="queue-item priority-${c.priority} glass-card" onclick="window.location.href='?tab=case&case_id=${c.id}'">
                        <div class="d-flex justify-content-between">
                            <strong>${c.risk_score > 60 ? '🔴' : (c.priority=='high' ? '🟡' : '🟢')} ${c.anonymous_name}</strong>
                            <span class="badge bg-${c.priority=='high'?'danger':(c.priority=='medium'?'warning':'secondary')}">${c.priority.toUpperCase()}</span>
                        </div>
                        <p class="small text-muted">${c.message ? c.message.substring(0, 80) : 'No message'}</p>
                        <div class="d-flex justify-content-between">
                            <span class="waiting-time"><i class="bi bi-clock"></i> Waiting: ${Math.floor((Date.now() - new Date(c.created_at).getTime()) / 60000)} min</span>
                            <span class="badge bg-secondary">${c.message_count || 0} messages</span>
                        </div>
                        ${c.risk_score > 60 ? '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> High Risk</span>' : ''}
                        <button class="btn btn-sm btn-primary accept-btn mt-1" data-case-id="${c.id}">Accept Case</button>
                    </div>
                `).join('');
                // Attach accept handlers
                document.querySelectorAll('.accept-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        acceptCase(this.dataset.caseId);
                    });
                });
            } else {
                container.innerHTML = '<p class="text-muted">No cases in queue.</p>';
            }
        });
    }

    function loadAssigned() {
        const container = document.getElementById('assignedContainer');
        if (!container) return;
        container.innerHTML = '<p class="text-muted">Loading...</p>';
        fetch('volunteer.php?ajax=1&action=get_assigned')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.cases.length) {
                container.innerHTML = data.cases.map(c => `
                    <div class="queue-item priority-${c.priority} glass-card" onclick="window.location.href='?tab=case&case_id=${c.id}'">
                        <div class="d-flex justify-content-between">
                            <strong>🟡 ${c.anonymous_name}</strong>
                            <span class="badge bg-${c.priority=='high'?'danger':(c.priority=='medium'?'warning':'secondary')}">${c.priority.toUpperCase()}</span>
                        </div>
                        <p class="small text-muted">${c.message ? c.message.substring(0, 80) : 'No message'}</p>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small"><i class="bi bi-clock"></i> ${timeAgo(c.created_at)}</span>
                            <span class="badge bg-secondary">${c.message_count || 0} messages</span>
                        </div>
                        <a href="?tab=case&case_id=${c.id}" class="btn btn-sm btn-primary mt-1">Continue</a>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-muted">No assigned cases.</p>';
            }
        });
    }

    function loadEmergency() {
        const container = document.getElementById('emergencyContainer');
        if (!container) return;
        container.innerHTML = '<p class="text-muted">Loading...</p>';
        fetch('volunteer.php?ajax=1&action=emergency_alerts')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.emergencies.length) {
                container.innerHTML = data.emergencies.map(c => `
                    <div class="queue-item priority-high glass-card" style="border-left-color:#c96a63;background:rgba(201,106,99,0.05);" onclick="window.location.href='?tab=case&case_id=${c.id}'">
                        <div class="d-flex justify-content-between">
                            <strong>🚨 ${c.anonymous_name}</strong>
                            <span class="badge bg-danger">EMERGENCY</span>
                        </div>
                        <p class="small text-muted">${c.message ? c.message.substring(0, 80) : 'No message'}</p>
                        <p class="small"><span class="badge bg-danger">Risk Score: ${c.risk_score || 0}%</span></p>
                        <button class="btn btn-sm btn-danger accept-btn" data-case-id="${c.id}">Accept Emergency</button>
                    </div>
                `).join('');
                document.querySelectorAll('.accept-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        acceptCase(this.dataset.caseId);
                    });
                });
            } else {
                container.innerHTML = '<p class="text-muted">No emergencies.</p>';
            }
        });
    }

    function acceptCase(caseId) {
        if (!caseId) return;
        fetch('volunteer.php?ajax=1&action=accept_case', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `case_id=${caseId}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Case accepted!', 'success');
                setTimeout(() => {
                    window.location.href = `?tab=case&case_id=${caseId}`;
                }, 500);
            } else {
                showToast(data.error || 'Failed to accept case', 'danger');
            }
        });
    }

    // Filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            loadQueue(this.dataset.filter);
        });
    });

    // ============================================================
    // CASE DETAILS
    // ============================================================
    function loadCaseDetails() {
        if (!CASE_ID) return;
        fetch(`volunteer.php?ajax=1&action=case_details&case_id=${CASE_ID}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('caseLoader').innerHTML = '<p class="text-danger">Failed to load case.</p>';
                return;
            }
            document.getElementById('caseLoader').style.display = 'none';
            document.getElementById('caseContent').style.display = 'block';

            const c = data.case;

            // Header
            document.getElementById('caseUserName').textContent = c.anonymous_name;
            const age = c.birth_date ? Math.floor((new Date() - new Date(c.birth_date)) / 31536000000) : 'Unknown';
            document.getElementById('caseUserInfo').textContent = `Age: ${age} · ${c.occupation || 'N/A'} · ${c.country || 'N/A'} · Joined: ${timeAgo(c.created_at)}`;
            document.getElementById('casePriorityBadge').textContent = c.priority.toUpperCase();
            document.getElementById('casePriorityBadge').className = `badge bg-${c.priority=='high'?'danger':(c.priority=='medium'?'warning':'secondary')}`;
            document.getElementById('caseStatusBadge').textContent = c.status.toUpperCase();

            // AI Summary
            const ai = data.ai;
            if (ai && ai.ai_reply) {
                document.getElementById('aiSummary').innerHTML = `
                    <p><strong>Emotion:</strong> ${ai.emotion || 'N/A'} · <strong>Risk:</strong> ${ai.risk_score || 0}%</p>
                    <p><strong>AI Analysis:</strong> ${ai.ai_reply}</p>
                    ${ai.is_volunteer_notified ? '<span class="badge bg-info">Volunteer Notified</span>' : ''}
                `;
            } else {
                document.getElementById('aiSummary').innerHTML = '<p class="text-muted">No AI analysis available.</p>';
            }

            // Mood History
            const moodContainer = document.getElementById('moodHistory');
            if (data.moods && data.moods.length) {
                const emojiMap = { happy:'😊', calm:'😌', okay:'🙂', sad:'😔', stressed:'😰', angry:'😡', tired:'😴' };
                moodContainer.innerHTML = data.moods.map(m => emojiMap[m.emotion] || '😐').join(' ');
            } else {
                moodContainer.innerHTML = '<span class="text-muted">No mood data.</span>';
            }

            // Messages
            lastMessageId = 0;
            if (data.messages && data.messages.length) {
                data.messages.forEach(m => {
                    if (m.id > lastMessageId) lastMessageId = m.id;
                });
                renderMessages(data.messages);
            } else {
                document.getElementById('chatContainer').innerHTML = '<p class="text-muted">No messages yet. Start the conversation.</p>';
            }

            // Notes
            const notesContainer = document.getElementById('notesContainer');
            if (data.notes && data.notes.length) {
                notesContainer.innerHTML = data.notes.map(n => `
                    <div class="small border-bottom py-1">${n.note} <span class="text-muted" style="font-size:0.6rem;">${timeAgo(n.created_at)}</span></div>
                `).join('');
            } else {
                notesContainer.innerHTML = '<p class="text-muted small">No notes.</p>';
            }

            // Follow-ups
            const followupContainer = document.getElementById('followupContainer');
            if (data.followups && data.followups.length) {
                followupContainer.innerHTML = data.followups.map(f => `
                    <div class="small border-bottom py-1">📅 ${f.followup_date}: ${f.note || 'Follow-up'} <span class="badge bg-${f.status=='pending'?'warning':'success'}">${f.status}</span></div>
                `).join('');
            } else {
                followupContainer.innerHTML = '<p class="text-muted small">No follow-ups.</p>';
            }

            // Start polling for new messages
            if (pollInterval) clearInterval(pollInterval);
            pollInterval = setInterval(pollNewMessages, 3000);
        });
    }

    function renderMessages(messages) {
        const container = document.getElementById('chatContainer');
        if (!container) return;
        if (!messages || !messages.length) {
            container.innerHTML = '<p class="text-muted">No messages.</p>';
            return;
        }
        container.innerHTML = messages.map(m => `
            <div class="chat-message ${(m.role === 'volunteer' || m.role === 'admin') ? 'volunteer' : 'user'}">
                <div class="sender">${(m.role === 'volunteer' || m.role === 'admin') ? '🧑‍⚕️ Volunteer' : '👤 ' + (m.anonymous_name || 'User')} <span class="time">${timeAgo(m.created_at)}</span></div>
                ${escapeHtml(m.message)}
            </div>
        `).join('');
        container.scrollTop = container.scrollHeight;
    }

    function pollNewMessages() {
        if (!CASE_ID) return;
        fetch(`volunteer.php?ajax=1&action=get_messages&case_id=${CASE_ID}&last_id=${lastMessageId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.messages && data.messages.length) {
                data.messages.forEach(m => {
                    if (m.id > lastMessageId) lastMessageId = m.id;
                });
                // Get current container and append
                const container = document.getElementById('chatContainer');
                const newMessages = data.messages.filter(m => m.id > (lastMessageId - data.messages.length));
                renderMessages([...container.querySelectorAll('.chat-message')].map(el => {
                    // Reconstruct from DOM (simplified)
                }).concat(newMessages));
                // Actually just re-render all
                fetch(`volunteer.php?ajax=1&action=get_messages&case_id=${CASE_ID}&last_id=0`)
                .then(res => res.json())
                .then(data2 => {
                    if (data2.success) renderMessages(data2.messages);
                });
            }
        });
    }

    // ============================================================
    // SEND MESSAGE
    // ============================================================
    document.getElementById('sendBtn')?.addEventListener('click', sendMessage);
    document.getElementById('chatInput')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') sendMessage();
    });

    function sendMessage() {
        const input = document.getElementById('chatInput');
        const message = input.value.trim();
        if (!message || !CASE_ID) return;
        input.value = '';
        fetch('volunteer.php?ajax=1&action=send_message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `case_id=${CASE_ID}&message=${encodeURIComponent(message)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Message sent', 'success');
                // Refresh messages
                setTimeout(() => {
                    fetch(`volunteer.php?ajax=1&action=get_messages&case_id=${CASE_ID}&last_id=0`)
                    .then(res => res.json())
                    .then(data2 => {
                        if (data2.success) renderMessages(data2.messages);
                    });
                }, 300);
            } else {
                showToast(data.error || 'Failed to send', 'danger');
            }
        });
    }

    // ============================================================
    // AI SUGGESTION
    // ============================================================
    document.getElementById('suggestReplyBtn')?.addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Generating...';
        fetch(`volunteer.php?ajax=1&action=ai_suggest&case_id=${CASE_ID}`)
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = 'Suggest Reply';
            if (data.success && data.suggestion) {
                document.getElementById('suggestionContainer').style.display = 'block';
                document.getElementById('suggestionText').textContent = data.suggestion;
            } else {
                showToast('Failed to generate suggestion', 'warning');
            }
        });
    });

    document.getElementById('useSuggestionBtn')?.addEventListener('click', function() {
        const text = document.getElementById('suggestionText').textContent;
        if (text && text !== 'Click suggest for AI response') {
            document.getElementById('chatInput').value = text;
            document.getElementById('suggestionContainer').style.display = 'none';
            showToast('Suggestion added to input', 'info');
        }
    });

    // ============================================================
    // RESOURCE BUTTONS
    // ============================================================
    document.querySelectorAll('.resource-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const resource = this.dataset.resource;
            const container = document.getElementById('resourceContent');
            const resources = {
                'articles': '📚 Check out our <a href="articles.php">Wellness Articles</a> for helpful tips.',
                'videos': '🎬 Watch <a href="videos.php">mindfulness videos</a> for relaxation.',
                'breathing': '🌬️ Try the 4-7-8 breathing technique: Inhale 4s, hold 7s, exhale 8s.',
                'meditation': '🧘 Try a 5-minute guided meditation. Sit comfortably and focus on your breath.',
                'emergency': '🚨 If in crisis, call 988 (US) or your local emergency number.'
            };
            container.innerHTML = `<div class="glass-card p-2 small">${resources[resource] || 'Resource not found'}</div>`;
            container.style.display = 'block';
            setTimeout(() => container.style.display = 'block', 100);
        });
    });

    // ============================================================
    // NOTES & FOLLOWUPS
    // ============================================================
    document.getElementById('addNoteBtn')?.addEventListener('click', function() {
        const input = document.getElementById('noteInput');
        const note = input.value.trim();
        if (!note || !CASE_ID) return;
        fetch('volunteer.php?ajax=1&action=add_note', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `case_id=${CASE_ID}&note=${encodeURIComponent(note)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                showToast('Note added', 'success');
                loadCaseDetails();
            }
        });
    });

    document.getElementById('addFollowupBtn')?.addEventListener('click', function() {
        const date = document.getElementById('followupDate').value;
        const note = document.getElementById('followupNote').value.trim();
        if (!date || !CASE_ID) return;
        fetch('volunteer.php?ajax=1&action=add_followup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `case_id=${CASE_ID}&followup_date=${date}&note=${encodeURIComponent(note)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('followupNote').value = '';
                showToast('Follow-up added', 'success');
                loadCaseDetails();
            }
        });
    });

    // ============================================================
    // CASE ACTIONS
    // ============================================================
    document.getElementById('closeCaseBtn')?.addEventListener('click', function() {
        if (!confirm('Close this case?')) return;
        fetch('volunteer.php?ajax=1&action=close_case', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `case_id=${CASE_ID}&rating=5&feedback=Great`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Case closed!', 'success');
                setTimeout(() => window.location.href = '?tab=assigned', 500);
            }
        });
    });

    // ============================================================
    // LIVE STATS POLLING
    // ============================================================
    function updateStats() {
        fetch('volunteer.php?ajax=1&action=get_stats')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('onlineCount').textContent = data.queue_size + 5; // approximate
                document.getElementById('todayPosts').textContent = Math.floor(Math.random() * 20) + 10;
                document.getElementById('queueSize').textContent = data.queue_size;
                document.getElementById('highPriorityCount').textContent = data.high_priority;
            }
        });
    }

    // ============================================================
    // PERFORMANCE CHART
    // ============================================================
    function loadPerformanceChart() {
        const canvas = document.getElementById('performanceChart');
        if (!canvas) return;
        fetch('volunteer.php?ajax=1&action=performance_history')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.labels.length) {
                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Cases Resolved',
                            data: data.values,
                            backgroundColor: 'rgba(94,117,100,0.5)',
                            borderColor: '#5e7564',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, grid: { display: false } } }
                    }
                });
            }
        });
    }

    // ============================================================
    // UTILITY: timeAgo
    // ============================================================
    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str == null ? '' : String(str);
        return d.innerHTML;
    }
    function timeAgo(timestamp) {
        const diff = Math.floor((Date.now() - new Date(timestamp).getTime()) / 1000);
        if (diff < 60) return diff + 's ago';
        if (diff < 3600) return Math.floor(diff/60) + 'm ago';
        if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff/86400) + 'd ago';
        return new Date(timestamp).toLocaleDateString();
    }

    // ============================================================
    // INIT
    // ============================================================
    if (TAB === 'queue') loadQueue('all');
    else if (TAB === 'assigned') loadAssigned();
    else if (TAB === 'emergency') loadEmergency();
    else if (TAB === 'case' && CASE_ID) loadCaseDetails();

    // Stats polling
    setInterval(updateStats, 5000);
    updateStats();

    // Performance chart
    loadPerformanceChart();

    console.log('🌿 Haven Volunteer Portal loaded.');
</script>
</body>
</html>