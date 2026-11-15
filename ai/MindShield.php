<?php
require_once 'AIProviderInterface.php';
require_once 'PromptBuilder.php';

class MindShield {
    private AIProviderInterface $ai;

    public function __construct(AIProviderInterface $ai) {
        $this->ai = $ai;
    }

    public function scan(string $content): array {
        $prompt = PromptBuilder::buildMindShieldPrompt($content);
        $result = null;
        try {
            $response = $this->ai->generateText($prompt, ['temperature' => 0.2, 'max_tokens' => 150]);
            $response = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($response));
            $decoded = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['risk_level'])) {
                $result = $decoded;
            }
        } catch (Throwable $e) {
            // AI unavailable – fall through to the keyword safety net below.
        }
        if ($result !== null) return $result;

        // Safety net: if the AI is down or returned junk, NEVER silently mark a
        // post as safe. Use keyword matching so self-harm posts still get flagged.
        $risk = 'low';
        $category = 'other';
        $keywords = ['suicide','kill myself','end my life','want to die','self-harm','self harm','hurt myself','cut myself','no reason to live'];
        foreach ($keywords as $kw) {
            if (stripos($content, $kw) !== false) {
                $risk = 'high';
                $category = 'self-harm';
                break;
            }
        }
        return ['risk_level' => $risk, 'category' => $category, 'confidence' => 50, 'suggested_action' => ($risk == 'high' ? 'notify_volunteer' : 'publish')];
    }
}
?>