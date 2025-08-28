<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/user-auth.php';
header('Content-Type: application/json');

function dm_db(): PDO {
  if (function_exists('db')) { $pdo = db(); if ($pdo instanceof PDO) return $pdo; }
  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) return $GLOBALS['pdo'];
  $dsn = defined('DB_DSN') ? constant('DB_DSN') : null;
  if ($dsn) {
    $user = defined('DB_USER') ? constant('DB_USER') : (defined('DB_USERNAME') ? constant('DB_USERNAME') : null);
    $pass = defined('DB_PASS') ? constant('DB_PASS') : (defined('DB_PASSWORD') ? constant('DB_PASSWORD') : null);
    return new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }
  if (defined('DB_HOST') || defined('DB_NAME') || defined('DB_DATABASE')) {
    $host    = defined('DB_HOST')    ? constant('DB_HOST')    : '127.0.0.1';
    $dbname  = defined('DB_NAME')    ? constant('DB_NAME')    : (defined('DB_DATABASE') ? constant('DB_DATABASE') : null);
    $user    = defined('DB_USER')    ? constant('DB_USER')    : (defined('DB_USERNAME') ? constant('DB_USERNAME') : null);
    $pass    = defined('DB_PASS')    ? constant('DB_PASS')    : (defined('DB_PASSWORD') ? constant('DB_PASSWORD') : null);
    $charset = defined('DB_CHARSET') ? constant('DB_CHARSET') : 'utf8mb4';
    if ($dbname) {
      return new PDO("mysql:host={$host};dbname={$dbname};charset={$charset}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
    }
  }
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'db_unavailable']); exit;
}

if (!user_logged_in()) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

$pdo = dm_db();

$raw  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$to   = (int)($raw['to'] ?? 0);
$body = trim((string)($raw['body'] ?? ''));

if ($to <= 0 || $body === '' || mb_strlen($body) > 2000) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'bad_request']);
  exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS direct_messages(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ts INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  recipient_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  KEY ix_pair(ts, sender_id, recipient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS dm_peers(
  user_id INT UNSIGNED NOT NULL,
  peer_id INT UNSIGNED NOT NULL,
  last_msg_ts INT UNSIGNED NOT NULL,
  last_msg_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, peer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$uid = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
$now = time();

$st = $pdo->prepare("INSERT INTO direct_messages(ts,sender_id,recipient_id,body) VALUES (?,?,?,?)");
$st->execute([$now, $uid, $to, $body]);
$msgId = (int)$pdo->lastInsertId();

$st = $pdo->prepare("REPLACE INTO dm_peers(user_id,peer_id,last_msg_ts,last_msg_id) VALUES (?,?,?,?)");
$st->execute([$uid, $to, $now, $msgId]);
$st->execute([$to, $uid, $now, $msgId]);

echo json_encode(['ok'=>true,'id'=>$msgId,'ts'=>$now]);
