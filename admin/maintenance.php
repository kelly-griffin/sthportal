<?php
// admin/maintenance.php — toggle Maintenance Mode (JSON flag + message + IP allowlist)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/maintenance.php';

require_admin();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

$me = $_SESSION['user']['email'] ?? 'admin';
$nowIp = maint_client_ip();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Bad CSRF'); }

    $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';
    $bypass  = isset($_POST['allow_admin_bypass']) && $_POST['allow_admin_bypass'] === '1';
    $message = trim((string)($_POST['message'] ?? ''));
    $ipsRaw  = trim((string)($_POST['allow_ips'] ?? ''));

    $ips = [];
    if ($ipsRaw !== '') {
        $ips = preg_split('/[\s,]+/', $ipsRaw);
        $ips = array_values(array_unique(array_filter(array_map('maint_ip_norm', array_map('strval', $ips)))));
    }

    $state = maintenance_state();
    $state['enabled'] = $enabled;
    $state['allow_admin_bypass'] = $bypass;
    $state['message'] = $message !== '' ? $message : $state['message'];
    $state['allow_ips'] = $ips;
    $state['updated_by'] = $me;

    $ok = maintenance_save($state);
    @log_audit('maintenance_update', [
        'enabled'=>$enabled,
        'allow_admin_bypass'=>$bypass,
        'allow_ips'=>$ips,
        'updated_by'=>$me
    ], $me, $nowIp);

    header('Location: maintenance.php?saved='.($ok?'1':'0'));
    exit;
}

// Load current
$state = maintenance_state();

// Header include
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/header-admin.php', __DIR__.'/header.php', __DIR__.'/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) echo "<!doctype html><meta charset='utf-8'><title>Maintenance Mode</title><body style='font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial; margin:16px'>";
?>
<h1>Maintenance Mode</h1>

<?php if (isset($_GET['saved'])): ?>
  <?php if ($_GET['saved'] === '1'): ?>
    <div style="border:1px solid #c8e6c9;background:#e8f5e9;border-radius:8px;padding:8px 10px;margin:.6rem 0">
      Saved. Non-admin visitors will now see the maintenance page (unless whitelisted).
    </div>
  <?php else: ?>
    <div style="border:1px solid #ffcdd2;background:#ffebee;border-radius:8px;padding:8px 10px;margin:.6rem 0">
      Could not write the settings file. Check permissions on <code><?= htmlspecialchars(MAINTENANCE_JSON_PATH) ?></code>.
    </div>
  <?php endif; ?>
<?php endif; ?>

<style>
.form{display:grid;grid-template-columns:1fr;gap:10px;max-width:760px}
.field{border:1px solid #ddd;border-radius:8px;padding:10px;background:#fff}
.field label{display:block;font-weight:600;margin-bottom:.25rem}
.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.badge{display:inline-block;padding:.15rem .5rem;border:1px solid #ccc;border-radius:999px;background:#f6f6f6}
textarea,input[type=text]{width:100%;padding:.45rem .6rem;border:1px solid #ccc;border-radius:6px}
.switch{display:flex;align-items:center;gap:.5rem}
.btn{padding:.45rem .8rem;border:1px solid #ccc;border-radius:6px;background:#fff;cursor:pointer}
.btn.primary{border-color:#6366f1}
</style>

<form method="post" class="form">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
  <div class="field switch">
    <input type="checkbox" id="enabled" name="enabled" value="1" <?= $state['enabled'] ? 'checked' : '' ?>>
    <label for="enabled">Enable maintenance mode</label>
    <span class="badge">Current: <?= $state['enabled'] ? 'ON' : 'OFF' ?></span>
  </div>

  <div class="field switch">
    <input type="checkbox" id="allow_admin_bypass" name="allow_admin_bypass" value="1" <?= !empty($state['allow_admin_bypass']) ? 'checked' : '' ?>>
    <label for="allow_admin_bypass">Allow admins to bypass</label>
  </div>

  <div class="field">
    <label for="message">Visitor message</label>
    <textarea id="message" name="message" rows="3" placeholder="We’re upgrading the site and will be back shortly."><?= htmlspecialchars((string)$state['message']) ?></textarea>
  </div>

  <div class="field">
    <label for="allow_ips">Allowlisted IPs (comma or newline separated)</label>
    <textarea id="allow_ips" name="allow_ips" rows="3" placeholder="127.0.0.1&#10;203.0.113.5"><?= htmlspecialchars(implode("\n", (array)$state['allow_ips'])) ?></textarea>
    <div class="row" style="margin-top:.35rem">
      <span class="badge">Your IP: <?= htmlspecialchars($nowIp) ?></span>
      <button type="button" class="btn" onclick="addMyIp()">Add my IP</button>
      <script>
        function addMyIp(){
          var ta=document.getElementById('allow_ips'); if(!ta) return;
          var ip="<?= htmlspecialchars($nowIp, ENT_QUOTES) ?>";
          var cur=ta.value.trim();
          var list=(cur?cur.split(/\s*,\s*|\s+/):[]);
          if(list.indexOf(ip)===-1){ list.push(ip); }
          ta.value=list.join("\n");
        }
      </script>
    </div>
  </div>

  <div class="row">
    <button type="submit" class="btn primary">Save</button>
    <a href="index.php" class="btn" style="text-decoration:none;color:#333">Back to Admin</a>
    <span class="badge">Settings file: <?= htmlspecialchars(MAINTENANCE_JSON_PATH) ?></span>
  </div>
</form>

<?php if (!$loadedHeader) echo "</body></html>"; ?>
