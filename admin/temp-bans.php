<?php
// admin/temp-bans.php — Manage temporary IP bans (add + search + unban + purge expired) + Server-side IP validation

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/session-guard.php';
require_admin();
if (function_exists('session_guard_boot')) session_guard_boot();

// ---- DB handle ----
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('Database handle not initialized.'); }

// ---- Ensure table ----
function ensure_ip_temp_bans(mysqli $db): void {
  $db->query("CREATE TABLE IF NOT EXISTS security_ip_temp_bans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(64) NOT NULL,
    until_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ip (ip)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
ensure_ip_temp_bans($dbc);

// ---- CSRF ----
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

// ---- Helpers ----
function fmt_ip(?string $ip): string {
  $ip = trim((string)$ip);
  if ($ip === '' || strtolower($ip) === 'unknown') return '';
  if ($ip === '::1') return '127.0.0.1';
  if (stripos($ip, '::ffff:') === 0) return substr($ip, 7);
  return $ip;
}
/** Strictly normalize a user-provided IP input. Returns '' if invalid. */
function normalize_ip_input(?string $raw): string {
  $ip = trim((string)$raw);
  if ($ip === '') return '';
  if (strpos($ip, ',') !== false) $ip = trim(strtok($ip, ',')); // first token
  if (preg_match('/^\\[(.*)\\]$/', $ip, $m)) $ip = $m[1];       // strip [ ]
  if (strpos($ip, '%') !== false) return '';                    // reject zone-id
  if (stripos($ip, '::ffff:') === 0) {
    $v4 = substr($ip, 7);
    if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $v4;
    return '';
  }
  if ($ip === '::1') return '127.0.0.1';
  return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}
function humanize_left(int $secs): string {
  if ($secs <= 0) return '0m';
  $d = intdiv($secs, 86400); $secs%=86400;
  $h = intdiv($secs, 3600);  $secs%=3600;
  $m = intdiv($secs, 60);
  if ($d > 0) return "{$d}d {$h}h";
  if ($h > 0) return "{$h}h {$m}m";
  return "{$m}m";
}
function build_qs(array $over = []): string {
  $p = $_GET;
  foreach ($over as $k=>$v) { if ($v === null) unset($p[$k]); else $p[$k] = $v; }
  return '?' . http_build_query($p);
}
function sort_link(string $key, string $label, string $cur, string $dir): string {
  $is = ($cur === $key);
  $ndir = $is && $dir === 'asc' ? 'desc' : 'asc';
  $arrow = $is ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
  $href = build_qs(['sort'=>$key,'dir'=>$ndir,'p'=>1]);
  return '<a href="'.htmlspecialchars($href).'">'.htmlspecialchars($label).$arrow.'</a>';
}
// set temp ban (hours clamped 1..8760)
function ip_temp_ban_set(mysqli $db, string $ip, int $hours): string {
  ensure_ip_temp_bans($db);
  $hours = max(1, min($hours, 24*365)); // 1..8760
  $until = (new DateTimeImmutable('now'))->modify("+{$hours} hour")->format('Y-m-d H:i:s');
  if ($stmt = $db->prepare("INSERT INTO security_ip_temp_bans (ip, until_at) VALUES (?, ?) ON DUPLICATE KEY UPDATE until_at=VALUES(until_at)")) {
    $stmt->bind_param('ss', $ip, $until); $stmt->execute(); $stmt->close();
  }
  return $until;
}

// ---- Inputs (GET) ----
$q       = trim((string)($_GET['q'] ?? ''));                 // search by IP
$active  = ($_GET['active'] ?? '1') === '1' ? '1' : '0';     // show active only by default
$sort    = (string)($_GET['sort'] ?? 'until_at');            // until_at | ip | created_at
$dir     = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = max(10, min(200, (int)($_GET['perPage'] ?? 50)));
$msg     = trim((string)($_GET['msg'] ?? ''));

// ---- POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Bad CSRF'); }
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'add') {
    $ipRaw = (string)($_POST['ip'] ?? '');
    $ip    = normalize_ip_input($ipRaw);
    $qty   = (int)($_POST['qty'] ?? 0);
    $unit  = (string)($_POST['unit'] ?? 'h'); // h|d
    if ($qty < 1) $qty = 1;
    if ($unit !== 'h' && $unit !== 'd') $unit = 'h';
    if ($ip === '') {
      header('Location: '.build_qs(['msg'=>'Enter a single, valid IPv4/IPv6 address (no lists, no zone suffix).'])); exit;
    }
    $hours = $unit === 'd' ? $qty * 24 : $qty;
    if ($hours > 24*365) $hours = 24*365;
    $until = ip_temp_ban_set($dbc, $ip, $hours);
    log_audit('ip_temp_ban_add', ['ip'=>$ip,'hours'=>$hours,'until'=>$until], 'admin');
    header('Location: '.build_qs(['msg'=>"Temp ban set for {$ip} until {$until}"])); exit;

  } elseif ($action === 'clear' || $action === 'clear_selected') {
    if ($action === 'clear') {
      $ip = normalize_ip_input((string)($_POST['ip'] ?? ''));
      if ($ip === '') { header('Location: '.build_qs(['msg'=>'Invalid IP for unban.'])); exit; }
      if ($stmt = $dbc->prepare("DELETE FROM security_ip_temp_bans WHERE ip=?")) {
        $stmt->bind_param('s', $ip); $stmt->execute(); $stmt->close();
        log_audit('ip_temp_ban_clear', ['ip'=>$ip,'src'=>'temp_bans_page'], 'admin');
      }
      header('Location: '.build_qs(['msg'=>"Unbanned {$ip}"])); exit;
    } else {
      $ips = isset($_POST['ips']) && is_array($_POST['ips']) ? array_map('normalize_ip_input', $_POST['ips']) : [];
      $ips = array_values(array_filter($ips, fn($x)=>$x!==''));
      if ($ips) {
        $place = implode(',', array_fill(0, count($ips), '?'));
        $types = str_repeat('s', count($ips));
        $stmt = $dbc->prepare("DELETE FROM security_ip_temp_bans WHERE ip IN ($place)");
        $stmt->bind_param($types, ...$ips); $stmt->execute(); $stmt->close();
        log_audit('ip_temp_ban_clear_bulk', ['count'=>count($ips),'src'=>'temp_bans_page'], 'admin');
        header('Location: '.build_qs(['msg'=>'Unbanned selected IPs'])); exit;
      } else {
        header('Location: '.build_qs(['msg'=>'No valid IPs selected'])); exit;
      }
    }

  } elseif ($action === 'purge_expired') {
    $dbc->query("DELETE FROM security_ip_temp_bans WHERE until_at <= NOW()");
    log_audit('ip_temp_ban_purge_expired', [], 'admin');
    header('Location: '.build_qs(['msg'=>'Expired temp bans purged'])); exit;
  }
}

// ---- Filtering ----
$where = '1';
$types = ''; $params = [];
if ($q !== '') { $where .= ' AND ip LIKE ?'; $params[] = '%'.$q.'%'; $types .= 's'; }
if ($active === '1') { $where .= ' AND until_at > NOW()'; }

// ---- Counts ----
$totalRows = 0;
$sqlCnt = "SELECT COUNT(*) FROM security_ip_temp_bans WHERE $where";
if ($types) { $stmt = $dbc->prepare($sqlCnt); $stmt->bind_param($types, ...$params); $stmt->execute(); $r=$stmt->get_result()->fetch_row(); $stmt->close(); $totalRows = (int)($r[0]??0); }
else        { $r = $dbc->query($sqlCnt)->fetch_row(); $totalRows = (int)($r[0]??0); }

// ---- Paging ----
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// ---- Query ----
$sql = "SELECT id, ip, until_at, created_at FROM security_ip_temp_bans
        WHERE $where
        ORDER BY " . ($sort === 'ip' ? 'INET6_ATON(ip)' : $sort) . " " . strtoupper($dir) . "
        LIMIT ? OFFSET ?";
if ($types) { $types2 = $types.'ii'; $params2 = array_merge($params, [$perPage, $offset]); $stmt = $dbc->prepare($sql); $stmt->bind_param($types2, ...$params2); $stmt->execute(); $res = $stmt->get_result(); }
else        { $stmt = $dbc->prepare($sql); $stmt->bind_param('ii', $perPage, $offset); $stmt->execute(); $res = $stmt->get_result(); }

// ---- Header include ----
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/header-admin.php', __DIR__.'/header.php', __DIR__.'/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) echo "<!doctype html><meta charset='utf-8'><title>Temp IP Bans</title><body style='font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial; margin:16px'>";

?>
<h1>Temporary IP Bans</h1>

<?php if ($msg !== ''): ?>
  <div style="margin:.5rem 0;padding:.5rem .75rem;border:1px solid #c7d2fe;background:#eef2ff;border-radius:8px;">
    <?= htmlspecialchars($msg) ?>
  </div>
<?php endif; ?>

<!-- Top toolbar: Add temp ban + Purge expired -->
<div style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;margin:.6rem 0;justify-content:space-between">
  <form method="post" class="filterbar">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
    <input type="hidden" name="action" value="add">
    <div>
      <label for="ip" style="font-size:.85rem;color:#555;display:block;margin-bottom:.15rem">IP address</label>
      <input id="ip" name="ip" type="text" placeholder="e.g. 203.0.113.42"
             style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px;min-width:220px" required>
    </div>
    <div>
      <label for="qty" style="font-size:.85rem;color:#555;display:block;margin-bottom:.15rem">Duration</label>
      <input id="qty" name="qty" type="number" min="1" max="8760" value="24"
             style="width:7ch;padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px;text-align:right" required>
    </div>
    <div>
      <label for="unit" style="font-size:.85rem;color:#555;display:block;margin-bottom:.15rem">&nbsp;</label>
      <select id="unit" name="unit" style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px;">
        <option value="h">hours</option>
        <option value="d">days</option>
      </select>
    </div>
    <div>
      <label style="font-size:.85rem;color:#555;display:block;margin-bottom:.15rem">&nbsp;</label>
      <button type="submit" style="padding:.4rem .8rem;border:1px solid #bbb;background:#f7f7f7;border-radius:8px;">Add temp ban</button>
    </div>
  </form>

  <form method="post" onsubmit="return confirm('Purge all expired rows now?');" style="display:inline;">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
    <input type="hidden" name="action" value="purge_expired">
    <button type="submit" style="padding:.4rem .8rem;border:1px solid #bbb;background:#f7f7f7;border-radius:8px;">Purge expired</button>
  </form>
</div>

<!-- Filters -->
<form method="get" class="filterbar">
  <div>
    <label for="q" style="font-size:.85rem;color:#555;display:block;margin-bottom:.15rem">Search IP</label>
    <input id="q" name="q" type="text" value="<?= htmlspecialchars($q) ?>" placeholder="e.g. 203.0.113"
           style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px;min-width:240px">
  </div>
  <div>
    <label for="active" style="font-size:.85rem;color:#555;display:block;margin-bottom:.15rem">Show</label>
    <select id="active" name="active" style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px;">
      <option value="1" <?= $active==='1'?'selected':'' ?>>Active only</option>
      <option value="0" <?= $active==='0'?'selected':'' ?>>All (incl. expired)</option>
    </select>
  </div>
  <div>
    <label for="perPage" style="font-size:.85rem;color:#555;display:block;margin-bottom:.15rem">Per page</label>
    <select id="perPage" name="perPage" style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px;">
      <?php foreach ([10,25,50,100,200] as $n): ?>
        <option value="<?= $n ?>" <?= $perPage===$n?'selected':'' ?>><?= $n ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div style="display:flex;gap:.5rem;align-items:center;">
    <button type="submit" style="padding:.35rem .6rem;border:1px solid #ccc;border-radius:6px;background:#fff;">Apply</button>
    <a href="temp-bans.php" style="padding:.35rem .6rem;border:1px solid #ccc;border-radius:6px;background:#fff;text-decoration:none;color:#333">Reset</a>
  </div>
</form>

<style>
  table{width:100%;border-collapse:collapse;margin:.6rem 0}
  th,td{padding:.5rem;border-bottom:1px solid #eee;vertical-align:top}
  th{text-align:left;background:#fafafa}
  .mono{font-family: ui-monospace, Menlo, Consolas, "Liberation Mono", monospace; font-size:.9rem}
  .nowrap{white-space:nowrap}
  .badge{display:inline-block;padding:.1rem .4rem;border:1px solid #ccc;border-radius:999px;font-size:.85rem}
  .badge.act{background:#eef2ff;border-color:#c7d2fe}
  .copybtn{padding:.15rem .45rem;border:1px solid #ccc;border-radius:5px;background:#fff;cursor:pointer}
  .copybtn:hover{background:#f7f7f7}
  form.inline{display:inline}
</style>

<?php
if ($totalRows === 0):
?>
  <div style="padding:10px;border:1px dashed #ccc;background:#fafafa">No matching temp bans found.</div>
<?php else: ?>
<form method="post" onsubmit="return confirm('Unban all selected IPs?');">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
  <input type="hidden" name="action" value="clear_selected">
  <table>
    <thead>
      <tr>
        <th><input type="checkbox" id="chkAll" onclick="document.querySelectorAll('.rowchk').forEach(c=>c.checked=this.checked)"></th>
        <th><?= sort_link('ip','IP',$sort,$dir) ?></th>
        <th><?= sort_link('until_at','Banned until',$sort,$dir) ?></th>
        <th><?= sort_link('created_at','Created',$sort,$dir) ?></th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($row = $res->fetch_assoc()): ?>
      <?php
        $ip    = fmt_ip($row['ip'] ?? '');
        $until = (string)($row['until_at'] ?? '');
        $leftS = max(0, strtotime($until) - time());
        $activeNow = $leftS > 0;
      ?>
      <tr>
        <td><input class="rowchk" type="checkbox" name="ips[]" value="<?= htmlspecialchars($ip) ?>"></td>
        <td class="mono nowrap">
          <?= htmlspecialchars($ip) ?>
          <?php if ($ip !== ''): ?>
            <button class="copybtn" type="button" onclick="navigator.clipboard?.writeText('<?= htmlspecialchars($ip, ENT_QUOTES) ?>')">Copy</button>
          <?php endif; ?>
        </td>
        <td class="mono nowrap"><?= htmlspecialchars($until) ?></td>
        <td class="mono nowrap"><?= htmlspecialchars((string)($row['created_at'] ?? '')) ?></td>
        <td>
          <?php if ($activeNow): ?>
            <span class="badge act">ACTIVE · <?= htmlspecialchars(humanize_left($leftS)) ?> left</span>
          <?php else: ?>
            <span class="badge" style="background:#e8fff0;border-color:#9bd3af">Expired</span>
          <?php endif; ?>
        </td>
        <td>
          <form class="inline" method="post" onsubmit="return confirm('Unban <?= htmlspecialchars($ip) ?> now?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
            <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
            <input type="hidden" name="action" value="clear">
            <button class="copybtn" type="submit">Unban</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

  <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin:.5rem 0">
    <button type="submit" class="copybtn" style="border-style:dashed">Unban selected</button>
    <a href="<?= htmlspecialchars(build_qs(['p'=>1])) ?>">« First</a>
    <?php $prev = max(1, $page-1); $next = min($totalPages, $page+1); ?>
    <a href="<?= htmlspecialchars(build_qs(['p'=>$prev])) ?>">‹ Prev</a>
    <span style="padding:.25rem .5rem;border:1px solid #ccc;border-radius:6px;background:#f7f7f7">Page <?= (int)$page ?> / <?= (int)$totalPages ?></span>
    <a href="<?= htmlspecialchars(build_qs(['p'=>$next])) ?>">Next ›</a>
    <a href="<?= htmlspecialchars(build_qs(['p'=>$totalPages])) ?>">Last »</a>
  </div>
</form>
<?php endif; ?>

<?php if (!$loadedHeader) echo "</body></html>"; ?>
