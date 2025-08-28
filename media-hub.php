<?php
require_once __DIR__ . '/includes/bootstrap.php';
$title = 'Media';
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — UHA Portal</title>
<style>
:root{--stage:#585858ff;--card:#0D1117;--card-border:#FFFFFF1A;--ink:#E8EEF5;--ink-soft:#95A3B4;--site-width:1200px}
body{margin:0;background:#202428;color:var(--ink);font:14px/1.5 system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
.site{width:var(--site-width);margin:0 auto;min-height:100vh}
.canvas{padding:0 16px 40px}.wrap{max-width:1000px;margin:20px auto}
.page-surface{margin:12px 0 32px;padding:16px 16px 24px;background:var(--stage);border-radius:16px;box-shadow:inset 0 1px 0 #ffffff0d,0 0 0 1px #ffffff0f;min-height:calc(100vh - 220px)}
h1{margin:0 0 6px;font-size:28px;line-height:1.15;letter-spacing:.2px}.muted{color:var(--ink-soft)}
.grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}@media(max-width:1080px){.grid3{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.grid3{grid-template-columns:1fr}}
.card{background:var(--card);border:1px solid var(--card-border);border-radius:16px;padding:16px;box-shadow:inset 0 1px 0 #ffffff12}
.card h2{margin:0 0 8px;color:#DFE8F5}.card p{margin:0 0 10px;color:#CFE1F3}
.btn{display:inline-block;padding:6px 10px;border-radius:10px;border:1px solid #2F3F53;background:#1B2431;color:#E6EEF8;text-decoration:none}.btn:hover{background:#223349;border-color:#3D5270}
</style>
</head><body>
<div class="site">
  <?php include __DIR__ . '/includes/topbar.php'; ?>
  <?php include __DIR__ . '/includes/leaguebar.php'; ?>
  <div class="canvas"><div class="wrap"><div class="page-surface">
    <h1><?= h($title) ?></h1>
    <p class="muted">Central newsroom for the league: articles, PR, weekly recaps, rankings, and social.</p>

    <div class="grid3">
      <section class="card">
        <h2>News Hub</h2><p>League news feed and features.</p>
        <a class="btn" href="<?= u('media/news.php') ?>">Open</a>
      </section>

      <section class="card">
        <h2>Press Releases</h2><p>Official statements from league & teams.</p>
        <a class="btn" href="<?= u('media/press-releases.php') ?>">Open</a>
      </section>

      <section class="card">
        <h2>Weekly Recaps</h2><p>Roundups, storylines, top performers.</p>
        <a class="btn" href="<?= u('media/weekly-recaps.php') ?>">Open</a>
      </section>

      <section class="card">
        <h2>Power Rankings</h2><p>Rankings with notes (manual or auto).</p>
        <a class="btn" href="<?= u('media/power-rankings.php') ?>">Open</a>
      </section>

      <section class="card">
        <h2>Player of the Week</h2><p>Award page with nominees & winner.</p>
        <a class="btn" href="<?= u('media/player-of-the-week.php') ?>">Open</a>
      </section>

      <section class="card">
        <h2>Team of the Week</h2><p>First/Second teams with positions.</p>
        <a class="btn" href="<?= u('media/team-of-the-week.php') ?>">Open</a>
      </section>

      <section class="card">
        <h2>Social Hub</h2><p>Embeds or links to socials.</p>
        <a class="btn" href="<?= u('media/social.php') ?>">Open</a>
      </section>
    </div>
  </div></div></div>
</div>
</body></html>
