<?php
// includes/security-ip.php — IP allow/ban + TEMP ban enforcement for login & admin
// Central gate: call security_ip_enforce_or_deny() before showing any login form.
// Your app already does this in includes/auth.php and login.php.

require_once __DIR__ . '/log.php'; // __log_db(), __log_ip(), log_audit(), log_login_attempt()

/** Ensure permanent rules table exists. */
function security_ip_rules_ensure(mysqli $db = null): mysqli {
    $db = $db instanceof mysqli ? $db : __log_db();
    $db->query("CREATE TABLE IF NOT EXISTS security_ip_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(64) NOT NULL,
        rule ENUM('ban','allow') NOT NULL,
        note VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_ip_rule (ip, rule)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    return $db;
}

/** Ensure temporary bans table exists. */
function security_ip_temp_bans_ensure(mysqli $db = null): mysqli {
    $db = $db instanceof mysqli ? $db : __log_db();
    $db->query("CREATE TABLE IF NOT EXISTS security_ip_temp_bans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(64) NOT NULL,
        until_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_ip (ip)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    return $db;
}

/** True if ip has explicit ALLOW rule (allow wins over ban). */
function security_ip_is_allowed(string $ip, mysqli $db = null): bool {
    $db = security_ip_rules_ensure($db);
    if (!$stmt = $db->prepare("SELECT 1 FROM security_ip_rules WHERE ip=? AND rule='allow' LIMIT 1")) return false;
    $stmt->bind_param('s', $ip); $stmt->execute(); $ok = (bool)($stmt->get_result()->fetch_row()); $stmt->close();
    return $ok;
}

/** True if ip has explicit BAN rule and no allow override. */
function security_ip_is_banned(string $ip, mysqli $db = null): bool {
    $db = security_ip_rules_ensure($db);
    if (security_ip_is_allowed($ip, $db)) return false;
    if (!$stmt = $db->prepare("SELECT 1 FROM security_ip_rules WHERE ip=? AND rule='ban' LIMIT 1")) return false;
    $stmt->bind_param('s', $ip); $stmt->execute(); $ok = (bool)($stmt->get_result()->fetch_row()); $stmt->close();
    return $ok;
}

/** If temp-banned, return until_at (string); otherwise null. */
function security_ip_temp_ban_until(string $ip, mysqli $db = null): ?string {
    $db = security_ip_temp_bans_ensure($db);
    if (!$stmt = $db->prepare("SELECT until_at FROM security_ip_temp_bans WHERE ip=? LIMIT 1")) return null;
    $stmt->bind_param('s', $ip); $stmt->execute(); $res = $stmt->get_result(); $row = $res ? $res->fetch_assoc() : null; $stmt->close();
    if (!$row) return null;
    $until = (string)$row['until_at'];
    return (strtotime($until) > time()) ? $until : null;
}

/** Convenience setters (optional, for other pages/tools to call). */
function security_ip_temp_ban_set(string $ip, int $hours = 24, mysqli $db = null): void {
    $db = security_ip_temp_bans_ensure($db);
    $until = (new DateTimeImmutable('now'))->modify("+{$hours} hour")->format('Y-m-d H:i:s');
    if ($stmt = $db->prepare("INSERT INTO security_ip_temp_bans (ip, until_at) VALUES (?, ?) ON DUPLICATE KEY UPDATE until_at=VALUES(until_at)")) {
        $stmt->bind_param('ss', $ip, $until); $stmt->execute(); $stmt->close();
    }
}
function security_ip_temp_ban_clear(string $ip, mysqli $db = null): void {
    $db = security_ip_temp_bans_ensure($db);
    if ($stmt = $db->prepare("DELETE FROM security_ip_temp_bans WHERE ip=?")) { $stmt->bind_param('s', $ip); $stmt->execute(); $stmt->close(); }
}

/**
 * Gatekeeper — call near the top of login.php and includes/auth.php.
 * Blocks permanent bans (403) and temp bans (429 + Retry-After).
 */
function security_ip_enforce_or_deny(?string $actor = null): void {
    $db = __log_db();
    $ip = __log_ip();

    // Permanent ban?
    if (security_ip_is_banned($ip, $db)) {
        log_login_attempt(false, 'ip_banned', (string)($actor ?? ''), $ip);
        log_audit('ip_blocked', ['type'=>'permanent','ip'=>$ip,'where'=>$_SERVER['REQUEST_URI'] ?? 'login.php'], 'security');
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Access denied.";
        exit;
    }

    // Temp ban?
    $until = security_ip_temp_ban_until($ip, $db);
    if ($until !== null) {
        $secs = max(1, strtotime($until) - time());
        $mins = (int)ceil($secs / 60);
        log_login_attempt(false, 'ip_temp_ban', (string)($actor ?? ''), $ip);
        log_audit('ip_blocked', ['type'=>'temporary','ip'=>$ip,'until'=>$until,'where'=>$_SERVER['REQUEST_URI'] ?? 'login.php'], 'security');

        http_response_code(429);
        header('Retry-After: ' . $secs);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Too many attempts from your network. Try again in ~{$mins} minute(s).";
        exit;
    }
}
