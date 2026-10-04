<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';
include 'includes/badges.php';

$user_id = $_SESSION['user_id'];

$pdo->exec("CREATE TABLE IF NOT EXISTS wellness_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type VARCHAR(40) NOT NULL,
    detail VARCHAR(255) DEFAULT NULL,
    completed_at DATETIME NOT NULL,
    INDEX (user_id, completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function jr_w($a, $s = 200) {
    http_response_code($s);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($a);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'log_activity') {
        $type = trim($_POST['type'] ?? '');
        $detail = trim($_POST['detail'] ?? '');
        $allowed = ['breathing', 'gratitude', 'relaxation'];
        if (!in_array($type, $allowed, true)) jr_w(['ok' => false], 400);
        $pdo->prepare("INSERT INTO wellness_activities (user_id, activity_type, detail, completed_at) VALUES (?, ?, ?, NOW())")
            ->execute([$user_id, $type, $detail ?: null]);
        havenCheckAndAwardBadges($pdo, $user_id);
        jr_w(['ok' => true]);
    }
    jr_w(['ok' => false], 400);
}

$weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
$q = $pdo->prepare("SELECT activity_type, COUNT(*) c FROM wellness_activities WHERE user_id = ? AND completed_at >= ? GROUP BY activity_type");
$q->execute([$user_id, $weekStart]);
$weekCounts = [];
foreach ($q->fetchAll() as $r) { $weekCounts[$r['activity_type']] = (int)$r['c']; }
$totalThisWeek = array_sum($weekCounts);

$gratitudePrompts = [
    'Who is someone that made your day a little easier recently, and what did they do?',
    'What is something small — a sound, a taste, a moment of quiet — that brought you comfort today?',
    'What is something about your own body or mind that you are quietly grateful for?',
];

$pageTitle = 'Wellness Center';
include 'includes/header.php';
?>
<style>
.wc-wrap { max-width: 720px; margin: 0 auto; }
.wc-hero { text-align: center; margin-bottom: 24px; }
.wc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 14px; margin-bottom: 30px; }
.wc-card { background: var(--card); border-radius: 20px; padding: 20px; text-align: center; cursor: pointer; transition: .2s; border: 2px solid transparent; }
.wc-card:hover { transform: translateY(-3px); }
.wc-card.active { border-color: var(--sage-dark); }
.wc-card .icon { font-size: 2rem; }
.wc-card .name { font-weight: 700; margin-top: 6px; }
.wc-card .dur { color: var(--muted); font-size: .78rem; }
.wc-panel { background: var(--card); border-radius: 22px; padding: 30px; text-align: center; display: none; }
.wc-panel.open { display: block; }
.breath-circle { width: 180px; height: 180px; border-radius: 50%; background: radial-gradient(circle, var(--sage-soft), var(--sage)); margin: 20px auto; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; transition: transform 4s ease-in-out; transform: scale(0.6); }
.breath-circle.inhale { transform: scale(1.15); }
.breath-circle.exhale { transform: scale(0.6); }
.grat-step { display: none; }
.grat-step.active { display: block; }
.wc-week-summary { display: flex; justify-content: center; gap: 20px; margin-top: 30px; font-size: .85rem; color: var(--muted); }
</style>

<div class="wc-wrap">
    <div class="wc-hero">
        <h3><i class="bi bi-flower1"></i> Haven Wellness Center</h3>
        <p class="text-muted">A few minutes of intentional calm, whenever you need it.</p>
    </div>

    <div class="wc-grid">
        <div class="wc-card" data-panel="breathing"><div class="icon">🫁</div><div class="name">Breathing</div><div class="dur">4-4 breathing · 5 min</div></div>
        <div class="wc-card" data-panel="gratitude"><div class="icon">🙏</div><div class="name">Gratitude</div><div class="dur">Reflection · 3 min</div></div>
        <div class="wc-card" data-panel="relaxation"><div class="icon">🌙</div><div class="name">Relaxation</div><div class="dur">Guided · 10 min</div></div>
        <a href="focus.php" class="wc-card text-decoration-none text-dark"><div class="icon">⏱️</div><div class="name">Focus Session</div><div class="dur">Study · 25 min</div></a>
    </div>

    <!-- Breathing -->
    <div class="wc-panel" id="panel-breathing">
        <h5>4-4 Breathing</h5>
        <p class="text-muted small">Breathe in for 4 seconds, out for 4 seconds. Follow the circle.</p>
        <div class="breath-circle" id="breathCircle">Breathe</div>
        <div id="breathStatus" class="text-muted small mb-3">Press start when you're ready</div>
        <button class="btn btn-primary" id="breathStart">Start</button>
        <button class="btn btn-outline-secondary d-none" id="breathStop">Stop</button>
    </div>

    <!-- Gratitude -->
    <div class="wc-panel" id="panel-gratitude">
        <h5>Gratitude Reflection</h5>
        <?php foreach ($gratitudePrompts as $i => $prompt): ?>
        <div class="grat-step <?= $i === 0 ? 'active' : '' ?>" data-step="<?= $i ?>">
            <p class="mt-3"><?= escape($prompt) ?></p>
            <textarea class="form-control mb-3 grat-input" rows="3" placeholder="Take a moment to write..."></textarea>
            <button class="btn btn-primary grat-next" data-next="<?= $i + 1 ?>"><?= $i < count($gratitudePrompts) - 1 ? 'Next' : 'Finish' ?></button>
        </div>
        <?php endforeach; ?>
        <div class="grat-step" data-step="<?= count($gratitudePrompts) ?>">
            <p class="mt-3">🌿 Thank you for taking that moment. Noticing these things — even briefly — can shift how the rest of your day feels.</p>
        </div>
    </div>

    <!-- Relaxation -->
    <div class="wc-panel" id="panel-relaxation">
        <h5>Guided Relaxation</h5>
        <p class="text-muted small">A short body-scan. Get comfortable, and read at your own pace.</p>
        <div id="relaxScript" class="text-start mt-3" style="font-size:.95rem;line-height:1.9;"></div>
        <button class="btn btn-primary mt-3" id="relaxStart">Begin</button>
    </div>

    <div class="wc-week-summary">
        <span>🫁 <?= $weekCounts['breathing'] ?? 0 ?> breathing sessions this week</span>
        <span>🙏 <?= $weekCounts['gratitude'] ?? 0 ?> gratitude reflections</span>
        <span>🌙 <?= $weekCounts['relaxation'] ?? 0 ?> relaxation sessions</span>
    </div>
</div>

<script>
document.querySelectorAll('.wc-card[data-panel]').forEach(card => {
    card.addEventListener('click', () => {
        document.querySelectorAll('.wc-panel').forEach(p => p.classList.remove('open'));
        document.querySelectorAll('.wc-card[data-panel]').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        document.getElementById('panel-' + card.dataset.panel).classList.add('open');
        document.getElementById('panel-' + card.dataset.panel).scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

async function logActivity(type, detail) {
    const fd = new FormData();
    fd.append('action', 'log_activity'); fd.append('type', type); fd.append('detail', detail || '');
    try { await fetch('wellness.php', { method: 'POST', body: fd }); } catch (e) {}
}

// ---- Breathing ----
let breathInterval = null;
const circle = document.getElementById('breathCircle');
const breathStatus = document.getElementById('breathStatus');
const breathStart = document.getElementById('breathStart');
const breathStop = document.getElementById('breathStop');
let breathCycles = 0;

breathStart.addEventListener('click', () => {
    breathStart.classList.add('d-none');
    breathStop.classList.remove('d-none');
    breathCycles = 0;
    runBreathCycle();
    breathInterval = setInterval(runBreathCycle, 8000);
});
function runBreathCycle() {
    circle.classList.remove('exhale'); circle.classList.add('inhale');
    circle.textContent = 'Breathe in…';
    breathStatus.textContent = 'Inhale for 4 seconds';
    setTimeout(() => {
        circle.classList.remove('inhale'); circle.classList.add('exhale');
        circle.textContent = 'Breathe out…';
        breathStatus.textContent = 'Exhale for 4 seconds';
    }, 4000);
    breathCycles++;
    if (breathCycles >= 15) stopBreathing(true); // ~2 minutes minimum before auto-complete offer
}
breathStop.addEventListener('click', () => stopBreathing(breathCycles >= 5));
function stopBreathing(logIt) {
    clearInterval(breathInterval);
    breathStart.classList.remove('d-none');
    breathStop.classList.add('d-none');
    circle.textContent = 'Breathe';
    circle.className = 'breath-circle';
    breathStatus.textContent = logIt ? '✅ Session logged. Well done.' : 'Press start when you\'re ready';
    if (logIt) logActivity('breathing', breathCycles + ' cycles');
}

// ---- Gratitude ----
document.querySelectorAll('.grat-next').forEach(btn => {
    btn.addEventListener('click', () => {
        const current = btn.closest('.grat-step');
        const next = document.querySelector('.grat-step[data-step="' + btn.dataset.next + '"]');
        current.classList.remove('active');
        if (next) next.classList.add('active');
        if (!next.querySelector('.grat-next')) {
            const answers = [...document.querySelectorAll('.grat-input')].map(t => t.value).filter(Boolean).join(' | ');
            logActivity('gratitude', answers.slice(0, 250));
        }
    });
});

// ---- Relaxation ----
const relaxSteps = [
    "Find a comfortable position, sitting or lying down. Let your shoulders drop.",
    "Close your eyes if that feels comfortable, or soften your gaze.",
    "Notice your feet. Let them feel heavy and relaxed.",
    "Bring attention to your legs. Let any tension melt away.",
    "Notice your stomach and chest rising and falling with each breath.",
    "Relax your hands, your arms, your shoulders.",
    "Soften your jaw, your forehead, the space around your eyes.",
    "Take one more slow breath in, and out. When you're ready, gently open your eyes."
];
document.getElementById('relaxStart').addEventListener('click', function() {
    this.classList.add('d-none');
    const box = document.getElementById('relaxScript');
    let i = 0;
    box.innerHTML = '';
    const interval = setInterval(() => {
        if (i >= relaxSteps.length) {
            clearInterval(interval);
            box.innerHTML += '<p class="text-success mt-3">🌙 Session complete. Well done.</p>';
            logActivity('relaxation', relaxSteps.length + ' steps');
            return;
        }
        const p = document.createElement('p');
        p.textContent = relaxSteps[i];
        p.style.opacity = 0;
        box.appendChild(p);
        setTimeout(() => { p.style.transition = 'opacity 1s'; p.style.opacity = 1; }, 50);
        i++;
    }, 6000);
});
</script>
<?php include 'includes/footer.php'; ?>
