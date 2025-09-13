<?php

require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Trade Proposals';

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
<div class="canvas proposals-container" id="tradeProposalsApp">
    <div class="card proposals-card">
      <h1>Trade Proposals</h1>
      <p>Review submitted trades. (Scaffold only — no accept/void yet)</p>

      <div class="actions" style="margin:10px 0;">
        <a href="#" class="btn" aria-disabled="true">New Proposal</a>
      </div>

      <table class="uha-table">
        <colgroup>
          <col style="width: 44px">
          <col>
          <col>
          <col style="width: 120px">
          <col style="width: 140px">
          <col style="width: 120px">
        </colgroup>
        <thead>
          <tr>
            <th>ID</th>
            <th>From</th>
            <th>To</th>
            <th>Status</th>
            <th>Submitted</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <!-- Placeholder rows -->
          <tr>
            <td>1027</td>
            <td>EDM</td>
            <td>MTL</td>
            <td>Pending</td>
            <td>—</td>
            <td>
              <a href="#" class="btn" aria-disabled="true">View</a>
            </td>
          </tr>
          <tr>
            <td>1026</td>
            <td>NYR</td>
            <td>WSH</td>
            <td>Pending</td>
            <td>—</td>
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

<script url_root('assets/js/admin-pages.js') ></script>
<script>
  window.UHA = window.UHA || {};
  UHA.adminPages && UHA.adminPages.initProposals?.(document.getElementById('tradeProposalsApp'));
</script>