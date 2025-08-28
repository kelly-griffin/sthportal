<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Weekly Recaps';
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — UHA Portal</title>
<style>
:root{--stage:#585858ff;--card:#0D1117;--card-border:#FFFFFF1A;--ink:#E8EEF5;--ink-soft:#95A3B4;--site-width:1200px}
body{margin:0;background:#202428;color:var(--ink);font:14px/1.5 system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
.site{width:var(--site-width);margin:0 auto;min-height:100vh}.canvas{padding:0 16px 40px}.wrap{max-width:1000px;margin:20px auto}
.page-surface{margin:12px 0 32px;padding:16px 16px 24px;background:var(--stage);border-radius:16px;box-shadow:inset 0 1px 0 #ffffff0d,0 0 0 1px #ffffff0f;min-height:calc(100vh - 220px)}
h1{margin:0 0 6px;font-size:28px}.muted{color:var(--ink-soft)}
.toolbar{display:flex;gap:10px;flex-wrap:wrap;margin:6px 0 12px}
select{background:#0f1420;color:#e6eef8;border:1px solid #2F3F53;border-radius:8px;padding:6px 8px}
.grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}@media(max-width:920px){.grid2{grid-template-columns:1fr}}
.card{background:var(--card);border:1px solid var(--card-border);border-radius:16px;padding:16px;color:#E8EEF5;box-shadow:inset 0 1px 0 #ffffff12}
.card h2{margin:0 0 6px}.card p{margin:0 0 10px;color:#CFE1F3}.meta{font-size:12px;color:var(--ink-soft)}
</style>
</head><body>
<div class="site">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
  <div class="canvas"><div class="wrap"><div class="page-surface">
    <h1><?= h($title) ?></h1>
    <p class="muted">Roundup of the week’s biggest stories and stats.</p>

    <div class="toolbar">
      <select aria-label="Week">
        <option>Week 1</option><option>Week 2</option><option>Week 3</option>
      </select>
      <select aria-label="Scope"><option>League</option><option>Pro</option><option>Farm</option></select>
    </div>

    <div class="grid2">
      <article class="card">
        <h2>Week N Recap</h2><p class="meta">By UHA Media</p>
        <p>Placeholder recap content block.</p>
      </article>
      <article class="card">
        <h2>Top Storyline</h2><p class="meta">By UHA Media</p>
        <p>Another placeholder block.</p>
      </article>
    </div>
  </div></div></div>
</div>
</body></html>
