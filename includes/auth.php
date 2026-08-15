<?php


function isLoggedIn() { return isset($_SESSION['user_id']); }

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = db();
    $users = $db->select('users', ['id' => $_SESSION['user_id']]);
    return $users[0] ?? null;
}
function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] != $role && $_SESSION['role'] != 'admin') {
        header('Location: index.php');
        exit;
    }
}

function getAnonymousName($user_id, $pdo) {
    $stmt = $pdo->prepare("SELECT anonymous_name FROM profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return $row ? $row['anonymous_name'] : 'Anonymous';
}

function getUserRole($user_id, $pdo) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return $row ? $row['role'] : 'user';
}
?>
