<?php
// Common response helper is intentionally duplicated in API files so each endpoint
// remains independently deployable on cPanel.
function haven_json(array $data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function haven_user_id(): int {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    return (int)($_SESSION['user_id'] ?? 0);
}
function haven_input(): array {
    $raw=file_get_contents('php://input');
    $json=json_decode($raw,true);
    return is_array($json) ? $json : $_POST;
}

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/database.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$uid=haven_user_id();if(!$uid)haven_json(['success'=>false,'message'=>'Login required.'],401);
$d=haven_input();$post=(int)($d['post_id']??0);$pdo=db()->getConnection();
$q=$pdo->prepare("INSERT IGNORE INTO hidden_posts(user_id,post_id,created_at) VALUES(:uid,:post,NOW())");$q->execute([':uid'=>$uid,':post'=>$post]);
haven_json(['success'=>true,'message'=>'Post hidden from your feed.']);
