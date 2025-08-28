<?php /* ProBoxScore.php — STHS-style: ProBoxScore.php?Game=123  */ ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=1280, initial-scale=1" />
  <title>Pro Box Score</title>
  <link rel="stylesheet" href="assets/css/nav.css">
  <link rel="stylesheet" href="assets/css/home.css">
  <link rel="stylesheet" href="assets/css/game.css">
</head>
<body>
  <div class="site">
    <!-- Header / Nav (same skeleton as home) -->
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

  <script>
    // Minimal config so header shows your league name
    window.UHA = window.UHA || {};
    UHA.title = UHA.title || "Your League Name";
    document.getElementById('portal-title').textContent = UHA.title;
    document.title = `${UHA.title} — Pro Box Score`;
  </script>
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
