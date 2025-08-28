<?php
// includes/log.php — unified logger (idempotent, backwards-compatible)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/** Get a mysqli handle using whatever the app already exposes. */
function __log_db(): mysqli {
    if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli)         return $GLOBALS['db'];
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli)     return $GLOBALS['conn'];
    if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) return $GLOBALS['mysqli'];
    if (function_exists('get_db')) {
        $h = get_db();
        if ($h instanceof mysqli) return $h;
    }
    // Last resort: constants (optional)
    $h = @new mysqli(defined('DB_HOST')?DB_HOST:'localhost', defined('DB_USER')?DB_USER:'', defined('DB_PASS')?DB_PASS:'', defined('DB_NAME')?DB_NAME:'');
    if ($h->connect_errno) { http_response_code(500); exit('Database connection failed: '.$h->connect_error); }
    $h->set_charset('utf8mb4');
    return $h;
}

/** Normalize IPv4/IPv6 (::1, ::ffff:127.0.0.1). */
function normalize_ip(string $ip): string {
    $ip = trim($ip);
    if ($ip === '' || strtolower($ip) === 'unknown') return '0.0.0.0';
    if ($ip === '::1') return '127.0.0.1';
    if (stripos($ip, '::ffff:') === 0) return substr($ip, 7);
    return $ip;
}

/** Best-effort client IP (proxy/CDN aware). */
function get_client_ip(): string {
    $candidates = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'];
    foreach ($candidates as $h) {
        if (!empty($_SERVER[$h])) {
            $raw = (string)$_SERVER[$h];
            if ($h === 'HTTP_X_FORWARDED_FOR') {
                foreach (array_map('trim', explode(',', $raw)) as $p) if ($p !== '') return $p;
            }
            return $raw;
        }
    }
    return '0.0.0.0';
}

/** Convenience used around the app. */
function __log_ip(): string { return normalize_ip(get_client_ip()); }

/** Db name for information_schema. */
function __log_dbname(mysqli $db): string {
    $r = $db->query('SELECT DATABASE()');
    if ($r && ($row = $r->fetch_row())) return (string)$row[0];
    return '';
}

/** Column existence check. */
function __log_has_column(mysqli $db, string $table, string $column): bool {
    $schema = __log_dbname($db);
    if ($schema === '') return false;
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
    if (!$stmt = $db->prepare($sql)) return false;
    $stmt->bind_param('sss', $schema, $table, $column);
    $stmt->execute(); $stmt->store_result();
    $ok = $stmt->num_rows > 0;
    $stmt->free_result(); $stmt->close();
    return $ok;
}

/** Ensure tables exist and add missing columns only if needed. */
function __log_ensure_tables(?mysqli $db = null): void {
    $db = $db instanceof mysqli ? $db : __log_db();

    // audit_log — details stored as JSON text (CHECK JSON_VALID may exist)
    $db->query("CREATE TABLE IF NOT EXISTS audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event VARCHAR(64) NOT NULL,
        details JSON NULL,
        actor VARCHAR(64) NULL,
        ip VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // login_attempts — superset schema; nullable extras are fine
    $db->query("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        actor VARCHAR(190) NULL,
        username VARCHAR(190) NULL,
        ip VARCHAR(64) NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        note VARCHAR(255) NULL,
        attempted_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Add any missing columns safely
    foreach ([
        ['login_attempts','actor',       "ALTER TABLE login_attempts ADD COLUMN actor VARCHAR(190) NULL"],
        ['login_attempts','username',    "ALTER TABLE login_attempts ADD COLUMN username VARCHAR(190) NULL"],
        ['login_attempts','ip',          "ALTER TABLE login_attempts ADD COLUMN ip VARCHAR(64) NULL"],
        ['login_attempts','user_agent', "ALTER TABLE login_attempts ADD COLUMN user_agent VARCHAR(255) NULL"],
        ['login_attempts','success',     "ALTER TABLE login_attempts ADD COLUMN success TINYINT(1) NOT NULL DEFAULT 0"],
        ['login_attempts','note',        "ALTER TABLE login_attempts ADD COLUMN note VARCHAR(255) NULL"],
        ['login_attempts','attempted_at',"ALTER TABLE login_attempts ADD COLUMN attempted_at DATETIME NULL"],
        ['login_attempts','created_at',  "ALTER TABLE login_attempts ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"],
    ] as [$t,$c,$ddl]) {
        if (!__log_has_column($db, $t, $c)) { @ $db->query($ddl); }
    }
}

/** Insert an audit event — always stores VALID JSON in `details`. */
function log_audit(string $event, $details = '', string $actor = '', ?string $ip = null): void {
    $db = __log_db(); __log_ensure_tables($db);
    $ev = (string)$event;
    if (is_string($details)) {
        $decoded = json_decode($details, true);
        if ($details !== '' && json_last_error() === JSON_ERROR_NONE) {
            $det = $details; // already JSON text
        } else {
            $det = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    } else {
        $det = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    $ac  = (string)$actor;
    $ipn = normalize_ip($ip ?? get_client_ip());
    if ($stmt = $db->prepare("INSERT INTO audit_log (event, details, actor, ip) VALUES (?, ?, ?, ?)")) {
        $stmt->bind_param('ssss', $ev, $det, $ac, $ipn);
        $stmt->execute(); $stmt->close();
    }
}

/**
 * Record a login attempt (supports BOTH new and old call styles).
 *
 * New (preferred): log_login_attempt(true, 'user login ok', $email[, $ip[, $username]])
 * Old (compat):    log_login_attempt($username, true[, $ip])
 */
function log_login_attempt(/* mixed ...$args */) {
    $db = __log_db(); __log_ensure_tables($db);

    $success = false; $note = ''; $actor = ''; $ip = null; $username = null;

    $args = func_get_args();
    if (isset($args[0]) && is_bool($args[0])) {
        // New signature: (bool $success, string $note='', string $actor='', ?string $ip=null, ?string $username=null)
        $success  = (bool)($args[0] ?? false);
        $note     = (string)($args[1] ?? '');
        $actor    = (string)($args[2] ?? '');
        $ip       = isset($args[3]) ? (string)$args[3] : null;
        $username = isset($args[4]) ? (string)$args[4] : null;
    } else {
        // Old signature: (string $username='', bool $success=false, ?string $ip=null)
        $username = (string)($args[0] ?? '');
        $success  = (bool)($args[1] ?? false);
        $ip       = isset($args[2]) ? (string)$args[2] : null;
        $actor    = $username;
        $note     = $success ? 'user login ok' : 'bad password';
    }

    $ok  = $success ? 1 : 0;
    $ac  = (string)$actor;
    $un  = $username !== null ? (string)$username : null;
    $nt  = (string)$note;
    $ipn = normalize_ip($ip ?? get_client_ip());

    if ($un === null) {
$sql = "INSERT INTO login_attempts (actor, ip, success, note, user_agent, attempted_at)
        VALUES (?, ?, ?, ?, ?, NOW())";
if ($stmt = $db->prepare($sql)) { $stmt->bind_param('ssiss', $ac, $ipn, $ok, $nt, $ua); $stmt->execute(); $stmt->close(); }

    } else {
        $sql = "INSERT INTO login_attempts (actor, username, ip, success, note, user_agent, attempted_at) VALUES (?, ?, ?, ?, ?, NOW())";
        if ($stmt = $db->prepare($sql)) { $stmt->bind_param('sssis', $ac, $un, $ipn, $ok, $nt); $stmt->execute(); $stmt->close(); }
    }
}

/** -----------------------------------------------------------------
 * NEW: log_mask() — tiny helper some pages call (e.g., devlog-delete)
 * Collapses whitespace and truncates to a sensible length.
 * ----------------------------------------------------------------*/
if (!function_exists('log_mask')) {
    function log_mask($value, int $max = 160): string {
        $s = (string)$value;
        // collapse whitespace
        $s = preg_replace('/\s+/u', ' ', $s ?? '');
        // truncate safely
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($s, 'UTF-8') > $max) $s = mb_substr($s, 0, $max - 1, 'UTF-8') . '…';
        } else {
            if (strlen($s) > $max) $s = substr($s, 0, $max - 1) . '…';
        }
        return $s;
    }
}

// ---- Back-compat shims (older names some pages might call) ----
function _dbh(): mysqli { return __log_db(); }
function ensure_tables(): void { __log_ensure_tables(); }
