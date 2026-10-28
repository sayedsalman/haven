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
$d=haven_input();
$action=(string)($d['action']??'add');   // callers send action=add (form) or omit it (JSON)
$pdo=db()->getConnection();

if($action==='delete'){
    $id=(int)($d['id']??0);
    $s=$pdo->prepare("SELECT post_id,user_id FROM comments WHERE id=:id");$s->execute([':id'=>$id]);$row=$s->fetch(PDO::FETCH_ASSOC);
    if(!$row||(int)$row['user_id']!==$uid)haven_json(['success'=>false,'message'=>'Unauthorized.'],403);
    $pdo->prepare("DELETE FROM comments WHERE id=:id")->execute([':id'=>$id]);
    $pdo->prepare("UPDATE posts SET comment_count=GREATEST(comment_count-1,0) WHERE id=:id")->execute([':id'=>$row['post_id']]);
    haven_json(['success'=>true]);
}
if($action!=='add')haven_json(['success'=>false,'message'=>'Invalid action.'],400);

$post=(int)($d['post_id']??0);$content=trim((string)($d['content']??''));$parent=(int)($d['parent_id']??0);
if(!$post||$content==='')haven_json(['success'=>false,'message'=>'Comment cannot be empty.'],422);
if(mb_strlen($content)>3000)haven_json(['success'=>false,'message'=>'Comment is too long.'],422);
$q=$pdo->prepare("INSERT INTO comments(post_id,user_id,parent_id,content,is_anonymous,created_at) VALUES(:post,:uid,:parent,:content,:anon,NOW())");
$q->execute([':post'=>$post,':uid'=>$uid,':parent'=>$parent?:null,':content'=>$content,':anon'=>!empty($d['is_anonymous'])?1:0]);
$cid=(int)$pdo->lastInsertId();$pdo->prepare("UPDATE posts SET comment_count=comment_count+1 WHERE id=:id")->execute([':id'=>$post]);
$u=$pdo->prepare("SELECT COALESCE(NULLIF(full_name,''),username) name,avatar_color FROM users WHERE id=:id");$u->execute([':id'=>$uid]);$user=$u->fetch(PDO::FETCH_ASSOC);
haven_json(['success'=>true,'comment'=>['id'=>$cid,'post_id'=>$post,'user_id'=>$uid,'parent_id'=>$parent?:null,'content'=>$content,'author_name'=>$user['name']??'Haven Member','avatar_color'=>$user['avatar_color']??'#8AA39B','created_at'=>date('c')]],201);
