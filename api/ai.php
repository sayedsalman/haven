<?php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../ai/AIManager.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'chat') {
    $message = $_POST['message'] ?? '';
    if (empty($message)) {
        echo json_encode(['error' => 'Message required']);
        exit;
    }
    $userId = isLoggedIn() ? $_SESSION['user_id'] : 0;
    $manager = new AIManager();
    if ($userId) {
        $response = $manager->chat($message, $userId);
    } else {
        // fallback for guests
        require_once '../ai/MindGuide.php';
        require_once '../ai/providers/GeminiProvider.php';
        $provider = new GeminiProvider(GEMINI_API_KEY);
        $guide = new MindGuide($provider);
        $response = $guide->generateReply($message, 0);
    }
    echo json_encode(['reply' => $response['reply'] ?? 'I\'m here to listen.']);
    exit;
}
echo json_encode(['error' => 'Invalid action']);
?>