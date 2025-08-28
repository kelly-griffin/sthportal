<?php
// includes/user-auth.php — user auth helpers (subfolder aware, minimal + safe)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';  // secure_session_start(), session_on_auth_success()
require_once __DIR__ . '/log.php';      // __log_db(), __log_ip(), log_audit(), log_login_attempt()

secure_session_start();

// --- Base URL fallback if bootstrap isn't loaded ---
if (!defined('BASE_URL')) {
  $sn    = $_SERVER['SCRIPT_NAME'] ?? '';
  $parts = explode('/', trim($sn, '/'));
  $first = $parts[0] ?? '';
  define('BASE_URL', ($first && $first !== 'index.php') ? ('/' . $first) : '');
}
if (!function_exists('u')) {
  function u(string $p): string { return BASE_URL . '/' . ltrim($p, '/'); }
}
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}

function ua_db(): mysqli { return __log_db(); }

// --------- Optional: idempotent schema helpers ---------
function ua_table_exists(mysqli $db, string $name): bool {
  $name = $db->real_escape_string($name);
  $res  = $db->query("SHOW TABLES LIKE '{$name}'");
  return $res && $res->num_rows > 0;
}

function ua_ensure_users(mysqli $db): void {
  if (ua_table_exists($db, 'users')) return;
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(128) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `role` VARCHAR(32) NOT NULL DEFAULT 'member',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `password_hash` VARCHAR(255) NULL,
  `last_login_at` DATETIME NULL,
  `failed_logins` INT NOT NULL DEFAULT 0,
  `locked_until` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
  $db->query($sql);
}

function ua_ensure_login_attempts(mysqli $db): void {
  if (ua_table_exists($db, 'login_attempts')) return;
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `reason` VARCHAR(64) NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`email`), INDEX (`ip`), INDEX (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
  $db->query($sql);
}

function ua_ensure_resets(mysqli $db): void {
  if (ua_table_exists($db, 'password_resets')) return;
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`), INDEX (`expires_at`), INDEX (`used`),
  CONSTRAINT `fk_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
  $db->query($sql);
}

// --------- URL + session helpers ---------
function ua_sanitize_next(string $raw): string {
  $p = parse_url($raw);
  if (!$p) return u('');
  if (!empty($p['host']) || !empty($p['scheme'])) return u('');
  $path = $p['path'] ?? '/';
  if ($path === '' || $path[0] !== '/') $path = '/' . $path;
  return $path;
}

function user_logged_in(): bool { return !empty($_SESSION['user_id']); }
function current_user_id(): ?int { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }
function current_user_name(): ?string { return isset($_SESSION['user_name']) ? (string)$_SESSION['user_name'] : null; }

function require_user(): void {
  if (!user_logged_in()) {
    $next = $_SERVER['REQUEST_URI'] ?? u('');
    header('Location: ' . u('login.php') . '?next=' . urlencode($next));
    exit;
  }
}

// --------- Core auth API ---------
function user_login(string $email, string $password, ?string &$outMsg = null): bool {
  $outMsg = '';
  $db = ua_db();
  ua_ensure_users($db);
  ua_ensure_login_attempts($db);

  $emailNorm = trim(mb_strtolower($email));
  if ($emailNorm === '' || $password === '') { $outMsg = 'Email and password required.'; return false; }

  if (!($stmt = $db->prepare('SELECT id,name,email,active,password_hash FROM users WHERE email=? LIMIT 1'))) {
    $outMsg = 'Server error.'; return false;
  }
  $stmt->bind_param('s', $emailNorm);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  if (!$row) { $outMsg = 'Invalid email or password.'; return false; }
  if ((int)$row['active'] !== 1) { $outMsg = 'Account is disabled.'; return false; }

  $hash = (string)$row['password_hash'];
  if ($hash === '' || !password_verify($password, $hash)) {
    $outMsg = 'Invalid email or password.'; return false;
  }

  // Success → set session
  $_SESSION['user_id']   = (int)$row['id'];
  $_SESSION['user_name'] = (string)$row['name'];
  $_SESSION['user'] = [ 'id'=>(int)$row['id'], 'display_name'=>(string)$row['name'], 'email'=>(string)$row['email'] ];
  if (function_exists('session_on_auth_success')) session_on_auth_success();

// 🔐 Load team ownership & caps into the session  
require_once __DIR__ . '/acl.php';
load_user_acl((int)$_SESSION['user_id']);

  // Update last_login_at (best-effort)
  if ($stmt = $db->prepare('UPDATE users SET last_login_at=NOW(), failed_logins=0, updated_at=NOW() WHERE id=?')) {
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
  }

  if (function_exists('log_audit')) log_audit('user_login', 'ok email=' . $emailNorm, 'user');
  return true;
}

function user_logout(): void {
  if (function_exists('log_audit') && !empty($_SESSION['user_id'])) log_audit('user_logout', 'ok user_id=' . (int)$_SESSION['user_id'], 'user');
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', !empty($params['secure']), !empty($params['httponly']));
  }
  @session_destroy();
}

// Optional: simple heuristic for captcha need (used by login.php)
if (!function_exists('ua_should_captcha')) {
  function ua_should_captcha(mysqli $db, string $ip, string $email): bool {
    // If there are >= 3 failed attempts in last 15 minutes for this IP or email
    ua_ensure_login_attempts($db);
    $sql = "SELECT COUNT(*) AS c FROM login_attempts WHERE attempted_at >= (NOW() - INTERVAL 15 MINUTE) AND success=0 AND (ip=? OR email=?)";
    if ($stmt = $db->prepare($sql)) {
      $stmt->bind_param('ss', $ip, $email);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $stmt->close();
      return $row && (int)$row['c'] >= 3;
    }
    return false; // fallback
  }
}
