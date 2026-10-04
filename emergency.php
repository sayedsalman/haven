<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
include 'includes/functions.php';

$user_country = null;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT country FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_country = $stmt->fetchColumn() ?: null;
}
$selected = $_GET['country'] ?? $user_country ?? '';

/**
 * A small, editable table of crisis lines by country. This is not
 * exhaustive — Admin → Settings can be extended later to manage this
 * list without a code deploy, but a hardcoded, reviewed list is safer
 * for a safety-critical page than leaving it empty.
 */
$resources = [
    'United States'  => [['name' => '988 Suicide & Crisis Lifeline', 'phone' => '988', 'note' => 'Call or text, 24/7'], ['name' => 'Crisis Text Line', 'phone' => 'Text HOME to 741741', 'note' => '24/7']],
    'United Kingdom' => [['name' => 'Samaritans', 'phone' => '116 123', 'note' => 'Free, 24/7'], ['name' => 'Shout Crisis Text Line', 'phone' => 'Text SHOUT to 85258', 'note' => '24/7']],
    'Canada'         => [['name' => 'Talk Suicide Canada', 'phone' => '988', 'note' => 'Call or text, 24/7']],
    'Australia'      => [['name' => 'Lifeline Australia', 'phone' => '13 11 14', 'note' => '24/7'], ['name' => 'Beyond Blue', 'phone' => '1300 22 4636', 'note' => '24/7']],
    'India'          => [['name' => 'AASRA', 'phone' => '+91-9820466726', 'note' => '24/7'], ['name' => 'iCall', 'phone' => '+91 9152987821', 'note' => 'Mon–Sat, 8am–10pm']],
    'Bangladesh'     => [['name' => 'Kaan Pete Roi', 'phone' => '+880 9611677777', 'note' => 'Daily, 3pm–9pm'], ['name' => 'National Mental Health Helpline', 'phone' => '09666777222', 'note' => '24/7']],
    'Pakistan'       => [['name' => 'Umang Pakistan', 'phone' => '0311-7786264', 'note' => 'Daily, 3pm–10pm']],
    'Germany'        => [['name' => 'Telefonseelsorge', 'phone' => '0800 111 0 111', 'note' => '24/7']],
    'France'         => [['name' => 'SOS Amitié', 'phone' => '09 72 39 40 50', 'note' => '24/7']],
    'Ireland'        => [['name' => 'Samaritans Ireland', 'phone' => '116 123', 'note' => 'Free, 24/7']],
    'New Zealand'    => [['name' => 'Lifeline Aotearoa', 'phone' => '0800 543 354', 'note' => '24/7']],
    'South Africa'   => [['name' => 'SADAG', 'phone' => '0800 567 567', 'note' => '24/7']],
];
$fallback = ['name' => 'International Association for Suicide Prevention', 'phone' => 'befrienders.org', 'note' => 'Directory of crisis lines worldwide'];

$matched = null;
foreach ($resources as $country => $lines) {
    if (mb_strtolower($country) === mb_strtolower(trim($selected))) { $matched = $country; break; }
}

$pageTitle = 'Emergency Resources';
include 'includes/header.php';
?>
<style>
.em-wrap { max-width: 620px; margin: 0 auto; }
.em-banner { background: #fdeceb; border: 1px solid #f3c6c1; border-radius: 18px; padding: 20px; margin-bottom: 24px; }
.em-banner h4 { color: #a8402f; }
.em-select { max-width: 280px; margin-bottom: 20px; }
.em-card { background: var(--card); border-radius: 16px; padding: 16px 18px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
.em-card .name { font-weight: 700; }
.em-card .note { color: var(--muted); font-size: .78rem; }
.em-card .phone { font-weight: 700; color: var(--sage-dark); font-size: 1.05rem; }
</style>

<div class="em-wrap">
    <div class="em-banner">
        <h4><i class="bi bi-life-preserver"></i> If you are in immediate danger</h4>
        <p class="mb-0">Please contact your local emergency number right now (e.g., 911, 999, 112) or go to your nearest emergency room. The resources below are crisis support lines, not a substitute for emergency services.</p>
    </div>

    <label class="form-label small fw-bold">Select your country</label>
    <select class="form-select em-select" onchange="location.href='emergency.php?country='+encodeURIComponent(this.value)">
        <option value="">Choose a country...</option>
        <?php foreach ($resources as $country => $lines): ?>
            <option value="<?= escape($country) ?>" <?= $matched === $country ? 'selected' : '' ?>><?= escape($country) ?></option>
        <?php endforeach; ?>
    </select>

    <?php if ($matched): ?>
        <h6 class="text-muted mb-2">Crisis lines in <?= escape($matched) ?></h6>
        <?php foreach ($resources[$matched] as $line): ?>
            <div class="em-card">
                <div><div class="name"><?= escape($line['name']) ?></div><div class="note"><?= escape($line['note']) ?></div></div>
                <div class="phone"><?= escape($line['phone']) ?></div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <h6 class="text-muted mb-2">International</h6>
        <div class="em-card">
            <div><div class="name"><?= escape($fallback['name']) ?></div><div class="note"><?= escape($fallback['note']) ?></div></div>
            <div class="phone"><?= escape($fallback['phone']) ?></div>
        </div>
        <p class="text-muted small mt-2">Select your country above for local crisis line numbers.</p>
    <?php endif; ?>

    <div class="glass-card p-3 mt-4 text-center">
        <p class="mb-2">You can also reach out within Haven:</p>
        <a href="chatbot.php" class="btn btn-outline-primary btn-sm me-2">💬 Talk to MindGuide</a>
        <a href="consultation.php?tab=new" class="btn btn-outline-primary btn-sm">🤝 Request a volunteer</a>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
