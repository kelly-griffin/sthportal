<?php
require_once __DIR__ . '/includes/bootstrap.php';
/**
 * UHA Portal — Tournaments (base scaffold)
 * Goal: theme-ready shell with filters + empty table (similar to Leagues)
 * No data wiring yet — safe to fill later.
 */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Tournaments</title>
  <script src="assets/js/tournaments.js" defer></script>
</head>
<body>
  <div class="site">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <?php include __DIR__ . '/includes/leaguebar.php'; ?>

    <div class="canvas">
      <div class="tournaments-container">
        <div class="tournaments-card">
          <h1>Tournaments</h1>

          <!-- Filters toolbar -->
          <div class="tn-toolbar">
            <div class="spacer"></div>
            <label>Type
              <select id="tn-type">
                <option value="">All</option>
                <option value="international">International</option>
                <option value="prospects">Prospects</option>
                <option value="junior">Junior</option>
              </select>
            </label>
            <label>Season
              <select id="tn-season">
                <option value="">All</option>
              </select>
            </label>
            <label>Search
              <input id="tn-q" type="search" placeholder="tournament / host / notes">
            </label>
          </div>

          <!-- Table scaffold -->
          <div class="table-scroll">
            <table class="tournaments-table" aria-describedby="tournaments-desc">
              <colgroup>
                <col class="col-name">
                <col class="col-type">
                <col class="col-season">
                <col class="col-teams">
                <col class="col-host">
                <col class="col-status">
                <col class="col-notes">
                <col class="col-actions">
              </colgroup>
              <thead>
                <tr>
                  <th scope="col">Tournament</th>
                  <th scope="col">Type</th>
                  <th scope="col">Season</th>
                  <th scope="col">Teams</th>
                  <th scope="col">Host</th>
                  <th scope="col">Status</th>
                  <th scope="col">Notes</th>
                  <th scope="col" class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody id="tournaments-body">
                <tr class="empty">
                  <td colspan="8" class="empty-msg">No tournaments configured yet.</td>
                </tr>
              </tbody>
            </table>
            <p id="tournaments-desc" class="visually-hidden">Configured tournaments with type, season, host, and status.</p>
          </div>

        </div>
      </div>
    </div>
  </div>
</body>
</html>
