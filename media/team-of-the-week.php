<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Team of the Week';
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — UHA Portal</title>
<style>
:root{--stage:#585858ff;--card:#0D1117;--card-border:#FFFFFF1A;--ink:#E8EEF5;--ink-soft:#95A3B4;--site-width:1200px}
body{margin:0;background:#202428;color:var(--ink);font:14px/1.5 system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
.site{width:var(--site-width);margin:0 auto;min-height:100vh}.canvas{padding:0 16px 40px}.wrap{max-width:1000px;margin:20px auto}
.page-surface{margin:12px 0 32px;padding:16px;background:var(--stage);border-radius:16px;box-shadow:inset 0 1px 0 #ffffff0d,0 0 0 1px #ffffff0f;min-height:calc(100vh - 220px)}
h1{margin:0 0 6px;font-size:28px}.muted{color:var(--ink-soft)}
.grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}@media(max-width:920px){.grid2{grid-template-columns:1fr}}
.card{background:var(--card);border:1px solid var(--card-border);border-radius:16px;padding:16px;box-shadow:inset 0 1px 0 #ffffff12}
.card h2{margin:0 0 8px}.list{display:grid;gap:8px}.slot{display:flex;gap:8px;align-items:center}
.badge{min-width:28px;height:28px;border-radius:8px;background:#1B2431;border:1px solid #2F3F53;display:flex;align-items:center;justify-content:center;font-weight:800}
</style>
</head><body>
<div class="site">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
  <div class="canvas"><div class="wrap"><div class="page-surface">
    <h1><?= h($title) ?></h1>
    <p class="muted">First & Second teams by position.</p>

    <div class="grid2">
      <section class="card">
        <h2>First Team</h2>
        <div class="list">
          <div class="slot"><div class="badge">C</div> Center — Team (placeholder)</div>
          <div class="slot"><div class="badge">LW</div> Left Wing — Team</div>
          <div class="slot"><div class="badge">RW</div> Right Wing — Team</div>
          <div class="slot"><div class="badge">D</div> Defense — Team</div>
          <div class="slot"><div class="badge">G</div> Goalie — Team</div>
        </div>
      </section>

      <section class="card">
        <h2>Second Team</h2>
        <div class="list">
          <div class="slot"><div class="badge">C</div> Center — Team</div>
          <div class="slot"><div class="badge">LW</div> Left Wing — Team</div>
          <div class="slot"><div class="badge">RW</div> Right Wing — Team</div>
          <div class="slot"><div class="badge">D</div> Defense — Team</div>
          <div class="slot"><div class="badge">G</div> Goalie — Team</div>
        </div>
      </section>
    </div>
  </div></div></div>
</div>
</body></html>
