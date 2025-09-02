<?php
require_once __DIR__ . '/includes/bootstrap.php';
/**
 * UHA Portal — Injuries (base scaffold)
 * Goal: clean, theme-ready scaffold with filters + empty table
 * No data wiring yet — safe to ship and fill later.
 */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Injuries</title>
  <link rel="stylesheet" href="assets/css/nav.css">
  <script src="assets/js/injuries.js" defer></script>
</head>
<body>
  <div class="site">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <?php include __DIR__ . '/includes/leaguebar.php'; ?>

    <div class="canvas">
      <div class="injuries-container">
        <div class="injuries-card">
          <h1>Injuries</h1>

          <!-- Filters toolbar (team / status / search) -->
          <div class="inj-toolbar">
            <div class="spacer"></div>
            <label>Team
              <select id="inj-team">
                <option value="">All</option>
              </select>
            </label>
            <label>Status
              <select id="inj-status">
                <option value="">All</option>
                <option value="IR">IR</option>
                <option value="LTIR">LTIR</option>
                <option value="Day-to-Day">Day-to-Day</option>
              </select>
            </label>
            <label>Search
              <input id="inj-q" type="search" placeholder="player / injury / notes">
            </label>
          </div>

          <!-- Table scaffold -->
          <div class="table-scroll">
            <table class="injuries-table" aria-describedby="injuries-desc">
              <colgroup>
                <col class="col-player">
                <col class="col-team">
                <col class="col-injury">
                <col class="col-status">
                <col class="col-start">
                <col class="col-return">
                <col class="col-notes">
              </colgroup>
              <thead>
                <tr>
                  <th scope="col">Player</th>
                  <th scope="col">Team</th>
                  <th scope="col">Injury</th>
                  <th scope="col">Status</th>
                  <th scope="col">Placed On</th>
                  <th scope="col">Expected Return</th>
                  <th scope="col">Notes</th>
                </tr>
              </thead>
              <tbody id="injuries-body">
                <tr class="empty">
                  <td colspan="7" class="empty-msg">No injuries listed yet.</td>
                </tr>
              </tbody>
            </table>
            <p id="injuries-desc" class="visually-hidden">Team injuries with status and return estimates.</p>
          </div>

        </div>
      </div>
    </div>
  </div>
</body>
</html>
