<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/user-auth.php';
header('Content-Type: application/json');

if (!user_logged_in()) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }

function dm_db(): PDO {
  if (function_exists('db')) { $pdo = db(); if ($pdo instanceof PDO) return $pdo; }
  $dsn = defined('DB_DSN') ? DB_DSN : null;
  if ($dsn) {
    $u = defined('DB_USER')?DB_USER:(defined('DB_USERNAME')?DB_USERNAME:null);
    $p = defined('DB_PASS')?DB_PASS:(defined('DB_PASSWORD')?DB_PASSWORD:null);
    return new PDO($dsn,$u,$p,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  }
  $host = defined('DB_HOST')?DB_HOST:'127.0.0.1';
  $name = defined('DB_NAME')?DB_NAME:(defined('DB_DATABASE')?DB_DATABASE:null);
  $u = defined('DB_USER')?DB_USER:(defined('DB_USERNAME')?DB_USERNAME:null);
  $p = defined('DB_PASS')?DB_PASS:(defined('DB_PASSWORD')?DB_PASSWORD:null);
  $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4",$u,$p,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  return $pdo;
}

$pdo = dm_db();
$q = trim((string)($_GET['q'] ?? ''));
$me = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);

if ($q === '') { echo json_encode(['ok'=>true,'users'=>[]]); exit; }

$like = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
$st = $pdo->prepare("SELECT id, name FROM users WHERE id <> ? AND name LIKE ? ORDER BY name LIMIT 20");
$st->execute([$me, $like]);
echo json_encode(['ok'=>true,'users'=>$st->fetchAll()]);
