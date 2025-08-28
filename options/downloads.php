<?php
// /options/downloads.php — Options: Downloads (universal shell)
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Downloads';
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
.btn{ display:inline-block; padding:6px 10px; border-radius:10px; border:1px solid #2F3F53; background:#1B2431; color:#E6EEF8; text-decoration:none; }
.btn:hover{ background:#223349; border-color:#3D5270; }
.btn.small{ padding:4px 8px; font-size:12px; line-height:1; }
.btn[aria-disabled="true"]{ opacity:.6; cursor:not-allowed; }
code{ background:#131a23; padding:2px 6px; border-radius:6px; }
</style>
</head>
<body>
<div class="site">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/leaguebar.php'; ?>

  <div class="canvas"><div class="wrap"><div class="page-surface">
    <h1><?= h($title) ?></h1>
    <p class="muted">Central place to grab league files and (later) GM tools.</p>

    <div class="grid2">
      <section class="card">
        <h2>League File</h2>
        <p>Latest packaged league file for GMs.</p>
        <a class="btn" href="<?= u('download.php?what=league') ?>">Download</a>
        <p class="muted" style="margin-top:8px;">Uses existing route <code>download.php?what=league</code>.</p>
      </section>

      <section class="card">
        <h2>Logos Pack</h2>
        <p>Team logos and wordmarks (ZIP). <span class="muted">(placeholder)</span></p>
        <a class="btn small" href="#" aria-disabled="true" onclick="return false;">Coming Soon</a>
      </section>

      <section class="card">
        <h2>Client Tools</h2>
        <p>Helpful utilities for GMs (CSV templates, docs). <span class="muted">(placeholder)</span></p>
        <a class="btn small" href="#" aria-disabled="true" onclick="return false;">Coming Soon</a>
      </section>

      <section class="card">
        <h2>Changelogs</h2>
        <p>What’s new between builds. <span class="muted">(placeholder)</span></p>
        <a class="btn small" href="#" aria-disabled="true" onclick="return false;">Coming Soon</a>
      </section>
    </div>
  </div></div></div>
</div>
</body>
</html>
