<?php
// admin/user-lock.php — form to lock a user; writes users.locked_until + audit entry
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/account-locks.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/admin-helpers.php';

admin_require_perm('manage_users');

$dbc  = admin_db();
$csrf = admin_csrf();
$back = admin_back('users.php');

$id = max(0, (int)($_REQUEST['id'] ?? 0));
if ($id <= 0) admin_redirect($back, ['err'=>'Invalid user id.']);

$st = $dbc->prepare("SELECT id,name,email,locked_until FROM users WHERE id=?");
$st->bind_param('i', $id); $st->execute();
$user = $st->get_result()->fetch_assoc(); $st->close();
if (!$user) admin_redirect($back, ['err'=>'User not found.']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) admin_redirect($back, ['err'=>'Bad CSRF token.']);
  $until  = trim((string)($_POST['until'] ?? ''));
  $reason = trim((string)($_POST['reason'] ?? ''));
  $ts = strtotime($until);
  if ($ts === false || $ts <= time()) {
    $self = 'user-lock.php?id=' . $id . '&back=' . rawurlencode($back) . '&err=' . rawurlencode('Pick a future date/time.');
    header('Location: '.$self); exit;
  }
  $untilSql = date('Y-m-d H:i:s', $ts);

  $st = $dbc->prepare("UPDATE users SET locked_until=?, updated_at=NOW() WHERE id=?");
  $st->bind_param('si', $untilSql, $id); $ok = $st->execute(); $st->close();

  al_ensure($dbc);
  al_set_lock($dbc, (string)$user['email'], 'user', $untilSql, $reason !== '' ? $reason : 'Locked via Users page');

  if ($ok && function_exists('log_audit')) log_audit('user_lock', ['id'=>$id,'until'=>$untilSql], 'admin');
  admin_redirect($back, $ok ? ['msg'=>"User #{$id} locked until {$untilSql}."] : ['err'=>'Lock failed.']);
  exit;
}

// --- page ---
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/_header.php', __DIR__.'/partials/admin-header.php', __DIR__.'/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) echo "<!doctype html><meta charset='utf-8'><title>Lock User</title><style>body{font-family:system-ui;margin:16px}</style>";
?>
<?php if (!empty($_GET['err'])): ?>
  <div style="margin:.5rem 0;padding:.5rem .75rem;border:1px solid #f3b4b4;background:#ffecec;border-radius:8px;color:#7a1212">
    <?= htmlspecialchars((string)$_GET['err']) ?>
  </div>
<?php endif; ?>
<h2>Lock user #<?= (int)$user['id'] ?> — <?= htmlspecialchars((string)$user['email']) ?></h2>
<form method="post" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
  <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
  <input type="hidden" name="back" value="<?= htmlspecialchars($back) ?>">
  <div>
    <label style="display:block;font-size:.9rem;margin-bottom:.2rem">Lock until</label>
    <input name="until" type="datetime-local" required style="padding:.35rem .5rem;border:1px solid #bbb;border-radius:6px;min-width:240px">
  </div>
  <div>
    <label style="display:block;font-size:.9rem;margin-bottom:.2rem">Reason (optional)</label>
    <input name="reason" type="text" maxlength="255" style="padding:.35rem .5rem;border:1px solid #bbb;border-radius:6px;min-width:280px">
  </div>
  <button class="btn" type="submit" style="padding:.45rem .9rem;border:1px solid #bbb;background:#f7f7f7;border-radius:8px">Lock</button>
  <a class="btn" href="<?= htmlspecialchars($back) ?>" style="padding:.45rem .9rem;border:1px solid #bbb;background:#fff;border-radius:8px;text-decoration:none;color:#222">Cancel</a>
</form>
<?php if (!$loadedHeader) echo "</body></html>"; ?>
