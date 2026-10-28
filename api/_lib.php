<?php
/**
 * Shared helpers for the requirement endpoints (vote, rating, phone, locations, graphql).
 * Same session login the rest of Haven uses; JSON in, JSON out.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function hv_json(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function hv_uid(): int { return (int)($_SESSION['user_id'] ?? 0); }
function hv_need_login(): int {
    $u = hv_uid();
    if (!$u) hv_json(['success' => false, 'message' => 'Please log in first.'], 401);
    return $u;
}
function hv_input(): array {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw ?: '', true);
    return is_array($j) ? $j : array_merge($_GET, $_POST);
}
function hv_pdo(): PDO { return db()->getConnection(); }
function hv_has_column(PDO $pdo, string $table, string $col): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $s->execute([$table, $col]);
    return (int)$s->fetchColumn() > 0;
}
