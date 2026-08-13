<?php

define('SITE_NAME', 'Haven');
define('SITE_URL', 'https://salman.rfnhsc.com/mind/');
define('SITE_EMAIL', 'salman@rfnhsc.com');


define('DB_HOST', 'localhost');
define('DB_NAME', 'haven');
define('DB_USER', 'root');
define('DB_PASS', '...');


define('HASH_COST', 12);
define('ENCRYPTION_KEY', 'your-32-char-secret-key-here');


define('AI_PROVIDER', 'openrouter');

// Gemini (keep for fallback or secondary use)
define('GEMINI_API_KEY', '');

// OpenRouter

// Alternative free models:
// 'meta-llama/llama-3-8b-instruct'
// 'microsoft/phi-3-mini-128k-instruct'
// 'google/gemini-2.0-flash-lite-preview-02-05'

// ============================================================
// Uploads
// ============================================================
define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg','image/png','image/webp']);

// ============================================================
// Start Session
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
