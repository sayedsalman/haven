<?php
require_once 'AIProviderInterface.php';
require_once 'PromptBuilder.php';

class MindInsight {
    private AIProviderInterface $ai;

    public function __construct(AIProviderInterface $ai) {
        $this->ai = $ai;
    }

    public function generateReport(int $userId): array {
        $db = db();
        $moods = $db->select('mood_entries', ['user_id' => $userId], 'entry_date DESC', '30');
        $postCount = $db->count('posts', ['user_id' => $userId]);
        
        // Fix: use PDO prepared statement
        $stmt = $db->query("SELECT AVG(stress) as avg FROM mood_entries WHERE user_id = ?", [$userId]);
        $row = $stmt->fetch();
        $avgStress = $row['avg'] ?? 0;

        $userData = [
            'moods' => array_column($moods, 'emotion'),
            'post_count' => $postCount,
            'avg_stress' => round($avgStress, 1)
        ];

        $prompt = PromptBuilder::buildMindInsightPrompt($userData);
        $response = $this->ai->generateText($prompt, ['temperature' => 0.5, 'max_tokens' => 150]);
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['summary' => 'Unable to generate insights.', 'recommendations' => ['Keep logging your mood'], 'trend' => 'stable'];
        }
        return $result;
    }
}
?>