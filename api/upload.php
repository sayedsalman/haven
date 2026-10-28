<?php
// Image upload endpoint (#13) - validates the real file type, size, and stores under a random name.
include '../includes/config.php';
include '../includes/database.php';
include '../includes/auth.php';
include '../includes/upload_helper.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'No file']);
    exit;
}
$r = haven_save_upload($_FILES['file'], ['image'], __DIR__ . '/../uploads/posts', 'uploads/posts', 5 * 1024 * 1024);
echo json_encode($r['ok']
    ? ['success' => true, 'url' => SITE_URL . $r['path']]
    : ['success' => false, 'error' => $r['error']]);
