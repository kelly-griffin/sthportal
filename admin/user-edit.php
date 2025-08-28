<?php
// admin/user-edit.php — create/edit user + CSRF + unique email + toast redirect
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/admin-helpers.php';

admin_require_perm('manage_users');

$dbc  = admin_db();
$csrf = admin_csrf();
$back = admin_back('users.php');
$id   = max(0, (int)($_REQUEST['id'] ?? 0)); // 0=new

// roles
$roles = [];
try {
  $chk = $dbc->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'roles'");
  if ($chk && $chk->fetch_row()) {
    if ($rs = $dbc->query("SELECT name FROM roles ORDER BY sort_order, name")) {
      while ($r = $rs->fetch_row()) { if (!empty($r[0])) $roles[] = (string)$r[0]; }
    }
  } else {
    if ($rs = $dbc->query("SELECT DISTINCT role FROM users ORDER BY role")) {
      while ($r = $rs->fetch_row()) { if (!empty($r[0])) $roles[] = (string)$r[0]; }
    }
  }
} catch (Throwable $e) {
  if ($rs = $dbc->query("SELECT DISTINCT role FROM users ORDER BY role")) {
    while ($r = $rs->fetch_row()) { if (!empty($r[0])) $roles[] = (string)$r[0]; }
  }
}
$__builtin_roles = ['member','admin','owner','commission','commish','gm'];
$roles = array_values(array_unique(array_merge($__builtin_roles, $roles)));
sort($roles, SORT_STRING | SORT_FLAG_CASE);

$roleSel = function(string $cur) use ($roles) {
  $h = '<select name="role">';
  foreach ($roles as $r) {
    $sel = ($r === $cur) ? ' selected' : '';
    $h .= '<option value="'.htmlspecialchars($r, ENT_QUOTES).'">'.$r.'</option>';
  }
  $h .= '</select>';
  return $h;
};// roles
$roles = [];
if ($rs = $dbc->query("SELECT DISTINCT role FROM users ORDER BY role")) {
  while ($r = $rs->fetch_row()) { if (!empty($r[0])) $roles[] = (string)$r[0]; }
}
if (!$roles) $roles = ['member','admin'];

// load
$user = ['id'=>0,'name'=>'','email'=>'','role'=>'member','active'=>1,'locked_until'=>null,'created_at'=>null,'updated_at'=>null];
if ($id > 0) {
  $st = $dbc->prepare("SELECT id,name,email,role,active,locked_until,created_at,updated_at FROM users WHERE id=?");
  $st->bind_param('i', $id); $st->execute();
  $row = $st->get_result()->fetch_assoc(); $st->close();
  if (!$row) admin_redirect($back, ['err'=>'User not found.']);
  $user = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) admin_redirect($back, ['err'=>'Bad CSRF token.']);

  $name   = trim((string)($_POST['name'] ?? ''));
  $email  = trim((string)($_POST['email'] ?? ''));
  $role   = trim((string)($_POST['role'] ?? 'member'));
  $active = isset($_POST['active']) ? 1 : 0;
  $pw1    = (string)($_POST['password'] ?? '');
  $pw2    = (string)($_POST['password2'] ?? '');

  $self = 'user-edit.php?id=' . $id . '&back=' . rawurlencode($back);

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { header('Location: '.$self.'&err='.rawurlencode('Enter a valid email.')); exit; }
  if ($pw1 !== '' && $pw1 !== $pw2) { header('Location: '.$self.'&err='.rawurlencode('Passwords do not match.')); exit; }
  if ($pw1 !== '' && strlen($pw1) < 8) { header('Location: '.$self.'&err='.rawurlencode('Password must be at least 8 characters.')); exit; }
  if ($role === '') $role = 'member';

  if ($id > 0) {
    $st = $dbc->prepare("SELECT id FROM users WHERE email=? AND id<>?");
    $st->bind_param('si', $email, $id);
  } else {
    $st = $dbc->prepare("SELECT id FROM users WHERE email=?");
    $st->bind_param('s', $email);
  }
  $st->execute(); $exists = $st->get_result()->fetch_row(); $st->close();
  if ($exists) { header('Location: '.$self.'&err='.rawurlencode('Email already in use.')); exit; }

  if ($id > 0) {
    if ($pw1 !== '') {
      $hash = password_hash($pw1, PASSWORD_DEFAULT);
      $st = $dbc->prepare("UPDATE users SET name=?, email=?, role=?, active=?, password_hash=?, updated_at=NOW() WHERE id=?");
      $st->bind_param('sssisi', $name, $email, $role, $active, $hash, $id);
    } else {
      $st = $dbc->prepare("UPDATE users SET name=?, email=?, role=?, active=?, updated_at=NOW() WHERE id=?");
      $st->bind_param('sssii', $name, $email, $role, $active, $id);
    }
    $ok = $st->execute(); $st->close();
    if ($ok && function_exists('log_audit')) log_audit('user_update', ['id'=>$id,'email'=>$email,'active'=>$active,'role'=>$role], 'admin');
    admin_redirect($back, $ok ? ['msg'=>"User #{$id} updated."] : ['err'=>'Save failed.']);
  } else {
    $hash = $pw1 !== '' ? password_hash($pw1, PASSWORD_DEFAULT) : null;
    $st = $dbc->prepare("INSERT INTO users (name,email,role,active,password_hash,created_at) VALUES (?,?,?,?,?,NOW())");
    $st->bind_param('sssis', $name, $email, $role, $active, $hash);
    $ok = $st->execute(); $newId = (int)$dbc->insert_id; $st->close();
    if ($ok && function_exists('log_audit')) log_audit('user_create', ['id'=>$newId,'email'=>$email,'active'=>$active,'role'=>$role], 'admin');
    admin_redirect($back, $ok ? ['msg'=>"User #{$newId} created."] : ['err'=>'Create failed.']);
  }
  exit;
}

// header
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/_header.php', __DIR__.'/header-admin.php', __DIR__.'/partials/admin-header.php', __DIR__.'/../includes/admin-header.php', __DIR__.'/../includes/header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
require_once __DIR__ . '/../includes/toast-center.php';

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$self = 'user-edit.php?id=' . $id . '&back=' . rawurlencode($back);
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><title><?= $id>0?'Edit User #'.(int)$user['id']:'New User' ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu; <?php if(!$loadedHeader): ?>margin:16px;<?php endif; ?> }
  .wrap { max-width:900px; }
  form { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
  label { display:block; font-size:.9rem; color:#333; margin-bottom:.2rem; }
  input[type="text"],input[type="email"],input[type="password"],select{width:100%;padding:.5rem .6rem;border:1px solid #ccc;border-radius:.5rem;font-size:.95rem;}
  .row{margin:.3rem 0 .6rem;} .row-full{grid-column:1/-1;}
  .actions{display:flex;gap:.5rem;margin-top:.6rem;}
  .btn{padding:.5rem .8rem;border:1px solid #ccc;border-radius:.5rem;background:#fff;text-decoration:none;color:#222;cursor:pointer;}
  .btn.primary{background:#0b5;border-color:#0b5;color:#fff;}
  .muted{color:#667;font-size:.9rem;}
</style></head><body>
<div class="wrap">
  <h1><?= $id>0?'Edit User':'New User' ?></h1>

  <?php if (!empty($_GET['err'])): ?>
    <div style="margin:.5rem 0;padding:.5rem .75rem;border:1px solid #f3b4b4;background:#ffecec;border-radius:8px;color:#7a1212">
      <?= h((string)$_GET['err']) ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= h($self) ?>">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="back" value="<?= h($back) ?>">

    <div class="row"><label>Name</label><input type="text" name="name" value="<?= h((string)$user['name']) ?>"></div>
    <div class="row"><label>Email</label><input type="email" name="email" required value="<?= h((string)$user['email']) ?>"></div>
    <div class="row"><label>Role</label>
      <select name="role"><?php foreach ($roles as $r): ?><option value="<?= h($r) ?>" <?= ($user['role']??'member')===$r?'selected':'' ?>><?= h(ucfirst($r)) ?></option><?php endforeach; ?></select>
    </div>
    <div class="row"><label>Active</label>
      <label style="display:inline-flex;align-items:center;gap:.4rem;"><input type="checkbox" name="active" value="1" <?= !empty($user['active'])?'checked':'' ?>> Active</label>
    </div>
    <div class="row row-full"><label>New Password <span class="muted">(leave blank to keep current)</span></label>
      <input type="password" name="password" autocomplete="new-password" placeholder="At least 8 characters"></div>
    <div class="row row-full"><label>Confirm Password</label><input type="password" name="password2" autocomplete="new-password"></div>
    <?php if (!empty($user['locked_until'])): ?><div class="row row-full muted">Locked until: <?= h((string)$user['locked_until']) ?></div><?php endif; ?>
    <div class="row row-full actions"><button class="btn primary" type="submit">Save</button><a class="btn" href="<?= h($back) ?>">Cancel</a></div>
  </form>
<?php
// ---- Alt Radar Watchlist (email-based toggle) ----
if (!function_exists('alt_watch_ensure')) {
  function alt_watch_ensure(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS alt_radar_watchlist (
      id INT AUTO_INCREMENT PRIMARY KEY,
      identity VARCHAR(190) NOT NULL,
      note VARCHAR(255) NULL,
      created_by VARCHAR(128) NULL,
      created_ip VARCHAR(45) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_identity (identity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wl_action'])) {
  if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) admin_redirect($back, ['err'=>'Bad CSRF token.']);
  alt_watch_ensure($dbc);
  $wl_action = (string)($_POST['wl_action'] ?? '');
  $ident = trim((string)($_POST['identity'] ?? ''));
  $note  = trim((string)($_POST['note'] ?? ''));
  $self  = 'user-edit.php?id=' . $id . '&back=' . rawurlencode($back);
  if ($ident === '') admin_redirect($self, ['err'=>'Identity required.']);
  if ($wl_action === 'add') {
    $who = $_SESSION['user']['email'] ?? 'admin';
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
    $sql = "INSERT INTO alt_radar_watchlist (identity, note, created_by, created_ip)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE note=VALUES(note)";
    if ($st = $dbc->prepare($sql)) { $st->bind_param('ssss', $ident, $note, $who, $ip); $st->execute(); $st->close(); }
    admin_redirect($self, ['msg'=>'Added to watchlist.']);
  } elseif ($wl_action === 'del') {
    if ($st = $dbc->prepare("DELETE FROM alt_radar_watchlist WHERE identity=?")) { $st->bind_param('s', $ident); $st->execute(); $st->close(); }
    admin_redirect($self, ['msg'=>'Removed from watchlist.']);
  }
  exit;
}

// Pre-compute current user's watch status and recent stats
alt_watch_ensure($dbc);
$identityEmail = (string)($user['email'] ?? '');
$wl = null;
if ($identityEmail !== '') {
  if ($st = $dbc->prepare("SELECT identity, note, created_at FROM alt_radar_watchlist WHERE identity=?")) {
    $st->bind_param('s', $identityEmail); $st->execute();
    $wl = $st->get_result()->fetch_assoc(); $st->close();
  }
  // stats (last 60 days; exclude localhost; successes only)
  $days = 60;
  $last_seen = null; $ip_count = 0;
  if ($st = $dbc->prepare("SELECT MAX(attempted_at) AS last_seen, COUNT(DISTINCT ip) AS ips
                           FROM login_attempts
                           WHERE (COALESCE(username, actor, `identity`) = ?)
                             AND ip IS NOT NULL AND ip <> ''
                             AND success = 1
                             AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                             AND ip NOT IN ('127.0.0.1','::1','::ffff:127.0.0.1')")) {
    $st->bind_param('si', $identityEmail, $days); $st->execute();
    $res = $st->get_result()->fetch_assoc(); $st->close();
    $last_seen = $res['last_seen'] ?? null;
    $ip_count  = (int)($res['ips'] ?? 0);
  }
  $bonus = 0;
  if (!empty($last_seen)) {
    $ts = strtotime((string)$last_seen);
    if ($ts !== false) {
      $daysAgo = (time() - $ts) / 86400.0;
      if ($daysAgo <= 7) $bonus = 2; elseif ($daysAgo <= 30) $bonus = 1;
    }
  }
  $score = $ip_count + $bonus;
}
?>

<div class="card" style="margin-top:1rem;padding:.8rem 1rem;border:1px solid #eee;border-radius:10px;background:#fff;">
  <h2 style="margin:.2rem 0 .6rem;">⭐ Alt Radar Watchlist</h2>
  <?php if ($identityEmail === ''): ?>
    <div class="muted">Save the user first to enable watchlist.</div>
  <?php else: ?>
    <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;">
      <div>
        <div class="muted">Identity</div>
        <div style="font-weight:600;"><?= h($identityEmail) ?></div>
      </div>
      <div>
        <div class="muted">Last Seen (60d)</div>
        <div><?= !empty($last_seen) ? h((string)$last_seen) : '—' ?></div>
      </div>
      <div>
        <div class="muted">Distinct IPs (60d)</div>
        <div><?= (int)$ip_count ?></div>
      </div>
      <div>
        <div class="muted">Score</div>
        <div style="font-weight:700;"><?= (int)($score ?? 0) ?></div>
      </div>
    </div>

    <?php if ($wl): ?>
      <div style="margin-top:.6rem;display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;">
        <form method="post" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;flex:1;">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="wl_action" value="add">
          <input type="hidden" name="identity" value="<?= h($identityEmail) ?>">
          <div style="flex:1;min-width:260px;">
            <label style="display:block;font-weight:600;margin-bottom:.2rem;">Note</label>
            <input name="note" value="<?= h((string)($wl['note'] ?? '')) ?>" style="width:100%;padding:.35rem;border:1px solid #ddd;border-radius:.35rem;">
          </div>
          <div>
            <button class="btn" type="submit">Update Note</button>
          </div>
        </form>
        <form method="post" onsubmit="return confirm('Remove from watchlist?')" style="display:inline;">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="wl_action" value="del">
          <input type="hidden" name="identity" value="<?= h($identityEmail) ?>">
          <button class="btn" type="submit">Remove</button>
        </form>
      </div>
    <?php else: ?>
      <form method="post" style="margin-top:.6rem;display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="wl_action" value="add">
        <input type="hidden" name="identity" value="<?= h($identityEmail) ?>">
        <div style="flex:1;min-width:260px;">
          <label style="display:block;font-weight:600;margin-bottom:.2rem;">Note (optional)</label>
          <input name="note" placeholder="Why are we watching this account?" style="width:100%;padding:.35rem;border:1px solid #ddd;border-radius:.35rem;">
        </div>
        <div>
          <button class="btn" type="submit">Add to Watchlist</button>
        </div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

</div>
<?php if (!$loadedHeader) echo "</body></html>"; ?>
