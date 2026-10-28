<?php
/** Feed for the small notification popover + mark-read. */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
$pdo = hx_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $me = hx_post_guard(); $id = (int)(hx_input()['id'] ?? 0);
    if ($id) $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $me]);
    else $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$me]);
    hx_out(['ok' => true]);
}
$me = hx_need_login();
$unreadOnly = ($_GET['f'] ?? '') === 'unread';
$s = $pdo->prepare('SELECT id, type, message, link, is_read, created_at FROM notifications WHERE user_id = ?' . ($unreadOnly ? ' AND is_read = 0' : '') . ' ORDER BY created_at DESC LIMIT 25');
$s->execute([$me]);
$icons = ['friend_request' => '🤝', 'friend_accept' => '🤝', 'follow' => '➕', 'support' => '💙', 'new_comment' => '💬', 'comment_reply' => '💬', 'badge' => '🏆',
          'message_request' => '✉️', 'message_accept' => '✅', 'message' => '💬', 'consultation' => '🩺', 'appointment' => '📅', 'urgent_report' => '⚠️', 'warning' => '⚠️', 'checkin_reminder' => '🌱'];
$rows = array_map(fn($r) => ['id' => (int)$r['id'], 'icon' => $icons[$r['type']] ?? '🔔', 'text' => $r['message'], 'link' => $r['link'],
                             'read' => (bool)$r['is_read'], 'at' => date('c', strtotime($r['created_at']))], $s->fetchAll());
hx_out(['ok' => true, 'items' => $rows]);
