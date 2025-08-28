<?php
// admin/license-issue.php — create/license keys for the portal
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';

require_admin();

// ---- DB handle
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('Database handle not initialized.'); }

// must be licensed & permitted
require_license($dbc);
require_perm('manage_licenses');

// ---- ensure licenses table has what we need
$dbc->query("CREATE TABLE IF NOT EXISTS licenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  license_key VARCHAR(64) NOT NULL UNIQUE,
  status VARCHAR(20) NOT NULL DEFAULT 'issued',      -- issued|active|revoked|expired
  type VARCHAR(20) NOT NULL DEFAULT 'full',          -- full|demo
  issued_to VARCHAR(190) NULL,                       -- name or email
  site_domain VARCHAR(190) NULL,
  expires_at DATETIME NULL,
  activated_at DATETIME NULL,
  notes TEXT NULL,
  created_by VARCHAR(128) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

@ $dbc->query("ALTER TABLE licenses ADD COLUMN IF NOT EXISTS license_key VARCHAR(64) NOT NULL UNIQUE");
@ $dbc->query("ALTER TABLE licenses ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'issued'");
@ $dbc->query("ALTER TABLE licenses ADD COLUMN IF NOT EXISTS type VARCHAR(20) NOT NULL DEFAULT 'full'");
@ $dbc->query("ALTER TABLE licenses ADD COLUMN IF NOT EXISTS issued_to VARCHAR(190) NULL");
@ $dbc->query("ALTER TABLE licenses ADD COLUMN IF NOT EXISTS site_domain VARCHAR(190) NULL");
@ $dbc->query("ALTER TABLE licenses ADD COLUMN IF NOT EXISTS expires_at DATETIME NULL");
@ $dbc->query("ALTER TABLE licenses ADD COLUMN IF NOT EXISTS activated_at DATETIME NULL");
@ $dbc->query("ALTER TABLE licenses ADD COLUMN IF NOT EXISTS notes TEXT NULL");
@ $dbc->query("ALTER TABLE licenses ADD COLUMN IF NOT EXISTS created_by VARCHAR(128) NULL");
@ $dbc->query("ALTER TABLE licenses ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL");

// ---- helpers
function li_rand_key(int $groups = 4, int $len = 5): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no I,O,0,1
    $out = [];
    for ($g = 0; $g < $groups; $g++) {
        $seg = '';
        for ($i = 0; $i < $len; $i++) {
            $seg .= $alphabet[random_int(0, strlen($alphabet)-1)];
        }
        $out[] = $seg;
    }
    return implode('-', $out);
}

// CSRF
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

// defaults
$now = new DateTimeImmutable('now');
$defaultDemoExpire = $now->modify('+14 days')->format('Y-m-d');
$defaultFullExpire = $now->modify('+365 days')->format('Y-m-d');

$error = '';
$done  = false;
$newKey = null;

// handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('Bad CSRF token.');
    }

    $type       = ($_POST['type'] ?? 'full') === 'demo' ? 'demo' : 'full';
    $issuedTo   = trim((string)($_POST['issued_to'] ?? ''));
    $domain     = trim((string)($_POST['site_domain'] ?? ''));
    $expiryIn   = trim((string)($_POST['expires_at'] ?? '')); // YYYY-MM-DD
    $activate   = !empty($_POST['activate_now']) ? 1 : 0;
    $notes      = trim((string)($_POST['notes'] ?? ''));
    $customKey  = strtoupper(trim((string)($_POST['license_key'] ?? '')));

    if ($expiryIn === '') {
        // sensible defaults if user leaves it blank
        $expiryIn = ($type === 'demo') ? $defaultDemoExpire : $defaultFullExpire;
    }

    // basic checks
    $expiryAt = null;
    if ($expiryIn !== '') {
        // Ensure valid date format
        $dt = DateTime::createFromFormat('Y-m-d', $expiryIn);
        if (!$dt) { $error = 'Expiry date is invalid.'; }
        else { $expiryAt = $dt->format('Y-m-d') . ' 23:59:59'; }
    }

    // generate/normalize key
    $key = $customKey !== '' ? preg_replace('/[^A-Z0-9-]/', '', $customKey) : li_rand_key();
    if (strlen($key) < 10) $error = 'License key looks too short.';

    if ($error === '') {
        // insert; retry key collision a couple of times
        $createdBy = $_SESSION['admin_name'] ?? 'admin';
        $status    = $activate ? 'active' : 'issued';
        $activatedAt = $activate ? $now->format('Y-m-d H:i:s') : null;
        $updatedAt = $now->format('Y-m-d H:i:s');

        $attempts = 0; $ok = false; $lastErr = '';
        while ($attempts < 3 && !$ok) {
            $attempts++;
            $kTry = ($attempts === 1) ? $key : li_rand_key();
            if ($stmt = $dbc->prepare("INSERT INTO licenses
                (license_key, status, type, issued_to, site_domain, expires_at, activated_at, notes, created_by, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")) {
                $stmt->bind_param('ssssssssss',
                    $kTry, $status, $type, $issuedTo, $domain, $expiryAt, $activatedAt, $notes, $createdBy, $updatedAt
                );
                $ok = $stmt->execute();
                $stmt->close();

                if ($ok) {
                    $newKey = $kTry;
                    log_audit('license_issued', "key={$kTry}; type={$type}; status={$status}; to=".log_mask($issuedTo), 'admin');
                } else {
                    $lastErr = $dbc->error;
                    if ($dbc->errno !== 1062) break; // not a duplicate-key issue
                }
            } else {
                $lastErr = 'DB error preparing insert.';
                break;
            }
        }

        if ($ok) {
            $done = true;
        } else {
            $error = 'Insert failed. ' . ($lastErr ?: '');
        }
    }
}

// ---- header loader
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/header-admin.php', __DIR__.'/header.php', __DIR__.'/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) {
  echo "<!doctype html><html><head><meta charset='utf-8'><title>Issue License</title>
  <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}</style>
  </head><body>";
}
?>
<h1>Issue License</h1>

<style>
.form-row{margin:.5rem 0}
label{display:block;margin-bottom:.25rem}
input[type=text],input[type=date],textarea,select{width:100%;max-width:560px}
.note{font-size:.9rem;color:#555}
.box{border:1px solid #ddd;border-radius:8px;padding:12px;background:#fff;max-width:760px}
.key{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:1.25rem;letter-spacing:.05em}
.badge{padding:.15rem .4rem;border-radius:.3rem;border:1px solid #ccc;font-size:.85rem}
.badge.ok{background:#e8fff0;border-color:#9bd3af}
</style>

<?php if ($done): ?>
  <div class="box">
    <h3>✅ License created</h3>
    <p class="note">Give this key to the user or activate on the target site.</p>
    <div class="key" style="margin:.5rem 0 1rem;"><strong><?= htmlspecialchars($newKey) ?></strong></div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
      <a class="badge ok" href="licenses.php">View all licenses</a>
      <a class="badge" href="license-issue.php">Issue another</a>
    </div>
  </div>
<?php else: ?>
  <?php if ($error): ?><div style="color:#b00; margin:.5rem 0;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="post" class="box">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-row">
      <label>Type</label>
      <select name="type" id="type">
        <option value="full" <?= (($_POST['type'] ?? '')!=='demo'?'selected':'') ?>>Full</option>
        <option value="demo" <?= (($_POST['type'] ?? '')==='demo'?'selected':'') ?>>Demo</option>
      </select>
      <div class="note">Full defaults to ~1 year, Demo defaults to ~14 days (you can override below).</div>
    </div>

    <div class="form-row">
      <label>Expires on</label>
      <?php
        $pref = $_POST['expires_at'] ?? '';
        $def = (($_POST['type'] ?? '')==='demo') ? $defaultDemoExpire : $defaultFullExpire;
      ?>
      <input type="date" name="expires_at" value="<?= htmlspecialchars($pref !== '' ? $pref : $def) ?>">
    </div>

    <div class="form-row">
      <label>Issued to (name or email)</label>
      <input type="text" name="issued_to" value="<?= htmlspecialchars((string)($_POST['issued_to'] ?? '')) ?>" placeholder="e.g., Jane Doe or jane@example.com">
    </div>

    <div class="form-row">
      <label>Site domain (optional)</label>
      <input type="text" name="site_domain" value="<?= htmlspecialchars((string)($_POST['site_domain'] ?? '')) ?>" placeholder="e.g., league.example.com or localhost">
    </div>

    <div class="form-row">
      <label>Notes (optional)</label>
      <textarea name="notes" rows="4" placeholder="Any internal notes for this license"><?= htmlspecialchars((string)($_POST['notes'] ?? '')) ?></textarea>
    </div>

    <div class="form-row" style="margin-top:.25rem;">
      <label><input type="checkbox" name="activate_now" value="1" <?= !empty($_POST['activate_now'])?'checked':''; ?>> Activate immediately</label>
      <div class="note">If checked, status = <strong>active</strong>; otherwise <strong>issued</strong> (to be activated later).</div>
    </div>

    <details style="margin:.5rem 0;">
      <summary>Advanced: set a custom key (optional)</summary>
      <div class="note">Uppercase letters & digits only; dashes allowed. Leave blank to auto-generate.</div>
      <input type="text" name="license_key" value="<?= htmlspecialchars((string)($_POST['license_key'] ?? '')) ?>" placeholder="e.g., ABCDE-23456-FGHIJ-789KL">
    </details>

    <div class="form-row" style="margin-top:.6rem;">
      <button type="submit">Create License</button>
      <a href="licenses.php" style="margin-left:.5rem;">Cancel</a>
    </div>
  </form>
<?php endif; ?>

<?php if (!$loadedHeader) { echo "</body></html>"; } ?>
