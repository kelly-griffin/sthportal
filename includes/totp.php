<?php
// includes/totp.php — minimal TOTP (RFC 6238) + admin trust tokens
// Self-contained: base32, TOTP calc, DB helpers, trust cookies.
// Uses __log_db() if available; safe fallbacks if you have $db/$mysqli/$conn.

// -------------- DB --------------
function totp_db(): mysqli {
  if (function_exists('__log_db')) return __log_db();
  foreach (['db','mysqli','conn'] as $g) {
    if (isset($GLOBALS[$g]) && $GLOBALS[$g] instanceof mysqli) return $GLOBALS[$g];
  }
  throw new RuntimeException('DB not initialized for TOTP');
}
function totp_ensure_tables(mysqli $db): void {
  // Admin TOTP status
  $db->query("CREATE TABLE IF NOT EXISTS security_admin_totp (
    id TINYINT PRIMARY KEY DEFAULT 1,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    secret VARCHAR(128) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Trusted devices (generic)
  $db->query("CREATE TABLE IF NOT EXISTS security_trusted_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope VARCHAR(32) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    user_id INT NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scope_token (scope, token_hash),
    INDEX idx_scope_exp (scope, expires_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Ensure one row exists for admin totp
  $res = $db->query("SELECT id FROM security_admin_totp WHERE id=1");
  if ($res && !$res->num_rows) { $db->query("INSERT INTO security_admin_totp (id, enabled) VALUES (1,0)"); }
  if ($res) $res->close();
}

// -------------- Base32 --------------
function b32_charset(): string { return 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; }
function b32_encode(string $bin): string {
  $alphabet = b32_charset();
  $bits = ''; $out = '';
  for ($i=0; $i<strlen($bin); $i++) $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
  for ($i=0; $i<strlen($bits); $i+=5) {
    $chunk = substr($bits, $i, 5);
    if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0');
    $out .= $alphabet[bindec($chunk)];
  }
  while (strlen($out) % 8 !== 0) $out .= '='; // pad
  return $out;
}
function b32_decode(string $b32): string {
  $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
  $alphabet = b32_charset(); $map = [];
  for ($i=0; $i<strlen($alphabet); $i++) $map[$alphabet[$i]] = $i;
  $bits = ''; $out = '';
  for ($i=0; $i<strlen($b32); $i++) $bits .= str_pad(decbin($map[$b32[$i]]), 5, '0', STR_PAD_LEFT);
  for ($i=0; $i<strlen($bits); $i+=8) {
    $chunk = substr($bits, $i, 8);
    if (strlen($chunk) === 8) $out .= chr(bindec($chunk));
  }
  return $out;
}

// -------------- TOTP --------------
function totp_secret_generate(int $bytes = 20): string { return b32_encode(random_bytes($bytes)); }
function hotp(string $keyBin, int $counter, int $digits = 6): string {
  $binCounter = pack('N*', 0) . pack('N*', $counter); // 64-bit big-endian
  $hs = hash_hmac('sha1', $binCounter, $keyBin, true);
  $offset = ord(substr($hs, -1)) & 0x0F;
  $bin = (ord($hs[$offset]) & 0x7F) << 24 |
         (ord($hs[$offset+1]) & 0xFF) << 16 |
         (ord($hs[$offset+2]) & 0xFF) << 8 |
         (ord($hs[$offset+3]) & 0xFF);
  $code = $bin % (10 ** $digits);
  return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
}
function totp_now(string $secretB32, int $period = 30, int $digits = 6, int $t0 = 0): string {
  $key = b32_decode($secretB32);
  $counter = (int) floor((time() - $t0) / $period);
  return hotp($key, $counter, $digits);
}
function totp_verify(string $secretB32, string $code, int $window = 1, int $period = 30, int $digits = 6, int $t0 = 0): bool {
  $code = preg_replace('/\s+/', '', $code);
  if (!preg_match('/^\d{6}$/', $code)) return false;
  $key = b32_decode($secretB32);
  $ctr = (int) floor((time() - $t0) / $period);
  for ($w=-$window; $w<=$window; $w++) {
    if (hash_equals(hotp($key, $ctr + $w, $digits), $code)) return true;
  }
  return false;
}

// -------------- Admin TOTP state --------------
function admin_totp_enabled(): bool {
  $db = totp_db(); totp_ensure_tables($db);
  $res = $db->query("SELECT enabled FROM security_admin_totp WHERE id=1");
  $row = $res ? $res->fetch_assoc() : null; if ($res) $res->close();
  return (bool)($row['enabled'] ?? 0);
}
function admin_totp_secret(): ?string {
  $db = totp_db(); totp_ensure_tables($db);
  $res = $db->query("SELECT secret FROM security_admin_totp WHERE id=1");
  $row = $res ? $res->fetch_assoc() : null; if ($res) $res->close();
  $s = trim((string)($row['secret'] ?? ''));
  return $s !== '' ? $s : null;
}
function admin_totp_set_secret(string $secretB32): void {
  $db = totp_db(); totp_ensure_tables($db);
  $now = date('Y-m-d H:i:s');
  $stmt = $db->prepare("UPDATE security_admin_totp SET secret=?, updated_at=? WHERE id=1");
  $stmt->bind_param('ss', $secretB32, $now); $stmt->execute(); $stmt->close();
}
function admin_totp_set_enabled(bool $on): void {
  $db = totp_db(); totp_ensure_tables($db);
  $now = date('Y-m-d H:i:s'); $en = $on ? 1 : 0;
  $stmt = $db->prepare("UPDATE security_admin_totp SET enabled=?, updated_at=? WHERE id=1");
  $stmt->bind_param('is', $en, $now); $stmt->execute(); $stmt->close();
}

// -------------- Trust tokens (remember this browser) --------------
function admin_trust_cookie_name(): string { return 'adm2fa'; }
function admin_trust_is_valid(): bool {
  if (empty($_COOKIE[admin_trust_cookie_name()])) return false;
  $token = (string)$_COOKIE[admin_trust_cookie_name()];
  if ($token === '') return false;
  $hash = hash('sha256', $token);
  $db = totp_db(); totp_ensure_tables($db);
  $scope = 'admin_totp';
  $stmt = $db->prepare("SELECT id FROM security_trusted_tokens WHERE scope=? AND token_hash=? AND expires_at > NOW() LIMIT 1");
  $stmt->bind_param('ss', $scope, $hash);
  $stmt->execute(); $res = $stmt->get_result(); $ok = $res && $res->num_rows > 0;
  $stmt->close();
  if ($ok) {
    // touch last_used_at
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("UPDATE security_trusted_tokens SET last_used_at=? WHERE scope=? AND token_hash=?");
    $stmt->bind_param('sss', $now, $scope, $hash);
    $stmt->execute(); $stmt->close();
  }
  return $ok;
}
function admin_trust_set(int $days = 30): void {
  $token = bin2hex(random_bytes(24));
  $hash = hash('sha256', $token);
  $db = totp_db(); totp_ensure_tables($db);
  $scope = 'admin_totp';
  $exp = (new DateTimeImmutable('now'))->modify("+{$days} days")->format('Y-m-d H:i:s');
  $stmt = $db->prepare("INSERT INTO security_trusted_tokens (scope, token_hash, expires_at) VALUES (?, ?, ?)");
  $stmt->bind_param('sss', $scope, $hash, $exp);
  $stmt->execute(); $stmt->close();

  $params = ['expires' => time() + ($days*86400), 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax'];
  setcookie(admin_trust_cookie_name(), $token, $params);
}
function admin_trust_clear(): void {
  if (!empty($_COOKIE[admin_trust_cookie_name()])) {
    $params = ['expires' => time() - 3600, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax'];
    setcookie(admin_trust_cookie_name(), '', $params);
  }
}
