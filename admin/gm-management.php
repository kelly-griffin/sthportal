<?php

require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'GM Management';

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
  <div class="canvas gm-container" id="gmManagementApp">
    <div class="card gm-card">
      <h1>GM Management</h1>
      <p>Review and manage General Managers. (Read-only scaffold)</p>

      <div class="actions" style="margin:10px 0;">
        <a href="#" class="btn" aria-disabled="true">Add GM</a>
      </div>

      <table class="uha-table">
        <colgroup>
          <col style="width: 36px">
          <col>
          <col style="width: 160px">
          <col style="width: 120px">
          <col style="width: 120px">
          <col style="width: 120px">
        </colgroup>
        <thead>
          <tr>
            <th>#</th>
            <th>GM / Team</th>
            <th>Email</th>
            <th>Last Activity</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <!-- Placeholder rows -->
          <tr>
            <td class="rank">1</td>
            <td class="team">
              <img class="crest" src="/assets/img/logos/placeholder.svg" alt="" />
              <span class="t">Jane Smith — TOR</span>
            </td>
            <td>jane@example.com</td>
            <td>—</td>
            <td>Active</td>
            <td>
              <a href="#" class="btn" aria-disabled="true">View</a>
            </td>
          </tr>
          <tr>
            <td class="rank">2</td>
            <td class="team">
              <img class="crest" src="/assets/img/logos/placeholder.svg" alt="" />
              <span class="t">Alex Kim — CGY</span>
            </td>
            <td>alex@example.com</td>
            <td>—</td>
            <td>Invited</td>
            <td>
              <a href="#" class="btn" aria-disabled="true">View</a>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="links" style="margin-top:12px;">
        <a class="btn ghost" href="/admin/index.php">Back to Admin</a>
      </div>
    </div>
  </div>
</main>

<script src="/../assets/js/admin-pages.js"></script>
<script>
  window.UHA = window.UHA || {};
  UHA.adminPages && UHA.adminPages.initGM?.(document.getElementById('gmManagementApp'));
</script>
