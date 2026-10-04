<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';
include 'includes/badges.php';

$user_id = $_SESSION['user_id'];

// Self-healing: create the goals table on first use.
$pdo->exec("CREATE TABLE IF NOT EXISTS goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    metric ENUM('mood_checkin','journal_entry','focus_session','custom') NOT NULL DEFAULT 'custom',
    period ENUM('daily','weekly') NOT NULL DEFAULT 'weekly',
    target_count INT NOT NULL DEFAULT 1,
    manual_progress INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function jr_g($a, $s = 200) {
    http_response_code($s);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($a);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $title = trim($_POST['title'] ?? '');
        $metric = $_POST['metric'] ?? 'custom';
        $period = $_POST['period'] ?? 'weekly';
        $target = max(1, min(30, (int)($_POST['target'] ?? 3)));
        if (!in_array($metric, ['mood_checkin','journal_entry','focus_session','custom'], true)) $metric = 'custom';
        if (!in_array($period, ['daily','weekly'], true)) $period = 'weekly';
        if ($title === '') jr_g(['ok' => false, 'message' => 'Please name your goal.']);
        $pdo->prepare("INSERT INTO goals (user_id, title, metric, period, target_count, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
            ->execute([$user_id, $title, $metric, $period, $target]);
        jr_g(['ok' => true]);
    }
    if ($_POST['action'] === 'increment') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE goals SET manual_progress = manual_progress + 1 WHERE id = ? AND user_id = ? AND metric = 'custom'")->execute([$id, $user_id]);
        jr_g(['ok' => true]);
    }
    if ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE goals SET is_active = 0 WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
        jr_g(['ok' => true]);
    }
    jr_g(['ok' => false], 400);
}

// Compute real progress for each active goal.
$goals = $pdo->prepare("SELECT * FROM goals WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC");
$goals->execute([$user_id]);
$goals = $goals->fetchAll();

$hasEntries = $pdo->query("SHOW TABLES LIKE 'mood_entries'")->rowCount() > 0;
$moodTable = $hasEntries ? 'mood_entries' : 'mood_logs';
$moodDateCol = $hasEntries ? 'entry_date' : 'log_date';
$hasJournals = $pdo->query("SHOW TABLES LIKE 'journals'")->rowCount() > 0;
$hasFocus = $pdo->query("SHOW TABLES LIKE 'focus_sessions'")->rowCount() > 0;

foreach ($goals as &$g) {
    $since = $g['period'] === 'daily' ? date('Y-m-d 00:00:00') : date('Y-m-d 00:00:00', strtotime('monday this week'));
    $count = 0;
    if ($g['metric'] === 'mood_checkin') {
        $q = $pdo->prepare("SELECT COUNT(*) FROM $moodTable WHERE user_id = ? AND $moodDateCol >= ?");
        $q->execute([$user_id, substr($since, 0, 10)]);
        $count = (int)$q->fetchColumn();
    } elseif ($g['metric'] === 'journal_entry' && $hasJournals) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM journals WHERE user_id = ? AND entry_date >= ?");
        $q->execute([$user_id, substr($since, 0, 10)]);
        $count = (int)$q->fetchColumn();
    } elseif ($g['metric'] === 'focus_session' && $hasFocus) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM focus_sessions WHERE user_id = ? AND completed = 1 AND started_at >= ?");
        $q->execute([$user_id, $since]);
        $count = (int)$q->fetchColumn();
    } else {
        $count = (int)$g['manual_progress'];
    }
    $g['current_count'] = $count;
    $g['percent'] = min(100, round(($count / max(1, $g['target_count'])) * 100));
}
unset($g);

$metricLabels = ['mood_checkin' => 'Mood check-ins', 'journal_entry' => 'Journal entries', 'focus_session' => 'Focus sessions', 'custom' => 'Manual tracking'];

$pageTitle = 'My Goals';
include 'includes/header.php';
?>
<style>
.goals-wrap { max-width: 640px; margin: 0 auto; }
.goal-card { background: var(--card); border-radius: 18px; padding: 18px; margin-bottom: 14px; }
.goal-card .title { font-weight: 700; }
.goal-bar-track { background: var(--cream-2); border-radius: 999px; height: 12px; margin: 10px 0 6px; overflow: hidden; }
.goal-bar-fill { background: var(--sage-dark); height: 100%; border-radius: 999px; transition: width .4s ease; }
.goal-meta { display: flex; justify-content: space-between; font-size: .78rem; color: var(--muted); }
.goal-card.complete .goal-bar-fill { background: #d9a441; }
</style>

<div class="goals-wrap">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <h3 class="mb-0"><i class="bi bi-bullseye"></i> My Goals</h3>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addGoalModal"><i class="bi bi-plus-lg"></i> New Goal</button>
    </div>
    <p class="text-muted mb-4">Small, trackable goals — tied to what you actually do in Haven.</p>

    <?php if (empty($goals)): ?>
        <div class="glass-card p-4 text-center text-muted">No goals yet. Create one to start tracking.</div>
    <?php endif; ?>

    <?php foreach ($goals as $g): ?>
        <div class="goal-card <?= $g['percent'] >= 100 ? 'complete' : '' ?>" data-id="<?= $g['id'] ?>">
            <div class="d-flex justify-content-between">
                <span class="title"><?= $g['percent'] >= 100 ? '✅ ' : '' ?><?= escape($g['title']) ?></span>
                <button class="btn btn-sm text-muted delete-goal" data-id="<?= $g['id'] ?>"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="goal-bar-track"><div class="goal-bar-fill" style="width:<?= $g['percent'] ?>%"></div></div>
            <div class="goal-meta">
                <span><?= $metricLabels[$g['metric']] ?> · <?= $g['current_count'] ?>/<?= $g['target_count'] ?> this <?= $g['period'] ?></span>
                <span><?= $g['percent'] ?>%</span>
            </div>
            <?php if ($g['metric'] === 'custom' && $g['percent'] < 100): ?>
                <button class="btn btn-sm btn-outline-primary mt-2 increment-btn" data-id="<?= $g['id'] ?>">+1 progress</button>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="addGoalModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="addGoalForm">
            <div class="modal-header"><h5 class="modal-title">New Goal</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">Goal title</label><input type="text" name="title" class="form-control" placeholder="e.g., Journal regularly" required></div>
                <div class="mb-2"><label class="form-label">Track automatically by</label>
                    <select name="metric" class="form-select">
                        <option value="mood_checkin">Mood check-ins</option>
                        <option value="journal_entry">Journal entries</option>
                        <option value="focus_session">Focus sessions</option>
                        <option value="custom">Manual (I'll update it myself)</option>
                    </select>
                </div>
                <div class="row">
                    <div class="col-6 mb-2"><label class="form-label">Target</label><input type="number" name="target" class="form-control" value="3" min="1" max="30"></div>
                    <div class="col-6 mb-2"><label class="form-label">Per</label><select name="period" class="form-select"><option value="weekly">Week</option><option value="daily">Day</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary w-100">Create Goal</button></div>
        </form>
    </div>
</div>

<script>
document.getElementById('addGoalForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target); fd.append('action', 'create');
    const r = await fetch('goals.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) location.reload(); else alert(d.message || 'Could not create goal.');
});
document.querySelectorAll('.increment-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const fd = new FormData(); fd.append('action', 'increment'); fd.append('id', btn.dataset.id);
        await fetch('goals.php', { method: 'POST', body: fd });
        location.reload();
    });
});
document.querySelectorAll('.delete-goal').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Remove this goal?')) return;
        const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', btn.dataset.id);
        await fetch('goals.php', { method: 'POST', body: fd });
        location.reload();
    });
});
</script>
<?php include 'includes/footer.php'; ?>
