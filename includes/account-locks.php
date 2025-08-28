<?php
// includes/account-locks.php — helper library for account locks (users + admin)
// Provides: al_db(), al_ensure(), al_set_lock(), al_clear_lock(), al_get_active_lock(), al_is_locked()
require_once __DIR__ . '/log.php'; // uses __log_db(), __log_ip()

/** mysqli handle aligned with app */
function al_db(): mysqli { return __log_db(); }

/** Ensure schema for admin-set account locks (separate from users.failed/locked_until). */
function al_ensure(mysqli $db): void {
  $db->query("CREATE TABLE IF NOT EXISTS security_account_locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identity VARCHAR(190) NOT NULL, /* email for users, literal 'admin' for admin PIN */
    type ENUM('user','admin') NOT NULL DEFAULT 'user',
    until_at DATETIME NOT NULL,
    reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_ip VARCHAR(64) NULL,
    UNIQUE KEY uniq_identity_type (identity, type),
    INDEX idx_active (type, until_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Upsert a lock. $untilAt must be 'Y-m-d H:i:s' in server time. */
function al_set_lock(mysqli $db, string $identity, string $type, string $untilAt, string $reason=''): bool {
  al_ensure($db);
  $identity = trim($identity);
  $type = ($type === 'admin') ? 'admin' : 'user';
  $ip = __log_ip();
  if ($stmt = $db->prepare("INSERT INTO security_account_locks (identity,type,until_at,reason,created_ip)
                            VALUES (?,?,?,?,?)
                            ON DUPLICATE KEY UPDATE until_at=VALUES(until_at), reason=VALUES(reason), created_ip=VALUES(created_ip)")) {
    $stmt->bind_param('sssss', $identity, $type, $untilAt, $reason, $ip);
    $ok = $stmt->execute(); $stmt->close();
    return $ok;
  }
  return false;
}

/** Clear a lock for identity/type. */
function al_clear_lock(mysqli $db, string $identity, string $type): bool {
  al_ensure($db);
  $type = ($type === 'admin') ? 'admin' : 'user';
  if ($stmt = $db->prepare("DELETE FROM security_account_locks WHERE identity=? AND type=?")) {
    $stmt->bind_param('ss', $identity, $type);
    $ok = $stmt->execute(); $stmt->close();
    return $ok;
  }
  return false;
}

/** Return active lock row (assoc) if identity/type is currently locked, else null. */
function al_get_active_lock(mysqli $db, string $identity, string $type): ?array {
  al_ensure($db);
  $type = ($type === 'admin') ? 'admin' : 'user';
  if ($stmt = $db->prepare("SELECT identity, type, until_at, reason, created_at
                            FROM security_account_locks
                            WHERE identity=? AND type=? AND until_at > NOW() LIMIT 1")) {
    $stmt->bind_param('ss', $identity, $type);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
  }
  return null;
}

/** Convenience: if active, return [true,'Y-m-d H:i:s','reason']; else [false,null,null] */
function al_is_locked(mysqli $db, string $identity, string $type='user'): array {
  $row = al_get_active_lock($db, $identity, $type);
  if ($row) return [true, (string)$row['until_at'], (string)($row['reason'] ?? '')];
  return [false, null, null];
}
