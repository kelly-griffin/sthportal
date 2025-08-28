<?php
// admin/roles.php — Roles Manager (Option A)
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-guard.php';
require_admin();

// DB handle
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('Database handle not initialized.'); }

// Ensure roles table
$dbc->query("CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL UNIQUE,
  admin_access TINYINT(1) NOT NULL DEFAULT 0,
  gets_digest TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 100,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Seed defaults when empty
$seed = 0;
if ($res = $dbc->query("SELECT COUNT(*) FROM roles")) { $seed = (int)($res->fetch_row()[0] ?? 0); }
if ($seed === 0) {
  $dbc->query("INSERT IGNORE INTO roles (name, admin_access, gets_digest, sort_order, note) VALUES
    ('member', 0, 0, 100, 'regular user'),
    ('admin', 1, 1, 10, 'full admin access'),
    ('owner', 1, 1, 20, 'league owner'),
    ('commission', 1, 1, 30, 'commissioner'),
    ('commish', 1, 1, 31, 'alias for commissioner'),
    ('gm', 0, 1, 40, 'general manager')");
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function bval($v): int { return (!empty($v) && $v !== '0') ? 1 : 0; }

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = (string)($_POST['action'] ?? '');
  if ($act === 'add') {
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') { $err = 'Name required.'; }
    else {
      $admin_access = bval($_POST['admin_access'] ?? 0);
      $gets_digest  = bval($_POST['gets_digest'] ?? 0);
      $sort_order   = (int)($_POST['sort_order'] ?? 100);
      $note         = trim((string)($_POST['note'] ?? ''));
      if ($st = $dbc->prepare("INSERT INTO roles (name, admin_access, gets_digest, sort_order, note) VALUES (?,?,?,?,?)")) {
        $st->bind_param('siiis', $name, $admin_access, $gets_digest, $sort_order, $note);
        if ($st->execute()) $msg = 'Role added.'; else $err = 'Could not add role (duplicate?)';
        $st->close();
      } else { $err = 'DB error.'; }
    }
  } elseif ($act === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $old = null; if ($rs = $dbc->query("SELECT name FROM roles WHERE id=$id")) { $old = $rs->fetch_row()[0] ?? null; }
    $name = trim((string)($_POST['name'] ?? ''));
    $admin_access = bval($_POST['admin_access'] ?? 0);
    $gets_digest  = bval($_POST['gets_digest'] ?? 0);
    $sort_order   = (int)($_POST['sort_order'] ?? 100);
    $note         = trim((string)($_POST['note'] ?? ''));
    if ($st = $dbc->prepare("UPDATE roles SET name=?, admin_access=?, gets_digest=?, sort_order=?, note=? WHERE id=?")) {
      $st->bind_param('siiisi', $name, $admin_access, $gets_digest, $sort_order, $note, $id);
      if ($st->execute()) {
        if ($old && $old !== $name) {
          if ($st2 = $dbc->prepare("UPDATE users SET role=? WHERE role=?")) {
            $st2->bind_param('ss', $name, $old); $st2->execute(); $st2->close();
          }
        }
        $msg = 'Role updated.';
      } else { $err = 'Update failed.'; }
      $st->close();
    } else { $err = 'DB error.'; }
  } elseif ($act === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $name = ''; if ($rs = $dbc->query("SELECT name FROM roles WHERE id=$id")) { $name = (string)($rs->fetch_row()[0] ?? ''); }
    if ($name === '') { $err = 'Invalid role.'; }
    else {
      $inuse = 0; if ($rs = $dbc->prepare("SELECT COUNT(*) FROM users WHERE role=?")) { $rs->bind_param('s',$name); $rs->execute(); $rs->bind_result($inuse); $rs->fetch(); $rs->close(); }
      if ($inuse > 0) $err = "Cannot delete — $inuse user(s) still have this role.";
      else { if ($dbc->query("DELETE FROM roles WHERE id=$id")) $msg = 'Role deleted.'; else $err = 'Delete failed.'; }
    }
  }
}

$rows = [];
$sql = "SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role = r.name) AS users_using
        FROM roles r ORDER BY r.sort_order, r.name";
if ($rs = $dbc->query($sql)) while ($r = $rs->fetch_assoc()) $rows[] = $r;

// header include
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/header-admin.php', __DIR__.'/header.php', __DIR__.'/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) echo "<!doctype html><meta charset='utf-8'><title>Roles</title><body style='font-family:system-ui; margin:16px'>";
?>
<h1>Roles</h1>

<?php if ($msg): ?><div style="padding:.5rem;border:1px solid #cde;background:#f6fff6;margin:.6rem 0;border-radius:6px;"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div style="padding:.5rem;border:1px solid #f5c2c7;background:#fff0f0;margin:.6rem 0;border-radius:6px;"><?= e($err) ?></div><?php endif; ?>

<div style="border:1px solid #ddd;border-radius:8px;background:#fff;margin:.6rem 0;">
  <h3 style="margin:0;padding:10px 12px;border-bottom:1px solid #eee;background:#fafafa">Add Role</h3>
  <div style="padding:10px 12px">
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <input type="hidden" name="action" value="add">
      <label>Name <input type="text" name="name" required style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px"></label>
      <label><input type="checkbox" name="admin_access" value="1"> Admin access</label>
      <label><input type="checkbox" name="gets_digest" value="1" checked> Gets digest</label>
      <label>Sort <input type="number" name="sort_order" value="100" style="width:5rem;padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px"></label>
      <label>Note <input type="text" name="note" style="min-width:16rem;padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px"></label>
      <button type="submit" class="btn" style="padding:.35rem .6rem;border:1px solid #ccc;border-radius:6px;background:#fff">Add</button>
    </form>
  </div>
</div>

<table style="width:100%;border-collapse:collapse;margin-top:.6rem">
  <thead><tr style="background:#fafafa">
    <th style="text-align:left;padding:.4rem;border-bottom:1px solid #eee">Name</th>
    <th style="text-align:left;padding:.4rem;border-bottom:1px solid #eee">Admin</th>
    <th style="text-align:left;padding:.4rem;border-bottom:1px solid #eee">Gets Digest</th>
    <th style="text-align:left;padding:.4rem;border-bottom:1px solid #eee">Users</th>
    <th style="text-align:left;padding:.4rem;border-bottom:1px solid #eee">Sort</th>
    <th style="text-align:left;padding:.4rem;border-bottom:1px solid #eee">Note</th>
    <th style="text-align:left;padding:.4rem;border-bottom:1px solid #eee">Actions</th>
  </tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <form method="post">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <td style="padding:.35rem;border-bottom:1px solid #eee"><input type="text" name="name" value="<?= e($r['name']) ?>" style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px"></td>
          <td style="padding:.35rem;border-bottom:1px solid #eee"><input type="checkbox" name="admin_access" value="1" <?= $r['admin_access'] ? 'checked' : '' ?>></td>
          <td style="padding:.35rem;border-bottom:1px solid #eee"><input type="checkbox" name="gets_digest" value="1" <?= $r['gets_digest'] ? 'checked' : '' ?>></td>
          <td style="padding:.35rem;border-bottom:1px solid #eee"><?= (int)$r['users_using'] ?></td>
          <td style="padding:.35rem;border-bottom:1px solid #eee"><input type="number" name="sort_order" value="<?= (int)$r['sort_order'] ?>" style="width:5rem;padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px"></td>
          <td style="padding:.35rem;border-bottom:1px solid #eee"><input type="text" name="note" value="<?= e((string)$r['note']) ?>" style="min-width:14rem;padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px"></td>
          <td style="padding:.35rem;border-bottom:1px solid #eee">
            <button type="submit" class="btn" style="padding:.3rem .55rem;border:1px solid #ccc;border-radius:6px;background:#fff">Save</button>
            <?php if ((int)$r['users_using'] === 0): ?>
              <button type="submit" name="action" value="delete" onclick="return confirm('Delete role &quot;<?= e($r['name']) ?>&quot;?');" style="padding:.3rem .55rem;border:1px solid #f5c2c7;border-radius:6px;background:#fff0f0;color:#8a0000">Delete</button>
            <?php else: ?>
              <span style="color:#777">in use</span>
            <?php endif; ?>
          </td>
        </form>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if (!$loadedHeader) echo "</body></html>"; ?>
