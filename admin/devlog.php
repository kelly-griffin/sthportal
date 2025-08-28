<?php
// admin/devlog.php — Toggle Table/Card views + search + CSV + actions
// with automatic migration from legacy `devlog` table (created_by/created_at/updated_at)
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-helpers.php';
require_once __DIR__ . '/../includes/toast-center.php';

if (function_exists('require_admin')) require_admin();  // allow any admin

$dbc  = admin_db();
$csrf = admin_csrf();

// original current URL for back links (cleaned of ephemeral flags below)
$backOrig = $_SERVER['REQUEST_URI'] ?? 'devlog.php';

// helper to strip ephemeral flags (msg/err/saved/deleted/ft/ft_err) from a URL
function _strip_flags_from_url(string $url): string {
  $p = parse_url($url);
  $query = [];
  if (!empty($p['query'])) {
    parse_str($p['query'], $query);
    unset($query['msg'], $query['err'], $query['saved'], $query['deleted'], $query['ft'], $query['ft_err']);
  }
  $rebuilt = ($p['path'] ?? 'devlog.php');
  if (!empty($query)) $rebuilt .= '?' . http_build_query($query);
  return $rebuilt;
}

// use a cleaned version for any back= links we render
$back = _strip_flags_from_url($backOrig);

/* ---------- helpers ---------- */
function tbl_exists(mysqli $db, string $name): bool {
  $name = $db->real_escape_string($name);
  $rs = $db->query("SHOW TABLES LIKE '{$name}'");
  return $rs && $rs->num_rows > 0;
}
function col_exists(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $rs = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return $rs && $rs->num_rows > 0;
}
function row_count(mysqli $db, string $table): int {
  $t = $db->real_escape_string($table);
  $rs = $db->query("SELECT COUNT(*) FROM `{$t}`");
  if(!$rs) return 0;
  [$n] = $rs->fetch_row();
  return (int)$n;
}

/* ---------- primary table ---------- */
$PRIMARY = 'devlog_entries';
$LEGACY  = 'devlog';

$dbc->query("CREATE TABLE IF NOT EXISTS `{$PRIMARY}` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  author VARCHAR(120) NOT NULL DEFAULT 'admin',
  tags VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

/* ---------- migrate legacy -> primary (once, safely) ---------- */
if (tbl_exists($dbc, $LEGACY)) {
  $primaryEmpty = row_count($dbc, $PRIMARY) === 0;
  $legacyRows   = row_count($dbc, $LEGACY);

  if ($legacyRows > 0 && $primaryEmpty) {
    // Column mapping for legacy table
    $authorCol  = col_exists($dbc, $LEGACY, 'author')     ? 'author'
                : (col_exists($dbc, $LEGACY, 'created_by') ? 'created_by' : null);

    $createdCol = col_exists($dbc, $LEGACY, 'created_at') ? 'created_at'
                : (col_exists($dbc, $LEGACY, 'created')    ? 'created'    : null);

    $updatedCol = col_exists($dbc, $LEGACY, 'updated_at') ? 'updated_at'
                : (col_exists($dbc, $LEGACY, 'updated')    ? 'updated'    : null);

    $authorExpr  = $authorCol  ? "`{$LEGACY}`.`{$authorCol}`" : "'admin'";
    $createdExpr = $createdCol ? "`{$LEGACY}`.`{$createdCol}`" : "NOW()";
    $updatedExpr = $updatedCol ? "`{$LEGACY}`.`{$updatedCol}`" : "NULL";

    // Use the legacy IDs so nothing duplicates; AUTO_INCREMENT will advance automatically
    $sql = "INSERT INTO `{$PRIMARY}` (id, title, body, author, tags, created_at, updated_at)
            SELECT `{$LEGACY}`.`id`,
                   `{$LEGACY}`.`title`,
                   `{$LEGACY}`.`body`,
                   {$authorExpr}  AS author,
                   `{$LEGACY}`.`tags`,
                   {$createdExpr} AS created_at,
                   {$updatedExpr} AS updated_at
            FROM `{$LEGACY}`";

    try {
      $dbc->begin_transaction();
      if (!$dbc->query($sql)) { throw new RuntimeException('Migration failed: '.$dbc->error); }
      $dbc->commit();
      // toast handled via ?msg in other actions; no local toast here to keep output clean
    } catch (Throwable $e) {
      $dbc->rollback();
      // swallow; list will still render; admin can re-run if needed
    }
  }
}

/* ---------- FULLTEXT availability on PRIMARY (ft_devlog) ---------- */
$HAS_FT = false;
if ($stmt = $dbc->prepare("SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = 'ft_devlog' AND INDEX_TYPE = 'FULLTEXT' LIMIT 1")) {
  $stmt->bind_param('s', $PRIMARY);
  $stmt->execute();
  $r = $stmt->get_result();
  $HAS_FT = ($r && $r->num_rows > 0);
  $stmt->close();
}

/* ---------- inputs ---------- */
$q       = trim((string)($_GET['q'] ?? ''));
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = max(10, min(200, (int)($_GET['pp'] ?? 25)));
$view    = strtolower((string)($_GET['view'] ?? 'card')); // 'card' | 'table'
$export  = (string)($_GET['export'] ?? '');

/* ---------- search ---------- */
$where = [];
$types = '';
$args  = [];
if ($q !== '') {
  if ($HAS_FT) {
    // Use FULLTEXT when index exists
    $where[] = "MATCH(title, body, tags, author) AGAINST (? IN NATURAL LANGUAGE MODE)";
    $types  .= 's';
    $args[]  = $q;
  } else {
    // Fallback to LIKE search
    $where[] = "(title LIKE CONCAT('%',?,'%') OR tags LIKE CONCAT('%',?,'%') OR author LIKE CONCAT('%',?,'%') OR body LIKE CONCAT('%',?,'%'))";
    $types  .= 'ssss';
    array_push($args, $q, $q, $q, $q);
  }
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ---------- count ---------- */
$stc = $dbc->prepare("SELECT COUNT(*) FROM `{$PRIMARY}` {$whereSql}");
if ($types !== '') $stc->bind_param($types, ...$args);
$stc->execute();
$total = (int)($stc->get_result()->fetch_row()[0] ?? 0);
$stc->close();

/* ---------- export CSV ---------- */
if ($export === 'csv') {
  $sql = "SELECT id, title, author, tags, created_at, updated_at, body
          FROM `{$PRIMARY}` {$whereSql}
          ORDER BY created_at DESC, id DESC";
  $st = $dbc->prepare($sql);
  if ($types !== '') $st->bind_param($types, ...$args);
  $st->execute();
  $res = $st->get_result();

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=devlog-'.date('Ymd-His').'.csv');

  $out = fopen('php://output', 'w');
  fputcsv($out, ['id','title','author','tags','created_at','updated_at','body']);
  while ($row = $res->fetch_assoc()) fputcsv($out, $row);
  fclose($out);
  exit;
}

/* ---------- page query ---------- */
$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT id, title, author, tags, created_at, updated_at, body
        FROM `{$PRIMARY}` {$whereSql}
        ORDER BY created_at DESC, id DESC
        LIMIT ? OFFSET ?";
$params = $args; $params[] = $perPage; $params[] = $offset;

$st = $dbc->prepare($sql);
if ($types !== '') {
  $t2 = $types . 'ii';
  $st->bind_param($t2, ...$params);
} else {
  $st->bind_param('ii', $perPage, $offset);
}
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

/* ---------- helpers ---------- */
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function qbuild(array $over = []): string {
  $p = $_GET;
  // do not propagate ephemeral flags
  unset($p['msg'], $p['err'], $p['saved'], $p['deleted'], $p['ft'], $p['ft_err'], $p['export']);
  foreach ($over as $k=>$v) { if ($v===null) unset($p[$k]); else $p[$k]=$v; }
  $q = http_build_query($p);
  return $q ? ('?' . $q) : '';
}
function chips(?string $tags): array {
  $tags = trim((string)$tags);
  if ($tags==='') return [];
  $parts = preg_split('/\s*,\s*/', $tags);
  $out=[]; foreach ($parts as $t) if ($t!=='') $out[]=$t;
  return $out;
}

/* ---------- header include ---------- */
$loadedHeader = false;
foreach ([
  __DIR__ . '/admin-header.php',
  __DIR__ . '/_header.php',
  __DIR__ . '/partials/admin-header.php',
  __DIR__ . '/../includes/admin-header.php',
  __DIR__ . '/../includes/header.php',
] as $p) { if (is_file($p)) { include $p; $loadedHeader = true; break; } }

if (!$loadedHeader) {
  echo "<!doctype html><meta charset='utf-8'><title>Devlog</title><style>body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:16px}</style>";
}
?>

<h1 style="display:flex;align-items:center;gap:.5rem">Devlog</h1>

<style>
  /* Page-specific layout only — buttons/filters/chips use global admin header styles */
  .toolbar{ display:flex; gap:.4rem; flex-wrap:wrap; align-items:center; justify-content:flex-end; margin:.45rem 0; }
  .muted{ color:#667; }

  .grid{ display:grid; gap:.55rem; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); }
  .card{ border:1px solid #ddd; border-radius:.6rem; background:#fff; overflow:hidden; }
  .card .hd{ display:flex; align-items:center; justify-content:space-between; padding:.45rem .55rem; border-bottom:1px solid #ddd; background:#fafafa; }
  .card .title{ font-weight:700; }
  .meta{ font-size:.82rem; color:#555; display:flex; gap:.5rem; flex-wrap:wrap; }
  .card .body{ padding:.5rem .55rem; white-space:pre-wrap; line-height:1.32; }

  .table{ width:100%; border-collapse:collapse; }
  th, td{ padding:.4rem .48rem; border-top:1px solid #ddd; text-align:left; vertical-align:top; }
  th{ position:sticky; top:0; background:#fafafa; z-index:1; color:#555; }
  .nowrap{ white-space:nowrap; }

  .actions{ display:flex; flex-wrap:wrap; gap:.25rem; }
</style>

<div style="display:flex; align-items:center; justify-content:space-between;">
  <div class="muted">Total: <strong><?= number_format($total) ?></strong></div>
  <div class="toolbar">
    <div class="view-toggle" role="tablist" aria-label="View mode">
      <a role="tab" class="<?= $view==='card'?'active':'' ?>" href="<?= h(qbuild(['view'=>'card','p'=>1])) ?>" aria-selected="<?= $view==='card'?'true':'false' ?>">Card</a>
      <a role="tab" class="<?= $view==='table'?'active':'' ?>" href="<?= h(qbuild(['view'=>'table','p'=>1])) ?>" aria-selected="<?= $view==='table'?'true':'false' ?>">Table</a>
    </div>
    <a class="btn" href="devlog-edit.php?back=<?= rawurlencode($back) ?>">New Entry</a>
    <a class="btn" href="<?= h(qbuild(['export'=>'csv'])) ?>">Export CSV</a>
    <?php if (!$HAS_FT): // show enable button only when missing ?>
      <a class="btn" href="devlog-index-create.php?csrf=<?= urlencode($csrf) ?>&back=<?= rawurlencode($back) ?>"
         onclick="return confirm('Create a full-text index now? This may take a moment.');">Enable Full-Text</a>
    <?php endif; ?>
    <?php if ($view === 'card'): ?>
      <a class="btn" id="btnExpandAll" href="#" onclick="expandAll();return false;">Expand All</a>
      <a class="btn" id="btnCollapseAll" href="#" onclick="collapseAll();return false;">Collapse All</a>
    <?php endif; ?>
  </div>
</div>

<form class="filters" method="get" action="">
  <div>
    <label>Search<?= $HAS_FT ? ' (full-text)' : '' ?></label>
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="title, tags, author, or body">
  </div>
  <div>
    <label>Per page</label>
    <select name="pp">
      <?php foreach ([10,25,50,100,200] as $pp): ?>
        <option value="<?= $pp ?>" <?= $perPage===$pp?'selected':'' ?>><?= $pp ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <input type="hidden" name="view" value="<?= h($view) ?>">
  <input type="hidden" name="p" value="1">
  <button class="btn" type="submit">Apply</button>
  <?php if ($q !== ''): ?>
    <a class="btn" href="devlog.php?view=<?= h($view) ?>">Reset</a>
  <?php endif; ?>
</form>

<?php
/* Pagination helpers */
$isFirst = $page <= 1;
$isLast  = $page >= $pages;
$firstHref = qbuild(['p'=>1]);
$prevHref  = qbuild(['p'=> max(1, $page-1)]);
$nextHref  = qbuild(['p'=> min($pages, $page+1)]);
$lastHref  = qbuild(['p'=> $pages]);
?>

<?php if ($view === 'table'): ?>

  <table class="table">
    <thead>
      <tr>
        <th class="nowrap">Created</th>
        <th class="nowrap">Updated</th>
        <th>Title</th>
        <th>Tags</th>
        <th class="nowrap">Author</th>
        <th>Body</th>
        <th class="nowrap">Details / Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="muted">No entries found.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <?php $chips = chips($r['tags'] ?? ''); $json = h(json_encode($r, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); ?>
        <tr>
          <td class="nowrap"><?= h((string)$r['created_at']) ?></td>
          <td class="nowrap"><?= h((string)($r['updated_at'] ?? '')) ?></td>
          <td>
            <div style="display:flex;gap:.35rem;align-items:center;flex-wrap:wrap;">
              <strong><?= h((string)$r['title']) ?></strong>
              <a class="btn" href="#" onclick="copyText('<?= h((string)$r['title']) ?>');return false;">Copy Title</a>
            </div>
          </td>
          <td>
            <div class="chips">
              <?php foreach ($chips as $c): ?><span class="chip"><?= h($c) ?></span><?php endforeach; ?>
            </div>
            <?php if ($chips): ?>
              <div style="margin-top:.25rem;">
                <a class="btn" href="#" onclick="copyText('<?= h(implode(', ', $chips)) ?>');return false;">Copy Tags</a>
              </div>
            <?php endif; ?>
          </td>
          <td class="nowrap"><?= h((string)$r['author']) ?></td>
          <td>
            <div id="body-<?= (int)$r['id'] ?>" style="max-height:9rem; overflow:auto; white-space:pre-wrap; border:1px solid #eee; border-radius:.5rem; padding:.5rem; background:#fff;">
              <?= nl2br(h((string)$r['body'])) ?>
            </div>
            <div style="margin-top:.25rem; display:flex; gap:.35rem; flex-wrap:wrap;">
              <a class="btn" href="#" onclick="copyText(document.getElementById('body-<?= (int)$r['id'] ?>').innerText);return false;">Copy Body</a>
              <a class="btn" href="#" onclick="copyText('<?= $json ?>');return false;">Copy JSON</a>
            </div>
          </td>
          <td class="nowrap">
            <div class="actions">
              <a class="btn" href="devlog-edit.php?id=<?= (int)$r['id'] ?>&back=<?= rawurlencode($back) ?>">Edit</a>
              <a class="btn" href="devlog-delete.php?id=<?= (int)$r['id'] ?>&csrf=<?= urlencode($csrf) ?>&back=<?= rawurlencode($back) ?>" onclick="return confirm('Delete this entry?');">Delete</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

<?php else: /* CARD VIEW */ ?>

  <div class="grid">
    <?php if (!$rows): ?>
      <div class="muted">No entries found.</div>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <?php $chips = chips($r['tags'] ?? ''); $json = h(json_encode($r, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); ?>
      <article class="card" data-card-id="<?= (int)$r['id'] ?>">
        <header class="hd">
          <div style="min-width:0;">
            <div class="title"><?= h((string)$r['title']) ?></div>
            <div class="meta">
              <span>By <strong><?= h((string)$r['author']) ?></strong></span>
              <span>Created <?= h((string)$r['created_at']) ?></span>
              <?php if (!empty($r['updated_at'])): ?><span>Updated <?= h((string)$r['updated_at']) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="actions">
            <a class="btn" href="#" onclick="copyText('<?= h((string)$r['title']) ?>');return false;">Copy Title</a>
            <a class="btn" href="#" onclick="copyText('<?= $json ?>');return false;">Copy JSON</a>
            <a class="btn" href="devlog-edit.php?id=<?= (int)$r['id'] ?>&back=<?= rawurlencode($back) ?>">Edit</a>
            <a class="btn" href="devlog-delete.php?id=<?= (int)$r['id'] ?>&csrf=<?= urlencode($csrf) ?>&back=<?= rawurlencode($back) ?>" onclick="return confirm('Delete this entry?');">Delete</a>
          </div>
        </header>
        <?php if ($chips): ?>
          <div style="padding:.5rem .55rem 0;">
            <div class="chips">
              <?php foreach ($chips as $c): ?><span class="chip"><?= h($c) ?></span><?php endforeach; ?>
            </div>
            <div style="margin:.35rem 0 0;">
              <a class="btn" href="#" onclick="copyText('<?= h(implode(', ', $chips)) ?>');return false;">Copy Tags</a>
            </div>
          </div>
        <?php endif; ?>
        <div class="body collapsible" data-collapsed="true" style="max-height:10rem; overflow:auto;">
          <?= nl2br(h((string)$r['body'])) ?>
        </div>
        <div style="display:flex; gap:.45rem; padding:.5rem .55rem .6rem;">
          <a class="btn" href="#" onclick="toggleBody(this);return false;">Expand</a>
          <a class="btn" href="#" onclick="copyText(this.closest('.card').querySelector('.body').innerText);return false;">Copy Body</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

<?php endif; ?>

<!-- Pagination -->
<div style="display:flex; gap:.4rem; align-items:center; justify-content:flex-end; margin-top:1rem; flex-wrap:wrap;">
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

<script>
  function copyText(txt){
    try{
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(txt);
      } else {
        var t=document.createElement('textarea');t.value=txt;document.body.appendChild(t);t.select();document.execCommand('copy');document.body.removeChild(t);
      }
    }catch(e){}
  }
  function toggleBody(btn){
    var card = btn.closest('.card');
    var body = card.querySelector('.body');
    var isCollapsed = body.getAttribute('data-collapsed') === 'true';
    if (isCollapsed) { body.style.maxHeight = 'none'; body.setAttribute('data-collapsed','false'); btn.textContent = 'Collapse'; }
    else { body.style.maxHeight = '10rem'; body.setAttribute('data-collapsed','true'); btn.textContent = 'Expand'; }
  }
  function expandAll(){
    document.querySelectorAll('.card .body').forEach(function(b){ b.style.maxHeight='none'; b.setAttribute('data-collapsed','false'); });
    document.querySelectorAll('.card a.btn').forEach(function(a){ if (a.textContent.trim()==='Expand') a.textContent='Collapse'; });
  }
  function collapseAll(){
    document.querySelectorAll('.card .body').forEach(function(b){ b.style.maxHeight='10rem'; b.setAttribute('data-collapsed','true'); });
    document.querySelectorAll('.card a.btn').forEach(function(a){ if (a.textContent.trim()==='Collapse') a.textContent='Expand'; });
  }
</script>
<script>
/* Hotkey: 'd' = New Devlog Entry (ignored while typing in inputs) */
(function () {
  function typingTarget(e) {
    const t = e.target || {};
    const tag = (t.tagName || '').toLowerCase();
    return t.isContentEditable || tag === 'input' || tag === 'textarea' || tag === 'select';
  }
  document.addEventListener('keydown', function (e) {
    if (e.defaultPrevented) return;
    if (typingTarget(e)) return;
    if ((e.key || '').toLowerCase() === 'd') {
      e.preventDefault();
      var back = encodeURIComponent(location.href);
      window.location = 'devlog-edit.php?back=' + back;
    }
  });

  // Clean the address bar of ephemeral flags without reloading
  (function(){
    try {
      var u = new URL(window.location.href);
      ['msg','err','saved','deleted','ft','ft_err'].forEach(function(k){ u.searchParams.delete(k); });
      var clean = u.pathname + (u.search ? u.search : '');
      if (clean !== window.location.pathname + window.location.search) {
        history.replaceState(null, '', clean);
      }
    } catch(e) {}
  })();
})();
</script>

<?php
if (isset($st) && $st instanceof mysqli_stmt) $st->close();
if (!$loadedHeader) echo "</body></html>";
