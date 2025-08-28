<?php
// admin/license-extend.php — extend/renew a license (resubscriptions)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';

require_admin();
require_perm('manage_licenses');

// ---- DB handle
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('Database handle not initialized.'); }

// ensure table (same shape used across license pages)
$dbc->query("CREATE TABLE IF NOT EXISTS licenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  license_key VARCHAR(64) NOT NULL UNIQUE,
  status VARCHAR(20) NOT NULL DEFAULT 'issued',      -- issued|active|revoked|expired
  type VARCHAR(20) NOT NULL DEFAULT 'full',          -- full|demo
  issued_to VARCHAR(190) NULL,
  site_domain VARCHAR(190) NULL,
  expires_at DATETIME NULL,
  activated_at DATETIME NULL,
  notes TEXT NULL,
  created_by VARCHAR(128) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// CSRF
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

// license id
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('Missing id.'); }

// load license
$stmt = $dbc->prepare("SELECT id, license_key, status, type, issued_to, site_domain, expires_at, activated_at FROM licenses WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$lic = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$lic) { http_response_code(404); exit('License not found.'); }

$now = new DateTimeImmutable('now');
$currentExpires = !empty($lic['expires_at']) ? new DateTimeImmutable($lic['expires_at']) : null;
$isExpired = $currentExpires ? ($currentExpires->getTimestamp() < time()) : false;

$error = '';
$done  = false;
$newExpStr = '';

// handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('Bad CSRF token.');
    }

    $months     = (int)($_POST['months'] ?? 0);           // extend by N months
    $exactDate  = trim((string)($_POST['exact_date'] ?? '')); // YYYY-MM-DD
    $setActive  = !empty($_POST['set_active']) ? 1 : 0;

    if ($exactDate === '' && $months <= 0) {
        $error = 'Choose months to extend or set an exact date.';
    }

    $targetExp = null;

    if ($error === '' && $exactDate !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $exactDate);
        if (!$dt) { $error = 'Exact date is invalid.'; }
        else {
            // end of selected day
            $targetExp = (new DateTimeImmutable($dt->format('Y-m-d')))->setTime(23, 59, 59);
        }
    }

    if ($error === '' && $targetExp === null) {
        // extend by months from the later of (now, current expiry)
        $base = $currentExpires && $currentExpires->getTimestamp() > time() ? $currentExpires : $now;
        try {
            $targetExp = $base->modify('+' . $months . ' months')->setTime(23, 59, 59);
        } catch (Throwable $e) {
            $error = 'Could not compute new expiry.';
        }
    }

    if ($error === '' && $targetExp !== null) {
        $newExpStr = $targetExp->format('Y-m-d H:i:s');

        // Build update; optionally set active if new expiry is in the future
        $canActivate = ($targetExp->getTimestamp() > time());
        $doActivate  = $setActive && $canActivate;

        if ($doActivate) {
            // set active, stamp activated_at (if null), bump expires_at, updated_at
            if ($stmt = $dbc->prepare("UPDATE licenses
                                          SET status='active',
                                              expires_at=?,
                                              activated_at=IF(activated_at IS NULL, NOW(), activated_at),
                                              updated_at=NOW()
                                        WHERE id=?")) {
                $stmt->bind_param('si', $newExpStr, $id);
                $stmt->execute(); $stmt->close();
            } else { $error = 'DB error preparing update.'; }
        } else {
            if ($stmt = $dbc->prepare("UPDATE licenses
                                          SET expires_at=?, updated_at=NOW()
                                        WHERE id=?")) {
                $stmt->bind_param('si', $newExpStr, $id);
                $stmt->execute(); $stmt->close();
            } else { $error = 'DB error preparing update.'; }
        }

        if ($error === '') {
            $oldExp = $currentExpires ? $currentExpires->format('Y-m-d H:i:s') : '(none)';
            $mode = ($exactDate !== '') ? ('set:' . $exactDate) : ('add_months:' . $months);
            $flag = $doActivate ? 'activated' : 'no-activate';
            log_audit('license_extended', "id={$id}; key=".log_mask($lic['license_key'])."; {$mode}; old_exp={$oldExp}; new_exp={$newExpStr}; {$flag}", 'admin');
            $done = true;
        }
    }
}

// header
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/header-admin.php', __DIR__.'/header.php', __DIR__.'/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) {
  echo "<!doctype html><html><head><meta charset='utf-8'><title>Extend License</title>
  <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}</style>
  </head><body>";
}
?>
<h1>Extend License</h1>

<style>
.box{border:1px solid #ddd;border-radius:8px;padding:12px;background:#fff;max-width:760px}
.form-row{margin:.5rem 0}
label{display:block;margin-bottom:.25rem}
input[type=number]{width:120px}
input[type=date],input[type=text],textarea,select{max-width:560px}
.note{font-size:.9rem;color:#555}
.key{font-family:ui-monospace,Menlo,Consolas,monospace}
.badge{padding:.15rem .4rem;border-radius:.3rem;border:1px solid #ccc;font-size:.85rem}
.badge.ok{background:#e8fff0;border-color:#9bd3af}
</style>

<?php if ($done): ?>
  <div class="box">
    <h3>✅ License extended</h3>
    <p><strong>Key:</strong> <span class="key"><?= htmlspecialchars($lic['license_key']) ?></span></p>
    <p><strong>New expiry:</strong> <?= htmlspecialchars($newExpStr) ?></p>
    <p style="margin-top:.6rem;">
      <a class="badge ok" href="licenses.php">Back to Licenses</a>
    </p>
  </div>
<?php else: ?>
  <?php if ($error): ?><div style="color:#b00; margin:.5rem 0;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="box">
    <p><strong>Key:</strong> <span class="key"><?= htmlspecialchars($lic['license_key']) ?></span></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($lic['status']) ?>
      <?php if ($isExpired): ?><span class="note">(currently expired)</span><?php endif; ?>
    </p>
    <p><strong>Current expiry:</strong>
      <?= htmlspecialchars($currentExpires ? $currentExpires->format('Y-m-d H:i:s') : '(none)') ?>
    </p>

    <form method="post" style="margin-top:.5rem;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

      <div class="form-row">
        <label>Extend by (months)</label>
        <input type="number" min="1" step="1" name="months" value="<?= htmlspecialchars((string)($_POST['months'] ?? '12')) ?>">
        <div class="note">Adds to the later of now or the current expiry. Defaults to 12 months.</div>
      </div>

      <div class="form-row">
        <label>— or — Set exact expiry date</label>
        <input type="date" name="exact_date" value="<?= htmlspecialchars((string)($_POST['exact_date'] ?? '')) ?>">
        <div class="note">If set, this exact date (end of day) will be used instead of “extend by months”.</div>
      </div>

      <div class="form-row" style="margin-top:.25rem;">
        <label><input type="checkbox" name="set_active" value="1" <?= (!empty($_POST['set_active']) || $isExpired) ? 'checked' : '' ?>>
          Set status to <strong>active</strong> after extending (if new expiry is in the future)
        </label>
      </div>

      <div class="form-row" style="margin-top:.6rem;">
        <button type="submit">Save Extension</button>
        <a href="licenses.php" style="margin-left:.5rem;">Cancel</a>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php if (!$loadedHeader) { echo "</body></html>"; } ?>
