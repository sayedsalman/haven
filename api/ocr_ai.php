<?php
/**
 * OPTIONAL "enhance with AI" reading of a prescription photo (handwriting is hard for local OCR).
 * Only runs after the member ticks the consent box; the image is sent to the configured AI provider,
 * is NOT stored here, and the result is only a suggestion the member must check.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
$me = hx_post_guard();
if (empty($_POST['consent'])) hx_out(['ok' => false, 'error' => 'Please tick the consent box first.'], 422);
if (!defined('OPENROUTER_API_KEY') || OPENROUTER_API_KEY === '' || stripos(OPENROUTER_API_KEY, 'CHANGE') !== false) hx_out(['ok' => false, 'error' => 'AI reading is not configured on this server.'], 503);
if (!hx_rate($me, 'ocr_ai', 10, 86400)) hx_out(['ok' => false, 'error' => 'Daily AI-reading limit reached.'], 429);
if (empty($_FILES['image'])) hx_out(['ok' => false, 'error' => 'Choose a photo.'], 422);
$r = hx_save_image($_FILES['image'], 'private:tmp', 1600, 10485760);
if (!$r['ok']) hx_out(['ok' => false, 'error' => $r['error']], 422);
$abs = hx_root() . '/' . $r['path'];
$b64 = base64_encode((string)file_get_contents($abs)); @unlink($abs);
$prompt = "You read medical prescriptions. Transcribe this prescription faithfully. Never guess: write [?] where unreadable. Reply with ONLY JSON: "
        . '{"doctor":"","date":"YYYY-MM-DD or empty","text":"full transcription","medicines":[{"name":"","dose":"","frequency":"","duration":"","notes":""}],"advice":""}';
$payload = ['model' => defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : 'anthropic/claude-haiku-4.5', 'max_tokens' => 1800, 'temperature' => 0,
            'messages' => [['role' => 'user', 'content' => [['type' => 'text', 'text' => $prompt], ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $b64]]]]]];
if (!function_exists('curl_init')) hx_out(['ok' => false, 'error' => 'Server cannot reach the AI service.'], 503);
$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . OPENROUTER_API_KEY, 'X-Title: Haven']]);
$resp = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
$j = json_decode((string)$resp, true);
$txt = $j['choices'][0]['message']['content'] ?? '';
if ($code !== 200 || $txt === '') { error_log('[Haven ocr_ai] HTTP ' . $code); hx_out(['ok' => false, 'error' => 'The AI service could not read this image right now.'], 502); }
if (preg_match('/\{.*\}/s', $txt, $m)) $txt = $m[0];
$out = json_decode($txt, true);
if (!is_array($out)) hx_out(['ok' => true, 'text' => hx_clean((string)$j['choices'][0]['message']['content'], 8000), 'medicines' => [], 'doctor' => '', 'date' => '', 'advice' => '']);
hx_out(['ok' => true, 'text' => hx_clean((string)($out['text'] ?? ''), 8000), 'doctor' => hx_clean((string)($out['doctor'] ?? ''), 160),
        'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($out['date'] ?? '')) ? $out['date'] : '', 'advice' => hx_clean((string)($out['advice'] ?? ''), 2000),
        'medicines' => array_slice(array_values(array_filter(array_map(fn($x) => is_array($x) ? ['name' => hx_clean((string)($x['name'] ?? ''), 120), 'dose' => hx_clean((string)($x['dose'] ?? ''), 60),
            'frequency' => hx_clean((string)($x['frequency'] ?? ''), 80), 'duration' => hx_clean((string)($x['duration'] ?? ''), 60), 'notes' => hx_clean((string)($x['notes'] ?? ''), 200)] : null,
            is_array($out['medicines'] ?? null) ? $out['medicines'] : []), fn($x) => $x && $x['name'] !== '')), 0, 30)]);
