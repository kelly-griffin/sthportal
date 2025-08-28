<?php
// includes/acl.php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/user-auth.php';

function acl_db(): PDO {
  if (function_exists('db')) { $pdo = db(); if ($pdo instanceof PDO) return $pdo; }
  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) return $GLOBALS['pdo'];

  // Fallbacks (adjust only if needed)
  $dsn = defined('DB_DSN') ? constant('DB_DSN') : null;
  if ($dsn) return new PDO($dsn, defined('DB_USER')?constant('DB_USER'):null, defined('DB_PASS')?constant('DB_PASS'):null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);

  if (defined('DB_HOST') || defined('DB_NAME') || defined('DB_DATABASE')) {
    $host = defined('DB_HOST') ? constant('DB_HOST') : '127.0.0.1';
    $name = defined('DB_NAME') ? constant('DB_NAME') : (defined('DB_DATABASE') ? constant('DB_DATABASE') : null);
    $user = defined('DB_USER') ? constant('DB_USER') : (defined('DB_USERNAME') ? constant('DB_USERNAME') : null);
    $pass = defined('DB_PASS') ? constant('DB_PASS') : (defined('DB_PASSWORD') ? constant('DB_PASSWORD') : null);
    $charset = defined('DB_CHARSET') ? constant('DB_CHARSET') : 'utf8mb4';
    if ($name) {
      $pdo = new PDO("mysql:host={$host};dbname={$name};charset={$charset}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
      ]);
      return $pdo;
    }
  }

  throw new RuntimeException('DB unavailable for ACL');
}

/* Commissioner/Admin detection (lenient to match your session) */
function user_is_commish(): bool {
  return !empty($_SESSION['is_admin'])
      || !empty($_SESSION['is_commish'])
      || (($_SESSION['role'] ?? '') === 'admin')
      || (($_SESSION['role'] ?? '') === 'commissioner');
}
/**
 * Fetch map: parent_id => [ ['id'=>child_id, 'level'=>'farm|echl|other'], ... ]
 */
function acl_fetch_affiliates(PDO $pdo): array {
  $rows = $pdo->query('SELECT parent_team_id, child_team_id, level FROM team_affiliates')->fetchAll();
  $map = [];
  foreach ($rows as $r) {
    $p = (int)$r['parent_team_id'];
    $c = (int)$r['child_team_id'];
    $map[$p][] = ['id' => $c, 'level' => $r['level'] ?? 'farm'];
  }
  return $map;
}

/**
 * Expand direct team perms with affiliate inheritance (BFS; caps copied).
 */
function acl_expand_with_affiliates(array $direct, PDO $pdo): array {
  $aff = acl_fetch_affiliates($pdo);
  $all = $direct;

  // Walk outward from each directly-owned team
  $queue = array_keys($direct);
  $seen  = array_fill_keys($queue, true);

  while ($queue) {
    $parent = (int)array_shift($queue);
    $parentCaps = $all[$parent]['caps'] ?? ['trade_offer'=>true,'sign_player'=>true,'callups'=>true,'lines'=>true];
    foreach ($aff[$parent] ?? [] as $child) {
      $cid = (int)$child['id'];
      if (isset($seen[$cid])) continue;
      $seen[$cid] = true;

      // Inherit caps from immediate parent (tweak here if you want to restrict)
      $all[$cid] = [
        'role' => 'inherited',
        'caps' => $parentCaps,
        'inherited_from' => $parent,
        'level' => $child['level'] ?? null,
      ];

      // Keep walking in case of multi-level chains
      $queue[] = $cid;
    }
  }

  return $all;
}

/* Load team caps for a user into session */
function load_user_acl(int $userId): void {
  $pdo = acl_db();

  // 1) Direct assignments
  $stmt = $pdo->prepare('SELECT team_id, role, can_trade, can_sign, can_callups, can_lines
                         FROM user_teams WHERE user_id = ? ORDER BY team_id');
  $stmt->execute([$userId]);

  $direct = [];
  foreach ($stmt as $r) {
    $tid = (int)$r['team_id'];
    $direct[$tid] = [
      'role' => $r['role'],
      'caps' => [
        'trade_offer' => (bool)$r['can_trade'],
        'sign_player' => (bool)$r['can_sign'],
        'callups'     => (bool)$r['can_callups'],
        'lines'       => (bool)$r['can_lines'],
      ],
      'inherited_from' => null,
      'level' => null,
    ];
  }

  // 2) Inherit affiliates (Pro → Farm/ECHL)
  $all = acl_expand_with_affiliates($direct, $pdo);

  // 3) Save to session
  $_SESSION['teams'] = $all;

  // 4) Keep existing active team if still allowed; else choose a sensible default
  if (isset($_SESSION['active_team_id']) && isset($all[(int)$_SESSION['active_team_id']])) {
    // keep current
  } else {
    // Prefer a direct team if available; else any inherited one
    $_SESSION['active_team_id'] = $direct
      ? (int)array_key_first($direct)
      : ($all ? (int)array_key_first($all) : null);
  }
}


/* Active team helpers */
function user_team_ids(): array {
  return array_keys($_SESSION['teams'] ?? []);
}
function user_active_team_id(): ?int {
  return isset($_SESSION['active_team_id']) ? (int)$_SESSION['active_team_id'] : null;
}
function set_active_team(int $teamId): bool {
  if (user_is_commish()) { $_SESSION['active_team_id'] = $teamId; return true; }
  if (isset($_SESSION['teams'][$teamId])) { $_SESSION['active_team_id'] = $teamId; return true; }
  return false;
}

/* Capability checks */
function user_is_gm_of(int $teamId): bool {
  return user_is_commish() || isset($_SESSION['teams'][$teamId]);
}
function user_can(string $capability, int $teamId): bool {
  if (user_is_commish()) return true;
  return !empty($_SESSION['teams'][$teamId]['caps'][$capability]);
}

/* Require guards (use in actions) */
function require_gm_of(int $teamId): void {
  if (!user_is_gm_of($teamId)) {
    http_response_code(403); echo 'Forbidden (team owner only)'; exit;
  }
}
function require_capability(int $teamId, string $cap): void {
  if (!user_can($cap, $teamId)) {
    http_response_code(403); echo 'Forbidden (missing capability)'; exit;
  }
}
