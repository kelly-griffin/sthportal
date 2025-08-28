<?php
// includes/password-reset.php — password reset helpers (idempotent, auto-detects password columns)
// Creates password_resets table, generates/validates tokens, updates user password,
// logs audit, and emails the link (falls back to mail()).

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/log.php';

/* ---------------- DB handle ---------------- */
function pr_db(): mysqli
{
    if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli)
        return $GLOBALS['db'];
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli)
        return $GLOBALS['conn'];
    if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli)
        return $GLOBALS['mysqli'];
    if (function_exists('get_db')) {
        $h = get_db();
        if ($h instanceof mysqli)
            return $h;
    }
    $h = new mysqli(defined('DB_HOST') ? DB_HOST : 'localhost', defined('DB_USER') ? DB_USER : '', defined('DB_PASS') ? DB_PASS : '', defined('DB_NAME') ? DB_NAME : '');
    if ($h->connect_errno) {
        http_response_code(500);
        exit('DB connection failed: ' . $h->connect_error);
    }
    $h->set_charset('utf8mb4');
    return $h;
}
function pr_dbname(mysqli $db): string
{
    $r = $db->query('SELECT DATABASE()');
    if ($r && ($row = $r->fetch_row()))
        return (string) $row[0];
    return '';
}
function pr_has_col(mysqli $db, string $table, string $col): bool
{
    $schema = pr_dbname($db);
    if ($schema === '')
        return false;
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
    if (!$stmt = $db->prepare($sql))
        return false;
    $stmt->bind_param('sss', $schema, $table, $col);
    $stmt->execute();
    $stmt->store_result();
    $ok = $stmt->num_rows > 0;
    $stmt->free_result();
    $stmt->close();
    return $ok;
}
function pr_user_columns(mysqli $db): array
{
    $out = [];
    $schema = pr_dbname($db);
    if ($schema === '')
        return $out;
    $sql = "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='users'";
    if (!$stmt = $db->prepare($sql))
        return $out;
    $stmt->bind_param('s', $schema);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_row()))
        $out[] = (string) $row[0];
    $stmt->close();
    return $out;
}

/* ---------------- Ensure tables ---------------- */
function pr_ensure_tables(mysqli $db = null): void
{
    $db = $db ?: pr_db();
    $db->query("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        email VARCHAR(190) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        ip VARCHAR(64) NULL,
        user_agent VARCHAR(255) NULL,
        INDEX (email),
        UNIQUE KEY token_hash_unique (token_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

/* ---------------- Utils ---------------- */
function pr_now(): string
{
    return date('Y-m-d H:i:s');
}
function pr_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'); // e.g. /sthportal
    return $scheme . '://' . $host . $base;
}
function pr_log(string $event, $details = [], string $actor = ''): void
{
    if (function_exists('log_audit')) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
        @log_audit($event, $details, $actor, $ip);
    }
}
function pr_send_email(string $to, string $subject, string $html, string $text = ''): bool
{
    $candidates = ['send_mail', 'mail_send', 'smtp_send', 'app_mail'];
    foreach ($candidates as $fn) {
        if (function_exists($fn)) {
            try {
                return (bool) $fn($to, $subject, $html, $text);
            } catch (Throwable $e) {
            }
        }
    }
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: no-reply@" . (parse_url(pr_base_url(), PHP_URL_HOST) ?: 'localhost') . "\r\n";
    return @mail($to, $subject, $html, $headers);
}

/* ---------------- Public API ---------------- */
function pr_request_reset(string $email, string $ip = '', string $ua = ''): array
{
    $db = pr_db();
    pr_ensure_tables($db);
    $email = trim(mb_strtolower($email));

    // Rate-limit: max 3 in 10 min per email
    if ($stmt = $db->prepare("SELECT COUNT(*) FROM password_resets WHERE email=? AND requested_at > NOW() - INTERVAL 10 MINUTE")) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($cnt);
        $stmt->fetch();
        $stmt->close();
        if ((int) $cnt >= 3) {
            pr_log('password_reset_rate_limited', ['email' => $email], '');
            return [true, ''];
        }
    }

    // Find user (optional)
    $userId = null;
    if ($stmt = $db->prepare("SELECT id FROM users WHERE email=? LIMIT 1")) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($uid);
        if ($stmt->fetch())
            $userId = (int) $uid;
        $stmt->close();
    }

    $token = bin2hex(random_bytes(32)); // 64 hex
    $hash = hash('sha256', $token);
    $exp = date('Y-m-d H:i:s', time() + 3600); // 60 min

    if ($stmt = $db->prepare("INSERT INTO password_resets (user_id, email, token_hash, requested_at, expires_at, ip, user_agent) VALUES (?,?,?,?,?,?,?)")) {
        $now = pr_now();
        $stmt->bind_param('issssss', $userId, $email, $hash, $now, $exp, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }

    $link = pr_base_url() . '/reset-password.php?token=' . $token;
    pr_log('password_reset_requested', ['email' => $email], $email);

    $html = "<p>We received a request to reset your password.</p>
             <p><a href=\"{$link}\">Reset your password</a></p>
             <p>If you didn’t request this, you can ignore this email.</p>";
    $text = "We received a request to reset your password.\n\nReset link: {$link}\n\nIf you didn’t request this, ignore this email.";
    @pr_send_email($email, 'Reset your password', $html, $text);

    return [true, $link];
}
function pr_validate_token(string $token): ?array
{
    $db = pr_db();
    pr_ensure_tables($db);
    if ($token === '' || strlen($token) < 32)
        return null;
    $hash = hash('sha256', $token);

    $sql = "SELECT id, user_id, email, expires_at, used_at FROM password_resets WHERE token_hash=? LIMIT 1";
    if (!$stmt = $db->prepare($sql))
        return null;
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $stmt->bind_result($id, $uid, $email, $exp, $used);
    if (!$stmt->fetch()) {
        $stmt->close();
        return null;
    }
    $stmt->close();

    if (!empty($used))
        return null;
    if (strtotime((string) $exp) < time())
        return null;

    return ['reset_id' => (int) $id, 'user_id' => $uid ? (int) $uid : null, 'email' => (string) $email];
}

/* ---- UPDATED: sets password by updating ANY plausible password column(s) ---- */
function pr_consume_and_set_password(string $token, string $newPassword): bool
{
    $db = pr_db();
    pr_ensure_tables($db);
    $info = pr_validate_token($token);
    if (!$info)
        return false;

    $ok = pr_set_password_for_email($info['email'], $newPassword);
    if (!$ok)
        return false;

    if ($stmt = $db->prepare("UPDATE password_resets SET used_at=NOW() WHERE id=?")) {
        $stmt->bind_param('i', $info['reset_id']);
        $stmt->execute();
        $stmt->close();
    }

    pr_log('password_reset_completed', ['email' => $info['email']], $info['email']);
    return true;
}

/**
 * Update password for user by email:
 * - Finds the user ID by email
 * - Hashes with PASSWORD_DEFAULT
 * - Updates ALL matching cols among:
 *   password_hash, password, pass, pwd, passwd, pass_hash, pwd_hash, passwordhash, user_password, user_pass, pw_hash, hash
 * - Adds updated_at=NOW() if that column exists
 */
function pr_set_password_for_email(string $email, string $newPassword): bool
{
    $db = pr_db();

    // Find user id
    $uid = null;
    if ($stmt = $db->prepare("SELECT id FROM users WHERE email=? LIMIT 1")) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($id);
        if ($stmt->fetch())
            $uid = (int) $id;
        $stmt->close();
    }
    if (!$uid)
        return false;

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Determine which columns exist
    $allCols = pr_user_columns($db);
    $lower = array_map('strtolower', $allCols);
    $map = array_combine($lower, $allCols); // keep original case

    $candidates = [
        'password_hash',
        'password',
        'pass',
        'pwd',
        'passwd',
        'pass_hash',
        'pwd_hash',
        'passwordhash',
        'user_password',
        'user_pass',
        'pw_hash',
        'hash'
    ];
    $targets = [];
    foreach ($candidates as $c) {
        if (isset($map[$c]))
            $targets[] = $map[$c];
    }
    // If none matched, heuristically pick any column name containing pass/pwd/hash
    if (!$targets) {
        foreach ($allCols as $c) {
            if (preg_match('/(pass|pwd|hash)/i', $c))
                $targets[] = $c;
        }
        $targets = array_values(array_unique($targets));
    }
    if (!$targets)
        return false;

    // Build dynamic UPDATE
    $sets = [];
    $bind = [];
    $types = '';
    foreach ($targets as $col) {
        $sets[] = "`$col`=?";
        $bind[] = $hash;
        $types .= 's';
    }

    $hasUpdatedAt = in_array('updated_at', $lower, true);
    if ($hasUpdatedAt)
        $sets[] = "updated_at=NOW()";

    $sql = "UPDATE users SET " . implode(',', $sets) . " WHERE id=?";
    if (!$stmt = $db->prepare($sql))
        return false;

    $types .= 'i';
    $bind[] = $uid;

    // bind dynamically
    $stmt->bind_param($types, ...$bind);
    $stmt->execute();
    $aff = $stmt->affected_rows;
    $stmt->close();

    return $aff >= 0; // even if same hash, count as success
}
