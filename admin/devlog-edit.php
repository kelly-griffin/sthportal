<?php
// admin/devlog-edit.php — editor with live Markdown preview, autosave, tag chips, Ctrl+Enter
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-helpers.php';
require_once __DIR__ . '/../includes/toast-center.php';

if (function_exists('require_admin')) require_admin();
if (function_exists('require_perm')) @require_perm('manage_devlog');

$dbc  = admin_db();
$csrf = admin_csrf();

// back URL sanitize
$rawBack = (string)($_GET['back'] ?? $_POST['back'] ?? '');
$backDec = rawurldecode($rawBack);
$back    = function_exists('sanitize_admin_path') ? sanitize_admin_path($backDec) : $backDec;
if ($back === '' || stripos($back, 'admin/') === false) $back = 'devlog.php';

$PRIMARY = 'devlog_entries';
$id      = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// redirect helper (adds msg=Saved!)
function devlog_next_after_save(string $back): string {
  $p = parse_url($back);
  $q = [];
  if (!empty($p['query'])) { parse_str($p['query'], $q); }
  unset($q['msg'], $q['err'], $q['saved'], $q['deleted']);
  $rebuilt = ($p['path'] ?? 'devlog.php');
  if (!empty($q)) $rebuilt .= '?' . http_build_query($q);
  $sep = (strpos($rebuilt,'?') !== false) ? '&' : '?';
  return $rebuilt . $sep . 'msg=' . rawurlencode('Saved!');
}

// handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tok = (string)($_POST['csrf'] ?? '');
  if (!hash_equals($csrf, $tok)) {
    header('Location: ' . $back);
    exit;
  }

  $title = trim((string)($_POST['title'] ?? ''));
  $tags  = trim((string)($_POST['tags'] ?? ''));
  $body  = (string)($_POST['body'] ?? '');

  // author: prefer session user name/email; fallback to 'admin'
  $author = 'admin';
  if (!empty($_SESSION['user'])) {
    $u = $_SESSION['user'];
    $author = (string)($u['name'] ?? $u['email'] ?? 'admin');
  }

  if ($id > 0) {
    $st = $dbc->prepare("UPDATE `{$PRIMARY}` SET title=?, body=?, tags=?, author=? WHERE id=?");
    $st->bind_param('ssssi', $title, $body, $tags, $author, $id);
    $st->execute();
    $st->close();
  } else {
    $st = $dbc->prepare("INSERT INTO `{$PRIMARY}` (title, body, tags, author) VALUES (?,?,?,?)");
    $st->bind_param('ssss', $title, $body, $tags, $author);
    $st->execute();
    $id = (int)$dbc->insert_id;
    $st->close();
  }

  // bounce back with toast
  header('Location: ' . devlog_next_after_save($back));
  exit;
}

// load record for edit (or defaults)
$row = [
  'id'    => $id,
  'title' => '',
  'tags'  => '',
  'body'  => '',
  'author'=> ''
];
if ($id > 0) {
  $st = $dbc->prepare("SELECT id, title, tags, body, author FROM `{$PRIMARY}` WHERE id=?");
  $st->bind_param('i', $id);
  $st->execute();
  $res = $st->get_result();
  if ($res && $res->num_rows) { $row = $res->fetch_assoc(); }
  $st->close();
}

// header include
$loadedHeader = false;
foreach ([
  __DIR__ . '/admin-header.php',
  __DIR__ . '/_header.php',
  __DIR__ . '/partials/admin-header.php',
  __DIR__ . '/../includes/admin-header.php',
  __DIR__ . '/../includes/header.php',
] as $p) { if (is_file($p)) { include $p; $loadedHeader = true; break; } }

if (!$loadedHeader) {
  echo "<!doctype html><meta charset='utf-8'><title>Devlog Editor</title><style>body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:16px}</style>";
}
?>
<h1 style="display:flex;align-items:center;gap:.5rem"><?= $id>0 ? 'Edit Devlog Entry' : 'New Devlog Entry' ?></h1>

<style>
/* Page-specific editor layout. Buttons/inputs use global admin header styles. */
.editor-wrap{ display:grid; gap:.6rem; grid-template-columns: 1fr; }
@media (min-width: 980px){ .editor-wrap{ grid-template-columns: 1fr 1fr; } }
.editor-card{ border:1px solid #ddd; border-radius:.6rem; background:#fff; overflow:hidden; }
.editor-card .hd{ padding:.5rem .6rem; border-bottom:1px solid #ddd; background:#fafafa; font-weight:700; }
.editor-card .bd{ padding:.6rem; }
.form-row{ display:flex; gap:.6rem; flex-wrap:wrap; align-items:flex-end; }
.form-row label{ display:block; font-size:.9rem; margin:0 0 .2rem; color:#333; }
.input{ width:100%; padding:.35rem .5rem; border:1px solid var(--btn-border, #bbb); border-radius:6px; background:#fff; }
.textarea{ width:100%; min-height:320px; padding:.5rem .6rem; font-family:ui-monospace,Menlo,Consolas,monospace; }
.help{ color:#667; font-size:.85rem; }

.chips{ display:flex; flex-wrap:wrap; gap:.25rem; margin-top:.35rem; }
.chip{ display:inline-block; padding:.12rem .5rem; border:1px solid #ead9a6; border-radius:999px; background:#fffbe6; font-size:.78rem; color:#5a4a00; }

.preview{ padding:.6rem; white-space:normal; line-height:1.4; }
.preview h1,.preview h2,.preview h3{ margin: .6rem 0 .35rem; }
.preview pre{ background:#0f172a; color:#e2e8f0; padding:.6rem .7rem; border-radius:.45rem; overflow:auto; }
.preview code{ background:#fff3cd; padding:.05rem .25rem; border-radius:.25rem; }
.preview a{ color:#1d4ed8; }
.preview ul, .preview ol { padding-left: 1.25rem; }
.toolbar{ display:flex; gap:.4rem; align-items:center; justify-content:flex-end; margin:.6rem 0; flex-wrap:wrap; }
</style>

<form method="post" action="">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
  <input type="hidden" name="back" value="<?= h($back) ?>">

  <div class="editor-wrap">
    <div class="editor-card">
      <div class="hd">Content</div>
      <div class="bd">
        <div class="form-row">
          <div style="flex:2 1 360px;">
            <label>Title</label>
            <input class="input" type="text" name="title" id="title" value="<?= h($row['title']) ?>" placeholder="Short, punchy title" required>
          </div>
          <div style="flex:1 1 260px;">
            <label>Tags (comma-separated)</label>
            <input class="input" type="text" name="tags" id="tags" value="<?= h($row['tags']) ?>" placeholder="ui, devops, release">
            <div class="chips" id="tagChips"></div>
          </div>
        </div>

        <div style="margin-top:.6rem;">
          <label>Body (Markdown supported)</label>
          <textarea class="input textarea" name="body" id="body" placeholder="# Heading&#10;&#10;Some **bold** text, _italics_, `inline code`, and a list:&#10;- item one&#10;- item two&#10;&#10;```php&#10;echo 'hello';&#10;```"><?= h($row['body']) ?></textarea>
          <div class="help">Tip: <strong>Ctrl+Enter</strong> to save · Drafts auto-save locally.</div>
        </div>
      </div>
    </div>

    <div class="editor-card">
      <div class="hd">Live Preview</div>
      <div class="bd">
        <div id="preview" class="preview muted">Start typing to preview…</div>
      </div>
    </div>
  </div>

  <div class="toolbar">
    <button class="btn" type="submit">Save</button>
    <a class="btn" href="<?= h($back) ?>">Cancel</a>
    <a class="btn" id="discardDraft" href="#" onclick="discardDraft();return false;">Discard Draft</a>
  </div>
</form>

<script>
// --- tiny Markdown renderer (basic) ---
// Handles: headings, bold/italic, code blocks, inline code, links, lists
function mdRender(src){
  if (!src) return '';
  // Escape HTML
  src = src.replace(/[&<>]/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[s]));
  // Code blocks ```lang ... ```
  src = src.replace(/```([\s\S]*?)```/g, function(_, code){
    return '<pre><code>'+code.replace(/\n$/, '')+'</code></pre>';
  });
  // Headings # ## ###
  src = src.replace(/^(#{1,3})\s*(.+)$/gm, function(_, hashes, text){
    var h = hashes.length; return '<h'+h+'>'+text+'</h'+h+'>';
  });
  // Bold **text** and italics _text_
  src = src.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  src = src.replace(/(^|[^\w])_([^_]+)_(?!\w)/g, '$1<em>$2</em>');
  // Inline code `code`
  src = src.replace(/`([^`]+?)`/g, '<code>$1</code>');
  // Links [text](url)
  src = src.replace(/\[([^\]]+)]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
  // Lists
  src = src.replace(/(^|\n)\s*-\s+(.+)(?=\n|$)/g, '$1• $2');
  src = src.replace(/(^|\n)• (.+)(?=\n• |\n\n|$)/g, function(m){ return '<li>'+m.replace(/(^|\n)• /g,'').replace(/\n/g,'</li><li>')+'</li>'; });
  src = src.replace(/(<li>[\s\S]+<\/li>)/g, '<ul>$1</ul>');
  // Paragraphs
  src = src.replace(/(?:^|\n)([^<\n][^\n]*)/g, function(_, line){
    if (/^\s*$/.test(line)) return line;
    if (/^<h\d|^<ul|^<pre|^<li/.test(line)) return line;
    return '<p>'+line+'</p>';
  });
  return src;
}

// --- tag chips ---
function updateChips(){
  var t = document.getElementById('tags').value || '';
  var chips = (t.split(',').map(s=>s.trim()).filter(Boolean));
  var wrap = document.getElementById('tagChips');
  wrap.innerHTML = '';
  chips.forEach(function(c){
    var span = document.createElement('span');
    span.className = 'chip';
    span.textContent = c;
    wrap.appendChild(span);
  });
}

// --- autosave ---
(function(){
  var id   = <?= (int)$row['id'] ?>;
  var KEY  = 'devlog:edit:' + (id > 0 ? id : 'new');
  var $t   = document.getElementById('title');
  var $g   = document.getElementById('tags');
  var $b   = document.getElementById('body');
  var $p   = document.getElementById('preview');

  function render(){ $p.innerHTML = mdRender($b.value); }
  function saveDraft(){
    try{
      var data = { title:$t.value, tags:$g.value, body:$b.value, ts:Date.now() };
      localStorage.setItem(KEY, JSON.stringify(data));
    }catch(e){}
  }
  function maybeRestore(){
    try{
      if ($t.value || $g.value || $b.value) return; // form already has content
      var raw = localStorage.getItem(KEY); if (!raw) return;
      var data = JSON.parse(raw || '{}'); if (!data) return;
      $t.value = data.title || ''; $g.value = data.tags || ''; $b.value = data.body || '';
      updateChips(); render();
    }catch(e){}
  }
  window.discardDraft = function(){
    try{ localStorage.removeItem(KEY); }catch(e){}
  };

  // events
  $g.addEventListener('input', updateChips);
  $b.addEventListener('input', render);
  $t.addEventListener('input', saveDraft);
  $g.addEventListener('input', saveDraft);
  $b.addEventListener('input', saveDraft);

  // initial
  updateChips(); render(); maybeRestore();

  // Ctrl+Enter to submit
  document.addEventListener('keydown', function(e){
    if (e.defaultPrevented) return;
    if (e.ctrlKey && (e.key === 'Enter' || e.keyCode === 13)) {
      e.preventDefault();
      // clear draft on save to avoid restoring old content
      window.discardDraft();
      document.forms[0].submit();
    }
  });
})();
</script>

<?php if (!$loadedHeader) echo "</body></html>"; ?>
