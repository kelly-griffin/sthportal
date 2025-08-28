<?php
// admin/user-security.php — Lock/Unlock users + Sign out everywhere
// Hyphen routes. CSRF-protected. Audit-logged. No schema surprises.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/session-guard.php';
require_admin();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

// ---- DB handle
$dbc = null;
foreach (['db','conn','mysqli'] as $g) if (isset($GLOBALS[$g]) && $GLOBALS[$g] instanceof mysqli) { $dbc = $GLOBALS[$g]; break; }
if (!$dbc && function_exists('get_db')) $dbc = get_db();
if (!$dbc instanceof mysqli) { http_response_code(500); exit('Database handle not initialized.'); }
$dbc->set_charset('utf8mb4');

// ---- Ensure aux tables (matches guard)
$dbc->query("CREATE TABLE IF NOT EXISTS account_locks (
  user_id INT PRIMARY KEY,
  is_locked TINYINT(1) NOT NULL DEFAULT 0,
  reason VARCHAR(255) NULL,
  locked_at DATETIME NULL,
  unlocked_at DATETIME NULL,
  updated_by VARCHAR(128) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$dbc->query("CREATE TABLE IF NOT EXISTS user_session_revocations (
  user_id INT PRIMARY KEY,
  revoked_after DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ---- Actions
$flash = '';
function post($k,$d=''){ return isset($_POST[$k]) ? (string)$_POST[$k] : $d; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($CSRF, post('csrf'))) { http_response_code(400); exit('Bad CSRF'); }
    $action = post('action');
    $uid    = (int)post('user_id', '0');
    if ($uid <= 0) { $flash = 'Invalid user id.'; }
    else {
        if ($action === 'lock') {
            $reason = trim(post('reason',''));
            // Upsert lock
            $stmt = $dbc->prepare("INSERT INTO account_locks (user_id, is_locked, reason, locked_at, updated_by)
                                   VALUES (?,1,?,NOW(),?)
                                   ON DUPLICATE KEY UPDATE is_locked=1, reason=VALUES(reason), locked_at=NOW(), unlocked_at=NULL, updated_by=VALUES(updated_by)");
            $who = $_SESSION['user']['email'] ?? 'admin';
            $stmt->bind_param('iss', $uid, $reason, $who);
            $stmt->execute(); $stmt->close();
            @log_audit('user_locked', ['user_id'=>$uid,'reason'=>$reason], $who, $_SERVER['REMOTE_ADDR'] ?? '');
            $flash = 'User locked.';
        } elseif ($action === 'unlock') {
            $stmt = $dbc->prepare("INSERT INTO account_locks (user_id, is_locked, unlocked_at, updated_by)
                                   VALUES (?,0,NOW(),?)
                                   ON DUPLICATE KEY UPDATE is_locked=0, unlocked_at=NOW(), updated_by=VALUES(updated_by)");
            $who = $_SESSION['user']['email'] ?? 'admin';
            $stmt->bind_param('is', $uid, $who);
            $stmt->execute(); $stmt->close();
            @log_audit('user_unlocked', ['user_id'=>$uid], $who, $_SERVER['REMOTE_ADDR'] ?? '');
            $flash = 'User unlocked.';
        } elseif ($action === 'signout') {
            // Upsert revoke timestamp to now
            $stmt = $dbc->prepare("INSERT INTO user_session_revocations (user_id, revoked_after)
                                   VALUES (?, NOW())
                                   ON DUPLICATE KEY UPDATE revoked_after=NOW()");
            $stmt->bind_param('i', $uid);
            $stmt->execute(); $stmt->close();
            $who = $_SESSION['user']['email'] ?? 'admin';
            @log_audit('user_signout_everywhere', ['user_id'=>$uid], $who, $_SERVER['REMOTE_ADDR'] ?? '');
            $flash = 'All sessions invalidated for that user.';
        }
    }
}

// ---- Discover optional columns
function has_col(mysqli $dbc, string $table, string $col): bool {
    if (!$stmt = $dbc->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1")) return false;
    $stmt->bind_param('ss', $table, $col); $stmt->execute(); $stmt->store_result();
    $ok = $stmt->num_rows > 0; $stmt->free_result(); $stmt->close(); return $ok;
}
$hasRole  = has_col($dbc, 'users', 'role');
$hasName  = has_col($dbc, 'users', 'name');

// ---- Load users + lock + revoke info (latest 200 by default; simple search)
$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$sql = "SELECT u.id, u.email"
     . ($hasName ? ", u.name" : "")
     . ($hasRole ? ", u.role" : "")
     . ", IFNULL(al.is_locked,0) AS is_locked, al.reason, al.locked_at, al.unlocked_at
        , usr.revoked_after
       FROM users u
       LEFT JOIN account_locks al ON al.user_id = u.id
       LEFT JOIN user_session_revocations usr ON usr.user_id = u.id ";

$bind = []; $types = '';
if ($search !== '') {
    $sql .= "WHERE (u.email LIKE CONCAT('%', ?, '%')" . ($hasName ? " OR u.name LIKE CONCAT('%', ?, '%')" : "") . ") ";
    $types .= 's' . ($hasName ? 's' : '');
    $bind[] = $search; if ($hasName) $bind[] = $search;
}
$sql .= "ORDER BY u.id DESC LIMIT 200";
$stmt = $dbc->prepare($sql);
if ($bind) { $stmt->bind_param($types, ...$bind); }
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($res && ($row = $res->fetch_assoc())) $rows[] = $row;
$stmt->close();

// ---- Header include
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/header-admin.php', __DIR__.'/header.php', __DIR__.'/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) echo "<!doctype html><meta charset='utf-8'><title>User Security</title><body style='font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial; margin:16px'>";
?>
<h1>User Security</h1>

<?php if ($flash): ?>
  <div style="margin:.6rem 0;padding:.5rem .6rem;border:1px solid #c8e6c9;background:#e8f5e9;border-radius:8px"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<form method="get" style="margin:.4rem 0 1rem;display:flex;gap:.5rem;align-items:center">
  <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search email<?= $hasName ? '/name' : '' ?>" style="padding:.45rem .6rem;border:1px solid #ccc;border-radius:6px;min-width:260px">
  <button class="btn" type="submit" style="padding:.45rem .8rem;border:1px solid #ccc;border-radius:6px;background:#fff;cursor:pointer">Search</button>
  <a href="user-security.php" class="btn" style="text-decoration:none;color:#333">Clear</a>
</form>

<style>
.table{border-collapse:collapse;width:100%}
.table th,.table td{border:1px solid #ddd;padding:6px 8px}
.table th{background:#f7f7f7;text-align:left}
.badge{padding:.15rem .4rem;border-radius:.3rem;border:1px solid #ccc;font-size:.85rem}
.badge.lock{background:#ffecec;border-color:#e3a0a0}
.badge.ok{background:#e8fff0;border-color:#9bd3af}
.actions form{display:inline}
.actions .btn{padding:.3rem .55rem;border:1px solid #ccc;border-radius:6px;background:#fff;cursor:pointer}
.small{color:#555;font-size:.85rem}
</style>

<?php if (!$rows): ?>
  <div style="padding:10px;border:1px dashed #ccc;background:#fafafa;border-radius:8px">No users found.</div>
<?php else: ?>
  <table class="table">
    <tr>
      <th>ID</th><th>Email</th><?php if($hasName): ?><th>Name</th><?php endif; ?><?php if($hasRole): ?><th>Role</th><?php endif; ?>
      <th>Status</th><th>Last sign-out-all</th><th>Actions</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= htmlspecialchars((string)$r['email']) ?></td>
        <?php if($hasName): ?><td><?= htmlspecialchars((string)($r['name'] ?? '')) ?></td><?php endif; ?>
        <?php if($hasRole): ?><td><?= htmlspecialchars((string)($r['role'] ?? '')) ?></td><?php endif; ?>
        <td>
          <?php if ((int)$r['is_locked'] === 1): ?>
            <span class="badge lock">locked</span>
            <?php if (!empty($r['reason'])): ?><div class="small">reason: <?= htmlspecialchars((string)$r['reason']) ?></div><?php endif; ?>
          <?php else: ?>
            <span class="badge ok">active</span>
          <?php endif; ?>
        </td>
        <td class="small"><?= htmlspecialchars((string)($r['revoked_after'] ?? '—')) ?></td>
        <td class="actions">
          <?php if ((int)$r['is_locked'] === 1): ?>
            <form method="post" onsubmit="return confirm('Unlock this user?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
              <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="action" value="unlock">
              <button class="btn" type="submit">Unlock</button>
            </form>
          <?php else: ?>
            <form method="post" onsubmit="return lockPrompt(this);">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
              <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="action" value="lock">
              <input type="hidden" name="reason" value="">
              <button class="btn" type="submit">Lock</button>
            </form>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('Invalidate all sessions for this user?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
            <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="action" value="signout">
            <button class="btn" type="submit">Sign out everywhere</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <script>
    function lockPrompt(form){
      var r = prompt('Reason for locking (optional):','');
      if (r === null) return false;
      form.querySelector('input[name=reason]').value = (r||'').trim();
      return true;
    }
  </script>
<?php endif; ?>

<?php if (!$loadedHeader) { echo "</body></html>"; } ?>