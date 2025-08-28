<?php
// api/chat/poll.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/user-auth.php';

header('Content-Type: application/json');

function chat_db(): PDO {
    // 1) App helper
    if (function_exists('db')) {
        $pdo = db();
        if ($pdo instanceof PDO) return $pdo;
    }

    // 2) Global PDO
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }

    // 3) Full DSN (username/password optional)
    $dsn = defined('DB_DSN') ? constant('DB_DSN') : null;
    if ($dsn) {
        $user = defined('DB_USER')      ? constant('DB_USER')
              : (defined('DB_USERNAME') ? constant('DB_USERNAME') : null);
        $pass = defined('DB_PASS')      ? constant('DB_PASS')
              : (defined('DB_PASSWORD') ? constant('DB_PASSWORD') : null);

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    // 4) Classic constants (DB_HOST/DB_NAME/DB_USER/DB_PASS)
    if (defined('DB_HOST') || defined('DB_NAME') || defined('DB_DATABASE')) {
        $host    = defined('DB_HOST')    ? constant('DB_HOST')    : '127.0.0.1';
        $dbname  = defined('DB_NAME')    ? constant('DB_NAME')
                 : (defined('DB_DATABASE') ? constant('DB_DATABASE') : null);
        $user    = defined('DB_USER')    ? constant('DB_USER')
                 : (defined('DB_USERNAME') ? constant('DB_USERNAME') : null);
        $pass    = defined('DB_PASS')    ? constant('DB_PASS')
                 : (defined('DB_PASSWORD') ? constant('DB_PASSWORD') : null);
        $charset = defined('DB_CHARSET') ? constant('DB_CHARSET') : 'utf8mb4';

        if ($dbname) {
            $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
    }

    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_unavailable']);
    exit;
}



// Auth
if (!function_exists('user_logged_in') || !user_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'auth']);
    exit;
}

$channel = strtolower(preg_replace('/[^a-z0-9\-]/','', $_GET['channel'] ?? 'general'));
$after   = (int)($_GET['after'] ?? 0);

try {
    $pdo = chat_db();

    // Look up channel
    $stmt = $pdo->prepare('SELECT id FROM chat_channels WHERE slug = ?');
    $stmt->execute([$channel]);
    $cid = (int)($stmt->fetchColumn() ?: 0);

    if (!$cid) {
        // No channel yet -> empty is fine
        echo json_encode(['ok'=>true,'messages'=>[]]);
        exit;
    }

    $stmt = $pdo->prepare('
        SELECT id, user_id, username, body, UNIX_TIMESTAMP(created_at) AS ts
        FROM chat_messages
        WHERE channel_id = ? AND id > ?
        ORDER BY id ASC
        LIMIT 200
    ');
    $stmt->execute([$cid, $after]);
    $rows = $stmt->fetchAll();

    echo json_encode(['ok'=>true,'messages'=>$rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server']);
}
