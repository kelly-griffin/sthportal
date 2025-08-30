<?php require_once __DIR__ . '/includes/bootstrap.php'; ?>
<?php /* ProGameLog.php — STHS-style: ProGameLog.php?Game=123  */ ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=1280, initial-scale=1" />
  <title>Pro Game Log</title>
  <link rel="stylesheet" href="assets/css/nav.css">
</head>
<body>
  <div class="site">

    <div class="canvas game-canvas">
      <main class="game-main">
        <section id="gamelogRoot" class="game-log"></section>
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

  <script>
  // Switch back to Box Score tab
    document.getElementById('tabBox')?.addEventListener('click', (e) => {
      e.preventDefault();
      const p = new URLSearchParams(location.search);
      const game = p.get('Game') ?? p.get('id') ?? '';
      location.href = `ProBoxScore.php?Game=${encodeURIComponent(game)}`;
    });
  </script>
</body>
</html>
