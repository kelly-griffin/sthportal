<?php
// admin/license-reactivate.php — reactivate (set to active) if not expired
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

// ensure table
$dbc->query("CREATE TABLE IF NOT EXISTS licenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  license_key VARCHAR(64) NOT NULL UNIQUE,
  status VARCHAR(20) NOT NULL DEFAULT 'issued',
  type VARCHAR(20) NOT NULL DEFAULT 'full',
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

// id
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

// determine if expired
$isExpired = (!empty($lic['expires_at']) && strtotime($lic['expires_at']) < time());

// on submit
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400); exit('Bad CSRF token.');
  }

  if ($isExpired) {
    $error = 'This license is expired. Issue a new license or extend the expiry first.';
  } else {
    if ($stmt = $dbc->prepare("UPDATE licenses SET status='active', activated_at=NOW(), updated_at=NOW() WHERE id=?")) {
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();
    }
    log_audit('license_reactivated', "id={$id}; key=".log_mask($lic['license_key'])."; prev_status={$lic['status']}", 'admin');
    header('Location: licenses.php'); exit;
  }
}

// header
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/header-admin.php', __DIR__.'/header.php', __DIR__.'/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) {
  echo "<!doctype html><html><head><meta charset='utf-8'><title>Reactivate License</title></head><body>";
}
?>
<h1>Reactivate License</h1>

<?php if ($error): ?>
  <div style="color:#b00; margin:.5rem 0;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<p>Set this license to <strong>active</strong> (only possible if it is not expired).</p>
<ul>
  <li><strong>Key:</strong> <?= htmlspecialchars($lic['license_key']) ?></li>
  <li><strong>Status:</strong> <?= htmlspecialchars($lic['status']) ?></li>
  <li><strong>Type:</strong> <?= htmlspecialchars($lic['type']) ?></li>
  <li><strong>Expires:</strong> <?= htmlspecialchars((string)$lic['expires_at']) ?></li>
</ul>

<form method="post" style="margin-top:.5rem;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
  <button type="submit" <?= $isExpired ? 'disabled title="Expired: cannot reactivate"' : '' ?>>Set Active</button>
  <a href="licenses.php" style="margin-left:.5rem;">Cancel</a>
</form>

<?php if (!$loadedHeader) { echo "</body></html>"; } ?>
