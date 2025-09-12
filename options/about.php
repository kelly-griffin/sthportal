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
  <?php require_once __DIR__ . '/../includes/head-assets.php'; ?>
</head>

<body>
  <div class="site">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <?php include __DIR__ . '/../includes/leaguebar.php'; ?>

    <div class="canvas">
      <div class="about-container">
        <div class="about-card">
        <section class="page-surface">
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
              <p>Logos & Assets: Team packs (fair use testing).<br><span class="muted">Detailed credits page to
                  come.</span></p>
            </section>

            <section class="card">
              <h2>Roadmap Notes</h2>
              <p>• Options → Theme packs (Winter ’25, Vintage ’67)<br>• Admin tools consolidation after sim upload<br>•
                Leaders/Standings polish + History archive</p>
            </section>

            <section class="card">
              <h2>Support</h2>
              <p>Bug reports & requests: add to your devlog and tag <code>[options]</code>.</p>
            </section>
          </div>
      </section>
      </div>
    </div>
  </div>
</body>

</html>