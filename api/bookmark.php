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
$d=haven_input();$post=(int)($d['post_id']??0);if(!$post)haven_json(['success'=>false,'message'=>'Invalid post.'],422);
$pdo=db()->getConnection();$q=$pdo->prepare("SELECT id FROM bookmarks WHERE user_id=:uid AND post_id=:post");$q->execute([':uid'=>$uid,':post'=>$post]);$id=$q->fetchColumn();
if($id){$pdo->prepare("DELETE FROM bookmarks WHERE id=:id")->execute([':id'=>$id]);$saved=false;}else{$pdo->prepare("INSERT INTO bookmarks(user_id,post_id,created_at) VALUES(:uid,:post,NOW())")->execute([':uid'=>$uid,':post'=>$post]);$saved=true;}
haven_json(['success'=>true,'saved'=>$saved]);
