<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';
include 'includes/badges.php';

$user_id = $_SESSION['user_id'];
$csrf = $_SESSION['haven_csrf'] ?? ($_SESSION['haven_csrf'] = bin2hex(random_bytes(32)));

$moodMap = [
    'happy'    => ['emoji' => '😊', 'label' => 'Happy',    'color' => '#d9a441'],
    'calm'     => ['emoji' => '😌', 'label' => 'Calm',     'color' => '#5e7564'],
    'okay'     => ['emoji' => '🙂', 'label' => 'Okay',     'color' => '#879d8b'],
    'sad'      => ['emoji' => '😔', 'label' => 'Sad',      'color' => '#7c9bbd'],
    'stressed' => ['emoji' => '😰', 'label' => 'Stressed', 'color' => '#c9a76b'],
    'anxious'  => ['emoji' => '😟', 'label' => 'Anxious',  'color' => '#c9a76b'],
    'angry'    => ['emoji' => '😡', 'label' => 'Angry',    'color' => '#c96a63'],
    'tired'    => ['emoji' => '😴', 'label' => 'Tired',    'color' => '#9c8fb0'],
];

function jr_m($a, $s = 200) {
    http_response_code($s);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($a);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $emotion = $_POST['emotion'] ?? 'okay';
    if (!isset($GLOBALS['moodMap'][$emotion])) $emotion = 'okay';
    $energy   = max(1, min(10, (int)($_POST['energy'] ?? 5)));
    $stress   = max(1, min(10, (int)($_POST['stress'] ?? 5)));
    $sleep    = max(1, min(10, (int)($_POST['sleep'] ?? 5)));
    $exercise = max(1, min(10, (int)($_POST['exercise'] ?? 5)));
    $note     = trim($_POST['note'] ?? '');
    $date     = $_POST['date'] ?? date('Y-m-d');

    $hasEntries = $pdo->query("SHOW TABLES LIKE 'mood_entries'")->rowCount() > 0;
    if ($hasEntries) {
        $stmt = $pdo->prepare("INSERT INTO mood_entries (user_id, emotion, energy, stress, sleep, exercise, medication, note, entry_date)
                                VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)
                                ON DUPLICATE KEY UPDATE emotion=VALUES(emotion), energy=VALUES(energy), stress=VALUES(stress),
                                sleep=VALUES(sleep), exercise=VALUES(exercise), note=VALUES(note)");
        $stmt->execute([$user_id, $emotion, $energy, $stress, $sleep, $exercise, $note, $date]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO mood_logs (user_id, mood_value, energy, stress, sleep, log_date)
                                VALUES (?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE mood_value=VALUES(mood_value), energy=VALUES(energy), stress=VALUES(stress), sleep=VALUES(sleep)");
        $stmt->execute([$user_id, $emotion, $energy, $stress, $sleep, $date]);
    }
    havenCheckAndAwardBadges($pdo, $user_id);
    jr_m(['ok' => true, 'message' => 'Check-in saved.']);
}
$hasEntries = $pdo->query("SHOW TABLES LIKE 'mood_entries'")->rowCount() > 0;
if ($hasEntries) {
    $stmt = $pdo->prepare("SELECT emotion, energy, stress, sleep, exercise, note, entry_date FROM mood_entries WHERE user_id = ? ORDER BY entry_date DESC LIMIT 60");
    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT mood_value AS emotion, energy, stress, sleep, NULL AS exercise, NULL AS note, log_date AS entry_date FROM mood_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT 60");
    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$today = date('Y-m-d');
$todayEntry = null;
foreach ($history as $h) { if ($h['entry_date'] === $today) { $todayEntry = $h; break; } }

// ============================================================
// MOOD CALENDAR — data for the requested (or current) month
// ============================================================
$calMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $calMonth)) $calMonth = date('Y-m');
$calStart = $calMonth . '-01';
$calEnd = date('Y-m-t', strtotime($calStart));
if ($hasEntries) {
    $stmt = $pdo->prepare("SELECT emotion, entry_date, note FROM mood_entries WHERE user_id = ? AND entry_date BETWEEN ? AND ?");
    $stmt->execute([$user_id, $calStart, $calEnd]);
} else {
    $stmt = $pdo->prepare("SELECT mood_value AS emotion, log_date AS entry_date, NULL AS note FROM mood_logs WHERE user_id = ? AND log_date BETWEEN ? AND ?");
    $stmt->execute([$user_id, $calStart, $calEnd]);
}
$calRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$calByDay = [];
foreach ($calRows as $r) { $calByDay[$r['entry_date']] = $r; }
$prevMonth = date('Y-m', strtotime($calStart . ' -1 month'));
$nextMonth = date('Y-m', strtotime($calStart . ' +1 month'));

// ============================================================
// MOOD STATISTICS
// ============================================================
$moodScoreMap = ['happy'=>9,'calm'=>8,'okay'=>6,'tired'=>5,'sad'=>4,'anxious'=>4,'stressed'=>3,'angry'=>3];
$allForStats = $history; // up to 60 most recent entries
$statsAvailable = count($allForStats) >= 1;
$mostCommonMood = null; $bestDay = null; $worstDay = null; $consistency = null; $weekTrend = null; $monthTrend = null;

if ($statsAvailable) {
    $freq = [];
    foreach ($allForStats as $h) { $freq[$h['emotion']] = ($freq[$h['emotion']] ?? 0) + 1; }
    arsort($freq);
    $mostCommonMood = array_key_first($freq);

    $scored = array_map(fn($h) => ['date' => $h['entry_date'], 'score' => $moodScoreMap[$h['emotion']] ?? 6, 'emotion' => $h['emotion']], $allForStats);
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    $bestDay = $scored[0];
    $worstDay = end($scored);

    // Consistency: % of last 30 days with a logged check-in
    $last30 = array_filter($allForStats, fn($h) => strtotime($h['entry_date']) >= strtotime('-30 days'));
    $uniqueDays30 = count(array_unique(array_column($last30, 'entry_date')));
    $consistency = round(($uniqueDays30 / 30) * 100);

    // Weekly trend: this week's avg score vs last week's
    $thisWeekStart = strtotime('monday this week');
    $lastWeekStart = strtotime('-1 week', $thisWeekStart);
    $thisWeekScores = array_map(fn($h) => $moodScoreMap[$h['emotion']] ?? 6, array_filter($allForStats, fn($h) => strtotime($h['entry_date']) >= $thisWeekStart));
    $lastWeekScores = array_map(fn($h) => $moodScoreMap[$h['emotion']] ?? 6, array_filter($allForStats, fn($h) => strtotime($h['entry_date']) >= $lastWeekStart && strtotime($h['entry_date']) < $thisWeekStart));
    if (count($thisWeekScores) && count($lastWeekScores)) {
        $diff = (array_sum($thisWeekScores)/count($thisWeekScores)) - (array_sum($lastWeekScores)/count($lastWeekScores));
        $weekTrend = $diff >= 0.4 ? 'up' : ($diff <= -0.4 ? 'down' : 'flat');
    }
    // Monthly trend: this month vs last month
    $thisMonthScores = array_map(fn($h) => $moodScoreMap[$h['emotion']] ?? 6, array_filter($allForStats, fn($h) => substr($h['entry_date'],0,7) === date('Y-m')));
    $lastMonthScores = array_map(fn($h) => $moodScoreMap[$h['emotion']] ?? 6, array_filter($allForStats, fn($h) => substr($h['entry_date'],0,7) === date('Y-m', strtotime('-1 month'))));
    if (count($thisMonthScores) && count($lastMonthScores)) {
        $diff2 = (array_sum($thisMonthScores)/count($thisMonthScores)) - (array_sum($lastMonthScores)/count($lastMonthScores));
        $monthTrend = $diff2 >= 0.4 ? 'up' : ($diff2 <= -0.4 ? 'down' : 'flat');
    }
}

$activeView = $_GET['view'] ?? 'checkin';

$pageTitle = 'Mood Tracker';
include 'includes/header.php';
?>
<style>
.mt-wrap { max-width: 760px; margin: 0 auto; }
.mood-picker { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; }
@media (max-width: 480px) { .mood-picker { grid-template-columns: repeat(4, 1fr); gap: 6px; } }
.mood-opt { border: 2px solid transparent; background: rgba(255,255,255,.5); border-radius: 16px; padding: 12px 6px; text-align: center; cursor: pointer; transition: .15s; }
.mood-opt .e { font-size: 1.7rem; display: block; }
.mood-opt .l { font-size: .68rem; font-weight: 600; color: var(--muted); margin-top: 4px; display: block; }
.mood-opt.selected { border-color: var(--sage-dark); background: var(--sage-soft); transform: translateY(-2px); }
.mt-slider-row { margin-bottom: 16px; }
.mt-slider-row .top { display: flex; justify-content: space-between; font-size: .82rem; font-weight: 600; margin-bottom: 4px; }
.mt-slider-row input[type=range] { width: 100%; accent-color: var(--sage-dark); }
.history-list { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
.history-item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 14px; background: rgba(255,255,255,.5); }
.history-item .e { font-size: 1.4rem; width: 34px; text-align: center; flex-shrink: 0; }
.history-item .meta { flex: 1; min-width: 0; }
.history-item .meta strong { font-size: .85rem; }
.history-item .meta small { display: block; color: var(--muted); font-size: .72rem; }
.history-item .stats { display: flex; gap: 10px; font-size: .68rem; color: var(--muted); flex-shrink: 0; }
.history-item .stats span b { color: var(--ink); }
.mt-tabs { display: flex; gap: 6px; margin-bottom: 20px; background: rgba(255,255,255,.5); padding: 6px; border-radius: 16px; width: fit-content; }
.mt-tabs a { padding: 8px 18px; border-radius: 12px; font-weight: 600; font-size: .85rem; color: var(--muted); text-decoration: none; }
.mt-tabs a.active { background: var(--sage-dark); color: #fff; }
.cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.cal-header a { color: var(--muted); font-size: 1.2rem; text-decoration: none; }
.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
.cal-dow { text-align: center; font-size: .68rem; font-weight: 700; color: var(--muted); text-transform: uppercase; }
.cal-day { aspect-ratio: 1; border-radius: 12px; background: rgba(255,255,255,.5); display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: .7rem; position: relative; }
.cal-day.has-mood { background: var(--sage-soft); }
.cal-day.today { border: 2px solid var(--sage-dark); }
.cal-day .num { color: var(--muted); font-size: .62rem; position: absolute; top: 4px; left: 6px; }
.cal-day .emoji { font-size: 1.2rem; margin-top: 4px; }
.cal-day.empty { background: transparent; }
.stat-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
.stat-box { background: var(--card); border-radius: 18px; padding: 18px; text-align: center; }
.stat-box .big { font-size: 1.8rem; }
.stat-box .lbl { color: var(--muted); font-size: .75rem; margin-top: 6px; }
.trend-up { color: #5e7564; } .trend-down { color: #c96a63; } .trend-flat { color: var(--muted); }
</style>

<div class="mt-wrap">
    <h3 class="mb-1"><i class="bi bi-emoji-smile"></i> Mood Tracker</h3>
    <p class="text-muted mb-4">How are you feeling today?</p>

    <div class="mt-tabs">
        <a href="?view=checkin" class="<?= $activeView == 'checkin' ? 'active' : '' ?>">Check-in</a>
        <a href="?view=calendar" class="<?= $activeView == 'calendar' ? 'active' : '' ?>">Calendar</a>
        <a href="?view=stats" class="<?= $activeView == 'stats' ? 'active' : '' ?>">Statistics</a>
    </div>

    <?php if ($activeView == 'calendar'): ?>
    <div class="glass-card p-4 mb-4">
        <div class="cal-header">
            <a href="?view=calendar&month=<?= $prevMonth ?>"><i class="bi bi-chevron-left"></i></a>
            <strong><?= date('F Y', strtotime($calStart)) ?></strong>
            <a href="?view=calendar&month=<?= $nextMonth ?>"><i class="bi bi-chevron-right"></i></a>
        </div>
        <div class="cal-grid">
            <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dow): ?><div class="cal-dow"><?= $dow ?></div><?php endforeach; ?>
            <?php
            $firstDow = (int)date('N', strtotime($calStart)); // 1=Mon..7=Sun
            for ($i = 1; $i < $firstDow; $i++) echo '<div class="cal-day empty"></div>';
            $daysInMonth = (int)date('t', strtotime($calStart));
            for ($d = 1; $d <= $daysInMonth; $d++):
                $dateStr = $calMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                $entry = $calByDay[$dateStr] ?? null;
                $m = $entry ? ($moodMap[$entry['emotion']] ?? ['emoji' => '🙂']) : null;
                $isToday = $dateStr === $today;
            ?>
                <div class="cal-day <?= $entry ? 'has-mood' : '' ?> <?= $isToday ? 'today' : '' ?>" title="<?= $entry ? escape($moodMap[$entry['emotion']]['label'] ?? $entry['emotion']) : '' ?>">
                    <span class="num"><?= $d ?></span>
                    <?php if ($m): ?><span class="emoji"><?= $m['emoji'] ?></span><?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <?php elseif ($activeView == 'stats'): ?>
    <?php if (!$statsAvailable): ?>
        <div class="glass-card p-4 text-center text-muted">Log a few check-ins and your statistics will appear here.</div>
    <?php else: ?>
    <div class="stat-grid mb-3">
        <div class="stat-box"><div class="big"><?= $moodMap[$mostCommonMood]['emoji'] ?? '🙂' ?></div><div class="lbl">Most common mood<br><strong><?= escape($moodMap[$mostCommonMood]['label'] ?? ucfirst($mostCommonMood)) ?></strong></div></div>
        <div class="stat-box"><div class="big"><?= $consistency ?>%</div><div class="lbl">Check-in consistency<br>(last 30 days)</div></div>
        <div class="stat-box"><div class="big"><?= $moodMap[$bestDay['emotion']]['emoji'] ?? '🙂' ?></div><div class="lbl">Best day<br><strong><?= date('M j', strtotime($bestDay['date'])) ?></strong></div></div>
        <div class="stat-box"><div class="big"><?= $moodMap[$worstDay['emotion']]['emoji'] ?? '🙂' ?></div><div class="lbl">Toughest day<br><strong><?= date('M j', strtotime($worstDay['date'])) ?></strong></div></div>
    </div>
    <div class="glass-card p-3">
        <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid var(--line);">
            <span>Weekly trend</span>
            <?php if ($weekTrend === null): ?><span class="text-muted">Not enough data yet</span>
            <?php elseif ($weekTrend === 'up'): ?><span class="trend-up">▲ Improving</span>
            <?php elseif ($weekTrend === 'down'): ?><span class="trend-down">▼ Declining</span>
            <?php else: ?><span class="trend-flat">— Stable</span><?php endif; ?>
        </div>
        <div class="d-flex justify-content-between py-2">
            <span>Monthly trend</span>
            <?php if ($monthTrend === null): ?><span class="text-muted">Not enough data yet</span>
            <?php elseif ($monthTrend === 'up'): ?><span class="trend-up">▲ Improving</span>
            <?php elseif ($monthTrend === 'down'): ?><span class="trend-down">▼ Declining</span>
            <?php else: ?><span class="trend-flat">— Stable</span><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="glass-card p-4 mb-4">
        <form id="moodForm">
            <input type="hidden" name="ajax" value="1">
            <input type="hidden" name="date" value="<?= $today ?>">
            <input type="hidden" name="emotion" id="emotionInput" value="<?= escape($todayEntry['emotion'] ?? 'okay') ?>">

            <div class="mood-picker" id="moodPicker">
                <?php foreach ($moodMap as $key => $m): ?>
                    <div class="mood-opt <?= ($todayEntry && $todayEntry['emotion'] === $key) ? 'selected' : '' ?>" data-key="<?= $key ?>">
                        <span class="e"><?= $m['emoji'] ?></span>
                        <span class="l"><?= $m['label'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php
            $sliders = [
                'energy' => ['Energy', $todayEntry['energy'] ?? 5],
                'stress' => ['Stress', $todayEntry['stress'] ?? 5],
                'sleep' => ['Sleep quality', $todayEntry['sleep'] ?? 5],
                'exercise' => ['Movement / exercise', $todayEntry['exercise'] ?? 5],
            ];
            foreach ($sliders as $key => [$label, $val]): ?>
                <div class="mt-slider-row">
                    <div class="top"><span><?= $label ?></span><span id="<?= $key ?>Val"><?= (int)$val ?></span></div>
                    <input type="range" name="<?= $key ?>" min="1" max="10" value="<?= (int)$val ?>" oninput="document.getElementById('<?= $key ?>Val').textContent=this.value">
                </div>
            <?php endforeach; ?>

            <div class="mb-3">
                <label class="form-label small">Optional note</label>
                <textarea name="note" class="form-control" rows="2" placeholder="Anything you want to remember about today…"><?= escape($todayEntry['note'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100"><?= $todayEntry ? 'Update today\'s check-in' : 'Save check-in' ?></button>
        </form>
        <div id="moodMsg" class="mt-2"></div>
    </div>

    <h5 class="mb-2">Recent history</h5>
    <div class="history-list">
        <?php if (empty($history)): ?>
            <p class="text-muted small">No check-ins yet — your first one is above.</p>
        <?php endif; ?>
        <?php foreach ($history as $h):
            $m = $moodMap[$h['emotion']] ?? ['emoji' => '🙂', 'label' => ucfirst($h['emotion'])];
        ?>
            <div class="history-item">
                <span class="e"><?= $m['emoji'] ?></span>
                <div class="meta">
                    <strong><?= escape($m['label']) ?></strong>
                    <small><?= date('D, M j', strtotime($h['entry_date'])) ?><?= $h['note'] ? ' — ' . escape(mb_substr($h['note'], 0, 40)) : '' ?></small>
                </div>
                <div class="stats">
                    <span>⚡<b><?= (int)($h['energy'] ?? 0) ?></b></span>
                    <span>💧<b><?= (int)($h['stress'] ?? 0) ?></b></span>
                    <span>🛌<b><?= (int)($h['sleep'] ?? 0) ?></b></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.mood-opt').forEach(el => {
    el.addEventListener('click', () => {
        document.querySelectorAll('.mood-opt').forEach(x => x.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('emotionInput').value = el.dataset.key;
    });
});
document.getElementById('moodForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const box = document.getElementById('moodMsg');
    try {
        const r = await fetch('mood-tracker.php', { method: 'POST', body: fd });
        const d = await r.json();
        box.innerHTML = d.ok
            ? '<div class="alert alert-success py-2 mb-0">' + d.message + '</div>'
            : '<div class="alert alert-danger py-2 mb-0">' + (d.message || 'Could not save.') + '</div>';
        if (d.ok) setTimeout(() => location.reload(), 700);
    } catch (err) {
        box.innerHTML = '<div class="alert alert-danger py-2 mb-0">Network error. Please try again.</div>';
    }
});
</script>
<?php include 'includes/footer.php'; ?>
