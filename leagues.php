<?php
require_once __DIR__ . '/includes/bootstrap.php';
/**
 * UHA Portal — Leagues (base scaffold)
 * Goal: theme-ready shell with filters + empty table, no data wiring yet.
 */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Leagues</title>
  <link rel="stylesheet" href="assets/css/nav.css">
  <script src="assets/js/leagues.js" defer></script>
</head>
<body>
  <div class="site">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <?php include __DIR__ . '/includes/leaguebar.php'; ?>

    <div class="canvas">
      <div class="leagues-container">
        <div class="leagues-card">
          <h1>Leagues</h1>

          <!-- Filters toolbar (view / scope / search) -->
          <div class="lg-toolbar">
            <div class="spacer"></div>
            <label>View
              <select id="lg-view">
                <option value="">All</option>
                <option value="active">Active</option>
                <option value="archived">Archived</option>
              </select>
            </label>
            <label>Scope
              <select id="lg-scope">
                <option value="">All</option>
                <option value="pro">Pro</option>
                <option value="farm">Farm</option>
                <option value="echl">ECHL</option>
              </select>
            </label>
            <label>Search
              <input id="lg-q" type="search" placeholder="league / division / notes">
            </label>
          </div>

          <!-- Table scaffold -->
          <div class="table-scroll">
            <table class="leagues-table" aria-describedby="leagues-desc">
              <colgroup>
                <col class="col-name">
                <col class="col-scope">
                <col class="col-teams">
                <col class="col-season">
                <col class="col-status">
                <col class="col-notes">
                <col class="col-actions">
              </colgroup>
              <thead>
                <tr>
                  <th scope="col">League</th>
                  <th scope="col">Scope</th>
                  <th scope="col">Teams</th>
                  <th scope="col">Season</th>
                  <th scope="col">Status</th>
                  <th scope="col">Notes</th>
                  <th scope="col" class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody id="leagues-body">
                <tr class="empty">
                  <td colspan="7" class="empty-msg">No leagues configured yet.</td>
                </tr>
              </tbody>
            </table>
            <p id="leagues-desc" class="visually-hidden">Configured leagues with scope, season, and status.</p>
          </div>

        </div>
      </div>
    </div>
  </div>
</body>
</html>
