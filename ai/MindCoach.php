<?php
/**
 * ai/MindCoach.php — Haven's fourth AI module. Unlike MindGuide (which
 * talks), MindCoach looks at a user's recent real activity (mood,
 * goals, focus sessions) and suggests one small, concrete action for
 * today. It never invents data — if there isn't enough history to
 * suggest something meaningful, it says so honestly.
 */
class MindCoach {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Returns ['title' => string, 'action' => string, 'link' => string]
     * or null if there's genuinely nothing useful to suggest yet.
     */
    public function suggestTodayGoal(int $userId): ?array {
        $pdo = $this->pdo;

        // 1. Look at the most recent mood check-in.
        $hasEntries = $pdo->query("SHOW TABLES LIKE 'mood_entries'")->rowCount() > 0;
        $table = $hasEntries ? 'mood_entries' : 'mood_logs';
        $dateCol = $hasEntries ? 'entry_date' : 'log_date';
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE user_id = ? ORDER BY $dateCol DESC LIMIT 1");
        $stmt->execute([$userId]);
        $latest = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($latest) {
            $stress = (int)($latest['stress'] ?? 0);
            $sleep = (int)($latest['sleep'] ?? 0);
            $energy = (int)($latest['energy'] ?? 0);

            if ($stress >= 7) {
                return ['title' => 'Your stress has been running high', 'action' => 'Try a 4-4 breathing exercise — just 5 minutes.', 'link' => 'wellness.php'];
            }
            if ($sleep > 0 && $sleep <= 4) {
                return ['title' => 'Sleep has been rough lately', 'action' => 'Log tonight in your journal — noticing patterns can help.', 'link' => 'journal.php'];
            }
            if ($energy > 0 && $energy <= 4) {
                return ['title' => 'Energy has been low', 'action' => 'Study for 25 minutes without checking social media.', 'link' => 'focus.php'];
            }
        }

        // 2. Check if they have any active goals at all.
        if ($pdo->query("SHOW TABLES LIKE 'goals'")->rowCount() > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM goals WHERE user_id = ? AND is_active = 1");
            $stmt->execute([$userId]);
            if ((int)$stmt->fetchColumn() === 0) {
                return ['title' => 'You have no active goals yet', 'action' => 'Set one small, trackable goal for this week.', 'link' => 'goals.php'];
            }
        }

        // 3. Encourage a first check-in if there's no mood history at all.
        if (!$latest) {
            return ['title' => 'Start your wellness picture', 'action' => "Log today's mood — it only takes a few seconds.", 'link' => 'mood-tracker.php'];
        }

        // Nothing urgent to suggest — things look steady.
        return ['title' => 'You\'re doing okay', 'action' => 'Study for 25 minutes without checking social media.', 'link' => 'focus.php'];
    }
}
?>
