<?php
// /sthportal/tools/health.php — quick, read-only sanity page
declare(strict_types=1);
@require_once __DIR__ . '/../includes/bootstrap.php'; // auto_prepend handles this anyway

header('Content-Type: text/plain; charset=utf-8');

echo "Portal health @ " . date('Y-m-d H:i:s') . "\n\n";

$ok = true;

// Session
echo "[SESSION] ";
if (session_status() === PHP_SESSION_ACTIVE) {
  echo "active\n";
} else { echo "NOT ACTIVE\n"; $ok = false; }

// DB
echo "[DB] ";
try {
  $pdo = function_exists('db') ? db() : (function_exists('pdo') ? pdo() : null);
  if ($pdo instanceof PDO) {
    $ver = $pdo->query('SELECT VERSION() as v')->fetch()['v'] ?? '?';
    echo "connected (MySQL " . $ver . ")\n";
  } else { echo "NO PDO\n"; $ok = false; }
} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  $ok = false;
}

// License (non-blocking; DEV flag may bypass)
echo "[LICENSE] ";
try {
  $key = defined('LICENSE_KEY') ? LICENSE_KEY : ($GLOBALS['CONFIG']['license_key'] ?? '');
  if ($key === '') { echo "no LICENSE_KEY configured (ok if DEV_DISABLE_LICENSE_GUARD=true)\n"; }
  else {
    $st = $pdo->prepare('SELECT id,status,expires_at FROM licenses WHERE license_key=?');
    $st->execute([$key]);
    $row = $st->fetch();
    if ($row) {
      echo "found (status={$row['status']}, expires_at=" . ($row['expires_at'] ?? 'NULL') . ")\n";
    } else {
      echo "not found\n";
    }
  }
} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\nOverall: " . ($ok ? "OK" : "Issues above") . "\n";
