<?php
declare(strict_types=1);

/**
 * includes/bootstrap.php
 * Minimal, no-output bootstrap:
 *  - Loads helpers then config
 *  - Establishes a single mysqli handle
 *  - Exposes get_db(): mysqli
 *  - No license/auth guards here (admin-only)
 *  - No CSS/JS output here
 */

if (!defined('APP_BASE')) {
  define('APP_BASE', str_replace('\\','/', dirname(__DIR__)));
}

// 1) Helpers first (so anyone can safely call h()/asset()/url_root())
$func = APP_BASE . '/includes/functions.php';
if (is_file($func)) { require_once $func; }

// 2) Config — DB constants
$cfg = APP_BASE . '/includes/config.php';
if (!is_file($cfg)) {
  throw new RuntimeException('Missing includes/config.php');
}
require_once $cfg;

// 3) Create (or reuse) a single mysqli; expose accessor
if (!isset($GLOBALS['mysqli']) || !($GLOBALS['mysqli'] instanceof mysqli)) {
  $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  if ($conn->connect_errno) {
    throw new RuntimeException('DB connect failed: ' . $conn->connect_error);
  }
  $GLOBALS['mysqli'] = $conn;
}

if (!function_exists('get_db')) {
  function get_db(): mysqli {
    if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) {
      return $GLOBALS['mysqli'];
    }
    // Rebuild if needed
    require_once APP_BASE . '/includes/config.php';
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_errno) {
      throw new RuntimeException('DB connect failed: ' . $conn->connect_error);
    }
    $GLOBALS['mysqli'] = $conn;
    return $conn;
  }
}

/* NOTE:
 * License/Auth are admin-only. Do NOT include license_guard.php or user-auth here.
 * Admin pages should include them explicitly.
 */
