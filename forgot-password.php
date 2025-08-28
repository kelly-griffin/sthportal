<?php
// forgot-password.php — request a reset link
require_once __DIR__ . '/includes/password-reset.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

$sent = false; $devLink = '';
$err  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Bad CSRF'); }

    $email = trim((string)($_POST['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email.';
    } else {
        [$ok, $link] = pr_request_reset($email, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $sent = true;
        // Dev convenience: show link if SMTP may not be configured
        if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
            $devLink = ''; // hide if SMTP is on
        } else {
            $devLink = $link;
        }
    }
}

?><!doctype html>
<meta charset="utf-8">
<title>Forgot Password</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#f7f7f9}
  .wrap{max-width:420px;margin:8vh auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.04);padding:20px}
  h1{margin:0 0 10px;font-size:1.4rem}
  label{display:block;margin:.5rem 0 .25rem}
  input[type=email],input[type=text]{width:100%;padding:.5rem .6rem;border:1px solid #cbd5e1;border-radius:6px}
  .btn{margin-top:.6rem; padding:.5rem .7rem;border:1px solid #6366f1;background:#fff;border-radius:6px;cursor:pointer}
  .muted{color:#555}
  .msg{margin:.6rem 0;padding:.5rem .6rem;border:1px solid #c8e6c9;background:#e8f5e9;border-radius:8px}
  .err{margin:.6rem 0;padding:.5rem .6rem;border:1px solid #ef9a9a;background:#ffebee;border-radius:8px}
  .dev{margin:.6rem 0;padding:.5rem .6rem;border:1px dashed #94a3b8;background:#f1f5f9;border-radius:8px;word-break:break-all}
  a{color:#2563eb;text-decoration:none}
</style>
<div class="wrap">
  <h1>Forgot Password</h1>

  <?php if ($sent): ?>
    <div class="msg">If an account exists for that email, a reset link has been sent.</div>
    <?php if ($devLink): ?>
      <div class="dev">
        <div class="muted">Dev shortcut (SMTP likely off):</div>
        <div><a href="<?= htmlspecialchars($devLink) ?>"><?= htmlspecialchars($devLink) ?></a></div>
      </div>
    <?php endif; ?>
    <p class="muted"><a href="login.php">Return to login</a></p>
  <?php else: ?>
    <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
      <label for="email">Email</label>
      <input required type="email" id="email" name="email" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      <button class="btn" type="submit">Send reset link</button>
    </form>
    <p class="muted" style="margin-top:.8rem"><a href="login.php">Back to login</a></p>
  <?php endif; ?>
</div>
