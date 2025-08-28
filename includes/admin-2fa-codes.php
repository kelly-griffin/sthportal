<?php
// includes/admin-2fa-codes.php — backup codes for Admin 2FA
// Creates: security_admin_backup_codes (hashed, one-time, regeneratable)

require_once __DIR__ . '/log.php'; // __log_db()

function a2bc_db(): mysqli { return __log_db(); }

function a2bc_ensure(mysqli $db): void {
  $db->query("CREATE TABLE IF NOT EXISTS security_admin_backup_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code_hash VARCHAR(255) NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Generate N backup codes, invalidate old ones, return the PLAIN codes (show once). */
function a2bc_generate(mysqli $db, int $count = 10): array {
  a2bc_ensure($db);
  // Invalidate old set by deleting them (simplest + safest)
  $db->query("DELETE FROM security_admin_backup_codes");

  $codes = [];
  for ($i=0; $i<$count; $i++) {
    // 10 hex chars -> XXXXX-XXXXX
    $raw = strtoupper(bin2hex(random_bytes(5)));
    $pretty = substr($raw, 0, 5) . '-' . substr($raw, 5);
    $codes[] = $pretty;

    $hash = password_hash($pretty, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO security_admin_backup_codes (code_hash) VALUES (?)");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $stmt->close();
  }
  return $codes;
}

/** Return count of unused codes left. */
function a2bc_remaining(mysqli $db): int {
  a2bc_ensure($db);
  $res = $db->query("SELECT COUNT(*) FROM security_admin_backup_codes WHERE used_at IS NULL");
  $row = $res ? $res->fetch_row() : [0];
  return (int)($row[0] ?? 0);
}

/** Try one code. On success, mark it used and return true. */
function a2bc_try(mysqli $db, string $code): bool {
  a2bc_ensure($db);
  $code = strtoupper(trim(preg_replace('/[^A-Z0-9-]/i', '', $code)));

  $res = $db->query("SELECT id, code_hash FROM security_admin_backup_codes WHERE used_at IS NULL");
  while ($row = $res->fetch_assoc()) {
    if (password_verify($code, $row['code_hash'])) {
      $id = (int)$row['id'];
      $stmt = $db->prepare("UPDATE security_admin_backup_codes SET used_at = NOW() WHERE id=?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();
      return true;
    }
  }
  return false;
}
