<?php
declare(strict_types=1);

// Common bootstrap for every page.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';          // expects $db = new mysqli(...)
// Normalize DB handle to $db for all pages
if (!isset($db) || !($db instanceof mysqli)) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $db = $mysqli;
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $db = $conn;
    }
}
require_once __DIR__ . '/license.php';     // keep any helpers you already have

// Optional: license guard if you added it
if (file_exists(__DIR__ . '/license_guard.php')) {
    require_once __DIR__ . '/license_guard.php';
}
// --- Base URL helper (handles subfolder like /sthportal) ---
if (!defined('BASE_URL')) {
  $sn    = $_SERVER['SCRIPT_NAME'] ?? '';
  $parts = explode('/', trim($sn, '/'));
  $first = $parts[0] ?? '';
  // If app is deployed under /sthportal/... first segment = 'sthportal'
  define('BASE_URL', ($first && $first !== 'index.php') ? ('/' . $first) : '');
}
if (!function_exists('u')) {
  function u(string $p): string { return BASE_URL . '/' . ltrim($p, '/'); }
}
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}
?>
<link rel="stylesheet" href="/sthportal/assets/css/global.css">