<?php
// includes/admin-pin-throttle.php — throttle admin PIN attempts by IP within a time window
// Usage: [$blocked, $retryAfter] = admin_pin_rate_block(__log_ip(), 5, 600);
require_once __DIR__ . '/log.php'; // for __log_db() and __log_ip()

/**
 * Returns [bool $blocked, int $retryAfterSeconds]
 * $max = allowed failed attempts within the last $windowSec seconds (default: 5 in 10min).
 */
function admin_pin_rate_block(?string $ip, int $max = 5, int $windowSec = 600): array {
  $db = __log_db();

  // We count recent FAILED admin attempts in the window
  $timeSql = "COALESCE(attempted_at, created_at) > (NOW() - INTERVAL ? SECOND)";

  if ($ip) {
    $sql = "SELECT COUNT(*) AS c, UNIX_TIMESTAMP(MIN(COALESCE(attempted_at, created_at))) AS first_ts
            FROM login_attempts
            WHERE actor='admin' AND success=0 AND ip=? AND $timeSql";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $windowSec, $ip);
  } else {
    // Fallback if we can't read IP: throttle per 'admin' actor globally
    $sql = "SELECT COUNT(*) AS c, UNIX_TIMESTAMP(MIN(COALESCE(attempted_at, created_at))) AS first_ts
            FROM login_attempts
            WHERE actor='admin' AND success=0 AND $timeSql";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $windowSec);
  }

  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $count  = (int)($row['c'] ?? 0);
  $firstTs= (int)($row['first_ts'] ?? 0);

  if ($count >= $max && $firstTs > 0) {
    $elapsed = time() - $firstTs;
    $retry   = max(0, $windowSec - $elapsed);
    return [true, $retry];
  }
  return [false, 0];
}
