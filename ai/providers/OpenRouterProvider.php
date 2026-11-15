<?php
// ============================================================
// OpenRouterProvider.php
//
// CHANGES vs. previous version:
//  * Failures are no longer disguised as a normal "Thank you for sharing"
//    reply. The provider now THROWS a RuntimeException with the real
//    reason, so callers (and the logs) know the AI actually failed.
//  * Tries a list of fallback models if the primary model is
//    retired / unavailable (HTTP 400/404) or temporarily failing.
//  * Retries once on rate-limit / 5xx / network errors.
//  * Stops immediately on 401/402/403 (bad key, no credits) because no
//    other model can fix those, and says so clearly in the log.
//  * Logs HTTP code + response body (the old code only logged them when
//    the body happened to contain an "error" key).
// ============================================================
require_once __DIR__ . '/../AIProviderInterface.php';

class OpenRouterProvider implements AIProviderInterface {
    private string $apiKey;
    private string $model;
    private array $fallbackModels;
    private string $apiUrl = 'https://openrouter.ai/api/v1/chat/completions';
    private $logFile;
    private string $lastError = '';
    private string $lastModelUsed = '';

    public function __construct(string $apiKey, string $model = 'anthropic/claude-haiku-4.5', array $fallbackModels = []) {
        $this->apiKey = trim($apiKey);
        $this->model = $model;
        $this->fallbackModels = array_values(array_filter($fallbackModels, fn($m) => is_string($m) && $m !== '' && $m !== $model));
        $this->logFile = __DIR__ . '/../logs/openrouter.log';
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    }

    public function getLastError(): string { return $this->lastError; }
    public function getLastModelUsed(): string { return $this->lastModelUsed; }

    private function log($msg) {
        @file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
    }

    public function generateText(string $prompt, array $params = []): string {
        $messages = [['role' => 'user', 'content' => $prompt]];
        return $this->sendChatRequest($messages, $params);
    }

    public function generateVisionText(string $prompt, string $imageUrl, array $params = []): string {
        // Multimodal content array: text + image_url blocks in one message.
        $messages = [[
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
            ],
        ]];
        // Optional explicit vision model, otherwise use the normal chain.
        return $this->sendChatRequest($messages, $params, $params['vision_model'] ?? null);
    }

    /**
     * Sends the request, walking through [primary, ...fallbacks].
     * Returns the model's text, or throws RuntimeException.
     */
    private function sendChatRequest(array $messages, array $params = [], ?string $modelOverride = null): string {
        if ($this->apiKey === '') {
            $this->lastError = 'OPENROUTER_API_KEY is empty / not defined';
            $this->log("❌ " . $this->lastError);
            throw new RuntimeException($this->lastError);
        }

        $chain = $modelOverride ? [$modelOverride] : array_merge([$this->model], $this->fallbackModels);
        $errors = [];

        foreach ($chain as $model) {
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                $this->log("📤 Model: $model (attempt $attempt)");
                [$httpCode, $curlErr, $body] = $this->post($model, $messages, $params);
                $this->log("📥 HTTP $httpCode" . ($curlErr ? " | cURL: $curlErr" : '') . " | body: " . substr((string)$body, 0, 400));

                // ---- Success path ----
                if (!$curlErr && $httpCode === 200) {
                    $result = json_decode((string)$body, true);
                    // OpenRouter can return HTTP 200 with an "error" object in the body.
                    if (isset($result['error'])) {
                        $errors[] = "$model: 200 with error " . json_encode($result['error']);
                        break; // try next model
                    }
                    $text = $result['choices'][0]['message']['content'] ?? '';
                    if (is_array($text)) { // some providers return content parts
                        $text = implode('', array_map(fn($p) => is_array($p) ? ($p['text'] ?? '') : (string)$p, $text));
                    }
                    $text = trim((string)$text);
                    if ($text !== '') {
                        $this->lastModelUsed = $model;
                        $this->lastError = '';
                        return $text;
                    }
                    $errors[] = "$model: empty response";
                    break; // try next model
                }

                // ---- Failure path ----
                $detail = $curlErr ?: $this->extractApiError((string)$body);

                // Key / billing problems: another model will not help. Stop now.
                if (in_array($httpCode, [401, 402, 403], true)) {
                    $hint = [
                        401 => 'API key invalid, revoked or disabled',
                        402 => 'OpenRouter account out of credits',
                        403 => 'request blocked (moderation / key restrictions)',
                    ][$httpCode];
                    $this->lastError = "HTTP $httpCode – $hint – $detail";
                    $this->log("❌ " . $this->lastError);
                    throw new RuntimeException($this->lastError);
                }

                // Rate limit / server / network: retry the same model once.
                $transient = ($curlErr !== '' || $httpCode === 429 || $httpCode >= 500 || $httpCode === 0);
                if ($transient && $attempt < 2) {
                    usleep(700000);
                    continue;
                }

                // Model-level problem (400/404 = retired or unknown model) or retries used up.
                $errors[] = "$model: HTTP $httpCode – $detail";
                break;
            }
        }

        $this->lastError = 'All models failed → ' . implode(' | ', $errors);
        $this->log("❌ " . $this->lastError);
        throw new RuntimeException($this->lastError);
    }

    private function post(string $model, array $messages, array $params): array {
        $data = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $params['temperature'] ?? 0.7,
            'max_tokens'  => $params['max_tokens'] ?? 500,
        ];
        $referer = defined('SITE_URL') ? SITE_URL : 'https://localhost/';

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'HTTP-Referer: ' . $referer,
            'X-Title: Haven AI',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);

        $body     = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        return [$httpCode, $curlErr, $body === false ? '' : $body];
    }

    private function extractApiError(string $body): string {
        $r = json_decode($body, true);
        if (is_array($r) && isset($r['error'])) {
            $e = $r['error'];
            if (is_array($e)) return trim(($e['message'] ?? '') . ' ' . (isset($e['code']) ? "(code {$e['code']})" : ''));
            return (string)$e;
        }
        return substr($body, 0, 200);
    }

    public function analyzeSentiment(string $text): array {
        $default = ['positive' => 0.3, 'negative' => 0.5, 'neutral' => 0.2];
        try {
            $prompt = "Analyze sentiment and return ONLY JSON with positive, negative, neutral floats. Text: \"$text\"";
            $result = $this->generateText($prompt, ['temperature' => 0.2, 'max_tokens' => 80]);
            $result = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($result));
            $parsed = json_decode($result, true);
            return (json_last_error() === JSON_ERROR_NONE && isset($parsed['positive'])) ? $parsed : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}
?>
