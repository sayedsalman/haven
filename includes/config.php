<?php
// ============================================================
// Site Settings
// ============================================================
define('SITE_NAME', 'Haven');
define('SITE_URL', 'https://salman.rfnhsc.com/mind/');
define('SITE_EMAIL', 'support@rfnhsc.com');

// ============================================================
// Database
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'rfnhscco_mind');
define('DB_USER', 'rfnhscco_mind');
define('DB_PASS', 'LuSC6c~R[5GZE?vF');

// ============================================================
// Security
// ============================================================
define('HASH_COST', 12);
define('ENCRYPTION_KEY', 'your-32-char-secret-key-here');

// ============================================================
// AI Configuration
// ============================================================
// Choose your primary provider: 'openrouter' or 'gemini'
define('AI_PROVIDER', 'openrouter');

// Gemini (keep for fallback or secondary use)
define('GEMINI_API_KEY', '');

// OpenRouter
define('OPENROUTER_API_KEY', '');
define('OPENROUTER_MODEL', 'anthropic/claude-3-haiku');
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
