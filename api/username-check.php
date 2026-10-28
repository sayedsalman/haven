<?php
include '../includes/config.php';
include '../includes/database.php';

$username = $_GET['username'] ?? '';
if (strlen($username) < 3) {
    echo json_encode(['available' => false, 'message' => 'Too short']);
    exit;
}
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
$exists = $stmt->fetch();
echo json_encode(['available' => !$exists]);
?>