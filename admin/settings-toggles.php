<?php

require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Settings &amp; Toggles';

?>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> — UHA Portal</title>
  <?php require_once __DIR__ . '/../includes/head-assets.php'; ?>
</head>
<main class="site">
<?php
include __DIR__ . '/../includes/topbar.php';
include __DIR__ . '/../includes/leaguebar.php';?>
 <div class="canvas settings-container" id="settingsTogglesApp">
    <div class="card settings-card">
      <h1>League Settings &amp; Toggles</h1>
      <p>Turn portal features on/off and adjust league options. (Scaffold only)</p>

      <div class="card" style="margin-top:10px;">
        <h2>Features</h2>
        <table class="uha-table">
          <thead>
            <tr>
              <th>Feature</th>
              <th>Description</th>
              <th style="width:120px;">Enabled</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="team">
                <span class="t">Junior Leagues</span>
              </td>
              <td>Expose Farm/ECHL scope switchers and pages.</td>
              <td>
                <input type="checkbox" disabled>
              </td>
            </tr>
            <tr>
              <td class="team">
                <span class="t">Trade Market</span>
              </td>
              <td>Public trade block + proposals UI.</td>
              <td>
                <input type="checkbox" disabled>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card" style="margin-top:10px;">
        <h2>League Options</h2>
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;">
          <label>Season Length
            <select disabled>
              <option>82</option>
              <option>84</option>
            </select>
          </label>
          <label>Waivers
            <select disabled>
              <option>On</option>
              <option>Off</option>
            </select>
          </label>
          <label>Cap Tracking
            <select disabled>
              <option>On</option>
              <option>Off</option>
            </select>
          </label>
        </div>
      </div>

      <div class="links" style="margin-top:12px;">
        <a class="btn ghost" href="/admin/index.php">Back to Admin</a>
      </div>
    </div>
  </div>
</main>

<script src="/../assets/js/admin-pages.js"></script>
<script>
  window.UHA = window.UHA || {};
  UHA.adminPages && UHA.adminPages.initSettings?.(document.getElementById('settingsTogglesApp'));
</script>
