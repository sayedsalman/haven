<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';

// ------------------------------------------------------------
// Anonymous Aggregate Insights — community-wide wellness trends.
// This NEVER exposes individual users, individual posts, or any
// data broken down to a group smaller than MIN_GROUP_SIZE. If a
// category has too few data points, it is folded into "Other"
// rather than shown on its own.
// ------------------------------------------------------------
const MIN_GROUP_SIZE = 10;
const MIN_TOTAL_FOR_PAGE = 20;

$weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));

$rows = $pdo->prepare("SELECT category, COUNT(*) c FROM ai_analysis
                        WHERE created_at >= ? AND category IS NOT NULL AND category != ''
                        GROUP BY category ORDER BY c DESC");
$rows->execute([$weekStart]);
$rows = $rows->fetchAll();

$total = array_sum(array_column($rows, 'c'));
$categories = [];
$otherCount = 0;
foreach ($rows as $r) {
    if ((int)$r['c'] >= MIN_GROUP_SIZE) {
        $categories[$r['category']] = (int)$r['c'];
    } else {
        $otherCount += (int)$r['c'];
    }
}
if ($otherCount > 0) $categories['Other'] = ($categories['Other'] ?? 0) + $otherCount;
arsort($categories);

$percentages = [];
foreach ($categories as $cat => $count) {
    $percentages[$cat] = $total > 0 ? round(($count / $total) * 100) : 0;
}

// Community-wide mood distribution (also aggregate-only, same threshold rule)
$moodRows = $pdo->prepare("SELECT emotion, COUNT(*) c FROM ai_analysis WHERE created_at >= ? AND emotion IS NOT NULL AND emotion != '' GROUP BY emotion ORDER BY c DESC");
$moodRows->execute([$weekStart]);
$moodRows = $moodRows->fetchAll();
$moodTotal = array_sum(array_column($moodRows, 'c'));

$hasEnoughData = $total >= MIN_TOTAL_FOR_PAGE;

$pageTitle = 'Community Trends';
include 'includes/header.php';
?>
<style>
.ins-wrap { max-width: 640px; margin: 0 auto; }
.ins-bar-row { margin-bottom: 14px; }
.ins-bar-row .top { display: flex; justify-content: space-between; font-size: .85rem; font-weight: 600; margin-bottom: 4px; }
.ins-bar-track { background: var(--cream-2); border-radius: 999px; height: 14px; overflow: hidden; }
.ins-bar-fill { background: var(--sage-dark); height: 100%; border-radius: 999px; }
.ins-note { background: var(--sage-soft); border-radius: 14px; padding: 14px 16px; font-size: .82rem; color: var(--sage-dark); margin-top: 20px; }
</style>

<div class="ins-wrap">
    <h3 class="mb-1"><i class="bi bi-globe"></i> Community Wellness Trends</h3>
    <p class="text-muted mb-4">What Haven's community has been navigating this week — fully anonymized, never tied to any individual.</p>

    <?php if (!$hasEnoughData): ?>
        <div class="glass-card p-4 text-center text-muted">
            Not enough community activity this week to show trends without risking anyone's privacy. Check back soon.
        </div>
    <?php else: ?>
        <div class="glass-card p-4 mb-3">
            <h6 class="mb-3">What people have been posting about</h6>
            <?php foreach ($percentages as $cat => $pct): ?>
                <div class="ins-bar-row">
                    <div class="top"><span><?= escape($cat) ?></span><span><?= $pct ?>%</span></div>
                    <div class="ins-bar-track"><div class="ins-bar-fill" style="width:<?= $pct ?>%"></div></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($moodTotal >= MIN_TOTAL_FOR_PAGE): ?>
        <div class="glass-card p-4">
            <h6 class="mb-3">Community mood this week</h6>
            <?php foreach ($moodRows as $m):
                $pct = round(($m['c'] / $moodTotal) * 100);
                if ($m['c'] < MIN_GROUP_SIZE) continue;
            ?>
                <div class="ins-bar-row">
                    <div class="top"><span><?= escape(ucfirst($m['emotion'])) ?></span><span><?= $pct ?>%</span></div>
                    <div class="ins-bar-track"><div class="ins-bar-fill" style="width:<?= $pct ?>%;background:var(--peach);"></div></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="ins-note">
            🔒 These numbers are aggregated across at least <?= MIN_GROUP_SIZE ?> posts per category and never reveal who posted what. Individual posts and users are never identifiable from this page.
        </div>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
