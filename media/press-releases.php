<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Press Releases';
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — UHA Portal</title>
</head>
<body>
<div class="site">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
  <div class="canvas">
    <div class="press-container">
      <div class="press-card">
    <h1><?= h($title) ?></h1>
    <p class="muted">Official announcements from the League Office and teams.</p>

    <article class="press-item">
      <h3>Stub: League Statement Title</h3>
      <div class="press-meta">League Office • 2025-08-25</div>
      <p>Placeholder copy. We’ll add editor/feeds later.</p>
      <a class="tile-cta" href="#">Open</a>
    </article>
      </div>
    </div>
  </div>
</div>
</body></html>
