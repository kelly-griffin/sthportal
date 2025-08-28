<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'News Hub';
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
select,input[type="search"]{background:#0f1420;color:#e6eef8;border:1px solid #2F3F53;border-radius:8px;padding:6px 8px}
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
    <p class="muted">League features, stories, and updates.</p>

    <div class="toolbar">
      <input type="search" placeholder="Search headlines…" aria-label="Search">
      <select aria-label="Section">
        <option value="all">All</option><option>Features</option><option>Game Recaps</option><option>Transactions</option>
      </select>
      <select aria-label="Time">
        <option>Latest</option><option>Past Week</option><option>Past Month</option>
      </select>
    </div>

    <div class="grid2">
      <article class="card">
        <h2>Sample Headline</h2>
        <p class="meta">By UHA Media • Today</p>
        <p>Stub content. We’ll wire feeds later.</p>
      </article>
      <article class="card">
        <h2>Another Story</h2>
        <p class="meta">By UHA Media • Yesterday</p>
        <p>Placeholder block to show layout.</p>
      </article>
    </div>
  </div></div></div>
</div>
</body></html>
