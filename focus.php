<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';
include 'includes/badges.php';

$user_id = $_SESSION['user_id'];

function jr_f($a, $s = 200) {
    http_response_code($s);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($a);
    exit;
}

// Self-healing: create a lightweight focus_sessions table the first time
// this page is used, rather than requiring a separate migration step.
$pdo->exec("CREATE TABLE IF NOT EXISTS focus_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    duration_minutes INT NOT NULL,
    completed TINYINT(1) DEFAULT 1,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NOT NULL,
    INDEX (user_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'log_session') {
        $minutes = max(1, min(180, (int)($_POST['minutes'] ?? 25)));
        $completed = !empty($_POST['completed']) ? 1 : 0;
        $stmt = $pdo->prepare("INSERT INTO focus_sessions (user_id, duration_minutes, completed, started_at, ended_at) VALUES (?, ?, ?, DATE_SUB(NOW(), INTERVAL ? MINUTE), NOW())");
        $stmt->execute([$user_id, $minutes, $completed, $minutes]);
        if ($completed) havenCheckAndAwardBadges($pdo, $user_id);
        jr_f(['ok' => true]);
    }
    jr_f(['ok' => false], 400);
}

$weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
$q = $pdo->prepare("SELECT COALESCE(SUM(duration_minutes),0) mins, COUNT(*) sessions FROM focus_sessions WHERE user_id = ? AND started_at >= ? AND completed = 1");
$q->execute([$user_id, $weekStart]);
$weekStats = $q->fetch();
$weekMinutes = (int)$weekStats['mins'];
$weekSessions = (int)$weekStats['sessions'];
$weekHours = floor($weekMinutes / 60);
$weekRemainMin = $weekMinutes % 60;

$recentSessions = $pdo->prepare("SELECT duration_minutes, ended_at FROM focus_sessions WHERE user_id = ? AND completed = 1 ORDER BY ended_at DESC LIMIT 8");
$recentSessions->execute([$user_id]);
$recentSessions = $recentSessions->fetchAll();

$pageTitle = 'Focus Timer';
include 'includes/header.php';
?>
<style>
.focus-wrap { max-width: 560px; margin: 0 auto; text-align: center; }
.focus-ring-wrap { position: relative; width: 260px; height: 260px; margin: 30px auto; }
.focus-ring-wrap svg { transform: rotate(-90deg); width: 100%; height: 100%; }
.focus-ring-bg { fill: none; stroke: var(--line); stroke-width: 10; }
.focus-ring-progress { fill: none; stroke: var(--sage-dark); stroke-width: 10; stroke-linecap: round; transition: stroke-dashoffset 1s linear; }
.focus-time { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-family: 'Playfair Display', serif; font-size: 2.6rem; color: var(--ink); }
.focus-label { color: var(--muted); font-weight: 700; letter-spacing: .08em; text-transform: uppercase; font-size: .75rem; margin-top: -10px; }
.focus-modes { display: flex; gap: 8px; justify-content: center; margin: 18px 0; }
.focus-modes button { border: 1px solid var(--line); background: rgba(255,255,255,.5); border-radius: 12px; padding: 8px 16px; font-weight: 600; font-size: .85rem; }
.focus-modes button.active { background: var(--sage-dark); color: #fff; border-color: var(--sage-dark); }
.focus-controls { display: flex; gap: 10px; justify-content: center; margin-top: 10px; }
.focus-controls button { border-radius: 14px; padding: 12px 28px; font-weight: 700; border: none; cursor: pointer; }
.focus-start { background: var(--sage-dark); color: #fff; }
.focus-reset { background: var(--cream-2); color: var(--ink); }
.focus-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-top: 34px; }
.focus-stat-card { background: var(--card); border-radius: 18px; padding: 18px; }
.focus-stat-card .num { font-family: 'Playfair Display', serif; font-size: 1.8rem; color: var(--ink); }
.focus-stat-card .lbl { color: var(--muted); font-size: .78rem; margin-top: 4px; }
.focus-history { text-align: left; margin-top: 24px; }
.focus-history-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--line); font-size: .85rem; }
</style>

<div class="focus-wrap">
    <h3><i class="bi bi-stopwatch"></i> Focus Session</h3>
    <p class="text-muted">A distraction-free timer for studying, working, or any task that needs your full attention.</p>

    <div class="focus-modes">
        <button type="button" class="mode-btn active" data-min="25">25 min</button>
        <button type="button" class="mode-btn" data-min="15">15 min</button>
        <button type="button" class="mode-btn" data-min="50">50 min</button>
    </div>

    <div class="focus-ring-wrap">
        <svg viewBox="0 0 120 120">
            <circle class="focus-ring-bg" cx="60" cy="60" r="52"></circle>
            <circle class="focus-ring-progress" id="ring" cx="60" cy="60" r="52" stroke-dasharray="326.7" stroke-dashoffset="0"></circle>
        </svg>
        <div class="focus-time" id="timeDisplay">25:00</div>
    </div>
    <div class="focus-label" id="statusLabel">Ready when you are</div>

    <div class="focus-controls">
        <button class="focus-start" id="startBtn"><i class="bi bi-play-fill"></i> Start Focus Session</button>
        <button class="focus-reset" id="resetBtn" style="display:none;"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
    </div>

    <div class="focus-stats">
        <div class="focus-stat-card"><div class="num"><?= $weekHours ?>h <?= $weekRemainMin ?>m</div><div class="lbl">Focused this week</div></div>
        <div class="focus-stat-card"><div class="num"><?= $weekSessions ?></div><div class="lbl">Sessions this week</div></div>
    </div>

    <?php if (!empty($recentSessions)): ?>
    <div class="focus-history">
        <h6 class="text-muted">Recent sessions</h6>
        <?php foreach ($recentSessions as $s): ?>
            <div class="focus-history-item">
                <span><?= (int)$s['duration_minutes'] ?> min focus session</span>
                <span class="text-muted"><?= timeAgo($s['ended_at']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
let totalSeconds = 25 * 60;
let remaining = totalSeconds;
let timerInterval = null;
let running = false;
const circumference = 326.7;
const ring = document.getElementById('ring');
const timeDisplay = document.getElementById('timeDisplay');
const statusLabel = document.getElementById('statusLabel');
const startBtn = document.getElementById('startBtn');
const resetBtn = document.getElementById('resetBtn');

document.querySelectorAll('.mode-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        if (running) return;
        document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        totalSeconds = parseInt(btn.dataset.min) * 60;
        remaining = totalSeconds;
        updateDisplay();
    });
});

function updateDisplay() {
    const m = Math.floor(remaining / 60).toString().padStart(2, '0');
    const s = (remaining % 60).toString().padStart(2, '0');
    timeDisplay.textContent = `${m}:${s}`;
    const progress = (totalSeconds - remaining) / totalSeconds;
    ring.style.strokeDashoffset = circumference * (1 - (1 - progress));
}

function tick() {
    remaining--;
    updateDisplay();
    if (remaining <= 0) {
        clearInterval(timerInterval);
        running = false;
        statusLabel.textContent = '🎉 Focus session completed!';
        logSession(Math.round(totalSeconds / 60), true);
        startBtn.innerHTML = '<i class="bi bi-play-fill"></i> Start another session';
        resetBtn.style.display = 'none';
    }
}

startBtn.addEventListener('click', () => {
    if (running) {
        clearInterval(timerInterval);
        running = false;
        statusLabel.textContent = 'Paused';
        startBtn.innerHTML = '<i class="bi bi-play-fill"></i> Resume';
    } else {
        running = true;
        statusLabel.textContent = 'Focus session in progress…';
        startBtn.innerHTML = '<i class="bi bi-pause-fill"></i> Pause';
        resetBtn.style.display = 'inline-block';
        timerInterval = setInterval(tick, 1000);
    }
});

resetBtn.addEventListener('click', () => {
    clearInterval(timerInterval);
    running = false;
    remaining = totalSeconds;
    updateDisplay();
    statusLabel.textContent = 'Ready when you are';
    startBtn.innerHTML = '<i class="bi bi-play-fill"></i> Start Focus Session';
    resetBtn.style.display = 'none';
});

async function logSession(minutes, completed) {
    const fd = new FormData();
    fd.append('action', 'log_session');
    fd.append('minutes', minutes);
    fd.append('completed', completed ? '1' : '');
    try { await fetch('focus.php', { method: 'POST', body: fd }); } catch (e) {}
}

updateDisplay();
</script>
<?php include 'includes/footer.php'; ?>
