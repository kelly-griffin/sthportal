<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'News Hub';
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
      <div class="news-container">
        <div class="news-card">
          <h1><?= h($title) ?></h1>
          <p class="news-lead">League features, stories, and updates.</p>

          <div class="news-toolbar">
            <div class="spacer"></div>
            <label>Category
              <select id="news-cat">
                <option value="">All</option>
                <option value="features">Features</option>
                <option value="recaps">Game Recaps</option>
                <option value="transactions">Transactions</option>
                <option value="awards">Awards</option>
              </select>
            </label>
            <label>Time
              <select id="news-time">
                <option value="">Latest</option>
                <option value="7d">Past Week</option>
                <option value="30d">Past Month</option>
              </select>
            </label>
            <label>Search
              <input id="news-q" type="search" placeholder="headline / team / tag">
            </label>
          </div>

          <div class="news-grid">
            <article class="news-item">
              <h3>Sample Headline</h3>
              <p class="meta">By UHA Media • Today</p>
              <p>Stub content. We’ll wire feeds later.</p>
              <a class="tile-cta" href="#">Open</a>
            </article>
            <article class="news-item">
              <h3>Another Story</h3>
              <p class="meta">By UHA Media • Yesterday</p>
              <p>Placeholder block to show layout.</p>
              <a class="tile-cta" href="#">Open</a>
            </article>

            <!-- empty state once wired -->
            <!-- <p class="news-empty">No articles yet.</p> -->
          </div>
          </div>
        </div>
      </div>
    </div>
</body>

</html>