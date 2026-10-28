<?php
include '../includes/config.php';
include '../includes/database.php';
include '../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$mood = isset($_POST['mood']) ? $_POST['mood'] : '';
if (!$mood) {
    echo json_encode(['success' => false, 'error' => 'No mood provided']);
    exit;
}
$user_id = $_SESSION['user_id'];
$date = date('Y-m-d');
$stmt = $pdo->prepare("INSERT INTO mood_logs (user_id, mood_value, log_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE mood_value = ?");
$stmt->execute([$user_id, $mood, $date, $mood]);
echo json_encode(['success' => true]);
?>