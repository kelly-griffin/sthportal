<?php
// admin/login-attempts.php — view (Copy/Expand + Ban/Whitelist + cURL) + CSV + Temp Ban presets + Custom hours/days + Add form + Humanized badge + Server-side IP validation

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/session-guard.php';
require_admin();
if (function_exists('session_guard_boot')) session_guard_boot();

// ----- DB handle -----
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('Database handle not initialized.'); }

// ----- CSRF -----
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

// ---------- Inputs ----------
$q       = trim((string)($_GET['q'] ?? ''));
$status  = trim((string)($_GET['status'] ?? ''));
$sort    = (string)($_GET['sort'] ?? 'attempted_at');
$dir     = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = max(10, min(200, (int)($_GET['perPage'] ?? 50)));
$type    = strtolower(trim((string)($_GET['type'] ?? '')));      // '', 'admin', 'users'
$since   = strtolower(trim((string)($_GET['since'] ?? 'all')));   // all | 24h | 7d | 30d
$export  = strtolower(trim((string)($_GET['export'] ?? '')));     // csv | ''
$msg     = trim((string)($_GET['msg'] ?? ''));

$sortMap = ['attempted_at'=>'attempted_at','created_at'=>'created_at','ip'=>'ip','username'=>'username','actor'=>'actor','success'=>'success'];
$orderBy = $sortMap[$sort] ?? 'attempted_at';

// ---------- Helpers ----------
function build_qs(array $overrides = []): string {
  $params = $_GET;
  foreach ($overrides as $k => $v) { if ($v === null) unset($params[$k]); else $params[$k] = $v; }
  return '?' . http_build_query($params);
}
function sort_link(string $key, string $label, string $currentSort, string $currentDir): string {
  $is = ($currentSort === $key);
  $dir = $is && $currentDir === 'asc' ? 'desc' : 'asc';
  $arrow = $is ? ($currentDir === 'asc' ? ' ▲' : ' ▼') : '';
  $href = build_qs(['sort'=>$key,'dir'=>$dir,'p'=>1]);
  return '<a href="'.htmlspecialchars($href).'">'.htmlspecialchars($label).$arrow.'</a>';
}
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
  if (strpos($ip, ',') !== false) $ip = trim(strtok($ip, ','));        // first token only
  if (preg_match('/^\\[(.*)\\]$/', $ip, $m)) $ip = $m[1];              // strip [v6]
  if (strpos($ip, '%') !== false) return '';                           // reject zone id
  if (stripos($ip, '::ffff:') === 0) {                                 // v6-mapped v4
    $v4 = substr($ip, 7);
    if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $v4;
    return '';
  }
  if ($ip === '::1') return '127.0.0.1';
  return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

// ---------- IP rules (ban/allow) ----------
function ensure_ip_rules(mysqli $db): void {
  $db->query("CREATE TABLE IF NOT EXISTS security_ip_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(64) NOT NULL,
    rule ENUM('ban','allow') NOT NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ip_rule (ip, rule)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function ensure_ip_temp_bans(mysqli $db): void {
  $db->query("CREATE TABLE IF NOT EXISTS security_ip_temp_bans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(64) NOT NULL,
    until_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ip (ip)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function ip_rule_get(mysqli $db, string $ip): array {
  ensure_ip_rules($db); ensure_ip_temp_bans($db);
  $out = ['ban'=>false,'allow'=>false,'temp_until'=>null];
  if ($stmt = $db->prepare("SELECT rule FROM security_ip_rules WHERE ip=?")) {
    $stmt->bind_param('s', $ip); $stmt->execute(); $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { if ($r['rule']==='ban') $out['ban']=true; if ($r['rule']==='allow') $out['allow']=true; }
    $stmt->close();
  }
  if ($stmt = $db->prepare("SELECT until_at FROM security_ip_temp_bans WHERE ip=?")) {
    $stmt->bind_param('s', $ip); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc();
    if ($row) $out['temp_until'] = $row['until_at'];
    $stmt->close();
  }
  return $out;
}
function ip_rule_set(mysqli $db, string $ip, string $rule, string $note=''): void {
  ensure_ip_rules($db);
  if ($stmt = $db->prepare("INSERT IGNORE INTO security_ip_rules (ip, rule, note) VALUES (?, ?, ?)")) {
    $stmt->bind_param('sss', $ip, $rule, $note); $stmt->execute(); $stmt->close();
  }
}
function ip_rule_del(mysqli $db, string $ip, string $rule=null): void {
  ensure_ip_rules($db);
  if ($rule === null) { $stmt = $db->prepare("DELETE FROM security_ip_rules WHERE ip=?"); $stmt->bind_param('s', $ip); }
  else { $stmt = $db->prepare("DELETE FROM security_ip_rules WHERE ip=? AND rule=?"); $stmt->bind_param('ss', $ip, $rule); }
  $stmt->execute(); $stmt->close();
}
function ip_temp_ban_set(mysqli $db, string $ip, int $hours): void {
  ensure_ip_temp_bans($db);
  $hours = max(1, min($hours, 24*365));
  $until = (new DateTimeImmutable('now'))->modify("+{$hours} hour")->format('Y-m-d H:i:s');
  if ($stmt = $db->prepare("INSERT INTO security_ip_temp_bans (ip, until_at) VALUES (?, ?) ON DUPLICATE KEY UPDATE until_at=VALUES(until_at)")) {
    $stmt->bind_param('ss', $ip, $until); $stmt->execute(); $stmt->close();
  }
}
function ip_temp_ban_clear(mysqli $db, string $ip): void {
  ensure_ip_temp_bans($db);
  if ($stmt = $db->prepare("DELETE FROM security_ip_temp_bans WHERE ip=?")) {
    $stmt->bind_param('s', $ip); $stmt->execute(); $stmt->close();
  }
}
function humanize_left(int $secs): string {
  if ($secs <= 0) return '0m';
  $days  = intdiv($secs, 86400);
  $secs %= 86400;
  $hours = intdiv($secs, 3600);
  $secs %= 3600;
  $mins  = intdiv($secs, 60);
  if ($days > 0) return "{$days}d {$hours}h";
  if ($hours > 0) return "{$hours}h {$mins}m";
  return "{$mins}m";
}
function temp_left_badge(?string $until): string {
  if (!$until) return '';
  $secs = max(0, strtotime($until) - time());
  if ($secs <= 0) return '';
  return '<span class="badge rule" title="Temporary ban in effect">TEMP ' . humanize_left($secs) . ' left</span>';
}

// ---------- POST: actions ----------
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
    if ($ip === '') { header('Location: '.build_qs(['msg'=>'Enter a single, valid IPv4/IPv6 address (no lists, no zone suffix).'])); exit; }
    $hours = $unit === 'd' ? $qty * 24 : $qty;
    if ($hours > 24*365) $hours = 24*365;
    ip_temp_ban_set($dbc, $ip, $hours);
    log_audit('ip_temp_ban_add', ['ip'=>$ip,'hours'=>$hours], 'admin');
    header('Location: '.build_qs(['msg'=>"Temp ban added for {$ip} ({$qty}{$unit})", 'q'=>($q?:$ip)])); exit;
  }

  $ip = normalize_ip_input((string)($_POST['ip'] ?? ''));
  if ($ip === '') { header('Location: '.build_qs(['msg'=>'Invalid IP for this action.'])); exit; }

  switch ($action) {
    case 'ban':        ip_rule_set($dbc, $ip, 'ban', 'Banned via login-attempts');            log_audit('ip_rule', ['ip'=>$ip,'rule'=>'ban','src'=>'login_attempts'], 'admin'); break;
    case 'allow':      ip_rule_set($dbc, $ip, 'allow', 'Whitelist via login-attempts');        log_audit('ip_rule', ['ip'=>$ip,'rule'=>'allow','src'=>'login_attempts'], 'admin'); break;
    case 'unban':      ip_rule_del($dbc, $ip, 'ban');                                          log_audit('ip_rule', ['ip'=>$ip,'rule'=>'unban','src'=>'login_attempts'], 'admin'); break;
    case 'unallow':    ip_rule_del($dbc, $ip, 'allow');                                        log_audit('ip_rule', ['ip'=>$ip,'rule'=>'unallow','src'=>'login_attempts'], 'admin'); break;

    case 'ban1h':      ip_temp_ban_set($dbc, $ip, 1);                                          log_audit('ip_temp_ban', ['ip'=>$ip,'hours'=>1],   'admin'); break;
    case 'ban24':      ip_temp_ban_set($dbc, $ip, 24);                                         log_audit('ip_temp_ban', ['ip'=>$ip,'hours'=>24],  'admin'); break;
    case 'ban7d':      ip_temp_ban_set($dbc, $ip, 24*7);                                       log_audit('ip_temp_ban', ['ip'=>$ip,'hours'=>168], 'admin'); break;
    case 'ban_custom':
      $hours = (int)($_POST['hours'] ?? 0);
      if ($hours < 1) $hours = 1;
      if ($hours > 24*365) $hours = 24*365;
      ip_temp_ban_set($dbc, $ip, $hours);
      log_audit('ip_temp_ban', ['ip'=>$ip,'hours'=>$hours], 'admin');
      break;
    case 'ban_custom_days':
      $days = (int)($_POST['days'] ?? 0);
      if ($days < 1) $days = 1;
      if ($days > 365) $days = 365;
      $hours = $days * 24;
      ip_temp_ban_set($dbc, $ip, $hours);
      log_audit('ip_temp_ban', ['ip'=>$ip,'days'=>$days,'hours'=>$hours], 'admin');
      break;
    case 'clear_temp': ip_temp_ban_clear($dbc, $ip);                                           log_audit('ip_temp_ban_clear', ['ip'=>$ip],        'admin'); break;
  }

  header('Location: '.build_qs([])); exit;
}

// ---------- Filters ----------
$where  = '1';
$types  = '';
$params = [];

if     ($type === 'admin')  $where .= " AND actor = 'admin'";
elseif ($type === 'users')  $where .= " AND (actor IS NULL OR actor <> 'admin')";

$tsExpr = "COALESCE(attempted_at, created_at)";
if     ($since === '24h') $where .= " AND $tsExpr > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
elseif ($since === '7d')  $where .= " AND $tsExpr > DATE_SUB(NOW(), INTERVAL 7 DAY)";
elseif ($since === '30d') $where .= " AND $tsExpr > DATE_SUB(NOW(), INTERVAL 30 DAY)";

if ($status === 'success')      $where .= ' AND success = 1';
elseif ($status === 'failed')   $where .= ' AND success = 0';

if ($q !== '') {
  $where .= ' AND (ip LIKE ? OR username LIKE ? OR actor LIKE ? OR note LIKE ?)';
  $like = '%' . $q . '%';
  $params = [$like, $like, $like, $like];
  $types  = 'ssss';
}

// ---------- Counts ----------
$totalRows = 0;
$cntSql = "SELECT COUNT(*) FROM login_attempts WHERE $where";
if ($types !== '') { $stmt = $dbc->prepare($cntSql); $stmt->bind_param($types, ...$params); $stmt->execute(); $r = $stmt->get_result()->fetch_row(); $stmt->close(); $totalRows = (int)($r[0] ?? 0); }
else { $r = $dbc->query($cntSql)->fetch_row(); $totalRows = (int)($r[0] ?? 0); }

// ---------- Export CSV ----------
if ($export === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="login-attempts.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['id','when_at','ip','username','actor','success','note','created_at']);
  $sql = "SELECT id, COALESCE(attempted_at, created_at) as when_at, ip, username, actor, success, note, created_at
          FROM login_attempts WHERE $where ORDER BY $orderBy " . strtoupper($dir);
  if ($types !== '') { $stmt = $dbc->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $rows = $stmt->get_result(); }
  else { $rows = $dbc->query($sql); }
  if ($rows) while ($row = $rows->fetch_assoc()) {
    fputcsv($out, [(int)$row['id'], (string)$row['when_at'], (string)$row['ip'], (string)($row['username']??''), (string)($row['actor']??''), (int)$row['success'], (string)($row['note']??''), (string)$row['created_at']]);
  }
  if (isset($stmt) && $stmt) $stmt->close();
  exit;
}

// ---------- Paging ----------
$totalPages  = max(1, (int)ceil($totalRows / $perPage));
$page        = min($page, $totalPages);
$offset      = ($page - 1) * $perPage;

// chips (overall success/fail counts)
$cntSucc=0; $cntFail=0;
if ($resChip = $dbc->query("SELECT success, COUNT(*) c FROM login_attempts GROUP BY success")) {
  while ($r = $resChip->fetch_assoc()) { if ((int)$r['success']===1) $cntSucc=(int)$r['c']; else $cntFail=(int)$r['c']; }
  $resChip->close();
}

// ---------- Query ----------
$sql = "SELECT id, ip, username, actor, success, note, attempted_at, created_at
        FROM login_attempts
        WHERE $where
        ORDER BY $orderBy " . strtoupper($dir) . "
        LIMIT ? OFFSET ?";
$stmt = $dbc->prepare($sql);
if ($types !== '') { $types2 = $types . 'ii'; $params2 = array_merge($params, [$perPage, $offset]); $stmt->bind_param($types2, ...$params2); }
else               { $stmt->bind_param('ii', $perPage, $offset); }
$stmt->execute();
$res = $stmt->get_result();

// ---------- Header include ----------
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/header-admin.php', __DIR__.'/header.php', __DIR__.'/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) echo "<!doctype html><meta charset='utf-8'><title>Login Attempts</title><body style='font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial; margin:16px'>";
?>
<h1>Login Attempts</h1>

<?php if ($msg !== ''): ?>
  <div style="margin:.5rem 0;padding:.5rem .75rem;border:1px solid #c7d2fe;background:#eef2ff;border-radius:8px;">
    <?= htmlspecialchars($msg) ?>
  </div>
<?php endif; ?>

<!-- Add temp ban (normalized to .filters) -->
<form method="post" class="filters" style="margin:.4rem 0 .8rem 0">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
  <input type="hidden" name="action" value="add">
  <div>
    <label for="add_ip">IP address</label>
    <input id="add_ip" name="ip" type="text" placeholder="e.g. 203.0.113.42" required>
  </div>
  <div>
    <label for="add_qty">Duration</label>
    <input id="add_qty" name="qty" type="number" min="1" max="8760" value="24" style="width:7ch;text-align:right" required>
  </div>
  <div>
    <label for="add_unit">&nbsp;</label>
    <select id="add_unit" name="unit">
      <option value="h">hours</option>
      <option value="d">days</option>
    </select>
  </div>
  <div>
    <label>&nbsp;</label>
    <button class="btn" type="submit">Add temp ban</button>
  </div>
</form>

<!-- Filters (use .filters, not inline-styled .filterbar) -->
<form method="get" class="filters" style="margin:.6rem 0">
  <div>
    <label for="q">Search</label>
    <input type="text" id="q" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="IP, username, actor, or note">
  </div>
  <div>
    <label for="status">Status</label>
    <select id="status" name="status">
      <option value="">All</option>
      <option value="success" <?= $status==='success' ?'selected':'' ?>>Success</option>
      <option value="failed"  <?= $status==='failed' ?'selected':''  ?>>Failed</option>
    </select>
  </div>
  <div>
    <label for="type">Type</label>
    <select id="type" name="type">
      <option value="" <?= $type===''?'selected':'' ?>>All</option>
      <option value="admin" <?= $type==='admin'?'selected':'' ?>>Admin PIN only</option>
      <option value="users" <?= $type==='users'?'selected':'' ?>>User logins only</option>
    </select>
  </div>
  <div>
    <label for="since">When</label>
    <select id="since" name="since">
      <option value="all"  <?= $since==='all'?'selected':''  ?>>All time</option>
      <option value="24h"  <?= $since==='24h'?'selected':''  ?>>Last 24h</option>
      <option value="7d"   <?= $since==='7d'?'selected':''   ?>>Last 7 days</option>
      <option value="30d"  <?= $since==='30d'?'selected':''  ?>>Last 30 days</option>
    </select>
  </div>
  <div>
    <label for="perPage">Per page</label>
    <select id="perPage" name="perPage">
      <?php foreach ([10,25,50,100,200] as $n): ?>
        <option value="<?= $n ?>" <?= $perPage===$n?'selected':'' ?>><?= $n ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label>&nbsp;</label>
    <button class="btn" type="submit">Apply</button>
  </div>
  <div>
    <label>&nbsp;</label>
    <a class="btn" href="login-attempts.php">Reset</a>
  </div>
  <div>
    <label>&nbsp;</label>
    <a class="btn" href="<?= htmlspecialchars(build_qs(['export'=>'csv','p'=>null])) ?>">Export CSV</a>
  </div>
  <div style="margin-left:auto;display:inline-flex;gap:.5rem;align-items:center;">
    <span class="chip">Total: <?= (int)$totalRows ?></span>
    <span class="chip">Success: <?= (int)$cntSucc ?></span>
    <span class="chip">Failed: <?= (int)$cntFail ?></span>
    <button type="button" class="btn" id="expandAll">Expand All</button>
    <button type="button" class="btn" id="collapseAll">Collapse All</button>
  </div>
</form>

<style>
  table{width:100%;border-collapse:collapse;margin:.6rem 0}
  th,td{padding:.5rem;border-bottom:1px solid #eee;vertical-align:top}
  th{text-align:left;background:#fafafa}
  .badge{display:inline-block;padding:.1rem .4rem;border:1px solid #ccc;border-radius:999px;font-size:.85rem}
  .badge.ok{background:#e8fff0;border-color:#9bd3af}
  .badge.fail{background:#ffecec;border-color:#e3a0a0}
  .badge.rule{background:#eef2ff;border-color:#c7d2fe}
  .json{white-space:pre; max-height:120px; overflow:auto; border:1px solid #eee; border-radius:6px; padding:6px; background:#fafafa}
  .json.expanded{max-height:none}
  .actions{display:flex;gap:.4rem;align-items:center;flex-wrap:wrap}
  .nowrap{white-space:nowrap}
  .mono{font-family: ui-monospace, Menlo, Consolas, "Liberation Mono", monospace; font-size:.9rem}
  .ip-actions{display:flex;gap:.3rem;align-items:center;margin-top:.25rem;flex-wrap:wrap}
  form.inline{display:inline}
  .nh,.nd{width:5.2ch;padding:.25rem .35rem;border:1px solid #ccc;border-radius:6px;text-align:right}
</style>

<?php if ($totalRows === 0): ?>
  <div class="empty" style="padding:10px;border:1px dashed #ccc;background:#fafafa">No login attempts found.</div>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th><?= sort_link('attempted_at','When',$sort,$dir) ?></th>
        <th><?= sort_link('username','Username',$sort,$dir) ?></th>
        <th><?= sort_link('actor','Actor',$sort,$dir) ?></th>
        <th><?= sort_link('ip','IP',$sort,$dir) ?></th>
        <th><?= sort_link('success','Success',$sort,$dir) ?></th>
        <th>Note</th>
        <th>Details / Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php
      $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
      $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
      $root   = preg_replace('#/admin$#','',$base);
      $loginUrl = $scheme.'://'.$host.$root.'/login.php';
    ?>
    <?php while ($row = $res->fetch_assoc()): ?>
    <?php
      $ip   = fmt_ip($row['ip'] ?? '');
      $rule = $ip !== '' ? ip_rule_get($dbc, $ip) : ['ban'=>false,'allow'=>false,'temp_until'=>null];
      $payload = [
        'id' => (int)$row['id'],
        'ip' => (string)$row['ip'],
        'username' => (string)($row['username'] ?? ''),
        'actor' => (string)($row['actor'] ?? ''),
        'success' => (bool)$row['success'],
        'note' => (string)($row['note'] ?? ''),
        'attempted_at' => (string)($row['attempted_at'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? '')
      ];
      $payloadStr = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

      $emailForCurl = (string)($row['actor'] ?? '');
      $curl = "curl -i -X POST \"$loginUrl\" "
            . "-H \"Content-Type: application/x-www-form-urlencoded\" "
            . ($ip !== '' ? "-H \"X-Forwarded-For: $ip\" " : "")
            . "--data-urlencode \"email=$emailForCurl\" "
            . "--data-urlencode \"password=REPLACE_ME\"";
    ?>
      <tr>
        <td class="nowrap mono"><?= htmlspecialchars((string)($row['attempted_at'] ?? $row['created_at'] ?? '')) ?></td>
        <td>
          <span class="mono"><?= htmlspecialchars((string)($row['username'] ?? '')) ?></span>
          <?php if (!empty($row['username'])): ?>
            <button class="copybtn js-copy" data-copy="<?= htmlspecialchars((string)$row['username']) ?>" type="button">Copy</button>
          <?php endif; ?>
        </td>
        <td>
          <span class="mono"><?= htmlspecialchars((string)($row['actor'] ?? '')) ?></span>
          <?php if (!empty($row['actor'])): ?>
            <button class="copybtn js-copy" data-copy="<?= htmlspecialchars((string)$row['actor']) ?>" type="button">Copy</button>
          <?php endif; ?>
        </td>
        <td class="nowrap">
          <div>
            <span class="mono"><?= htmlspecialchars($ip) ?></span>
            <?php if ($ip !== ''): ?>
              <button class="copybtn js-copy" data-copy="<?= htmlspecialchars($ip) ?>" type="button">Copy</button>
            <?php endif; ?>
          </div>
          <?php if ($ip !== ''): ?>
          <div class="ip-actions">
            <?= $rule['temp_until'] ? temp_left_badge($rule['temp_until']) : '' ?>

            <?php if ($rule['ban']): ?>
              <span class="badge rule">BANNED</span>
              <form class="inline" method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
                <input type="hidden" name="action" value="unban">
                <button class="copybtn" type="submit">Unban</button>
              </form>
            <?php else: ?>
              <form class="inline" method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
                <input type="hidden" name="action" value="ban">
                <button class="copybtn" type="submit">Ban IP</button>
              </form>
            <?php endif; ?>

            <?php if ($rule['allow']): ?>
              <span class="badge rule">ALLOWED</span>
              <form class="inline" method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
                <input type="hidden" name="action" value="unallow">
                <button class="copybtn" type="submit">Remove Allow</button>
              </form>
            <?php else: ?>
              <form class="inline" method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
                <input type="hidden" name="action" value="allow">
                <button class="copybtn" type="submit">Whitelist</button>
              </form>
            <?php endif; ?>

            <?php if ($rule['temp_until'] && strtotime((string)$rule['temp_until']) > time()): ?>
              <form class="inline" method="post" title="Remove temporary block">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
                <input type="hidden" name="action" value="clear_temp">
                <button class="copybtn" type="submit">Clear Temp</button>
              </form>
            <?php else: ?>
              <form class="inline" method="post" title="Block for 1 hour">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
                <input type="hidden" name="action" value="ban1h">
                <button class="copybtn" type="submit">Block 1h</button>
              </form>
              <form class="inline" method="post" title="Block for 24 hours">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
                <input type="hidden" name="action" value="ban24">
                <button class="copybtn" type="submit">Block 24h</button>
              </form>
              <form class="inline" method="post" title="Block for 7 days">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
                <input type="hidden" name="action" value="ban7d">
                <button class="copybtn" type="submit">Block 7d</button>
              </form>

              <!-- Custom N hours -->
              <form class="inline" method="post" title="Block for N hours">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
                <input type="hidden" name="action" value="ban_custom">
                <input class="nh" type="number" name="hours" min="1" max="8760" value="69" aria-label="Hours to block">
                <button class="copybtn" type="submit">Block Nh</button>
              </form>

              <!-- Custom N days -->
              <form class="inline" method="post" title="Block for N days">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
                <input type="hidden" name="action" value="ban_custom_days">
                <input class="nd" type="number" name="days" min="1" max="365" value="69" aria-label="Days to block">
                <button class="copybtn" type="submit">Block Nd</button>
              </form>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </td>
        <td>
          <?php $ok = (int)$row['success'] === 1; ?>
          <span class="badge <?= $ok ? 'ok' : 'fail' ?>"><?= $ok ? 'yes' : 'no' ?></span>
        </td>
        <td><?= htmlspecialchars((string)($row['note'] ?? '')) ?></td>
        <td class="actions">
          <button class="copybtn js-toggle" type="button">Expand</button>
          <button class="copybtn js-copy" data-copy='<?= htmlspecialchars($payloadStr, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>' type="button">Copy JSON</button>
          <button class="copybtn js-copy" data-copy="<?= htmlspecialchars($curl) ?>" type="button">Copy cURL</button>
          <pre class="json"><?= htmlspecialchars($payloadStr) ?></pre>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

  <?php $prev = max(1, $page-1); $next = min($totalPages, $page+1); ?>
  <div class="pager" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;margin:.6rem 0">
    <a href="<?= htmlspecialchars(build_qs(['p'=>1])) ?>">« First</a>
    <a href="<?= htmlspecialchars(build_qs(['p'=>$prev])) ?>">‹ Prev</a>
    <span class="current" style="padding:.25rem .5rem;border:1px solid #ccc;border-radius:6px;background:#e8fff0;border-color:#9bd3af">Page <?= (int)$page ?> / <?= (int)$totalPages ?></span>
    <a href="<?= htmlspecialchars(build_qs(['p'=>$next])) ?>">Next ›</a>
    <a href="<?= htmlspecialchars(build_qs(['p'=>$totalPages])) ?>">Last »</a>
  </div>
<?php endif; ?>

<?php if (!$loadedHeader) echo "</body></html>"; ?>

<script>
document.addEventListener('click', function(e){
  var copyBtn = e.target.closest('.js-copy');
  if (copyBtn) {
    var txt = copyBtn.getAttribute('data-copy') || '';
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(txt); }
    else { window.prompt('Copy:', txt); }
  }
  var tog = e.target.closest('.js-toggle');
  if (tog) {
    var pre = tog.parentElement.querySelector('pre.json');
    if (pre) {
      var open = !pre.classList.contains('expanded');
      if (open) pre.classList.add('expanded'); else pre.classList.remove('expanded');
      tog.textContent = open ? 'Collapse' : 'Expand';
    }
  }
});
(function(){
  var expAll = document.getElementById('expandAll');
  var colAll = document.getElementById('collapseAll');
  function setAll(open) {
    document.querySelectorAll('pre.json').forEach(function(pre){
      if (open) pre.classList.add('expanded'); else pre.classList.remove('expanded');
      var btn = pre.parentElement.querySelector('.js-toggle');
      if (btn) btn.textContent = open ? 'Collapse' : 'Expand';
    });
  }
  if (expAll) expAll.addEventListener('click', function(){ setAll(true); });
  if (colAll) colAll.addEventListener('click', function(){ setAll(false); });
})();
</script>
