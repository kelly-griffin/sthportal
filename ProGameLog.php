<?php /* ProGameLog.php — STHS-style: ProGameLog.php?Game=123  */ ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=1280, initial-scale=1" />
  <title>Pro Game Log</title>
  <link rel="stylesheet" href="assets/css/nav.css">
  <link rel="stylesheet" href="assets/css/home.css">
  <link rel="stylesheet" href="assets/css/game.css">
</head>
<body>
  <div class="site">
    <header class="portal-header">
      <div class="portal-top">
        <div class="brand">
          <div class="logo"></div>
          <div class="title" id="portal-title">Your League Name</div>
        </div>
        <nav class="main-nav nav-wrap" aria-label="Primary">
          <div class="nav-item"><a class="nav-btn" href="home.php">Home</a></div>
          <div class="nav-item"><a class="nav-btn" href="news-index.php">News</a></div>
          <div class="nav-item"><a class="nav-btn" href="standings.php">Standings</a></div>
          <div class="nav-item"><a class="nav-btn" href="schedule.php">Schedule</a></div>
          <div class="nav-item"><a class="nav-btn" href="player-stats.php">Player Stats</a></div>
          <div class="nav-item"><a class="nav-btn" href="team-stats.php">Team Stats</a></div>
          <div class="nav-item"><a class="nav-btn" href="options.php">Options</a></div>
        </nav>
        <div class="profile"><a class="btn" href="#">Login</a></div>
      </div>
    </header>

    <div class="context-header">
      <div class="context-inner">
        <div class="context-logo" aria-hidden="true"></div>
        <div class="context-titles">
          <div class="kicker">Pro League</div>
          <div class="h1">Game Log</div>
          <div class="subnav">
            <a class="pill" id="tabBox" href="#">Box Score</a>
            <a class="pill active" id="tabLog" href="#">Game Log</a>
          </div>
        </div>
      </div>
    </div>

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
    window.UHA = window.UHA || {};
    UHA.title = UHA.title || "Your League Name";
    document.getElementById('portal-title').textContent = UHA.title;
    document.title = `${UHA.title} — Pro Game Log`;
  </script>
  <script src="assets/js/urls.js"></script>
  <script src="assets/js/gamelog.js"></script>
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
