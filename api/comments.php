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
$pdo=db()->getConnection();$post=(int)($_GET['post_id']??0);$limit=min(100,max(1,(int)($_GET['limit']??50)));
$q=$pdo->prepare("SELECT c.id,c.post_id,c.user_id,c.parent_id,c.content,c.is_anonymous,c.created_at,
 CASE WHEN c.is_anonymous=1 THEN 'Haven Member' ELSE COALESCE(NULLIF(u.full_name,''),u.username,'Haven Member') END author_name,
 u.avatar_color
 FROM comments c LEFT JOIN users u ON u.id=c.user_id
 WHERE c.post_id=:post ORDER BY c.created_at ASC LIMIT $limit");
$q->execute([':post'=>$post]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as &$r){$r['id']=(int)$r['id'];$r['user_id']=(int)$r['user_id'];$r['parent_id']=$r['parent_id']?(int)$r['parent_id']:null;$r['is_anonymous']=(bool)$r['is_anonymous'];}unset($r);
haven_json(['success'=>true,'comments'=>$rows]);
