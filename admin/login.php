<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-guard.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/totp.php';
require_once __DIR__ . '/../includes/trust-device.php'; // NEW

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (function_exists('session_guard_boot')) session_guard_boot();

if (function_exists('__log_ensure_tables')) { __log_ensure_tables(); }

$nextRaw = $_GET['next'] ?? $_POST['next'] ?? '';
$next = $nextRaw !== '' ? $nextRaw : 'index.php';

$HARD_TRIES = 8;
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$cutSql = "DATE_SUB(NOW(), INTERVAL 30 MINUTE)";

$dbh = function_exists('__log_db') ? __log_db() : (isset($db) ? $db : (isset($mysqli) ? $mysqli : $conn));

if ($stmt = $dbh->prepare("SELECT COUNT(*) FROM login_attempts WHERE success=0 AND ip=? AND created_at > $cutSql")) {
    $stmt->bind_param('s', $ip);
    $stmt->execute(); $stmt->bind_result($ipFails); $stmt->fetch(); $stmt->close();
}
if ($stmt = $dbh->prepare("SELECT COUNT(*) FROM login_attempts WHERE success=0 AND actor='admin' AND created_at > $cutSql")) {
    $stmt->execute(); $stmt->bind_result($idFails); $stmt->fetch(); $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($ipFails ?? 0) >= $HARD_TRIES || ($idFails ?? 0) >= $HARD_TRIES) {
        $error = "Too many admin PIN attempts. Try again later.";
        if (function_exists('log_audit')) log_audit('admin_pin_rate_limited', ['ip'=>$ip], 'guest');
    } else {
        $pin = (string)($_POST['pin'] ?? '');
        $grant_licenses = !empty($_POST['grant_licenses']);
        $grant_users    = !empty($_POST['grant_users']);
        $grant_devlog   = !empty($_POST['grant_devlog']);

        if ($pin !== '' && defined('ADMIN_PASSWORD_HASH') && password_verify($pin, ADMIN_PASSWORD_HASH)) {
            // If TOTP is ON and device not trusted, go to 2FA step
            if (function_exists('admin_totp_enabled') && admin_totp_enabled() && !admin_trust_is_valid()) {
                $_SESSION['admin_pin_ok'] = 1;
                $_SESSION['admin_pin_time'] = time();
                // Keep chosen perms through the 2FA step
                $_SESSION['pending_perms'] = [
                    'manage_licenses' => $grant_licenses ? 1 : 0,
                    'manage_users'    => $grant_users ? 1 : 0,
                    'manage_devlog'   => $grant_devlog ? 1 : 0,
                ];
                $_SESSION['pending_admin_user'] = $_SESSION['user']['email'] ?? 'admin';
                header("Location: admin-2fa-verify.php?next=" . urlencode($next));
                exit;
            }

            // No 2FA required (trusted device or 2FA disabled)
            $_SESSION['is_admin'] = true;
            $_SESSION['perms'] = [
                'manage_licenses' => $grant_licenses ? 1 : 0,
                'manage_users'    => $grant_users ? 1 : 0,
                'manage_devlog'   => $grant_devlog ? 1 : 0,
            ];
            if (function_exists('log_login_attempt')) log_login_attempt(true, 'admin', $ip);
            header("Location: " . $next);
            exit;
        } else {
            if (function_exists('log_login_attempt')) log_login_attempt(false, 'admin', $ip);
            $error = "Invalid PIN.";
        }
    }
}

$loadedHeader = defined('ADMIN_HEADER_RENDERED');
if (!$loadedHeader) {
  echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Admin Login</title>
  <style>
    .card{border:1px solid #eee; border-radius:10px; padding:12px; max-width:460px}
    input[type=password], input[type=text]{width:100%; padding:.5rem .6rem; border:1px solid #ccc; border-radius:8px}
    label{display:block; margin-top:.5rem}
    .btn{margin-top:.6rem; padding:.5rem .7rem; border:1px solid #ccc; border-radius:8px; background:#fff; cursor:pointer}
    fieldset{border:1px solid #ccc; padding:.5rem; margin-top:.5rem; border-radius:8px}
    .adminbar{display:flex;gap:12px;align-items:center;padding:10px;border-bottom:1px solid #ddd;background:#f7f7f7}
    .adminbar a{color:#333;text-decoration:none}.adminbar a:hover{text-decoration:underline}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}</style>
    </head><body>
    <nav class='adminbar'>
      <a href='index.php'>↩️ Back to Admin Home</a>
      <a href='logout.php'>🚪 Logout</a>
    </nav>";
}
?>
<h1>Admin Login</h1>
<?php if ($error ?? ''): ?>
  <div style="color:#b00; margin:.5rem 0;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post">
  <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">

  <label>Admin PIN
    <input type="password" name="pin" autofocus>
  </label>

  <fieldset style="border:1px solid #ccc; padding:.5rem; margin-top:.5rem;">
    <legend>Admin Permissions</legend>
    <label style="display:flex;align-items:center;gap:.5rem;margin-top:.25rem">
      <input type="checkbox" name="grant_licenses" value="1" checked> Manage licenses
    </label>
    <label style="display:flex;align-items:center;gap:.5rem;margin-top:.25rem">
      <input type="checkbox" name="grant_users" value="1" checked> Manage users
    </label>
    <label style="display:flex;align-items:center;gap:.5rem;margin-top:.25rem">
      <input type="checkbox" name="grant_devlog" value="1" checked> Manage devlog
    </label>
  </fieldset>

  <div style="margin-top:.75rem;">
    <button type="submit">Sign in</button>
    <?php if (function_exists('admin_totp_enabled') && admin_totp_enabled()): ?>
      <span style="color:#555;font-size:.9rem">
        2FA is enabled<?= admin_trust_is_valid() ? ' (trusted device)' : '' ?>.
      </span>
    <?php endif; ?>
  </div>
</form>
<?php if (!$loadedHeader) { echo "</body></html>"; } ?>
