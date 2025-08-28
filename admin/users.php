<?php
// admin/users.php — list, filters, sort, paging, actions, CSV export
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
@include_once __DIR__ . '/../includes/admin-helpers.php';

if (!defined('TEAMS_JSON_PATH')) {
  define('TEAMS_JSON_PATH', __DIR__ . '/../data/uploads/teams.json'); // <-- update if different
}

// Resolve a team name by id (tries common tables; falls back to "Team #id")
function admin_team_name(mysqli $db, int $id): string
{
  static $cache = null;
  $id = (int) $id;

  if ($cache === null) {
    $cache = [];

    // ---------- A) Load from teams.json (first choice) ----------
    $path = defined('TEAMS_JSON_PATH') ? TEAMS_JSON_PATH : '';
    if ($path && is_file($path)) {
      $raw = @file_get_contents($path);
      $data = $raw ? json_decode($raw, true) : null;

      if (is_array($data)) {
        // Try a few common shapes without throwing guesses:
        // 1) { "teams": [ { "id": 28, "name": "..." }, ... ] }
        if (isset($data['teams']) && is_array($data['teams'])) {
          foreach ($data['teams'] as $t) {
            if (is_array($t) && isset($t['id'], $t['name'])) {
              $cache[(int) $t['id']] = (string) $t['name'];
            }
          }
        }
        // 2) [ { "id": 28, "name": "..." }, ... ]
        elseif (isset($data[0]) && is_array($data[0]) && isset($data[0]['id'], $data[0]['name'])) {
          foreach ($data as $t) {
            $cache[(int) $t['id']] = (string) $t['name'];
          }
        }
        // 3) { "28": "Team Name", ... }
        else {
          foreach ($data as $k => $v) {
            if (is_numeric($k) && is_string($v)) {
              $cache[(int) $k] = $v;
            }
          }
        }
      }
    }

    // ---------- B) Optional DB lookups (only if tables exist) ----------
    // These are wrapped in try/catch and prefixed with SHOW TABLES so they never fatal.
    foreach ([
      ['table' => 'teams', 'id' => 'id', 'name' => 'name'],
      ['table' => 'team_info', 'id' => 'TeamID', 'name' => 'Name']
    ] as $q) {
      try {
        $tbl = $db->real_escape_string($q['table']);
        if ($res = @$db->query("SHOW TABLES LIKE '{$tbl}'")) {
          if ($res && $res->num_rows) {
            $res->free();
            if ($rs = @$db->query("SELECT {$q['id']} AS tid, {$q['name']} AS tname FROM {$tbl}")) {
              while ($row = $rs->fetch_assoc()) {
                $tid = (int) $row['tid'];
                $tname = (string) $row['tname'];
                if ($tid && $tname && !isset($cache[$tid])) {
                  $cache[$tid] = $tname;
                }
              }
              $rs->free();
            }
          }
        }
      } catch (\Throwable $e) {
        // ignore — we only augment cache if table exists
      }
    }
  }

  return $cache[$id] ?? ("Team #{$id}");
}


// ---------- Safe fallbacks (avoid fatal if helpers not loaded) ----------
if (!function_exists('h')) {
  function h($s): string
  {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
  }
}
if (!function_exists('build_qs')) {
  function build_qs(array $overrides = [], array $remove = []): string
  {
    $qs = $_GET;
    foreach ($remove as $k) {
      unset($qs[$k]);
    }
    foreach ($overrides as $k => $v) {
      if ($v === null) {
        unset($qs[$k]);
      } else {
        $qs[$k] = $v;
      }
    }
    $base = parse_url($_SERVER['REQUEST_URI'] ?? 'users.php', PHP_URL_PATH) ?: 'users.php';
    $q = http_build_query($qs);
    return $base . ($q ? ('?' . $q) : '');
  }
}
if (!function_exists('admin_db')) {
  function admin_db(): mysqli
  {
    return $GLOBALS['db'] ?? $GLOBALS['dbc'] ?? new mysqli();
  }
}
if (!function_exists('require_admin')) {
  function require_admin(): void
  {
  }
}
if (!function_exists('require_perm')) {
  function require_perm(string $p): void
  {
  }
}
if (!function_exists('admin_csrf')) {
  function admin_csrf(): string
  {
    return $_SESSION['csrf'] ?? '';
  }
}
// -----------------------------------------------------------------------

require_admin();
require_perm('manage_users');

$dbc = admin_db();
$csrf = admin_csrf();
$back = $_SERVER['REQUEST_URI'] ?? 'users.php';

// ensure table exists (no-op if present)
@$dbc->query("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  role VARCHAR(32) NOT NULL DEFAULT 'member',
  password_hash VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  locked_until DATETIME NULL,
  locked_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Stats (tolerant)
[$totalUsers] = ($dbc->query("SELECT COUNT(*) FROM users")->fetch_row() ?: [0]);
[$activeUsers] = ($dbc->query("SELECT COUNT(*) FROM users WHERE active=1")->fetch_row() ?: [0]);
[$lockedUsers] = ($dbc->query("SELECT COUNT(*) FROM users WHERE locked_until IS NOT NULL AND locked_until > NOW()")->fetch_row() ?: [0]);

// Inputs
$q = trim((string) ($_GET['q'] ?? ''));
$status = strtolower(trim((string) ($_GET['status'] ?? ''))); // '', active, inactive, locked
$role = trim((string) ($_GET['role'] ?? ''));
$export = (string) ($_GET['export'] ?? ''); // 'csv' to export

// Roles dropdown (tolerant)
$roles = [];
if ($res = @$dbc->query("SELECT DISTINCT role FROM users ORDER BY role")) {
  while ($r = $res->fetch_row()) {
    if (!empty($r[0]))
      $roles[] = (string) $r[0];
  }
}
$builtin = ['member', 'admin', 'owner', 'commission', 'commish', 'gm'];
$roles = array_values(array_unique(array_merge($builtin, $roles)));
sort($roles, SORT_STRING | SORT_FLAG_CASE);

// Sorting
$allowedSorts = [
  'id' => 'id',
  'name' => 'name',
  'email' => 'email',
  'role' => 'role',
  'active' => 'active',
  'locked' => 'locked_until',
  'created' => 'created_at',
  'updated' => 'updated_at',
];
$sort = strtolower((string) ($_GET['sort'] ?? 'id'));
$dir = strtoupper((string) ($_GET['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
$sortCol = $allowedSorts[$sort] ?? 'id';

// Filters -> WHERE
$where = [];
$args = [];
$types = '';

if ($q !== '') {
  $where[] = "(name LIKE CONCAT('%', ?, '%') OR email LIKE CONCAT('%', ?, '%'))";
  $args[] = $q;
  $args[] = $q;
  $types .= 'ss';
}
if ($status === 'active') {
  $where[] = "active=1";
} elseif ($status === 'inactive') {
  $where[] = "active=0";
} elseif ($status === 'locked') {
  $where[] = "locked_until IS NOT NULL AND locked_until > NOW()";
}
if ($role !== '') {
  $where[] = "role=?";
  $args[] = $role;
  $types .= 's';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count for pagination
$stc = $dbc->prepare("SELECT COUNT(*) FROM users {$whereSql}");
if ($types !== '') {
  $stc->bind_param($types, ...$args);
}
$stc->execute();
[$total] = $stc->get_result()->fetch_row();
$stc->close();

// Paging
$perPage = max(5, min(100, (int) ($_GET['pp'] ?? 25)));
$page = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;
$pages = max(1, (int) ceil($total / $perPage));

// CSV export (respects current filters/sort)
if ($export === 'csv') {
  $sql = "SELECT id,name,email,role,active,locked_until,created_at,updated_at
          FROM users {$whereSql}
          ORDER BY {$sortCol} {$dir}";
  $st = $dbc->prepare($sql);
  if ($types !== '') {
    $st->bind_param($types, ...$args);
  }
  $st->execute();
  $res = $st->get_result();

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="users-' . date('Ymd-His') . '.csv"');

  $out = fopen('php://output', 'w');
  fputcsv($out, ['id', 'name', 'email', 'role', 'active', 'locked_until', 'created_at', 'updated_at']);
  while ($row = $res->fetch_assoc())
    fputcsv($out, $row);
  fclose($out);
  exit;
}

// Page query — bind 'ii' always, plus filters if present
$sql = "SELECT id,name,email,role,active,locked_until,locked_reason,created_at,updated_at
        FROM users {$whereSql}
        ORDER BY {$sortCol} {$dir}
        LIMIT ? OFFSET ?";
$stmt = $dbc->prepare($sql);
if ($types !== '') {
  $t2 = $types . 'ii';
  $a2 = array_merge($args, [$perPage, $offset]);
  $stmt->bind_param($t2, ...$a2);
} else {
  $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$res = $stmt->get_result();

// existing…
$stmt->execute();
$res = $stmt->get_result();

// NEW — capture the current page of users into an array
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
if ($res) {
  $res->free();
}
// header include (tolerant)
$loadedHeader = false;
foreach ([
  __DIR__ . '/admin-header.php',
  __DIR__ . '/header-admin.php',
  __DIR__ . '/partials/admin-header.php',
  __DIR__ . '/../includes/admin-header.php',
  __DIR__ . '/../includes/header.php',
] as $p) {
  if (is_file($p)) {
    include $p;
    $loadedHeader = true;
    break;
  }
}
if (!$loadedHeader)
  echo "<!doctype html><meta charset='utf-8'><title>Users</title><style>body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial;margin:16px}</style>";
?>
<h1 style="display:flex;align-items:center;gap:.5rem">Users</h1>

<!-- SVG sprite -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none;">
  <symbol id="i-plus" viewBox="0 0 24 24">
    <path fill="currentColor" d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2z" />
  </symbol>
  <symbol id="i-download" viewBox="0 0 24 24">
    <path fill="currentColor" d="M5 20h14v-2H5zM11 3h2v9h3l-4 4-4-4h3z" />
  </symbol>
  <symbol id="i-edit" viewBox="0 0 24 24">
    <path fill="currentColor"
      d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm18-11.5a1 1 0 0 0 0-1.41L18.66 2a1 1 0 0 0-1.41 0L15 4.24l3.75 3.75L21 5.75z" />
  </symbol>
  <symbol id="i-lock" viewBox="0 0 24 24">
    <path fill="currentColor"
      d="M17 8h-1V6a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2zM9 7a3 3 0 1 1 6 0v1H9V7z" />
  </symbol>
  <symbol id="i-unlock" viewBox="0 0 24 24">
    <path fill="currentColor"
      d="M18 8h-1V6a5 5 0 1 0-10 0h2a3 3 0 1 1 6 0v2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2z" />
  </symbol>
  <symbol id="i-check-circle" viewBox="0 0 24 24">
    <path fill="currentColor"
      d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1 14l-4-4 1.4-1.4L11 13.2l5.6-5.6L18 9l-7 7z" />
  </symbol>
  <symbol id="i-x-circle" viewBox="0 0 24 24">
    <path fill="currentColor"
      d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm2.3 12.1L12 11.8l-2.3 2.3-1.4-1.4 2.3-2.3-2.3-2.3 1.4-1.4L12 9l2.3-2.3 1.4 1.4-2.3 2.3 2.3 2.3-1.4 1.4z" />
  </symbol>
  <symbol id="i-copy" viewBox="0 0 24 24">
    <path fill="currentColor" d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10V1z" />
    <path fill="currentColor"
      d="M18 5H8a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 14H8V7h10v12z" />
  </symbol>
  <symbol id="i-teams" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
    stroke-linejoin="round">
    <path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" />
    <circle cx="9" cy="7" r="4" />
    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
  </symbol>
</svg>

<style>
  .filters {
    display: flex;
    gap: .6rem;
    align-items: flex-end;
    flex-wrap: wrap;
    margin: .6rem 0 1rem
  }

  .filters label {
    display: block;
    font-size: .9rem;
    color: #333;
    margin-bottom: .15rem
  }

  .filters input[type=text],
  .filters select {
    padding: .35rem .5rem;
    border: 1px solid #bbb;
    border-radius: 6px;
    min-width: 160px
  }

  table {
    width: 100%;
    border-collapse: collapse
  }

  th,
  td {
    padding: .5rem;
    border-bottom: 1px solid #eee;
    vertical-align: top;
    text-align: left
  }

  th {
    background: #fafafa;
    position: sticky;
    top: 0;
    z-index: 1
  }

  .badge {
    display: inline-block;
    padding: .1rem .35rem;
    border-radius: 6px;
    border: 1px solid #ddd;
    font-size: .85rem
  }

  .badge.on {
    background: #0b4;
    color: #fff;
    border-color: #0b4
  }

  .badge.off {
    background: #b00;
    color: #fff;
    border-color: #b00
  }

  .muted {
    color: #667
  }

  /* use global .btn from admin header; this helper only sets size for icon pills */
  .btn--icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    padding: 0;
  }

  .btn--icon svg {
    width: 18px;
    height: 18px
  }

  /* row action icons (neutral, not themed) */
  .iconbtn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    padding: .25rem;
    border: 1px solid #ddd;
    border-radius: .5rem;
    background: #fff;
    color: #222;
    text-decoration: none;
    line-height: 1;
    vertical-align: middle;
  }

  .iconbtn svg {
    width: 18px;
    height: 18px
  }

  .iconbtn.red {
    color: #a11212;
  }

  .iconbtn.green {
    color: #0a7d2f;
  }

  .iconbtn.disabled {
    opacity: .45;
    pointer-events: none;
  }

  .actions .iconbtn+.iconbtn {
    margin-left: .25rem;
  }

  .sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
  }

  .stats {
    display: flex;
    gap: .6rem;
    margin: .6rem 0;
    font-size: .95rem;
    color: #444
  }

  .toolbar {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    margin: .5rem 0;
  }

  .emailcell {
    display: flex;
    align-items: center;
    gap: .35rem
  }
</style>

<div style="display:flex; align-items:center; justify-content:space-between;">
  <div class="stats">
    <div>Total: <strong><?= number_format((int) $totalUsers) ?></strong></div>
    <div>Active: <strong><?= number_format((int) $activeUsers) ?></strong></div>
    <div>Locked: <strong><?= number_format((int) $lockedUsers) ?></strong></div>
  </div>
  <div class="toolbar">
    <!-- Uses global .btn colors, compact via .btn--icon -->
    <a id="btnAddUser" class="btn btn--icon" href="user-edit.php?back=<?= rawurlencode($back) ?>" title="Add user (N)">
      <svg>
        <use href="#i-plus" />
      </svg><span class="sr-only">Add user</span>
    </a>
    <a class="btn btn--icon" href="<?= h(build_qs(['export' => 'csv'])) ?>" title="Export CSV">
      <svg>
        <use href="#i-download" />
      </svg><span class="sr-only">Export</span>
    </a>
  </div>
</div>

<form class="filters" method="get" action="">
  <div>
    <label>Search</label>
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="name or email">
  </div>
  <div>
    <label>Status</label>
    <select name="status">
      <option value="" <?= $status === '' ? 'selected' : '' ?>>Any</option>
      <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      <option value="locked" <?= $status === 'locked' ? 'selected' : '' ?>>Locked</option>
    </select>
  </div>
  <div>
    <label>Role</label>
    <select name="role">
      <option value="" <?= $role === '' ? 'selected' : '' ?>>Any</option>
      <?php foreach ($roles as $r): ?>
        <option value="<?= h($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= h(ucfirst($r)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <input type="hidden" name="p" value="1">
  <button class="btn" type="submit" title="Apply filters">Apply</button>
  <?php if ($q !== '' || $role !== '' || $status !== ''): ?>
    <a class="btn" href="users.php" title="Reset filters">Reset</a>
  <?php endif; ?>
</form>

<?php
$db = admin_db();

// gather user ids shown on this page
$userIds = array_map(static fn($r) => (int) $r['id'], $rows);

// pull their team ids
$userTeams = [];
$allTeamIds = [];
if ($userIds) {
  $idList = implode(',', array_map('intval', $userIds));
  if ($res = $db->query("SELECT user_id, team_id FROM user_teams WHERE user_id IN ({$idList}) ORDER BY team_id")) {
    while ($r = $res->fetch_assoc()) {
      $uid = (int) $r['user_id'];
      $tid = (int) $r['team_id'];
      $userTeams[$uid][] = $tid;
      $allTeamIds[$tid] = true;
    }
  }
}

// resolve team names once
$teamNameCache = [];
foreach (array_keys($allTeamIds) as $tid) {
  $teamNameCache[$tid] = admin_team_name($db, $tid);
}

if (!empty($rows)): ?>

  <table class="table">
    <thead>
      <tr>
        <th><a
            href="<?= h(build_qs(['sort' => 'id', 'dir' => $sort === 'id' && $dir === 'ASC' ? 'desc' : 'asc'])) ?>">ID</a>
        </th>
        <th><a
            href="<?= h(build_qs(['sort' => 'name', 'dir' => $sort === 'name' && $dir === 'ASC' ? 'desc' : 'asc'])) ?>">Name</a>
        </th>
        <th><a
            href="<?= h(build_qs(['sort' => 'email', 'dir' => $sort === 'email' && $dir === 'ASC' ? 'desc' : 'asc'])) ?>">Email</a>
        </th>
        <th><a
            href="<?= h(build_qs(['sort' => 'role', 'dir' => $sort === 'role' && $dir === 'ASC' ? 'desc' : 'asc'])) ?>">Teams</a>
        </th>
        <th><a
            href="<?= h(build_qs(['sort' => 'role', 'dir' => $sort === 'role' && $dir === 'ASC' ? 'desc' : 'asc'])) ?>">Role</a>
        </th>
        <th><a
            href="<?= h(build_qs(['sort' => 'active', 'dir' => $sort === 'active' && $dir === 'ASC' ? 'desc' : 'asc'])) ?>">Active</a>
        </th>
        <th><a
            href="<?= h(build_qs(['sort' => 'locked', 'dir' => $sort === 'locked' && $dir === 'ASC' ? 'desc' : 'asc'])) ?>">Locked
            until</a></th>
        <th><a
            href="<?= h(build_qs(['sort' => 'created', 'dir' => $sort === 'created' && $dir === 'ASC' ? 'desc' : 'asc'])) ?>">Created</a>
        </th>
        <th><a
            href="<?= h(build_qs(['sort' => 'updated', 'dir' => $sort === 'updated' && $dir === 'ASC' ? 'desc' : 'asc'])) ?>">Updated</a>
        </th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $u):

        $isLocked = (!empty($u['locked_until']) && strtotime((string) $u['locked_until']) > time());
        $activeNow = !empty($u['active']);
        $rowId = (int) $u['id'];
        ?>
        <tr>
          <td><?= (int) $u['id'] ?></td>
          <td><?= h((string) $u['name']) ?></td>
          <td class="emailcell">
            <span id="email-<?= $rowId ?>"><?= h((string) $u['email']) ?></span>
            <a class="iconbtn" title="Copy email" aria-label="Copy email" href="javascript:void(0)"
              onclick="copyEmail(<?= $rowId ?>)">
              <svg>
                <use href="#i-copy" />
              </svg><span class="sr-only">Copy email</span>
            </a>
          </td>
          <td>
            <?php
            $labels = [];
            foreach ($userTeams[$rowId] ?? [] as $tid) {
              $labels[] = h($teamNameCache[$tid] ?? ("Team #{$tid}"));
            }
            echo $labels ? implode(', ', $labels) : '<span class="muted">—</span>';
            ?>
          </td>
          <td><?= h((string) $u['role']) ?></td>
          <td><?= $activeNow ? '<span class="badge on">Yes</span>' : '<span class="badge off">No</span>' ?></td>
          <td>
            <?php if ($isLocked): ?>
              <?php $tip = trim((string) ($u['locked_reason'] ?? '')); ?>
              <span class="badge lock"
                title="<?= $tip !== '' ? 'Reason: ' . h($tip) : '' ?>"><?= h((string) $u['locked_until']) ?></span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td><?= h((string) $u['created_at']) ?></td>
          <td><?= h((string) $u['updated_at']) ?></td>
          <td class="actions">
            <!-- Edit -->
            <a class="iconbtn" title="Edit user" href="user-edit.php?id=<?= $rowId ?>&back=<?= rawurlencode($back) ?>">
              <svg>
                <use href="#i-edit" />
              </svg><span class="sr-only">Edit</span>
            </a>
            <!-- Teams -->
            <a class="iconbtn" title="Manage teams"
              href="user-teams.php?user_id=<?= $rowId ?>&back=<?= rawurlencode($back) ?>">
              <svg class="icon" width="14" height="14" aria-hidden="true">
                <use href="#i-teams"></use>
              </svg>
            </a>
            <!-- Lock / Unlock -->
            <?php if ($isLocked): ?>
              <a class="iconbtn green" title="Unlock user"
                href="user-unlock.php?id=<?= $rowId ?>&csrf=<?= urlencode($csrf) ?>&back=<?= rawurlencode($back) ?>"
                onclick="return confirm('Unlock this account now?');">
                <svg>
                  <use href="#i-unlock" />
                </svg><span class="sr-only">Unlock</span>
              </a>
            <?php else: ?>
              <a class="iconbtn" title="Lock user" href="user-lock.php?id=<?= $rowId ?>&back=<?= rawurlencode($back) ?>">
                <svg>
                  <use href="#i-lock" />
                </svg><span class="sr-only">Lock</span>
              </a>
            <?php endif; ?>

            <!-- Activate / Deactivate -->
            <?php if ($activeNow): ?>
              <a class="iconbtn red" title="Deactivate user"
                href="user-toggle-active.php?id=<?= $rowId ?>&to=0&csrf=<?= urlencode($csrf) ?>&back=<?= rawurlencode($back) ?>"
                onclick="return confirm('Deactivate this user? They won’t be able to sign in.');">
                <svg>
                  <use href="#i-x-circle" />
                </svg><span class="sr-only">Deactivate</span>
              </a>
            <?php else: ?>
              <a class="iconbtn green" title="Activate user"
                href="user-toggle-active.php?id=<?= $rowId ?>&to=1&csrf=<?= urlencode($csrf) ?>&back=<?= rawurlencode($back) ?>"
                onclick="return confirm('Activate this user?');">
                <svg>
                  <use href="#i-check-circle" />
                </svg><span class="sr-only">Activate</span>
              </a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <div class="muted">No users found.</div>
<?php endif; ?>

<?php
// pagination controls
$isFirst = $page <= 1;
$isLast = $page >= $pages;
$firstHref = build_qs(['p' => 1]);
$prevHref = build_qs(['p' => max(1, $page - 1)]);
$nextHref = build_qs(['p' => min($pages, $page + 1)]);
$lastHref = build_qs(['p' => $pages]);

if ($q !== '' || $role !== '' || $status !== '' || $total > $perPage):
  ?>
  <div style="display:flex; gap:.5rem; align-items:center; justify-content:flex-end; margin-top:1rem; flex-wrap:wrap;">
    <span class="muted">Total: <?= number_format($total) ?></span>
    <span class="btn" title="Page <?= $page ?> of <?= $pages ?>">Page <?= $page ?> / <?= $pages ?></span>
    <a class="iconbtn<?= $isFirst ? ' disabled' : '' ?>" href="<?= h($firstHref) ?>" title="First">«</a>
    <a class="iconbtn<?= $isFirst ? ' disabled' : '' ?>" href="<?= h($prevHref) ?>" title="Prev">‹</a>
    <a class="iconbtn<?= $isLast ? ' disabled' : '' ?>" href="<?= h($nextHref) ?>" title="Next">›</a>
    <a class="iconbtn<?= $isLast ? ' disabled' : '' ?>" href="<?= h($lastHref) ?>" title="Last">»</a>
  </div>
<?php endif;

if (isset($stmt) && $stmt instanceof mysqli_stmt)
  $stmt->close();
if (isset($st) && $st instanceof mysqli_stmt)
  $st->close();

if ($loadedHeader === true) {
  foreach ([
    __DIR__ . '/admin-footer.php',
    __DIR__ . '/footer-admin.php',
    __DIR__ . '/partials/admin-footer.php',
    __DIR__ . '/../includes/admin-footer.php',
    __DIR__ . '/../includes/footer.php',
  ] as $p) {
    if (is_file($p)) {
      include $p;
      $loadedHeader = false;
      break;
    }
  }
}
if ($loadedHeader)
  echo "</body></html>";
?>
<script>
  // Copy email to clipboard
  function copyEmail(id) {
    var el = document.getElementById('email-' + id);
    if (!el) return;
    var txt = el.textContent || el.value || '';
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(txt).catch(function () { });
    } else {
      var t = document.createElement('textarea'); t.value = txt; document.body.appendChild(t);
      t.select(); try { document.execCommand('copy'); } catch (e) { } document.body.removeChild(t);
    }
  }

  // Keyboard shortcut: N => Add user (only when not typing in a field)
  document.addEventListener('keydown', function (e) {
    var tag = (e.target && e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.ctrlKey || e.metaKey || e.altKey) return;
    if (e.key === 'n' || e.key === 'N') {
      var el = document.getElementById('btnAddUser');
      if (el) { el.click(); e.preventDefault(); }
    }
  }, true);
</script>