<?php
class ContextBuilder {
    public static function buildUserContext(int $userId): array {
        $db = db();
        $user = $db->select('users', ['id' => $userId]);
        if (empty($user)) return [];
        $user = $user[0];
        $moods = $db->select('mood_entries', ['user_id' => $userId], 'entry_date DESC', '5');
        $lastMood = $moods[0]['emotion'] ?? 'unknown';
        
        // Fix: use PDO prepared statement
        $stmt = $db->query("SELECT AVG(stress) as avg FROM mood_entries WHERE user_id = ?", [$userId]);
        $row = $stmt->fetch();
        $avgStress = $row['avg'] ?? 0;
        
        $posts = $db->select('posts', ['user_id' => $userId], 'created_at DESC', '3');
        $postTitles = array_column($posts, 'title');
        return [
            'age' => $user['birth_date'] ? (new DateTime())->diff(new DateTime($user['birth_date']))->y : 'unknown',
            'occupation' => $user['occupation'] ?? 'unknown',
            'mood' => $lastMood,
            'avg_stress' => round($avgStress, 1),
            'recent_posts' => $postTitles
        ];
    }
}
?>