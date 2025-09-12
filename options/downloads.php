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

  <?php require_once __DIR__ . '/../includes/head-assets.php'; ?>
</head>

<body>
  <div class="site">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
    <div class="downloads-container">
      <div class="downloads-card">
        <div class="page-surface">
          <h1><?= h($title) ?></h1>
          <p class="muted">Central place to grab league files and (later) GM tools.</p>

          <div class="grid2">
            <section class="card">
              <h2>League File</h2>
              <p>Latest packaged league file for GMs.</p>
              <a class="btn" href="<?= u('download.php?what=league') ?>">Download</a>
              <p class="muted" style="margin-top:8px;">Uses existing route <code>download.php?what=league</code>.
              </p>
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
        </div>
      </div>
    </div>

  </div>
</body>

</html>