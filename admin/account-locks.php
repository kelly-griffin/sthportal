<?php
// admin/account-locks.php — iconized UI + centered success/error toast (auto-fades)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/account-locks.php';
require_once __DIR__ . '/../includes/session-guard.php';

require_admin();
if (function_exists('session_guard_boot')) session_guard_boot();
require_perm('manage_users');

// DB handle
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('Database handle not initialized.'); }
al_ensure($dbc);

// Session + CSRF
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

// Inputs
$q       = trim((string)($_GET['q'] ?? ''));
$type    = (string)($_GET['type'] ?? '');
$active  = ($_GET['active'] ?? '1') === '1' ? '1' : '0';
$sort    = (string)($_GET['sort'] ?? 'until_at');
$dir     = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = max(10, min(200, (int)($_GET['perPage'] ?? 50)));
$export  = (string)($_GET['export'] ?? '');
// Toast messages via query
$msg     = trim((string)($_GET['msg'] ?? ''));
$err     = trim((string)($_GET['err'] ?? ''));

// Helpers
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

// POST actions (add/clear/extend/purge)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Bad CSRF'); }
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'add') {
    $ident  = trim((string)($_POST['identity'] ?? ''));
    $t      = (string)($_POST['acct_type'] ?? 'user');
    $until  = trim((string)($_POST['until'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($t !== 'admin') $t = 'user';
    if ($t === 'admin' && $ident === '') $ident = 'admin';
    if ($ident === '' || $until === '') { header('Location: '.build_qs(['err'=>'Identity and "Lock until" are required.'])); exit; }

    $ts = strtotime($until);
    if ($ts === false) { header('Location: '.build_qs(['err'=>'Invalid date/time. Use the picker.'])); exit; }
    if ($ts <= time()) { header('Location: '.build_qs(['err'=>'"Lock until" must be in the future.'])); exit; }
    $untilSql = date('Y-m-d H:i:s', $ts);

    if ($t === 'user') {
      if ($stmt = $dbc->prepare("UPDATE users SET locked_until=? WHERE email=?")) {
        $stmt->bind_param('ss', $untilSql, $ident); $stmt->execute(); $stmt->close();
      }
    }
    al_set_lock($dbc, $ident, $t, $untilSql, $reason !== '' ? $reason : 'Locked via admin panel');
    log_audit('account_lock_add', ['identity'=>$ident,'type'=>$t,'until'=>$untilSql,'reason'=>$reason], 'admin');
    header('Location: '.build_qs(['msg'=>"Locked {$ident} until {$untilSql}"])); exit;
  }
  elseif ($action === 'extend') {
    $ident  = trim((string)($_POST['identity'] ?? ''));
    $t      = (string)($_POST['acct_type'] ?? 'user');
    $days   = max(1, (int)($_POST['days'] ?? 0));
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($t !== 'admin') $t = 'user';
    if ($t === 'admin' && $ident === '') $ident = 'admin';
    if ($ident === '' || $days <= 0) { header('Location: '.build_qs(['err'=>'Missing identity or days.'])); exit; }

    $row = al_get_active_lock($dbc, $ident, $t);
    $baseTs = time();
    if ($row && !empty($row['until_at'])) {
      $cur = strtotime((string)$row['until_at']);
      if ($cur !== false && $cur > $baseTs) $baseTs = $cur;
    }
    $newTs = $baseTs + ($days * 86400);
    $untilSql = date('Y-m-d H:i:s', $newTs);

    if ($t === 'user') {
      if ($stmt = $dbc->prepare("UPDATE users SET locked_until=? WHERE email=?")) {
        $stmt->bind_param('ss', $untilSql, $ident); $stmt->execute(); $stmt->close();
      }
    }
    $note = $reason !== '' ? $reason : ('Extended +' . $days . 'd');
    al_set_lock($dbc, $ident, $t, $untilSql, $note);
    log_audit('account_lock_extend', ['identity'=>$ident,'type'=>$t,'days'=>$days,'until'=>$untilSql], 'admin');
    header('Location: '.build_qs(['msg'=>"Extended {$ident} to {$untilSql}"])); exit;
  }
  elseif ($action === 'clear') {
    $ident = trim((string)($_POST['identity'] ?? ''));
    $t     = (string)($_POST['acct_type'] ?? 'user');
    if ($t !== 'admin') $t = 'user';
    if ($ident !== '') {
      al_clear_lock($dbc, $ident, $t);
      if ($t === 'user') {
        if ($stmt = $dbc->prepare("UPDATE users SET locked_until=NULL WHERE email=?")) {
          $stmt->bind_param('s', $ident); $stmt->execute(); $stmt->close();
        }
      }
      log_audit('account_lock_clear', ['identity'=>$ident,'type'=>$t], 'admin');
      header('Location: '.build_qs(['msg'=>"Unlocked {$ident}"])); exit;
    }
    header('Location: '.build_qs(['err'=>'Nothing to clear.'])); exit;
  }
  elseif ($action === 'purge_expired') {
    $dbc->query("DELETE FROM security_account_locks WHERE until_at <= NOW()");
    log_audit('account_lock_purge', [], 'admin');
    header('Location: '.build_qs(['msg'=>'Expired locks purged.'])); exit;
  }
}

// Filtering (shared by table view and CSV)
$where = '1';
$types = ''; $params = [];
if ($q !== '') { $where .= ' AND (identity LIKE ? OR reason LIKE ?)'; $types .= 'ss'; $like='%'.$q.'%'; $params[]=$like; $params[]=$like; }
if ($type === 'user' || $type === 'admin') { $where .= ' AND type=?'; $types .= 's'; $params[] = $type; }
if ($active === '1') { $where .= ' AND until_at > NOW()'; }

// CSV export
if ($export === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="account-locks.csv"');
  $sql = "SELECT identity, type, until_at, reason, created_at FROM security_account_locks WHERE $where ORDER BY until_at DESC";
  if ($types !== '') { $stmt=$dbc->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $res=$stmt->get_result(); }
  else               { $res=$dbc->query($sql); }
  $out = fopen('php://output', 'w'); fputcsv($out, ['identity','type','until_at','reason','created_at']);
  while ($row = $res->fetch_assoc()) { fputcsv($out, [$row['identity'],$row['type'],$row['until_at'],$row['reason'],$row['created_at']]); }
  fclose($out); exit;
}

// Count + paged query
$cntSql = "SELECT COUNT(*) FROM security_account_locks WHERE $where";
if ($types !== '') { $stmt=$dbc->prepare($cntSql); $stmt->bind_param($types, ...$params); $stmt->execute(); $r=$stmt->get_result()->fetch_row(); $stmt->close(); $totalRows=(int)($r[0]??0); }
else               { $r=$dbc->query($cntSql)->fetch_row(); $totalRows=(int)($r[0]??0); }
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$allowedSort = ['identity','type','until_at','created_at']; // harden ORDER BY
$orderCol = in_array($sort, $allowedSort, true) ? $sort : 'until_at';

$sql = "SELECT id, identity, type, until_at, reason, created_at
        FROM security_account_locks
        WHERE $where
        ORDER BY $orderCol " . strtoupper($dir) . "
        LIMIT ? OFFSET ?";
if ($types !== '') { $types2 = $types.'ii'; $params2 = array_merge($params, [$perPage, $offset]); $stmt=$dbc->prepare($sql); $stmt->bind_param($types2, ...$params2); $stmt->execute(); $res=$stmt->get_result(); }
else               { $stmt=$dbc->prepare($sql); $stmt->bind_param('ii', $perPage, $offset); $stmt->execute(); $res=$stmt->get_result(); }

// Header include (best-effort)
$loadedHeader = false;
foreach ([
  __DIR__.'/admin-header.php',
  __DIR__.'/_header.php',
  __DIR__.'/header-admin.php',
  __DIR__.'/partials/admin-header.php',
  __DIR__.'/../includes/admin-header.php',
  __DIR__.'/../includes/header.php',
] as $p) { if (is_file($p)) { include $p; $loadedHeader = true; break; } }
if (!$loadedHeader) echo "<!doctype html><meta charset='utf-8'><title>Account Locks</title><style>body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial;margin:16px}</style>";

?>
<h1 style="display:flex;align-items:center;gap:.75rem;">Account Locks
  <span style="font-size:.9rem;color:#667;font-weight:400"> — manage user/admin lockouts</span>
</h1>

<!-- Centered Toasts -->
<style>
.toast {
  position: fixed; left: 50%; top: 12px; transform: translateX(-50%);
  z-index: 9999; padding: .6rem .8rem; border-radius: .6rem; border:1px solid;
  box-shadow: 0 6px 18px rgba(0,0,0,.08);
  display:flex; align-items:center; gap:.5rem;
  transition: opacity .3s ease, transform .3s ease;
}
.toast-success { background:#e9f8ef; border-color:#bfe6bf; color:#0a7d2f; }
.toast-error   { background:#ffecec; border-color:#f3b4b4; color:#7a1212; }
.toast-hide    { opacity:0; transform: translate(-50%, -6px); }
.toast .toast-close { background:transparent; border:0; font-size:1rem; line-height:1; cursor:pointer; color:inherit; padding:.1rem .25rem; }
.toast svg { width:16px; height:16px; flex:0 0 auto; }
</style>
<svg xmlns="http://www.w3.org/2000/svg" style="display:none;">
  <symbol id="i-check" viewBox="0 0 24 24"><path fill="currentColor" d="M9 16.2l-3.5-3.5L4 14.2l5 5L20 8.2 18.6 7z"/></symbol>
  <symbol id="i-x" viewBox="0 0 24 24"><path fill="currentColor" d="M18.3 5.7L12 12l6.3 6.3-1.4 1.4L10.6 13.4 4.3 19.7 2.9 18.3 9.2 12 2.9 5.7 4.3 4.3l6.3 6.3 6.3-6.3z"/></symbol>
</svg>
<?php if ($msg !== ''): ?>
  <div class="toast toast-success" id="toast">
    <svg><use href="#i-check"/></svg>
    <div><?= htmlspecialchars($msg) ?></div>
    <button class="toast-close" onclick="(function(){var t=document.getElementById('toast'); if(!t) return; t.classList.add('toast-hide'); setTimeout(function(){t.remove();}, 250); })()">×</button>
  </div>
  <script>setTimeout(function(){var t=document.getElementById('toast'); if(!t) return; t.classList.add('toast-hide'); setTimeout(function(){t.remove();}, 250);}, 2000);</script>
<?php endif; ?>
<?php if ($err !== ''): ?>
  <div class="toast toast-error" id="toastErr">
    <svg><use href="#i-x"/></svg>
    <div><?= htmlspecialchars($err) ?></div>
    <button class="toast-close" onclick="(function(){var t=document.getElementById('toastErr'); if(!t) return; t.classList.add('toast-hide'); setTimeout(function(){t.remove();}, 250); })()">×</button>
  </div>
  <script>setTimeout(function(){var t=document.getElementById('toastErr'); if(!t) return; t.classList.add('toast-hide'); setTimeout(function(){t.remove();}, 250);}, 4000);</script>
<?php endif; ?>

<!-- SVG icons for actions -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none;">
  <symbol id="i-copy" viewBox="0 0 24 24"><path fill="currentColor" d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10V1zm3 4H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2z"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><path fill="currentColor" d="M11 5h2v14h-2zM5 11h14v2H5z"/></symbol>
  <symbol id="i-unlock" viewBox="0 0 24 24"><path fill="currentColor" d="M18 8h-1V6a5 5 0 1 0-10 0h2a3 3 0 1 1 6 0v2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2z"/></symbol>
  <symbol id="i-download" viewBox="0 0 24 24"><path fill="currentColor" d="M5 20h14v-2H5v2zm7-18l-5 5h3v6h4V7h3l-5-5z"/></symbol>
  <symbol id="i-trash" viewBox="0 0 24 24"><path fill="currentColor" d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></symbol>
  <symbol id="i-first" viewBox="0 0 24 24"><path fill="currentColor" d="M6 6h2v12H6zM10 12l8 6V6z"/></symbol>
  <symbol id="i-prev" viewBox="0 0 24 24"><path fill="currentColor" d="M15 6v12l-8-6z"/></symbol>
  <symbol id="i-next" viewBox="0 0 24 24"><path fill="currentColor" d="M9 6v12l8-6z"/></symbol>
  <symbol id="i-last" viewBox="0 0 24 24"><path fill="currentColor" d="M16 6h2v12h-2zM6 6l8 6-8 6z"/></symbol>
</svg>

<!-- Add Lock -->
<form method="post" class="filterbar">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
  <input type="hidden" name="action" value="add">
  <div>
    <label style="display:block;font-size:.85rem;color:#555;margin-bottom:.15rem">Type</label>
    <select name="acct_type" style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px;">
      <option value="user" <?= $type==='user'?'selected':'' ?>>User (by email)</option>
      <option value="admin" <?= $type==='admin'?'selected':'' ?>>Admin PIN</option>
    </select>
  </div>
  <div>
    <label style="display:block;font-size:.85rem;color:#555;margin-bottom:.15rem">Identity</label>
    <input name="identity" type="text" value="<?= htmlspecialchars($type==='admin'?'':$q) ?>" placeholder="email or leave blank for admin"
           style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px;min-width:240px">
  </div>
  <div>
    <label style="display:block;font-size:.85rem;color:#555;margin-bottom:.15rem">Lock until</label>
    <input name="until" type="datetime-local" required
           style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px;">
  </div>
  <div>
    <label style="display:block;font-size:.85rem;color:#555;margin-bottom:.15rem">Reason (optional)</label>
    <input name="reason" type="text" maxlength="255" placeholder="e.g., chargeback or ToS violation"
           style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px;min-width:260px">
  </div>
  <div>
    <label style="display:block;font-size:.85rem;color:#555;margin-bottom:.15rem">&nbsp;</label>
    <button type="submit" style="padding:.45rem .9rem;border:1px solid #bbb;background:#f7f7f7;border-radius:8px;">Add lock</button>
  </div>
</form>

<!-- Toolbar: CSV + Purge -->
<div style="display:flex;gap:.5rem;align-items:center;margin:.25rem 0 .75rem;flex-wrap:wrap;">
  <a class="iconbtn" href="<?= htmlspecialchars(build_qs(['export'=>'csv'])) ?>" title="Export CSV" aria-label="Export CSV">
    <svg><use href="#i-download"/></svg>
  </a>
  <form method="post" onsubmit="return confirm('Purge expired locks?');">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
    <input type="hidden" name="action" value="purge_expired">
    <button class="iconbtn red" type="submit" title="Purge expired" aria-label="Purge expired">
      <svg><use href="#i-trash"/></svg>
    </button>
  </form>
</div>

<!-- Filters -->
<form method="get" class="filterbar">
  <div>
    <label style="display:block;font-size:.85rem;color:#555;margin-bottom:.15rem">Search</label>
    <input name="q" type="text" value="<?= htmlspecialchars($q) ?>" placeholder="identity or reason"
           style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px;min-width:240px">
  </div>
  <div>
    <label style="display:block;font-size:.85rem;color:#555;margin-bottom:.15rem">Type</label>
    <select name="type" style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px;">
      <option value="" <?= $type===''?'selected':'' ?>>All</option>
      <option value="user" <?= $type==='user'?'selected':'' ?>>User</option>
      <option value="admin" <?= $type==='admin'?'selected':'' ?>>Admin</option>
    </select>
  </div>
  <div>
    <label style="display:block;font-size:.85rem;color:#555;margin-bottom:.15rem">Show</label>
    <select name="active" style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px;">
      <option value="1" <?= $active==='1'?'selected':'' ?>>Active only</option>
      <option value="0" <?= $active==='0'?'selected':'' ?>>All</option>
    </select>
  </div>
  <div>
    <label style="display:block;font-size:.85rem;color:#555;margin-bottom:.15rem">Per page</label>
    <select name="perPage" style="padding:.35rem .5rem;border:1px solid #ccc;border-radius:6px;">
      <?php foreach ([10,25,50,100,200] as $n): ?>
        <option value="<?= $n ?>" <?= $perPage===$n?'selected':'' ?>><?= $n ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div style="display:flex;gap:.5rem;align-items:center;">
    <button type="submit" style="padding:.35rem .6rem;border:1px solid #ccc;border-radius:6px;background:#fff;">Apply</button>
    <a href="account-locks.php" style="padding:.35rem .6rem;border:1px solid #ccc;border-radius:6px;background:#fff;text-decoration:none;color:#333">Reset</a>
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
  .btn{padding:.35rem .6rem;border:1px solid #ccc;border-radius:6px;background:#fff;text-decoration:none;color:#333}
  .iconbtn{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1px solid #ddd;border-radius:.5rem;background:#fff;color:#222;text-decoration:none}
  .iconbtn svg{width:16px;height:16px}
  .iconbtn.badge{position:relative}
  .iconbtn.badge::after{content:attr(data-badge);position:absolute;top:-6px;right:-6px;font-size:.6rem;line-height:1;padding:.05rem .25rem;background:#0b5;color:#fff;border-radius:999px;border:1px solid #fff}
  .iconbtn.red{color:#a11212;border-color:#f3b4b4}
  .iconbtn.disabled{opacity:.45;pointer-events:none}
</style>

<?php if ($totalRows === 0): ?>
  <div style="padding:10px;border:1px dashed #ccc;background:#fafafa">No locks found.</div>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th><?= sort_link('identity','Identity',$sort,$dir) ?></th>
        <th><?= sort_link('type','Type',$sort,$dir) ?></th>
        <th><?= sort_link('until_at','Locked until',$sort,$dir) ?></th>
        <th><?= sort_link('created_at','Created',$sort,$dir) ?></th>
        <th>Reason</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $res->fetch_assoc()): ?>
        <?php
          $rowId = (int)$row['id'];
          $ident = (string)$row['identity'];
          $t     = (string)$row['type'];
          $until = (string)$row['until_at'];
          $leftS = max(0, strtotime($until) - time());
          $activeNow = $leftS > 0;
        ?>
        <tr>
          <td class="mono">
            <span id="ident-<?= $rowId ?>"><?= htmlspecialchars($ident) ?></span>
            <a class="iconbtn" href="#" onclick="copyIdentity(<?= $rowId ?>);return false;" title="Copy identity" aria-label="Copy identity"><svg><use href="#i-copy"/></svg></a>
          </td>
          <td class="mono"><?= htmlspecialchars($t) ?></td>
          <td class="mono nowrap"><?= htmlspecialchars($until) ?></td>
          <td class="mono nowrap"><?= htmlspecialchars((string)$row['created_at']) ?></td>
          <td><?= htmlspecialchars((string)($row['reason'] ?? '')) ?></td>
          <td>
            <!-- +7d -->
            <form class="inline" method="post" style="display:inline" onsubmit="return confirm('Extend <?= htmlspecialchars($ident) ?> by 7 days?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
              <input type="hidden" name="action" value="extend">
              <input type="hidden" name="identity" value="<?= htmlspecialchars($ident) ?>">
              <input type="hidden" name="acct_type" value="<?= htmlspecialchars($t) ?>">
              <input type="hidden" name="days" value="7">
              <button class="iconbtn badge" data-badge="+7d" type="submit" title="Extend by 7 days" aria-label="Extend by 7 days"><svg><use href="#i-plus"/></svg></button>
            </form>
            <!-- +30d -->
            <form class="inline" method="post" style="display:inline" onsubmit="return confirm('Extend <?= htmlspecialchars($ident) ?> by 30 days?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
              <input type="hidden" name="action" value="extend">
              <input type="hidden" name="identity" value="<?= htmlspecialchars($ident) ?>">
              <input type="hidden" name="acct_type" value="<?= htmlspecialchars($t) ?>">
              <input type="hidden" name="days" value="30">
              <button class="iconbtn badge" data-badge="+30d" type="submit" title="Extend by 30 days" aria-label="Extend by 30 days"><svg><use href="#i-plus"/></svg></button>
            </form>
            <!-- Unlock -->
            <form class="inline" method="post" style="display:inline" onsubmit="return confirm('Unlock <?= htmlspecialchars($ident) ?>?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
              <input type="hidden" name="action" value="clear">
              <input type="hidden" name="identity" value="<?= htmlspecialchars($ident) ?>">
              <input type="hidden" name="acct_type" value="<?= htmlspecialchars($t) ?>">
              <button class="iconbtn" type="submit" title="Unlock" aria-label="Unlock"><svg><use href="#i-unlock"/></svg></button>
            </form>

            <?php if ($activeNow): ?>
              <span class="badge act"><?= (int)floor($leftS/3600) ?>h left</span>
            <?php else: ?>
              <span class="badge" style="background:#e8fff0;border-color:#9bd3af">Expired</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <?php
    $isFirst = $page <= 1; $isLast = $page >= $totalPages;
    $firstHref = build_qs(['p'=>1]);
    $prevHref  = build_qs(['p'=> max(1, $page-1)]);
    $nextHref  = build_qs(['p'=> min($totalPages, $page+1)]);
    $lastHref  = build_qs(['p'=> $totalPages]);
  ?>
  <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin:.5rem 0">
    <span class="btn" style="background:#f7f7f7">Page <?= (int)$page ?> / <?= (int)$totalPages ?></span>

    <a class="iconbtn<?= $isFirst ? ' disabled' : '' ?>" href="<?= htmlspecialchars($firstHref) ?>" title="First page" aria-label="First page"><svg><use href="#i-first"/></svg></a>
    <a class="iconbtn<?= $isFirst ? ' disabled' : '' ?>" href="<?= htmlspecialchars($prevHref) ?>"  title="Previous page" aria-label="Previous page"><svg><use href="#i-prev"/></svg></a>
    <a class="iconbtn<?= $isLast  ? ' disabled' : '' ?>" href="<?= htmlspecialchars($nextHref) ?>"  title="Next page" aria-label="Next page"><svg><use href="#i-next"/></svg></a>
    <a class="iconbtn<?= $isLast  ? ' disabled' : '' ?>" href="<?= htmlspecialchars($lastHref) ?>"  title="Last page" aria-label="Last page"><svg><use href="#i-last"/></svg></a>
  </div>
<?php endif; ?>

<script>
function copyIdentity(rowId){
  const el = document.getElementById('ident-' + rowId);
  if (!el) return;
  const txt = el.textContent || el.value || '';
  navigator.clipboard.writeText(txt).then(()=>{}).catch(()=>{});
}
</script>

<?php
// Footer include (best-effort)
foreach ([
  __DIR__.'/admin-footer.php',
  __DIR__.'/_footer.php',
  __DIR__.'/partials/admin-footer.php',
  __DIR__.'/../includes/admin-footer.php',
  __DIR__.'/../includes/footer.php',
] as $p) { if (is_file($p)) { include $p; break; } }
