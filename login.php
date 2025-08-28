<?php
// login.php — subfolder-aware login with optional captcha + account locks
require_once __DIR__ . '/includes/bootstrap.php';      // for BASE_URL/u()/session
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/user-auth.php';       // ua_* + user_login()
require_once __DIR__ . '/includes/account-locks.php';   // al_is_locked()/al_db() (if present)
require_once __DIR__ . '/includes/recaptcha.php';       // recaptcha_enabled()/recaptcha_verify()
require_once __DIR__ . '/includes/security-ip.php';     // security_ip_enforce_or_deny()

security_ip_enforce_or_deny($_POST['email'] ?? null);

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

// Next target (default to Profile in Options)
$nextDefault = u('options/profile.php');
$nextRaw     = $_GET['next'] ?? $_POST['next'] ?? $nextDefault;
$next        = $nextRaw !== '' ? ua_sanitize_next((string)$nextRaw) : $nextDefault;

$ip       = __log_ip();
$preEmail = isset($_POST['email']) ? (string) $_POST['email'] : '';
$needCaptchaGet = isset($_GET['captcha']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = (string) ($_POST['email'] ?? '');
  $pass  = (string) ($_POST['password'] ?? '');

  // Admin lock check (if module present)
  if ($error === '' && function_exists('al_is_locked')) {
    [$isLocked, $untilAt, $why] = al_is_locked(al_db(), $email, 'user');
    if ($isLocked) {
      if (function_exists('log_login_attempt')) log_login_attempt(false, 'account_locked', $email);
      $msgWhy = $why !== '' ? " — reason: {$why}" : '';
      $error  = "Account locked until {$untilAt}{$msgWhy}";
    }
  }

  // Captcha challenge when needed
  $showCaptcha = function_exists('recaptcha_enabled') && recaptcha_enabled()
                 && ($needCaptchaGet || (!empty($email) && function_exists('ua_should_captcha') && ua_should_captcha(ua_db(), $ip, $email)));
  if ($error === '' && $showCaptcha) {
    if (!function_exists('recaptcha_verify') || !recaptcha_verify()) {
      if (function_exists('log_login_attempt')) log_login_attempt(false, 'captcha_required', $email);
      $error = 'Please complete the captcha.';
    }
  }

  // Attempt login
  if ($error === '') {
    $msg = '';
    $ok  = user_login($email, $pass, $msg);
    if ($ok) {
      if (function_exists('log_login_attempt')) log_login_attempt(true, 'ok', $email);
      header('Location: ' . $next);
      exit;
    } else {
      $error = $msg ?: 'Login failed.';
      if (function_exists('log_login_attempt')) log_login_attempt(false, 'bad_credentials', $email);
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sign in</title>
  <?php if (function_exists('recaptcha_enabled') && recaptcha_enabled()): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <?php endif; ?>
</head>
<body style="font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#e6eef8; background:#202428;">
<div style="max-width:420px;margin:40px auto;padding:20px;border-radius:16px;background:#0d1117;border:1px solid #ffffff1a;box-shadow:inset 0 1px 0 #ffffff12;">
  <h1 style="margin:0 0 8px;">Sign in</h1>

  <?php if ($error !== ''): ?>
    <div style="margin:.5rem 0;padding:.5rem .75rem;border:1px solid #fecaca;background:#fff1f2;border-radius:8px;color:#8a2c2c;">
      <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <form method="post" style="display:grid;gap:.6rem;">
    <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">

    <label>Email<br>
      <input type="email" name="email" value="<?= htmlspecialchars($preEmail, ENT_QUOTES, 'UTF-8') ?>" required autofocus
             style="width:100%;padding:8px;border-radius:8px;border:1px solid #2f3f53;background:#0f1621;color:#e6eef8;">
    </label>

    <label>Password<br>
      <input type="password" name="password" required
             style="width:100%;padding:8px;border-radius:8px;border:1px solid #2f3f53;background:#0f1621;color:#e6eef8;">
    </label>

    <?php
      $showCaptcha = function_exists('recaptcha_enabled') && recaptcha_enabled()
                     && ($needCaptchaGet || (!empty($preEmail) && function_exists('ua_should_captcha') && ua_should_captcha(ua_db(), $ip, $preEmail)));
      if ($showCaptcha):
    ?>
      <div style="margin-top:.25rem;">
<div class="g-recaptcha" data-sitekey="<?= htmlspecialchars(recaptcha_site_key(), ENT_QUOTES, 'UTF-8') ?>">

      </div>
    <?php endif; ?>

    <div style="margin-top:.25rem;display:flex;gap:.5rem;align-items:center;">
      <button type="submit" style="padding:8px 12px;border-radius:10px;border:1px solid #3d6ea8;background:#1b2431;color:#e6eef8;">Sign in</button>
      <a href="<?= htmlspecialchars(u('forgot.php'), ENT_QUOTES, 'UTF-8') ?>" style="color:#9cc4ff;">Forgot your password?</a>
    </div>
  </form>
</div>
</body>
</html>
