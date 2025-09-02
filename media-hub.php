<?php
require_once __DIR__ . '/includes/bootstrap.php';
$title = 'Media';
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — UHA Portal</title>

</head><body>
<div class="site">
  <?php include __DIR__ . '/includes/topbar.php'; ?>
  <?php include __DIR__ . '/includes/leaguebar.php'; ?>
  <div class="canvas">
    <div class="media-container">
      <div class="media-card">
    <h1><?= h($title) ?></h1>
    <p class="media-lead">Central newsroom for the league: articles, PR, weekly recaps, rankings, and social.</p>

    <div class="media-grid">
      <article class="media-tile">
        <h3>News Hub</h3>
        <p>League news feed and features.</p>
        <a class="tile-cta" href="<?= u('media/news.php') ?>">Open</a>
      </article>

      <article class="media-tile">
        <h3>Press Releases</h3>
        <p>Official statements from league & teams.</p>
        <a class="tile-cta" href="<?= u('media/press-releases.php') ?>">Open</a>
      </article>

      <article class="media-tile">
        <h3>Weekly Recaps</h3>
        <p>Roundups, storylines, top performers.</p>
        <a class="tile-cta" href="<?= u('media/weekly-recaps.php') ?>">Open</a>
      </article>

      <article class="media-tile">
        <h3>Power Rankings</h3>
        <p>Rankings with notes (manual or auto).</p>
        <a class="tile-cta" href="<?= u('media/power-rankings.php') ?>">Open</a>
      </article>

      <article class="media-tile">
        <h3>Player of the Week</h3>
        <p>Award page with nominees & winner.</p>
        <a class="tile-cta" href="<?= u('media/player-of-the-week.php') ?>">Open</a>
      </article>

      <article class="media-tile">
        <h3>Team of the Week</h3>
        <p>First/Second teams with positions.</p>
        <a class="tile-cta" href="<?= u('media/team-of-the-week.php') ?>">Open</a>
      </article>

      <article class="media-tile">
        <h3>Social Hub</h3>
        <p>Embeds or links to socials.</p>
        <a class="tile-cta" href="<?= u('media/social.php') ?>">Open</a>
      </article>
    </div>
  </div></div></div>
</div>
</body></html>
