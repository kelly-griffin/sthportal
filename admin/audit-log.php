<?php
// admin/audit-log.php — searchable/sortable Audit Log with CSV export + Copy/Expand

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/session-guard.php';
require_admin();

// ----- DB handle -----
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('Database handle not initialized.'); }

// ----- Inputs -----
$q       = trim((string)($_GET['q'] ?? ''));
$eventF  = trim((string)($_GET['event'] ?? ''));
$actorF  = trim((string)($_GET['actor'] ?? ''));
$ipF     = trim((string)($_GET['ip'] ?? ''));
$sort    = (string)($_GET['sort'] ?? 'created_at');
$dir     = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = max(10, min(200, (int)($_GET['perPage'] ?? 50)));
$export  = trim((string)($_GET['export'] ?? '')); // "csv" to export

$sortMap = [
  'created_at' => 'created_at',
  'event'      => 'event',
  'actor'      => 'actor',
  'ip'         => 'ip',
  'id'         => 'id'
];
$orderBy = $sortMap[$sort] ?? 'created_at';

// ----- Helpers -----
function build_qs(array $overrides = []): string {
  $params = $_GET;
  foreach ($overrides as $k=>$v) { if ($v === null) unset($params[$k]); else $params[$k]=$v; }
  return '?' . http_build_query($params);
}
function sort_link(string $key, string $label, string $currentSort, string $currentDir): string {
  $is = ($currentSort === $key);
  $dir = $is && $currentDir === 'asc' ? 'desc' : 'asc';
  $arrow = $is ? ($currentDir === 'asc' ? ' ▲' : ' ▼') : '';
  $href = build_qs(['sort'=>$key,'dir'=>$dir,'p'=>1]);
  return '<a href="'.htmlspecialchars($href).'">'.htmlspecialchars($label).$arrow.'</a>';
}
function is_json(string $s): bool {
  if ($s === '') return false;
  json_decode($s, true);
  return json_last_error() === JSON_ERROR_NONE;
}

// ----- Filters -----
$where  = '1';
$types  = '';
$params = [];

if ($q !== '') {
  $like = '%'.$q.'%';
  $where .= ' AND (event LIKE ? OR actor LIKE ? OR ip LIKE ? OR details LIKE ?)';
  array_push($params, $like, $like, $like, $like);
  $types .= 'ssss';
}
if ($eventF !== '') { $where .= ' AND event = ?';  $params[]=$eventF; $types.='s'; }
if ($actorF !== '') { $where .= ' AND actor = ?';  $params[]=$actorF; $types.='s'; }
if ($ipF !== '')    { $where .= ' AND ip = ?';     $params[]=$ipF;    $types.='s'; }

// ----- CSV Export (same filters/sort, no pagination) -----
if ($export === 'csv') {
  $sql = "SELECT id, created_at, event, actor, ip, details FROM audit_log
          WHERE $where
          ORDER BY $orderBy ".strtoupper($dir);
  if (!$stmt = $dbc->prepare($sql)) { http_response_code(500); exit('Export prepare failed.'); }
  if ($types !== '') $stmt->bind_param($types, ...$params);
  $stmt->execute(); $res = $stmt->get_result();

  header('Content-Type: text/csv; charset=utf-8');
  $fname = 'audit_log_' . date('Ymd_His') . '.csv';
  header('Content-Disposition: attachment; filename="'.$fname.'"');

  $out = fopen('php://output', 'w');
  fputcsv($out, ['id','created_at','event','actor','ip','details']);
  while ($row = $res->fetch_assoc()) {
    fputcsv($out, [
      (int)$row['id'],
      (string)$row['created_at'],
      (string)$row['event'],
      (string)($row['actor'] ?? ''),
      (string)($row['ip'] ?? ''),
      (string)$row['details']
    ]);
  }
  fclose($out);
  exit;
}

// ----- Counts -----
$sqlCount = "SELECT COUNT(*) FROM audit_log WHERE $where";
$stmt = $dbc->prepare($sqlCount);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute(); $stmt->bind_result($totalRows); $stmt->fetch(); $stmt->close();

$totalRows  = (int)$totalRows;
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// ----- Query (paged) -----
$sql = "SELECT id, created_at, event, actor, ip, details
        FROM audit_log
        WHERE $where
        ORDER BY $orderBy ".strtoupper($dir)."
        LIMIT ? OFFSET ?";
$stmt = $dbc->prepare($sql);
if ($types !== '') { $types2=$types.'ii'; $params2=[...$params,$perPage,$offset]; $stmt->bind_param($types2, ...$params2); }
else               { $stmt->bind_param('ii', $perPage, $offset); }
$stmt->execute(); $res = $stmt->get_result();

// ---------- Header include ----------
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/header-admin.php', __DIR__.'/header.php', __DIR__.'/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) echo "<!doctype html><meta charset='utf-8'><title>Login Attempts</title><body style='font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial; margin:16px'>";
?>
<h1>Audit Log</h1>

<form method="get" class="filters" style="margin:.6rem 0">
  <div>
    <label>Search</label>
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="event, actor, ip, details…">
  </div>
  <div>
    <label>Event</label>
    <input type="text" name="event" value="<?= htmlspecialchars($eventF) ?>" placeholder="e.g. user_login">
  </div>
  <div>
    <label>Actor</label>
    <input type="text" name="actor" value="<?= htmlspecialchars($actorF) ?>" placeholder="email/user">
  </div>
  <div>
    <label>IP</label>
    <input type="text" name="ip" value="<?= htmlspecialchars($ipF) ?>" placeholder="x.x.x.x">
  </div>
  <div>
    <label>Per page</label>
    <select name="perPage">
      <?php foreach ([10,25,50,100,200] as $n): ?>
        <option value="<?= $n ?>" <?= $perPage===$n?'selected':'' ?>><?= $n ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div style="margin-left:auto;display:inline-flex;gap:.5rem;align-items:center;">
    <a class="btn" href="<?= htmlspecialchars(build_qs(['export'=>'csv','p'=>null,'perPage'=>null])) ?>">Export CSV</a>
    <span class="chip">Total: <?= (int)$totalRows ?></span>
    <button type="button" class="btn" id="expandAll">Expand All</button>
    <button type="button" class="btn" id="collapseAll">Collapse All</button>
  </div>
</form>


<style>
  table{width:100%;border-collapse:collapse;margin:.6rem 0}
  th,td{padding:.5rem;border-bottom:1px solid #eee;vertical-align:top}
  th{text-align:left;background:#fafafa}
  .chip{display:inline-block;padding:.15rem .5rem;border:1px solid #ccc;border-radius:999px;background:#f6f6f6}
  .json{white-space:pre; max-height:160px; overflow:auto; border:1px solid #eee; border-radius:6px; padding:6px; background:#fafafa}
  .json.expanded{max-height:none}
  .mono{font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:.9rem}
  .nowrap{white-space:nowrap}
  /* no .btn / .copybtn styles here – inherit from admin-header */
</style>

<?php if ($totalRows === 0): ?>
  <div style="padding:10px;border:1px dashed #ccc;background:#fafafa">No audit entries found.</div>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th><?= sort_link('created_at','When',$sort,$dir) ?></th>
        <th><?= sort_link('event','Event',$sort,$dir) ?></th>
        <th><?= sort_link('actor','Actor',$sort,$dir) ?></th>
        <th><?= sort_link('ip','IP',$sort,$dir) ?></th>
        <th><?= sort_link('id','ID',$sort,$dir) ?></th>
        <th>Details</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($row = $res->fetch_assoc()):
      $det = (string)($row['details'] ?? '');
      $pretty = $det;
      if (is_json($det)) $pretty = json_encode(json_decode($det, true), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    ?>
      <tr>
        <td class="nowrap mono"><?= htmlspecialchars((string)$row['created_at']) ?></td>
        <td class="mono"><?= htmlspecialchars((string)$row['event']) ?></td>
        <td class="mono"><?= htmlspecialchars((string)($row['actor'] ?? '')) ?></td>
        <td class="mono"><?= htmlspecialchars((string)($row['ip'] ?? '')) ?></td>
        <td class="mono"><?= (int)$row['id'] ?></td>
        <td>
          <button class="copybtn js-toggle" type="button">Expand</button>
          <button class="copybtn js-copy" data-copy="<?= htmlspecialchars($pretty) ?>" type="button">Copy JSON</button>
          <pre class="json"><?= htmlspecialchars($pretty) ?></pre>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

  <?php $prev = max(1, $page-1); $next = min($totalPages, $page+1); ?>
  <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;margin:.6rem 0">
    <a href="<?= htmlspecialchars(build_qs(['p'=>1])) ?>">« First</a>
    <a href="<?= htmlspecialchars(build_qs(['p'=>$prev])) ?>">‹ Prev</a>
    <span class="chip">Page <?= (int)$page ?> / <?= (int)$totalPages ?></span>
    <a href="<?= htmlspecialchars(build_qs(['p'=>$next])) ?>">Next ›</a>
    <a href="<?= htmlspecialchars(build_qs(['p'=>$totalPages])) ?>">Last »</a>
  </div>
<?php endif; ?>

<?php if (!$loadedHeader) echo "</body></html>"; ?>

<script>
document.addEventListener('click', function(e){
  var c = e.target.closest('.js-copy');
  if (c) {
    var txt = c.getAttribute('data-copy') || '';
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(txt);
    else window.prompt('Copy:', txt);
  }
  var t = e.target.closest('.js-toggle');
  if (t) {
    var pre = t.parentElement.querySelector('pre.json');
    if (pre) {
      pre.classList.toggle('expanded');
      t.textContent = pre.classList.contains('expanded') ? 'Collapse' : 'Expand';
    }
  }
});
(function(){
  var expAll = document.getElementById('expandAll');
  var colAll = document.getElementById('collapseAll');
  function setAll(open) {
    document.querySelectorAll('pre.json').forEach(function(pre){
      if (open) pre.classList.add('expanded'); else pre.classList.remove('expanded');
    });
    document.querySelectorAll('.js-toggle').forEach(function(btn){
      btn.textContent = open ? 'Collapse' : 'Expand';
    });
  }
  if (expAll) expAll.addEventListener('click', function(){ setAll(true); });
  if (colAll) colAll.addEventListener('click', function(){ setAll(false); });
})();
</script>