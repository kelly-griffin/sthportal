<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Power Rankings';
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
.toolbar{display:flex;gap:10px;flex-wrap:wrap;margin:6px 0 12px}
select{background:#0f1420;color:#e6eef8;border:1px solid #2F3F53;border-radius:8px;padding:6px 8px}
.list{display:grid;gap:10px}
.rank{background:var(--card);border:1px solid var(--card-border);border-radius:12px;padding:12px;display:flex;gap:10px;align-items:center}
.badge{min-width:34px;height:34px;border-radius:8px;background:#1B2431;border:1px solid #2F3F53;display:flex;align-items:center;justify-content:center;font-weight:800}
.team{font-weight:700}.note{color:#CFE1F3}
</style>
</head><body>
<div class="site">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
  <div class="canvas"><div class="wrap"><div class="page-surface">
    <h1><?= h($title) ?></h1>
    <p class="muted">Manual/auto weekly rankings with notes.</p>

    <div class="toolbar">
      <select aria-label="Week"><option>Week 1</option><option>Week 2</option></select>
      <select aria-label="Scope"><option>League</option><option>Pro</option><option>Farm</option></select>
    </div>

    <div class="list">
      <?php for($i=1;$i<=10;$i++): ?>
      <div class="rank">
        <div class="badge"><?= $i ?></div>
        <div class="team">Team <?= $i ?></div>
        <div class="note">— placeholder note for Team <?= $i ?>.</div>
      </div>
      <?php endfor; ?>
    </div>
  </div></div></div>
</div>
</body></html>
