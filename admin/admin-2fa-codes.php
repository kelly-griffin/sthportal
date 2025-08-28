<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-guard.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/totp.php';
require_once __DIR__ . '/../includes/admin-2fa-codes.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_admin();
if (function_exists('session_guard_boot')) session_guard_boot();

$db = __log_db();
a2bc_ensure($db);

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

$msg = ''; $codesToShow = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Bad CSRF'); }
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'gen') {
    $codesToShow = a2bc_generate($db, 10);
    $msg = 'Backup codes generated. Copy them now — they will not be shown again.';
    log_audit('admin_2fa_backup_generated', [], 'admin');
  } elseif ($action === 'regen') {
    $codesToShow = a2bc_generate($db, 10);
    $msg = 'Backup codes regenerated. All previous codes are now invalid.';
    log_audit('admin_2fa_backup_regenerated', [], 'admin');
  } elseif ($action === 'forget') {
    if (function_exists('admin_trust_clear')) admin_trust_clear(); // clear cookie
    $msg = 'This browser has been “forgotten”. You will be asked for 2FA next time.';
  }
}

// ---- UI ----
$loadedHeader = false;
foreach ([__DIR__ . '/admin-header.php', __DIR__ . '/header-admin.php', __DIR__ . '/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) echo "<!doctype html><meta charset='utf-8'><title>Admin 2FA Codes</title><body style='font-family:system-ui,Segoe UI,Roboto,Arial'>";
$enabled = function_exists('admin_totp_enabled') ? admin_totp_enabled() : true;
$remain = a2bc_remaining($db);
?>
<style>
  .wrap{max-width:760px;margin:16px auto;padding:16px}
  .panel{border:1px solid #e5e7eb;background:#f9fafb;border-radius:10px;padding:14px;margin-top:.6rem}
  .msg{border:1px solid #c7d2fe;background:#eef2ff;border-radius:8px;padding:.6rem .8rem;margin:.5rem 0}
  .codes{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin-top:.6rem}
  .code{font-family:ui-monospace,Menlo,Consolas,monospace;border:1px solid #ddd;border-radius:8px;padding:.4rem .6rem;background:#fff;text-align:center}
  .btn{padding:.45rem .9rem;border:1px solid #bbb;background:#f7f7f7;border-radius:8px;cursor:pointer}
  .muted{color:#555}
</style>
<div class="wrap">
  <h1>Admin 2FA — Backup Codes</h1>
  <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="panel">
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
      <?php if ($remain === 0): ?>
        <input type="hidden" name="action" value="gen">
        <button class="btn" type="submit"<?= $enabled ? '' : ' disabled' ?>>Generate 10 backup codes</button>
        <span class="muted"><?= $enabled ? '' : '(Enable 2FA first)' ?></span>
      <?php else: ?>
        <input type="hidden" name="action" value="regen">
        <button class="btn" type="submit">Regenerate backup codes</button>
        <span class="muted">(Existing unused codes will be invalidated)</span>
      <?php endif; ?>
    </form>

    <form method="post" style="margin-top:.5rem">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" value="forget">
      <button class="btn" type="submit">Forget this browser</button>
      <span class="muted">(clears trusted-device cookie)</span>
    </form>

    <p class="muted" style="margin-top:.6rem">Unused codes remaining: <strong><?= (int)$remain ?></strong></p>

    <?php if (!empty($codesToShow)): ?>
      <h3 style="margin-top:.8rem">Copy these backup codes now</h3>
      <div class="codes">
        <?php foreach ($codesToShow as $c): ?>
          <div class="code"><?= htmlspecialchars($c) ?></div>
        <?php endforeach; ?>
      </div>
      <p class="muted">Each code works once. Store them in a safe place (password manager, printed copy, etc.).</p>
    <?php endif; ?>
  </div>
</div>
<?php if (!$loadedHeader) echo "</body></html>"; ?>
