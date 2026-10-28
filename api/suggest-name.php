<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/pseudonym.php';

header('Content-Type: application/json; charset=utf-8');
$pdo = db()->getConnection();
echo json_encode(['success' => true, 'name' => generateUniqueHavenPseudonym($pdo)]);
