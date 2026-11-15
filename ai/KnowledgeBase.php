<?php
class KnowledgeBase {
    private static array $resources = [
        'study_stress' => [
            'tips' => 'Break study into 25-minute sessions with 5-minute breaks. Stay hydrated.',
            'article_id' => 1
        ],
        'anxiety' => [
            'tips' => 'Try the 4-7-8 breathing technique: inhale 4s, hold 7s, exhale 8s.',
            'article_id' => 2
        ],
        'sleep' => [
            'tips' => 'Maintain a consistent sleep schedule and avoid screens 1 hour before bed.',
            'article_id' => 5
        ],
        'exam' => [
            'tips' => 'Prepare a study plan and practice past papers.',
            'article_id' => 3
        ],
        'loneliness' => [
            'tips' => 'Connect with online groups or local meetups. You are not alone.',
            'article_id' => 6
        ]
    ];
    public static function get(string $key): ?array {
        return self::$resources[$key] ?? null;
    }
}
?>