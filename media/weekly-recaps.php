<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Weekly Recaps';
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> — UHA Portal</title>

</head>

<body>
  <div class="site">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
    <div class="canvas">
      <div class="recaps-container">
        <div class="recaps-card">
          <h1><?= h($title) ?></h1>
          <p class="recaps-lead">Roundup of the week’s biggest stories and stats.</p>

          <div class="recaps-toolbar">
            <div class="spacer"></div>
            <label>Week
              <select id="recap-week">
                <option value="">This Week</option>
                <!-- later: populate Week of YYYY-MM-DD (Sat–Fri) -->
              </select>
            </label>
            <label>Search
              <input id="recap-q" type="search" placeholder="team / player / headline">
            </label>
          </div>

          <div class="recap-grid">
            <article class="recap-item">
              <h3>Stub: Week N Recap</h3>
              <p class="meta">By UHA Media</p>
              <div class="recap-meta">UHA Media • Week of 2025-10-04</div>
              <p>Placeholder summary—top games, three stars, standings movers.</p>
              <a class="tile-cta" href="#">Open</a>
            </article>

            <article class="recap-item">
              <h3>Division Highlights</h3>
              <div class="recap-meta">UHA Media • Week of 2025-10-04</div>
              <p>Quick hits from each division. Content wiring comes later.</p>
              <a class="tile-cta" href="#">Open</a>
            </article>
        </div>
      </div>
    </div>
  </div>
</body>

</html>