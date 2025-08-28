<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session-guard.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/totp.php';
require_once __DIR__ . '/../includes/admin-2fa-codes.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (function_exists('session_guard_boot')) session_guard_boot();

// Only allow if we either already are admin OR just passed PIN and need 2FA
if (empty($_SESSION['admin_pin_ok']) && empty($_SESSION['is_admin'])) {
  header('Location: login.php'); exit;
}
if (!admin_totp_enabled()) { header('Location: index.php'); exit; }

$pending = $_SESSION['admin_pin_ok'] ?? null;
$next = (is_array($pending) && !empty($pending['next'])) ? (string)$pending['next'] : 'index.php';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $code = (string)($_POST['code'] ?? '');
  $remember = !empty($_POST['remember']);
  $ok = false;

  // 1) Try TOTP (uses totp.php signature: secret + code)
  if (!$ok) {
    $secret = admin_totp_secret();
    if ($secret && totp_verify($secret, $code, 1, 30, 6, 0)) {
      $ok = true;
    }
  }

  // 2) Try backup code (one-time)
  if (!$ok) {
    $ok = a2bc_try(__log_db(), $code);
  }

  if ($ok) {
    // Promote to full admin, keep perms passed from PIN step
    $_SESSION['is_admin'] = true;
    if (is_array($pending) && isset($pending['perms'])) {
      $_SESSION['perms'] = $pending['perms'];
    }
    unset($_SESSION['admin_pin_ok']);

    // Trust this device for 30 days (correct helper from totp.php)
    if ($remember && function_exists('admin_trust_set')) {
      admin_trust_set(30);
    }

    log_audit('admin_2fa_ok', [], 'admin');
    log_login_attempt(true, 'admin 2fa ok', 'admin');

    if (function_exists('session_on_auth_success')) session_on_auth_success();
    header('Location: ' . $next);
    exit;
  } else {
    $msg = 'Invalid code. Enter a 6-digit code from your app or a backup code (e.g., ABCDE-12345).';
    log_audit('admin_2fa_fail', [], 'guest');
    log_login_attempt(false, 'admin 2fa fail', 'admin');
  }
}

// ------------ UI ------------
$loadedHeader = false;
foreach ([__DIR__ . '/admin-header.php', __DIR__ . '/header-admin.php', __DIR__ . '/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) echo "<!doctype html><meta charset='utf-8'><title>2FA</title><body style='font-family:system-ui,Segoe UI,Roboto,Arial'>";
?>
<style>
  .wrap{max-width:560px;margin:16px auto;padding:16px}
  .panel{border:1px solid #e5e7eb;background:#f9fafb;border-radius:10px;padding:14px}
  .err{border:1px solid #fecaca;background:#fff1f2;border-radius:8px;padding:.6rem .8rem;margin:.5rem 0}
  .muted{color:#555;font-size:.9rem}
  input[type=text]{padding:.45rem .6rem;border:1px solid #ccc;border-radius:6px;width:200px}
  .btn{padding:.45rem .9rem;border:1px solid #bbb;background:#f7f7f7;border-radius:8px;cursor:pointer}
  label{display:block;margin-top:.5rem}
</style>
<div class="wrap">
  <h1>Two-Factor Authentication</h1>
  <?php if ($msg): ?><div class="err"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <div class="panel">
    <form method="post">
      <label>Enter code (TOTP or backup)
        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456 or ABCDE-12345" autofocus>
      </label>
      <label style="display:flex;align-items:center;gap:.5rem;margin-top:.25rem">
        <input type="checkbox" name="remember" value="1" checked> Remember this browser for 30 days
      </label>
      <div style="margin-top:12px">
        <button class="btn" type="submit">Verify</button>
        <span class="muted">Tip: use a backup code if your app’s unavailable.</span>
      </div>
    </form>
  </div>
</div>
<?php if (!$loadedHeader) echo "</body></html>"; ?>
