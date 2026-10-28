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
require_once __DIR__.'/../includes/badges.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$uid=haven_user_id();if(!$uid)haven_json(['success'=>false,'message'=>'Login required.'],401);
$d=haven_input();
$post=(int)($d['post_id']??0);
$reason=trim((string)($d['reason']??'other'));
$description=trim((string)($d['description']??$d['details']??''));
$targetType=(string)($d['target_type']??'post');
$targetId=(int)($d['target_id']??0);
if(!in_array($targetType,['post','comment','user','profile','story'],true))$targetType='post';
$priority=in_array($reason,['self_harm','dangerous_content'],true)?'high':'medium';
$pdo=db()->getConnection();
$__rc=$pdo->prepare("SELECT COUNT(*) FROM reports WHERE reporter_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)");$__rc->execute([$uid]);
if((int)$__rc->fetchColumn()>=30)haven_json(['success'=>false,'message'=>'Too many reports in a short time.'],429);

if($targetType==='post'&&$post){
    $chk=$pdo->prepare("SELECT id FROM posts WHERE id=?");$chk->execute([$post]);
    if(!$chk->fetch())haven_json(['success'=>false,'message'=>'Post not found.'],404);
}

$q=$pdo->prepare("INSERT INTO reports(reporter_id,target_type,target_id,post_id,reason,description,priority,status,created_at) VALUES(:uid,:ttype,:tid,:post,:reason,:desc,:priority,'pending',NOW())");
try{
    $q->execute([':uid'=>$uid,':ttype'=>$targetType,':tid'=>($targetType==='post'?$post:($targetId?:$post)),':post'=>($targetType==='post'?$post:null),':reason'=>$reason,':desc'=>$description,':priority'=>$priority]);
    if($reason==='self_harm'||$reason==='dangerous_content'){
        // High-priority reports notify moderators/admins immediately rather
        // than waiting to be picked up from the general queue.
        $staff=$pdo->query("SELECT id FROM users WHERE role IN('moderator','admin') AND is_active=1")->fetchAll(PDO::FETCH_COLUMN);
        $notif=$pdo->prepare("INSERT INTO notifications(user_id,type,message,link,is_read) VALUES(?,'urgent_report',?,?,0)");
        foreach($staff as $staffId){$notif->execute([$staffId,'⚠️ Urgent report submitted — please review.','moderator.php?view=reports']);}
    }
    haven_json(['success'=>true,'message'=>'Thank you. The report was sent to moderation.']);
}catch(Throwable $e){error_log('[Haven] report.php failed: '.$e->getMessage());haven_json(['success'=>false,'message'=>'Could not submit report.'],500);}
