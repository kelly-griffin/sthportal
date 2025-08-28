<?php
// includes/admin-helpers.php — tiny utils shared by admin pages

function admin_db(): mysqli {
  if (isset($GLOBALS['db'])     && $GLOBALS['db']     instanceof mysqli) return $GLOBALS['db'];
  if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) return $GLOBALS['mysqli'];
  if (function_exists('get_db')) { $x = get_db(); if ($x instanceof mysqli) return $x; }
  if (function_exists('db'))     { $x = db();     if ($x instanceof mysqli) return $x; }
  throw new RuntimeException('DB not initialized');
}

function admin_csrf(): string {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}

function admin_back(string $default = 'index.php'): string {
  $raw  = (string)($_REQUEST['back'] ?? $default);
  $back = rawurldecode($raw);
  if ($back === '') $back = $default;
  // keep local (strip domain)
  if (preg_match('~^https?://~i', $back)) {
    $u = parse_url($back);
    $back = ($u['path'] ?? $default) . (isset($u['query']) ? '?'.$u['query'] : '');
  }
  return $back;
}

function admin_redirect(string $back, array $params = []): never {
  $sep = (strpos($back, '?') !== false) ? '&' : '?';
  header('Location: ' . $back . $sep . http_build_query($params));
  exit;
}

function admin_require_perm(string $perm): void {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (empty($_SESSION['is_admin'])) { header('Location: admin-login.php'); exit; }
  // Full admins bypass granular checks
  if (!empty($_SESSION['is_admin'])) return;
  if (empty($_SESSION['perms'][$perm] ?? null)) { header('Location: admin-login.php'); exit; }
}
