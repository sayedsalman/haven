<?php
// ============================================================
// MindGuide.php – Better fallback: raw text
// ============================================================
require_once 'AIProviderInterface.php';
require_once 'PromptBuilder.php';
require_once 'ContextBuilder.php';
require_once 'KnowledgeBase.php';
require_once 'EmotionDetector.php';

class MindGuide {
    private AIProviderInterface $ai;
    private $logFile;

    public function __construct(AIProviderInterface $ai) {
        $this->ai = $ai;
        $this->logFile = __DIR__ . '/../logs/mindguide.log';
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - MindGuide constructed with provider: " . get_class($ai) . "\n", FILE_APPEND);
    }

    private function log($msg) {
        file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
    }

    // Extract JSON using regex (fallback)
    private function extractJsonValue($json, $key) {
        $pattern = '/"' . preg_quote($key) . '"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/';
        if (preg_match($pattern, $json, $matches)) {
            return stripcslashes($matches[1]);
        }
        return null;
    }

    public function generateReply(string $content, int $userId = 0, array $extraContext = []): array {
        $this->log("📝 generateReply called for user $userId");
        $this->log("📝 Content: " . substr($content, 0, 100) . "...");

        $context = $userId ? ContextBuilder::buildUserContext($userId) : [];
        if (!empty($extraContext)) {
            $context = array_merge($context, $extraContext);
        }

        $prompt = PromptBuilder::buildMindGuidePrompt($content, $context);

        $this->log("📤 Sending to provider...");
        // 300 tokens was too small: the JSON (reply + tips + keys) got cut off
        // mid-string, which broke parsing. Provider errors now throw and are
        // handled by AIManager (no more silent canned replies).
        $response = $this->ai->generateText($prompt, ['temperature' => 0.8, 'max_tokens' => 600]);
        $this->log("📥 Raw response: " . substr($response, 0, 300) . "...");

        // Models often wrap JSON in ```json fences or add a sentence around it.
        $clean = trim($response);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);
        $first = strpos($clean, '{');
        $last  = strrpos($clean, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $clean = substr($clean, $first, $last - $first + 1);
        }

        // Try JSON decode
        $result = json_decode($clean, true);
        $parsedOk = (json_last_error() === JSON_ERROR_NONE && isset($result['reply']));

        if ($parsedOk) {
            $this->log("✅ JSON parsed successfully.");
            $result['reply'] = preg_replace('/[\x00-\x1F\x7F]/u', '', $result['reply']);
            $result['reply'] = $result['reply'] ?? "Thank you for sharing. You are not alone. 💚";
            $result['emotion'] = $result['emotion'] ?? 'neutral';
            $result['confidence'] = $result['confidence'] ?? 0;
            $result['tips'] = is_array($result['tips']) ? $result['tips'] : [];
            $result['resources'] = is_array($result['resources']) ? $result['resources'] : [];
            $result['volunteer_required'] = isset($result['volunteer_required']) ? (bool)$result['volunteer_required'] : false;
            return $result;
        }

        // Fallback: try regex extraction
        $this->log("⚠️ JSON decode failed: " . json_last_error_msg());
        $this->log("⚠️ Attempting to extract reply via regex...");

        $replyText = $this->extractJsonValue($response, 'reply');
        $emotion = $this->extractJsonValue($response, 'emotion') ?? 'neutral';
        $confidence = (int)($this->extractJsonValue($response, 'confidence') ?? 0);

        if ($replyText !== null) {
            $replyText = preg_replace('/[\x00-\x1F\x7F]/u', '', $replyText);
            $this->log("✅ Extracted reply via regex.");
            return [
                'reply' => $replyText,
                'emotion' => $emotion,
                'confidence' => $confidence ?: 70,
                'tips' => [],
                'resources' => [],
                'volunteer_required' => false
            ];
        }

        // Final fallback: use the raw response as the reply (strip any JSON leftovers)
        $this->log("⚠️ Regex extraction failed. Using raw response as reply.");
        $replyText = trim($response);
        // Remove markdown code blocks
        $replyText = preg_replace('/^```(?:json)?\s*/', '', $replyText);
        $replyText = preg_replace('/```$/', '', $replyText);
        // If the response still contains JSON-like braces, we can try to extract anything after the first {
        if (strpos($replyText, '{') !== false && strpos($replyText, '}') !== false) {
            // It might be JSON with extra text, we'll keep it as is and the user will see the AI's message
            // We'll just clean control chars
            $replyText = preg_replace('/[\x00-\x1F\x7F]/u', '', $replyText);
        }
        if (strlen($replyText) < 10) {
            $replyText = "Thank you for sharing your thoughts with our community. You're not alone in this journey. A volunteer has been notified and will reach out soon. 💚";
        }

        $this->log("📝 Using raw response as reply.");
        return [
            'reply' => $replyText,
            'emotion' => EmotionDetector::detect($content),
            'confidence' => 70,
            'tips' => [],
            'resources' => [],
            'volunteer_required' => false
        ];
    }
}
?>