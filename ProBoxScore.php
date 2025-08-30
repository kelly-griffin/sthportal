<?php require_once __DIR__ . '/includes/bootstrap.php'; ?>
<?php /* ProBoxScore.php — STHS-style: ProBoxScore.php?Game=123  */ ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=1280, initial-scale=1" />
  <title>Pro Box Score</title>
  <link rel="stylesheet" href="assets/css/nav.css">
</head>
<body>
  <div class="site">

    <div class="context-header">
      <div class="context-inner">
        <div class="context-logo" aria-hidden="true"></div>
        <div class="context-titles">
          <div class="kicker">Pro League</div>
          <div class="h1">Box Score</div>
          <div class="subnav">
            <a class="pill active" id="tabBox" href="#">Box Score</a>
            <a class="pill" id="tabLog" href="#">Game Log</a>
          </div>
        </div>
      </div>
    </div>

    <div class="canvas game-canvas">
      <main class="game-main">
        <section id="boxscoreRoot" class="game-box"></section>
      </main>
      <aside class="sidebar-right">
        <div class="box">
          <div class="title">Game Links</div>
          <div class="side-links" id="gameLinks"></div>
        </div>
      </aside>
    </div>

    <footer class="footer">Placeholder footer • © Your League</footer>
  </div>

  <script src="assets/js/urls.js"></script>
  <script src="assets/js/boxscore.js"></script>
  <script>
    // Switch to Game Log tab
    document.getElementById('tabLog')?.addEventListener('click', (e) => {
      e.preventDefault();
      const p = new URLSearchParams(location.search);
      const game = p.get('Game') ?? p.get('id') ?? '';
      location.href = `ProGameLog.php?Game=${encodeURIComponent(game)}`;
    });
  </script>
</body>
</html>
