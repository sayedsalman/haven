<?php
include '../includes/config.php';
include '../includes/database.php';
include '../includes/auth.php';
include '../includes/functions.php';

$q = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'all';

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$results = [];
if ($type == 'posts' || $type == 'all') {
    $stmt = $pdo->prepare("SELECT id, title, content, 'post' as type FROM posts WHERE status='published' AND (title LIKE ? OR content LIKE ?) LIMIT 20");
    $like = "%$q%";
    $stmt->execute([$like, $like]);
    $posts = $stmt->fetchAll();
    $results = array_merge($results, $posts);
}
if ($type == 'articles' || $type == 'all') {
    $stmt = $pdo->prepare("SELECT id, title, excerpt, 'article' as type FROM articles WHERE title LIKE ? OR content LIKE ? LIMIT 10");
    $like = "%$q%";
    $stmt->execute([$like, $like]);
    $articles = $stmt->fetchAll();
    $results = array_merge($results, $articles);
}
if ($type == 'users' || $type == 'all') {
    $stmt = $pdo->prepare("SELECT id, username, anonymous_name, 'user' as type FROM users WHERE username LIKE ? OR anonymous_name LIKE ? LIMIT 10");
    $like = "%$q%";
    $stmt->execute([$like, $like]);
    $users = $stmt->fetchAll();
    $results = array_merge($results, $users);
}

header('Content-Type: application/json');
echo json_encode($results);
?>