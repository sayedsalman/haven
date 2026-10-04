<?php
/**
 * migrate_charset_fix.php — run this ONCE (visit in browser, or via
 * `php migrate_charset_fix.php` on the server).
 *
 * Root cause: 20 tables in the original database schema were created
 * with `latin1` charset instead of `utf8mb4`, including mood_entries,
 * badges, categories, testimonials, polls, chat_sessions, and more.
 * latin1 cannot store emoji or many non-Latin scripts — any attempt to
 * insert them throws a fatal PDOException ("Incorrect string value").
 * This was silently waiting to break the app; a badge-seeding emoji
 * insert was simply the first thing to actually trigger it during
 * real testing, crashing dashboard.php for every single user.
 *
 * This script converts all affected tables to utf8mb4 safely. It is
 * idempotent — safe to run more than once.
 */
include 'includes/config.php';
include 'includes/database.php';

$tables = [
    'ai_logs', 'ai_recommendations', 'ai_sessions', 'anonymous_identities',
    'badges', 'categories', 'chat_ai_analysis', 'chat_sessions', 'jobs',
    'login_history', 'mood_entries', 'otp_verifications', 'polls',
    'poll_votes', 'rate_limits', 'testimonials', 'user_badges',
    'user_meta', 'volunteer_followups', 'volunteer_notes', 'volunteer_training',
];

$results = [];
foreach ($tables as $t) {
    try {
        $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->rowCount() > 0;
        if (!$exists) { $results[$t] = 'skipped (table not found)'; continue; }
        $pdo->exec("ALTER TABLE `$t` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $results[$t] = 'converted to utf8mb4';
    } catch (Throwable $e) {
        $results[$t] = 'FAILED: ' . $e->getMessage();
    }
}

header('Content-Type: text/plain');
foreach ($results as $t => $r) {
    echo str_pad($t, 25) . " -> $r\n";
}
echo "\nDone. You can delete this file now.\n";
