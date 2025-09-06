<?php
declare(strict_types=1);
/**
 * includes/db.php
 * Legacy shim so older pages that required this still work.
 */
require_once __DIR__ . '/bootstrap.php';

// Provide legacy $mysqli for old code paths
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
  $mysqli = get_db();
}
