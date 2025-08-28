<?php
// admin/index.php — Admin Home (schema-safe; hyphen routes)
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/session-guard.php';

require_admin();

// ---- DB handle
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('admin_db'))                 { $dbc = admin_db(); }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('Database handle not initialized.'); }

// Ensure base tables exist so the dashboard queries work
__log_ensure_tables($dbc);

// --- Alt Radar Alerts (score >= 6 in last 7 days)
$alertsHi = 0;
try {
    // ensure table exists
    $dbc->query("CREATE TABLE IF NOT EXISTS alt_radar_watchlist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        identity VARCHAR(190) NOT NULL,
        note VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_identity (identity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $days = 7;
    $notLocal = " AND ip NOT IN ('127.0.0.1','::1','::ffff:127.0.0.1') ";
    $stmt = $dbc->prepare("SELECT w.identity,
                                  MAX(la.attempted_at) AS last_seen,
                                  COUNT(DISTINCT la.ip) AS ip_count
                           FROM alt_radar_watchlist w
                           LEFT JOIN login_attempts la
                             ON (COALESCE(la.username, la.actor, la.identity) = w.identity
                                 AND la.success=1
                                 AND la.ip IS NOT NULL AND la.ip <> ''
                                 AND la.attempted_at >= DATE_SUB(NOW(), INTERVAL ? DAY) $notLocal)
                           GROUP BY w.identity");
    if ($stmt) {
        $stmt->bind_param('i', $days);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $ips = (int)($row['ip_count'] ?? 0);
            $last = $row['last_seen'] ?? null;
            $bonus = 0;
            if ($last) {
                $ts = strtotime((string)$last);
                if ($ts !== false) {
                    $d = (time() - $ts) / 86400.0;
                    if ($d <= 7) $bonus = 2; elseif ($d <= 30) $bonus = 1;
                }
            }
            $score = $ips + $bonus;
            if ($score >= 6) $alertsHi++;
        }
        $stmt->close();
    }
} catch (Throwable $e) { /* no-op */ }

// ---------- Helpers ----------
function table_exists(mysqli $dbc, string $name): bool {
    try {
        $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1";
        if (!$stmt = $dbc->prepare($sql)) return false;
        $stmt->bind_param('s', $name);
        $stmt->execute(); $stmt->store_result();
        $ok = $stmt->num_rows > 0;
        $stmt->free_result(); $stmt->close();
        return $ok;
    } catch (Throwable $e) { return false; }
}
function col_exists(mysqli $dbc, string $table, string $col): bool {
    try {
        $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
        if (!$stmt = $dbc->prepare($sql)) return false;
        $stmt->bind_param('ss', $table, $col);
        $stmt->execute(); $stmt->store_result();
        $ok = $stmt->num_rows > 0;
        $stmt->free_result(); $stmt->close();
        return $ok;
    } catch (Throwable $e) { return false; }
}
function q1(mysqli $dbc, string $sql, array $bind = []) {
    try {
        if (!$stmt = $dbc->prepare($sql)) return null;
        if ($bind) {
            $types = '';
            foreach ($bind as $b) { $types .= is_int($b) ? 'i' : (is_float($b) ? 'd' : 's'); }
            $stmt->bind_param($types, ...$bind);
        }
        $stmt->execute();
        $stmt->bind_result($val);
        $valOut = null;
        if ($stmt->fetch()) $valOut = $val;
        $stmt->close();
        return $valOut;
    } catch (Throwable $e) { return null; }
}
function rows(mysqli $dbc, string $sql, array $bind = []): array {
    try {
        $data = [];
        if (!$stmt = $dbc->prepare($sql)) return $data;
        if ($bind) {
            $types = '';
            foreach ($bind as $b) { $types .= is_int($b) ? 'i' : (is_float($b) ? 'd' : 's'); }
            $stmt->bind_param($types, ...$bind);
        }
        $stmt->execute();
        if ($r = $stmt->get_result()) while ($row = $r->fetch_assoc()) $data[] = $row;
        $stmt->close();
        return $data;
    } catch (Throwable $e) { return []; }
}

// ---------- Stats (schema-smart) ----------
$hasUsersTbl   = table_exists($dbc, 'users');
$usersTotal    = $hasUsersTbl ? (int)(q1($dbc, "SELECT COUNT(*) FROM users") ?? 0) : 0;

// Active: prefer `active`, fallback `is_active`
$usersActive = null;
if ($hasUsersTbl) {
    if (col_exists($dbc, 'users', 'active')) {
        $usersActive = (int)(q1($dbc, "SELECT COUNT(*) FROM users WHERE active=1") ?? 0);
    } elseif (col_exists($dbc, 'users', 'is_active')) {
        $usersActive = (int)(q1($dbc, "SELECT COUNT(*) FROM users WHERE is_active=1") ?? 0);
    }
}
$usersLocked = null;
if ($hasUsersTbl) {
    if (col_exists($dbc, 'users', 'locked')) {
        $usersLocked = (int)(q1($dbc, "SELECT COUNT(*) FROM users WHERE locked=1") ?? 0);
    } elseif (col_exists($dbc, 'users', 'is_locked')) {
        $usersLocked = (int)(q1($dbc, "SELECT COUNT(*) FROM users WHERE is_locked=1") ?? 0);
    }
}

$hasLicTbl     = table_exists($dbc, 'licenses');
$licensesTotal = $hasLicTbl ? (int)(q1($dbc, "SELECT COUNT(*) FROM licenses") ?? 0) : 0;

// Last 24h counts
$fail24h = (int)(q1($dbc, "SELECT COUNT(*) FROM login_attempts WHERE created_at > NOW() - INTERVAL 1 DAY AND success=0") ?? 0);
$succ24h = (int)(q1($dbc, "SELECT COUNT(*) FROM login_attempts WHERE created_at > NOW() - INTERVAL 1 DAY AND success=1") ?? 0);

// Top noisy IPs (last 24h failed)
$noisy = rows($dbc,
    "SELECT ip, COUNT(*) AS c FROM login_attempts
     WHERE created_at > NOW() - INTERVAL 1 DAY AND success=0 AND ip IS NOT NULL AND ip <> ''
     GROUP BY ip ORDER BY c DESC LIMIT 5");

// Recent audit & login
$recentAudit = rows($dbc, "SELECT event, actor, ip, created_at FROM audit_log ORDER BY id DESC LIMIT 10");
$recentLogin = rows($dbc, "SELECT success, actor, ip, note, created_at FROM login_attempts ORDER BY id DESC LIMIT 10");

// ---------- Header include ----------
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/header-admin.php', __DIR__.'/header.php', __DIR__.'/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) {
  echo "<!doctype html><meta charset='utf-8'><title>Admin Home</title><style>body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial; margin:16px}</style>";
}
?>
<h1>Admin Home</h1>
<?php include __DIR__ . '/_whats_new_badge.php'; ?>
<style>
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;margin:.6rem 0}
  .card{border:1px solid #ddd;border-radius:8px;background:#fff}
  .card h3{margin:0;padding:10px 12px;border-bottom:1px solid #eee;background:#fafafa}
  .card .body{padding:10px 12px}
  .muted{color:#666;font-size:.9rem}
  .btnrow{display:flex;gap:8px;flex-wrap:wrap;margin:.5rem 0}
  .btn{display:inline-block;padding:.35rem .6rem;border:1px solid #d6c698;border-radius:6px;background:#fff;text-decoration:none;color:#333}
  table{width:100%;border-collapse:collapse}
  th,td{padding:.4rem;border-bottom:1px solid #eee}
  th{text-align:left;background:#fafafa}
  .mono{font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; font-size:.9rem}
  .nowrap{white-space:nowrap}
  .chip{display:inline-block;padding:.15rem .5rem;border:1px solid #ccc;border-radius:999px;background:#f6f6f6}

.badge-alert{display:inline-block;margin-left:.5rem;padding:.05rem .4rem .1rem .4rem;border-radius:6px;border:1px solid #f29c9c;background:#ffe3e3;color:#8a0000;font-weight:700;font-size:.85rem;}
</style>

<div class="grid">
  <div class="card">
    <h3>CB Alt Radar<?php if ((int)$alertsHi>0): ?><span class="badge-alert">Alerts <?= (int)$alertsHi ?></span><?php endif; ?></h3>
    <div class="body">
      <div class="btnrow">
        <?php if (is_file(__DIR__ . '/alt-radar.php')): ?><a class="btn" href="alt-radar.php">Open Radar</a><?php endif; ?>
        <?php if (is_file(__DIR__ . '/login-attempts.php')): ?><a class="btn" href="login-attempts.php">Login Attempts</a><?php endif; ?>
      </div>
      <div class="muted">Shared IPs & account pairs (read-only). New in Phase 2.</div>
    </div>
  </div>

  <div class="card">
    <h3>Users</h3>
    <div class="body">
      <div class="btnrow">
        <?php if (is_file(__DIR__ . '/users.php')): ?><a class="btn" href="users.php">Manage Users</a><?php endif; ?>
        <?php if (is_file(__DIR__ . '/roles.php')): ?><a class="btn" href="roles.php">Roles</a><?php endif; ?>
        <?php if (is_file(__DIR__ . '/users-export.php')): ?><a class="btn" href="users-export.php">Export Users</a><?php endif; ?>
      </div>
      <div class="muted">
        Total: <strong><?= (int)$usersTotal ?></strong>
        <?php if ($usersActive !== null): ?> • Active: <strong><?= (int)$usersActive ?></strong><?php endif; ?>
        <?php if ($usersLocked !== null): ?> • Locked: <strong><?= (int)$usersLocked ?></strong><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <h3>Licenses</h3>
    <div class="body">
      <?php if (!$hasLicTbl): ?>
        <div class="muted">License module not installed.</div>
      <?php else: ?>
        <div class="btnrow">
          <?php if (is_file(__DIR__ . '/licenses.php')): ?><a class="btn" href="licenses.php">Manage Licenses</a><?php endif; ?>
          <?php if (is_file(__DIR__ . '/licenses-export.php')): ?><a class="btn" href="licenses-export.php">Export</a><?php endif; ?>
          <?php if (is_file(__DIR__ . '/license-issue.php')): ?><a class="btn" href="license-issue.php">Issue</a><?php endif; ?>
          <?php if (is_file(__DIR__ . '/license-extend.php')): ?><a class="btn" href="license-extend.php">Extend</a><?php endif; ?>
        </div>
        <div class="muted">Total: <strong><?= (int)$licensesTotal ?></strong></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h3>Tools</h3>
    <div class="body">
      <div class="btnrow">
        <?php if (is_file(__DIR__ . '/maintenance.php')): ?><a class="btn" href="maintenance.php">Maintenance Mode</a><?php endif; ?>  
        <?php if (is_file(__DIR__ . '/schema-check.php')): ?><a class="btn" href="schema-check.php">Schema Check</a><?php endif; ?>
        <?php if (is_file(__DIR__ . '/devlog.php')): ?><a class="btn" href="devlog.php">Devlog</a><?php endif; ?>
        <?php if (is_file(__DIR__ . '/audit-log.php')): ?><a class="btn" href="audit-log.php">Audit Log</a><?php endif; ?>
        <?php if (is_file(__DIR__ . '/user-security.php')): ?><a class="btn" href="user-security.php">User Security</a><?php endif; ?>
        <?php if (is_file(__DIR__ . '/backup-now.php')): ?><a class="btn" href="backup-now.php">Backup Now</a><?php endif; ?>
        <?php if (is_file(__DIR__ . '/login-attempts.php')): ?><a class="btn" href="login-attempts.php">Login Attempts</a><?php endif; ?>
      </div>
      <div class="muted">reCAPTCHA: <?= (defined('RECAPTCHA_ENABLED') && RECAPTCHA_ENABLED) ? 'on' : 'off' ?> · SMTP: <?= (defined('SMTP_ENABLED') && SMTP_ENABLED) ? 'on' : 'off' ?></div>
      <div class="muted">PHP <?= htmlspecialchars(PHP_VERSION ?: 'n/a') ?> · MySQL <?= htmlspecialchars(@$dbc->server_info ?: 'n/a') ?></div>
    </div>
  </div>
</div>

<div class="grid">
  <div class="card">
    <h3>Security Snapshot</h3>
    <div class="body">
      <div class="muted">Last 24h — Failed: <strong><?= (int)$fail24h ?></strong> • Success: <strong><?= (int)$succ24h ?></strong></div>
      <?php if ($noisy): ?>
        <h4 style="margin:.6rem 0 .25rem">Top Failed IPs</h4>
        <table>
          <tr><th>IP</th><th>Count</th></tr>
          <?php foreach ($noisy as $row): ?>
            <tr>
              <td class="mono"><?= htmlspecialchars((string)$row['ip']) ?></td>
              <td class="mono"><?= (int)$row['c'] ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h3>Recent Audit Events</h3>
    <div class="body">
      <?php if (!$recentAudit): ?>
        <div class="muted">No audit events.</div>
      <?php else: ?>
        <table>
          <tr><th>When</th><th>Event</th><th>Actor</th><th>IP</th></tr>
          <?php foreach ($recentAudit as $a): ?>
            <tr>
              <td class="nowrap"><?= htmlspecialchars((string)$a['created_at']) ?></td>
              <td><?= htmlspecialchars((string)$a['event']) ?></td>
              <td><?= htmlspecialchars((string)$a['actor']) ?></td>
              <td class="mono"><?= htmlspecialchars((string)$a['ip']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h3>Recent Login Attempts <span class="chip"><a href="login-attempts.php" style="text-decoration:none">View all</a></span></h3>
    <div class="body">
      <?php if (!$recentLogin): ?>
        <div class="muted">No login attempts.</div>
      <?php else: ?>
        <table>
          <tr><th>When</th><th>Status</th><th>Actor</th><th>IP</th><th>Note</th></tr>
          <?php foreach ($recentLogin as $l): ?>
            <tr>
              <td class="nowrap"><?= htmlspecialchars((string)$l['created_at']) ?></td>
              <td><?= $l['success'] ? '✅ success' : '❌ failed' ?></td>
              <td><?= htmlspecialchars((string)$l['actor']) ?></td>
              <td class="mono"><?= htmlspecialchars((string)$l['ip']) ?></td>
              <td><?= htmlspecialchars((string)$l['note']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (!$loadedHeader) { echo "</body></html>"; } ?>
