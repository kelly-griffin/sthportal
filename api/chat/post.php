<?php
// api/chat/post.php
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

$raw = json_decode(file_get_contents('php://input'), true);
if (!is_array($raw)) { $raw = $_POST; }

$channel = strtolower(preg_replace('/[^a-z0-9\-]/','', $raw['channel'] ?? 'general'));
$body    = trim((string)($raw['body'] ?? ''));

// Validate
if ($body === '' || mb_strlen($body) > 2000) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'empty_or_too_long']);
    exit;
}

// Simple flood control: 1 msg/sec per user
$now = time();
if (!isset($_SESSION['chat_last_post_ts'])) { $_SESSION['chat_last_post_ts'] = 0; }
if ($now - (int)$_SESSION['chat_last_post_ts'] < 1) {
    http_response_code(429);
    echo json_encode(['ok'=>false,'error'=>'rate_limited']);
    exit;
}

try {
    $pdo = chat_db();

    // Channel
    $stmt = $pdo->prepare('SELECT id FROM chat_channels WHERE slug = ?');
    $stmt->execute([$channel]);
    $cid = (int)($stmt->fetchColumn() ?: 0);
    if (!$cid) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'no_channel']);
        exit;
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $userName = (string)($_SESSION['user_name'] ?? ($_SESSION['username'] ?? 'User'));

    $stmt = $pdo->prepare('
        INSERT INTO chat_messages (channel_id, user_id, username, body)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([$cid, $userId, $userName, $body]);

    $_SESSION['chat_last_post_ts'] = $now;

    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server']);
}
