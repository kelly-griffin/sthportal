<?php
// reset-password.php — robust version with optional debug output
// Add &debug=1 to the URL to see detailed errors (dev only).

require_once __DIR__ . '/includes/config.php';        // pulls env + maintenance (won’t block this page)
require_once __DIR__ . '/includes/password-reset.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

$DEBUG = isset($_GET['debug']) && $_GET['debug'] == '1';
if ($DEBUG) { ini_set('display_errors','1'); error_reporting(E_ALL); }
else        { ini_set('display_errors','0'); }

$fatal = '';
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$info  = null;
$done  = false; 
$err   = '';

try {
    if ($token !== '') {
        $info = pr_validate_token($token); // returns null if invalid/expired
    }
} catch (Throwable $e) {
    $fatal = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$fatal) {
    if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Bad CSRF'); }
    try {
        if (!$info) {
            $err = 'Invalid or expired link.';
        } else {
            $p1 = (string)($_POST['password'] ?? '');
            $p2 = (string)($_POST['password2'] ?? '');
            if ($p1 === '' || strlen($p1) < 8)        $err = 'Password must be at least 8 characters.';
            elseif ($p1 !== $p2)                      $err = 'Passwords do not match.';
            else if (!pr_consume_and_set_password($token, $p1)) $err = 'Unable to set the new password.';
            else $done = true;
        }
    } catch (Throwable $e) {
        $fatal = $e->getMessage();
    }
}

?><!doctype html>
<meta charset="utf-8">
<title>Reset Password</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#f7f7f9}
  .wrap{max-width:420px;margin:8vh auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.04);padding:20px}
  h1{margin:0 0 10px;font-size:1.4rem}
  label{display:block;margin:.5rem 0 .25rem}
  input[type=password]{width:100%;padding:.5rem .6rem;border:1px solid #cbd5e1;border-radius:6px}
  .btn{margin-top:.6rem; padding:.5rem .7rem;border:1px solid #6366f1;background:#fff;border-radius:6px;cursor:pointer}
  .muted{color:#555}
  .msg{margin:.6rem 0;padding:.5rem .6rem;border:1px solid #c8e6c9;background:#e8f5e9;border-radius:8px}
  .err{margin:.6rem 0;padding:.5rem .6px;border:1px solid #ef9a9a;background:#ffebee;border-radius:8px}
  .fatal{margin:.6rem 0;padding:.5rem .6rem;border:1px solid #ef9a9a;background:#fff5f5;border-radius:8px}
</style>
<div class="wrap">
  <h1>Reset Password</h1>

  <?php if ($fatal): ?>
    <div class="fatal"><strong>Server error:</strong> <?= htmlspecialchars($fatal) ?><br><span class="muted">Tip: keep <code>?debug=1</code> on while testing.</span></div>
    <p><a href="forgot-password.php">Request a new link</a></p>
  <?php elseif ($done): ?>
    <div class="msg">Your password has been updated. You can now sign in.</div>
    <p><a href="login.php">Back to login</a></p>
  <?php elseif (!$info): ?>
    <div class="err">Invalid or expired link.</div>
    <p><a href="forgot-password.php">Request a new link</a></p>
  <?php else: ?>
    <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <label for="password">New password</label>
      <input required type="password" id="password" name="password" minlength="8" placeholder="At least 8 characters">
      <label for="password2">Confirm password</label>
      <input required type="password" id="password2" name="password2" minlength="8">
      <button class="btn" type="submit">Set new password</button>
    </form>
  <?php endif; ?>
</div>
