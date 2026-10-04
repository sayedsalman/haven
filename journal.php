<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';
include 'includes/badges.php';

$user_id = $_SESSION['user_id'];

$moodMap = [
    'happy'    => '😊', 'good'     => '🙂', 'okay'     => '😐',
    'sad'      => '😔', 'angry'    => '😡', 'anxious'  => '😰',
];

// Self-healing: add mood_emoji + ai_reflection columns the first time this
// runs, so we don't require a separate manual migration.
$col1 = $pdo->query("SHOW COLUMNS FROM journals LIKE 'mood_emoji'")->fetch();
if (!$col1) $pdo->exec("ALTER TABLE journals ADD COLUMN mood_emoji VARCHAR(10) DEFAULT NULL AFTER entry");
$col2 = $pdo->query("SHOW COLUMNS FROM journals LIKE 'ai_reflection'")->fetch();
if (!$col2) $pdo->exec("ALTER TABLE journals ADD COLUMN ai_reflection TEXT DEFAULT NULL AFTER mood_emoji");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entry = trim($_POST['entry'] ?? '');
    $mood = $_POST['mood'] ?? '';
    if (!isset($moodMap[$mood])) $mood = null;
    $date = date('Y-m-d');

    if ($entry !== '') {
        // Generate a short, supportive AI reflection on the entry. This
        // never blocks saving the entry if the AI call fails.
        $reflection = null;
        $aiFile = __DIR__ . '/ai/AIManager.php';
        if (is_file($aiFile)) {
            require_once $aiFile;
            if (class_exists('AIManager')) {
                try {
                    $manager = new AIManager();
                    $prompt = "This is a private journal entry, not a public post. Respond with one short, warm, reflective sentence (max 30 words) that shows you noticed something specific in what they wrote — do not just say something generic. Do not ask a question unless it feels natural. Entry: \"$entry\"";
                    $result = $manager->chat($prompt, $user_id);
                    $reflection = trim($result['reply'] ?? '') ?: null;
                } catch (Throwable $e) {
                    error_log('[Haven] Journal AI reflection failed: ' . $e->getMessage());
                }
            }
        }

        $stmt = $pdo->prepare("INSERT INTO journals (user_id, entry, mood_emoji, ai_reflection, entry_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $entry, $mood, $reflection, $date]);
        havenCheckAndAwardBadges($pdo, $user_id);
    }
    header('Location: journal.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM journals WHERE user_id = ? ORDER BY entry_date DESC, id DESC LIMIT 20");
$stmt->execute([$user_id]);
$entries = $stmt->fetchAll();

$pageTitle = 'Journal';
include 'includes/header.php';
?>
<style>
.jr-wrap { max-width: 680px; margin: 0 auto; }
.jr-mood-row { display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
.jr-mood-opt { border: 2px solid transparent; background: rgba(255,255,255,.5); border-radius: 14px; padding: 10px 14px; text-align: center; cursor: pointer; font-size: 1.5rem; }
.jr-mood-opt.selected { border-color: var(--sage-dark); background: var(--sage-soft); }
.jr-entry { background: var(--card); border-radius: 18px; padding: 18px; margin-bottom: 14px; }
.jr-entry .jr-date { font-weight: 700; font-size: .85rem; }
.jr-entry .jr-mood { font-size: 1.3rem; float: right; }
.jr-reflection { margin-top: 12px; background: var(--sage-soft); border-radius: 12px; padding: 12px 14px; font-size: .85rem; color: var(--sage-dark); }
.jr-reflection b { display: block; font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
</style>

<div class="jr-wrap">
    <h3 class="mb-1"><i class="bi bi-journal-richtext"></i> Haven Journal</h3>
    <p class="text-muted mb-4">A private space just for you — no one else can see this.</p>

    <div class="glass-card p-4 mb-4">
        <form method="POST">
            <label class="form-label small fw-bold">How are you feeling today?</label>
            <div class="jr-mood-row" id="jrMoodRow">
                <?php foreach ($moodMap as $key => $emoji): ?>
                    <div class="jr-mood-opt" data-mood="<?= $key ?>"><?= $emoji ?></div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="mood" id="jrMoodInput" value="">

            <label class="form-label small fw-bold mt-2">What happened today?</label>
            <textarea name="entry" rows="5" class="form-control" placeholder="Write freely — this is just for you..." required></textarea>

            <button type="submit" class="btn btn-primary mt-3 w-100">Save Entry</button>
        </form>
    </div>

    <h5 class="mb-3">Past Entries</h5>
    <?php if (empty($entries)): ?>
        <p class="text-muted">No entries yet — your first one is above.</p>
    <?php endif; ?>
    <?php foreach ($entries as $e): ?>
        <div class="jr-entry">
            <?php if (!empty($e['mood_emoji']) && isset($moodMap[$e['mood_emoji']])): ?>
                <span class="jr-mood"><?= $moodMap[$e['mood_emoji']] ?></span>
            <?php endif; ?>
            <span class="jr-date"><?= date('F j, Y', strtotime($e['entry_date'])) ?></span>
            <p class="mt-2 mb-0"><?= nl2br(escape($e['entry'])) ?></p>
            <?php if (!empty($e['ai_reflection'])): ?>
                <div class="jr-reflection"><b>AI reflection</b><?= escape($e['ai_reflection']) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
document.querySelectorAll('.jr-mood-opt').forEach(el => {
    el.addEventListener('click', () => {
        document.querySelectorAll('.jr-mood-opt').forEach(x => x.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('jrMoodInput').value = el.dataset.mood;
    });
});
</script>
<?php include 'includes/footer.php'; ?>
