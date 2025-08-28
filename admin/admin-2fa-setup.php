<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-guard.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/totp.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_admin();
if (function_exists('session_guard_boot')) session_guard_boot();

$loadedHeader = false;
foreach ([__DIR__ . '/admin-header.php', __DIR__ . '/header-admin.php', __DIR__ . '/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) echo "<!doctype html><meta charset='utf-8'><title>Admin 2FA</title><body style='font-family:system-ui,Segoe UI,Roboto,Arial'>";

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Bad CSRF'); }
  $action = (string)($_POST['action'] ?? '');
  if ($action === 'enable') {
    $secret = (string)($_POST['secret'] ?? '');
    $code   = (string)($_POST['code'] ?? '');
    if ($secret === '' || $code === '') { $err = 'Secret and code required.'; }
    elseif (!totp_verify($secret, $code, 1)) { $err = 'Invalid or expired code.'; }
    else {
      admin_totp_set_secret($secret);
      admin_totp_set_enabled(true);
      admin_trust_clear(); // force new trust after enabling
      log_audit('admin_2fa_enabled', [], 'admin');
      $msg = 'Admin 2FA enabled.';
    }
  } elseif ($action === 'disable') {
    admin_totp_set_enabled(false);
    admin_trust_clear();
    log_audit('admin_2fa_disabled', [], 'admin');
    $msg = 'Admin 2FA disabled.';
  }
}

$enabled = admin_totp_enabled();
$secret  = admin_totp_secret();
if (!$enabled && !$secret) { $secret = totp_secret_generate(); }

$issuer = rawurlencode('STH Portal Admin');
$label  = rawurlencode('admin');
$otpauth = "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&digits=6&period=30";
$qr = "https://api.qrserver.com/v1/create-qr-code/?size=196x196&data=" . rawurlencode($otpauth);
?>
<h1>Admin 2FA</h1>

<?php if ($msg): ?><div style="background:#e8fff0;border:1px solid #9bd3af;padding:.6rem;border-radius:8px;margin:.5rem 0"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div style="background:#ffecec;border:1px solid #e3a0a0;padding:.6rem;border-radius:8px;margin:.5rem 0"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:200px 1fr;gap:16px;align-items:start;max-width:700px">
  <img src="<?= htmlspecialchars($qr) ?>" alt="Scan with Authenticator app" width="196" height="196" style="border:1px solid #eee;border-radius:8px;background:#fff">
  <div>
    <p style="margin-top:0">Scan this QR in Google Authenticator / Authy, or enter the key manually.</p>
    <div style="font-family:ui-monospace,Menlo,Consolas,monospace;padding:.4rem .6rem;border:1px solid #ddd;border-radius:8px;background:#fafafa;display:inline-block"><?= htmlspecialchars($secret) ?></div>
    <p style="margin:.5rem 0 0">Account: <strong>admin</strong> · Issuer: <strong>STH Portal Admin</strong></p>
  </div>
</div>

<?php if (!$enabled): ?>
  <form method="post" style="margin-top:12px">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
    <input type="hidden" name="action" value="enable">
    <input type="hidden" name="secret" value="<?= htmlspecialchars($secret) ?>">
    <label>Enter a code from your app to confirm:
      <input type="text" name="code" autocomplete="one-time-code" style="padding:.4rem;border:1px solid #ccc;border-radius:6px" placeholder="123456">
    </label>
    <div style="margin-top:.5rem">
      <button type="submit">Enable 2FA</button>
    </div>
  </form>
<?php else: ?>
  <form method="post" style="margin-top:12px" onsubmit="return confirm('Disable Admin 2FA?');">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
    <input type="hidden" name="action" value="disable">
    <button type="submit">Disable 2FA</button>
  </form>
<?php endif; ?>

<?php if (!$loadedHeader) echo "</body></html>"; ?>
