<?php
// /options/about.php — Options: About (universal shell)
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'About';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — UHA Portal</title>
<style>
:root{ --stage:#585858ff; --card:#0D1117; --card-border:#FFFFFF1A; --ink:#E8EEF5; --ink-soft:#95A3B4; --site-width:1200px; }
body{ margin:0; background:#202428; color:var(--ink); font:14px/1.5 system-ui, Segoe UI, Roboto, Helvetica, Arial,sans-serif; }
.site{ width:var(--site-width); margin:0 auto; min-height:100vh; }
.canvas{ padding:0 16px 40px; } .wrap{ max-width:1000px; margin:20px auto; }
.page-surface{ margin:12px 0 32px; padding:16px 16px 24px; background:var(--stage); border-radius:16px; box-shadow:inset 0 1px 0 #ffffff0d,0 0 0 1px #ffffff0f; min-height:calc(100vh - 220px); }
h1{ margin:0 0 6px; font-size:28px; line-height:1.15; letter-spacing:.2px; } .muted{ color:var(--ink-soft); }
.grid2{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; } @media (max-width:920px){ .grid2{ grid-template-columns:1fr; } }
.card{ background:var(--card); border:1px solid var(--card-border); border-radius:16px; padding:16px; box-shadow:inset 0 1px 0 #ffffff12; }
.card h2{ margin:0 0 10px; color:#DFE8F5; } .card p{ margin:0 0 10px; color:#CFE1F3; }
code{ background:#131a23; padding:2px 6px; border-radius:6px; }
</style>
</head>
<body>
<div class="site">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/leaguebar.php'; ?>

  <div class="canvas"><div class="wrap"><div class="page-surface">
    <h1><?= h($title) ?></h1>
    <p class="muted">Version info, credits, and project notes.</p>

    <div class="grid2">
      <section class="card">
        <h2>Portal Version</h2>
        <p>Release: <strong>Alpha</strong></p>
        <p>Build: <code>dev-local</code></p>
        <p class="muted">(We’ll hook this to real version constants or a JSON later.)</p>
      </section>

      <section class="card">
        <h2>Credits</h2>
        <p>Design & Development: <strong>Kelly Griffin</strong></p>
        <p>Simulator: <strong>STHS</strong></p>
        <p>Logos & Assets: Team packs (fair use testing).<br><span class="muted">Detailed credits page to come.</span></p>
      </section>

      <section class="card">
        <h2>Roadmap Notes</h2>
        <p>• Options → Theme packs (Winter ’25, Vintage ’67)<br>• Admin tools consolidation after sim upload<br>• Leaders/Standings polish + History archive</p>
      </section>

      <section class="card">
        <h2>Support</h2>
        <p>Bug reports & requests: add to your devlog and tag <code>[options]</code>.</p>
      </section>
    </div>
  </div></div></div>
</div>
</body>
</html>
