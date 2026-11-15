<?php
// ============================================================
// PromptBuilder.php – Ultra-Strict JSON only
// ============================================================
class PromptBuilder {
    public static function buildMindGuidePrompt(string $content, array $context = []): string {
        $mood = $context['mood'] ?? '';
        $age = $context['age'] ?? '';
        $occupation = $context['occupation'] ?? '';
        $history = $context['history'] ?? '';

        $historyBlock = '';
        if (!empty($history)) {
            $historyBlock = "Conversation so far:\n$history\n\n";
        }

        return "
You are MindGuide, a supportive AI assistant for an anonymous mental wellness community.

Rules:
- Never diagnose or replace professional help.
- Use warm, empathetic language.
- Keep reply under 150 words.
- Offer practical suggestions.
- Always mention that volunteers are available.

$historyBlock

Context:
User Age: $age
User Occupation: $occupation
User Mood: $mood

User's latest message: \"$content\"

**IMPORTANT: Return ONLY a valid JSON object. No extra text, no markdown, no code blocks, no explanations.**
The JSON must have exactly these keys: reply (string), emotion (string), confidence (integer), tips (array of strings), resources (array of integers), volunteer_required (boolean).
";
    }

    public static function buildMindShieldPrompt(string $content): string {
        return "Return ONLY JSON: {\"risk_level\":\"low/medium/high/critical\",\"category\":\"...\",\"confidence\":0-100,\"suggested_action\":\"publish/flag/hide/notify_volunteer/notify_admin\"}. Text: \"$content\"";
    }

    public static function buildMindInsightPrompt(array $userData): string {
        return "Return ONLY JSON: {\"summary\":\"...\",\"recommendations\":[\"...\"],\"trend\":\"...\"}. Mood history: " . json_encode($userData['moods']) . ", Posts: " . $userData['post_count'] . ", Avg stress: " . $userData['avg_stress'];
    }
}
?>