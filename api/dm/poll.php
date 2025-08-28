<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/user-auth.php';
header('Content-Type: application/json');

/* robust DB pickup (matches our chat adapter style) */
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

$pdo   = dm_db();
$uid   = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
$peer  = (int)($_GET['with'] ?? 0);
$after = (int)($_GET['after'] ?? 0);

/* ensure tables exist (lightweight) */
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

if ($peer > 0) {
  // Return messages with sender/recipient names
  $st = $pdo->prepare("
    SELECT m.id, m.ts, m.sender_id, su.name AS sender_name,
           m.recipient_id, ru.name AS recipient_name, m.body
    FROM direct_messages m
    LEFT JOIN users su ON su.id = m.sender_id
    LEFT JOIN users ru ON ru.id = m.recipient_id
    WHERE ((m.sender_id=? AND m.recipient_id=?) OR (m.sender_id=? AND m.recipient_id=?))
      AND m.id > ?
    ORDER BY m.id ASC
  ");
  $st->execute([$uid,$peer,$peer,$uid,$after]);
  echo json_encode(['ok'=>true,'messages'=>$st->fetchAll()]); exit;
}

// No peer specified → inbox: recent peers with names
$st = $pdo->prepare("
  SELECT p.peer_id,
         COALESCE(u.name, CONCAT('User #', p.peer_id)) AS peer_name,
         p.last_msg_ts, p.last_msg_id
  FROM dm_peers p
  LEFT JOIN users u ON u.id = p.peer_id
  WHERE p.user_id = ?
  ORDER BY p.last_msg_ts DESC
  LIMIT 50
");
$st->execute([$uid]);
echo json_encode(['ok'=>true,'peers'=>$st->fetchAll()]);
