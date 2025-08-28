<?php
// admin/licenses.php — icons matching Users + centered toasts + CSV + sort/filter/paging
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-helpers.php';

if (function_exists('require_admin')) require_admin();
if (function_exists('require_perm'))  require_perm('manage_licenses');

$dbc  = admin_db();
$csrf = admin_csrf();
$back = $_SERVER['REQUEST_URI'] ?? 'licenses.php';

// inputs
$q        = trim((string)($_GET['q'] ?? ''));
$status   = (string)($_GET['status'] ?? '');
$expiry   = (string)($_GET['expiry'] ?? ''); // '', 'expired', 'expiring', 'valid'
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = min(200, max(10, (int)($_GET['perPage'] ?? 25)));
$export   = (string)($_GET['export'] ?? '');
$msg      = trim((string)($_GET['msg'] ?? ''));
$err      = trim((string)($_GET['err'] ?? ''));

// sorting
$sortable = [
  'id' => 'l.id',
  'portal_id' => 'l.portal_id',
  'license_key' => 'l.license_key',
  'licensed_to' => 'l.licensed_to',
  'email' => 'l.email',
  'status' => 'l.status',
  'registered_domain' => 'l.registered_domain',
  'expires_at' => 'l.expires_at',
  'last_check' => 'l.last_check',
  'created_at' => 'l.created_at',
  'updated_at' => 'l.updated_at',
];
$sort = (string)($_GET['sort'] ?? 'id');
$dir  = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$orderBy = $sortable[$sort] ?? 'l.id';

// filters
$where = [];
$types = '';
$args  = [];
if ($q !== '') {
  $where[] = "(l.portal_id LIKE CONCAT('%', ?, '%')
           OR  l.license_key LIKE CONCAT('%', ?, '%')
           OR  l.licensed_to LIKE CONCAT('%', ?, '%')
           OR  l.email LIKE CONCAT('%', ?, '%')
           OR  l.registered_domain LIKE CONCAT('%', ?, '%'))";
  $types .= 'sssss';
  array_push($args, $q, $q, $q, $q, $q);
}
if ($status !== '' && in_array($status, ['active','demo','blocked'], true)) {
  $where[] = "l.status = ?";
  $types  .= 's';
  $args[]  = $status;
}
$now = (new DateTimeImmutable('now', new DateTimeZone('America/Toronto')))->format('Y-m-d H:i:s');
if ($expiry === 'expired') {
  $where[] = "(l.expires_at IS NOT NULL AND l.expires_at < ?)";
  $types  .= 's';
  $args[]  = $now;
} elseif ($expiry === 'expiring') {
  $soon = (new DateTimeImmutable('now', new DateTimeZone('America/Toronto')))->modify('+30 days')->format('Y-m-d H:i:s');
  $where[] = "(l.expires_at IS NOT NULL AND l.expires_at BETWEEN ? AND ?)";
  $types  .= 'ss';
  array_push($args, $now, $soon);
} elseif ($expiry === 'valid') {
  $where[] = "(l.expires_at IS NULL OR l.expires_at >= ?)";
  $types  .= 's';
  $args[]  = $now;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// count
$countSql = "SELECT COUNT(*) AS c FROM licenses l $whereSql";
$stc = $dbc->prepare($countSql);
if ($types !== '') $stc->bind_param($types, ...$args);
$stc->execute();
$total = (int)($stc->get_result()->fetch_assoc()['c'] ?? 0);
$stc->close();

// CSV
if ($export === 'csv') {
  $sql = "SELECT l.id, l.portal_id, l.license_key, l.licensed_to, l.email, l.status,
                 l.registered_domain, l.expires_at, l.last_check, l.created_at, l.updated_at, l.notes
          FROM licenses l
          $whereSql
          ORDER BY $orderBy " . strtoupper($dir);
  $stmt = $dbc->prepare($sql);
  if ($types !== '') $stmt->bind_param($types, ...$args);
  $stmt->execute();
  $res = $stmt->get_result();

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="licenses-' . date('Ymd-His') . '.csv"');

  $out = fopen('php://output', 'w');
  fputcsv($out, ['id','portal_id','license_key','licensed_to','email','status','registered_domain','expires_at','last_check','created_at','updated_at','notes']);
  while ($row = $res->fetch_assoc()) fputcsv($out, $row);
  fclose($out); exit;
}

// page query
$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT l.id, l.portal_id, l.license_key, l.licensed_to, l.email, l.status,
               l.registered_domain, l.expires_at, l.last_check, l.created_at, l.updated_at, l.notes
        FROM licenses l
        $whereSql
        ORDER BY $orderBy " . strtoupper($dir) . "
        LIMIT ? OFFSET ?";
$types2 = $types . 'ii';
$params = $args; $params[] = $perPage; $params[] = $offset;
$stmt = $dbc->prepare($sql);
$stmt->bind_param($types2, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// helpers
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function qBuild(array $merge): string { $base = $_GET; foreach ($merge as $k=>$v){ if($v===null) unset($base[$k]); else $base[$k]=$v; } return '?' . http_build_query($base); }
function rel(?string $ts): string {
  if (!$ts) return '';
  try {
    $dt = new DateTimeImmutable($ts); $now = new DateTimeImmutable('now');
    $d = $now->getTimestamp() - $dt->getTimestamp(); $a = abs($d);
    if ($a < 60) return $d >= 0 ? 'just now' : 'in moments';
    if ($a < 3600) return ($m=(int)floor($a/60)) . ($d>=0?" min ago":" min");
    if ($a < 86400) return ($h=(int)floor($a/3600)) . ($d>=0?" hr ago":" hr");
    $days = (int)floor($a/86400); return $d >= 0 ? "$days d ago" : "in $days d";
  } catch(Throwable $e) { return (string)$ts; }
}
function shortKey(string $k): string { return strlen($k)<=10 ? $k : substr($k,0,4).'…'.substr($k,-4); }

// header include
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/_header.php', __DIR__.'/partials/admin-header.php', __DIR__.'/../includes/admin-header.php', __DIR__.'/../includes/header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
require_once __DIR__ . '/../includes/toast-center.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Licenses</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Icon sprite (same set as Users) -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none;">
  <symbol id="i-copy" viewBox="0 0 24 24"><path fill="currentColor" d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10V1zm3 4H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2z"/></symbol>
  <symbol id="i-edit" viewBox="0 0 24 24"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z"/><path fill="currentColor" d="M20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><path fill="currentColor" d="M11 5h2v14h-2zM5 11h14v2H5z"/></symbol>
  <symbol id="i-check-circle" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1 15l-4-4 1.4-1.4L11 13.2l5.6-5.6L18 9l-7 8z"/></symbol>
  <symbol id="i-x-circle" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM8.7 8.7L11 11l2.3-2.3 1.4 1.4L12.4 12.4l2.3 2.3-1.4 1.4L11 13.8l-2.3 2.3-1.4-1.4 2.3-2.3-2.3-2.3z"/></symbol>
  <symbol id="i-download" viewBox="0 0 24 24"><path fill="currentColor" d="M5 20h14v-2H5v2zm7-18l-5 5h3v6h4V7h3l-5-5z"/></symbol>
</svg>

<style>
  :root { --line:#ddd; --ink:#222; --muted:#667; }
  body { <?php if(!$loadedHeader): ?>font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu;margin:16px;<?php endif; ?> }
  .container { margin: 0 1.25rem 1.25rem; }
  .card { background:#fff; border:1px solid var(--line); border-radius:.75rem; }
  .content { padding:1rem; }
  table { width:100%; border-collapse:collapse; }
  th, td { padding:.6rem .6rem; border-top:1px solid var(--line); vertical-align:top; text-align:left; }
  th { background:#fafafa; color:var(--muted); font-weight:600; position:sticky; top:0; }
  tr:hover td { background:#fcfcff; }
  .muted { color:var(--muted); font-size:.88rem; }
  .badge { display:inline-block; padding:.15rem .4rem; border:1px solid #ddd; border-radius:.5rem; font-size:.8rem; }
  /* Keep the quick-extend “6m/12m/24m” bubbles */
  .iconbtn.badge{ position:relative; }
  .iconbtn.badge::after{
    content: attr(data-badge);
    position:absolute; top:-6px; right:-6px;
    font-size:.6rem; line-height:1; padding:.05rem .25rem;
    background:#0b5; color:#fff; border-radius:999px; border:1px solid #fff;
  }
  .iconbtn.red   { color:#a11212; }
  .iconbtn.green { color:#0a7d2f; }
</style>
</head>
<body>
<div class="container">
  <div style="display:flex;align-items:center;justify-content:space-between;margin:1rem 0;">
    <div class="h1" style="font-size:1.25rem;font-weight:700;">Licenses</div>
    <div class="toolbar" style="display:flex; gap:.5rem; flex-wrap:wrap;">
      <a class="btn" href="<?= h(qBuild(['export'=>'csv'])) ?>" title="Export CSV">
        <svg><use href="#i-download"/></svg> Export
      </a>
      <a class="btn" href="license-edit.php" title="Create new license">
        <svg><use href="#i-edit"/></svg> New
      </a>
    </div>
  </div>

  <div class="card">
    <div class="content">
      <form class="filters" method="get" action="">
        <input type="text" name="q" placeholder="Search id/key/name/email/domain…" value="<?= h($q) ?>">
        <select name="status">
          <option value="">Any status</option>
          <?php foreach (['active','demo','blocked'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $status===$opt?'selected':'' ?>><?= ucfirst($opt) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="expiry">
          <option value="">Any expiry</option>
          <option value="valid" <?= $expiry==='valid'?'selected':'' ?>>Valid / no expiry</option>
          <option value="expiring" <?= $expiry==='expiring'?'selected':'' ?>>Expiring (≤30d)</option>
          <option value="expired" <?= $expiry==='expired'?'selected':'' ?>>Expired</option>
        </select>
        <select name="perPage">
          <?php foreach ([25,50,100,200] as $pp): ?>
            <option value="<?= $pp ?>" <?= $perPage===$pp?'selected':'' ?>><?= $pp ?>/page</option>
          <?php endforeach; ?>
        </select>
        <div style="display:flex; gap:.5rem; justify-content:flex-end;">
          <button class="btn" type="submit" title="Apply filters">Apply</button>
          <a class="btn" href="licenses.php" title="Reset filters">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card" style="margin-top:1rem;">
    <div class="content" style="padding:0;">
      <table>
        <thead>
          <tr>
            <?php
              function thSort(string $label, string $key, string $current, string $dir): void {
                $nextDir = ($current === $key && $dir === 'asc') ? 'desc' : 'asc';
                $qs = $_GET; $qs['sort'] = $key; $qs['dir'] = $nextDir;
                $href = '?' . http_build_query($qs);
                $arrow = ($current === $key) ? ($dir === 'asc' ? '↑' : '↓') : '';
                echo '<th><a href="'.h($href).'" style="color:inherit;text-decoration:none;">'.h($label).' '.h($arrow).'</a></th>';
              }
            ?>
            <?php thSort('ID', 'id', $sort, $dir); ?>
            <?php thSort('Portal', 'portal_id', $sort, $dir); ?>
            <?php thSort('Key', 'license_key', $sort, $dir); ?>
            <?php thSort('Licensed To', 'licensed_to', $sort, $dir); ?>
            <?php thSort('Email', 'email', $sort, $dir); ?>
            <?php thSort('Status', 'status', $sort, $dir); ?>
            <?php thSort('Domain', 'registered_domain', $sort, $dir); ?>
            <?php thSort('Expires', 'expires_at', $sort, $dir); ?>
            <?php thSort('Last Check', 'last_check', $sort, $dir); ?>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="10" class="muted" style="padding:1rem;">No results.</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $expired = ($r['expires_at'] && strtotime((string)$r['expires_at']) < time());
              $expSoon = ($r['expires_at'] && strtotime((string)$r['expires_at']) < strtotime('+30 days') && !$expired);
              $id = (int)$r['id'];
              $backUrl = 'license-action.php?back=' . rawurlencode($back) . '&csrf=' . urlencode($csrf);
            ?>
            <tr>
              <td><?= $id ?></td>
              <td>
                <div><?= h($r['portal_id']) ?></div>
                <div class="muted"><?= h($r['created_at']) ?></div>
              </td>
              <td title="<?= h($r['license_key']) ?>">
                <span id="key-full-<?= $id ?>" style="display:none;"><?= h($r['license_key']) ?></span>
                <span class="mono"><?= h(shortKey($r['license_key'])) ?></span>
                <a class="iconbtn" href="#" title="Copy license key" aria-label="Copy license key" onclick="copyKey(<?= $id ?>);return false;">
                  <svg><use href="#i-copy"/></svg>
                </a>
              </td>
              <td><?= h($r['licensed_to']) ?></td>
              <td><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a></td>
              <td>
                <?php
                  $s = (string)$r['status']; 
                  $color = $s==='blocked' ? '#a11212' : ($s==='demo' ? '#915a00' : '#0a7d2f');
                ?>
                <span class="badge" style="background:#fff;border-color:#ddd;color:<?= h($color) ?>"><?= h($s) ?></span>
              </td>
              <td><?= h($r['registered_domain']) ?></td>
              <td>
                <div><?= h($r['expires_at'] ?? '') ?></div>
                <?php if ($r['expires_at']): ?>
                  <div class="muted">
                    <?= h(rel($r['expires_at'])) ?>
                    <?php if ($expired): ?><span class="badge">expired</span><?php elseif ($expSoon): ?><span class="badge">expiring</span><?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <div><?= h($r['last_check'] ?? '') ?></div>
                <div class="muted"><?= $r['updated_at'] ? 'upd '.h(rel($r['updated_at'])) : '' ?></div>
              </td>
              <td style="white-space:nowrap;">
                <!-- Edit -->
                <a class="iconbtn" href="license-edit.php?id=<?= $id ?>&back=<?= rawurlencode($back) ?>" title="Edit license" aria-label="Edit license">
                  <svg><use href="#i-edit"/></svg>
                </a>
                <!-- Quick extend -->
                <a class="iconbtn badge" data-badge="6m"
                   href="<?= h($backUrl . '&act=extend&id='.$id.'&months=6') ?>"
                   title="Extend by 6 months" aria-label="Extend by 6 months">
                   <svg><use href="#i-plus"/></svg>
                </a>
                <a class="iconbtn badge" data-badge="12m"
                   href="<?= h($backUrl . '&act=extend&id='.$id.'&months=12') ?>"
                   title="Extend by 12 months" aria-label="Extend by 12 months">
                   <svg><use href="#i-plus"/></svg>
                </a>
                <a class="iconbtn badge" data-badge="24m"
                   href="<?= h($backUrl . '&act=extend&id='.$id.'&months=24') ?>"
                   title="Extend by 24 months" aria-label="Extend by 24 months">
                   <svg><use href="#i-plus"/></svg>
                </a>
                <!-- Block / Unblock -->
                <?php if ($r['status'] === 'blocked'): ?>
                  <a class="iconbtn green"
                     href="<?= h($backUrl . '&act=unblock&id='.$id) ?>"
                     title="Unblock license" aria-label="Unblock license">
                    <svg><use href="#i-check-circle"/></svg>
                  </a>
                <?php else: ?>
                  <a class="iconbtn red"
                     href="<?= h($backUrl . '&act=block&id='.$id) ?>"
                     title="Block license" aria-label="Block license">
                    <svg><use href="#i-x-circle"/></svg>
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php
        $isFirst = $page <= 1;
        $isLast  = $page >= $pages;
        $firstHref = qBuild(['page'=>1]);
        $prevHref  = qBuild(['page'=> max(1, $page-1)]);
        $nextHref  = qBuild(['page'=> min($pages, $page+1)]);
        $lastHref  = qBuild(['page'=> $pages]);
      ?>
      <div style="display:flex; gap:.5rem; padding:1rem; align-items:center; justify-content:flex-end;">
        <div class="muted">Total: <?= number_format($total) ?></div>
        <span class="btn" title="Page <?= $page ?> of <?= $pages ?>">Page <?= $page ?> / <?= $pages ?></span>

        <a class="iconbtn<?= $isFirst ? ' disabled' : '' ?>" href="<?= h($firstHref) ?>" title="First page" aria-label="First page">
          <svg viewBox="0 0 24 24"><path fill="currentColor" d="M6 6h2v12H6zM10 12l8 6V6z"/></svg>
        </a>
        <a class="iconbtn<?= $isFirst ? ' disabled' : '' ?>" href="<?= h($prevHref) ?>" title="Previous page" aria-label="Previous page">
          <svg viewBox="0 0 24 24"><path fill="currentColor" d="M15 6v12l-8-6z"/></svg>
        </a>
        <a class="iconbtn<?= $isLast ? ' disabled' : '' ?>" href="<?= h($nextHref) ?>" title="Next page" aria-label="Next page">
          <svg viewBox="0 0 24 24"><path fill="currentColor" d="M9 6v12l8-6z"/></svg>
        </a>
        <a class="iconbtn<?= $isLast ? ' disabled' : '' ?>" href="<?= h($lastHref) ?>" title="Last page" aria-label="Last page">
          <svg viewBox="0 0 24 24"><path fill="currentColor" d="M16 6h2v12h-2zM6 6l8 6-8 6z"/></svg>
        </a>
      </div>
    </div>
  </div>
</div>

<script>
// copy key to clipboard
function copyKey(id){
  var el = document.getElementById('key-full-'+id);
  if(!el) return;
  var txt = el.textContent || el.value || '';
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(txt).catch(function(){});
  } else {
    var t = document.createElement('textarea'); t.value = txt; document.body.appendChild(t);
    t.select(); try{ document.execCommand('copy'); }catch(e){} document.body.removeChild(t);
  }
}
</script>
</body>
</html>
