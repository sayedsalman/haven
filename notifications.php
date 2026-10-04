<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// Mark all as read
if (isset($_GET['mark_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header('Location: notifications.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

$typeIcons = [
    'checkin_reminder' => '🌱', 'weekly_insight' => '🧠', 'new_comment' => '💬', 'comment_reply' => '💬',
    'support' => '💙', 'friend_request' => '🤝', 'friend_accept' => '🤝', 'badge' => '🏆',
    'consultation' => '🩺', 'consultation_message' => '🩺', 'volunteer_assigned' => '🩺', 'volunteer_message' => '🩺',
    'case_closed' => '🩺', 'warning' => '⚠️', 'report' => '🚩', 'goal_reminder' => '🎯',
];
function notifIcon($type, $map) { return $map[$type] ?? '🔔'; }

$pageTitle = 'Notifications';
include 'includes/header.php';
?>
<style>
.notif-item { display: flex; gap: 12px; align-items: flex-start; }
.notif-icon { font-size: 1.3rem; width: 36px; height: 36px; border-radius: 10px; background: rgba(255,255,255,.6); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
</style>
<div class="glass-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Notifications</h2>
        <a href="?mark_read=1" class="btn btn-sm btn-outline-secondary">Mark all as read</a>
    </div>
    <?php if (empty($notifications)): ?>
        <p class="text-muted">No notifications yet.</p>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <div class="glass-card p-3 mb-2 notif-item <?= $n['is_read'] ? '' : 'border-start border-primary border-4' ?>">
                <span class="notif-icon"><?= notifIcon($n['type'], $typeIcons) ?></span>
                <div class="flex-grow-1">
                    <p class="mb-1"><?= escape($n['message']) ?></p>
                    <small class="text-muted"><?= timeAgo($n['created_at']) ?></small>
                    <?php if ($n['link']): ?>
                        <a href="<?= escape($n['link']) ?>" class="btn btn-sm btn-primary ms-2">View</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>