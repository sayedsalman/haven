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




define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg','image/png','image/webp']);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
