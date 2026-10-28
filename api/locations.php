<?php
/** Dependent drop-down data (#6): ?type=countries  or  ?type=cities&country=Bangladesh */
require_once __DIR__ . '/_lib.php';
$pdo = hv_pdo();
$type = $_GET['type'] ?? 'countries';
if ($type === 'cities') {
    $s = $pdo->prepare("SELECT ci.name FROM cities ci JOIN countries co ON co.id = ci.country_id WHERE co.name = ? ORDER BY ci.name");
    $s->execute([trim((string)($_GET['country'] ?? ''))]);
    hv_json(['success' => true, 'items' => $s->fetchAll(PDO::FETCH_COLUMN)]);
}
hv_json(['success' => true, 'items' => $pdo->query("SELECT name FROM countries ORDER BY name")->fetchAll(PDO::FETCH_COLUMN)]);
