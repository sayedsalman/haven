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
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/badges.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$uid=haven_user_id();if(!$uid)haven_json(['success'=>false,'message'=>'Login required.'],401);
$d=haven_input();

// Sending encouragement/support directly to another user (used on profile
// pages) is a distinct action from reacting to a post — it has no post_id
// and targets a user instead, so it's handled first and separately.
if(($d['action']??'')==='send_support'){
    $target=(int)($d['user_id']??0);
    if(!$target||$target===$uid)haven_json(['success'=>false,'message'=>'Invalid user.'],422);
    $pdo=db()->getConnection();
    $blocked=$pdo->prepare("SELECT id FROM blocked_users WHERE (user_id=:a1 AND blocked_id=:b1) OR (user_id=:b2 AND blocked_id=:a2)");
    $blocked->execute([':a1'=>$uid,':b1'=>$target,':b2'=>$uid,':a2'=>$target]);
    if($blocked->fetch())haven_json(['success'=>false,'message'=>'This action is not available for this user.'],403);
    createNotification($target,'support','Someone sent you support 🌿','profile.php?id='.$uid,$pdo);
    haven_json(['success'=>true]);
}

$post=(int)($d['post_id']??0);$type=(string)($d['reaction_type']??'support');
$allowed=['like','dislike','support','empathy','helpful'];
$EMOJI=['like'=>'👍','dislike'=>'👎','support'=>'🌿','empathy'=>'💚','helpful'=>'✨'];if(!in_array($type,$allowed,true))$type='support';
$pdo=db()->getConnection();$check=$pdo->prepare("SELECT id FROM reactions WHERE post_id=:post AND user_id=:uid");$check->execute([':post'=>$post,':uid'=>$uid]);$old=$check->fetch(PDO::FETCH_ASSOC);
if($old){
 if(($d['toggle']??true) && $type===(string)($d['current_reaction']??'')){ $pdo->prepare("DELETE FROM reactions WHERE id=:id")->execute([':id'=>$old['id']]);$active=false;}
 else{$pdo->prepare("UPDATE reactions SET reaction_type=:type,emoji=:emoji WHERE id=:id")->execute([':type'=>$type,':emoji'=>$EMOJI[$type],':id'=>$old['id']]);$active=true;}
}else{$pdo->prepare("INSERT INTO reactions(post_id,user_id,reaction_type,emoji) VALUES(:post,:uid,:type,:emoji)")->execute([':post'=>$post,':uid'=>$uid,':type'=>$type,':emoji'=>$EMOJI[$type]]);$active=true;}
$cnt=$pdo->prepare("SELECT COUNT(*) FROM reactions WHERE post_id=:post");$cnt->execute([':post'=>$post]);$count=(int)$cnt->fetchColumn();
$pdo->prepare("UPDATE posts SET reaction_count=:c WHERE id=:post")->execute([':c'=>$count,':post'=>$post]);
if($active) havenCheckAndAwardBadges($pdo,$uid);
$lk=$pdo->prepare("SELECT SUM(reaction_type='like'),SUM(reaction_type='dislike') FROM reactions WHERE post_id=:post");$lk->execute([':post'=>$post]);[$likes,$dislikes]=array_map('intval',$lk->fetch(PDO::FETCH_NUM));
haven_json(['success'=>true,'active'=>$active,'reaction_type'=>$active?$type:null,'reaction_count'=>$count,'like_count'=>$likes,'dislike_count'=>$dislikes]);
