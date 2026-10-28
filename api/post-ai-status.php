<?php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
if (!$post_id) {
    echo json_encode(['error' => 'Post ID required']);
    exit;
}
$stmt = $pdo->prepare("SELECT ai_analysis_id FROM posts WHERE id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch();
if (!$post || !$post['ai_analysis_id']) {
    echo json_encode(['status' => 'pending']);
    exit;
}
$stmt = $pdo->prepare("SELECT ai_reply, emotion FROM ai_analysis WHERE id = ?");
$stmt->execute([$post['ai_analysis_id']]);
$ai = $stmt->fetch();
if ($ai && $ai['ai_reply']) {
    echo json_encode(['status' => 'ready', 'reply' => $ai['ai_reply'], 'emotion' => $ai['emotion']]);
} else {
    echo json_encode(['status' => 'pending']);
}
?>