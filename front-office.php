<?php
require_once __DIR__ . '/includes/bootstrap.php';
/**
 * UHA Portal — Front Office (GM Desk) — base scaffold
 * Goal: theme-ready shell with quick actions, notepad, tasks, shortcuts, cap snapshot.
 * No data wiring yet — safe to fill later.
 */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Front Office</title>
  <link rel="stylesheet" href="assets/css/nav.css">
  <script src="assets/js/front-office.js" defer></script>
</head>
<body>
  <div class="site">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <?php include __DIR__ . '/includes/leaguebar.php'; ?>

    <div class="canvas">
      <div class="frontoffice-container">
        <div class="frontoffice-card">
          <h1>Front Office</h1>

          <!-- Quick actions / toolbar -->
          <div class="gm-toolbar">
            <div class="spacer"></div>
            <a class="btn" href="transactions.php#trades">New Trade</a>
            <a class="btn" href="transactions.php#signings">Sign Player</a>
            <a class="btn" href="transactions.php#moves">Call Up / Send Down</a>
            <a class="btn" href="injuries.php">IR / LTIR</a>
          </div>

          <!-- Desk layout -->
          <div class="gm-grid">
            <section class="gm-col">
              <div class="gm-panel gm-notepad">
                <h2>GM Notepad</h2>
                <textarea class="note-pad" placeholder="Jot scouting notes, roster ideas, cap targets…"></textarea>
              </div>

              <div class="gm-panel gm-tasks">
                <h2>Tasks</h2>
                <ul class="mini-board gm-task-list">
                  <li><span class="rank">1</span><span>Wire this panel later</span><span class="val"></span><span class="val"></span></li>
                  <li><span class="rank">2</span><span>Hook to reminders or admin</span><span class="val"></span><span class="val"></span></li>
                </ul>
              </div>
            </section>

            <aside class="gm-side">
              <div class="gm-panel gm-shortcuts">
                <h2>Shortcuts</h2>
                <ul class="shortcut-list">
                  <li><a href="players.php">Players</a></li>
                  <li><a href="transactions.php">Transactions</a></li>
                  <li><a href="injuries.php">Injuries</a></li>
                  <li><a href="leagues.php">Leagues</a></li>
                </ul>
              </div>

              <div class="gm-panel gm-cap">
                <h2>Cap Snapshot</h2>
                <div class="gm-cap-grid">
                  <div class="cap-card">
                    <div class="cap-label">Cap Used</div>
                    <div class="cap-value">$0</div>
                  </div>
                  <div class="cap-card">
                    <div class="cap-label">Cap Space</div>
                    <div class="cap-value">$0</div>
                  </div>
                </div>
                <div class="gm-cap-note">To be wired when cap data is ready.</div>
              </div>

              <div class="gm-panel gm-dates">
                <h2>Key Dates</h2>
                <ul class="mini-board">
                  <li><span class="rank">—</span><span>Wire important league dates here</span><span class="val"></span><span class="val"></span></li>
                </ul>
              </div>
            </aside>
          </div>

        </div>
      </div>
    </div>
  </div>
</body>
</html>
