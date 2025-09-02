<?php
require_once __DIR__ . '/includes/bootstrap.php';

/**
 * UHA Portal — Players (base scaffold)
 * Goal: theme-ready shell with search, toggle, Player Spotlight, and grid list containers.
 * No data wiring yet — safe to fill later via players.js.
 */
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <title>Players</title>
  <link rel="stylesheet" href="assets/css/nav.css">
  <script src="assets/js/players.js" defer></script>
</head>

<body>
  <div class="site">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <?php include __DIR__ . '/includes/leaguebar.php'; ?>


    <div class="canvas">
      <div class="players-container">
        <div class="players-card">
          <h1>Players</h1>


          <!-- Toolbar: search + active-only toggle -->
          <div class="pl-toolbar" role="search">
            <div class="spacer"></div>
            <div class="pl-searchbox" data-player-url="player.php?id=">
              <label class="pl-search-label">
                <span class="vh">Search players</span>
                <input id="pl-q" class="pl-search" type="search" placeholder="Search players">
              </label>
              <!-- results dropdown -->
              <div class="pl-results" id="pl-results" hidden>
                <div class="scroll">
                  <table class="pl-results-table" aria-describedby="pl-results-desc">
                    <thead>
                      <tr>
                        <th>Player</th>
                        <th>Pos</th>
                        <th>Team</th>
                        <th>#</th>
                        <th>Status</th>
                        <th>Ht</th>
                        <th>Wt</th>
                        <th>Birthplace</th>
                      </tr>
                    </thead>
                    <tbody id="pl-results-body">
                      <tr class="empty"><td colspan="8">Type at least 3 characters…</td></tr>
                    </tbody>
                  </table>
                  <p id="pl-results-desc" class="vh">Search results will appear below the search box.</p>
                </div>
              </div>
            </div>
            <label class="pl-toggle">
              <input id="pl-active" type="checkbox" checked>
              <span>Active players only</span>
            </label>
          </div>

          <!-- Player Spotlight -->
          <section class="pl-spotlight" aria-labelledby="pl-spotlight-h">
            <h2 id="pl-spotlight-h">Player Spotlight</h2>
            <ul class="spotlight-grid" id="spotlight-grid">
              <li class="spotlight-item" data-team="WSH">
                <a class="spotlight-link" href="player.php?id=8471214">
                  <div class="avatar"><img src="https://assets.nhle.com/mugs/nhl/20252026/WSH/8471214.png" alt="Alex Ovechkin headshot"></div>
                  <span class="name">Alex Ovechkin</span>
                  <div class="sub">
                    <img class="team-logo" src="assets/img/logos/WSH_light.svg" alt="WSH">
                    <span class="num">#8</span>
                    <span class="middot">•</span>
                    <span class="pos">LW</span>
                  </div>
                </a>
              </li>
            </ul>
          </section>
          


        </div>
      </div>
    </div>
  </div>
</body>

</html>